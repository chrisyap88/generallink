<?php

// NEW 18 Aug 2026 — CBE Ecosystem Home screen static text. Priority
// screen per Chris — first one CBE members (e.g. Tao group, Chinese-
// speaking) will actually use, so it's fully wired before the rest of
// Phase 1/2.
return [
    'preview_banner'     => 'PREVIEW MODE — sample data shown, no real CBE members exist yet. Reachable only by Admin at /cbe/preview.',
    'search_placeholder' => 'Search GLADE...',

    // Icon rail
    'rail_home'          => 'Home',
    'rail_profile'       => 'Profile',
    'rail_reports'       => 'Reports',
    'rail_communication' => 'Communication',
    'rail_growth'        => 'Growth',

    // Sidebar section headers
    'sb_overview'               => 'Overview',
    'sb_network_tree'           => 'Network Tree',
    'sb_customer_relationship'  => 'Customer Relationship',
    'sb_business'               => 'Business',
    'sb_communication'          => 'Communication',
    'sb_master_file_maintenance'=> 'Master File Maintenance',
    'sb_growth_outreach'        => 'Growth & Outreach Center',
    'sb_my_account'             => 'My Account',

    // Sidebar items
    'sb_ecosystem_home'            => 'Ecosystem Home',
    'sb_my_network'                => 'My Network',
    'sb_customers'                 => 'Customers',
    'sb_customer_kpi'               => 'Customer KPI',
    'sb_customer_referrals'         => 'Customer Referrals',
    'sb_support_tickets'            => 'Support Tickets',
    'sb_submit_sales_transaction'   => 'Submit Sales Transaction',
    'sb_sales_transaction_maint'    => 'Sales Transaction Maintenance',
    'sb_help_desk'                  => 'Help Desk',
    'sb_notice_board'               => 'Notice Board',
    'sb_reminders'                  => 'Reminders',
    'sb_hierarchy_levels'           => 'Hierarchy Levels',
    'sb_records_reports'            => 'Records & Reports',
    // NEW 10 Sep 2026 (Task #400) — Administration Management, placed
    // after Financial Accounting Module. Houses general admin
    // utilities (Bank Statement, Annual Report, Reminders today; more
    // to follow) that don't belong under Records & Reports.
    'sb_administration_management'  => 'Administration Management',
    'sb_meeting_minutes'            => 'Meeting Minutes',
    'sb_activities'                 => 'Activities',
    'sb_marketplace_listings'       => 'Listings',
    'sb_marketplace_orders'         => 'Orders',
    'sb_marketplace_campaigns'      => 'Campaigns',
    'sb_vendor_approvals'           => 'Vendor Approvals',
    // NEW 17 Sep 2026 — temple Notice Board and Calendar (this entity's
    // own, not the platform-wide ones — 'sb_notice_board' above already
    // names the platform-wide one, so these use their own distinct keys).
    'sb_temple_notice_board'        => 'Notice Board',
    'sb_temple_calendar'            => 'Events Calendar',
    'sb_cbe_messaging'              => 'Messaging',
    'sb_cbe_tickets' => 'Support Tickets',

    // NEW 17 Sep 2026 — per Chris ("yes build all this for me").
    'sb_documents' => 'Document Repository',
    'sb_blast' => 'Send Announcement',
    'sb_correspondences' => 'Correspondence Register',
    'sb_compliance' => 'Compliance Reminders',
    'sb_ai_assistants' => 'AI FAQ & Answers',
    'sb_bank_statement'             => 'Bank Statement',
    'sb_annual_report'              => 'Annual Report',
    'sb_accounting'                 => 'Accounting',
    'sb_exec_dashboard'             => 'Executive Dashboard',
    'sb_events_donations'           => 'Events & Donations',
    'sb_events'                     => 'Events',
    'sb_donor_register'             => 'Donor Register',
    'sb_my_referral_link'           => 'My Referral Link',
    'sb_marketplace'                => 'Marketplace',
    'sb_submit_marketing_content'   => 'Submit Marketing Content',
    'sb_send_survey'                => 'Send Survey',
    'sb_my_profile'                 => 'My Profile',
    'sb_my_integrations'            => 'My Integrations',
    'sb_document_credit'            => 'Document Credit',
    'tag_soon'                      => 'soon',
    'tag_live'                      => 'live',

    // Profile header
    'community_fallback'   => 'CBE Community',
    'close_profile_header'  => 'Close Profile Header',
    'edit_profile'           => 'Edit Profile',

    // Greeting
    'good_morning'    => 'Good Morning',
    'good_afternoon'  => 'Good Afternoon',
    'good_evening'    => 'Good Evening',
    'greeting_sub'    => "Here's what's happening in your GLADE world today.",

    // My World
    'my_world'        => 'My World',
    'reminders_due'   => 'Reminders Due',
    'new_notices'     => 'New Notices',

    // Quick Actions
    'quick_actions'      => 'Quick Actions',
    'qa_edit_my_profile'  => 'Edit My Profile',
    'qa_help_desk'        => 'Help Desk',
    'qa_view_notices'     => 'View Notices',
    'qa_my_reminders'     => 'My Reminders',

    // Smart Reminders card
    'smart_reminders'        => 'Smart Reminders',
    'view_all'                => 'View All',
    'no_reminders'            => 'No reminders due right now.',
    'due'                      => 'Due',
    'reminder_badge'           => 'Reminder',

    // Notice Board card
    'notice_board'      => 'Notice Board',
    'no_notices'         => 'No notices posted right now.',

    // Hierarchy Levels card
    'hierarchy_levels'          => 'Hierarchy Levels',
    'no_hierarchy_levels'        => 'No hierarchy levels set up yet — configured by Admin under Group Name Maintenance.',

    // NEW 28 Aug 2026 — GLADE Home / Main Menu landing screen
    'glade_home_title' => 'Main Menu',
    'glade_home_hint'  => 'Pick a program from the menu on the left to get started.',
];
