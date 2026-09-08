<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Exceptions\DomainException;
use App\Exceptions\IdempotencyException;
use App\Models\Journal;
use App\Models\PayrollRun;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    protected $accountingService;

    protected Notifier $notifier;

    public function __construct(AccountingService $accountingService, Notifier $notifier)
    {
        $this->accountingService = $accountingService;
        $this->notifier = $notifier;
    }

    public function createPayrollRun(string $month): PayrollRun
    {
        $monthKey = Carbon::parse($month)->startOfMonth()->toDateString();

        return DB::transaction(function () use ($monthKey) {
            $existing = PayrollRun::whereDate('month', $monthKey)
                ->whereNotIn('status', ['reversed'])
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            // Masih ada run yang belum di-approve → selesaikan itu dulu.
            if ($existing && $existing->status === 'pending') {
                throw new DomainException('Masih ada payroll run bulan ini yang belum di-approve.');
            }

            // Sudah ada run yang approved → run baru ini otomatis jadi
            // "pembayaran susulan": nanti saat di-approve hanya menyapu honor
            // yang BELUM terbayar di bulan itu (mis. presensi/tarif yang baru
            // masuk setelah payroll bulan itu berjalan). Gaji tutor tetap tidak
            // dibayar dua kali (dijaga di approvePayrollRun()).
            return PayrollRun::create([
                'month' => $monthKey,
                'status' => 'pending',
            ]);
        });
    }

    public function approvePayrollRun(int $payrollRunId, int $approvedBy): PayrollRun
    {
        return DB::transaction(function () use ($payrollRunId, $approvedBy) {
            // Lock baris run — cegah dua "approve" paralel sama-sama menyapu &
            // memposting jurnal. Status di-cek ULANG setelah lock.
            $payrollRun = PayrollRun::lockForUpdate()->findOrFail($payrollRunId);
            if ($payrollRun->status === 'approved') {
                throw new DomainException('Payroll run ini sudah di-approve sebelumnya.');
            }
            if ($payrollRun->status === 'reversed') {
                throw new DomainException('Payroll run ini sudah di-reverse — buat run baru untuk membayar.');
            }

            $tutors = Tutor::with('user')->get();
            $date = now()->toDateString();
            // Consistency fix: previously `now()` was called per-tutor inside
            // the loop, so each tutor's attendances got a slightly different
            // `paid_at` timestamp (millisecond drift). For audit purposes all
            // payments in a payroll run should have the same timestamp.
            $paidAt = now();

            // Run lain di bulan yang sama yang sudah approved → run ini adalah
            // pembayaran susulan. Gaji tutor tetap TIDAK dibayar ulang.
            $priorApprovedRunIds = PayrollRun::whereDate('month', $payrollRun->month)
                ->where('id', '!=', $payrollRun->id)
                ->where('status', 'approved')
                ->pluck('id');

            foreach ($tutors as $tutor) {
                $salaryAlreadyPaid = $priorApprovedRunIds->contains(
                    fn ($rid) => Journal::where('reference', "PAYROLL-{$rid}-TUTOR-{$tutor->id}-SALARY")->exists()
                );
                // ── 1. Tutor tetap: gaji bulanan ──────────────────────────
                // Dibayar regardless berapa meeting yang diajar. Pro-rata per
                // hari untuk bulan pertama/terakhir yang tidak penuh (mis.
                // baru diangkat / berhenti di tengah bulan). Berbasis periode
                // [salaried_since, salaried_until], BUKAN employment_type,
                // supaya bulan lampau tetap benar walau statusnya sudah
                // berubah.
                $salary = $tutor->proratedSalaryForMonth($payrollRun->month);
                if (! $salaryAlreadyPaid && bccomp($salary, '0', 2) > 0) {
                    try {
                        $this->accountingService->createJournal(
                            $date,
                            "Payroll Salary for Tutor: {$tutor->user->name} - Run #{$payrollRun->id}",
                            "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-SALARY",
                            [
                                ['account_code' => AccountCode::EXPENSE_TUTOR_PERMANENT_SALARY->value, 'debit' => $salary, 'credit' => 0],
                                ['account_code' => AccountCode::BANK->value,                           'debit' => 0,       'credit' => $salary],
                            ],
                            'payroll'
                        );
                    } catch (IdempotencyException $e) {
                        // Sudah pernah diposting untuk run ini — abaikan.
                    }
                    $this->notifier->payrollPaid($tutor, Carbon::parse($payrollRun->month)->translatedFormat('F Y'), (float) $salary);
                }

                // ── 2. Fee freelance per meeting ──────────────────────────
                $unpaidAttendances = DB::table('attendance_tutor')
                    ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
                    ->where('attendance_tutor.tutor_id', $tutor->id)
                    ->whereNull('attendance_tutor.paid_at')
                    ->where('attendance_tutor.pending_rate', false)
                    ->where('attendance_tutor.payable_amount', '>', 0)
                    ->whereYear('attendance.date', Carbon::parse($payrollRun->month)->year)
                    ->whereMonth('attendance.date', Carbon::parse($payrollRun->month)->month)
                    ->select('attendance_tutor.*')
                    ->get();

                if ($unpaidAttendances->isEmpty()) {
                    continue;
                }

                $totalAmount = $unpaidAttendances->sum('payable_amount');
                $reference = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}";

                // Jurnal: Pembayaran hutang ke tutor. try/catch simetris dengan
                // cabang gaji di atas — kalau entah bagaimana sudah ada, abaikan.
                try {
                    $this->accountingService->createJournal(
                        $date,
                        "Payroll Payment for Tutor: {$tutor->user->name} - Run #{$payrollRun->id}",
                        $reference.'-PAY',
                        [
                            ['account_code' => AccountCode::TUTOR_PAYABLE->value, 'debit' => $totalAmount, 'credit' => 0],
                            ['account_code' => AccountCode::BANK->value,          'debit' => 0,            'credit' => $totalAmount],
                        ],
                        'payroll'
                    );
                } catch (IdempotencyException $e) {
                    // Sudah pernah diposting untuk run ini — abaikan.
                }

                DB::table('attendance_tutor')
                    ->whereIn('id', $unpaidAttendances->pluck('id'))
                    ->update(['paid_at' => $paidAt, 'payroll_run_id' => $payrollRun->id]);

                $this->notifier->payrollPaid($tutor, Carbon::parse($payrollRun->month)->translatedFormat('F Y'), (float) $totalAmount);
            }

            $payrollRun->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
            ]);

            return $payrollRun;
        });
    }

    public function reversePayrollRun(int $payrollRunId, int $reversedBy): PayrollRun
    {
        return DB::transaction(function () use ($payrollRunId, $reversedBy) {
            // Lock baris run + cek status ULANG di dalam transaction — cegah dua
            // "reverse" paralel sama-sama memposting jurnal pembalik.
            $payrollRun = PayrollRun::lockForUpdate()->findOrFail($payrollRunId);
            if ($payrollRun->status !== 'approved') {
                throw new DomainException('Hanya payroll run dengan status approved yang bisa di-reverse.');
            }

            $tutors = Tutor::with('user')->get();
            $date = now()->toDateString();
            $monthLabel = Carbon::parse($payrollRun->month)->translatedFormat('F Y');

            foreach ($tutors as $tutor) {
                $reversedSomething = false;

                // ── 1. Reverse jurnal gaji tutor tetap ───────────────────
                $salaryRef = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-SALARY";
                $salaryJournal = Journal::where('reference', $salaryRef)->first();
                if ($salaryJournal && ! Journal::where('reference', "REV-{$salaryRef}")->exists()) {
                    $this->accountingService->createJournal(
                        $date,
                        "REVERSE Payroll Salary for Tutor: {$tutor->user->name} - Run #{$payrollRun->id}",
                        "REV-{$salaryRef}",
                        [
                            ['account_code' => AccountCode::BANK->value,                           'debit' => $salaryJournal->total_amount, 'credit' => 0],
                            ['account_code' => AccountCode::EXPENSE_TUTOR_PERMANENT_SALARY->value, 'debit' => 0,                            'credit' => $salaryJournal->total_amount],
                        ],
                        'payroll'
                    );
                    $reversedSomething = true;
                }

                // ── 2. Reverse jurnal pembayaran fee freelance ───────────
                $payRef = "PAYROLL-{$payrollRun->id}-TUTOR-{$tutor->id}-PAY";
                $payJournal = Journal::where('reference', $payRef)->first();
                if ($payJournal && ! Journal::where('reference', "REV-{$payRef}")->exists()) {
                    $this->accountingService->createJournal(
                        $date,
                        "REVERSE Payroll Payment for Tutor: {$tutor->user->name} - Run #{$payrollRun->id}",
                        "REV-{$payRef}",
                        [
                            ['account_code' => AccountCode::BANK->value,          'debit' => $payJournal->total_amount, 'credit' => 0],
                            ['account_code' => AccountCode::TUTOR_PAYABLE->value, 'debit' => 0, 'credit' => $payJournal->total_amount],
                        ],
                        'payroll'
                    );
                    $reversedSomething = true;
                }

                // ── 3. Kembalikan status "belum dibayar" pada presensi ───
                // Presisi: hanya baris yang dibayar OLEH run ini. Dilakukan
                // TERLEPAS dari keberadaan jurnal (kalau jurnalnya hilang karena
                // diedit manual, baris tetap wajib dibersihkan).
                $tied = DB::table('attendance_tutor')
                    ->where('tutor_id', $tutor->id)
                    ->where('payroll_run_id', $payrollRun->id)
                    ->update(['paid_at' => null, 'payroll_run_id' => null]);
                if ($tied > 0) {
                    $reversedSomething = true;
                } elseif ($payJournal) {
                    // Run lama (di-approve sebelum kolom payroll_run_id ada):
                    // ada jurnal fee tapi baris presensinya tidak tertaut.
                    // Fallback ke filter bulan, hanya baris yang belum tertaut
                    // run mana pun (baris milik run lain punya payroll_run_id).
                    DB::table('attendance_tutor')
                        ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
                        ->where('attendance_tutor.tutor_id', $tutor->id)
                        ->whereNotNull('attendance_tutor.paid_at')
                        ->whereNull('attendance_tutor.payroll_run_id')
                        ->where('attendance_tutor.pending_rate', false)
                        ->whereYear('attendance.date', Carbon::parse($payrollRun->month)->year)
                        ->whereMonth('attendance.date', Carbon::parse($payrollRun->month)->month)
                        ->update(['attendance_tutor.paid_at' => null]);
                }

                if ($reversedSomething) {
                    $this->notifier->payrollReversed($tutor, $monthLabel);
                }
            }

            $payrollRun->update([
                'status' => 'reversed',
                'reversed_by' => $reversedBy,
            ]);

            return $payrollRun;
        });
    }
}
