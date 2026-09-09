<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquityStatementController extends Controller
{
    public function __construct(protected FinancialReportService $reports) {}

    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $years = range(now()->year - 2, now()->year + 2);

        $startOfYear = "{$year}-01-01";
        $endOfYear = "{$year}-12-31";
        $endOfLastYear = ($year - 1).'-12-31';

        // Modal AWAL = ekuitas disetor + akumulasi laba ditahan s.d. akhir tahun
        // lalu. (Tanpa jurnal penutup, laba ditahan masih "menempel" di akun
        // Revenue/Expense → harus dihitung dari P&L akumulatif, sama seperti
        // Neraca, supaya kedua laporan konsisten.)
        $equityContributedLastYear = (float) DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Equity')
            ->whereDate('journals.date', '<=', $endOfLastYear)
            ->selectRaw('SUM(journal_items.credit) - SUM(journal_items.debit) as balance')
            ->value('balance') ?? 0;
        $modalAwal = $equityContributedLastYear + $this->reports->netProfitToDate($endOfLastYear);

        // Laba bersih tahun berjalan — rumus tunggal (contra-revenue 4111 sudah
        // dikurangkan dengan benar di dalam service).
        $labaBersih = (float) $this->reports->profitLoss($startOfYear, $endOfYear)['netProfit'];

        // Prive / drawing: akun Equity bernama prive/drawing/withdrawal, mutasi
        // debit tahun ini (pengurang ekuitas).
        $prive = (float) DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Equity')
            ->where(function ($q) {
                $q->where('accounts.name', 'like', '%prive%')
                    ->orWhere('accounts.name', 'like', '%drawing%')
                    ->orWhere('accounts.name', 'like', '%withdrawal%');
            })
            ->whereBetween('journals.date', [$startOfYear, $endOfYear])
            ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
            ->value('balance') ?? 0;

        // Setoran modal baru tahun ini (akun Equity non-prive, mutasi kredit).
        $setoran = (float) DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Equity')
            ->where(function ($q) {
                $q->where('accounts.name', 'not like', '%prive%')
                    ->where('accounts.name', 'not like', '%drawing%')
                    ->where('accounts.name', 'not like', '%withdrawal%');
            })
            ->whereBetween('journals.date', [$startOfYear, $endOfYear])
            ->selectRaw('SUM(journal_items.credit) - SUM(journal_items.debit) as balance')
            ->value('balance') ?? 0;

        // Modal AKHIR = modal awal + setoran + laba − prive. Ini SELALU sama
        // dengan Total Ekuitas di Neraca per 31 Des tahun ini.
        $modalAkhir = $modalAwal + $setoran + $labaBersih - $prive;

        return view('admin.reports.equity_statement', compact(
            'year', 'years', 'modalAwal', 'labaBersih', 'prive', 'setoran', 'modalAkhir'
        ));
    }
}
