<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 18 Jul 2026 — the customer-facing side of the renewal reminder.
// No login required — the customer reaches this page via a secure,
// signed link emailed to them (see SendRenewalReminders command), and
// it expires automatically (Laravel signed-URL TTL). This is
// deliberately NOT a real login: the customers table has no password
// of its own, and building one would blur the line between "customer"
// and "agent account" — see the design discussion with Chris.
//
// Clicking "Yes Renew" doesn't create a Sales Transaction — the actual
// new-year document/pricing doesn't exist yet (only the vendor can
// quote it). It just records intent + which add-ons/coverage type the
// customer wants to keep, and notifies the owning Introducer (+ their
// TL/GL/Admin) that a quotation is now owed. That's tracked by
// CheckRenewalQuotationReminders separately.
// -------------------------------------------------------
class RenewalResponseController extends Controller
{
    public function show(Request $request, string $policyId)
    {
        abort_unless($request->hasValidSignature(), 403, 'This renewal link has expired or is invalid. Please contact your agent for a new one.');

        $txn = DB::table('sales_transactions as st')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->where('st.policy_id', $policyId)
            ->where('st.is_deleted', false)
            ->select('st.policy_id', 'st.document_reference_number', 'st.sum_insured', 'c.full_name as customer_name', 'v.vendor_name')
            ->first();

        abort_if(!$txn, 404);

        $renewal = DB::table('insurance_renewal_schedules')->where('policy_id', $policyId)->first();
        abort_if(!$renewal, 404);

        $attributes = DB::table('sales_transaction_attributes')
            ->where('policy_id', $policyId)
            ->pluck('attribute_value', 'attribute_name');

        $existingRequest = DB::table('renewal_quotation_requests')
            ->where('policy_id', $policyId)
            ->orderByDesc('requested_at')
            ->first();

        return view('renewal.response', compact('txn', 'renewal', 'attributes', 'existingRequest'));
    }

    public function respond(Request $request, string $policyId)
    {
        abort_unless($request->hasValidSignature(), 403, 'This renewal link has expired or is invalid. Please contact your agent for a new one.');

        $request->validate([
            'decision' => ['required', 'in:YES,NO,DISCUSS'],
        ]);

        $txn = DB::table('sales_transactions')->where('policy_id', $policyId)->where('is_deleted', false)->first();
        abort_if(!$txn, 404);

        // Don't create a duplicate active request if one is already
        // sitting there unresolved for this policy.
        $alreadyPending = DB::table('renewal_quotation_requests')
            ->where('policy_id', $policyId)
            ->where('status', 'REQUESTED')
            ->exists();

        if (!$alreadyPending) {
            $requestId = Str::uuid()->toString();
            DB::table('renewal_quotation_requests')->insert([
                'request_id'  => $requestId,
                'policy_id'   => $policyId,
                'customer_id' => $txn->customer_id,
                'agent_id'    => $txn->agent_id,
                'decision'    => $request->decision,
                'status'      => $request->decision === 'YES' ? 'REQUESTED' : 'CANCELLED',
                'requested_at'=> now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Immediate notification (not an escalation — that's the
            // scheduled command's job) so the Introducer knows right
            // away a customer is waiting on them, same day.
            if ($request->decision === 'YES') {
                $customer = DB::table('customers')->where('customer_id', $txn->customer_id)->first();
                $this->notifyOwnerChain($txn->agent_id, 'RENEWAL_QUOTATION_REQUESTED', 'Customer Requesting Renewal Quotation',
                    "{$customer->full_name} has asked to renew policy {$txn->document_reference_number} and is waiting for a quotation. Please prepare and upload it as soon as possible.");
            }
        }

        return view('renewal.response-thanks', ['decision' => $request->decision]);
    }

    /**
     * Notify the owning Introducer + their TL + GL + Admin — the same
     * distribution list agreed for the renewal reminder itself.
     */
    private function notifyOwnerChain(string $agentId, string $type, string $title, string $message): void
    {
        $notificationService = app(NotificationService::class);
        $owner = \App\Models\Agent::find($agentId);
        if (!$owner) {
            return;
        }

        $recipients = collect([$owner]);
        $current = $owner;
        while ($current->parent_id) {
            $parent = \App\Models\Agent::find($current->parent_id);
            if (!$parent) break;
            $recipients->push($parent);
            if ($parent->role === 'GROUP_LEADER') break;
            $current = $parent;
        }
        $admins = \App\Models\Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
        $recipients = $recipients->merge($admins)->unique('agent_id')->values()->all();

        $notificationService->notify($recipients, $type, $title, $message, $agentId);
    }
}
