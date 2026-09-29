<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Controller;
use App\Services\CbeAccountingService;
use App\Services\CbeFeatureGateService;
use Illuminate\Support\Facades\DB;

// NEW 25 Aug 2026 — per Chris's 3-role executive dashboard spec
// (Director/President, Finance/Treasurer, Membership/Sales), each
// gated Free vs Subscription. One entry point that looks up which
// officer role the logged-in agent holds (see cbe_node_officers —
// separate from GeneralLink's 3 platform Admin accounts, which oversee
// the whole platform, not one CBE organization) and renders that
// role's dashboard, scoped to their node + everything beneath it.
class CbeExecDashboardController extends Controller
{
    public function index()
    {
        $agent = auth('agent')->user();

        $officer = DB::table('cbe_node_officers as o')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'o.node_id')
            ->leftJoin('group_labels as g', 'g.group_label_id', '=', 'o.group_label_id')
            ->where('o.agent_id', $agent->agent_id)
            ->where('o.is_active', true)
            ->select('o.*', 'n.node_name', 'n.node_name_zh', 'n.hierarchy_path', 'g.group_name', 'g.subscription_tier')
            ->first();

        if (! $officer) {
            return view('cbe.exec-dashboard.not-an-officer');
        }

        $isPaid = $officer->subscription_tier === 'PAID';
        $nodeIds = CbeAccountingService::descendantNodeIds($officer->node_id);
        $from = now()->startOfYear()->toDateString();
        $to = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // Shared building blocks every role's view can draw from.
        $childBranchCount = DB::table('cbe_hierarchy_nodes')->where('parent_node_id', $officer->node_id)->count();
        $totalMembers = DB::table('cbe_group_memberships')->whereIn('cbe_node_id', $nodeIds)->where('status', 'ACTIVE')->count();
        $newMembersThisMonth = DB::table('cbe_group_memberships')->whereIn('cbe_node_id', $nodeIds)
            ->where('status', 'ACTIVE')->where('joined_at', '>=', $monthStart)->count();
        $inactiveMembers = DB::table('cbe_group_memberships')->whereIn('cbe_node_id', $nodeIds)->where('status', 'INACTIVE')->count();
        $branchesReporting = DB::table('cbe_group_memberships')->whereIn('cbe_node_id', $nodeIds)
            ->where('status', 'ACTIVE')->distinct('cbe_node_id')->count('cbe_node_id');
        $upcomingEvents = DB::table('cbe_events')->whereIn('cbe_node_id', $nodeIds)
            ->where('status', '!=', 'CLOSED')->where('event_start_date', '>=', $to)->count();
        $completedEvents = DB::table('cbe_events')->whereIn('cbe_node_id', $nodeIds)->where('status', 'CLOSED')->count();
        $meetingsThisYear = DB::table('cbe_meeting_minutes')->whereIn('cbe_node_id', $nodeIds)
            ->whereYear('meeting_date', now()->year)->count();
        $financial = CbeAccountingService::financialSummary($officer->node_id, $from, $to);
        $financialMonth = CbeAccountingService::financialSummary($officer->node_id, $monthStart, $to);

        $apBills = DB::table('cbe_purchase_bills')->whereIn('cbe_node_id', $nodeIds)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])->get();
        $billsDueSoon = $apBills->filter(fn ($b) => $b->due_date && \Carbon\Carbon::parse($b->due_date)->lte(now()->addDays(7)))->count();
        $billsOverdue = $apBills->filter(fn ($b) => $b->due_date && \Carbon\Carbon::parse($b->due_date)->lt(now()))->count();

        // "Accounts Receivable" here means outstanding event donation/
        // sponsorship pledges — deliberately NOT part of the formal
        // journal (see CbeAccountingService header) so it's computed
        // separately, straight from cbe_contributions.
        $arOutstanding = (float) DB::table('cbe_contributions as c')
            ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
            ->whereIn('e.cbe_node_id', $nodeIds)
            ->where('c.status', '!=', 'CANCELLED')
            ->selectRaw('SUM(pledged_amount - received_amount) as v')->value('v') ?: 0;

        $shared = [
            'officer' => $officer,
            'isPaid' => $isPaid,
            'tierLabel' => $isPaid ? 'PAID' : 'FREE',
            'nodeCount' => count($nodeIds),
            'childBranchCount' => $childBranchCount,
            'totalMembers' => $totalMembers,
            'newMembersThisMonth' => $newMembersThisMonth,
            'inactiveMembers' => $inactiveMembers,
            'branchesReporting' => $branchesReporting,
            'upcomingEvents' => $upcomingEvents,
            'completedEvents' => $completedEvents,
            'meetingsThisYear' => $meetingsThisYear,
            'financial' => $financial,
            'financialMonth' => $financialMonth,
            'billsDueSoon' => $billsDueSoon,
            'billsOverdue' => $billsOverdue,
            'apBillCount' => $apBills->count(),
            'arOutstanding' => $arOutstanding,
        ];

        return match ($officer->role) {
            'DIRECTOR' => view('cbe.exec-dashboard.director', $shared),
            'FINANCE' => view('cbe.exec-dashboard.finance', $shared),
            'MEMBERSHIP' => view('cbe.exec-dashboard.membership', $shared),
            default => view('cbe.exec-dashboard.not-an-officer'),
        };
    }

    // NEW 18 Sep 2026 — per Chris: "ALL" KPI (Vendor, Customer,
    // Communication) reached via Next from this officer's own
    // Executive KPI Dashboard — same page-2 idea, same shared service,
    // as the platform Admin's own Executive KPI Dashboard.
    // SPLIT 18 Sep 2026 — per Chris: "it should have Overall Executive
    // KPI, Customer KPI, Vendor Market Place KPI, Communication KPI"
    // as 4 SEPARATE sidebar links, not one combined "More KPI" page.
    private function extraKpiOfficerScope(): ?array
    {
        $agent = auth('agent')->user();
        $officer = DB::table('cbe_node_officers')->where('agent_id', $agent->agent_id)->where('is_active', true)->first();
        if (! $officer) {
            return null;
        }

        return [
            'nodeIds' => CbeAccountingService::descendantNodeIds($officer->node_id),
            'monthStart' => now()->startOfMonth()->toDateString(),
            'asOfDate' => now()->toDateString(),
        ];
    }

    public function communicationKpi()
    {
        $s = $this->extraKpiOfficerScope();
        if (! $s) {
            return redirect()->route('cbe.exec-dashboard');
        }

        return view('cbe.exec-dashboard.communication-kpi', array_merge([
            'communicationKpi' => \App\Services\CbeExtraKpiService::communication($s['nodeIds'], $s['monthStart'], $s['asOfDate']),
        ], \App\Http\Controllers\Admin\GladeAnalyticsController::computeNoticeBoardMetrics()));
    }

    public function vendorMarketplaceKpi()
    {
        $s = $this->extraKpiOfficerScope();
        if (! $s) {
            return redirect()->route('cbe.exec-dashboard');
        }

        return view('cbe.exec-dashboard.vendor-marketplace-kpi', [
            'vendorMarketplaceKpi' => \App\Services\CbeExtraKpiService::vendorMarketplace($s['nodeIds'], $s['monthStart'], $s['asOfDate']),
        ]);
    }

    public function customerKpi()
    {
        $s = $this->extraKpiOfficerScope();
        if (! $s) {
            return redirect()->route('cbe.exec-dashboard');
        }

        return view('cbe.exec-dashboard.customer-kpi', [
            'customerKpi' => \App\Services\CbeExtraKpiService::customer($s['nodeIds'], $s['monthStart']),
        ]);
    }
}
