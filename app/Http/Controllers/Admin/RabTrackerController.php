<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rab;
use App\Models\RabMonthlyActual;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tracker RAB — versi bulanan (meniru spreadsheet CFO "Tracker RAB").
 *
 * Per akun beban: anggaran tahunan + per-kuartal, realisasi PER BULAN
 * (Jan–Des) yang dicatat manual di rab_monthly_actuals, plus % terpakai &
 * sisa anggaran. Halaman ini khusus CFO (grup rute /finance).
 */
class RabTrackerController extends Controller
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Kode semu untuk baris "Total Pendapatan" di rab_monthly_actuals. */
    private const REVENUE_CODE = '__REVENUE__';

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $years = range(now()->year - 2, now()->year + 2);

        $rabRows = Rab::where('year', $year)
            ->orderBy('division')
            ->orderBy('account_name')
            ->get();

        // Realisasi bulanan manual, key: "account_code" -> [month => amount]
        $actuals = RabMonthlyActual::where('year', $year)->get()
            ->groupBy('account_code')
            ->map(fn ($g) => $g->pluck('amount', 'month')->all());

        // Realisasi dari jurnal (pembanding) per akun per bulan.
        $journalMonthly = $this->journalMonthlyByAccount($year);

        $rows = $rabRows->map(function ($rab) use ($actuals, $journalMonthly) {
            $manual = $actuals->get($rab->account_code, []);
            $fromJournal = $journalMonthly->get($rab->account_code, []);

            $months = [];
            $total = 0;
            for ($m = 1; $m <= 12; $m++) {
                $val = (int) ($manual[$m] ?? 0);
                $months[$m] = $val;
                $total += $val;
            }

            $budget = (int) $rab->total;
            $pct = $budget > 0 ? round($total / $budget * 100, 1) : 0;

            return [
                'id' => $rab->id,
                'division' => $rab->division,
                'account_name' => $rab->account_name,
                'account_code' => $rab->account_code,
                'rab_prev' => (int) $rab->rab_prev,
                'budget_q1' => (int) $rab->q1,
                'budget_q2' => (int) $rab->q2,
                'budget_q3' => (int) $rab->q3,
                'budget_q4' => (int) $rab->q4,
                'budget_total' => $budget,
                'months' => $months,
                'journal_months' => array_map(fn ($m) => (int) ($fromJournal[$m] ?? 0), range(1, 12)),
                'real_total' => $total,
                'pct' => $pct,
                'sisa' => $budget - $total,
                'status' => $this->status($pct),
            ];
        })->values();

        $totals = [
            'budget_total' => $rows->sum('budget_total'),
            'real_total' => $rows->sum('real_total'),
            'months' => collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => $rows->sum(fn ($r) => $r['months'][$m])])->all(),
        ];
        $totals['sisa'] = $totals['budget_total'] - $totals['real_total'];
        $totals['pct'] = $totals['budget_total'] > 0 ? round($totals['real_total'] / $totals['budget_total'] * 100, 1) : 0;

        // Ringkasan Laba-Rugi per bulan. Pendapatan: pakai entri manual bulan
        // itu kalau ada, kalau tidak ambil dari jurnal keuangan.
        $manualRevenue = RabMonthlyActual::where('year', $year)
            ->where('account_code', self::REVENUE_CODE)
            ->pluck('amount', 'month')->all();
        $journalRevenue = $this->revenueMonthly($year);
        $revenueRow = [];
        for ($m = 1; $m <= 12; $m++) {
            $revenueRow[$m] = array_key_exists($m, $manualRevenue)
                ? (int) $manualRevenue[$m]
                : (int) ($journalRevenue[$m] ?? 0);
        }

        $plMonthly = collect(range(1, 12))->map(fn ($m) => [
            'month' => self::MONTHS[$m - 1],
            'revenue' => $revenueRow[$m],
            'expense' => $totals['months'][$m],
            'profit' => $revenueRow[$m] - $totals['months'][$m],
        ]);

        return view('admin.rab-tracker.index', [
            'year' => $year,
            'years' => $years,
            'monthNames' => self::MONTHS,
            'rows' => $rows,
            'totals' => $totals,
            'plMonthly' => $plMonthly,
            'revenueRow' => $revenueRow,
            'revenueIsManual' => ! empty($manualRevenue),
        ]);
    }

    public function saveActuals(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'actuals' => 'required|array',
            'actuals.*.account_code' => 'required|string|max:20',
            'actuals.*.month' => 'required|integer|min:1|max:12',
            'actuals.*.amount' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['actuals'] as $a) {
                RabMonthlyActual::updateOrCreate(
                    ['year' => $data['year'], 'account_code' => $a['account_code'], 'month' => $a['month']],
                    ['amount' => (int) round($a['amount'])],
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Realisasi bulanan disimpan.']);
    }

    /** Isi realisasi bulanan dari jurnal untuk satu tahun (menimpa manual). */
    public function syncFromJournals(Request $request)
    {
        $year = (int) $request->validate(['year' => 'required|integer'])['year'];
        $monthly = $this->journalMonthlyByAccount($year);

        DB::transaction(function () use ($year, $monthly) {
            foreach ($monthly as $code => $byMonth) {
                foreach ($byMonth as $m => $amount) {
                    RabMonthlyActual::updateOrCreate(
                        ['year' => $year, 'account_code' => $code, 'month' => $m],
                        ['amount' => (int) round($amount)],
                    );
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Realisasi disinkron dari jurnal.']);
    }

    private function status(float $pct): string
    {
        return $pct >= 100 ? 'Lewat' : ($pct >= 95 ? 'Kritis' : ($pct >= 80 ? 'Waspada' : 'Aman'));
    }

    /**
     * Realisasi beban per akun per bulan dari jurnal. Bulan diambil di PHP
     * supaya portabel (jangan pakai strftime / MONTH() yang beda antar DB).
     *
     * @return Collection<string, array<int,int>> account_code => [month => amount]
     */
    private function journalMonthlyByAccount(int $year)
    {
        return $this->journalLineTotals($year, 'Expense')
            ->groupBy('code')
            ->map(function ($g) {
                $byMonth = [];
                foreach ($g as $r) {
                    $byMonth[(int) $r->m] = ($byMonth[(int) $r->m] ?? 0) + (int) round($r->debit - $r->credit);
                }

                return $byMonth;
            });
    }

    /** @return array<int,int> month => revenue */
    private function revenueMonthly(int $year): array
    {
        $byMonth = [];
        foreach ($this->journalLineTotals($year, 'Revenue') as $r) {
            $byMonth[(int) $r->m] = ($byMonth[(int) $r->m] ?? 0) + (int) round($r->credit - $r->debit);
        }

        return $byMonth;
    }

    private function journalLineTotals(int $year, string $accountType)
    {
        return DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereBetween('journals.date', ["{$year}-01-01", "{$year}-12-31"])
            ->where('accounts.type', $accountType)
            ->get([
                'accounts.code as code',
                'journals.date as date',
                'journal_items.debit as debit',
                'journal_items.credit as credit',
            ])
            ->map(function ($r) {
                $r->m = (int) Carbon::parse($r->date)->month;

                return $r;
            });
    }
}
