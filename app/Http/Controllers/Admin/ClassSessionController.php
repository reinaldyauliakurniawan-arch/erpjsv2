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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class ClassSessionController extends Controller
{
    /**
     * AttendanceService instance.
     *
     * @var \App\Services\AttendanceService
     */
    protected $attendanceService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\AttendanceService  $attendanceService
     * @return void
     */
    public function __construct(AttendanceService $attendanceService)
    {
        $this->middleware('auth');
        $this->attendanceService = $attendanceService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('viewAny', ClassSession::class);

        $classSessions = ClassSession::with('program')
            ->withCount('enrollments')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.class_sessions.index', compact('classSessions'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('create', ClassSession::class);

        $programs   = Program::orderBy('name')->get();
        $tutors     = Tutor::with('user')->get();
        $classTypes = ClassType::cases();
        $classrooms = Classroom::all();
        $timeBlocks = TimeBlock::cases();
        $days       = DayOfWeek::cases();

        return view('admin.class_sessions.create', compact('programs', 'tutors', 'classTypes', 'classrooms', 'timeBlocks', 'days'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', ClassSession::class);

        $validated = $this->validateStore($request);

        try {
            $classSession = DB::transaction(function () use ($validated) {
                $classSession = ClassSession::create($validated['session']);

                if (!empty($validated['tutor_ids'])) {
                    $this->attachTutors($classSession, $validated['tutor_ids']);
                }

                if (!empty($validated['enrollment_ids'])) {
                    $this->updateEnrollments($classSession, $validated['enrollment_ids']);
                }

                if (!empty($validated['schedules'])) {
                    $this->createSchedules($classSession, $validated['schedules']);
                }

                return $classSession;
            });

            Log::info('Class session created successfully', [
                'class_session_id' => $classSession->id,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('admin.class-sessions.show', $classSession->id)
                ->with('success', 'Class session created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create class session', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'input' => $request->all()
            ]);

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $classSession = ClassSession::with([
            'program',
            'enrollments.student.user',
            'tutors.user',
            'schedules.classroom',
        ])->findOrFail($id);

        $this->authorize('view', $classSession);

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

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $programs     = Program::orderBy('name')->get();
        $classTypes   = ClassType::cases();

        return view('admin.class_sessions.edit', compact('classSession', 'programs', 'classTypes'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $validated = $this->validateUpdate($request, $id);

        try {
            $classSession->update($validated['session']);

            Log::info('Class session updated successfully', [
                'class_session_id' => $classSession->id,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('admin.class-sessions.index')->with('success', 'Class session updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update class session', [
                'error' => $e->getMessage(),
                'class_session_id' => $id,
                'user_id' => auth()->id()
            ]);

            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('delete', $classSession);

        // Check if there are active enrollments
        $hasActiveEnrollments = Enrollment::where('class_session_id', $id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveEnrollments) {
            return redirect()->route('admin.class-sessions.index')
                ->with('error', 'Class session cannot be deleted because there are active students enrolled.');
        }

        // Check if there are any paid attendances for tutors
        $hasPaidAttendance = DB::table('attendance_tutor')
            ->join('attendance', 'attendance_tutor.attendance_id', '=', 'attendance.id')
            ->where('attendance.class_session_id', $id)
            ->whereNotNull('attendance_tutor.paid_at')
            ->exists();

        if ($hasPaidAttendance) {
            return redirect()->route('admin.class-sessions.index')
                ->with('error', 'Class session cannot be deleted because there are tutors who have been paid for this session.');
        }

        try {
            DB::transaction(function () use ($classSession) {
                // Reverse all attendances for this class session
                $attendances = Attendance::where('class_session_id', $classSession->id)->get();
                foreach ($attendances as $attendance) {
                    $this->attendanceService->reverseAttendance($attendance);
                }

                // Delete the class session (this will cascade to related records like schedules, etc.)
                $classSession->delete();
            });

            Log::info('Class session deleted successfully', [
                'class_session_id' => $id,
                'user_id' => auth()->id()
            ]);

            return redirect()->route('admin.class-sessions.index')->with('success', 'Class session deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete class session', [
                'error' => $e->getMessage(),
                'class_session_id' => $id,
                'user_id' => auth()->id()
            ]);

            throw $e;
        }
    }

    /**
     * Assign an enrollment to the class session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function assignEnrollment(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $request->validate(['enrollment_id' => 'required|exists:enrollments,id']);

        Enrollment::where('id', $request->enrollment_id')
            ->update(['class_session_id' => $id]);

        return back()->with('success', 'Student assigned to class session successfully.');
    }

    /**
     * Remove an enrollment from the class session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function removeEnrollment(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $request->validate(['enrollment_id' => 'required|exists:enrollments,id']);

        Enrollment::where('id', $request->enrollment_id)
            ->where('class_session_id', $id)
            ->update(['class_session_id' => null]);

        return back()->with('success', 'Student removed from class session successfully.');
    }

    /**
     * Assign a tutor to the class session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function assignTutor(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $request->validate(['tutor_id' => 'required|exists:tutors,id']);

        if ($classSession->tutors()->where('tutor_id', $request->tutor_id)->exists()) {
            return back()->withErrors(['error' => 'Tutor is already assigned to this class session.']);
        }

        $classSession->tutors()->attach($request->tutor_id, ['status' => 'pending']);

        return back()->with('success', 'Tutor assigned successfully.');
    }

    /**
     * Remove a tutor from the class session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function removeTutor(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $request->validate(['tutor_id' => 'required|exists:tutors,id']);

        $classSession->tutors()->detach($request->tutor_id);

        return back()->with('success', 'Tutor removed successfully.');
    }

    /**
     * Update the status of a tutor in the class session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $tutorId
     * @return \Illuminate\Http\Response
     */
    public function updateTutorStatus(Request $request, $id, $tutorId)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $request->validate(['status' => 'required|in:pending,confirmed']);

        $classSession->tutors()->updateExistingPivot($tutorId, [
            'status' => $request->status,
        ]);

        return back()->with('success', 'Tutor status updated successfully.');
    }

    /**
     * Store a new schedule for the class session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function storeSchedule(Request $request, $id)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        $validated = $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'day'          => 'required|string',
            'time_block'   => 'required|string',
            'custom_time'  => 'nullable|required_if:time_block,Custom|string',
        ]);

        $timeBlock = $request->time_block === 'Custom' ? $request->custom_time : $request->time_block;

        // Lock the classroom and class session to prevent race conditions
        $classroom = Classroom::where('id', $request->classroom_id)->lockForUpdate()->first();
        if (!$classroom) {
            return back()->withErrors(['error' => 'Kelas tidak ditemukan.']);
        }

        $classSessionLock = ClassSession::where('id', $id)->lockForUpdate()->first();
        if (!$classSessionLock) {
            return back()->withErrors(['error' => 'Kelas sesi tidak ditemukan.']);
        }

        // Check for conflicts
        $roomConflict = \App\Models\Schedule::where('classroom_id', $request->classroom_id)
            ->where('day', $request->day)
            ->where('time_block', $timeBlock)
            ->exists();

        $classSessionConflict = \App\Models\Schedule::where('class_session_id', $id)
            ->where('day', $request->day)
            ->where('time_block', $timeBlock)
            ->exists();

        if ($roomConflict || $classSessionConflict) {
            return back()->withErrors(['error' => 'Jadwal bentrok.']);
        }

        \App\Models\Schedule::create([
            'class_session_id' => $id,
            'enrollment_id'    => null,
            'classroom_id'     => $request->classroom_id,
            'day'              => $request->day,
            'time_block'       => $timeBlock,
        ]);

        return back()->with('success', 'Schedule added successfully.');
    }

    /**
     * Remove a schedule from the class session.
     *
     * @param  int  $id
     * @param  int  $scheduleId
     * @return \Illuminate\Http\Response
     */
    public function destroySchedule($id, $scheduleId)
    {
        $classSession = ClassSession::findOrFail($id);

        $this->authorize('update', $classSession);

        \App\Models\Schedule::where('id', $scheduleId)
            ->where('class_session_id', $id)
            ->delete();

        return back()->with('success', 'Schedule removed successfully.');
    }

    /**
     * Get information about the class session for AJAX requests.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function info($id)
    {
        $classSession = ClassSession::with('program', 'schedules.classroom')->findOrFail($id);

        $this->authorize('view', $classSession);

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

    /**
     * Get available enrollments for a program (for AJAX requests).
     *
     * @param  int  $programId
     * @return \Illuminate\Http\Response
     */
    public function availableEnrollments($programId)
    {
        $enrollments = Enrollment::with('student.user')
            ->where('program_id', $programId)
            ->where('status', 'active')
            ->whereNull('class_session_id')
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id'   => $enrollment->id,
                    'name' => $enrollment->student->user->name,
                ];
            });

        return response()->json($enrollments);
    }

    /**
     * Validate the store request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function validateStore(Request $request)
    {
        $validated = $request->validate([
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

        return [
            'session' => $request->only('name', 'program_id', 'class_type', 'status'),
            'tutor_ids' => $request->input('tutor_ids', []),
            'enrollment_ids' => $request->input('enrollment_ids', []),
            'schedules' => $request->input('schedules', []),
        ];
    }

    /**
     * Validate the update request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return array
     */
    protected function validateUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255|unique:class_sessions,name,' . $id,
            'program_id' => 'required|exists:programs,id',
            'class_type' => 'required|in:private,semi-private,group',
            'status'     => 'required|in:active,inactive',
        ]);

        return [
            'session' => $request->only('name', 'program_id', 'class_type', 'status'),
        ];
    }

    /**
     * Attach tutors to the class session.
     *
     * @param  \App\Models\ClassSession  $classSession
     * @param  array  $tutorIds
     * @return void
     */
    protected function attachTutors(ClassSession $classSession, array $tutorIds)
    {
        $classSession->tutors()->attach($tutorIds, ['status' => 'pending']);
    }

    /**
     * Update enrollments to point to the class session.
     *
     * @param  \App\Models\ClassSession  $classSession
     * @param  array  $enrollmentIds
     * @return void
     */
    protected function updateEnrollments(ClassSession $classSession, array $enrollmentIds)
    {
        Enrollment::whereIn('id', $enrollmentIds)
            ->update(['class_session_id' => $classSession->id]);
    }

    /**
     * Create schedules for the class session.
     *
     * @param  \App\Models\ClassSession  $classSession
     * @param  array  $schedules
     * @return void
     */
    protected function createSchedules(ClassSession $classSession, array $schedules)
    {
        foreach ($schedules as $schedule) {
            if (empty($schedule['day']) || empty($schedule['time_block']) || empty($schedule['classroom_id'])) {
                continue;
            }

            // Lock the classroom and class session to prevent race conditions
            $classroom = Classroom::where('id', $schedule['classroom_id'])->lockForUpdate()->first();
            if (!$classroom) {
                throw new \Exception("Kelas tidak ditemukan.");
            }

            $classSessionLock = ClassSession::where('id', $classSession->id)->lockForUpdate()->first();
            if (!$classSessionLock) {
                throw new \Exception("Kelas sesi tidak ditemukan.");
            }

            // Check for conflicts
            $roomConflict = \App\Models\Schedule::where('classroom_id', $schedule['classroom_id'])
                ->where('day', $schedule['day'])
                ->where('time_block', $schedule['time_block'])
                ->exists();

            $classSessionConflict = \App\Models\Schedule::where('class_session_id', $classSession->id)
                ->where('day', $schedule['day'])
                ->where('time_block', $schedule['time_block'])
                ->exists();

            if ($roomConflict || $classSessionConflict) {
                throw new \Exception("Jadwal bentrok untuk ruang {$schedule['classroom_id']} atau kelas sesi {$classSession->id} pada hari {$schedule['day']} pukul {$schedule['time_block']}.");
            }

            \App\Models\Schedule::create([
                'class_session_id' => $classSession->id,
                'enrollment_id'    => null,
                'classroom_id'     => $schedule['classroom_id'],
                'day'              => $schedule['day'],
                'time_block'       => $schedule['time_block'],
            ]);
        }
    }
}
