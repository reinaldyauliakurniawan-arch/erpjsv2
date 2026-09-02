<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Exceptions\DomainException;
use App\Exceptions\IdempotencyException;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Journal;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use App\Models\TutorRate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    protected $accountingService;

    protected $revenueRecognitionService;

    public function __construct(AccountingService $accountingService, RevenueRecognitionService $revenueRecognitionService)
    {
        $this->accountingService = $accountingService;
        $this->revenueRecognitionService = $revenueRecognitionService;
    }

    public function markAttendance(array $data): Attendance
    {
        $classSession = ClassSession::with('program')->findOrFail($data['class_session_id']);

        try {
            return DB::transaction(function () use ($data, $classSession) {

                // Lock row untuk cegah race condition
                $attendance = Attendance::where('class_session_id', $classSession->id)
                    ->where('date', $data['date'])
                    ->where('time_block', $data['time_block'])
                    ->lockForUpdate()
                    ->first();

                $isNew = ! $attendance;

                if ($isNew) {
                    $attendance = Attendance::create([
                        'class_session_id' => $classSession->id,
                        'date' => $data['date'],
                        'time_block' => $data['time_block'],
                        'classroom_id' => $data['classroom_id'],
                        'marked_by' => $data['marked_by'],
                        'notes' => $data['notes'] ?? null,
                    ]);

                    // Eager load enrollments sekaligus untuk hindari N+1
                    $enrollments = Enrollment::with(['program', 'student.user', 'schedules', 'installments'])
                        ->whereIn('id', collect($data['students'])->pluck('enrollment_id'))
                        ->where('class_session_id', $classSession->id)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    $studentAttachData = [];
                    foreach ($data['students'] as $studentData) {
                        $enrollment = $enrollments->get($studentData['enrollment_id']);

                        if (! $enrollment) {
                            continue;
                        }

                        if ($enrollment->remaining_meetings <= 0) {
                            throw new DomainException("No remaining meetings for student: {$enrollment->student->user->name}");
                        }

                        $studentAttachData[$enrollment->id] = [
                            'is_present' => (bool) ($studentData['is_present'] ?? true),
                            'notes' => $studentData['notes'] ?? null,
                        ];
                    }

                    // GAP #14 fix: snapshot jumlah meeting yang sudah recognized
                    // SEBELUM attach() di bawah ini menambah baris attendance_student
                    // untuk meeting yang sedang diproses. Kalau diambil setelah
                    // attach(), meetingsRecognizedSoFar() akan off-by-one (ikut
                    // menghitung meeting yang sedang diproses ini sendiri).
                    $meetingsRecognizedBeforeThis = [];
                    foreach (array_keys($studentAttachData) as $enrollmentId) {
                        $meetingsRecognizedBeforeThis[$enrollmentId] = $this->revenueRecognitionService
                            ->meetingsRecognizedSoFar($enrollments->get($enrollmentId));
                    }

                    // Batch attach semua siswa sekaligus
                    $attendance->students()->attach($studentAttachData);

                    // Proses remaining_meetings dan revenue recognition
                    foreach ($enrollments as $enrollment) {
                        $enrollment->decrement('remaining_meetings');

                        $fresh = $enrollment->fresh();
                        if ($fresh->remaining_meetings <= 0) {
                            $unpaidInstallments = $enrollment->installments()->whereNull('paid_at')->count();
                            $newStatus = $unpaidInstallments > 0 ? 'expired' : 'graduate';
                            $enrollment->update(['status' => $newStatus]);
                            RoomBooking::where('enrollment_id', $enrollment->id)
                                ->where('date', '>', $data['date'])
                                ->delete();
                            $tutorIds = $enrollment->tutors()->pluck('tutors.id');
                            foreach ($enrollment->schedules as $schedule) {
                                foreach ($tutorIds as $tutorId) {
                                    $stillOccupied = Schedule::where('day', $schedule->day)
                                        ->where('time_block', $schedule->time_block)
                                        ->where('enrollment_id', '!=', $enrollment->id)
                                        ->whereHas('enrollment', fn ($q) => $q->whereIn('status', ['active', 'waitlist']))
                                        ->whereHas('enrollment.tutors', fn ($q) => $q->where('tutor_id', $tutorId))
                                        ->exists();

                                    if (! $stillOccupied) {
                                        TutorAvailability::where('day', $schedule->day)
                                            ->where('time_block', $schedule->time_block)
                                            ->where('tutor_id', $tutorId)
                                            ->update(['status' => 'available']);
                                    }
                                }
                            }
                        }

                        // GAP #14 fix: revenue per meeting = total_amount kontrak /
                        // total_meetings (FIXED), bukan paidAmount saat itu yang
                        // fluktuatif mengikuti progress cicilan. Porsi yang belum
                        // ada saldo Deferred Revenue-nya (siswa belum/kurang bayar
                        // sampai meeting ini) dicatat sebagai Piutang, bukan
                        // memaksa Deferred Revenue jadi minus.
                        $split = $this->revenueRecognitionService->splitForNextMeeting(
                            $enrollment,
                            $meetingsRecognizedBeforeThis[$enrollment->id] ?? 0
                        );

                        $journalItems = [];
                        if (bccomp($split['fromDeferredRevenue'], '0', 2) > 0) {
                            $journalItems[] = ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => $split['fromDeferredRevenue'], 'credit' => 0];
                        }
                        if (bccomp($split['fromReceivable'], '0', 2) > 0) {
                            $journalItems[] = ['account_code' => AccountCode::ACCOUNTS_RECEIVABLE->value, 'debit' => $split['fromReceivable'], 'credit' => 0];
                        }
                        $journalItems[] = ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $split['revenueThisMeeting']];

                        try {
                            $this->accountingService->createJournal(
                                $data['date'],
                                "Revenue Recognition: {$enrollment->student->user->name}, Session: {$attendance->id}",
                                "REV-REC-{$attendance->id}-{$enrollment->id}",
                                $journalItems,
                                'revenue_recognition',
                                $classSession->program_id,
                                $enrollment->id
                            );
                        } catch (IdempotencyException $e) {
                            // Journal sudah ada, skip
                        }
                    }
                }

                if ($isNew) {
                    // Attach primary tutor
                    $tutor = Tutor::with('user')->where('user_id', $data['marked_by'])->firstOrFail();
                    $this->attachTutor($attendance, $tutor, $classSession, $data['date'], [
                        'is_replacement' => (bool) ($data['is_replacement'] ?? false),
                        'is_team_teaching' => (bool) ($data['is_team_teaching'] ?? false),
                        'replaced_tutor_id' => $data['replaced_tutor_id'] ?? null,
                    ]);

                    // Attach co-tutors jika team teaching
                    if (! empty($data['co_tutor_ids']) && is_array($data['co_tutor_ids'])) {
                        $coTutors = Tutor::with('user')
                            ->whereIn('id', $data['co_tutor_ids'])
                            ->get();

                        foreach ($coTutors as $coTutor) {
                            $this->attachTutor($attendance, $coTutor, $classSession, $data['date'], [
                                'is_replacement' => false,
                                'is_team_teaching' => true,
                                'replaced_tutor_id' => null,
                            ]);
                        }
                    }
                }

                return $attendance;
            });
        } catch (QueryException $e) {
            // Race-condition fallback: if two concurrent requests both passed
            // the lockForUpdate()->first() check (because SELECT FOR UPDATE
            // locks nothing when no row matches) and both tried to INSERT,
            // the unique constraint on (class_session_id, date, time_block)
            // rejects the second. Treat that as "already marked" — re-load
            // the existing attendance and return it.
            if ($e->errorInfo[1] === 19 || str_contains($e->getMessage(), 'UniqueViolation')
                || str_contains($e->getMessage(), 'unique constraint')) {
                return Attendance::where('class_session_id', $classSession->id)
                    ->where('date', $data['date'])
                    ->where('time_block', $data['time_block'])
                    ->firstOrFail();
            }
            throw $e;
        }
    }

    protected function attachTutor(
        Attendance $attendance,
        Tutor $tutor,
        ClassSession $classSession,
        string $date,
        array $pivotExtra = []
    ): void {
        $alreadyAttached = $attendance->tutors()
            ->where('tutor_id', $tutor->id)
            ->exists();

        if ($alreadyAttached) {
            throw new DomainException("Tutor {$tutor->user->name} sudah tercatat di sesi ini.");
        }

        $rate = TutorRate::where('tutor_id', $tutor->id)
            ->where('program_id', $classSession->program_id)
            ->first();

        if ($rate) {
            try {
                $journal = $this->accountingService->createJournal(
                    $date,
                    "Tutor Fee: {$tutor->user->name}, Session: {$attendance->id}",
                    "TUTOR-PAY-{$attendance->id}-{$tutor->id}",
                    [
                        ['account_code' => AccountCode::EXPENSE_TUTOR_FEE->value, 'debit' => $rate->rate, 'credit' => 0],
                        ['account_code' => AccountCode::TUTOR_PAYABLE->value,     'debit' => 0, 'credit' => $rate->rate],
                    ],
                    'tutor_accrual',
                    $classSession->program_id
                );
            } catch (IdempotencyException $e) {
                $journal = Journal::where('reference', "TUTOR-PAY-{$attendance->id}-{$tutor->id}")->first();
            }

            $attendance->tutors()->attach($tutor->id, array_merge([
                'payable_amount' => $rate->rate,
                'pending_rate' => false,
                'journal_id' => $journal->id,
            ], $pivotExtra));
        } else {
            $attendance->tutors()->attach($tutor->id, array_merge([
                'payable_amount' => 0,
                'pending_rate' => true,
                'journal_id' => null,
            ], $pivotExtra));
        }
    }

    public function reverseAttendance(Attendance $attendance): void
    {
        DB::transaction(function () use ($attendance) {
            // Lock the attendance_tutor rows first so a concurrent assignRate()
            // (which also runs inside its own DB::transaction) can't slip in
            // new journal_id/payable_amount data between our read and our
            // reversal writes below.
            DB::table('attendance_tutor')
                ->where('attendance_id', $attendance->id)
                ->lockForUpdate()
                ->get();

            $attendance->load([
                'students.program',
                'students.student.user',
                'tutors',
            ]);

            // Cek semua tutor, tidak boleh ada yang sudah dibayar
            foreach ($attendance->tutors as $tutor) {
                if ($tutor->pivot->paid_at !== null) {
                    throw new DomainException("Tidak bisa reverse: tutor {$tutor->user->name} sudah dibayar.");
                }
            }

            // Reverse revenue recognition semua siswa
            foreach ($attendance->students as $enrollment) {
                $refRevRec = "REV-REC-{$attendance->id}-{$enrollment->id}";
                $originalJournal = Journal::where('reference', $refRevRec)->first();
                $revenueAmount = $originalJournal
                    ? (string) $originalJournal->total_amount
                    : bcdiv((string) $enrollment->total_amount, (string) $enrollment->program->total_meetings, 2);

                $this->accountingService->createJournal(
                    now()->toDateString(),
                    "REVERSE Revenue Recognition: {$enrollment->student->user->name}, Session: {$attendance->id}",
                    "REV-{$refRevRec}",
                    [
                        ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => $revenueAmount, 'credit' => 0],
                        ['account_code' => AccountCode::DEFERRED_REVENUE->value,     'debit' => 0, 'credit' => $revenueAmount],
                    ],
                    'reversal',
                    $enrollment->program_id,
                    $enrollment->id
                );

                $enrollment->increment('remaining_meetings');

                $freshEnrollment = $enrollment->fresh();
                if (in_array($freshEnrollment->status, ['graduate', 'expired'])) {
                    $freshEnrollment->update(['status' => 'active']);
                }
            }

            // Reverse tutor fee semua tutor (primary + co-tutor)
            foreach ($attendance->tutors as $tutor) {
                $journalId = $tutor->pivot->journal_id;
                if (! $journalId) {
                    continue;
                }

                $payable = $tutor->pivot->payable_amount;
                if ($payable <= 0) {
                    continue;
                }

                try {
                    $this->accountingService->createJournal(
                        now()->toDateString(),
                        "REVERSE Tutor Fee: {$tutor->user->name}, Session: {$attendance->id}",
                        "REV-TUTOR-PAY-{$attendance->id}-{$tutor->id}",
                        [
                            ['account_code' => AccountCode::TUTOR_PAYABLE->value,     'debit' => $payable, 'credit' => 0],
                            ['account_code' => AccountCode::EXPENSE_TUTOR_FEE->value, 'debit' => 0, 'credit' => $payable],
                        ],
                        'reversal'
                    );
                } catch (IdempotencyException $e) {
                    // Journal already exists, skip
                }
            }

            $attendance->students()->detach();
            $attendance->tutors()->detach();
            $attendance->delete();
        });
    }
}
