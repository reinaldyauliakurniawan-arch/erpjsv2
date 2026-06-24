<?php

namespace Database\Seeders\Traits;
use IlluminateSupportFacadesDB;
use CarbonCarbon;


trait HasFinancialSeeders
{
    private function seedInstallments(array $enrollments): void
    {
        $now = Carbon::now();

        foreach ($enrollments as $enroll) {
            if ($enroll['pay_method'] !== 'installment') continue;

            $total   = $enroll['total_amount'];
            $terms   = rand(2, 4);
            $perTerm = round($total / $terms, -3);
            $base    = $enroll['enroll_date']->copy();
            $cashCode = $enroll['pay_channel'] === 'bank' ? self::ACC_BANK : self::ACC_CASH;

            $paidCount = 0;
            for ($t = 0; $t < $terms; $t++) {
                $due    = $base->copy()->addMonths($t);
                // Cicilan yang jatuh tempo di masa lalu → 80% sudah dibayar
                $isPaid = $due->lt($now) && rand(0, 4) > 0;

                $instId = DB::table('installments')->insertGetId([
                    'enrollment_id'   => $enroll['id'],
                    'amount'          => $perTerm,
                    'payment_channel' => $enroll['pay_channel'],
                    'due_date'        => $due->toDateString(),
                    'paid_at'         => $isPaid ? $due->copy()->addDays(rand(0,5))->toDateString() : null,
                    'created_at'      => $now,'updated_at'=>$now,
                ]);

                if ($isPaid) {
                    $paidCount++;
                    // Journal per installment yang sudah dibayar
                    // (sesuai markInstallmentPaid: cash/bank debit, deferred_rev credit)
                    $this->journal(
                        $due->toDateString(),
                        "Installment Payment - Enrollment #{$enroll['id']}",
                        "INSTALLMENT-{$instId}",
                        [
                            ['code'=>$cashCode,             'debit'=>$perTerm,'credit'=>0],
                            ['code'=>self::ACC_DEFERRED_REV,'debit'=>0,'credit'=>$perTerm],
                        ],
                        'payment', $enroll['program_id']
                    );
                }
            }

            // Update payment_status berdasarkan cicilan yang sudah dibayar
            $unpaid = DB::table('installments')
                ->where('enrollment_id',$enroll['id'])->whereNull('paid_at')->count();
            $payStatus = $unpaid === 0 ? 'full' : ($paidCount > 0 ? 'partial' : 'pending');
            DB::table('enrollments')->where('id',$enroll['id'])
                ->update(['payment_status'=>$payStatus]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // PAYROLL RUNS
    // Approved runs → set paid_at pada attendance_tutor di bulan itu
    // ────────────────────────────────────────────────────────────────────

    private function seedPayrollRuns(): void
    {
        $now = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $month    = Carbon::now()->startOfMonth()->subMonths($i);
            $status   = $i > 0 ? 'approved' : 'pending';
            $approver = $status === 'approved' ? self::ADMIN_USER_ID : null;

            if (!DB::table('payroll_runs')->where('month',$month->toDateString())->exists()) {
                DB::table('payroll_runs')->insert([
                    'month'=>$month->toDateString(),'status'=>$status,
                    'approved_by'=>$approver,'created_at'=>$now,'updated_at'=>$now,
                ]);
            }

            // Kalau approved → mark paid_at untuk semua attendance_tutor di bulan itu
            if ($status === 'approved') {
                $start = $month->copy()->startOfMonth()->toDateString();
                $end   = $month->copy()->endOfMonth()->toDateString();

                // Ambil attendance_tutor yang belum dibayar di bulan ini
                $attTutorIds = DB::table('attendance_tutor')
                    ->join('attendance','attendance_tutor.attendance_id','=','attendance.id')
                    ->whereBetween('attendance.date',[$start,$end])
                    ->where('attendance_tutor.pending_rate', 0)
                    ->whereNull('attendance_tutor.paid_at')
                    ->pluck('attendance_tutor.id');

                foreach ($attTutorIds as $id) {
                    DB::table('attendance_tutor')->where('id',$id)->update([
                        'paid_at'    => $month->copy()->endOfMonth()->toDateString(),
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // ROOM BOOKINGS
    // ────────────────────────────────────────────────────────────────────

    private function seedAdjustingJournals(): void
    {
        $now                  = Carbon::now();
        $expenseAccountId     = DB::table('accounts')->where('code','5108')->value('id');
        $accumulatedAccountId = DB::table('accounts')->where('code','1006')->value('id');

        // Seed 6 bulan depreciation journals
        for ($i = 5; $i >= 0; $i--) {
            $period = Carbon::now()->startOfMonth()->subMonths($i);
            $ref    = 'DEP-' . $period->format('Ym');

            if (DB::table('adjusting_journals')->where('reference', $ref)->exists()) continue;

            // Total monthly depreciation dari semua fixed assets
            $assets        = DB::table('fixed_assets')->where('is_active', 1)->get();
            $totalDepreciation = 0;
            foreach ($assets as $asset) {
                $monthlyDep = round(($asset->cost - $asset->salvage_value) / ($asset->useful_life * 12), 2);
                $totalDepreciation += $monthlyDep;
            }

            if ($totalDepreciation <= 0) continue;

            $isPosted = $i > 0;

            $adjId = DB::table('adjusting_journals')->insertGetId([
                'period'       => $period->toDateString(),
                'reference'    => $ref,
                'description'  => 'Penyusutan Aset Tetap ' . $period->isoFormat('MMMM YYYY'),
                'type'         => 'depreciation',
                'status'       => $isPosted ? 'posted' : 'draft',
                'total_amount' => $totalDepreciation,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            // Kalau posted, buat jurnal di tabel journals juga
            $postedJournalId = null;
            if ($isPosted) {
                $postedJournalId = DB::table('journals')->insertGetId([
                    'date'         => $period->endOfMonth()->toDateString(),
                    'description'  => '[AJP] Penyusutan Aset Tetap ' . $period->isoFormat('MMMM YYYY'),
                    'reference'    => $ref,
                    'total_amount' => $totalDepreciation,
                    'type'         => 'adjusting',
                    'approved_by'  => self::ADMIN_USER_ID,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);

                DB::table('journal_items')->insert([
                    [
                        'journal_id' => $postedJournalId,
                        'account_id' => $expenseAccountId,
                        'debit'      => $totalDepreciation,
                        'credit'     => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'journal_id' => $postedJournalId,
                        'account_id' => $accumulatedAccountId,
                        'debit'      => 0,
                        'credit'     => $totalDepreciation,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);

                DB::table('adjusting_journals')->where('id', $adjId)->update([
                    'posted_journal_id' => $postedJournalId,
                ]);
            }

            DB::table('adjusting_journal_items')->insert([
                [
                    'adjusting_journal_id' => $adjId,
                    'account_id'           => $expenseAccountId,
                    'debit'                => $totalDepreciation,
                    'credit'               => 0,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ],
                [
                    'adjusting_journal_id' => $adjId,
                    'account_id'           => $accumulatedAccountId,
                    'debit'                => 0,
                    'credit'               => $totalDepreciation,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ],
            ]);
        }
    }


}
