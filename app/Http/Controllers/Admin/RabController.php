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

        // Summary stats
        $totalBudget    = $rows->sum('total');
        $currentQuarter = ceil(now()->month / 3);
        $qField         = "q{$currentQuarter}";
        $budgetQuarter  = $rows->sum($qField);

        return view('admin.rab.index', compact('rows', 'year', 'divisions', 'years', 'totalBudget', 'budgetQuarter', 'accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'rows'                  => 'required|array|min:1',
            'rows.*.division'       => 'required|string|max:100',
            'rows.*.account_name'   => 'required|string|max:100',
            'rows.*.activity'       => 'nullable|string|max:255',
            'rows.*.account_code'   => 'nullable|string|max:20',
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
                    'year'         => $year,
                    'division'     => $row['division'],
                    'account_name' => $row['account_name'],
                    'activity'     => $row['activity'] ?? null,
                    'account_code' => $row['account_code'] ?? null,
                    'q1'           => (int) $row['q1'],
                    'q2'           => (int) $row['q2'],
                    'q3'           => (int) $row['q3'],
                    'q4'           => (int) $row['q4'],
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
            'id'           => $r->id,
            'division'     => $r->division,
            'account_name' => $r->account_name,
            'activity'     => $r->activity,
            'account_code' => $r->account_code,
            'q1'           => $r->q1,
            'q2'           => $r->q2,
            'q3'           => $r->q3,
            'q4'           => $r->q4,
            'total'        => $r->total,
        ]));
    }
}
