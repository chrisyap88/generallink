<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Vendor;
use App\Services\HierarchyService;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // -------------------------------------------------------
    // Show login page. Updated 15 Jul 2026: if arriving fresh from
    // account activation (?welcome=1 on the URL), flash a friendly
    // greeting so the user isn't just dropped silently on a blank
    // login form after everything they just completed.
    // -------------------------------------------------------
    public function showLogin(Request $request)
    {
        if (auth('agent')->check()) {
            return $this->redirectToDashboard(auth('agent')->user());
        }
        if ($request->query('welcome')) {
            session()->flash('success', '🎉 Your account is now active! Please sign in below to get started.');
        }
        return view('auth.login', [
            'introVideo' => \App\Http\Controllers\Admin\VideoLibraryController::latestIntroVideo(),
        ]);
    }

    // -------------------------------------------------------
    // Process login (unchanged from current live version — NOT restoring
    // the email_verified_at block yet, see note to user)
    // -------------------------------------------------------
    // -------------------------------------------------------
    // NEW 14 Aug 2026 — per Chris: "why dont you have one commen login
    // for all includes vendor." This ONE form (the standard /login page)
    // now handles Admin/TL/GL/Introducer AND Vendor accounts — no more
    // separate /vendor/login page to remember. It looks up which table
    // the email actually belongs to and hands off to the matching,
    // completely unchanged login logic below (loginAsAgent) or in
    // VendorAuthController (vendor's own login(), reused as-is — just
    // fed the same email under the field name it expects). Special
    // Privilege Group branded logins (e.g. PVATM) are deliberately NOT
    // folded in here — those stay isolated to their own page, per the
    // existing 06 Jul 2026 decision; this only merges the two logins
    // Chris was actually confused by.
    // -------------------------------------------------------
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $agentExists = Agent::where('email', $request->email)->where('is_deleted', false)->exists();
        if ($agentExists) {
            return $this->loginAsAgent($request);
        }

        $vendorExists = Vendor::where('vendor_email', strtolower($request->email))->exists();
        if ($vendorExists) {
            $request->merge(['vendor_email' => $request->email]);
            return app(\App\Http\Controllers\Auth\VendorAuthController::class)->login($request);
        }

        return back()->withErrors(['email' => 'No account found with this email address.'])->withInput();
    }

    /** Unchanged from before this was split out — every Admin/TL/GL/Introducer login still goes through exactly this logic. */
    private function loginAsAgent(Request $request)
    {
        $agent = Agent::where('email', $request->email)
            ->where('is_deleted', false)
            ->first();

        if (!$agent) {
            return back()->withErrors(['email' => 'No account found with this email address.'])->withInput();
        }

        // Organization Rewards Group members (GL/TL/Introducer, never
        // Admin) are STRICTLY confined to their own group's branded
        // login page only — never the standard page, and never another
        // group's page. Different organizations are fully isolated
        // from each other. Confirmed decision (06 Jul 2026).
        if ($agent->role !== 'ADMIN' && $agent->group_label_id) {
            $ownLabel = \Illuminate\Support\Facades\DB::table('group_labels')
                ->where('group_label_id', $agent->group_label_id)
                ->where('promotion_demotion_enabled', false)
                ->first();
            if ($ownLabel) {
                $ownLoginUrl = route('special-group.login', $ownLabel->slug);
                return back()->withErrors(['email' => "Your account belongs to {$ownLabel->group_name}. Please use your organization's own login page: {$ownLoginUrl}"])->withInput();
            }
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

        auth('agent')->login($agent, $request->boolean('remember'));
        $request->session()->regenerate();

        // Standard login page = full combined view. Explicitly clear
        // this, so it never carries over stale from a previous session
        // where Admin had logged in via a specific group's branded page.
        $request->session()->forget('login_origin_slug');
        // NEW 27 Aug 2026 — same idea for the /glade CBE front door: a
        // standard /login sign-in must never carry over a stale GLADE
        // portal flag from a previous session on this browser, or every
        // CBE KPI view would wrongly keep rendering the sidebar-less
        // GLADE layout instead of the normal DSG/ORG dashboard shell.
        $request->session()->forget('portal');
        $request->session()->forget('login_origin_glade');

        return $this->redirectToDashboard($agent);
    }

    public function logout(Request $request)
    {
        $agent = auth('agent')->user();

        // Check BEFORE logging out. Two cases:
        // 1. Session tracks WHICH branded page was used to log in —
        //    this covers Admin, who has no fixed group of their own.
        // 2. Fallback: the agent's own fixed group_label_id — covers
        //    GL/TL/Introducer, who always belong to one specific group.
        $redirectRoute = route('auth.login');
        $originSlug = $request->session()->get('login_origin_slug');
        // NEW 27 Aug 2026 — checked BEFORE the slug/group fallback below,
        // same reasoning: a session that came in through /glade must log
        // back out to /glade, not the standard /login page, regardless
        // of the agent's role or group.
        if ($request->session()->get('login_origin_glade')) {
            $redirectRoute = route('glade.login');
        } elseif ($originSlug) {
            $redirectRoute = route('special-group.login', $originSlug);
        } elseif ($agent && $agent->group_label_id) {
            $label = \Illuminate\Support\Facades\DB::table('group_labels')
                ->where('group_label_id', $agent->group_label_id)
                ->where('promotion_demotion_enabled', false)
                ->first();
            if ($label && $label->slug) {
                $redirectRoute = route('special-group.login', $label->slug);
            }
        }

        auth('agent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect($redirectRoute)->with('success', 'You have been logged out successfully.');
    }

    // -------------------------------------------------------
    // NEW 03 Jul 2026 — Show registration form
    // -------------------------------------------------------
    public function showRegister()
    {
        // States pulled live from the shared malaysia_postcodes table —
        // same source the Admin Vendor form's State dropdown uses.
        // No separate/hardcoded state list, per normalization rule.
        $states = DB::table('malaysia_postcodes')
            ->select('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        $introVideo = \App\Http\Controllers\Admin\VideoLibraryController::latestIntroVideo();

        return view('auth.register', compact('states', 'introVideo'));
    }

    // -------------------------------------------------------
    // NEW 03 Jul 2026 — Process registration form submission
    // -------------------------------------------------------
    public function register(Request $request)
    {
        // NEW 28 Sep 2026 — per Chris (member file item 30): the same person is
        // never registered twice. Mobile / Email matching a person already in
        // the member file:
        //  - added by staff and never logged in  → this registration CLAIMS that
        //    record (his affiliations, positions and history stay with him);
        //  - already has a login                 → refused: log in instead.
        if ($claim = $this->memberFileMatch($request)) {
            if ($claim['has_login']) {
                return back()->withErrors(['email' => __('member_file.reg_already_registered')])->withInput();
            }

            return $this->claimMemberRecord($request, $claim['agent']);
        }

        $request->validate([
            'upline_type'           => ['required', 'in:GROUP_LEADER,TEAM_LEADER,INTRODUCER,ADMIN_ASSIGN'],
            'upline_agent_id'       => ['required_unless:upline_type,ADMIN_ASSIGN', 'nullable', 'string'],
            'name'                  => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z\s\'\-\.\@]+$/'],
            'second_name'           => ['nullable', 'string', 'max:200'],
            'phone'                 => ['required', 'string', PhoneNumberService::rule()],
            'email'                 => ['required', 'email', 'max:200', 'unique:agents,email'],
            'address'               => ['required', 'string'],
            'postcode'              => ['required', 'string', 'regex:/^\d{5}$/'],
            'city'                  => ['required', 'string', 'max:100'],
            'state'                 => ['required', 'string', 'max:100'],
        ], [
            'name.regex'         => 'Full Name must be as per NRIC — letters and spaces only.',
            'postcode.regex'     => 'Postcode must be exactly 5 digits.',
        ]);

        // NRIC and bank details are DELIBERATELY never collected here —
        // per confirmed legal/privacy requirement (06 Jul 2026). Bank
        // details are collected later, only at the moment of an actual
        // commission withdrawal, in a separate purpose-built flow —
        // never stored as part of a member's profile.

        $agentData = [
            'full_name'              => $request->name,
            'second_name'            => $request->second_name ?: null,
            'email'                  => strtolower($request->email),
            'password_hash'          => Hash::make(Str::random(32)), // temporary — replaced when they set their real password
            'phone'                  => PhoneNumberService::normalize($request->phone),
            'email_verification_token' => Str::random(64),
        ];

        if ($request->upline_type === 'ADMIN_ASSIGN') {
            // No sponsor yet — Admin assigns later via the existing Pending
            // Assignment queue. agent_code/member_code stay NULL until then.
            $agent = Agent::create(array_merge($agentData, [
                'agent_id'   => Str::uuid()->toString(),
                'role'       => 'INTRODUCER',
                'status'     => 'INACTIVE',
                'parent_id'  => null,
                'group_id'   => null,
                'created_by' => null,
            ]));
        } else {
            $sponsor = Agent::where('agent_id', $request->upline_agent_id)
                ->where('status', 'ACTIVE')
                ->where('is_deleted', false)
                ->first();

            if (! $sponsor) {
                return back()->withErrors(['upline_agent_id' => 'Selected upline is no longer valid. Please search again.'])->withInput();
            }

            try {
                $agent = app(HierarchyService::class)->registerUnderSponsor($sponsor, $agentData, $sponsor->agent_id);
            } catch (\Exception $e) {
                return back()->withErrors(['upline_agent_id' => $e->getMessage()])->withInput();
            }
        }

        // Force INACTIVE regardless of which branch/service set the
        // initial status — becomes ACTIVE only after BOTH email
        // verification AND password are set (see setPassword() below).
        $agent->update(['status' => 'INACTIVE']);

        \App\Services\AuditService::logChange('agents', $agent->agent_id, 'REGISTRATION', null, [
            'full_name' => $agent->full_name,
            'email'     => $agent->email,
            'role'      => $agent->role,
            'upline_type' => $request->upline_type,
        ]);

        // NEW 25 Jul 2026 (task #210) — Growth & Outreach Center referral
        // analytics. Only mark the click "converted" if the sponsor
        // actually used here is the SAME agent whose referral link/QR
        // was clicked — if the visitor changed their mind and searched
        // for a different sponsor manually, this signup is correctly
        // NOT attributed to the original link. Never touches commission
        // or hierarchy logic — read-only match against parent_id.
        $referralClickId = $request->session()->pull('referral_click_id');
        if ($referralClickId && $agent->parent_id) {
            $click = DB::table('referral_clicks')->where('click_id', $referralClickId)->whereNull('converted_agent_id')->first();
            if ($click && $click->agent_id === $agent->parent_id) {
                DB::table('referral_clicks')->where('click_id', $referralClickId)->update([
                    'converted_agent_id' => $agent->agent_id,
                    'updated_at'         => now(),
                ]);
            }
        }

        // Address fields live in agent_profiles, not the agents table itself
        // (agents has no address/postcode/city/state columns) — one row per
        // agent, linked by agent_id. No duplicate storage anywhere else.
        DB::table('agent_profiles')->insert([
            'profile_id' => Str::uuid()->toString(),
            'agent_id'   => $agent->agent_id,
            'address'    => $request->address,
            'city'       => $request->city,
            'state'      => $request->state,
            'postcode'   => $request->postcode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // NEW 28 Sep 2026 — member file item 31: came from an entity QR code → join that entity at once
        \App\Http\Controllers\JoinController::finishPendingJoin($request, $agent->agent_id);
        $this->sendVerificationEmail($agent);

        return redirect()->route('auth.verify-pending')->with('email', $agent->email);
    }

    // NEW 15 Jul 2026 — sending itself now lives in one shared
    // VerificationMailService (used by public registration, Special
    // Group registration, and the Admin/GL/TL Resend Verification
    // action), so the template and branding logic exist in exactly one
    // place instead of being duplicated per controller.
    private function sendVerificationEmail(Agent $agent): void
    {
        app(\App\Services\VerificationMailService::class)->send($agent);
    }

    // -------------------------------------------------------
    // NEW 03 Jul 2026 (restored + adapted) — Email verification link handler
    // -------------------------------------------------------
    public function verifyEmail(Request $request, string $token)
    {
        $agent = Agent::where('email_verification_token', $token)
            ->where('is_deleted', false)
            ->first();

        if (! $agent) {
            return redirect()->route('auth.login')->with('error', 'Invalid or expired verification link.');
        }

        $agent->update([
            'email_verified_at'        => now(),
            'email_verification_token' => null,
        ]);

        \App\Services\AuditService::logChange('agents', $agent->agent_id, 'EMAIL_VERIFIED', ['email_verified_at' => null], ['email_verified_at' => now()->toDateTimeString()]);

        auth('agent')->login($agent);

        // Decision 3 (03 Jul 2026) — simple password step, no security phrase
        return redirect()->route('auth.set-password');
    }

    // -------------------------------------------------------
    // NEW 05 Jul 2026 — Confirms a pending email change made via
    // Introducer Maintenance edit. Only NOW does the real agents.email
    // column actually change — this is what keeps login safe from a
    // typo or premature edit (Decision: email edits "follow the public
    // registration method").
    // -------------------------------------------------------
    public function verifyEmailChange(Request $request, string $token)
    {
        $agent = Agent::where('pending_email_token', $token)
            ->where('is_deleted', false)
            ->first();

        if (! $agent || ! $agent->pending_email) {
            return redirect()->route('auth.login')->with('error', 'Invalid or expired email change link.');
        }

        $oldEmail = $agent->email;
        $agent->update([
            'email'                => $agent->pending_email,
            'pending_email'        => null,
            'pending_email_token'  => null,
        ]);

        \App\Services\AuditService::logChange('agents', $agent->agent_id, 'EMAIL_CHANGE_CONFIRMED', ['email' => $oldEmail], ['email' => $agent->email]);

        return redirect()->route('auth.login')->with('success', 'Your email has been updated. You can now log in with your new email address.');
    }

    // -------------------------------------------------------
    // NEW 03 Jul 2026 — Simple password setup (Decision 3: no strength
    // rules, no separate security phrase step)
    // Updated 15 Jul 2026 — branding (logo + group name) now follows the
    // agent's own group_label master file setting (logo_path), for BOTH
    // Public and Special groups, not just a generic GeneralLink logo.
    // Falls back to the generic logo only when the group has none set.
    // -------------------------------------------------------
    public function showSetPassword()
    {
        $agent = auth('agent')->user();
        [$brandLogoUrl, $brandGroupName] = $this->resolveBranding($agent);

        return view('auth.set-password', [
            'brandLogoUrl'   => $brandLogoUrl,
            'brandGroupName' => $brandGroupName,
        ]);
    }

    // -------------------------------------------------------
    // Shared helper — resolves the correct logo + display name for an
    // agent based on their group_label's master file setting
    // (group_labels.logo_path). Works for Public and Special groups
    // alike; falls back to the generic GeneralLink logo when the group
    // has no logo configured, or the agent has no group at all.
    // -------------------------------------------------------
    private function resolveBranding(?Agent $agent): array
    {
        $logoUrl = url('/images/generallink-logo.jpeg');
        $groupName = 'GeneralLink';

        if ($agent && $agent->group_label_id) {
            $label = DB::table('group_labels')->where('group_label_id', $agent->group_label_id)->first();
            if ($label) {
                $groupName = $label->group_name;
                if ($label->logo_path) {
                    $logoUrl = url('/images/' . $label->logo_path);
                }
            }
        }

        return [$logoUrl, $groupName];
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $agent = auth('agent')->user();
        $agent->update([
            'password_hash' => Hash::make($request->password),
            // Activation happens ONLY now — both email verification AND
            // password are confirmed done. Before this point, status
            // stays INACTIVE the whole time, regardless of how long the
            // person takes between verifying and setting a password.
            'status' => 'ACTIVE',
        ]);

        \App\Services\AuditService::logChange('agents', $agent->agent_id, 'ACCOUNT_ACTIVATED', ['status' => 'INACTIVE'], ['status' => 'ACTIVE']);

        // Check whether THIS activation just pushed the sponsor over
        // their promotion threshold (3 active direct recruits). Checked
        // here — the moment a recruit becomes genuinely Active — not at
        // registration or email verification, since an unverified or
        // inactive recruit shouldn't count toward anyone's promotion.
        // Uses the REAL, authoritative HierarchyService engine — NOT the
        // separate PromoteAgentService (removed 06 Jul 2026 after
        // discovering it duplicated logic that already existed here,
        // more completely, including demotion + displaced-downline
        // reuniting that PromoteAgentService never had).
        $sponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        if ($sponsor) {
            app(\App\Services\HierarchyService::class)->evaluateRankChange($sponsor);
        }

        // Group-wide notification — moved here from verifyEmail(), since
        // THIS is the true activation moment now, not just verification.
        $recipients = app(\App\Services\NotificationService::class)->recipientsForGroupBroadcast($agent, $sponsor);
        app(\App\Services\NotificationService::class)->notify(
            $recipients,
            'NEW_REGISTRATION',
            'New Introducer Joined',
            "{$agent->full_name}" . ($agent->agent_code ? " ({$agent->agent_code})" : ' (pending code assignment)') . " has just activated their account" . ($sponsor ? ", recruited by {$sponsor->full_name}." : '.')
        );

        // Personal welcome confirmation — TO the new agent themselves,
        // with their permanent QR referral code included, professional
        // and appreciative tone, per confirmed wording requirement.
        // Updated 15 Jul 2026: explains what the attached QR code is for
        // (login to the Affiliate Ecosystem to renew car insurance /
        // share with family & friends / earning income), replacing the
        // old generic "ready to invite new members" line. Also converted
        // to compact HTML (same small size as the other emails) instead
        // of plain text, so it fits on one screen without scrolling.
        // NEW 15 Jul 2026 — branding resolved ONCE here (was previously
        // hardcoded to the generic GeneralLink logo, unlike every other
        // touchpoint in this flow), then reused for both this email AND
        // the verification-success redirect below. Standardizes Public
        // and Organization Rewards Group agents through the exact same code path —
        // no more duplicated, drifting copies of this logic.
        $qrUrl = url('/register?ref=' . $agent->qr_code_token);
        [$welcomeLogoUrl, $welcomeGroupName] = $this->resolveBranding($agent);
        $welcomeMessage = "Dear {$agent->full_name},\n\n"
            . "Congratulations — your {$welcomeGroupName} account has been successfully verified and activated. "
            . "We truly appreciate you joining us, and we look forward to a successful journey together.\n\n"
            . "Your personal QR code is attached to this email. Please download and save a copy on your phone or computer. "
            . "You may use this QR code to log in to our Affiliate Ecosystem and renew your own car insurance, or share it with your family, friends, and community so they can conveniently renew theirs as well. "
            . "Every successful renewal completed through your QR code will be recorded as part of your earning income, based on the applicable commission rate for that product.\n\n"
            . "Thank you.\n\n"
            . "Kind Regards,\n"
            . "System Admin";
        $welcomeHtml = <<<WHTML
<div style="font-family:'Segoe UI',Arial,sans-serif; max-width:400px; margin:0 auto; background:#f4f9fb;">
    <div style="background:linear-gradient(135deg,#1565C0,#1B9AE4); padding:14px; text-align:center; border-radius:8px 8px 0 0;">
        <img src="{$welcomeLogoUrl}" alt="{$welcomeGroupName}" style="max-height:34px; border-radius:6px; margin-bottom:6px;">
        <div style="color:#fff; font-size:14px; font-weight:700;">Account Activated!</div>
    </div>
    <div style="background:#fff; padding:16px 14px; border-radius:0 0 8px 8px;">
        <p style="font-size:12px; color:#1a1a1a; margin:0 0 8px;">Dear {$agent->full_name},</p>
        <p style="font-size:11px; color:#374151; line-height:1.4; margin:0 0 10px;">
            Congratulations — your {$welcomeGroupName} account has been successfully verified and activated. We truly appreciate you joining us, and we look forward to a successful journey together.
        </p>
        <p style="font-size:11px; color:#374151; line-height:1.4; margin:0 0 10px;">
            Your personal QR code is attached to this email (PNG and JPG). Please download and save a copy on your phone or computer. You may use this QR code to log in to our Affiliate Ecosystem and renew your own car insurance, or share it with your family, friends, and community so they can conveniently renew theirs as well. Every successful renewal completed through your QR code will be recorded as part of your earning income, based on the applicable commission rate for that product.
        </p>
        <p style="font-size:11px; color:#374151; margin-top:14px;">
            Thank you,<br>
            <strong>System Admin</strong>
        </p>
    </div>
    <div style="text-align:center; padding:8px; font-size:8.5px; color:#9ca3af; letter-spacing:.05em;">
        AI-POWERED &middot; MALAYSIA &middot; SOUTHEAST ASIA
    </div>
</div>
WHTML;

        DB::table('notifications')->insert([
            'notification_id'    => (string) \Illuminate\Support\Str::uuid(),
            'recipient_agent_id' => $agent->agent_id,
            'type'                => 'ACCOUNT_ACTIVATED',
            'title'               => 'Welcome to GeneralLink!',
            'message'             => $welcomeMessage,
            'related_agent_id'    => $agent->agent_id,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        if ($agent->email) {
            // Generate the actual QR code image server-side — PNG first
            // (the correct, scannable format: lossless, sharp edges),
            // then a JPEG copy purely for the additional format requested.
            // Uses direct instantiation (current library API) — the
            // static Builder::create() method used in an older version
            // was removed, which is what caused this to break.
            $qrPng = (new \Endroid\QrCode\Builder\Builder(
                writer: new \Endroid\QrCode\Writer\PngWriter(),
                data: $qrUrl,
                size: 300,
                margin: 10,
            ))->build();

            $pngPath = storage_path('app/temp_qr_' . $agent->agent_id . '.png');
            $jpegPath = storage_path('app/temp_qr_' . $agent->agent_id . '.jpg');
            file_put_contents($pngPath, $qrPng->getString());

            // Convert the same image to JPEG using GD (already built into
            // XAMPP's PHP — no extra extension needed).
            $img = imagecreatefromstring($qrPng->getString());
            imagejpeg($img, $jpegPath, 92);
            imagedestroy($img);

            // Wrapped in try/catch — the account is ALREADY correctly
            // activated by this point (saved above). A mail-sending
            // hiccup (e.g. Mailtrap's free-tier rate limit) should never
            // crash the whole flow or make it look like activation failed.
            try {
                Mail::html($welcomeHtml, function ($mail) use ($agent, $pngPath, $jpegPath) {
                    $mail->to($agent->email)
                        ->subject('GeneralLink — Welcome! Your Account is Now Active')
                        ->attach($pngPath, ['as' => 'my-referral-qr.png', 'mime' => 'image/png'])
                        ->attach($jpegPath, ['as' => 'my-referral-qr.jpg', 'mime' => 'image/jpeg']);
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Welcome email failed to send (account activation still succeeded): ' . $e->getMessage());
            }

            // Clean up the temporary files — the email already has them
            // attached, no need to keep copies sitting on disk.
            @unlink($pngPath);
            @unlink($jpegPath);
        }

        // -------------------------------------------------------
        // NEW 15 Jul 2026 — after activation, land back on a LOGIN page
        // (the agent's own Organization Rewards Group branded login if they
        // belong to one, else the standard public login), NOT auto-carried
        // straight into their dashboard. Confirmed decision (15 Jul 2026):
        // capture the display info + correct login destination BEFORE
        // logging out, since auth()->user() is unavailable afterward.
        // -------------------------------------------------------
        $vaName = $agent->full_name;
        $vaRole = $agent->role;
        $vaQr   = $agent->qr_code_token;
        // Reuses the SAME branding already resolved above for the
        // welcome email — one lookup, not two, and guarantees this
        // page and that email never disagree on the agent's logo/name.
        $vaLogoUrl = $welcomeLogoUrl;
        $vaGroupName = $welcomeGroupName;

        $vaLoginRoute = route('auth.login');
        if ($agent->group_label_id) {
            $label = DB::table('group_labels')
                ->where('group_label_id', $agent->group_label_id)
                ->where('promotion_demotion_enabled', false)
                ->first();
            if ($label && $label->slug) {
                $vaLoginRoute = route('special-group.login', $label->slug);
            }
        }
        // Tag the destination so the login page can greet the user
        // instead of silently showing a blank form.
        $vaLoginRoute .= (str_contains($vaLoginRoute, '?') ? '&' : '?') . 'welcome=1';

        auth('agent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.verification-success')->with([
            'va_name'        => $vaName,
            'va_role'        => $vaRole,
            'va_qr'          => $vaQr,
            'va_login_route' => $vaLoginRoute,
            'va_logo_url'    => $vaLogoUrl,
            'va_group_name'  => $vaGroupName,
        ]);
    }

    public function verificationSuccess()
    {
        return view('auth.verification-success', [
            'vaName'       => session('va_name', 'Agent'),
            'vaRole'       => session('va_role', 'INTRODUCER'),
            'vaQr'         => session('va_qr', ''),
            'vaLoginRoute' => session('va_login_route', route('auth.login')),
            'vaLogoUrl'    => session('va_logo_url', url('/images/generallink-logo.jpeg')),
            'vaGroupName'  => session('va_group_name', 'GeneralLink'),
        ]);
    }

    // -------------------------------------------------------
    // UPDATED 16 Jul 2026 — verify-pending now mirrors the exact
    // verification EMAIL content (same wording as VerificationMailService::send())
    // on-screen too, in a left/right split matching set-password.blade.php.
    // Looks the agent up by session('email') (flashed on redirect here from
    // register()/setPassword()-adjacent flows) so it can show their real
    // name, group branding, and their actual working verify link — this
    // is the same link/token as the emailed one, not a separate resend.
    // -------------------------------------------------------
    public function verifyPending()
    {
        $agent = Agent::where('email', session('email'))->first();
        [$logoUrl, $groupName] = $this->resolveBranding($agent);
        $roleLabel = $agent ? ucwords(strtolower(str_replace('_', ' ', $agent->role))) : 'Introducer';
        $createdByName = $agent && $agent->created_by ? (Agent::find($agent->created_by)->full_name ?? 'GeneralLink Admin') : 'Self-Registered';
        $createdAt = $agent && $agent->created_at ? $agent->created_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');
        $verifyLink = $agent ? route('auth.verify-email', $agent->email_verification_token) : null;

        return view('auth.verify-pending', [
            'agent'          => $agent,
            'brandLogoUrl'   => $logoUrl,
            'brandGroupName' => $groupName,
            'roleLabel'      => $roleLabel,
            'createdByName'  => $createdByName,
            'createdAt'      => $createdAt,
            'verifyLink'     => $verifyLink,
        ]);
    }

    // -------------------------------------------------------
    // Security phrase methods — KEPT for backward compatibility with the
    // 5 existing accounts that already have security_phrase_set = true.
    // NOT used by the new registration flow going forward (Decision 3).
    // -------------------------------------------------------
    public function showSecurityPhrase()
    {
        $agent = auth('agent')->user();
        if ($agent->security_phrase_set) {
            return $this->redirectToDashboard($agent);
        }
        return view('auth.security-phrase');
    }

    public function saveSecurityPhrase(Request $request)
    {
        $request->validate([
            'security_phrase' => ['required', 'string', 'min:4', 'max:100'],
        ]);

        $agent = auth('agent')->user();
        $agent->update([
            'security_phrase'     => Hash::make($request->security_phrase),
            'security_phrase_set' => true,
        ]);

        return $this->redirectToDashboard($agent);
    }

    // -------------------------------------------------------
    // Redirect to correct dashboard based on role (unchanged for
    // Admin/GL/TL/Introducer — DSG and ORG members land exactly where
    // they always have).
    //
    // NEW 17 Aug 2026 — per Chris: the login page itself is NOT
    // changed for anyone, including CBE. Only where the agent lands
    // AFTER logging in differs — a CBE member (group_labels.group_type
    // = 'CBE') is sent to the new GLADE-style Ecosystem Home instead
    // of the usual role dashboard, checked first since CBE doesn't use
    // the GROUP_LEADER/TEAM_LEADER/INTRODUCER role split at all.
    // -------------------------------------------------------
    private function redirectToDashboard(Agent $agent)
    {
        // NEW 28 Sep 2026 — member file item 31: logged in from an entity's QR join page → back to it
        if ($tok = session('pending_join_token')) {
            return redirect()->route('join.show', $tok);
        }
        if ($agent->group_label_id) {
            $groupType = DB::table('group_labels')->where('group_label_id', $agent->group_label_id)->value('group_type');
            if ($groupType === 'CBE') {
                return redirect()->route('cbe.dashboard');
            }
        }

        return match($agent->role) {
            'ADMIN'        => redirect()->route('admin.dashboard'),
            'GROUP_LEADER' => redirect()->route('gl.dashboard'),
            'TEAM_LEADER'  => redirect()->route('tl.dashboard'),
            default        => redirect()->route('introducer.dashboard'),
        };
    }

    // ---- NEW 28 Sep 2026 — member file item 30: registration duplicate rule ----
    private function memberFileMatch(Request $request): ?array
    {
        $phone = preg_replace('/\D/', '', (string) $request->input('phone'));
        $email = strtolower(trim((string) $request->input('email')));
        $q = DB::table('agents')->where('is_deleted', false)->where(function ($w) use ($phone, $email) {
            if (strlen($phone) >= 7) {
                $w->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone,'-',''),' ',''),'+',''),'(','') LIKE ?", ['%'.substr($phone, -9)]);
            }
            if ($email !== '') {
                $w->orWhere('email', $email);
            }
        });
        if (strlen($phone) < 7 && $email === '') {
            return null;
        }
        $hit = $q->orderByRaw('email_verified_at IS NULL')->first();
        if (! $hit) {
            return null;
        }
        $staffAdded = str_starts_with((string) $hit->agent_code, 'CBE-') || str_ends_with(strtolower((string) $hit->email), '@noemail.generallink.local');
        $hasLogin = ! empty($hit->email_verified_at) || ! $staffAdded;

        return ['agent' => $hit, 'has_login' => $hasLogin];
    }

    private function claimMemberRecord(Request $request, object $existing)
    {
        $request->validate([
            'upline_type' => ['required', 'in:GROUP_LEADER,TEAM_LEADER,INTRODUCER,ADMIN_ASSIGN'],
            'upline_agent_id' => ['required_unless:upline_type,ADMIN_ASSIGN', 'nullable', 'string'],
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', PhoneNumberService::rule()],
            'email' => ['required', 'email', 'max:200', \Illuminate\Validation\Rule::unique('agents', 'email')->ignore($existing->agent_id, 'agent_id')],
            'address' => ['required', 'string'],
            'postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
        ]);
        $agent = Agent::find($existing->agent_id);
        DB::transaction(function () use ($request, $agent) {
            $upd = [
                'email' => strtolower($request->email),
                'phone' => PhoneNumberService::normalize($request->phone),
                'second_name' => $request->second_name ?: $agent->second_name,
                'email_verification_token' => Str::random(64),
                'address' => $agent->address ?: $request->address,
                'postcode' => $agent->postcode ?: $request->postcode,
                'city' => $agent->city ?: $request->city,
                'state' => $agent->state ?: $request->state,
                'status' => 'INACTIVE',   // ACTIVE again once email is verified and the password is set
            ];
            // place him under the chosen upline (same as a new registration) when he has none yet
            if ($request->upline_type !== 'ADMIN_ASSIGN' && ! $agent->parent_id) {
                $sponsor = Agent::where('agent_id', $request->upline_agent_id)->where('status', 'ACTIVE')->where('is_deleted', false)->first();
                $hs = app(HierarchyService::class);
                if ($sponsor && $hs->canRecruit($sponsor)) {
                    $code = $hs->generateAgentCode($sponsor);
                    $upd += ['agent_code' => $code, 'member_code' => $code, 'parent_id' => $sponsor->agent_id, 'group_id' => $sponsor->group_id,
                        'origin_group_id' => $sponsor->group_id, 'hierarchy_path' => $sponsor->hierarchy_path.$agent->agent_id.'/',
                        'recruitable_tier_depth' => $sponsor->recruitable_tier_depth + 1, 'recruitment_blocked' => false];
                }
            }
            DB::table('agents')->where('agent_id', $agent->agent_id)->update($upd + ['updated_at' => now()]);
            if (! DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->exists()) {
                DB::table('agent_profiles')->insert(['profile_id' => Str::uuid()->toString(), 'agent_id' => $agent->agent_id, 'address' => $request->address,
                    'city' => $request->city, 'state' => $request->state, 'postcode' => $request->postcode, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        \App\Services\AuditService::logChange('agents', $agent->agent_id, 'REGISTRATION_CLAIM', null, ['email' => $agent->email, 'note' => 'Registration claimed the member file record added by staff']);
        // came from an entity QR code: join that entity at once (item 31)
        \App\Http\Controllers\JoinController::finishPendingJoin($request, $agent->agent_id);
        $this->sendVerificationEmail($agent->fresh());

        return redirect()->route('auth.verify-pending')->with('email', $agent->email);
    }
}
