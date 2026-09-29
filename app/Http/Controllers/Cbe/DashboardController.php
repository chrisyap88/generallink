<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Controller;
use App\Services\NoticeRelevanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 17 Aug 2026 — per Chris: CBE (Community & Business Enterprise
// Group) gets its own post-login "Ecosystem Home" dashboard, separate
// from the existing Admin/GL/TL/Introducer sidebar layout. Login pages
// are NOT touched — this only governs what a CBE member sees AFTER
// they've logged in, per group_labels.group_type = 'CBE'.
//
// Phase 1 scope (per Chris, "I will go through individual after login
// and discuss with you what are the programs involve"): this screen
// shows the real, existing menu programs (same list GL/TL/Introducer
// already have — Dashboard, Network Tree, Customer Relationship,
// Business, Communication, Master File Maintenance, Growth & Outreach
// Center, My Account) so nothing invented is on it, but most items are
// placeholders until each one is individually wired for CBE in a
// follow-up round — CBE's own hierarchy (configurable levels, not the
// fixed GROUP_LEADER/TEAM_LEADER/INTRODUCER roles) and its "no
// commission, no promotion/demotion" rule mean several of those
// programs need real rework before they're safe to turn on here, not
// just a new route.
//
// Notice Board and Reminders ARE already wired for real, live data,
// since both are generic per-agent features today (not role-branched),
// so they work correctly for a CBE member with no extra work.
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        return $this->render($agent, false);
    }

    // Admin-only preview — lets Chris review this screen's design before
    // any real CBE member exists to log in and see it themselves (CBE
    // registration is a later phase). Uses sample data, clearly labeled.
    public function preview(Request $request)
    {
        return $this->render(null, true);
    }

    private function render($agent, bool $isPreview)
    {
        $groupLabel = null;
        $hierarchyLevels = collect();
        $myLevelName = null;

        if ($agent && $agent->group_label_id) {
            $groupLabel = DB::table('group_labels')->where('group_label_id', $agent->group_label_id)->first();
            $hierarchyLevels = DB::table('cbe_hierarchy_levels')
                ->where('group_label_id', $agent->group_label_id)
                ->orderBy('level_order')
                ->get();
        }

        if ($isPreview) {
            $groupLabel = $groupLabel ?? (object) ['group_name' => 'Sample CBE Community (Preview)'];
            $hierarchyLevels = $hierarchyLevels->isNotEmpty() ? $hierarchyLevels : collect([
                (object) ['level_order' => 1, 'level_name' => 'HQ'],
                (object) ['level_order' => 2, 'level_name' => 'State'],
                (object) ['level_order' => 3, 'level_name' => 'Branch'],
                (object) ['level_order' => 4, 'level_name' => 'Sub-section'],
                (object) ['level_order' => 5, 'level_name' => 'Entity'],
            ]);
            $myLevelName = $hierarchyLevels->first()->level_name ?? null;
        }

        // Notice Board — real data, same relevance scoring already used
        // for every other role (NoticeRelevanceService), just scoped to
        // whatever agent is viewing. Falls back to the 3 most recent
        // notices in preview mode (no logged-in agent to score against).
        if ($agent) {
            [$relevanceSql, $bindings] = NoticeRelevanceService::sqlExpression(null);
            $notices = DB::table('notices as n')
                ->where('n.is_deleted', false)
                ->where(function ($q) {
                    $q->whereNull('n.expires_at')->orWhereDate('n.expires_at', '>=', now()->toDateString());
                })
                ->orderByRaw($relevanceSql . ' DESC', $bindings)
                ->orderByDesc('n.created_at')
                ->limit(3)
                ->get();

            $reminders = DB::table('personal_reminders')
                ->where('agent_id', $agent->agent_id)
                ->where('is_deleted', false)
                ->where('status', 'PENDING')
                ->orderBy('reminder_date')
                ->limit(3)
                ->get();
        } else {
            $notices = DB::table('notices')
                ->where('is_deleted', false)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
                })
                ->orderByDesc('created_at')->limit(3)->get();
            $reminders = collect();
        }

        return view('cbe.ecosystem-home', [
            'agent'           => $agent,
            'isPreview'       => $isPreview,
            'groupLabel'      => $groupLabel,
            'hierarchyLevels' => $hierarchyLevels,
            'myLevelName'     => $myLevelName,
            'notices'         => $notices,
            'reminders'       => $reminders,
        ]);
    }
}
