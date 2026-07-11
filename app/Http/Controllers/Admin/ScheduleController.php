<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Classroom;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ->get()
        ->unique(fn($s) => $s->class_session_id . '|' . $s->classroom_id . '|' . $s->day . '|' . $s->time_block)
        ->values();

        $classrooms   = Classroom::all();
        $classSessions = ClassSession::with(['program', 'enrollments.student.user', 'tutors.user'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

        $weekOffset = (int) request('week', 0);
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset);
        $weekEnd   = $weekStart->copy()->endOfWeek();

        $physicalClassrooms = Classroom::where('is_at_just_speak', true)->get();

        // N+1 fix: previously ran 2 COUNT queries PER tutor in a loop
        // (2 × 30 tutors = 60 queries). Now a single GROUP BY query fetches
        // all tutor availability stats in one shot.
        $tutorAvailStats = DB::table('tutor_availability')
            ->select('tutor_id')
            ->selectRaw("SUM(CASE WHEN status IN ('available','occupied') THEN 1 ELSE 0 END) as total")
            ->selectRaw("SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied")
            ->groupBy('tutor_id')
            ->get()
            ->keyBy('tutor_id');

        $tutorAvailTotal    = $tutorAvailStats->sum(fn($s) => (int) $s->total);
        $tutorAvailOccupied = $tutorAvailStats->sum(fn($s) => (int) $s->occupied);
        $tutorOccupancyRate = $tutorAvailTotal > 0 ? round($tutorAvailOccupied / $tutorAvailTotal * 100) : 0;

        $tutorStats = Tutor::with('user')
            ->where('status', 'active')
            ->get()
            ->map(function ($tutor) use ($tutorAvailStats) {
                $stat     = $tutorAvailStats->get($tutor->id);
                $avail    = $stat ? (int) $stat->total : 0;
                $occupied = $stat ? (int) $stat->occupied : 0;
                $ratio    = $avail > 0 ? round($occupied / $avail * 100) : 0;
                return [
                    'name'     => $tutor->user->name,
                    'avail'    => $avail,
                    'occupied' => $occupied,
                    'free'     => $avail - $occupied,
                    'ratio'    => $ratio,
                ];
            })
            ->sortBy('ratio')
            ->values();
        $physicalIds = $physicalClassrooms->pluck('id');

        // Reuses the exact same slot logic as ClassroomController@buildOccupancyStats
        // so this number always matches /admin/classrooms.
        $classroomController = app(\App\Http\Controllers\Admin\ClassroomController::class);
        $roomOccupancyStats = $classroomController->buildOccupancyStats($weekStart, $weekEnd);

        $occupiedCount = array_sum(array_column($roomOccupancyStats, 'occupied'));
        $totalSlots    = array_sum(array_column($roomOccupancyStats, 'total'));
        $occupancyRate = $totalSlots > 0 ? round($occupiedCount / $totalSlots * 100) : 0;
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
            'days', 'weekDates', 'bookings', 'weekOffset', 'occupancyRate', 'occupiedCount', 'totalSlots',
            'tutorOccupancyRate', 'tutorAvailOccupied', 'tutorAvailTotal', 'tutorStats'
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

    // Atomicity fix: previously 4+ separate writes (delete room bookings,
    // update schedule, update old-slot tutor availability, update new-slot
    // tutor availability). If any failed, the schedule was moved but tutor
    // availability was in an inconsistent state — tutors appeared
    // double-booked or free when they shouldn't be.
    return DB::transaction(function () use ($request, $id) {
        $schedule = Schedule::lockForUpdate()->findOrFail($id);
        $oldDay       = $schedule->day;
        $oldTimeBlock = $schedule->time_block;

        $tutorIds = $schedule->classSession
            ? $schedule->classSession->tutors()->pluck('tutors.id')
            : collect();

        $schedule->roomBookings()->delete();
        $schedule->update($request->only('classroom_id', 'day', 'time_block'));

        // Bebaskan slot lama
        foreach ($tutorIds as $tutorId) {
            $stillOccupied = Schedule::where('day', $oldDay)
                ->where('time_block', $oldTimeBlock)
                ->where('id', '!=', $schedule->id)
                ->whereHas('classSession.tutors', fn($q) => $q->where('tutor_id', $tutorId))
                ->exists();

            if (!$stillOccupied) {
                \App\Models\TutorAvailability::where('tutor_id', $tutorId)
                    ->where('day', $oldDay)
                    ->where('time_block', $oldTimeBlock)
                    ->update(['status' => 'available']);
            }
        }

        // Tandai slot baru sebagai occupied
        foreach ($tutorIds as $tutorId) {
            \App\Models\TutorAvailability::where('tutor_id', $tutorId)
                ->where('day', $request->day)
                ->where('time_block', $request->time_block)
                ->update(['status' => 'occupied']);
        }

        return back()->with('success', 'Jadwal updated.');
    });
}

public function destroy($id)
{
    // Atomicity fix: wrap delete + tutor-availability cleanup in transaction.
    return DB::transaction(function () use ($id) {
        $schedule = Schedule::lockForUpdate()->findOrFail($id);

        $tutorIds = $schedule->classSession
            ? $schedule->classSession->tutors()->pluck('tutors.id')
            : collect();

        $schedule->roomBookings()->delete();
        $schedule->delete();

        foreach ($tutorIds as $tutorId) {
            $stillOccupied = Schedule::where('day', $schedule->day)
                ->where('time_block', $schedule->time_block)
                ->whereHas('classSession.tutors', fn($q) => $q->where('tutor_id', $tutorId))
                ->exists();

            if (!$stillOccupied) {
                \App\Models\TutorAvailability::where('tutor_id', $tutorId)
                    ->where('day', $schedule->day)
                    ->where('time_block', $schedule->time_block)
                    ->update(['status' => 'available']);
            }
        }

        return back()->with('success', 'Jadwal deleted.');
    });
}
}
