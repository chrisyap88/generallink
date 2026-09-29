<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Show login page
     */
    public function showLogin()
    {
        if (auth('agent')->check()) {
            return $this->redirectToDashboard(auth('agent')->user());
        }
        return view('auth.login');
    }

    /**
     * Handle login form submission
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Find agent by email
        $agent = Agent::where('email', $request->email)
            ->where('is_deleted', false)
            ->first();

        // Check agent exists
        if (!$agent) {
            return back()->withErrors(['email' => 'No account found with this email address.'])->withInput();
        }

        // Check account status
        if ($agent->status === 'PENDING') {
            return back()->withErrors(['email' => 'Your account is pending email verification. Please check your email.'])->withInput();
        }

        if ($agent->status === 'SUSPENDED') {
            return back()->withErrors(['email' => 'Your account has been suspended. Please contact admin.'])->withInput();
        }

        if ($agent->status === 'TERMINATED') {
            return back()->withErrors(['email' => 'Your account has been terminated. Please contact admin.'])->withInput();
        }

        // Check if locked
        if ($agent->isLocked()) {
            return back()->withErrors(['email' => 'Your account is temporarily locked due to too many failed login attempts. Please try again later.'])->withInput();
        }

        // Verify password
        if (!Hash::check($request->password, $agent->password_hash)) {
            // Increment failed attempts
            $agent->increment('failed_login_attempts');

            // Lock after 5 failed attempts
            if ($agent->failed_login_attempts >= 5) {
                $agent->update(['locked_until' => now()->addMinutes(30)]);
                return back()->withErrors(['email' => 'Too many failed attempts. Your account is locked for 30 minutes.'])->withInput();
            }

            return back()->withErrors(['password' => 'Incorrect password. Please try again.'])->withInput();
        }

        // Reset failed attempts on successful login
        $agent->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);

        // Login
        auth('agent')->login($agent, $request->boolean('remember'));

        $request->session()->regenerate();

        return $this->redirectToDashboard($agent);
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        auth('agent')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('auth.login')->with('success', 'You have been logged out successfully.');
    }

    /**
     * Show verify pending page
     */
    public function verifyPending()
    {
        return view('auth.verify-email');
    }

    /**
     * Show security phrase setup page
     */
    public function showSecurityPhrase()
    {
        $agent = auth('agent')->user();

        if ($agent->security_phrase_set) {
            return $this->redirectToDashboard($agent);
        }

        return view('auth.security-phrase');
    }

    /**
     * Save security phrase
     */
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

    /**
     * Redirect to correct dashboard based on role
     */
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
