<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rab;
use App\Models\RabMonthlyActual;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Realisasi RAB — halaman pemantauan anggaran vs realisasi. Khusus CFO
 * (grup rute /finance).
 *
 * Prinsip perhitungan (praktik manajemen keuangan — analisis selisih anggaran):
 *
 *  1. Anggaran tahunan (`annual_budget`) adalah komitmen. Rincian per kuartal
 *     (q1..q4) hanya rencana pembagian bertahap — diskalakan ulang supaya total
 *     rinciannya sama dengan anggaran tahunan (rincian yang tidak cocok dengan
 *     anggaran tahunan itu tanda RAB belum rapi).
 *  2. Yang dipantau bukan "persen terpakai dari anggaran tahunan" (itu baru
 *     berarti di akhir tahun), tapi realisasi dibanding anggaran sampai bulan
 *     berjalan.
 *     - Selisih < 0  → hemat (di bawah rencana)
 *     - Selisih > 0  → boros (di atas rencana)
 *  3. Proyeksi akhir tahun memakai laju realisasi terkini: realisasi sampai
 *     bulan berjalan ÷ jumlah bulan berjalan × 12.
 *  4. Serapan tahunan tetap ditampilkan sebagai informasi.
 *
 * Realisasi diambil OTOMATIS dari pembukuan (journal_items) setiap halaman
 * dibuka — selalu ikut kondisi buku besar terkini. `rab_monthly_actuals` hanya
 * dipakai sebagai penimpa manual per sel (untuk penyesuaian kas→akrual yang
 * belum tercermin di jurnal). Kalau sebuah sel tidak ditimpa manual, angkanya =
 * angka jurnal. Tombol "Kembalikan ke angka jurnal" menghapus penimpa manual.
 */
class RabRealisasiController extends Controller
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const REVENUE_CODE = '__REVENUE__';

    private const REVENUE_TARGET_CODE = '__REVENUE_TARGET__';

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $years = range(now()->year - 2, now()->year + 2);
        $months = range(1, 12);

        // Berapa bulan tahun ini yang sudah berjalan (dasar hitung anggaran sampai bulan berjalan).
        $monthsElapsed = match (true) {
            $year < now()->year => 12,
            $year > now()->year => 0,
            default => (int) now()->month,
        };

        $rabRows = Rab::where('year', $year)->orderBy('division')->orderBy('account_name')->get();

        $actuals = RabMonthlyActual::where('year', $year)->get()->groupBy('account_code')
            ->map(fn ($g) => $g->pluck('amount', 'month')->all());

        // Angka realisasi per akun per bulan langsung dari pembukuan.
        $journalByAccount = $this->journalMonthlyByAccount($year);

        $rows = $rabRows->map(function ($rab) use ($actuals, $journalByAccount, $months, $monthsElapsed) {
            $manual = $actuals->get($rab->account_code, []);
            $fromJournal = $journalByAccount[$rab->account_code] ?? [];

            $m = [];
            $realTotal = 0;
            foreach ($months as $mm) {
                // Penimpa manual menang; kalau tidak ada, pakai angka jurnal.
                $v = array_key_exists($mm, $manual)
                    ? (int) $manual[$mm]
                    : (int) round($fromJournal[$mm] ?? 0);
                $m[$mm] = $v;
                $realTotal += $v;
            }

            $annual = $rab->annualBudget();

            // Pembagian anggaran bulanan: bentuknya mengikuti rencana kuartal,
            // tapi jumlah 12 bulan dibuat sama dengan anggaran tahunan. Kalau
            // rencana per-kuartal belum diisi ($qRaw semua 0 — baris RAB baru
            // yang baru diisi angka tahunannya saja), sebar rata 12 bulan
            // alih-alih diam-diam jadi Rp 0 di semua bulan.
            $qRaw = [(int) $rab->q1, (int) $rab->q2, (int) $rab->q3, (int) $rab->q4];
            $qSum = array_sum($qRaw);
            $monthlyBudget = [];
            foreach ($months as $mm) {
                $q = (int) ceil($mm / 3);
                $monthlyBudget[$mm] = $qSum > 0 ? $annual * ($qRaw[$q - 1] / $qSum) / 3 : $annual / 12;
            }

            $budgetToDate = 0;
            $realToDate = 0;
            for ($mm = 1; $mm <= $monthsElapsed; $mm++) {
                $budgetToDate += $monthlyBudget[$mm];
                $realToDate += $m[$mm];
            }

            $budgetQ = [];
            $realQ = [];
            foreach ([1, 2, 3, 4] as $q) {
                $budgetQ["q$q"] = $qSum > 0 ? (int) round($annual * ($qRaw[$q - 1] / $qSum)) : (int) round($annual / 4);
                $realQ["q$q"] = $m[$q * 3 - 2] + $m[$q * 3 - 1] + $m[$q * 3];
            }

            $absorption = $annual > 0 ? round($realTotal / $annual * 100, 1) : 0;
            $pace = $budgetToDate > 0 ? round($realToDate / $budgetToDate * 100, 1) : null;
            $varianceYtd = (int) round($realToDate - $budgetToDate);
            $forecast = $monthsElapsed > 0 ? (int) round($realTotal / $monthsElapsed * 12) : null;
            $forecastVariance = $forecast !== null ? $forecast - $annual : null;

            return [
                'id' => $rab->id,
                'division' => $rab->division,
                'account_name' => $rab->account_name,
                'account_code' => $rab->account_code,
                'rab_prev' => (int) $rab->rab_prev,
                'budget_q1' => $budgetQ['q1'], 'budget_q2' => $budgetQ['q2'],
                'budget_q3' => $budgetQ['q3'], 'budget_q4' => $budgetQ['q4'],
                'budget_total' => $annual,
                'budget_to_date' => (int) round($budgetToDate),
                'months' => $m,
                'real_q1' => $realQ['q1'], 'real_q2' => $realQ['q2'], 'real_q3' => $realQ['q3'], 'real_q4' => $realQ['q4'],
                'real_total' => $realTotal,
                'sisa' => $annual - $realTotal,
                'absorption' => $absorption,
                'pace' => $pace,
                'variance_ytd' => $varianceYtd,
                'variance_ytd_pct' => $budgetToDate > 0 ? round($varianceYtd / $budgetToDate * 100, 1) : null,
                'forecast' => $forecast,
                'forecast_variance' => $forecastVariance,
                'status' => $this->status($pace, $absorption),
            ];
        })->values();

        // ── Totals ───────────────────────────────────────────────────
        $totals = [
            'budget_total' => (int) $rows->sum('budget_total'),
            'budget_to_date' => (int) $rows->sum('budget_to_date'),
            'real_total' => (int) $rows->sum('real_total'),
            'real_to_date' => (int) $rows->sum(fn ($r) => array_sum(array_slice($r['months'], 0, $monthsElapsed, true))),
            'months' => collect($months)->mapWithKeys(fn ($mm) => [$mm => (int) $rows->sum(fn ($r) => $r['months'][$mm])])->all(),
        ];
        foreach ([1, 2, 3, 4] as $q) {
            $totals["budget_q$q"] = (int) $rows->sum("budget_q$q");
            $totals["real_q$q"] = (int) $rows->sum("real_q$q");
        }
        $totals['sisa'] = $totals['budget_total'] - $totals['real_total'];
        $totals['absorption'] = $totals['budget_total'] > 0 ? round($totals['real_total'] / $totals['budget_total'] * 100, 1) : 0;
        $totals['pace'] = $totals['budget_to_date'] > 0 ? round($totals['real_to_date'] / $totals['budget_to_date'] * 100, 1) : null;
        $totals['variance_ytd'] = $totals['real_to_date'] - $totals['budget_to_date'];
        $totals['forecast'] = $monthsElapsed > 0 ? (int) round($totals['real_total'] / $monthsElapsed * 12) : null;
        $totals['forecast_variance'] = $totals['forecast'] !== null ? $totals['forecast'] - $totals['budget_total'] : null;

        // ── Pendapatan + target ──────────────────────────────────────
        $manualRevenue = $actuals->get(self::REVENUE_CODE, []);
        $manualTarget = $actuals->get(self::REVENUE_TARGET_CODE, []);
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

        // ── KPI per kuartal ──────────────────────────────────────────
        $quarters = [];
        foreach ([1, 2, 3, 4] as $q) {
            $mm = range($q * 3 - 2, $q * 3);
            $rev = array_sum(array_map(fn ($x) => $revenueRow[$x], $mm));
            $tgt = array_sum(array_map(fn ($x) => $targetRow[$x], $mm));
            $exp = $totals["real_q$q"];
            $bud = $totals["budget_q$q"];
            // Kuartal dianggap "berjalan/selesai" kalau minimal 1 bulannya sudah lewat.
            $started = $monthsElapsed >= $q * 3 - 2;
            $quarters[] = [
                'label' => "Q$q",
                'revenue' => $rev,
                'target' => $tgt,
                'expense' => $exp,
                'budget' => $bud,
                'profit' => $rev - $exp,
                'profit_pct' => ($started && $rev > 0) ? round(($rev - $exp) / $rev * 100, 1) : null,
                'revenue_achievement' => ($started && $tgt > 0) ? round($rev / $tgt * 100, 1) : null,
                'budget_realization' => ($started && $bud > 0) ? round($exp / $bud * 100, 1) : null,
                'variance' => $rev - $exp,
            ];
        }

        // ── Data grafik ──────────────────────────────────────────────
        // Kurva rencana = akumulasi anggaran per bulan (bentuk mengikuti
        // rencana kuartal), bukan garis lurus rata.
        $planCurve = [];
        $actualCurve = [];
        $cumPlan = 0;
        $cumActual = 0;
        foreach ($months as $mm) {
            $q = (int) ceil($mm / 3);
            $cumPlan += $totals['budget_total'] > 0 ? $totals["budget_q$q"] / 3 : 0;
            $cumActual += $totals['months'][$mm];
            $planCurve[] = $totals['budget_total'] > 0 ? round($cumPlan / $totals['budget_total'] * 100, 1) : 0;
            $actualCurve[] = ($totals['budget_total'] > 0 && $mm <= $monthsElapsed)
                ? round($cumActual / $totals['budget_total'] * 100, 1)
                : null;
        }

        $byCategory = $rows->sortByDesc('real_total')->take(10)->map(fn ($r) => [
            'name' => $r['account_name'],
            'budget' => $r['budget_total'],
            'real' => $r['real_total'],
        ])->values();

        $charts = [
            'months' => self::MONTHS,
            'months_elapsed' => $monthsElapsed,
            'revenue' => array_values($revenueRow),
            'target' => array_values($targetRow),
            'expense' => array_map(fn ($mm) => $totals['months'][$mm], $months),
            'profit' => $plMonthly->pluck('profit')->all(),
            'quarter_labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'quarter_budget' => array_map(fn ($q) => $totals["budget_q$q"], [1, 2, 3, 4]),
            'quarter_real' => array_map(fn ($q) => $totals["real_q$q"], [1, 2, 3, 4]),
            'absorption_actual' => $actualCurve,
            'absorption_plan' => $planCurve,
            'category' => $byCategory,
        ];

        return view('admin.rab-realisasi.index', [
            'year' => $year,
            'years' => $years,
            'monthNames' => self::MONTHS,
            'monthsElapsed' => $monthsElapsed,
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
            // Boleh negatif: untuk koreksi atau pengembalian beban.
            'actuals.*.amount' => 'required|numeric',
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

    /**
     * Hapus SEMUA penimpa manual realisasi beban tahun ini, supaya halaman
     * kembali sepenuhnya mengikuti angka pembukuan. (Baris pendapatan/target
     * pendapatan tidak tersentuh — target itu memang manual.)
     */
    public function syncFromJournals(Request $request)
    {
        $year = (int) $request->validate(['year' => 'required|integer'])['year'];

        RabMonthlyActual::where('year', $year)
            ->whereNotIn('account_code', [self::REVENUE_CODE, self::REVENUE_TARGET_CODE])
            ->delete();

        return response()->json(['success' => true, 'message' => 'Semua penimpa manual dihapus — realisasi kembali mengikuti pembukuan.']);
    }

    /**
     * Status selisih anggaran (praktik manajer keuangan):
     *  - anggaran tahunan sudah terlampaui                  → Kritis
     *  - realisasi > 110% dari rencana sampai bulan ini     → Kritis (boros)
     *  - 100–110%                                            → Waspada
     *  - ≤ 100%                                              → Aman (sesuai / di bawah rencana)
     */
    private function status(?float $pace, float $absorption): string
    {
        if ($absorption >= 100) {
            return 'Kritis';
        }
        if ($pace === null) {
            return 'Belum mulai';
        }
        if ($pace > 110) {
            return 'Kritis';
        }
        if ($pace > 100) {
            return 'Waspada';
        }

        return 'Aman';
    }

    /** account_code => [month => amount] (beban: debit − credit). Bulan diambil di PHP → portabel. */
    private function journalMonthlyByAccount(int $year): array
    {
        $out = [];
        foreach ($this->journalLines($year, 'Expense') as $r) {
            $m = (int) Carbon::parse($r->date)->month;
            $out[$r->code][$m] = ($out[$r->code][$m] ?? 0) + ($r->debit - $r->credit);
        }

        return $out;
    }

    /** @return array<int,int> month => total (revenue: credit − debit; expense: debit − credit) */
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
