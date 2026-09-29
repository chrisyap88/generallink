<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\RoleLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 31 Jul 2026 — Configurable Rank System, Phase 4. Lets Chris
// configure the criteria that automatically promote/demote an agent
// INTO one of his configured ranks (role_ranks) — no Excel, no manual
// tagging once this is set up. Mirrors PromotionRuleController's
// list-of-rules-per-scope pattern, but scoped directly by rank_id
// (a rank_id already fully identifies its own role + group via
// role_ranks.role / role_ranks.group_id, so there's nothing extra to
// pick beyond Role -> Group -> Rank).
//
// Adds a 4th criteria type beyond the 3 role-promotion already has:
// TOP_N_BY_METRIC — for Chris's "HQ" example, where threshold_value
// holds N (e.g. 1 = single highest) rather than a minimum bar, and
// whoever ranks in the top N by the chosen metric across everyone
// sharing that rank's role+group holds it automatically.
// -------------------------------------------------------
class RankPromotionRuleController extends Controller
{
    private const ROLES = ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];
    private const CRITERIA = ['RECRUIT_COUNT', 'SALES_VOLUME', 'TENURE_MONTHS', 'TOP_N_BY_METRIC'];

    public function index(Request $request)
    {
        $role = $request->filled('role') && in_array($request->get('role'), self::ROLES) ? $request->get('role') : 'GROUP_LEADER';
        $groupId = $request->filled('group_id') ? $request->get('group_id') : null;

        $groups = DB::table('groups')->where('is_active', true)->orderBy('group_name')->get(['group_id', 'group_name']);

        $ranksQuery = DB::table('role_ranks')->where('role', $role)->where('is_active', true);
        $ranks = $groupId
            ? (clone $ranksQuery)->where('group_id', $groupId)->orderBy('display_order')->get()
            : (clone $ranksQuery)->whereNull('group_id')->orderBy('display_order')->get();

        $rankId = $request->filled('rank_id') && $ranks->contains('rank_id', $request->get('rank_id'))
            ? $request->get('rank_id')
            : optional($ranks->first())->rank_id;

        $rules = $rankId
            ? DB::table('rank_promotion_rules')->where('rank_id', $rankId)->orderBy('created_at')->get()
            : collect();

        $combineLogic = $rankId
            ? (DB::table('rank_promotion_rule_logic')->where('rank_id', $rankId)->value('combine_logic') ?? 'AND')
            : 'AND';

        $hasTopN = $rules->contains('criteria_type', 'TOP_N_BY_METRIC');

        return view('masterfile.rank-promotion-rules', [
            'role'          => $role,
            'roleLabel'     => RoleLabelService::label($role, $groupId),
            'groupId'       => $groupId,
            'groups'        => $groups,
            'ranks'         => $ranks,
            'rankId'        => $rankId,
            'rules'         => $rules,
            'combineLogic'  => $combineLogic,
            'hasTopN'       => $hasTopN,
        ]);
    }

    public function updateLogic(Request $request)
    {
        $request->validate([
            'rank_id'       => ['required', 'exists:role_ranks,rank_id'],
            'combine_logic' => ['required', 'in:AND,OR'],
        ]);

        $rankId = $request->input('rank_id');
        $combineLogic = $request->input('combine_logic');

        $existing = DB::table('rank_promotion_rule_logic')->where('rank_id', $rankId)->first();

        if ($existing) {
            DB::table('rank_promotion_rule_logic')->where('logic_id', $existing->logic_id)->update([
                'combine_logic' => $combineLogic,
                'updated_at'    => now(),
            ]);
        } else {
            DB::table('rank_promotion_rule_logic')->insert([
                'logic_id'      => (string) Str::uuid(),
                'rank_id'       => $rankId,
                'combine_logic' => $combineLogic,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Rule logic updated.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'rank_id'       => ['required', 'exists:role_ranks,rank_id'],
            'criteria_type' => ['required', 'in:' . implode(',', self::CRITERIA)],
            'threshold'     => ['required', 'numeric', 'min:0'],
        ]);

        $rankId = $request->input('rank_id');
        $criteria = $request->input('criteria_type');

        // Only one TOP_N_BY_METRIC rule per rank makes sense — it defines
        // the whole population comparison, not a threshold to combine
        // with others.
        if ($criteria === 'TOP_N_BY_METRIC') {
            $already = DB::table('rank_promotion_rules')->where('rank_id', $rankId)->where('criteria_type', 'TOP_N_BY_METRIC')->exists();
            if ($already) {
                return redirect()->back()->withErrors(['This rank already has a Top-N rule — remove it first if you want to change it.']);
            }
        }

        $row = [
            'rule_id'             => (string) Str::uuid(),
            'rank_id'             => $rankId,
            'criteria_type'       => $criteria,
            'threshold_value'     => (float) $request->input('threshold'),
            'sales_metric'        => null,
            'sales_period_months' => null,
            'is_active'           => true,
            'created_by'          => Auth::guard('agent')->id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ];

        if (in_array($criteria, ['SALES_VOLUME', 'TOP_N_BY_METRIC'])) {
            $request->validate([
                'sales_metric' => ['required', 'in:PREMIUM,EARNING_INCOME'],
                'sales_period' => ['required', 'integer', 'min:1', 'max:60'],
            ]);
            $row['sales_metric']        = $request->input('sales_metric');
            $row['sales_period_months'] = (int) $request->input('sales_period');
        }

        DB::table('rank_promotion_rules')->insert($row);

        AuditService::logChange('rank_promotion_rules', $row['rule_id'], 'RANK_PROMOTION_RULE_ADDED', null, $row);

        return redirect()->back()->with('success', 'Rule added.');
    }

    public function destroy(Request $request, string $id)
    {
        $rule = DB::table('rank_promotion_rules')->where('rule_id', $id)->first();
        abort_if(!$rule, 404);

        DB::table('rank_promotion_rules')->where('rule_id', $id)->delete();

        AuditService::logChange('rank_promotion_rules', $id, 'RANK_PROMOTION_RULE_REMOVED', $rule, null);

        return redirect()->back()->with('success', 'Rule removed.');
    }
}
