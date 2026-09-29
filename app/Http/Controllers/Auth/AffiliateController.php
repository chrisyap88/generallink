<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    // -------------------------------------------------------
    // Live upline lookup for the registration form.
    // GET /affiliate/lookup?q=amy&type=TEAM_LEADER
    //
    // Wildcard search by NAME or CODE (partial match, either one) —
    // returns a LIST of matches for a pick-list dropdown, so the person
    // doesn't need to already know the sponsor's exact full code.
    // Only returns ACTIVE, non-deleted agents whose role matches the
    // selected upline type — matches the registration doc's rule:
    // "Sponsor token must exist and belong to an active agent."
    // -------------------------------------------------------
    public function lookup(Request $request)
    {
        $q    = trim((string) $request->query('q', $request->query('code', '')));
        $type = $request->query('type', '');

        if (strlen($q) < 2) {
            return response()->json(['success' => false, 'message' => 'Enter at least 2 characters.']);
        }

        $validRoles = ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];
        if (! in_array($type, $validRoles, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid upline type.']);
        }

        $matches = Agent::where('role', $type)
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->where(function ($query) use ($q) {
                $query->where('agent_code', 'like', '%' . $q . '%')
                      ->orWhere('full_name', 'like', '%' . $q . '%');
            })
            ->orderBy('full_name')
            ->limit(15)
            ->get(['agent_id', 'full_name', 'agent_code', 'role']);

        if ($matches->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active ' . $this->roleLabel($type) . ' found matching "' . $q . '".',
            ]);
        }

        return response()->json([
            'success' => true,
            'matches' => $matches->map(fn ($agent) => [
                'id'             => $agent->agent_id,
                'name'           => $agent->full_name,
                'affiliate_code' => $agent->agent_code,
                'role_label'     => $this->roleLabel($agent->role),
            ]),
        ]);
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'GROUP_LEADER' => 'Group Leader',
            'TEAM_LEADER'  => 'Team Leader',
            'INTRODUCER'   => 'Introducer',
            default        => $role,
        };
    }

    // -------------------------------------------------------
    // QR code lookup — used both when arriving via a scanned QR link
    // (?ref=TOKEN in the URL) and when uploading a QR image directly on
    // the form (decoded client-side, then just the token is sent here).
    // Returns the sponsor's role too, so the form can auto-select the
    // correct Upline Type — the person doesn't need to already know it.
    // -------------------------------------------------------
    public function lookupByToken(Request $request)
    {
        $token = trim((string) $request->query('token', ''));

        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'No QR token provided.']);
        }

        $agent = Agent::where('qr_code_token', $token)
            ->whereIn('role', ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'])
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->first(['agent_id', 'full_name', 'agent_code', 'role']);

        if (! $agent) {
            return response()->json(['success' => false, 'message' => 'QR code not recognized or sponsor is no longer active.']);
        }

        // NEW 25 Jul 2026 (task #210) — Growth & Outreach Center referral
        // analytics. This is the ONE place both ways of arriving via a
        // referral link/QR pass through (direct ?ref= URL auto-lookup on
        // page load, and the "upload a QR image" option), so logging
        // here catches both. The click_id is stashed in session so
        // register() below can mark it converted if this exact sponsor
        // ends up being used — never counted as a signup if the visitor
        // picks a different sponsor manually afterward.
        $clickId = (string) Str::uuid();
        DB::table('referral_clicks')->insert([
            'click_id'   => $clickId,
            'agent_id'   => $agent->agent_id,
            'ip_hash'    => hash('sha256', $request->ip() . '|' . now()->format('Y-m-d')),
            'clicked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $request->session()->put('referral_click_id', $clickId);

        return response()->json([
            'success' => true,
            'agent' => [
                'id'             => $agent->agent_id,
                'name'           => $agent->full_name,
                'affiliate_code' => $agent->agent_code,
                'role'           => $agent->role,
                'role_label'     => $this->roleLabel($agent->role),
            ],
        ]);
    }
}
