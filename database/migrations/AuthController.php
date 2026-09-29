<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\HierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // -------------------------------------------------------
    // Show login page (unchanged)
    // -------------------------------------------------------
    public function showLogin()
    {
        if (auth('agent')->check()) {
            return $this->redirectToDashboard(auth('agent')->user());
        }
        return view('auth.login');
    }

    // -------------------------------------------------------
    // Process login (unchanged from current live version — NOT restoring
    // the email_verified_at block yet, see note to user)
    // -------------------------------------------------------
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $agent = Agent::where('email', $request->email)
            ->where('is_deleted', false)
            ->first();

        if (!$agent) {
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

        if (!Hash::check($request->password, $agent->password_hash)) {
            $agent->increment('failed_login_attempts');
            if ($agent->failed_login_attempts >= 5) {
                $agent->update(['locked_until' => now()->addMinutes(30)]);
                return back()->withErrors(['email' => 'Too many failed attempts. Your account is locked for 30 minutes.'])->withInput();
            }
            return back()->withErrors(['password' => 'Incorrect password. Please try again.'])->withInput();
        }

        $agent->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        auth('agent')->login($agent, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectToDashboard($agent);
    }

    public function logout(Request $request)
    {
        auth('agent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.login')->with('success', 'You have been logged out successfully.');
    }

    // -------------------------------------------------------
    // NEW 03 Jul 2026 — Show registration form
    // -------------------------------------------------------
    public function showRegister()
    {
        return view('auth.register');
    }

    // -------------------------------------------------------
    // NEW 03 Jul 2026 — Process registration form submission
    // -------------------------------------------------------
    public function register(Request $request)
    {
        $request->validate([
            'upline_type'           => ['required', 'in:GROUP_LEADER,TEAM_LEADER,INTRODUCER,ADMIN_ASSIGN'],
            'upline_agent_id'       => ['required_unless:upline_type,ADMIN_ASSIGN', 'nullable', 'string'],
            'name'                  => ['required', 'string', 'max:200'],
            'nric'                  => ['required', 'string'],
            'phone'                 => ['required', 'string', 'max:20'],
            'email'                 => ['required', 'email', 'max:200', 'unique:agents,email'],
            'address'               => ['required', 'string'],
            'bank_name'             => ['required', 'string', 'max:100'],
            'bank_account'          => ['required', 'string', 'max:20'],
        ]);

        // Known gap (flagged, not hidden): no nric_hash column exists in the
        // live schema to check NRIC uniqueness efficiently. Skipped for now.

        $agentData = [
            'full_name'              => $request->name,
            'email'                  => strtolower($request->email),
            'password_hash'          => Hash::make(Str::random(32)), // temporary — replaced when they set their real password
            'nric_encrypted'         => Crypt::encryptString($request->nric),
            'phone'                  => $request->phone,
            'bank_name'              => $request->bank_name,
            'bank_account_encrypted' => Crypt::encryptString($request->bank_account),
            'email_verification_token' => Str::random(64),
        ];

        if ($request->upline_type === 'ADMIN_ASSIGN') {
            // No sponsor yet — Admin assigns later via the existing Pending
            // Assignment queue. agent_code/member_code stay NULL until then.
            $agent = Agent::create(array_merge($agentData, [
                'agent_id'   => Str::uuid()->toString(),
                'role'       => 'INTRODUCER',
                'status'     => 'ACTIVE',
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

        $this->sendVerificationEmail($agent);

        return redirect()->route('auth.verify-pending')->with('email', $agent->email);
    }

    private function sendVerificationEmail(Agent $agent): void
    {
        $link = route('auth.verify-email', $agent->email_verification_token);

        Mail::raw(
            "Hi {$agent->full_name},\n\nWelcome to GeneralLink! Please verify your email by clicking the link below:\n\n{$link}\n\nThis link expires in 24 hours.",
            function ($message) use ($agent) {
                $message->to($agent->email)
                        ->subject('GeneralLink — Verify Your Email');
            }
        );
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

        auth('agent')->login($agent);

        // Decision 3 (03 Jul 2026) — simple password step, no security phrase
        return redirect()->route('auth.set-password');
    }

    // -------------------------------------------------------
    // NEW 03 Jul 2026 — Simple password setup (Decision 3: no strength
    // rules, no separate security phrase step)
    // -------------------------------------------------------
    public function showSetPassword()
    {
        return view('auth.set-password');
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $agent = auth('agent')->user();
        $agent->update([
            'password_hash' => Hash::make($request->password),
        ]);

        return redirect()->route('auth.verification-success');
    }

    public function verificationSuccess()
    {
        return view('auth.verification-success');
    }

    // -------------------------------------------------------
    // Verify pending page (unchanged)
    // -------------------------------------------------------
    public function verifyPending()
    {
        return view('auth.verify-pending');
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
    // Redirect to correct dashboard based on role (unchanged)
    // -------------------------------------------------------
    private function redirectToDashboard(Agent $agent)
    {
        return match($agent->role) {
            'ADMIN'        => redirect()->route('admin.dashboard'),
            'GROUP_LEADER' => redirect()->route('gl.dashboard'),
            'TEAM_LEADER'  => redirect()->route('tl.dashboard'),
            default        => redirect()->route('introducer.dashboard'),
        };
    }
}
