<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rab;
use App\Models\RabMonthlyActual;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Realisasi RAB — halaman tunggal untuk memantau anggaran vs realisasi
 * (menggantikan sekaligus "Tracker RAB" lama). Khusus CFO (grup rute /finance).
 *
 * Isi:
 *  - realisasi PER BULAN per akun beban (rab_monthly_actuals, bisa diedit CFO),
 *  - ringkasan per KUARTAL + status Aman/Waspada/Kritis,
 *  - ringkasan laba–rugi bulanan (pendapatan manual / dari jurnal),
 *  - grafik: revenue vs beban, laba/rugi, serapan anggaran, per-kuartal,
 *  - KPI per kuartal: profit %, pencapaian pendapatan, serapan anggaran.
 *
 * Realisasi bersumber dari rab_monthly_actuals (dicatat manual selama transisi
 * cash→accrual). Tombol "Tarik dari Jurnal" mengisinya dari journal_items.
 */
class RabRealisasiController extends Controller
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const REVENUE_CODE = '__REVENUE__';

    private const REVENUE_TARGET_CODE = '__REVENUE_TARGET__';

    private const SPECIAL_CODES = [self::REVENUE_CODE, self::REVENUE_TARGET_CODE];

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $years = range(now()->year - 2, now()->year + 2);

        $rabRows = Rab::where('year', $year)->orderBy('division')->orderBy('account_name')->get();

        $actuals = RabMonthlyActual::where('year', $year)->get()->groupBy('account_code')
            ->map(fn ($g) => $g->pluck('amount', 'month')->all());

        [$quarterOf, $months] = [fn (int $m) => (int) ceil($m / 3), range(1, 12)];

        $rows = $rabRows->map(function ($rab) use ($actuals, $months, $quarterOf) {
            $manual = $actuals->get($rab->account_code, []);

            $m = [];
            $realQ = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            $realTotal = 0;
            foreach ($months as $mm) {
                $v = (int) ($manual[$mm] ?? 0);
                $m[$mm] = $v;
                $realTotal += $v;
                $realQ[$quarterOf($mm)] += $v;
            }

            $budget = $rab->annualBudget();
            $budgetQ = ['q1' => (int) $rab->q1, 'q2' => (int) $rab->q2, 'q3' => (int) $rab->q3, 'q4' => (int) $rab->q4];
            $pct = $budget > 0 ? round($realTotal / $budget * 100, 1) : 0;

            return [
                'id' => $rab->id,
                'division' => $rab->division,
                'account_name' => $rab->account_name,
                'account_code' => $rab->account_code,
                'rab_prev' => (int) $rab->rab_prev,
                'budget_q1' => $budgetQ['q1'], 'budget_q2' => $budgetQ['q2'],
                'budget_q3' => $budgetQ['q3'], 'budget_q4' => $budgetQ['q4'],
                'budget_total' => $budget,
                'months' => $m,
                'real_q1' => $realQ[1], 'real_q2' => $realQ[2], 'real_q3' => $realQ[3], 'real_q4' => $realQ[4],
                'real_total' => $realTotal,
                'pct' => $pct,
                'sisa' => $budget - $realTotal,
                'status' => $this->status($pct),
                'status_q1' => $this->status($budgetQ['q1'] > 0 ? $realQ[1] / $budgetQ['q1'] * 100 : 0),
                'status_q2' => $this->status($budgetQ['q2'] > 0 ? $realQ[2] / $budgetQ['q2'] * 100 : 0),
                'status_q3' => $this->status($budgetQ['q3'] > 0 ? $realQ[3] / $budgetQ['q3'] * 100 : 0),
                'status_q4' => $this->status($budgetQ['q4'] > 0 ? $realQ[4] / $budgetQ['q4'] * 100 : 0),
            ];
        })->values();

        // Totals
        $totals = [
            'budget_total' => (int) $rows->sum('budget_total'),
            'real_total' => (int) $rows->sum('real_total'),
            'months' => collect($months)->mapWithKeys(fn ($mm) => [$mm => (int) $rows->sum(fn ($r) => $r['months'][$mm])])->all(),
        ];
        foreach (['q1', 'q2', 'q3', 'q4'] as $q) {
            $totals["budget_$q"] = (int) $rows->sum("budget_$q");
            $totals["real_$q"] = (int) $rows->sum("real_$q");
        }
        $totals['sisa'] = $totals['budget_total'] - $totals['real_total'];
        $totals['pct'] = $totals['budget_total'] > 0 ? round($totals['real_total'] / $totals['budget_total'] * 100, 1) : 0;

        // Pendapatan + target (manual atau jurnal)
        $manualRevenue = ($actuals->get(self::REVENUE_CODE, []));
        $manualTarget = ($actuals->get(self::REVENUE_TARGET_CODE, []));
        $journalRevenue = $this->journalMonthly($year, 'Revenue');

        $revenueRow = [];
        $targetRow = [];
        foreach ($months as $mm) {
            $revenueRow[$mm] = array_key_exists($mm, $manualRevenue) ? (int) $manualRevenue[$mm] : (int) ($journalRevenue[$mm] ?? 0);
            $targetRow[$mm] = (int) ($manualTarget[$mm] ?? 0);
        }

        $plMonthly = collect($months)->map(fn ($mm) => [
            'month' => self::MONTHS[$mm - 1],
            'revenue' => $revenueRow[$mm],
            'target' => $targetRow[$mm],
            'expense' => $totals['months'][$mm],
            'profit' => $revenueRow[$mm] - $totals['months'][$mm],
        ]);

        // ── KPI per kuartal ────────────────────────────────────────────
        $quarters = [];
        foreach ([1, 2, 3, 4] as $q) {
            $mm = range(($q - 1) * 3 + 1, $q * 3);
            $rev = array_sum(array_map(fn ($x) => $revenueRow[$x], $mm));
            $tgt = array_sum(array_map(fn ($x) => $targetRow[$x], $mm));
            $exp = $totals["real_q$q"];
            $bud = $totals["budget_q$q"];
            $quarters[] = [
                'label' => "Q$q",
                'revenue' => $rev,
                'expense' => $exp,
                'budget' => $bud,
                'profit' => $rev - $exp,
                'profit_pct' => $rev > 0 ? round(($rev - $exp) / $rev * 100, 1) : null,
                'revenue_achievement' => $tgt > 0 ? round($rev / $tgt * 100, 1) : null,
                'budget_realization' => $bud > 0 ? round($exp / $bud * 100, 1) : null,
            ];
        }

        // ── Data grafik ───────────────────────────────────────────────
        $idealCurve = [];
        $actualCurve = [];
        $cum = 0;
        foreach ($months as $mm) {
            $cum += $totals['months'][$mm];
            $actualCurve[] = $totals['budget_total'] > 0 ? round($cum / $totals['budget_total'] * 100, 1) : 0;
            $idealCurve[] = round($mm / 12 * 100, 1);
        }

        $byCategory = $rows->sortByDesc('real_total')->take(10)->map(fn ($r) => [
            'name' => $r['account_name'],
            'budget' => $r['budget_total'],
            'real' => $r['real_total'],
        ])->values();

        $charts = [
            'months' => self::MONTHS,
            'revenue' => array_values($revenueRow),
            'target' => array_values($targetRow),
            'expense' => array_map(fn ($mm) => $totals['months'][$mm], $months),
            'profit' => $plMonthly->pluck('profit')->all(),
            'quarter_labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'quarter_budget' => array_map(fn ($q) => $totals["budget_q$q"], [1, 2, 3, 4]),
            'quarter_real' => array_map(fn ($q) => $totals["real_q$q"], [1, 2, 3, 4]),
            'absorption_actual' => $actualCurve,
            'absorption_ideal' => $idealCurve,
            'category' => $byCategory,
        ];

        return view('admin.rab-realisasi.index', [
            'year' => $year,
            'years' => $years,
            'monthNames' => self::MONTHS,
            'rows' => $rows,
            'totals' => $totals,
            'plMonthly' => $plMonthly,
            'revenueRow' => $revenueRow,
            'targetRow' => $targetRow,
            'quarters' => $quarters,
            'charts' => $charts,
            'currentQuarter' => (int) ceil(now()->month / 3),
        ]);
    }

    public function saveActuals(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'actuals' => 'required|array',
            'actuals.*.account_code' => 'required|string|max:25',
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

    public function syncFromJournals(Request $request)
    {
        $year = (int) $request->validate(['year' => 'required|integer'])['year'];

        DB::transaction(function () use ($year) {
            $monthly = $this->journalMonthlyByAccount($year);
            foreach ($monthly as $code => $byMonth) {
                foreach ($byMonth as $m => $amount) {
                    RabMonthlyActual::updateOrCreate(
                        ['year' => $year, 'account_code' => $code, 'month' => $m],
                        ['amount' => max(0, (int) round($amount))],
                    );
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Realisasi disinkron dari jurnal keuangan.']);
    }

    private function status(float $pct): string
    {
        return $pct >= 100 ? 'Lewat' : ($pct >= 95 ? 'Kritis' : ($pct >= 80 ? 'Waspada' : 'Aman'));
    }

    /** account_code => [month => amount] (beban; bulan diambil di PHP → portabel). */
    private function journalMonthlyByAccount(int $year): array
    {
        $out = [];
        foreach ($this->journalLines($year, 'Expense') as $r) {
            $m = (int) Carbon::parse($r->date)->month;
            $out[$r->code][$m] = ($out[$r->code][$m] ?? 0) + ($r->debit - $r->credit);
        }

        return $out;
    }

    /** @return array<int,int> month => total (revenue = credit-debit, expense = debit-credit) */
    private function journalMonthly(int $year, string $type): array
    {
        $out = [];
        foreach ($this->journalLines($year, $type) as $r) {
            $m = (int) Carbon::parse($r->date)->month;
            $delta = $type === 'Revenue' ? $r->credit - $r->debit : $r->debit - $r->credit;
            $out[$m] = ($out[$m] ?? 0) + (int) round($delta);
        }

        return $out;
    }

    private function journalLines(int $year, string $type)
    {
        return DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereBetween('journals.date', ["{$year}-01-01", "{$year}-12-31"])
            ->where('accounts.type', $type)
            ->get(['accounts.code as code', 'journals.date as date', 'journal_items.debit as debit', 'journal_items.credit as credit']);
    }
}
