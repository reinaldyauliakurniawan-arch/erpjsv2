<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Program;
use App\Models\Enrollment;
use App\Models\Tutor;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    public function index()
    {
        $classSessions = ClassSession::with('program')
            ->withCount('enrollments')
            ->orderBy('name')
            ->get();
        return view('admin.class_sessions.index', compact('classSessions'));
    }

    public function create()
    {
        $programs = Program::orderBy('name')->get();
        return view('admin.class_sessions.create', compact('programs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255|unique:class_sessions,name',
            'program_id' => 'required|exists:programs,id',
            'status'     => 'required|in:active,inactive',
        ]);

        ClassSession::create($request->only('name', 'program_id', 'status'));

        return redirect()->route('admin.class-sessions.index')->with('success', 'Class session created.');
    }

    public function show($id)
    {
        $classSession = ClassSession::with([
            'program',
            'enrollments.student.user',
            'tutors.user',
        ])->findOrFail($id);

        $availableEnrollments = Enrollment::with('student.user')
            ->where('program_id', $classSession->program_id)
            ->where('status', 'active')
            ->whereNull('class_session_id')
            ->get();

        $availableTutors = Tutor::with('user')
            ->whereDoesntHave('classSessions', function ($q) use ($id) {
                $q->where('class_session_id', $id);
            })
            ->get();

        return view('admin.class_sessions.show', compact('classSession', 'availableEnrollments', 'availableTutors'));
    }

    public function edit($id)
    {
        $classSession = ClassSession::findOrFail($id);
        $programs     = Program::orderBy('name')->get();
        return view('admin.class_sessions.edit', compact('classSession', 'programs'));
    }

    public function update(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $request->validate([
            'name'       => 'required|string|max:255|unique:class_sessions,name,' . $id,
            'program_id' => 'required|exists:programs,id',
            'status'     => 'required|in:active,inactive',
        ]);

        $classSession->update($request->only('name', 'program_id', 'status'));

        return redirect()->route('admin.class-sessions.index')->with('success', 'Class session updated.');
    }

    public function destroy($id)
    {
        $classSession = ClassSession::findOrFail($id);

        $hasActiveEnrollments = Enrollment::where('class_session_id', $id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveEnrollments) {
            return redirect()->route('admin.class-sessions.index')
                ->with('error', 'Class session tidak bisa dihapus karena masih ada siswa aktif di dalamnya.');
        }

        $classSession->delete();
        return redirect()->route('admin.class-sessions.index')->with('success', 'Class session deleted.');
    }

    public function assignEnrollment(Request $request, $id)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
        ]);

        Enrollment::where('id', $request->enrollment_id)
            ->update(['class_session_id' => $id]);

        return back()->with('success', 'Student assigned to class session.');
    }

    public function removeEnrollment(Request $request, $id)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
        ]);

        Enrollment::where('id', $request->enrollment_id)
            ->where('class_session_id', $id)
            ->update(['class_session_id' => null]);

        return back()->with('success', 'Student removed from class session.');
    }

    public function assignTutor(Request $request, $id)
    {
        $request->validate([
            'tutor_id' => 'required|exists:tutors,id',
        ]);

        $classSession = ClassSession::findOrFail($id);
        $classSession->tutors()->attach($request->tutor_id, ['status' => 'pending']);

        return back()->with('success', 'Tutor assigned to class session.');
    }

    public function removeTutor(Request $request, $id)
    {
        $request->validate([
            'tutor_id' => 'required|exists:tutors,id',
        ]);

        $classSession = ClassSession::findOrFail($id);
        $classSession->tutors()->detach($request->tutor_id);

        return back()->with('success', 'Tutor removed from class session.');
    }

    public function updateTutorStatus(Request $request, $id, $tutorId)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed',
        ]);

        $classSession = ClassSession::findOrFail($id);
        $classSession->tutors()->updateExistingPivot($tutorId, [
            'status' => $request->status,
        ]);

        return back()->with('success', 'Tutor status updated.');
    }
}
