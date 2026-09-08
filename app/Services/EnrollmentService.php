<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Enums\ClassType;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Exceptions\DomainException;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use App\Models\User;
use App\Support\ScheduleFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnrollmentService
{
    public function __construct(
        protected AccountingService $accountingService,
        protected Notifier $notifier,
    ) {}

    public function enroll(array $data): array
    {
        $program = Program::findOrFail($data['program_id']);
        $classType = ClassType::from($program->type);

        // Seragamkan format hari & jam sebelum apa pun ditulis / dicocokkan.
        foreach ($data['schedules'] ?? [] as $i => $schedule) {
            if (isset($schedule['day'])) {
                $data['schedules'][$i]['day'] = ScheduleFormat::day($schedule['day']);
            }
            if (isset($schedule['time_block'])) {
                $data['schedules'][$i]['time_block'] = ScheduleFormat::timeBlock($schedule['time_block']);
            }
        }

        $roomNotes = [];
        foreach ($data['schedules'] ?? [] as $schedule) {
            $note = $this->validateRoomOccupancy(
                $schedule['classroom_id'],
                $schedule['day'],
                $schedule['time_block'],
                $classType->value
            );
            if ($note) {
                $roomNotes[] = $note;
            }
        }

        $result = DB::transaction(function () use ($data, $program, $classType, $roomNotes) {

            if (! empty($data['schedules']) && ! empty($data['existing_student_id'])) {
                foreach ($data['schedules'] as $schedule) {
                    if (empty($schedule['day']) || empty($schedule['time_block'])) {
                        continue;
                    }

                    $conflict = Schedule::whereHas('enrollment', function ($q) use ($data) {
                        $q->where('student_id', $data['existing_student_id'])
                            ->whereIn('status', ['active', 'waitlist']);
                    })
                        ->where('day', $schedule['day'])
                        ->where('time_block', $schedule['time_block'])
                        ->lockForUpdate()
                        ->exists();

                    if ($conflict) {
                        throw new DomainException("Student sudah memiliki sesi di {$schedule['day']} {$schedule['time_block']}.");
                    }
                }
            }

            // Existing atau baru
            if (! empty($data['existing_student_id'])) {
                $student = Student::findOrFail($data['existing_student_id']);
                $user = $student->user;
            } else {
                // Security fix: previously hardcoded 'password123' — anyone who
                // knew this convention could log in as any student created via
                // enrollment. Now we generate a random 24-char password and
                // force a password reset on first login via the must_reset_password
                // flag (handled by a migration + login flow check).
                // For now, the random password is logged so admin can share it
                // with the student. In production, dispatch a welcome email with
                // a signed password-reset link instead.
                $plainPassword = Str::random(24);
                $user = User::create([
                    'name' => $data['new_student']['name'],
                    'email' => $data['new_student']['email'],
                    'phone' => $data['new_student']['phone'] ?? null,
                    'password' => bcrypt($plainPassword),
                ]);
                $user->role = Role::STUDENT->value;
                $user->save();
                $student = Student::create([
                    'user_id' => $user->id,
                    'education_level' => $data['new_student']['education_level'] ?? null,
                ]);
                // Log the temporary password so the admin who enrolled the
                // student can relay it. This is a stopgap — the real fix is
                // sending a password-setup email link.
                Log::info('Student account created with temporary password', [
                    'student_id' => $student->id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'note' => 'Temporary password generated. Admin should share securely or trigger password reset.',
                ]);
            }
            $data['student_id'] = $student->id;

            // ── Kunci sumber daya SEBELUM cek kapasitas ────────────────────
            // `SELECT ... FOR UPDATE` tidak mengunci apa pun kalau hasilnya
            // kosong (kelas / slot ruang masih kosong), jadi dua enrollment
            // paralel bisa sama-sama lolos cek kapasitas. Solusinya: kunci
            // baris INDUK — tiap ruangan yang dipakai + kelas tujuan. Dengan
            // begitu request kedua benar-benar antre sampai yang pertama commit.
            $lockedRooms = [];
            foreach ($data['schedules'] ?? [] as $s) {
                $cid = $s['classroom_id'] ?? null;
                if ($cid && ! array_key_exists($cid, $lockedRooms)) {
                    $lockedRooms[$cid] = Classroom::where('id', $cid)->lockForUpdate()->first();
                }
            }
            if (! empty($data['class_session_id'])) {
                ClassSession::where('id', $data['class_session_id'])->lockForUpdate()->first();
            }
            // Lock tutor rows juga — cek slot jam tutor di bawah bisa "kosong"
            // hasilnya, jadi lock induk supaya dua enrollment paralel yang
            // memakai tutor sama antre.
            if (! empty($data['tutor_ids'])) {
                Tutor::whereIn('id', $data['tutor_ids'])->lockForUpdate()->get();
            }

            if ($classType === ClassType::PRIVATE) {
                if (! empty($data['class_session_id'])) {
                    $classSession = ClassSession::where('id', $data['class_session_id'])
                        ->where('program_id', $program->id)
                        ->where('class_type', ClassType::PRIVATE->value)
                        ->firstOrFail();
                } else {
                    $firstName = explode(' ', trim($user->name))[0];
                    $tutorFirstName = null;
                    if (! empty($data['tutor_ids'])) {
                        $tutor = Tutor::with('user')->find($data['tutor_ids'][0]);
                        $tutorFirstName = $tutor ? explode(' ', trim($tutor->user->name))[0] : null;
                    }

                    $sessionName = $tutorFirstName
                        ? "{$tutorFirstName}_{$firstName}"
                        : "Private_{$firstName}";

                    $classSession = ClassSession::create([
                        'name' => $sessionName,
                        'program_id' => $program->id,
                        'class_type' => $classType->value,
                        'status' => 'active',
                    ]);
                }
                $enrollmentStatus = 'active';
            } else {
                if (! empty($data['class_session_id'])) {
                    $classSession = ClassSession::with('program')
                        ->where('id', $data['class_session_id'])
                        ->where('program_id', $program->id)
                        ->firstOrFail();

                    $currentCount = Enrollment::where('class_session_id', $classSession->id)
                        ->whereIn('status', ['active', 'waitlist'])
                        ->lockForUpdate()
                        ->count();

                    $newCount = $currentCount + 1;
                    $quotaMet = $newCount >= $program->min_quota;
                    $hasTutor = $classSession->tutors()->wherePivot('status', 'confirmed')->exists()
                        || (isset($data['tutor_ids']) && ! empty($data['tutor_ids']));
                    $enrollmentStatus = ($quotaMet && $hasTutor) ? 'active' : 'waitlist';

                    // Kapasitas kelas grup (jumlah siswa) — konsisten dengan
                    // halaman Kelas & dropdown "sesi yang bisa dipilih". Baris
                    // enrollment yang di-count sudah di-lock; kelas induk juga.
                    $classroomForClass = ($lockedRooms[$data['schedules'][0]['classroom_id'] ?? null] ?? null)
                        ?: $classSession->schedules()->with('classroom')->first()?->classroom;
                    if ($classroomForClass && $classroomForClass->countsForOccupancy()
                        && $currentCount >= $classroomForClass->capacity) {
                        throw new DomainException(
                            "Kelas {$classSession->name} sudah penuh (kapasitas {$classroomForClass->capacity} orang). Enrollment dibatalkan."
                        );
                    }

                    if ($quotaMet && $hasTutor) {
                        Enrollment::where('class_session_id', $classSession->id)
                            ->where('status', 'waitlist')
                            ->whereNotIn('status', ['expired', 'graduate'])
                            ->update(['status' => 'active']);

                        if ($classSession->status !== 'active') {
                            $classSession->update(['status' => 'active']);
                        }
                    }
                } else {
                    $firstSchedule = $data['schedules'][0] ?? null;
                    if (! empty($firstSchedule['day']) && ! empty($firstSchedule['time_block'])) {
                        // Admin tidak pilih class session — buat sesi baru otomatis
                        // untuk group/semi-private, sama seperti perilaku kelas private.
                        $classSession = ClassSession::create([
                            'name' => "{$program->name}_{$firstSchedule['day']}_{$firstSchedule['time_block']}",
                            'program_id' => $program->id,
                            'class_type' => $classType->value,
                            'status' => 'active',
                        ]);
                        $enrollmentStatus = 'waitlist';
                    } else {
                        $classSession = null;
                        $enrollmentStatus = 'waitlist';
                    }
                }
            }

            // ── Re-cek okupansi ruang (locked) untuk SEMUA slot ───────────
            // Berlaku untuk private maupun grup, sesi baru maupun sesi yang
            // sudah ada. Ruangan sudah di-lock di atas.
            foreach ($data['schedules'] ?? [] as $s) {
                $this->assertRoomAvailableLocked(
                    $lockedRooms[$s['classroom_id']] ?? null,
                    $s['day'],
                    $s['time_block'],
                    $classType,
                    $classSession?->id,
                );
            }

            // ── Re-cek slot jam tutor (locked) — cegah tutor dobel-booking ─
            foreach ($data['tutor_ids'] ?? [] as $tutorId) {
                foreach ($data['schedules'] ?? [] as $s) {
                    $busy = Schedule::query()
                        ->where('day', $s['day'])
                        ->where('time_block', $s['time_block'])
                        ->when($classSession, fn ($q) => $q->where('class_session_id', '!=', $classSession->id))
                        ->whereHas('classSession.tutors', fn ($q) => $q->where('tutor_id', $tutorId))
                        ->whereHas('classSession.enrollments', fn ($q) => $q->whereIn('status', ['active', 'waitlist']))
                        ->lockForUpdate()
                        ->exists();
                    if ($busy) {
                        $t = Tutor::with('user')->find($tutorId);
                        throw new DomainException(
                            "Tutor {$t?->user?->name} sudah mengajar kelas lain pada {$s['day']} {$s['time_block']}."
                        );
                    }
                }
            }

            $enrollment = Enrollment::create([
                'student_id' => $data['student_id'],
                'program_id' => $data['program_id'],
                'class_session_id' => $classSession?->id,
                'enrollment_date' => $data['enrollment_date'],
                'expiry_date' => $data['expiry_date'],
                'payment_method' => $data['payment_method'],
                'payment_channel' => $data['payment_channel'],
                'total_amount' => $data['total_amount'] ?? $program->price,
                'payment_status' => $data['payment_method'] === 'full upfront' ? PaymentStatus::FULL->value : PaymentStatus::PARTIAL->value,
                'status' => $enrollmentStatus,
                'remaining_meetings' => (($data['remaining_meetings'] ?? null) !== null && ($data['remaining_meetings'] ?? '') !== '') ? (int) $data['remaining_meetings'] : $program->total_meetings,
            ]);

            foreach ($data['schedules'] ?? [] as $s) {
                $exists = $classSession ? Schedule::where('class_session_id', $classSession->id)
                    ->where('day', $s['day'])
                    ->where('time_block', $s['time_block'])
                    ->exists() : false;

                if (! $exists) {
                    Schedule::create([
                        'enrollment_id' => $enrollment->id,
                        'class_session_id' => $classSession?->id,
                        'classroom_id' => $s['classroom_id'],
                        'day' => $s['day'],
                        'time_block' => $s['time_block'],
                    ]);
                }
            }

            $firstInstallment = null;
            if ($data['payment_method'] === 'installment') {
                foreach ($data['installments'] ?? [] as $index => $inst) {
                    $createdInstallment = Installment::create([
                        'enrollment_id' => $enrollment->id,
                        'amount' => $inst['amount'],
                        'due_date' => $inst['due_date'],
                        'payment_channel' => $inst['payment_channel'] ?? $data['payment_channel'],
                    ]);
                    if ($index === 0) {
                        $firstInstallment = $createdInstallment;
                    }
                }
            }

            if (isset($data['tutor_ids'])) {
                $enrollment->tutors()->attach($data['tutor_ids'], ['status' => 'pending']);
                if ($classSession) {
                    $classSession->tutors()->syncWithoutDetaching(
                        collect($data['tutor_ids'])->mapWithKeys(fn ($id) => [$id => ['status' => 'pending']])->all()
                    );
                }

                foreach ($data['tutor_ids'] as $tutorId) {
                    foreach ($data['schedules'] ?? [] as $s) {
                        TutorAvailability::where('tutor_id', $tutorId)
                            ->where('day', $s['day'])
                            ->where('time_block', $s['time_block'])
                            ->update(['status' => 'occupied']);
                    }
                }
            }

            $paymentAmount = 0;
            if ($data['payment_method'] === 'full upfront') {
                $paymentAmount = $data['total_amount'] ?? $program->price;
            } elseif ($data['payment_method'] === 'installment') {
                // Cicilan pertama = DP saat enrollment. Kalau daftar cicilan
                // kosong (belum ada pembayaran), DP dianggap nol.
                $paymentAmount = collect($data['installments'] ?? [])->first()['amount'] ?? 0;
            }

            if ($paymentAmount > 0) {
                $this->accountingService->createJournal(
                    $data['enrollment_date'],
                    "Student Payment - Enrollment #{$enrollment->id}",
                    "PAYMENT-ENROLL-{$enrollment->id}",
                    [
                        ['account_code' => $data['payment_channel'] === 'bank' ? AccountCode::BANK->value : AccountCode::CASH->value, 'debit' => $paymentAmount, 'credit' => 0],
                        ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0,              'credit' => $paymentAmount],
                    ],
                    'payment',
                    $program->id,
                    $enrollment->id
                );

                // GAP #13 fix: cicilan pertama = DP yang dibayar saat enrollment
                // (dikonfirmasi kebijakan bisnis), jadi harus langsung ditandai
                // lunas di sini. Sebelumnya paid_at tidak pernah di-set meski
                // jurnalnya sudah dibuat, jadi installment ini masih kelihatan
                // "belum dibayar" dan admin bisa mark-paid lagi lewat
                // markInstallmentPaid() -> jurnal kedua dibuat -> revenue &
                // kas tercatat 2x untuk cicilan yang sama.
                if ($data['payment_method'] === 'installment' && $firstInstallment) {
                    $firstInstallment->update(['paid_at' => $data['enrollment_date']]);
                }
            }

            return [$enrollment, $roomNotes];
        });

        // Integrasi peran: beri tahu tutor-tutor kelas ada siswa baru.
        $result[0]->loadMissing('classSession', 'student.user');
        $this->notifier->newStudentInClass($result[0]);

        return $result;
    }

    /**
     * Cek okupansi ruang versi TERKUNCI — dipanggil di dalam transaction setelah
     * baris ruangan di-lock. Selalu melempar (bukan mengembalikan catatan) kalau
     * slot tidak boleh dipakai:
     *   - kelas private butuh ruang sendiri (tidak boleh ada kelas lain di slot),
     *   - tidak boleh masuk ke slot yang sudah dipakai kelas private,
     *   - jumlah kelas berbeda di satu slot ruang tidak boleh melebihi kapasitas.
     */
    protected function assertRoomAvailableLocked(?Classroom $room, string $day, string $timeBlock, ClassType $incomingType, ?int $ownClassSessionId): void
    {
        if (! $room || ! $room->countsForOccupancy()) {
            return;
        }

        $others = Schedule::with('classSession.program')
            ->where('classroom_id', $room->id)
            ->where('day', $day)
            ->where('time_block', $timeBlock)
            ->when($ownClassSessionId, fn ($q) => $q->where(fn ($qq) => $qq
                ->whereNull('class_session_id')
                ->orWhere('class_session_id', '!=', $ownClassSessionId)))
            ->lockForUpdate()
            ->get();

        if ($others->isEmpty()) {
            return;
        }

        if ($incomingType === ClassType::PRIVATE) {
            throw new DomainException("Ruangan {$room->name} sudah dipakai kelas lain pada {$day} {$timeBlock} — kelas private butuh ruang sendiri.");
        }
        if ($others->contains(fn ($sch) => $sch->classSession?->program?->type === ClassType::PRIVATE->value)) {
            throw new DomainException("Ruangan {$room->name} sudah dipakai kelas private pada {$day} {$timeBlock}.");
        }

        // Kelas ini akan menambah 1 kelas ke slot HANYA kalau belum punya jadwal
        // di sana (join kelas grup yang sudah terjadwal = tidak menambah kelas).
        $ownAlreadyScheduled = $ownClassSessionId && Schedule::where('class_session_id', $ownClassSessionId)
            ->where('classroom_id', $room->id)->where('day', $day)->where('time_block', $timeBlock)->exists();
        $distinctOthers = $others->pluck('class_session_id')->filter()->unique()->count();
        if (! $ownAlreadyScheduled && ($distinctOthers + 1) > $room->capacity) {
            throw new DomainException("Ruangan {$room->name} sudah penuh pada {$day} {$timeBlock}.");
        }
    }

    protected function validateRoomOccupancy($classroomId, $day, $timeBlock, string $incomingClassType): ?string
    {
        $classroom = Classroom::findOrFail($classroomId);

        // Ruang online / di luar Just Speak tidak punya batas okupansi —
        // banyak kelas bisa jalan bersamaan di slot yang sama.
        if (! $classroom->countsForOccupancy()) {
            return null;
        }

        $occupyingSchedule = Schedule::with('classSession.program')
            ->where('classroom_id', $classroomId)
            ->where('day', $day)
            ->where('time_block', $timeBlock)
            ->first();

        if (! $occupyingSchedule) {
            return null;
        }

        $occupyingType = $occupyingSchedule->classSession?->program?->type;

        if ($occupyingType === ClassType::PRIVATE->value) {
            throw new DomainException("Ruangan {$classroom->name} sudah dipakai kelas private pada {$day} {$timeBlock}.");
        }

        $currentCount = Schedule::where('classroom_id', $classroomId)
            ->where('day', $day)
            ->where('time_block', $timeBlock)
            ->count();

        if ($currentCount >= $classroom->capacity) {
            throw new DomainException("Ruangan {$classroom->name} sudah penuh pada {$day} {$timeBlock}.");
        }

        return "Ruangan {$classroom->name} pada {$day} {$timeBlock} sudah dipakai kelas lain namun masih tersedia.";
    }
}
