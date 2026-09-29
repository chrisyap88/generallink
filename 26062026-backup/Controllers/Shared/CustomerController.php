<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $visibleAgentIds = $agent->visibleAgentsQuery()->pluck('agent_id');

        $query = DB::table('customers as c')
            ->join('agents as a', 'a.agent_id', '=', 'c.owned_by_agent_id')
            ->whereIn('c.owned_by_agent_id', $visibleAgentIds)
            ->where('c.is_deleted', false)
            ->select('c.*', 'a.full_name as agent_name', 'a.member_code');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('c.full_name', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('c.phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('c.created_at')->paginate(20);

        return view('customer.index', compact('customers', 'agent'));
    }

    public function create()
    {
        return view('customer.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'nric'      => ['required', 'string', 'size:12'],
            'phone'     => ['required', 'string', 'max:20'],
            'email'     => ['nullable', 'email', 'max:200'],
            'address'   => ['nullable', 'string'],
            'postcode'  => ['nullable', 'string', 'max:10'],
            'city'      => ['nullable', 'string', 'max:100'],
            'state'     => ['nullable', 'string', 'max:100'],
        ]);

        $agent   = Auth::guard('agent')->user();
        $nricRaw = preg_replace('/[^0-9]/', '', $request->nric);
        $nricHash = hash('sha256', $nricRaw);

        // Check for duplicate NRIC
        $existing = DB::table('customers')->where('nric_hash', $nricHash)->where('is_deleted', false)->first();
        if ($existing) {
            return back()->withErrors(['nric' => 'A customer with this NRIC already exists.'])->withInput();
        }

        $customerId = Str::uuid()->toString();
        DB::table('customers')->insert([
            'customer_id'        => $customerId,
            'nric_encrypted'     => encrypt($nricRaw),
            'nric_hash'          => $nricHash,
            'full_name'          => $request->full_name,
            'email'              => strtolower($request->email ?? ''),
            'phone'              => $request->phone,
            'address'            => $request->address,
            'postcode'           => $request->postcode,
            'city'               => $request->city,
            'state'              => $request->state,
            'owned_by_agent_id'  => $agent->agent_id,
            'is_deleted'         => false,
            'created_by'         => $agent->agent_id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        AuditService::logChange('customers', $customerId, 'CREATE');

        return redirect()->route('customers.show', $customerId)
                         ->with('success', 'Customer created successfully.');
    }

    public function show(string $customerId)
    {
        $agent    = Auth::guard('agent')->user();
        $customer = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false)->firstOrFail();

        // Decrypt NRIC for display (masked)
        $nricMasked = '****' . substr(decrypt($customer->nric_encrypted), -4);

        $policies = DB::table('sales_transactions as st')
            ->join('vendors as v',  'v.vendor_id',  '=', 'st.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'st.product_id')
            ->where('st.customer_id', $customerId)
            ->where('st.is_deleted', false)
            ->select('st.*', 'v.vendor_name', 'p.product_name')
            ->orderByDesc('st.created_at')
            ->get();

        return view('customer.show', compact('customer', 'nricMasked', 'policies', 'agent'));
    }

    public function update(Request $request, string $customerId)
    {
        $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'phone'     => ['required', 'string', 'max:20'],
            'email'     => ['nullable', 'email', 'max:200'],
            'address'   => ['nullable', 'string'],
            'postcode'  => ['nullable', 'string', 'max:10'],
            'city'      => ['nullable', 'string', 'max:100'],
            'state'     => ['nullable', 'string', 'max:100'],
        ]);

        $before = DB::table('customers')->where('customer_id', $customerId)->first();
        DB::table('customers')->where('customer_id', $customerId)->update([
            'full_name'  => $request->full_name,
            'email'      => strtolower($request->email ?? ''),
            'phone'      => $request->phone,
            'address'    => $request->address,
            'postcode'   => $request->postcode,
            'city'       => $request->city,
            'state'      => $request->state,
            'updated_by' => Auth::guard('agent')->id(),
            'updated_at' => now(),
        ]);

        AuditService::logChange('customers', $customerId, 'UPDATE', (array)$before, $request->all());
        return back()->with('success', 'Customer updated.');
    }
}
