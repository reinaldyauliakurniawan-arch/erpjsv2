<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TrackerColumn;
use App\Models\TrackerEntry;
use Illuminate\Http\Request;

class TrackerController extends Controller
{
    public function index()
    {
        $columns = TrackerColumn::orderBy('order')->get();
        $students = Student::with(['user', 'trackerEntries'])->get();

        // Pastikan setiap student punya entry untuk setiap kolom
        foreach ($students as $student) {
            foreach ($columns as $column) {
                TrackerEntry::firstOrCreate([
                    'student_id' => $student->id,
                    'tracker_column_id' => $column->id,
                ], ['is_done' => false]);
            }
        }

        // Reload dengan entries terbaru
        $students = Student::with(['user', 'trackerEntries.column'])->get();

        return view('admin.students.tracker', compact('columns', 'students'));
    }

    public function storeColumn(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);

        $order = TrackerColumn::max('order') + 1;
        TrackerColumn::create(['name' => $request->name, 'order' => $order]);

        return back()->with('success', 'Kolom ditambahkan.');
    }

    public function destroyColumn(TrackerColumn $column)
    {
        $column->delete();
        return back()->with('success', 'Kolom dihapus.');
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'tracker_column_id' => 'required|exists:tracker_columns,id',
        ]);

        $entry = TrackerEntry::where('student_id', $request->student_id)
            ->where('tracker_column_id', $request->tracker_column_id)
            ->first();

        if ($entry) {
            $entry->update(['is_done' => !$entry->is_done]);
        }

        return response()->json(['is_done' => $entry->fresh()->is_done]);
    }
}
