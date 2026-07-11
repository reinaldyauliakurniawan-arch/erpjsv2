<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;

class ClassroomController extends Controller
{
    // Note: 'auth' + role enforcement applied via the role:admin route group.

    protected $timeBlocks = ['09:00-10:30','10:30-12:00','13:00-14:30','14:30-16:00','16:00-17:30','18:30-20:00'];
    protected $dayNames   = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Classroom::class);

        $classrooms = Classroom::all();

        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : Carbon::now()->endOfWeek();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $occupancyStats = $this->buildOccupancyStats($from, $to);

        $totalOccupied = array_sum(array_column($occupancyStats, 'occupied'));
        $totalSlots    = array_sum(array_column($occupancyStats, 'total'));
        $occupancyRate = $totalSlots > 0 ? round($totalOccupied / $totalSlots * 100) : 0;

        return view('admin.classrooms.index', compact('classrooms', 'occupancyStats', 'occupancyRate', 'from', 'to'));
    }

    public function buildOccupancyStats(Carbon $from, Carbon $to): array
    {
        $physicalClassrooms = Classroom::where('is_at_just_speak', true)->get();
        $physicalIds = $physicalClassrooms->pluck('id');

        $scheduledSlots = Schedule::whereIn('classroom_id', $physicalIds)
            ->select('classroom_id', 'day', 'time_block')
            ->distinct()
            ->get();

        $skippedSlots = RoomBooking::whereIn('classroom_id', $physicalIds)
            ->where('type', 'regular_skip')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('classroom_id', 'time_block', 'date')
            ->get();

        $tempSlots = RoomBooking::whereIn('classroom_id', $physicalIds)
            ->where('type', 'temporary')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('classroom_id', 'time_block', 'date')
            ->get();

        $totalDays = $from->diffInDays($to) + 1;
        $result = [];

        foreach ($physicalClassrooms as $room) {
            $occupied = 0;
            $total = 0;

            for ($i = 0; $i < $totalDays; $i++) {
                $date = $from->copy()->addDays($i);
                $dayName = $this->dayNames[$date->dayOfWeekIso - 1];

                foreach ($this->timeBlocks as $block) {
                    $total++;
                    $dateStr = $date->toDateString();

                    $hasSchedule = $scheduledSlots->where('classroom_id', $room->id)
                        ->where('day', $dayName)
                        ->where('time_block', $block)
                        ->isNotEmpty();

                    $isSkipped = $skippedSlots->where('classroom_id', $room->id)
                        ->where('time_block', $block)
                        ->where('date', $dateStr)
                        ->isNotEmpty();

                    $isTemp = $tempSlots->where('classroom_id', $room->id)
                        ->where('time_block', $block)
                        ->where('date', $dateStr)
                        ->isNotEmpty();

                    if (($hasSchedule && !$isSkipped) || $isTemp) {
                        $occupied++;
                    }
                }
            }

            $rate = $total > 0 ? round($occupied / $total * 100) : 0;

            $result[] = [
                'id'       => $room->id,
                'name'     => $room->name,
                'occupied' => $occupied,
                'total'    => $total,
                'rate'     => $rate,
            ];
        }

        return $result;
    }

    public function create()
    {
        $this->authorize('create', Classroom::class);

        return view('admin.classrooms.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Classroom::class);

        $request->validate([
            'name'     => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1',
        ]);
        Classroom::create([
            'name'             => $request->name,
            'capacity'         => $request->capacity,
            'is_at_just_speak' => $request->boolean('is_at_just_speak'),
        ]);
        return redirect()->route('admin.classrooms.index')->with('success', 'Classroom created.');
    }

    public function destroy(Classroom $classroom)
    {
        $this->authorize('delete', $classroom);

        $hasActiveSchedule = \App\Models\Schedule::where('classroom_id', $classroom->id)
            ->whereHas('enrollment', fn($q) => $q->whereIn('status', ['active', 'waitlist']))
            ->exists();

        if ($hasActiveSchedule) {
            return redirect()->route('admin.classrooms.index')
                ->with('error', 'Classroom tidak bisa dihapus karena masih dipakai jadwal aktif.');
        }

        $classroom->delete();
        return redirect()->route('admin.classrooms.index')->with('success', 'Classroom deleted.');
    }

    public function update(Request $request, Classroom $classroom)
    {
        $this->authorize('update', $classroom);

        $request->validate([
            'name'     => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1',
        ]);
        $classroom->update([
            'name'             => $request->name,
            'capacity'         => $request->capacity,
            'is_at_just_speak' => $request->boolean('is_at_just_speak'),
        ]);
        return redirect()->route('admin.classrooms.index')->with('success', 'Classroom updated.');
    }
}
