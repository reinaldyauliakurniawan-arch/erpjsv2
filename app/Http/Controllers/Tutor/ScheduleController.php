<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Classroom;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        $days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

        // Week navigation
        $weekOffset = (int) $request->get('week', 0);
        $weekOffset = max(-1, min(2, $weekOffset)); // clamp: -1 s/d +2
        $weekStart  = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset);
        $weekEnd    = $weekStart->copy()->endOfWeek();

        $weekDates = collect($days)->mapWithKeys(function ($day, $i) use ($weekStart) {
            return [$day => $weekStart->copy()->addDays($i)->toDateString()];
        });

        // Jadwal milik tutor ini (weekly summary panel)
        $mySchedules = Schedule::with(['classroom', 'enrollment.student.user', 'enrollment.program'])
            ->whereHas('enrollment', function ($q) use ($tutor) {
                $q->whereHas('tutors', fn($q2) => $q2->where('tutor_id', $tutor->id));
            })
            ->orderBy('day')
            ->orderBy('time_block')
            ->get();

        $myByDay = $mySchedules->groupBy('day');

        // Semua jadwal untuk matrix
        $allSchedules = Schedule::with([
            'classroom',
            'enrollment.student.user',
            'enrollment.tutors.user',
            'enrollment.program',
        ])
        ->orderBy('day')
        ->orderBy('time_block')
        ->get();

        $byRoom = $allSchedules->groupBy('classroom.name')->map(
            fn($s) => $s->groupBy('day')
        );

        // Bookings minggu ini
        $bookings = RoomBooking::with(['enrollment.student.user', 'tutor.user'])
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->groupBy(fn($b) => Carbon::parse($b->date)->format('Y-m-d'));

        $classrooms = Classroom::orderBy('name')->get();

        return view('tutor.schedule.index', compact(
            'myByDay', 'byRoom', 'bookings',
            'classrooms', 'days', 'weekDates',
            'weekOffset', 'weekStart', 'tutor'
        ));
    }
}
