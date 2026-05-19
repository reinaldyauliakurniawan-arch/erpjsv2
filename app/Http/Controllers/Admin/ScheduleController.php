<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Classroom;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::with([
            'classroom',
            'classSession.enrollments.student.user',
            'classSession.tutors.user',
        ])
        ->whereNotNull('class_session_id')
        ->orderBy('day')
        ->orderBy('time_block')
        ->get();

        $classrooms   = Classroom::all();
        $classSessions = ClassSession::with(['program', 'enrollments.student.user', 'tutors.user'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekDates = collect($days)->mapWithKeys(function ($day, $i) use ($weekStart) {
            return [$day => $weekStart->copy()->addDays($i)->toDateString()];
        });

        $bookings = RoomBooking::with(['tutor.user'])
            ->whereBetween('date', [
                $weekStart->toDateString(),
                $weekStart->copy()->endOfWeek()->toDateString()
            ])
            ->get()
            ->groupBy(fn($b) => Carbon::parse($b->date)->format('Y-m-d'));

        $byRoom = $schedules->groupBy('classroom.name')->map(
            fn($s) => $s->groupBy('day')
        );

        $byTutor = collect();
        foreach ($schedules as $schedule) {
            $tutors = $schedule->classSession?->tutors ?? collect();
            if ($tutors->isEmpty()) {
                $byTutor->put('—', $byTutor->get('—', collect())->push($schedule));
            } else {
                foreach ($tutors as $tutor) {
                    $name = $tutor->user->name;
                    $byTutor->put($name, $byTutor->get($name, collect())->push($schedule));
                }
            }
        }
        $byTutor = $byTutor->map(fn($s) => $s->groupBy('day'));

        $classSessionsJson = $classSessions->map(fn($cs) => [
            'id'      => $cs->id,
            'name'    => $cs->name,
            'program' => $cs->program->name ?? '',
            'tutors'  => $cs->tutors->map(fn($t) => $t->user->name)->join(', '),
            'students'=> $cs->enrollments->map(fn($e) => $e->student->user->name)->join(', '),
        ]);

        return view('admin.schedule.index', compact(
            'byRoom', 'byTutor', 'classrooms', 'classSessions', 'classSessionsJson',
            'days', 'weekDates', 'bookings'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_session_id' => 'required|exists:class_sessions,id',
            'classroom_id'     => 'required|exists:classrooms,id',
            'day'              => 'required|string',
            'time_block'       => 'required|string',
        ]);

        $exists = Schedule::where('classroom_id', $request->classroom_id)
            ->where('day', $request->day)
            ->where('time_block', $request->time_block)
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Slot ini sudah terisi.']);
        }

        $csExists = Schedule::where('class_session_id', $request->class_session_id)
            ->where('day', $request->day)
            ->where('time_block', $request->time_block)
            ->exists();

        if ($csExists) {
            return back()->withErrors(['error' => 'Kelas ini sudah punya jadwal di slot yang sama.']);
        }

        Schedule::create([
            'class_session_id' => $request->class_session_id,
            'enrollment_id'    => null,
            'classroom_id'     => $request->classroom_id,
            'day'              => $request->day,
            'time_block'       => $request->time_block,
        ]);

        return back()->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
{
    $request->validate([
        'classroom_id' => 'required|exists:classrooms,id',
        'day'          => 'required|string',
        'time_block'   => 'required|string',
    ]);

    $schedule = Schedule::findOrFail($id);
    $schedule->roomBookings()->delete();
    $schedule->update($request->only('classroom_id', 'day', 'time_block'));

    return back()->with('success', 'Jadwal updated.');
}

public function destroy($id)
{
    $schedule = Schedule::findOrFail($id);
    $schedule->roomBookings()->delete();
    $schedule->delete();

    return back()->with('success', 'Jadwal deleted.');
}
}
