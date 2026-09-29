<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\PhoneNumberService;
use App\Services\RoleLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MasterFileController extends Controller
{
    // -------------------------------------------------------
    // TIER STRUCTURE MAINTENANCE (NEW) — Introducer / Team Leader / Group
    // Leader list screens. Same search/filter/paginate pattern as vendors()
    // above. "Add New" on these screens reuses register.blade.php per
    // spec Decision 2 — there is no separate/simpler direct-add form.
    // -------------------------------------------------------
    private function wildcardPattern(string $raw): string
    {
        $raw = trim($raw);
        // Wildcard support: * or % typed by the user both work as a
        // multi-character wildcard (e.g. "1-1-%" or "*@gmail.com").
        // Otherwise, default to an automatic "contains" match.
        if (str_contains($raw, '*') || str_contains($raw, '%')) {
            return str_replace('*', '%', $raw);
        }
        return '%' . $raw . '%';
    }

    // -------------------------------------------------------
    // DOWNLINE SCOPING — restricts Tier Structure Maintenance results to
    // only the logged-in agent's own downline, per the mandatory policy:
    // Admin sees everyone. GL sees their own TLs + those TLs' Introducers
    // + GL's own direct Introducers. TL sees only their own Introducers.
    // Introducer sees only their own direct recruits. This is applied to
    // BOTH the list/search results AND the individual View/Edit screens
    // (as a real authorization check, not just hiding rows from a list)
    // so a scoped role cannot bypass it by guessing a URL.
    // -------------------------------------------------------
    private function applyDownlineScope($query)
    {
        $me = Auth::guard('agent')->user();

        if ($me->role === 'ADMIN') {
            return $query; // no restriction
        }

        if ($me->role === 'GROUP_LEADER') {
            $tlIds = Agent::where('parent_id', $me->agent_id)
                ->where('role', 'TEAM_LEADER')
                ->pluck('agent_id');
            return $query->where(function ($q) use ($me, $tlIds) {
                $q->where('agents.parent_id', $me->agent_id)
                  ->orWhereIn('agents.parent_id', $tlIds);
            });
        }

        // TEAM_LEADER and INTRODUCER both: direct downline only.
        return $query->where('agents.parent_id', $me->agent_id);
    }

    // Authorization check for View/Edit screens — throws 403 if the
    // requested agent is outside the logged-in agent's allowed scope,
    // so scoping can't be bypassed by directly guessing a URL.
    private function assertInScope(Agent $target): void
    {
        $me = Auth::guard('agent')->user();
        if ($me->role === 'ADMIN') {
            return;
        }
        if ($me->role === 'GROUP_LEADER') {
            $tlIds = Agent::where('parent_id', $me->agent_id)->where('role', 'TEAM_LEADER')->pluck('agent_id')->toArray();
            if ($target->parent_id === $me->agent_id || in_array($target->parent_id, $tlIds, true)) {
                return;
            }
            abort(403, 'You do not have access to this record.');
        }
        // TEAM_LEADER and INTRODUCER
        if ($target->parent_id !== $me->agent_id) {
            abort(403, 'You do not have access to this record.');
        }
    }

    // -------------------------------------------------------
    // NEW 15 Jul 2026 — PENDING VERIFICATIONS. A permanent dashboard
    // page (not a one-off script) listing every agent — Introducer,
    // Team Leader, or Group Leader, Direct Selling or Organization Rewards Group alike —
    // who hasn't yet clicked their verification link, with a Resend
    // button per row. Uses the SAME applyDownlineScope() authority
    // rule as every other Tier Structure Maintenance screen: Admin
    // sees everyone; GL sees their own TLs + those TLs' Introducers;
    // TL sees only their own direct Introducers. Available to Admin,
    // GL, and TL — not Introducer (matches resendVerification() below).
    // -------------------------------------------------------
    public function pendingVerifications(Request $request)
    {
        $query = Agent::where('agents.is_deleted', false)
            ->whereNull('agents.email_verified_at')
            ->leftJoin('group_labels', 'group_labels.group_label_id', '=', 'agents.group_label_id')
            ->select('agents.*', 'group_labels.group_name as group_label_name');

        $query = $this->applyDownlineScope($query);

        // Kept deliberately small (8/page, not 20) so the table fits one
        // screen without a scrollbar — same "no scroll" rule as the
        // rest of the app, confirmed mandatory since day 1.
        $pending = $query->orderByDesc('agents.created_at')->paginate(8)->withQueryString();

        return view('masterfile.pending-verifications', compact('pending'));
    }

    // -------------------------------------------------------
    // NEW 15 Jul 2026 — universal "Resend Verification" action, usable
    // from the Introducer, Team Leader, or Group Leader edit screens,
    // AND from the Pending Verifications list above. Only Admin, Group
    // Leader, and Team Leader may use this (not Introducer). Authority
    // follows the SAME master policy already enforced by
    // assertInScope() above: Admin = anyone; GL = their own TLs +
    // those TLs' Introducers; TL = only their own direct Introducers.
    // Sends via the shared VerificationMailService, which picks the
    // correct Public/Special group branding automatically.
    // -------------------------------------------------------
    public function resendVerification(string $id)
    {
        $me = Auth::guard('agent')->user();
        if (! in_array($me->role, ['ADMIN', 'GROUP_LEADER', 'TEAM_LEADER'], true)) {
            abort(403, 'You do not have access to this action.');
        }

        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $this->assertInScope($agent);

        if ($agent->email_verified_at) {
            return back()->with('error', "{$agent->full_name} has already verified their email — no need to resend.");
        }

        $agent->email_verification_token = Str::random(64);
        $agent->status = 'INACTIVE';
        $agent->save();

        app(\App\Services\VerificationMailService::class)->send($agent);

        AuditService::logChange('agents', $agent->agent_id, 'VERIFICATION_RESENT', null, [
            'resent_by' => $me->full_name,
            'resent_to' => $agent->email,
        ]);

        return back()->with('success', "Verification email resent to {$agent->full_name} ({$agent->email}).");
    }

    // Since these methods are now shared across Admin/GL/TL/Introducer
    // (each reached via their own route prefix), redirects after
    // save need to go back to whichever prefix the current role uses.
    private function myRoutePrefix(): string
    {
        return match (Auth::guard('agent')->user()->role) {
            'ADMIN'        => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER'  => 'tl',
            default        => 'introducer',
        };
    }

    private function tierListing(Request $request, string $role)
    {
        // Joins agent_profiles so address/city/state/postcode are part of
        // the same universal search, not just the agents table columns.
        $query = Agent::where('agents.role', $role)
            ->where('agents.is_deleted', false)
            ->leftJoin('agent_profiles', 'agent_profiles.agent_id', '=', 'agents.agent_id')
            ->select('agents.*');

        // FIXED 17 Aug 2026 — per Chris: Group Leader / Team Leader /
        // Introducer Maintenance now live under Direct Selling Group in
        // the sidebar, so they must only ever list DSG agents — never
        // an Organization Rewards Group's own equivalent tier (e.g.
        // "PVATM") or a Community & Business Enterprise Group's members.
        // An agent with no group_label_id at all is the standard/default
        // DSG case (see GroupIsolationScope's own "no group = standard
        // group" rule) and must still show up here.
        $query->leftJoin('group_labels', 'group_labels.group_label_id', '=', 'agents.group_label_id')
            ->where(function ($q) {
                $q->whereNull('agents.group_label_id')
                  ->orWhere('group_labels.group_type', 'DSG');
            });

        $query = $this->applyDownlineScope($query);

        // Discrete per-field filters (from the "Search To Edit" blank form)
        // — each filled field is combined with AND, matching how a normal
        // multi-field search form is expected to behave.
        $fieldMap = [
            'name'        => 'agents.full_name',
            'agent_code'  => 'agents.agent_code',
            'phone'       => 'agents.phone',
            'email'       => 'agents.email',
            'address'     => 'agent_profiles.address',
            'postcode'    => 'agent_profiles.postcode',
            'city'        => 'agent_profiles.city',
        ];

        $sponsorId  = $request->input('sponsor_id', '');
        $joinedFrom = $request->input('joined_from', '');
        $joinedTo   = $request->input('joined_to', '');
        $stateVal   = $request->input('state', '');

        $hasDiscreteFilter = false;
        foreach ($fieldMap as $key => $column) {
            if ($request->filled($key)) {
                $hasDiscreteFilter = true;
                break;
            }
        }

        $hasFilter = $request->filled('search')
            || $request->filled('status')
            || $sponsorId || $joinedFrom || $joinedTo || $request->filled('recruitment_blocked') || $stateVal
            || $hasDiscreteFilter;

        if ($hasFilter) {
            if ($request->filled('search')) {
                $pattern = $this->wildcardPattern($request->search);
                $query->where(function ($q) use ($pattern) {
                    $q->where('agents.full_name', 'like', $pattern)
                      ->orWhere('agents.agent_code', 'like', $pattern)
                      ->orWhere('agents.email', 'like', $pattern)
                      ->orWhere('agents.phone', 'like', $pattern)
                      ->orWhere('agents.status', 'like', $pattern)
                      ->orWhere('agent_profiles.address', 'like', $pattern)
                      ->orWhere('agent_profiles.city', 'like', $pattern)
                      ->orWhere('agent_profiles.state', 'like', $pattern)
                      ->orWhere('agent_profiles.postcode', 'like', $pattern);
                });
            }
            // Discrete fields — each one ANDed in only if filled.
            foreach ($fieldMap as $key => $column) {
                if ($request->filled($key)) {
                    $query->where($column, 'like', $this->wildcardPattern($request->input($key)));
                }
            }
            if ($stateVal) {
                $query->where('agent_profiles.state', $stateVal);
            }
            if ($request->filled('status')) {
                $query->where('agents.status', $request->status);
            }
            // Sponsor/Upline — picked via the SAME typeahead endpoint
            // Network Drill Down already uses (admin.network.ajax.typeahead,
            // mode=intro), so this matches existing behaviour exactly.
            if ($sponsorId) {
                $query->where('agents.parent_id', $sponsorId);
            }
            // Joined Date (from/to) — same whereDate() pattern as
            // NetworkController::index()/byGL()/byTL(), per spec 21.4's
            // Standard Filter Set for agent/introducer lists.
            if ($joinedFrom) {
                $query->whereDate('agents.created_at', '>=', $joinedFrom);
            }
            if ($joinedTo) {
                $query->whereDate('agents.created_at', '<=', $joinedTo);
            }
            if ($request->filled('recruitment_blocked')) {
                $query->where('agents.recruitment_blocked', (bool) $request->input('recruitment_blocked'));
            }
            // 37.11 note #4 — must order this way for correct 1-1-1, 1-1-2,
            // 1-1-10 sequencing, not plain alphabetical.
            return $query->orderByRaw('LENGTH(agents.agent_code), agents.agent_code')->paginate(10)->withQueryString();
        }

        return collect();
    }

    public function introducerSearchForm()
    {
        // States pulled live from the shared malaysia_postcodes table —
        // same source used by register.blade.php and Vendor forms.
        $states = DB::table('malaysia_postcodes')->select('state')->distinct()->orderBy('state')->pluck('state');
        return view('masterfile.introducer-search-form', compact('states'));
    }

    // -------------------------------------------------------
    // Autocomplete for Phone / Email / Bank Name on the Search To Edit
    // form. Sponsor uses the existing admin.network.ajax.typeahead, and
    // Postcode/City reuse the existing register.postcode-lookup route —
    // this one covers the fields neither of those already handle.
    // -------------------------------------------------------
    public function introducerFieldLookup(Request $request)
    {
        $field = $request->get('field');
        $q     = trim($request->get('q', ''));

        $columnMap = [
            'phone'     => 'agents.phone',
            'email'     => 'agents.email',
        ];

        if (!isset($columnMap[$field]) || $q === '') {
            return response()->json([]);
        }

        $column = $columnMap[$field];

        $results = Agent::where('agents.role', 'INTRODUCER')
            ->where('agents.is_deleted', false)
            ->where($column, 'like', '%' . $q . '%')
            ->orderBy('agents.full_name')
            ->limit(20)
            ->get(['agents.full_name as name', 'agents.agent_code as code', $column . ' as value']);

        return response()->json($results);
    }

    // -------------------------------------------------------
    // VIEW/EDIT — Introducer full record. Locked fields (Full Name,
    // NRIC, Agent Code, Upline, Role, Group) are enforced server-side in
    // introducerUpdate() below, not just hidden in the view — a direct
    // POST cannot bypass them. NRIC/Bank Account shown decrypted per
    // Admin's explicit decision (with the security trade-off already
    // discussed and accepted).
    // -------------------------------------------------------
    public function introducerShow(string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $this->assertInScope($agent);
        $profile = DB::table('agent_profiles')->where('agent_id', $id)->first();

        // NRIC and bank details are NEVER stored on the agent record —
        // removed per confirmed legal/privacy requirement (06 Jul 2026).

        $sponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $group   = $agent->group_id ? DB::table('groups')->where('group_id', $agent->group_id)->first() : null;
        $createdByAgent = $agent->created_by ? Agent::find($agent->created_by) : null;
        $updatedByAgent = $agent->updated_by ? Agent::find($agent->updated_by) : null;

        $states = DB::table('malaysia_postcodes')->select('state')->distinct()->orderBy('state')->pluck('state');

        // For the inline Status pencil-editor.
        $activeDownlineCount = Agent::where('parent_id', $id)->where('status', 'ACTIVE')->where('is_deleted', false)->count();
        $inactiveReasons = DB::table('reason_codes')->where('category', 'TERMINATION')->where('is_active', true)->orderBy('description')->get();

        // For the Role History + Request Undo section (4-eye approval).
        $latestRoleHistory = DB::table('role_history')->where('agent_id', $id)->orderByDesc('effective_date')->first();
        $hasPendingUndo = DB::table('pending_approvals')
            ->where('action_type', 'UNDO_ROLE_CHANGE')
            ->where('target_agent_id', $id)
            ->where('status', 'PENDING')
            ->exists();

        // Read-only QR preview for Admin — same permanent token as the
        // person's own Profile page, never regenerated.
        $qrUrl = url('/register?ref=' . $agent->qr_code_token);

        return view('masterfile.introducer-edit', compact('agent', 'profile', 'sponsor', 'group', 'states', 'createdByAgent', 'updatedByAgent', 'qrUrl', 'activeDownlineCount', 'inactiveReasons', 'latestRoleHistory', 'hasPendingUndo'));
    }

    public function introducerUpdate(Request $request, string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $this->assertInScope($agent);
        $profile = DB::table('agent_profiles')->where('agent_id', $id)->first();

        // Server-side enforcement of locked fields — these are simply
        // never read from the request, regardless of what's submitted.
        $request->validate([
            'second_name'  => ['nullable', 'string', 'max:200'],
            'phone'        => ['required', 'string', PhoneNumberService::rule()],
            'email'        => ['required', 'email', 'max:200'],
            'address'      => ['required', 'string'],
            'postcode'     => ['required', 'string', 'max:10'],
            'city'         => ['required', 'string', 'max:100'],
            'state'        => ['required', 'string', 'max:100'],
            'photo'        => ['nullable', 'image', 'max:2048'],
            // Status is optional — only present if the inline pencil
            // editor was actually used this save.
            'new_status'      => ['nullable', 'in:ACTIVE,INACTIVE'],
            'reason_code_id'  => ['required_if:new_status,INACTIVE', 'nullable', 'exists:reason_codes,reason_code_id'],
            'reason_notes'    => ['nullable', 'string', 'max:1000'],
        ]);

        // Status change — same downline-block rule as before, just now
        // part of the single consolidated save instead of a separate page.
        $statusChanged = false;
        if ($request->filled('new_status') && $request->new_status !== $agent->status) {
            if ($request->new_status === 'INACTIVE') {
                $activeDownlineCount = Agent::where('parent_id', $id)
                    ->where('status', 'ACTIVE')
                    ->where('is_deleted', false)
                    ->count();
                if ($activeDownlineCount > 0) {
                    return back()->withErrors([
                        'new_status' => "Cannot set Inactive — this Introducer has {$activeDownlineCount} active sub-Introducer(s). Reassign or set them Inactive first."
                    ])->withInput();
                }
            }
            $statusChanged = true;
        }

        $before = (array) $agent;

        $agent->phone = PhoneNumberService::normalize($request->phone);
        $agent->second_name = $request->second_name ?: null;

        // Status changes NO LONGER apply immediately — per confirmed
        // 4-eye policy, they go through ApprovalService instead (see
        // below). Only OTHER fields save right away, as before.

        // Email — safer flow, matching public registration: if changed,
        // store as pending_email + send a verification link. The real
        // agents.email column is untouched until that link is clicked.
        $emailChanged = strtolower($request->email) !== strtolower($agent->email);
        if ($emailChanged) {
            $newEmailTaken = Agent::where('email', strtolower($request->email))
                ->where('agent_id', '!=', $agent->agent_id)
                ->exists();
            if ($newEmailTaken) {
                return back()->withErrors(['email' => 'This email is already used by another account.'])->withInput();
            }
            $agent->pending_email = strtolower($request->email);
            $agent->pending_email_token = Str::random(64);
            $this->sendEmailChangeVerification($agent);
        }

        $agent->save();

        // Photo — ADMIN-ONLY, even when editing a downline record.
        // GL/TL/Introducer have edit access to their own downline's
        // other fields, but photo stays self-service-only for everyone
        // except Admin (confirmed decision — this is intentionally
        // narrower than the "same as Admin" rule that applies elsewhere).
        $photoPath = $profile->photo_path ?? null;
        $photoChanged = false;
        if ($request->hasFile('photo') && Auth::guard('agent')->user()->role === 'ADMIN') {
            if ($photoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('profile-pictures', 'public');
            $photoChanged = true;
        }

        if ($photoChanged) {
            AuditService::logChange('agent_profiles', $agent->agent_id, 'PHOTO_UPDATE', ['photo_path' => $profile->photo_path ?? null], ['photo_path' => $photoPath, 'updated_by_admin' => Auth::guard('agent')->user()->full_name]);
        }

        DB::table('agent_profiles')->updateOrInsert(
            ['agent_id' => $agent->agent_id],
            [
                'address'    => $request->address,
                'postcode'   => $request->postcode,
                'city'       => $request->city,
                'state'      => $request->state,
                'photo_path' => $photoPath,
                'updated_at' => now(),
            ]
        );


        AuditService::logChange('agents', $agent->agent_id, 'PROFILE_UPDATE', $before, $request->all());

        $statusApprovalSubmitted = false;
        if ($statusChanged) {
            // 4-eye policy — status change does NOT apply yet. A request
            // is created; a DIFFERENT Admin must approve it before the
            // status actually changes on the live record.
            app(\App\Services\ApprovalService::class)->requestApproval(
                'STATUS_CHANGE',
                $agent->agent_id,
                ['new_status' => $request->new_status],
                Auth::guard('agent')->id(),
                $request->reason_code_id,
                $request->reason_notes
            );
            $statusApprovalSubmitted = true;
        }

        // Broad, group-wide notification on EVERY save (any editable
        // field), per confirmed policy: direct sponsor + every
        // Introducer in the Group + every Team Leader in the Group +
        // the Group Leader + Admin — both email and in-app bell. Keeps
        // the whole group informed for effective communication/teamwork.
        $changedFields = array_keys(array_diff_assoc(
            ['phone' => PhoneNumberService::normalize($request->phone), 'address' => $request->address, 'postcode' => $request->postcode, 'city' => $request->city, 'state' => $request->state],
            ['phone' => $before['phone'], 'address' => $profile->address ?? null, 'postcode' => $profile->postcode ?? null, 'city' => $profile->city ?? null, 'state' => $profile->state ?? null]
        ));
        if ($statusChanged) $changedFields[] = 'status';
        if ($emailChanged) $changedFields[] = 'email (pending verification)';

        $notifSponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $recipients = app(\App\Services\NotificationService::class)->recipientsForGroupBroadcast($agent, $notifSponsor);
        app(\App\Services\NotificationService::class)->notify(
            $recipients,
            'PROFILE_UPDATE',
            'Profile Updated',
            "{$agent->full_name} ({$agent->agent_code})'s profile was updated. Changed: " . (count($changedFields) ? implode(', ', $changedFields) : 'details') . '.',
            $agent->agent_id,
            $statusChanged ? $request->reason_code_id : null,
            $statusChanged ? $request->reason_notes : null
        );

        $msg = 'Profile updated successfully.';
        if ($emailChanged) {
            $msg = 'Profile updated. A verification link was sent to the new email — it will not become the login email until confirmed.';
        }
        if ($statusApprovalSubmitted) {
            $msg .= ' Status change submitted — a different Admin must approve it before it takes effect.';
        }

        // NEW 23 Jul 2026 — per Chris: if we arrived via a `?back=` link
        // (e.g. the Organization Rewards Group Audit View), return there
        // instead of the generic Introducer Maintenance index.
        $backUrl = $request->input('back') ? urldecode($request->input('back')) : route($this->myRoutePrefix() . '.masterfile.introducers');
        return redirect($backUrl)->with('success', $msg);
    }

    private function sendEmailChangeVerification(Agent $agent): void
    {
        $link = route('auth.verify-email-change', $agent->pending_email_token);
        Mail::raw(
            "Hi {$agent->full_name},\n\nA request was made to change your GeneralLink login email to {$agent->pending_email}.\n\nIf this was you, confirm it here:\n{$link}\n\nThis link expires in 24 hours. If you didn't request this, you can ignore this email — your current login email stays unchanged.",
            function ($message) use ($agent) {
                $message->to($agent->pending_email)
                        ->subject('GeneralLink — Confirm Your New Email');
            }
        );
    }

    // -------------------------------------------------------
    // STATUS UPDATE — Active/Inactive/Terminated, with reason code +
    // optional notes, and the downline-block rule: cannot Terminate
    // someone who still has active sub-Introducers under them (Decision 1).
    // -------------------------------------------------------
    public function introducerStatusForm(string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();

        $activeDownlineCount = Agent::where('parent_id', $id)
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->count();

        $terminationReasons = DB::table('reason_codes')
            ->where('category', 'TERMINATION')
            ->where('is_active', true)
            ->orderBy('description')
            ->get();

        return view('masterfile.introducer-status', compact('agent', 'activeDownlineCount', 'terminationReasons'));
    }

    public function introducerStatusUpdate(Request $request, string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();

        $request->validate([
            'new_status' => ['required', 'in:ACTIVE,INACTIVE,TERMINATED'],
            'reason_code_id' => ['required_if:new_status,TERMINATED', 'nullable', 'exists:reason_codes,reason_code_id'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->new_status === 'TERMINATED') {
            $activeDownlineCount = Agent::where('parent_id', $id)
                ->where('status', 'ACTIVE')
                ->where('is_deleted', false)
                ->count();

            if ($activeDownlineCount > 0) {
                return back()->withErrors([
                    'new_status' => "Cannot Terminate — this Introducer has {$activeDownlineCount} active sub-Introducer(s). Reassign or terminate them first."
                ]);
            }
        }

        $before = ['status' => $agent->status];
        $agent->update(['status' => $request->new_status]);

        AuditService::logChange('agents', $agent->agent_id, 'STATUS_CHANGE', $before, ['status' => $request->new_status]);

        // Record the reason separately with dedicated columns (per our
        // reason_codes design), not buried in the generic before/after JSON.
        if ($request->filled('reason_code_id') || $request->filled('reason_notes')) {
            DB::table('audit_logs')->where('table_name', 'agents')
                ->where('record_id', $agent->agent_id)
                ->where('action', 'STATUS_CHANGE')
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'reason_code_id' => $request->reason_code_id,
                    'reason_notes'   => $request->reason_notes,
                ]);
        }

        return redirect()->route('admin.masterfile.introducers')->with('success', "Status updated to {$request->new_status}.");
    }

    // =========================================================
    // TEAM LEADER MAINTENANCE — exact same pattern as Introducer above,
    // adapted only for role=TEAM_LEADER and its own routes/views.
    // =========================================================
    public function teamLeaderSearchForm()
    {
        $states = DB::table('malaysia_postcodes')->select('state')->distinct()->orderBy('state')->pluck('state');
        return view('masterfile.team-leader-search-form', compact('states'));
    }

    public function teamLeaderFieldLookup(Request $request)
    {
        $field = $request->get('field');
        $q     = trim($request->get('q', ''));

        $columnMap = [
            'phone'     => 'agents.phone',
            'email'     => 'agents.email',
        ];

        if (!isset($columnMap[$field]) || $q === '') {
            return response()->json([]);
        }

        $column = $columnMap[$field];

        $results = Agent::where('agents.role', 'TEAM_LEADER')
            ->where('agents.is_deleted', false)
            ->where($column, 'like', '%' . $q . '%')
            ->orderBy('agents.full_name')
            ->limit(20)
            ->get(['agents.full_name as name', 'agents.agent_code as code', $column . ' as value']);

        return response()->json($results);
    }

    public function teamLeaderShow(string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $this->assertInScope($agent);
        $profile = DB::table('agent_profiles')->where('agent_id', $id)->first();

        // NRIC and bank details are NEVER stored on the agent record —
        // removed per confirmed legal/privacy requirement (06 Jul 2026).

        $sponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $group   = $agent->group_id ? DB::table('groups')->where('group_id', $agent->group_id)->first() : null;
        $createdByAgent = $agent->created_by ? Agent::find($agent->created_by) : null;
        $updatedByAgent = $agent->updated_by ? Agent::find($agent->updated_by) : null;

        $states = DB::table('malaysia_postcodes')->select('state')->distinct()->orderBy('state')->pluck('state');

        $activeDownlineCount = Agent::where('parent_id', $id)->where('status', 'ACTIVE')->where('is_deleted', false)->count();
        $inactiveReasons = DB::table('reason_codes')->where('category', 'TERMINATION')->where('is_active', true)->orderBy('description')->get();

        $latestRoleHistory = DB::table('role_history')->where('agent_id', $id)->orderByDesc('effective_date')->first();
        $hasPendingUndo = DB::table('pending_approvals')
            ->where('action_type', 'UNDO_ROLE_CHANGE')
            ->where('target_agent_id', $id)
            ->where('status', 'PENDING')
            ->exists();

        $qrUrl = url('/register?ref=' . $agent->qr_code_token);

        return view('masterfile.team-leader-edit', compact('agent', 'profile', 'sponsor', 'group', 'states', 'createdByAgent', 'updatedByAgent', 'qrUrl', 'activeDownlineCount', 'inactiveReasons', 'latestRoleHistory', 'hasPendingUndo'));
    }

    public function teamLeaderUpdate(Request $request, string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $this->assertInScope($agent);
        $profile = DB::table('agent_profiles')->where('agent_id', $id)->first();

        $request->validate([
            'second_name'  => ['nullable', 'string', 'max:200'],
            'phone'        => ['required', 'string', PhoneNumberService::rule()],
            'email'        => ['required', 'email', 'max:200'],
            'address'      => ['required', 'string'],
            'postcode'     => ['required', 'string', 'max:10'],
            'city'         => ['required', 'string', 'max:100'],
            'state'        => ['required', 'string', 'max:100'],
            'new_status'      => ['nullable', 'in:ACTIVE,INACTIVE'],
            'reason_code_id'  => ['required_if:new_status,INACTIVE', 'nullable', 'exists:reason_codes,reason_code_id'],
            'reason_notes'    => ['nullable', 'string', 'max:1000'],
            'photo'           => ['nullable', 'image', 'max:2048'],
        ]);

        $statusChanged = false;
        if ($request->filled('new_status') && $request->new_status !== $agent->status) {
            if ($request->new_status === 'INACTIVE') {
                $activeDownlineCount = Agent::where('parent_id', $id)
                    ->where('status', 'ACTIVE')
                    ->where('is_deleted', false)
                    ->count();
                if ($activeDownlineCount > 0) {
                    return back()->withErrors([
                        'new_status' => "Cannot set Inactive — this Team Leader has {$activeDownlineCount} active downline record(s). Reassign or set them Inactive first."
                    ])->withInput();
                }
            }
            $statusChanged = true;
        }

        $before = (array) $agent;

        $agent->phone = PhoneNumberService::normalize($request->phone);
        $agent->second_name = $request->second_name ?: null;

        // Status changes NO LONGER apply immediately — per confirmed
        // 4-eye policy, they go through ApprovalService instead (see
        // below). Only OTHER fields save right away, as before.

        // Photo — ADMIN-ONLY, same rule as Introducer.
        $photoPath = $profile->photo_path ?? null;
        $photoChanged = false;
        if ($request->hasFile('photo') && Auth::guard('agent')->user()->role === 'ADMIN') {
            if ($photoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('profile-pictures', 'public');
            $photoChanged = true;
        }
        if ($photoChanged) {
            AuditService::logChange('agent_profiles', $agent->agent_id, 'PHOTO_UPDATE', ['photo_path' => $profile->photo_path ?? null], ['photo_path' => $photoPath, 'updated_by_admin' => Auth::guard('agent')->user()->full_name]);
        }


        $emailChanged = strtolower($request->email) !== strtolower($agent->email);
        if ($emailChanged) {
            $newEmailTaken = Agent::where('email', strtolower($request->email))
                ->where('agent_id', '!=', $agent->agent_id)
                ->exists();
            if ($newEmailTaken) {
                return back()->withErrors(['email' => 'This email is already used by another account.'])->withInput();
            }
            $agent->pending_email = strtolower($request->email);
            $agent->pending_email_token = Str::random(64);
            $this->sendEmailChangeVerification($agent);
        }

        $agent->save();

        DB::table('agent_profiles')->updateOrInsert(
            ['agent_id' => $agent->agent_id],
            [
                'address'    => $request->address,
                'postcode'   => $request->postcode,
                'city'       => $request->city,
                'state'      => $request->state,
                'photo_path' => $photoPath,
                'updated_at' => now(),
            ]
        );


        AuditService::logChange('agents', $agent->agent_id, 'PROFILE_UPDATE', $before, $request->all());

        $statusApprovalSubmitted = false;
        if ($statusChanged) {
            // 4-eye policy — status change does NOT apply yet. A request
            // is created; a DIFFERENT Admin must approve it before the
            // status actually changes on the live record.
            app(\App\Services\ApprovalService::class)->requestApproval(
                'STATUS_CHANGE',
                $agent->agent_id,
                ['new_status' => $request->new_status],
                Auth::guard('agent')->id(),
                $request->reason_code_id,
                $request->reason_notes
            );
            $statusApprovalSubmitted = true;
        }

        // Broad, group-wide notification on EVERY save, per confirmed
        // policy — same rule as Introducer.
        $changedFields = array_keys(array_diff_assoc(
            ['phone' => PhoneNumberService::normalize($request->phone), 'address' => $request->address, 'postcode' => $request->postcode, 'city' => $request->city, 'state' => $request->state],
            ['phone' => $before['phone'], 'address' => $profile->address ?? null, 'postcode' => $profile->postcode ?? null, 'city' => $profile->city ?? null, 'state' => $profile->state ?? null]
        ));
        if ($statusChanged) $changedFields[] = 'status';
        if ($emailChanged) $changedFields[] = 'email (pending verification)';

        $notifSponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $recipients = app(\App\Services\NotificationService::class)->recipientsForGroupBroadcast($agent, $notifSponsor);
        app(\App\Services\NotificationService::class)->notify(
            $recipients,
            'PROFILE_UPDATE',
            'Profile Updated',
            "{$agent->full_name} ({$agent->agent_code})'s profile was updated. Changed: " . (count($changedFields) ? implode(', ', $changedFields) : 'details') . '.',
            $agent->agent_id,
            $statusChanged ? $request->reason_code_id : null,
            $statusChanged ? $request->reason_notes : null
        );

        $msg = 'Profile updated successfully.';
        if ($emailChanged) {
            $msg = 'Profile updated. A verification link was sent to the new email — it will not become the login email until confirmed.';
        }
        if ($statusApprovalSubmitted) {
            $msg .= ' Status change submitted — a different Admin must approve it before it takes effect.';
        }

        // NEW 23 Jul 2026 — per Chris: if we arrived via a `?back=` link
        // (e.g. the Organization Rewards Group Audit View), return there
        // instead of the generic Team Leader Maintenance index.
        $backUrl = $request->input('back') ? urldecode($request->input('back')) : route($this->myRoutePrefix() . '.masterfile.team-leaders');
        return redirect($backUrl)->with('success', $msg);
    }

    public function teamLeaderStatusForm(string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();

        $activeDownlineCount = Agent::where('parent_id', $id)
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->count();

        $terminationReasons = DB::table('reason_codes')
            ->where('category', 'TERMINATION')
            ->where('is_active', true)
            ->orderBy('description')
            ->get();

        return view('masterfile.team-leader-status', compact('agent', 'activeDownlineCount', 'terminationReasons'));
    }

    public function teamLeaderStatusUpdate(Request $request, string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();

        $request->validate([
            'new_status' => ['required', 'in:ACTIVE,INACTIVE,TERMINATED'],
            'reason_code_id' => ['required_if:new_status,TERMINATED', 'nullable', 'exists:reason_codes,reason_code_id'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->new_status === 'TERMINATED') {
            $activeDownlineCount = Agent::where('parent_id', $id)
                ->where('status', 'ACTIVE')
                ->where('is_deleted', false)
                ->count();

            if ($activeDownlineCount > 0) {
                return back()->withErrors([
                    'new_status' => "Cannot Terminate — this Team Leader has {$activeDownlineCount} active downline record(s). Reassign or terminate them first."
                ]);
            }
        }

        $before = ['status' => $agent->status];
        $agent->update(['status' => $request->new_status]);

        AuditService::logChange('agents', $agent->agent_id, 'STATUS_CHANGE', $before, ['status' => $request->new_status]);

        if ($request->filled('reason_code_id') || $request->filled('reason_notes')) {
            DB::table('audit_logs')->where('table_name', 'agents')
                ->where('record_id', $agent->agent_id)
                ->where('action', 'STATUS_CHANGE')
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'reason_code_id' => $request->reason_code_id,
                    'reason_notes'   => $request->reason_notes,
                ]);
        }

        return redirect()->route('admin.masterfile.team-leaders')->with('success', "Status updated to {$request->new_status}.");
    }

    public function introducers(Request $request)
    {
        $introducers = $this->tierListing($request, 'INTRODUCER');
        return view('masterfile.introducers', compact('introducers'));
    }

    public function teamLeaders(Request $request)
    {
        $teamLeaders = $this->tierListing($request, 'TEAM_LEADER');
        return view('masterfile.team-leaders', compact('teamLeaders'));
    }

    public function groupLeaders(Request $request)
    {
        $groupLeaders = $this->tierListing($request, 'GROUP_LEADER');
        return view('masterfile.group-leaders', compact('groupLeaders'));
    }

    // =========================================================
    // GROUP LEADER MAINTENANCE — exact same pattern as Introducer/TL
    // above, adapted only for role=GROUP_LEADER and its own routes/views.
    // =========================================================
    public function groupLeaderSearchForm()
    {
        $states = DB::table('malaysia_postcodes')->select('state')->distinct()->orderBy('state')->pluck('state');
        return view('masterfile.group-leader-search-form', compact('states'));
    }

    public function groupLeaderFieldLookup(Request $request)
    {
        $field = $request->get('field');
        $q     = trim($request->get('q', ''));

        $columnMap = [
            'phone'     => 'agents.phone',
            'email'     => 'agents.email',
        ];

        if (!isset($columnMap[$field]) || $q === '') {
            return response()->json([]);
        }

        $column = $columnMap[$field];

        $results = Agent::where('agents.role', 'GROUP_LEADER')
            ->where('agents.is_deleted', false)
            ->where($column, 'like', '%' . $q . '%')
            ->orderBy('agents.full_name')
            ->limit(20)
            ->get(['agents.full_name as name', 'agents.agent_code as code', $column . ' as value']);

        return response()->json($results);
    }

    public function groupLeaderShow(string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $this->assertInScope($agent);
        $profile = DB::table('agent_profiles')->where('agent_id', $id)->first();

        // NRIC and bank details are NEVER stored on the agent record —
        // removed per confirmed legal/privacy requirement (06 Jul 2026).

        $sponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $group   = $agent->group_id ? DB::table('groups')->where('group_id', $agent->group_id)->first() : null;
        $createdByAgent = $agent->created_by ? Agent::find($agent->created_by) : null;
        $updatedByAgent = $agent->updated_by ? Agent::find($agent->updated_by) : null;

        $states = DB::table('malaysia_postcodes')->select('state')->distinct()->orderBy('state')->pluck('state');

        $activeDownlineCount = Agent::where('parent_id', $id)->where('status', 'ACTIVE')->where('is_deleted', false)->count();
        $inactiveReasons = DB::table('reason_codes')->where('category', 'TERMINATION')->where('is_active', true)->orderBy('description')->get();

        $latestRoleHistory = DB::table('role_history')->where('agent_id', $id)->orderByDesc('effective_date')->first();
        $hasPendingUndo = DB::table('pending_approvals')
            ->where('action_type', 'UNDO_ROLE_CHANGE')
            ->where('target_agent_id', $id)
            ->where('status', 'PENDING')
            ->exists();

        $qrUrl = url('/register?ref=' . $agent->qr_code_token);

        return view('masterfile.group-leader-edit', compact('agent', 'profile', 'sponsor', 'group', 'states', 'createdByAgent', 'updatedByAgent', 'qrUrl', 'activeDownlineCount', 'inactiveReasons', 'latestRoleHistory', 'hasPendingUndo'));
    }

    public function groupLeaderUpdate(Request $request, string $id)
    {
        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();
        $this->assertInScope($agent);
        $profile = DB::table('agent_profiles')->where('agent_id', $id)->first();

        $request->validate([
            'second_name'  => ['nullable', 'string', 'max:200'],
            'phone'        => ['required', 'string', PhoneNumberService::rule()],
            'email'        => ['required', 'email', 'max:200'],
            'address'      => ['required', 'string'],
            'postcode'     => ['required', 'string', 'max:10'],
            'city'         => ['required', 'string', 'max:100'],
            'state'        => ['required', 'string', 'max:100'],
            'new_status'      => ['nullable', 'in:ACTIVE,INACTIVE'],
            'reason_code_id'  => ['required_if:new_status,INACTIVE', 'nullable', 'exists:reason_codes,reason_code_id'],
            'reason_notes'    => ['nullable', 'string', 'max:1000'],
            'photo'           => ['nullable', 'image', 'max:2048'],
        ]);

        $statusChanged = false;
        if ($request->filled('new_status') && $request->new_status !== $agent->status) {
            if ($request->new_status === 'INACTIVE') {
                $activeDownlineCount = Agent::where('parent_id', $id)
                    ->where('status', 'ACTIVE')
                    ->where('is_deleted', false)
                    ->count();
                if ($activeDownlineCount > 0) {
                    return back()->withErrors([
                        'new_status' => "Cannot set Inactive — this Group Leader has {$activeDownlineCount} active downline record(s). Reassign or set them Inactive first."
                    ])->withInput();
                }
            }
            $statusChanged = true;
        }

        $before = (array) $agent;

        $agent->phone = PhoneNumberService::normalize($request->phone);
        $agent->second_name = $request->second_name ?: null;

        // Status changes NO LONGER apply immediately — per confirmed
        // 4-eye policy, they go through ApprovalService instead (see
        // below). Only OTHER fields save right away, as before.

        // Photo — ADMIN-ONLY, same rule as Introducer/TL.
        $photoPath = $profile->photo_path ?? null;
        $photoChanged = false;
        if ($request->hasFile('photo') && Auth::guard('agent')->user()->role === 'ADMIN') {
            if ($photoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('profile-pictures', 'public');
            $photoChanged = true;
        }
        if ($photoChanged) {
            AuditService::logChange('agent_profiles', $agent->agent_id, 'PHOTO_UPDATE', ['photo_path' => $profile->photo_path ?? null], ['photo_path' => $photoPath, 'updated_by_admin' => Auth::guard('agent')->user()->full_name]);
        }


        $emailChanged = strtolower($request->email) !== strtolower($agent->email);
        if ($emailChanged) {
            $newEmailTaken = Agent::where('email', strtolower($request->email))
                ->where('agent_id', '!=', $agent->agent_id)
                ->exists();
            if ($newEmailTaken) {
                return back()->withErrors(['email' => 'This email is already used by another account.'])->withInput();
            }
            $agent->pending_email = strtolower($request->email);
            $agent->pending_email_token = Str::random(64);
            $this->sendEmailChangeVerification($agent);
        }

        $agent->save();

        DB::table('agent_profiles')->updateOrInsert(
            ['agent_id' => $agent->agent_id],
            [
                'address'    => $request->address,
                'postcode'   => $request->postcode,
                'city'       => $request->city,
                'state'      => $request->state,
                'photo_path' => $photoPath,
                'updated_at' => now(),
            ]
        );


        AuditService::logChange('agents', $agent->agent_id, 'PROFILE_UPDATE', $before, $request->all());

        $statusApprovalSubmitted = false;
        if ($statusChanged) {
            // 4-eye policy — status change does NOT apply yet. A request
            // is created; a DIFFERENT Admin must approve it before the
            // status actually changes on the live record.
            app(\App\Services\ApprovalService::class)->requestApproval(
                'STATUS_CHANGE',
                $agent->agent_id,
                ['new_status' => $request->new_status],
                Auth::guard('agent')->id(),
                $request->reason_code_id,
                $request->reason_notes
            );
            $statusApprovalSubmitted = true;
        }

        // Broad, group-wide notification on EVERY save, per confirmed
        // policy — same rule as Introducer/TL.
        $changedFields = array_keys(array_diff_assoc(
            ['phone' => PhoneNumberService::normalize($request->phone), 'address' => $request->address, 'postcode' => $request->postcode, 'city' => $request->city, 'state' => $request->state],
            ['phone' => $before['phone'], 'address' => $profile->address ?? null, 'postcode' => $profile->postcode ?? null, 'city' => $profile->city ?? null, 'state' => $profile->state ?? null]
        ));
        if ($statusChanged) $changedFields[] = 'status';
        if ($emailChanged) $changedFields[] = 'email (pending verification)';

        $notifSponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $recipients = app(\App\Services\NotificationService::class)->recipientsForGroupBroadcast($agent, $notifSponsor);
        app(\App\Services\NotificationService::class)->notify(
            $recipients,
            'PROFILE_UPDATE',
            'Profile Updated',
            "{$agent->full_name} ({$agent->agent_code})'s profile was updated. Changed: " . (count($changedFields) ? implode(', ', $changedFields) : 'details') . '.',
            $agent->agent_id,
            $statusChanged ? $request->reason_code_id : null,
            $statusChanged ? $request->reason_notes : null
        );

        $msg = 'Profile updated successfully.';
        if ($emailChanged) {
            $msg = 'Profile updated. A verification link was sent to the new email — it will not become the login email until confirmed.';
        }
        if ($statusApprovalSubmitted) {
            $msg .= ' Status change submitted — a different Admin must approve it before it takes effect.';
        }

        // NEW 23 Jul 2026 — per Chris: if we arrived via a `?back=` link
        // (e.g. the Organization Rewards Group Audit View), return there
        // instead of the generic Group Leader Maintenance index.
        $backUrl = $request->input('back') ? urldecode($request->input('back')) : route($this->myRoutePrefix() . '.masterfile.group-leaders');
        return redirect($backUrl)->with('success', $msg);
    }

    // =========================================================
    // AUDIT LOG VIEWER — Admin-only. Shows every change already being
    // recorded by AuditService::logChange() throughout the system
    // (before/after values, date/time, who made the change).
    // =========================================================
    public function auditLogs(Request $request)
    {
        // NEW 10 Aug 2026 — per Chris: a new Admin should be able to trace
        // exactly who created/changed a specific record (e.g. a Video
        // Library entry) even after the original Admin who did it has
        // left the company. record_id lets other screens deep-link
        // straight to ONE record's full history instead of Admin having
        // to scroll every audit entry for that whole table by eye.
        $hasAnyFilter = $request->filled('agent_name') || $request->filled('table_name')
            || $request->filled('action') || $request->filled('date_from') || $request->filled('date_to')
            || $request->filled('record_id');

        $logs = null;
        if ($hasAnyFilter) {
            $query = DB::table('audit_logs')
                ->leftJoin('agents', 'agents.agent_id', '=', 'audit_logs.agent_id')
                ->leftJoin('reason_codes', 'reason_codes.reason_code_id', '=', 'audit_logs.reason_code_id')
                ->select(
                    'audit_logs.*',
                    'agents.full_name as changed_by_name',
                    'agents.agent_code as changed_by_code',
                    'reason_codes.description as reason_description'
                );

            if ($request->filled('agent_name')) {
                $query->where('agents.full_name', 'like', '%' . $request->agent_name . '%');
            }
            if ($request->filled('table_name')) {
                $query->where('audit_logs.table_name', $request->table_name);
            }
            if ($request->filled('action')) {
                $query->where('audit_logs.action', $request->action);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('audit_logs.created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('audit_logs.created_at', '<=', $request->date_to);
            }
            if ($request->filled('record_id')) {
                $query->where('audit_logs.record_id', $request->record_id);
            }

            $logs = $query->orderByDesc('audit_logs.created_at')->paginate(10)->withQueryString();
        }

        // Distinct values for the filter dropdowns, so Admin picks from
        // what actually exists rather than typing free text.
        $tableNames = DB::table('audit_logs')->select('table_name')->distinct()->orderBy('table_name')->pluck('table_name');
        $actions = DB::table('audit_logs')->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('masterfile.audit-logs', compact('logs', 'hasAnyFilter', 'tableNames', 'actions'));
    }

    // -------------------------------------------------------
    // PRODUCTS
    // -------------------------------------------------------
    public function products(Request $request)
    {
        $mode            = $request->get('mode', 'main');
        $products        = null;
        $selectedProduct = null;
        $vendors         = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name', 'vendor_code']);

        if ($mode === 'search' && $request->has('do_search')) {
            $query = DB::table('products as p')
                ->leftJoin('vendors as v', DB::raw('p.vendor_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.vendor_id COLLATE utf8mb4_unicode_ci'))
                ->select('p.*', 'v.vendor_name', 'v.vendor_code');

            if ($request->filled('product_name'))  $query->where('p.product_name',  'like', '%'.$request->product_name.'%');
            if ($request->filled('product_code'))  $query->where('p.product_code',  'like', '%'.$request->product_code.'%');
            if ($request->filled('vendor_id'))     $query->where('p.vendor_id',     $request->vendor_id);
            if ($request->filled('product_type'))  $query->where('p.product_type',  $request->product_type);
            if ($request->filled('is_active'))     $query->where('p.is_active',     $request->is_active);

            // Smaller page size so a full page always fits the visible
            // area without scrolling — Prev/Next handles the rest.
            $products = $query->orderBy('p.product_name')->paginate(7)->withQueryString();
        }

        if ($mode === 'edit' && $request->filled('product_id')) {
            $selectedProduct = DB::table('products as p')
                ->leftJoin('vendors as v', DB::raw('p.vendor_id COLLATE utf8mb4_unicode_ci'), '=', DB::raw('v.vendor_id COLLATE utf8mb4_unicode_ci'))
                ->where('p.product_id', $request->product_id)
                ->first(['p.*', 'v.vendor_name', 'v.vendor_code']);
        }

        return view('masterfile.products', compact('mode', 'products', 'selectedProduct', 'vendors'));
    }

    public function storeProduct(Request $request)
    {
        $request->validate([
            'vendor_id'      => ['required', 'exists:vendors,vendor_id'],
            'product_name'   => ['required', 'string', 'max:200'],
            'product_code'   => ['required', 'string', 'max:20', 'unique:products,product_code'],
            'product_type'   => ['required', 'in:MOTOR,PERSONAL_ACCIDENT,FIRE,OTHER'],
            'campaign_start' => ['nullable', 'date'],
            'campaign_end'   => ['nullable', 'date', 'after:campaign_start'],
        ]);
        $id = Str::uuid()->toString();
        DB::table('products')->insert([
            'product_id'     => $id,
            'vendor_id'      => $request->vendor_id,
            'product_name'   => $request->product_name,
            'product_code'   => strtoupper($request->product_code),
            'product_type'   => $request->product_type,
            'description'    => $request->description,
            'campaign_start' => $request->campaign_start,
            'campaign_end'   => $request->campaign_end,
            'is_active'      => true,
            'created_by'     => Auth::guard('agent')->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        AuditService::logChange('products', $id, 'PRODUCT_CREATED', null, $request->all());
        return redirect()->route('admin.masterfile.products', ['mode' => 'main'])->with('success', 'Product added successfully.');
    }

    public function updateProduct(Request $request, string $id)
    {
        $request->validate([
            'product_name'   => ['required', 'string', 'max:200'],
            'product_type'   => ['required', 'in:MOTOR,PERSONAL_ACCIDENT,FIRE,OTHER'],
            'is_active'      => ['required', 'in:0,1'],
            'campaign_start' => ['nullable', 'date'],
            'campaign_end'   => ['nullable', 'date', 'after:campaign_start'],
        ]);
        $before = DB::table('products')->where('product_id', $id)->first();
        DB::table('products')->where('product_id', $id)->update([
            'product_name'   => $request->product_name,
            'product_type'   => $request->product_type,
            'description'    => $request->description,
            'campaign_start' => $request->campaign_start,
            'campaign_end'   => $request->campaign_end,
            'is_active'      => $request->is_active,
            'updated_by'     => Auth::guard('agent')->id(),
            'updated_at'     => now(),
        ]);
        AuditService::logChange('products', $id, 'PRODUCT_UPDATED', (array)$before, $request->all());
        $backUrl = $request->filled('back') ? urldecode($request->back) : route('admin.masterfile.products', ['mode' => 'search']);
        return redirect($backUrl)->with('success', 'Product updated successfully.');
    }

    public function toggleProduct(string $id)
    {
        $p = DB::table('products')->where('product_id', $id)->first();
        DB::table('products')->where('product_id', $id)->update([
            'is_active'  => !$p->is_active,
            'updated_at' => now(),
        ]);
        AuditService::logChange('products', $id, 'PRODUCT_STATUS_TOGGLED', ['is_active' => $p->is_active], ['is_active' => !$p->is_active]);
        return back()->with('success', 'Product status updated.');
    }

    // -------------------------------------------------------
    // COMMISSION STRUCTURES
    // -------------------------------------------------------
    // NEW 24 Jul 2026 — Vendor/Product typeahead for Earning Income
    // Structures screen. Chris: plain dropdowns can't be searched by
    // typing; replace with the same debounced typeahead box pattern
    // already used on Renewal Forecast.
    public function commissionVendorTypeahead(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') return response()->json([]);

        $vendors = DB::table('vendors')
            ->where('is_active', true)
            ->where('vendor_name', 'like', '%' . $q . '%')
            ->orderBy('vendor_name')
            ->limit(15)
            ->get(['vendor_id', 'vendor_name']);

        return response()->json($vendors);
    }

    public function commissionProductTypeahead(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $vendorId = trim((string) $request->get('vendor_id', ''));
        if ($q === '') return response()->json([]);

        $query = DB::table('products')
            ->where('is_active', true)
            ->where('product_name', 'like', '%' . $q . '%');

        if ($vendorId !== '') {
            $query->where('vendor_id', $vendorId);
        }

        $products = $query->orderBy('product_name')->limit(15)->get(['product_id', 'product_name']);

        return response()->json($products);
    }

    // REBUILT 24 Jul 2026 — Chris: "follow the product master maintenance
    // method and screen design" (cramped 2-column filter+list+add-form-all-
    // at-once layout was replaced with the same Main hub / full-width Add /
    // full-width Search-criteria / full-width Search-Results mode pattern
    // used by products()/products.blade.php). No default listing is shown
    // until Search is explicitly clicked, matching that same standard.
    public function commissionStructures(Request $request)
    {
        $mode = $request->get('mode', 'main');
        $structures = null;
        $selectedStructure = null;
        $rankAllocatedTotal = null;
        $groupHasRanks = true; // default true so nothing is blocked before a structure is loaded

        // NEW 1 Aug 2026 — per Chris: PVATM's own short labels (e.g. "HQ" /
        // "Cwg" / "Ali") weren't showing on this Edit screen — every
        // RoleLabelService call here used to be the no-arg form, which
        // resolves to whichever group the CURRENTLY LOGGED-IN agent
        // belongs to (Admin has none, so it always fell back to System
        // Default). This screen has no logged-in-agent context of its own
        // to go on, so it needs an explicit ?group_label_id= the same way
        // the Rank Hierarchy/Rank Allocation screens already do, threaded
        // through the Configure Rank Allocation link and back again.
        // FIXED 18 Aug 2026 — per Chris: this group_label_id picker only
        // exists to resolve ORG's own rank short labels (e.g. PVATM's
        // "HQ"/"Cwg") for a Rank-Only structure — ranks are an ORG-only
        // concept, so the picker must only offer ORG groups, no System
        // Default, no DSG/CBE mixed in.
        $groupLabels  = DB::table('group_labels')->where('group_type', 'ORG')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : ($groupLabels->first()->group_label_id ?? null);
        $roleShortLabels = collect(['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'])
            ->mapWithKeys(fn ($role) => [$role => RoleLabelService::shortLabel($role, 3, $groupLabelId)]);

        $vendors  = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name']);
        $products = DB::table('products')->where('is_active', true)->orderBy('product_name')->get(['product_id', 'product_name', 'vendor_id']);

        if ($mode === 'search' && $request->has('do_search')) {
            $query = DB::table('commission_structures as cs')
                ->join('vendors as v',  'v.vendor_id',  '=', 'cs.vendor_id')
                ->join('products as p', 'p.product_id', '=', 'cs.product_id')
                ->select('cs.*', 'v.vendor_name', 'p.product_name');

            if ($request->filled('vendor_id'))  $query->where('cs.vendor_id',  $request->vendor_id);
            if ($request->filled('product_id')) $query->where('cs.product_id', $request->product_id);
            if ($request->filled('status'))     $query->where('cs.is_active',  $request->status);

            // Smaller page size so a full page always fits the visible
            // area without scrolling — Prev/Next handles the rest.
            $structures = $query->orderBy('v.vendor_name')->orderBy('p.product_name')->paginate(7)->withQueryString();

            // NEW 1 Aug 2026 — per Chris: "the breakdown 10% is from the
            // rank not the category... after set up allocation it should
            // update back HQ, Cwg and Introducer." For a Rank-Only
            // structure, group_leader_pct/team_leader_pct/introducer_pct
            // are just static leftover 0s — Rank-Only bypasses the role
            // bucket entirely, the rank_pct rows are the real source of
            // truth. commission_structures only has ONE shared set of
            // these columns (not one per group), so we deliberately do NOT
            // write a rollup back into them (that already bit us once with
            // role_label_overrides — last group to save wins and corrupts
            // every other group's number). Instead we compute the rollup
            // here, per row, scoped to whichever group's labels this
            // search is viewing, and overwrite it onto the in-memory row
            // only — display-only, nothing is persisted.
            $autoDetectedGroupIds = [];

            foreach ($structures as $s) {
                if ($s->is_rank_only) {
                    $rowGroupLabelId = $groupLabelId;
                    $rowGroupName    = null;

                    // NEW 1 Aug 2026 (2) — per Chris, again: he shouldn't
                    // have to remember to pick "Labels for: PVATM" every
                    // single time just to see the breakdown he already
                    // saved. If nobody explicitly filtered by group (still
                    // sitting on System Default), and System Default has
                    // no rank rows for THIS structure, but exactly one
                    // other group does, just show that group's numbers —
                    // tagged with its name so it's never ambiguous which
                    // group's breakdown is on screen.
                    if (!$rowGroupLabelId) {
                        $hasSystemDefaultRows = DB::table('commission_rank_allocations as cra')
                            ->join('role_ranks as rr', 'rr.rank_id', '=', 'cra.rank_id')
                            ->where('cra.structure_id', $s->structure_id)
                            ->whereNull('rr.group_label_id')
                            ->exists();

                        if (!$hasSystemDefaultRows) {
                            $otherGroups = DB::table('commission_rank_allocations as cra')
                                ->join('role_ranks as rr', 'rr.rank_id', '=', 'cra.rank_id')
                                ->join('group_labels as gl', 'gl.group_label_id', '=', 'rr.group_label_id')
                                ->where('cra.structure_id', $s->structure_id)
                                ->whereNotNull('rr.group_label_id')
                                ->distinct()
                                ->get(['rr.group_label_id', 'gl.group_name']);

                            if ($otherGroups->count() === 1) {
                                $rowGroupLabelId = $otherGroups->first()->group_label_id;
                                $rowGroupName    = $otherGroups->first()->group_name;
                            }
                        }
                    }

                    $rollup = DB::table('commission_rank_allocations as cra')
                        ->join('role_ranks as rr', 'rr.rank_id', '=', 'cra.rank_id')
                        ->where('cra.structure_id', $s->structure_id)
                        ->where(function ($q) use ($rowGroupLabelId) {
                            $rowGroupLabelId ? $q->where('rr.group_label_id', $rowGroupLabelId) : $q->whereNull('rr.group_label_id');
                        })
                        ->selectRaw('rr.role, SUM(cra.rank_pct) as total_pct')
                        ->groupBy('rr.role')
                        ->pluck('total_pct', 'role');
                    $s->group_leader_pct = (float) ($rollup['GROUP_LEADER'] ?? 0);
                    $s->team_leader_pct  = (float) ($rollup['TEAM_LEADER'] ?? 0);
                    $s->introducer_pct   = (float) ($rollup['INTRODUCER'] ?? 0);
                    $s->rank_group_name  = $rowGroupName;

                    if ($rowGroupName) {
                        $autoDetectedGroupIds[$rowGroupLabelId] = true;
                    }
                }
            }

            // If every auto-detected row (no explicit "Labels for" filter
            // chosen) traces back to the SAME single group, use that
            // group's short labels for the column headers too — so "Int"
            // correctly reads "Ali" without Chris having to pick the
            // filter manually every time.
            if (!$groupLabelId && count($autoDetectedGroupIds) === 1) {
                $autoGroupLabelId = array_key_first($autoDetectedGroupIds);
                $roleShortLabels = collect(['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'])
                    ->mapWithKeys(fn ($role) => [$role => RoleLabelService::shortLabel($role, 3, $autoGroupLabelId)]);
            }
        }

        // NEW 24 Jul 2026 — View/Edit screen, same as Edit Product: Vendor/
        // Product identity shown read-only, everything else (basis, %
        // split, dates, status) editable in one screen. Chris: "you didn't
        // give me view and edit" — the one-click Activate/Deactivate button
        // alone wasn't enough; this replaces it with a proper Edit screen
        // and folds status into it exactly like Edit Product does.
        if ($mode === 'edit' && $request->filled('structure_id')) {
            $selectedStructure = DB::table('commission_structures as cs')
                ->join('vendors as v',  'v.vendor_id',  '=', 'cs.vendor_id')
                ->join('products as p', 'p.product_id', '=', 'cs.product_id')
                ->where('cs.structure_id', $request->structure_id)
                ->first(['cs.*', 'v.vendor_name', 'p.product_name']);

            if ($selectedStructure) {
                // NEW 31 Jul 2026 — rank % is no longer edited on this
                // screen (see RankAllocationController); this is just a
                // quick read-only status line so Admin can see at a
                // glance whether the separate Rank Allocation screen has
                // been configured yet, without having to click through.
                // FIXED 1 Aug 2026 — this used to sum EVERY group's rank
                // allocations together for this structure, mixing System
                // Default's own breakdown with PVATM's (or any other
                // group's) into one meaningless combined number. Now
                // scoped to just the group currently being viewed, same
                // as the Rank Allocation screen itself.
                $rankAllocatedTotal = DB::table('commission_rank_allocations as cra')
                    ->join('role_ranks as rr', 'rr.rank_id', '=', 'cra.rank_id')
                    ->where('cra.structure_id', $selectedStructure->structure_id)
                    ->where(function ($q) use ($groupLabelId) {
                        $groupLabelId ? $q->where('rr.group_label_id', $groupLabelId) : $q->whereNull('rr.group_label_id');
                    })
                    ->sum('cra.rank_pct');

                // NEW 1 Aug 2026 — per Chris: "if the user select the group
                // label that no rank assignment you should not allow him
                // to configure by rank." Whichever group is currently
                // picked in "Labels for" must actually have its own rank
                // ladder defined (Rank Hierarchy Maintenance) before
                // Admin is allowed into the Rank Allocation screen for
                // it — otherwise it opens to an empty grid with nothing
                // to save against. Checked across ALL roles at once
                // (GL/TL/Introducer), since Rank Allocation covers all
                // three.
                $groupHasRanks = DB::table('role_ranks')
                    ->where('is_active', true)
                    ->where(function ($q) use ($groupLabelId) {
                        $groupLabelId ? $q->where('group_label_id', $groupLabelId) : $q->whereNull('group_label_id');
                    })
                    ->exists();
            }
        }

        return view('masterfile.commission-structures', compact('mode', 'structures', 'selectedStructure', 'vendors', 'products', 'rankAllocatedTotal', 'groupLabelId', 'groupLabels', 'roleShortLabels', 'groupHasRanks'));
    }

    // MOVED 31 Jul 2026 — validateRankAllocations(), validateRankOnlyAllocations(),
    // and saveRankAllocations() used to live here (24/31 Jul 2026 — see
    // git history / master spec for the full reasoning behind the "must
    // add up exactly" rule and per-group-label clusters). Per Chris ("open
    // a totally new screen when want to set up by rank"), rank % is no
    // longer entered on the Earning Income Structure screen at all — it
    // moved, unchanged, to App\Http\Controllers\Admin\RankAllocationController,
    // which now owns validation and saving for commission_rank_allocations
    // and commission_rank_overrides.

    public function storeCommissionStructure(Request $request)
    {
        $isRankOnly = $request->boolean('is_rank_only');

        $request->validate([
            'vendor_id'            => ['required', 'exists:vendors,vendor_id'],
            'product_id'           => ['required', 'exists:products,product_id'],
            'commission_basis'     => ['required', 'in:PREMIUM_PCT,SUM_INSURED_PCT'],
            'total_commission_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'group_leader_pct'     => [$isRankOnly ? 'nullable' : 'required', 'numeric', 'min:0'],
            'team_leader_pct'      => [$isRankOnly ? 'nullable' : 'required', 'numeric', 'min:0'],
            'introducer_pct'       => [$isRankOnly ? 'nullable' : 'required', 'numeric', 'min:0'],
            'valid_from'           => ['required', 'date'],
            'valid_to'             => ['nullable', 'date', 'after:valid_from'],
        ]);

        // NEW 31 Jul 2026 — Rank-Only Structure mode. Skips the GL/TL/
        // Introducer bucket entirely; every active rank must instead add
        // up to the Total % directly. Rank % itself is no longer entered
        // here — per Chris ("open a totally new screen when want to set
        // up by rank"), that now happens on the separate Rank Allocation
        // screen, reached after this structure is saved (needs a
        // structure_id to attach to). So a freshly-saved Rank-Only
        // structure is deliberately left with 0/unconfigured rank %
        // until Admin visits that screen — not validated here.
        if ($isRankOnly) {
            $groupLeaderPct = 0;
            $teamLeaderPct  = 0;
            $introducerPct  = 0;
        } else {
            $allocated = $request->group_leader_pct + $request->team_leader_pct + $request->introducer_pct;
            if (abs($allocated - $request->total_commission_pct) > 0.001) {
                $glLabel = \App\Services\RoleLabelService::shortLabel('GROUP_LEADER');
                $tlLabel = \App\Services\RoleLabelService::shortLabel('TEAM_LEADER');
                $introLabel = \App\Services\RoleLabelService::label('INTRODUCER');
                return back()->withErrors([
                    'splits' => "{$glLabel} + {$tlLabel} + {$introLabel} % must equal Total Commission % ({$request->total_commission_pct}%). Current allocated: {$allocated}%."
                ])->withInput();
            }
            $groupLeaderPct = $request->group_leader_pct;
            $teamLeaderPct  = $request->team_leader_pct;
            $introducerPct  = $request->introducer_pct;
        }

        $id = Str::uuid()->toString();
        DB::table('commission_structures')->insert([
            'structure_id'         => $id,
            'vendor_id'            => $request->vendor_id,
            'product_id'           => $request->product_id,
            'commission_basis'     => $request->commission_basis,
            'total_commission_pct' => $request->total_commission_pct,
            'is_rank_only'         => $isRankOnly,
            'group_leader_pct'     => $groupLeaderPct,
            'team_leader_pct'      => $teamLeaderPct,
            'introducer_pct'       => $introducerPct,
            'valid_from'           => $request->valid_from,
            'valid_to'             => $request->valid_to,
            'is_active'            => true,
            'created_by'           => Auth::guard('agent')->id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
        // Rank allocation itself is no longer saved here — see the Rank
        // Allocation screen (RankAllocationController) for that step.

        AuditService::logChange('commission_structures', $id, 'COMMISSION_STRUCTURE_CREATED', null, $request->all());
        return redirect()->route('admin.masterfile.commissions', ['mode' => 'main'])->with('success', 'Earning income structure saved.');
    }

    // NEW 24 Jul 2026 — Update from the Edit screen. Vendor/Product are
    // fixed (identity of the row, same as Product Code on Edit Product) —
    // everything else, including Active/Inactive status, is editable here.
    public function updateCommissionStructure(Request $request, string $id)
    {
        $isRankOnly = $request->boolean('is_rank_only');

        $request->validate([
            'commission_basis'     => ['required', 'in:PREMIUM_PCT,SUM_INSURED_PCT'],
            'total_commission_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'group_leader_pct'     => [$isRankOnly ? 'nullable' : 'required', 'numeric', 'min:0'],
            'team_leader_pct'      => [$isRankOnly ? 'nullable' : 'required', 'numeric', 'min:0'],
            'introducer_pct'       => [$isRankOnly ? 'nullable' : 'required', 'numeric', 'min:0'],
            'valid_from'           => ['required', 'date'],
            'valid_to'             => ['nullable', 'date', 'after:valid_from'],
            'is_active'            => ['required', 'in:0,1'],
        ]);

        // NEW 31 Jul 2026 — rank % is no longer entered on this screen at
        // all (see storeCommissionStructure() note above) — editing a
        // Rank-Only structure's dates/status/total here leaves its rank
        // allocation exactly as it was; that's only ever touched via the
        // separate Rank Allocation screen now.
        if ($isRankOnly) {
            $groupLeaderPct = 0;
            $teamLeaderPct  = 0;
            $introducerPct  = 0;
        } else {
            $allocated = $request->group_leader_pct + $request->team_leader_pct + $request->introducer_pct;
            if (abs($allocated - $request->total_commission_pct) > 0.001) {
                $glLabel = \App\Services\RoleLabelService::shortLabel('GROUP_LEADER');
                $tlLabel = \App\Services\RoleLabelService::shortLabel('TEAM_LEADER');
                $introLabel = \App\Services\RoleLabelService::label('INTRODUCER');
                return back()->withErrors([
                    'splits' => "{$glLabel} + {$tlLabel} + {$introLabel} % must equal Total Commission % ({$request->total_commission_pct}%). Current allocated: {$allocated}%."
                ])->withInput();
            }
            $groupLeaderPct = $request->group_leader_pct;
            $teamLeaderPct  = $request->team_leader_pct;
            $introducerPct  = $request->introducer_pct;
        }

        $before = DB::table('commission_structures')->where('structure_id', $id)->first();
        DB::table('commission_structures')->where('structure_id', $id)->update([
            'commission_basis'     => $request->commission_basis,
            'total_commission_pct' => $request->total_commission_pct,
            'is_rank_only'         => $isRankOnly,
            'group_leader_pct'     => $groupLeaderPct,
            'team_leader_pct'      => $teamLeaderPct,
            'introducer_pct'       => $introducerPct,
            'valid_from'           => $request->valid_from,
            'valid_to'             => $request->valid_to,
            'is_active'            => $request->is_active,
            'updated_at'           => now(),
        ]);
        // Rank allocation itself is no longer saved here — see the Rank
        // Allocation screen (RankAllocationController) for that step.

        AuditService::logChange('commission_structures', $id, 'COMMISSION_STRUCTURE_UPDATED', (array) $before, $request->all());
        $backUrl = $request->filled('back') ? urldecode($request->back) : route('admin.masterfile.commissions', ['mode' => 'search']);
        return redirect($backUrl)->with('success', 'Earning income structure updated.');
    }

    public function toggleCommissionStructure(string $id)
    {
        $cs = DB::table('commission_structures')->where('structure_id', $id)->first();
        DB::table('commission_structures')->where('structure_id', $id)->update([
            'is_active'  => !$cs->is_active,
            'updated_at' => now(),
        ]);
        AuditService::logChange('commission_structures', $id, 'COMMISSION_STRUCTURE_TOGGLED', ['is_active' => $cs->is_active], ['is_active' => !$cs->is_active]);
        return back()->with('success', 'Commission structure status updated.');
    }

    // -------------------------------------------------------
    // GROUPS
    // -------------------------------------------------------
    public function groups(Request $request)
    {
        $mode          = $request->get('mode', 'main');
        $groups        = null;
        $selectedGroup = null;

        if ($mode === 'search' && $request->has('do_search')) {
            $query = DB::table('groups');
            if ($request->filled('group_name'))  $query->where('group_name',  'like', '%'.$request->group_name.'%');
            if ($request->filled('group_code'))  $query->where('group_code',  'like', '%'.$request->group_code.'%');
            if ($request->filled('group_email')) $query->where('group_email', 'like', '%'.$request->group_email.'%');
            if ($request->filled('is_active'))   $query->where('is_active',   $request->is_active);
            // Smaller page size so a full page always fits the visible
            // area without scrolling — Prev/Next handles the rest.
            $groups = $query->orderBy('group_code')->paginate(7)->withQueryString();
        }

        if ($mode === 'edit' && $request->filled('group_id')) {
            $selectedGroup = DB::table('groups')->where('group_id', $request->group_id)->first();
        }

        return view('masterfile.groups', compact('mode', 'groups', 'selectedGroup'));
    }

    public function storeGroup(Request $request)
    {
        $request->validate([
            'group_name'     => ['required', 'string', 'max:200'],
            'group_code'     => ['required', 'string', 'max:20', 'unique:groups,group_code'],
            'group_email'    => ['required', 'email'],
            'separator_char' => ['nullable', 'in:-,.,_'],
            'description'    => ['nullable', 'string', 'max:1000'],
        ]);
        $id = Str::uuid()->toString();
        DB::table('groups')->insert([
            'group_id'           => $id,
            'group_name'         => $request->group_name,
            'description'        => $request->description,
            'group_code'         => strtoupper($request->group_code),
            'group_email'        => strtolower($request->group_email),
            'separator_char'     => $request->separator_char ?? '-',
            'root_member_suffix' => '0',
            'is_active'          => true,
            'created_by'         => Auth::guard('agent')->id(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
        AuditService::logChange('groups', $id, 'GROUP_CREATED', null, $request->all());
        return redirect()->route('admin.masterfile.groups', ['mode' => 'main'])->with('success', 'Group created successfully.');
    }

    public function updateGroup(Request $request, string $id)
    {
        $request->validate([
            'group_name'     => ['required', 'string', 'max:200'],
            'group_email'    => ['required', 'email'],
            'separator_char' => ['nullable', 'in:-,.,_'],
            'is_active'      => ['required', 'in:0,1'],
            'description'    => ['nullable', 'string', 'max:1000'],
        ]);
        $before = DB::table('groups')->where('group_id', $id)->first();
        DB::table('groups')->where('group_id', $id)->update([
            'group_name'     => $request->group_name,
            'description'    => $request->description,
            'group_email'    => strtolower($request->group_email),
            'separator_char' => $request->separator_char ?? '-',
            'is_active'      => $request->is_active,
            'updated_by'     => Auth::guard('agent')->id(),
            'updated_at'     => now(),
        ]);
        AuditService::logChange('groups', $id, 'GROUP_UPDATED', (array)$before, $request->all());
        $backUrl = $request->filled('back') ? urldecode($request->back) : route('admin.masterfile.groups', ['mode' => 'search']);
        return redirect($backUrl)->with('success', 'Group updated successfully.');
    }

    // -------------------------------------------------------
    // REWARD POINTS RATES
    // -------------------------------------------------------
    public function rewardRates(Request $request)
    {
        $query = DB::table('reward_points_rates as r')
            ->leftJoin('vendors as v',  'v.vendor_id',  '=', 'r.vendor_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'r.product_id')
            ->select('r.*', 'v.vendor_name', 'p.product_name');

        if ($request->filled('vendor_id') || $request->filled('product_id') || ($request->has('status') && $request->status !== '')) {
            if ($request->filled('vendor_id'))  $query->where('r.vendor_id',  $request->vendor_id);
            if ($request->filled('product_id')) $query->where('r.product_id', $request->product_id);
            if ($request->has('status') && $request->status !== '') $query->where('r.is_active', $request->status);
            $rates = $query->orderByDesc('r.created_at')->get();
        } else {
            $rates = collect();
        }

        $vendors  = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();
        $products = DB::table('products')->where('is_active', true)->orderBy('product_name')->get();
        return view('masterfile.reward-rates', compact('rates', 'vendors', 'products'));
    }

    public function storeRewardRate(Request $request)
    {
        $request->validate([
            'points_per_rm' => ['required', 'numeric', 'min:0.0001'],
            'valid_from'    => ['required', 'date'],
            'valid_to'      => ['nullable', 'date', 'after:valid_from'],
        ]);
        $id = Str::uuid()->toString();
        DB::table('reward_points_rates')->insert([
            'rate_id'       => $id,
            'vendor_id'     => $request->vendor_id ?: null,
            'product_id'    => $request->product_id ?: null,
            'points_per_rm' => $request->points_per_rm,
            'valid_from'    => $request->valid_from,
            'valid_to'      => $request->valid_to,
            'is_active'     => true,
            'created_by'    => Auth::guard('agent')->id(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        AuditService::logChange('reward_points_rates', $id, 'REWARD_RATE_CREATED', null, $request->all());
        return back()->with('success', 'Reward rate added.');
    }
}
