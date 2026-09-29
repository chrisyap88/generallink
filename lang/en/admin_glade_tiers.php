<?php

// NEW 27 Aug 2026 — Task #233: GLADE Public Model membership tier
// catalog admin screen + community tier approval flow.
return [
    'page_title' => 'GLADE Membership Tiers',
    'tab_catalog' => 'Tier Catalog',
    'tab_pending' => 'Pending Approvals',
    'tab_active' => 'Active Tiers',
    'pager_info' => 'Page :current of :last (:total total)',
    'catalog_title' => 'Tier Catalog',
    'catalog_helper' => 'Every figure below is editable, and new tiers can be added at any time — nothing here is hardcoded.',
    'col_tier_name' => 'Tier Name',
    'col_max_users' => 'Max Users',
    'col_annual_fee' => 'Subscription Fee per Annum (RM)',
    'col_custom_quote' => 'Custom Quotation',
    'col_active' => 'Active',
    'no_cap' => 'No cap',
    'custom_quotation' => 'Custom Quotation',
    'btn_save' => 'Save',
    'saved' => '✓ Saved.',
    'err_name_required' => 'Tier name is required.',

    // NEW 11 Sep 2026 — Add New Tier form.
    'add_tier_title' => 'Add New Tier',
    'new_tier_name_placeholder' => 'Tier name (e.g. "Student")',
    'btn_add_tier' => 'Add Tier',
    'btn_save_all' => 'Save All',
    'select_all' => 'Select All',

    'pending_title' => 'Pending Tier Approvals',
    'pending_helper' => 'A community\'s requested tier only takes effect once approved here — separate from whoever proposed it.',
    'col_community' => 'Community',
    'col_requested_tier' => 'Requested Tier',
    'col_requested_by' => 'Requested By',
    'col_requested_at' => 'Requested At',
    'btn_approve' => 'Approve',
    'btn_reject' => 'Reject',
    'no_pending' => 'No tier changes awaiting approval.',
    'approved_flash' => '✓ Approved tier for :name.',
    'rejected_flash' => 'Rejected tier request for :name.',

    'active_title' => 'Active Community Tiers',
    'col_approved_by' => 'Approved By',
    'col_approved_at' => 'Approved At',
    'no_active' => 'No community has an active GLADE tier yet.',

    'subscription_tier_label' => 'Subscription Type',
    'subscription_tier_helper' => 'Free = this community runs as a single unit. Paid = this community uses multiple linked levels (e.g. HQ, State, Branch, Temple).',
    'subscription_tier_free' => 'Free (single-unit)',
    'subscription_tier_paid' => 'Paid (multi-level HQ→State→Branch→Temple)',
    'subscription_tier_free_short' => 'Free',
    'subscription_tier_paid_short' => 'Paid (Multi-level)',
    'glade_tier_label' => 'GLADE Membership Tier (Billing)',
    'glade_tier_none' => '— None —',
    'status_pending' => 'Pending Admin approval — not yet active.',
    'status_active' => 'Active.',
];
