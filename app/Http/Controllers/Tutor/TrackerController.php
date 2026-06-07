<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Practice;
use Carbon\Carbon;

class TrackerController extends Controller
{
    public function index()
    {
        $period = request('period', 'week');
        $classFilter = request('class');

        $tutor = \App\Models\Tutor::where('user_id', auth()->id())->firstOrFail();

        $sessions = ClassSession::with([
            'enrollments.student.user',
        ])->whereHas('tutors', fn($q) => $q->where('tutor_id', $tutor->id))->get();

        // Ambil practices milik tutor ini saja
        $practices = Practice::with([
            'students.student.user',
        ])->where('status', 'published')
          ->where('tutor_id', auth()->id())
          ->get();

        // Group data per class_session
        $classes = $sessions->map(function ($session) use ($practices, $period) {
            $students = $session->enrollments->map(function ($enrollment) use ($practices, $period) {
                $student = $enrollment->student;
                if (!$student) return null;

                // Practices assigned ke student ini
                $assignedPractices = $practices->map(function ($practice) use ($student, $period) {
                    $pivot = $practice->students
                        ->firstWhere('id', $student->id);

                    if (!$pivot) return null;

                    $p = $pivot->pivot;

                    // Filter by period
                    if ($period === 'week') {
                        $startOfWeek = Carbon::now()->startOfWeek();
                        if ($practice->created_at < $startOfWeek) return null;
                    }

                    return [
                        'id'                => $practice->id,
                        'title'             => $practice->title,
                        'estimated_duration'=> $practice->estimated_duration,
                        'deadline'          => $practice->deadline,
                        'completion_status' => $p->completion_status,
                        'opened_at'         => $p->opened_at,
                        'completed_at'      => $p->completed_at,
                        'reflection'        => $p->reflection,
                    ];
                })->filter()->values();

                $totalMinutes = $assignedPractices
                    ->where('completion_status', 'completed')
                    ->sum('estimated_duration');

                $weeklyTarget = 60;
                $percentage = $weeklyTarget > 0 ? min(round(($totalMinutes / $weeklyTarget) * 100), 999) : 0;

                return [
                    'id'            => $student->id,
                    'name'          => $student->user->name ?? '—',
                    'practices'     => $assignedPractices,
                    'total_minutes' => $totalMinutes,
                    'percentage'    => $percentage,
                    'target_met'    => $totalMinutes >= $weeklyTarget,
                ];
            })->filter()->values();

            return [
                'id'       => $session->id,
                'name'     => $session->name,
                'students' => $students,
            ];
        });

        if ($classFilter) {
            $classes = $classes->where('id', $classFilter)->values();
        }

        return view('tutor.tracker.index', compact('classes', 'period', 'sessions', 'classFilter'));
    }
}
