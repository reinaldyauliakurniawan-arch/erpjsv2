<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Practice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;

class PracticeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (auth()->user() && auth()->user()->role !== 'tutor') {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $practices = Practice::where('tutor_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
        return view('tutor.practice.index', compact('practices'));
    }

    public function create()
    {
        $tutor = \App\Models\Tutor::where('user_id', Auth::id())->firstOrFail();
        $classes = \App\Models\ClassSession::with(['enrollments.student.user'])
            ->whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id))
            ->where('status', 'active')
            ->get()
            ->map(function ($session) {
                return (object) [
                    'id'         => $session->id,
                    'name'       => $session->name,
                    'class_type' => $session->class_type,
                    'students'   => $session->enrollments
                        ->where('status', 'active')
                        ->map(fn($e) => $e->student ? (object)[
                            'id'   => $e->student->id,
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
            'class_ids'          => 'nullable|array',
            'class_ids.*'        => 'exists:class_sessions,id',
            'student_ids'        => 'nullable|array',
            'student_ids.*'      => 'exists:students,id',
        ]);

        // Atomicity fix: previously Practice::create() + $practice->students()->attach()
        // were 2 separate writes. If attach failed (e.g. invalid student_id), the
        // Practice record was orphaned with no students assigned.
        $tutor = \App\Models\Tutor::where('user_id', Auth::id())->firstOrFail();

        DB::transaction(function () use ($validated, $tutor) {
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
        });

        return redirect()->route('tutor.practice.index')
                         ->with('success', 'Practice berhasil disimpan.');
    }

    public function edit(Practice $practice)
    {
        if ($practice->tutor_id !== Auth::id()) {
            abort(403);
        }
        $tutor = \App\Models\Tutor::where('user_id', Auth::id())->firstOrFail();
        $classes = \App\Models\ClassSession::with(['enrollments.student.user'])
            ->whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id))
            ->where('status', 'active')
            ->get()
            ->map(function ($session) {
                return (object) [
                    'id'         => $session->id,
                    'name'       => $session->name,
                    'class_type' => $session->class_type,
                    'students'   => $session->enrollments
                        ->where('status', 'active')
                        ->map(fn($e) => $e->student ? (object)[
                            'id'   => $e->student->id,
                            'name' => $e->student->user->name ?? '—',
                        ] : null)->filter()->values(),
                ];
            });
        return view('tutor.practice.edit', compact('practice', 'classes'));
    }

    public function update(Request $request, Practice $practice)
    {
        if ($practice->tutor_id !== Auth::id()) {
            abort(403);
        }
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'external_link'      => 'nullable|url',
            'estimated_duration' => 'nullable|integer|min:1',
            'deadline'           => 'nullable|date',
            'status'             => 'required|in:draft,published',
        ]);
        $practice->update($validated);
        return redirect()->route('tutor.practice.index')
                         ->with('success', 'Practice berhasil diperbarui.');
    }
}
