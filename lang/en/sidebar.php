<?php

// NEW 18 Aug 2026 — everything under the sidebar's top-level categories
// (nav.php covers the category headers themselves), plus the role badge,
// "Prev — Main Menu" footer, and the notification dropdown's static text.
// Per Chris: every drill-down submenu item must switch language too, not
// just the top-level headers.
return [
    'administrator' => 'Administrator',
    'prev_main_menu' => 'Prev — Main Menu',
    'prev_fin_categories' => 'Prev — Categories',
    'sec_administration' => 'Administration',
    // NEW 17 Sep 2026 — per Chris: split the old single "Administration"
    // group into 5 groups since it had grown to 12 items.
    'sec_meetings' => 'Meetings',
    // RENAMED/REGROUPED 18 Sep 2026 — per Chris (acting as an
    // experienced NGO secretary): each group is now one real job, not a
    // technical catch-all. sec_notices_messages/sec_documents_records/
    // sec_activities kept (not deleted) in case any other view still
    // references the old key names — only the sidebar itself uses the
    // new ones below.
    'sec_notice_board' => 'Notice Board',
    'sec_member_communication' => 'Member Communication',
    'sec_compliance_records' => 'Compliance & Records',
    'sec_activities_calendar' => 'Activities & Calendar',
    'sec_marketplace' => 'Marketplace & Offers',
    'sec_kpi_reports' => 'Reports & KPI',
    'sec_notices_messages' => 'Notices & Messages',
    'sec_documents_records' => 'Documents & Records',
    'sec_activities' => 'Activities',
    'sec_ai_tools' => 'AI FAQ & Answers',
    'secretarial_management' => 'Secretarial Management',
    // NEW 19 Sep 2026 — per Chris: sidebar-only labels for the
    // relocated items (before My Account). The actual AI FAQ &
    // Answers page keeps its own title (cbe_ai_assistant.page_title)
    // — only this sidebar link text changes to "AI Agent".
    // "Community Business Enterprise Group" is split into two
    // lines for the sidebar (secretarial_management key kept as-is,
    // still used inside the GLADE/CBE portal's own wording).
    'ai_agent' => 'AI Agent',
    'community_business_line1' => 'Community Business',
    'community_business_line2' => 'Enterprise Group',
    'sec_donor_management' => 'Donor Management',
    'sec_consultant_practitioner' => 'Advisor & Practitioner',
    'sec_membership_management' => 'Membership Management',
    'prev_sec_categories' => 'Prev — Categories',
    'mark_all_read' => 'Mark all read',
    'loading' => 'Loading...',

    // Overview KPI (Admin)
    'overview_kpi_item' => 'Company Overview',
    'vendor_kpi' => 'Vendor KPI',
    'customer_kpi' => 'Customer KPI',
    'cbe_kpi' => 'CBE KPI',

    // Network Tree (role-specific)
    'network_tree_item' => 'Network Tree',
    'my_group_tree' => 'My Group Tree',
    'my_team_tree' => 'My Team Tree',
    'my_recruits' => 'My Recruits',

    // Customer Relationship
    'customers' => 'Customers',
    'customer_referrals' => 'Customer Referrals',
    'customer_status_maintenance' => 'Customer Status Maintenance',
    'customer_type_maintenance' => 'Customer Type Maintenance',
    'customer_category_maintenance' => 'Customer Category Maintenance',
    'occupation_group_maintenance' => 'Occupation Group Maintenance',
    'customer_source_maintenance' => 'Customer Source Maintenance',
    'support_tickets' => 'Support Tickets',
    'open_addon_crm' => 'Open GeneralLink Add-on CRM',

    // Business
    'submit_sales_transaction' => 'Submit Sales Transaction',
    'sales_transaction_maintenance' => 'Sales Transaction Maintenance',
    'submit_by_email' => 'Submit by Email',
    'earning_income_ledger' => 'Earning Income Ledger',
    'reward_points' => 'Reward Points',
    'transfer_buy_points' => 'Transfer / Buy Points',
    'point_purchases' => 'Point Purchases',
    'transactions_dashboard' => 'Transactions Dashboard',
    'earning_income' => 'Earning Income',

    // Communication
    'help_desk' => 'Help Desk',
    'notice_board' => 'Notice Board',
    'notification_setup' => 'Notification Setup',
    'reminders' => 'Reminders',
    'whatsapp_audit_log' => 'WhatsApp Audit Log',

    // Action Required (Admin)
    'renewal_quotation_requests' => 'Renewal Quotation Requests',
    'pending_assignment' => 'Pending Assignment',
    'approvals' => 'Approvals',
    'batch_upload' => 'Batch Upload',
    'pending_verifications' => 'Pending Verifications',
    'customer_housekeeping' => 'Customer Housekeeping',
    'document_credit' => 'Document Credit',
    'glade_engagement_analytics' => 'GLADE Engagement Analytics',
    'executive_kpi_dashboard' => 'Overall Executive KPI',
    'communication_kpi' => 'Communication KPI',
    'vendor_marketplace_kpi' => 'Vendor Marketplace KPI',
    'customer_kpi' => 'Customer KPI',
    'risk_review_queue' => 'Risk Review Queue',
    'good_to_know' => 'Good To Know',
    'sales_earning_forecast_item' => 'Sales & Earning Forecast',

    // Master File Maintenance > Group Set Up
    'group_set_up' => 'Group Set Up',
    'direct_selling_group_item' => 'Direct Selling Group',
    'group_name' => 'Group Name',
    'breakaway_bonus_rules' => 'Breakaway Bonus Rules',
    'promotion_demotion_rules' => 'Promotion & Demotion Rules',
    'organization_rewards_group_item' => 'Organization Rewards Group',
    'organization_category' => 'Organization Category',
    'organization_rewards_groups' => 'Organization Rewards Groups',
    'rank_hierarchy_structure' => 'Rank Hierarchy Structure',
    'rank_assignment' => 'Rank Assignment',
    'cbe_group_item' => 'Community & Business Enterprise Group',
    'group_name_hierarchy_levels' => 'Group Name & Hierarchy Levels',
    'appoint_team_leader' => 'Appoint Team Leader',
    'add_introducer_special' => 'Add Introducer (Special)',

    // Master File Maintenance > Vendor Management
    'vendor_management' => 'Vendor Management',
    'pending_vendor_logins' => 'Pending Vendor Logins',
    'vendor_onboarding_workflow' => 'Vendor Onboarding Workflow',
    'vendor_approvals' => 'Vendor Approvals',
    'agreement_compliance_log' => 'Agreement Compliance Log',
    'head_office_main_outlet' => 'Head Office / Main Outlet',
    'branch_outlet' => 'Branch / Outlet',
    'products' => 'Products',
    'earning_income_structures' => 'Earning Income Structures',
    'content_library' => 'Content Library',

    // Master File Maintenance > Affiliate Partner
    'affiliate_partner' => 'Affiliate Partner',
    'affiliate_partner_profile' => 'Affiliate Partner Profile',
    'affiliate_partner_members' => 'Affiliate Partner Members',
    'affiliate_partner_claims' => 'Affiliate Partner Claims',
    'affiliate_partner_ledger' => 'Affiliate Partner Ledger',
    'reason_code' => 'Reason Code',
    'partner_api_keys' => 'Partner API Keys',
    // NEW 19 Sep 2026 -- AI Master Data Assistant, top item in Master
    // File Maintenance, per Chris's uploaded spec.
    'ai_master_data_assistant' => 'AI Master Data Assistant',
    // NEW 13 Sep 2026 (Task #418) — GLADE Master File Maintenance, split
    // into sub-screens since 25+ links no longer fit one no-scroll
    // screen. Each opens its own full sub-screen with its own Prev/Next.
    'group_membership_setup' => 'Membership Master File',
    'directory_catalog_types' => 'Directory & Catalog Types',
    'financial_master_file' => 'Financial Master File',
    'vendor_master_file' => 'Vendor Master File',

    // Growth & Outreach Center
    'my_referral_link' => 'My Referral Link',
    'rebate_offer_search' => 'Rebate Offer Search',
    'recruitment_contests' => 'Recruitment Contests',
    'my_public_profile' => 'My Public Profile',
    'submit_marketing_content' => 'Submit Marketing Content',
    'my_submissions' => 'My Submissions',
    'survey_management' => 'Survey Management',
    'send_survey' => 'Send Survey',
    'broadcast_campaigns' => 'Broadcast Marketplace Campaigns',
    'channel_connections' => 'Channel Connections',

    // Reward Point Programs
    'reward_rates' => 'Reward Rates',
    'vendor_rebate_offers' => 'Vendor Rebate Offers',
    'rebate_program_applications' => 'Rebate Program Applications',

    // My Account
    'my_integrations' => 'My Integrations',
    'earning_income_wallet' => 'Earning Income Wallet',
    'team_document_credit' => 'Team Document Credit',
    'audit_logs' => 'Audit Logs',
    'breakaway_bonus_claims' => 'Breakaway Bonus Claims',
    'beneficiary' => 'Beneficiary',
    'entity_hierarchy_link' => 'CBE Entity Affiliation',
];
