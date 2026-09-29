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
// NEW 31 Jul 2026 — "Override Recipient Profile" (personal profile),
// per Chris's spec. Lets Admin pick ANY specific agent — regardless of
// their system Role, and regardless of where they sit in the org
// hierarchy — and give them a personal override entitlement: a % or a
// fixed RM amount, drawn from a chosen source Role (optionally one
// specific Rank within it), scoped to a company/product or all of
// them, active between an effective and expiry date, switchable
// Active/Inactive. An agent can have any number of these rows (multiple
// override sources) — see CommissionEngine::resolveIndividualOverrides()
// for how they're evaluated; every matching active row stacks.
//
// Distinct from Rank Promotion Rules / Rank Allocation, which are
// configured per RANK SLOT and apply to whoever currently holds it —
// this screen is configured per NAMED PERSON instead.
// -------------------------------------------------------
class AgentOverrideProfileController extends Controller
{
    private const SOURCE_ROLES = ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'];

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $agentsQuery = DB::table('agents')
            ->where('is_deleted', false)
            ->where('role', '!=', 'ADMIN')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('full_name', 'like', "%{$search}%")
                       ->orWhere('agent_code', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name');

        $agents = $agentsQuery->paginate(50)->withQueryString();

        // So the list shows at a glance who already has an override profile.
        $agentIdsWithOverrides = DB::table('agent_commission_overrides')
            ->whereIn('recipient_agent_id', collect($agents->items())->pluck('agent_id'))
            ->distinct()
            ->pluck('recipient_agent_id')
            ->all();

        return view('masterfile.override-recipient-profile-index', compact('agents', 'search', 'agentIdsWithOverrides'));
    }

    public function profile(Request $request, string $agentId)
    {
        $agent = DB::table('agents')->where('agent_id', $agentId)->first();
        abort_if(!$agent, 404);

        $rows = DB::table('agent_commission_overrides as o')
            ->leftJoin('role_ranks as rr', 'rr.rank_id', '=', 'o.source_rank_id')
            ->leftJoin('vendors as v', 'v.vendor_id', '=', 'o.vendor_id')
            ->leftJoin('products as p', 'p.product_id', '=', 'o.product_id')
            ->where('o.recipient_agent_id', $agentId)
            ->orderByDesc('o.priority')
            ->orderBy('o.created_at')
            ->select('o.*', 'rr.rank_name', 'v.vendor_name', 'p.product_name')
            ->get();

        // NEW 31 Jul 2026 — natural-sorted by the admin-typed Rank No
        // instead of the old plain-integer display_order.
        $ranksByRole = collect(self::SOURCE_ROLES)->mapWithKeys(function ($role) {
            return [$role => DB::table('role_ranks')->where('role', $role)->where('is_active', true)->get(['rank_id', 'rank_name', 'rank_no'])->sortBy(fn($r) => $r->rank_no, SORT_NATURAL)->values()];
        });

        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name']);

        // vendor_id => [{product_id, product_name}] for the client-side
        // dependent dropdown — no extra AJAX route needed.
        $productsByVendor = DB::table('products')
            ->where('is_active', true)
            ->orderBy('product_name')
            ->get(['product_id', 'product_name', 'vendor_id'])
            ->groupBy('vendor_id');

        return view('masterfile.override-recipient-profile', [
            'agent'            => $agent,
            'roleLabel'        => RoleLabelService::label($agent->role, $agent->group_label_id ?? null),
            'rows'             => $rows,
            'ranksByRole'      => $ranksByRole,
            'vendors'          => $vendors,
            'productsByVendor' => $productsByVendor,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'recipient_agent_id' => ['required', 'exists:agents,agent_id'],
            'source_role'         => ['required', 'in:' . implode(',', self::SOURCE_ROLES)],
            'source_rank_id'      => ['nullable', 'exists:role_ranks,rank_id'],
            'vendor_id'           => ['nullable', 'exists:vendors,vendor_id'],
            'product_id'          => ['nullable', 'exists:products,product_id'],
            'override_type'       => ['required', 'in:PERCENTAGE,FIXED_AMOUNT'],
            'override_value'      => ['required', 'numeric', 'min:0'],
            'effective_date'      => ['required', 'date'],
            'expiry_date'         => ['nullable', 'date', 'after_or_equal:effective_date'],
            'priority'            => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->input('override_type') === 'PERCENTAGE' && (float) $request->input('override_value') > 100) {
            return redirect()->back()->withErrors(['A percentage override cannot exceed 100.']);
        }

        $row = [
            'override_id'         => (string) Str::uuid(),
            'recipient_agent_id'  => $request->input('recipient_agent_id'),
            'source_role'         => $request->input('source_role'),
            'source_rank_id'      => $request->input('source_rank_id') ?: null,
            'vendor_id'           => $request->input('vendor_id') ?: null,
            'product_id'          => $request->input('product_id') ?: null,
            'override_type'       => $request->input('override_type'),
            'override_value'      => (float) $request->input('override_value'),
            'effective_date'      => $request->input('effective_date'),
            'expiry_date'         => $request->input('expiry_date') ?: null,
            'status'              => 'ACTIVE',
            'priority'            => (int) ($request->input('priority') ?: 0),
            'created_by'          => Auth::guard('agent')->id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ];

        DB::table('agent_commission_overrides')->insert($row);

        AuditService::logChange('agent_commission_overrides', $row['override_id'], 'OVERRIDE_RECIPIENT_RULE_ADDED', null, $row);

        return redirect()->route('admin.masterfile.override-recipient-profile.show', $row['recipient_agent_id'])->with('success', 'Override rule added.');
    }

    public function toggleStatus(Request $request, string $id)
    {
        $row = DB::table('agent_commission_overrides')->where('override_id', $id)->first();
        abort_if(!$row, 404);

        $newStatus = $row->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        DB::table('agent_commission_overrides')->where('override_id', $id)->update([
            'status'     => $newStatus,
            'updated_at' => now(),
        ]);

        AuditService::logChange('agent_commission_overrides', $id, 'OVERRIDE_RECIPIENT_RULE_STATUS_CHANGED', $row, ['status' => $newStatus]);

        return redirect()->route('admin.masterfile.override-recipient-profile.show', $row->recipient_agent_id)->with('success', "Rule set to {$newStatus}.");
    }

    public function destroy(Request $request, string $id)
    {
        $row = DB::table('agent_commission_overrides')->where('override_id', $id)->first();
        abort_if(!$row, 404);

        DB::table('agent_commission_overrides')->where('override_id', $id)->delete();

        AuditService::logChange('agent_commission_overrides', $id, 'OVERRIDE_RECIPIENT_RULE_REMOVED', $row, null);

        return redirect()->route('admin.masterfile.override-recipient-profile.show', $row->recipient_agent_id)->with('success', 'Rule removed.');
    }
}
