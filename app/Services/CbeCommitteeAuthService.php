<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 17 Sep 2026 — per Chris's own answer when asked who should be able
// to manage the temple Notice Board / Calendar (and, going forward, the
// document repository and WhatsApp/Email blast): the 3 node officers
// (Director, Finance, Membership/Sales — cbe_node_officers) plus the
// entity's current Secretary (cbe_committee_positions, position title
// "Secretary", within its own term_start_date/term_end_date). An
// ordinary member can read these screens but never write to them.
//
// Kept as one small shared service (not copy-pasted per controller) so
// every future "officers + secretary can manage this" screen reads the
// same rule the same way.
class CbeCommitteeAuthService
{
    public static function isAuthorizedManager(?object $agent, ?string $nodeId): bool
    {
        if (! $agent || ! $nodeId) {
            return false;
        }

        // Platform Admin already has full access to everything via the
        // Admin panel — never locked out of a screen it is viewing on
        // an entity's behalf (same picker every other CBE screen uses).
        if ($agent->role === 'ADMIN') {
            return true;
        }

        $isOfficer = DB::table('cbe_node_officers')
            ->where('agent_id', $agent->agent_id)
            ->where('node_id', $nodeId)
            ->where('is_active', true)
            ->whereIn('role', ['DIRECTOR', 'FINANCE', 'MEMBERSHIP'])
            ->exists();
        if ($isOfficer) {
            return true;
        }

        $today = now()->toDateString();

        return DB::table('cbe_committee_positions as p')
            ->join('cbe_group_memberships as m', 'm.membership_id', '=', 'p.membership_id')
            ->where('m.agent_id', $agent->agent_id)
            ->where('p.cbe_node_id', $nodeId)
            ->whereRaw('LOWER(p.position_title) = ?', ['secretary'])
            ->where('p.term_start_date', '<=', $today)
            ->where('p.term_end_date', '>=', $today)
            ->exists();
    }
}
