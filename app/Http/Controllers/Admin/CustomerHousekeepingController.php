<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 19 Jul 2026 — per Chris: no role can ever delete a customer/
// prospect record directly — they can only set it to Inactive (see
// CustomerController::deactivate). This screen is the other half: an
// Admin-only, manual, per-batch cleanup tool. Admin reviews the list of
// Inactive customers and, for each batch they tick, chooses either:
//   - Soft-Delete: sets is_deleted=true — record disappears from every
//     list/search/report immediately but the row (and its sales
//     history) stays in the DB forever, reversible by a direct DB edit
//     if ever truly needed.
//   - Permanently Delete: a real DB delete. Blocked by the existing
//     sales_transactions.customer_id restrictOnDelete foreign key if
//     that customer has any policy history — caught here and reported
//     back with a friendly message instead of a raw SQL error, telling
//     Admin to use Soft-Delete for those instead.
// No scheduling — Admin must open this screen and click a button each
// time, per Chris's explicit instruction (manual only, not automatic).
class CustomerHousekeepingController extends Controller
{
    public function index()
    {
        $customers = DB::table('customers as c')
            ->join('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
            ->join('agents as a', 'c.owned_by_agent_id', '=', 'a.agent_id')
            ->where('cs.code', 'INACTIVE')
            ->where('c.is_deleted', false)
            ->select(
                'c.customer_id', 'c.full_name', 'c.email', 'c.phone', 'c.updated_at',
                'a.full_name as agent_name', 'a.agent_code',
                DB::raw('(select count(*) from sales_transactions st where st.customer_id = c.customer_id and st.is_deleted = 0) as policy_count')
            )
            ->orderBy('c.updated_at')
            // REDUCED 8 Aug 2026 per Chris: strict no-scroll rule — rows
            // are 2 lines tall (name+contact, agent+code), so 20/page
            // never reliably fit one screen. View rebuilt to the
            // fixed-height + bottom Prev/Next pattern.
            ->paginate(7);

        return view('admin.housekeeping.customers', compact('customers'));
    }

    public function purge(Request $request)
    {
        $request->validate([
            'customer_ids'   => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['string'],
            'action'         => ['required', 'in:soft,hard'],
        ]);

        $agent = auth('agent')->user();
        $ids = $request->customer_ids;

        // Safety net — only ever act on customers that are genuinely
        // Inactive right now, in case the list was stale when submitted.
        $eligibleIds = DB::table('customers as c')
            ->join('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
            ->where('cs.code', 'INACTIVE')
            ->whereIn('c.customer_id', $ids)
            ->pluck('c.customer_id')
            ->toArray();

        if (empty($eligibleIds)) {
            return back()->with('error', 'None of the selected records are still Inactive — nothing was done.');
        }

        if ($request->action === 'soft') {
            DB::table('customers')->whereIn('customer_id', $eligibleIds)->update([
                'is_deleted' => true,
                'updated_by' => $agent->agent_id,
                'updated_at' => now(),
            ]);
            foreach ($eligibleIds as $id) {
                AuditService::logChange('customers', $id, 'DELETE', null, ['note' => 'Soft-deleted via Housekeeping'], $agent->agent_id);
            }
            return back()->with('success', count($eligibleIds) . ' customer(s) soft-deleted. They no longer appear anywhere but remain recoverable in the database.');
        }

        // Hard delete — one at a time so a blocked row (has policy
        // history) doesn't stop the rest of the batch from going through.
        $deleted = [];
        $blocked = [];
        foreach ($eligibleIds as $id) {
            try {
                DB::table('customers')->where('customer_id', $id)->delete();
                $deleted[] = $id;
            } catch (\Illuminate\Database\QueryException $e) {
                $blocked[] = $id;
            }
        }

        foreach ($deleted as $id) {
            AuditService::logChange('customers', $id, 'DELETE', null, ['note' => 'Permanently deleted via Housekeeping'], $agent->agent_id);
        }

        $message = count($deleted) . ' customer(s) permanently deleted.';
        if (!empty($blocked)) {
            $message .= ' ' . count($blocked) . ' record(s) were skipped because they still have sales transaction / policy history — use Soft-Delete for those instead.';
        }

        return back()->with(count($blocked) ? 'error' : 'success', $message);
    }
}
