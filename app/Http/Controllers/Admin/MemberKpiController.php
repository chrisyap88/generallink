<?php

// NEW 28 Sep 2026 — per Chris (member file item 35): Member KPI dashboard,
// under Executive KPI Dashboard. Admin: All CBE Groups or one CBE Group;
// a CBE officer: his own entities only. Every figure comes from the member
// file (Affiliation Register, plans, payments, committee) — nothing hardcoded.

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MemberFileService as MF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemberKpiController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = Auth::guard('agent')->user()?->role === 'ADMIN';
        $groups = DB::table('group_labels')->where('group_type', 'CBE')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $group = null;
        if ($isAdmin) {
            $group = $groups->firstWhere('group_label_id', $request->get('group'));
            $nodeIds = $group ? DB::table('cbe_hierarchy_nodes')->where('group_label_id', $group->group_label_id)->pluck('node_id')->all()
                : DB::table('cbe_hierarchy_nodes')->pluck('node_id')->all();
        } else {
            $mine = DB::table('cbe_node_officers')->where('agent_id', Auth::guard('agent')->id())->where('is_active', true)->pluck('node_id')->all();
            abort_if(! $mine, 403);
            $nodeIds = DB::table('cbe_hierarchy_nodes')->where(function ($w) use ($mine) {
                $w->whereIn('node_id', $mine);
                foreach ($mine as $m) {
                    $w->orWhere('hierarchy_path', 'like', '%/'.$m.'/%');
                }
            })->pluck('node_id')->all();
            $group = $groups->firstWhere('group_label_id', DB::table('cbe_hierarchy_nodes')->where('node_id', $mine[0])->value('group_label_id'));
            $groups = collect($group ? [$group] : []);
        }
        $nodeIds = $nodeIds ?: ['#'];
        $monthStart = now()->startOfMonth()->toDateString();
        $lines = fn () => DB::table('cbe_member_role_tags as t')->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')
            ->whereIn('m.cbe_node_id', $nodeIds);
        $active = fn () => $lines()->where('t.status', 'ACTIVE');

        $k = [];
        $k['people'] = $active()->distinct()->count('m.agent_id');
        $k['entities'] = $active()->distinct()->count('m.cbe_node_id');
        $k['new_month'] = $active()->where('t.from_date', '>=', $monthStart)->distinct()->count('m.agent_id');
        $k['ended_month'] = $lines()->where('t.status', 'ENDED')->where('t.to_date', '>=', $monthStart)->distinct()->count('m.agent_id');
        $k['birthdays'] = DB::table('agents')->whereIn('agent_id', $active()->select('m.agent_id'))->whereMonth('date_of_birth', now()->month)->count();
        // by type
        $byType = $active()->select('t.tag', DB::raw('COUNT(DISTINCT m.agent_id) as n'))->groupBy('t.tag')->pluck('n', 't.tag')->all();
        $byType['PRACTITIONER'] = DB::table('cbe_practitioner_profiles')->whereIn('cbe_node_id', $nodeIds)->where('is_active', true)->distinct()->count('agent_id');
        $groupIds = DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $nodeIds)->distinct()->pluck('group_label_id')->all();
        $byType['COMMITTEE'] = DB::table('group_committee_members')->whereIn('group_label_id', $groupIds ?: ['#'])->whereNull('ended_at')
            ->where(fn ($w) => $w->whereNull('term_end_date')->orWhere('term_end_date', '>=', now()->toDateString()))->distinct()->count('agent_id');
        if ((DB::getSchemaBuilder()->hasColumn('cbe_donors', 'agent_id'))) {
            $byType['DONOR'] = max($byType['DONOR'] ?? 0, DB::table('cbe_donors')->whereIn('cbe_node_id', $nodeIds)->count());
        }
        $k['by_type'] = $byType;
        // fee status of Member lines with a plan
        $fee = ['PAID' => 0, 'DUE' => 0, 'OVERDUE' => 0, 'FREE' => 0, 'WAIVED' => 0, 'FAMILY' => 0];
        foreach ($active()->where('t.tag', 'MEMBER')->whereNotNull('t.plan_id')->leftJoin('cbe_membership_plans as p', 'p.id', '=', 't.plan_id')
            ->get(['t.tag', 't.plan_id', 't.paid_until', 't.fee_waived', 't.source', 'p.fee', 'p.period']) as $t) {
            $st = MF::feeStatus($t);
            if ($st) {
                $fee[$st] = ($fee[$st] ?? 0) + 1;
            }
        }
        $k['fee'] = $fee;
        $k['no_plan'] = $active()->where('t.tag', 'MEMBER')->whereNull('t.plan_id')->count();
        // membership type
        $k['mem_type'] = $active()->where('t.tag', 'MEMBER')->join('cbe_membership_plans as p', 'p.id', '=', 't.plan_id')
            ->join('member_profile_options as o', 'o.id', '=', 'p.membership_type_id')
            ->select('o.id', 'o.label', DB::raw('COUNT(*) as n'))->groupBy('o.id', 'o.label')->orderByDesc('n')->get();
        // how they joined
        $k['how'] = $active()->select(DB::raw("COALESCE(t.source,'STAFF') as src"), DB::raw('COUNT(*) as n'))->groupBy('src')->pluck('n', 'src')->all();
        // fees collected this month
        $k['collected_month'] = (float) DB::table('cbe_membership_payments as pay')->join('cbe_member_role_tags as t', 't.tag_id', '=', 'pay.tag_id')
            ->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')->whereIn('m.cbe_node_id', $nodeIds)
            ->where('pay.paid_on', '>=', $monthStart)->sum('pay.amount');
        // top entities
        $k['top'] = $active()->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'm.cbe_node_id')
            ->select('n.node_id', 'n.node_name', DB::raw('COUNT(DISTINCT m.agent_id) as n'))->groupBy('n.node_id', 'n.node_name')->orderByDesc('n')->limit(5)->get();

        return view('admin.member-kpi.index', ['k' => $k, 'groups' => $groups, 'group' => $group, 'isAdmin' => $isAdmin, 'typeLabels' => MF::typeLabels()]);
    }
}
