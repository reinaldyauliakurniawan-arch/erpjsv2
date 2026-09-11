<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber TUNGGAL rumus laporan keuangan: Laba–Rugi (P&L), Neraca (Balance
 * Sheet), Arus Kas (Cash Flow) — metode tidak langsung.
 *
 * SEMUA angka dihitung LIVE dari `journal_items` setiap dipanggil — tidak ada
 * cache, tidak ada kolom saldo tersimpan. Dipakai bersama oleh halaman Laporan
 * (App\Http\Controllers\Admin\ReportController), Dashboard CFO (FinanceController),
 * dan Laporan Perubahan Ekuitas (EquityStatementController): kalau rumus
 * akuntansi berubah, cukup diubah di sini.
 *
 * PRINSIP SALDO NORMAL (konsisten di semua rumus):
 *   - Asset, Expense  → saldo normal DEBIT   → saldo = Σdebit − Σkredit
 *   - Liability, Equity, Revenue → saldo normal KREDIT → saldo = Σkredit − Σdebit
 *   - Akun contra-revenue (kode 4111) tetap type Revenue tapi bersaldo DEBIT
 *     (potongan/diskon) → SELALU DIKURANGKAN dari pendapatan & laba.
 *
 * IDENTITAS ARUS KAS (dijamin di semua skenario):
 *   Karena setiap jurnal balance (Σdebit = Σkredit), perubahan saldo kas selama
 *   suatu periode = −Σ(debit − kredit) dari SEMUA akun NON-kas = Σ(kredit − debit)
 *   akun non-kas. Maka: cashOpening + netChange === cashEnding (persis).
 */
class FinancialReportService
{
    /** Akun contra-revenue (potongan/diskon) — bersaldo debit, mengurangi pendapatan. */
    public const CONTRA_REVENUE_CODES = ['4111'];

    /** Akun kas & bank (Asset, saldo debit). */
    public const CASH_CODES = ['1001', '1002'];

    /**
     * Tanggal jauh sebelum transaksi pertama — dipakai untuk akumulasi "sejak
     * awal" (netProfitToDate, laba ditahan di balanceSheet(), periode "Semua
     * Waktu"). HARUS selalu <= tanggal jurnal paling lama di database, atau
     * Neraca bisa tidak balance: balanceSheet() menjumlah saldo Asset/
     * Liability/Equity TANPA batas bawah tanggal, tapi melipat laba/rugi ke
     * ekuitas hanya dari LEDGER_INCEPTION dan seterusnya — kalau ada jurnal
     * (migrasi data lama, salah input tanggal, dsb) yang lebih tua dari
     * konstanta ini DAN menyentuh akun Revenue/Expense, dampaknya masuk ke
     * Aset/Liabilitas tapi tidak ke Ekuitas → Neraca selisih. 1900-01-01 dipilih
     * sebagai margin aman jauh di luar rentang tanggal bisnis yang masuk akal.
     */
    private const LEDGER_INCEPTION = '1900-01-01';

    /**
     * Terjemahkan preset periode + custom range menjadi [from, to] + label +
     * granularitas grafik. Preset: today | week | month | quarter | year | custom.
     *
     * @return array{from:string, to:string, label:string, granularity:string, period:string}
     */
    public function resolvePeriod(?string $period, ?string $from = null, ?string $to = null): array
    {
        $now = now();
        $period = $period ?: 'month';

        if ($period === 'custom' && $from && $to) {
            $f = Carbon::parse(min($from, $to))->toDateString();
            $t = Carbon::parse(max($from, $to))->toDateString();
            $label = Carbon::parse($f)->translatedFormat('d M Y').' – '.Carbon::parse($t)->translatedFormat('d M Y');
        } else {
            [$f, $t, $label] = match ($period) {
                'today' => [$now->copy()->startOfDay()->toDateString(), $now->copy()->endOfDay()->toDateString(), 'Hari Ini'],
                'week' => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString(), 'Minggu Ini'],
                'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(), 'Bulan Lalu'],
                'quarter' => [$now->copy()->startOfQuarter()->toDateString(), $now->copy()->endOfQuarter()->toDateString(), 'Kuartal Ini'],
                'year' => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString(), 'Tahun Ini'],
                'all' => [self::LEDGER_INCEPTION, $now->copy()->endOfDay()->toDateString(), 'Semua Waktu'],
                default => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString(), 'Bulan Ini'],
            };
            $period = in_array($period, ['today', 'week', 'month', 'last_month', 'quarter', 'year', 'all']) ? $period : 'month';
        }

        $days = Carbon::parse($f)->diffInDays(Carbon::parse($t)) + 1;
        // Bucket bulanan di-cap 60 (5 tahun) di bucketLabels() — rentang lebih
        // lebar dari itu (mis. periode "Semua Waktu" yang start-nya jauh di
        // masa lalu) HARUS pindah ke granularitas tahunan, supaya setiap
        // transaksi tetap kebagian bucket (tidak diam-diam hilang dari
        // trendSeries/cashFlowSeries — lihat bucketLabels()).
        $granularity = $days <= 62 ? 'day' : ($days <= 60 * 31 ? 'month' : 'year');

        return ['from' => $f, 'to' => $t, 'label' => $label, 'granularity' => $granularity, 'period' => $period];
    }

    // ── LABA–RUGI ──────────────────────────────────────────────────────────

    /**
     * @return array{rows:Collection, totalRevenue:float, totalContra:float, totalExpense:float, netRevenue:float, netProfit:float}
     */
    public function profitLoss(string $from, string $to): array
    {
        $rows = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.type', ['Revenue', 'Expense'])
            ->whereBetween('journals.date', [$from, $to])
            ->selectRaw("
                accounts.id, accounts.code, accounts.name, accounts.type,
                SUM(CASE WHEN accounts.type = 'Revenue' THEN (journal_items.credit - journal_items.debit) ELSE (journal_items.debit - journal_items.credit) END) as amount
            ")
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();

        $totalRevenue = (float) $rows->where('type', 'Revenue')->whereNotIn('code', self::CONTRA_REVENUE_CODES)->sum('amount');

        // Contra-revenue bersaldo DEBIT: `amount` (credit − debit) negatif. Balik
        // tandanya jadi POSITIF supaya konsisten "dikurangkan" di mana pun.
        $totalContra = -1 * (float) $rows->where('type', 'Revenue')->whereIn('code', self::CONTRA_REVENUE_CODES)->sum('amount');

        $totalExpense = (float) $rows->where('type', 'Expense')->sum('amount');

        $netRevenue = $totalRevenue - $totalContra;
        $netProfit = $netRevenue - $totalExpense;

        return compact('rows', 'totalRevenue', 'totalContra', 'totalExpense', 'netRevenue', 'netProfit');
    }

    /** Pendapatan bersih (bruto − contra) untuk sebuah periode. */
    public function netRevenue(string $from, string $to): float
    {
        return (float) $this->profitLoss($from, $to)['netRevenue'];
    }

    /** Laba bersih akumulatif dari awal pembukuan sampai tanggal tertentu. */
    public function netProfitToDate(string $asOf): float
    {
        return (float) $this->profitLoss(self::LEDGER_INCEPTION, $asOf)['netProfit'];
    }

    // ── LABOR EFFICIENCY RATIO (konsep Greg Crabtree, "Simple Numbers") ─────

    /** Akun biaya tutor (Direct Labor): honor tutor lepas (5001) + gaji tutor tetap (5006). */
    public const DIRECT_LABOR_CODES = ['5001', '5006'];

    /**
     * DLER (Direct Labor Efficiency Ratio) = Margin Kotor ÷ Biaya Tenaga Kerja
     * Langsung (tutor), untuk periode `$from`–`$to`.
     *
     * Just Speak adalah bisnis jasa (mengajar) — tidak ada Cost of Goods Sold
     * di luar biaya tutor (dicek: tidak ada akun HPP non-tutor yang pernah
     * dipakai di buku besar). Maka, persis seperti model Crabtree untuk bisnis
     * jasa (biaya tenaga kerja langsung ADALAH "cost of sales"-nya):
     *
     *     Margin Kotor = Pendapatan Bersih − Biaya Tutor
     *     DLER         = Margin Kotor ÷ Biaya Tutor
     *
     * DLER adalah RASIO ("2.3x"), bukan persentase — istilah asli Crabtree
     * "power rating". `dler` bernilai `null` kalau belum ada biaya tutor
     * tercatat di periode ini (pembagi nol, bukan "efisiensi sempurna").
     *
     * MLER (Management Labor Efficiency Ratio) SENGAJA tidak dihitung di sini
     * — akun gaji admin/manajemen di chart of accounts belum pernah dipakai
     * di buku besar sama sekali (lihat AUDIT_REPORT.md untuk detail temuan).
     *
     * @return array{net_revenue:float, direct_labor_cost:float, gross_margin:float, dler:?float}
     */
    public function directLaborEfficiency(string $from, string $to): array
    {
        $pl = $this->profitLoss($from, $to);
        $netRevenue = (float) $pl['netRevenue'];

        $directLaborCost = (float) $pl['rows']
            ->where('type', 'Expense')
            ->whereIn('code', self::DIRECT_LABOR_CODES)
            ->sum('amount');

        $grossMargin = $netRevenue - $directLaborCost;
        $dler = $directLaborCost > 0.0 ? round($grossMargin / $directLaborCost, 2) : null;

        return [
            'net_revenue' => $netRevenue,
            'direct_labor_cost' => $directLaborCost,
            'gross_margin' => $grossMargin,
            'dler' => $dler,
        ];
    }

    /**
     * MLER (Management Labor Efficiency Ratio) = Contribution Margin ÷ Biaya
     * Tenaga Kerja Manajemen/Admin, untuk periode `$from`–`$to`.
     *
     * SENGAJA SELALU mengembalikan `null` untuk saat ini: akun gaji
     * admin/manajemen di chart of accounts (`5002 Beban Gaji Karyawan`)
     * belum pernah dipakai mencatat transaksi sama sekali di buku besar
     * (dicek langsung ke `journal_items` — 0 baris). Menghitung MLER
     * sekarang akan selalu membagi dengan nol dan MENYESATKAN (bukan berarti
     * manajemen "sangat efisien"). Lihat AUDIT_REPORT.md untuk detail temuan
     * & rekomendasi ke CFO. Begitu gaji staff admin mulai dicatat sebagai
     * jurnal bulanan ke akun yang jelas, isi method ini dengan rumus
     * sesungguhnya: Contribution Margin ÷ Biaya Tenaga Kerja Manajemen.
     */
    public function managementLaborEfficiency(string $from, string $to): ?float
    {
        return null;
    }

    /**
     * Gabungkan DLER + MLER jadi Total LER (Labor Efficiency Ratio) — konsep
     * Greg Crabtree: kesimpulan akhir efisiensi tenaga kerja bisnis secara
     * keseluruhan. `null` kalau SALAH SATU komponen belum bisa dihitung —
     * supaya tidak diam-diam menampilkan DLER-saja sebagai Total LER yang
     * menyesatkan (harus terlihat jelas belum lengkap, bukan disembunyikan).
     * Logic null-propagation ini terpusat di sini, bukan diduplikasi di view.
     */
    public function combineLaborEfficiency(?float $dler, ?float $mler): ?float
    {
        return ($dler !== null && $mler !== null) ? round($dler + $mler, 2) : null;
    }

    /**
     * Ringkasan Labor Efficiency Ratio lengkap untuk Dashboard Finance:
     * rincian DLER (dari `directLaborEfficiency()`) + `mler` + `total_ler`
     * (DLER + MLER, lewat `combineLaborEfficiency()`).
     *
     * @return array{net_revenue:float, direct_labor_cost:float, gross_margin:float, dler:?float, mler:?float, total_ler:?float}
     */
    public function laborEfficiency(string $from, string $to): array
    {
        $dlerResult = $this->directLaborEfficiency($from, $to);
        $mler = $this->managementLaborEfficiency($from, $to);
        $totalLer = $this->combineLaborEfficiency($dlerResult['dler'], $mler);

        return $dlerResult + ['mler' => $mler, 'total_ler' => $totalLer];
    }

    // ── NERACA ─────────────────────────────────────────────────────────────

    /**
     * Neraca per tanggal `$asOf`. Ekuitas SUDAH termasuk laba/rugi akumulatif
     * sejak awal pembukuan (retained + berjalan) — tanpa jurnal penutup, laba
     * masih "menempel" di akun Revenue/Expense, jadi harus dilipat ke ekuitas
     * di sini supaya Neraca SELALU balance (Asset = Liability + Equity).
     *
     * @return array{rows:Collection, totalAsset:float, totalLiability:float, totalEquity:float, equityContributed:float, retainedAndCurrent:float, netProfitCurrentYear:float, isBalanced:bool}
     */
    public function balanceSheet(string $asOf): array
    {
        $rows = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.type', ['Asset', 'Liability', 'Equity'])
            ->whereDate('journals.date', '<=', $asOf)
            ->selectRaw('
                accounts.id, accounts.code, accounts.name, accounts.type,
                SUM(journal_items.debit) as total_debit,
                SUM(journal_items.credit) as total_credit
            ')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($r) {
                // Asset: saldo debit. Liability/Equity: saldo kredit.
                $r->balance = $r->type === 'Asset'
                    ? ($r->total_debit - $r->total_credit)
                    : ($r->total_credit - $r->total_debit);

                return $r;
            })
            ->filter(fn ($r) => round($r->balance, 2) != 0.0)
            ->values();

        $totalAsset = (float) $rows->where('type', 'Asset')->sum('balance');
        $totalLiability = (float) $rows->where('type', 'Liability')->sum('balance');
        $equityContributed = (float) $rows->where('type', 'Equity')->sum('balance');

        // Laba akumulatif SEJAK AWAL → masuk ekuitas. Rumus identik dengan P&L.
        $retainedAndCurrent = $this->netProfitToDate($asOf);
        $netProfitCurrentYear = (float) $this->profitLoss(Carbon::parse($asOf)->startOfYear()->toDateString(), $asOf)['netProfit'];

        $totalEquity = $equityContributed + $retainedAndCurrent;

        // Baris sintetis supaya tabel Neraca ikut menjumlah ke total ekuitas.
        if (round($retainedAndCurrent, 2) != 0.0) {
            $rows->push((object) [
                'id' => null,
                'code' => '3999',
                'name' => 'Laba Ditahan & Laba Berjalan',
                'type' => 'Equity',
                'total_debit' => 0,
                'total_credit' => 0,
                'balance' => $retainedAndCurrent,
            ]);
            $rows = $rows->sortBy('code')->values();
        }

        $isBalanced = abs($totalAsset - ($totalLiability + $totalEquity)) < 0.01;

        return compact(
            'rows', 'totalAsset', 'totalLiability', 'totalEquity',
            'equityContributed', 'retainedAndCurrent', 'netProfitCurrentYear', 'isBalanced'
        );
    }

    // ── ARUS KAS (metode tidak langsung) ──────────────────────────────────

    /**
     * Arus kas metode tidak langsung.
     *
     * Operasi   = Laba Bersih + Penyusutan (non-kas) + Δ modal kerja
     *             (Σ kredit−debit akun operating NON-kas selain Revenue/Expense).
     * Investasi = Σ kredit−debit akun investing NON-kas (5108/1006 saling
     *             meniadakan → penyusutan otomatis ter-exclude; sisanya =
     *             belanja/pelepasan aset tetap).
     * Pendanaan = Σ kredit−debit akun financing (Equity: setoran/prive).
     *
     * netChange dihitung dari SALDO KAS AKTUAL (cashEnding − cashOpening) supaya
     * DIJAMIN benar. `unclassified` menangkap selisih kalau ada akun non-kas
     * yang belum diberi cash_flow_category atau jurnal yang tidak balance.
     *
     * @return array{operating:Collection, investing:Collection, financing:Collection, netProfit:float, depreciationAddBack:float, workingCapital:Collection, netWorkingCapital:float, netOperating:float, netInvesting:float, netFinancing:float, netChange:float, unclassified:float, cashOpening:float, cashEnding:float, cashDifference:float}
     */
    public function cashFlow(string $from, string $to): array
    {
        // Mutasi SETIAP akun non-kas selama periode, TANDA SERAGAM (kredit −
        // debit) = "dampak kas"-nya. Kategori diturunkan di PHP supaya laporan
        // TAHAN kalau `cash_flow_category` di DB kosong/salah.
        $nonCash = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereBetween('journals.date', [$from, $to])
            ->whereNotIn('accounts.code', self::CASH_CODES)
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, accounts.cash_flow_category,
                SUM(journal_items.credit) - SUM(journal_items.debit) as net')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.cash_flow_category')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($r) {
                $r->net = (float) $r->net;
                $r->cf = $this->cashFlowCategory($r);

                return $r;
            })
            ->filter(fn ($r) => round($r->net, 2) != 0.0);

        // ── Operasi (metode tidak langsung) ──
        $netProfit = (float) $this->profitLoss($from, $to)['netProfit'];

        // Penyusutan/amortisasi = beban NON-kas → ditambahkan kembali ke laba.
        $depreciationAddBack = -1 * (float) $nonCash
            ->where('type', 'Expense')->where('cf', 'investing')->sum('net');

        // Perubahan modal kerja = akun OPERATING yang Asset/Liability
        // (Piutang, Pendapatan Diterima Dimuka, Utang Tutor, prepaid dll).
        $workingCapital = $nonCash
            ->where('cf', 'operating')
            ->whereIn('type', ['Asset', 'Liability'])
            ->sortBy('code')
            ->values();
        $netWorkingCapital = (float) $workingCapital->sum('net');

        $netOperating = round($netProfit + $depreciationAddBack + $netWorkingCapital, 2);

        $operating = collect([
            (object) ['name' => 'Laba Bersih Periode', 'net' => round($netProfit, 2), 'code' => '', 'synthetic' => true],
        ]);
        if (round($depreciationAddBack, 2) != 0.0) {
            $operating->push((object) ['name' => 'Penyusutan & amortisasi (non-kas)', 'net' => round($depreciationAddBack, 2), 'code' => '', 'synthetic' => true]);
        }
        foreach ($workingCapital as $r) {
            $label = $r->net >= 0 ? "Kenaikan {$r->name}" : "Penurunan {$r->name}";
            $operating->push((object) ['name' => $label, 'net' => round($r->net, 2), 'code' => $r->code, 'synthetic' => false]);
        }

        // ── Investasi & Pendanaan ──
        $investing = $nonCash->where('cf', 'investing')->sortBy('code')->values();
        $financing = $nonCash->where('cf', 'financing')->sortBy('code')->values();

        $netInvesting = round((float) $investing->sum('net'), 2);
        $netFinancing = round((float) $financing->sum('net'), 2);

        // ── netChange dari saldo kas AKTUAL (dijamin benar) ──
        $cashOpening = $this->cashBalanceBefore($from);
        $cashEnding = $this->cashBalanceAsOf($to);
        $netChange = round($cashEnding - $cashOpening, 2);

        $unclassified = round($netChange - ($netOperating + $netInvesting + $netFinancing), 2);
        $cashDifference = $unclassified; // dipakai view untuk peringatan

        return compact(
            'operating', 'investing', 'financing',
            'netProfit', 'depreciationAddBack', 'workingCapital', 'netWorkingCapital',
            'netOperating', 'netInvesting', 'netFinancing',
            'netChange', 'unclassified', 'cashOpening', 'cashEnding', 'cashDifference'
        );
    }

    /**
     * Kategori arus kas satu akun, TAHAN kalau `cash_flow_category` di DB
     * kosong/salah: pakai nilai DB kalau valid, kalau tidak turunkan dari tipe
     * & nama akun (standar: Ekuitas→financing, aset tetap & beban penyusutan→
     * investing, sisanya→operating).
     */
    private function cashFlowCategory(object $account): string
    {
        $cat = strtolower(trim((string) ($account->cash_flow_category ?? '')));
        if (in_array($cat, ['operating', 'investing', 'financing'], true)) {
            return $cat;
        }
        if ($account->type === 'Equity') {
            return 'financing';
        }
        $name = mb_strtolower((string) $account->name);
        foreach (['penyusutan', 'depresiasi', 'amortisasi', 'aset tetap', 'aktiva tetap', 'peralatan', 'kendaraan', 'gedung', 'bangunan'] as $needle) {
            if (str_contains($name, $needle)) {
                return 'investing';
            }
        }

        return 'operating';
    }

    /** Saldo kas & bank sampai dengan tanggal tertentu (null = seluruh buku besar). */
    public function cashBalanceAsOf(?string $asOf = null): float
    {
        return (float) (DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.code', self::CASH_CODES)
            ->when($asOf, fn ($q) => $q->whereDate('journals.date', '<=', $asOf))
            ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
            ->value('balance') ?? 0);
    }

    /** Saldo kas & bank SEBELUM tanggal tertentu. */
    public function cashBalanceBefore(string $date): float
    {
        return (float) (DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.code', self::CASH_CODES)
            ->whereDate('journals.date', '<', $date)
            ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
            ->value('balance') ?? 0);
    }

    // ── SERI GRAFIK (tren dari waktu ke waktu dalam periode) ────────────────

    /**
     * Tren Pendapatan (bersih) vs Beban vs Laba Bersih per sub-periode.
     *
     * @return array{labels:string[], revenue:float[], expense:float[], netProfit:float[]}
     */
    public function trendSeries(string $from, string $to, string $granularity): array
    {
        $buckets = $this->bucketLabels($from, $to, $granularity);
        $keys = array_keys($buckets);
        $grossRev = array_fill_keys($keys, 0.0);
        $contra = array_fill_keys($keys, 0.0);
        $expense = array_fill_keys($keys, 0.0);

        $lines = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.type', ['Revenue', 'Expense'])
            ->whereBetween('journals.date', [$from, $to])
            ->get(['journals.date', 'accounts.type', 'accounts.code', 'journal_items.debit', 'journal_items.credit']);

        foreach ($lines as $l) {
            $key = $this->bucketKey((string) $l->date, $granularity);
            if (! array_key_exists($key, $grossRev)) {
                continue;
            }
            if ($l->type === 'Revenue') {
                if (in_array($l->code, self::CONTRA_REVENUE_CODES)) {
                    // Contra bersaldo debit → akumulasi sebagai potongan POSITIF.
                    $contra[$key] += (float) $l->debit - (float) $l->credit;
                } else {
                    $grossRev[$key] += (float) $l->credit - (float) $l->debit;
                }
            } else {
                $expense[$key] += (float) $l->debit - (float) $l->credit;
            }
        }

        $revenue = [];
        $exp = [];
        $netProfit = [];
        foreach ($keys as $k) {
            $netRev = $grossRev[$k] - $contra[$k];
            $revenue[] = round($netRev, 2);
            $exp[] = round($expense[$k], 2);
            $netProfit[] = round($netRev - $expense[$k], 2);
        }

        return ['labels' => array_values($buckets), 'revenue' => $revenue, 'expense' => $exp, 'netProfit' => $netProfit];
    }

    /**
     * Arus kas bersih AKTUAL per sub-periode (langsung dari mutasi akun kas).
     * Jumlah seluruh bucket = cashEnding − cashOpening = netChange.
     *
     * @return array{labels:string[], net:float[]}
     */
    public function cashFlowSeries(string $from, string $to, string $granularity): array
    {
        $buckets = $this->bucketLabels($from, $to, $granularity);
        $keys = array_keys($buckets);
        $net = array_fill_keys($keys, 0.0);

        $lines = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.code', self::CASH_CODES)
            ->whereBetween('journals.date', [$from, $to])
            ->get(['journals.date', 'journal_items.debit', 'journal_items.credit']);

        foreach ($lines as $l) {
            $key = $this->bucketKey((string) $l->date, $granularity);
            if (! array_key_exists($key, $net)) {
                continue;
            }
            // Kas = Asset saldo debit: debit menaikkan, kredit menurunkan.
            $net[$key] += (float) $l->debit - (float) $l->credit;
        }

        return [
            'labels' => array_values($buckets),
            'net' => array_map(fn ($k) => round($net[$k], 2), $keys),
        ];
    }

    /** @return array<string,string> bucketKey => label, urut kronologis */
    private function bucketLabels(string $from, string $to, string $granularity): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $out = [];

        if ($granularity === 'day') {
            $cursor = $start->copy();
            while ($cursor->lte($end) && count($out) < 62) {
                $out[$cursor->toDateString()] = $cursor->translatedFormat('d M');
                $cursor->addDay();
            }
        } elseif ($granularity === 'year') {
            // Rentang sangat lebar (mis. "Semua Waktu") — satu bucket per
            // tahun kalender supaya SETIAP transaksi di [$from,$to] pasti
            // kebagian bucket, berapa pun panjang rentangnya.
            $cursor = $start->copy()->startOfYear();
            while ($cursor->lte($end) && count($out) < 200) {
                $out[$cursor->format('Y')] = $cursor->format('Y');
                $cursor->addYear();
            }
        } else {
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end) && count($out) < 60) {
                $out[$cursor->format('Y-m')] = $cursor->translatedFormat('M Y');
                $cursor->addMonthNoOverflow();
            }
        }

        return $out;
    }

    private function bucketKey(string $date, string $granularity): string
    {
        return match ($granularity) {
            'day' => substr($date, 0, 10),
            'year' => substr($date, 0, 4),
            default => substr($date, 0, 7),
        };
    }
}
