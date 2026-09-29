<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\BeneficiaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgentManagementController extends Controller
{
    public function __construct(private BeneficiaryService $beneficiaryService) {}

    public function index(Request $request)
    {
        $query = DB::table('agents as a')
            ->leftJoin('agents as p', 'p.agent_id', '=', 'a.parent_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 'a.group_id')
            ->where('a.is_deleted', false)
            ->where('a.role', '!=', 'ADMIN')
            ->select('a.*', 'p.full_name as parent_name', 'g.group_code', 'g.group_name');

        if ($search = $request->input('search')) {
            $query->where(fn($q) => $q
                ->where('a.full_name','like',"%{$search}%")
                ->orWhere('a.email','like',"%{$search}%")
                ->orWhere('a.member_code','like',"%{$search}%")
                ->orWhere('a.agent_code','like',"%{$search}%")
            );
        }
        if ($role = $request->input('role'))     $query->where('a.role', $role);
        if ($status = $request->input('status')) $query->where('a.status', $status);
        if ($group = $request->input('group'))   $query->where('g.group_code', $group);

        $agents = $query->orderByDesc('a.created_at')->paginate(25);
        $groups = DB::table('groups')->orderBy('group_code')->get();

        return view('agents.index', compact('agents', 'groups'));
    }

    public function show(string $agentId)
    {
        $agent = DB::table('agents as a')
            ->leftJoin('agents as p', 'p.agent_id', '=', 'a.parent_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 'a.group_id')
            ->where('a.agent_id', $agentId)
            ->select('a.*', 'p.full_name as parent_name', 'p.member_code as parent_code', 'g.group_code', 'g.group_name')
            ->firstOrFail();

        $nricMasked = '';
        try { $nricMasked = '****' . substr(decrypt($agent->nric_encrypted), -4); } catch (\Exception $e) {}

        $downlines = DB::table('agents')->where('parent_id', $agentId)->where('is_deleted', false)->get();

        $commissionTotal = DB::table('commission_transactions')
            ->where('agent_id', $agentId)->where('status', 'CONFIRMED')->sum('commission_amount');

        $pointsBalance = DB::table('reward_points_ledger')
            ->where('agent_id', $agentId)
            ->selectRaw('COALESCE(SUM(points_in)-SUM(points_out),0) as bal')
            ->value('bal') ?? 0;

        $beneficiaries = DB::table('beneficiaries')->where('agent_id', $agentId)->where('is_active', true)->get();

        $recentCommissions = DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'st.policy_id', '=', 'ct.policy_id')
            ->where('ct.agent_id', $agentId)
            ->select('ct.*', 'st.policy_number')
            ->orderByDesc('ct.created_at')
            ->limit(5)->get();

        return view('agents.show', compact(
            'agent', 'nricMasked', 'downlines',
            'commissionTotal', 'pointsBalance',
            'beneficiaries', 'recentCommissions'
        ));
    }

    public function updateStatus(Request $request, string $agentId)
    {
        $request->validate([
            'status' => ['required', 'in:ACTIVE,INACTIVE,TERMINATED,RISK_DEBT,RESIGNED,DECEASED'],
            'notes'  => ['nullable', 'string', 'max:300'],
        ]);

        $agent  = DB::table('agents')->where('agent_id', $agentId)->first();
        $admin  = Auth::guard('agent')->user();
        $before = ['status' => $agent->status];

        DB::table('agents')->where('agent_id', $agentId)->update([
            'status'     => $request->status,
            'updated_by' => $admin->agent_id,
            'updated_at' => now(),
        ]);

        AuditService::logChange('agents', $agentId, 'STATUS_CHANGE', $before, ['status' => $request->status, 'notes' => $request->notes]);

        // Trigger beneficiary takeover if resigned or deceased
        if (in_array($request->status, ['RESIGNED', 'DECEASED'])) {
            $agentModel = \App\Models\Agent::find($agentId);
            if ($agentModel) {
                $this->beneficiaryService->triggerTakeover($agentModel, $admin->agent_id, $request->notes ?? '');
            }
        }

        return back()->with('success', "Agent status updated to {$request->status}." .
            (in_array($request->status, ['RESIGNED','DECEASED']) ? ' Beneficiary takeover initiated.' : ''));
    }
}
