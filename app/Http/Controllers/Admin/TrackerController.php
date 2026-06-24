<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TrackerColumn;
use App\Models\TrackerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackerController extends Controller
{
    public function index()
    {
        $columns = TrackerColumn::orderBy('order')->get();
        $students = Student::with(['user', 'trackerEntries'])->get();

        // Buat entries yang belum ada secara bulk
        $existingPairs = TrackerEntry::whereIn('student_id', $students->pluck('id'))
            ->whereIn('tracker_column_id', $columns->pluck('id'))
            ->get(['student_id', 'tracker_column_id'])
            ->map(fn($e) => $e->student_id . '-' . $e->tracker_column_id)
            ->flip();

        $toInsert = [];
        $now = now();
        foreach ($students as $student) {
            foreach ($columns as $column) {
                $key = $student->id . '-' . $column->id;
                if (!isset($existingPairs[$key])) {
                    $toInsert[] = [
                        'student_id'        => $student->id,
                        'tracker_column_id' => $column->id,
                        'is_done'           => false,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }
        }

        if (!empty($toInsert)) {
            TrackerEntry::insert($toInsert);
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

        // Race-condition fix: previously a read-modify-write (`$entry->is_done`
        // → `update(['is_done' => !$entry->is_done])`). Two concurrent toggles
        // could both read `is_done=false` and both write `true`, resulting in
        // a net single toggle instead of two (state ends up `true` instead of
        // back to `false`).
        //
        // Fix: use a single atomic UPDATE with `NOT is_done` so the toggle is
        // applied server-side, immune to concurrent reads.
        $updated = TrackerEntry::where('student_id', $request->student_id)
            ->where('tracker_column_id', $request->tracker_column_id)
            ->update(['is_done' => DB::raw('NOT is_done'), 'updated_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'Entry not found.'], 404);
        }

        $newValue = (bool) TrackerEntry::where('student_id', $request->student_id)
            ->where('tracker_column_id', $request->tracker_column_id)
            ->value('is_done');

        return response()->json(['is_done' => $newValue]);
    }
}
