<?php
namespace App\Services;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Program;
use App\Models\ClassSession;
use App\Models\Schedule;
use App\Models\Installment;
use App\Models\Tutor;
use App\Models\Classroom;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use App\Enums\PaymentStatus;
use App\Enums\AccountCode;
use App\Enums\ClassType;

class EnrollmentService
{
    public function __construct(protected AccountingService $accountingService) {}

    public function enroll(array $data): Enrollment
    {
        $program   = Program::findOrFail($data['program_id']);
        $classType = ClassType::from($program->type);

        foreach ($data['schedules'] as $schedule) {
            $this->validateRoomOccupancy(
                $schedule['classroom_id'],
                $schedule['day'],
                $schedule['time_block']
            );
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
                if (empty($data['class_session_id'])) {
                    throw new DomainException('Class session harus dipilih untuk program group/semi-private.');
                }

                $classSession = ClassSession::with('program')
                    ->where('id', $data['class_session_id'])
                    ->where('program_id', $program->id)
                    ->firstOrFail();

                $currentCount = Enrollment::where('class_session_id', $classSession->id)
                    ->whereIn('status', ['active', 'waitlist'])
                    ->count();

                $newCount         = $currentCount + 1;
                $enrollmentStatus = $newCount >= $program->min_quota ? 'active' : 'waitlist';

                if ($enrollmentStatus === 'active') {
                    Enrollment::where('class_session_id', $classSession->id)
                        ->where('status', 'waitlist')
                        ->update(['status' => 'active']);

                    if ($classSession->status !== 'active') {
                        $classSession->update(['status' => 'active']);
                    }
                }
            }

            $enrollment = Enrollment::create([
                'student_id'         => $data['student_id'],
                'program_id'         => $data['program_id'],
                'class_session_id'   => $classSession->id,
                'enrollment_date'    => $data['enrollment_date'],
                'expiry_date'        => $data['expiry_date'],
                'payment_method'     => $data['payment_method'],
                'total_amount'       => $data['total_amount'] ?? $program->price,
                'payment_status'     => PaymentStatus::PENDING->value,
                'status'             => $enrollmentStatus,
                'remaining_meetings' => $data['remaining_meetings'] ?? $program->total_meetings,
            ]);

            foreach ($data['schedules'] as $s) {
                $exists = Schedule::where('class_session_id', $classSession->id)
                    ->where('day', $s['day'])
                    ->where('time_block', $s['time_block'])
                    ->exists();

                if (!$exists) {
                    Schedule::create([
                        'enrollment_id'    => $enrollment->id,
                        'class_session_id' => $classSession->id,
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
                    ]);
                }
            }

            if (isset($data['tutor_ids'])) {
                $enrollment->tutors()->attach($data['tutor_ids'], ['status' => 'pending']);
            }

            $paymentAmount = 0;
            if ($data['payment_method'] === 'full upfront') {
                $paymentAmount = $program->price;
            } elseif ($data['payment_method'] === 'installment') {
                $paymentAmount = collect($data['installments'])->first()['amount'];
            }

            if ($paymentAmount > 0) {
                $this->accountingService->createJournal(
                    $data['enrollment_date'],
                    "Student Payment - Enrollment #{$enrollment->id}",
                    "PAYMENT-ENROLL-{$enrollment->id}",
                    [
                        ['account_code' => AccountCode::CASH_BANK->value,        'debit' => $paymentAmount, 'credit' => 0],
                        ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0,              'credit' => $paymentAmount],
                    ],
                    'payment',
                    $program->id
                );
            }

            return $enrollment;
        });
    }

    protected function validateRoomOccupancy($classroomId, $day, $timeBlock)
    {
        $classroom = Classroom::findOrFail($classroomId);

        $count = Schedule::where('classroom_id', $classroomId)
            ->where('day', $day)
            ->where('time_block', $timeBlock)
            ->count();

        if ($count >= 1) {
            throw new DomainException("Ruangan {$classroom->name} sudah terisi pada {$day} {$timeBlock}.");
        }
    }
}
