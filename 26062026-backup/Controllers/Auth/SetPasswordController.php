<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Mail\AdminNewRegistrationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class SetPasswordController extends Controller
{
    /**
     * Show set password page
     */
    public function show()
    {
        $agent = auth('agent')->user();

        if (!$agent) {
            return redirect()->route('auth.login');
        }

        // Must have verified email first
        if (!$agent->email_verified_at) {
            return redirect()->route('auth.verify-email')
                ->withErrors(['error' => 'Please verify your email first.']);
        }

        // Already has password — go to dashboard
        if ($agent->password_hash) {
            return redirect($this->dashboardRoute($agent));
        }

        return view('auth.set-password');
    }

    /**
     * Handle password creation
     */
    public function store(Request $request)
    {
        $agent = auth('agent')->user();

        if (!$agent) {
            return redirect()->route('auth.login');
        }

        // Validate password
        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(10)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ], [
            'password.confirmed' => 'Passwords do not match.',
            'password.min'       => 'Password must be at least 10 characters.',
        ]);

        // Save password and activate account
        $agent->update([
            'password_hash' => Hash::make($request->password),
            'status'        => 'ACTIVE',
        ]);

        // Generate member code / agent code if not yet assigned
        if (!$agent->agent_code) {
            $agent->update([
                'agent_code' => $this->generateAgentCode($agent),
            ]);
        }

        // Notify Admin — Status: ACTIVE
        $this->notifyAdmin($agent);

        // Redirect to success page
        return redirect()->route('auth.verified')
            ->with('success', 'Password created successfully! Your account is now active.');
    }

    /**
     * Show verification success page
     */
    public function success()
    {
        $agent = auth('agent')->user();

        if (!$agent) {
            return redirect()->route('auth.login');
        }

        return view('auth.verification-success', [
            'dashboardRoute' => $this->dashboardRoute($agent),
        ]);
    }

    /**
     * Get dashboard route based on role
     */
    private function dashboardRoute(Agent $agent): string
    {
        return match($agent->role) {
            'ADMIN'        => route('admin.dashboard'),
            'GROUP_LEADER' => route('gl.dashboard'),
            'TEAM_LEADER'  => route('tl.dashboard'),
            default        => route('introducer.dashboard'),
        };
    }

    /**
     * Generate unique agent code based on parent hierarchy
     */
    private function generateAgentCode(Agent $agent): string
    {
        if (!$agent->parent_id) {
            // No parent — assign under default group
            $count = Agent::whereNull('parent_id')->count();
            return 'C0001-' . $count;
        }

        $parent = Agent::where('agent_id', $agent->parent_id)->first();
        if (!$parent || !$parent->agent_code) {
            return 'C0001-' . rand(100, 999);
        }

        // Count existing children of parent
        $childCount = Agent::where('parent_id', $agent->parent_id)
            ->whereNotNull('agent_code')
            ->count();

        return $parent->agent_code . '-' . $childCount;
    }

    /**
     * Notify admin — account now ACTIVE
     */
    private function notifyAdmin(Agent $agent): void
    {
        $adminEmail  = config('generallink.admin_email', env('ADMIN_EMAIL', 'admin@generallink.my'));
        $uplineAgent = $agent->parent_id
            ? Agent::where('agent_id', $agent->parent_id)->first()
            : null;

        $details = [
            'agent'       => $agent,
            'uplineAgent' => $uplineAgent,
            'uplineType'  => $uplineAgent?->role ?? 'ADMIN_ASSIGN',
            'status'      => 'ACTIVE',
            'submittedAt' => now()->format('d M Y, h:i A'),
        ];

        Mail::to($adminEmail)->send(new AdminNewRegistrationMail($details));
    }
}
