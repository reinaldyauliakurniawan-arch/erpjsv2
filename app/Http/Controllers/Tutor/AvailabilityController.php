<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AvailabilityController extends Controller
{
    public function index()
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();
        $availability = TutorAvailability::where('tutor_id', $tutor->id)
            ->orderBy('day')->orderBy('time_block')->get();

        return view('tutor.availability.index', compact('availability'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'day'        => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time_block' => 'required|string|max:20',
        ]);

        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        $exists = TutorAvailability::where('tutor_id', $tutor->id)
            ->where('day', $request->day)
            ->where('time_block', $request->time_block)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Slot ini sudah ada.');
        }

        TutorAvailability::create([
            'tutor_id'   => $tutor->id,
            'day'        => $request->day,
            'time_block' => $request->time_block,
            'status'     => 'available',
        ]);

        return back()->with('success', 'Slot berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,not_available',
        ]);

        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        $slot = TutorAvailability::where('id', $id)
            ->where('tutor_id', $tutor->id)
            ->firstOrFail();

        if ($slot->status === 'occupied') {
            return back()->with('error', 'Slot ini sedang dipakai dan tidak bisa diubah.');
        }

        $slot->update(['status' => $request->status]);

        return back()->with('success', 'Availabilitas diperbarui.');
    }

    public function destroy($id)
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        $slot = TutorAvailability::where('id', $id)
            ->where('tutor_id', $tutor->id)
            ->firstOrFail();

        if ($slot->status === 'occupied') {
            return back()->with('error', 'Slot sedang dipakai, tidak bisa dihapus.');
        }

        $slot->delete();

        return back()->with('success', 'Slot dihapus.');
    }
}
