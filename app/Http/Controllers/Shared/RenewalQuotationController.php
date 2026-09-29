<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 18 Jul 2026 — the agent-facing side of the renewal quotation
// flow. Lists requests where a customer said "Yes Renew, please give
// me the renewal insurance coverage quotation" and lets whoever's
// handling it upload the quotation PDF, which flips status to
// QUOTATION_SENT and stops the escalation reminders for that request.
// Scoped via DataScopeService so Admin sees everything, GL sees their
// group, TL sees themself + their Introducers, Introducer sees only
// their own.
// -------------------------------------------------------
class RenewalQuotationController extends Controller
{
    private function rolePrefix($agent): string
    {
        return match ($agent->role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $query = DB::table('renewal_quotation_requests as rqr')
            ->join('sales_transactions as st', 'rqr.policy_id', '=', 'st.policy_id')
            ->join('customers as c', 'rqr.customer_id', '=', 'c.customer_id')
            ->join('agents as a', 'rqr.agent_id', '=', 'a.agent_id')
            ->where('rqr.status', 'REQUESTED');
        // NOTE: applyToQuery() is built for filtering the agents table
        // itself (it checks group_id/parent_id ON the row) — wrong tool
        // here, since each renewal_quotation_requests row belongs to an
        // agent via a foreign key, not IS an agent. Use the same
        // whereIn(getAgentIds()) approach as applyToTransactions/
        // applyToCustomers instead.
        if (!$scope->isAdmin()) {
            $query->whereIn('rqr.agent_id', $scope->getAgentIds());
        }

        $requests = $query->select(
                'rqr.request_id', 'rqr.requested_at', 'rqr.introducer_reminder_sent_at',
                'rqr.tl_reminder_sent_at', 'rqr.gl_reminder_sent_at', 'rqr.admin_escalation_sent_at',
                'st.policy_id', 'st.document_reference_number',
                'c.full_name as customer_name', 'c.phone as customer_phone',
                'a.full_name as agent_name', 'a.agent_code'
            )
            ->orderBy('rqr.requested_at')
            // REDUCED 8 Aug 2026 per Chris: strict no-scroll rule — each
            // row is really 2 lines tall (customer+phone, agent+code,
            // date+days-waiting), so 15/page never reliably fit one
            // screen. View also rebuilt to the fixed-height + bottom
            // Prev/Next pattern instead of the default ->links() widget.
            ->paginate(6);

        return view('renewal-quotations.index', compact('requests', 'rolePrefix'));
    }

    public function markSent(Request $request, string $requestId)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $query = DB::table('renewal_quotation_requests')->where('request_id', $requestId);
        if (!$scope->isAdmin()) {
            $query->whereIn('agent_id', $scope->getAgentIds());
        }
        $reqRow = $query->first();

        abort_if(!$reqRow, 404);

        $request->validate([
            'quotation' => ['required', 'file', 'mimes:pdf', 'max:8192'],
        ]);

        $file = $request->file('quotation');
        $filePath = $file->store('renewal-quotations', 'local');

        DB::table('renewal_quotation_documents')->insert([
            'document_id' => Str::uuid()->toString(),
            'request_id'  => $requestId,
            'file_name'   => $file->getClientOriginalName(),
            'file_path'   => $filePath,
            'file_size'   => $file->getSize(),
            'uploaded_by' => $agent->agent_id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('renewal_quotation_requests')->where('request_id', $requestId)->update([
            'status'            => 'QUOTATION_SENT',
            'quotation_sent_at' => now(),
            'quotation_sent_by' => $agent->agent_id,
            'updated_at'        => now(),
        ]);

        return redirect()->route($rolePrefix . '.renewal-quotations.index')->with('success', 'Quotation uploaded — the customer\'s renewal request is now marked as sent.');
    }
}
