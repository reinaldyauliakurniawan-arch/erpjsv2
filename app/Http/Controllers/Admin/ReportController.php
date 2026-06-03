<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function cashFlow(Request $request)
{
    $period = $request->get('period', 'month');
    $from   = $request->get('from');
    $to     = $request->get('to');

    if (!$from || !$to) {
        $now = now();
        if ($period === 'quarter') {
            $from = $now->copy()->startOfQuarter()->toDateString();
            $to   = $now->copy()->endOfQuarter()->toDateString();
        } elseif ($period === 'year') {
            $from = $now->copy()->startOfYear()->toDateString();
            $to   = $now->toDateString();
        } else {
            $from = $now->copy()->startOfMonth()->toDateString();
            $to   = $now->toDateString();
        }
    }

    // Helper: ambil transaksi per kategori & akun
    $getLines = function (string $category) use ($from, $to) {
        return DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.cash_flow_category', $category)
            ->whereBetween('journals.date', [$from, $to])
            ->selectRaw('
                accounts.id,
                accounts.code,
                accounts.name,
                accounts.type,
                SUM(journal_items.debit)  as total_debit,
                SUM(journal_items.credit) as total_credit
            ')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($r) {
                // Net cash: Revenue/Liability bertambah saat kredit, Asset/Expense saat debit
                $isDebitNormal = in_array($r->type, ['Asset', 'Expense']);
                $r->net = $isDebitNormal
                    ? ((float)$r->total_debit - (float)$r->total_credit)
                    : ((float)$r->total_credit - (float)$r->total_debit);
                return $r;
            });
    };

    $operating  = $getLines('operating');
    $investing  = $getLines('investing');
    $financing  = $getLines('financing');

    $netOperating = $operating->sum('net');
    $netInvesting = $investing->sum('net');
    $netFinancing = $financing->sum('net');
    $netChange    = $netOperating + $netInvesting + $netFinancing;

    // Kas awal: saldo akun Cash & Bank sebelum $from
    $cashOpening = DB::table('journal_items')
        ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
        ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
        ->whereIn('accounts.code', ['1001', '1002'])
        ->whereDate('journals.date', '<', $from)
        ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
        ->value('balance') ?? 0;

    // Kas akhir: saldo akun Cash & Bank sampai $to (harus sama dengan neraca)
    $cashEnding = DB::table('journal_items')
        ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
        ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
        ->whereIn('accounts.code', ['1001', '1002'])
        ->whereDate('journals.date', '<=', $to)
        ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
        ->value('balance') ?? 0;

    return view('admin.reports.cash_flow', compact(
        'operating', 'investing', 'financing',
        'netOperating', 'netInvesting', 'netFinancing',
        'netChange', 'cashOpening', 'cashEnding',
        'from', 'to', 'period'
    ));
}

    public function generalLedger(Request $request)
    {
    $from    = $request->get('from');
    $to      = $request->get('to');
    $period  = $request->get('period', 'month');

    if (!$from || !$to) {
        $now = now();
        if ($period === 'quarter') {
            $from = $now->copy()->startOfQuarter()->toDateString();
            $to   = $now->copy()->endOfQuarter()->toDateString();
        } elseif ($period === 'year') {
            $from = $now->copy()->startOfYear()->toDateString();
            $to   = $now->toDateString();
        } else {
            $from = $now->copy()->startOfMonth()->toDateString();
            $to   = $now->toDateString();
        }
    }

    // Ambil semua akun yang punya transaksi di periode ini
    $accounts = DB::table('accounts')
        ->join('journal_items', 'accounts.id', '=', 'journal_items.account_id')
        ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
        ->whereBetween('journals.date', [$from, $to])
        ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
        ->distinct()
        ->orderBy('accounts.code')
        ->get();

    // Untuk setiap akun, ambil semua transaksinya + hitung saldo berjalan
    $ledger = $accounts->map(function ($account) use ($from, $to) {
        $normalDebet = in_array($account->type, ['Asset', 'Expense']);

        // Saldo awal: semua transaksi SEBELUM $from
        $opening = DB::table('journal_items')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('journal_items.account_id', $account->id)
            ->whereDate('journals.date', '<', $from)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $openingBalance = $normalDebet
            ? ((float)($opening->total_debit ?? 0) - (float)($opening->total_credit ?? 0))
            : ((float)($opening->total_credit ?? 0) - (float)($opening->total_debit ?? 0));

        // Transaksi dalam periode
        $items = DB::table('journal_items')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('journal_items.account_id', $account->id)
            ->whereBetween('journals.date', [$from, $to])
            ->select(
                'journals.date',
                'journals.description',
                'journals.reference',
                'journal_items.debit',
                'journal_items.credit'
            )
            ->orderBy('journals.date')
            ->orderBy('journals.id')
            ->get();

        // Hitung saldo berjalan
        $runningBalance = $openingBalance;
        $rows = $items->map(function ($item) use (&$runningBalance, $normalDebet) {
            $debit  = (float) $item->debit;
            $credit = (float) $item->credit;

            $runningBalance += $normalDebet ? ($debit - $credit) : ($credit - $debit);

            return [
                'date'          => $item->date,
                'description'   => $item->description,
                'reference'     => $item->reference,
                'debit'         => $debit,
                'credit'        => $credit,
                'saldo_debet'   => $runningBalance >= 0 ? $runningBalance : 0,
                'saldo_kredit'  => $runningBalance < 0  ? abs($runningBalance) : 0,
                'running'       => $runningBalance,
            ];
        });

        $finalBalance = $runningBalance;

        return [
            'id'              => $account->id,
            'code'            => $account->code,
            'name'            => $account->name,
            'type'            => $account->type,
            'normal_debet'    => $normalDebet,
            'opening_balance' => $openingBalance,
            'final_balance'   => $finalBalance,
            'rows'            => $rows,
        ];
    });

    // Summary stats
    $totalDebet  = DB::table('journal_items')
        ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
        ->whereBetween('journals.date', [$from, $to])
        ->sum('journal_items.debit');

    $totalKredit = DB::table('journal_items')
        ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
        ->whereBetween('journals.date', [$from, $to])
        ->sum('journal_items.credit');

    $activeAccounts = $ledger->count();

    return view('admin.reports.general_ledger', compact(
        'ledger', 'from', 'to', 'period',
        'totalDebet', 'totalKredit', 'activeAccounts'
    ));
}
    public function adjustedTrialBalance(Request $request)
    {
        $from = $request->get('from');
        $to   = $request->get('to');

        // Kumpulkan posted_journal_id dari AJP yang sudah posted
        $ajpJournalIds = DB::table('adjusting_journals')
            ->whereNotNull('posted_journal_id')
            ->where('status', 'posted')
            ->pluck('posted_journal_id');

        // Base query builder — reusable
        $baseQuery = fn($journalScope) => DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->when($from, fn($q) => $q->whereDate('journals.date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('journals.date', '<=', $to))
            ->tap($journalScope)
            ->selectRaw('
                accounts.id,
                accounts.code,
                accounts.name,
                accounts.type,
                SUM(journal_items.debit)  as debit,
                SUM(journal_items.credit) as credit
            ')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code');

        // Pre-adjustment: semua jurnal KECUALI AJP
        $preRows = $baseQuery(fn($q) => $ajpJournalIds->isNotEmpty()
            ? $q->whereNotIn('journals.id', $ajpJournalIds)
            : $q
        )->get()->keyBy('id');

        // Adjustment only: hanya jurnal AJP yang posted
        $adjRows = $ajpJournalIds->isNotEmpty()
            ? $baseQuery(fn($q) => $q->whereIn('journals.id', $ajpJournalIds))->get()->keyBy('id')
            : collect();

        // Merge semua account_id
        $allIds = $preRows->keys()->merge($adjRows->keys())->unique();

        $normalDebet = ['Asset', 'Expense'];

        $rows = $allIds->map(function ($id) use ($preRows, $adjRows, $normalDebet) {
            $pre = $preRows->get($id);
            $adj = $adjRows->get($id);
            $ref = $pre ?? $adj;

            $preDebit  = (float) ($pre->debit  ?? 0);
            $preCredit = (float) ($pre->credit ?? 0);
            $adjDebit  = (float) ($adj->debit  ?? 0);
            $adjCredit = (float) ($adj->credit ?? 0);

            $isNormal = in_array($ref->type, $normalDebet);

            // Saldo pre-adjustment
            $preSaldo = $isNormal
                ? ($preDebit - $preCredit)
                : ($preCredit - $preDebit);

            // Net adjustment (signed: positif = nambah saldo normal)
            $adjNet = $isNormal
                ? ($adjDebit - $adjCredit)
                : ($adjCredit - $adjDebit);

            // Adjusted saldo
            $adjustedSaldo = $preSaldo + $adjNet;

            return (object) [
                'id'             => $ref->id,
                'code'           => $ref->code,
                'name'           => $ref->name,
                'type'           => $ref->type,
                'pre_saldo'      => $preSaldo,
                'adj_debit'      => $adjDebit,
                'adj_credit'     => $adjCredit,
                'adjusted_saldo' => $adjustedSaldo,
                'has_adj'        => ($adjDebit > 0 || $adjCredit > 0),
            ];
        })->filter(fn($r) => $r->pre_saldo != 0 || $r->adj_debit > 0 || $r->adj_credit > 0)
          ->sortBy('code')
          ->values();

        $totalPreSaldo      = $rows->sum('pre_saldo');
        $totalAdjDebit      = $rows->sum('adj_debit');
        $totalAdjCredit     = $rows->sum('adj_credit');
        $totalAdjustedSaldo = $rows->sum('adjusted_saldo');

        return view('admin.reports.adjusted_trial_balance', compact(
            'rows', 'from', 'to',
            'totalPreSaldo', 'totalAdjDebit', 'totalAdjCredit', 'totalAdjustedSaldo'
        ));
    }

    public function trialBalance(Request $request)
    {
        $query = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, SUM(journal_items.debit) as debit, SUM(journal_items.credit) as credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code');

        if ($request->get('from')) $query->whereDate('journals.date', '>=', $request->get('from'));
        if ($request->get('to'))   $query->whereDate('journals.date', '<=', $request->get('to'));

        $balances    = $query->get()->filter(fn($r) => $r->debit > 0 || $r->credit > 0);
        $rows        = $balances;
        $totalDebit  = $balances->sum('debit');
        $totalCredit = $balances->sum('credit');
        $from        = $request->get('from');
        $to          = $request->get('to');

        return view('admin.reports.trial_balance', compact('rows', 'totalDebit', 'totalCredit', 'from', 'to'));
    }

    public function profitLoss(Request $request)
    {
        $period = $request->get('period', 'month');
        $from   = $request->get('from');
        $to     = $request->get('to');

        if (!$from || !$to) {
            $now = now();
            if ($period === 'quarter') {
                $from = $now->copy()->startOfQuarter()->toDateString();
                $to   = $now->copy()->endOfQuarter()->toDateString();
            } elseif ($period === 'year') {
                $from = $now->copy()->startOfYear()->toDateString();
                $to   = $now->copy()->endOfYear()->toDateString();
            } else {
                $from = $now->copy()->startOfMonth()->toDateString();
                $to   = $now->toDateString();
            }
        }

        $rows = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.type', ['Revenue', 'Expense'])
            ->whereBetween('journals.date', [$from, $to])
            ->selectRaw("
                accounts.id, accounts.code, accounts.name, accounts.type,
                SUM(CASE WHEN accounts.type = 'Revenue' THEN journal_items.credit ELSE journal_items.debit END) as amount
            ")
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();

        $contraRevenueCodes = ['4111'];
        $totalRevenue = $rows->where('type', 'Revenue')->whereNotIn('code', $contraRevenueCodes)->sum('amount');
        $totalContra  = $rows->where('type', 'Revenue')->whereIn('code', $contraRevenueCodes)->sum('amount');
        $totalExpense = $rows->where('type', 'Expense')->sum('amount');
        $netProfit    = $totalRevenue - $totalContra - $totalExpense;

        return view('admin.reports.profit_loss', compact('rows', 'totalRevenue', 'totalContra', 'totalExpense', 'netProfit', 'from', 'to', 'period'));
    }

    public function balanceSheet(Request $request)
    {
        $asOf = $request->get('as_of', now()->toDateString());

        $rows = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.type', ['Asset', 'Liability', 'Equity'])
            ->whereDate('journals.date', '<=', $asOf)
            ->selectRaw("
                accounts.id, accounts.code, accounts.name, accounts.type,
                SUM(journal_items.debit) as total_debit,
                SUM(journal_items.credit) as total_credit
            ")
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($r) {
                $r->balance = $r->type === 'Asset'
                    ? ($r->total_debit - $r->total_credit)
                    : ($r->total_credit - $r->total_debit);
                return $r;
            })
            ->filter(fn($r) => $r->balance != 0);

        $totalAsset     = $rows->where('type', 'Asset')->sum('balance');
        $totalLiability = $rows->where('type', 'Liability')->sum('balance');
        $totalEquity    = $rows->where('type', 'Equity')->sum('balance');

        // Tambah net profit periode berjalan (belum closing)
        $currentYearStart = now()->startOfYear()->toDateString();
        $netProfitCurrent = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->whereIn('accounts.type', ['Revenue', 'Expense'])
            ->whereDate('journals.date', '>=', $currentYearStart)
            ->whereDate('journals.date', '<=', $asOf)
            ->selectRaw("SUM(CASE WHEN accounts.type = 'Revenue' THEN journal_items.credit - journal_items.debit ELSE journal_items.debit - journal_items.credit END) as net")
            ->value('net') ?? 0;

        $totalEquity += (float) $netProfitCurrent;

        $accounts = \App\Models\Account::orderBy('code')->get();
        return view('admin.reports.balance_sheet', compact('rows', 'totalAsset', 'totalLiability', 'totalEquity', 'netProfitCurrent', 'asOf', 'accounts'));
    }

    public function deferredRevenue(Request $request)
    {
        $filterFrom    = $request->get('from');
        $filterTo      = $request->get('to');
        $filterProgram = $request->get('program');

        $query = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->join('programs', 'enrollments.program_id', '=', 'programs.id')
            ->leftJoin(
                DB::raw('(SELECT enrollment_id, SUM(amount) as paid_amount FROM installments WHERE paid_at IS NOT NULL GROUP BY enrollment_id) as paid'),
                'paid.enrollment_id', '=', 'enrollments.id'
            )
            ->where('enrollments.status', 'active')
            ->select(
                'enrollments.id',
                'enrollments.remaining_meetings',
                'enrollments.total_amount',
                'enrollments.payment_method',
                'enrollments.created_at',
                'programs.total_meetings',
                'programs.id as program_id',
                'users.name as student_name',
                'programs.name as program_name',
                DB::raw('COALESCE(paid.paid_amount, 0) as paid_amount')
            );

        if ($filterProgram) $query->where('programs.id', $filterProgram);
        if ($filterFrom)    $query->whereDate('enrollments.created_at', '>=', $filterFrom);
        if ($filterTo)      $query->whereDate('enrollments.created_at', '<=', $filterTo);

        $enrollments = $query->get()->map(function ($e) {
                // Full upfront: paid_amount = total_amount
                $paidAmount = $e->payment_method === 'full upfront'
                    ? (float) $e->total_amount
                    : (float) $e->paid_amount;

                if ($e->total_meetings <= 0 || $paidAmount <= 0) return null;

                $ratePerMeeting   = $paidAmount / $e->total_meetings;
                $meetingsUsed     = $e->total_meetings - $e->remaining_meetings;
                $recognizedAmount = $ratePerMeeting * $meetingsUsed;
                $deferredAmount   = $paidAmount - $recognizedAmount;

                return [
                    'student_name'      => $e->student_name,
                    'program_name'      => $e->program_name,
                    'enrolled_month'    => substr($e->created_at, 0, 7),
                    'total_meetings'    => $e->total_meetings,
                    'meetings_used'     => $meetingsUsed,
                    'remaining'         => $e->remaining_meetings,
                    'paid_amount'       => $paidAmount,
                    'recognized_amount' => (float) $recognizedAmount,
                    'rate_per_meeting'  => (float) $ratePerMeeting,
                    'deferred_amount'   => (float) $deferredAmount,
                ];
            })
            ->filter(fn($e) => $e && $e['deferred_amount'] > 0)
            ->sortByDesc('deferred_amount')
            ->values();

        $totalDeferred = $enrollments->sum('deferred_amount');
        $programs      = DB::table('programs')->select('id', 'name')->orderBy('name')->get();

        return view('admin.reports.deferred_revenue', compact('enrollments', 'totalDeferred', 'programs', 'filterFrom', 'filterTo', 'filterProgram'));
    }

    public function storeOpeningBalance(Request $request)
    {
        $balances = collect($request->get('balances', []))
            ->filter(fn($b) => ($b['debit'] ?? 0) > 0 || ($b['credit'] ?? 0) > 0);

        if ($balances->isEmpty()) {
            return back()->with('error', 'Tidak ada saldo yang diinput.');
        }

        $totalDebit  = $balances->sum(fn($b) => (float) ($b['debit'] ?? 0));
        $totalCredit = $balances->sum(fn($b) => (float) ($b['credit'] ?? 0));

        if (abs($totalDebit - $totalCredit) > 0.001) {
            return back()->with('error', "Saldo awal tidak balance. Debit: Rp " . number_format($totalDebit) . " — Kredit: Rp " . number_format($totalCredit));
        }

        $accountMap = \App\Models\Account::whereIn('id', $balances->keys())->pluck('code', 'id');

        $items = $balances->map(fn($b, $id) => [
            'account_code' => $accountMap[$id],
            'debit'        => (float) ($b['debit'] ?? 0),
            'credit'       => (float) ($b['credit'] ?? 0),
        ])->values()->toArray();

        try {
            app(\App\Services\AccountingService::class)->createJournal(
                now()->toDateString(),
                'Saldo Awal',
                'OB-' . now()->format('Y'),
                $items
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('finance.reports.balance-sheet')->with('success', 'Saldo awal berhasil disimpan.');
    }
}
