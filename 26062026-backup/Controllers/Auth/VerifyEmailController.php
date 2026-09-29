<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Mail\AdminNewRegistrationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class VerifyEmailController extends Controller
{
    /**
     * Show verify email notice page
     */
    public function show()
    {
        return view('auth.verify-email');
    }

    /**
     * Handle verification link click
     */
    public function verify(Request $request, string $token)
    {
        // Check request is valid signed URL
        if (!$request->hasValidSignature()) {
            return redirect()->route('auth.login')
                ->withErrors(['error' => 'This verification link has expired or is invalid. Please register again or request a new link.']);
        }

        // Find agent by token
        $agent = Agent::where('email_verification_token', $token)
            ->where('is_deleted', false)
            ->first();

        if (!$agent) {
            return redirect()->route('auth.login')
                ->withErrors(['error' => 'Invalid verification link. Please contact support.']);
        }

        // Already verified
        if ($agent->email_verified_at) {
            return redirect()->route('auth.set-password')
                ->with('info', 'Email already verified. Please set your password.');
        }

        // Mark email as verified
        $agent->update([
            'email_verified_at'         => now(),
            'email_verification_token'  => null,
        ]);

        // Auto login
        auth('agent')->login($agent);

        // Redirect to set password
        return redirect()->route('auth.set-password')
            ->with('success', 'Email verified successfully! Please create your password.');
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request)
    {
        $agent = auth('agent')->user();

        if (!$agent) {
            return redirect()->route('auth.login');
        }

        if ($agent->email_verified_at) {
            return redirect()->route('auth.set-password')
                ->with('info', 'Your email is already verified.');
        }

        // Generate new token
        $newToken = \Illuminate\Support\Str::random(64);
        $agent->update(['email_verification_token' => $newToken]);

        // Build new verification URL
        $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'auth.verify-email.token',
            now()->addHours(24),
            ['token' => $newToken]
        );

        // Resend email
        Mail::to($agent->email)->send(new \App\Mail\AgentVerificationMail($agent, $verificationUrl));

        return back()->with('success', 'Verification email resent! Please check your inbox.');
    }
}
