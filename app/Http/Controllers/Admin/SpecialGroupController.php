<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SpecialGroupController extends Controller
{
    // UPDATED 29 Jul 2026 — this controller's own phoneRule() (added
    // 24 Jul 2026) was the correct check, so it's now the shared
    // App\Services\PhoneNumberService used app-wide instead of a private
    // copy here. Behavior is unchanged — same regex, same message.
    private function phoneRule(): \Closure
    {
        return PhoneNumberService::rule();
    }

    // -------------------------------------------------------
    // ADMIN — create the one Group Leader for an existing Special
    // Privilege Group Name Label (Admin must have already created the
    // label itself via Group Name Maintenance, with the flag OFF).
    // -------------------------------------------------------
    // Landing page — Add New / Search-View-Edit, same pattern as
    // everywhere else in the app.
    public function index()
    {
        return view('special-group.index');
    }

    public function searchForm()
    {
        return view('special-group.search-form');
    }

    public function typeahead(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        // CHANGED 24 Sep 2026 -- per Chris: "add search all criteria" --
        // now matches Description too, same as Group Name & Hierarchy
        // Levels' own search.
        // NEW 25 Sep 2026 -- per Chris: every search screen must offer
        // an explicit "Search All" choice, listed first, followed by
        // each specific field -- not just a silently-merged OR match.
        $field = $request->get('field', '');
        $allowedFields = ['group_name', 'description'];

        $s = '%' . $q . '%';
        $results = DB::table('group_labels')
            ->where('promotion_demotion_enabled', false)
            ->where(function ($sub) use ($s, $field, $allowedFields) {
                if (in_array($field, $allowedFields, true)) {
                    $sub->where($field, 'like', $s);
                } else {
                    $sub->where('group_name', 'like', $s)
                        ->orWhere('description', 'like', $s);
                }
            })
            ->orderBy('group_name')
            ->limit(15)
            ->get(['group_label_id', 'group_name']);

        return response()->json($results);
    }

    // View one Organization Rewards Group's GL, TL, and Introducers
    // together — confirmed choice: one group at a time, not a unified
    // cross-group search.
    public function view(Request $request, string $groupLabelId)
    {
        $label = DB::table('group_labels')->where('group_label_id', $groupLabelId)->where('promotion_demotion_enabled', false)->firstOrFail();

        $gl = Agent::where('group_label_id', $groupLabelId)->where('role', 'GROUP_LEADER')->first();

        // REBUILT AGAIN 23 Jul 2026 (v3) — per Chris: "i want drill down
        // from introducer" — an Introducer can recruit their OWN
        // sub-Introducers (same "however deep" nesting DataScopeService
        // already handles elsewhere), so a fixed 2-level GL→TL→
        // Introducer view wasn't enough. This is now a genuinely
        // recursive drill-down: every node (GL, a TL, or any
        // Introducer) shows its own DIRECT children with each child's
        // own child-count, and clicking a row with children > 0 drills
        // one level deeper — however many levels actually exist. "Back"
        // just walks to the current node's own parent_id, so it works
        // at any depth without needing a separate breadcrumb trail.
        //
        // $node = whose direct children we're currently listing.
        // Defaults to the GL (so the default view is "Team Leaders").
        $nodeId = trim((string) $request->get('node', ''));
        $node = $nodeId !== ''
            ? Agent::where('agent_id', $nodeId)->where('group_label_id', $groupLabelId)->where('is_deleted', false)->first()
            : $gl;
        if (!$node) {
            $node = $gl;
        }

        $children = null;
        $childLabel = 'Team Leaders';
        if ($node) {
            $isTop = $gl && $node->agent_id === $gl->agent_id;
            $childLabel = $isTop ? 'Team Leaders' : 'Introducers';

            // NEW 23 Jul 2026 (v3) — per Chris: still cut off at 8/page.
            // Dropped to 5/page — small enough to always fit with zero
            // scroll regardless of exact screen height, since I can't
            // see his actual browser viewport from here.
            $children = Agent::where('parent_id', $node->agent_id)
                ->where('is_deleted', false)
                ->orderBy('full_name')
                ->paginate(5, ['*'], 'page')
                ->withQueryString();

            $childIds = collect($children->items())->pluck('agent_id')->all();
            $grandchildCounts = empty($childIds) ? collect() : Agent::whereIn('parent_id', $childIds)
                ->where('is_deleted', false)
                ->select('parent_id', DB::raw('count(*) as cnt'))
                ->groupBy('parent_id')
                ->pluck('cnt', 'parent_id');

            foreach ($children as $child) {
                $child->child_count = (int) ($grandchildCounts[$child->agent_id] ?? 0);
            }
        }

        return view('special-group.view', compact('label', 'gl', 'node', 'children', 'childLabel'));
    }

    // NEW 1 Aug 2026 — per Chris: "during new registration when the
    // registration is for which gl or tl immediately you prompt the use
    // to pick up his rank no... check the set up in the earning income
    // structure or the master file group label flag if specify need
    // rank assignment during registration." Two independent triggers,
    // either one is enough to require a rank pick:
    //   1. Admin explicitly switched group_labels.requires_rank_assignment
    //      ON for this group (manual override).
    //   2. This group already has an Earning Income Structure configured
    //      with a Rank Assignment breakdown for this role (i.e. someone
    //      used "Configure/Edit Rank Allocation" and saved a multi-tier
    //      %-per-rank split) — if payouts for this role are actually
    //      computed per rank, a new agent with no rank would just fall
    //      into the fallback/breakage logic silently, so it makes sense
    //      to prompt right away regardless of the flag.
    // Returns the ranks to offer in the picker, or null if no picker is
    // needed (neither trigger fired, or no ranks defined yet).
    private function ranksRequiringSelection(string $role, ?string $groupLabelId)
    {
        if (!$groupLabelId) {
            return null;
        }

        $flagOn = (bool) DB::table('group_labels')->where('group_label_id', $groupLabelId)->value('requires_rank_assignment');

        $hasRankStructure = DB::table('commission_rank_allocations as cra')
            ->join('role_ranks as rr', 'rr.rank_id', '=', 'cra.rank_id')
            ->where('rr.role', $role)
            ->where('rr.group_label_id', $groupLabelId)
            ->exists();

        if (!$flagOn && !$hasRankStructure) {
            return null;
        }

        $ranks = DB::table('role_ranks')->where('role', $role)->where('group_label_id', $groupLabelId)->where('is_active', true)
            ->get()->sortBy(fn ($r) => $r->rank_no, SORT_NATURAL)->values();

        return $ranks->isEmpty() ? null : $ranks;
    }

    public function createGLForm()
    {
        $labels = DB::table('group_labels')
            ->where('promotion_demotion_enabled', false)
            ->get();

        // Ranks for EVERY requires_rank_assignment group, keyed by
        // group_label_id, so the view's JS can reveal the right rank
        // picker once Admin picks which group this new GL belongs to —
        // the group isn't known until that dropdown is chosen, unlike
        // appointTL/addIntroducer where the group is already fixed.
        $ranksByGroup = [];
        foreach ($labels as $label) {
            $ranks = $this->ranksRequiringSelection('GROUP_LEADER', $label->group_label_id);
            if ($ranks) {
                $ranksByGroup[$label->group_label_id] = $ranks;
            }
        }

        return view('special-group.create-gl', compact('labels', 'ranksByGroup'));
    }

    public function createGL(Request $request)
    {
        $request->validate([
            'group_label_id' => ['required', 'exists:group_labels,group_label_id'],
            'full_name'      => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'second_name'    => ['nullable', 'string', 'max:200'],
            'email'          => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'          => ['required', 'string', $this->phoneRule()],
            'address'        => ['required', 'string'],
            'postcode'       => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'           => ['required', 'string', 'max:100'],
            'state'          => ['required', 'string', 'max:100'],
        ]);

        // Server-side enforcement — the DROPDOWN already only shows
        // Special Privilege groups, but that alone is just a display
        // convenience, not real protection against a tampered
        // submission. This is the actual, unbypassable check.
        $label = DB::table('group_labels')->where('group_label_id', $request->group_label_id)->first();
        if (!$label || $label->promotion_demotion_enabled) {
            return back()->withErrors(['group_label_id' => 'This action is only allowed for an Organization Rewards Group (Promotion/Demotion Rules must be Disabled on that group).'])->withInput();
        }

        // Block if this label already has a GL — confirmed rule: only
        // ONE GL per Organization Rewards Group.
        $existingGL = Agent::where('group_label_id', $request->group_label_id)->where('role', 'GROUP_LEADER')->exists();
        if ($existingGL) {
            return back()->withErrors(['group_label_id' => 'This group already has a Group Leader. Only one is allowed per Organization Rewards Group.'])->withInput();
        }

        // NEW 1 Aug 2026 — require a rank pick right here if this group
        // has been switched to requires_rank_assignment, instead of
        // leaving the new GL unranked and silently risking breakage
        // later on a rank-configured Earning Income Structure.
        $rankId = null;
        $ranksNeeded = $this->ranksRequiringSelection('GROUP_LEADER', $request->group_label_id);
        if ($ranksNeeded) {
            $request->validate(['rank_id' => ['required', 'exists:role_ranks,rank_id']]);
            if (!$ranksNeeded->contains('rank_id', $request->rank_id)) {
                return back()->withErrors(['rank_id' => 'Please pick a valid rank for this group.'])->withInput();
            }
            $rankId = $request->rank_id;
        }

        $result = $this->createAgent($request, 'GROUP_LEADER', null, null, $request->group_label_id, $rankId);

        return redirect()->route('admin.masterfile.group-names')->with('success', "Group Leader created for this Organization Rewards Group. Verification email sent to {$request->email}.");
    }

    // -------------------------------------------------------
    // GL — appoint Team Leaders for their own Organization Rewards Group.
    // UPDATED 16 Jul 2026 — multiple Team Leaders per group are now
    // supported (e.g. PVATM's 14 regional/state offices, each with
    // their own TL). The old "exactly one TL" block is removed; the
    // page instead lists every existing TL and always offers the form
    // to appoint another. Matches "Option A — manual branch/office
    // selection" agreed in principle in the master spec (37.21) and
    // now actually built.
    // -------------------------------------------------------
    public function appointTLForm()
    {
        $me = Auth::guard('agent')->user();
        if ($me->role !== 'GROUP_LEADER' || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only a Group Leader of a genuine Organization Rewards Group can appoint a Team Leader.');
        }

        $existingTLs = Agent::where('group_label_id', $me->group_label_id)
            ->where('role', 'TEAM_LEADER')
            ->where('is_deleted', false)
            ->leftJoin('agent_profiles', 'agent_profiles.agent_id', '=', 'agents.agent_id')
            ->orderBy('agents.full_name')
            ->get(['agents.*', 'agent_profiles.state as office_state', 'agent_profiles.city as office_city']);

        // NEW 1 Aug 2026 — group is already fixed here (unlike Create GL),
        // so we can resolve the rank picker up front, no JS needed.
        $ranksNeeded = $this->ranksRequiringSelection('TEAM_LEADER', $me->group_label_id);

        return view('special-group.appoint-tl', compact('existingTLs', 'ranksNeeded'));
    }

    public function appointTL(Request $request)
    {
        $me = Auth::guard('agent')->user();
        if ($me->role !== 'GROUP_LEADER' || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only a Group Leader of a genuine Organization Rewards Group can appoint a Team Leader.');
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'second_name' => ['nullable', 'string', 'max:200'],
            'email'     => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'     => ['required', 'string', $this->phoneRule()],
            'address'   => ['required', 'string'],
            'postcode'  => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'      => ['required', 'string', 'max:100'],
            'state'     => ['required', 'string', 'max:100'],
        ]);

        $rankId = null;
        $ranksNeeded = $this->ranksRequiringSelection('TEAM_LEADER', $me->group_label_id);
        if ($ranksNeeded) {
            $request->validate(['rank_id' => ['required', 'exists:role_ranks,rank_id']]);
            if (!$ranksNeeded->contains('rank_id', $request->rank_id)) {
                return back()->withErrors(['rank_id' => 'Please pick a valid rank for this group.'])->withInput();
            }
            $rankId = $request->rank_id;
        }

        $this->createAgent($request, 'TEAM_LEADER', $me->agent_id, $me->group_id, $me->group_label_id, $rankId);

        return redirect()->route('special-group.appoint-tl')->with('success', "Team Leader appointed. Verification email sent to {$request->email}.");
    }

    // -------------------------------------------------------
    // GL or TL — add an Introducer directly under a TL in the group.
    // UPDATED 16 Jul 2026 — now that a group can have multiple regional
    // Team Leaders, a GL caller must pick WHICH TL/office the new
    // Introducer goes under ("Option A — manual branch/office
    // selection" from master spec 37.21). A TL caller still always
    // places the new Introducer under themselves — no picker needed.
    // -------------------------------------------------------
    public function addIntroducerForm()
    {
        $me = Auth::guard('agent')->user();
        if (!in_array($me->role, ['GROUP_LEADER', 'TEAM_LEADER']) || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only the GL or TL of a genuine Organization Rewards Group can add an Introducer this way.');
        }

        $teamLeaders = collect();
        if ($me->role === 'GROUP_LEADER') {
            $teamLeaders = Agent::where('group_label_id', $me->group_label_id)
                ->where('role', 'TEAM_LEADER')
                ->where('is_deleted', false)
                ->leftJoin('agent_profiles', 'agent_profiles.agent_id', '=', 'agents.agent_id')
                ->orderBy('agents.full_name')
                ->get(['agents.*', 'agent_profiles.state as office_state', 'agent_profiles.city as office_city']);
        }

        // NEW 1 Aug 2026 — per Chris, this is the exact scenario he asked
        // about: "when i register a new introducer... immediately you
        // prompt the use to pick up his rank no." Group is fixed to
        // $me->group_label_id, so the rank picker (if this group requires
        // it) can be resolved right here.
        $ranksNeeded = $this->ranksRequiringSelection('INTRODUCER', $me->group_label_id);

        return view('special-group.add-introducer', compact('teamLeaders', 'ranksNeeded'));
    }

    public function addIntroducer(Request $request)
    {
        $me = Auth::guard('agent')->user();
        if (!in_array($me->role, ['GROUP_LEADER', 'TEAM_LEADER']) || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only the GL or TL of a genuine Organization Rewards Group can add an Introducer this way.');
        }

        if ($me->role === 'TEAM_LEADER') {
            // TL always adds directly under themselves — no picker needed.
            $tl = $me;
        } else {
            // GL must specify which TL/office this Introducer belongs to.
            $request->validate(['team_leader_id' => ['required', 'exists:agents,agent_id']]);
            $tl = Agent::where('agent_id', $request->team_leader_id)
                ->where('group_label_id', $me->group_label_id)
                ->where('role', 'TEAM_LEADER')
                ->first();
            if (!$tl) {
                return back()->withErrors(['team_leader_id' => 'Please select a valid Team Leader from this group.'])->withInput();
            }
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'second_name' => ['nullable', 'string', 'max:200'],
            'email'     => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'     => ['required', 'string', $this->phoneRule()],
            'address'   => ['required', 'string'],
            'postcode'  => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'      => ['required', 'string', 'max:100'],
            'state'     => ['required', 'string', 'max:100'],
        ]);

        $rankId = null;
        $ranksNeeded = $this->ranksRequiringSelection('INTRODUCER', $me->group_label_id);
        if ($ranksNeeded) {
            $request->validate(['rank_id' => ['required', 'exists:role_ranks,rank_id']]);
            if (!$ranksNeeded->contains('rank_id', $request->rank_id)) {
                return back()->withErrors(['rank_id' => 'Please pick a valid rank for this group.'])->withInput();
            }
            $rankId = $request->rank_id;
        }

        $this->createAgent($request, 'INTRODUCER', $tl->agent_id, $tl->group_id, $me->group_label_id, $rankId);

        return back()->with('success', "Introducer added under {$tl->full_name}. Verification email sent to {$request->email}.");
    }

    // -------------------------------------------------------
    // PUBLIC — branded login page per Organization Rewards Group, showing
    // that group's own logo. Submits to the SAME existing, working
    // login endpoint (auth.login.post) — this file never touches
    // AuthController's actual authentication logic at all.
    // Updated 15 Jul 2026: if arriving fresh from account activation
    // (?welcome=1), flash a friendly greeting so the user isn't just
    // dropped silently on a blank login form.
    // -------------------------------------------------------
    public function loginPage(Request $request, string $slug)
    {
        $label = DB::table('group_labels')->where('slug', $slug)->where('promotion_demotion_enabled', false)->firstOrFail();
        if ($request->query('welcome')) {
            session()->flash('success', '🎉 Your account is now active! Please sign in below to get started.');
        }
        return view('special-group.login', compact('label'));
    }

    // Dedicated login endpoint for Organization Rewards Group pages — same
    // checks as the standard login, PLUS a critical group-membership
    // verification. Without this, ANY valid credential (Admin, or any
    // agent from a completely different group) could log in through
    // ANY group's branded page, since the standard login has no
    // awareness of which page it was submitted from. Confirmed security
    // gap (06 Jul 2026) — closed here without touching the standard,
    // heavily-tested login logic at all.
    public function loginSubmit(Request $request, string $slug)
    {
        $label = DB::table('group_labels')->where('slug', $slug)->where('promotion_demotion_enabled', false)->firstOrFail();

        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $agent = Agent::where('email', $request->email)->where('is_deleted', false)->first();

        if (!$agent) {
            return back()->withErrors(['email' => 'No account found with this email address.'])->withInput();
        }

        // The critical check — this account must genuinely belong to
        // THIS specific group. Admin, or anyone from a different group,
        // is correctly rejected here, even with valid credentials.
        // Admin is exempt from the group-match check — Admin is a
        // single universal team managing ALL groups (standard and
        // special), never tied to one specific group. Confirmed
        // decision (06 Jul 2026). GL/TL/Introducer still must match
        // exactly, per the original security fix.
        if ($agent->role !== 'ADMIN' && $agent->group_label_id !== $label->group_label_id) {
            return back()->withErrors(['email' => "This account does not belong to {$label->group_name}. Please use your organization's own login page."])->withInput();
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

        if (!Hash::check($request->password, $agent->password_hash)) {
            $agent->increment('failed_login_attempts');
            \App\Services\AuditService::logChange('agents', $agent->agent_id, 'LOGIN_FAILED', null, ['attempt_number' => $agent->failed_login_attempts, 'ip_address' => $request->ip()], $agent->agent_id);
            if ($agent->failed_login_attempts >= 5) {
                $agent->update(['locked_until' => now()->addMinutes(30)]);
                return back()->withErrors(['email' => 'Too many failed attempts. Your account is locked for 30 minutes.'])->withInput();
            }
            return back()->withErrors(['password' => 'Incorrect password. Please try again.'])->withInput();
        }

        $agent->update(['failed_login_attempts' => 0, 'locked_until' => null]);
        \App\Services\AuditService::logChange('agents', $agent->agent_id, 'LOGIN_SUCCESS', null, ['ip_address' => $request->ip()], $agent->agent_id);

        Auth::guard('agent')->login($agent, $request->boolean('remember'));
        $request->session()->regenerate();

        // Remember which branded page was used to log in, so logout
        // returns here — works for ANY role, including Admin, who has
        // no fixed group of their own to check against.
        $request->session()->put('login_origin_slug', $slug);

        return match($agent->role) {
            'ADMIN'        => redirect()->route('admin.dashboard'),
            'GROUP_LEADER' => redirect()->route('gl.dashboard'),
            'TEAM_LEADER'  => redirect()->route('tl.dashboard'),
            default        => redirect()->route('introducer.dashboard'),
        };
    }

    // -------------------------------------------------------
    // PUBLIC — branded self-registration page. A prospect joins as
    // Introducer directly under the group's one TL — no sponsor choice
    // needed, since there's only ever one TL per Organization Rewards Group.
    // -------------------------------------------------------
    public function joinPage(string $slug)
    {
        $label = DB::table('group_labels')->where('slug', $slug)->where('promotion_demotion_enabled', false)->firstOrFail();
        $tl = Agent::where('group_label_id', $label->group_label_id)->where('role', 'TEAM_LEADER')->first();

        return view('special-group.join', compact('label', 'tl'));
    }

    public function joinSubmit(Request $request, string $slug)
    {
        $label = DB::table('group_labels')->where('slug', $slug)->where('promotion_demotion_enabled', false)->firstOrFail();
        $tl = Agent::where('group_label_id', $label->group_label_id)->where('role', 'TEAM_LEADER')->first();
        if (!$tl) {
            return back()->withErrors(['email' => 'This group is not yet ready to accept new members — no Team Leader assigned.']);
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'second_name' => ['nullable', 'string', 'max:200'],
            'email'     => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'     => ['required', 'string', $this->phoneRule()],
            'address'   => ['required', 'string'],
            'postcode'  => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'      => ['required', 'string', 'max:100'],
            'state'     => ['required', 'string', 'max:100'],
        ]);

        $this->createAgent($request, 'INTRODUCER', $tl->agent_id, $tl->group_id, $label->group_label_id);

        return redirect()->route('special-group.join', $slug)->with('success', 'Thank you for joining! Please check your email to verify your account.');
    }

    // -------------------------------------------------------
    // NEW 16 Jul 2026 — thin public wrapper around createAgent() so a
    // console/import command (e.g. bulk-loading PVATM's 14 regional
    // Team Leaders from a spreadsheet) can reuse the EXACT same,
    // already-tested creation logic — agent code generation, group
    // creation for a new GL, verification token + branded email — via
    // a plain array instead of a real HTTP Request. Zero duplication.
    // -------------------------------------------------------
    public function createAgentForImport(array $data, string $role, ?string $parentId, ?string $groupId, string $groupLabelId): Agent
    {
        // Request::create() (not `new Request($data)`) — this correctly
        // populates the POST/input bag, matching what $request->full_name
        // etc. read from on a real form submission. Passing $data as the
        // first constructor argument would put it in the QUERY bag instead.
        $request = Request::create('/', 'POST', $data);
        return $this->createAgent($request, $role, $parentId, $groupId, $groupLabelId);
    }

    // -------------------------------------------------------
    // Shared creation logic — role assigned DIRECTLY (no promotion
    // involved at all), same privacy-compliant fields as normal
    // registration (no NRIC/bank), same verification email step.
    // -------------------------------------------------------
    private function createAgent(Request $request, string $role, ?string $parentId, ?string $groupId, string $groupLabelId, ?string $rankId = null): Agent
    {
        $agentId = (string) Str::uuid();
        $verifyToken = Str::random(64);

        // Root-level code for a new GL (no parent); child code
        // otherwise, reusing the real HierarchyService generator so
        // codes stay consistent with the rest of the system.
        if ($parentId) {
            $parent = Agent::find($parentId);
            $agentCode = app(\App\Services\HierarchyService::class)->generateAgentCode($parent);
        } else {
            $max = Agent::whereNull('parent_id')->where('is_deleted', false)->get()->map(fn ($a) => (int) $a->agent_code)->filter()->max();
            $agentCode = (string) (($max ?? 0) + 1);
        }

        // New GL needs its own operational group (for notifications,
        // consistency with the rest of the system) — TL/Introducer
        // inherit the GL's existing one.
        if ($role === 'GROUP_LEADER') {
            $groupId = (string) Str::uuid();
            DB::table('groups')->insert([
                'group_id'           => $groupId,
                'group_name'         => $request->full_name . "'s Group",
                'group_code'         => 'G-' . strtoupper(Str::random(6)),
                'group_email'        => strtolower($request->email),
                'separator_char'     => '-',
                'root_member_suffix' => '0',
                'is_active'          => true,
                'created_by'         => Auth::guard('agent')->id(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        // Uses raw DB insert, NOT Eloquent's Agent::create() — this
        // avoids a real bug we found: Eloquent silently drops any field
        // not listed in the model's $fillable property, which is
        // exactly why group_label_id was saving as NULL despite being
        // correctly set here.
        //
        // FIXED 23 Jul 2026 — this insert never set hierarchy_path,
        // leaving every Organization Rewards Group TL/Introducer stuck on
        // the schema default '/'. That silently broke every feature
        // that finds a downline via hierarchy_path LIKE '%/{id}/%'
        // (Renewal Forecast, Data Scope visibility, Document Credit
        // Wallet, etc.) even though parent_id was always correct.
        // Computed the same way HierarchyService::registerUnderSponsor()
        // does it: parent's hierarchy_path + this new agent_id + '/'.
        $hierarchyPath = $parentId
            ? (Agent::find($parentId)->hierarchy_path ?? '/') . $agentId . '/'
            : '/' . $agentId . '/';

        DB::table('agents')->insert([
            'agent_id'                  => $agentId,
            'full_name'                 => $request->full_name,
            'second_name'               => $request->second_name ?: null,
            'email'                     => strtolower($request->email),
            'password_hash'             => Hash::make(Str::random(32)),
            'phone'                     => PhoneNumberService::normalize($request->phone),
            'role'                      => $role,
            'agent_code'                => $agentCode,
            'parent_id'                 => $parentId,
            'group_id'                  => $groupId,
            'group_label_id'            => $groupLabelId,
            'rank_id'                   => $rankId, // NEW 1 Aug 2026 — set at registration time when the group requires it (see ranksRequiringSelection())
            'hierarchy_path'            => $hierarchyPath,
            'status'                    => 'INACTIVE', // same rule as everyone — becomes Active only after their own verification + password
            'qr_code_token'             => Str::random(10),
            'email_verification_token'  => $verifyToken,
            'created_by'                => Auth::guard('agent')->id(),
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);
        $agent = Agent::find($agentId);

        DB::table('agent_profiles')->insert([
            'profile_id' => (string) Str::uuid(),
            'agent_id'   => $agentId,
            'address'    => $request->address,
            'postcode'   => $request->postcode,
            'city'       => $request->city,
            'state'      => $request->state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // NEW 15 Jul 2026 — sending now goes through the same shared
        // VerificationMailService used by public registration and the
        // Resend Verification action, so the template lives in one
        // place and correctly shows THIS agent's own group_label logo
        // (e.g. PVATM), not always the generic GeneralLink one.
        app(\App\Services\VerificationMailService::class)->send($agent);

        \App\Services\AuditService::logChange('agents', $agentId, 'SPECIAL_GROUP_ADD', null, ['full_name' => $request->full_name, 'role' => $role]);

        return $agent;
    }
}