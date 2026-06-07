<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index()
    {
        $payrollRuns = PayrollRun::latest()->get();
        return view('admin.finance.payroll.index', compact('payrollRuns'));
    }

    public function store(Request $request)
    {
        $request->validate(['month' => 'required|date']);

        try {
            $this->payrollService->createPayrollRun($request->month);
        } catch (\App\Exceptions\DomainException $e) {
            return redirect()->route('admin.payroll.index')->withErrors(['month' => $e->getMessage()]);
        }

        return redirect()->route('admin.payroll.index')->with('success', 'Payroll run initiated.');
    }

    public function approve($id)
    {
        try {
            $this->payrollService->approvePayrollRun($id, Auth::id());
        } catch (\App\Exceptions\DomainException $e) {
            return redirect()->route('admin.payroll.index')->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('admin.payroll.index')->with('success', 'Payroll approved and journals generated.');
    }
    public function reverse(int $id, Request $request)
    {
    try {
        $this->payrollService->reversePayrollRun($id, Auth::id());
        return back()->with('success', 'Payroll run berhasil di-reverse. Attendance bulan tersebut dapat di-reverse kembali.');
    } catch (\App\Exceptions\DomainException $e) {
        return back()->withErrors(['error' => $e->getMessage()]);
    }
    }
}
