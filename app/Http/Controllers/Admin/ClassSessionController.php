<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Program;
use App\Models\Enrollment;
use App\Models\Tutor;
use App\Models\Classroom;
use App\Models\Attendance;
use App\Enums\TimeBlock;
use App\Enums\DayOfWeek;
use App\Enums\ClassType;
use App\Services\AttendanceService;
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
    $programs   = Program::orderBy('name')->get();
    $tutors     = Tutor::with('user')->get();
    $classTypes = ClassType::cases();
    $classrooms = Classroom::all();
    $timeBlocks = TimeBlock::cases();
    $days       = DayOfWeek::cases();
    return view('admin.class_sessions.create', compact('programs', 'tutors', 'classTypes', 'classrooms', 'timeBlocks', 'days'));
}

    public function store(Request $request)
{
    $request->validate([
        'name'                      => 'required|string|max:255|unique:class_sessions,name',
        'program_id'                => 'required|exists:programs,id',
        'class_type'                => 'required|in:private,semi-private,group',
        'status'                    => 'required|in:active,inactive',
        'tutor_ids'                 => 'nullable|array',
        'tutor_ids.*'               => 'exists:tutors,id',
        'enrollment_ids'            => 'nullable|array',
        'enrollment_ids.*'          => 'exists:enrollments,id',
        'schedules'                 => 'nullable|array',
        'schedules.*.day'           => 'required_with:schedules|string',
        'schedules.*.time_block'    => 'required_with:schedules|string',
        'schedules.*.classroom_id'  => 'required_with:schedules|exists:classrooms,id',
    ]);

    $classSession = ClassSession::create($request->only('name', 'program_id', 'class_type', 'status'));

    if ($request->filled('tutor_ids')) {
        $classSession->tutors()->attach($request->tutor_ids, ['status' => 'pending']);
    }

    if ($request->filled('enrollment_ids')) {
        Enrollment::whereIn('id', $request->enrollment_ids)
            ->update(['class_session_id' => $classSession->id]);
    }

    if ($request->filled('schedules')) {
        foreach ($request->schedules as $s) {
            if (empty($s['day']) || empty($s['time_block']) || empty($s['classroom_id'])) continue;

            $conflict = \App\Models\Schedule::where('classroom_id', $s['classroom_id'])
                ->where('day', $s['day'])
                ->where('time_block', $s['time_block'])
                ->exists();

            if (!$conflict) {
                \App\Models\Schedule::create([
                    'class_session_id' => $classSession->id,
                    'enrollment_id'    => null,
                    'classroom_id'     => $s['classroom_id'],
                    'day'              => $s['day'],
                    'time_block'       => $s['time_block'],
                ]);
            }
        }
    }

    return redirect()->route('admin.class-sessions.show', $classSession->id)
        ->with('success', 'Class session created.');
}

    public function show($id)
    {
        $classSession = ClassSession::with([
            'program',
            'enrollments.student.user',
            'tutors.user',
            'schedules.classroom',
        ])->findOrFail($id);

        $availableEnrollments = Enrollment::with('student.user')
            ->where('program_id', $classSession->program_id)
            ->whereIn('status', ['active', 'waitlist'])
            ->whereNull('class_session_id')
            ->get();

        $availableTutors = Tutor::with('user')
            ->whereDoesntHave('classSessions', function ($q) use ($id) {
                $q->where('class_session_id', $id);
            })
            ->get();

        $classrooms = Classroom::all();
        $timeBlocks = TimeBlock::cases();
        $days       = DayOfWeek::cases();

        return view('admin.class_sessions.show', compact(
            'classSession', 'availableEnrollments', 'availableTutors',
            'classrooms', 'timeBlocks', 'days'
        ));
    }

    public function edit($id)
    {
        $classSession = ClassSession::findOrFail($id);
        $programs     = Program::orderBy('name')->get();
        $classTypes   = ClassType::cases();
        return view('admin.class_sessions.edit', compact('classSession', 'programs', 'classTypes'));
    }

    public function update(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $request->validate([
            'name'       => 'required|string|max:255|unique:class_sessions,name,' . $id,
            'program_id' => 'required|exists:programs,id',
            'class_type' => 'required|in:private,semi-private,group',
            'status'     => 'required|in:active,inactive',
        ]);

        $classSession->update($request->only('name', 'program_id', 'class_type', 'status'));

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
            ->with('error', 'Class session tidak bisa dihapus karena masih ada siswa aktif.');
    }

    Attendance::where('class_session_id', $id)->each(function ($attendance) {
        app(AttendanceService::class)->reverseAttendance($attendance);
    });

    $classSession->delete();
    return redirect()->route('admin.class-sessions.index')->with('success', 'Class session deleted.');
}

    public function assignEnrollment(Request $request, $id)
    {
        $request->validate(['enrollment_id' => 'required|exists:enrollments,id']);

        Enrollment::where('id', $request->enrollment_id)
            ->update(['class_session_id' => $id]);

        return back()->with('success', 'Student assigned to class session.');
    }

    public function removeEnrollment(Request $request, $id)
    {
        $request->validate(['enrollment_id' => 'required|exists:enrollments,id']);

        Enrollment::where('id', $request->enrollment_id)
            ->where('class_session_id', $id)
            ->update(['class_session_id' => null]);

        return back()->with('success', 'Student removed from class session.');
    }

    public function assignTutor(Request $request, $id)
    {
        $request->validate(['tutor_id' => 'required|exists:tutors,id']);

        $classSession = ClassSession::findOrFail($id);
        $classSession->tutors()->attach($request->tutor_id, ['status' => 'pending']);

        return back()->with('success', 'Tutor assigned.');
    }

    public function removeTutor(Request $request, $id)
    {
        $request->validate(['tutor_id' => 'required|exists:tutors,id']);

        $classSession = ClassSession::findOrFail($id);
        $classSession->tutors()->detach($request->tutor_id);

        return back()->with('success', 'Tutor removed.');
    }

    public function updateTutorStatus(Request $request, $id, $tutorId)
    {
        $request->validate(['status' => 'required|in:pending,confirmed']);

        ClassSession::findOrFail($id)->tutors()->updateExistingPivot($tutorId, [
            'status' => $request->status,
        ]);

        return back()->with('success', 'Tutor status updated.');
    }

    public function storeSchedule(Request $request, $id)
    {
        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'day'          => 'required|string',
            'time_block'   => 'required|string',
            'custom_time'  => 'nullable|required_if:time_block,Custom|string',
        ]);

        $classSession = ClassSession::findOrFail($id);
        $timeBlock    = $request->time_block === 'Custom' ? $request->custom_time : $request->time_block;

        $exists = \App\Models\Schedule::where('class_session_id', $id)
            ->where('day', $request->day)
            ->where('time_block', $timeBlock)
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Jadwal ini sudah ada.']);
        }

        $conflictRoom = \App\Models\Schedule::where('classroom_id', $request->classroom_id)
            ->where('day', $request->day)
            ->where('time_block', $timeBlock)
            ->exists();

        if ($conflictRoom) {
            return back()->withErrors(['error' => 'Ruangan sudah dipakai di slot ini.']);
        }

        \App\Models\Schedule::create([
            'class_session_id' => $id,
            'enrollment_id'    => $classSession->enrollments()->first()?->id,
            'classroom_id'     => $request->classroom_id,
            'day'              => $request->day,
            'time_block'       => $timeBlock,
        ]);

        return back()->with('success', 'Jadwal ditambahkan.');
    }

    public function destroySchedule($id, $scheduleId)
    {
        \App\Models\Schedule::where('id', $scheduleId)
            ->where('class_session_id', $id)
            ->delete();

        return back()->with('success', 'Jadwal dihapus.');
    }

    public function info($id)
    {
        $classSession = ClassSession::with('program', 'schedules.classroom')->findOrFail($id);

        $finishedCount = Attendance::where('class_session_id', $id)
            ->where('status', 'finished')
            ->count();

        $totalMeetings    = $classSession->program->total_meetings;
        $remainingDefault = max(0, $totalMeetings - $finishedCount);
        $enrollmentCount  = $classSession->enrollments()->whereIn('status', ['active', 'waitlist'])->count();
        $capacity         = $classSession->schedules->first()?->classroom?->capacity ?? null;

        return response()->json([
            'finished_meetings' => $finishedCount,
            'total_meetings'    => $totalMeetings,
            'remaining_default' => $remainingDefault,
            'enrollment_count'  => $enrollmentCount,
            'capacity'          => $capacity,
            'price'             => $classSession->program->price,
            'is_mid_join'       => $finishedCount > 0,
        ]);
    }

    public function availableEnrollments($programId)
{
    $enrollments = Enrollment::with('student.user')
        ->where('program_id', $programId)
        ->where('status', 'active')
        ->whereNull('class_session_id')
        ->get()
        ->map(fn($e) => [
            'id'   => $e->id,
            'name' => $e->student->user->name,
        ]);

    return response()->json($enrollments);
}
}
