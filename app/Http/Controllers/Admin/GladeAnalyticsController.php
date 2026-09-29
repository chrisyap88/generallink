<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 4 (Task #91).
// Admin-only analytics for the whole GLADE module built across Phases
// 1-3: how many notices went out, how they were delivered (channel x
// status), how many actually got read, and how the AI Insight feature
// (Phase 3) performed. Own screen (not squeezed into the main KPI
// dashboard grid, which per Chris's no-scroll rule has no spare slot —
// same precedent as the WhatsApp Audit Log screen).
class GladeAnalyticsController extends Controller
{
    // EXTRACTED 19 Sep 2026 — per Chris: the CBE Communication KPI screen
    // (AdminCbeKpiController::communicationKpi() /
    // CbeExecDashboardController::communicationKpi()) must show this
    // exact same notice-board engagement data, on the exact same
    // glassmorphism card layout, just retitled "Communication KPI".
    // Pulled the query logic out of index() so both screens compute it
    // ONE way, never duplicated. Note: this data is platform-wide (the
    // general `notices` board — not cbe_node_id-scoped), so it reads the
    // same regardless of which CBE group/node the Communication KPI
    // screen is scoped to; per Chris, that's fine — he wants the display
    // to match, not a per-node breakdown of this particular data.
    public static function computeNoticeBoardMetrics(): array
    {
        $since30 = now()->subDays(30);

        $totalNotices = DB::table('notices')->where('is_deleted', false)->count();
        $notices30 = DB::table('notices')->where('is_deleted', false)->where('created_at', '>=', $since30)->count();

        // Delivery counts by channel x status (notice_deliveries — Phase 1/Task #85).
        $deliveryRows = DB::table('notice_deliveries')
            ->select('channel', 'status', DB::raw('count(*) as cnt'))
            ->groupBy('channel', 'status')
            ->get();

        $channels = ['EMAIL', 'WHATSAPP', 'SMS', 'TELEGRAM', 'LINE', 'WECHAT'];
        $deliveryMatrix = [];
        foreach ($channels as $ch) {
            $deliveryMatrix[$ch] = ['SENT' => 0, 'FAILED' => 0, 'SKIPPED' => 0];
        }
        foreach ($deliveryRows as $row) {
            if (isset($deliveryMatrix[$row->channel])) {
                $deliveryMatrix[$row->channel][$row->status] = (int) $row->cnt;
            }
        }

        // Read rate — total notice_reads vs (active agents x notices), a
        // simple all-time proxy for "how much of what's posted actually
        // gets opened."
        $totalReads = DB::table('notice_reads')->count();
        $activeAgents = DB::table('agents')->where('status', 'ACTIVE')->where('is_deleted', false)->count();
        $possibleReads = max(1, $activeAgents * max(1, $totalNotices));
        $readRate = round(min(100, ($totalReads / $possibleReads) * 100), 1);

        // AI Insight (Phase 3 / Task #90) — how many Promotion notices got
        // a real blurb vs how many the AI judged not worth surfacing.
        $aiTotal = DB::table('notice_ai_matches')->count();
        $aiWithBlurb = DB::table('notice_ai_matches')->whereNotNull('blurb')->count();
        $aiSkipped = $aiTotal - $aiWithBlurb;

        // Top 5 most-read notices (all-time).
        $topNotices = DB::table('notices as n')
            ->leftJoin('notice_reads as r', 'r.notice_id', '=', 'n.notice_id')
            ->where('n.is_deleted', false)
            ->select('n.notice_id', 'n.title', 'n.category', DB::raw('count(r.read_id) as read_count'))
            ->groupBy('n.notice_id', 'n.title', 'n.category')
            ->orderByDesc('read_count')
            ->limit(5)
            ->get();

        return compact(
            'totalNotices', 'notices30', 'deliveryMatrix', 'totalReads', 'readRate',
            'aiTotal', 'aiWithBlurb', 'aiSkipped', 'topNotices'
        );
    }

    public function index()
    {
        return view('admin.glade-analytics.index', self::computeNoticeBoardMetrics());
    }
}
