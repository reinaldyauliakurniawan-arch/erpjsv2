<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Program;
use App\Models\Tutor;
use App\Http\Requests\Admin\StoreStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
   public function index()
{
    return view('admin.students.index');
}

public function data(Request $request)
{
    $students = Student::with([
        'user',
        'activeEnrollment.program',
        'activeEnrollment.tutors.user',
        'activeEnrollment.installments',
    ])->get();

    $today = now();

    $rows = $students->map(function ($student) use ($today) {
        $enrollment   = $student->activeEnrollment;
        $installments = $enrollment?->installments ?? collect();
        $totalMeet    = $enrollment?->program?->total_meetings ?? 0;
        $remaining    = $enrollment?->remaining_meetings ?? 0;
        $done         = max(0, $totalMeet - $remaining);
        $percent      = $totalMeet > 0 ? round(($done / $totalMeet) * 100) : 0;
        $isActive     = $enrollment?->status === 'active';
        $hasOverdue   = $installments->contains(fn($i) => is_null($i->paid_at) && $i->due_date < $today);
        $hasUnpaid    = $installments->contains(fn($i) => is_null($i->paid_at));

        $enrollStatus = $isActive ? 'active' : ($enrollment ? $enrollment->status : 'none');

        $paymentStatus = null;
        if ($enrollment) {
            if ($hasOverdue)      $paymentStatus = 'overdue';
            elseif ($hasUnpaid)   $paymentStatus = 'cicilan';
            else                  $paymentStatus = 'lunas';
        }

        $tutors = $enrollment?->tutors->map(fn($t) => $t->user->name)->filter()->values() ?? collect();

        return [
            'id'             => $student->id,
            'name'           => $student->user->name,
            'email'          => $student->user->email,
            'initials'       => strtoupper(substr($student->user->name, 0, 2)),
            'notes'          => $student->notes ?? '',
            'program'        => $enrollment?->program?->name ?? null,
            'tutors'         => $tutors,
            'done'           => $done,
            'total_meet'     => $totalMeet,
            'remaining'      => $remaining,
            'percent'        => $percent,
            'enroll_status'  => $enrollStatus,
            'payment_status' => $paymentStatus,
        ];
    });

    // Filter by tab
    $tab = $request->input('tab', 'all');
    if ($tab === 'active') {
        $rows = $rows->filter(fn($r) => $r['enroll_status'] === 'active');
    } elseif ($tab === 'inactive') {
        $rows = $rows->filter(fn($r) =>
        $r['enroll_status'] !== 'active' ||
        $r['remaining'] === 0
        );
    }

    $summary = [
        'total'   => $students->count(),
        'active'  => $students->filter(fn($s) => $s->activeEnrollment?->status === 'active')->count(),
        'overdue' => $students->filter(function ($s) use ($today) {
            $e = $s->activeEnrollment;
            if (!$e) return false;
            return $e->installments->contains(fn($i) => is_null($i->paid_at) && $i->due_date < $today);
        })->count(),
    ];

    return response()->json([
        'rows'    => $rows->values(),
        'summary' => $summary,
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
        'name'  => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email,' . $student->user_id,
        'notes' => 'nullable|string',
    ]);

    $student->user->update([
        'name'  => $request->name,
        'email' => $request->email,
    ]);
    $student->update(['notes' => $request->notes]);

    return redirect()->route('admin.students.index')->with('success', 'Data student berhasil diupdate.');
}

    public function destroy(Student $student)
    {
        $student->user->delete();
        $student->delete();

        return redirect()->route('admin.students.index')->with('success', 'Student berhasil dihapus.');
    }
}
