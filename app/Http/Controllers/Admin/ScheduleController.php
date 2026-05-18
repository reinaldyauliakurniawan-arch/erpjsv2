<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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
            'enrollment.student.user',
            'enrollment.tutors.user',
            'enrollment.classSession',
        ])
        ->orderBy('day')
        ->orderBy('time_block')
        ->get();

        $classrooms  = Classroom::all();
        $enrollments = Enrollment::with(['student.user', 'program', 'classSession'])->get();
        $days        = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekDates = collect($days)->mapWithKeys(function ($day, $i) use ($weekStart) {
            return [$day => $weekStart->copy()->addDays($i)->toDateString()];
        });

        $bookings = RoomBooking::with(['enrollment.student.user', 'tutor.user'])
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
            $tutors = $schedule->enrollment->tutors;
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

        $enrollmentsJson = $enrollments->map(fn($e) => [
            'id'               => $e->id,
            'name'             => $e->student->user->name . ' — ' . ($e->program->name ?? ''),
            'class_session_id' => $e->class_session_id,
        ]);

        return view('admin.schedule.index', compact(
            'byRoom', 'byTutor', 'classrooms', 'enrollments', 'enrollmentsJson', 'days', 'weekDates', 'bookings'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'classroom_id'  => 'required|exists:classrooms,id',
            'day'           => 'required|string',
            'time_block'    => 'required|string',
        ]);

        $exists = Schedule::where('classroom_id', $request->classroom_id)
            ->where('day', $request->day)
            ->where('time_block', $request->time_block)
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Slot ini sudah terisi.']);
        }

        Schedule::create($request->only('enrollment_id', 'classroom_id', 'day', 'time_block'));

        return back()->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'day'          => 'required|string',
            'time_block'   => 'required|string',
        ]);

        Schedule::findOrFail($id)->update($request->only('classroom_id', 'day', 'time_block'));

        return back()->with('success', 'Jadwal updated.');
    }

    public function destroy($id)
    {
        Schedule::findOrFail($id)->delete();

        return back()->with('success', 'Jadwal deleted.');
    }
}
