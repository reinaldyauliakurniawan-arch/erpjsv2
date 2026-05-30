<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutorRate;
use App\Models\TutorAvailability;
use App\Models\User;
use App\Models\Program;
use App\Http\Requests\Admin\StoreTutorRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TutorController extends Controller
{
    public function index()
    {
        $tutors = Tutor::with('user')->get();
        return view('admin.tutors.index', compact('tutors'));
    }

    public function show($id)
    {
        $tutor    = Tutor::with(['user', 'rates.program', 'availability'])->findOrFail($id);
        $programs = Program::all();
        return view('admin.tutors.show', compact('tutor', 'programs'));
    }

    public function store(StoreTutorRequest $request)
    {
        $validated = $request->validated();
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => 'tutor',
        ]);
        Tutor::create([
            'user_id' => $user->id,
            'persona' => $validated['persona'],
        ]);
        return redirect()->route('admin.tutors.index')->with('success', 'Tutor created successfully.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:users,email,' . Tutor::findOrFail($id)->user_id,
            'persona' => 'required|string|max:255',
        ]);
        $tutor = Tutor::findOrFail($id);
        $tutor->user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);
        $tutor->update(['persona' => $request->persona]);
        return back()->with('success', 'Tutor updated successfully.');
    }

    public function confirmEnrollment(Request $request, $tutorId)
    {
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
        ]);
        $tutor = Tutor::findOrFail($tutorId);
        $tutor->enrollments()->updateExistingPivot($request->enrollment_id, ['status' => 'confirmed']);
        return back()->with('success', 'Tutor confirmed for this enrollment.');
    }

    public function storeRate(Request $request, $tutorId)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'rate'       => 'required|numeric|min:0',
        ]);
        TutorRate::updateOrCreate(
            ['tutor_id' => $tutorId, 'program_id' => $request->program_id],
            ['rate'     => $request->rate]
        );
        return back()->with('success', 'Tutor rate saved.');
    }

    public function storeAvailability(Request $request, $tutorId)
    {
        $request->validate([
            'availability'              => 'required|array',
            'availability.*.day'        => 'required|string',
            'availability.*.time_block' => 'required|string',
            'availability.*.status'     => 'sometimes|in:available,not_available,occupied',
        ]);

        $hardcodedBlocks = ['09:00-10:30', '10:30-12:00', '13:00-14:30', '14:30-16:00', '16:00-17:30', '18:30-20:00'];

        TutorAvailability::where('tutor_id', $tutorId)
            ->whereIn('time_block', $hardcodedBlocks)
            ->delete();

        foreach ($request->availability as $slot) {
            TutorAvailability::create([
                'tutor_id'   => $tutorId,
                'day'        => strtolower($slot['day']),
                'time_block' => $slot['time_block'],
                'status'     => $slot['status'] ?? 'available',
            ]);
        }
        return back()->with('success', 'Availability updated.');
    }

    public function storeCustomAvailability(Request $request, $tutorId)
    {
        $request->validate([
            'day'        => 'required|string',
            'time_block' => 'required|string',
            'status'     => 'required|in:available,not_available,occupied',
        ]);

        TutorAvailability::updateOrCreate(
            ['tutor_id' => $tutorId, 'day' => strtolower($request->day), 'time_block' => $request->time_block],
            ['status' => $request->status]
        );

        return back()->with('success', 'Custom slot added.');
    }

    public function destroyAvailability($tutorId, $availabilityId)
    {
        TutorAvailability::where('tutor_id', $tutorId)->where('id', $availabilityId)->delete();
        return back()->with('success', 'Slot removed.');
    }
}
