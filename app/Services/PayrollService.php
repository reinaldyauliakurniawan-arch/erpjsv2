<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Exceptions\DomainException;
use App\Exceptions\IdempotencyException;
use App\Models\Journal;
use App\Models\PayrollRun;
use App\Models\Tutor;
use App\Models\TutorFeeAdjustment;
use App\Support\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
                // Periode honor: tanggal 26 bulan lalu s.d. 25 bulan ini.
                $unpaidAttendances = DB::table('attendance_tutor')
                    ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
                    ->where('attendance_tutor.tutor_id', $tutor->id)
                    ->whereNull('attendance_tutor.paid_at')
                    ->where('attendance_tutor.pending_rate', false)
                    ->where('attendance_tutor.payable_amount', '>', 0)
                    ->whereBetween('attendance.date', $this->periodBounds($payrollRun->month))
                    ->select('attendance_tutor.*')
                    ->get();

                // ── 3. Penyesuaian honor (B2B, tunjangan, koreksi, saldo awal) ─
                $unpaidAdjustments = TutorFeeAdjustment::where('tutor_id', $tutor->id)
                    ->whereNull('paid_at')
                    ->inPayrollPeriod($this->periodBounds($payrollRun->month))
                    ->get();

                if ($unpaidAttendances->isEmpty() && $unpaidAdjustments->isEmpty()) {
                    continue;
                }

                $totalAmount = bcadd(
                    (string) $unpaidAttendances->sum('payable_amount'),
                    (string) $unpaidAdjustments->sum('amount'),
                    2
                );
                if (bccomp($totalAmount, '0', 2) <= 0) {
                    // Koreksi negatif lebih besar dari honornya — tidak ada yang
                    // dibayar; biarkan semuanya terbuka untuk periode berikutnya.
                    continue;
                }
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
                TutorFeeAdjustment::whereIn('id', $unpaidAdjustments->pluck('id'))
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
                $tied += TutorFeeAdjustment::where('tutor_id', $tutor->id)
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
                        ->whereBetween('attendance.date', $this->periodBounds($payrollRun->month))
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

    /**
     * Catat penyesuaian honor tutor + jurnalnya.
     *
     *   honor / tunjangan : Dr Beban Gaji Tutor (5001)   / Cr Utang Tutor (2003)
     *   saldo_awal        : Dr Ekuitas Saldo Awal (3003) / Cr Utang Tutor (2003)
     *                       — honor periode sebelum tanggal mulai pembukuan yang
     *                       baru dibayar sekarang (bebannya milik periode lama).
     * Nominal negatif = koreksi kelebihan klaim (jurnalnya dibalik).
     */
    public function addAdjustment(int $tutorId, string $date, string $amount, string $kind, string $description, ?int $createdBy = null): TutorFeeAdjustment
    {
        if (! array_key_exists($kind, TutorFeeAdjustment::KINDS)) {
            throw new DomainException("Jenis penyesuaian tidak dikenal: {$kind}");
        }
        $amount = bcadd($amount, '0', 2);
        if (bccomp($amount, '0', 2) === 0) {
            throw new DomainException('Nominal penyesuaian tidak boleh 0.');
        }

        return DB::transaction(function () use ($tutorId, $date, $amount, $kind, $description, $createdBy) {
            $tutor = Tutor::with('user')->findOrFail($tutorId);
            $adj = TutorFeeAdjustment::create([
                'tutor_id' => $tutor->id, 'date' => $date, 'kind' => $kind,
                'amount' => $amount, 'description' => $description, 'created_by' => $createdBy,
            ]);

            $debitCode = $kind === 'saldo_awal'
                ? AccountCode::OPENING_BALANCE_EQUITY->value
                : AccountCode::EXPENSE_TUTOR_FEE->value;
            $abs = ltrim($amount, '-');
            $positive = bccomp($amount, '0', 2) > 0;
            $journal = $this->accountingService->createJournal(
                Carbon::parse($date)->toDateString(),
                TutorFeeAdjustment::KINDS[$kind].": {$tutor->user->name} — {$description}",
                "TUTOR-ADJ-{$adj->id}",
                [
                    ['account_code' => $debitCode,                        'debit' => $positive ? $abs : 0, 'credit' => $positive ? 0 : $abs],
                    ['account_code' => AccountCode::TUTOR_PAYABLE->value, 'debit' => $positive ? 0 : $abs, 'credit' => $positive ? $abs : 0],
                ],
                $kind === 'saldo_awal' ? 'opening' : 'tutor_accrual',
            );
            $adj->update(['journal_id' => $journal->id]);

            $payrollMonth = PayrollPeriod::monthFor($date);
            if (PayrollRun::whereDate('month', $payrollMonth->toDateString())->where('status', 'approved')->exists()) {
                $this->notifier->feeAfterPayrollApproved($tutor, $payrollMonth->translatedFormat('F Y'));
            }

            return $adj;
        });
    }

    /** Hapus penyesuaian yang belum dibayar beserta jurnalnya. */
    public function deleteAdjustment(int $id): void
    {
        DB::transaction(function () use ($id) {
            $adj = TutorFeeAdjustment::lockForUpdate()->findOrFail($id);
            if ($adj->paid_at) {
                throw new DomainException('Penyesuaian ini sudah dibayar oleh payroll run — reverse payroll-nya dulu.');
            }
            if ($adj->journal_id) {
                DB::table('journal_items')->where('journal_id', $adj->journal_id)->delete();
                Journal::whereKey($adj->journal_id)->delete();
            }
            $adj->delete();
        });
    }

    /**
     * Rekap yang AKAN dibayar payroll bulan $month per tutor (belum dibayar):
     * honor per pertemuan + penyesuaian + gaji tetap.
     *
     * @return Collection<int, array{tutor: string, meetings: int, fee: float, adjustments: float, salary: float, total: float}>
     */
    public function preview($month)
    {
        $bounds = $this->periodBounds($month);
        $salaryPaid = PayrollRun::whereDate('month', Carbon::parse($month)->startOfMonth()->toDateString())
            ->where('status', 'approved')->pluck('id');

        return Tutor::with('user')->get()->map(function (Tutor $tutor) use ($bounds, $month, $salaryPaid) {
            $fees = DB::table('attendance_tutor')
                ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
                ->where('attendance_tutor.tutor_id', $tutor->id)
                ->whereNull('attendance_tutor.paid_at')
                ->where('attendance_tutor.pending_rate', false)
                ->where('attendance_tutor.payable_amount', '>', 0)
                ->whereBetween('attendance.date', $bounds)
                ->selectRaw('COUNT(*) n, COALESCE(SUM(attendance_tutor.payable_amount), 0) s')->first();
            $adj = (float) TutorFeeAdjustment::where('tutor_id', $tutor->id)->whereNull('paid_at')
                ->inPayrollPeriod($bounds)->sum('amount');
            $salaryAlreadyPaid = $salaryPaid->contains(
                fn ($rid) => Journal::where('reference', "PAYROLL-{$rid}-TUTOR-{$tutor->id}-SALARY")->exists()
            );
            $salary = $salaryAlreadyPaid ? 0.0 : (float) $tutor->proratedSalaryForMonth($month);
            $fee = (float) $fees->s;
            $variable = max(0, $fee + $adj);

            return [
                'tutor' => $tutor->user->name, 'meetings' => (int) $fees->n, 'fee' => $fee,
                'adjustments' => $adj, 'salary' => $salary, 'total' => $variable + $salary,
            ];
        })->filter(fn ($r) => $r['total'] > 0 || $r['adjustments'] != 0)->sortBy('tutor')->values();
    }

    /**
     * Batas tanggal pertemuan (Y-m-d, inklusif) untuk payroll bulan $month.
     *
     * @return array{0: string, 1: string}
     */
    private function periodBounds($month): array
    {
        [$from, $to] = PayrollPeriod::forMonth($month);

        return [$from->toDateString(), $to->toDateString()];
    }
}
