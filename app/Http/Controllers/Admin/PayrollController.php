<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Tutor;
use App\Models\TutorFeeAdjustment;
use App\Services\PayrollService;
use App\Support\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index(Request $request)
    {
        // Paginated (was ->get()) — prevents memory blowup once payroll
        // history grows past a few hundred runs.
        $payrollRuns = PayrollRun::with(['approvedBy', 'reversedBy'])
            ->latest()
            ->paginate(20);

        // Rekap periode (default: payroll yang sedang berjalan menurut tanggal hari ini).
        $month = $request->filled('period')
            ? Carbon::parse($request->get('period').'-01')
            : PayrollPeriod::monthFor(now());
        [$from, $to] = PayrollPeriod::forMonth($month);
        $preview = $this->payrollService->preview($month);
        $adjustments = TutorFeeAdjustment::with(['tutor.user', 'payrollRun'])
            ->inPayrollPeriod([$from->toDateString(), $to->toDateString()])
            ->orderBy('date')->orderBy('id')->get();
        $tutors = Tutor::with('user')->get()->sortBy('user.name');

        return view('admin.finance.payroll.index', compact('payrollRuns', 'month', 'preview', 'adjustments', 'tutors'));
    }

    public function storeAdjustment(Request $request)
    {
        $data = $request->validate([
            'tutor_id' => 'required|exists:tutors,id',
            'date' => 'required|date',
            'kind' => 'required|in:'.implode(',', array_keys(TutorFeeAdjustment::KINDS)),
            'amount' => 'required|numeric|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        try {
            $adj = $this->payrollService->addAdjustment(
                (int) $data['tutor_id'], $data['date'], (string) $data['amount'], $data['kind'], $data['description'], Auth::id()
            );
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('finance.payroll.index', ['period' => PayrollPeriod::monthFor($adj->date)->format('Y-m')])
            ->with('success', 'Penyesuaian honor dicatat.');
    }

    public function destroyAdjustment(int $id)
    {
        try {
            $this->payrollService->deleteAdjustment($id);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Penyesuaian honor dihapus.');
    }

    public function store(Request $request)
    {
        $request->validate(['month' => 'required|date']);

        try {
            $this->payrollService->createPayrollRun($request->month);
        } catch (DomainException $e) {
            return redirect()->route('finance.payroll.index')->withErrors(['month' => $e->getMessage()]);
        }

        return redirect()->route('finance.payroll.index')->with('success', 'Payroll run initiated.');
    }

    public function approve($id)
    {
        try {
            $this->payrollService->approvePayrollRun($id, Auth::id());
        } catch (DomainException $e) {
            return redirect()->route('finance.payroll.index')->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('finance.payroll.index')->with('success', 'Payroll approved and journals generated.');
    }

    public function reverse(int $id, Request $request)
    {
        try {
            $this->payrollService->reversePayrollRun($id, Auth::id());

            return back()->with('success', 'Payroll run berhasil di-reverse. Attendance bulan tersebut dapat di-reverse kembali.');
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
