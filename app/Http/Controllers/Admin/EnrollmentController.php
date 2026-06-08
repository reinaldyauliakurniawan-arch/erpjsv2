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

class EnrollmentController extends Controller
{
    protected $enrollmentService;
    protected $accountingService;

    public function __construct(EnrollmentService $enrollmentService, AccountingService $accountingService)
    {
        $this->enrollmentService = $enrollmentService;
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $enrollments = Enrollment::with(['student.user', 'program', 'classSession'])->get();
        return view('admin.enrollments.index', compact('enrollments'));
    }

    public function data(Request $request)
{
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
    $q = $request->input('q', '');
    $students = \App\Models\Student::with('user')
        ->whereHas('user', fn($query) => $query->where('name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%"))
        ->limit(10)
        ->get()
        ->map(fn($s) => [
            'id'    => $s->id,
            'name'  => $s->user->name,
            'email' => $s->user->email,
            'phone' => $s->user->phone,
            'enrollments' => $s->enrollments()->with('program')->latest()->take(3)->get()->map(fn($e) => [
                'program' => $e->program->name,
                'status'  => $e->status,
            ]),
        ]);

    return response()->json($students);
}

public function eligibleSessions(Request $request)
{
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

    $sessions = \App\Models\ClassSession::with(['schedules.classroom', 'tutors.user'])
        ->where('program_id', $programId)
        ->where('status', 'active')
        ->whereHas('schedules', fn($q) => $q->where('day', $day)->where('time_block', $timeBlock))
        ->get()
        ->map(function ($session) use ($day, $timeBlock) {
            $activeCount = $session->enrollments()->whereIn('status', ['active', 'waitlist'])->count();
            $finished    = \App\Models\Attendance::where('class_session_id', $session->id)->distinct('date')->count('date');
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

    return response()->json($sessions);
}

public function availableTutors(Request $request)
{
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
        $installment = Installment::where('id', $installmentId)
            ->where('enrollment_id', $enrollmentId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($installment->paid_at) {
            return back()->withErrors(['error' => 'Installment sudah dibayar sebelumnya.']);
        }

        $enrollment = $installment->enrollment;

        DB::transaction(function () use ($installment, $enrollment, $enrollmentId, $installmentId) {
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
        $enrollment = Enrollment::with(['student.user', 'program'])->findOrFail($id);

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
    }

    public function graduate($id)
    {
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
    $request->validate([
        'tutor_id' => 'required|exists:tutors,id',
    ]);

    $enrollment = Enrollment::findOrFail($id);
    $enrollment->tutors()->detach($request->tutor_id);

    return back()->with('success', 'Tutor removed.');
}

public function updateTutorStatus(Request $request, $id, $tutorId)
{
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
    $enrollment = Enrollment::findOrFail($id);

    $hasJournal = \App\Models\Journal::where('reference', 'PAYMENT-ENROLL-' . $enrollment->id)->exists();
    if ($hasJournal) {
        return response()->json([
            'success' => false,
            'message' => 'Enrollment ini sudah memiliki jurnal pembayaran dan tidak bisa dihapus. Gunakan Expire jika ingin menonaktifkan.',
        ], 422);
    }

    $enrollment->delete();
    return response()->json(['success' => true]);
}
}
