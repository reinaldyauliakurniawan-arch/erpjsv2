<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\ClassSession;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'students_count'             => Student::count(),
            'tutors_count'               => Tutor::count(),
            'active_enrollments'         => Enrollment::where('status', 'active')->count(),
            'pending_tutors_enrollments' => Enrollment::whereHas('tutors', function($q) {
                $q->where('enrollment_tutor.status', 'pending');
            })->count(),
        ];

        $today   = Carbon::today();
        $in7days = Carbon::today()->addDays(7);

        $expiringEnrollments = Enrollment::with(['student.user', 'program'])
            ->where('status', 'active')
            ->whereBetween('expiry_date', [$today, $in7days])
            ->orderBy('expiry_date')
            ->get();

        $unpaidInstallments = Installment::with(['enrollment.student.user', 'enrollment.program'])
            ->whereNull('paid_at')
            ->orderBy('due_date')
            ->get();

        // Waiting list reguler: enrollment pending, program type = reguler
        $waitingReguler = Enrollment::with(['student.user', 'program', 'classSession'])
            ->where('status', 'pending')
            ->whereHas('program', fn($q) => $q->where('type', 'reguler'))
            ->orderBy('created_at')
            ->get();

        // Waiting list private: enrollment pending, program type = private
        $waitingPrivate = Enrollment::with(['student.user', 'program', 'tutors'])
            ->where('status', 'pending')
            ->whereHas('program', fn($q) => $q->where('type', 'private'))
            ->orderBy('created_at')
            ->get();

        // Waiting list semi-private: enrollment pending, program type = semi-private
        $waitingSemi = Enrollment::with(['student.user', 'program', 'classSession'])
            ->where('status', 'pending')
            ->whereHas('program', fn($q) => $q->where('type', 'semi-private'))
            ->orderBy('created_at')
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'expiringEnrollments',
            'unpaidInstallments',
            'waitingReguler',
            'waitingPrivate',
            'waitingSemi'
        ));
    }
}
