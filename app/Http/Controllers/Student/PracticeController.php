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
        $student = \App\Models\Student::where('user_id', Auth::id())->firstOrFail();

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
            $practice->pivot_data = $pivot;
            return $practice;
        });

        return view('student.practice.index', compact('practices'));
    }

    public function open(Practice $practice)
    {
        $student = \App\Models\Student::where('user_id', Auth::id())->firstOrFail();

        $pivot = $practice->students()->where('practice_student.student_id', $student->id)->first()?->pivot;

        if ($pivot && !$pivot->opened_at) {
            $practice->students()->updateExistingPivot($student->id, [
                'opened_at' => now(),
                'completion_status' => 'in_progress',
            ]);
        }

        if ($practice->external_link) {
            $url = $practice->external_link;
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            return redirect()->away($url);
        }

        return back();
    }

    public function submit(Request $request, Practice $practice)
    {
        $request->validate([
            'reflection' => 'required|string|min:10',
        ]);

        $student = \App\Models\Student::where('user_id', Auth::id())->firstOrFail();

        $practice->students()->updateExistingPivot($student->id, [
            'reflection'        => $request->reflection,
            'completion_status' => 'completed',
            'completed_at'      => now(),
        ]);

        return back()->with('success', 'Practice berhasil diselesaikan!');
    }
}
