<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Classroom;
use App\Models\Tutor;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    return view('tutor.attendance.index', compact(
        'unpaidTotal', 'paidThisMonth', 'pendingRateCount'
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
        $pivot = DB::table('attendance_tutor')
            ->where('attendance_id', $att->id)
            ->where('tutor_id', $tutor->id)
            ->first();

        return [
            'id'           => $att->id,
            'date'         => $att->date,
            'date_fmt'     => \Carbon\Carbon::parse($att->date)->isoFormat('D MMM YYYY'),
            'program'      => $att->classSession?->program?->name ?? '—',
            'time_block'   => $att->time_block,
            'hadir'        => $att->students->where('pivot.is_present', true)->count() . '/' . $att->students->count(),
            'payable'      => $pivot?->payable_amount ?? 0,
            'pending_rate' => $pivot?->pending_rate ?? false,
            'paid_at'      => $pivot?->paid_at,
        ];
    });

    return response()->json($rows);
}

    public function searchSessions(Request $request)
{
    $tutor = Tutor::where('user_id', Auth::id())->firstOrFail();
    $q = $request->input('q', '');

    $query = ClassSession::with('program')
        ->where('status', 'active')
        ->where(function ($query) use ($q) {
            $query->where('name', 'like', "%{$q}%")
                  ->orWhereHas('program', fn($q2) => $q2->where('name', 'like', "%{$q}%"));
        });

    if ($request->input('mode') === 'own') {
        $query->whereHas('tutors', fn($q2) => $q2->where('tutor_id', $tutor->id));
    }

    return response()->json(
        $query->limit(10)->get()->map(fn($cs) => [
            'id'   => $cs->id,
            'name' => $cs->name . ' — ' . $cs->program->name,
        ])
    );
}

public function create(Request $request)
{
    $classrooms     = Classroom::orderBy('name')->get();
    $enrollments    = collect();
    $assignedTutors = collect();
    $selectedSession = null;

    if ($request->filled('class_session_id')) {
        $selectedSession = ClassSession::with('program')->find($request->class_session_id);

        $enrollments = Enrollment::with('student.user')
            ->where('class_session_id', $request->class_session_id)
            ->where('status', 'active')
            ->get();

        $currentTutor = Tutor::where('user_id', Auth::id())->first();
        $assignedTutors = ClassSession::with('tutors.user')
            ->find($request->class_session_id)
            ?->tutors
            ->filter(fn($t) => $t->id !== $currentTutor?->id)
            ?? collect();
    }

    return view('tutor.attendance.create', compact('classrooms', 'enrollments', 'assignedTutors', 'selectedSession'));
}

    public function store(Request $request)
    {
        $request->validate([
            'class_session_id'         => 'required|exists:class_sessions,id',
            'date'                     => 'required|date',
            'time_block'               => 'required|string',
            'classroom_id'             => 'nullable|exists:classrooms,id',
            'students'                 => 'required|array|min:1',
            'students.*.enrollment_id' => 'required|exists:enrollments,id',
            'students.*.is_present'    => 'required|boolean',
            'students.*.notes'         => 'nullable|string',
            'notes'              => 'nullable|string|max:1000',
            'is_replacement'     => 'nullable|boolean',
            'replaced_tutor_id'  => 'nullable|exists:tutors,id',
        ]);

        $data              = $request->only(['class_session_id', 'date', 'time_block', 'classroom_id', 'notes', 'students', 'is_replacement', 'replaced_tutor_id']);
        $data['marked_by'] = Auth::id();

        $this->attendanceService->markAttendance($data);

        return redirect()->route('tutor.attendance.index')->with('success', 'Attendance marked successfully.');
    }

    public function destroy(int $id)
    {
        $tutor      = Tutor::where('user_id', Auth::id())->firstOrFail();
        $attendance = Attendance::whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id))
            ->findOrFail($id);

        $this->attendanceService->reverseAttendance($attendance);

        return redirect()->route('tutor.attendance.index')->with('success', 'Attendance reversed successfully.');
    }
}
