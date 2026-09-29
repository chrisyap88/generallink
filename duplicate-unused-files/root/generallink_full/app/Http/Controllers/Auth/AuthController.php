<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 30;

    // -------------------------------------------------------
    // Show login page
    // All 4 roles share one login page — role is determined after auth
    // -------------------------------------------------------
    public function showLogin()
    {
        if (Auth::guard('agent')->check()) {
            return $this->redirectByRole(Auth::guard('agent')->user());
        }
        return view('auth.login');
    }

    // -------------------------------------------------------
    // Process login
    // -------------------------------------------------------
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login.' . strtolower($request->email) . '.' . $request->ip();

        // Rate limit check
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $agent = Agent::where('email', strtolower($request->email))
                      ->where('is_deleted', false)
                      ->first();

        // Check agent exists and password matches
        if (! $agent || ! Hash::check($request->password, $agent->password_hash)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_MINUTES * 60);

            // Increment failed attempts on the record
            if ($agent) {
                $agent->increment('failed_login_attempts');
                if ($agent->failed_login_attempts >= self::MAX_ATTEMPTS) {
                    $agent->update(['locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)]);
                }
            }

            AuditService::logLogin($request, $agent?->agent_id, false, 'Invalid credentials');

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // Check account lock
        if ($agent->isLocked()) {
            AuditService::logLogin($request, $agent->agent_id, false, 'Account locked');
            throw ValidationException::withMessages([
                'email' => 'Your account is locked. Please check your email to unlock it.',
            ]);
        }

        // Check account status
        if (! $agent->isActive()) {
            AuditService::logLogin($request, $agent->agent_id, false, "Account status: {$agent->status}");
            throw ValidationException::withMessages([
                'email' => 'Your account is not active. Please contact your administrator.',
            ]);
        }

        // Check email verification
        if (! $agent->email_verified_at) {
            return redirect()->route('auth.verify-pending')
                             ->with('email', $agent->email);
        }

        // Reset failed attempts on successful auth
        RateLimiter::clear($throttleKey);
        $agent->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        // Log the user in
        Auth::guard('agent')->login($agent, $request->boolean('remember'));

        AuditService::logLogin($request, $agent->agent_id, true);

        // First-time login — must set security phrase
        if (! $agent->security_phrase_set) {
            return redirect()->route('auth.security-phrase');
        }

        $request->session()->regenerate();

        return $this->redirectByRole($agent);
    }

    // -------------------------------------------------------
    // Security phrase setup (first-time login only)
    // -------------------------------------------------------
    public function showSecurityPhrase()
    {
        return view('auth.security-phrase');
    }

    public function saveSecurityPhrase(Request $request)
    {
        $request->validate([
            'security_phrase' => ['required', 'string', 'min:4', 'max:100'],
        ]);

        $agent = Auth::guard('agent')->user();
        $agent->update([
            'security_phrase'     => Hash::make($request->security_phrase),
            'security_phrase_set' => true,
        ]);

        return $this->redirectByRole($agent);
    }

    // -------------------------------------------------------
    // Logout
    // -------------------------------------------------------
    public function logout(Request $request)
    {
        Auth::guard('agent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.login');
    }

    // -------------------------------------------------------
    // Email verification pending page
    // -------------------------------------------------------
    public function verifyPending()
    {
        return view('auth.verify-pending');
    }

    // -------------------------------------------------------
    // Email verification link handler
    // -------------------------------------------------------
    public function verifyEmail(Request $request, string $token)
    {
        $agent = Agent::where('email_verification_token', $token)
                      ->where('is_deleted', false)
                      ->first();

        if (! $agent) {
            return redirect()->route('auth.login')
                             ->with('error', 'Invalid or expired verification link.');
        }

        $agent->update([
            'email_verified_at'       => now(),
            'email_verification_token'=> null,
        ]);

        Auth::guard('agent')->login($agent);

        return redirect()->route('auth.security-phrase')
                         ->with('success', 'Email verified! Please set your security phrase.');
    }

    // -------------------------------------------------------
    // Role-based redirect after login
    // -------------------------------------------------------
    private function redirectByRole(Agent $agent)
    {
        return match ($agent->role) {
            'ADMIN'        => redirect()->route('admin.dashboard'),
            'GROUP_LEADER' => redirect()->route('gl.dashboard'),
            'TEAM_LEADER'  => redirect()->route('tl.dashboard'),
            'INTRODUCER'   => redirect()->route('introducer.dashboard'),
            default        => redirect()->route('auth.login'),
        };
    }
}
