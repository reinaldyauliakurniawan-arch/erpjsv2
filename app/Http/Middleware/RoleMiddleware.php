<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based authorization middleware.
 *
 * Usage in routes:
 *   ->middleware('role:admin')           // single role
 *   ->middleware('role:admin,cfo')       // multiple roles (any match)
 *
 * Behavior:
 *   - Unauthenticated → redirect to login (web requests) OR 401 JSON (API/XHR).
 *   - Insufficient role → 403 page (web) OR 403 JSON (API/XHR) with
 *     a structured error body.
 *
 * The JSON path matters because we now have JSON endpoints (e.g. /search)
 * protected by role middleware — they should return a parseable error
 * body, not an HTML error page that breaks fetch().json().
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'unauthenticated',
                    'message' => 'Authentication required.',
                ], 401);
            }
            return redirect()->route('login');
        }

        if (!in_array($request->user()->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'forbidden',
                    'message' => 'This action is not authorized for your role.',
                ], 403);
            }
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
