<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Classroom;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ── KPI ──────────────────────────────────────────────
        $activeStudents = Enrollment::where('status', 'active')
            ->distinct('student_id')
            ->count('student_id');

        $stats = [
            'active_students'            => $activeStudents,
            'tutors_count'               => Tutor::count(),
            'pending_tutors_enrollments' => Enrollment::whereHas('tutors', function ($q) {
                $q->where('enrollment_tutor.status', 'pending');
            })->count(),
        ];

        // ── Room Occupancy (minggu ini) ────────────────────────
        // Reuses the exact same slot logic as ClassroomController@buildOccupancyStats
        // so the number here always matches /admin/classrooms.
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->endOfWeek();

        $classroomController = app(\App\Http\Controllers\Admin\ClassroomController::class);
        $occupancyStats = $classroomController->buildOccupancyStats($weekStart, $weekEnd);

        $occupiedCount = array_sum(array_column($occupancyStats, 'occupied'));
        $totalSlots    = array_sum(array_column($occupancyStats, 'total'));
        $occupancyRate = $totalSlots > 0 ? round($occupiedCount / $totalSlots * 100) : 0;

        // ── Waiting List ──────────────────────────────────────
        $waitingReguler = Enrollment::with(['student.user', 'program', 'classSession', 'tutors'])
            ->where('status', 'waitlist')
            ->whereHas('program', fn($q) => $q->where('type', 'group'))
            ->orderBy('created_at')
            ->get();

        $waitingPrivate = Enrollment::with(['student.user', 'program', 'tutors'])
            ->where('status', 'waitlist')
            ->whereHas('program', fn($q) => $q->where('type', 'private'))
            ->orderBy('created_at')
            ->get();

        $waitingSemi = Enrollment::with(['student.user', 'program', 'classSession', 'tutors'])
            ->where('status', 'waitlist')
            ->whereHas('program', fn($q) => $q->where('type', 'semi-private'))
            ->orderBy('created_at')
            ->get();

        // ── Action Tables ─────────────────────────────────────
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
            ->limit(50)
            ->get();

        // ── Insight ───────────────────────────────────────────
        $typeLabels = [
            'group'        => 'Group / Reguler',
            'private'      => 'Private',
            'semi-private' => 'Semi-Private',
        ];

        $enrollmentDistribution = Enrollment::with('program')
            ->where('status', 'active')
            ->get()
            ->groupBy(fn($e) => $typeLabels[$e->program->type ?? ''] ?? 'Lainnya')
            ->map->count();

        $newStudents = Student::with('user')
            ->where('created_at', '>=', now()->subMonth())
            ->orderByDesc('created_at')
            ->get();

        $educationStats = Student::selectRaw('COALESCE(education_level, "Tidak diisi") as education_level, count(*) as total')
            ->groupBy('education_level')
            ->orderByRaw('FIELD(education_level, "SD","SMP","SMA","Kuliah","Umum") DESC')
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'activeStudents',
            'occupiedCount',
            'totalSlots',
            'occupancyRate',
            'expiringEnrollments',
            'unpaidInstallments',
            'waitingReguler',
            'waitingPrivate',
            'waitingSemi',
            'enrollmentDistribution',
            'newStudents',
            'educationStats'
        ));
    }
}
