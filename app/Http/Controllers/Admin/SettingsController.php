<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tutor;
use App\Models\Student;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function index()
    {
        $users = User::orderBy('role')->orderBy('name')->get();
        return view('admin.settings.index', compact('users'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,cfo,tutor,student',
            'phone'    => 'nullable|string|max:20',
        ]);

        // Atomicity fix: previously User::create() + Tutor::create()/Student::create()
        // were 2 separate writes with no transaction. If the second failed (e.g. FK
        // constraint), the User was orphaned with role=tutor/student but no profile record.
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'phone'    => $request->phone,
            ]);
            // Set role explicitly (not mass-assignable per User model security)
            $user->role = $request->role;
            $user->save();

            if ($request->role === 'tutor') {
                Tutor::create(['user_id' => $user->id, 'persona' => null]);
            } elseif ($request->role === 'student') {
                Student::create(['user_id' => $user->id, 'notes' => null]);
            }
        });

        return redirect()->route('admin.settings.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role'  => 'required|in:admin,cfo,tutor,student',
            'phone' => 'nullable|string|max:20',
        ]);

        $oldRole = $user->role;
        $newRole = $request->role;

        // Race-condition + ordering fix: previously the unpaid-fee guard
        // ran AFTER $user->update(), so if the guard fired the redirect
        // returned an error but the user's role was already persisted as
        // non-tutor — leaving the user stuck (can't access tutor routes,
        // but the tutor record + unpaid attendance remained).
        //
        // Fix: run the guard BEFORE any write, and wrap everything in a
        // transaction so role change + cleanup + new profile record are atomic.
        if ($oldRole === 'tutor' && $newRole !== 'tutor' && $user->tutor) {
            $hasUnpaidAttendance = DB::table('attendance_tutor')
                ->where('tutor_id', $user->tutor->id)
                ->whereNull('paid_at')
                ->where('pending_rate', false)
                ->where('payable_amount', '>', 0)
                ->exists();

            if ($hasUnpaidAttendance) {
                return redirect()->route('admin.settings.index')
                    ->with('error', 'Role tidak bisa diubah karena tutor masih memiliki hutang fee yang belum dibayar.');
            }
        }

        DB::transaction(function () use ($request, $user, $oldRole, $newRole) {
            $user->update([
                'name'  => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);
            // Set role explicitly (not mass-assignable per User model security)
            $user->role = $newRole;
            $user->save();

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            // Cleanup record lama jika role berubah
            if ($oldRole === 'tutor' && $newRole !== 'tutor' && $user->tutor) {
                $user->tutor->enrollments()->detach();
                $user->tutor->classSessions()->detach();
                $user->tutor->availability()->delete();
                $user->tutor->rates()->delete();
                $user->tutor->delete();
            } elseif ($oldRole === 'student' && $newRole !== 'student' && $user->student) {
                $user->student->enrollments()->each(fn($e) => $e->delete());
                $user->student->delete();
            }

            // Buat record tutor/student jika role berubah ke tutor/student
            if ($newRole === 'tutor' && !$user->fresh()->tutor) {
                Tutor::create(['user_id' => $user->id, 'persona' => null]);
            } elseif ($newRole === 'student' && !$user->fresh()->student) {
                Student::create(['user_id' => $user->id, 'notes' => null]);
            }
        });

        return redirect()->route('admin.settings.index')->with('success', 'User berhasil diupdate.');
    }

    public function destroyUser(User $user)
    {
        // Atomicity fix: previously multi-table cascade (detach + delete +
        // delete user) was not wrapped in a transaction. If $user->delete()
        // failed, the tutor/student + all their data was gone but the user
        // record remained as a zombie.
        return DB::transaction(function () use ($user) {
            if ($user->role === 'tutor') {
                $tutor = $user->tutor;
                if ($tutor) {
                    $hasUnpaidAttendance = DB::table('attendance_tutor')
                        ->where('tutor_id', $tutor->id)
                        ->whereNull('paid_at')
                        ->where('pending_rate', false)
                        ->where('payable_amount', '>', 0)
                        ->exists();

                    if ($hasUnpaidAttendance) {
                        return redirect()->route('admin.settings.index')
                            ->with('error', 'User tidak bisa dihapus karena tutor masih memiliki hutang fee yang belum dibayar.');
                    }

                    $tutor->enrollments()->detach();
                    $tutor->classSessions()->detach();
                    $tutor->availability()->delete();
                    $tutor->rates()->delete();
                    $tutor->delete();
                }
            } elseif ($user->role === 'student') {
                $student = $user->student;
                if ($student) {
                    $hasActiveEnrollment = $student->enrollments()
                        ->whereIn('status', ['active', 'waitlist'])
                        ->exists();

                    if ($hasActiveEnrollment) {
                        return redirect()->route('admin.settings.index')
                            ->with('error', 'User tidak bisa dihapus karena student masih memiliki enrollment aktif.');
                    }

                    $student->enrollments()->each(fn($e) => $e->delete());
                    $student->delete();
                }
            }

            $user->delete();
            return redirect()->route('admin.settings.index')->with('success', 'User berhasil dihapus.');
        });
    }

    public function colors()
{
    return view('admin.settings.colors', [
        'color_primary'   => Setting::get('color_primary', '#065f46'),
        'color_secondary' => Setting::get('color_secondary', '#059669'),
        'color_sidebar'   => Setting::get('color_sidebar', '#065f46'),
    ]);
}

public function updateColors(Request $request)
{
    $request->validate([
        'color_primary'   => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        'color_secondary' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        'color_sidebar'   => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
    ]);

    Setting::set('color_primary',   $request->color_primary);
    Setting::set('color_secondary', $request->color_secondary);
    Setting::set('color_sidebar',   $request->color_sidebar);

    return back()->with('success', 'Warna berhasil disimpan.');
}
}
