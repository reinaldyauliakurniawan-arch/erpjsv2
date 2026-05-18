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
        Classroom::create($request->only('name', 'capacity'));
        return redirect()->route('admin.classrooms.index')->with('success', 'Classroom created.');
    }

    public function destroy(Classroom $classroom)
    {
        $classroom->delete();
        return redirect()->route('admin.classrooms.index')->with('success', 'Classroom deleted.');
    }

    public function update(Request $request, Classroom $classroom)
{
    $request->validate([
        'name'     => 'required|string|max:255',
        'capacity' => 'nullable|integer|min:1',
    ]);
    $classroom->update($request->only('name', 'capacity'));
    return redirect()->route('admin.classrooms.index')->with('success', 'Classroom updated.');
}
}
