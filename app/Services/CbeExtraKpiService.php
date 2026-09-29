<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 18 Sep 2026 — per Chris: "ALL" KPI must be under Executive KPI
// Dashboard, and he explicitly confirmed Vendor KPI, Customer KPI, and
// "all future kpi develop for cbe group" belong here too — plus
// Communication KPI (from the internal messaging engine, since CBE's
// own Notice Board has no delivery/read tracking to report on, unlike
// the platform-wide one GladeAnalyticsController reads). ONE shared
// place for these 3 new categories so both the platform Admin's
// Executive KPI Dashboard (AdminCbeKpiController) and a CBE officer's
// own Executive KPI Dashboard (CbeExecDashboardController) show
// identical numbers — never computed twice, two different ways.
class CbeExtraKpiService
{
    public static function communication(array $nodeIds, string $monthStart, string $asOfDate): array
    {
        $threadsQuery = DB::table('cbe_message_threads')->whereIn('cbe_node_id', $nodeIds);

        return [
            'total_threads' => (clone $threadsQuery)->count(),
            'open' => (clone $threadsQuery)->where('status', 'OPEN')->count(),
            'outstanding' => (clone $threadsQuery)->where('status', 'OUTSTANDING')->count(),
            'escalated' => (clone $threadsQuery)->where('status', 'ESCALATED')->count(),
            'resolved' => (clone $threadsQuery)->where('status', 'RESOLVED')->count(),
            'notices_this_month' => DB::table('cbe_temple_notices')->whereIn('cbe_node_id', $nodeIds)
                ->where('is_deleted', false)->where('created_at', '>=', $monthStart)->count(),
            'notices_active' => DB::table('cbe_temple_notices')->whereIn('cbe_node_id', $nodeIds)
                ->where('is_deleted', false)
                ->where(function ($q) use ($asOfDate) { $q->whereNull('expires_at')->orWhere('expires_at', '>=', $asOfDate); })
                ->count(),
        ];
    }

    public static function vendorMarketplace(array $nodeIds, string $monthStart, string $asOfDate): array
    {
        // FIXED 19 Sep 2026 — this was querying status='APPROVED', which
        // does not exist on cbe_vendor_node_links (the real enum is
        // PENDING_APPROVAL / ACTIVE / REJECTED — see that table's own
        // migration), so "Approved Vendors" always silently showed 0.
        $vendorLinksQuery = DB::table('cbe_vendor_node_links')->whereIn('cbe_node_id', $nodeIds);
        $approvedVendors = (clone $vendorLinksQuery)->where('status', 'ACTIVE')->distinct('vendor_id')->count('vendor_id');

        $listingsQuery = DB::table('cbe_marketplace_listings')->whereIn('cbe_node_id', $nodeIds);
        $activeListings = (clone $listingsQuery)->where('status', 'ACTIVE')->count();

        $ordersQuery = DB::table('cbe_marketplace_orders')->whereIn('cbe_node_id', $nodeIds);

        // ADDED 19 Sep 2026 — per Chris: "follow the generallink kpi
        // screen" (admin/vendor-kpi/index.blade.php) — same 3-section
        // shape: overview tiles, a 3-column status-breakdown row, and a
        // 2-column breakdown/top-N row. Built from what this domain
        // actually has (vendor link approval status, listing status,
        // order payment status, listing source, top listings by sales)
        // rather than copying fields that don't exist here (there's no
        // document-verification or AI-risk step for CBE vendors, no
        // "entity type" on a listing).
        $vendorLinkStatus = [
            'pending' => (clone $vendorLinksQuery)->where('status', 'PENDING_APPROVAL')->count(),
            'active' => (clone $vendorLinksQuery)->where('status', 'ACTIVE')->count(),
            'rejected' => (clone $vendorLinksQuery)->where('status', 'REJECTED')->count(),
        ];

        $listingStatus = [
            'active' => (clone $listingsQuery)->where('status', 'ACTIVE')->count(),
            'inactive' => (clone $listingsQuery)->where('status', 'INACTIVE')->count(),
        ];

        $orderStatus = [
            'pending' => (clone $ordersQuery)->where('payment_status', 'PENDING_PAYMENT')->count(),
            'paid' => (clone $ordersQuery)->where('payment_status', 'PAID')->count(),
            'cancelled' => (clone $ordersQuery)->where('payment_status', 'CANCELLED')->count(),
        ];

        $listingsBySource = [
            'entity_own' => (clone $listingsQuery)->whereNull('vendor_id')->count(),
            'vendor_supplied' => (clone $listingsQuery)->whereNotNull('vendor_id')->count(),
        ];

        $top5Listings = DB::table('cbe_marketplace_orders as o')
            ->join('cbe_marketplace_listings as l', 'l.listing_id', '=', 'o.listing_id')
            ->whereIn('o.cbe_node_id', $nodeIds)
            ->where('o.payment_status', 'PAID')
            ->select('l.listing_id', 'l.title', DB::raw('count(o.order_id) as order_count'), DB::raw('sum(o.total_amount) as total'))
            ->groupBy('l.listing_id', 'l.title')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'approved_vendors' => $approvedVendors,
            'active_listings' => $activeListings,
            'orders_pending' => (clone $ordersQuery)->where('payment_status', 'PENDING_PAYMENT')->count(),
            'orders_paid_this_month' => (clone $ordersQuery)->where('payment_status', 'PAID')->where('updated_at', '>=', $monthStart)->count(),
            'revenue_paid_ytd' => (float) (clone $ordersQuery)->where('payment_status', 'PAID')->sum('total_amount'),
            'vendor_link_status' => $vendorLinkStatus,
            'listing_status' => $listingStatus,
            'order_status' => $orderStatus,
            'listings_by_source' => $listingsBySource,
            'top5_listings' => $top5Listings,
        ];
    }

    public static function customer(array $nodeIds, string $monthStart): array
    {
        $customersQuery = DB::table('cbe_customers')->whereIn('cbe_node_id', $nodeIds);

        // ADDED 19 Sep 2026 — per Chris: "follow the generallink kpi
        // screen" (the real customer-kpi/index.blade.php — Top 3
        // Customers, Customer Overview, Performance Overview by group).
        // CBE customers don't carry a category/occupation/source/state
        // (that's the general platform Customer Master's own schema —
        // cbe_customers is the CBE Accounts Receivable customer list),
        // so "Sales Amount" here comes from cbe_invoices raised against
        // each CBE customer, and "by group" becomes "by CBE Node" —
        // the real equivalent dimension in this domain.
        $top3BySales = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->whereIn('i.cbe_node_id', $nodeIds)
            ->where('i.status', '!=', 'CANCELLED')
            ->select('c.customer_id', 'c.customer_name', DB::raw('sum(i.amount) as total_sales'))
            ->groupBy('c.customer_id', 'c.customer_name')
            ->orderByDesc('total_sales')
            ->limit(3)
            ->get();

        $invoiceStatus = [
            'unpaid' => DB::table('cbe_invoices')->whereIn('cbe_node_id', $nodeIds)->where('status', 'UNPAID')->count(),
            'partially_paid' => DB::table('cbe_invoices')->whereIn('cbe_node_id', $nodeIds)->where('status', 'PARTIALLY_PAID')->count(),
            'paid' => DB::table('cbe_invoices')->whereIn('cbe_node_id', $nodeIds)->where('status', 'PAID')->count(),
            'cancelled' => DB::table('cbe_invoices')->whereIn('cbe_node_id', $nodeIds)->where('status', 'CANCELLED')->count(),
        ];

        $byNode = DB::table('cbe_customers as c')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'c.cbe_node_id')
            ->leftJoin('cbe_invoices as i', function ($join) {
                $join->on('i.customer_id', '=', 'c.customer_id')->where('i.status', '!=', 'CANCELLED');
            })
            ->whereIn('c.cbe_node_id', $nodeIds)
            ->select('n.node_id', 'n.node_name', DB::raw('count(distinct c.customer_id) as customer_count'), DB::raw('coalesce(sum(i.amount), 0) as total_sales'), DB::raw('coalesce(sum(i.paid_amount), 0) as total_paid'))
            ->groupBy('n.node_id', 'n.node_name')
            ->orderByDesc('total_sales')
            ->get();

        return [
            'total_customers' => (clone $customersQuery)->count(),
            'total_donors' => (clone $customersQuery)->where('is_donor', true)->count(),
            'linked_to_member' => (clone $customersQuery)->whereNotNull('agent_id')->count(),
            'new_this_month' => (clone $customersQuery)->where('created_at', '>=', $monthStart)->count(),
            'top3_by_sales' => $top3BySales,
            'invoice_status' => $invoiceStatus,
            'by_node' => $byNode,
        ];
    }
}
