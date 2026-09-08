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

        // ── Sesi berikutnya per enrollment (sadar "skip" & "pindah ruang") ─
        // Kalau tutor/admin men-skip / memindahkan satu pertemuan, dashboard
        // siswa ikut menyesuaikan — jadi jadwal yang dilihat siswa selalu
        // cocok dengan yang dilihat tutor & admin.
        $today = Carbon::today();
        $sessionIds = $enrollments->pluck('class_session_id')->filter()->unique();

        $bookings = RoomBooking::with('classroom')
            ->whereDate('date', '>=', $today->toDateString())
            ->when($sessionIds->isNotEmpty(), fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('class_session_id', $sessionIds)
                ->orWhereIn('schedule_id', fn ($s) => $s->select('id')->from('schedules')->whereIn('class_session_id', $sessionIds))
                ->orWhereIn('classroom_id', fn ($s) => $s->select('classroom_id')->from('schedules')->whereIn('class_session_id', $sessionIds))))
            ->get();

        $onDate = fn ($b, Carbon $date) => Carbon::parse($b->date)->toDateString() === $date->toDateString();

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
                    'moved_to' => null,
                ];

                // Pindah ruang: booking sementara untuk class session ini di
                // tanggal & jam yang sama -> pertemuan tetap jalan, di ruang lain.
                $move = $bookings->first(fn ($b) => $b->type === 'temporary'
                    && (int) $b->class_session_id === (int) $enrollment->class_session_id
                    && $b->time_block === $slot->time_block
                    && $onDate($b, $date));
                if ($move) {
                    $entry['moved_to'] = $move->classroom?->name ?? '—';
                    $entry['skipped_before'] = $skippedBefore;
                    $nextSessions[$enrollment->id] = $entry;
                    break;
                }

                // Skip: pertemuan di ruang aslinya ditiadakan (tanpa pengganti).
                $skipped = $bookings->contains(fn ($b) => $b->type === 'regular_skip'
                    && (int) $b->classroom_id === (int) $slot->classroom_id
                    && $b->time_block === $slot->time_block
                    && $onDate($b, $date));
                if ($skipped) {
                    $skippedBefore ??= $entry;

                    continue;
                }

                $entry['skipped_before'] = $skippedBefore;
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
