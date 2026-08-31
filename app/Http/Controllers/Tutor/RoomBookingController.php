<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\RoomBooking;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomBookingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'date'         => 'required|date',
            'time_block'   => 'required|string',
        ]);

        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        // Cegah booking masa lampau
        $endTime = explode('-', $request->time_block)[1] ?? '23:59';
        $slotEnd = \Carbon\Carbon::parse($request->date . ' ' . trim($endTime));
        if ($slotEnd->isPast()) {
            return back()->with('error', 'Slot ini sudah lewat dan tidak bisa dibooking.');
        }

        // Cek slot tidak conflict
        $conflict = RoomBooking::where('classroom_id', $request->classroom_id)
            ->whereDate('date', $request->date)
            ->where('time_block', $request->time_block)
            ->whereIn('type', ['temporary'])
            ->exists();

        if ($conflict) {
            return back()->with('error', 'Slot ini sudah dibooking.');
        }

        // Cek apakah ada jadwal kelas reguler aktif di slot ini tanpa regular_skip
        $dayName = \Carbon\Carbon::parse($request->date)->format('l'); // 'Monday', 'Tuesday', dst — sesuai App\Enums\DayOfWeek
        $hasActiveRegularSchedule = \App\Models\Schedule::where('classroom_id', $request->classroom_id)
            ->where('day', $dayName)
            ->where('time_block', $request->time_block)
            ->whereHas('classSession', fn($q) => $q->where('status', 'active'))
            ->exists();

        if ($hasActiveRegularSchedule) {
            $isSkipped = RoomBooking::where('classroom_id', $request->classroom_id)
                ->whereDate('date', $request->date)
                ->where('time_block', $request->time_block)
                ->where('type', 'regular_skip')
                ->exists();

            if (!$isSkipped) {
                return back()->with('error', 'Slot ini sedang dipakai kelas reguler. Skip jadwal reguler terlebih dahulu sebelum booking.');
            }
        }

        // Kalau ada regular_skip, slot ini memang available — boleh book
        try {
            RoomBooking::create([
                'classroom_id' => $request->classroom_id,
                'date'         => $request->date,
                'time_block'   => $request->time_block,
                'type'         => 'temporary',
                'tutor_id'     => $tutor->id,
                'notes'        => 'Booked by tutor: ' . Auth::user()->name . ($request->notes ? ' — ' . $request->notes : ''),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return back()->with('error', 'Slot ini baru saja dibooking oleh orang lain.');
        }
        return back()->with('success', 'Slot berhasil dibooking.');
    }

    public function destroy($id)
    {
        $tutor   = Tutor::where('user_id', Auth::id())->firstOrFail();
        $booking = RoomBooking::where('id', $id)
            ->where('tutor_id', $tutor->id)
            ->where('type', 'temporary')
            ->firstOrFail();

        $booking->delete();

        return back()->with('success', 'Booking dibatalkan.');
    }
}
