<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\HierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #212). "My Badges" —
// earned milestone badges plus live progress toward the next ones.
// Read-only, same metrics as the badges:evaluate command. UPDATED 25
// Jul 2026 — per Chris: Sales/Earning Income badges dropped (duplicated
// the KPI Dashboard's own Top 3 Sales/Earning views) in favour of
// leadership/network badges (TL Promotions, GL Promotions, Network
// Size) that aren't shown anywhere else, plus a drill-down detail view
// per category so Chris can see exactly WHO/WHEN behind each number.
class BadgeController extends Controller
{
    public function index(Request $request, HierarchyService $hierarchy)
    {
        $agent = Auth::guard('agent')->user();
        $isAdmin = $agent->role === 'ADMIN';

        $badges = DB::table('badge_definitions')->where('is_active', true)->orderBy('sort_order')->get();
        $earned = DB::table('agent_badges')->where('agent_id', $agent->agent_id)->get()->keyBy('badge_code');

        // Group Label filter — Admin only. Per Chris: "always have filter
        // for which group label, which management, operation affiliates
        // or all." Non-admin roles only ever see their own downline, so
        // the filter is hidden for them (it would be a no-op). Every
        // count below is a plain SQL COUNT() — never a full record pull
        // — so this stays fast however many agents exist (per Chris:
        // "always remember 10000000 records, so dont hang the screen").
        $groupLabelId = $isAdmin && $request->filled('group_label_id') ? $request->get('group_label_id') : 'ALL';
        $groupLabels = $isAdmin ? DB::table('group_labels')->orderBy('group_name')->get(['group_label_id', 'group_name']) : collect();

        if ($isAdmin) {
            $scope = Agent::where('status', 'ACTIVE')->where('is_deleted', false)
                ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER']);
            if ($groupLabelId === 'SYSTEM_DEFAULT') {
                $scope->whereNull('group_label_id');
            } elseif ($groupLabelId !== 'ALL') {
                $scope->where('group_label_id', $groupLabelId);
            }
            $subtreeIds = (clone $scope)->pluck('agent_id')->all();

            // Company-wide aggregate mode: "Recruiting" = fresh Introducers
            // in scope, "Network Size" = everyone in scope (so the two
            // stay distinct instead of duplicating one another).
            $recruitCount = (float) (clone $scope)->where('role', 'INTRODUCER')->count();
            $networkSize = (float) count($subtreeIds);
        } else {
            $subtreeIds = array_values(array_diff($hierarchy->subtreeAgentIds($agent), [$agent->agent_id]));
            $recruitCount = (float) Agent::where('parent_id', $agent->agent_id)
                ->where('status', 'ACTIVE')->where('is_deleted', false)->count();
            $networkSize = (float) Agent::whereIn('agent_id', $subtreeIds)->where('status', 'ACTIVE')->where('is_deleted', false)->count();
        }

        $tlPromotions = (float) DB::table('role_history')->where('new_role', 'TEAM_LEADER')->whereIn('agent_id', $subtreeIds)->count();
        $glPromotions = (float) DB::table('role_history')->where('new_role', 'GROUP_LEADER')->whereIn('agent_id', $subtreeIds)->count();

        foreach ($badges as $b) {
            $current = match ($b->badge_type) {
                'RECRUIT_COUNT'  => $recruitCount,
                'TL_PROMOTIONS'  => $tlPromotions,
                'GL_PROMOTIONS'  => $glPromotions,
                'NETWORK_SIZE'   => $networkSize,
                default          => 0.0,
            };
            $b->current_value = $current;
            $b->pct = $b->threshold_value > 0 ? min(100, round(($current / $b->threshold_value) * 100)) : 0;
            $b->earned_row = $earned->get($b->badge_code);
            // "Achieved" drives the icon/colour. For a personal view this
            // is the same as having a real agent_badges row. For Admin's
            // company-wide aggregate view there IS no personal award row
            // (Admin isn't personally awarded a badge for the whole
            // company crossing a milestone) — so it's derived straight
            // from current vs. threshold instead, otherwise the box would
            // show "100%" next to a lock icon, which is exactly the
            // contradiction Chris flagged as confusing.
            $b->achieved = $isAdmin ? ($current >= (float) $b->threshold_value) : (bool) $b->earned_row;
        }

        // Grouped by category so the view can render one row per
        // category (never mixing categories in the same row) — per
        // Chris: "the box sequence is not appropriate in same row same
        // category."
        $groups = [
            'RECRUIT_COUNT' => ['label' => 'Recruiting', 'icon' => '🎯', 'badges' => $badges->where('badge_type', 'RECRUIT_COUNT')->values(), 'current' => $recruitCount],
            'TL_PROMOTIONS' => ['label' => 'TL Promotions', 'icon' => '👔', 'badges' => $badges->where('badge_type', 'TL_PROMOTIONS')->values(), 'current' => $tlPromotions],
            'GL_PROMOTIONS' => ['label' => 'GL Promotions', 'icon' => '👑', 'badges' => $badges->where('badge_type', 'GL_PROMOTIONS')->values(), 'current' => $glPromotions],
            'NETWORK_SIZE'  => ['label' => 'Network Size', 'icon' => '🌐', 'badges' => $badges->where('badge_type', 'NETWORK_SIZE')->values(), 'current' => $networkSize],
        ];

        $earnedCount = $earned->count();
        $totalCount = $badges->count();

        return view('growth.badges', compact('groups', 'earnedCount', 'totalCount', 'agent', 'isAdmin', 'groupLabelId', 'groupLabels'));
    }

    // Drill-down — per Chris: "must have drill down to know who what
    // all the detail." One screen, Prev/Next pagination, never scrolls.
    public function detail(Request $request, string $type, HierarchyService $hierarchy)
    {
        $agent = Auth::guard('agent')->user();
        $isAdmin = $agent->role === 'ADMIN';
        abort_unless(in_array($type, ['RECRUIT_COUNT', 'TL_PROMOTIONS', 'GL_PROMOTIONS', 'NETWORK_SIZE'], true), 404);

        $groupLabelId = $isAdmin && $request->filled('group_label_id') ? $request->get('group_label_id') : 'ALL';

        if ($isAdmin) {
            $scope = Agent::where('status', 'ACTIVE')->where('is_deleted', false)
                ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER']);
            if ($groupLabelId === 'SYSTEM_DEFAULT') {
                $scope->whereNull('group_label_id');
            } elseif ($groupLabelId !== 'ALL') {
                $scope->where('group_label_id', $groupLabelId);
            }
            $subtreeIds = (clone $scope)->pluck('agent_id')->all();
        } else {
            $subtreeIds = array_values(array_diff($hierarchy->subtreeAgentIds($agent), [$agent->agent_id]));
        }

        $typeLabels = [
            'RECRUIT_COUNT' => 'Recruiting — Direct Recruits',
            'TL_PROMOTIONS' => 'TL Promotions — Your Downline',
            'GL_PROMOTIONS' => 'GL Promotions — Your Downline',
            'NETWORK_SIZE'  => 'Network Size — Entire Downline',
        ];

        $rows = match ($type) {
            'RECRUIT_COUNT' => ($isAdmin
                    ? Agent::whereIn('agent_id', $subtreeIds)->where('role', 'INTRODUCER')
                    : Agent::where('parent_id', $agent->agent_id))
                ->where('status', 'ACTIVE')->where('is_deleted', false)
                ->orderByDesc('created_at')
                ->paginate(8, ['*'], 'p')
                ->through(fn ($a) => (object) [
                    'full_name' => $a->full_name, 'agent_code' => $a->agent_code,
                    'role'      => $a->role, 'when' => $a->created_at,
                ]),
            'TL_PROMOTIONS' => DB::table('role_history as rh')
                ->join('agents as a', 'rh.agent_id', '=', 'a.agent_id')
                ->where('rh.new_role', 'TEAM_LEADER')->whereIn('rh.agent_id', $subtreeIds)
                ->orderByDesc('rh.effective_date')
                ->select('a.full_name', 'a.agent_code', 'a.role', 'rh.effective_date as when')
                ->paginate(8, ['*'], 'p'),
            'GL_PROMOTIONS' => DB::table('role_history as rh')
                ->join('agents as a', 'rh.agent_id', '=', 'a.agent_id')
                ->where('rh.new_role', 'GROUP_LEADER')->whereIn('rh.agent_id', $subtreeIds)
                ->orderByDesc('rh.effective_date')
                ->select('a.full_name', 'a.agent_code', 'a.role', 'rh.effective_date as when')
                ->paginate(8, ['*'], 'p'),
            'NETWORK_SIZE' => Agent::whereIn('agent_id', $subtreeIds)
                ->where('status', 'ACTIVE')->where('is_deleted', false)
                ->orderByDesc('created_at')
                ->paginate(8, ['*'], 'p')
                ->through(fn ($a) => (object) [
                    'full_name' => $a->full_name, 'agent_code' => $a->agent_code,
                    'role'      => $a->role, 'when' => $a->created_at,
                ]),
        };

        $typeLabel = $typeLabels[$type];

        // Keep the Group Label filter in the Prev/Next pagination links
        // so drilling deeper doesn't silently drop back to "All Groups."
        if ($isAdmin) {
            $rows->appends(['group_label_id' => $groupLabelId]);
        }

        return view('growth.badge-detail', compact('rows', 'type', 'typeLabel', 'agent', 'isAdmin', 'groupLabelId'));
    }
}
