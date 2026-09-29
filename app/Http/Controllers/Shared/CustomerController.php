<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\DataScopeService;
use App\Services\EspoCrmService;
use App\Services\MalaysianAddressParser;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// REWRITTEN 18 Jul 2026 — Customer Maintenance, shared across Admin/GL/
// TL/Introducer (same pattern as SalesTransactionController, scoped via
// DataScopeService). This file previously existed but was never wired
// into routes/web.php (dead code — used $agent->visibleAgentsQuery(),
// a method that doesn't exist on the current Agent model, and pointed
// at views that were never built). The only real Customer Maintenance
// screen up to now was GL-only (app/Http/Controllers/GL/
// CustomerController.php) — Admin, TL, and Introducer had no way to
// view or manage customer records at all. This replaces both: the
// gl.customers.* routes now point here too, so nothing that already
// linked to those routes breaks.
//
// Design agreed with Chris:
//  - No delete — customers can never be removed from this screen.
//  - Full Name and NRIC are locked for everyone except Admin, since
//    they're the fields used for identity/duplicate-matching integrity
//    (Customer Resolution Service keys off NRIC). Admin changing the
//    NRIC re-encrypts and re-hashes it exactly the way it was first
//    stored, so lookups keep working afterward.
//  - CHANGED 19 Jul 2026 — per Chris: every non-Admin role sees/edits
//    ONLY customers/prospects they personally own — never their
//    downline's — so an upline can never see or interfere with a
//    downline agent's own contacts. Admin still sees everyone.
// -------------------------------------------------------
class CustomerController extends Controller
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
        $isAdmin = $scope->isAdmin();
        // CHANGED 19 Jul 2026 — per Chris: customer/prospect visibility is
        // now locked to the owning agent only for EVERY non-Admin role
        // (see DataScopeService::applyToCustomers — no downline access
        // any more, to stop an upline from seeing/interfering with a
        // downline agent's own contacts). That means the "Owned By" role
        // filter can now only ever show ONE possible value for GL/TL too,
        // not just Introducer — so it's hidden for all non-Admin roles.
        $isSelfScoped = !$isAdmin;

        $hasQuery = $request->filled('search') || $request->filled('state') || $request->filled('area')
            || $request->filled('agent_role') || $request->filled('joined_from') || $request->filled('joined_to')
            || $request->filled('page');

        if (!$hasQuery) {
            $customers = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
            $statesQuery = DB::table('customers')->where('is_deleted', false);
            $scope->applyToCustomers($statesQuery);
            $states = (clone $statesQuery)->whereNotNull('state')->distinct()->orderBy('state')->pluck('state');
            return view('customers.index', compact('customers', 'states', 'hasQuery', 'rolePrefix', 'isAdmin', 'isSelfScoped'));
        }

        $query = DB::table('customers as c')
            ->join('agents as a', 'c.owned_by_agent_id', '=', 'a.agent_id')
            ->leftJoin('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
            ->leftJoin('customer_types as ct', 'c.customer_type_id', '=', 'ct.type_id')
            ->where('c.is_deleted', false);
        $scope->applyToCustomers($query, 'c');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('c.full_name', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('c.phone', 'like', "%{$search}%");
            });
        }
        if ($request->filled('state')) {
            $query->where('c.state', $request->state);
        }
        // NEW 19 Jul 2026 — per Chris: there was no way to search by area
        // (city/address free text) at all, only an exact-match State
        // dropdown. This searches city OR address.
        if ($request->filled('area')) {
            $area = $request->area;
            $query->where(function ($q) use ($area) {
                $q->where('c.city', 'like', "%{$area}%")
                  ->orWhere('c.address', 'like', "%{$area}%");
            });
        }
        if ($request->filled('agent_role')) {
            $query->where('a.role', $request->agent_role);
        }
        // NEW 19 Jul 2026 — per Chris: replaced the old "Joined" sort
        // dropdown (Newest/Oldest/Name A-Z etc.) with a real date-range
        // filter, so an agent can search for customers who joined within
        // a specific window instead of only sorting the full list.
        if ($request->filled('joined_from')) {
            $query->whereDate('c.created_at', '>=', $request->joined_from);
        }
        if ($request->filled('joined_to')) {
            $query->whereDate('c.created_at', '<=', $request->joined_to);
        }

        $customers = $query->orderByDesc('c.created_at')
            ->select(
                'c.customer_id', 'c.full_name', 'c.email', 'c.phone', 'c.city', 'c.state', 'c.created_at',
                'cs.code as status_code', 'cs.description as status_description',
                'ct.description as customer_type_description',
                'a.full_name as agent_name', 'a.agent_code', 'a.role as agent_role'
            )
            ->paginate(10)->appends($request->query());

        $statesQuery = DB::table('customers')->where('is_deleted', false);
        $scope->applyToCustomers($statesQuery);
        $states = (clone $statesQuery)->whereNotNull('state')->distinct()->orderBy('state')->pluck('state');

        return view('customers.index', compact('customers', 'states', 'hasQuery', 'rolePrefix', 'isAdmin', 'isSelfScoped'));
    }

    /**
     * NEW 19 Jul 2026 — per Chris: live type-ahead on the Customer list
     * search box, scoped exactly like the list itself (unlike the
     * unscoped one used on the Sales Transaction create form, which
     * intentionally searches everyone since an agent may be attaching a
     * policy to any existing customer). Picking a suggestion jumps
     * straight to that customer's Detail screen.
     */
    public function typeahead(Request $request)
    {
        $scope = new DataScopeService();
        $term = trim((string) $request->get('term'));
        if ($term === '') {
            return response()->json([]);
        }

        $query = DB::table('customers')->where('is_deleted', false);
        $scope->applyToCustomers($query);
        $query->where(function ($q) use ($term) {
            $q->where('full_name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });

        $results = $query->select('customer_id', 'full_name', 'phone', 'email')->orderBy('full_name')->limit(10)->get();

        return response()->json($results);
    }

    public function show(string $customerId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);
        $isAdmin = $scope->isAdmin();

        $query = DB::table('customers as c')
            ->join('agents as a', 'c.owned_by_agent_id', '=', 'a.agent_id')
            ->leftJoin('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
            ->leftJoin('customer_types as ct', 'c.customer_type_id', '=', 'ct.type_id')
            ->leftJoin('customer_categories as cc', 'c.customer_category_id', '=', 'cc.category_id')
            ->leftJoin('occupation_groups as og', 'c.occupation_group_id', '=', 'og.occupation_group_id')
            ->leftJoin('customer_sources as csrc', 'c.source_id', '=', 'csrc.source_id')
            ->where('c.customer_id', $customerId)
            ->where('c.is_deleted', false);
        $scope->applyToCustomers($query, 'c');

        $customer = $query->select(
            'c.*', 'a.full_name as agent_name', 'a.agent_code', 'a.role as agent_role',
            'cs.code as status_code', 'cs.description as status_description',
            'ct.description as customer_type_description',
            'cc.description as category_description',
            'og.description as occupation_group_description',
            'csrc.description as source_description'
        )->first();

        abort_if(!$customer, 404);

        // Every transaction (not just current page) — used both by the
        // Sales KPI Dashboard tab and as the base data for the Sales
        // History table below.
        $allTransactions = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('st.customer_id', $customerId)
            ->where('st.is_deleted', false)
            ->select('st.status', 'st.premium_amount', 'st.coverage_end', 'st.created_at', 'p.product_name')
            ->get();

        // NEW 19 Jul 2026 — Renewal Reminder sub-tab: the automatic,
        // system-generated per-policy reminder captured at Sales
        // Transaction submission (task #94), same content the agent
        // already previewed on the create screen. Read-only, unpaginated
        // since a customer rarely has more than a handful of policies —
        // separate from the agent's own personal Follow Up Reminders.
        $renewalReminders = DB::table('sales_transactions as st')
            ->join('insurance_renewal_schedules as irs', 'st.policy_id', '=', 'irs.policy_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->where('st.customer_id', $customerId)
            ->where('st.is_deleted', false)
            ->whereNotNull('irs.reminder_message')
            ->select(
                'st.policy_id', 'st.document_reference_number', 'p.product_name', 'v.vendor_name',
                'irs.reminder_scheduled_date', 'irs.reminder_message', 'irs.reminder_sent_at', 'irs.coverage_end'
            )
            ->orderBy('irs.reminder_scheduled_date')
            ->get();

        // PAGINATED 19 Jul 2026 — per Chris: this previously loaded every
        // transaction into one internally-scrolling box, which hides how
        // many records exist and how to see the rest. Switched to real
        // pagination, matching the same Prev/Next convention already used
        // on the Sales Transaction Maintenance list and ~20 other screens.
        $transactions = DB::table('sales_transactions as st')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('agents as ta', 'st.agent_id', '=', 'ta.agent_id')
            ->where('st.customer_id', $customerId)
            ->where('st.is_deleted', false)
            ->select(
                'st.policy_id', 'st.document_reference_number', 'st.premium_amount', 'st.sum_insured',
                'st.coverage_start', 'st.coverage_end', 'st.renewal_date', 'st.status', 'st.created_at',
                'p.product_name', 'p.product_type', 'v.vendor_name', 'v.industry as vendor_industry',
                'ta.full_name as submitted_by_name', 'ta.agent_code as submitted_by_code'
            )
            // NEW 26 Jul 2026 — per Chris: Sales History row should show 2
            // amount columns, Sales Amount and Earning Income. Earning
            // Income here is THIS logged-in agent's own commission_amount
            // for that policy (not the whole pool — Admin/GL/TL/Introducer
            // each have their own tier row, or none at all if they had no
            // entitlement on that particular sale), same non-reversed rule
            // used everywhere else (CustomerKpiController etc: status !=
            // REVERSED). A correlated subquery (not a join) so a policy
            // can never fan out into duplicate rows here.
            ->addSelect(['earning_income' => DB::table('commission_transactions as ct')
                ->selectRaw('SUM(ct.commission_amount)')
                ->whereColumn('ct.policy_id', 'st.policy_id')
                ->where('ct.agent_id', $agent->agent_id)
                ->where('ct.status', '!=', 'REVERSED')
            ])
            ->orderByDesc('st.created_at')
            ->paginate(10)
            ->withQueryString();

        // NEW 19 Jul 2026 — per Chris: Sales History should show the same
        // full "Policy / Sale Details" card used on the dedicated Sales
        // Transaction View screen, not just a compact row. Fetched in
        // bulk for the current page of policies (max 8) rather than one
        // query per row.
        $pagePolicyIds = $transactions->pluck('policy_id')->all();
        $renewalsByPolicy = DB::table('insurance_renewal_schedules')
            ->whereIn('policy_id', $pagePolicyIds)
            ->get()
            ->keyBy('policy_id');
        $attributesByPolicy = DB::table('sales_transaction_attributes')
            ->whereIn('policy_id', $pagePolicyIds)
            ->get()
            ->groupBy('policy_id')
            ->map(fn($rows) => $rows->pluck('attribute_value', 'attribute_name'));

        // NEW 19 Jul 2026 — per Chris: personal follow-up reminders are
        // private to whoever set them (not scope-shared like the
        // customer record itself), so only the logged-in agent's own
        // reminders against this customer are shown here.
        $reminders = DB::table('personal_reminders')
            ->where('customer_id', $customerId)
            ->where('agent_id', $agent->agent_id)
            ->where('is_deleted', false)
            ->orderByRaw("status = 'PENDING' desc")
            ->orderBy('reminder_date')
            ->get();

        // NEW 29 Jul 2026 — Support Tickets tab (task #259). Unlike
        // personal_reminders, tickets aren't private to one agent — any
        // agent within scope of this customer can see all of them (same
        // "shared visibility on the customer record" rule as Sales
        // History), since a complaint/service request matters to
        // whoever ends up handling this customer next.
        $tickets = DB::table('customer_support_tickets')
            ->where('customer_id', $customerId)
            ->where('is_deleted', false)
            ->orderByRaw("status = 'CLOSED' asc")
            ->orderByDesc('created_at')
            ->get();

        // NEW 19 Jul 2026 — Activity Log History tab. Only records CREATE/
        // UPDATE actions made from now on (AuditService wired into
        // create/update/Prospect-conversion) — no retroactive history for
        // customers that already existed before this was added.
        $activityLog = DB::table('audit_logs as al')
            ->leftJoin('agents as ag', 'al.agent_id', '=', 'ag.agent_id')
            ->where('al.table_name', 'customers')
            ->where('al.record_id', $customerId)
            ->orderByDesc('al.created_at')
            ->select('al.action', 'al.before_value', 'al.after_value', 'al.created_at', 'ag.full_name as changed_by')
            ->limit(50)
            ->get();

        // NEW 29 Jul 2026 — Customer Timeline (task #263), the last item
        // from the EspoCRM Feature Reuse Review that hadn't been wired
        // up yet. Read-only, supplementary — shown alongside (not merged
        // into) the Activity Log above, since it's a different system's
        // own record of activity (Stream), not GeneralLink's.
        $espoTimeline = !empty($customer->espocrm_contact_id) ? $espoCrm->listContactStream($customer->espocrm_contact_id) : [];

        // NEW 19 Jul 2026 — per Chris: the old 5-stat-box summary "means
        // nothing" once a customer only has 1 transaction (Active/Pending
        // Renewal/Lapsed are all trivially 0). Replaced with a proper
        // Sales KPI Dashboard tab: a few genuinely useful numbers plus 2
        // donut charts (premium mix by product, policy status mix) that
        // still render sensibly at n=1 and get more useful as more
        // policies are added.
        $kpiTotalPremium = $allTransactions->sum('premium_amount');
        $kpiTotalPolicies = $allTransactions->count();
        $kpiAvgPremium = $kpiTotalPolicies > 0 ? $kpiTotalPremium / $kpiTotalPolicies : 0;
        $kpiCustomerSince = \Illuminate\Support\Carbon::parse($customer->created_at)->diffForHumans(null, true);
        $kpiNextRenewal = $allTransactions->pluck('coverage_end')->filter()->map(fn($d) => \Illuminate\Support\Carbon::parse($d))->filter(fn($d) => $d->isFuture())->sort()->first();
        // FIXED 19 Jul 2026 — per Chris: was calling ->diffInDays(now())
        // directly in the blade, which on this Carbon version returns a
        // signed FLOAT (e.g. "-263.50421576661"), not a clean whole
        // number of days. Computed here instead via raw Unix timestamp
        // subtraction — same safe convention already used in
        // RenewalForecastController — which always gives a plain
        // positive integer since $kpiNextRenewal is already filtered to
        // future dates only above.
        $kpiNextRenewalDays = $kpiNextRenewal ? (int) round(($kpiNextRenewal->timestamp - now()->timestamp) / 86400) : null;

        $kpiByProduct = $allTransactions->groupBy('product_name')->map(fn($grp) => (float) $grp->sum('premium_amount'));
        $kpiByStatus = $allTransactions->groupBy('status')->map->count();

        return view('customers.show', compact(
            'customer', 'transactions', 'allTransactions', 'reminders', 'activityLog', 'rolePrefix', 'isAdmin',
            'kpiTotalPremium', 'kpiTotalPolicies', 'kpiAvgPremium', 'kpiCustomerSince', 'kpiNextRenewal', 'kpiNextRenewalDays', 'kpiByProduct', 'kpiByStatus',
            'renewalsByPolicy', 'attributesByPolicy', 'renewalReminders', 'tickets', 'espoTimeline'
        ));
    }

    public function edit(string $customerId)
    {
        $scope = new DataScopeService();
        $agent = auth('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);
        $isAdmin = $scope->isAdmin();

        $query = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false);
        $scope->applyToCustomers($query);
        $customer = $query->first();

        abort_if(!$customer, 404);

        // Single source of truth — also used by MalaysianAddressParser
        // when splitting an address read off a calibrated document, so
        // the dropdown here and the automated parser never drift apart.
        $states = MalaysianAddressParser::STATES;

        // CHANGED 19 Jul 2026 — per Chris: Classification (Status, Type,
        // Category, Occupation Group, Source) is now editable by whoever
        // owns/sees this record — not Admin-only any more. Only Full
        // Name and NRIC stay Admin-locked (identity/duplicate-matching
        // integrity — see update()). Loaded for every role now.
        $statuses = DB::table('customer_statuses')->where('is_active', true)->orderBy('description')->get();
        $customerTypes = DB::table('customer_types')->where('is_active', true)->orderBy('description')->get();
        $categories = DB::table('customer_categories')->where('is_active', true)->orderBy('description')->get();
        $occupationGroups = DB::table('occupation_groups')->where('is_active', true)->orderBy('description')->get();
        $sources = DB::table('customer_sources')->where('is_active', true)->orderBy('description')->get();

        return view('customers.edit', compact('customer', 'rolePrefix', 'isAdmin', 'states', 'statuses', 'customerTypes', 'categories', 'occupationGroups', 'sources'));
    }

    public function update(Request $request, string $customerId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $query = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false);
        $scope->applyToCustomers($query);
        $customer = $query->first();

        abort_if(!$customer, 404);

        $rules = [
            'email'    => ['nullable', 'email', 'max:200'],
            'phone'    => ['required', 'string', PhoneNumberService::rule()],
            'address'  => ['nullable', 'string'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'city'     => ['nullable', 'string', 'max:100'],
            'state'    => ['nullable', 'string', 'max:100'],
            // CHANGED 19 Jul 2026 — per Chris: Classification (Status,
            // Type, Category, Occupation Group, Source) is now editable
            // by whoever owns/sees this record, not Admin-only — moved
            // out of the isAdmin() block below. Only Full Name/NRIC stay
            // Admin-locked (identity/duplicate-matching integrity).
            'status_id'             => ['required', 'exists:customer_statuses,status_id'],
            'customer_type_id'      => ['nullable', 'exists:customer_types,type_id'],
            'customer_category_id'  => ['nullable', 'exists:customer_categories,category_id'],
            'occupation_group_id'   => ['nullable', 'exists:occupation_groups,occupation_group_id'],
            'source_id'             => ['nullable', 'exists:customer_sources,source_id'],
        ];

        $update = [
            'email'                  => $request->email,
            'phone'                  => PhoneNumberService::normalize($request->phone),
            'address'                => $request->address,
            'postcode'               => $request->postcode,
            'city'                   => $request->city,
            'state'                  => $request->state,
            'status_id'              => $request->status_id,
            'customer_type_id'       => $request->customer_type_id ?: null,
            'customer_category_id'   => $request->customer_category_id ?: null,
            'occupation_group_id'    => $request->occupation_group_id ?: null,
            'source_id'              => $request->source_id ?: null,
            'updated_by'             => $agent->agent_id,
            'updated_at'             => now(),
        ];

        // Full Name and NRIC stay Admin only. Changing the NRIC
        // re-encrypts and re-hashes it exactly like it was first stored,
        // so future NRIC lookups (Customer Resolution Service) keep
        // working.
        if ($scope->isAdmin()) {
            $rules['full_name'] = ['required', 'string', 'max:200'];
            $rules['nric'] = ['nullable', 'string', 'max:20'];

            $update['full_name'] = $request->full_name;

            if ($request->filled('nric')) {
                $nricHash = hash('sha256', $request->nric);
                $existing = DB::table('customers')
                    ->where('nric_hash', $nricHash)
                    ->where('customer_id', '!=', $customerId)
                    ->where('is_deleted', false)
                    ->exists();
                if ($existing) {
                    return back()->withInput()->withErrors(['nric' => 'This NRIC already belongs to another customer record.']);
                }
                $update['nric_encrypted'] = encrypt($request->nric);
                $update['nric_hash'] = $nricHash;
            }
        }

        $request->validate($rules);

        DB::table('customers')->where('customer_id', $customerId)->update($update);

        // NEW 19 Jul 2026 — feeds the Activity Log History tab on the
        // Customer Detail screen.
        AuditService::logChange('customers', $customerId, 'UPDATE', (array) $customer, $update, $agent->agent_id);

        // NEW 29 Jul 2026 — EspoCRM integration (task #252). Keep the
        // mirrored Contact's details current. If this customer somehow
        // doesn't have a Contact yet (e.g. was created before this sync
        // existed), create one now instead of silently doing nothing.
        $freshCustomer = DB::table('customers')->where('customer_id', $customerId)->first();
        if (!empty($customer->espocrm_contact_id)) {
            $espoCrm->updateContact($customer->espocrm_contact_id, $freshCustomer);
        } else {
            $newContactId = $espoCrm->createContact($freshCustomer);
            if ($newContactId) {
                DB::table('customers')->where('customer_id', $customerId)->update(['espocrm_contact_id' => $newContactId]);
            }
        }

        return redirect()->route($rolePrefix . '.customers.show', $customerId)->with('success', 'Customer updated successfully.');
    }

    // -------------------------------------------------------
    // DEACTIVATE — NEW 19 Jul 2026, per Chris: nobody can delete a
    // customer/prospect record, full stop. This is the replacement
    // action — any agent (within their own scope, i.e. customers/
    // prospects they personally own) can set a record's status to
    // Inactive themselves, no Admin approval needed. Admin can do this
    // for any customer too. Reactivating (or any other status change)
    // stays Admin-only via the existing Edit screen. A separate Admin-
    // only Housekeeping screen is where Inactive records eventually get
    // permanently purged.
    // -------------------------------------------------------
    public function deactivate(string $customerId)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        $query = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false);
        $scope->applyToCustomers($query);
        $customer = $query->first();

        abort_if(!$customer, 404);

        $inactiveStatusId = DB::table('customer_statuses')->where('code', 'INACTIVE')->value('status_id');
        abort_if(!$inactiveStatusId, 500, 'INACTIVE status is missing from customer_statuses — contact Admin.');

        if ($customer->status_id === $inactiveStatusId) {
            return redirect()->route($rolePrefix . '.customers.show', $customerId)->with('success', 'This customer is already Inactive.');
        }

        DB::table('customers')->where('customer_id', $customerId)->update([
            'status_id'  => $inactiveStatusId,
            'updated_by' => $agent->agent_id,
            'updated_at' => now(),
        ]);

        AuditService::logChange('customers', $customerId, 'UPDATE', ['status_id' => $customer->status_id], ['status_id' => $inactiveStatusId, 'note' => 'Set Inactive'], $agent->agent_id);

        return redirect()->route($rolePrefix . '.customers.show', $customerId)->with('success', 'Customer set to Inactive.');
    }

    // -------------------------------------------------------
    // PROSPECT — NEW 19 Jul 2026, per Chris: an agent's own personal
    // contact they're trying to close, before any policy exists. No
    // NRIC required (customers.nric_encrypted/nric_hash made nullable
    // for this reason). Owned by whoever creates it, same ownership
    // rule as a real customer. Converts to a regular CUSTOMER-type
    // record automatically the moment a real Sales Transaction is
    // submitted against them (CustomerResolutionService keys off NRIC
    // once it's captured at that point) — no separate "convert" step
    // needed here.
    // -------------------------------------------------------
    public function createProspect()
    {
        $agent = auth('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);
        $states = MalaysianAddressParser::STATES;

        return view('customers.create-prospect', compact('rolePrefix', 'states'));
    }

    public function storeProspect(Request $request, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'phone'     => ['required', 'string', PhoneNumberService::rule()],
            'email'     => ['nullable', 'email', 'max:200'],
            'address'   => ['nullable', 'string'],
            'postcode'  => ['nullable', 'string', 'max:10'],
            'city'      => ['nullable', 'string', 'max:100'],
            'state'     => ['nullable', 'string', 'max:100'],
        ]);

        // NEW 19 Jul 2026 — looked up by code, not hardcoded, since
        // customer_statuses is now a user-configurable table.
        $prospectStatusId = DB::table('customer_statuses')->where('code', 'PROSPECT')->value('status_id');
        abort_if(!$prospectStatusId, 500, 'PROSPECT status is missing from customer_statuses — contact Admin.');

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'customer_id'       => $customerId,
            'status_id'         => $prospectStatusId,
            'nric_encrypted'    => null,
            'nric_hash'         => null,
            'full_name'         => $validated['full_name'],
            'phone'             => PhoneNumberService::normalize($validated['phone']),
            'email'             => $validated['email'] ?? null,
            'address'           => $validated['address'] ?? null,
            'postcode'          => $validated['postcode'] ?? null,
            'city'              => $validated['city'] ?? null,
            'state'             => $validated['state'] ?? null,
            'owned_by_agent_id' => $agent->agent_id,
            'is_deleted'        => false,
            'created_by'        => $agent->agent_id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        AuditService::logChange('customers', $customerId, 'CREATE', null, ['full_name' => $validated['full_name'], 'status' => 'PROSPECT'], $agent->agent_id);

        // NEW 29 Jul 2026 — EspoCRM integration (task #252).
        $newCustomer = DB::table('customers')->where('customer_id', $customerId)->first();
        $contactId = $espoCrm->createContact($newCustomer);
        if ($contactId) {
            DB::table('customers')->where('customer_id', $customerId)->update(['espocrm_contact_id' => $contactId]);
        }

        return redirect()->route($rolePrefix . '.customers.show', $customerId)->with('success', 'Prospect added. Set a personal follow-up reminder below to help you remember to call them back.');
    }
}
