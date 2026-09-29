<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Usage in routes:
     *   ->middleware('role:ADMIN')
     *   ->middleware('role:ADMIN,GROUP_LEADER')
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $agent = Auth::guard('agent')->user();

        if (! $agent) {
            return redirect()->route('auth.login');
        }

        if (! $agent->isActive()) {
            Auth::guard('agent')->logout();
            return redirect()->route('auth.login')
                             ->with('error', 'Your account is not active.');
        }

        if (! in_array($agent->role, $roles, true)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
