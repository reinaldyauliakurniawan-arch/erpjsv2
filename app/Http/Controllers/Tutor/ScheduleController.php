<?php
namespace App\Http\Controllers\Tutor;
use App\Http\Controllers\Controller;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Classroom;
use App\Models\Tutor;
use App\Support\ScheduleFormat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();
        // Sama persis dengan grid admin (App\Support\ScheduleFormat).
        $days = ScheduleFormat::DAYS;
        $timeBlocks = ScheduleFormat::TIME_BLOCKS;

        $weekOffset = (int) $request->get('week', 0);
        $weekOffset = max(-1, min(2, $weekOffset));
        $weekStart  = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset);
        $weekEnd    = $weekStart->copy()->endOfWeek();

        $weekDates = collect($days)->mapWithKeys(function ($day, $i) use ($weekStart) {
            return [$day => $weekStart->copy()->addDays($i)->toDateString()];
        });

        // Jadwal milik tutor ini
        $mySchedules = Schedule::with(['classroom', 'classSession.enrollments.student.user', 'classSession.program', 'roomBookings'])
            ->whereHas('classSession.tutors', fn($q) => $q->where('tutor_id', $tutor->id))
            ->orderBy('day')
            ->orderBy('time_block')
            ->get();
        $myByDay = $mySchedules->groupBy('day');

        // Semua jadwal untuk matrix
        $allSchedules = Schedule::with([
            'classroom',
            'classSession.enrollments.student.user',
            'classSession.tutors.user',
        ])
        ->whereNotNull('class_session_id')
        ->orderBy('day')
        ->orderBy('time_block')
        ->get();

        $byRoom = $allSchedules->groupBy('classroom.name')->map(
            fn($s) => $s->groupBy('day')
        );

        $bookings = RoomBooking::with(['tutor.user', 'classSession'])
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->groupBy(fn($b) => Carbon::parse($b->date)->format('Y-m-d'));

        $classrooms = Classroom::orderBy('name')->get();

        return view('tutor.schedule.index', compact(
            'myByDay', 'byRoom', 'bookings',
            'classrooms', 'days', 'timeBlocks', 'weekDates',
            'weekOffset', 'weekStart', 'tutor'
        ));
    }

    // Catatan: pembuatan/pembatalan booking & skip untuk tutor ditangani
    // sepenuhnya oleh App\Http\Controllers\Tutor\RoomBookingController
    // (route tutor.room-bookings.*). Controller ini hanya menampilkan jadwal.
}
