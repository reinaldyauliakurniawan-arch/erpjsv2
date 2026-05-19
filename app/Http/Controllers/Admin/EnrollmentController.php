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
        $query->whereHas('student.user', fn($q) => $q->where('name', 'like', "%{$search}%"))
              ->orWhereHas('program', fn($q) => $q->where('name', 'like', "%{$search}%"));
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
        ]),
    ]);
}

    public function create()
    {
        $programs      = Program::all();
        $classrooms    = Classroom::all();
        $classSessions = ClassSession::with('program')
            ->withCount(['enrollments as filled_count' => function ($q) {
                $q->whereIn('status', ['active', 'waitlist']);
            }])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $students = \App\Models\Student::with('user')->orderBy('created_at', 'desc')->get();
        return view('admin.enrollments.create', compact('programs', 'classrooms', 'classSessions', 'students'));
    }

    public function store(StoreEnrollmentRequest $request)
    {
        try {
            $this->enrollmentService->enroll($request->validated());
            return redirect()->route('admin.enrollments.index')->with('success', 'Student enrolled successfully.');
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
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
                    ['account_code' => AccountCode::CASH_BANK->value,        'debit' => $installment->amount, 'credit' => 0],
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

        $perMeetingPrice   = bcdiv($enrollment->total_amount, $enrollment->program->total_meetings, 2);
        $remainingDeferred = bcmul($enrollment->remaining_meetings, $perMeetingPrice, 2);

        if ($remainingDeferred > 0) {
            try {
                $this->accountingService->createJournal(
                    now()->toDateString(),
                    "Manual Expiry - Student {$enrollment->student->user->name}",
                    "MANUAL-EXPIRY-{$enrollment->id}",
                    [
                        ['account_code' => AccountCode::DEFERRED_REVENUE->value,     'debit' => $remainingDeferred, 'credit' => 0],
                        ['account_code' => AccountCode::REVENUE_TUITION_FEES->value, 'debit' => 0, 'credit' => $remainingDeferred],
                    ]
                );
            } catch (DomainException $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        }

        $enrollment->update(['status' => 'expired', 'remaining_meetings' => 0]);

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
}
