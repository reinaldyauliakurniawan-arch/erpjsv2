<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\RoomBooking;
use App\Models\Schedule;
use App\Models\Tutor;
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

        // Cegah duplikat
        $exists = RoomBooking::where('classroom_id', $request->classroom_id)
            ->where('date', $request->date)
            ->where('time_block', $request->time_block)
            ->where('type', $request->type)
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Booking ini sudah ada.']);
        }

        RoomBooking::create($request->only(
            'classroom_id', 'schedule_id', 'date', 'time_block', 'type', 'enrollment_id', 'tutor_id', 'notes'
        ));

        return back()->with('success', 'Booking berhasil disimpan.');
    }

    public function destroy($id)
    {
        RoomBooking::findOrFail($id)->delete();

        return back()->with('success', 'Booking dihapus.');
    }
}
