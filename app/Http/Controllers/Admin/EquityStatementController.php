<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquityStatementController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
        $years = range(now()->year - 2, now()->year + 2);

        $startOfYear = "{$year}-01-01";
        $endOfYear   = "{$year}-12-31";

        // Modal awal: saldo akun Equity s.d. 31 Des tahun lalu
        $endOfLastYear = ($year - 1) . '-12-31';
        $modalAwal = (float) DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Equity')
            ->whereDate('journals.date', '<=', $endOfLastYear)
            ->selectRaw('SUM(journal_items.credit) - SUM(journal_items.debit) as balance')
            ->value('balance') ?? 0;

        // Laba bersih tahun berjalan — formula identik dengan profitLoss()
        $plRows = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.type', ['Revenue', 'Expense'])
            ->whereBetween('journals.date', [$startOfYear, $endOfYear])
            ->selectRaw("accounts.type, accounts.code,
                SUM(CASE WHEN accounts.type = 'Revenue' THEN (journal_items.credit - journal_items.debit) ELSE (journal_items.debit - journal_items.credit) END) as amount")
            ->groupBy('accounts.type', 'accounts.code')
            ->get();

        $contraRevenueCodes = ['4111'];
        $totalRevenue = $plRows->where('type', 'Revenue')->whereNotIn('code', $contraRevenueCodes)->sum('amount');
        $totalContra  = $plRows->where('type', 'Revenue')->whereIn('code', $contraRevenueCodes)->sum('amount');
        $totalExpense = $plRows->where('type', 'Expense')->sum('amount');
        $labaBersih   = (float) ($totalRevenue - $totalContra - $totalExpense);

        // Prive: akun Equity yang normanya debit (drawing/prive), cari by name
        $prive = (float) DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Equity')
            ->where(function($q) {
                $q->where('accounts.name', 'like', '%prive%')
                  ->orWhere('accounts.name', 'like', '%drawing%')
                  ->orWhere('accounts.name', 'like', '%withdrawal%');
            })
            ->whereBetween('journals.date', [$startOfYear, $endOfYear])
            ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
            ->value('balance') ?? 0;

        $modalAkhir = $modalAwal + $labaBersih - $prive;

        return view('admin.reports.equity_statement', compact(
            'year', 'years', 'modalAwal', 'labaBersih', 'prive', 'modalAkhir'
        ));
    }
}
