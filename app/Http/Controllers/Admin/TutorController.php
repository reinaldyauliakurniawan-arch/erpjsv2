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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TutorController extends Controller
{
    public function index()
    {
        // Pagination fix: previously ->get() loaded every tutor into memory.
        // With active + inactive tutors over years of operation, this grows
        // unbounded. Paginated to 20 per page.
        // Sorted alphabetically by tutor name (join to users table — name
        // lives on users, not tutors, so with()/latest() can't sort by it).
        $tutors = Tutor::with('user')
            ->join('users', 'users.id', '=', 'tutors.user_id')
            ->orderBy('users.name', 'asc')
            ->select('tutors.*')
            ->paginate(20);
        $activeTutorCount = Tutor::where('status', 'active')->count();
        return view('admin.tutors.index', compact('tutors', 'activeTutorCount'));
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

        // Atomicity fix: User + Tutor creation must be atomic. If Tutor::create
        // fails, the User would be orphaned with role=tutor but no tutor record.
        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->role = 'tutor';
            $user->save();
            Tutor::create([
                'user_id' => $user->id,
                'persona' => $validated['persona'],
            ]);
        });

        return redirect()->route('admin.tutors.index')->with('success', 'Tutor created successfully.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:users,email,' . Tutor::findOrFail($id)->user_id,
            'persona' => 'required|string|max:255',
            'status'  => 'required|in:active,inactive',
        ]);
        $tutor = Tutor::findOrFail($id);
        $tutor->user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);
        $tutor->update(['persona' => $request->persona, 'status' => $request->status]);
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

        // Atomicity fix: previously delete-then-recreate was 2 separate
        // writes. If any create failed, the tutor's availability was wiped
        // but only partially recreated — leaving the tutor with no slots.
        DB::transaction(function () use ($request, $tutorId) {
            $incomingBlocks = collect($request->availability)->pluck('time_block')->unique()->values()->toArray();

            TutorAvailability::where('tutor_id', $tutorId)
                ->whereIn('time_block', $incomingBlocks)
                ->delete();

            foreach ($request->availability as $slot) {
                TutorAvailability::create([
                    'tutor_id'   => $tutorId,
                    'day'        => $slot['day'],
                    'time_block' => $slot['time_block'],
                    'status'     => $slot['status'] ?? 'available',
                ]);
            }
        });

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

    public function destroy($id)
    {
        // Atomicity fix: previously 6 separate writes (detach x2, delete x3,
        // delete user). If $user->delete() failed, the tutor and all their
        // data was gone but the user record remained as a zombie with role=tutor.
        return DB::transaction(function () use ($id) {
            $tutor = Tutor::lockForUpdate()->findOrFail($id);

            $hasUnpaidAttendance = DB::table('attendance_tutor')
                ->where('tutor_id', $tutor->id)
                ->whereNull('paid_at')
                ->where('pending_rate', false)
                ->where('payable_amount', '>', 0)
                ->exists();

            if ($hasUnpaidAttendance) {
                return redirect()->route('admin.tutors.index')
                    ->with('error', 'Tutor tidak bisa dihapus karena masih ada hutang fee yang belum dibayar. Selesaikan payroll terlebih dahulu.');
            }

            $user  = $tutor->user;
            $tutor->enrollments()->detach();
            $tutor->classSessions()->detach();
            $tutor->availability()->delete();
            $tutor->rates()->delete();
            $tutor->delete();
            $user->delete();
            return redirect()->route('admin.tutors.index')->with('success', 'Tutor berhasil dihapus.');
        });
    }
}
