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
        $days  = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

        $weekOffset = (int) $request->get('week', 0);
        $weekOffset = max(-1, min(2, $weekOffset));
        $weekStart  = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset);
        $weekEnd    = $weekStart->copy()->endOfWeek();

        $weekDates = collect($days)->mapWithKeys(function ($day, $i) use ($weekStart) {
            return [$day => $weekStart->copy()->addDays($i)->toDateString()];
        });

        // Jadwal milik tutor ini
        $mySchedules = Schedule::with(['classroom', 'classSession.enrollments.student.user', 'classSession.program'])
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

        $bookings = RoomBooking::with(['tutor.user'])
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

    public function store(Request $request)
{
    $request->validate([
        'classroom_id' => 'required|exists:classrooms,id',
        'date'         => 'required|date',
        'time_block'   => 'required|string',
        'type'         => 'nullable|in:regular_skip,temporary',
        'schedule_id'  => 'nullable|exists:schedules,id',
        'notes'        => 'nullable|string|max:255',
    ]);

    $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();
    $type  = $request->type ?? 'temporary';

    // Cegah booking masa lampau (per time block)
    $endTime = explode('-', $request->time_block)[1] ?? '23:59';
    $slotEnd = \Carbon\Carbon::parse($request->date . ' ' . trim($endTime));
    if ($slotEnd->isPast()) {
        return back()->withErrors(['error' => 'Slot ini sudah lewat dan tidak bisa diubah.']);
    }

    $conflict = RoomBooking::where('classroom_id', $request->classroom_id)
        ->where('date', $request->date)
        ->where('time_block', $request->time_block)
        ->where('type', $type)
        ->exists();

    if ($conflict) {
        return back()->with('error', 'Slot ini sudah ada booking dengan tipe yang sama.');
    }

    try {
        RoomBooking::create([
            'classroom_id' => $request->classroom_id,
            'schedule_id'  => $request->schedule_id,
            'date'         => $request->date,
            'time_block'   => $request->time_block,
            'type'         => $type,
            'tutor_id'     => $tutor->id,
            'notes'        => $request->notes,
        ]);
    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
        return back()->with('error', 'Slot ini baru saja dibooking oleh orang lain.');
    }

    $msg = $type === 'regular_skip' ? 'Sesi berhasil di-skip.' : 'Slot berhasil dibooking.';
    return back()->with('success', $msg);
}
}
