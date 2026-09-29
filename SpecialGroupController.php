<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SpecialGroupController extends Controller
{
    // -------------------------------------------------------
    // ADMIN — create the one Group Leader for an existing Special
    // Privilege Group Name Label (Admin must have already created the
    // label itself via Group Name Maintenance, with the flag OFF).
    // -------------------------------------------------------
    public function createGLForm()
    {
        $labels = DB::table('group_labels')
            ->where('promotion_demotion_enabled', false)
            ->get();

        return view('special-group.create-gl', compact('labels'));
    }

    public function createGL(Request $request)
    {
        $request->validate([
            'group_label_id' => ['required', 'exists:group_labels,group_label_id'],
            'full_name'      => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'email'          => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'          => ['required', 'string', 'regex:/^01[0-9]-?[0-9]{7,8}$/'],
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
            return back()->withErrors(['group_label_id' => 'This action is only allowed for a Special Privilege Group (Promotion/Demotion Rules must be Disabled on that group).'])->withInput();
        }

        // Block if this label already has a GL — confirmed rule: only
        // ONE GL per Special Privilege Group.
        $existingGL = Agent::where('group_label_id', $request->group_label_id)->where('role', 'GROUP_LEADER')->exists();
        if ($existingGL) {
            return back()->withErrors(['group_label_id' => 'This group already has a Group Leader. Only one is allowed per Special Privilege Group.'])->withInput();
        }

        $result = $this->createAgent($request, 'GROUP_LEADER', null, null, $request->group_label_id);

        return redirect()->route('admin.masterfile.group-names')->with('success', "Group Leader created for this Special Privilege Group. Verification email sent to {$request->email}.");
    }

    // -------------------------------------------------------
    // GL — appoint the one Team Leader for their own Special
    // Privilege Group.
    // -------------------------------------------------------
    public function appointTLForm()
    {
        $me = Auth::guard('agent')->user();
        if ($me->role !== 'GROUP_LEADER' || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only a Group Leader of a genuine Special Privilege Group can appoint a Team Leader.');
        }

        $existingTL = Agent::where('group_label_id', $me->group_label_id)->where('role', 'TEAM_LEADER')->first();

        return view('special-group.appoint-tl', compact('existingTL'));
    }

    public function appointTL(Request $request)
    {
        $me = Auth::guard('agent')->user();
        if ($me->role !== 'GROUP_LEADER' || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only a Group Leader of a genuine Special Privilege Group can appoint a Team Leader.');
        }

        $existingTL = Agent::where('group_label_id', $me->group_label_id)->where('role', 'TEAM_LEADER')->exists();
        if ($existingTL) {
            return back()->withErrors(['email' => 'This group already has a Team Leader. Only one is allowed per Special Privilege Group.']);
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'email'     => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'     => ['required', 'string', 'regex:/^01[0-9]-?[0-9]{7,8}$/'],
            'address'   => ['required', 'string'],
            'postcode'  => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'      => ['required', 'string', 'max:100'],
            'state'     => ['required', 'string', 'max:100'],
        ]);

        $this->createAgent($request, 'TEAM_LEADER', $me->agent_id, $me->group_id, $me->group_label_id);

        return redirect()->route('gl.dashboard')->with('success', "Team Leader appointed. Verification email sent to {$request->email}.");
    }

    // -------------------------------------------------------
    // GL or TL — add an Introducer directly under the group's TL.
    // -------------------------------------------------------
    public function addIntroducerForm()
    {
        $me = Auth::guard('agent')->user();
        if (!in_array($me->role, ['GROUP_LEADER', 'TEAM_LEADER']) || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only the GL or TL of a genuine Special Privilege Group can add an Introducer this way.');
        }
        return view('special-group.add-introducer');
    }

    public function addIntroducer(Request $request)
    {
        $me = Auth::guard('agent')->user();
        if (!in_array($me->role, ['GROUP_LEADER', 'TEAM_LEADER']) || !$me->group_label_id || DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled')) {
            abort(403, 'Only the GL or TL of a genuine Special Privilege Group can add an Introducer this way.');
        }

        $tl = Agent::where('group_label_id', $me->group_label_id)->where('role', 'TEAM_LEADER')->first();
        if (!$tl) {
            return back()->withErrors(['email' => 'This group has no Team Leader yet — appoint one first.']);
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'email'     => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'     => ['required', 'string', 'regex:/^01[0-9]-?[0-9]{7,8}$/'],
            'address'   => ['required', 'string'],
            'postcode'  => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'      => ['required', 'string', 'max:100'],
            'state'     => ['required', 'string', 'max:100'],
        ]);

        $this->createAgent($request, 'INTRODUCER', $tl->agent_id, $tl->group_id, $me->group_label_id);

        return back()->with('success', "Introducer added under {$tl->full_name}. Verification email sent to {$request->email}.");
    }

    // -------------------------------------------------------
    // PUBLIC — branded login page per Special Privilege Group, showing
    // that group's own logo. Submits to the SAME existing, working
    // login endpoint (auth.login.post) — this file never touches
    // AuthController's actual authentication logic at all.
    // -------------------------------------------------------
    public function loginPage(string $groupLabelId)
    {
        $label = DB::table('group_labels')->where('group_label_id', $groupLabelId)->where('promotion_demotion_enabled', false)->firstOrFail();
        return view('special-group.login', compact('label'));
    }

    // -------------------------------------------------------
    // PUBLIC — branded self-registration page. A prospect joins as
    // Introducer directly under the group's one TL — no sponsor choice
    // needed, since there's only ever one TL per Special Privilege Group.
    // -------------------------------------------------------
    public function joinPage(string $groupLabelId)
    {
        $label = DB::table('group_labels')->where('group_label_id', $groupLabelId)->where('promotion_demotion_enabled', false)->firstOrFail();
        $tl = Agent::where('group_label_id', $groupLabelId)->where('role', 'TEAM_LEADER')->first();

        return view('special-group.join', compact('label', 'tl'));
    }

    public function joinSubmit(Request $request, string $groupLabelId)
    {
        $label = DB::table('group_labels')->where('group_label_id', $groupLabelId)->where('promotion_demotion_enabled', false)->firstOrFail();
        $tl = Agent::where('group_label_id', $groupLabelId)->where('role', 'TEAM_LEADER')->first();
        if (!$tl) {
            return back()->withErrors(['email' => 'This group is not yet ready to accept new members — no Team Leader assigned.']);
        }

        $request->validate([
            'full_name' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.]+$/'],
            'email'     => ['required', 'email', 'max:200', 'unique:agents,email'],
            'phone'     => ['required', 'string', 'regex:/^01[0-9]-?[0-9]{7,8}$/'],
            'address'   => ['required', 'string'],
            'postcode'  => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'      => ['required', 'string', 'max:100'],
            'state'     => ['required', 'string', 'max:100'],
        ]);

        $this->createAgent($request, 'INTRODUCER', $tl->agent_id, $tl->group_id, $groupLabelId);

        return redirect()->route('special-group.join', $groupLabelId)->with('success', 'Thank you for joining! Please check your email to verify your account.');
    }

    // -------------------------------------------------------
    // Shared creation logic — role assigned DIRECTLY (no promotion
    // involved at all), same privacy-compliant fields as normal
    // registration (no NRIC/bank), same verification email step.
    // -------------------------------------------------------
    private function createAgent(Request $request, string $role, ?string $parentId, ?string $groupId, string $groupLabelId): Agent
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

        $agent = Agent::create([
            'agent_id'                  => $agentId,
            'full_name'                 => $request->full_name,
            'email'                     => strtolower($request->email),
            'password_hash'             => Hash::make(Str::random(32)),
            'phone'                     => $request->phone,
            'role'                      => $role,
            'agent_code'                => $agentCode,
            'parent_id'                 => $parentId,
            'group_id'                  => $groupId,
            'group_label_id'            => $groupLabelId,
            'status'                    => 'INACTIVE', // same rule as everyone — becomes Active only after their own verification + password
            'qr_code_token'             => Str::random(10),
            'email_verification_token'  => $verifyToken,
            'created_by'                => Auth::guard('agent')->id(),
        ]);

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

        $verifyLink = url('/verify-email/' . $verifyToken);
        Mail::raw(
            "Hi {$request->full_name},\n\nYou've been registered with GeneralLink as a {$role}. Please verify your email to activate your account:\n{$verifyLink}",
            function ($mail) use ($request) {
                $mail->to($request->email)->subject('GeneralLink — Verify Your Email');
            }
        );

        \App\Services\AuditService::logChange('agents', $agentId, 'SPECIAL_GROUP_ADD', null, ['full_name' => $request->full_name, 'role' => $role]);

        return $agent;
    }
}
