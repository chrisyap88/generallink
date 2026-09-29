<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 31 Jul 2026 — Vendor Override Members. Per Chris, this is a
// DIFFERENT concept from Override Recipient Profile (agent_commission_
// overrides): that one pays an extra slice to a named AGENT inside our
// hierarchy. This one represents people on the VENDOR'S side — an
// insurance company's Country Director, Regional Directors, Marketing
// Director, or in other industries a distribution/manufacturer rep or
// franchise counterpart. Deliberately NOT stored in `agents` — they
// have no role, no rank, no parent_id, no login, no wallet.
//
// Each member belongs to exactly one vendor AND one Special Privilege
// Group (their override is calculated only off that group's book of
// business with that vendor). A vendor can have several members at
// once (AIA's Country Director + 3 Region Directors, each with their
// own separate deal). Eligibility/payout rules live one level below
// the member, scoped per PRODUCT (Motor vs Fire vs PA can each have a
// totally different rate for the same person). Settlement (deduct vs
// claim-back) is set per member — confirmed by Chris this varies
// member by member, never automatic.
// -------------------------------------------------------
class OverrideMemberController extends Controller
{
    private const CRITERIA = ['PERCENTAGE', 'FIXED_AMOUNT', 'SALES_TARGET', 'CUSTOM_KPI'];

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $vendorId = $request->filled('vendor_id') ? $request->get('vendor_id') : null;

        $members = DB::table('override_members as om')
            ->join('vendors as v', 'v.vendor_id', '=', 'om.vendor_id')
            ->join('group_labels as gl', 'gl.group_label_id', '=', 'om.group_label_id')
            ->when($vendorId, fn($q) => $q->where('om.vendor_id', $vendorId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('om.full_name', 'like', "%{$search}%")
                       ->orWhere('om.override_member_code', 'like', "%{$search}%")
                       ->orWhere('om.position_title', 'like', "%{$search}%");
                });
            })
            ->orderBy('v.vendor_name')->orderBy('om.full_name')
            ->select('om.*', 'v.vendor_name', 'gl.group_name')
            ->paginate(50)->withQueryString();

        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name']);

        return view('masterfile.override-members-index', compact('members', 'vendors', 'search', 'vendorId'));
    }

    public function create()
    {
        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name']);
        // FIXED 18 Aug 2026 — per Chris: this picker's own label says
        // "Organization Rewards Group", so it must only ever offer ORG
        // groups — no DSG/CBE groups mixed in.
        $groupLabels = DB::table('group_labels')->where('group_type', 'ORG')->orderBy('group_name')->get(['group_label_id', 'group_name']);

        return view('masterfile.override-members-create', compact('vendors', 'groupLabels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name'         => ['required', 'string', 'max:255'],
            'position_title'    => ['required', 'string', 'max:255'],
            'vendor_id'         => ['required', 'exists:vendors,vendor_id'],
            'group_label_id'    => ['required', 'exists:group_labels,group_label_id'],
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'settlement_method' => ['required', 'in:DEDUCT_FROM_CLAIM,CLAIM_BACK_REPORT'],
        ]);

        $id = (string) Str::uuid();
        $row = [
            'override_member_id'  => $id,
            'override_member_code'=> $this->nextCode(),
            'full_name'           => $request->input('full_name'),
            'position_title'      => $request->input('position_title'),
            'vendor_id'           => $request->input('vendor_id'),
            'group_label_id'      => $request->input('group_label_id'),
            'email'               => $request->input('email') ?: null,
            'phone'               => $request->input('phone') ?: null,
            'settlement_method'   => $request->input('settlement_method'),
            'is_active'           => true,
            'created_by'          => Auth::guard('agent')->id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ];

        DB::table('override_members')->insert($row);
        AuditService::logChange('override_members', $id, 'OVERRIDE_MEMBER_CREATED', null, $row);

        return redirect()->route('admin.masterfile.override-members.show', $id)->with('success', "Override member {$row['override_member_code']} created — now add their per-product eligibility rules below.");
    }

    private function nextCode(): string
    {
        $last = DB::table('override_members')->orderByDesc('created_at')->value('override_member_code');
        $nextNum = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $nextNum = ((int) $m[1]) + 1;
        }
        $code = 'OVM-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
        while (DB::table('override_members')->where('override_member_code', $code)->exists()) {
            $nextNum++;
            $code = 'OVM-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    public function show(Request $request, string $id)
    {
        $member = DB::table('override_members as om')
            ->join('vendors as v', 'v.vendor_id', '=', 'om.vendor_id')
            ->join('group_labels as gl', 'gl.group_label_id', '=', 'om.group_label_id')
            ->where('om.override_member_id', $id)
            ->select('om.*', 'v.vendor_name', 'gl.group_name')
            ->first();
        abort_if(!$member, 404);

        $rules = DB::table('override_member_eligibility_rules as r')
            ->leftJoin('products as p', 'p.product_id', '=', 'r.product_id')
            ->where('r.override_member_id', $id)
            ->orderBy('p.product_name')
            ->select('r.*', 'p.product_name')
            ->get();

        $products = DB::table('products as p')
            ->join('vendors as v', 'v.vendor_id', '=', 'p.vendor_id')
            ->where('p.vendor_id', $member->vendor_id)
            ->where('p.is_active', true)
            ->orderBy('p.product_name')
            ->get(['p.product_id', 'p.product_name']);

        return view('masterfile.override-members-show', compact('member', 'rules', 'products'));
    }

    public function update(Request $request, string $id)
    {
        $member = DB::table('override_members')->where('override_member_id', $id)->first();
        abort_if(!$member, 404);

        $request->validate([
            'full_name'         => ['required', 'string', 'max:255'],
            'position_title'    => ['required', 'string', 'max:255'],
            'group_label_id'    => ['required', 'exists:group_labels,group_label_id'],
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'settlement_method' => ['required', 'in:DEDUCT_FROM_CLAIM,CLAIM_BACK_REPORT'],
            'is_active'         => ['required', 'in:0,1'],
        ]);

        DB::table('override_members')->where('override_member_id', $id)->update([
            'full_name'         => $request->input('full_name'),
            'position_title'    => $request->input('position_title'),
            'group_label_id'    => $request->input('group_label_id'),
            'email'             => $request->input('email') ?: null,
            'phone'             => $request->input('phone') ?: null,
            'settlement_method' => $request->input('settlement_method'),
            'is_active'         => $request->input('is_active'),
            'updated_at'        => now(),
        ]);

        AuditService::logChange('override_members', $id, 'OVERRIDE_MEMBER_UPDATED', (array) $member, $request->all());

        return redirect()->route('admin.masterfile.override-members.show', $id)->with('success', 'Override member updated.');
    }

    public function storeRule(Request $request, string $id)
    {
        $member = DB::table('override_members')->where('override_member_id', $id)->first();
        abort_if(!$member, 404);

        $request->validate([
            'product_id'              => ['nullable', 'exists:products,product_id'],
            'criteria_type'           => ['required', 'in:' . implode(',', self::CRITERIA)],
            'threshold_value'         => ['nullable', 'numeric', 'min:0'],
            'sales_metric'            => ['nullable', 'in:PREMIUM,EARNING_INCOME'],
            'period_months'           => ['nullable', 'integer', 'min:1', 'max:60'],
            'custom_kpi_description'  => ['nullable', 'string', 'max:1000'],
        ]);

        $criteria = $request->input('criteria_type');
        if (in_array($criteria, ['PERCENTAGE', 'FIXED_AMOUNT', 'SALES_TARGET']) && $request->input('threshold_value') === null) {
            return back()->withErrors(['threshold_value' => 'Enter a value for this criteria type.'])->withInput();
        }
        if ($criteria === 'CUSTOM_KPI' && !$request->filled('custom_kpi_description')) {
            return back()->withErrors(['custom_kpi_description' => 'Describe the KPI to check for this rule.'])->withInput();
        }

        $ruleId = (string) Str::uuid();
        DB::table('override_member_eligibility_rules')->insert([
            'rule_id'                => $ruleId,
            'override_member_id'     => $id,
            'product_id'             => $request->input('product_id') ?: null,
            'criteria_type'          => $criteria,
            'threshold_value'        => $request->input('threshold_value'),
            'sales_metric'           => $request->input('sales_metric') ?: null,
            'period_months'          => $request->input('period_months') ?: null,
            'custom_kpi_description' => $request->input('custom_kpi_description') ?: null,
            'is_active'              => true,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        AuditService::logChange('override_member_eligibility_rules', $ruleId, 'OVERRIDE_ELIGIBILITY_RULE_ADDED', null, $request->all());

        return redirect()->route('admin.masterfile.override-members.show', $id)->with('success', 'Eligibility rule added.');
    }

    public function toggleRule(Request $request, string $id, string $ruleId)
    {
        $rule = DB::table('override_member_eligibility_rules')->where('rule_id', $ruleId)->first();
        abort_if(!$rule, 404);

        $newStatus = ! $rule->is_active;
        DB::table('override_member_eligibility_rules')->where('rule_id', $ruleId)->update([
            'is_active'  => $newStatus,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.override-members.show', $id)->with('success', $newStatus ? 'Rule activated.' : 'Rule deactivated.');
    }

    public function destroyRule(Request $request, string $id, string $ruleId)
    {
        $rule = DB::table('override_member_eligibility_rules')->where('rule_id', $ruleId)->first();
        abort_if(!$rule, 404);

        DB::table('override_member_eligibility_rules')->where('rule_id', $ruleId)->delete();
        AuditService::logChange('override_member_eligibility_rules', $ruleId, 'OVERRIDE_ELIGIBILITY_RULE_REMOVED', (array) $rule, null);

        return redirect()->route('admin.masterfile.override-members.show', $id)->with('success', 'Rule removed.');
    }
}
