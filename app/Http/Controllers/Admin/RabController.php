<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rab;
use Illuminate\Http\Request;

class RabController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('year', now()->year);
        $rows = Rab::where('year', $year)->orderBy('division')->orderBy('account_name')->get();

        $divisions = Rab::divisions();
        $years     = range(now()->year - 2, now()->year + 2);
        $accounts  = \App\Models\Account::whereIn('type', ['Expense', 'Revenue'])
            ->orderBy('code')->get(['id', 'code', 'name', 'type']);

        // Summary stats — anggaran tahunan pakai annual_budget (angka otoritatif),
        // bukan jumlah kuartal (q1..q4 cuma pacing dan sering tidak sama persis).
        $totalBudget    = $rows->sum(fn ($r) => $r->annualBudget());
        $currentQuarter = ceil(now()->month / 3);
        $qField         = "q{$currentQuarter}";
        $budgetQuarter  = $rows->sum($qField);

        // Baris untuk grid (annual_budget sudah lewat accessor: pakai fallback ke Σ kuartal).
        $tableRows = $rows->map(fn ($r) => [
            'id'            => $r->id,
            'division'      => $r->division,
            'account_name'  => $r->account_name,
            'activity'      => $r->activity,
            'account_code'  => $r->account_code,
            'rab_prev'      => (int) $r->rab_prev,
            'annual_budget' => $r->annualBudget(),
            'q1'            => (int) $r->q1,
            'q2'            => (int) $r->q2,
            'q3'            => (int) $r->q3,
            'q4'            => (int) $r->q4,
            'total'         => (int) $r->total,
        ])->values();

        return view('admin.rab.index', compact('rows', 'tableRows', 'year', 'divisions', 'years', 'totalBudget', 'budgetQuarter', 'accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'rows'                  => 'required|array|min:1',
            'rows.*.division'       => 'required|string|max:100',
            'rows.*.account_name'   => 'required|string|max:100',
            'rows.*.activity'       => 'nullable|string|max:255',
            'rows.*.account_code'   => 'required|string|max:20|exists:accounts,code',
            'rows.*.rab_prev'       => 'nullable|integer|min:0',
            'rows.*.annual_budget'  => 'nullable|integer|min:0',
            'rows.*.q1'             => 'required|integer|min:0',
            'rows.*.q2'             => 'required|integer|min:0',
            'rows.*.q3'             => 'required|integer|min:0',
            'rows.*.q4'             => 'required|integer|min:0',
        ]);

        $year = $request->input('year', now()->year);

        // Delete existing for this year, then re-insert (full replace pattern)
        \Illuminate\Support\Facades\DB::transaction(function () use ($year, $request) {
            Rab::where('year', $year)->delete();

            foreach ($request->rows as $row) {
                Rab::create([
                    'year'          => $year,
                    'division'      => $row['division'],
                    'account_name'  => $row['account_name'],
                    'activity'      => $row['activity'] ?? null,
                    'account_code'  => $row['account_code'] ?? null,
                    'rab_prev'      => (int) ($row['rab_prev'] ?? 0),
                    'annual_budget' => (int) ($row['annual_budget'] ?? 0),
                    'q1'            => (int) $row['q1'],
                    'q2'            => (int) $row['q2'],
                    'q3'            => (int) $row['q3'],
                    'q4'            => (int) $row['q4'],
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'RAB berhasil disimpan.']);
    }

    public function destroy(Rab $rab)
    {
        $rab->delete();
        return response()->json(['success' => true]);
    }

    public function data(Request $request)
    {
        $year = $request->input('year', now()->year);
        $rows = Rab::where('year', $year)->orderBy('division')->orderBy('account_name')->get();

        return response()->json($rows->map(fn($r) => [
            'id'            => $r->id,
            'division'      => $r->division,
            'account_name'  => $r->account_name,
            'activity'      => $r->activity,
            'account_code'  => $r->account_code,
            'rab_prev'      => $r->rab_prev,
            'annual_budget' => $r->annualBudget(),
            'q1'            => $r->q1,
            'q2'            => $r->q2,
            'q3'            => $r->q3,
            'q4'            => $r->q4,
            'total'         => $r->total,
        ]));
    }
}
