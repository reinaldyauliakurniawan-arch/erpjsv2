<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Program;
use App\Services\TutorAssignmentService;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function __construct(protected TutorAssignmentService $tutorAssignment) {}

    public function index()
    {
        $programs = Program::all();

        return view('admin.programs.index', compact('programs'));
    }

    public function update(Request $request, Program $program)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'price' => 'required|numeric',
            'total_meetings' => 'required|integer',
            'min_quota' => 'nullable|integer',
        ]);

        $hasActiveEnrollments = $program->enrollments()
            ->whereIn('status', ['active', 'waitlist'])
            ->exists();

        if ($hasActiveEnrollments && $request->total_meetings != $program->total_meetings) {
            return redirect()->route('admin.programs.index')
                ->withErrors(['error' => 'total_meetings tidak bisa diubah karena program masih memiliki enrollment aktif.']);
        }

        $quotaChanged = $request->filled('min_quota') && (int) $request->min_quota !== (int) $program->min_quota;

        $program->update($request->only(['name', 'type', 'price', 'total_meetings', 'min_quota']));

        // Kuota minimum berubah → kelas yang tadinya nunggu kuota bisa langsung
        // aktif (kalau sudah ada tutor confirmed & jumlah siswa cukup).
        if ($quotaChanged) {
            ClassSession::where('program_id', $program->id)
                ->whereHas('enrollments', fn ($q) => $q->where('status', 'waitlist'))
                ->get()
                ->each(fn ($cs) => $this->tutorAssignment->reevaluateWaitlist($cs));
        }

        return redirect()->route('admin.programs.index')->with('success', 'Program updated.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'price' => 'required|numeric',
            'total_meetings' => 'required|integer',
            'min_quota' => 'nullable|integer',
        ]);
        Program::create($request->only(['name', 'type', 'price', 'total_meetings', 'min_quota']));

        return redirect()->route('admin.programs.index')->with('success', 'Program created successfully.');
    }

    public function destroy($id)
    {
        $program = Program::findOrFail($id);

        $hasActiveEnrollments = $program->enrollments()
            ->whereIn('status', ['active', 'waitlist'])
            ->exists();

        if ($hasActiveEnrollments) {
            return redirect()->route('admin.programs.index')
                ->withErrors(['error' => 'Program tidak bisa dihapus karena masih ada siswa aktif atau waitlist.']);
        }

        $program->delete();

        return redirect()->route('admin.programs.index')->with('success', 'Program deleted.');
    }
}
