<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\RoomBooking;
use App\Models\Schedule;
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

        // Cek slot tidak conflict
        $conflict = RoomBooking::where('classroom_id', $request->classroom_id)
            ->where('date', $request->date)
            ->where('time_block', $request->time_block)
            ->whereIn('type', ['temporary'])
            ->exists();

        if ($conflict) {
            return back()->with('error', 'Slot ini sudah dibooking.');
        }

        // Kalau ada regular_skip, slot ini memang available — boleh book
        RoomBooking::create([
            'classroom_id' => $request->classroom_id,
            'date'         => $request->date,
            'time_block'   => $request->time_block,
            'type'         => 'temporary',
            'tutor_id'     => $tutor->id,
            'notes'        => 'Booked by tutor: ' . Auth::user()->name . ($request->notes ? ' — ' . $request->notes : ''),
        ]);

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
