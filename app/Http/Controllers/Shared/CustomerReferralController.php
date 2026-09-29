<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #213). Customer
// refer-a-friend, logged by the owning agent on the customer's behalf
// (no Customer Portal yet — task #147, deferred). Admin sees every
// referral company-wide; every other role sees only their own
// customers' referrals. Reward is never auto-credited.
class CustomerReferralController extends Controller
{
    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $isAdmin = $agent->role === 'ADMIN';
        $status = in_array($request->get('status'), ['SUBMITTED', 'CONTACTED', 'CONVERTED', 'DECLINED'], true) ? $request->get('status') : '';

        $query = DB::table('customer_referrals as r')
            ->join('customers as c', 'r.referring_customer_id', '=', 'c.customer_id')
            ->join('agents as a', 'r.agent_id', '=', 'a.agent_id');

        if (!$isAdmin) {
            $query->where('r.agent_id', $agent->agent_id);
        }
        if ($status !== '') {
            $query->where('r.status', $status);
        }

        $referrals = $query->select(
            'r.referral_id', 'r.referred_name', 'r.referred_contact', 'r.notes', 'r.status',
            'r.reward_type', 'r.reward_value', 'r.reward_status', 'r.created_at',
            'c.full_name as referring_customer_name', 'a.full_name as agent_name'
        )->orderByDesc('r.created_at')->paginate(8)->appends($request->except('page'));

        return view('growth.customer-referrals', compact('referrals', 'isAdmin', 'agent', 'status'));
    }

    public function create()
    {
        $agent = Auth::guard('agent')->user();
        return view('growth.customer-referral-create', compact('agent'));
    }

    public function store(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        $request->validate([
            'referring_customer_id' => ['required', 'string'],
            'referred_name'         => ['required', 'string', 'max:200'],
            'referred_contact'      => ['required', 'string', 'max:100'],
            'notes'                 => ['nullable', 'string'],
        ]);

        // Referring customer must actually belong to this agent — never
        // trust a client-supplied customer_id at face value.
        $customer = DB::table('customers')
            ->where('customer_id', $request->input('referring_customer_id'))
            ->where('owned_by_agent_id', $agent->agent_id)
            ->where('is_deleted', false)
            ->first();
        if (!$customer) {
            return back()->withErrors(['referring_customer_id' => 'Select a valid customer of yours.'])->withInput();
        }

        $referralId = (string) Str::uuid();
        DB::table('customer_referrals')->insert([
            'referral_id'            => $referralId,
            'referring_customer_id'  => $customer->customer_id,
            'agent_id'               => $agent->agent_id,
            'referred_name'          => $request->input('referred_name'),
            'referred_contact'       => $request->input('referred_contact'),
            'notes'                  => $request->input('notes'),
            'status'                 => 'SUBMITTED',
            'reward_status'          => 'NONE',
            'created_by'             => $agent->agent_id,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        AuditService::logChange('customer_referrals', $referralId, 'CUSTOMER_REFERRAL_CREATED', null, ['referred_name' => $request->input('referred_name')]);

        return redirect()->route('customer-referrals.index')->with('success', 'Referral logged.');
    }

    public function updateStatus(Request $request, string $id)
    {
        $agent = Auth::guard('agent')->user();
        $referral = DB::table('customer_referrals')->where('referral_id', $id)->first();
        abort_if(!$referral, 404);
        abort_unless($agent->role === 'ADMIN' || $referral->agent_id === $agent->agent_id, 403);

        $request->validate(['status' => ['required', 'in:SUBMITTED,CONTACTED,CONVERTED,DECLINED']]);

        DB::table('customer_referrals')->where('referral_id', $id)->update([
            'status'     => $request->input('status'),
            'updated_at' => now(),
        ]);
        AuditService::logChange('customer_referrals', $id, 'CUSTOMER_REFERRAL_STATUS_CHANGED', ['status' => $referral->status], ['status' => $request->input('status')]);

        return redirect()->back()->with('success', 'Status updated.');
    }

    // Admin-only — sets the reward that will be paid once actually
    // credited through the relevant existing screen (points/document
    // credit/cash), same bookkeeping-only pattern as Breakaway/Contests.
    public function setReward(Request $request, string $id)
    {
        $agent = Auth::guard('agent')->user();
        abort_unless($agent->role === 'ADMIN', 403);

        $referral = DB::table('customer_referrals')->where('referral_id', $id)->first();
        abort_if(!$referral, 404);

        $request->validate([
            'reward_type'  => ['required', 'in:POINTS,CASH,DOCUMENT_CREDIT'],
            'reward_value' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::table('customer_referrals')->where('referral_id', $id)->update([
            'reward_type'   => $request->input('reward_type'),
            'reward_value'  => (float) $request->input('reward_value'),
            'reward_status' => 'PENDING',
            'updated_at'    => now(),
        ]);
        AuditService::logChange('customer_referrals', $id, 'CUSTOMER_REFERRAL_REWARD_SET', null, $request->only('reward_type', 'reward_value'));

        return redirect()->back()->with('success', 'Reward set.');
    }

    public function markRewarded(string $id)
    {
        $agent = Auth::guard('agent')->user();
        abort_unless($agent->role === 'ADMIN', 403);

        $referral = DB::table('customer_referrals')->where('referral_id', $id)->first();
        abort_if(!$referral, 404);

        if ($referral->reward_status === 'PENDING') {
            DB::table('customer_referrals')->where('referral_id', $id)->update([
                'reward_status' => 'AWARDED',
                'rewarded_at'   => now(),
                'rewarded_by'   => $agent->agent_id,
                'updated_at'    => now(),
            ]);
            AuditService::logChange('customer_referrals', $id, 'CUSTOMER_REFERRAL_REWARD_AWARDED', $referral, ['reward_status' => 'AWARDED']);
        }

        return redirect()->back()->with('success', 'Marked as rewarded.');
    }
}
