<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 12 Sep 2026 (Task #416) — per Chris: "the paid or free is wrong...
// NOT the entire CBE is free or paid... you must have another master
// file... a program library master file that you retrieve for me from
// the entire system... i have a checkbox to tick the crown logo, if i
// click crown logo means paid... must always update because we may have
// future new develop program that i need to flag again."
//
// Replaces the old idea of one FREE/PAID flag on the whole community
// (group_labels.subscription_tier) with a real catalog of every
// individual program/feature across the whole app (Admin, GL/TL/
// Introducer dashboards, GLADE/CBE portal — 192 items scanned directly
// out of the live sidebar menus, not typed by hand), each independently
// flaggable Paid or Free. New programs built after this migration are
// added here going forward as a standing rule, never left uncatalogued.
// Everything seeds is_paid = false (Free) by default — Chris ticks the
// crown on whichever programs he wants to charge for, on the new
// Program Library screen; nothing here assumes what should be paid.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_catalog')) {
            Schema::create('program_catalog', function (Blueprint $table) {
                $table->uuid('id')->primary();
                // Stable machine key (portal + route, slugified) — used to
                // look up a program's Paid/Free flag from sidebar code
                // without depending on the human-editable label.
                $table->string('program_key')->unique();
                // Which sidebar/portal this program lives in — DASHBOARD
                // (Admin/GL/TL/Introducer shared layout) or GLADE (CBE
                // community portal). Free text, not an enum, since a
                // future portal (e.g. Vendor) can be added without a
                // migration.
                $table->string('portal');
                // The sidebar section/group it was found under (e.g.
                // "Business", "Financial Accounting Module"), display-only
                // grouping on the Program Library screen.
                $table->string('section')->nullable();
                // Human-readable label as shown on screen (kept editable
                // by Admin — display text only, never used as a lookup
                // key).
                $table->string('label');
                // The Laravel route name this program resolves to, where
                // one exists (some sidebar entries are placeholders with
                // no route yet, or a dynamic/{prefix}-style route, or an
                // external link) — nullable, informational only, not a
                // foreign key since routes aren't DB rows.
                $table->string('route_name')->nullable();
                // The actual Paid/Free flag Chris asked for — ticking this
                // on the Program Library screen shows the crown icon next
                // to the program wherever it appears.
                $table->boolean('is_paid')->default(false);
                $table->timestamps();
            });

            $now = now();
            $rows = [
            ['program_key' => 'dashboard_dashboard_home', 'portal' => 'DASHBOARD', 'section' => '', 'label' => 'Dashboard', 'route_name' => null],
            ['program_key' => 'dashboard_admin_dashboard', 'portal' => 'DASHBOARD', 'section' => 'Overview KPI', 'label' => 'Overview KPI', 'route_name' => 'admin.dashboard'],
            ['program_key' => 'dashboard_admin_vendor_kpi_index', 'portal' => 'DASHBOARD', 'section' => 'Overview KPI', 'label' => 'Vendor KPI', 'route_name' => 'admin.vendor-kpi.index'],
            ['program_key' => 'dashboard_customer_kpi_index', 'portal' => 'DASHBOARD', 'section' => 'Overview KPI', 'label' => 'Customer KPI', 'route_name' => 'customer-kpi.index'],
            ['program_key' => 'dashboard_admin_cbe_kpi', 'portal' => 'DASHBOARD', 'section' => 'Overview KPI', 'label' => 'CBE KPI', 'route_name' => 'admin.cbe-kpi'],
            ['program_key' => 'dashboard_admin_network', 'portal' => 'DASHBOARD', 'section' => 'Network Tree', 'label' => 'Network Tree (Admin)', 'route_name' => 'admin.network'],
            ['program_key' => 'dashboard_gl_network', 'portal' => 'DASHBOARD', 'section' => 'Network Tree', 'label' => 'My Group Tree (GL)', 'route_name' => 'gl.network'],
            ['program_key' => 'dashboard_tl_introducers', 'portal' => 'DASHBOARD', 'section' => 'Network Tree', 'label' => 'My Team Tree (TL)', 'route_name' => 'tl.introducers'],
            ['program_key' => 'dashboard_introducer_recruits', 'portal' => 'DASHBOARD', 'section' => 'Network Tree', 'label' => 'My Recruits (Introducer)', 'route_name' => 'introducer.recruits'],
            ['program_key' => 'dashboard_prefix_customers_index', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Customers', 'route_name' => '{prefix}.customers.index'],
            ['program_key' => 'dashboard_customer_referrals_index', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Customer Referrals', 'route_name' => 'customer-referrals.index'],
            ['program_key' => 'dashboard_admin_masterfile_customer_statuses', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Customer Status Maintenance', 'route_name' => 'admin.masterfile.customer-statuses'],
            ['program_key' => 'dashboard_admin_masterfile_customer_types', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Customer Type Maintenance', 'route_name' => 'admin.masterfile.customer-types'],
            ['program_key' => 'dashboard_admin_masterfile_customer_categories', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Customer Category Maintenance', 'route_name' => 'admin.masterfile.customer-categories'],
            ['program_key' => 'dashboard_admin_masterfile_occupation_groups', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Occupation Group Maintenance', 'route_name' => 'admin.masterfile.occupation-groups'],
            ['program_key' => 'dashboard_admin_masterfile_customer_sources', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Customer Source Maintenance', 'route_name' => 'admin.masterfile.customer-sources'],
            ['program_key' => 'dashboard_support_tickets_index', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Support Tickets', 'route_name' => 'support-tickets.index'],
            ['program_key' => 'dashboard_open_addon_crm', 'portal' => 'DASHBOARD', 'section' => 'Customer Relationship', 'label' => 'Open Addon CRM', 'route_name' => null],
            ['program_key' => 'dashboard_prefix_sales_transactions_create', 'portal' => 'DASHBOARD', 'section' => 'Business', 'label' => 'Submit Sales Transaction', 'route_name' => '{prefix}.sales-transactions.create'],
            ['program_key' => 'dashboard_prefix_sales_transactions_index', 'portal' => 'DASHBOARD', 'section' => 'Business', 'label' => 'Sales Transaction Maintenance', 'route_name' => '{prefix}.sales-transactions.index'],
            ['program_key' => 'dashboard_prefix_submission_key', 'portal' => 'DASHBOARD', 'section' => 'Business', 'label' => 'Submit by Email', 'route_name' => '{prefix}.submission-key'],
            ['program_key' => 'dashboard_admin_earning_ledger', 'portal' => 'DASHBOARD', 'section' => 'Business', 'label' => 'Earning Income Ledger', 'route_name' => 'admin.earning-ledger'],
            ['program_key' => 'dashboard_reward_points', 'portal' => 'DASHBOARD', 'section' => 'Business', 'label' => 'Reward Points', 'route_name' => null],
            ['program_key' => 'dashboard_prefix_transactions', 'portal' => 'DASHBOARD', 'section' => 'Business', 'label' => 'Transactions Dashboard', 'route_name' => '{prefix}.transactions'],
            ['program_key' => 'dashboard_gl_commissions_index', 'portal' => 'DASHBOARD', 'section' => 'Business', 'label' => 'Earning Income', 'route_name' => 'gl.commissions.index'],
            ['program_key' => 'dashboard_help_desk_index', 'portal' => 'DASHBOARD', 'section' => 'Communication', 'label' => 'Help Desk', 'route_name' => 'help-desk.index'],
            ['program_key' => 'dashboard_notice_board_index', 'portal' => 'DASHBOARD', 'section' => 'Communication', 'label' => 'Notice Board', 'route_name' => 'notice-board.index'],
            ['program_key' => 'dashboard_admin_notification_setup_index', 'portal' => 'DASHBOARD', 'section' => 'Communication', 'label' => 'Notification Setup', 'route_name' => 'admin.notification-setup.index'],
            ['program_key' => 'dashboard_calendar_index', 'portal' => 'DASHBOARD', 'section' => 'Communication', 'label' => 'Reminders', 'route_name' => 'calendar.index'],
            ['program_key' => 'dashboard_admin_whatsapp_audit_index', 'portal' => 'DASHBOARD', 'section' => 'Communication', 'label' => 'WhatsApp Audit Log', 'route_name' => 'admin.whatsapp-audit.index'],
            ['program_key' => 'dashboard_rqprefix_renewal_quotations_index', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Renewal Quotation Requests', 'route_name' => '{rqPrefix}.renewal-quotations.index'],
            ['program_key' => 'dashboard_admin_agents_pending', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Pending Assignment', 'route_name' => 'admin.agents.pending'],
            ['program_key' => 'dashboard_admin_approvals_index', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Approvals', 'route_name' => 'admin.approvals.index'],
            ['program_key' => 'dashboard_admin_batch_index', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Batch Upload', 'route_name' => 'admin.batch.index'],
            ['program_key' => 'dashboard_admin_masterfile_pending_verifications', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Pending Verifications', 'route_name' => 'admin.masterfile.pending-verifications'],
            ['program_key' => 'dashboard_admin_housekeeping_customers', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Customer Housekeeping', 'route_name' => 'admin.housekeeping.customers'],
            ['program_key' => 'dashboard_admin_document_credit_index', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Document Credit', 'route_name' => 'admin.document-credit.index'],
            ['program_key' => 'dashboard_admin_glade_analytics_index', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'GLADE Engagement Analytics', 'route_name' => 'admin.glade-analytics.index'],
            ['program_key' => 'dashboard_admin_fraud_review_index', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Risk Review Queue', 'route_name' => 'admin.fraud-review.index'],
            ['program_key' => 'dashboard_renewal_forecast_index', 'portal' => 'DASHBOARD', 'section' => 'Action Required', 'label' => 'Sales & Earning Forecast', 'route_name' => 'renewal-forecast.index'],
            ['program_key' => 'dashboard_admin_masterfile_group_names', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Group Name (DSG)', 'route_name' => 'admin.masterfile.group-names'],
            ['program_key' => 'dashboard_admin_masterfile_breakaway_bonus_rules', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Breakaway Bonus Rules', 'route_name' => 'admin.masterfile.breakaway-bonus-rules'],
            ['program_key' => 'dashboard_admin_masterfile_promotion_rules', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Promotion/Demotion Rules', 'route_name' => 'admin.masterfile.promotion-rules'],
            ['program_key' => 'dashboard_admin_masterfile_org_category', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Organization Category', 'route_name' => 'admin.masterfile.org-category'],
            ['program_key' => 'dashboard_admin_special_group_index', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Organization Rewards Groups', 'route_name' => 'admin.special-group.index'],
            ['program_key' => 'dashboard_admin_masterfile_role_ranks', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Rank Hierarchy Structure', 'route_name' => 'admin.masterfile.role-ranks'],
            ['program_key' => 'dashboard_admin_masterfile_rank_assignment', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Rank Assignment', 'route_name' => 'admin.masterfile.rank-assignment'],
            ['program_key' => 'dashboard_admin_glade_tiers_index', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'GLADE Membership Tiers', 'route_name' => 'admin.glade-tiers.index'],
            ['program_key' => 'dashboard_special_group_appoint_tl', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Appoint Team Leader', 'route_name' => 'special-group.appoint-tl'],
            ['program_key' => 'dashboard_special_group_add_introducer', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Add Introducer (Special Group)', 'route_name' => 'special-group.add-introducer'],
            ['program_key' => 'dashboard_admin_vendors_pending_logins', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Vendor Management: Pending Vendor Logins', 'route_name' => 'admin.vendors.pending-logins'],
            ['program_key' => 'dashboard_admin_vendors_onboarding_workflow', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Vendor Onboarding Workflow', 'route_name' => 'admin.vendors.onboarding-workflow'],
            ['program_key' => 'dashboard_admin_vendors_approvals', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Vendor Approvals', 'route_name' => 'admin.vendors.approvals'],
            ['program_key' => 'dashboard_admin_vendor_agreements_index', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Agreement & Compliance Log', 'route_name' => 'admin.vendor-agreements.index'],
            ['program_key' => 'dashboard_admin_vendors_index', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Head Office/Main Outlet', 'route_name' => 'admin.vendors.index'],
            ['program_key' => 'dashboard_admin_branches_index', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Branch/Outlet', 'route_name' => 'admin.branches.index'],
            ['program_key' => 'dashboard_admin_masterfile_products', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Products', 'route_name' => 'admin.masterfile.products'],
            ['program_key' => 'dashboard_admin_masterfile_commissions', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Earning Income Structures', 'route_name' => 'admin.masterfile.commissions'],
            ['program_key' => 'dashboard_admin_video_library_index', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Content Library', 'route_name' => 'admin.video-library.index'],
            ['program_key' => 'dashboard_admin_masterfile_override_recipient_profile', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Affiliate Partner Profile', 'route_name' => 'admin.masterfile.override-recipient-profile'],
            ['program_key' => 'dashboard_admin_masterfile_override_members', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Affiliate Partner Members', 'route_name' => 'admin.masterfile.override-members'],
            ['program_key' => 'dashboard_admin_masterfile_override_claims', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Affiliate Partner Claims', 'route_name' => 'admin.masterfile.override-claims'],
            ['program_key' => 'dashboard_admin_masterfile_override_ledger', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Affiliate Partner Ledger', 'route_name' => 'admin.masterfile.override-ledger'],
            ['program_key' => 'dashboard_admin_masterfile_reason_codes', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Reason Code', 'route_name' => 'admin.masterfile.reason-codes'],
            ['program_key' => 'dashboard_admin_partner_api_index', 'portal' => 'DASHBOARD', 'section' => 'Master File Maintenance', 'label' => 'Partner API Keys', 'route_name' => 'admin.partner-api.index'],
            ['program_key' => 'dashboard_referral_link_index', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'My Referral Link', 'route_name' => 'referral-link.index'],
            ['program_key' => 'dashboard_rebate_offers_search', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'Rebate Offer Search', 'route_name' => 'rebate-offers.search'],
            ['program_key' => 'dashboard_growth_contests_index', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'Recruitment Contests', 'route_name' => 'growth-contests.index'],
            ['program_key' => 'dashboard_public_profile_edit', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'My Public Profile', 'route_name' => 'public-profile.edit'],
            ['program_key' => 'dashboard_content_submission_create', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'Submit Marketing Content', 'route_name' => 'content-submission.create'],
            ['program_key' => 'dashboard_content_submission_index', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'My Submissions', 'route_name' => 'content-submission.index'],
            ['program_key' => 'dashboard_admin_growth_surveys_index', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'Survey Management', 'route_name' => 'admin.growth.surveys.index'],
            ['program_key' => 'dashboard_survey_send_create', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'Send Survey', 'route_name' => 'survey-send.create'],
            ['program_key' => 'dashboard_admin_growth_broadcasts_index', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'Broadcast Campaigns', 'route_name' => 'admin.growth.broadcasts.index'],
            ['program_key' => 'dashboard_admin_growth_channels_index', 'portal' => 'DASHBOARD', 'section' => 'Growth & Outreach', 'label' => 'Channel Connections', 'route_name' => 'admin.growth.channels.index'],
            ['program_key' => 'dashboard_admin_masterfile_reward_rates', 'portal' => 'DASHBOARD', 'section' => 'Reward Point Programs', 'label' => 'Reward Rates', 'route_name' => 'admin.masterfile.reward-rates'],
            ['program_key' => 'dashboard_admin_masterfile_rebate_offers', 'portal' => 'DASHBOARD', 'section' => 'Reward Point Programs', 'label' => 'Vendor Rebate Offers', 'route_name' => 'admin.masterfile.rebate-offers'],
            ['program_key' => 'dashboard_admin_masterfile_rebate_applications', 'portal' => 'DASHBOARD', 'section' => 'Reward Point Programs', 'label' => 'Rebate Program Applications', 'route_name' => 'admin.masterfile.rebate-applications'],
            ['program_key' => 'dashboard_profile', 'portal' => 'DASHBOARD', 'section' => 'My Account', 'label' => 'My Profile', 'route_name' => 'profile'],
            ['program_key' => 'dashboard_integrations_index', 'portal' => 'DASHBOARD', 'section' => 'My Account', 'label' => 'My Integrations', 'route_name' => 'integrations.index'],
            ['program_key' => 'dashboard_wallet_show', 'portal' => 'DASHBOARD', 'section' => 'My Account', 'label' => 'Earning Income Wallet', 'route_name' => 'wallet.show'],
            ['program_key' => 'dashboard_document_credit_show', 'portal' => 'DASHBOARD', 'section' => 'My Account', 'label' => 'Document Credit', 'route_name' => 'document-credit.show'],
            ['program_key' => 'dashboard_team_document_credit_index', 'portal' => 'DASHBOARD', 'section' => 'My Account', 'label' => 'Team Document Credit', 'route_name' => 'team-document-credit.index'],
            ['program_key' => 'dashboard_admin_masterfile_audit_logs', 'portal' => 'DASHBOARD', 'section' => 'My Account', 'label' => 'Audit Logs', 'route_name' => 'admin.masterfile.audit-logs'],
            ['program_key' => 'dashboard_breakaway_claims_index', 'portal' => 'DASHBOARD', 'section' => 'My Account', 'label' => 'Breakaway Bonus Claims', 'route_name' => 'breakaway-claims.index'],
            ['program_key' => 'glade_cbe_exec_dashboard', 'portal' => 'GLADE', 'section' => 'Overview', 'label' => 'Executive Dashboard', 'route_name' => 'cbe.exec-dashboard'],
            ['program_key' => 'glade_cbe_minutes_index', 'portal' => 'GLADE', 'section' => 'Administration Management', 'label' => 'Meeting Minutes', 'route_name' => 'cbe.minutes.index'],
            ['program_key' => 'glade_cbe_activities_index', 'portal' => 'GLADE', 'section' => 'Administration Management', 'label' => 'Activities', 'route_name' => 'cbe.activities.index'],
            ['program_key' => 'glade_admin_cbe_kpi_donors', 'portal' => 'GLADE', 'section' => 'Membership Module', 'label' => 'Donor Maintenance', 'route_name' => 'admin.cbe-kpi.donors'],
            ['program_key' => 'glade_admin_cbe_kpi_members', 'portal' => 'GLADE', 'section' => 'Membership Module', 'label' => 'Advisor Maintenance', 'route_name' => 'admin.cbe-kpi.members'],
            ['program_key' => 'glade_admin_cbe_kpi_hierarchy_nodes_create', 'portal' => 'GLADE', 'section' => 'Membership Module', 'label' => 'Entity Maintenance', 'route_name' => 'admin.cbe-kpi.hierarchy-nodes.create'],
            ['program_key' => 'glade_cbe_events_index', 'portal' => 'GLADE', 'section' => 'Membership Module', 'label' => 'Events', 'route_name' => 'cbe.events.index'],
            ['program_key' => 'glade_cbe_donors_index', 'portal' => 'GLADE', 'section' => 'Membership Module', 'label' => 'Donor Register', 'route_name' => 'cbe.donors.index'],
            ['program_key' => 'glade_admin_masterfile_group_names', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Set Up CBE Group', 'route_name' => 'admin.masterfile.group-names'],
            ['program_key' => 'glade_admin_glade_tiers_index', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'GLADE Membership Tiers', 'route_name' => 'admin.glade-tiers.index'],
            ['program_key' => 'glade_admin_faith_practice_types_index', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Faith/Practice Types', 'route_name' => 'admin.faith-practice-types.index'],
            ['program_key' => 'glade_cbe_accounting_customers', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Debtor Master', 'route_name' => 'cbe.accounting.customers'],
            ['program_key' => 'glade_cbe_accounting_customer_categories', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Debtor Category', 'route_name' => 'cbe.accounting.customer-categories'],
            ['program_key' => 'glade_cbe_accounting_suppliers', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Creditor Master', 'route_name' => 'cbe.accounting.suppliers'],
            ['program_key' => 'glade_cbe_accounting_supplier_categories', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Creditor Category', 'route_name' => 'cbe.accounting.supplier-categories'],
            ['program_key' => 'glade_cbe_accounting_chart_of_accounts', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Chart of Accounts', 'route_name' => 'cbe.accounting.chart-of-accounts'],
            ['program_key' => 'glade_cbe_accounting_account_categories', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Transaction Type', 'route_name' => 'cbe.accounting.account-categories'],
            ['program_key' => 'glade_cbe_accounting_document_number_control', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Document Number Control', 'route_name' => 'cbe.accounting.document-number-control'],
            ['program_key' => 'glade_cbe_accounting_asset_categories', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Fixed Asset Category', 'route_name' => 'cbe.accounting.asset-categories'],
            ['program_key' => 'glade_cbe_accounting_asset_locations', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Fixed Asset Location', 'route_name' => 'cbe.accounting.asset-locations'],
            ['program_key' => 'glade_cbe_finance_bank_accounts', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Bank Account Number', 'route_name' => 'cbe.finance.bank-accounts'],
            ['program_key' => 'glade_cbe_finance_bank_transaction_types', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Bank Transaction Types Master', 'route_name' => 'cbe.finance.bank-transaction-types'],
            ['program_key' => 'glade_cbe_accounting_bank_reconciliation_rules', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Reconciliation Rules', 'route_name' => 'cbe.accounting.bank-reconciliation-rules'],
            ['program_key' => 'glade_cbe_accounting_payment_terms', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Payment Terms Master', 'route_name' => 'cbe.accounting.payment-terms'],
            ['program_key' => 'glade_cbe_accounting_payment_methods', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Payment Methods Master', 'route_name' => 'cbe.accounting.payment-methods'],
            ['program_key' => 'glade_cbe_accounting_funds', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Fund Master', 'route_name' => 'cbe.accounting.funds'],
            ['program_key' => 'glade_cbe_accounting_tax_rates', 'portal' => 'GLADE', 'section' => 'Financial Accounting Module - Master File Maintenance', 'label' => 'Tax Rates Master', 'route_name' => 'cbe.accounting.tax-rates'],
            ['program_key' => 'glade_cbe_accounting_invoices', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Invoices', 'route_name' => 'cbe.accounting.invoices'],
            ['program_key' => 'glade_cbe_accounting_donation_entry_create', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Donation Entry', 'route_name' => 'cbe.accounting.donation-entry.create'],
            ['program_key' => 'glade_cbe_accounting_donation_pledges', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Donation Pledges', 'route_name' => 'cbe.accounting.donation-pledges'],
            ['program_key' => 'glade_cbe_accounting_ar_debit_notes', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'AR Debit Notes', 'route_name' => 'cbe.accounting.ar-debit-notes'],
            ['program_key' => 'glade_cbe_accounting_ar_credit_notes', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'AR Credit Notes', 'route_name' => 'cbe.accounting.ar-credit-notes'],
            ['program_key' => 'glade_cbe_accounting_payment_allocation', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Payment Allocation', 'route_name' => 'cbe.accounting.payment-allocation'],
            ['program_key' => 'glade_cbe_accounting_ar_adjustments', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'AR Adjustments', 'route_name' => 'cbe.accounting.ar-adjustments'],
            ['program_key' => 'glade_cbe_accounting_ar_refunds', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'AR Refunds', 'route_name' => 'cbe.accounting.ar-refunds'],
            ['program_key' => 'glade_cbe_accounting_ar_opening_balances', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'AR Opening Balances', 'route_name' => 'cbe.accounting.ar-opening-balances'],
            ['program_key' => 'glade_cbe_accounting_reports_debtor_ledger_picker', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Debtor Ledger', 'route_name' => 'cbe.accounting.reports.debtor-ledger-picker'],
            ['program_key' => 'glade_cbe_accounting_invoice_enquiry', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Invoice Enquiry', 'route_name' => 'cbe.accounting.invoice-enquiry'],
            ['program_key' => 'glade_cbe_accounting_receipt_enquiry', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Receipt Enquiry', 'route_name' => 'cbe.accounting.receipt-enquiry'],
            ['program_key' => 'glade_cbe_accounting_outstanding_balance_enquiry', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Outstanding Balance Enquiry', 'route_name' => 'cbe.accounting.outstanding-balance-enquiry'],
            ['program_key' => 'glade_cbe_accounting_reports_donor_statement_picker', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Donor Statement', 'route_name' => 'cbe.accounting.reports.donor-statement-picker'],
            ['program_key' => 'glade_cbe_accounting_reports_ar_aging', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'AR Aging', 'route_name' => 'cbe.accounting.reports.ar-aging'],
            ['program_key' => 'glade_cbe_accounting_reports_fund_balance', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'Fund Balance', 'route_name' => 'cbe.accounting.reports.fund-balance'],
            ['program_key' => 'glade_cbe_accounting_reports_ar_reports', 'portal' => 'GLADE', 'section' => 'Accounts Receivable', 'label' => 'AR Reports Hub', 'route_name' => 'cbe.accounting.reports.ar-reports'],
            ['program_key' => 'glade_cbe_accounting_bills', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'Bills', 'route_name' => 'cbe.accounting.bills'],
            ['program_key' => 'glade_cbe_accounting_debit_notes', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Credit Notes', 'route_name' => 'cbe.accounting.debit-notes'],
            ['program_key' => 'glade_cbe_accounting_ap_debit_notes', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Debit Notes', 'route_name' => 'cbe.accounting.ap-debit-notes'],
            ['program_key' => 'glade_cbe_accounting_payment_voucher', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'Payment Voucher', 'route_name' => 'cbe.accounting.payment-voucher'],
            ['program_key' => 'glade_cbe_accounting_ap_adjustments', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Adjustments', 'route_name' => 'cbe.accounting.ap-adjustments'],
            ['program_key' => 'glade_cbe_accounting_ap_refunds', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Refunds', 'route_name' => 'cbe.accounting.ap-refunds'],
            ['program_key' => 'glade_cbe_accounting_ap_opening_balances', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Opening Balances', 'route_name' => 'cbe.accounting.ap-opening-balances'],
            ['program_key' => 'glade_cbe_accounting_purchase_requests', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'Purchase Requests', 'route_name' => 'cbe.accounting.purchase-requests'],
            ['program_key' => 'glade_cbe_accounting_bill_enquiry', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'Bill Enquiry', 'route_name' => 'cbe.accounting.bill-enquiry'],
            ['program_key' => 'glade_cbe_accounting_ap_payment_enquiry', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Payment Enquiry', 'route_name' => 'cbe.accounting.ap-payment-enquiry'],
            ['program_key' => 'glade_cbe_accounting_ap_outstanding_balance_enquiry', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Outstanding Balance Enquiry', 'route_name' => 'cbe.accounting.ap-outstanding-balance-enquiry'],
            ['program_key' => 'glade_cbe_accounting_reports_ap_aging', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Aging', 'route_name' => 'cbe.accounting.reports.ap-aging'],
            ['program_key' => 'glade_cbe_accounting_reports_ap_reports', 'portal' => 'GLADE', 'section' => 'Accounts Payable', 'label' => 'AP Reports Hub', 'route_name' => 'cbe.accounting.reports.ap-reports'],
            ['program_key' => 'glade_cbe_accounting_account_groups', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Account Groups', 'route_name' => 'cbe.accounting.account-groups'],
            ['program_key' => 'glade_cbe_accounting_journal_types', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Journal Types', 'route_name' => 'cbe.accounting.journal-types'],
            ['program_key' => 'glade_cbe_accounting_cost_centres', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Cost Centres', 'route_name' => 'cbe.accounting.cost-centres'],
            ['program_key' => 'glade_cbe_accounting_opening_balances', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Opening Balances', 'route_name' => 'cbe.accounting.opening-balances'],
            ['program_key' => 'glade_cbe_accounting_periods', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Periods', 'route_name' => 'cbe.accounting.periods'],
            ['program_key' => 'glade_cbe_accounting_journal_vouchers', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Journal Vouchers', 'route_name' => 'cbe.accounting.journal-vouchers'],
            ['program_key' => 'glade_cbe_accounting_adjustment_journals', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Adjustment Journals', 'route_name' => 'cbe.accounting.adjustment-journals'],
            ['program_key' => 'glade_cbe_accounting_accrual_journals', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Accrual Journals', 'route_name' => 'cbe.accounting.accrual-journals'],
            ['program_key' => 'glade_cbe_accounting_recurring_journal_templates', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Recurring Journal Templates', 'route_name' => 'cbe.accounting.recurring-journal-templates'],
            ['program_key' => 'glade_cbe_accounting_chart_of_accounts_enquiry', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Chart of Accounts Enquiry', 'route_name' => 'cbe.accounting.chart-of-accounts-enquiry'],
            ['program_key' => 'glade_cbe_accounting_general_ledger_enquiry', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'General Ledger Enquiry', 'route_name' => 'cbe.accounting.general-ledger-enquiry'],
            ['program_key' => 'glade_cbe_accounting_journal_enquiry', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Journal Enquiry', 'route_name' => 'cbe.accounting.journal-enquiry'],
            ['program_key' => 'glade_cbe_accounting_trial_balance_enquiry', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Trial Balance Enquiry', 'route_name' => 'cbe.accounting.trial-balance-enquiry'],
            ['program_key' => 'glade_cbe_accounting_transaction_history', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Transaction History', 'route_name' => 'cbe.accounting.transaction-history'],
            ['program_key' => 'glade_cbe_accounting_integration_status', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Integration Status', 'route_name' => 'cbe.accounting.integration-status'],
            ['program_key' => 'glade_cbe_accounting_reports_trial_balance', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Trial Balance (report)', 'route_name' => 'cbe.accounting.reports.trial-balance'],
            ['program_key' => 'glade_cbe_accounting_reports_balance_sheet', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Balance Sheet', 'route_name' => 'cbe.accounting.reports.balance-sheet'],
            ['program_key' => 'glade_cbe_accounting_reports_profit_loss', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Profit & Loss', 'route_name' => 'cbe.accounting.reports.profit-loss'],
            ['program_key' => 'glade_cbe_accounting_reports_general_ledger', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'General Ledger Report', 'route_name' => 'cbe.accounting.reports.general-ledger'],
            ['program_key' => 'glade_cbe_accounting_reports_journal_export', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'Journal Export', 'route_name' => 'cbe.accounting.reports.journal-export'],
            ['program_key' => 'glade_cbe_accounting_reports_gl_reports', 'portal' => 'GLADE', 'section' => 'General Ledger', 'label' => 'GL Reports Hub', 'route_name' => 'cbe.accounting.reports.gl-reports'],
            ['program_key' => 'glade_cbe_accounting_fixed_assets', 'portal' => 'GLADE', 'section' => 'Fixed Assets Register', 'label' => 'Fixed Assets', 'route_name' => 'cbe.accounting.fixed-assets'],
            ['program_key' => 'glade_cbe_accounting_asset_enquiry', 'portal' => 'GLADE', 'section' => 'Fixed Assets Register', 'label' => 'Asset Enquiry', 'route_name' => 'cbe.accounting.asset-enquiry'],
            ['program_key' => 'glade_cbe_accounting_fixed_asset_reports_hub', 'portal' => 'GLADE', 'section' => 'Fixed Assets Register', 'label' => 'FA Reports Hub', 'route_name' => 'cbe.accounting.fixed-asset-reports-hub'],
            ['program_key' => 'glade_cbe_accounting_reports_fixed_asset_schedule', 'portal' => 'GLADE', 'section' => 'Fixed Assets Register', 'label' => 'Fixed Asset Schedule', 'route_name' => 'cbe.accounting.reports.fixed-asset-schedule'],
            ['program_key' => 'glade_cbe_accounting_reports_fixed_asset_disposal_listing', 'portal' => 'GLADE', 'section' => 'Fixed Assets Register', 'label' => 'Fixed Asset Disposal Listing', 'route_name' => 'cbe.accounting.reports.fixed-asset-disposal-listing'],
            ['program_key' => 'glade_cbe_accounting_reports_depreciation_listing', 'portal' => 'GLADE', 'section' => 'Fixed Assets Register', 'label' => 'Depreciation Listing', 'route_name' => 'cbe.accounting.reports.depreciation-listing'],
            ['program_key' => 'glade_cbe_accounting_fixed_asset_audit_log', 'portal' => 'GLADE', 'section' => 'Fixed Assets Register', 'label' => 'Fixed Asset Audit Log', 'route_name' => 'cbe.accounting.fixed-asset-audit-log'],
            ['program_key' => 'glade_cbe_accounting_bank_reconciliations', 'portal' => 'GLADE', 'section' => 'Bank Reconciliation', 'label' => 'Bank Reconciliation', 'route_name' => 'cbe.accounting.bank-reconciliations'],
            ['program_key' => 'glade_cbe_accounting_bank_account_enquiry', 'portal' => 'GLADE', 'section' => 'Bank Reconciliation', 'label' => 'Bank Account Enquiry', 'route_name' => 'cbe.accounting.bank-account-enquiry'],
            ['program_key' => 'glade_cbe_accounting_bank_transaction_enquiry', 'portal' => 'GLADE', 'section' => 'Bank Reconciliation', 'label' => 'Bank Transaction Enquiry', 'route_name' => 'cbe.accounting.bank-transaction-enquiry'],
            ['program_key' => 'glade_cbe_accounting_bank_reconciliation_enquiry', 'portal' => 'GLADE', 'section' => 'Bank Reconciliation', 'label' => 'Bank Reconciliation Enquiry', 'route_name' => 'cbe.accounting.bank-reconciliation-enquiry'],
            ['program_key' => 'glade_cbe_accounting_unmatched_transaction_enquiry', 'portal' => 'GLADE', 'section' => 'Bank Reconciliation', 'label' => 'Unmatched Transaction Enquiry', 'route_name' => 'cbe.accounting.unmatched-transaction-enquiry'],
            ['program_key' => 'glade_cbe_accounting_bank_reconciliation_reports_hub', 'portal' => 'GLADE', 'section' => 'Bank Reconciliation', 'label' => 'Bank Reconciliation Reports Hub', 'route_name' => 'cbe.accounting.bank-reconciliation-reports-hub'],
            ['program_key' => 'glade_cbe_ai_accounting_index', 'portal' => 'GLADE', 'section' => 'AI Accounting Automation', 'label' => 'Batches', 'route_name' => 'cbe.ai-accounting.index'],
            ['program_key' => 'glade_cbe_ai_accounting_batches_create', 'portal' => 'GLADE', 'section' => 'AI Accounting Automation', 'label' => 'Upload', 'route_name' => 'cbe.ai-accounting.batches.create'],
            ['program_key' => 'glade_cbe_ai_accounting_rules', 'portal' => 'GLADE', 'section' => 'AI Accounting Automation', 'label' => 'Rules', 'route_name' => 'cbe.ai-accounting.rules'],
            ['program_key' => 'glade_cbe_ai_accounting_exceptions', 'portal' => 'GLADE', 'section' => 'AI Accounting Automation', 'label' => 'Exceptions', 'route_name' => 'cbe.ai-accounting.exceptions'],
            ['program_key' => 'glade_cbe_ai_accounting_audit_log', 'portal' => 'GLADE', 'section' => 'AI Accounting Automation', 'label' => 'AI Audit Log', 'route_name' => 'cbe.ai-accounting.audit-log'],
            ['program_key' => 'glade_cbe_finance_index', 'portal' => 'GLADE', 'section' => 'Cash & Bank Management', 'label' => 'Daily Transactions', 'route_name' => 'cbe.finance.index'],
            ['program_key' => 'glade_cbe_accounting_petty_cash_funds', 'portal' => 'GLADE', 'section' => 'Cash & Bank Management', 'label' => 'Petty Cash', 'route_name' => 'cbe.accounting.petty-cash-funds'],
            ['program_key' => 'glade_cbe_finance_transfers_create', 'portal' => 'GLADE', 'section' => 'Cash & Bank Management', 'label' => 'Bank Transfer', 'route_name' => 'cbe.finance.transfers.create'],
            ['program_key' => 'glade_cbe_accounting_reports_cash_bank_position', 'portal' => 'GLADE', 'section' => 'Cash & Bank Management', 'label' => 'Cash & Bank Position', 'route_name' => 'cbe.accounting.reports.cash-bank-position'],
            ['program_key' => 'glade_cbe_accounting_reports_cash_flow_statement', 'portal' => 'GLADE', 'section' => 'Financial Reporting', 'label' => 'Cash Flow Statement', 'route_name' => 'cbe.accounting.reports.cash-flow-statement'],
            ['program_key' => 'glade_cbe_accounting_reports_monthly_financial_summary', 'portal' => 'GLADE', 'section' => 'Financial Reporting', 'label' => 'Monthly Financial Summary', 'route_name' => 'cbe.accounting.reports.monthly-financial-summary'],
            ['program_key' => 'glade_cbe_accounting_year_end_closing_pack', 'portal' => 'GLADE', 'section' => 'Financial Reporting', 'label' => 'Year-End Pack', 'route_name' => 'cbe.accounting.year-end-closing.pack'],
            ['program_key' => 'glade_cbe_accounting_year_end_closing', 'portal' => 'GLADE', 'section' => 'Year-End Closing', 'label' => 'Year-End Closing', 'route_name' => 'cbe.accounting.year-end-closing'],
            ['program_key' => 'glade_cbe_accounting_reports_prior_year_comparison', 'portal' => 'GLADE', 'section' => 'Year-End Closing', 'label' => 'Prior Year Comparison', 'route_name' => 'cbe.accounting.reports.prior-year-comparison'],
            ['program_key' => 'glade_cbe_accounting_approvals', 'portal' => 'GLADE', 'section' => 'Audit Trail & Internal Control', 'label' => 'Approvals', 'route_name' => 'cbe.accounting.approvals'],
            ['program_key' => 'glade_cbe_accounting_reports_office_bearer_list', 'portal' => 'GLADE', 'section' => 'ROS / Regulatory Reporting', 'label' => 'Office Bearer List', 'route_name' => 'cbe.accounting.reports.office-bearer-list'],
            ['program_key' => 'glade_cbe_accounting_ros_submission_checklist', 'portal' => 'GLADE', 'section' => 'ROS / Regulatory Reporting', 'label' => 'ROS Submission Checklist', 'route_name' => 'cbe.accounting.ros-submission-checklist'],
            ];

            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('program_catalog')->insert(array_map(function ($r) use ($now) {
                    return [
                        'id' => (string) Str::uuid(),
                        'program_key' => $r['program_key'],
                        'portal' => $r['portal'],
                        'section' => $r['section'] !== '' ? $r['section'] : null,
                        'label' => $r['label'],
                        'route_name' => $r['route_name'],
                        'is_paid' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $chunk));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('program_catalog');
    }
};
