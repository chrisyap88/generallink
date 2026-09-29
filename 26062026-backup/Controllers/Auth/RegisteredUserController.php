<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Group;
use App\Mail\AgentVerificationMail;
use App\Mail\AdminNewRegistrationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    /**
     * Show registration form — public
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Handle registration form submission
     */
    public function store(Request $request)
    {
        // Validate all fields
        $request->validate([
            'upline_type'           => ['required', 'in:GROUP_LEADER,TEAM_LEADER,INTRODUCER,ADMIN_ASSIGN'],
            'upline_affiliate_code' => ['required_unless:upline_type,ADMIN_ASSIGN', 'nullable', 'string'],
            'upline_agent_id'       => ['required_unless:upline_type,ADMIN_ASSIGN', 'nullable', 'string'],
            'name'                  => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'nric'                  => ['required', 'string', 'regex:/^\d{6}-\d{2}-\d{4}$/', 'unique:agents,nric_encrypted'],
            'email'                 => ['required', 'email', 'unique:agents,email'],
            'phone'                 => ['required', 'string', 'regex:/^01[0-9]-?\d{7,8}$/'],
            'address'               => ['required', 'string', 'min:10', 'max:500'],
            'bank_name'             => ['required', 'string'],
            'bank_account'          => ['required', 'string', 'regex:/^\d{5,20}$/'],
        ], [
            'name.regex'          => 'Full name must contain letters only.',
            'nric.regex'          => 'NRIC must follow Malaysia format e.g. 900101-10-1234.',
            'nric.unique'         => 'This NRIC is already registered.',
            'email.unique'        => 'This email is already registered.',
            'phone.regex'         => 'Must be a valid Malaysia mobile number e.g. 012-3456789.',
            'address.min'         => 'Please enter your full address (minimum 10 characters).',
            'bank_account.regex'  => 'Bank account must be numbers only, 5-20 digits.',
        ]);

        // If not Admin Assign — verify upline agent exists and is active
        $uplineAgent = null;
        if ($request->upline_type !== 'ADMIN_ASSIGN') {
            $uplineAgent = Agent::where('agent_id', $request->upline_agent_id)
                ->where('status', 'ACTIVE')
                ->where('is_deleted', false)
                ->first();

            if (!$uplineAgent) {
                return back()->withErrors(['upline_affiliate_code' => 'Invalid upline. Please search and select a valid affiliate code.'])->withInput();
            }
        }

        // Generate email verification token
        $verificationToken = Str::random(64);

        // Generate agent_id (UUID)
        $agentId = Str::uuid()->toString();

        // Create agent record
        $agent = Agent::create([
            'agent_id'                  => $agentId,
            'full_name'                 => $request->name,
            'email'                     => $request->email,
            'nric_encrypted'            => $request->nric, // TODO: encrypt in production
            'phone'                     => $request->phone,
            'address'                   => $request->address,
            'bank_name'                 => $request->bank_name,
            'bank_account_encrypted'    => $request->bank_account, // TODO: encrypt in production
            'role'                      => 'INTRODUCER', // ALL new registrations start as Introducer
            'status'                    => 'PENDING',
            'parent_id'                 => $uplineAgent?->agent_id ?? null,
            'email_verification_token'  => $verificationToken,
            'recruitment_blocked'       => false,
            'is_deleted'                => false,
            'created_by'                => $uplineAgent?->agent_id ?? 'SYSTEM',
        ]);

        // Build signed verification URL — 24 hour expiry
        $verificationUrl = URL::temporarySignedRoute(
            'auth.verify-email.token',
            now()->addHours(24),
            ['token' => $verificationToken]
        );

        // Send verification email to agent
        Mail::to($agent->email)->send(new AgentVerificationMail($agent, $verificationUrl));

        // Notify Admin — Status: PENDING VERIFICATION
        $this->notifyAdmin($agent, $uplineAgent, $request->upline_type, 'PENDING VERIFICATION');

        // Auto login
        auth('agent')->login($agent);

        // Redirect to verify email page
        return redirect()->route('auth.verify-email')
            ->with('success', 'Registration successful! Please check your email to verify your account.');
    }

    /**
     * Register new affiliate from GL/TL/Introducer dashboard
     * Upline is automatically the logged-in agent
     */
    public function createByAgent()
    {
        return view('auth.register-affiliate', [
            'uplineAgent' => auth('agent')->user(),
        ]);
    }

    public function storeByAgent(Request $request)
    {
        $loggedInAgent = auth('agent')->user();

        // Check if logged-in agent can recruit
        if ($loggedInAgent->recruitment_blocked) {
            return back()->withErrors(['error' => 'You are not eligible to recruit new members at this time.']);
        }

        // For Introducer — check max 3 direct recruits
        if ($loggedInAgent->role === 'INTRODUCER') {
            $directCount = Agent::where('parent_id', $loggedInAgent->agent_id)
                ->where('is_deleted', false)
                ->count();
            if ($directCount >= 3) {
                return back()->withErrors(['error' => 'You have reached the maximum number of direct recruits.']);
            }
        }

        // Merge upline info into request
        $request->merge([
            'upline_type'     => $loggedInAgent->role,
            'upline_agent_id' => $loggedInAgent->agent_id,
        ]);

        // Reuse store logic
        return $this->store($request);
    }

    /**
     * Send admin notification email
     */
    private function notifyAdmin(Agent $agent, ?Agent $uplineAgent, string $uplineType, string $status): void
    {
        $adminEmail = config('generallink.admin_email', env('ADMIN_EMAIL', 'admin@generallink.my'));

        $details = [
            'agent'        => $agent,
            'uplineAgent'  => $uplineAgent,
            'uplineType'   => $uplineType,
            'status'       => $status,
            'submittedAt'  => now()->format('d M Y, h:i A'),
        ];

        Mail::to($adminEmail)->send(new AdminNewRegistrationMail($details));
    }
}
