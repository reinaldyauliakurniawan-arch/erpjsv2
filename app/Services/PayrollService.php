<?php

namespace App\Services;

use App\Models\Tutor;
use App\Models\PayrollRun;
use App\Enums\AccountCode;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DomainException;

class PayrollService
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function createPayrollRun(string $month): PayrollRun
    {
        $monthKey = \Carbon\Carbon::parse($month)->startOfMonth()->toDateString();

        $existing = PayrollRun::where('month', $monthKey)->first();
        if ($existing) {
            throw new DomainException("Payroll run untuk bulan ini sudah ada (status: {$existing->status}).");
        }

        return PayrollRun::create([
            'month'  => $monthKey,
            'status' => 'pending',
        ]);
    }

    public function approvePayrollRun(int $payrollRunId, int $approvedBy): PayrollRun
    {
        $payrollRun = PayrollRun::findOrFail($payrollRunId);

        if ($payrollRun->status === 'approved') {
            throw new DomainException("Payroll run ini sudah di-approve sebelumnya.");
        }

        return DB::transaction(function () use ($payrollRun, $approvedBy) {
            $tutors = Tutor::with('user')->get();
            $date   = now()->toDateString();

            foreach ($tutors as $tutor) {
                $unpaidAttendances = DB::table('attendance_tutor')
                    ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
                    ->where('attendance_tutor.tutor_id', $tutor->id)
                    ->whereNull('attendance_tutor.paid_at')
                    ->where('attendance_tutor.pending_rate', false)
                    ->whereYear('attendance.date', \Carbon\Carbon::parse($payrollRun->month)->year)
                    ->whereMonth('attendance.date', \Carbon\Carbon::parse($payrollRun->month)->month)
                    ->select('attendance_tutor.*')
                    ->get();

                if ($unpaidAttendances->isEmpty()) {
                    continue;
                }

                $totalAmount = $unpaidAttendances->sum('payable_amount');
                $reference   = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}";

                // Jurnal: Pembayaran hutang ke tutor
                $this->accountingService->createJournal(
                    $date,
                    "Payroll Payment for Tutor: {$tutor->user->name} - Run #{$payrollRun->id}",
                    $reference . '-PAY',
                    [
                        ['account_code' => AccountCode::TUTOR_PAYABLE->value, 'debit' => $totalAmount, 'credit' => 0],
                        ['account_code' => AccountCode::BANK->value,          'debit' => 0,            'credit' => $totalAmount],
                    ],
                    'payroll'
                );

                DB::table('attendance_tutor')
                    ->whereIn('id', $unpaidAttendances->pluck('id'))
                    ->update(['paid_at' => now()]);
            }

            $payrollRun->update([
                'status'      => 'approved',
                'approved_by' => $approvedBy,
            ]);

            return $payrollRun;
        });
        public function reversePayrollRun(int $payrollRunId, int $reversedBy): PayrollRun
    {
        $payrollRun = PayrollRun::findOrFail($payrollRunId);

        if ($payrollRun->status !== 'approved') {
            throw new DomainException("Hanya payroll run dengan status approved yang bisa di-reverse.");
        }

        return DB::transaction(function () use ($payrollRun, $reversedBy) {
            $tutors = Tutor::with('user')->get();
            $date   = now()->toDateString();

            foreach ($tutors as $tutor) {
                $reference = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";

                $originalJournal = \App\Models\Journal::where('reference', $reference)->first();
                if (!$originalJournal) {
                    continue;
                }

                $reverseReference = "REV-{$reference}";
                $alreadyReversed  = \App\Models\Journal::where('reference', $reverseReference)->exists();
                if ($alreadyReversed) {
                    continue;
                }

                $this->accountingService->createJournal(
                    $date,
                    "REVERSE Payroll Payment for Tutor: {$tutor->user->name} - Run #{$payrollRun->id}",
                    $reverseReference,
                    [
                        ['account_code' => AccountCode::BANK->value,          'debit' => $originalJournal->total_amount, 'credit' => 0],
                        ['account_code' => AccountCode::TUTOR_PAYABLE->value, 'debit' => 0, 'credit' => $originalJournal->total_amount],
                    ],
                    'payroll'
                );

                DB::table('attendance_tutor')
                    ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
                    ->where('attendance_tutor.tutor_id', $tutor->id)
                    ->whereNotNull('attendance_tutor.paid_at')
                    ->where('attendance_tutor.pending_rate', false)
                    ->whereYear('attendance.date', \Carbon\Carbon::parse($payrollRun->month)->year)
                    ->whereMonth('attendance.date', \Carbon\Carbon::parse($payrollRun->month)->month)
                    ->update(['attendance_tutor.paid_at' => null]);
            }

            $payrollRun->update([
                'status'      => 'reversed',
                'reversed_by' => $reversedBy,
            ]);

            return $payrollRun;
        });
    }
}
    }
}
