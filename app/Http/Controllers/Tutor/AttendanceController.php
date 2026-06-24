<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Tutor;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function index()
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        $unpaidTotal = DB::table('attendance_tutor')
            ->where('tutor_id', $tutor->id)
            ->whereNull('paid_at')
            ->where('pending_rate', false)
            ->sum('payable_amount');

        $paidThisMonth = DB::table('attendance_tutor')
            ->where('tutor_id', $tutor->id)
            ->whereNotNull('paid_at')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('payable_amount');

        $pendingRateCount = DB::table('attendance_tutor')
            ->where('tutor_id', $tutor->id)
            ->where('pending_rate', true)
            ->whereNull('paid_at')
            ->count();

        $classrooms = Classroom::orderBy('name')->get();

        return view('tutor.attendance.index', compact(
            'unpaidTotal', 'paidThisMonth', 'pendingRateCount', 'classrooms'
        ));
    }

    public function data(Request $request)
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();

        $query = Attendance::with(['classSession.program', 'students', 'tutors'])
            ->whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id));

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('classSession.program', fn($q2) => $q2->where('name', 'like', "%$search%"))
                  ->orWhere('time_block', 'like', "%$search%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $rows = $query->orderByDesc('date')->get()->map(function ($att) use ($tutor) {
            // N+1 fix: previously ran a separate DB query per attendance row
            // to fetch the pivot. The `tutors` relation is already eager-loaded
            // (line 59), so we can read the pivot from the loaded collection.
            $pivot = $att->tutors->firstWhere('id', $tutor->id)?->pivot;

            $mode = 'own';
            if ($pivot?->is_replacement) $mode = 'replacement';
            elseif ($pivot?->is_team_teaching) $mode = 'team_teaching';

            return [
                'id'               => $att->id,
                'date'             => $att->date,
                'date_fmt'         => \Carbon\Carbon::parse($att->date)->isoFormat('D MMM YYYY'),
                'program'          => $att->classSession?->program?->name ?? '—',
                'class_session_id' => $att->class_session_id,
                'time_block'       => $att->time_block,
                'hadir'            => $att->students->where('pivot.is_present', true)->count() . '/' . $att->students->count(),
                'mode'             => $mode,
                'payable'          => $pivot?->payable_amount ?? 0,
                'pending_rate'     => $pivot?->pending_rate ?? false,
                'paid_at'          => $pivot?->paid_at,
                'notes'            => $att->notes,
            ];
        });

        return response()->json($rows);
    }

    public function searchSessions(Request $request)
    {
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();
        $q     = $request->input('q', '');

        $query = ClassSession::with('program')
            ->where('status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhereHas('program', fn($q2) => $q2->where('name', 'like', "%{$q}%"));
            });

        if ($request->input('mode') === 'own' || $request->input('mode') === 'team_teaching') {
            $query->whereHas('tutors', fn($q2) => $q2->where('tutor_id', $tutor->id));
        }

        return response()->json(
            $query->limit(10)->get()->map(function ($cs) {
                // Default classroom: ambil dari attendance terakhir kelas ini
                $lastClassroomId = Attendance::where('class_session_id', $cs->id)
                    ->orderByDesc('date')
                    ->value('classroom_id');

                return [
                    'id'                  => $cs->id,
                    'name'                => $cs->name . ' — ' . $cs->program->name,
                    'last_classroom_id'   => $lastClassroomId,
                ];
            })
        );
    }

    public function history(Request $request)
    {
        $request->validate(['class_session_id' => 'required|exists:class_sessions,id']);

        $enrollments = Enrollment::with('student.user')
            ->where('class_session_id', $request->class_session_id)
            ->whereIn('status', ['active', 'waitlist'])
            ->get();

        $attendances = Attendance::with(['students', 'tutors.user'])
            ->where('class_session_id', $request->class_session_id)
            ->whereNull('deleted_at')
            ->orderByDesc('date')
            ->limit(10)
            ->get();

        $sessions = $attendances->map(fn($att) => [
            'id'         => $att->id,
            'date'       => \Carbon\Carbon::parse($att->date)->isoFormat('D MMM'),
            'time_block' => $att->time_block,
            'tutors'     => $att->tutors->map(fn($t) => $t->user->name)->join(', '),
            'notes'      => $att->notes,
        ]);

        $matrix = $enrollments->map(function ($enrollment) use ($attendances) {
            $presence = $attendances->map(fn($att) => [
                'attendance_id' => $att->id,
                'is_present'    => (bool) optional(
                    $att->students->firstWhere('id', $enrollment->id)
                )->pivot?->is_present,
                'notes'         => optional(
                    $att->students->firstWhere('id', $enrollment->id)
                )->pivot?->notes,
            ]);

            return [
                'enrollment_id' => $enrollment->id,
                'name'          => $enrollment->student->user->name,
                'presence'      => $presence,
            ];
        });

        // Co-tutor candidates untuk mode team teaching
        $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();
        $coTutorCandidates = ClassSession::find($request->class_session_id)
            ?->tutors()
            ->with('user')
            ->where('tutor_id', '!=', $tutor->id)
            ->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->user->name]);

        // Semua tutor aktif untuk mode replacement
        $assignedTutors = \App\Models\Tutor::with('user')
            ->where('id', '!=', $tutor->id)
            ->get()
            ->map(fn($t) => ['id' => $t->id, 'name' => $t->user->name]);

        return response()->json([
            'sessions'        => $sessions,
            'matrix'          => $matrix,
            'co_tutor_candidates' => $coTutorCandidates,
            'assigned_tutors' => $assignedTutors,
            'enrollments'     => $enrollments->map(fn($e) => [
                'enrollment_id' => $e->id,
                'name'          => $e->student->user->name,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_session_id'         => 'required|exists:class_sessions,id',
            'date'                     => 'required|date',
            'time_block'               => 'required|string',
            'classroom_id'             => 'required|exists:classrooms,id',
            'students'                 => 'required|array|min:1',
            'students.*.enrollment_id' => 'required|exists:enrollments,id',
            'students.*.is_present'    => 'required|boolean',
            'students.*.notes'         => 'nullable|string|max:500',
            'notes'                    => 'nullable|string|max:1000',
            'mode'                     => 'required|in:own,replacement,team_teaching',
            'replaced_tutor_id'        => 'nullable|exists:tutors,id',
            'co_tutor_ids'             => 'nullable|array',
            'co_tutor_ids.*'           => 'exists:tutors,id',
        ]);

        $mode = $request->input('mode');

        $data = [
            'class_session_id'  => $request->class_session_id,
            'date'              => $request->date,
            'time_block'        => $request->time_block,
            'classroom_id'      => $request->classroom_id,
            'notes'             => $request->notes,
            'students'          => $request->students,
            'marked_by'         => Auth::id(),
            'is_replacement'    => $mode === 'replacement',
            'is_team_teaching'  => $mode === 'team_teaching',
            'replaced_tutor_id' => $mode === 'replacement' ? $request->replaced_tutor_id : null,
            'co_tutor_ids'      => $mode === 'team_teaching' ? $request->co_tutor_ids : [],
        ];

        try {
            $this->attendanceService->markAttendance($data);
            return response()->json(['success' => true, 'message' => 'Absensi berhasil disimpan.']);
        } catch (\App\Exceptions\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // Silent-fail fix: previously the exception was swallowed with a
            // generic message and no logging. Production bugs were impossible
            // to diagnose. Now we log the full exception + input for triage.
            Log::error('tutor.attendance.store failed', [
                'tutor_id'   => $tutor->id ?? null,
                'input'      => $request->except(['students']),
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan. Coba lagi.'], 500);
        }
    }

    public function destroy(int $id)
    {
        $tutor      = Tutor::where('user_id', Auth::id())->firstOrFail();
        $attendance = Attendance::whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id))
            ->findOrFail($id);

        try {
            $this->attendanceService->reverseAttendance($attendance);
            return response()->json(['success' => true, 'message' => 'Absensi berhasil di-reverse.']);
        } catch (\App\Exceptions\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // Silent-fail fix: log the exception so production bugs are diagnosable.
            Log::error('tutor.attendance.destroy failed', [
                'tutor_id'     => $tutor->id,
                'attendance_id'=> $id,
                'exception'    => $e::class,
                'message'      => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan.'], 500);
        }
    }
}
