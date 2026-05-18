<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $role = Auth::user()->role;
        return match($role) {
            'admin'   => redirect()->route('admin.dashboard'),
            'cfo'     => redirect()->route('finance.index'),
            'tutor'   => redirect()->route('tutor.dashboard'),
            'student' => redirect()->route('student.dashboard'),
            default   => redirect()->route('dashboard'),
        };
    }
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
