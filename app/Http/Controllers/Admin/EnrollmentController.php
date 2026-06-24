<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Program;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Services\EnrollmentService;
use App\Services\AccountingService;
use App\Http\Requests\Admin\StoreEnrollmentRequest;
use App\Enums\AccountCode;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class EnrollmentController extends Controller
{
    protected $enrollmentService;
    protected $accountingService;

    public function __construct(EnrollmentService $enrollmentService, AccountingService $accountingService)
    {
        $this->middleware('auth');
        $this->enrollmentService = $enrollmentService;
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $this->authorize('viewAny', Enrollment::class);

        $enrollments = Enrollment::with(['student.user', 'program', 'classSession'])->get();
        return view('admin.enrollments.index', compact('enrollments'));
    }

    public function data(Request $request)
{
    $this->authorize('viewAny', Enrollment::class);

    $query = Enrollment::with(['student.user', 'program', 'classSession']);

    if ($request->filled('search')) {
    $search = $request->search;
    $query->where(function($q) use ($search) {
        $q->whereHas('student.user', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
          ->orWhereHas('program', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
    });
}

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('payment_status')) {
        $query->where('payment_status', $request->payment_status);
    }

    $page  = max(1, (int) $request->input('page', 1));
    $size  = (int) $request->input('size', 20);
    $total = $query->count();
    $items = $query->latest()->skip(($page - 1) * $size)->take($size)->get();

    return response()->json([
        'last_page' => ceil($total / $size),
        'data'      => $items->map(fn($e) => [
            'id'               => $e->id,
            'student'          => $e->student->user->name,
            'program'          => $e->program->name,
            'class_session'    => $e->classSession?->name ?? '-',
            'payment_status'   => $e->payment_status,
            'status'           => $e->status,
            'remaining'        => $e->remaining_meetings,
            'show_url'         => route('admin.enrollments.show', $e->id),
            'delete_url'       => route('admin.enrollments.destroy', $e->id),
        ]),
    ]);
}

    public function create()
    {
        $this->authorize('create', Enrollment::class);

        $programs      = Program::all();
        $classrooms    = Classroom::all();
        $classSessions = ClassSession::with(['program', 'schedules.classroom'])
            ->withCount(['enrollments as enrollments_count' => fn($q) => $q->whereIn('status', ['active', 'waitlist'])])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $students = \App\Models\Student::with('user')->orderBy('created_at', 'desc')->get();
        return view('admin.enrollments.create', compact('programs', 'classrooms', 'classSessions', 'students'));
    }

    public function store(StoreEnrollmentRequest $request)
    {
        $this->authorize('create', Enrollment::class);

        try {
            [$enrollment, $roomNotes] = $this->enrollmentService->enroll($request->validated());
            $successMsg = 'Student enrolled successfully.';
            if (!empty($roomNotes)) {
                $successMsg .= ' Catatan ruangan: ' . implode(' | ', $roomNotes);
            }
            return redirect()->route('admin.enrollments.index')->with('success', $successMsg);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function searchStudents(Request $request)
{
    $this->authorize('viewAny', Enrollment::class);

    $q = $request->input('q', '');

    // N+1 fix: previously the enrollments query ran inside ->map() per
    // student (1 query × 10 students = 10 extra queries). Now we eager-load
    // the latest 3 enrollments + program in a single relation query.
    $students = \App\Models\Student::with([
        'user',
        'enrollments' => fn($query) => $query->with('program')->latest()->take(3),
    ])
        ->whereHas('user', fn($query) => $query->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%"))
        ->limit(10)
        ->get()
        ->map(fn($s) => [
            'id'    => $s->id,
            'name'  => $s->user->name,
            'email' => $s->user->email,
            'phone' => $s->user->phone,
            'enrollments' => $s->enrollments->map(fn($e) => [
                'program' => $e->program->name,
                'status'  => $e->status,
            ]),
        ]);

    return response()->json($students);
}

public function eligibleSessions(Request $request)
{
    $this->authorize('viewAny', Enrollment::class);

    $request->validate([
        'program_id' => 'required|exists:programs,id',
        'day'        => 'nullable|string',
        'time_block' => 'nullable|string',
    ]);

    $programId = $request->program_id;
    $day       = $request->day;
    $timeBlock = $request->time_block;

    // Kalau hari/timeblock kosong → return empty (student masuk waitlist)
    if (!$day || !$timeBlock) {
        return response()->json([]);
    }

    // N+1 fix: previously 2 queries per session inside ->map() (one for
    // active count, one for finished count). With 50 sessions that's 100
    // extra queries. Now we use withCount + a single subquery for
    // finished meetings, reducing to 1 query for all sessions.
    $sessions = \App\Models\ClassSession::with(['schedules.classroom', 'tutors.user'])
        ->withCount([
            'enrollments as active_count' => fn($q) => $q->whereIn('status', ['active', 'waitlist']),
        ])
        ->where('program_id', $programId)
        ->where('status', 'active')
        ->whereHas('schedules', fn($q) => $q->where('day', $day)->where('time_block', $timeBlock))
        ->get();

    // Single query for all sessions' finished-meeting counts
    $sessionIds   = $sessions->pluck('id')->all();
    $finishedMap  = \App\Models\Attendance::whereIn('class_session_id', $sessionIds)
        ->selectRaw('class_session_id, COUNT(DISTINCT date) as finished')
        ->groupBy('class_session_id')
        ->pluck('finished', 'class_session_id');

    $result = $sessions->map(function ($session) use ($day, $timeBlock, $finishedMap) {
        $activeCount = $session->active_count;
        $finished    = $finishedMap->get($session->id, 0);
        $schedule    = $session->schedules->first();
        $capacity    = $schedule?->classroom?->capacity ?? 999;

        if ($activeCount >= $capacity) return null;
        if ($finished > 8) return null;

        return [
            'id'                => $session->id,
            'name'              => $session->name,
            'day'               => $day,
            'time_block'        => $timeBlock,
            'classroom'         => $schedule?->classroom?->name,
            'capacity'          => $schedule?->classroom?->capacity,
            'enrolled_count'    => $activeCount,
            'finished_meetings' => $finished,
            'tutors'            => $session->tutors->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->user->name,
            ]),
        ];
    })
    ->filter()
    ->values();

    return response()->json($result);
}

public function availableTutors(Request $request)
{
    $this->authorize('viewAny', Enrollment::class);

    $day       = $request->input('day');
    $timeBlock = $request->input('time_block');

    $tutors = \App\Models\Tutor::with('user')
        ->when($day && $timeBlock, function ($q) use ($day, $timeBlock) {
            // Exclude tutor yang sudah ada di sesi lain pada slot yang sama
            $q->whereHas('availability', fn($q2) => $q2->where('day', $day)->where('time_block', $timeBlock))
  ->whereDoesntHave('classSessions', function ($q2) use ($day, $timeBlock) {
      $q2->whereHas('schedules', fn($q3) => $q3->where('day', $day)->where('time_block', $timeBlock));
  });
        })
        ->get()
        ->map(fn($t) => [
            'id'   => $t->id,
            'name' => $t->user->name,
        ]);

    return response()->json($tutors);
}

    public function show($id)
{
    $this->authorize('view', Enrollment::findOrFail($id));

    $enrollment = Enrollment::with([
        'student.user',
        'program',
        'classSession',
        'installments',
        'tutors.user',
        'schedules.classroom',
    ])->findOrFail($id);

    $availableTutors = \App\Models\Tutor::with('user')
        ->whereDoesntHave('enrollments', function ($q) use ($id) {
            $q->where('enrollment_id', $id);
        })
        ->get();

    return view('admin.enrollments.show', compact('enrollment', 'availableTutors'));
}

    public function markInstallmentPaid(Request $request, $enrollmentId, $installmentId)
    {
        $this->authorize('update', Enrollment::findOrFail($enrollmentId));

        // Race-condition fix: previously `lockForUpdate()` was called OUTSIDE
        // the DB transaction, so the row lock was released immediately. Also,
        // only the current installment was (supposedly) locked; sibling
        // installments and the enrollment row were not. Two concurrent
        // requests paying different installments of the same enrollment
        // could both count "1 unpaid" and both write `payment_status=partial`,
        // corrupting the final `payment_status` (should be `full`).
        //
        // Fix: lock the entire enrollment row + all its installments inside
        // a single transaction. Re-check `paid_at` inside the lock to defend
        // against a concurrent request that already paid this installment.
        $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollmentId);

        $installment = Installment::where('id', $installmentId)
            ->where('enrollment_id', $enrollmentId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($installment->paid_at) {
            return back()->withErrors(['error' => 'Installment sudah dibayar sebelumnya.']);
        }

        // Lock all sibling installments so the unpaid-count below is consistent
        Installment::where('enrollment_id', $enrollmentId)->lockForUpdate()->get();

        DB::transaction(function () use ($installment, $enrollment, $enrollmentId, $installmentId) {
            // Re-check inside the transaction — a concurrent request may have
            // paid this installment between the outer check and the lock acquisition.
            if ($installment->fresh()->paid_at) {
                throw new \App\Exceptions\DomainException('Installment sudah dibayar sebelumnya.');
            }

            $this->accountingService->createJournal(
                now()->toDateString(),
                "Installment Payment - Enrollment #{$enrollmentId}",
                "INSTALLMENT-{$installmentId}",
                [
                    ['account_code' => $installment->payment_channel === 'bank' ? AccountCode::BANK->value : AccountCode::CASH->value, 'debit' => $installment->amount, 'credit' => 0],
                    ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => $installment->amount],
                ]
            );

            $installment->update(['paid_at' => now()]);

            $unpaidCount = Installment::where('enrollment_id', $enrollmentId)
                ->whereNull('paid_at')
                ->count();

            $enrollment->update([
                'payment_status' => $unpaidCount === 0
                    ? PaymentStatus::FULL->value
                    : PaymentStatus::PARTIAL->value,
            ]);
        });

        return back()->with('success', 'Installment marked as paid.');
    }

    public function expire($id)
    {
        $this->authorize('update', Enrollment::findOrFail($id));

        // Atomicity fix: previously, journal creation, enrollment update, and
        // room booking deletion were 3 separate writes with no transaction.
        // If the enrollment update failed after the journal was created, we'd
        // have a revenue recognition journal for an enrollment that's still
        // active — leading to double revenue recognition later.
        return DB::transaction(function () use ($id) {
            $enrollment = Enrollment::with(['student.user', 'program'])->lockForUpdate()->findOrFail($id);

            if ($enrollment->status !== 'active') {
                return back()->withErrors(['error' => 'Enrollment tidak aktif.']);
            }

            $paidAmount        = $enrollment->payment_method === 'full upfront'
                ? (float) $enrollment->total_amount
                : (float) $enrollment->installments()->whereNotNull('paid_at')->sum('amount');
            $perMeetingPrice   = $enrollment->program->total_meetings > 0
                ? bcdiv((string) $paidAmount, (string) $enrollment->program->total_meetings, 2)
                : '0';
            $remainingDeferred = bcmul((string) $enrollment->remaining_meetings, $perMeetingPrice, 2);

            if ($remainingDeferred > 0) {
                try {
                    $this->accountingService->createJournal(
                        now()->toDateString(),
                        "Manual Expiry - Student {$enrollment->student->user->name}",
                        "MANUAL-EXPIRY-{$enrollment->id}",
                        [
                            ['account_code' => AccountCode::DEFERRED_REVENUE->value,     'debit' => $remainingDeferred, 'credit' => 0],
                            ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $remainingDeferred],
                        ],
                        'revenue_recognition',
                        $enrollment->program_id
                    );
                } catch (\App\Exceptions\IdempotencyException $e) {
                    // Journal sudah ada — lanjut update status
                } catch (DomainException $e) {
                    return back()->withErrors(['error' => $e->getMessage()]);
                }
            }

            $enrollment->update([
                'status'           => 'expired',
                'remaining_meetings' => 0,
                'payment_status'   => PaymentStatus::FULL->value,
            ]);

            \App\Models\RoomBooking::where('enrollment_id', $enrollment->id)
                ->where('date', '>', now()->toDateString())
                ->delete();

            return back()->with('success', 'Enrollment marked as expired, remaining revenue recognized.');
        });
    }

    public function graduate($id)
    {
        $this->authorize('update', Enrollment::findOrFail($id));

        $enrollment = Enrollment::findOrFail($id);

        if ($enrollment->status !== 'active') {
            return back()->withErrors(['error' => 'Enrollment tidak aktif.']);
        }

        if ($enrollment->remaining_meetings > 0) {
            return back()->withErrors(['error' => "Masih ada {$enrollment->remaining_meetings} meeting tersisa. Gunakan expire jika ingin hanguskan."]);
        }

        $unpaidInstallments = $enrollment->installments()->whereNull('paid_at')->count();
        if ($unpaidInstallments > 0) {
            return back()->withErrors(['error' => "Masih ada {$unpaidInstallments} cicilan belum lunas."]);
        }

        $enrollment->update(['status' => 'graduate']);

        return back()->with('success', 'Student marked as graduate.');
    }

    public function assignTutor(Request $request, $id)
{
    $this->authorize('update', Enrollment::findOrFail($id));

    $request->validate([
        'tutor_id' => 'required|exists:tutors,id',
    ]);

    $enrollment = Enrollment::findOrFail($id);

    if ($enrollment->tutors()->where('tutor_id', $request->tutor_id)->exists()) {
        return back()->withErrors(['error' => 'Tutor sudah di-assign ke enrollment ini.']);
    }

    $enrollment->tutors()->attach($request->tutor_id, ['status' => 'pending']);

    return back()->with('success', 'Tutor assigned.');
}

public function removeTutor(Request $request, $id)
{
    $this->authorize('update', Enrollment::findOrFail($id));

    $request->validate([
        'tutor_id' => 'required|exists:tutors,id',
    ]);

    $enrollment = Enrollment::findOrFail($id);
    $enrollment->tutors()->detach($request->tutor_id);

    return back()->with('success', 'Tutor removed.');
}

public function updateTutorStatus(Request $request, $id, $tutorId)
{
    $this->authorize('update', Enrollment::findOrFail($id));

    $request->validate([
        'status' => 'required|in:pending,confirmed',
    ]);

    $enrollment = Enrollment::findOrFail($id);
    $enrollment->tutors()->updateExistingPivot($tutorId, [
        'status' => $request->status,
    ]);

    return back()->with('success', 'Tutor status updated.');
}

    public function destroy($id)
    {
        $this->authorize('delete', Enrollment::findOrFail($id));

        // TOCTOU fix: previously the journal-exists check ran outside any
        // transaction. A payment journal could be created between the check
        // and the delete, leaving an orphaned payment journal for a deleted
        // enrollment. Lock the enrollment row + re-check inside transaction.
        return DB::transaction(function () use ($id) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($id);

            $hasJournal = \App\Models\Journal::where('reference', 'PAYMENT-ENROLL-' . $enrollment->id)->exists();
            if ($hasJournal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enrollment ini sudah memiliki jurnal pembayaran dan tidak bisa dihapus. Gunakan Expire jika ingin menonaktifkan.',
                ], 422);
            }

            $enrollment->delete();
            return response()->json(['success' => true]);
        });
    }
}
