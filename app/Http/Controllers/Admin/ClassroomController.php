<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;

class ClassroomController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorize('viewAny', Classroom::class);

        $classrooms = Classroom::all();
        return view('admin.classrooms.index', compact('classrooms'));
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
