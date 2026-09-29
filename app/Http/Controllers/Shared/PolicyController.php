<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\CommissionEngine;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PolicyController extends Controller
{
    public function __construct(private CommissionEngine $commissionEngine) {}

    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $visibleIds = $agent->visibleAgentsQuery()->pluck('agent_id');

        $query = DB::table('sales_transactions as st')
            ->join('customers as c',  'c.customer_id',  '=', 'st.customer_id')
            ->join('vendors as v',    'v.vendor_id',    '=', 'st.vendor_id')
            ->join('products as p',   'p.product_id',   '=', 'st.product_id')
            ->join('agents as a',     'a.agent_id',     '=', 'st.agent_id')
            ->whereIn('st.agent_id', $visibleIds)
            ->where('st.is_deleted', false)
            ->select('st.*','c.full_name as customer_name','v.vendor_name','p.product_name','a.full_name as agent_name');

        if ($s = $request->input('search')) {
            $query->where(fn($q) => $q->where('st.policy_number','like',"%{$s}%")->orWhere('c.full_name','like',"%{$s}%"));
        }
        if ($status = $request->input('status')) {
            $query->where('st.status', $status);
        }

        $policies = $query->orderByDesc('st.created_at')->paginate(20);
        $vendors  = DB::table('vendors')->where('is_active', true)->get();
        $products = DB::table('products')->where('is_active', true)->get();
        $customers = DB::table('customers')
            ->where('owned_by_agent_id', $agent->agent_id)
            ->where('is_deleted', false)
            ->get();

        return view('policy.index', compact('policies', 'vendors', 'products', 'customers', 'agent'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => ['required', 'exists:customers,customer_id'],
            'vendor_id'      => ['required', 'exists:vendors,vendor_id'],
            'product_id'     => ['required', 'exists:products,product_id'],
            'policy_number'  => ['required', 'string', 'max:100', 'unique:sales_transactions,policy_number'],
            'premium_amount' => ['required', 'numeric', 'min:0.01'],
            'sum_insured'    => ['nullable', 'numeric', 'min:0'],
            'coverage_start' => ['required', 'date'],
            'coverage_end'   => ['required', 'date', 'after:coverage_start'],
        ]);

        $agent    = Auth::guard('agent')->user();
        $policyId = Str::uuid()->toString();

        DB::table('sales_transactions')->insert([
            'policy_id'      => $policyId,
            'policy_number'  => strtoupper($request->policy_number),
            'vendor_id'      => $request->vendor_id,
            'product_id'     => $request->product_id,
            'customer_id'    => $request->customer_id,
            'agent_id'       => $agent->agent_id,
            'premium_amount' => $request->premium_amount,
            'sum_insured'    => $request->sum_insured,
            'coverage_start' => $request->coverage_start,
            'coverage_end'   => $request->coverage_end,
            'renewal_date'   => \Carbon\Carbon::parse($request->coverage_end)->addYear()->toDateString(),
            'status'         => 'ACTIVE',
            'version'        => 1,
            'is_deleted'     => false,
            'created_by'     => $agent->agent_id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Store EAV attributes (product-specific fields)
        $extras = $request->input('attributes', []);
        foreach ($extras as $key => $value) {
            if ($value) {
                DB::table('sales_transaction_attributes')->insert([
                    'attr_id'         => Str::uuid()->toString(),
                    'policy_id'       => $policyId,
                    'product_id'      => $request->product_id,
                    'attribute_name'  => $key,
                    'attribute_value' => $value,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        // Fire commission engine
        try {
            $this->commissionEngine->calculate($policyId);
        } catch (\Exception $e) {
            \Log::error("Commission engine failed for policy {$policyId}: " . $e->getMessage());
        }

        AuditService::logChange('sales_transactions', $policyId, 'CREATE');

        return redirect()->route('policies.index')
                         ->with('success', 'Policy submitted and commission calculated.');
    }

    public function show(string $policyId)
    {
        $policy = DB::table('sales_transactions as st')
            ->join('customers as c',  'c.customer_id',  '=', 'st.customer_id')
            ->join('vendors as v',    'v.vendor_id',    '=', 'st.vendor_id')
            ->join('products as p',   'p.product_id',   '=', 'st.product_id')
            ->join('agents as a',     'a.agent_id',     '=', 'st.agent_id')
            ->where('st.policy_id', $policyId)
            ->select('st.*','c.full_name as customer_name','v.vendor_name','p.product_name','a.full_name as agent_name')
            ->firstOrFail();

        $attributes = DB::table('sales_transaction_attributes')
            ->where('policy_id', $policyId)->get();

        $commissions = DB::table('commission_transactions as ct')
            ->join('agents as a', 'a.agent_id', '=', 'ct.agent_id')
            ->where('ct.policy_id', $policyId)
            ->select('ct.*', 'a.full_name as agent_name', 'a.member_code')
            ->get();

        $agent = Auth::guard('agent')->user();
        return view('policy.show', compact('policy', 'attributes', 'commissions', 'agent'));
    }

    public function cancel(Request $request, string $policyId)
    {
        $request->validate(['reason' => ['required', 'string', 'max:300']]);

        $before = DB::table('sales_transactions')->where('policy_id', $policyId)->first();
        DB::table('sales_transactions')->where('policy_id', $policyId)
            ->update(['status' => 'CANCELLED', 'updated_at' => now()]);

        // Reverse commissions
        $reversalId = Str::uuid()->toString();
        try {
            $this->commissionEngine->reverse($policyId, $reversalId);
        } catch (\Exception $e) {
            \Log::error("Commission reversal failed for {$policyId}: " . $e->getMessage());
        }

        AuditService::logChange('sales_transactions', $policyId, 'CANCEL', (array)$before, ['status' => 'CANCELLED', 'reason' => $request->reason]);
        return back()->with('success', 'Policy cancelled and commissions reversed.');
    }
}
