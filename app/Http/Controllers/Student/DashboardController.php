<?php

namespace App\Http\Controllers\Student;

use App\Enums\DayOfWeek;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\RoomBooking;
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

        // ── Sesi berikutnya per enrollment (sadar "skip") ────────────────
        // Kalau tutor/admin men-skip satu pertemuan, sesi itu dilewati di sini
        // juga — jadi jadwal yang dilihat siswa selalu cocok dengan yang
        // dilihat tutor & admin.
        $today = Carbon::today();

        // Semua skip mendatang untuk class session yang diikuti siswa ini.
        $sessionIds = $enrollments->pluck('class_session_id')->filter()->unique();
        $skips = RoomBooking::where('type', 'regular_skip')
            ->whereDate('date', '>=', $today->toDateString())
            ->when($sessionIds->isNotEmpty(), fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('schedule_id', function ($s) use ($sessionIds) {
                    $s->select('id')->from('schedules')->whereIn('class_session_id', $sessionIds);
                })
                ->orWhereIn('classroom_id', function ($s) use ($sessionIds) {
                    $s->select('classroom_id')->from('schedules')->whereIn('class_session_id', $sessionIds);
                })))
            ->get(['classroom_id', 'time_block', 'date']);

        $isSkipped = fn (string $classroomId, string $block, Carbon $date) => $skips->contains(
            fn ($b) => (int) $b->classroom_id === (int) $classroomId
                && $b->time_block === $block
                && Carbon::parse($b->date)->toDateString() === $date->toDateString()
        );

        $nextSessions = [];
        foreach ($enrollments as $enrollment) {
            $nextSessions[$enrollment->id] = null;
            if ($enrollment->status !== 'active' || $enrollment->schedules->isEmpty()) {
                continue;
            }

            $skippedBefore = null;
            for ($i = 0; $i <= 21; $i++) {
                $date = $today->copy()->addDays($i);
                $dayName = DayOfWeek::fromDate($date)->value;
                $slot = $enrollment->schedules->firstWhere('day', $dayName);
                if (! $slot) {
                    continue;
                }

                $entry = [
                    'day' => $slot->day,
                    'time_block' => $slot->time_block,
                    'classroom' => $slot->classroom?->name ?? '—',
                    'date' => $date->isoFormat('D MMM YYYY'),
                    'is_today' => $i === 0,
                ];

                if ($isSkipped((string) $slot->classroom_id, $slot->time_block, $date)) {
                    $skippedBefore ??= $entry; // catat sesi terdekat yang diliburkan

                    continue;
                }

                $entry['skipped_before'] = $skippedBefore; // sesi sebelumnya yang libur (kalau ada)
                $nextSessions[$enrollment->id] = $entry;
                break;
            }
        }

        return view('student.dashboard', compact(
            'enrollments', 'attendanceHistory', 'attendanceCounts',
            'nextSessions', 'today'
        ));
    }
}
