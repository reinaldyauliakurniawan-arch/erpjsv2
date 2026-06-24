<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomBooking;
use Illuminate\Http\Request;

class RoomBookingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'date'         => 'required|date',
            'time_block'   => 'required|string',
            'type'         => 'required|in:regular_skip,temporary',
            'enrollment_id'=> 'nullable|exists:enrollments,id',
            'tutor_id'     => 'nullable|exists:tutors,id',
            'notes'        => 'nullable|string|max:255',
            'schedule_id'   => 'nullable|exists:schedules,id',
        ]);
        // Cegah booking masa lampau (per time block)
        $endTime = explode('-', $request->time_block)[1] ?? '23:59';
        $slotEnd = \Carbon\Carbon::parse($request->date . ' ' . trim($endTime));
        if ($slotEnd->isPast()) {
            return back()->withErrors(['error' => 'Slot ini sudah lewat dan tidak bisa diubah.']);
        }
        // Cegah duplikat
        $exists = RoomBooking::where('classroom_id', $request->classroom_id)
            ->where('date', $request->date)
            ->where('time_block', $request->time_block)
            ->where('type', $request->type)
            ->exists();

        // Boleh temporary booking di slot yang sudah di-skip
        if ($request->type === 'temporary') {
            $exists = RoomBooking::where('classroom_id', $request->classroom_id)
                ->where('date', $request->date)
                ->where('time_block', $request->time_block)
                ->where('type', 'temporary')
                ->exists();
        }

        if ($exists) {
            return back()->withErrors(['error' => 'Booking ini sudah ada.']);
        }

        try {
            RoomBooking::create($request->only(
                'classroom_id', 'schedule_id', 'date', 'time_block', 'type', 'enrollment_id', 'tutor_id', 'notes', 'class_session_id'
            ));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return back()->withErrors(['error' => 'Slot ini baru saja dibooking oleh orang lain.']);
        }
        return back()->with('success', 'Booking berhasil disimpan.');
    }

    public function destroy($id)
    {
        RoomBooking::findOrFail($id)->delete();

        return back()->with('success', 'Booking dihapus.');
    }
}
