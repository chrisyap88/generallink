<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 11 Sep 2026 (Task #413 follow-up) — per Chris's decision: the
// Membership Module sidebar's "Entity Maintenance" link already computed
// an officer-scoped ['group' => $officerGroupId] param (glade.blade.php),
// meaning a CBE node officer was always meant to reach this screen for
// their OWN node — but the route sat inside role:ADMIN-only middleware,
// so an officer clicking it hit a 403. This middleware admits platform
// Admin (agents.role = 'ADMIN', unchanged) OR any agent with an ACTIVE
// cbe_node_officers row.
//
// This middleware only decides who gets through the door — it does NOT
// scope what an officer can do once inside. AdminCbeHierarchyNodeController
// still enforces server-side that an officer can only create entities
// within their own node's hierarchy_path subtree, so a tampered
// group/level/parent query or form value can't be used to reach into
// another node's data.
class RequireAdminOrCbeOfficer
{
    public function handle(Request $request, Closure $next): mixed
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

        if ($agent->role === 'ADMIN') {
            return $next($request);
        }

        $isActiveOfficer = DB::table('cbe_node_officers')
            ->where('agent_id', $agent->agent_id)
            ->where('is_active', true)
            ->exists();

        if (! $isActiveOfficer) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
