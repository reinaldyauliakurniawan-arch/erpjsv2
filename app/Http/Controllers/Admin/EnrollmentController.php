<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountCode;
use App\Enums\ClassType;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Exceptions\IdempotencyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEnrollmentRequest;
use App\Http\Requests\Admin\UpdateEnrollmentRequest;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Journal;
use App\Models\Program;
use App\Models\RoomBooking;
use App\Models\Student;
use App\Models\Tutor;
use App\Services\AccountingService;
use App\Services\EnrollmentService;
use App\Services\RevenueRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    protected $enrollmentService;

    protected $accountingService;

    protected $revenueRecognitionService;

    public function __construct(EnrollmentService $enrollmentService, AccountingService $accountingService, RevenueRecognitionService $revenueRecognitionService)
    {
        $this->enrollmentService = $enrollmentService;
        $this->accountingService = $accountingService;
        $this->revenueRecognitionService = $revenueRecognitionService;
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
            $query->where(function ($q) use ($search) {
                $q->whereHas('student.user', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('program', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $page = max(1, (int) $request->input('page', 1));
        $size = (int) $request->input('size', 20);
        $total = $query->count();
        $items = $query->latest()->skip(($page - 1) * $size)->take($size)->get();

        return response()->json([
            'last_page' => ceil($total / $size),
            'data' => $items->map(fn ($e) => [
                'id' => $e->id,
                'student' => $e->student->user->name,
                'program' => $e->program->name,
                'class_session' => $e->classSession?->name ?? '-',
                'payment_status' => $e->payment_status,
                'status' => $e->status,
                'remaining' => $e->remaining_meetings,
                'show_url' => route('admin.enrollments.show', $e->id),
                'edit_url' => route('admin.enrollments.edit', $e->id),
                'delete_url' => route('admin.enrollments.destroy', $e->id),
            ]),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Enrollment::class);

        $programs = Program::all();
        $classrooms = Classroom::all();
        $classSessions = ClassSession::with(['program', 'schedules.classroom'])
            ->withCount(['enrollments as enrollments_count' => fn ($q) => $q->whereIn('status', ['active', 'waitlist'])])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $students = Student::with('user')->orderBy('created_at', 'desc')->get();

        return view('admin.enrollments.create', compact('programs', 'classrooms', 'classSessions', 'students'));
    }

    public function store(StoreEnrollmentRequest $request)
    {
        $this->authorize('create', Enrollment::class);

        try {
            [$enrollment, $roomNotes] = $this->enrollmentService->enroll($request->validated());
            $successMsg = 'Student enrolled successfully.';
            if (! empty($roomNotes)) {
                $successMsg .= ' Catatan ruangan: '.implode(' | ', $roomNotes);
            }

            return redirect()->route('admin.enrollments.index')->with('success', $successMsg);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function edit($id)
    {
        $enrollment = Enrollment::with(['student.user', 'program', 'classSession', 'installments', 'tutors.user'])
            ->findOrFail($id);
        $this->authorize('update', $enrollment);

        $programs = Program::orderBy('name')->get();
        $classSessions = ClassSession::where('program_id', $enrollment->program_id)->orderBy('name')->get();
        $students = Student::with('user')
            ->get()
            ->sortBy(fn ($s) => $s->user?->name)
            ->values();

        // Dependency guard info untuk UI: kalau revenue sudah diakui via
        // attendance (fase 4), sebagian field dikunci.
        $recognizedMeetings = DB::table('attendance_student')->where('enrollment_id', $enrollment->id)->count();
        // Cicilan yang sudah punya jurnal pembayaran resmi (INSTALLMENT-<id>)
        // tidak boleh dihapus dari form ini.
        $lockedInstallmentIds = Journal::where('reference', 'like', 'INSTALLMENT-%')
            ->pluck('reference')
            ->map(fn ($r) => (int) str_replace('INSTALLMENT-', '', $r))
            ->intersect($enrollment->installments->pluck('id'))
            ->values();
        $hasEnrollPaymentJournal = Journal::where('reference', 'PAYMENT-ENROLL-'.$enrollment->id)->exists();

        return view('admin.enrollments.edit', compact(
            'enrollment', 'programs', 'classSessions', 'students',
            'recognizedMeetings', 'lockedInstallmentIds', 'hasEnrollPaymentJournal'
        ));
    }

    public function update(UpdateEnrollmentRequest $request, $id)
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($id, $data) {
                $enrollment = Enrollment::with('program')->lockForUpdate()->findOrFail($id);
                Installment::where('enrollment_id', $id)->lockForUpdate()->get();

                $recognized = DB::table('attendance_student')->where('enrollment_id', $id)->count();

                // Guard dependency akuntansi: begitu ada pertemuan yang revenue-nya
                // sudah diakui, mengubah tarif revenue-per-meeting (total_amount /
                // program) atau atribusi siswa bikin buku tidak konsisten. Blokir
                // di sini — koreksi lewat jurnal penyesuaian.
                if ($recognized > 0) {
                    $locked = [];
                    if ((int) $data['program_id'] !== (int) $enrollment->program_id) {
                        $locked[] = 'program';
                    }
                    if (bccomp((string) $data['total_amount'], (string) $enrollment->total_amount, 2) !== 0) {
                        $locked[] = 'total biaya';
                    }
                    if ((int) $data['student_id'] !== (int) $enrollment->student_id) {
                        $locked[] = 'siswa';
                    }
                    if ($locked) {
                        throw new DomainException(
                            "Enrollment ini sudah punya {$recognized} pertemuan yang revenue-nya diakui. "
                            .'Tidak bisa mengubah: '.implode(', ', $locked).'. '
                            .'Gunakan jurnal penyesuaian untuk koreksi nilai.'
                        );
                    }
                }

                // --- Rekonsiliasi baris cicilan ---
                $submitted = collect($data['installments'] ?? []);
                $submittedIds = $submitted->pluck('id')->filter()->map(fn ($v) => (int) $v)->all();

                $toDelete = Installment::where('enrollment_id', $id)
                    ->when($data['payment_method'] === 'installment', fn ($q) => $q->whereNotIn('id', $submittedIds))
                    ->get();
                foreach ($toDelete as $inst) {
                    if (Journal::where('reference', 'INSTALLMENT-'.$inst->id)->exists()) {
                        throw new DomainException(
                            "Cicilan #{$inst->id} sudah punya jurnal pembayaran resmi dan tidak bisa dihapus dari sini. "
                            .'Gunakan flow refund yang proper.'
                        );
                    }
                    $inst->delete();
                }

                if ($data['payment_method'] === 'installment') {
                    foreach ($submitted as $row) {
                        $attrs = [
                            'amount' => $row['amount'],
                            'due_date' => $row['due_date'],
                            'payment_channel' => $row['payment_channel'] ?? $data['payment_channel'],
                        ];
                        $markPaid = ! empty($row['paid']);

                        if (! empty($row['id'])) {
                            $inst = Installment::where('enrollment_id', $id)->find($row['id']);
                            if (! $inst) {
                                continue;
                            }
                            // Pertahankan timestamp paid_at asli kalau statusnya tidak berubah.
                            if ($markPaid) {
                                $attrs['paid_at'] = $inst->paid_at ?: ($row['due_date']);
                            } else {
                                if ($inst->paid_at && Journal::where('reference', 'INSTALLMENT-'.$inst->id)->exists()) {
                                    throw new DomainException(
                                        "Cicilan #{$inst->id} punya jurnal pembayaran resmi — tidak bisa ditandai belum lunas dari sini."
                                    );
                                }
                                $attrs['paid_at'] = null;
                            }
                            $inst->update($attrs);
                        } else {
                            $attrs['paid_at'] = $markPaid ? ($row['due_date']) : null;
                            Installment::create(['enrollment_id' => $id] + $attrs);
                        }
                    }
                }

                $enrollment->update([
                    'student_id' => $data['student_id'],
                    'program_id' => $data['program_id'],
                    'class_session_id' => $data['class_session_id'] ?? null,
                    'enrollment_date' => $data['enrollment_date'],
                    'expiry_date' => $data['expiry_date'],
                    'payment_method' => $data['payment_method'],
                    'payment_channel' => $data['payment_channel'],
                    'total_amount' => $data['total_amount'],
                    'payment_status' => $data['payment_status'],
                    'status' => $data['status'],
                    'remaining_meetings' => $data['remaining_meetings'],
                ]);
            });
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.enrollments.show', $id)->with('success', 'Enrollment berhasil diperbarui.');
    }

    public function searchStudents(Request $request)
    {
        $this->authorize('viewAny', Enrollment::class);

        $q = $request->input('q', '');

        // N+1 fix: previously the enrollments query ran inside ->map() per
        // student (1 query × 10 students = 10 extra queries). Now we eager-load
        // the latest 3 enrollments + program in a single relation query.
        $students = Student::with([
            'user',
            'enrollments' => fn ($query) => $query->with('program')->latest()->take(3),
        ])
            ->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"))
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->user->name,
                'email' => $s->user->email,
                'phone' => $s->user->phone,
                'enrollments' => $s->enrollments->map(fn ($e) => [
                    'program' => $e->program->name,
                    'status' => $e->status,
                ]),
            ]);

        return response()->json($students);
    }

    public function eligibleSessions(Request $request)
    {
        $this->authorize('viewAny', Enrollment::class);

        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'day' => 'nullable|string',
            'time_block' => 'nullable|string',
            'q' => 'nullable|string',
        ]);

        $programId = $request->program_id;
        $day = $request->day;
        $timeBlock = $request->time_block;
        $search = $request->q;
        $program = Program::find($programId);
        $isPrivate = $program && $program->type === ClassType::PRIVATE->value;

        // Selalu tampilkan semua class session milik program ini (jenis program
        // otomatis konsisten lewat program_id). Hari & time block, kalau diisi,
        // cuma jadi filter tambahan opsional — bukan syarat wajib — supaya admin
        // bisa lihat & pilih sesi yang sudah ada tanpa harus isi hari/jam dulu.
        $query = ClassSession::with(['schedules.classroom', 'tutors.user'])
            ->withCount([
                'enrollments as active_count' => fn ($q) => $q->whereIn('status', ['active', 'waitlist']),
            ])
            ->where('program_id', $programId)
            ->where('status', 'active');

        if (! $isPrivate && $day && $timeBlock) {
            $query->whereHas('schedules', fn ($q) => $q->where('day', $day)->where('time_block', $timeBlock));
        }

        if ($search) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $sessions = $query->limit(20)->get();

        // Single query for all sessions' finished-meeting counts
        $sessionIds = $sessions->pluck('id')->all();
        $finishedMap = Attendance::whereIn('class_session_id', $sessionIds)
            ->selectRaw('class_session_id, COUNT(DISTINCT date) as finished')
            ->groupBy('class_session_id')
            ->pluck('finished', 'class_session_id');

        $result = $sessions->map(function ($session) use ($day, $timeBlock, $finishedMap, $isPrivate) {
            $activeCount = $session->active_count;
            $finished = $finishedMap->get($session->id, 0);
            $schedule = $session->schedules->first();
            $capacity = $schedule?->classroom?->capacity ?? 999;

            if (! $isPrivate && $activeCount >= $capacity) {
                return null;
            }
            if (! $isPrivate && $finished > 8) {
                return null;
            }

            return [
                'id' => $session->id,
                'name' => $session->name,
                'day' => $schedule?->day ?? $day,
                'time_block' => $schedule?->time_block ?? $timeBlock,
                'classroom' => $schedule?->classroom?->name,
                'classroom_id' => $schedule?->classroom_id,
                'capacity' => $schedule?->classroom?->capacity,
                'enrolled_count' => $activeCount,
                'finished_meetings' => $finished,
                'tutors' => $session->tutors->map(fn ($t) => [
                    'id' => $t->id,
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

        $day = $request->input('day');
        $timeBlock = $request->input('time_block');

        $tutors = Tutor::with('user')
            ->when($day && $timeBlock, function ($q) use ($day, $timeBlock) {
                // Exclude tutor yang sudah ada di sesi lain pada slot yang sama
                $q->whereHas('availability', fn ($q2) => $q2->where('day', $day)->where('time_block', $timeBlock))
                    ->whereDoesntHave('classSessions', function ($q2) use ($day, $timeBlock) {
                        $q2->whereHas('schedules', fn ($q3) => $q3->where('day', $day)->where('time_block', $timeBlock));
                    });
            })
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
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

        $availableTutors = Tutor::with('user')
            ->whereDoesntHave('enrollments', function ($q) use ($id) {
                $q->where('enrollment_id', $id);
            })
            ->get();

        return view('admin.enrollments.show', compact('enrollment', 'availableTutors'));
    }

    public function markInstallmentPaid(Request $request, $enrollmentId, $installmentId)
    {
        $this->authorize('update', Enrollment::findOrFail($enrollmentId));

        // Race-condition fix: ALL lockForUpdate() calls must be INSIDE the
        // DB transaction. Previously they were outside, so the locks released
        // immediately when each SELECT completed. Two concurrent requests
        // paying different installments of the same enrollment could both
        // count "1 unpaid" and both write `payment_status=partial`, corrupting
        // the final `payment_status` (should be `full`).
        return DB::transaction(function () use ($enrollmentId, $installmentId) {
            // Lock the enrollment + the target installment + all sibling installments
            $enrollment = Enrollment::with('program')->lockForUpdate()->findOrFail($enrollmentId);

            $installment = Installment::where('id', $installmentId)
                ->where('enrollment_id', $enrollmentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($installment->paid_at) {
                return back()->withErrors(['error' => 'Installment sudah dibayar sebelumnya.']);
            }

            // Lock all sibling installments so the unpaid-count below is consistent
            Installment::where('enrollment_id', $enrollmentId)->lockForUpdate()->get();

            // GAP #14 fix: kalau ada meeting yang sudah diabsen & revenue-nya
            // sudah diakui SEBELUM pembayaran ini masuk (siswa nunggak lalu
            // bayar belakangan), uang yang masuk sekarang melunasi Piutang itu
            // dulu. Sisanya (kalau ada, untuk meeting-meeting masa depan)
            // masuk ke Deferred Revenue seperti biasa. Dihitung SEBELUM
            // installment ini di-mark paid_at, supaya outstandingReceivable()
            // belum menghitung pembayaran yang sedang diproses ini sendiri.
            $split = $this->revenueRecognitionService->splitForNewPayment($enrollment, (string) $installment->amount);

            $cashAccount = $installment->payment_channel === 'bank' ? AccountCode::BANK->value : AccountCode::CASH->value;
            $journalItems = [
                ['account_code' => $cashAccount, 'debit' => $installment->amount, 'credit' => 0],
            ];
            if (bccomp($split['toReceivable'], '0', 2) > 0) {
                $journalItems[] = ['account_code' => AccountCode::ACCOUNTS_RECEIVABLE->value, 'debit' => 0, 'credit' => $split['toReceivable']];
            }
            if (bccomp($split['toDeferredRevenue'], '0', 2) > 0) {
                $journalItems[] = ['account_code' => AccountCode::DEFERRED_REVENUE->value, 'debit' => 0, 'credit' => $split['toDeferredRevenue']];
            }

            $this->accountingService->createJournal(
                now()->toDateString(),
                "Installment Payment - Enrollment #{$enrollmentId}",
                "INSTALLMENT-{$installmentId}",
                $journalItems
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

            return back()->with('success', 'Installment marked as paid.');
        });
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

            // GAP #14 fix: write off saldo Deferred Revenue yang BENAR-BENAR
            // tersedia sekarang (totalPaid - totalRevenueRecognizedSoFar),
            // bukan remaining_meetings * (paidAmount lama / total_meetings).
            // Formula lama mengasumsikan semua sisa meeting akan dibayar
            // lunas dengan rate paidAmount SAAT ITU — salah untuk kontrak
            // installment yang paidAmount-nya berubah-ubah. Kalau ada
            // Piutang outstanding (siswa nunggak, revenue sudah diakui
            // duluan), itu TIDAK di-write-off di sini — itu tetap piutang
            // yang harus ditagih terpisah, bukan bagian dari Deferred Revenue.
            $remainingDeferred = $this->revenueRecognitionService->availableDeferredRevenue($enrollment);

            if (bccomp($remainingDeferred, '0', 2) > 0) {
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
                } catch (IdempotencyException $e) {
                    // Journal sudah ada — lanjut update status
                }
                // Note: DomainException is intentionally NOT caught here.
                // Previously, catching it and returning back()->withErrors()
                // from inside the transaction callback caused Laravel to COMMIT
                // the transaction (return = success), leaving partial writes
                // (e.g. journal rows) committed while the enrollment status
                // was NOT updated — half-state. Now we let DomainException
                // bubble up and roll back the entire transaction.
            }

            $enrollment->update([
                'status' => 'expired',
                'remaining_meetings' => 0,
                'payment_status' => PaymentStatus::FULL->value,
            ]);

            RoomBooking::where('enrollment_id', $enrollment->id)
                ->where('date', '>', now()->toDateString())
                ->delete();

            return back()->with('success', 'Enrollment marked as expired, remaining revenue recognized.');
        });
    }

    public function graduate($id)
    {
        $this->authorize('update', Enrollment::findOrFail($id));

        // TOCTOU fix: previously check-then-update with no transaction/lock.
        // Between the check and the update, a new attendance could decrement
        // remaining_meetings, or a new installment could be marked unpaid.
        // Now wrapped in transaction with lockForUpdate.
        return DB::transaction(function () use ($id) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($id);

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
        });
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

            $hasJournal = Journal::where('reference', 'PAYMENT-ENROLL-'.$enrollment->id)->exists();
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
