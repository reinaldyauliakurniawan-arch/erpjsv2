<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Practice;
use App\Models\ClassRoom; // sesuaikan model kelas yang ada
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PracticeController extends Controller
{
    public function index()
    {
        $tutor = \App\Models\Tutor::where('user_id', Auth::id())->firstOrFail();
        $practices = Practice::where('tutor_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
        return view('tutor.practice.index', compact('practices'));
    }

    public function create()
    {
        // Ambil kelas yang diajar tutor ini — sesuaikan query dengan relasi yang ada
        $tutor = \App\Models\Tutor::where('user_id', Auth::id())->firstOrFail();
        $classes = \App\Models\ClassSession::with(['enrollments.student.user'])
    ->whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id))
    ->get()
    ->map(function ($session) {
        return (object) [
            'id'       => $session->id,
            'name'     => $session->name,
            'students' => $session->enrollments->map(fn($e) => $e->student ? (object)[
                'id'   => $e->student->user_id,
                'name' => $e->student->user->name ?? '—',
            ] : null)->filter()->values(),
        ];
    });

        return view('tutor.practice.create', compact('classes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'external_link'      => 'nullable|url',
            'estimated_duration' => 'nullable|integer|min:1',
            'deadline'           => 'nullable|date',
            'status'             => 'required|in:draft,published',
            'student_ids'        => 'nullable|array',
            'student_ids.*'      => 'exists:students,id',
        ]);

        $practice = Practice::create([
            'tutor_id'           => Auth::id(),
            'title'              => $validated['title'],
            'description'        => $validated['description'] ?? null,
            'external_link'      => $validated['external_link'] ?? null,
            'estimated_duration' => $validated['estimated_duration'] ?? null,
            'deadline'           => $validated['deadline'] ?? null,
            'status'             => $validated['status'],
        ]);

        if (!empty($validated['student_ids'])) {
            $practice->students()->attach($validated['student_ids']);
        }

        return redirect()->route('tutor.practice.create')
                         ->with('success', 'Practice berhasil disimpan.');
    }
}
