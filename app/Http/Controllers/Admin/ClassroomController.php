<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index()
    {
        $classrooms = Classroom::all();
        return view('admin.classrooms.index', compact('classrooms'));
    }

    public function create()
    {
        return view('admin.classrooms.create');
    }

    public function store(Request $request)
    {
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
