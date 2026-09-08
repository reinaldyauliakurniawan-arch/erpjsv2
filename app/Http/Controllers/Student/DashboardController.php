<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Enrollment;
use App\Support\UpcomingSessions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // ── Sesi berikutnya per enrollment (sadar "skip" & "pindah ruang") ─
        // Pakai App\Support\UpcomingSessions supaya identik dengan yang dilihat
        // admin di halaman enrollment, tutor di jadwal, dsb.
        $today = Carbon::today();
        $nextSessions = [];
        foreach ($enrollments as $enrollment) {
            $nextSessions[$enrollment->id] = null;
            if ($enrollment->status !== 'active') {
                continue;
            }

            $upcoming = UpcomingSessions::forEnrollment($enrollment, limit: 6);
            $skippedBefore = null;
            foreach ($upcoming as $s) {
                if ($s['status'] === 'skipped') {
                    $skippedBefore ??= [
                        'day' => $s['day'],
                        'date' => $s['date_label'],
                    ];

                    continue;
                }
                $nextSessions[$enrollment->id] = [
                    'day' => $s['day'],
                    'time_block' => $s['time_block'],
                    'classroom' => $s['classroom'],
                    'date' => $s['date_label'],
                    'is_today' => $s['is_today'],
                    'moved_to' => $s['status'] === 'moved' ? $s['moved_to'] : null,
                    'skipped_before' => $skippedBefore,
                ];
                break;
            }
        }

        return view('student.dashboard', compact(
            'enrollments', 'attendanceHistory', 'attendanceCounts',
            'nextSessions', 'today'
        ));
    }
}
