<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tutor;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'phone'    => $request->phone,
        ]);

        if ($request->role === 'tutor') {
            Tutor::create(['user_id' => $user->id, 'persona' => null]);
        } elseif ($request->role === 'student') {
            Student::create(['user_id' => $user->id, 'notes' => null]);
        }

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

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
            'role'  => $newRole,
            'phone' => $request->phone,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Buat record tutor/student jika role berubah
        if ($newRole === 'tutor' && !$user->tutor) {
            Tutor::create(['user_id' => $user->id, 'persona' => null]);
        } elseif ($newRole === 'student' && !$user->student) {
            Student::create(['user_id' => $user->id, 'notes' => null]);
        }

        return redirect()->route('admin.settings.index')->with('success', 'User berhasil diupdate.');
    }

    public function destroyUser(User $user)
    {
        $user->delete();
        return redirect()->route('admin.settings.index')->with('success', 'User berhasil dihapus.');
    }
}
