<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassroomKind;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;

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
        // Hanya ruang fisik yang dihitung untuk okupansi. Kelas online (bisa
        // dari mana saja) dan kelas di luar Just Speak / B2B tidak menempati
        // ruang kita, jadi tidak masuk hitungan — tapi tetap tampil di jadwal.
        $physicalClassrooms = Classroom::physical()->get();
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

        // Hitung per hari kalender. `diffInDays` bisa mengembalikan pecahan
        // (mis. Senin 00:00 -> Minggu 23:59 = 6.99) yang kalau tidak dibulatkan
        // ke bawah membuat loop menambah satu hari ekstra dan menghitung ganda
        // slot hari pertama.
        $totalDays = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
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

        $data = $this->validatedData($request);
        Classroom::create($data);

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

        $classroom->update($this->validatedData($request));

        return redirect()->route('admin.classrooms.index')->with('success', 'Classroom updated.');
    }

    /**
     * Validasi + siapkan payload ruangan. `kind` menentukan apakah ruang
     * dihitung untuk okupansi; `is_at_just_speak` diturunkan otomatis oleh
     * Classroom::saving(). Form lama yang hanya mengirim checkbox
     * `is_at_just_speak` tetap didukung.
     */
    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            // Kolom `capacity` NOT NULL di database — wajib diisi supaya tidak
            // gagal insert di MySQL dan supaya cek kapasitas kelas selalu benar.
            'capacity' => 'required|integer|min:1',
            'kind'     => ['nullable', Rule::in(ClassroomKind::values())],
        ]);

        $validated['kind'] = $validated['kind']
            ?? ($request->boolean('is_at_just_speak') ? ClassroomKind::PHYSICAL->value : ClassroomKind::OFFSITE->value);

        return $validated;
    }
}
