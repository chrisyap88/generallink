<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PointsController extends Controller
{
    public function __construct(private PointsService $service) {}

    // -------------------------------------------------------
    // Transfer / Buy Points page
    // -------------------------------------------------------
    public function index()
    {
        $agent   = Auth::guard('agent')->user();
        $balance = $this->service->getPointsBalance($agent->agent_id);
        $ledger  = DB::table('reward_points_ledger')
                     ->where('agent_id', $agent->agent_id)
                     ->orderByDesc('created_at')
                     ->limit(20)
                     ->get();

        // Admin bank details (to display to members wanting to buy points)
        $adminBank = DB::table('agents')
                       ->where('role', 'ADMIN')
                       ->where('is_deleted', false)
                       ->select('full_name', 'admin_bank_name', 'admin_bank_account_encrypted')
                       ->first();

        $adminBankAccount = null;
        if ($adminBank && $adminBank->admin_bank_account_encrypted) {
            try { $adminBankAccount = decrypt($adminBank->admin_bank_account_encrypted); } catch (\Exception $e) {}
        }

        // Pending purchase requests by this agent
        $myPurchases = DB::table('point_purchases')
                         ->where('agent_id', $agent->agent_id)
                         ->orderByDesc('created_at')
                         ->get();

        return view('points.index', compact(
            'agent', 'balance', 'ledger', 'myPurchases',
            'adminBank', 'adminBankAccount'
        ));
    }

    // -------------------------------------------------------
    // Transfer points to another member
    // -------------------------------------------------------
    public function transferPoints(Request $request)
    {
        $request->validate([
            'to_member_code' => ['required', 'string'],
            'points'         => ['required', 'numeric', 'min:1'],
            'notes'          => ['nullable', 'string', 'max:200'],
        ]);

        $agent = Auth::guard('agent')->user();
        $to    = Agent::where('member_code', $request->to_member_code)
                      ->where('is_deleted', false)->first()
                   ?? Agent::where('agent_code', $request->to_member_code)
                            ->where('is_deleted', false)->first();

        if (! $to) return back()->with('error', 'Member not found. Check the member code.');

        try {
            $this->service->transferPoints($agent, $to->agent_id, (float) $request->points, $request->notes);
            return back()->with('success', number_format($request->points) . ' points transferred to ' . $to->full_name . '.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // Transfer commission to another member
    // -------------------------------------------------------
    public function transferCommission(Request $request)
    {
        $request->validate([
            'to_member_code' => ['required', 'string'],
            'amount'         => ['required', 'numeric', 'min:1'],
            'notes'          => ['nullable', 'string', 'max:200'],
        ]);

        $agent = Auth::guard('agent')->user();
        $to    = Agent::where('member_code', $request->to_member_code)
                      ->where('is_deleted', false)->first()
                   ?? Agent::where('agent_code', $request->to_member_code)
                            ->where('is_deleted', false)->first();

        if (! $to) return back()->with('error', 'Member not found. Check the member code.');

        try {
            $this->service->transferCommission($agent, $to->agent_id, (float) $request->amount, $request->notes);
            return back()->with('success', 'RM ' . number_format($request->amount, 2) . ' transferred to ' . $to->full_name . '.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // Submit point purchase request + bank slip upload
    // -------------------------------------------------------
    public function submitPurchase(Request $request)
    {
        $request->validate([
            'amount_paid'  => ['required', 'numeric', 'min:1'],
            'bank_slip'    => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'slip_ref'     => ['required', 'string', 'max:100'],
            'bank_in_date' => ['required', 'date'],
        ]);

        $agent    = Auth::guard('agent')->user();
        $slipPath = $request->file('bank_slip')->store('bank_slips', 'local');

        try {
            $this->service->submitPurchaseRequest(
                $agent,
                (float) $request->amount_paid,
                $slipPath,
                $request->slip_ref,
                $request->bank_in_date
            );
            return back()->with('success', 'Purchase request submitted. Admin will credit your points after verification.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // Admin: view all pending point purchases
    // -------------------------------------------------------
    public function adminPurchases()
    {
        $purchases = DB::table('point_purchases as pp')
            ->join('agents as a', 'a.agent_id', '=', 'pp.agent_id')
            ->select('pp.*', 'a.full_name as agent_name', 'a.member_code', 'a.agent_code')
            ->orderByDesc('pp.created_at')
            ->paginate(20);

        $adminAgent = Auth::guard('agent')->user();
        $adminBankAccount = null;
        if ($adminAgent->admin_bank_account_encrypted) {
            try { $adminBankAccount = decrypt($adminAgent->admin_bank_account_encrypted); } catch (\Exception $e) {}
        }

        return view('points.admin-purchases', compact('purchases', 'adminAgent', 'adminBankAccount'));
    }

    // -------------------------------------------------------
    // Admin: approve a purchase
    // -------------------------------------------------------
    public function approvePurchase(string $purchaseId)
    {
        $admin = Auth::guard('agent')->user();
        try {
            $this->service->approvePurchase($purchaseId, $admin->agent_id);
            return back()->with('success', 'Points credited to member successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // Admin: reject a purchase
    // -------------------------------------------------------
    public function rejectPurchase(Request $request, string $purchaseId)
    {
        $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $admin = Auth::guard('agent')->user();
        $this->service->rejectPurchase($purchaseId, $admin->agent_id, $request->reason);
        return back()->with('success', 'Purchase request rejected.');
    }
}
