<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\Account;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function index()
    {
        $assets = FixedAsset::with('expenseAccount', 'accumulatedAccount')
            ->orderBy('acquired_at')
            ->get()
            ->map(function ($a) {
                return [
                    'id'                       => $a->id,
                    'name'                     => $a->name,
                    'category'                 => $a->category,
                    'acquired_at'              => $a->acquired_at->format('Y-m-d'),
                    'cost'                     => (float) $a->cost,
                    'salvage_value'            => (float) $a->salvage_value,
                    'useful_life'              => $a->useful_life,
                    'depreciation_method'      => $a->depreciation_method,
                    'notes'                    => $a->notes,
                    'expense_account_id'       => $a->expense_account_id,
                    'expense_account_name'     => $a->expenseAccount?->name,
                    'accumulated_account_id'   => $a->accumulated_account_id,
                    'accumulated_account_name' => $a->accumulatedAccount?->name,
                    'is_active'                => $a->is_active,
                    'monthly_depreciation'     => round($a->monthly_depreciation, 2),
                    'accumulated_depreciation' => round($a->accumulated_depreciation, 2),
                    'book_value'               => round($a->book_value, 2),
                    'months_elapsed'           => $a->months_elapsed,
                    'status'                   => $a->status,
                ];
            });

        $activeAssets     = $assets->where('is_active', true);
        $totalCost        = $activeAssets->sum('cost');
        $totalAccumulated = $activeAssets->sum('accumulated_depreciation');
        $totalBookValue   = $activeAssets->sum('book_value');

        $accounts = Account::orderBy('code')->get(['id', 'code', 'name', 'type']);

        return view('admin.reports.fixed_assets', compact(
            'assets', 'totalCost', 'totalAccumulated', 'totalBookValue', 'accounts'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                   => 'required|string|max:255',
            'category'               => 'required|string|max:100',
            'acquired_at'            => 'required|date',
            'cost'                   => 'required|numeric|min:0',
            'salvage_value'          => 'required|numeric|min:0',
            'useful_life'            => 'required|integer|min:1',
            'depreciation_method'    => 'required|in:straight_line',
            'expense_account_id'     => 'nullable|exists:accounts,id',
            'accumulated_account_id' => 'nullable|exists:accounts,id',
            'notes'                  => 'nullable|string',
        ]);

        FixedAsset::create(array_merge(
            $request->only(
                'name', 'category', 'acquired_at', 'cost', 'salvage_value',
                'useful_life', 'depreciation_method', 'notes',
                'expense_account_id', 'accumulated_account_id'
            ),
            ['is_active' => true]
        ));

        return back()->with('success', 'Aset berhasil ditambahkan.');
    }

    public function update(Request $request, FixedAsset $fixedAsset)
    {
        $request->validate([
            'name'                   => 'required|string|max:255',
            'category'               => 'required|string|max:100',
            'acquired_at'            => 'required|date',
            'cost'                   => 'required|numeric|min:0',
            'salvage_value'          => 'required|numeric|min:0',
            'useful_life'            => 'required|integer|min:1',
            'depreciation_method'    => 'required|in:straight_line',
            'expense_account_id'     => 'nullable|exists:accounts,id',
            'accumulated_account_id' => 'nullable|exists:accounts,id',
            'is_active'              => 'boolean',
            'notes'                  => 'nullable|string',
        ]);

        $fixedAsset->update($request->only(
            'name', 'category', 'acquired_at', 'cost', 'salvage_value',
            'useful_life', 'depreciation_method', 'notes',
            'expense_account_id', 'accumulated_account_id', 'is_active'
        ));

        $hasPostedJournal = \App\Models\AdjustingJournal::where('source_id', $fixedAsset->id)
            ->where('source_type', \App\Models\FixedAsset::class)
            ->where('status', 'posted')
            ->exists();

        $msg = 'Aset berhasil diupdate.';
        if ($hasPostedJournal) {
            $msg .= ' Perhatian: aset ini sudah memiliki jurnal depresiasi — perubahan nilai hanya berlaku untuk perhitungan bulan berikutnya.';
        }

        return back()->with('success', $msg);
    }

    public function generateDepreciation(\Illuminate\Http\Request $request)
    {
        $period = $request->input('period', now()->format('Y-m-d'));
        \Artisan::call('finance:generate-ajp', ['--period' => $period]);
        return back()->with('success', 'Jurnal penyusutan berhasil di-generate dan diposting ke Jurnal Penyesuaian.');
    }

    public function destroy(FixedAsset $fixedAsset)
    {
        $hasJournal = \App\Models\AdjustingJournal::where('source_id', $fixedAsset->id)
            ->where('source_type', \App\Models\FixedAsset::class)
            ->where('status', 'posted')
            ->exists();

        if ($hasJournal) {
            return back()->with('error', 'Aset tidak bisa dihapus karena sudah memiliki jurnal depresiasi. Nonaktifkan aset dengan mengubah status is_active menjadi false.');
        }

        $fixedAsset->delete();
        return back()->with('success', 'Aset berhasil dihapus.');
    }
}
