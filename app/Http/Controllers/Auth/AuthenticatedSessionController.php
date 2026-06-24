<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
class AuthenticatedSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('throttle:60,1')->only(['store']);
    }
    public function create(): View
    {
        return view('auth.login');
    }
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
            $request->session()->regenerate();
            Log::info('User logged in successfully', ['user_id' => Auth::id()]);
            $role = Auth::user()->role;
            return match($role) {
                'admin'   => redirect()->route('admin.dashboard'),
                'cfo'     => redirect()->route('finance.index'),
                'tutor'   => redirect()->route('tutor.dashboard'),
                'student' => redirect()->route('student.dashboard'),
                default   => redirect()->route('dashboard'),
            };
        } catch (ValidationException $e) {
            Log::warning('Login validation failed', ['email' => $request->email, 'errors' => $e->errors()]);
            throw $e;
        } catch (AuthenticationException $e) {
            Log::warning('Login authentication failed', ['email' => $request->email]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error during login', ['email' => $request->email, 'exception' => $e->getMessage()]);
            throw $e;
        }
    }
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
