<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Practice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PracticeController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $practices = Practice::with(['students' => function ($q) use ($userId) {
            $q->wherePivot('student_id', $userId);
        }])
        ->whereHas('students', function ($q) use ($userId) {
            $q->where('practice_student.student_id', $userId);
        })
        ->where('status', 'published')
        ->orderBy('deadline')
        ->get()
        ->map(function ($practice) {
            $pivot = $practice->students->first()?->pivot;
            $practice->pivot_data = $pivot;
            return $practice;
        });

        return view('student.practice.index', compact('practices'));
    }

    public function open(Practice $practice)
    {
        $userId = Auth::id();

        $pivot = $practice->students()->where('practice_student.student_id', $userId)->first()?->pivot;

        if ($pivot && !$pivot->opened_at) {
            $practice->students()->updateExistingPivot($userId, [
                'opened_at' => now(),
            ]);
        }

        if ($practice->external_link) {
            return redirect($practice->external_link);
        }

        return back();
    }

    public function submit(Request $request, Practice $practice)
    {
        $request->validate([
            'reflection' => 'required|string|min:10',
        ]);

        $userId = Auth::id();

        $practice->students()->updateExistingPivot($userId, [
            'reflection'        => $request->reflection,
            'completion_status' => 'completed',
            'completed_at'      => now(),
        ]);

        return back()->with('success', 'Practice berhasil diselesaikan!');
    }
}
