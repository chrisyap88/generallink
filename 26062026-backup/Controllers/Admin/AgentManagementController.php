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
        // No search submitted yet — show empty tree
        if (!$request->filled('f_role')) {
            return view('agents.index', ['agents' => null, 'groups' => collect()]);
        }

        $role   = $request->input('f_role');
        $mode   = $request->input('f_mode', 'all');
        $ids    = $request->input('f_ids', '');
        $status = $request->input('f_status');
        $from   = $request->input('f_from');
        $to     = $request->input('f_to');

        $query = DB::table('agents as a')
            ->leftJoin('agents as p', 'p.agent_id', '=', 'a.parent_id')
            ->leftJoin('groups as g', 'g.group_id', '=', 'a.group_id')
            ->where('a.is_deleted', false)
            ->where('a.role', $role)
            ->select('a.*', 'p.full_name as parent_name', 'g.group_code', 'g.group_name');

        // Status filter
        if ($status) $query->where('a.status', $status);

        // Date range filter
        if ($from) $query->whereDate('a.created_at', '>=', $from);
        if ($to)   $query->whereDate('a.created_at', '<=', $to);

        // Mode filter
        $idList = $ids ? explode(',', $ids) : [];

        if ($mode === 'all') {
            // Show root level only for GL, all for others
            if ($role === 'GROUP_LEADER') {
                $query->whereNull('a.parent_id');
            }
        } elseif ($mode === 'group' && !empty($idList)) {
            // Filter by selected group IDs
            $query->whereIn('a.group_id', $idList);
            if ($role === 'GROUP_LEADER') {
                $query->whereNull('a.parent_id');
            }
        } elseif ($mode === 'tl' && !empty($idList)) {
            // Filter by selected TL parent IDs
            $query->whereIn('a.parent_id', $idList);
        } elseif ($mode === 'individual' && !empty($idList)) {
            // Filter specific agents
            $query->whereIn('a.agent_id', $idList);
        }

        $agents = $query->orderByRaw("FIELD(a.role,'GROUP_LEADER','TEAM_LEADER','INTRODUCER')")
                        ->orderBy('a.full_name')
                        ->paginate(10)
                        ->withQueryString();

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
