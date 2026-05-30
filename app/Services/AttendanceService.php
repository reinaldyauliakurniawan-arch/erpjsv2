<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Enums\AccountCode;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DomainException;
use App\Models\RoomBooking;
use App\Models\TutorAvailability;

class AttendanceService
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function markAttendance(array $data): Attendance
    {
        $classSession = ClassSession::with('program')->findOrFail($data['class_session_id']);

        return DB::transaction(function () use ($data, $classSession) {
            $attendance = Attendance::where('class_session_id', $classSession->id)
                ->where('date', $data['date'])
                ->where('time_block', $data['time_block'])
                ->first();

            $isNew = !$attendance;

            if ($isNew) {
                $attendance = Attendance::create([
                    'class_session_id' => $classSession->id,
                    'date'             => $data['date'],
                    'time_block'       => $data['time_block'],
                    'classroom_id'     => $data['classroom_id'] ?? null,
                    'marked_by'        => $data['marked_by'],
                    'notes'            => $data['notes'] ?? null,
                ]);

                foreach ($data['students'] as $studentData) {
                    $enrollment = Enrollment::with(['program', 'student.user'])->findOrFail($studentData['enrollment_id']);
                    $isPresent  = (bool) ($studentData['is_present'] ?? true);

                    if ($enrollment->remaining_meetings <= 0) {
                        throw new DomainException("No remaining meetings for student: {$enrollment->student->user->name}");
                    }

                    $attendance->students()->attach($enrollment->id, [
                        'is_present' => $isPresent,
                        'notes'      => $studentData['notes'] ?? null,
                    ]);

                    $enrollment->decrement('remaining_meetings');
                    if ($enrollment->fresh()->remaining_meetings <= 0) {
                        $enrollment->update(['status' => 'graduate']);
                        RoomBooking::where('enrollment_id', $enrollment->id)
                            ->where('date', '>', $data['date'])
                            ->delete();
                        $tutorIds = $enrollment->tutors()->pluck('tutors.id');
                        foreach ($enrollment->schedules as $schedule) {
                            TutorAvailability::where('day', $schedule->day)
                                ->where('time_block', $schedule->time_block)
                                ->whereIn('tutor_id', $tutorIds)
                                ->update(['status' => 'available']);
                        }
                    }

                    $revenueAmount = bcdiv((string) $enrollment->total_amount, (string) $enrollment->program->total_meetings, 2);

                    $this->accountingService->createJournal(
                        $data['date'],
                        "Revenue Recognition: {$enrollment->student->user->name}, Session: {$attendance->id}",
                        "REV-REC-{$attendance->id}-{$enrollment->id}",
                        [
                            ['account_code' => AccountCode::DEFERRED_REVENUE->value,     'debit' => $revenueAmount, 'credit' => 0],
                            ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $revenueAmount],
                        ],
                        'revenue_recognition',
                        $classSession->program_id
                    );
                }
            }

            $tutor = Tutor::with('user')->where('user_id', $data['marked_by'])->first();

            if ($tutor) {
                $alreadyAttached = $attendance->tutors()->where('tutor_id', $tutor->id)->exists();
                if ($alreadyAttached) {
                    throw new DomainException("Tutor {$tutor->user->name} sudah submit attendance untuk sesi ini.");
                }

                $rate = TutorRate::where('tutor_id', $tutor->id)
                    ->where('program_id', $classSession->program_id)
                    ->first();

                if ($rate) {
                    $journal = $this->accountingService->createJournal(
                        $data['date'],
                        "Tutor Fee: {$tutor->user->name}, Session: {$attendance->id}",
                        "TUTOR-PAY-{$attendance->id}-{$tutor->id}",
                        [
                            ['account_code' => AccountCode::EXPENSE_TUTOR_FEE->value, 'debit' => $rate->rate, 'credit' => 0],
                            ['account_code' => AccountCode::TUTOR_PAYABLE->value,     'debit' => 0, 'credit' => $rate->rate],
                        ],
                        'tutor_accrual',
                        $classSession->program_id
                    );

                    $attendance->tutors()->attach($tutor->id, [
                        'payable_amount'     => $rate->rate,
                        'pending_rate'       => false,
                        'journal_id'         => $journal->id,
                        'is_replacement'     => (bool) ($data['is_replacement'] ?? false),
                        'replaced_tutor_id'  => $data['replaced_tutor_id'] ?? null,
                    ]);
                } else {
                    $attendance->tutors()->attach($tutor->id, [
                        'payable_amount'     => 0,
                        'pending_rate'       => true,
                        'journal_id'         => null,
                        'is_replacement'     => (bool) ($data['is_replacement'] ?? false),
                        'replaced_tutor_id'  => $data['replaced_tutor_id'] ?? null,
                    ]);
                }
            }

            return $attendance;
        });
    }

    public function reverseAttendance(Attendance $attendance): void
    {
        DB::transaction(function () use ($attendance) {
            $attendance->load([
                'students.program',
                'students.student.user',
                'tutors',
            ]);

            foreach ($attendance->students as $enrollment) {
                $refRevRec = "REV-REC-{$attendance->id}-{$enrollment->id}";
                $revenueAmount = bcdiv((string) $enrollment->total_amount, (string) $enrollment->program->total_meetings, 2);

                $this->accountingService->createJournal(
                    now()->toDateString(),
                    "REVERSE Revenue Recognition: {$enrollment->student->user->name}, Session: {$attendance->id}",
                    "REV-{$refRevRec}",
                    [
                        ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => $revenueAmount, 'credit' => 0],
                        ['account_code' => AccountCode::DEFERRED_REVENUE->value,     'debit' => 0, 'credit' => $revenueAmount],
                    ],
                    'reversal'
                );

                $enrollment->increment('remaining_meetings');
            }

            foreach ($attendance->tutors as $tutor) {
                $journalId = $tutor->pivot->journal_id;
                if (!$journalId) continue;

                if ($tutor->pivot->paid_at !== null) {
                    throw new DomainException("Tidak bisa reverse: tutor {$tutor->user->name} sudah dibayar.");
                }

                $payable = $tutor->pivot->payable_amount;
                if ($payable <= 0) continue;

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
            }

            $attendance->students()->detach();
            $attendance->tutors()->detach();
            $attendance->delete();
        });
    }
}
