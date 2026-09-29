<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Breakaway Bonus rule config. One target per group
// (Chris's example: Amy Tan's whole group must hit RM Y in sales/
// earning income before Chris Yap earns X% of that amount as a
// breakaway bonus). Scoped per Direct Selling Group (group_labels.
// group_label_id), same NULL = System Default pattern as Promotion &
// Demotion Rules — a group with no rule of its own inherits System
// Default automatically. This screen only edits/saves the ONE rule per
// group — see PromotionRuleController for the multi-criteria list
// version (different shape, since Breakaway only ever has one target).
// (NOTE 18 Aug 2026 — "Organization Rewards Group" above predates the
// DSG/ORG naming split; this is DSG-scoped, ORG never has breakaway
// bonuses since it never has promotion/demotion at all.)
class BreakawayBonusRuleController extends Controller
{
    public function index(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : null;

        // Same fix as Promotion & Demotion Rules — a group flagged
        // promotion_demotion_enabled = false (e.g. PVATM) never
        // auto-promotes anyone to Group Leader in the first place, so
        // there's no breakaway to configure a bonus for. Per Chris:
        // "you should not show PVATM because PVATM flagging is No
        // promotion and demotion rules".
        // FIXED 18 Aug 2026 — switched to the explicit group_type
        // column instead of the legacy promotion_demotion_enabled flag.
        $groupLabels = DB::table('group_labels')->where('group_type', 'DSG')->orderBy('group_name')->get(['group_label_id', 'group_name']);

        if ($groupLabelId !== null && !$groupLabels->contains('group_label_id', $groupLabelId)) {
            return redirect()->route('admin.masterfile.breakaway-bonus-rules');
        }

        $ownRule = DB::table('breakaway_bonus_rules')->where('group_label_id', $groupLabelId)->first();

        $usingDefault = false;
        $rule = $ownRule;
        if (!$rule && $groupLabelId !== null) {
            $rule = DB::table('breakaway_bonus_rules')->whereNull('group_label_id')->first();
            $usingDefault = true;
        }

        return view('masterfile.breakaway-bonus-rules', [
            'groupLabelId' => $groupLabelId,
            'groupLabels'  => $groupLabels,
            'rule'         => $rule,
            'usingDefault' => $usingDefault,
        ]);
    }

    public function update(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;

        $request->validate([
            'group_label_id'  => ['nullable', 'exists:group_labels,group_label_id'],
            'target_metric'   => ['required', 'in:PREMIUM,EARNING_INCOME'],
            'target_amount'   => ['required', 'numeric', 'min:0'],
            'bonus_pct'       => ['required', 'numeric', 'min:0', 'max:100'],
            'period_months'   => ['required', 'integer', 'min:1', 'max:60'],
            'is_active'       => ['required', 'in:0,1'],
        ]);

        $existing = DB::table('breakaway_bonus_rules')->where('group_label_id', $groupLabelId)->first();

        $values = [
            'target_metric'  => $request->input('target_metric'),
            'target_amount'  => (float) $request->input('target_amount'),
            'bonus_pct'      => (float) $request->input('bonus_pct'),
            'period_months'  => (int) $request->input('period_months'),
            'is_active'      => $request->input('is_active'),
            'updated_at'     => now(),
        ];

        if ($existing) {
            DB::table('breakaway_bonus_rules')->where('rule_id', $existing->rule_id)->update($values);
            AuditService::logChange('breakaway_bonus_rules', $existing->rule_id, 'BREAKAWAY_RULE_UPDATED', $existing, $values);
        } else {
            $ruleId = (string) Str::uuid();
            DB::table('breakaway_bonus_rules')->insert(array_merge($values, [
                'rule_id'        => $ruleId,
                'group_label_id' => $groupLabelId,
                'created_by'     => Auth::guard('agent')->id(),
                'created_at'     => now(),
            ]));
            AuditService::logChange('breakaway_bonus_rules', $ruleId, 'BREAKAWAY_RULE_CREATED', null, $values);
        }

        return redirect()->route('admin.masterfile.breakaway-bonus-rules', $groupLabelId ? ['group_label_id' => $groupLabelId] : [])
            ->with('success', 'Breakaway Bonus rule saved.');
    }
}
