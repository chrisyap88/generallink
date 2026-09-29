<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 13 Sep 2026 (Task #418) — CBE Marketplace, Phase 3: Listings +
// Orders. Same group->node picker fallback as Member/Donor/Entity
// Maintenance (AdminCbeMembersController etc.) — an officer lands
// straight on their own entity, platform Admin picks one. A listing's
// seller is either an approved vendor (must have an ACTIVE row in
// cbe_vendor_node_links for this exact node — the 4-eye-approved pick
// list from Phase 2) or the entity itself (vendor_id null, per Chris:
// "CBE entity itself can sell things like events talks, roadshow,
// prayer package etc").
class CbeMarketplaceController extends Controller
{
    private function currentOfficerNodeId(): ?string
    {
        $agent = Auth::guard('agent')->user();
        if (! $agent || $agent->role === 'ADMIN') {
            return null;
        }

        return DB::table('cbe_node_officers')
            ->where('agent_id', $agent->agent_id)
            ->where('is_active', true)
            ->value('node_id');
    }

    // Shared node resolution — returns the node object, or null (and
    // renders the group->node picker) when none is yet chosen. Mirrors
    // AdminCbeMembersController::index()'s own fallback exactly.
    private function resolveNode(Request $request, string $pickerRoute, string $pickerTitle)
    {
        $officerNodeId = $this->currentOfficerNodeId();
        $nodeId = $officerNodeId ?: $request->get('node');

        if ($nodeId) {
            $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
            if ($node) {
                return $node;
            }
        }

        $groupId = $request->get('group');
        $groups = DB::table('group_labels')->where('group_type', 'CBE')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $group = null;
        $nodes = collect();

        if ($groupId) {
            $group = DB::table('group_labels')->where('group_label_id', $groupId)->where('group_type', 'CBE')->first();
            if ($group) {
                $nodes = DB::table('cbe_hierarchy_nodes as n')
                    ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                    ->where('n.group_label_id', $groupId)
                    ->orderBy('l.level_order')->orderBy('n.node_name')
                    ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'l.level_name')
                    ->get();
            }
        }

        return response()->view('admin.cbe-kpi.node-picker', [
            'groups' => $groups, 'group' => $group, 'nodes' => $nodes,
            'pickerRoute' => $pickerRoute, 'pickerTitle' => $pickerTitle,
        ]);
    }

    // Listings — an entity's marketplace catalog (its own items, plus
    // any approved vendor's items).
    public function listings(Request $request)
    {
        $node = $this->resolveNode($request, 'admin.masterfile.cbe-marketplace-listings', __('cbe_marketplace.listings_page_title'));
        if (! is_object($node) || ! isset($node->node_id)) {
            return $node;
        }

        $listings = DB::table('cbe_marketplace_listings as li')
            ->leftJoin('cbe_vendors as v', 'v.vendor_id', '=', 'li.vendor_id')
            ->where('li.cbe_node_id', $node->node_id)
            ->orderByDesc('li.created_at')
            ->select('li.listing_id', 'li.title', 'li.price', 'li.stock_quantity', 'li.status', 'v.vendor_name')
            ->get();

        $approvedVendors = DB::table('cbe_vendor_node_links as l')
            ->join('cbe_vendors as v', 'v.vendor_id', '=', 'l.vendor_id')
            ->where('l.cbe_node_id', $node->node_id)
            ->where('l.status', 'ACTIVE')
            ->orderBy('v.vendor_name')
            ->select('v.vendor_id', 'v.vendor_name')
            ->get();

        return view('masterfile.cbe-marketplace-listings', compact('node', 'listings', 'approvedVendors'));
    }

    public function storeListing(Request $request, string $nodeId)
    {
        $request->validate([
            'vendor_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
            'title_zh' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        // A vendor can only list under a node they are ACTIVE for — the
        // exact protection Chris asked for ("Taoist prayer cannot sell
        // to Christian group"), enforced here at write time too, not
        // just by what the dropdown shows.
        if ($request->vendor_id) {
            $approved = DB::table('cbe_vendor_node_links')
                ->where('vendor_id', $request->vendor_id)
                ->where('cbe_node_id', $nodeId)
                ->where('status', 'ACTIVE')
                ->exists();
            abort_unless($approved, 403, 'This vendor is not approved to sell into this entity.');
        }

        DB::table('cbe_marketplace_listings')->insert([
            'listing_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'vendor_id' => $request->vendor_id ?: null,
            'title' => $request->title,
            'title_zh' => $request->title_zh,
            'description' => $request->description,
            'price' => $request->price,
            'stock_quantity' => $request->stock_quantity,
            'status' => 'ACTIVE',
            'created_by' => Auth::guard('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.cbe-marketplace-listings', ['node' => $nodeId])->with('success', __('cbe_marketplace.listing_created_flash'));
    }

    public function toggleListing(Request $request, string $listingId)
    {
        $listing = DB::table('cbe_marketplace_listings')->where('listing_id', $listingId)->first();
        abort_if(! $listing, 404);

        DB::table('cbe_marketplace_listings')->where('listing_id', $listingId)->update([
            'status' => $listing->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.cbe-marketplace-listings', ['node' => $listing->cbe_node_id]);
    }

    // Orders — record a purchase against a listing. Buyer can be an
    // existing cbe_customers record (typeahead, same pattern as every
    // other customer search in the app) OR a first-time walk-in with
    // just name/phone/email — per Chris's own "Chris Yap is a customer
    // of Rotary Club" example, no membership or even a prior Customer
    // Master record is required to buy something.
    public function orders(Request $request)
    {
        $node = $this->resolveNode($request, 'admin.masterfile.cbe-marketplace-orders', __('cbe_marketplace.orders_page_title'));
        if (! is_object($node) || ! isset($node->node_id)) {
            return $node;
        }

        $orders = DB::table('cbe_marketplace_orders as o')
            ->join('cbe_marketplace_listings as li', 'li.listing_id', '=', 'o.listing_id')
            ->leftJoin('cbe_customers as c', 'c.customer_id', '=', 'o.buyer_customer_id')
            ->where('o.cbe_node_id', $node->node_id)
            ->orderByDesc('o.created_at')
            ->select('o.order_id', 'li.title', 'o.quantity', 'o.total_amount', 'o.payment_status', 'o.buyer_name', 'o.buyer_phone', 'c.customer_name')
            ->get();

        $activeListings = DB::table('cbe_marketplace_listings')
            ->where('cbe_node_id', $node->node_id)
            ->where('status', 'ACTIVE')
            ->orderBy('title')
            ->get(['listing_id', 'title', 'price']);

        return view('masterfile.cbe-marketplace-orders', compact('node', 'orders', 'activeListings'));
    }

    public function storeOrder(Request $request, string $nodeId)
    {
        $request->validate([
            'listing_id' => ['required', 'uuid'],
            'buyer_customer_id' => ['nullable', 'uuid'],
            'buyer_name' => ['nullable', 'string', 'max:150'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'buyer_email' => ['nullable', 'email', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! $request->buyer_customer_id && ! $request->buyer_name) {
            return back()->withErrors(['buyer' => __('cbe_marketplace.err_buyer_required')])->withInput();
        }

        $listing = DB::table('cbe_marketplace_listings')->where('listing_id', $request->listing_id)->where('cbe_node_id', $nodeId)->first();
        abort_if(! $listing, 404);

        DB::table('cbe_marketplace_orders')->insert([
            'order_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'listing_id' => $request->listing_id,
            'buyer_customer_id' => $request->buyer_customer_id ?: null,
            'buyer_name' => $request->buyer_name,
            'buyer_phone' => $request->buyer_phone,
            'buyer_email' => $request->buyer_email,
            'quantity' => $request->quantity,
            'unit_price' => $listing->price,
            'total_amount' => $listing->price * $request->quantity,
            'payment_status' => 'PENDING_PAYMENT',
            'recorded_by' => Auth::guard('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.cbe-marketplace-orders', ['node' => $nodeId])->with('success', __('cbe_marketplace.order_recorded_flash'));
    }

    // Customer typeahead for the "existing buyer" search on the order
    // form — same shape as AdminCbeCustomersController::typeahead().
    public function customerTypeahead(Request $request, string $nodeId)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $results = DB::table('cbe_customers')
            ->where('cbe_node_id', $nodeId)
            ->where(function ($w) use ($q) {
                $w->where('customer_name', 'like', '%' . $q . '%')->orWhere('phone', 'like', '%' . $q . '%');
            })
            ->limit(10)
            ->get(['customer_id', 'customer_name', 'phone'])
            ->map(fn ($r) => ['customer_id' => $r->customer_id, 'label' => $r->customer_name . ' (' . $r->phone . ')']);

        return response()->json($results);
    }

    // Campaigns — Phase 4. Type comes from the editable
    // cbe_marketplace_campaign_types catalog (never a hardcoded PHP
    // list, per the standing "no hardcoding" rule), seeded with
    // "Membership Recruitment Initiative" — Chris's own design for
    // approaching a non-member marketplace customer (public buyer)
    // about joining as a member, e.g. "Chris Yap buys from Temple A as
    // a customer, not a member — Temple A's team can target him with a
    // Membership Recruitment campaign" — without any automatic
    // cross-entity "already a member elsewhere" flag, which Chris
    // explicitly said not to build.
    public function campaigns(Request $request)
    {
        $node = $this->resolveNode($request, 'admin.masterfile.cbe-marketplace-campaigns', __('cbe_marketplace.campaigns_page_title'));
        if (! is_object($node) || ! isset($node->node_id)) {
            return $node;
        }

        $campaigns = DB::table('cbe_marketplace_campaigns as c')
            ->join('cbe_marketplace_campaign_types as t', 't.type_id', '=', 'c.type_id')
            ->where('c.cbe_node_id', $node->node_id)
            ->orderByDesc('c.created_at')
            ->select('c.campaign_id', 'c.campaign_name', 'c.start_date', 'c.end_date', 'c.status', 't.type_name')
            ->get();

        $campaignTypes = DB::table('cbe_marketplace_campaign_types')
            ->where('is_active', true)
            ->orderBy('type_name')
            ->get(['type_id', 'type_name']);

        return view('masterfile.cbe-marketplace-campaigns', compact('node', 'campaigns', 'campaignTypes'));
    }

    public function storeCampaign(Request $request, string $nodeId)
    {
        $request->validate([
            'type_id' => ['required', 'uuid'],
            'campaign_name' => ['required', 'string', 'max:200'],
            'message' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        DB::table('cbe_marketplace_campaigns')->insert([
            'campaign_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'type_id' => $request->type_id,
            'campaign_name' => $request->campaign_name,
            'message' => $request->message,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'DRAFT',
            'created_by' => Auth::guard('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.cbe-marketplace-campaigns', ['node' => $nodeId])->with('success', __('cbe_marketplace.campaign_created_flash'));
    }

    // Cycles a campaign forward one step: DRAFT -> ACTIVE -> ENDED. A
    // simple one-button "Next Stage" action rather than separate
    // Activate/End buttons — matches the fixed-grid row layout used
    // everywhere else in this house style.
    public function advanceCampaign(Request $request, string $campaignId)
    {
        $campaign = DB::table('cbe_marketplace_campaigns')->where('campaign_id', $campaignId)->first();
        abort_if(! $campaign, 404);

        $next = match ($campaign->status) {
            'DRAFT' => 'ACTIVE',
            'ACTIVE' => 'ENDED',
            default => $campaign->status,
        };

        DB::table('cbe_marketplace_campaigns')->where('campaign_id', $campaignId)->update([
            'status' => $next,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.cbe-marketplace-campaigns', ['node' => $campaign->cbe_node_id]);
    }
}
