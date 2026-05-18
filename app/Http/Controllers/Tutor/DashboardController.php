<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $classes = DB::table('enrollment_tutor')
            ->join('enrollments', 'enrollment_tutor.enrollment_id', '=', 'enrollments.id')
            ->join('programs', 'enrollments.program_id', '=', 'programs.id')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('enrollment_tutor.tutor_id', $tutor->id)
            ->select('enrollments.id', 'programs.name as program_name', 'users.name as student_name', 'enrollment_tutor.status')
            ->get();

        $unpaidTotal = DB::table('attendance_tutor')
            ->where('tutor_id', $tutor->id)
            ->whereNull('paid_at')
            ->where('pending_rate', false)
            ->sum('payable_amount');

        $paidThisMonth = DB::table('attendance_tutor')
            ->where('tutor_id', $tutor->id)
            ->whereNotNull('paid_at')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('payable_amount');

        $pendingRateCount = DB::table('attendance_tutor')
            ->where('tutor_id', $tutor->id)
            ->where('pending_rate', true)
            ->whereNull('paid_at')
            ->count();

        $recentAttendances = Attendance::with(['classSession.program', 'students.student.user', 'tutors'])
            ->whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id))
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        return view('tutor.dashboard', compact(
            'classes', 'unpaidTotal', 'paidThisMonth', 'pendingRateCount', 'recentAttendances'
        ));
    }
}
