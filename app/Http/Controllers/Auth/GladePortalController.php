<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// NEW 27 Aug 2026 — per Chris: a totally separate front door for CBE
// (branded "GLADE"), reached at /glade instead of the standard /login.
// Same `agents` table/credentials as every other login page in this
// app (no new accounts, no new passwords) — this file only adds a
// dedicated URL + a CBE-specific access check + a role-aware landing
// redirect. It never touches AuthController's actual authentication
// logic, mirroring the existing branded-login precedent in
// SpecialGroupController::loginPage()/loginSubmit().
//
// Who is let in:
//   1. Any of GeneralLink's 3 platform Admin accounts (Director/
//      Finance/Sales departments) — same accounts used at /admin,
//      gated only by role=ADMIN (see routes/web.php 'role:ADMIN'
//      middleware on the whole admin.* group).
//   2. Any CBE hierarchy node's own officer (HQ/State/Branch/Temple —
//      Director/Finance/Membership) — see cbe_node_officers, one row
//      per (node, role, agent), already used by
//      CbeExecDashboardController.
// Everyone else is rejected at the door, even with valid credentials.
class GladePortalController extends Controller
{
    public function loginPage(Request $request)
    {
        if (auth('agent')->check()) {
            return $this->landingFor(auth('agent')->user());
        }
        // FIX 28 Aug 2026 — auth.glade-login includes the shared
        // partials.intro-video-widget (same as the main login page),
        // which expects an $introVideo variable to already be passed
        // in. This view was rendering it bare, causing "Undefined
        // variable $introVideo" on GET /glade. Same lookup
        // AuthController::showLogin() already uses.
        $introVideo = \App\Http\Controllers\Admin\VideoLibraryController::latestIntroVideo();
        return view('auth.glade-login', compact('introVideo'));
    }

    public function loginSubmit(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $agent = Agent::where('email', $request->email)->where('is_deleted', false)->first();

        if (! $agent) {
            return back()->withErrors(['email' => 'No account found with this email address.'])->withInput();
        }

        if ($agent->status === 'TERMINATED') {
            return back()->withErrors(['email' => 'Your account has been terminated. Please contact admin.'])->withInput();
        }

        if ($agent->status === 'INACTIVE') {
            return back()->withErrors(['email' => 'Your account is inactive. Please contact admin.'])->withInput();
        }

        if ($agent->isLocked()) {
            return back()->withErrors(['email' => 'Your account is temporarily locked due to too many failed login attempts. Please try again later.'])->withInput();
        }

        if (! Hash::check($request->password, $agent->password_hash)) {
            $agent->increment('failed_login_attempts');
            \App\Services\AuditService::logChange('agents', $agent->agent_id, 'LOGIN_FAILED', null, ['attempt_number' => $agent->failed_login_attempts, 'ip_address' => $request->ip()], $agent->agent_id);
            if ($agent->failed_login_attempts >= 5) {
                $agent->update(['locked_until' => now()->addMinutes(30)]);
                return back()->withErrors(['email' => 'Too many failed attempts. Your account is locked for 30 minutes.'])->withInput();
            }
            return back()->withErrors(['password' => 'Incorrect password. Please try again.'])->withInput();
        }

        // The critical check — this account must genuinely have CBE
        // access, either as a platform Admin or as a CBE node officer.
        // A valid GeneralLink password alone is not enough here.
        if (! $this->hasGladeAccess($agent)) {
            return back()->withErrors(['email' => 'This account does not have GLADE access. Please contact your organization\'s Admin.'])->withInput();
        }

        $agent->update(['failed_login_attempts' => 0, 'locked_until' => null]);
        \App\Services\AuditService::logChange('agents', $agent->agent_id, 'LOGIN_SUCCESS', null, ['ip_address' => $request->ip()], $agent->agent_id);

        Auth::guard('agent')->login($agent, $request->boolean('remember'));
        $request->session()->regenerate();

        // Remember that this session came in through the GLADE front
        // door, so (a) every CBE KPI/exec-dashboard view renders with
        // the minimal GLADE layout instead of the standard DSG/ORG
        // sidebar, and (b) logout returns here instead of /login.
        $request->session()->forget('login_origin_slug');
        $request->session()->put('portal', 'glade');
        $request->session()->put('login_origin_glade', true);

        return $this->landingFor($agent);
    }

    // NEW 28 Aug 2026 — per Chris: "when i login the default is the
    // main menu, not this" — the true landing screen after /glade
    // login. Sidebar renders in its default collapsed "main menu" state
    // here (no $xxxActive flag in layouts/glade.blade.php matches this
    // route), and Admin/officer both pick where to go next from the
    // sidebar themselves, instead of being dropped straight into one
    // report. Notices/reminders snapshot reuses the exact same query
    // Cbe\DashboardController::render() already uses for the older
    // Ecosystem Home screen, so both landing screens show consistent
    // data.
    // NEW 18 Sep 2026 — per Chris: "i didnt see what you have develop
    // above listed in the dashboard menu, why?" Root cause: the ONLY
    // way into GLADE/Secretarial Management was a second login at
    // /glade, and nothing on the normal dashboard linked to it, so an
    // already-logged-in Admin/officer had no path there at all. This is
    // that missing bridge — same access check as loginSubmit(), but for
    // someone who is already authenticated: just stamps the session
    // 'portal' flag every GLADE view/layout checks, then hands off to
    // the same landing logic. No second password needed.
    public function enter(Request $request)
    {
        $agent = auth('agent')->user();
        if (! $this->hasGladeAccess($agent)) {
            abort(403, 'This account does not have GLADE access.');
        }

        $request->session()->put('portal', 'glade');
        $request->session()->put('login_origin_glade', true);

        return redirect()->route('glade.home');
    }

    public function home(Request $request)
    {
        $agent = auth('agent')->user();

        if (! $this->hasGladeAccess($agent)) {
            abort(403, 'This account does not have GLADE access.');
        }

        [$relevanceSql, $bindings] = \App\Services\NoticeRelevanceService::sqlExpression(null);
        $notices = DB::table('notices as n')
            ->where('n.is_deleted', false)
            ->where(function ($q) {
                $q->whereNull('n.expires_at')->orWhereDate('n.expires_at', '>=', now()->toDateString());
            })
            ->orderByRaw($relevanceSql . ' DESC', $bindings)
            ->orderByDesc('n.created_at')
            ->limit(3)
            ->get();

        $reminders = DB::table('personal_reminders')
            ->where('agent_id', $agent->agent_id)
            ->where('is_deleted', false)
            ->where('status', 'PENDING')
            ->orderBy('reminder_date')
            ->limit(3)
            ->get();

        return view('glade.home', compact('agent', 'notices', 'reminders'));
    }

    private function hasGladeAccess(Agent $agent): bool
    {
        if ($agent->role === 'ADMIN') {
            return true;
        }

        return DB::table('cbe_node_officers')
            ->where('agent_id', $agent->agent_id)
            ->where('is_active', true)
            ->exists();
    }

    // Auto-detect landing per Chris: Platform Admin lands straight in
    // the cross-org CBE KPI dashboard; a node officer lands straight
    // in their own node's KPI dashboard. Both existing screens —
    // nothing new is rendered here, this just routes to the right one.
    private function landingFor(Agent $agent)
    {
        // UPDATED 28 Aug 2026 — per Chris: "when i login the default is
        // the main menu, not this" — both Admin and an officer now land
        // on the GLADE home/main-menu screen first (glade.home), and
        // pick CBE KPI / Exec Dashboard / any other screen from the
        // sidebar themselves, instead of being auto-dropped into one
        // specific report.
        if ($this->hasGladeAccess($agent)) {
            return redirect()->route('glade.home');
        }

        // Valid GeneralLink login, but no CBE access at all — shouldn't
        // normally be reachable (loginSubmit already blocks it), but
        // guards the auto-redirect-if-already-logged-in path too, e.g.
        // an existing DSG/ORG session visiting /glade directly.
        abort(403, 'This account does not have GLADE access.');
    }
}
