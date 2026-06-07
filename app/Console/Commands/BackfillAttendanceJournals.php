<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-attendance-journals')]
#[Description('Backfill missing revenue recognition and tutor fee journals for existing attendance records')]
class BackfillAttendanceJournals extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountingService = app(\App\Services\AccountingService::class);

        $attendances = \App\Models\Attendance::with([
            'students.program',
            'students.student.user',
            'tutors.user',
            'classSession.program',
        ])->get();

        $this->info("Processing {$attendances->count()} attendance records...");
        $revCreated = 0;
        $tutorCreated = 0;
        $skipped = 0;

        foreach ($attendances as $attendance) {
            foreach ($attendance->students as $enrollment) {
                $ref = "REV-REC-{$attendance->id}-{$enrollment->id}";
                if (\App\Models\Journal::where('reference', $ref)->exists()) { $skipped++; continue; }

                $revenueAmount = bcdiv((string) $enrollment->total_amount, (string) $enrollment->program->total_meetings, 2);

                try {
                    $accountingService->createJournal(
                        $attendance->date,
                        "Revenue Recognition: {$enrollment->student->user->name}, Session: {$attendance->id}",
                        $ref,
                        [
                            ['account_code' => \App\Enums\AccountCode::DEFERRED_REVENUE->value,     'debit' => $revenueAmount, 'credit' => 0],
                            ['account_code' => \App\Enums\AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $revenueAmount],
                        ],
                        'revenue_recognition',
                        $attendance->classSession->program_id ?? null
                    );
                    $revCreated++;
                } catch (\Exception $e) {
                    $this->warn("Skip REV-REC {$attendance->id}-{$enrollment->id}: {$e->getMessage()}");
                }
            }

            foreach ($attendance->tutors as $tutor) {
                $ref = "TUTOR-PAY-{$attendance->id}-{$tutor->id}";
                if (\App\Models\Journal::where('reference', $ref)->exists()) { $skipped++; continue; }

                $payable = $tutor->pivot->payable_amount ?? 0;
                if ($payable <= 0) continue;

                try {
                    $journal = $accountingService->createJournal(
                        $attendance->date,
                        "Tutor Fee: {$tutor->user->name}, Session: {$attendance->id}",
                        $ref,
                        [
                            ['account_code' => \App\Enums\AccountCode::EXPENSE_TUTOR_FEE->value, 'debit' => $payable, 'credit' => 0],
                            ['account_code' => \App\Enums\AccountCode::TUTOR_PAYABLE->value,     'debit' => 0, 'credit' => $payable],
                        ],
                        'tutor_accrual',
                        $attendance->classSession->program_id ?? null
                    );
                    \Illuminate\Support\Facades\DB::table('attendance_tutor')
                        ->where('attendance_id', $attendance->id)
                        ->where('tutor_id', $tutor->id)
                        ->update(['journal_id' => $journal->id]);
                    $tutorCreated++;
                } catch (\Exception $e) {
                    $this->warn("Skip TUTOR-PAY {$attendance->id}-{$tutor->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Done! Revenue journals created: {$revCreated}, Tutor fee journals: {$tutorCreated}, Skipped: {$skipped}");
        return 0;
    }
}
