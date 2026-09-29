<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /**
     * Live affiliate code lookup — AJAX endpoint
     * Optimised for 1 million+ records
     * Min 4 chars, debounced on frontend (500ms)
     */
    public function lookup(Request $request)
    {
        $code       = trim($request->query('code', ''));
        $type       = trim($request->query('type', ''));
        $adminName  = config('generallink.admin_name', 'Admin');
        $adminPhone = config('generallink.admin_phone', 'Please contact the office');
        $contactMsg = "📞 Admin: {$adminName} | Tel: {$adminPhone}";

        // Minimum 4 characters
        if (strlen($code) < 4) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter at least 4 characters.',
            ]);
        }

        // Search by agent_code — indexed column, exact match, ACTIVE only
        $agent = Agent::where('agent_code', $code)
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->select('agent_id', 'full_name', 'role', 'agent_code', 'status', 'recruitment_blocked', 'parent_id')
            ->first();

        // Not found
        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => "Affiliate code not found. Please check the code and try again. {$contactMsg}",
            ]);
        }

        // Role must match selected upline type
        if ($type && $agent->role !== $type) {
            return response()->json([
                'success' => false,
                'message' => "This affiliate code belongs to a {$this->roleLabel($agent->role)}, not a {$this->roleLabel($type)}. Please select the correct upline type or enter a different code. {$contactMsg}",
            ]);
        }

        // Introducer — check recruitment blocked flag
        if ($agent->role === 'INTRODUCER') {
            if ($agent->recruitment_blocked) {
                return response()->json([
                    'success' => false,
                    'message' => "This affiliate is not eligible to recruit new members at this time. Please contact our office or choose a different upline. {$contactMsg}",
                ]);
            }

            // Check if Introducer already has 3 direct recruits
            $directCount = Agent::where('parent_id', $agent->agent_id)
                ->where('is_deleted', false)
                ->count();

            if ($directCount >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => "This affiliate has reached the maximum number of direct recruits and is not eligible to recruit new members at this time. {$contactMsg}",
                ]);
            }
        }

        // Success
        return response()->json([
            'success' => true,
            'agent'   => [
                'id'             => $agent->agent_id,
                'name'           => $agent->full_name,
                'affiliate_code' => $agent->agent_code,
                'role'           => $agent->role,
                'role_label'     => $this->roleLabel($agent->role),
                'status'         => $agent->status,
            ],
        ]);
    }

    private function roleLabel(string $role): string
    {
        return match($role) {
            'ADMIN'        => 'Admin',
            'GROUP_LEADER' => 'Group Leader',
            'TEAM_LEADER'  => 'Team Leader',
            'INTRODUCER'   => 'Introducer',
            default        => ucfirst(strtolower($role)),
        };
    }
}
