<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RabRealisasiController extends Controller
{
    public function index(Request $request)
    {
        $year    = $request->input('year', now()->year);
        $years   = range(now()->year - 2, now()->year + 2);

        // Ambil semua baris RAB tahun ini
        $rabRows = Rab::where('year', $year)
            ->orderBy('division')
            ->orderBy('account_name')
            ->get();

        // Realisasi per akun dari journal_items, key by account_code
        $realisasi = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereYear('journals.date', $year)
            ->whereIn('accounts.type', ['Expense', 'Revenue'])
            ->select(
                'accounts.name as account_name',
                'accounts.code as account_code',
                'accounts.type',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('accounts.id', 'accounts.name', 'accounts.code', 'accounts.type')
            ->get()
            ->keyBy('account_code');

        // Per kuartal, key by account_code
        $realisasiQ = [];
        for ($q = 1; $q <= 4; $q++) {
            $startMonth = ($q - 1) * 3 + 1;
            $endMonth   = $q * 3;
            $qRows = DB::table('journal_items')
                ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
                ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
                ->whereYear('journals.date', $year)
                ->whereMonth('journals.date', '>=', $startMonth)
                ->whereMonth('journals.date', '<=', $endMonth)
                ->whereIn('accounts.type', ['Expense', 'Revenue'])
                ->select(
                    'accounts.code as account_code',
                    'accounts.type',
                    DB::raw('SUM(journal_items.debit) as total_debit'),
                    DB::raw('SUM(journal_items.credit) as total_credit')
                )
                ->groupBy('accounts.id', 'accounts.code', 'accounts.type')
                ->get();

            foreach ($qRows as $row) {
                $val = $row->type === 'Expense' ? $row->total_debit : $row->total_credit;
                $realisasiQ[$row->account_code]["q{$q}"] = $val;
            }
        }

        // Gabungkan RAB dengan realisasi (match by account_code, fallback by account_name)
        $rows = $rabRows->map(function ($rab) use ($realisasi, $realisasiQ) {
            $key  = $rab->account_code;
            $real = $key ? $realisasi->get($key) : null;
            $realVal = 0;
            if ($real) {
                $realVal = $real->type === 'Expense' ? $real->total_debit : $real->total_credit;
            }

            $rq1 = $realisasiQ[$key]['q1'] ?? 0;
            $rq2 = $realisasiQ[$key]['q2'] ?? 0;
            $rq3 = $realisasiQ[$key]['q3'] ?? 0;
            $rq4 = $realisasiQ[$key]['q4'] ?? 0;

            $budget  = $rab->total;
            $pct     = $budget > 0 ? round(($realVal / $budget) * 100, 1) : 0;

            $statusQ = function($anggaran, $realisasi) {
                if ($anggaran <= 0) return 'N/A';
                $p = round(($realisasi / $anggaran) * 100, 1);
                return $p >= 95 ? 'Kritis' : ($p >= 80 ? 'Waspada' : 'Aman');
            };

            $worstStatus = function($statuses) {
                if (in_array('Kritis', $statuses)) return 'Kritis';
                if (in_array('Waspada', $statuses)) return 'Waspada';
                return 'Aman';
            };

            $qStatuses = [];
            foreach ([1,2,3,4] as $q) {
                $qa = $rab->{"q{$q}"};
                $qr = (int) ($realisasiQ[$key]["q{$q}"] ?? 0);
                if ($qa > 0) $qStatuses[] = $statusQ($qa, $qr);
            }
            $status = $worstStatus($qStatuses);

            return [
                'division'     => $rab->division,
                'account_name' => $rab->account_name,
                'account_code' => $rab->account_code,
                'budget_q1'    => $rab->q1,
                'budget_q2'    => $rab->q2,
                'budget_q3'    => $rab->q3,
                'budget_q4'    => $rab->q4,
                'budget_total' => $budget,
                'real_q1'      => (int) $rq1,
                'real_q2'      => (int) $rq2,
                'real_q3'      => (int) $rq3,
                'real_q4'      => (int) $rq4,
                'real_total'   => (int) $realVal,
                'pct'          => $pct,
                'status'       => $status,
                'status_q1'    => $statusQ($rab->q1, (int)$rq1),
                'status_q2'    => $statusQ($rab->q2, (int)$rq2),
                'status_q3'    => $statusQ($rab->q3, (int)$rq3),
                'status_q4'    => $statusQ($rab->q4, (int)$rq4),
            ];
        });

        $totalBudget  = $rows->sum('budget_total');
        $totalReal    = $rows->sum('real_total');
        $pctOverall   = $totalBudget > 0 ? round(($totalReal / $totalBudget) * 100, 1) : 0;
        $totalKritis  = $rows->where('status', 'Kritis')->count();
        $totalAman    = $rows->where('status', 'Aman')->count();

        return view('admin.rab-realisasi.index', compact(
            'rows', 'year', 'years',
            'totalBudget', 'totalReal', 'pctOverall',
            'totalKritis', 'totalAman'
        ));
    }
}
