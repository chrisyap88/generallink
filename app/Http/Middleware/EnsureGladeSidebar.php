<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// NEW 24 Sep 2026 -- per Chris: "why the sidebar suddenly like this it
// suppose at membership master file" -- Admin screens used to render
// with one of TWO completely different sidebars (the "Community
// Business Enterprise Group / Membership Master File" one, or the
// generic "General Link Digital Affiliate Ecosystem" one with
// Dashboard/KPI Dashboard/Network Tree/etc.), depending on an
// invisible session('portal') flag that only got set by logging in
// through the separate /glade front door or the enter() bridge route.
// A regular Admin login left it unset, silently flipping every Admin
// screen (resources/views/**/*.blade.php pattern:
// @extends(session('portal') === 'glade' ? 'layouts.glade' :
// 'layouts.dashboard')) over to the other, unrelated sidebar -- this
// broke Chris's standing rule of one consistent look across all
// screens.
//
// Rather than touching that conditional in every individual view file
// (dozens of them, high risk of missing one), this middleware just
// makes sure the flag those views already check is always set to
// 'glade' for every Admin screen, however the Admin got there. It is
// only applied to the 'admin' route group -- other portals (GL/TL/
// Introducer/CBE node officer) are untouched.
class EnsureGladeSidebar
{
    // CHANGED 27 Sep 2026 -- per Chris: "is there any other sidebar is
    // wrong link" -- now also applied to the shared route groups the CBE
    // sidebar links into (Customer Relationship, Help Desk, Treasury /
    // accounting, Secretarial, Executive KPI ...). Those groups also
    // serve Group Leaders / Team Leaders / Introducers, who must keep
    // their own sidebar, so the flag is only forced for an Admin or an
    // active CBE node officer.
    public function handle(Request $request, Closure $next): mixed
    {
        $agent = auth('agent')->user();
        $isCbeUser = $agent && ($agent->role === 'ADMIN'
            || \Illuminate\Support\Facades\DB::table('cbe_node_officers')
                ->where('agent_id', $agent->agent_id)->where('is_active', true)->exists());

        if ($isCbeUser && $request->session()->get('portal') !== 'glade') {
            $request->session()->put('portal', 'glade');
        }

        return $next($request);
    }
}
