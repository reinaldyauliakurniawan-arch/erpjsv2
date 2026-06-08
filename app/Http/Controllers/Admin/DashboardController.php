<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\RoomBooking;
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

        // ── Room Occupancy (minggu ini) ───────────────────────
        $days       = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
        $timeBlocks = ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'];

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->endOfWeek();

        $physicalClassrooms = Classroom::where('is_at_just_speak', true)->get();
        $physicalIds        = $physicalClassrooms->pluck('id');

        $totalSlots = $physicalClassrooms->count() * 7 * count($timeBlocks);

        $scheduledSlots = Schedule::whereIn('classroom_id', $physicalIds)
            ->select('classroom_id', 'day', 'time_block')
            ->distinct()
            ->get();

        $skippedSlots = RoomBooking::whereIn('classroom_id', $physicalIds)
            ->where('type', 'regular_skip')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->select('classroom_id', 'time_block', 'date')
            ->get();

        $tempSlots = RoomBooking::whereIn('classroom_id', $physicalIds)
            ->where('type', 'temporary')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->select('classroom_id', 'time_block', 'date')
            ->get();

        $occupiedCount = 0;
        foreach ($physicalClassrooms as $room) {
            foreach ($timeBlocks as $block) {
                foreach ($scheduledSlots->where('classroom_id', $room->id)->where('time_block', $block) as $sched) {
                    $dayIndex = array_search($sched->day, $days);
                    if ($dayIndex === false) continue;
                    $date      = $weekStart->copy()->addDays($dayIndex)->toDateString();
                    $isSkipped = $skippedSlots->where('classroom_id', $room->id)->where('time_block', $block)->where('date', $date)->isNotEmpty();
                    $isTemp    = $tempSlots->where('classroom_id', $room->id)->where('time_block', $block)->where('date', $date)->isNotEmpty();
                    if (!$isSkipped || $isTemp) $occupiedCount++;
                }
                $hasTempOnly = $tempSlots->where('classroom_id', $room->id)->where('time_block', $block)->isNotEmpty();
                $hasSchedule = $scheduledSlots->where('classroom_id', $room->id)->where('time_block', $block)->isNotEmpty();
                if ($hasTempOnly && !$hasSchedule) $occupiedCount++;
            }
        }

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
