<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Tutor;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function data(Request $request)
    {
        $query = Attendance::with(['classSession.program', 'tutors.user', 'students'])
            ->orderByDesc('date')
            ->orderBy('time_block');

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tutor')) {
            $query->whereHas('tutors.user', fn($q) => $q->where('name', 'like', '%' . $request->tutor . '%'));
        }
        if ($request->filled('program_type')) {
            $query->whereHas('classSession.program', fn($q) => $q->where('type', $request->program_type));
        }

        $attendances = $query->get();

        $duplicateKeys = $attendances
            ->groupBy(fn($a) => $a->date . '|' . $a->time_block . '|' . $a->class_session_id)
            ->filter(fn($g) => $g->count() > 1)
            ->keys()
            ->toArray();

        $today = Carbon::today()->toDateString();

        $replacedTutorIds = $attendances->flatMap(fn($a) => $a->tutors)
            ->filter(fn($t) => $t->pivot->is_replacement && $t->pivot->replaced_tutor_id)
            ->pluck('pivot.replaced_tutor_id')
            ->unique()
            ->values();

        $replacedTutors = Tutor::with('user')
            ->whereIn('id', $replacedTutorIds)
            ->get()
            ->keyBy('id');

        $rows = $attendances->map(function ($att) use ($duplicateKeys, $replacedTutors) {
            $key           = $att->date . '|' . $att->time_block . '|' . $att->class_session_id;
            $isDuplicate   = in_array($key, $duplicateKeys);
            $totalStudents = $att->students->count();
            $present       = $att->students->where('pivot.is_present', true)->count();
            $pct           = $totalStudents > 0 ? round($present / $totalStudents * 100) : null;
            $tutorNames    = $att->tutors->map(fn($t) => $t->user->name)->filter()->values();

$replacements = $att->tutors
    ->where('pivot.is_replacement', true)
    ->map(function ($t) use ($replacedTutors) {
        $replacedTutorName = null;
        if ($t->pivot->replaced_tutor_id) {
            $replacedTutorName = $replacedTutors->get($t->pivot->replaced_tutor_id)?->user?->name;
        }
        return [
            'replaced_by'    => $t->user->name,
            'replaced_tutor' => $replacedTutorName,
        ];
    })->values();

            return [
                'id'           => $att->id,
                'date'         => $att->date,
                'date_label'   => Carbon::parse($att->date)->format('d M Y'),
                'day_label'    => Carbon::parse($att->date)->format('l'),
                'time_block'   => $att->time_block,
                'class_name'   => $att->classSession?->name ?? '—',
                'program_name' => $att->classSession?->program?->name ?? '—',
                'program_type' => $att->classSession?->program?->type ?? '',
                'tutors'       => $tutorNames,
                'replacements' => $replacements,
                'present'      => $present,
                'total'        => $totalStudents,
                'pct'          => $pct,
                'status'       => $att->status ?? 'pending',
                'is_duplicate' => $isDuplicate,
            ];
        });

        $withStudents  = $attendances->filter(fn($a) => $a->students->count() > 0);
        $avgAttendance = $withStudents->count() > 0
            ? round($withStudents->map(fn($a) =>
                $a->students->where('pivot.is_present', true)->count() / $a->students->count() * 100
              )->avg(), 1)
            : 0;

        $summary = [
            'total_sessions'  => $attendances->count(),
            'active_today'    => $attendances->filter(fn($a) => $a->date === $today)->count(),
            'avg_attendance'  => $avgAttendance,
            'duplicate_count' => count($duplicateKeys),
        ];

        return response()->json([
            'rows'    => $rows,
            'summary' => $summary,
        ]);
    }

    public function index()
    {
        return view('admin.attendance.index');
    }

    public function update(int $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:scheduled,ongoing,finished,skipped,postponed',
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->update([
            'status' => $request->input('status'),
        ]);

        return redirect()->route('admin.attendance.index')->with('success', 'Status attendance diperbarui.');
    }

    public function destroy(int $id)
    {
        $attendance = Attendance::findOrFail($id);
        $this->attendanceService->reverseAttendance($attendance);

        return redirect()->route('admin.attendance.index')->with('success', 'Attendance deleted.');
    }
}
