<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
   public function index()
{
    return view('admin.students.index');
}

public function data(Request $request)
{
    $filter = $request->input('filter', 'all');

    $query = Student::with([
        'user',
        'enrollments.program',
        'enrollments.tutors.user',
        'enrollments.installments',
    ]);

    if ($filter === 'inactive') {
        $query->whereDoesntHave('enrollments', fn($q) => $q->where('status', 'active'));
    } elseif ($filter === 'overdue') {
        $query->whereHas('enrollments.installments', fn($q) => $q->whereNull('paid_at')->where('due_date', '<', now()));
    }

    $students = $query->paginate(50);

    $today = now();

    $rows = $students->map(function ($student) use ($today) {
        $enrollments = $student->enrollments->sortByDesc('id')->map(function ($e) use ($today) {
            $totalMeet = $e->program?->total_meetings ?? 0;
            $remaining = $e->remaining_meetings ?? 0;
            $done      = $totalMeet > 0 ? max(0, $totalMeet - $remaining) : 0;
            $percent   = $totalMeet > 0 ? min(100, round(($done / $totalMeet) * 100)) : 0;

            $installments = $e->installments ?? collect();
            $hasOverdue   = $installments->contains(fn($i) => is_null($i->paid_at) && $i->due_date < $today);
            $hasUnpaid    = $installments->contains(fn($i) => is_null($i->paid_at));

            $paymentStatus = null;
            if ($e->payment_method === 'full upfront') {
                $paymentStatus = 'lunas';
            } elseif ($hasOverdue) {
                $paymentStatus = 'overdue';
            } elseif ($hasUnpaid) {
                $paymentStatus = 'cicilan';
            } else {
                $paymentStatus = 'lunas';
            }

            return [
                'enrollment_id'  => $e->id,
                'program'        => $e->program?->name ?? '—',
                'status'         => $e->status,
                'tutors'         => $e->tutors->map(fn($t) => $t->user->name)->values(),
                'done'           => $done,
                'total_meet'     => $totalMeet,
                'percent'        => $percent,
                'payment_status' => $paymentStatus,
            ];
        })->values();

        return [
            'id'              => $student->id,
            'name'            => $student->user->name,
            'email'           => $student->user->email,
            'initials'        => strtoupper(substr($student->user->name, 0, 2)),
            'notes'           => $student->notes ?? '',
            'education_level' => $student->education_level ?? '',
            'enrollments'     => $enrollments,
        ];
    });

    $summary = [
        'total'    => \App\Models\Student::count(),
        'active'   => \App\Models\Enrollment::where('status', 'active')->distinct('student_id')->count('student_id'),
        'inactive' => \App\Models\Student::whereDoesntHave('enrollments', fn($q) => $q->where('status', 'active'))->count(),
        'overdue'  => \App\Models\Installment::whereNull('paid_at')
            ->where('due_date', '<', $today)
            ->join('enrollments', 'installments.enrollment_id', '=', 'enrollments.id')
            ->distinct('enrollments.student_id')
            ->count('enrollments.student_id'),
    ];

    if ($request->input('summary_only')) {
        return response()->json(['summary' => $summary]);
    }

    return response()->json([
        'data'      => $rows->values(),
        'summary'   => $summary,
        'last_page' => $students->lastPage(),
    ]);
}


    public function show(Student $student)
    {
        $student->load([
            'user',
            'enrollments.program',
            'enrollments.tutors.user',
        ]);

        return view('admin.students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $student->load('user');

        return view('admin.students.edit', compact('student'));
    }

    public function update(Request $request, Student $student)
{
    if ($request->has('password')) {
        $request->validate(['password' => 'required|string|min:8']);
        $student->user->update(['password' => Hash::make($request->password)]);
        return redirect()->route('admin.students.index')->with('success', 'Password berhasil direset.');
    }

    $request->validate([
        'name'            => 'required|string|max:255',
        'email'           => 'required|email|max:255|unique:users,email,' . $student->user_id,
        'notes'           => 'nullable|string',
        'education_level' => 'nullable|in:SD,SMP,SMA,Kuliah,Umum',
    ]);

    $student->user->update([
        'name'  => $request->name,
        'email' => $request->email,
    ]);
    $student->update([
        'notes'           => $request->notes,
        'education_level' => $request->education_level,
    ]);

    return redirect()->route('admin.students.index')->with('success', 'Data student berhasil diupdate.');
}

    public function destroy(Student $student)
    {
        // Atomicity fix: previously 5 separate writes (cascade-delete each
        // enrollment → delete student → delete user). If user->delete() failed
        // (e.g. FK on attendance_tutor.marked_by), the student and all their
        // enrollments were gone but the user record remained as a zombie.
        // Now wrapped in a transaction so all-or-nothing semantics hold.
        return DB::transaction(function () use ($student) {
            $hasActiveEnrollment = $student->enrollments()
                ->whereIn('status', ['active', 'waitlist'])
                ->lockForUpdate()
                ->exists();

            if ($hasActiveEnrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student tidak bisa dihapus karena masih memiliki enrollment aktif. Expire atau graduate enrollment terlebih dahulu.',
                ], 422);
            }

            $hasJournal = \App\Models\Journal::whereIn(
                'reference',
                $student->enrollments()->pluck('id')->map(fn($id) => 'PAYMENT-ENROLL-' . $id)
            )->exists();

            if ($hasJournal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student tidak bisa dihapus karena memiliki riwayat jurnal pembayaran.',
                ], 422);
            }

            $user = $student->user;
            $student->enrollments()->each(fn($e) => $e->delete());
            $student->delete();
            $user->delete();

            return response()->json(['success' => true]);
        });
    }
}
