<?php

namespace App\Services;

use App\Enums\AccountCode;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Journal;
use App\Support\LedgerStart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menjaga buku besar (journals / journal_items) tetap SINKRON dengan posisi
 * keuangan sebenarnya dari sebuah enrollment.
 *
 * Prinsip (best practice akuntansi): jurnal yang sudah diposting TIDAK PERNAH
 * diubah atau dihapus. Kalau data operasional berubah (total biaya, program,
 * cicilan dibayar/dibatalkan, metode bayar), kita hitung selisih antara posisi
 * yang SEHARUSNYA dan yang SUDAH diposting, lalu posting satu jurnal
 * penyesuaian yang balance untuk menutup selisih itu.
 *
 * Posisi 4 akun yang dilacak per enrollment:
 *   - Kas/Bank (1001/1002)         — uang yang benar-benar diterima
 *   - Piutang (1003)               — revenue diakui tapi belum dibayar
 *   - Pendapatan Diterima Dimuka (2002) — dibayar tapi belum diakui
 *   - Pendapatan (4101)            — revenue yang sudah diakui (dari attendance)
 *
 * Target dihitung ulang dari data operasional (sama sumbernya dg
 * RevenueRecognitionService): total_amount kontrak, total_meetings program,
 * jumlah baris attendance_student, dan cicilan yang paid_at-nya terisi.
 */
class EnrollmentLedgerService
{
    private const EPS = '0.01';

    public function __construct(
        protected AccountingService $accounting,
        protected RevenueRecognitionService $revenueRecognition,
    ) {}

    /**
     * Bangun ulang SELURUH jurnal enrollment ini dari data operasional saat ini
     * — hapus semua jurnal lama (enrollment_id = ini), lalu posting ulang:
     *   1. jurnal kas sebesar uang yang benar-benar diterima (Dr Kas/Bank / Cr
     *      Pendapatan Diterima Dimuka),
     *   2. jurnal revenue recognition per pertemuan (replay tanggal attendance).
     *
     * Dipakai saat admin mengoreksi nominal enrollment lewat form edit: jurnalnya
     * ikut "ter-edit" langsung — TANPA jurnal penyesuaian (ENR-SYNC). Hasil akhir
     * selalu persis sama dengan posisi target (isInSync() == true).
     *
     * Jurnal honor tutor (TUTOR-PAY-*, enrollment_id NULL) tidak disentuh.
     *
     * Caller WAJIB sudah lockForUpdate() row Enrollment di transaction yang sama.
     */
    public function rebuild(Enrollment $enrollment, ?string $date = null): void
    {
        $enrollment->loadMissing('program');
        $date ??= optional($enrollment->enrollment_date)->toDateString() ?? now()->toDateString();

        // Tanggal mulai pembukuan: tidak ada jurnal enrollment sebelum tanggal ini.
        $start = LedgerStart::date();
        if ($start !== null && $date < $start) {
            $date = $start;
        }
        $opening = $this->openingPosition($enrollment, $start);

        // 1. hapus jurnal lama milik enrollment ini
        $jids = Journal::where('enrollment_id', $enrollment->id)->pluck('id');
        if ($jids->isNotEmpty()) {
            DB::table('attendance_tutor')->whereIn('journal_id', $jids)->update(['journal_id' => null]);
            DB::table('journal_items')->whereIn('journal_id', $jids)->delete();
            Journal::whereIn('id', $jids)->delete();
        }

        // 2a. saldo awal per tanggal mulai pembukuan: sisa uang titipan &
        //     sisa tagihan dari riwayat sebelumnya, lawannya Ekuitas Saldo Awal.
        //     (Kasnya sendiri masuk saldo kas awal yang dicatat CFO.)
        if ($start !== null
            && (bccomp($opening['deferred'], '0', 2) > 0 || bccomp($opening['receivable'], '0', 2) > 0)) {
            $items = [];
            if (bccomp($opening['receivable'], '0', 2) > 0) {
                $items[] = ['account_code' => AccountCode::ACCOUNTS_RECEIVABLE->value, 'debit' => $opening['receivable'], 'credit' => 0];
            }
            if (bccomp($opening['deferred'], '0', 2) > 0) {
                $items[] = ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => $opening['deferred']];
            }
            $equity = bcsub($opening['deferred'], $opening['receivable'], 2);
            if (bccomp($equity, '0', 2) !== 0) {
                $positive = bccomp($equity, '0', 2) > 0;
                $items[] = [
                    'account_code' => AccountCode::OPENING_BALANCE_EQUITY->value,
                    'debit' => $positive ? $equity : '0',
                    'credit' => $positive ? '0' : bcmul($equity, '-1', 2),
                ];
            }

            $this->accounting->createJournal(
                $start,
                "Saldo awal enrollment #{$enrollment->id} per {$start}",
                'SALDO-AWAL-ENR-'.$enrollment->id,
                $items,
                'opening',
                $enrollment->program_id,
                $enrollment->id,
            );
        }

        // 2b. jurnal kas = uang yang benar-benar diterima (sejak tanggal mulai
        //     pembukuan, kalau ada). Pelunasan tagihan saldo awal didahulukan.
        $cash = $opening['paidAfter'];
        if (bccomp($cash, '0', 2) > 0) {
            $cashCode = $enrollment->payment_channel === 'cash'
                ? AccountCode::CASH->value
                : AccountCode::BANK->value;

            // Untuk metode cicilan, tanggal jurnal kas ikut tanggal
            // pembayaran cicilan TERAKHIR yang lunas (bukan selalu tanggal
            // transaksi awal) — supaya koreksi tanggal bayar di form edit
            // enrollment benar-benar "nyambung" ke jurnalnya.
            $cashDate = $date;
            if ($enrollment->payment_method === 'installment') {
                $latestPaidAt = $enrollment->installments()->whereNotNull('paid_at')->max('paid_at');
                if ($latestPaidAt) {
                    $cashDate = Carbon::parse($latestPaidAt)->toDateString();
                }
            }

            $toReceivable = bccomp($opening['receivable'], $cash, 2) >= 0 ? $cash : $opening['receivable'];
            $toDeferred = bcsub($cash, $toReceivable, 2);
            $items = [['account_code' => $cashCode, 'debit' => $cash, 'credit' => 0]];
            if (bccomp($toReceivable, '0', 2) > 0) {
                $items[] = ['account_code' => AccountCode::ACCOUNTS_RECEIVABLE->value, 'debit' => 0, 'credit' => $toReceivable];
            }
            if (bccomp($toDeferred, '0', 2) > 0) {
                $items[] = ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => $toDeferred];
            }

            $this->accounting->createJournal(
                $cashDate,
                "Pembayaran enrollment #{$enrollment->id}",
                'PAYMENT-ENROLL-'.$enrollment->id,
                $items,
                'payment',
                $enrollment->program_id,
                $enrollment->id,
            );
        }

        // 3. replay revenue recognition per pertemuan (urut tanggal) — hanya
        //    pertemuan sejak tanggal mulai pembukuan; yang sebelumnya sudah
        //    terhitung di saldo awal.
        $meetings = DB::table('attendance_student as ats')
            ->join('attendance as a', 'a.id', '=', 'ats.attendance_id')
            ->where('ats.enrollment_id', $enrollment->id)
            ->when($start !== null, fn ($q) => $q->where('a.date', '>=', $start))
            ->orderBy('a.date')->orderBy('a.id')
            ->get(['a.id as att_id', 'a.date']);

        $meetingCount = $opening['meetingsBefore'] + $meetings->count();
        foreach ($meetings->values() as $i => $m) {
            $split = $this->revenueRecognition->splitForNextMeeting($enrollment, $opening['meetingsBefore'] + $i);
            if (bccomp($split['revenueThisMeeting'], '0', 2) <= 0) {
                continue;
            }
            $items = [];
            if (bccomp($split['fromDeferredRevenue'], '0', 2) > 0) {
                $items[] = ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => $split['fromDeferredRevenue'], 'credit' => 0];
            }
            if (bccomp($split['fromReceivable'], '0', 2) > 0) {
                $items[] = ['account_code' => AccountCode::ACCOUNTS_RECEIVABLE->value, 'debit' => $split['fromReceivable'], 'credit' => 0];
            }
            $items[] = ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $split['revenueThisMeeting']];

            $this->accounting->createJournal(
                $m->date instanceof \DateTimeInterface ? $m->date->format('Y-m-d') : (string) $m->date,
                "Revenue Recognition: enrollment #{$enrollment->id}, Session: {$m->att_id}",
                "REV-REC-{$m->att_id}-{$enrollment->id}",
                $items,
                'revenue_recognition',
                $enrollment->program_id,
                $enrollment->id,
            );
        }

        // Enrollment sudah berakhir (expired = hangus / graduate = selesai):
        // sisa uang yang belum diakui jadi pendapatan (siswa tidak dapat refund
        // untuk pertemuan yang tidak dipakai).
        if (in_array($enrollment->status, ['expired', 'graduate'], true)) {
            $recognized = $this->revenueRecognition->totalRevenueRecognizedSoFar($enrollment, $meetingCount);
            $forfeit = bcsub($this->revenueRecognition->totalPaid($enrollment), $recognized, 2);
            if (bccomp($forfeit, self::EPS, 2) > 0) {
                $this->accounting->createJournal(
                    $date,
                    "Pengakuan sisa pendapatan (enrollment #{$enrollment->id} {$enrollment->status})",
                    "MANUAL-EXPIRY-{$enrollment->id}",
                    [
                        ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => $forfeit, 'credit' => 0],
                        ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $forfeit],
                    ],
                    'revenue_recognition',
                    $enrollment->program_id,
                    $enrollment->id,
                );
            }
        }
    }

    /**
     * Posisi yang SEHARUSNYA, dari data operasional saat ini.
     * Nilai positif = saldo normal akun tsb (Kas/Piutang debit; Deferred/Revenue kredit).
     *
     * @return array{cash:string, receivable:string, deferred:string, revenue:string}
     */
    public function targetPosition(Enrollment $enrollment): array
    {
        $enrollment->loadMissing('program');
        $totalMeetings = (int) ($enrollment->program->total_meetings ?? 0);
        $totalAmount = (string) ($enrollment->total_amount ?? '0');

        $meetings = $this->revenueRecognition->meetingsRecognizedSoFar($enrollment);

        $perMeeting = $totalMeetings > 0 ? bcdiv($totalAmount, (string) $totalMeetings, 2) : '0';
        // Kalau semua pertemuan sudah jalan, revenue = TEPAT total_amount (sisa
        // pembulatan perMeeting diserap) — konsisten dg RevenueRecognitionService.
        $revenue = ($totalMeetings > 0 && $meetings >= $totalMeetings)
            ? bcadd($totalAmount, '0', 2)
            : bcmul((string) $meetings, $perMeeting, 2);

        $cash = $enrollment->payment_method === 'installment'
            ? (string) $enrollment->installments()->whereNotNull('paid_at')->sum('amount')
            : $totalAmount;
        $cash = bcadd($cash, '0', 2);

        // Enrollment berakhir (expired/graduate): seluruh kas jadi pendapatan
        // (sisa pertemuan hangus, tidak di-refund).
        if (in_array($enrollment->status, ['expired', 'graduate'], true) && bccomp($cash, $revenue, 2) > 0) {
            $revenue = $cash;
        }

        $deferred = bccomp(bcsub($cash, $revenue, 2), '0', 2) > 0 ? bcsub($cash, $revenue, 2) : '0';
        $receivable = bccomp(bcsub($revenue, $cash, 2), '0', 2) > 0 ? bcsub($revenue, $cash, 2) : '0';

        // Dengan tanggal mulai pembukuan, buku besar hanya memuat kas yang masuk
        // & pendapatan yang diakui SEJAK tanggal itu; riwayat sebelumnya sudah
        // diringkas jadi saldo awal (piutang/uang titipan) di jurnal SALDO-AWAL.
        $start = LedgerStart::date();
        if ($start !== null) {
            $opening = $this->openingPosition($enrollment, $start);
            $cash = $opening['paidAfter'];
            $revenue = bcsub($revenue, $opening['revenueBefore'], 2);
        }

        return compact('cash', 'receivable', 'deferred', 'revenue');
    }

    /**
     * Pembagian posisi enrollment di tanggal mulai pembukuan.
     *
     * - paidBefore / paidAfter: uang diterima sebelum / sejak tanggal mulai
     *   (cicilan menurut paid_at; bayar penuh menurut tanggal enrollment).
     * - meetingsBefore: pertemuan terpakai sebelum tanggal mulai (riwayat lama
     *   `opening_meetings_used` + baris absensi bertanggal sebelumnya).
     * - revenueBefore, deferred, receivable: posisi saldo awal hasil keduanya.
     *
     * Tanpa tanggal mulai: semuanya dianggap "sesudah" (saldo awal nol).
     *
     * @return array{paidBefore:string, paidAfter:string, meetingsBefore:int, revenueBefore:string, deferred:string, receivable:string}
     */
    public function openingPosition(Enrollment $enrollment, ?string $start): array
    {
        $totalPaid = bcadd($this->revenueRecognition->totalPaid($enrollment), '0', 2);

        if ($start === null) {
            return [
                'paidBefore' => '0', 'paidAfter' => $totalPaid, 'meetingsBefore' => 0,
                'revenueBefore' => '0', 'deferred' => '0', 'receivable' => '0',
            ];
        }

        if ($enrollment->payment_method === 'full upfront') {
            $enrolledOn = optional($enrollment->enrollment_date)->toDateString();
            $paidBefore = ($enrolledOn === null || $enrolledOn < $start) ? $totalPaid : '0';
        } else {
            $paidBefore = bcadd((string) $enrollment->installments()
                ->whereNotNull('paid_at')
                ->where('paid_at', '<', $start)
                ->sum('amount'), '0', 2);
        }

        $meetingsBefore = (int) $enrollment->opening_meetings_used + DB::table('attendance_student as ats')
            ->join('attendance as a', 'a.id', '=', 'ats.attendance_id')
            ->where('ats.enrollment_id', $enrollment->id)
            ->where('a.date', '<', $start)
            ->count();

        $revenueBefore = $this->revenueRecognition->totalRevenueRecognizedSoFar($enrollment, $meetingsBefore);
        $diff = bcsub($paidBefore, $revenueBefore, 2);

        return [
            'paidBefore' => $paidBefore,
            'paidAfter' => bcsub($totalPaid, $paidBefore, 2),
            'meetingsBefore' => $meetingsBefore,
            'revenueBefore' => $revenueBefore,
            'deferred' => bccomp($diff, '0', 2) > 0 ? $diff : '0',
            'receivable' => bccomp($diff, '0', 2) < 0 ? bcmul($diff, '-1', 2) : '0',
        ];
    }

    /**
     * Posisi yang SUDAH diposting ke buku besar untuk enrollment ini
     * (semua jurnal dg journals.enrollment_id = enrollment->id).
     *
     * @return array{cash:string, receivable:string, deferred:string, revenue:string}
     */
    public function postedPosition(Enrollment $enrollment): array
    {
        $rows = DB::table('journal_items as ji')
            ->join('journals as j', 'j.id', '=', 'ji.journal_id')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->where('j.enrollment_id', $enrollment->id)
            ->groupBy('a.code')
            ->selectRaw('a.code, SUM(ji.debit) as d, SUM(ji.credit) as c')
            ->get();

        $net = [];
        foreach ($rows as $r) {
            $net[$r->code] = ['d' => (string) $r->d, 'c' => (string) $r->c];
        }
        $debitMinusCredit = fn (string $code) => isset($net[$code])
            ? bcsub($net[$code]['d'], $net[$code]['c'], 2) : '0';
        $creditMinusDebit = fn (string $code) => isset($net[$code])
            ? bcsub($net[$code]['c'], $net[$code]['d'], 2) : '0';

        $cash = bcadd($debitMinusCredit(AccountCode::CASH->value), $debitMinusCredit(AccountCode::BANK->value), 2);
        $receivable = $debitMinusCredit(AccountCode::ACCOUNTS_RECEIVABLE->value);
        $deferred = $creditMinusDebit(AccountCode::DEFERRED_REVENUE->value);
        $revenue = $creditMinusDebit(AccountCode::REVENUE_TUITION_FEES->value);

        return compact('cash', 'receivable', 'deferred', 'revenue');
    }

    /**
     * Apakah buku besar enrollment ini sudah sinkron dengan data operasional?
     */
    public function isInSync(Enrollment $enrollment): bool
    {
        $t = $this->targetPosition($enrollment);
        $p = $this->postedPosition($enrollment);

        foreach (['cash', 'receivable', 'deferred', 'revenue'] as $k) {
            if (bccomp(self::absDiff($t[$k], $p[$k]), self::EPS, 2) >= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Posting jurnal penyesuaian selisih supaya buku besar match dg data
     * operasional. Return null kalau sudah sinkron (tidak ada yg diposting).
     *
     * Caller WAJIB sudah lockForUpdate() row Enrollment di dalam transaction
     * yang sama (kontrak sama dg RevenueRecognitionService).
     */
    public function reconcile(Enrollment $enrollment, ?string $date = null, string $reason = 'Penyesuaian data enrollment'): ?Journal
    {
        $date ??= now()->toDateString();
        $target = $this->targetPosition($enrollment);
        $posted = $this->postedPosition($enrollment);

        // Guard: masih ada jurnal pembayaran lama untuk enrollment ini yang
        // belum di-link (enrollment_id NULL). Kalau diteruskan, postedPosition
        // menghitungnya 0 -> reconcile bisa dobel-posting. Minta backfill dulu.
        $legacyRefs = collect(['PAYMENT-ENROLL-'.$enrollment->id, 'MANUAL-EXPIRY-'.$enrollment->id])
            ->merge($enrollment->installments()->pluck('id')->map(fn ($i) => 'INSTALLMENT-'.$i))
            ->all();
        if (Journal::whereNull('enrollment_id')->whereIn('reference', $legacyRefs)->exists()) {
            throw new DomainException(
                'Masih ada jurnal pembayaran untuk enrollment ini yang belum terhubung. '
                .'Jalankan `php artisan journals:link-enrollments` dulu.'
            );
        }

        // Guard: kas yang SUDAH tercatat lebih besar dari yang seharusnya.
        // Auto-adjust akan "menghapus" uang riil dari buku — itu harus lewat
        // flow refund. Terjadi kalau: (a) siswa kelebihan bayar, atau (b) admin
        // menandai cicilan yang sudah ada jurnal kas-nya jadi "belum lunas".
        if (bccomp(bcsub($posted['cash'], $target['cash'], 2), self::EPS, 2) > 0) {
            throw new DomainException(
                "Kas yang sudah tercatat untuk enrollment ini (Rp {$posted['cash']}) lebih besar dari yang seharusnya (Rp {$target['cash']}). "
                .'Kurangi kas yang diterima berarti refund — selesaikan lewat flow refund/kredit, bukan dari form ini.'
            );
        }

        $items = [];
        $this->deltaItem($items, AccountCode::BANK->value, $target['cash'], $posted['cash'], true);
        $this->deltaItem($items, AccountCode::ACCOUNTS_RECEIVABLE->value, $target['receivable'], $posted['receivable'], true);
        $this->deltaItem($items, AccountCode::DEFERRED_REVENUE->value, $target['deferred'], $posted['deferred'], false);
        $this->deltaItem($items, AccountCode::REVENUE_TUITION_FEES->value, $target['revenue'], $posted['revenue'], false);

        if (empty($items)) {
            return null;
        }

        $seq = Journal::where('enrollment_id', $enrollment->id)
            ->where('reference', 'like', 'ENR-SYNC-'.$enrollment->id.'-%')
            ->count() + 1;

        return $this->accounting->createJournal(
            $date,
            "Sinkronisasi buku besar enrollment #{$enrollment->id} — {$reason}",
            "ENR-SYNC-{$enrollment->id}-{$seq}",
            $items,
            'adjustment',
            $enrollment->program_id,
            $enrollment->id,
        );
    }

    /**
     * Tambahkan satu baris jurnal untuk selisih pada satu akun, kalau selisihnya
     * melebihi ambang. $debitNatural = true untuk akun bersaldo debit (Kas,
     * Piutang); false untuk akun bersaldo kredit (Deferred, Pendapatan).
     */
    private function deltaItem(array &$items, string $accountCode, string $target, string $posted, bool $debitNatural): void
    {
        $delta = bcsub($target, $posted, 2); // + berarti perlu menambah saldo normal akun
        if (bccomp(self::absDiff($delta, '0'), self::EPS, 2) < 0) {
            return;
        }

        $increase = bccomp($delta, '0', 2) > 0;
        $amount = $increase ? $delta : bcmul($delta, '-1', 2);

        // Naikkan saldo debit-natural -> debit; turunkan -> kredit. Sebaliknya utk kredit-natural.
        $onDebit = $debitNatural ? $increase : ! $increase;

        $items[] = [
            'account_code' => $accountCode,
            'debit' => $onDebit ? $amount : '0',
            'credit' => $onDebit ? '0' : $amount,
        ];
    }

    private static function absDiff(string $a, string $b): string
    {
        $d = bcsub($a, $b, 2);

        return bccomp($d, '0', 2) < 0 ? bcmul($d, '-1', 2) : $d;
    }

    // ==========================================================================
    //  Sinkronisasi data OPERASIONAL terhadap kas yang benar-benar sudah masuk
    //  ke buku besar (dipakai untuk membereskan data migrasi yang salah label).
    // ==========================================================================

    /**
     * Kas bersih yang sudah diposting untuk enrollment ini (akun 1001/1002).
     */
    public function postedCash(Enrollment $enrollment): string
    {
        return $this->postedPosition($enrollment)['cash'];
    }

    /**
     * Bereskan data operasional (payment_method, baris cicilan, payment_status)
     * supaya cocok dengan kas yang SUDAH tercatat di buku besar. Tidak menyentuh
     * jurnal sama sekali — dipakai untuk data lama yang salah label (mis. hasil
     * migrasi menandai "full upfront" padahal baru dibayar sebagian).
     *
     * Caller WAJIB sudah lockForUpdate() row Enrollment.
     *
     * @return string[] daftar perubahan yang dilakukan (kosong = sudah beres)
     */
    public function reconcileOperational(Enrollment $enrollment): array
    {
        $changes = [];
        $enrollment->loadMissing('program');
        $cash = $this->postedCash($enrollment);
        $total = bcadd((string) ($enrollment->total_amount ?? '0'), '0', 2);

        if ($enrollment->payment_method === 'installment') {
            // Tandai cicilan lunas (paling awal dulu) sampai jumlah yang lunas
            // ≈ kas yang sudah masuk.
            $installments = $enrollment->installments()->orderBy('due_date')->orderBy('id')->get();
            $paidSoFar = '0';
            foreach ($installments as $inst) {
                $shouldBePaid = bccomp(bcadd($paidSoFar, (string) $inst->amount, 2), bcadd($cash, self::EPS, 2), 2) <= 0
                    && bccomp($cash, $paidSoFar, 2) > 0;
                if ($shouldBePaid && ! $inst->paid_at) {
                    $inst->update(['paid_at' => $inst->due_date]);
                    $changes[] = "cicilan #{$inst->id} ditandai lunas (Rp {$inst->amount})";
                }
                if ($shouldBePaid || $inst->paid_at) {
                    $paidSoFar = bcadd($paidSoFar, (string) $inst->amount, 2);
                }
            }
        } elseif (bccomp($cash, bcsub($total, self::EPS, 2), 2) < 0 && bccomp($cash, '0', 2) >= 0) {
            // "full upfront" tapi kas yang masuk < total kontrak -> ini sebenarnya
            // pembayaran cicilan. Ubah ke installment: satu baris lunas sebesar
            // yang sudah dibayar + satu baris belum lunas sebesar sisanya.
            $enrollment->installments()->delete();
            $edate = optional($enrollment->enrollment_date)->toDateString() ?? now()->toDateString();
            $xdate = optional($enrollment->expiry_date)->toDateString() ?? $edate;

            if (bccomp($cash, '0', 2) > 0) {
                Installment::create([
                    'enrollment_id' => $enrollment->id,
                    'amount' => $cash,
                    'due_date' => $edate,
                    'paid_at' => $edate,
                    'payment_channel' => $enrollment->payment_channel,
                ]);
            }
            $remainder = bcsub($total, $cash, 2);
            if (bccomp($remainder, self::EPS, 2) > 0) {
                Installment::create([
                    'enrollment_id' => $enrollment->id,
                    'amount' => $remainder,
                    'due_date' => $xdate,
                    'paid_at' => null,
                    'payment_channel' => $enrollment->payment_channel,
                ]);
            }
            $enrollment->payment_method = 'installment';
            $enrollment->save();
            $changes[] = "metode diubah ke installment (dibayar Rp {$cash} dari Rp {$total})";
        }

        // Selaraskan payment_status
        $paid = $enrollment->payment_method === 'installment'
            ? (string) $enrollment->installments()->whereNotNull('paid_at')->sum('amount')
            : $total;
        $status = bccomp($paid, self::EPS, 2) < 0
            ? PaymentStatus::PENDING->value
            : (bccomp($paid, bcsub($total, self::EPS, 2), 2) >= 0
                ? PaymentStatus::FULL->value
                : PaymentStatus::PARTIAL->value);
        if ($enrollment->payment_status !== $status) {
            $old = $enrollment->payment_status;
            $enrollment->update(['payment_status' => $status]);
            $changes[] = "payment_status {$old} -> {$status}";
        }

        return $changes;
    }
}
