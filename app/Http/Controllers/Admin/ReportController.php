<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function trialBalance()
    {
        $balances = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, SUM(journal_items.debit) as debit, SUM(journal_items.credit) as credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get()
            ->filter(fn($r) => $r->debit > 0 || $r->credit > 0);

        $rows        = $balances;
        $totalDebit  = $balances->sum('debit');
        $totalCredit = $balances->sum('credit');

        return view('admin.reports.trial_balance', compact('rows', 'totalDebit', 'totalCredit'));
    }

    public function profitLoss(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());

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
            ->get()
            ->filter(fn($r) => $r->amount > 0);

        $totalRevenue = $rows->where('type', 'Revenue')->sum('amount');
        $totalExpense = $rows->where('type', 'Expense')->sum('amount');
        $netProfit = bcsub((string) $totalRevenue, (string) $totalExpense, 2);

        return view('admin.reports.profit_loss', compact('rows', 'totalRevenue', 'totalExpense', 'netProfit', 'from', 'to'));
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

        return view('admin.reports.balance_sheet', compact('rows', 'totalAsset', 'totalLiability', 'totalEquity', 'asOf'));
    }

    public function deferredRevenue()
    {
        $enrollments = DB::table('enrollments')
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
                'programs.total_meetings',
                'users.name as student_name',
                'programs.name as program_name',
                DB::raw('COALESCE(paid.paid_amount, 0) as paid_amount')
            )
            ->get()
            ->map(function ($e) {
                if ($e->total_meetings <= 0 || $e->paid_amount <= 0) return null;

                $ratePerMeeting = bcdiv((string) $e->paid_amount, (string) $e->total_meetings, 2);
                $deferredAmount = bcmul((string) $e->remaining_meetings, $ratePerMeeting, 2);

                return [
                    'student_name'     => $e->student_name,
                    'program_name'     => $e->program_name,
                    'total_meetings'   => $e->total_meetings,
                    'meetings_used'    => $e->total_meetings - $e->remaining_meetings,
                    'remaining'        => $e->remaining_meetings,
                    'paid_amount'      => $e->paid_amount,
                    'rate_per_meeting' => $ratePerMeeting,
                    'deferred_amount'  => $deferredAmount,
                ];
            })
            ->filter(fn($e) => $e && $e['deferred_amount'] > 0)
            ->sortByDesc('deferred_amount')
            ->values();

        $totalDeferred = $enrollments->sum('deferred_amount');

        return view('admin.reports.deferred_revenue', compact('enrollments', 'totalDeferred'));
    }
}
