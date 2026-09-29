<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\HierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #211). Agent-facing
// view: every currently-running (or upcoming) contest that applies to
// this agent — company-wide contests (group_label_id NULL) plus any
// created specifically for their own Organization Rewards Group — with
// their own live progress toward the target.
class RecruitmentContestController extends Controller
{
    public function index(Request $request, HierarchyService $hierarchy)
    {
        $agent = Auth::guard('agent')->user();
        $today = now()->toDateString();

        $contests = DB::table('recruitment_contests as c')
            ->leftJoin('vendors as v', 'c.sponsor_vendor_id', '=', 'v.vendor_id')
            ->where('c.is_active', true)
            ->where('c.end_date', '>=', $today)
            ->where(function ($q) use ($agent) {
                $q->whereNull('c.group_label_id');
                if ($agent->group_label_id) {
                    $q->orWhere('c.group_label_id', $agent->group_label_id);
                }
            })
            ->orderBy('c.end_date')
            ->select('c.*', 'v.vendor_name as sponsor_vendor_name')
            ->get();

        foreach ($contests as $c) {
            $since = \Carbon\Carbon::parse($c->start_date)->startOfDay();
            $until = \Carbon\Carbon::parse(min($c->end_date, $today))->endOfDay();

            $c->my_progress = match ($c->metric) {
                'RECRUIT_COUNT'  => (float) Agent::where('parent_id', $agent->agent_id)
                    ->where('status', 'ACTIVE')->where('is_deleted', false)
                    ->whereBetween('created_at', [$since, $until])->count(),
                'SALES_VOLUME'   => $hierarchy->teamVolumeBetween($agent, 'PREMIUM', $since, $until),
                'EARNING_INCOME' => $hierarchy->teamVolumeBetween($agent, 'EARNING_INCOME', $since, $until),
                default          => 0.0,
            };
            $c->pct = $c->target_value > 0 ? min(100, round(($c->my_progress / $c->target_value) * 100)) : 0;
            $c->already_won = DB::table('recruitment_contest_awards')
                ->where('contest_id', $c->contest_id)->where('agent_id', $agent->agent_id)->exists();
            // Query already restricts end_date >= today, so this is
            // always a non-negative count of days remaining.
            $c->days_left = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($c->end_date)->startOfDay());
        }

        return view('growth.contests', compact('contests', 'agent'));
    }
}
