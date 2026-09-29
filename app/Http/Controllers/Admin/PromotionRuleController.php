<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// REWORKED 24 Jul 2026 — Promotion & Demotion Rules, per Chris's
// follow-up requests:
//   - Scoped per Direct Selling Group (group_labels.group_label_id —
//     the same identity that already gates promotion_demotion_enabled),
//     not one shared set. "prihatin2u is one... rela2u is another type."
//     (NOTE 18 Aug 2026 — that comment predates the DSG/ORG naming split;
//     "prihatin2u"/"rela2u" here are DSG groups, not Organization Rewards
//     Groups — ORG never has promotion/demotion at all, see below.)
//   - No longer capped at 3 fixed criteria — an unlimited, add/remove
//     LIST of rules per (group, transition), each picking one metric
//     (Recruit Count / Sales Volume / Tenure — more can be added later
//     without changing this screen).
// A group with no rules of its own for a transition automatically
// inherits the System Default set (group_label_id = null) — including
// any brand new group Admin creates going forward.
class PromotionRuleController extends Controller
{
    private const TRANSITIONS = [
        'TEAM_LEADER'  => 'INTRODUCER',  // Introducer -> Team Leader
        'GROUP_LEADER' => 'TEAM_LEADER', // Team Leader -> Group Leader
    ];
    private const CRITERIA = ['RECRUIT_COUNT', 'SALES_VOLUME', 'TENURE_MONTHS'];

    public function index(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : null;

        // Only groups that actually USE promotion/demotion at all — a
        // group like PVATM (promotion_demotion_enabled = false) never
        // auto-promotes/demotes anyone (see HierarchyService::
        // evaluateRankChange(), which bails out immediately for such
        // groups), so configuring rules for it would do nothing. Per
        // Chris: "you should not show PVATM because PVATM flagging is
        // No promotion and demotion rules".
        // FIXED 18 Aug 2026 — switched to the explicit group_type
        // column (added 17 Aug 2026) instead of the legacy
        // promotion_demotion_enabled flag, so this stays correct even
        // if a future group type other than DSG/ORG also happens to
        // share that flag's value.
        $groupLabels = DB::table('group_labels')->where('group_type', 'DSG')->orderBy('group_name')->get(['group_label_id', 'group_name']);

        // Guard against a stale/typed-in URL still pointing at a group
        // that's disabled (or was disabled after this link was bookmarked)
        // — fall back to System Default rather than silently configuring
        // rules nobody will ever see evaluated.
        if ($groupLabelId !== null && !$groupLabels->contains('group_label_id', $groupLabelId)) {
            return redirect()->route('admin.masterfile.promotion-rules');
        }

        $rules = DB::table('promotion_demotion_rules')
            ->where('group_label_id', $groupLabelId)
            ->orderBy('created_at')
            ->get();

        // So Admin can tell at a glance whether this group is actually
        // using its own rules or silently inheriting System Default.
        $usingDefaultFor = [];
        $rulesByTransition = [];
        $effectiveScopeFor = [];
        foreach (self::TRANSITIONS as $toRole => $fromRole) {
            $ownRules = $rules->where('to_role', $toRole)->values();
            if ($ownRules->isNotEmpty() || $groupLabelId === null) {
                $rulesByTransition[$toRole] = $ownRules;
                $usingDefaultFor[$toRole] = false;
                $effectiveScopeFor[$toRole] = $groupLabelId;
            } else {
                $rulesByTransition[$toRole] = DB::table('promotion_demotion_rules')
                    ->where('to_role', $toRole)
                    ->whereNull('group_label_id')
                    ->orderBy('created_at')
                    ->get();
                $usingDefaultFor[$toRole] = true;
                $effectiveScopeFor[$toRole] = null;
            }
        }

        // AND/OR only matters once 2+ rules apply to a transition — per
        // Chris: "if i chose one matrix is one matrix if i click 2
        // matric you should ask me and / or".
        $combineLogicFor = [];
        foreach (self::TRANSITIONS as $toRole => $fromRole) {
            $combineLogicFor[$toRole] = DB::table('promotion_rule_logic')
                ->where('group_label_id', $effectiveScopeFor[$toRole])
                ->where('to_role', $toRole)
                ->value('combine_logic') ?? 'AND';
        }

        return view('masterfile.promotion-rules', [
            'groupLabelId'      => $groupLabelId,
            'groupLabels'       => $groupLabels,
            'rulesByTransition' => $rulesByTransition,
            'usingDefaultFor'   => $usingDefaultFor,
            'combineLogicFor'   => $combineLogicFor,
            'transitions'       => self::TRANSITIONS,
        ]);
    }

    // Only reachable from the UI when 2+ rules exist for this
    // transition — see promotion-rules.blade.php.
    public function updateLogic(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;

        $request->validate([
            'group_label_id' => ['nullable', 'exists:group_labels,group_label_id'],
            'to_role'        => ['required', 'in:TEAM_LEADER,GROUP_LEADER'],
            'combine_logic'  => ['required', 'in:AND,OR'],
        ]);

        $toRole = $request->input('to_role');
        $combineLogic = $request->input('combine_logic');

        $existing = DB::table('promotion_rule_logic')
            ->where('group_label_id', $groupLabelId)
            ->where('to_role', $toRole)
            ->first();

        if ($existing) {
            DB::table('promotion_rule_logic')->where('logic_id', $existing->logic_id)->update([
                'combine_logic' => $combineLogic,
                'updated_at'    => now(),
            ]);
        } else {
            DB::table('promotion_rule_logic')->insert([
                'logic_id'       => (string) Str::uuid(),
                'group_label_id' => $groupLabelId,
                'to_role'        => $toRole,
                'combine_logic'  => $combineLogic,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return redirect()->route('admin.masterfile.promotion-rules', $groupLabelId ? ['group_label_id' => $groupLabelId] : [])
            ->with('success', 'Rule logic updated.');
    }

    public function store(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;

        $request->validate([
            'group_label_id' => ['nullable', 'exists:group_labels,group_label_id'],
            'to_role'        => ['required', 'in:TEAM_LEADER,GROUP_LEADER'],
            'criteria_type'  => ['required', 'in:' . implode(',', self::CRITERIA)],
            'threshold'      => ['required', 'numeric', 'min:0'],
        ]);

        $toRole = $request->input('to_role');
        $fromRole = self::TRANSITIONS[$toRole];
        $criteria = $request->input('criteria_type');

        $row = [
            'rule_id'         => (string) Str::uuid(),
            'group_label_id'  => $groupLabelId,
            'from_role'       => $fromRole,
            'to_role'         => $toRole,
            'criteria_type'   => $criteria,
            'threshold_value' => (float) $request->input('threshold'),
            'sales_metric'        => null,
            'sales_period_months' => null,
            'is_active'       => true,
            'created_by'      => Auth::guard('agent')->id(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        if ($criteria === 'SALES_VOLUME') {
            $request->validate([
                'sales_metric' => ['required', 'in:PREMIUM,EARNING_INCOME'],
                'sales_period' => ['required', 'integer', 'min:1', 'max:60'],
            ]);
            $row['sales_metric']        = $request->input('sales_metric');
            $row['sales_period_months'] = (int) $request->input('sales_period');
        }

        DB::table('promotion_demotion_rules')->insert($row);

        AuditService::logChange('promotion_demotion_rules', $row['rule_id'], 'PROMOTION_RULE_ADDED', null, $row);

        return redirect()->route('admin.masterfile.promotion-rules', $groupLabelId ? ['group_label_id' => $groupLabelId] : [])
            ->with('success', 'Rule added.');
    }

    public function destroy(Request $request, string $id)
    {
        $rule = DB::table('promotion_demotion_rules')->where('rule_id', $id)->first();
        abort_if(!$rule, 404);

        DB::table('promotion_demotion_rules')->where('rule_id', $id)->delete();

        AuditService::logChange('promotion_demotion_rules', $id, 'PROMOTION_RULE_REMOVED', $rule, null);

        return redirect()->route('admin.masterfile.promotion-rules', $rule->group_label_id ? ['group_label_id' => $rule->group_label_id] : [])
            ->with('success', 'Rule removed.');
    }
}
