<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::all();
        return view('admin.programs.index', compact('programs'));
    }
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|string',
            'price'          => 'required|numeric',
            'total_meetings' => 'required|integer',
            'min_quota'      => 'nullable|integer',
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
