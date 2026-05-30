<?php
namespace App\Services;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Program;
use App\Models\ClassSession;
use App\Models\Schedule;
use App\Models\Installment;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use App\Models\Classroom;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use App\Enums\PaymentStatus;
use App\Enums\AccountCode;
use App\Enums\ClassType;

class EnrollmentService
{
    public function __construct(protected AccountingService $accountingService) {}

    public function enroll(array $data): array
    {
        $program   = Program::findOrFail($data['program_id']);
        $classType = ClassType::from($program->type);

        $roomNotes = [];
foreach ($data['schedules'] ?? [] as $schedule) {
    $note = $this->validateRoomOccupancy(
        $schedule['classroom_id'],
        $schedule['day'],
        $schedule['time_block'],
        $classType->value
    );
    if ($note) $roomNotes[] = $note;
}
        if (!empty($data['schedules']) && !empty($data['existing_student_id'])) {
            foreach ($data['schedules'] as $schedule) {
        if (empty($schedule['day']) || empty($schedule['time_block'])) continue;

        $conflict = Schedule::whereHas('enrollment', function ($q) use ($data) {
            $q->where('student_id', $data['existing_student_id'])
              ->whereIn('status', ['active', 'waitlist']);
        })
        ->where('day', $schedule['day'])
        ->where('time_block', $schedule['time_block'])
        ->exists();

        if ($conflict) {
            throw new DomainException("Student sudah memiliki sesi di {$schedule['day']} {$schedule['time_block']}.");
        }
    }
}
        return DB::transaction(function () use ($data, $program, $classType) {

            // Existing atau baru
            if (!empty($data['existing_student_id'])) {
                $student = Student::findOrFail($data['existing_student_id']);
                $user    = $student->user;
            } else {
                $user = \App\Models\User::create([
                    'name'     => $data['new_student']['name'],
                    'email'    => $data['new_student']['email'],
                    'phone'    => $data['new_student']['phone'] ?? null,
                    'password' => bcrypt('password123'),
                    'role'     => 'student',
                ]);
                $student = Student::create(['user_id' => $user->id]);
            }
            $data['student_id'] = $student->id;

            if ($classType === ClassType::PRIVATE) {
                $firstName      = explode(' ', trim($user->name))[0];
                $tutorFirstName = null;
                if (!empty($data['tutor_ids'])) {
                    $tutor          = Tutor::with('user')->find($data['tutor_ids'][0]);
                    $tutorFirstName = $tutor ? explode(' ', trim($tutor->user->name))[0] : null;
                }

                $sessionName = $tutorFirstName
                    ? "{$tutorFirstName}_{$firstName}"
                    : "Private_{$firstName}";

                $classSession = ClassSession::create([
                    'name'       => $sessionName,
                    'program_id' => $program->id,
                    'class_type' => $classType->value,
                    'status'     => 'active',
                ]);
                $enrollmentStatus = 'active';

            } else {
    if (!empty($data['class_session_id'])) {
        $classSession = ClassSession::with('program')
            ->where('id', $data['class_session_id'])
            ->where('program_id', $program->id)
            ->firstOrFail();

        $currentCount = Enrollment::where('class_session_id', $classSession->id)
            ->whereIn('status', ['active', 'waitlist'])
            ->lockForUpdate()
            ->count();

        $newCount         = $currentCount + 1;
        $quotaMet = $newCount >= $program->min_quota;
$hasTutor = $classSession->tutors()->wherePivot('status', 'confirmed')->exists();
$enrollmentStatus = ($quotaMet && $hasTutor) ? 'active' : 'waitlist';

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
        $classSession     = null;
        $enrollmentStatus = 'waitlist';
    }
}

            $enrollment = Enrollment::create([
                'student_id'         => $data['student_id'],
                'program_id'         => $data['program_id'],
                'class_session_id'   => $classSession?->id,
                'enrollment_date'    => $data['enrollment_date'],
                'expiry_date'        => $data['expiry_date'],
                'payment_method'     => $data['payment_method'],
                'payment_channel'    => $data['payment_channel'],
                'total_amount'       => $data['total_amount'] ?? $program->price,
                'payment_status'     => $data['payment_method'] === 'full upfront' ? PaymentStatus::FULL->value : PaymentStatus::PARTIAL->value,
                'status'             => $enrollmentStatus,
                'remaining_meetings' => ($data['remaining_meetings'] !== null && $data['remaining_meetings'] !== '') ? (int) $data['remaining_meetings'] : $program->total_meetings,
            ]);

            foreach ($data['schedules'] ?? [] as $s) {
                $exists = $classSession ? Schedule::where('class_session_id', $classSession->id)
                    ->where('day', $s['day'])
                    ->where('time_block', $s['time_block'])
                    ->exists() : false;

                if (!$exists) {
                    Schedule::create([
                        'enrollment_id'    => $enrollment->id,
                        'class_session_id' => $classSession?->id,
                        'classroom_id'     => $s['classroom_id'],
                        'day'              => $s['day'],
                        'time_block'       => $s['time_block'],
                    ]);
                }
            }

            if ($data['payment_method'] === 'installment') {
                foreach ($data['installments'] as $inst) {
                    Installment::create([
                        'enrollment_id' => $enrollment->id,
                        'amount'        => $inst['amount'],
                        'due_date'      => $inst['due_date'],
                        'payment_channel' => $inst['payment_channel'] ?? $data['payment_channel'],
                    ]);
                }
            }

           if (isset($data['tutor_ids'])) {
    if ($classType === ClassType::PRIVATE) {
        $enrollment->tutors()->attach($data['tutor_ids'], ['status' => 'pending']);
    }
    if ($classSession) {
        $classSession->tutors()->syncWithoutDetaching(
            collect($data['tutor_ids'])->mapWithKeys(fn($id) => [$id => ['status' => 'pending']])->all()
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
                $paymentAmount = collect($data['installments'])->first()['amount'];
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
                    $program->id
                );
            }

            return [$enrollment, $roomNotes];
        });
    }

    protected function validateRoomOccupancy($classroomId, $day, $timeBlock, string $incomingClassType): ?string
{
    $classroom = Classroom::findOrFail($classroomId);

    $occupyingSchedule = Schedule::with('classSession.program')
        ->where('classroom_id', $classroomId)
        ->where('day', $day)
        ->where('time_block', $timeBlock)
        ->first();

    if (!$occupyingSchedule) return null;

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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->payment_method === 'installment') {
                $installments = $this->installments ?? [];
                foreach ($installments as $i => $inst) {
                    if (empty($inst['amount'])) {
                        $validator->errors()->add("installments.{$i}.amount", 'Jumlah cicilan wajib diisi.');
                    }
                if (empty($inst['due_date'])) {
                        $validator->errors()->add("installments.{$i}.due_date", 'Jatuh tempo wajib diisi.');
                    }
                }
            }
        });
    }
}
