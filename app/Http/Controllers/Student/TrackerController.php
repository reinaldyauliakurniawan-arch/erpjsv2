<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Practice;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TrackerController extends Controller
{
    public function index()
    {
        $userId  = Auth::id();
        $student = Student::where('user_id', $userId)->firstOrFail();

        $practices = Practice::with(['students' => function ($q) use ($student) {
            $q->wherePivot('student_id', $student->id);
        }])
        ->whereHas('students', function ($q) use ($student) {
            $q->where('practice_student.student_id', $student->id);
        })
        ->where('status', 'published')
        ->orderBy('deadline')
        ->get()
        ->map(function ($practice) {
            $pivot = $practice->students->first()?->pivot;
            return [
                'id'                 => $practice->id,
                'title'              => $practice->title,
                'estimated_duration' => $practice->estimated_duration ?? 0,
                'deadline'           => $practice->deadline,
                'completion_status'  => $pivot?->completion_status ?? 'not_started',
                'opened_at'          => $pivot?->opened_at,
                'completed_at'       => $pivot?->completed_at,
            ];
        });

        $weeklyTarget = 60; // menit
        $startOfWeek  = Carbon::now()->startOfWeek();

        $weeklyMinutes = $practices
            ->filter(fn($p) => $p['completion_status'] === 'completed'
                && $p['completed_at']
                && Carbon::parse($p['completed_at'])->gte($startOfWeek))
            ->sum('estimated_duration');

        $totalMinutes = $practices
            ->where('completion_status', 'completed')
            ->sum('estimated_duration');

        $weeklyPct = $weeklyTarget > 0 ? min(round(($weeklyMinutes / $weeklyTarget) * 100), 100) : 0;

        return view('student.tracker.index', compact(
            'practices', 'weeklyMinutes', 'weeklyTarget',
            'weeklyPct', 'totalMinutes'
        ));
    }
}
