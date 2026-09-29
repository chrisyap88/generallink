<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GLADE')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <style>
        {{-- NEW 27 Aug 2026 — minimal GLADE-only layout for the /glade
             CBE front door (Task #240). Same CSS variables, font, and
             exact 46px topbar height as layouts/dashboard.blade.php so
             every existing CBE KPI/exec-dashboard view (which already
             hardcodes calc(100vh - 46px) for its own scroll area) needs
             zero internal changes — only its @extends() line switches
             based on session('portal').
             UPDATED 27 Aug 2026 — per Chris: (1) reuse the SAME sidebar
             menu structure/section headers/item descriptions/icons as
             cbe/ecosystem-home.blade.php's own sidebar (same __('cbe.sb_*')
             labels), but only give a real href to items that are both
             built AND relevant to the account that's logged in — an
             Admin oversees ALL CBE groups (no single node), so Records &
             Reports / Events & Donations (which belong to ONE node) stay
             unwired "soon" items for Admin, same as they'd correctly show
             for a brand-new CBE member who hasn't been given those
             permissions yet. An officer (has a cbe_node_officers row)
             gets every item wired for real.
             REVERTED 28 Aug 2026 — per Chris: "change back cbe login
             dashboard back to blue, follow back generallink ... i dont
             like the green color" — back to the exact same blue palette
             as layouts/dashboard.blade.php (--gl-blue:#1565C0 etc.), so
             GLADE now looks identical to the main portal. --}}
        {{-- WIDENED 10 Sep 2026 (Task #401) — per Chris: "the word
             Administration Management is too close to the border" —
             200px left the longest nav-parent label (25 characters,
             uppercase, bold) almost touching the sidebar's right edge
             where the ▼ arrow sits. One variable drives #sidebar,
             #topbar, and #main, so this single change re-aligns the
             whole layout consistently. --}}
        :root{--gl-blue:#1565C0;--gl-blue2:#1E88E5;--gl-cyan:#00BCD4;--gl-cyan2:#B2EBF2;--gl-light:#E0F7FA;--sidebar-w:236px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f9ff;margin:0;padding:0;min-height:100vh;color:#263238;overflow-x:hidden;}

        #sidebar{position:fixed;top:0;left:0;height:100vh;width:var(--sidebar-w);background:linear-gradient(180deg,#1565C0 0%,#1976D2 60%,#1E88E5 100%);display:flex;flex-direction:column;z-index:100;}
        .glade-sidebar-logo{flex-shrink:0;padding:10px 16px 8px;border-bottom:1px solid rgba(255,255,255,0.15);background:rgba(0,0,0,0.08);}
        .glade-sidebar-logo h1{color:#FFFFFF;font-size:9.5px;font-weight:800;letter-spacing:0.2px;line-height:1.2;white-space:nowrap;}
        .glade-sidebar-logo p{color:rgba(255,255,255,0.8);font-size:9px;font-weight:600;margin-top:1px;letter-spacing:0.5px;}
        .sidebar-nav{flex:1;min-height:0;overflow-y:auto;padding:6px 0 8px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,0.35) transparent;}
        .sidebar-nav::-webkit-scrollbar{width:4px;}
        .sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.35);border-radius:4px;}
        {{-- UPDATED 28 Aug 2026 — per Chris: "the white color no bold" —
             reverted the 27 Aug bold-everything change below; sidebar item
             text is white at normal weight again. Section headers
             (.nav-parent) keep their own bold/uppercase treatment since
             those are headings, not item labels, and Chris did not object
             to those. --}}
        .sb-section{font-size:9px;font-weight:800;color:rgba(255,255,255,.85);text-transform:uppercase;letter-spacing:.05em;margin:10px 14px 4px;}
        .sb-section:first-child{margin-top:4px;}
        .sb-item{display:flex;align-items:center;gap:7px;padding:5px 14px;font-size:11px;color:#FFFFFF;font-weight:400;text-decoration:none;border-left:3px solid transparent;white-space:nowrap;}
        a.sb-item:hover{background:rgba(255,255,255,.14);border-left-color:rgba(255,255,255,.5);}
        a.sb-item.active{background:rgba(255,255,255,.18);border-left-color:#FFFFFF;}
        .sb-item.soon{color:rgba(255,255,255,.55);cursor:default;}
        .sb-item .tag{margin-left:auto;font-size:8px;border-radius:8px;padding:1px 6px;font-weight:800;flex-shrink:0;}
        .sb-item.live .tag{background:#FFFFFF;color:#1565C0;}
        .sb-item.soon .tag{background:rgba(255,255,255,.2);color:rgba(255,255,255,.75);}
        .sb-item .badge{margin-left:auto;background:#F44336;color:#FFFFFF;border-radius:20px;min-width:15px;height:15px;padding:0 4px;font-size:8.5px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

        {{-- NEW 28 Aug 2026 — collapsible group headers + Prev button,
             same mechanism as layouts/dashboard.blade.php: only ONE
             group's items show at a time, so the sidebar never needs to
             scroll no matter how many groups exist. --}}
        {{-- FIXED 10 Sep 2026 (Task #404) — per Chris: "no truncated labels"
             — category headers longer than the sidebar width used to be
             cut off (white-space:nowrap + fixed width = hidden overflow).
             Now wraps onto a 2nd line instead; the label div is a flex
             item that's allowed to shrink/wrap while the arrow stays put
             at the top-right via align-items:flex-start. --}}
        .nav-parent{display:flex;align-items:flex-start;justify-content:space-between;gap:6px;padding:7px 14px;color:rgba(255,255,255,.85);font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;cursor:pointer;transition:all .15s;border-left:3px solid transparent;user-select:none;line-height:1.35;}
        .nav-parent>div:first-child{flex:1;min-width:0;}
        .nav-parent:hover{background:rgba(255,255,255,.08);color:#fff;}
        .nav-parent.open{color:#fff;background:rgba(255,255,255,.06);}
        .nav-parent .parrow{font-size:9px;transition:transform .25s;opacity:.6;flex-shrink:0;margin-left:6px;margin-top:1px;}
        .nav-parent.open .parrow{transform:rotate(180deg);opacity:1;}
        #sidebar.focused #sidebarNav>.nav-parent:not(.open){display:none;}
        .nav-submenu{max-height:0;overflow:hidden;transition:max-height .3s ease;}
        .nav-submenu.open{max-height:2000px;}
        {{-- NEW 2 Sep 2026 — per Chris: clicking Financial Accounting
             Module must show ONLY the 6 category names (AR/AP/GL/Fixed
             Assets/Bank Reconciliation/Fund Accounting); clicking a
             category then reveals only its own programs. Same
             nav-parent/nav-submenu accordion mechanism, nested one level
             deeper with extra left indent so it reads as a sub-level. --}}
        {{-- TIGHTENED 10 Sep 2026 (Task #405) — per Chris: "display in one
             screen ... no scroll ... row width reduce" — the Financial
             Accounting Module list now has 12 categories (Master Files
             added in Task #404) and was overflowing, forcing a scroll.
             Shorter vertical padding + slightly smaller text on this row
             type only (category headers) buys back enough height for all
             12 to fit without scrolling on ordinary screens. --}}
        .nav-parent-sub{padding-top:5px;padding-bottom:5px;padding-left:26px;font-size:8.5px;line-height:1.25;}
        {{-- TIGHTENED 10 Sep 2026 (Task #408) — per Chris: "put all master
             file in one screen no scroll" — Master File Maintenance alone
             lists 17 screens, and every other category (AR/AP/GL etc.)
             has similarly long lists, all sharing this same row style.
             Shorter vertical padding + smaller text buys back enough
             height for the longest lists to fit without scrolling. --}}
        .sb-item-sub{padding-top:3px;padding-bottom:3px;padding-left:24px;font-size:9.5px;line-height:1.3;}
        .sidebar-prev-btn{display:none;flex-shrink:0;align-items:center;gap:6px;padding:9px 14px;color:#fff;font-size:10.5px;font-weight:700;cursor:pointer;background:rgba(0,0,0,.15);border-top:1px solid rgba(255,255,255,.15);user-select:none;transition:background .15s;}
        .sidebar-prev-btn:hover{background:rgba(0,0,0,.25);}
        .sidebar-prev-btn i{font-size:14px;}
        #sidebar.focused .sidebar-prev-btn{display:flex;}
        {{-- NEW 2 Sep 2026 — per Chris: "when i click Account receivable
             all this module should no appear, same goes to other module,
             ONLY show programs related to the module." The outer rule on
             line 70 only hides TOP-LEVEL .nav-parent siblings (direct
             children of #sidebarNav) when focused — it never reached the
             6 category headers nested inside #finMenu, so clicking AR
             still left AP/GL/Fixed Assets/Bank Reconciliation/Fund
             Accounting visible as headers. This mirrors that same
             hide-siblings mechanism one level deeper: #finMenu gets its
             own .sub-focused class (set by toggleFinSub() below) so only
             the clicked category's header + programs remain, with its own
             Prev row to come back to the 6-category list without leaving
             Financial Accounting Module entirely. --}}
        #finMenu.sub-focused>.nav-parent-sub:not(.open){display:none;}
        .sidebar-prev-btn-sub{display:none;flex-shrink:0;align-items:center;gap:6px;padding:8px 14px 8px 26px;color:rgba(255,255,255,.85);font-size:9.5px;font-weight:700;cursor:pointer;background:rgba(0,0,0,.1);user-select:none;transition:background .15s;}
        .sidebar-prev-btn-sub:hover{background:rgba(0,0,0,.2);color:#fff;}
        .sidebar-prev-btn-sub i{font-size:13px;}
        #finMenu.sub-focused .sidebar-prev-btn-sub{display:flex;}
        {{-- NEW 13 Sep 2026 (Task #418 follow-up) — per Chris: "financial
             master file or vendor master file ... change as sub menu" —
             same nested-accordion mechanism as #finMenu above, scoped to
             #mfmMenu (Master File Maintenance) instead, via its own
             MFM_SUB_IDS/toggleMfmSub() JS below. --}}
        #mfmMenu.sub-focused>.nav-parent-sub:not(.open){display:none;}
        #mfmMenu.sub-focused .sidebar-prev-btn-sub{display:flex;}
        {{-- FIXED 13 Sep 2026 (Task #418 follow-up) — per Chris: "when i
             click financial master file it collapse and only show
             financial master file program[s], same with vendor master
             file" — the rule above only hides the OTHER accordion header
             (Vendor Master File / Financial Master File), but mfmMenu also
             has plain links sitting alongside them (Group Name &
             Hierarchy Levels, GLADE Membership Tiers, Faith Types,
             Program Library, Reason Code, Partner API Keys) that were
             still left showing. This hides those too while a Financial/
             Vendor sub-menu is focused, so ONLY that program list shows —
             matching how opening AR hides every other Financial
             Accounting Module category, not just the other headers. --}}
        #mfmMenu.sub-focused>a.sb-item{display:none;}
        {{-- FIXED 13 Sep 2026 (Task #418 follow-up) — per Chris: "why the
             font for financial and vendor is so small not same as other
             fonts" — fmfSubParent/vmfSubParent were given the same
             .nav-parent-sub styling as the AR/AP/GL category headers
             inside Financial Accounting Module (8.5px, tightened padding),
             which only exists there to squeeze 12 categories onto one
             no-scroll screen (Task #405). Master File Maintenance's list
             is much shorter — Financial/Vendor Master File sit right
             alongside plain 11px items (Group Name & Hierarchy Levels,
             GLADE Membership Tiers, Faith Types, Program Library), so they
             need to match THOSE, not the cramped AR/AP style. Overriding
             back to the same font/weight/case/padding as every other item
             in this list, keeping only the expand arrow to show they're
             collapsible. --}}
        #groupSetupSubParent, #fmfSubParent, #vmfSubParent{font-size:11px;font-weight:400;text-transform:none;letter-spacing:normal;padding:5px 14px;line-height:1.4;}
        #groupSetupSubParent .parrow, #fmfSubParent .parrow, #vmfSubParent .parrow{font-size:9px;}
        {{-- FIXED 13 Sep 2026 (Task #418 follow-up) — per Chris: "ONLY show
             the financial masterfile on top of the menu NO other programs"
             — hides the bold "MASTER FILE MAINTENANCE" header itself
             (toggled via JS in toggleMfmSub()/closeMfmSubFocus() below)
             while Financial or Vendor Master File is focused, so that
             header becomes the only thing showing at the top instead of
             sitting above it. --}}
        #mfmParent.mfm-header-hidden{display:none;}
        {{-- NEW 17 Sep 2026 — same nested-accordion CSS as #mfmMenu
             above, scoped to #membershipMenu for the 4 Secretarial
             Management groups (Administration/Donor Management/
             Consultant & Practitioner/Membership Management). --}}
        #membershipMenu.sub-focused>.nav-parent-sub:not(.open){display:none;}
        #membershipMenu.sub-focused>a.sb-item{display:none;}
        #membershipMenu.sub-focused .sidebar-prev-btn-sub{display:flex;}
        #membershipParent.mship-header-hidden{display:none;}

        {{-- NEW 16 Sep 2026 — per Chris: "create a sub menu AI Power
             Accounting ... same create bank reconciliation sub module" —
             two collapsible groups nested one level INSIDE cashBankSubMenu
             (itself already a level-2 accordion under Financial
             Accounting Module). Deliberately kept as a plain
             expand/collapse only — no "sub-focused hide-everything"
             mode is added at this 3rd level, so the 5 plain items above
             (Bank Accounts Master, Daily Transactions, Petty Cash, Bank
             Transfer, Cash & Bank Position) and both group headers stay
             visible at all times; that keeps the accordion-state logic
             simple rather than stacking a 3rd copy of the sibling/header-
             hiding mechanism on top of the 2 that already exist
             (toggleFinSub/toggleMfmSub). --}}
        .nav-parent-subsub{font-size:9.5px;font-weight:400;text-transform:none;letter-spacing:normal;padding:3px 8px 3px 24px;line-height:1.3;}
        .nav-parent-subsub .parrow{font-size:8px;}
        .sb-item-subsub{padding-left:34px;}

        {{-- NEW 28 Aug 2026 — per Chris: "why no log out button follow
             back the DSG and ORG Dashboard style la" — same agent-card +
             logout footer as layouts/dashboard.blade.php, same CSS class
             names/values, just at the bottom of the GLADE sidebar. --}}
        .sidebar-footer{flex-shrink:0;padding:8px 16px;border-top:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.1);}
        .agent-card{display:flex;align-items:center;gap:10px;}
        .agent-avatar{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);}
        .agent-name{color:#fff;font-size:10.5px;font-weight:500;}
        .agent-code{color:rgba(255,255,255,0.45);font-size:9.5px;}

        #topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:46px;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-bottom:1px solid var(--gl-cyan2);display:flex;align-items:center;justify-content:space-between;padding:0 16px;z-index:90;box-shadow:0 2px 10px rgba(0,150,200,0.08);}
        .glade-wordmark{font-weight:800;font-size:14px;letter-spacing:.06em;color:var(--gl-blue);margin-right:14px;}
        .topbar-title{font-size:13px;font-weight:600;color:#546E7A;}
        .topbar-right{display:flex;align-items:center;gap:10px;}
        .topbar-btn{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--gl-blue);background:var(--gl-light);border:1px solid var(--gl-cyan2);cursor:pointer;font-size:16px;position:relative;text-decoration:none;transition:all 0.15s;}
        .topbar-btn:hover{background:var(--gl-cyan2);color:var(--gl-blue);}
        .badge-count{position:absolute;top:-4px;right:-4px;width:14px;height:14px;border-radius:50%;background:#F44336;color:#FFFFFF;font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;}
        #main{position:absolute;top:46px;left:var(--sidebar-w);right:0;bottom:0;background:var(--gl-light);overflow:hidden;}

        {{-- FIXED 13 Sep 2026 (Task #418 follow-up) — per Chris: this is a
             desktop business app, the sidebar must never disappear, and
             there was no button to bring it back if it did. Breakpoint
             dropped from 900px to 480px so it only affects screens far
             too narrow to use this app on regardless. --}}
        @media(max-width:480px){#sidebar{transform:translateX(-100%);}#topbar{left:0;}#main{left:0;}}
    </style>
</head>
<body>
@php($agent = auth('agent')->user())
@php($isAdmin = $agent && $agent->role === 'ADMIN')
@php($officerRow = $isAdmin ? null : \Illuminate\Support\Facades\DB::table('cbe_node_officers')->where('agent_id', $agent->agent_id)->where('is_active', true)->first())
@php($isOfficer = (bool) $officerRow)
@php($officerNodeId = $officerRow->node_id ?? null)
{{-- NEW 27 Aug 2026 — Hierarchy Node Maintenance (Temple/Branch/State/
     HQ) needs a CBE Group to work within. An officer's own community is
     derived here (never shown a picker over every other CBE community
     on the platform, same privacy boundary as the rest of GLADE); Admin
     gets the full group-picker screen since they oversee all of them. --}}
@php($officerGroupId = $officerNodeId ? \Illuminate\Support\Facades\DB::table('cbe_hierarchy_nodes')->where('node_id', $officerNodeId)->value('group_label_id') : null)
@php($langShortMap = ['EN' => 'ENG', 'MS' => 'BM', 'ZH' => 'CHI'])
@php($langShort = $langShortMap[$agent->preferred_language ?? 'EN'] ?? 'ENG')
{{-- NEW 27 Aug 2026 — per Chris: "all the blue color dashboard program
     that related to Glade dashboard must bring over like help desk
     survey ... I say only not related program no need to bring over."
     Same badge-count queries as layouts/dashboard.blade.php, reused
     as-is (Help Desk/Notice Board are shared, group-agnostic features —
     not DSG/ORG-specific — so both Admin and a CBE officer see them). --}}
@php($helpDeskUnreadCount = \Illuminate\Support\Facades\DB::table('help_desk_threads')
    ->where(function ($q) use ($agent, $isAdmin) {
        $q->where(function ($q2) use ($agent) {
            $q2->where('initiator_agent_id', $agent->agent_id)
               ->where(function ($q3) {
                   $q3->whereNull('last_viewed_by_initiator_at')
                      ->orWhereColumn('last_viewed_by_initiator_at', '<', 'last_message_at');
               });
        })->orWhere(function ($q2) use ($agent) {
            $q2->where('recipient_agent_id', $agent->agent_id)
               ->where(function ($q3) {
                   $q3->whereNull('last_viewed_by_recipient_at')
                      ->orWhereColumn('last_viewed_by_recipient_at', '<', 'last_message_at');
               });
        });
        if ($isAdmin) {
            $q->orWhere(function ($q2) {
                $q2->where('created_by_type', 'CAROLYN_AI')->where('status', 'OPEN');
            });
        }
    })->count())
@php($noticeUnreadCount = 0)
@if(!$isAdmin)
    @php($noticeUnreadCount = \Illuminate\Support\Facades\DB::table('notices as n')
        ->leftJoin('notice_reads as r', function ($join) use ($agent) {
            $join->on('n.notice_id', '=', 'r.notice_id')->where('r.agent_id', '=', $agent->agent_id);
        })
        ->where('n.is_deleted', false)
        ->where(function ($q) {
            $q->whereNull('n.expires_at')->orWhere('n.expires_at', '>=', now()->toDateString());
        })
        ->whereNull('r.read_id')
        ->count())
@endif
@php($profileRoute = match($agent->role) {
    'ADMIN'        => route('admin.profile.show'),
    'GROUP_LEADER' => route('gl.profile.show'),
    'TEAM_LEADER'  => route('tl.profile.show'),
    default        => route('introducer.profile.show'),
})
{{-- NEW 28 Aug 2026 — per Chris: "organize your menu presentation like
     Generallink dashboard NO scroll and click prev menu." Rebuilt the
     sidebar to use the same collapsible nav-parent/nav-submenu + Prev
     button pattern as layouts/dashboard.blade.php instead of one long
     always-expanded list. Each group's "active" flag decides whether it
     auto-opens on page load (mirrors the exact same $xxxActive pattern
     used in the blue dashboard). --}}
{{-- REBUILT 28 Aug 2026 — per Chris: "your customer relationship does not
     have Customer KPI? you should put it there and help desk, support
     ticket, then you have to create a Marketing Menu where campaign,
     bulletin, broadcasting all should be here... transfer the entire
     business menu to membership module." Business, Communication, and
     Growth & Outreach Center are all dissolved here — see the comments
     on Customer Relationship, Marketing Menu, Records & Reports, and
     Membership Module below for exactly where each item landed
     (confirmed 1-by-1 with Chris). --}}
{{-- UPDATED 10 Sep 2026 (Task #401) — per Chris: "transfer minutes and
     Records and Report put under Administration Management" — Records &
     Reports no longer exists as its own section; Meeting Minutes and
     Activities moved into Administration Management below. Bank
     Statement and Annual Report were dropped from the menu entirely —
     per Chris ("is it already place in GL?"), confirmed by reading both
     controllers: the old "Bank Statement" screen (manual PDF upload +
     a separate cbe_transactions log, 22 Aug 2026) is superseded by the
     Financial Accounting Module's Bank Reconciliation + AI Accounting
     Automation (which actually posts to the real ledger); the old
     "Annual Report" screen's Income & Expenditure figures come from
     that same legacy cbe_transactions table, and are a strict subset of
     the Year-End Closing "AGM / ROS Reporting Pack" (Secretary Report +
     Income & Expenditure + Balance Sheet + Trial Balance, from the real
     GL). Routes/controllers/data untouched — only removed from
     navigation, so nothing already on file is lost. --}}
{{-- $adminMgmtActive removed 17 Sep 2026 — folded into the 5 Secretarial Management sub-groups below (now $secMeetingsSubActive/$secNoticesSubActive/etc, split further later that day per Chris). --}}
{{-- NEW 28 Aug 2026 — per Chris: "under this menu you should link the
     Financial Accounting Module... then click AR is all the programs
     related, Entry, Enquiry, Reports... transfer all main Menu Master
     Maintenance program to Finance Module and Membership." The old
     single "Accounting" link (Records & Reports) and the old Master File
     Maintenance section are both dissolved into these two new sections —
     see the two per-item comments below for exactly which item went
     where and why (Chris answered directly for Donor/Entity Maintenance,
     Group Name & Hierarchy Levels). No Accounts Receivable module exists
     yet (donor/event money-in is tracked as Contributions/Receipts, not
     a formal AR ledger) — only Accounts Payable + General Ledger are
     real today, so only those two show here. --}}
{{-- REBUILT 2 Sep 2026 — per Chris: "you follow back the original phase 1
     program placement accordingly... put back every to the respective
     module as you propose earlier." Reverted from the 6-category
     collapse back to Chris's own original Phase 1 module list (10
     modules) — every item now sits under the exact module he named it
     under, including the reports/screens he deliberately cross-listed
     under 2 modules (e.g. Trial Balance under both General Ledger and
     Financial Reporting; Period Lock under General Ledger, Year-End
     Closing AND Audit Trail). Each route's *auto-expand* flag below is
     only ever claimed by ONE module (its most natural home) even where
     the link itself appears in 2+ submenus, so opening a shared report
     doesn't pop two categories open at once. --}}
@php($glSubActive = request()->routeIs('cbe.accounting.journal-vouchers*') || request()->routeIs('cbe.accounting.adjustment-journals*') || request()->routeIs('cbe.accounting.accrual-journals*') || request()->routeIs('cbe.accounting.recurring-journal-templates*') || request()->routeIs('cbe.accounting.chart-of-accounts-enquiry*') || request()->routeIs('cbe.accounting.general-ledger-enquiry*') || request()->routeIs('cbe.accounting.journal-enquiry*') || request()->routeIs('cbe.accounting.trial-balance-enquiry*') || request()->routeIs('cbe.accounting.transaction-history*') || request()->routeIs('cbe.accounting.integration-status*') || request()->routeIs('cbe.accounting.reports.trial-balance') || request()->routeIs('cbe.accounting.reports.balance-sheet') || request()->routeIs('cbe.accounting.reports.profit-loss') || request()->routeIs('cbe.accounting.reports.general-ledger') || request()->routeIs('cbe.accounting.reports.journal-export') || request()->routeIs('cbe.accounting.reports.gl-reports') || request()->routeIs('cbe.accounting.reports.bank-reconciliation-gl') || request()->routeIs('cbe.accounting.reports.journal-listing') || request()->routeIs('cbe.accounting.reports.gl-account-balance') || request()->routeIs('cbe.accounting.reports.gl-monthly-summary') || request()->routeIs('cbe.accounting.reports.unposted-journals') || request()->routeIs('cbe.accounting.reports.reversal-listing') || request()->routeIs('cbe.accounting.reports.adjustment-journal-listing'))
@php($glEntriesGroupActive = request()->routeIs('cbe.accounting.journal-vouchers*') || request()->routeIs('cbe.accounting.adjustment-journals*') || request()->routeIs('cbe.accounting.accrual-journals*') || request()->routeIs('cbe.accounting.recurring-journal-templates*'))
@php($glEnquiryGroupActive = request()->routeIs('cbe.accounting.chart-of-accounts-enquiry*') || request()->routeIs('cbe.accounting.general-ledger-enquiry*') || request()->routeIs('cbe.accounting.journal-enquiry*') || request()->routeIs('cbe.accounting.trial-balance-enquiry*') || request()->routeIs('cbe.accounting.transaction-history*') || request()->routeIs('cbe.accounting.integration-status*'))
@php($glReportsGroupActive = request()->routeIs('cbe.accounting.reports.trial-balance') || request()->routeIs('cbe.accounting.reports.balance-sheet') || request()->routeIs('cbe.accounting.reports.profit-loss') || request()->routeIs('cbe.accounting.reports.general-ledger') || request()->routeIs('cbe.accounting.reports.journal-export') || request()->routeIs('cbe.accounting.reports.gl-reports'))
{{-- RESTRUCTURED 16 Sep 2026 — per Chris: "centralised all bank related
     process in one menu ... cash and bank management" — Bank
     Reconciliation and AI Accounting Automation used to be their own
     separate sidebar sections (and "Bank Account Number" appeared under
     3 different menus, all pointing at the exact same screen, which
     read as 3 duplicate programs). $brSubActive and $aiAutomationSubActive
     are folded into this one flag; the old individual flags no longer
     exist. See the single merged "Cash & Bank Management" section below
     for the actual link order (setup → day-to-day entries → import →
     reconcile). --}}
@php($cashBankSubActive = request()->routeIs('cbe.finance.bank-accounts*') || request()->routeIs('cbe.finance.transfers*') || request()->routeIs('cbe.finance.transactions*') || request()->routeIs('cbe.accounting.petty-cash-funds*') || request()->routeIs('cbe.accounting.reports.cash-bank-position') || request()->routeIs('cbe.ai-accounting.*') || request()->routeIs('cbe.accounting.bank-reconciliations*') || request()->routeIs('cbe.accounting.reports.bank-reconciliation-listing') || request()->routeIs('cbe.accounting.bank-account-enquiry') || request()->routeIs('cbe.accounting.bank-transaction-enquiry') || request()->routeIs('cbe.accounting.bank-reconciliation-enquiry') || request()->routeIs('cbe.accounting.unmatched-transaction-enquiry') || request()->routeIs('cbe.accounting.bank-reconciliation-reports-hub') || request()->routeIs('cbe.accounting.reports.bank-account-listing') || request()->routeIs('cbe.accounting.reports.bank-transaction-report') || request()->routeIs('cbe.accounting.reports.bank-reconciliation-statement') || request()->routeIs('cbe.accounting.reports.outstanding-cheques') || request()->routeIs('cbe.accounting.reports.deposits-in-transit') || request()->routeIs('cbe.accounting.reports.unmatched-bank-transactions') || request()->routeIs('cbe.accounting.reports.bank-charges') || request()->routeIs('cbe.accounting.reports.bank-interest') || request()->routeIs('cbe.accounting.bank-reconciliation-audit-log'))
{{-- NEW 16 Sep 2026 — per Chris: "under cash and bank management, can
     you create a sub menu AI Power Accounting ... same create bank
     reconciliation sub module" — these two flags are precise SUBSETS
     of $cashBankSubActive above (same routes, just split out) so the
     two new nested groups inside cashBankSubMenu below know whether to
     auto-expand on page load, without changing whether Cash & Bank
     Management itself (or Financial Accounting Module) shows as open —
     that's still driven entirely by $cashBankSubActive/$finActive. --}}
@php($aiPowerGroupActive = request()->routeIs('cbe.ai-accounting.*'))
@php($bankReconGroupActive = request()->routeIs('cbe.accounting.bank-reconciliations*') || request()->routeIs('cbe.accounting.reports.bank-reconciliation-listing') || request()->routeIs('cbe.accounting.bank-account-enquiry') || request()->routeIs('cbe.accounting.bank-transaction-enquiry') || request()->routeIs('cbe.accounting.bank-reconciliation-enquiry') || request()->routeIs('cbe.accounting.unmatched-transaction-enquiry') || request()->routeIs('cbe.accounting.bank-reconciliation-reports-hub') || request()->routeIs('cbe.accounting.reports.bank-account-listing') || request()->routeIs('cbe.accounting.reports.bank-transaction-report') || request()->routeIs('cbe.accounting.reports.bank-reconciliation-statement') || request()->routeIs('cbe.accounting.reports.outstanding-cheques') || request()->routeIs('cbe.accounting.reports.deposits-in-transit') || request()->routeIs('cbe.accounting.reports.unmatched-bank-transactions') || request()->routeIs('cbe.accounting.reports.bank-charges') || request()->routeIs('cbe.accounting.reports.bank-interest') || request()->routeIs('cbe.accounting.bank-reconciliation-audit-log'))
{{-- RESTRUCTURED 3 Sep 2026 (Task #378) — added every AP route built
     against Chris's Temple/NGO AP spec (Tasks #370-377): Supplier
     Categories, AP Debit/Credit Notes (2 directions), Payment Voucher,
     AP Adjustments/Refunds/Opening Balances, 4 AP Enquiry screens, the
     AP Reports hub + Supplier Statement + every new AP report. --}}
@php($apSubActive = request()->routeIs('cbe.accounting.bills*') || request()->routeIs('cbe.accounting.debit-notes*') || request()->routeIs('cbe.accounting.ap-debit-notes*') || request()->routeIs('cbe.accounting.payment-voucher*') || request()->routeIs('cbe.accounting.ap-adjustments*') || request()->routeIs('cbe.accounting.ap-refunds*') || request()->routeIs('cbe.accounting.ap-opening-balances*') || request()->routeIs('cbe.accounting.purchase-requests*') || request()->routeIs('cbe.accounting.supplier-enquiry*') || request()->routeIs('cbe.accounting.bill-enquiry*') || request()->routeIs('cbe.accounting.ap-payment-enquiry*') || request()->routeIs('cbe.accounting.ap-outstanding-balance-enquiry*') || request()->routeIs('cbe.accounting.reports.ap-aging') || request()->routeIs('cbe.accounting.reports.ap-reports') || request()->routeIs('cbe.accounting.reports.supplier-statement*') || request()->routeIs('cbe.accounting.reports.ap-outstanding-payables') || request()->routeIs('cbe.accounting.reports.bill-listing') || request()->routeIs('cbe.accounting.reports.ap-payment-listing') || request()->routeIs('cbe.accounting.reports.ap-credit-note-listing') || request()->routeIs('cbe.accounting.reports.ap-debit-note-listing') || request()->routeIs('cbe.accounting.reports.supplier-balance') || request()->routeIs('cbe.accounting.reports.monthly-ap-summary') || request()->routeIs('cbe.accounting.reports.ap-transaction-report') || request()->routeIs('cbe.accounting.reports.ap-gl-reconciliation') || request()->routeIs('cbe.accounting.reports.expense-summary-by-supplier') || request()->routeIs('cbe.accounting.reports.expense-summary-by-category'))
@php($arSubActive = request()->routeIs('cbe.accounting.invoices*') || request()->routeIs('cbe.accounting.reports.ar-aging') || request()->routeIs('cbe.donors.*') || request()->routeIs('cbe.accounting.donation-entry*') || request()->routeIs('cbe.accounting.donation-pledges*') || request()->routeIs('cbe.accounting.reports.fund-balance') || request()->routeIs('cbe.accounting.ar-debit-notes*') || request()->routeIs('cbe.accounting.ar-credit-notes*') || request()->routeIs('cbe.accounting.reports.debtor-ledger*') || request()->routeIs('cbe.accounting.reports.debtor-statement') || request()->routeIs('cbe.accounting.ar-adjustments*') || request()->routeIs('cbe.accounting.ar-refunds*') || request()->routeIs('cbe.accounting.ar-opening-balances*') || request()->routeIs('cbe.accounting.payment-allocation*') || request()->routeIs('cbe.accounting.invoice-enquiry*') || request()->routeIs('cbe.accounting.receipt-enquiry*') || request()->routeIs('cbe.accounting.outstanding-balance-enquiry*') || request()->routeIs('cbe.accounting.reports.ar-reports') || request()->routeIs('cbe.accounting.reports.invoice-listing') || request()->routeIs('cbe.accounting.reports.receipt-listing') || request()->routeIs('cbe.accounting.reports.ar-debit-note-listing') || request()->routeIs('cbe.accounting.reports.ar-credit-note-listing') || request()->routeIs('cbe.accounting.reports.outstanding-receivables') || request()->routeIs('cbe.accounting.reports.debtor-balance') || request()->routeIs('cbe.accounting.reports.monthly-ar-summary') || request()->routeIs('cbe.accounting.reports.ar-transaction-report') || request()->routeIs('cbe.accounting.reports.payment-collection') || request()->routeIs('cbe.accounting.reports.donor-statement*'))
@php($faSubActive = request()->routeIs('cbe.accounting.fixed-assets*') || request()->routeIs('cbe.accounting.asset-enquiry*') || request()->routeIs('cbe.accounting.fixed-asset-reports-hub*') || request()->routeIs('cbe.accounting.fixed-asset-audit-log*') || request()->routeIs('cbe.accounting.reports.fixed-asset-schedule') || request()->routeIs('cbe.accounting.reports.fixed-asset-disposal-listing') || request()->routeIs('cbe.accounting.reports.depreciation-listing') || request()->routeIs('cbe.accounting.reports.asset-acquisition') || request()->routeIs('cbe.accounting.reports.asset-transfer') || request()->routeIs('cbe.accounting.reports.asset-write-off') || request()->routeIs('cbe.accounting.reports.asset-by-location') || request()->routeIs('cbe.accounting.reports.asset-by-department') || request()->routeIs('cbe.accounting.reports.asset-by-fund') || request()->routeIs('cbe.accounting.reports.fa-gl-reconciliation'))
{{-- FIXED 13 Sep 2026 (Task #418 follow-up QC pass) — cbe.exec-dashboard
     used to be claimed here AND by $overviewActive above (the Overview
     section, a true top-level sibling of Financial Accounting Module),
     the same "two top-level sections open at once" bug just found on
     Customers — landing on the Executive Dashboard report lit up both
     Overview and Financial Accounting Module. Kept under $overviewActive
     only (it's the more natural home — an overview/KPI dashboard). --}}
@php($finRepSubActive = request()->routeIs('cbe.accounting.reports.cash-flow-statement') || request()->routeIs('cbe.accounting.reports.monthly-financial-summary'))
@php($yearEndSubActive = request()->routeIs('cbe.accounting.year-end-closing') || request()->routeIs('cbe.accounting.reports.prior-year-comparison'))
@php($auditSubActive = request()->routeIs('cbe.accounting.approvals*') || request()->routeIs('cbe.accounting.approval-settings*') || request()->routeIs('cbe.accounting.transaction-history'))
@php($rosSubActive = request()->routeIs('cbe.accounting.year-end-closing.pack') || request()->routeIs('cbe.accounting.reports.office-bearer-list') || request()->routeIs('cbe.accounting.ros-submission-checklist*'))
{{-- FIXED 13 Sep 2026 (Task #418 follow-up) — per Chris: landing directly
     on "Customers" (Debtor Master, now reached via the Master File
     Maintenance > Financial Master File sub-menu) showed BOTH Financial
     Accounting Module AND Master File Maintenance expanded on screen at
     once, "dashboard change crazy". Root cause: $finActive used a blanket
     routeIs('cbe.accounting.*') wildcard left over from when Master
     Files was still nested INSIDE Financial Accounting Module (before
     Task #418 pulled it out as its own top-level section) — so it kept
     matching every master-file-only route too (Customers, Suppliers,
     Chart of Accounts, etc.), leaving finParent "open" at the same time
     as mfmParent, and #sidebar.focused's hide-siblings rule couldn't
     hide either one since both had the 'open' class. Rebuilt as the OR
     of this module's own 11 precise category flags (same approach
     $masterFileMaintActive already uses) so it only ever matches an
     actual AR/AP/GL/FA/Bank Recon/Cash & Bank/AI Automation/Financial
     Reporting/Year-End/Audit/ROS screen — never a Master File Maintenance
     one. This also fixes a second pre-existing gap: cbe.finance.* routes
     (Cash & Bank Management's own screens) were never matched by the old
     'cbe.accounting.*'-only wildcard, so Financial Accounting Module
     could fail to show as open at all when landing on Bank Accounts. --}}
@php($crmActive = request()->routeIs('customer-kpi*') || request()->routeIs('help-desk*') || request()->routeIs('support-tickets*') || request()->routeIs('admin.masterfile.customer-statuses*') || request()->routeIs('admin.masterfile.customer-types*') || request()->routeIs('admin.masterfile.customer-categories*') || request()->routeIs('admin.masterfile.customer-sources*') || request()->routeIs('admin.masterfile.occupation-groups*'))
@php($finActive = $glSubActive || $cashBankSubActive || $apSubActive || $arSubActive || $faSubActive || $finRepSubActive || $yearEndSubActive || $auditSubActive || $rosSubActive)
{{-- NEW 10 Sep 2026 (Task #406) — Master Files sidebar submenu active
     flag. Routes here used to be claimed by arSubActive/apSubActive/
     glSubActive/faSubActive, but their direct sidebar links were removed
     from those categories in Task #404 (centralised into Master Files
     instead) — so the auto-expand claim moved here too, to stay accurate. --}}
@php($masterFilesSubActive = request()->routeIs('cbe.accounting.customers*') || request()->routeIs('cbe.accounting.customer-categories*') || request()->routeIs('cbe.accounting.suppliers*') || request()->routeIs('cbe.accounting.supplier-categories*') || request()->routeIs('cbe.accounting.account-categories*') || request()->routeIs('cbe.accounting.document-number-control*') || request()->routeIs('cbe.accounting.asset-categories*') || request()->routeIs('cbe.accounting.asset-locations*') || request()->routeIs('cbe.finance.bank-transaction-types*') || request()->routeIs('cbe.accounting.bank-reconciliation-rules*') || request()->routeIs('cbe.accounting.payment-terms*') || request()->routeIs('cbe.accounting.payment-methods*') || request()->routeIs('cbe.accounting.funds*') || request()->routeIs('cbe.accounting.tax-rates*'))
{{-- NEW 13 Sep 2026 (Task #418) — per Chris: Master File Maintenance
     pulled out of Financial Accounting Module into its own top-level
     sidebar section (positioned after Membership Module, before My
     Account). Combines $masterFilesSubActive's financial reference
     data with the other master-file-only screens (GLADE Tiers, Faith
     Types, Program Library, Reason Code, Partner API Keys). --}}
{{-- REMOVED 13 Sep 2026 (Task #418 follow-up QC pass) — $financialHubActive
     and $vendorHubActive used to live here (blanket routeIs('cbe.accounting.*')
     / routeIs('cbe.finance.*') wildcards from when Financial Master File
     was still a flat link). They were exactly what caused the "dashboard
     change crazy" bug Chris reported: landing on ANY Financial Accounting
     Module screen also lit up Master File Maintenance. Both variables
     were fully replaced below by the precise $fmfSubActive/$vmfSubActive
     flags and are no longer read anywhere — deleted outright rather than
     left in place unused, so a future edit can't accidentally wire the
     old broad wildcards back in. --}}
{{-- NEW 13 Sep 2026 (Task #418 follow-up) — precise route lists (only
     the exact screens each sub-menu now links to) used to auto-open the
     new Financial Master File / Vendor Master File nested accordions
     when the page loads directly on one of their screens. --}}
{{-- Chart of Accounts and Bank Account Number are deliberately left out
     of this auto-expand check even though both are still clickable links
     in the Financial Master File sub-menu below: those two screens are
     also claimed by General Ledger ($glSubActive) and Cash & Bank
     Management ($cashBankSubActive) respectively — same one-module-per-
     route convention already used for every other cross-listed report
     in this file, so landing on either doesn't pop two accordions open
     at once. --}}
@php($fmfSubActive = request()->routeIs('cbe.accounting.chart-of-accounts*') || request()->routeIs('cbe.accounting.customers*', 'cbe.accounting.customer-categories*', 'cbe.accounting.suppliers*', 'cbe.accounting.supplier-categories*', 'cbe.accounting.account-categories*', 'cbe.accounting.transaction-categories*', 'cbe.accounting.document-number-control*', 'cbe.accounting.asset-categories*', 'cbe.accounting.asset-locations*', 'cbe.finance.bank-transaction-types*', 'cbe.accounting.bank-reconciliation-rules*', 'cbe.accounting.payment-terms*', 'cbe.accounting.payment-methods*', 'cbe.accounting.funds*', 'cbe.accounting.tax-rates*', 'cbe.accounting.account-groups*', 'cbe.accounting.journal-types*', 'cbe.accounting.cost-centres*', 'cbe.accounting.opening-balances*', 'cbe.accounting.periods*'))
@php($fmfGlSetupGroupActive = request()->routeIs('cbe.accounting.account-groups*') || request()->routeIs('cbe.accounting.journal-types*') || request()->routeIs('cbe.accounting.cost-centres*') || request()->routeIs('cbe.accounting.opening-balances*') || request()->routeIs('cbe.accounting.periods*'))
{{-- NEW 19 Sep 2026 -- per Chris: "no scroll on sidebar" (Financial
     Master File specifically). 5 route-active flags for the new
     nested-accordion groups inside fmfSubMenu, same convention as
     $glSetupGroupActive etc. above. --}}
@php($fmfCustSuppGroupActive = request()->routeIs('cbe.accounting.customers*', 'cbe.accounting.customer-categories*', 'cbe.accounting.suppliers*', 'cbe.accounting.supplier-categories*'))
@php($fmfCoaGroupActive = request()->routeIs('cbe.accounting.chart-of-accounts*', 'cbe.accounting.account-categories*', 'cbe.accounting.transaction-categories*', 'cbe.accounting.document-number-control*'))
@php($fmfFaGroupActive = request()->routeIs('cbe.accounting.asset-categories*', 'cbe.accounting.asset-locations*'))
@php($fmfBankGroupActive = request()->routeIs('cbe.finance.bank-transaction-types*', 'cbe.accounting.bank-reconciliation-rules*', 'cbe.accounting.payment-terms*', 'cbe.accounting.payment-methods*'))
@php($fmfFundTaxGroupActive = request()->routeIs('cbe.accounting.funds*', 'cbe.accounting.tax-rates*'))
{{-- FIXED 13 Sep 2026 (Task #418 follow-up) — this used to fold in the
     blanket $financialHubActive/$vendorHubActive wildcards below (left
     over from before Financial Master File / Vendor Master File became
     nested sub-menus), which meant landing on ANY Financial Accounting
     Module screen (e.g. Invoices, an Accounts Receivable item) ALSO lit
     up Master File Maintenance as open at the same time — the same
     "two sections open at once" bug as the $finActive fix above, just
     the other direction. Swapped for the precise $fmfSubActive/
     $vmfSubActive flags (which already correctly exclude Chart of
     Accounts and Bank Account Number — those two stay owned by General
     Ledger / Cash & Bank Management, per the comment above $fmfSubActive). --}}
{{-- FIXED 14 Sep 2026 — per Chris: drilling into Group Set Up (Group
     Name & Hierarchy Levels > edit a specific CBE group) was falling
     back to the collapsed main menu instead of staying on Master File
     Maintenance. Root cause: the group-names.edit/.create/.search
     sub-routes carry no ?type= query string (only the index list page
     does), so the old `(routeIs('admin.masterfile.group-names*') &&
     type===CBE)` check went false the instant you opened a record —
     `group-names*` already matched the sub-routes, it was the missing
     ?type=CBE that broke it. Split into: exact index route still
     requires type=CBE (shared with DSG's own Group Name list), and a
     separate dot-wildcard for every group-names sub-route (edit/
     create/search/typeahead/store/update), which needs no type check
     since those pages only ever render inside this GLADE layout. --}}
@php($vmfSubActive = request()->routeIs('admin.masterfile.cbe-vendors*', 'admin.masterfile.cbe-vendor-approvals*', 'admin.masterfile.cbe-marketplace-listings*', 'admin.masterfile.cbe-marketplace-orders*', 'admin.masterfile.cbe-marketplace-campaigns*'))
{{-- NEW 19 Sep 2026 -- per Chris: "no scroll at the sidebar". Master
     File Maintenance had grown to 9 flat catalog links above Financial/
     Vendor Master File, pushing the menu past one screen height. Same
     fix as General Ledger's earlier split: grouped into 2 collapsible
     sub-menus, same toggleMfmSub() mechanism already used by Financial/
     Vendor Master File below (opening one closes the others, Prev
     button unchanged). --}}
@php($directorySubActive = request()->routeIs('admin.faith-practice-types*') || request()->routeIs('admin.committee-position-types*') || request()->routeIs('admin.practitioner-types*') || request()->routeIs('admin.practitioners*') || request()->routeIs('admin.cbe-notice-styles*') || request()->routeIs('admin.cbe-season-themes*') || request()->routeIs('admin.cbe-ticket-categories*') || request()->routeIs('admin.cbe-document-categories*'))
{{-- FIXED 24 Sep 2026 -- per Chris: opening a Directory & Catalog
     Types screen (e.g. Faith Practice Types) left "Membership Master
     File" itself collapsed in the sidebar, even though the screen you
     were on lives inside it. $directorySubActive now feeds into its
     parent's own active check so the whole chain from top-level
     Master File Maintenance down to the specific open screen stays
     expanded together. --}}
@php($groupSetupSubActive = ($isAdmin && request()->routeIs('admin.cbe-kpi.hierarchy-nodes*', 'admin.cbe-kpi.hierarchy-link*', 'admin.cbe-kpi.group-entities*')) || (request()->routeIs('admin.masterfile.group-names') && request('type') === 'CBE') || request()->routeIs('admin.masterfile.group-names.*') || request()->routeIs('admin.glade-tiers*', 'admin.member-pick-lists*', 'admin.membership-plans*') || $directorySubActive)
@php($masterFileMaintActive = $masterFilesSubActive || ($isAdmin && request()->routeIs('admin.cbe-kpi.hierarchy-nodes*', 'admin.cbe-kpi.hierarchy-link*', 'admin.cbe-kpi.group-entities*')) || request()->routeIs('admin.glade-tiers*') || request()->routeIs('admin.faith-practice-types*') || request()->routeIs('admin.committee-position-types*') || request()->routeIs('admin.practitioner-types*') || request()->routeIs('admin.practitioners*') || request()->routeIs('admin.cbe-notice-styles*') || request()->routeIs('admin.cbe-season-themes*') || request()->routeIs('admin.cbe-ticket-categories*') || request()->routeIs('admin.cbe-document-categories*') || $fmfSubActive || $vmfSubActive || request()->routeIs('admin.masterfile.reason-codes*') || request()->routeIs('admin.partner-api.*') || request()->routeIs('admin.masterfile.program-library*') || request()->routeIs('admin.masterfile.program-unlocks*') || (request()->routeIs('admin.masterfile.group-names') && request('type') === 'CBE') || request()->routeIs('admin.masterfile.group-names.*') || request()->routeIs('admin.masterfile.ai-assistant*'))
{{-- FIXED 13 Sep 2026 (Task #418 follow-up QC pass) — cbe.donors.* used
     to be claimed here AND by $arSubActive above (a genuine top-level
     conflict: Membership Module vs Financial Accounting Module both
     open at once when landing on a Donor Register screen). Task #404's
     own comment above $arSubActive already settled this — "Donor
     Register stays [in AR] — it isn't a master/reference table, it's
     each entity's own working list" — so AR keeps sole ownership here. --}}
{{-- RESTRUCTURED 17 Sep 2026 — per Chris: fold the old Administration
     Management top-level section (Meeting Minutes/Activities/Notice
     Board/Temple Calendar/Reminders) into this same menu as its FIRST
     nested group, alongside 3 new groups (Donor Management, Consultant
     & Practitioner, Membership Management) — same nested-accordion
     mechanism as Financial/Vendor Master File above (see
     SEC_SUB_IDS/toggleSecSub() below), scoped to #membershipMenu.
     cbe.donors.* (Donor Register) intentionally stays OUT of
     $secDonorSubActive — Task #418 already settled that AR keeps sole
     ownership of that route's highlight (see the note above the old
     $membershipActive line); the Donor Register LINK still lives in
     the Donor Management group below, it just isn't what opens it. --}}
{{-- REGROUPED 18 Sep 2026 — per Chris, acting as an experienced NGO
     secretary would organize this: each group is now ONE real job the
     Secretary does, not a technical "communication" catch-all. Tickets
     and Announcement Blast were flagged as having nothing to do with
     running a meeting — they're gone from that group entirely. The
     personal Reminders link (calendar.index — an individual agent's own
     to-do reminders, not a secretarial function) is removed from this
     menu altogether. Rule 5 (Section 90): each route belongs to exactly
     one flag below, verified by grep before this was saved. --}}
@php($secMeetingsSubActive = request()->routeIs('cbe.minutes.*'))
@php($secNoticesSubActive = request()->routeIs('cbe.notice-board.*') || request()->routeIs('cbe.blast.*'))
@php($secCommsSubActive = request()->routeIs('cbe.messaging.*') || request()->routeIs('cbe.tickets.*'))
@php($secDocsSubActive = request()->routeIs('cbe.documents.*') || request()->routeIs('cbe.correspondences.*') || request()->routeIs('cbe.compliance-items.*'))
@php($secActivitiesSubActive = request()->routeIs('cbe.activities.*') || request()->routeIs('cbe.temple-calendar.*'))
@php($secAiSubActive = request()->routeIs('cbe.ai-assistants.*'))
@php($secDonorSubActive = request()->routeIs('admin.cbe-kpi.donors', 'admin.donor-link*'))
@php($secConsultSubActive = request()->routeIs('admin.practitioners*') || request()->routeIs('book-appointment') || request()->routeIs('book-appointment.*') || request()->routeIs('my-appointments*'))
@php($secMemberSubActive = request()->routeIs('admin.cbe-kpi.members*', 'admin.member-file*') || (! $isAdmin && request()->routeIs('admin.cbe-kpi.hierarchy-nodes*', 'admin.cbe-kpi.hierarchy-link*', 'admin.cbe-kpi.group-entities*')) || request()->routeIs('cbe.events.*') || request()->routeIs('*.masterfile.pending-verifications*') || request()->routeIs('admin.document-credit*') || request()->routeIs('admin.fraud-review*'))
@php($membershipActive = $secMeetingsSubActive || $secNoticesSubActive || $secCommsSubActive || $secDocsSubActive || $secActivitiesSubActive || $secAiSubActive || $secDonorSubActive || $secConsultSubActive || $secMemberSubActive)
@php($accActive = request()->routeIs('*.profile*') || request()->routeIs('integrations.*'))

{{-- NEW 19 Sep 2026 -- per Chris: a screen can opt out of the shared
     GLADE sidebar entirely (not just the topbar) the same way it already
     opts out of the topbar via @section('hide-topbar','1'). A page opts
     out by defining @section('hide-sidebar','1'). #topbar and #main are
     both reset to left:0 right below when this is set, since they're
     normally offset by --sidebar-w to make room for the sidebar. --}}
@if (! $__env->hasSection('hide-sidebar'))
<nav id="sidebar">
    <div class="glade-sidebar-logo">
        <h1>{{ __('glade.sidebar_brand_name') }}</h1>
        <p>{{ __('glade.footer_tagline') }}</p>
    </div>
    {{-- REBUILT 28 Aug 2026 — per Chris: "organize your menu presentation
         like Generallink dashboard NO scroll and click prev menu." Same
         collapsible nav-parent/nav-submenu + Prev button pattern as
         layouts/dashboard.blade.php — only one group's items are visible
         at a time, so the sidebar never needs to scroll. The standalone
         "Network Tree" section was dropped here: it linked to the exact
         same route as the new "Entity Maintenance" item below
         (admin.cbe-kpi.hierarchy-nodes.create with identical params), so
         keeping both was a duplicate. Every existing @if($isAdmin)/
         @if($isOfficer) condition and route is unchanged. --}}
    <div class="sidebar-nav" id="sidebarNav">
        {{-- RESTORED + RENAMED 18 Sep 2026 — per Chris: back to a
             separate top-level section (was briefly nested inside
             Secretarial Management), renamed "Executive KPI Dashboard"
             — every KPI/analytics screen for CBE lives here, whatever
             gets added in future too. Communication KPI (was "GLADE
             Engagement Analytics") moved in from the now-removed
             Action Required group. --}}
        {{-- FIXED 27 Sep 2026 -- per Chris ("see your promise"): 'admin.cbe-kpi*' also matched
             Entity Maintenance / CBE Entity Affiliation / Member & Donor maintenance (their
             route names start with admin.cbe-kpi.), so the Executive KPI menu opened and
             highlighted on those screens. Those belong to Membership Master File /
             Secretarial Management and are excluded here. --}}
        @php($execKpiActive = (request()->routeIs('admin.cbe-kpi*') && ! request()->routeIs('admin.cbe-kpi.hierarchy-nodes*', 'admin.cbe-kpi.hierarchy-link*', 'admin.cbe-kpi.group-entities*', 'admin.cbe-kpi.place-lookup', 'admin.cbe-kpi.members*', 'admin.cbe-kpi.donors*')) || request()->routeIs('cbe.exec-dashboard') || request()->routeIs('cbe.exec-dashboard.*') || request()->routeIs('admin.member-kpi'))
        <div class="nav-parent {{ $execKpiActive ? 'open' : '' }}" id="execKpiParent" onclick="toggleSection('execKpiMenu','execKpiParent')">
            <div>{{ __('sidebar.executive_kpi_dashboard') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $execKpiActive ? 'open' : '' }}" id="execKpiMenu">
        @if($isAdmin)
            <a href="{{ route('admin.cbe-kpi') }}" class="sb-item live {{ request()->routeIs('admin.cbe-kpi') ? 'active' : '' }}">{{ __('sidebar.executive_kpi_dashboard') }}</a>
            <a href="{{ route('admin.cbe-kpi.communication', ['scope' => 'all']) }}" class="sb-item live {{ request()->routeIs('admin.cbe-kpi.communication') ? 'active' : '' }}">{{ __('sidebar.communication_kpi') }}</a>
            <a href="{{ route('admin.cbe-kpi.vendor-marketplace', ['scope' => 'all']) }}" class="sb-item live {{ request()->routeIs('admin.cbe-kpi.vendor-marketplace') ? 'active' : '' }}">{{ __('sidebar.vendor_marketplace_kpi') }}</a>
            <a href="{{ route('admin.cbe-kpi.customer', ['scope' => 'all']) }}" class="sb-item live {{ request()->routeIs('admin.cbe-kpi.customer') ? 'active' : '' }}">{{ __('sidebar.customer_kpi') }}</a>
            {{-- NEW 28 Sep 2026 -- member file item 35 --}}
            <a href="{{ route('admin.member-kpi') }}" class="sb-item live {{ request()->routeIs('admin.member-kpi') ? 'active' : '' }}">{{ __('member_file.kpi_title') }}</a>
        @else
            <a href="{{ route('cbe.exec-dashboard') }}" class="sb-item live {{ request()->routeIs('cbe.exec-dashboard') ? 'active' : '' }}">{{ __('sidebar.executive_kpi_dashboard') }}</a>
            <a href="{{ route('cbe.exec-dashboard.communication') }}" class="sb-item live {{ request()->routeIs('cbe.exec-dashboard.communication') ? 'active' : '' }}">{{ __('sidebar.communication_kpi') }}</a>
            <a href="{{ route('cbe.exec-dashboard.vendor-marketplace') }}" class="sb-item live {{ request()->routeIs('cbe.exec-dashboard.vendor-marketplace') ? 'active' : '' }}">{{ __('sidebar.vendor_marketplace_kpi') }}</a>
            <a href="{{ route('cbe.exec-dashboard.customer') }}" class="sb-item live {{ request()->routeIs('cbe.exec-dashboard.customer') ? 'active' : '' }}">{{ __('sidebar.customer_kpi') }}</a>
            @if($isOfficer)<a href="{{ route('admin.member-kpi') }}" class="sb-item live {{ request()->routeIs('admin.member-kpi') ? 'active' : '' }}">{{ __('member_file.kpi_title') }}</a>@endif
        @endif
        </div>





        {{-- RESTRUCTURED 17 Sep 2026 — per Chris: "move everything from
             administration Management under Secretarial Management as
             first display submenu, and propose ... sub menu Donor
             Management, sub menu Consultant and Practitioner ... where
             all appoint and set up is under this sub menu, same for
             membership management as sub menu." The old top-level
             Administration Management section is removed entirely (per
             Chris's own answer) and folded in here as the FIRST of 4
             nested groups inside Secretarial Management (the renamed
             Membership Module) — Donor Management, Consultant &
             Practitioner, and Membership Management follow. Admin-only
             queues (Pending Verifications/Document Credit/Risk Review)
             are folded into the Membership Management group per Chris's
             own answer, rather than kept as a separate group. Same
             nested-accordion mechanism as Financial/Vendor Master File
             above (SEC_SUB_IDS/toggleSecSub() below), scoped to
             #membershipMenu. --}}
        {{-- NEW 28 Aug 2026 — per Chris: "link the Financial Accounting
             Module and when click it open up the sub module Account
             Receivable, Account Payable General Ledger etc (depend what
             you have develop) the click AR is all the programs related,
             Entry, Enquiry, Reports." Only Accounts Payable and General
             Ledger are real today (built 25 Aug 2026 — see
             CbeAccountingController) — there's no Accounts Receivable
             ledger yet, so it isn't shown; money coming in is tracked as
             Donor Contributions/Receipts under Business instead. Each
             sub-module is grouped into Entry / Enquiry / Reports, same
             split Chris asked for. --}}
        {{-- RESTORED 18 Sep 2026 — per Chris: confirmed relevant to CBE,
             put back exactly as it was. --}}
        <div class="nav-parent {{ $crmActive ? 'open' : '' }}" id="crmParent" onclick="toggleSection('crmMenu','crmParent')">
            <div>{{ __('nav.customer_relationship') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $crmActive ? 'open' : '' }}" id="crmMenu">
        {{-- REMOVED 26 Sep 2026 -- per Chris: one dashboard only, no duplicates; all dashboards live under Executive KPI Dashboard. Old link: <a href="{ { route('customer-kpi.index') } }" class="sb-item live { { request()->routeIs('customer-kpi*') ? 'active' : '' } }">{ { __('sidebar.customer_kpi') } }</a> --}}
        <a href="{{ route('help-desk.index') }}" class="sb-item live {{ request()->routeIs('help-desk*') ? 'active' : '' }}">{{ __('sidebar.help_desk') }} @if($helpDeskUnreadCount > 0)<span class="badge">{{ $helpDeskUnreadCount > 9 ? '9+' : $helpDeskUnreadCount }}</span>@endif</a>
        <a href="{{ route('support-tickets.index') }}" class="sb-item live {{ request()->routeIs('support-tickets*') ? 'active' : '' }}">{{ __('sidebar.support_tickets') }}</a>
        @if($isAdmin)
        <a href="{{ route('admin.masterfile.customer-statuses') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.customer-statuses*') ? 'active' : '' }}">{{ __('sidebar.customer_status_maintenance') }}</a>
        <a href="{{ route('admin.masterfile.customer-types') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.customer-types*') ? 'active' : '' }}">{{ __('sidebar.customer_type_maintenance') }}</a>
        <a href="{{ route('admin.masterfile.customer-categories') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.customer-categories*') ? 'active' : '' }}">{{ __('sidebar.customer_category_maintenance') }}</a>
        <a href="{{ route('admin.masterfile.customer-sources') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.customer-sources*') ? 'active' : '' }}">{{ __('sidebar.customer_source_maintenance') }}</a>
        {{-- NEW 28 Sep 2026 -- per Chris: every pick list has its master file in the sidebar. Occupation Group (used by the Member file and Customer profile) was only in the old dashboard menu. --}}
        <a href="{{ route('admin.masterfile.occupation-groups') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.occupation-groups*') ? 'active' : '' }}">{{ __('sidebar.occupation_group_maintenance') }}</a>
        @endif
        </div>


        <div class="nav-parent {{ $finActive ? 'open' : '' }}" id="finParent" onclick="toggleSection('finMenu','finParent')">
            <div>{{ __('nav.financial_accounting_module') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $finActive ? 'open' : '' }}" id="finMenu">
        {{-- REBUILT 2 Sep 2026 — per Chris: "you follow back the original
             phase 1 program placement accordingly... put back every to
             the respective module as you propose earlier." This is
             Chris's own original Phase 1 list — 10 modules, in his exact
             order, each program under the module he named it under. A
             few reports/screens are cross-listed under 2 modules exactly
             as Chris's own list does (e.g. Trial Balance appears under
             both General Ledger and Financial Reporting) — only the
             category clicked expands, same accordion mechanism as the
             top-level sidebar sections, via toggleFinSub() below. --}}

        {{-- REORDERED 10 Sep 2026 (Task #402) — per Chris: the Accounting
             Hub tiles (Task #398) already show AR, AP, GL, FA, Bank
             Reconciliation, AI Accounting Automation in that order — this
             submenu had never been updated to match and still opened with
             General Ledger first. Same 6 categories now appear in the
             same order here as on the Hub; the categories that aren't one
             of the Hub's 7 tiles (Cash & Bank Management, Financial
             Reporting, Year-End Closing, Audit Trail, ROS/Regulatory
             Reporting) follow after, in their previous relative order.
             No links, routes, or content changed — only the sequence of
             the category headers. --}}

        {{-- MOVED OUT 13 Sep 2026 (Task #418) — per Chris: this whole hub
             used to live inside Financial Accounting Module, but Master
             File Maintenance is now its own top-level sidebar section
             (see below, positioned after Membership Module) so it isn't
             buried a click deeper inside Finance. Set Up CBE Group and
             Entity Maintenance are NOT repeated there — both already live
             in Membership Module (see Task #407's own note above about
             not showing the same screen twice) — only the pure
             reference/master-data screens moved. --}}

        {{-- 2. Accounts Receivable / Donation & Fund Management --}}
        <div class="nav-parent nav-parent-sub {{ $arSubActive ? 'open' : '' }}" id="arSubParent" onclick="toggleFinSub('arSubMenu','arSubParent')">
            <div>{{ __('cbe_accounting.group_accounts_receivable') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $arSubActive ? 'open' : '' }}" id="arSubMenu">
            {{-- RESTRUCTURED 3 Sep 2026 (Task #362) — regrouped the 21 AR
                 items into Master File / Entry / Enquiry / Reports order.
                 Per Chris's explicit choice, this is a silent reordering
                 only — no visible "A./B./C." section labels are shown,
                 consistent with Task #351 which already removed literal
                 Entry/Enquiry/Reports headers from the rest of the
                 Financial Accounting menu. Nothing was renamed, removed,
                 or re-routed — same items, same links, new order. --}}

            {{-- Master File / Setup — FIXED 10 Sep 2026 (Task #404): Customers,
                 Funds, Customer Categories, Payment Methods and Payment
                 Terms all moved to the new Master Files hub (Task #398) —
                 they were still duplicated here too, which is exactly the
                 "scattered across every module" problem Master Files was
                 supposed to fix. Donor Register stays — it isn't a master/
                 reference table, it's each entity's own working list. --}}
            <a href="{{ route('cbe.donors.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.donors.*') ? 'active' : '' }}">{{ __('cbe.sb_donor_register') }}</a>

            {{-- Entry --}}
            <a href="{{ route('cbe.accounting.invoices') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.invoices*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_invoices') }}</a>
            <a href="{{ route('cbe.accounting.donation-entry.create') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.donation-entry*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_donation_entry') }}</a>
            {{-- NEW 3 Sep 2026 (Task #366) — Donation Pledge (GL-integrated,
                 donor-scoped), separate from the event-scoped Donor Register. --}}
            <a href="{{ route('cbe.accounting.donation-pledges') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.donation-pledges*') ? 'active' : '' }}">{{ __('cbe_accounting.donation_pledges_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ar-debit-notes') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ar-debit-notes*') ? 'active' : '' }}">{{ __('cbe_accounting.ar_debit_notes_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ar-credit-notes') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ar-credit-notes*') ? 'active' : '' }}">{{ __('cbe_accounting.ar_credit_notes_page_title') }}</a>
            <a href="{{ route('cbe.accounting.payment-allocation') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.payment-allocation*') ? 'active' : '' }}">{{ __('cbe_accounting.payment_allocation_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ar-adjustments') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ar-adjustments*') ? 'active' : '' }}">{{ __('cbe_accounting.ar_adjustments_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ar-refunds') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ar-refunds*') ? 'active' : '' }}">{{ __('cbe_accounting.ar_refunds_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ar-opening-balances') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ar-opening-balances*') ? 'active' : '' }}">{{ __('cbe_accounting.ar_opening_balances_page_title') }}</a>

            {{-- Enquiry --}}
            <a href="{{ route('cbe.accounting.reports.debtor-ledger-picker') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.debtor-ledger*') || request()->routeIs('cbe.accounting.reports.debtor-statement') ? 'active' : '' }}">{{ __('cbe_accounting.debtor_ledger_page_title') }}</a>
            <a href="{{ route('cbe.accounting.invoice-enquiry') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.invoice-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.invoice_enquiry_page_title') }}</a>
            <a href="{{ route('cbe.accounting.receipt-enquiry') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.receipt-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.receipt_enquiry_page_title') }}</a>
            <a href="{{ route('cbe.accounting.outstanding-balance-enquiry') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.outstanding-balance-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.outstanding_balance_enquiry_page_title') }}</a>
            {{-- NEW 3 Sep 2026 (Task #368) — Donor Statement (mirrors Debtor Statement). --}}
            <a href="{{ route('cbe.accounting.reports.donor-statement-picker') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.donor-statement*') ? 'active' : '' }}">{{ __('cbe_accounting.donor_statement_title') }}</a>

            {{-- Reports (incl. AR/GL Reconciliation, inside the hub) --}}
            <a href="{{ route('cbe.accounting.reports.ar-aging') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.ar-aging') ? 'active' : '' }}">{{ __('cbe_accounting.tile_ar_aging') }}</a>
            <a href="{{ route('cbe.accounting.reports.fund-balance') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.fund-balance') ? 'active' : '' }}">{{ __('cbe_accounting.tile_fund_balance') }}</a>
            <a href="{{ route('cbe.accounting.reports.ar-reports') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.ar-reports') ? 'active' : '' }}">{{ __('cbe_accounting.ar_reports_hub_page_title') }}</a>
        </div>

        {{-- 3. Accounts Payable --}}
        <div class="nav-parent nav-parent-sub {{ $apSubActive ? 'open' : '' }}" id="apSubParent" onclick="toggleFinSub('apSubMenu','apSubParent')">
            <div>{{ __('cbe_accounting.group_accounts_payable') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $apSubActive ? 'open' : '' }}" id="apSubMenu">
            {{-- RESTRUCTURED 3 Sep 2026 (Task #378) — regrouped every AP
                 item into Master File / Entry / Enquiry / Reports order,
                 same silent-reordering convention as Task #362 on the AR
                 side — no visible "A./B./C." section labels, nothing
                 renamed, removed, or re-routed. --}}

            {{-- Master File / Setup — FIXED 10 Sep 2026 (Task #404): Suppliers
                 and Supplier Categories both moved to the Master Files hub
                 (Task #398) but were still duplicated here — removed. --}}

            {{-- Entry --}}
            <a href="{{ route('cbe.accounting.bills') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.bills*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_bills') }}</a>
            <a href="{{ route('cbe.accounting.debit-notes') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.debit-notes*') ? 'active' : '' }}">{{ __('cbe_accounting.ap_credit_notes_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ap-debit-notes') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ap-debit-notes*') ? 'active' : '' }}">{{ __('cbe_accounting.ap_debit_notes_page_title') }}</a>
            <a href="{{ route('cbe.accounting.payment-voucher') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.payment-voucher*') ? 'active' : '' }}">{{ __('cbe_accounting.payment_voucher_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ap-adjustments') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ap-adjustments*') ? 'active' : '' }}">{{ __('cbe_accounting.ap_adjustments_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ap-refunds') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ap-refunds*') ? 'active' : '' }}">{{ __('cbe_accounting.ap_refunds_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ap-opening-balances') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ap-opening-balances*') ? 'active' : '' }}">{{ __('cbe_accounting.ap_opening_balances_page_title') }}</a>
            <a href="{{ route('cbe.accounting.purchase-requests') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.purchase-requests*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_purchase_requests') }}</a>

            {{-- Enquiry --}}
            <a href="{{ route('cbe.accounting.bill-enquiry') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.bill-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.bill_enquiry_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ap-payment-enquiry') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ap-payment-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.ap_payment_enquiry_page_title') }}</a>
            <a href="{{ route('cbe.accounting.ap-outstanding-balance-enquiry') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ap-outstanding-balance-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.ap_outstanding_balance_enquiry_page_title') }}</a>

            {{-- Reports (incl. Supplier Statement + AP/GL Reconciliation,
                 inside the hub) --}}
            <a href="{{ route('cbe.accounting.reports.ap-aging') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.ap-aging') ? 'active' : '' }}">{{ __('cbe_accounting.tile_ap_aging') }}</a>
            <a href="{{ route('cbe.accounting.reports.ap-reports') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.ap-reports') ? 'active' : '' }}">{{ __('cbe_accounting.ap_reports_hub_page_title') }}</a>
        </div>

        {{-- 4. General Ledger --}}
        <div class="nav-parent nav-parent-sub {{ $glSubActive ? 'open' : '' }}" id="glSubParent" onclick="toggleFinSub('glSubMenu','glSubParent')">
            <div>{{ __('cbe_accounting.group_general_ledger') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $glSubActive ? 'open' : '' }}" id="glSubMenu">
            {{-- MOVED OUT 24 Sep 2026 -- per Chris: "why this general
                 ledger have set up? it should all standardize in master
                 file, move to financial master file avoid duplicate" --
                 Account Groups / Journal Types / Cost Centres / Opening
                 Balances / Month-End Close used to have their own "Setup"
                 group here, duplicating the one place (Financial Master
                 File) all other GL/AP/AR/FA master data already lives.
                 Moved to Financial Master File > General Ledger Setup
                 below -- see that block for the same 5 links. --}}
            <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $glEntriesGroupActive ? 'open' : '' }}" id="glEntriesSubParent" onclick="toggleGlNestedSub('glEntriesSubMenu','glEntriesSubParent')">
                <div>{{ __('cbe_accounting.group_gl_entries') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu nav-submenu-subsub {{ $glEntriesGroupActive ? 'open' : '' }}" id="glEntriesSubMenu">
                <a href="{{ route('cbe.accounting.journal-vouchers') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.journal-vouchers*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_journal_vouchers') }}</a>
                <a href="{{ route('cbe.accounting.adjustment-journals') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.adjustment-journals*') ? 'active' : '' }}">{{ __('cbe_accounting.adjustment_journals_page_title') }}</a>
                <a href="{{ route('cbe.accounting.accrual-journals') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.accrual-journals*') ? 'active' : '' }}">{{ __('cbe_accounting.accrual_journals_page_title') }}</a>
                <a href="{{ route('cbe.accounting.recurring-journal-templates') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.recurring-journal-templates*') ? 'active' : '' }}">{{ __('cbe_accounting.recurring_templates_page_title') }}</a>
            </div>

            <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $glEnquiryGroupActive ? 'open' : '' }}" id="glEnquirySubParent" onclick="toggleGlNestedSub('glEnquirySubMenu','glEnquirySubParent')">
                <div>{{ __('cbe_accounting.group_gl_enquiries') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu nav-submenu-subsub {{ $glEnquiryGroupActive ? 'open' : '' }}" id="glEnquirySubMenu">
                <a href="{{ route('cbe.accounting.chart-of-accounts-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.chart-of-accounts-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.coa_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.general-ledger-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.general-ledger-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.gl_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.journal-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.journal-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.journal_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.trial-balance-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.trial-balance-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.trial_balance_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.transaction-history') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.transaction-history*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_transaction_history') }}</a>
                <a href="{{ route('cbe.accounting.integration-status') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.integration-status*') ? 'active' : '' }}">{{ __('cbe_accounting.integration_status_page_title') }}</a>
            </div>

            <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $glReportsGroupActive ? 'open' : '' }}" id="glReportsSubParent" onclick="toggleGlNestedSub('glReportsSubMenu','glReportsSubParent')">
                <div>{{ __('cbe_accounting.group_gl_reports') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu nav-submenu-subsub {{ $glReportsGroupActive ? 'open' : '' }}" id="glReportsSubMenu">
                <a href="{{ route('cbe.accounting.reports.trial-balance') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.reports.trial-balance') ? 'active' : '' }}">{{ __('cbe_accounting.tile_trial_balance') }}</a>
                <a href="{{ route('cbe.accounting.reports.balance-sheet') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.reports.balance-sheet') ? 'active' : '' }}">{{ __('cbe_accounting.tile_balance_sheet') }}</a>
                <a href="{{ route('cbe.accounting.reports.profit-loss') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.reports.profit-loss') ? 'active' : '' }}">{{ __('cbe_accounting.tile_profit_loss') }}</a>
                <a href="{{ route('cbe.accounting.reports.general-ledger') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.reports.general-ledger') ? 'active' : '' }}">{{ __('cbe_accounting.tile_general_ledger') }}</a>
                <a href="{{ route('cbe.accounting.reports.journal-export') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.reports.journal-export') ? 'active' : '' }}">{{ __('cbe_accounting.tile_journal_export') }}</a>
                <a href="{{ route('cbe.accounting.reports.gl-reports') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.reports.gl-reports') ? 'active' : '' }}">{{ __('cbe_accounting.gl_reports_hub_page_title') }}</a>
            </div>
        </div>

        {{-- 5. Fixed Assets Register --}}
        <div class="nav-parent nav-parent-sub {{ $faSubActive ? 'open' : '' }}" id="faSubParent" onclick="toggleFinSub('faSubMenu','faSubParent')">
            <div>{{ __('cbe_accounting.group_fixed_assets') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $faSubActive ? 'open' : '' }}" id="faSubMenu">
            <a href="{{ route('cbe.accounting.fixed-assets') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.fixed-assets*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_fixed_assets') }}</a>
            {{-- NEW 4 Sep 2026 (Task #395 Phase 3/4) — Asset Enquiry search,
                 consolidated Reports Hub (replaces the 3 separate report
                 links below it), and the Fixed Asset Audit Trail. --}}
            <a href="{{ route('cbe.accounting.asset-enquiry') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.asset-enquiry*') ? 'active' : '' }}">{{ __('cbe_accounting.asset_enquiry_page_title') }}</a>
            <a href="{{ route('cbe.accounting.fixed-asset-reports-hub') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.fixed-asset-reports-hub*') || request()->routeIs('cbe.accounting.reports.asset-*') || request()->routeIs('cbe.accounting.reports.fa-gl-reconciliation') ? 'active' : '' }}">{{ __('cbe_accounting.fa_reports_hub_page_title') }}</a>
            <a href="{{ route('cbe.accounting.reports.fixed-asset-schedule') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.fixed-asset-schedule') ? 'active' : '' }}">{{ __('cbe_accounting.tile_fixed_asset_schedule') }}</a>
            {{-- NEW 3 Sep 2026 (Task #388) — Disposal Listing / Depreciation Listing reports. --}}
            <a href="{{ route('cbe.accounting.reports.fixed-asset-disposal-listing') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.fixed-asset-disposal-listing') ? 'active' : '' }}">{{ __('cbe_accounting.tile_fixed_asset_disposal_listing') }}</a>
            <a href="{{ route('cbe.accounting.reports.depreciation-listing') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.depreciation-listing') ? 'active' : '' }}">{{ __('cbe_accounting.tile_depreciation_listing') }}</a>
            <a href="{{ route('cbe.accounting.fixed-asset-audit-log') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.fixed-asset-audit-log*') ? 'active' : '' }}">{{ __('cbe_accounting.fixed_asset_audit_log_page_title') }}</a>
        </div>

        {{-- 6. Cash & Bank Management — RESTRUCTURED 16 Sep 2026 per
             Chris: "centralised all bank related process in one menu
             ... cash and bank management." This single section replaces
             what used to be 3 separate sidebar sections (Bank
             Reconciliation, AI Accounting Automation, Cash & Bank
             Management) that between them listed "Bank Account Number"
             three times, all pointing at the exact same screen. Link
             order below is the actual process sequence: set up the
             account once (Bank Accounts Master) → day-to-day entries →
             import/upload statements (AI Accounting) → reconcile. --}}
        <div class="nav-parent nav-parent-sub {{ $cashBankSubActive ? 'open' : '' }}" id="cashBankSubParent" onclick="toggleFinSub('cashBankSubMenu','cashBankSubParent')">
            <div>{{ __('cbe_accounting.group_cash_bank_management') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $cashBankSubActive ? 'open' : '' }}" id="cashBankSubMenu">
            <a href="{{ route('cbe.finance.bank-accounts') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.finance.bank-accounts*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_bank_accounts_master') }}</a>
            <a href="{{ route('cbe.finance.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.finance.transactions*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_daily_transactions') }}</a>
            <a href="{{ route('cbe.accounting.petty-cash-funds') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.petty-cash-funds*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_petty_cash') }}</a>
            <a href="{{ route('cbe.finance.transfers.create') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.finance.transfers*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_bank_transfer') }}</a>
            <a href="{{ route('cbe.accounting.reports.cash-bank-position') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.cash-bank-position') ? 'active' : '' }}">{{ __('cbe_accounting.tile_cash_bank_position') }}</a>
            {{-- NEW 16 Sep 2026 — per Chris: "create a sub menu AI Power
                 Accounting, move all the process involve to this sub
                 menu" — the 5 AI Accounting Automation links (previously
                 flat here) now nest under their own collapsible group.
                 Mutual-exclusion accordion with the Bank Reconciliation
                 group below, via toggleCashBankNestedSub() JS — the plain
                 5 items above stay visible always; only the clicked
                 group's own list expands/collapses (deliberately NOT the
                 full "sub-focused hide-everything" mechanism that
                 toggleFinSub()/toggleMfmSub() use one level up, to avoid
                 stacking two layers of that behavior). --}}
            <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $aiPowerGroupActive ? 'open' : '' }}" id="cashBankAiSubParent" onclick="toggleCashBankNestedSub('cashBankAiSubMenu','cashBankAiSubParent')">
                <div>{{ __('cbe_ai.group_ai_power_accounting') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu nav-submenu-subsub {{ $aiPowerGroupActive ? 'open' : '' }}" id="cashBankAiSubMenu">
                {{-- Batches link no longer also matches batches.create, so
                     Upload and Batches don't both light up at once when
                     you're on the upload screen. --}}
                <a href="{{ route('cbe.ai-accounting.batches.create') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.ai-accounting.batches.create') ? 'active' : '' }}">{{ __('cbe_ai.upload_button') }}</a>
                <a href="{{ route('cbe.ai-accounting.index') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.ai-accounting.index') || request()->routeIs('cbe.ai-accounting.batches.show') ? 'active' : '' }}">{{ __('cbe_ai.batches_page_title') }}</a>
                <a href="{{ route('cbe.ai-accounting.rules') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.ai-accounting.rules*') ? 'active' : '' }}">{{ __('cbe_ai.rules_page_title') }}</a>
                <a href="{{ route('cbe.ai-accounting.exceptions') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.ai-accounting.exceptions') ? 'active' : '' }}">{{ __('cbe_ai.exceptions_page_title') }}</a>
                <a href="{{ route('cbe.ai-accounting.audit-log') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.ai-accounting.audit-log') ? 'active' : '' }}">{{ __('cbe_ai.audit_log_page_title') }}</a>
            </div>

            {{-- NEW 16 Sep 2026 — per Chris: "same create bank
                 reconciliation sub module and move all related bank
                 reconciliation to this sub menu" — same nested-group
                 pattern as AI Power Accounting above. Its own duplicate
                 "Bank Accounts" link (same screen as the one at the top
                 of this menu) was removed rather than kept a second
                 time. --}}
            <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $bankReconGroupActive ? 'open' : '' }}" id="cashBankBrSubParent" onclick="toggleCashBankNestedSub('cashBankBrSubMenu','cashBankBrSubParent')">
                <div>{{ __('cbe_accounting.group_bank_reconciliation') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu nav-submenu-subsub {{ $bankReconGroupActive ? 'open' : '' }}" id="cashBankBrSubMenu">
                <a href="{{ route('cbe.accounting.bank-reconciliations') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.bank-reconciliations*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_bank_reconciliation') }}</a>
                <a href="{{ route('cbe.accounting.bank-account-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.bank-account-enquiry') ? 'active' : '' }}">{{ __('cbe_accounting.bank_account_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.bank-transaction-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.bank-transaction-enquiry') ? 'active' : '' }}">{{ __('cbe_accounting.bank_transaction_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.bank-reconciliation-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.bank-reconciliation-enquiry') ? 'active' : '' }}">{{ __('cbe_accounting.bank_reconciliation_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.unmatched-transaction-enquiry') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.unmatched-transaction-enquiry') ? 'active' : '' }}">{{ __('cbe_accounting.unmatched_transaction_enquiry_page_title') }}</a>
                <a href="{{ route('cbe.accounting.bank-reconciliation-reports-hub') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.bank-reconciliation-reports-hub') || request()->routeIs('cbe.accounting.reports.bank-reconciliation-listing') || request()->routeIs('cbe.accounting.reports.bank-account-listing') || request()->routeIs('cbe.accounting.reports.bank-transaction-report') || request()->routeIs('cbe.accounting.reports.bank-reconciliation-statement') || request()->routeIs('cbe.accounting.reports.outstanding-cheques') || request()->routeIs('cbe.accounting.reports.deposits-in-transit') || request()->routeIs('cbe.accounting.reports.unmatched-bank-transactions') || request()->routeIs('cbe.accounting.reports.bank-charges') || request()->routeIs('cbe.accounting.reports.bank-interest') || request()->routeIs('cbe.accounting.bank-reconciliation-audit-log') ? 'active' : '' }}">{{ __('cbe_accounting.br_reports_hub_page_title') }}</a>
            </div>
        </div>

        {{-- 7. Financial Reporting (Trial Balance/Balance Sheet/P&L/AP/AR
             Aging/GL report are cross-listed here AND under their own
             modules above — same routes, Chris's own list duplicates
             them across 2 modules on purpose.) --}}
        <div class="nav-parent nav-parent-sub {{ $finRepSubActive ? 'open' : '' }}" id="finRepSubParent" onclick="toggleFinSub('finRepSubMenu','finRepSubParent')">
            <div>{{ __('cbe_accounting.group_financial_reporting') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $finRepSubActive ? 'open' : '' }}" id="finRepSubMenu">
            <a href="{{ route('cbe.accounting.reports.trial-balance') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.trial-balance') ? 'active' : '' }}">{{ __('cbe_accounting.tile_trial_balance') }}</a>
            <a href="{{ route('cbe.accounting.reports.balance-sheet') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.balance-sheet') ? 'active' : '' }}">{{ __('cbe_accounting.tile_balance_sheet') }}</a>
            <a href="{{ route('cbe.accounting.reports.profit-loss') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.profit-loss') ? 'active' : '' }}">{{ __('cbe_accounting.tile_profit_loss') }}</a>
            <a href="{{ route('cbe.accounting.reports.ap-aging') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.ap-aging') ? 'active' : '' }}">{{ __('cbe_accounting.tile_ap_aging') }}</a>
            <a href="{{ route('cbe.accounting.reports.ar-aging') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.ar-aging') ? 'active' : '' }}">{{ __('cbe_accounting.tile_ar_aging') }}</a>
            <a href="{{ route('cbe.accounting.reports.general-ledger') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.general-ledger') ? 'active' : '' }}">{{ __('cbe_accounting.tile_general_ledger') }}</a>
            <a href="{{ route('cbe.accounting.reports.cash-flow-statement') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.cash-flow-statement') ? 'active' : '' }}">{{ __('cbe_accounting.tile_cash_flow_statement') }}</a>
            <a href="{{ route('cbe.accounting.reports.monthly-financial-summary') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.monthly-financial-summary') ? 'active' : '' }}">{{ __('cbe_accounting.tile_monthly_financial_summary') }}</a>
            {{-- REMOVED 26 Sep 2026 -- per Chris: one dashboard only, no duplicates; all dashboards live under Executive KPI Dashboard. Old link: <a href="{ { route('cbe.exec-dashboard') } }" class="sb-item live sb-item-sub { { request()->routeIs('cbe.exec-dashboard') ? 'active' : '' } }">{ { __('cbe.sb_exec_dashboard') } }</a> --}}
            <a href="{{ route('cbe.accounting.year-end-closing.pack') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.year-end-closing.pack') ? 'active' : '' }}">{{ __('cbe_accounting.tile_year_end_pack') }}</a>
        </div>

        {{-- 8. Year-End Closing (Period Lock cross-listed with General
             Ledger above.) --}}
        <div class="nav-parent nav-parent-sub {{ $yearEndSubActive ? 'open' : '' }}" id="yearEndSubParent" onclick="toggleFinSub('yearEndSubMenu','yearEndSubParent')">
            <div>{{ __('cbe_accounting.group_year_end_closing') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $yearEndSubActive ? 'open' : '' }}" id="yearEndSubMenu">
            <a href="{{ route('cbe.accounting.periods') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.periods*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_periods') }}</a>
            <a href="{{ route('cbe.accounting.year-end-closing') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.year-end-closing') ? 'active' : '' }}">{{ __('cbe_accounting.tile_year_end') }}</a>
            <a href="{{ route('cbe.accounting.reports.prior-year-comparison') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.prior-year-comparison') ? 'active' : '' }}">{{ __('cbe_accounting.tile_prior_year_comparison') }}</a>
        </div>

        {{-- 9. Audit Trail & Internal Control (Period Lock cross-listed
             again — same route as General Ledger + Year-End Closing.) --}}
        <div class="nav-parent nav-parent-sub {{ $auditSubActive ? 'open' : '' }}" id="auditSubParent" onclick="toggleFinSub('auditSubMenu','auditSubParent')">
            <div>{{ __('cbe_accounting.group_audit_trail') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $auditSubActive ? 'open' : '' }}" id="auditSubMenu">
            <a href="{{ route('cbe.accounting.approvals') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.approvals*') || request()->routeIs('cbe.accounting.approval-settings*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_approvals') }}</a>
            <a href="{{ route('cbe.accounting.transaction-history') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.transaction-history') ? 'active' : '' }}">{{ __('cbe_accounting.tile_transaction_history') }}</a>
            <a href="{{ route('cbe.accounting.periods') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.periods*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_periods') }}</a>
        </div>

        {{-- 10. ROS / Regulatory Reporting (Annual Report Pack is the
             same route as Financial Reporting's AGM Treasurer's Report
             pack above — one feature, cross-listed per Chris's list.) --}}
        <div class="nav-parent nav-parent-sub {{ $rosSubActive ? 'open' : '' }}" id="rosSubParent" onclick="toggleFinSub('rosSubMenu','rosSubParent')">
            <div>{{ __('cbe_accounting.group_ros_regulatory') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $rosSubActive ? 'open' : '' }}" id="rosSubMenu">
            <a href="{{ route('cbe.accounting.year-end-closing.pack') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.year-end-closing.pack') ? 'active' : '' }}">{{ __('cbe_accounting.tile_year_end_pack') }}</a>
            <a href="{{ route('cbe.accounting.reports.office-bearer-list') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.reports.office-bearer-list') ? 'active' : '' }}">{{ __('cbe_accounting.tile_office_bearer_list') }}</a>
            <a href="{{ route('cbe.accounting.ros-submission-checklist') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.accounting.ros-submission-checklist*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_ros_submission_checklist') }}</a>
        </div>
        {{-- NEW 2 Sep 2026 — Prev row back to the 6-category list, shown
             only while a category is expanded (see #finMenu.sub-focused
             CSS above). --}}
        <div class="sidebar-prev-btn-sub" onclick="closeFinSubFocus()"><i class="ti ti-arrow-back-up"></i> {{ __('sidebar.prev_fin_categories') }}</div>
        </div>

        <div class="nav-parent {{ $membershipActive ? 'open' : '' }}" id="membershipParent" onclick="toggleSection('membershipMenu','membershipParent')">
            <div>{{ __('nav.membership_module') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $membershipActive ? 'open' : '' }}" id="membershipMenu">

        {{-- Group 1: Administration — moved in from the removed
             Administration Management top-level section, shown first
             per Chris. Notice Board/Temple Calendar are this entity's
             own (every member reads; only officers/Secretary can
             post/edit/delete — see CbeCommitteeAuthService), distinct
             from Reminders (platform-wide company events + this agent's
             own personal reminders). --}}
        {{-- RESTRUCTURED 17 Sep 2026 — per Chris: the old single
             "Administration" group had grown to 12 items and no longer
             fit one glance. Split into 5 groups by what the Secretary
             is actually doing in each: Meetings, Notices & Messages,
             Documents & Records, Activities, and AI Tools (kept
             separate on its own per Chris's own answer, since it isn't
             record-keeping and may grow more AI features later). --}}
        <div class="nav-parent nav-parent-sub {{ $secMeetingsSubActive ? 'open' : '' }}" id="secMeetingsSubParent" onclick="toggleSecSub('secMeetingsSubMenu','secMeetingsSubParent')">
            <div>{{ __('sidebar.sec_meetings') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secMeetingsSubActive ? 'open' : '' }}" id="secMeetingsSubMenu">
            <a href="{{ route('cbe.minutes.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.minutes.*') ? 'active' : '' }}">{{ __('cbe.sb_meeting_minutes') }}</a>
        </div>

        <div class="nav-parent nav-parent-sub {{ $secNoticesSubActive ? 'open' : '' }}" id="secNoticesSubParent" onclick="toggleSecSub('secNoticesSubMenu','secNoticesSubParent')">
            <div>{{ __('sidebar.sec_notice_board') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secNoticesSubActive ? 'open' : '' }}" id="secNoticesSubMenu">
            <a href="{{ route('cbe.notice-board.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.notice-board.*') ? 'active' : '' }}">{{ __('cbe.sb_temple_notice_board') }}</a>
            <a href="{{ route('cbe.blast.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.blast.*') ? 'active' : '' }}">{{ __('cbe.sb_blast') }}</a>
        </div>

        {{-- NEW 18 Sep 2026 — per Chris: this used to be lumped into
             Notice Board even though it's a different job — a member
             asking the Secretary something one-on-one, not the
             Secretary broadcasting to everyone. --}}
        <div class="nav-parent nav-parent-sub {{ $secCommsSubActive ? 'open' : '' }}" id="secCommsSubParent" onclick="toggleSecSub('secCommsSubMenu','secCommsSubParent')">
            <div>{{ __('sidebar.sec_member_communication') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secCommsSubActive ? 'open' : '' }}" id="secCommsSubMenu">
            <a href="{{ route('cbe.messaging.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.messaging.*') ? 'active' : '' }}">{{ __('cbe.sb_cbe_messaging') }}</a>
            <a href="{{ route('cbe.tickets.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.tickets.*') ? 'active' : '' }}">{{ __('cbe.sb_cbe_tickets') }}</a>
        </div>

        {{-- UPDATED 18 Sep 2026 — per Chris: statutory/regulatory duties
             (ROS deadlines) named first so they don't get buried behind
             ordinary document filing. --}}
        <div class="nav-parent nav-parent-sub {{ $secDocsSubActive ? 'open' : '' }}" id="secDocsSubParent" onclick="toggleSecSub('secDocsSubMenu','secDocsSubParent')">
            <div>{{ __('sidebar.sec_compliance_records') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secDocsSubActive ? 'open' : '' }}" id="secDocsSubMenu">
            <a href="{{ route('cbe.compliance-items.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.compliance-items.*') ? 'active' : '' }}">{{ __('cbe.sb_compliance') }}</a>
            <a href="{{ route('cbe.documents.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.documents.*') ? 'active' : '' }}">{{ __('cbe.sb_documents') }}</a>
            <a href="{{ route('cbe.correspondences.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.correspondences.*') ? 'active' : '' }}">{{ __('cbe.sb_correspondences') }}</a>
        </div>

        {{-- UPDATED 18 Sep 2026 — per Chris: Events Calendar moved here
             from Notice Board — planning what's coming (Calendar) and
             logging what happened (Activities) are the same diary, just
             viewed from two directions; both feed the ROS Annual
             Activity Report together. --}}
        <div class="nav-parent nav-parent-sub {{ $secActivitiesSubActive ? 'open' : '' }}" id="secActivitiesSubParent" onclick="toggleSecSub('secActivitiesSubMenu','secActivitiesSubParent')">
            <div>{{ __('sidebar.sec_activities_calendar') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secActivitiesSubActive ? 'open' : '' }}" id="secActivitiesSubMenu">
            <a href="{{ route('cbe.temple-calendar.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.temple-calendar.*') ? 'active' : '' }}">{{ __('cbe.sb_temple_calendar') }}</a>
            <a href="{{ route('cbe.activities.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.activities.*') ? 'active' : '' }}">{{ __('cbe.sb_activities') }}</a>
        </div>

        <div class="nav-parent nav-parent-sub {{ $secAiSubActive ? 'open' : '' }}" id="secAiSubParent" onclick="toggleSecSub('secAiSubMenu','secAiSubParent')">
            <div>{{ __('sidebar.sec_ai_tools') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secAiSubActive ? 'open' : '' }}" id="secAiSubMenu">
            <a href="{{ route('cbe.ai-assistants.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.ai-assistants.*') ? 'active' : '' }}">{{ __('cbe.sb_ai_assistants') }}</a>
        </div>


        {{-- NEW 18 Sep 2026 — per Chris: CBE KPI used to sit in the main
             dashboard's Admin-only KPI Dashboard group (renamed from
             Overview KPI) alongside Vendor KPI/Customer KPI (DSG/ORG
             figures) — but CBE performance is Secretarial's own
             responsibility, not something that belongs mixed in with
             DSG/ORG numbers. Moved here; Vendor KPI/Customer KPI are
             untouched on the main dashboard. --}}

        {{-- Group 2: Donor Management. cbe.donors.* (Donor Register)
             intentionally has no highlight of its own here — Task #418
             already settled that AR (Financial Accounting Module) keeps
             sole ownership of that route's highlight; the link itself
             still lives here for navigation. --}}
        <div class="nav-parent nav-parent-sub {{ $secDonorSubActive ? 'open' : '' }}" id="secDonorSubParent" onclick="toggleSecSub('secDonorSubMenu','secDonorSubParent')">
            <div>{{ __('sidebar.sec_donor_management') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secDonorSubActive ? 'open' : '' }}" id="secDonorSubMenu">
            <a href="{{ route('admin.cbe-kpi.donors', $isOfficer ? ['node' => $officerNodeId] : []) }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-kpi.donors') ? 'active' : '' }}">{{ __('cbe_masterfile.donor_maintenance') }}</a>
            <a href="{{ route('cbe.donors.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.donors.*') ? 'active' : '' }}">{{ __('cbe.sb_donor_register') }}</a>
            {{-- NEW 28 Sep 2026 -- member file item 26: link donors / sponsors to the person or company, merge duplicates --}}
            @if($isAdmin || $isOfficer)
            <a href="{{ route('admin.donor-link.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.donor-link*') ? 'active' : '' }}">{{ __('member_file.donor_link_title') }}</a>
            @endif
        </div>

        {{-- Group 3: Consultant & Practitioner — every appointment and
             setup screen lives here, per Chris. Consultant Maintenance
             opens the same Members screen as Member Maintenance in
             Membership Management below (no separate Consultant table —
             per Chris's schema decision); kept as its own link here for
             wording clarity, same as before this restructure. --}}
        <div class="nav-parent nav-parent-sub {{ $secConsultSubActive ? 'open' : '' }}" id="secConsultSubParent" onclick="toggleSecSub('secConsultSubMenu','secConsultSubParent')">
            <div>{{ __('sidebar.sec_consultant_practitioner') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secConsultSubActive ? 'open' : '' }}" id="secConsultSubMenu">
            <a href="{{ route('admin.cbe-kpi.members', $isOfficer ? ['node' => $officerNodeId] : []) }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-kpi.members*') ? 'active' : '' }}">{{ __('cbe_masterfile.consultant_maintenance') }}</a>
            <a href="{{ route('admin.practitioners.index', $isOfficer ? ['node' => $officerNodeId] : []) }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.practitioners*') ? 'active' : '' }}">{{ __('cbe_masterfile.practitioner_setup') }}</a>
            <a href="{{ route('book-appointment') }}" class="sb-item live sb-item-sub {{ request()->routeIs('book-appointment') || request()->routeIs('book-appointment.*') ? 'active' : '' }}">{{ __('booking.page_title') }}</a>
            <a href="{{ route('my-appointments') }}" class="sb-item live sb-item-sub {{ request()->routeIs('my-appointments*') ? 'active' : '' }}">{{ __('booking.my_appointments') }}</a>
        </div>

        {{-- Group 4: Membership Management — Member/Entity Maintenance
             and Events, plus the 3 Admin-only queues folded in here per
             Chris's own answer rather than kept as a separate group. --}}
        <div class="nav-parent nav-parent-sub {{ $secMemberSubActive ? 'open' : '' }}" id="secMemberSubParent" onclick="toggleSecSub('secMemberSubMenu','secMemberSubParent')">
            <div>{{ __('sidebar.sec_membership_management') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $secMemberSubActive ? 'open' : '' }}" id="secMemberSubMenu">
            {{-- CHANGED 28 Sep 2026 -- per Chris: Admin opens the ONE member file for all CBEs (Member Maintenance); a CBE officer keeps his own entity's Members screen. --}}
            @if($isAdmin)
            <a href="{{ route('admin.member-file.landing') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.member-file*') ? 'active' : '' }}">{{ __('cbe_masterfile.member_maintenance') }}</a>
            @elseif($isOfficer)
            {{-- CHANGED 28 Sep 2026 -- item 36: a CBE officer uses the same member file, scoped to his own entity. --}}
            <a href="{{ route('admin.member-file.landing') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.member-file*') ? 'active' : '' }}">{{ __('cbe_masterfile.member_maintenance') }}</a>
            @endif
            {{-- MOVED 26 Sep 2026 -- per Chris: Entity Maintenance (CBE hierarchy) belongs in Master File Maintenance > Membership Master File. Kept here only for a CBE node officer, who has no Master File menu. --}}
            @if(! $isAdmin)
            @php($hnParams = $isOfficer ? ['group' => $officerGroupId] : [])
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create', $hnParams) }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-kpi.hierarchy-nodes*') ? 'active' : '' }}">{{ __('cbe_masterfile.entity_maintenance') }}</a>
            <a href="{{ route('admin.cbe-kpi.hierarchy-link') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-kpi.hierarchy-link*') ? 'active' : '' }}">{{ __('sidebar.entity_hierarchy_link') }}</a>
            @endif
            <a href="{{ route('cbe.events.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('cbe.events.*') ? 'active' : '' }}">{{ __('cbe.sb_events') }}</a>
            @if($isAdmin)
            <a href="{{ route('admin.masterfile.pending-verifications') }}" class="sb-item live sb-item-sub {{ request()->routeIs('*.masterfile.pending-verifications*') ? 'active' : '' }}">{{ __('sidebar.pending_verifications') }}</a>
            <a href="{{ route('admin.document-credit.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.document-credit*') ? 'active' : '' }}">{{ __('sidebar.document_credit') }}</a>
            <a href="{{ route('admin.fraud-review.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.fraud-review*') ? 'active' : '' }}">{{ __('sidebar.risk_review_queue') }}</a>
            @endif
        </div>



        <div class="sidebar-prev-btn-sub" onclick="closeSecSubFocus()"><i class="ti ti-arrow-back-up"></i> {{ __('sidebar.prev_sec_categories') }}</div>
        </div>


        {{-- NEW 13 Sep 2026 (Task #418) — per Chris: "Move Master file
             from Financial Module out from Financial Module to main
             dashboard after membership module before account" — the
             financial reference/master-data screens that used to be
             the "Master Files" hub inside Financial Accounting Module,
             now its own top-level section. Set Up CBE Group / Entity
             Maintenance stay in Membership Module only (not repeated
             here — see the moved-out comment above). --}}
        <div class="nav-parent {{ $masterFileMaintActive ? 'open' : '' }}" id="mfmParent" onclick="toggleSection('mfmMenu','mfmParent')">
            <div>{{ __('nav.master_file_maintenance') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $masterFileMaintActive ? 'open' : '' }}" id="mfmMenu">
            @if($isAdmin)
            {{-- NEW 19 Sep 2026 -- per Chris: "no scroll at the sidebar".
                 These 9 links used to sit flat here, pushing the menu
                 past one screen. Grouped into 2 collapsible sub-menus,
                 same toggleMfmSub() mechanism as Financial/Vendor Master
                 File below -- MOVED here, not deleted, so every route
                 still works. --}}
            {{-- RESTRUCTURED 24 Sep 2026 -- per Chris: "group & membership
                 set up put directory & catalog type under membership
                 master file rename group master & membership set up as
                 membership set up" -- Directory & Catalog Types used to
                 be its own sibling accordion sitting right next to this
                 one; it's now nested one level inside "Membership Setup"
                 (same nav-parent-subsub pattern already used for General
                 Ledger's/Financial Master File's own nested groups), and
                 this accordion's own label is shortened from "Group &
                 Membership Setup" to "Membership Setup". --}}
            <div class="nav-parent nav-parent-sub {{ $groupSetupSubActive ? 'open' : '' }}" id="groupSetupSubParent" onclick="toggleMfmSub('groupSetupSubMenu','groupSetupSubParent')">
                <div>{{ __('sidebar.group_membership_setup') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu {{ $groupSetupSubActive ? 'open' : '' }}" id="groupSetupSubMenu">
                {{-- MOVED 13 Sep 2026 (Task #418 follow-up) — per Chris: Group
                     Name & Hierarchy Levels relocated here from Membership
                     Module, placed before GLADE Membership Tiers. --}}
                <a href="{{ route('admin.masterfile.group-names', ['type' => 'CBE']) }}" class="sb-item live sb-item-sub {{ ((request()->routeIs('admin.masterfile.group-names') && request('type') === 'CBE') || request()->routeIs('admin.masterfile.group-names.*')) ? 'active' : '' }}">{{ __('sidebar.group_name_hierarchy_levels') }}</a>
                {{-- MOVED 26 Sep 2026 -- per Chris: "IT SHOULD BE IN MASTERFILE MEMBERSHIP MASTERFILE" -- Entity Maintenance (set up HQ/State/Branch/City and link entities under them). --}}
                <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-kpi.hierarchy-nodes*') ? 'active' : '' }}">{{ __('cbe_masterfile.entity_maintenance') }}</a>
                {{-- REMOVED 27 Sep 2026 -- per Chris: one program, one purpose. CBE Entity Affiliation is now done in
                     Group Name & Hierarchy Levels › (CBE) › Affiliate Group tab (auto-identify + auto-save). --}}
                <a href="{{ route('admin.glade-tiers.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.glade-tiers*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_glade_tiers') }}</a>

                {{-- CHANGED 25 Sep 2026 -- per Chris: "no need Directory &
                     Catalog, just display all the program, no need sub
                     menu" -- these 8 links now sit directly inside
                     Membership Setup, same single level as Group Name &
                     Hierarchy Levels / GLADE Membership Tiers above,
                     instead of being tucked inside their own nested
                     "Directory & Catalog Types" accordion. --}}
                <a href="{{ route('admin.faith-practice-types.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.faith-practice-types*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_faith_types') }}</a>
                {{-- NEW 15 Sep 2026 — per Chris: admin-editable Committee /
                     Management Positions master file (President, Deputy,
                     Secretary, Treasurer, CEO, Finance Director etc.), never
                     hardcoded — same catalog pattern as Faith Types above. --}}
                <a href="{{ route('admin.committee-position-types.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.committee-position-types*') ? 'active' : '' }}">{{ __('admin_committee_types.page_title') }}</a>
                {{-- NEW 28 Sep 2026 -- per Chris: member file master lists (Race, Religion, Dietary ...) and Membership Plans per CBE group. --}}
                <a href="{{ route('admin.member-pick-lists.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.member-pick-lists*') ? 'active' : '' }}">{{ __('member_file.pick_list_title') }}</a>
                <a href="{{ route('admin.membership-plans.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.membership-plans*') ? 'active' : '' }}">{{ __('member_file.plans_title') }}</a>
                {{-- NEW 25 Sep 2026 -- per Chris: "all committee related
                     program place here under membership master file" --
                     a shortcut to Member Maintenance so assigning an
                     actual person to a committee position sits right
                     next to the position catalog. Same screen already
                     reachable via Secretarial Management > Membership
                     Management, nothing duplicated behind it -- same
                     pattern already used for Practitioner Setup above. --}}
                {{-- CHANGED 28 Sep 2026 -- per Chris: Admin opens the ONE member file for all CBEs (Member Maintenance); a CBE officer keeps his own entity's Members screen. --}}
            @if($isAdmin)
            <a href="{{ route('admin.member-file.landing') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.member-file*') ? 'active' : '' }}">{{ __('cbe_masterfile.member_maintenance') }}</a>
            @elseif($isOfficer)
            {{-- CHANGED 28 Sep 2026 -- item 36: a CBE officer uses the same member file, scoped to his own entity. --}}
            <a href="{{ route('admin.member-file.landing') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.member-file*') ? 'active' : '' }}">{{ __('cbe_masterfile.member_maintenance') }}</a>
            @endif
                {{-- NEW 15 Sep 2026 — per Chris: appointment booking module.
                     Admin-editable catalog of practitioner types (Sensei,
                     Consultant, Legal Advisor, etc.), never hardcoded — same
                     pattern as Faith Types / Committee Positions above. --}}
                <a href="{{ route('admin.practitioner-types.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.practitioner-types*') ? 'active' : '' }}">{{ __('admin_practitioner_types.page_title') }}</a>
                {{-- NEW 24 Sep 2026 — per Chris: a shortcut to each
                     practitioner's own availability setup (weekly
                     hours, slot duration, max slots/day, booking
                     window, leave dates), placed right after
                     Practitioner Types since Chris expects it here
                     in Membership Master File. This is the exact
                     same screen also reachable via Secretarial
                     Management > Advisor & Practitioner — both
                     links go to the one screen, nothing duplicated
                     behind them. --}}
                <a href="{{ route('admin.practitioners.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.practitioners*') ? 'active' : '' }}">{{ __('cbe_masterfile.practitioner_setup') }}</a>
                {{-- NEW 17 Sep 2026 — per Chris: "all is automatic as by
                     default from the system unless the admin want to turn
                     off or edit or add." Behind the CBE Notice Board's
                     content-aware + seasonal styling. --}}
                <a href="{{ route('admin.cbe-notice-styles.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-notice-styles*') ? 'active' : '' }}">{{ __('admin_cbe_notice_styles.page_title') }}</a>
                <a href="{{ route('admin.cbe-season-themes.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-season-themes*') ? 'active' : '' }}">{{ __('admin_cbe_season_themes.page_title') }}</a>
                <a href="{{ route('admin.cbe-ticket-categories.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-ticket-categories*') ? 'active' : '' }}">{{ __('admin_cbe_ticket_categories.page_title') }}</a>
                {{-- NEW 17 Sep 2026 — per Chris ("yes build all this for me"):
                     Document Repository categories, same admin-editable
                     catalog pattern. --}}
                <a href="{{ route('admin.cbe-document-categories.index') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.cbe-document-categories*') ? 'active' : '' }}">{{ __('admin_cbe_document_categories.page_title') }}</a>
            </div>
            @endif
            {{-- NEW 13 Sep 2026 (Task #418) — per Chris: this menu had grown
                 to 25+ direct links and no longer fits one no-scroll screen.
                 Split into two hub sub-screens (each with its own Prev/Next):
                 Financial Master File holds the 16 accounting/finance links,
                 Vendor Master File holds vendor + CBE Marketplace. Reason
                 Code and Partner API Keys stay here at Master File
                 Maintenance level, moved to the bottom per Chris. --}}
            {{-- CONVERTED 13 Sep 2026 (Task #418 follow-up) — per Chris:
                 "when i click financial master file or Vendor Master file
                 it should open as sub menu NOT immediate the program
                 list" — both changed from a single click-through page
                 into an in-sidebar nested accordion, listing their child
                 screens directly, same nav-parent-sub/nav-submenu pattern
                 as the AR/AP/GL/FA/Bank Recon/AI Automation categories
                 inside Financial Accounting Module, scoped here via their
                 own MFM_SUB_IDS/toggleMfmSub() mechanism below so they
                 don't disturb that separate accordion. The old hub pages
                 (financial-master-file / vendor-master-file) and their
                 routes are left in place, just no longer linked from the
                 sidebar. --}}
            <div class="nav-parent nav-parent-sub {{ $fmfSubActive ? 'open' : '' }}" id="fmfSubParent" onclick="toggleMfmSub('fmfSubMenu','fmfSubParent')">
                <div>{{ __('sidebar.financial_master_file') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu {{ $fmfSubActive ? 'open' : '' }}" id="fmfSubMenu">
                {{-- NEW 19 Sep 2026 -- per Chris: "no scroll on sidebar",
                     twice on this exact menu. 16 flat links here was too
                     many even after Master File Maintenance's own split
                     above. Same nested-accordion pattern as General
                     Ledger's 4 groups (toggleGlNestedSub) -- one level
                     deeper (nav-parent-subsub), no sibling-hiding needed
                     since fmfSubMenu's own sub-focus (toggleMfmSub) above
                     already hides everything else at that level. --}}
                <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $fmfCustSuppGroupActive ? 'open' : '' }}" id="fmfCustSuppSubParent" onclick="toggleFmfNestedSub('fmfCustSuppSubMenu','fmfCustSuppSubParent')">
                    <div>{{ __('cbe_accounting.group_fmf_customers_suppliers') }}</div>
                    <span class="parrow">▼</span>
                </div>
                <div class="nav-submenu nav-submenu-subsub {{ $fmfCustSuppGroupActive ? 'open' : '' }}" id="fmfCustSuppSubMenu">
                    <a href="{{ route('cbe.accounting.customers') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.customers*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_debtor_master') }}</a>
                    <a href="{{ route('cbe.accounting.customer-categories') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.customer-categories*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_debtor_category') }}</a>
                    <a href="{{ route('cbe.accounting.suppliers') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.suppliers*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_creditor_master') }}</a>
                    <a href="{{ route('cbe.accounting.supplier-categories') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.supplier-categories*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_creditor_category') }}</a>
                </div>

                <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $fmfCoaGroupActive ? 'open' : '' }}" id="fmfCoaSubParent" onclick="toggleFmfNestedSub('fmfCoaSubMenu','fmfCoaSubParent')">
                    <div>{{ __('cbe_accounting.group_fmf_chart_of_accounts') }}</div>
                    <span class="parrow">▼</span>
                </div>
                <div class="nav-submenu nav-submenu-subsub {{ $fmfCoaGroupActive ? 'open' : '' }}" id="fmfCoaSubMenu">
                    {{-- REORDERED 23 Sep 2026 -- per Chris: Chart of Account
                         Category (the group each account belongs to) now sits
                         before the Structure Tree, since you pick a Category
                         before browsing the tree by it. --}}
                    <a href="{{ route('cbe.accounting.account-categories') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.account-categories*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_transaction_type') }}</a>
                    <a href="{{ route('cbe.accounting.chart-of-accounts-structure-tree') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.chart-of-accounts-structure-tree*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_coa_structure_tree') }}</a>
                <a href="{{ route('cbe.accounting.chart-of-accounts') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.chart-of-accounts', 'cbe.accounting.chart-of-accounts.*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_chart_of_accounts') }}</a>
                    {{-- ADDED 19 Sep 2026 -- per Chris: lets the treasurer link a
                         named category (e.g. "Meeting & Refreshment") straight to
                         its own GL account, without needing to already know the
                         Chart of Accounts structure -- used both on Bills/
                         Invoices/JVs and the AI Accounting Automation GL Account
                         dropdown. --}}
                    <a href="{{ route('cbe.accounting.transaction-categories') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.transaction-categories*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_transaction_categories') }}</a>
                    <a href="{{ route('cbe.accounting.document-number-control') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.document-number-control*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_doc_number_control') }}</a>
                </div>

                {{-- MOVED IN 24 Sep 2026 -- per Chris: "why this general
                     ledger have set up? it should all standardize in
                     master file, move to financial master file avoid
                     duplicate" -- was General Ledger's own "Setup" group
                     (glSetupSubMenu), duplicating this Financial Master
                     File section. Same 5 links, same routes, just
                     relocated here so master-data setup lives in one
                     place only. --}}
                <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $fmfGlSetupGroupActive ? 'open' : '' }}" id="fmfGlSetupSubParent" onclick="toggleFmfNestedSub('fmfGlSetupSubMenu','fmfGlSetupSubParent')">
                    <div>{{ __('cbe_accounting.group_fmf_gl_setup') }}</div>
                    <span class="parrow">▼</span>
                </div>
                <div class="nav-submenu nav-submenu-subsub {{ $fmfGlSetupGroupActive ? 'open' : '' }}" id="fmfGlSetupSubMenu">
                    <a href="{{ route('cbe.accounting.account-groups') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.account-groups*') ? 'active' : '' }}">{{ __('cbe_accounting.account_groups_page_title') }}</a>
                    <a href="{{ route('cbe.accounting.journal-types') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.journal-types*') ? 'active' : '' }}">{{ __('cbe_accounting.journal_types_page_title') }}</a>
                    <a href="{{ route('cbe.accounting.cost-centres') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.cost-centres*') ? 'active' : '' }}">{{ __('cbe_accounting.cost_centres_page_title') }}</a>
                    <a href="{{ route('cbe.accounting.opening-balances') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.opening-balances*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_opening_balances') }}</a>
                    <a href="{{ route('cbe.accounting.periods') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.periods*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_periods') }}</a>
                </div>

                <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $fmfFaGroupActive ? 'open' : '' }}" id="fmfFaSubParent" onclick="toggleFmfNestedSub('fmfFaSubMenu','fmfFaSubParent')">
                    <div>{{ __('cbe_accounting.group_fmf_fixed_assets') }}</div>
                    <span class="parrow">▼</span>
                </div>
                <div class="nav-submenu nav-submenu-subsub {{ $fmfFaGroupActive ? 'open' : '' }}" id="fmfFaSubMenu">
                    <a href="{{ route('cbe.accounting.asset-categories') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.asset-categories*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_fa_category') }}</a>
                    <a href="{{ route('cbe.accounting.asset-locations') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.asset-locations*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_fa_location') }}</a>
                </div>

                <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $fmfBankGroupActive ? 'open' : '' }}" id="fmfBankSubParent" onclick="toggleFmfNestedSub('fmfBankSubMenu','fmfBankSubParent')">
                    <div>{{ __('cbe_accounting.group_fmf_bank_payments') }}</div>
                    <span class="parrow">▼</span>
                </div>
                <div class="nav-submenu nav-submenu-subsub {{ $fmfBankGroupActive ? 'open' : '' }}" id="fmfBankSubMenu">
                    {{-- REMOVED 16 Sep 2026 — per Chris: "centralised all bank
                         related process in one menu ... cash and bank
                         management." This was a 3rd duplicate link to the
                         exact same Bank Account Number screen already at the
                         top of Financial Accounting Module → Cash & Bank
                         Management. Route/controller untouched, just removed
                         from this menu. --}}
                    <a href="{{ route('cbe.finance.bank-transaction-types') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.finance.bank-transaction-types*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_bank_transaction_types_master') }}</a>
                    <a href="{{ route('cbe.accounting.bank-reconciliation-rules') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.bank-reconciliation-rules*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_reconciliation_rules') }}</a>
                    <a href="{{ route('cbe.accounting.payment-terms') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.payment-terms*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_payment_terms_master') }}</a>
                    <a href="{{ route('cbe.accounting.payment-methods') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.payment-methods*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_payment_methods_master') }}</a>
                </div>

                <div class="nav-parent nav-parent-sub nav-parent-subsub {{ $fmfFundTaxGroupActive ? 'open' : '' }}" id="fmfFundTaxSubParent" onclick="toggleFmfNestedSub('fmfFundTaxSubMenu','fmfFundTaxSubParent')">
                    <div>{{ __('cbe_accounting.group_fmf_funds_tax') }}</div>
                    <span class="parrow">▼</span>
                </div>
                <div class="nav-submenu nav-submenu-subsub {{ $fmfFundTaxGroupActive ? 'open' : '' }}" id="fmfFundTaxSubMenu">
                    <a href="{{ route('cbe.accounting.funds') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.funds*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_fund_master') }}</a>
                    <a href="{{ route('cbe.accounting.tax-rates') }}" class="sb-item live sb-item-sub sb-item-subsub {{ request()->routeIs('cbe.accounting.tax-rates*') ? 'active' : '' }}">{{ __('cbe_accounting.tile_tax_rates_master') }}</a>
                </div>
            </div>


            <div class="nav-parent nav-parent-sub {{ $vmfSubActive ? 'open' : '' }}" id="vmfSubParent" onclick="toggleMfmSub('vmfSubMenu','vmfSubParent')">
                <div>{{ __('sidebar.vendor_master_file') }}</div>
                <span class="parrow">▼</span>
            </div>
            <div class="nav-submenu {{ $vmfSubActive ? 'open' : '' }}" id="vmfSubMenu">
                @if($isAdmin)
                <a href="{{ route('admin.masterfile.cbe-vendors') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.masterfile.cbe-vendors*') ? 'active' : '' }}">{{ __('cbe_vendors.page_title') }}</a>
                @endif
                <a href="{{ route('admin.masterfile.cbe-vendor-approvals') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.masterfile.cbe-vendor-approvals*') ? 'active' : '' }}">{{ __('cbe_vendors.approvals_page_title') }}</a>
                <a href="{{ route('admin.masterfile.cbe-marketplace-listings') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.masterfile.cbe-marketplace-listings*') ? 'active' : '' }}">{{ __('cbe_marketplace.listings_page_title') }}</a>
                <a href="{{ route('admin.masterfile.cbe-marketplace-orders') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.masterfile.cbe-marketplace-orders*') ? 'active' : '' }}">{{ __('cbe_marketplace.orders_page_title') }}</a>
                <a href="{{ route('admin.masterfile.cbe-marketplace-campaigns') }}" class="sb-item live sb-item-sub {{ request()->routeIs('admin.masterfile.cbe-marketplace-campaigns*') ? 'active' : '' }}">{{ __('cbe_marketplace.campaigns_page_title') }}</a>
            </div>

            @if($isAdmin)
            <a href="{{ route('admin.masterfile.reason-codes') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.reason-codes*') ? 'active' : '' }}">{{ __('sidebar.reason_code') }}</a>
            {{-- MOVED 24 Sep 2026 -- per Chris: "AI Master Data Assistant
                 move after Reason Code and dont immediate display sub
                 menu arrow" -- was previously the very first item inside
                 Master File Maintenance, sitting directly above the
                 Group & Membership Setup / Directory & Catalog Types
                 accordions, which made it look like it owned those two
                 arrow-headed sub-menus. Moved down here next to the
                 other flat, arrow-less links (Reason Code / Partner API
                 Keys / Program Library) so it no longer sits beside any
                 expandable sub-menu. --}}
            <a href="{{ route('admin.masterfile.ai-assistant') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.ai-assistant*') || request()->routeIs('cbe.accounting.chart-of-accounts.chat*') ? 'active' : '' }}">{{ __('sidebar.ai_master_data_assistant') }}</a>
            <a href="{{ route('admin.partner-api.index') }}" class="sb-item live {{ request()->routeIs('admin.partner-api.*') ? 'active' : '' }}">{{ __('sidebar.partner_api_keys') }}</a>
            {{-- MOVED 14 Sep 2026 — per Chris: Program Library goes last
                 in Master File Maintenance, after Partner API Keys. --}}
            <a href="{{ route('admin.masterfile.program-library') }}" class="sb-item live {{ request()->routeIs('admin.masterfile.program-library*') || request()->routeIs('admin.masterfile.program-unlocks*') ? 'active' : '' }}">{{ __('admin_program_library.page_title') }}</a>
            @endif

            <div class="sidebar-prev-btn-sub" onclick="closeMfmSubFocus()"><i class="ti ti-arrow-back-up"></i> {{ __('sidebar.prev_fin_categories') }}</div>
        </div>

        <div class="nav-parent {{ $accActive ? 'open' : '' }}" id="accParent" onclick="toggleSection('accMenu','accParent')">
            <div>{{ __('nav.my_account') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $accActive ? 'open' : '' }}" id="accMenu">
        <a href="{{ $profileRoute }}" class="sb-item live {{ request()->routeIs('*.profile*') ? 'active' : '' }}">{{ __('nav.my_profile') }}</a>
        <a href="{{ route('integrations.index') }}" class="sb-item live {{ request()->routeIs('integrations.*') ? 'active' : '' }}">{{ __('sidebar.my_integrations') }}</a>
        </div>
    </div>
    <div class="sidebar-prev-btn" id="sidebarPrevBtn" onclick="closeFocusedMenu()"><i class="ti ti-arrow-back-up"></i> {{ __('sidebar.prev_main_menu') }}</div>

    <div class="sidebar-footer">
        <div class="agent-card">
            <div class="agent-avatar">{{ strtoupper(substr($agent->full_name,0,2)) }}</div>
            <div style="flex:1;min-width:0">
                <div class="agent-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $agent->full_name }}</div>
                <div class="agent-code">{{ $agent->agent_code ?? $agent->member_code ?? '—' }}</div>
            </div>
            <form method="POST" action="{{ route('auth.logout') }}" onsubmit="try{localStorage.removeItem('gl_admin_dashboard_scope_v1');}catch(e){}">
                @csrf
                <button type="submit" style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.7);width:32px;height:32px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px" title="{{ __('nav.logout') }}">
                    <i class="ti ti-logout"></i>
                </button>
            </form>
        </div>
    </div>
</nav>
@endif
@if ($__env->hasSection('hide-sidebar'))
<style>#topbar{left:0 !important;}#main{left:0 !important;}</style>
@endif

{{-- NEW 14 Sep 2026 — per Chris: group-name-edit.blade.php (and any
     future tightly-fit, no-scroll GLADE screen) needs to opt out of
     this shared topbar the same way layouts/dashboard.blade.php
     already allows via @section('hide-topbar', '1'). Mirrors that
     mechanism exactly so GLADE-portal agents get the same no-topbar
     behaviour as DSG/Admin agents on the same screen. --}}
@if (! $__env->hasSection('hide-topbar'))
<header id="topbar">
    <div style="display:flex;align-items:center;">
        <span class="topbar-title">@yield('page-title', '')</span>
    </div>
    <div class="topbar-right" style="position:relative;">
        {{-- NEW 28 Aug 2026 — per Chris: "develop all the program, all the
             program that label with the word soon." Once Admin picks a
             CBE Group + entity via the shared picker, that choice is
             remembered for their whole visit (ResolvesCbeActiveNode) so
             they don't have to re-pick on every single screen — but they
             still need a quick way to work on a DIFFERENT entity without
             logging out. This clears the remembered choice and drops
             them back into the picker on whichever screen they're
             currently viewing. Officers never see this — they only ever
             have their own one entity. --}}
        @if($isAdmin && session('cbe_admin_active_node'))
        <a href="{{ url()->current() }}?reset_node=1" class="topbar-btn" style="width:auto;padding:0 10px;font-size:10.5px;font-weight:700;gap:5px;" title="{{ __('cbe_masterfile.switch_entity') }}">{{ __('cbe_masterfile.switch_entity') }}</a>
        @endif
        <div style="position:relative;">
            <a href="#" class="topbar-btn" title="{{ __('nav.notifications') }}" onclick="toggleNotifDropdown(event)">
                <i class="ti ti-bell"></i>
                <span class="badge-count" id="notifBadge" style="display:none;">0</span>
            </a>
            <div id="notifDropdown" style="display:none; position:absolute; top:110%; right:0; width:340px; max-height:420px; overflow-y:auto; background:#fff; border:1px solid var(--gl-cyan2); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:200;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid #f3f4f6;">
                    <strong style="font-size:12px; color:var(--gl-blue);">{{ __('nav.notifications') }}</strong>
                    <span onclick="markAllNotifsRead()" style="font-size:10.5px; color:var(--gl-blue); cursor:pointer;">{{ __('sidebar.mark_all_read') }}</span>
                </div>
                <div id="notifList" style="padding:6px 0;">
                    <div style="padding:16px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('sidebar.loading') }}</div>
                </div>
            </div>
        </div>
        {{-- NEW 27 Aug 2026 — per Chris: "whatapps" — same Send WhatsApp
             Message button as the blue dashboard's top bar, unchanged
             green brand color (WhatsApp's own color, not part of the
             blue→green swap). --}}
        <a href="{{ route('whatsapp.form') }}" class="topbar-btn" title="{{ __('nav.send_whatsapp') }}" style="color:#fff; background:#25D366; border-color:#25D366;" onmouseover="this.style.background='#1FAE55'" onmouseout="this.style.background='#25D366'">
            <i class="ti ti-brand-whatsapp"></i>
        </a>
        <div style="position:relative;">
            <a href="#" class="topbar-btn" title="{{ __('nav.language') }}" onclick="toggleLangDropdown(event)" style="width:auto; min-width:30px; padding:0 8px; font-size:10px; font-weight:700; letter-spacing:0.3px;">
                {{ $langShort }}
            </a>
            <div id="langDropdown" style="display:none; position:absolute; top:110%; right:0; width:170px; background:#fff; border:1px solid var(--gl-cyan2); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:200; overflow:hidden;">
                @foreach(['EN' => 'English', 'MS' => 'Bahasa Malaysia', 'ZH' => '中文 (Chinese)'] as $code => $label)
                <form method="POST" action="{{ route('language.quick-switch') }}">
                    @csrf
                    <input type="hidden" name="language" value="{{ $code }}">
                    <button type="submit" style="width:100%; text-align:left; background:{{ ($agent->preferred_language ?? 'EN') === $code ? 'var(--gl-light)' : '#fff' }}; border:none; padding:9px 14px; font-size:11.5px; color:#374151; cursor:pointer; {{ ($agent->preferred_language ?? 'EN') === $code ? 'font-weight:700; color:var(--gl-blue);' : '' }}">
                        {{ ($agent->preferred_language ?? 'EN') === $code ? '✓ ' : '' }}{{ $label }}
                    </button>
                </form>
                @endforeach
            </div>
        </div>
        <span style="font-size:11px; color:#546E7A; font-weight:600; margin:0 2px;">{{ $agent->full_name ?? '' }}</span>
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="topbar-btn" title="{{ __('nav.logout') }}">
                <i class="ti ti-logout"></i>
            </button>
        </form>
    </div>
</header>
@endif
@if ($__env->hasSection('hide-topbar'))
<style>#main{top:0 !important;}</style>
@endif

<main id="main">
    <div class="page-content">
        @yield('content')
    </div>
</main>

<script>
{{-- NEW 28 Aug 2026 — collapsible-sidebar JS, copied from
     layouts/dashboard.blade.php's own toggleSection/closeFocusedMenu/
     auto-focus pattern so GLADE behaves identically to the main
     dashboard. --}}
const SECTION_IDS = [
    { menu: 'execKpiMenu', parent: 'execKpiParent' },
    { menu: 'crmMenu', parent: 'crmParent' },
    { menu: 'mfmMenu', parent: 'mfmParent' },
    { menu: 'finMenu', parent: 'finParent' },
    // RESTRUCTURED 18 Sep 2026 — per Chris: Overview, Customer
    // Relationship, Action Required were pure duplicates of the main
    // DSG/ORG dashboard (removed here entirely — still untouched there).
    // Financial Accounting Module and Master File Maintenance are
    // genuinely CBE's own, so they moved to be nested sub-groups INSIDE
    // Secretarial Management instead of separate top-level sections —
    // see finMenu/mfmMenu now inside membershipMenu, tracked in
    // SEC_SUB_IDS below instead of here.
    { menu: 'membershipMenu', parent: 'membershipParent' },
    { menu: 'accMenu',      parent: 'accParent' },
];

// FIXED 26 Sep 2026 -- per Chris: "WHERE IS MEMBERSHIP MANAGEMENT
// MISSING IN MAIN DASHBOARD?" -- after opening a sub-group (e.g.
// Secretarial Management > Membership Management > Document Credit) and
// then pressing Prev / switching section, the Secretarial Management
// header kept its "hidden" flag, so it vanished from the main menu.
// Every section change now resets all nested sub-group states first.
function resetAllSubFocus() {
    if (typeof closeFinSubFocus === 'function') closeFinSubFocus();
    if (typeof closeMfmSubFocus === 'function') closeMfmSubFocus();
    if (typeof closeSecSubFocus === 'function') closeSecSubFocus();
    ['membershipParent', 'mfmParent', 'finParent'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.classList.remove('mship-header-hidden', 'mfm-header-hidden', 'fin-header-hidden'); }
    });
}

function toggleSection(menuId, parentId) {
    const menu   = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    const sidebar = document.getElementById('sidebar');
    const nav    = document.getElementById('sidebarNav');
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    resetAllSubFocus();
    SECTION_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    if (sidebar) { sidebar.classList.toggle('focused', !isOpen); }
    if (!isOpen && nav) {
        setTimeout(() => {
            const parentEl = document.getElementById(parentId);
            const navRect = nav.getBoundingClientRect();
            const parentRect = parentEl.getBoundingClientRect();
            nav.scrollTo({ top: nav.scrollTop + parentRect.top - navRect.top - 10, behavior: 'smooth' });
        }, 50);
    }
}

{{-- NEW 2 Sep 2026 — second accordion level, scoped only to the 6
     Financial Accounting Module categories: opening one closes the other
     5 but does NOT touch/close the outer SECTION_IDS sections above. --}}
{{-- UPDATED 16 Sep 2026 — brSubMenu/brSubParent and
     aiAutomationSubMenu/aiAutomationSubParent removed from this list:
     Bank Reconciliation and AI Accounting Automation were folded into
     cashBankSubMenu/cashBankSubParent (see the "Cash & Bank Management"
     section comment above) and those DOM ids no longer exist. --}}
const FIN_SUB_IDS = [
    { menu: 'glSubMenu',       parent: 'glSubParent' },
    { menu: 'cashBankSubMenu', parent: 'cashBankSubParent' },
    { menu: 'apSubMenu',       parent: 'apSubParent' },
    { menu: 'arSubMenu',       parent: 'arSubParent' },
    { menu: 'faSubMenu',       parent: 'faSubParent' },
    { menu: 'finRepSubMenu',   parent: 'finRepSubParent' },
    { menu: 'yearEndSubMenu',  parent: 'yearEndSubParent' },
    { menu: 'auditSubMenu',    parent: 'auditSubParent' },
    { menu: 'rosSubMenu',      parent: 'rosSubParent' },
];

function toggleFinSub(menuId, parentId) {
    const menu = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    const finMenu = document.getElementById('finMenu');
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    FIN_SUB_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    // NEW 2 Sep 2026 — per Chris: only the clicked category should show;
    // .sub-focused hides the other 5 category headers via the CSS rule
    // above (mirrors the outer #sidebar.focused mechanism one level in).
    if (finMenu) { finMenu.classList.toggle('sub-focused', !isOpen); }
}

// NEW 2 Sep 2026 — Prev row inside Financial Accounting Module: collapses
// back to the 6-category list without leaving the module itself (that
// still needs the outer Prev — Main Menu / closeFocusedMenu()).
function closeFinSubFocus() {
    FIN_SUB_IDS.forEach(({ menu: mId, parent: pId }) => {
        const m = document.getElementById(mId); if (m) m.classList.remove('open');
        const p = document.getElementById(pId); if (p) p.classList.remove('open');
    });
    const finMenu = document.getElementById('finMenu');
    if (finMenu) { finMenu.classList.remove('sub-focused'); }
}

// NEW 13 Sep 2026 (Task #418 follow-up) — third accordion instance,
// scoped only to the Financial Master File / Vendor Master File pair
// inside mfmMenu: opening one closes the other but does NOT touch
// FIN_SUB_IDS (Financial Accounting Module) or SECTION_IDS (top level).
const MFM_SUB_IDS = [
    { menu: 'groupSetupSubMenu', parent: 'groupSetupSubParent' },
    { menu: 'fmfSubMenu', parent: 'fmfSubParent' },
    { menu: 'vmfSubMenu', parent: 'vmfSubParent' },
];

function toggleMfmSub(menuId, parentId) {
    const menu = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    const mfmMenu = document.getElementById('mfmMenu');
    // FIXED 13 Sep 2026 (Task #418 follow-up) — per Chris: "it will ONLY
    // show the financial masterfile on top of the menu NO other programs"
    // — hiding the sibling items inside mfmMenu wasn't enough, since the
    // bold "MASTER FILE MAINTENANCE" header itself (mfmParent, a SIBLING
    // of mfmMenu, not a child, so the CSS hide-rule below can't reach it)
    // stayed visible above the Financial/Vendor list. Now hidden via JS
    // the same moment mfmMenu goes sub-focused, so Financial Master File
    // (or Vendor Master File) becomes the only thing showing at the top,
    // with just its own programs below and one Prev row back.
    const mfmParent = document.getElementById('mfmParent');
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    MFM_SUB_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    if (mfmMenu) { mfmMenu.classList.toggle('sub-focused', !isOpen); }
    if (mfmParent) { mfmParent.classList.toggle('mfm-header-hidden', !isOpen); }
}

function closeMfmSubFocus() {
    MFM_SUB_IDS.forEach(({ menu: mId, parent: pId }) => {
        const m = document.getElementById(mId); if (m) m.classList.remove('open');
        const p = document.getElementById(pId); if (p) p.classList.remove('open');
    });
    const mfmMenu = document.getElementById('mfmMenu');
    if (mfmMenu) { mfmMenu.classList.remove('sub-focused'); }
    // Bring back the "MASTER FILE MAINTENANCE" header when returning to
    // its own list via the Prev row below.
    const mfmParent = document.getElementById('mfmParent');
    if (mfmParent) { mfmParent.classList.remove('mfm-header-hidden'); }
}

// NEW 17 Sep 2026 — 5th accordion instance, scoped to the 4 Secretarial
// Management groups (Administration/Donor Management/Consultant &
// Practitioner/Membership Management) inside #membershipMenu — same
// mechanism as MFM_SUB_IDS/toggleMfmSub() above, independent of it.
const SEC_SUB_IDS = [
    { menu: 'secMeetingsSubMenu',   parent: 'secMeetingsSubParent' },
    { menu: 'secNoticesSubMenu',    parent: 'secNoticesSubParent' },
    { menu: 'secCommsSubMenu',      parent: 'secCommsSubParent' },
    { menu: 'secDocsSubMenu',       parent: 'secDocsSubParent' },
    { menu: 'secActivitiesSubMenu', parent: 'secActivitiesSubParent' },
    { menu: 'secAiSubMenu',         parent: 'secAiSubParent' },
    { menu: 'secDonorSubMenu',      parent: 'secDonorSubParent' },
    { menu: 'secConsultSubMenu',    parent: 'secConsultSubParent' },
    { menu: 'secMemberSubMenu',     parent: 'secMemberSubParent' },
];

function toggleSecSub(menuId, parentId) {
    const menu = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    const membershipMenu = document.getElementById('membershipMenu');
    const membershipParent = document.getElementById('membershipParent');
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    SEC_SUB_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    if (membershipMenu) { membershipMenu.classList.toggle('sub-focused', !isOpen); }
    if (membershipParent) { membershipParent.classList.toggle('mship-header-hidden', !isOpen); }
}

function closeSecSubFocus() {
    SEC_SUB_IDS.forEach(({ menu: mId, parent: pId }) => {
        const m = document.getElementById(mId); if (m) m.classList.remove('open');
        const p = document.getElementById(pId); if (p) p.classList.remove('open');
    });
    const membershipMenu = document.getElementById('membershipMenu');
    if (membershipMenu) { membershipMenu.classList.remove('sub-focused'); }
    const membershipParent = document.getElementById('membershipParent');
    if (membershipParent) { membershipParent.classList.remove('mship-header-hidden'); }
}

// NEW 16 Sep 2026 — per Chris: "create a sub menu AI Power Accounting
// ... same create bank reconciliation sub module" — a 4th accordion
// instance, one level DEEPER than FIN_SUB_IDS/MFM_SUB_IDS: scoped only
// to the AI Power Accounting / Bank Reconciliation pair nested inside
// cashBankSubMenu. Opening one closes the other, but — unlike
// toggleFinSub()/toggleMfmSub() above — does NOT hide any sibling
// headers or plain items (no .sub-focused / header-hidden class here),
// so cashBankSubMenu's own 5 plain links and both group headers always
// stay visible. Simpler on purpose: stacking the full hide-everything
// mechanism a 3rd level deep was judged not worth the extra risk of
// accordion-state bugs for what's really just a visual grouping.
const CASH_BANK_NESTED_IDS = [
    { menu: 'cashBankAiSubMenu', parent: 'cashBankAiSubParent' },
    { menu: 'cashBankBrSubMenu', parent: 'cashBankBrSubParent' },
];

function toggleCashBankNestedSub(menuId, parentId) {
    const menu = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    CASH_BANK_NESTED_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
}

// NEW 19 Sep 2026 -- per Chris: "all the sidebar cannot have scroll" --
// same simple nested-accordion as CASH_BANK_NESTED_IDS above, scoped to
// General Ledger's own 4 groups (Setup/Entries/Enquiries/Reports), which
// together replaced 21 flat items that no longer fit without scrolling.
const GL_NESTED_IDS = [
    { menu: 'glEntriesSubMenu', parent: 'glEntriesSubParent' },
    { menu: 'glEnquirySubMenu', parent: 'glEnquirySubParent' },
    { menu: 'glReportsSubMenu', parent: 'glReportsSubParent' },
];

function toggleGlNestedSub(menuId, parentId) {
    const menu = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    GL_NESTED_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
}

// NEW 19 Sep 2026 -- per Chris: "no scroll on sidebar" a second time,
// this time on Financial Master File specifically (16 flat items).
// Same simple nested-accordion as GL_NESTED_IDS above, scoped to Financial
// Master File's own 5 groups.
const FMF_NESTED_IDS = [
    { menu: 'fmfCustSuppSubMenu', parent: 'fmfCustSuppSubParent' },
    { menu: 'fmfCoaSubMenu', parent: 'fmfCoaSubParent' },
    { menu: 'fmfGlSetupSubMenu', parent: 'fmfGlSetupSubParent' },
    { menu: 'fmfFaSubMenu', parent: 'fmfFaSubParent' },
    { menu: 'fmfBankSubMenu', parent: 'fmfBankSubParent' },
    { menu: 'fmfFundTaxSubMenu', parent: 'fmfFundTaxSubParent' },
];

function toggleFmfNestedSub(menuId, parentId) {
    const menu = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    FMF_NESTED_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
}

function closeFocusedMenu() {
    resetAllSubFocus();
    SECTION_IDS.forEach(({ menu: mId, parent: pId }) => {
        const m = document.getElementById(mId); if (m) m.classList.remove('open');
        const p = document.getElementById(pId); if (p) p.classList.remove('open');
    });
    const sidebar = document.getElementById('sidebar');
    if (sidebar) { sidebar.classList.remove('focused'); }
}

(function() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const anyOpen = SECTION_IDS.some(function(s) {
        const p = document.getElementById(s.parent);
        return p && p.classList.contains('open');
    });
    if (anyOpen) { sidebar.classList.add('focused'); }
    // NEW 2 Sep 2026 — same check one level in: if the page loaded with a
    // financial category already active (e.g. landing directly on an AR
    // screen), #finMenu needs .sub-focused from the start too, or the
    // other 5 category headers would show until the next click.
    const finMenu = document.getElementById('finMenu');
    if (finMenu) {
        const anySubOpen = FIN_SUB_IDS.some(function(s) {
            const p = document.getElementById(s.parent);
            return p && p.classList.contains('open');
        });
        if (anySubOpen) { finMenu.classList.add('sub-focused'); }
    }
    // NEW 13 Sep 2026 (Task #418 follow-up) — same check for the new
    // Financial Master File / Vendor Master File accordion: if the page
    // loaded directly on one of their screens, #mfmMenu needs
    // .sub-focused from the start too.
    const mfmMenu = document.getElementById('mfmMenu');
    if (mfmMenu) {
        const anyMfmSubOpen = MFM_SUB_IDS.some(function(s) {
            const p = document.getElementById(s.parent);
            return p && p.classList.contains('open');
        });
        if (anyMfmSubOpen) {
            mfmMenu.classList.add('sub-focused');
            // FIXED 13 Sep 2026 (Task #418 follow-up) — same
            // header-hiding as toggleMfmSub() below, applied on a fresh
            // page load landing directly on a Financial/Vendor Master
            // File screen (e.g. a bookmark or browser refresh), so the
            // "MASTER FILE MAINTENANCE" header doesn't flash visible
            // until the next click.
            const mfmParent = document.getElementById('mfmParent');
            if (mfmParent) { mfmParent.classList.add('mfm-header-hidden'); }
        }
    }
    // NEW 17 Sep 2026 — same check for the new Secretarial Management
    // groups (Administration/Donor Management/Consultant & Practitioner/
    // Membership Management): if the page loaded directly on one of
    // their screens, #membershipMenu needs .sub-focused from the start.
    const membershipMenu = document.getElementById('membershipMenu');
    if (membershipMenu) {
        const anySecSubOpen = SEC_SUB_IDS.some(function(s) {
            const p = document.getElementById(s.parent);
            return p && p.classList.contains('open');
        });
        if (anySecSubOpen) {
            membershipMenu.classList.add('sub-focused');
            const membershipParent = document.getElementById('membershipParent');
            if (membershipParent) { membershipParent.classList.add('mship-header-hidden'); }
        }
    }
})();

function toggleLangDropdown(e) {
    e.preventDefault();
    var dd = document.getElementById('langDropdown');
    dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
}

function toggleNotifDropdown(e) {
    e.preventDefault();
    var dd = document.getElementById('notifDropdown');
    var isOpen = dd.style.display === 'block';
    dd.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) loadNotifications();
}

function loadNotifications() {
    fetch('{{ route('notifications.index') }}')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var badge = document.getElementById('notifBadge');
            if (!badge) return; // screens without the top bar have no badge
            if (data.unread_count > 0) {
                badge.style.display = 'flex';
                badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
            } else {
                badge.style.display = 'none';
            }

            var list = document.getElementById('notifList');
            if (!data.notifications.length) {
                list.innerHTML = '<div style="padding:16px; text-align:center; color:#9ca3af; font-size:11.5px;">No notifications yet.</div>';
                return;
            }
            list.innerHTML = '';
            data.notifications.forEach(function(n) {
                var unread = !n.read_at;
                var item = document.createElement('div');
                item.style.cssText = 'padding:9px 14px; border-bottom:1px solid #f3f4f6; cursor:pointer;' + (unread ? ' background:#f0f9ff;' : '');
                item.innerHTML = '<div style="font-size:11.5px; font-weight:' + (unread ? '700' : '500') + '; color:#1565C0;">' + n.title + '</div>' +
                                  '<div style="font-size:10.5px; color:#4b5563; margin-top:2px; white-space:pre-wrap;">' + n.message + '</div>' +
                                  '<div style="font-size:9px; color:#9ca3af; margin-top:3px;">' + n.created_at + '</div>';
                item.onclick = function() {
                    if (unread) {
                        fetch('/notifications/' + n.notification_id + '/read', {
                            method: 'POST',
                            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}
                        }).then(loadNotifications);
                    }
                };
                list.appendChild(item);
            });
        });
}

function markAllNotifsRead() {
    fetch('{{ route('notifications.read-all') }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}
    }).then(loadNotifications);
}

document.addEventListener('click', function(e) {
    var dd = document.getElementById('notifDropdown');
    if (dd && !dd.contains(e.target) && !e.target.closest('[onclick="toggleNotifDropdown(event)"]')) {
        dd.style.display = 'none';
    }
    var ld = document.getElementById('langDropdown');
    if (ld && !ld.contains(e.target) && !e.target.closest('[onclick="toggleLangDropdown(event)"]')) {
        ld.style.display = 'none';
    }
});

loadNotifications();
setInterval(loadNotifications, 30000);
</script>
{{-- NEW 27 Aug 2026 — per Chris: "carolyn also need in Glade ya" — same
     floating AI Assistant widget as the blue dashboard, self-contained
     (only needs guestMode + the logged-in agent, both already true
     here), so it's a straight include with no other wiring needed. --}}
@include('partials.ai-assistant-widget', ['guestMode' => false])
{{-- NEW 19 Sep 2026 -- "AI Accountant", per Chris: a separate agent
     from Carolyn for Chart of Accounts / GL Code questions. Admin-only
     for now (same scope as the "AI Master Data Assistant" sidebar item
     it's paired with) -- ordinary CBE officers/members have no reason
     to be adding GL codes.
     UPDATED 23 Sep 2026 -- per Chris: "you only display in Financial
     master file module not every screen because Accountant AI is only
     for Accounting master file modules related". Used to show on every
     glade-portal screen for Admin; now gated on $fmfSubActive too (the
     same flag the sidebar itself uses, just above, to decide whether
     the Financial Master File accordion is the active section), so it
     only appears on Chart of Accounts / Account Categories / Transaction
     Categories / Document Number Control / Asset Categories / Asset
     Locations / Customers / Suppliers / Tax Rates / Payment Terms /
     Payment Methods / Funds / Bank Reconciliation Rules -- the screens
     that actually live under Financial Master File. --}}
@if($isAdmin && $fmfSubActive)
@include('partials.ai-accountant-widget')
@endif
</body>
</html>
