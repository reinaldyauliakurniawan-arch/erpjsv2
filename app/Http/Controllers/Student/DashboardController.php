<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user    = Auth::user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $enrollments = Enrollment::with([
            'program', 'installments', 'classSession',
            'tutors.user',
            'schedules.classroom',
        ])
        ->where('student_id', $student->id)
        ->get();

        // Hitung total hadir per enrollment
        $attendanceCounts = DB::table('attendance_student')
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('is_present', true)
            ->select('enrollment_id', DB::raw('count(*) as total_attended'))
            ->groupBy('enrollment_id')
            ->pluck('total_attended', 'enrollment_id');

        // Attendance history — include catatan kelas (attendance.notes) + catatan per siswa
        $attendanceHistory = DB::table('attendance_student')
            ->join('attendance', 'attendance_student.attendance_id', '=', 'attendance.id')
            ->join('enrollments', 'attendance_student.enrollment_id', '=', 'enrollments.id')
            ->whereIn('attendance_student.enrollment_id', $enrollments->pluck('id'))
            ->select(
                'attendance.id as attendance_id',
                'attendance.date',
                'attendance.time_block',
                'attendance.notes as class_notes',
                'attendance_student.is_present',
                'attendance_student.notes as personal_notes',
                'enrollments.id as enrollment_id',
            )
            ->orderByDesc('attendance.date')
            ->limit(50)
            ->get();

        // Next session per enrollment
        $days = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 0];
        $today = Carbon::today();
        $todayDow = $today->dayOfWeek; // 0=Sun, 1=Mon...

        $nextSessions = [];
        foreach ($enrollments as $enrollment) {
            $next = null;
            $minDiff = PHP_INT_MAX;
            foreach ($enrollment->schedules as $schedule) {
                $scheduleDow = $days[$schedule->day] ?? null;
                if ($scheduleDow === null) continue;
                $diff = ($scheduleDow - $todayDow + 7) % 7;
                if ($diff === 0) $diff = 0; // hari ini
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $next = [
                        'day'        => $schedule->day,
                        'time_block' => $schedule->time_block,
                        'classroom'  => $schedule->classroom?->name ?? '—',
                        'date'       => $today->copy()->addDays($diff)->isoFormat('D MMM YYYY'),
                        'is_today'   => $diff === 0,
                    ];
                }
            }
            $nextSessions[$enrollment->id] = $next;
        }

        return view('student.dashboard', compact(
            'enrollments', 'attendanceHistory', 'attendanceCounts',
            'nextSessions', 'today'
        ));
    }
}
