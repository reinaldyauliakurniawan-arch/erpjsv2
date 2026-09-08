<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber TUNGGAL rumus laporan keuangan: Laba–Rugi (P&L), Neraca (Balance
 * Sheet), Arus Kas (Cash Flow).
 *
 * SEMUA angka dihitung LIVE dari `journal_items` setiap dipanggil — tidak ada
 * cache, tidak ada kolom saldo tersimpan. Dipakai bersama oleh halaman Laporan
 * (App\Http\Controllers\Admin\ReportController) dan Dashboard CFO
 * (FinanceController): kalau rumus akuntansi berubah, cukup diubah di sini dan
 * semua laporan otomatis konsisten.
 */
class FinancialReportService
{
    /** Akun contra-revenue (potongan/diskon) — diperlakukan sama dengan ReportController. */
    private const CONTRA_REVENUE_CODES = ['4111'];

    /** Akun kas & bank. */
    public const CASH_CODES = ['1001', '1002'];

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
            // Normalisasi: pastikan from <= to.
            $f = Carbon::parse(min($from, $to))->toDateString();
            $t = Carbon::parse(max($from, $to))->toDateString();
            $label = Carbon::parse($f)->translatedFormat('d M Y').' – '.Carbon::parse($t)->translatedFormat('d M Y');
        } else {
            [$f, $t, $label] = match ($period) {
                'today' => [$now->copy()->startOfDay()->toDateString(), $now->copy()->endOfDay()->toDateString(), 'Hari Ini'],
                'week' => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString(), 'Minggu Ini'],
                'quarter' => [$now->copy()->startOfQuarter()->toDateString(), $now->copy()->endOfQuarter()->toDateString(), 'Kuartal Ini'],
                'year' => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString(), 'Tahun Ini'],
                default => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString(), 'Bulan Ini'],
            };
            $period = in_array($period, ['today', 'week', 'month', 'quarter', 'year']) ? $period : 'month';
        }

        $days = Carbon::parse($f)->diffInDays(Carbon::parse($t)) + 1;
        $granularity = $days <= 62 ? 'day' : 'month';

        return ['from' => $f, 'to' => $t, 'label' => $label, 'granularity' => $granularity, 'period' => $period];
    }

    // ── LABA–RUGI ──────────────────────────────────────────────────────────

    /**
     * @return array{rows:Collection, totalRevenue:float, totalContra:float, totalExpense:float, netProfit:float}
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
        $totalContra = (float) $rows->where('type', 'Revenue')->whereIn('code', self::CONTRA_REVENUE_CODES)->sum('amount');
        $totalExpense = (float) $rows->where('type', 'Expense')->sum('amount');
        $netProfit = $totalRevenue - $totalContra - $totalExpense;

        return compact('rows', 'totalRevenue', 'totalContra', 'totalExpense', 'netProfit');
    }

    /** Pendapatan bersih (bruto − contra) untuk sebuah periode. */
    public function netRevenue(string $from, string $to): float
    {
        $pl = $this->profitLoss($from, $to);

        return (float) $pl['totalRevenue'] - (float) $pl['totalContra'];
    }

    // ── NERACA ─────────────────────────────────────────────────────────────

    /**
     * @return array{rows:Collection, totalAsset:float, totalLiability:float, totalEquity:float, netProfitCurrent:float}
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
                $r->balance = $r->type === 'Asset'
                    ? ($r->total_debit - $r->total_credit)
                    : ($r->total_credit - $r->total_debit);

                return $r;
            })
            ->filter(fn ($r) => $r->balance != 0)
            ->values();

        $totalAsset = (float) $rows->where('type', 'Asset')->sum('balance');
        $totalLiability = (float) $rows->where('type', 'Liability')->sum('balance');
        $totalEquity = (float) $rows->where('type', 'Equity')->sum('balance');

        // Laba berjalan tahun ini masuk ke ekuitas — rumus IDENTIK dengan P&L.
        $yearStart = Carbon::parse($asOf)->startOfYear()->toDateString();
        $netProfitCurrent = (float) $this->profitLoss($yearStart, $asOf)['netProfit'];
        $totalEquity += $netProfitCurrent;

        return compact('rows', 'totalAsset', 'totalLiability', 'totalEquity', 'netProfitCurrent');
    }

    // ── ARUS KAS ───────────────────────────────────────────────────────────

    /**
     * @return array{operating:Collection, investing:Collection, financing:Collection, netOperating:float, netInvesting:float, netFinancing:float, netChange:float, cashOpening:float, cashEnding:float, cashDifference:float}
     */
    public function cashFlow(string $from, string $to): array
    {
        $getLines = function (string $category) use ($from, $to) {
            return DB::table('journal_items')
                ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
                ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
                ->where('accounts.cash_flow_category', $category)
                ->whereBetween('journals.date', [$from, $to])
                ->selectRaw('
                    accounts.id, accounts.code, accounts.name, accounts.type,
                    SUM(journal_items.debit)  as total_debit,
                    SUM(journal_items.credit) as total_credit
                ')
                ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
                ->orderBy('accounts.code')
                ->get()
                ->map(function ($r) {
                    $isDebitNormal = in_array($r->type, ['Asset', 'Expense']);
                    $r->net = $isDebitNormal
                        ? ((float) $r->total_debit - (float) $r->total_credit)
                        : ((float) $r->total_credit - (float) $r->total_debit);

                    return $r;
                });
        };

        $operating = $getLines('operating');
        $investing = $getLines('investing');
        $financing = $getLines('financing');

        $netOperating = (float) $operating->sum('net');
        $netInvesting = (float) $investing->sum('net');
        $netFinancing = (float) $financing->sum('net');
        $netChange = $netOperating + $netInvesting + $netFinancing;

        $cashOpening = $this->cashBalanceBefore($from);
        $cashEnding = $this->cashBalanceAsOf($to);
        $cashDifference = round($cashEnding - ($cashOpening + $netChange), 2);

        return compact(
            'operating', 'investing', 'financing',
            'netOperating', 'netInvesting', 'netFinancing',
            'netChange', 'cashOpening', 'cashEnding', 'cashDifference'
        );
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
     * Tren Revenue vs Expense vs Net Profit per sub-periode (harian/bulanan).
     * Bucket dilakukan di PHP supaya portabel (tidak pakai DATE_FORMAT/strftime).
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
                $amt = (float) $l->credit - (float) $l->debit;
                if (in_array($l->code, self::CONTRA_REVENUE_CODES)) {
                    $contra[$key] += $amt;
                } else {
                    $grossRev[$key] += $amt;
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
     * Arus kas bersih per sub-periode (untuk bar chart hijau/merah).
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
            ->whereIn('accounts.cash_flow_category', ['operating', 'investing', 'financing'])
            ->whereBetween('journals.date', [$from, $to])
            ->get(['journals.date', 'accounts.type', 'journal_items.debit', 'journal_items.credit']);

        foreach ($lines as $l) {
            $key = $this->bucketKey((string) $l->date, $granularity);
            if (! array_key_exists($key, $net)) {
                continue;
            }
            $isDebitNormal = in_array($l->type, ['Asset', 'Expense']);
            $net[$key] += $isDebitNormal
                ? ((float) $l->debit - (float) $l->credit)
                : ((float) $l->credit - (float) $l->debit);
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
        return $granularity === 'day' ? substr($date, 0, 10) : substr($date, 0, 7);
    }
}
