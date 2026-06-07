<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Account;
use App\Models\Enrollment;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\AccountCode;
use Carbon\Carbon;

class FinanceController extends Controller
{
    public function __construct(protected AccountingService $accountingService) {}

    public function dashboard(Request $request)
    {
        $month     = $request->input('month', now()->format('Y-m'));
        $startDate = Carbon::parse($month)->startOfMonth()->toDateString();
        $endDate   = Carbon::parse($month)->endOfMonth()->toDateString();

        $revenue = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Revenue')
            ->whereBetween('journals.date', [$startDate, $endDate])
            ->sum('journal_items.credit');

        $expense = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Expense')
            ->whereBetween('journals.date', [$startDate, $endDate])
            ->sum('journal_items.debit');

        $netProfit = $revenue - $expense;

        $deferredRevenue = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->where('accounts.code', AccountCode::DEFERRED_REVENUE->value)
            ->selectRaw('SUM(journal_items.credit) - SUM(journal_items.debit) as balance')
            ->value('balance') ?? 0;

        $tutorPayable = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->where('accounts.code', AccountCode::TUTOR_PAYABLE->value)
            ->selectRaw('SUM(journal_items.credit) - SUM(journal_items.debit) as balance')
            ->value('balance') ?? 0;

        $journals = Journal::with('items.account')
            ->whereBetween('date', [$startDate, $endDate])
            ->latest()
            ->take(10)
            ->get();

        $overdueInstallments = DB::table('installments')
            ->join('enrollments', 'installments.enrollment_id', '=', 'enrollments.id')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->join('programs', 'enrollments.program_id', '=', 'programs.id')
            ->whereNull('installments.paid_at')
            ->whereDate('installments.due_date', '<', now()->toDateString())
            ->select(
                'installments.id',
                'installments.amount',
                'installments.due_date',
                'users.name as student_name',
                'programs.name as program_name',
                'enrollments.id as enrollment_id'
            )
            ->orderBy('installments.due_date')
            ->limit(100)
            ->get();

        $overdueTotalAmount = $overdueInstallments->sum('amount');

        $pendingRates = DB::table('attendance_tutor')
            ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
            ->join('tutors', 'attendance_tutor.tutor_id', '=', 'tutors.id')
            ->join('users', 'tutors.user_id', '=', 'users.id')
            ->join('class_sessions', 'attendance.class_session_id', '=', 'class_sessions.id')
            ->join('programs', 'class_sessions.program_id', '=', 'programs.id')
            ->where('attendance_tutor.pending_rate', true)
            ->select(
                'attendance_tutor.id',
                'attendance_tutor.attendance_id',
                'attendance_tutor.tutor_id',
                'users.name as tutor_name',
                'programs.id as program_id',
                'programs.name as program_name',
                'attendance.date',
                'class_sessions.name as session_name'
            )
            ->orderBy('attendance.date')
            ->get();

        // Chart 1: Revenue vs Expense 12 bulan terakhir
        $chartMonths  = [];
        $chartRevenue = [];
        $chartExpense = [];

        for ($i = 11; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $start = $date->copy()->startOfMonth()->toDateString();
            $end   = $date->copy()->endOfMonth()->toDateString();

            $chartMonths[] = $date->translatedFormat('M Y');

            $chartRevenue[] = (float) DB::table('journal_items')
                ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
                ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
                ->where('accounts.type', 'Revenue')
                ->whereBetween('journals.date', [$start, $end])
                ->sum('journal_items.credit');

            $chartExpense[] = (float) DB::table('journal_items')
                ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
                ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
                ->where('accounts.type', 'Expense')
                ->whereBetween('journals.date', [$start, $end])
                ->sum('journal_items.debit');
        }

        // Cash Balance
        $cashBalance = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->whereIn('accounts.code', ['1001', '1002'])
            ->selectRaw('SUM(journal_items.debit) - SUM(journal_items.credit) as balance')
            ->value('balance') ?? 0;

        // Collection Rate bulan ini
        $totalTagihan = DB::table('installments')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->sum('amount');
        $totalTerbayar = DB::table('installments')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->whereNotNull('paid_at')
            ->sum('amount');
        $collectionRate = $totalTagihan > 0 ? round(($totalTerbayar / $totalTagihan) * 100, 1) : 0;

        // Burn Rate: rata-rata expense 3 bulan terakhir
        $burnRate = collect(range(1, 6))->map(fn($i) => (float) DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->where('accounts.type', 'Expense')
            ->whereBetween('journals.date', [
                now()->subMonths($i)->startOfMonth()->toDateString(),
                now()->subMonths($i)->endOfMonth()->toDateString(),
            ])
            ->sum('journal_items.debit')
        )->average();
        $runwayMonths = $burnRate > 0 ? floor($cashBalance / $burnRate) : null;

        // Chart 2: Enrollment per program
        $enrollmentByProgram = DB::table('enrollments')
            ->join('programs', 'enrollments.program_id', '=', 'programs.id')
            ->whereNotIn('enrollments.status', ['cancelled', 'refunded'])
            ->selectRaw('programs.name, COUNT(enrollments.id) as total')
            ->groupBy('programs.name')
            ->orderByDesc('total')
            ->get();

        $chartProgramLabels = $enrollmentByProgram->pluck('name')->toArray();
        $chartProgramData   = $enrollmentByProgram->pluck('total')->toArray();

// Chart 3: Revenue per program (bulan ini) — dari journal_items dengan program_id
        $revenueByProgram = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->join('programs', 'journal_items.program_id', '=', 'programs.id')
            ->where('accounts.code', AccountCode::REVENUE_TUITION_FEES->value)
            ->whereBetween('journals.date', [$startDate, $endDate])
            ->selectRaw('programs.name, SUM(journal_items.credit) as total')
            ->groupBy('programs.name')
            ->orderByDesc('total')
            ->get();

        $chartProgramRevenueLabels = $revenueByProgram->pluck('name')->toArray();
        $chartProgramRevenueData   = $revenueByProgram->pluck('total')->map(fn($v) => (float)$v)->toArray();

        return view('admin.finance.dashboard', compact(
            'revenue', 'expense', 'netProfit',
            'cashBalance', 'collectionRate', 'burnRate', 'runwayMonths',
            'deferredRevenue', 'tutorPayable',
            'journals', 'month',
            'overdueInstallments', 'overdueTotalAmount',
            'pendingRates',
            'chartMonths', 'chartRevenue', 'chartExpense',
            'chartProgramLabels', 'chartProgramData',
            'chartProgramRevenueLabels', 'chartProgramRevenueData'
        ));
    }

    public function chartRevenueByProgram(Request $request)
    {
        $period = $request->input('period', 'year');
        $from   = $request->input('from');
        $to     = $request->input('to');

        if ($period === 'custom' && $from && $to) {
            $start = $from;
            $end   = $to;
        } elseif ($period === 'month') {
            $start = now()->startOfMonth()->toDateString();
            $end   = now()->toDateString();
        } elseif ($period === 'quarter') {
            $start = now()->startOfQuarter()->toDateString();
            $end   = now()->toDateString();
        } else {
            $start = now()->startOfYear()->toDateString();
            $end   = now()->toDateString();
        }

        $data = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_items.journal_id', '=', 'journals.id')
            ->join('programs', 'journal_items.program_id', '=', 'programs.id')
            ->where('accounts.code', AccountCode::REVENUE_TUITION_FEES->value)
            ->whereBetween('journals.date', [$start, $end])
            ->selectRaw('programs.name, SUM(journal_items.credit) as total')
            ->groupBy('programs.name')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'labels' => $data->pluck('name'),
            'data'   => $data->pluck('total')->map(fn($v) => (float)$v),
        ]);
    }

    public function assignRate(Request $request, int $attendanceTutorId)
    {
        $request->validate([
            'payable_amount' => 'required|numeric|min:1',
        ]);

        $row = DB::table('attendance_tutor')->where('id', $attendanceTutorId)->first();

        if (!$row || !$row->pending_rate) {
            return back()->with('error', 'Rate sudah di-assign sebelumnya.');
        }

        $reference = 'RATE-AT-' . $attendanceTutorId;

        DB::transaction(function () use ($attendanceTutorId, $reference, $request) {
            $journal = $this->accountingService->createJournal(
                now()->toDateString(),
                'Tutor fee assigned for attendance_tutor #' . $attendanceTutorId,
                $reference,
                [
                    ['account_code' => AccountCode::EXPENSE_TUTOR_FEE->value, 'debit' => $request->payable_amount, 'credit' => 0],
                    ['account_code' => AccountCode::TUTOR_PAYABLE->value,     'debit' => 0, 'credit' => $request->payable_amount],
                ],
                'tutor_accrual'
            );

            DB::table('attendance_tutor')->where('id', $attendanceTutorId)->update([
                'payable_amount' => $request->payable_amount,
                'pending_rate'   => false,
                'journal_id'     => $journal->id,
            ]);
        });

        return back()->with('success', 'Rate berhasil di-assign.');
    }

    public function reports()
    {
        $accounts = Account::all();

        $balances = DB::table('journal_items')
            ->join('accounts', 'journal_items.account_id', '=', 'accounts.id')
            ->selectRaw('accounts.id, accounts.code, accounts.type, SUM(journal_items.debit) as total_debit, SUM(journal_items.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.type')
            ->get()
            ->keyBy('code')
            ->map(function ($row) {
                if (in_array($row->type, ['Asset', 'Expense'])) {
                    return $row->total_debit - $row->total_credit;
                }
                return $row->total_credit - $row->total_debit;
            });

        return view('admin.finance.reports', compact('balances', 'accounts'));
    }
}
