<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    // -------------------------------------------------------
    // Live upline lookup for the registration form.
    // GET /affiliate/lookup?code=1-4&type=TEAM_LEADER
    //
    // Only returns ACTIVE, non-deleted agents whose role matches the
    // selected upline type — matches the registration doc's rule:
    // "Sponsor token must exist and belong to an active agent."
    // -------------------------------------------------------
    public function lookup(Request $request)
    {
        $code = trim((string) $request->query('code', ''));
        $type = $request->query('type', '');

        if (strlen($code) < 4) {
            return response()->json(['success' => false, 'message' => 'Enter at least 4 characters.']);
        }

        $validRoles = ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];
        if (! in_array($type, $validRoles, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid upline type.']);
        }

        $agent = Agent::where('agent_code', $code)
            ->where('role', $type)
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->first(['agent_id', 'full_name', 'agent_code', 'role']);

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => 'No active ' . $this->roleLabel($type) . ' found with that code.',
            ]);
        }

        return response()->json([
            'success' => true,
            'agent' => [
                'id'             => $agent->agent_id,
                'name'           => $agent->full_name,
                'affiliate_code' => $agent->agent_code,
                'role_label'     => $this->roleLabel($agent->role),
            ],
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
}
