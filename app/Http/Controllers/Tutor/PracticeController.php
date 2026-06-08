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
         = \App\Models\Tutor::where('user_id', Auth::id())->firstOrFail();
         = \App\Models\ClassSession::with(['enrollments.student.user'])
            ->whereHas('tutors', fn() => ->where('tutor_id', ->id))
            ->where('status', 'active')
            ->get()
            ->map(function () {
                return (object) [
                    'id'         => ->id,
                    'name'       => ->name,
                    'class_type' => ->class_type,
                    'students'   => ->enrollments
                        ->where('status', 'active')
                        ->map(fn() => ->student ? (object)[
                            'id'   => ->student->id,
                            'name' => ->student->user->name ?? '—',
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
            'class_ids'          => 'nullable|array',
            'class_ids.*'        => 'exists:class_sessions,id',
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

        $studentIds = collect($validated['student_ids'] ?? []);

        // Auto-assign semua student aktif untuk semi-private & group
        if (!empty($validated['class_ids'])) {
            $sessions = \App\Models\ClassSession::with(['enrollments' => fn($q) => $q->where('status', 'active')])
                ->whereIn('id', $validated['class_ids'])
                ->whereIn('class_type', ['semi-private', 'group'])
                ->get();

            foreach ($sessions as $session) {
                $sessionStudentIds = $session->enrollments->pluck('student_id');
                $studentIds = $studentIds->merge($sessionStudentIds);
            }
        }

        $uniqueIds = $studentIds->unique()->values()->toArray();
        if (!empty($uniqueIds)) {
            $practice->students()->attach($uniqueIds);
        }

        return redirect()->route('tutor.practice.index')
                         ->with('success', 'Practice berhasil disimpan.');
    }
}
