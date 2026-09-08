<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\DayOfWeek;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        // ── Kelas yang diajar sekarang (sumber sama dgn halaman jadwal:
        //    class_session_tutor), hanya yang masih punya siswa aktif. ──────
        $myClasses = $tutor->activeClassSessions()
            ->with(['program', 'schedules.classroom'])
            ->orderBy('name')
            ->get();

        // Penugasan lewat enrollment yang belum punya class session (mis.
        // kelas privat baru, belum dijadwalkan) — supaya tetap kelihatan.
        $unscheduledAssignments = Enrollment::with(['program', 'student.user'])
            ->whereNull('class_session_id')
            ->whereIn('status', ['active', 'waitlist'])
            ->whereHas('tutors', fn ($q) => $q->where('tutor_id', $tutor->id))
            ->get();

        // ── Sesi hari ini (jadwal reguler + status skip), konsisten dgn
        //    halaman jadwal tutor. ─────────────────────────────────────────
        $today = now()->toDateString();
        $todayName = DayOfWeek::fromDate($today)->value;
        $todaySessions = Schedule::with(['classroom', 'classSession.program', 'roomBookings'])
            ->where('day', $todayName)
            ->whereNotNull('class_session_id')
            ->whereHas('classSession.tutors', fn ($q) => $q->where('tutor_id', $tutor->id))
            ->whereHas('classSession.enrollments', fn ($q) => $q->whereIn('status', ['active', 'waitlist']))
            ->orderBy('time_block')
            ->get()
            ->map(function ($s) use ($today) {
                $s->is_skipped_today = $s->roomBookings
                    ->where('type', 'regular_skip')
                    ->contains(fn ($b) => Carbon::parse($b->date)->toDateString() === $today);

                return $s;
            });

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
            ->whereHas('tutors', fn ($q) => $q->where('tutor_id', $tutor->id))
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        $replacedHistory = DB::table('attendance_tutor')
            ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
            ->join('class_sessions', 'attendance.class_session_id', '=', 'class_sessions.id')
            ->join('tutors as replacing_tutor', 'attendance_tutor.tutor_id', '=', 'replacing_tutor.id')
            ->join('users as replacing_user', 'replacing_tutor.user_id', '=', 'replacing_user.id')
            ->where('attendance_tutor.replaced_tutor_id', $tutor->id)
            ->select(
                'attendance.date',
                'attendance.time_block',
                'class_sessions.name as class_name',
                'replacing_user.name as replaced_by'
            )
            ->orderByDesc('attendance.date')
            ->limit(10)
            ->get();

        // Tutor tetap: penghasilan bukan akumulasi per meeting tapi gaji
        // bulanan (pro-rata utk bulan parsial). Berbasis periode tetap, bukan
        // employment_type — jadi tutor yang sudah balik freelance otomatis
        // lihat tampilan freelance lagi.
        $isSalaried = $tutor->isCurrentlySalaried();
        $monthlySalary = $isSalaried ? (float) $tutor->proratedSalaryForMonth(now()) : 0;
        if ($isSalaried) {
            $unpaidTotal = 0;
            $pendingRateCount = 0;

            // "Dibayar bulan ini" untuk tutor tetap = jurnal gaji yang sudah
            // diposting untuk payroll run bulan ini.
            $runIds = DB::table('payroll_runs')
                ->whereYear('month', now()->year)
                ->whereMonth('month', now()->month)
                ->pluck('id');
            $salaryRefs = $runIds->map(fn ($rid) => "PAYROLL-{$rid}-TUTOR-{$tutor->id}-SALARY");
            $paidThisMonth = (float) DB::table('journals')
                ->whereIn('reference', $salaryRefs)
                ->sum('total_amount');
        }

        return view('tutor.dashboard', compact(
            'myClasses', 'unscheduledAssignments', 'todaySessions',
            'unpaidTotal', 'paidThisMonth', 'pendingRateCount', 'recentAttendances', 'replacedHistory',
            'isSalaried', 'monthlySalary'
        ));
    }
}
