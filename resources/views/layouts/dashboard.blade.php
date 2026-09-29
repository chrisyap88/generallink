<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — GeneralLink</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <style>
        :root{--gl-blue:#1565C0;--gl-blue2:#1E88E5;--gl-cyan:#00BCD4;--gl-cyan2:#B2EBF2;--gl-light:#E0F7FA;--sidebar-w:248px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f9ff;margin:0;padding:0;min-height:100vh;color:#263238;overflow-x:hidden;}
        #sidebar{position:fixed;top:0;left:0;height:100vh;width:calc(var(--sidebar-w) + 1px);background:linear-gradient(180deg,#1565C0 0%,#1976D2 60%,#1E88E5 100%);display:flex;flex-direction:column;z-index:100;}
        .sidebar-logo{flex-shrink:0;padding:10px 20px 8px;border-bottom:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.1);}
        .sidebar-logo h1{color:#fff;font-size:15px;font-weight:700;letter-spacing:1px;line-height:1.2;}
        .sidebar-logo p{color:rgba(255,255,255,0.55);font-size:9px;margin-top:1px;letter-spacing:0.5px;}
        .sidebar-role-badge{flex-shrink:0;margin:6px 16px;padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;text-align:center;letter-spacing:0.5px;}
        {{-- NEW 23 Jul 2026 (v12) — per Chris: "cannot see all option
             in action center" — hiding the OTHER top-level headings
             (v10 fix) still wasn't enough on a shorter screen once
             Action Center grew to 9 items across 2 groups. While a
             menu is open, the logo subtitle/badge shrink too, clawing
             back a bit more height on top of every row below also
             being tightened. --}}
        #sidebar.focused .sidebar-logo{padding:6px 20px 5px;}
        #sidebar.focused .sidebar-logo p{display:none;}
        #sidebar.focused .sidebar-role-badge{margin:4px 16px;padding:2px 10px;font-size:9px;}
        .badge-admin{background:#F6AD55;color:#7B341E;}
        .badge-gl{background:#68D391;color:#1C4532;}
        .badge-tl{background:#76E4F7;color:#065666;}
        .badge-introducer{background:#B794F4;color:#322659;}
        {{-- NEW 23 Jul 2026 (v9) — per Chris: menu items were "hidden"
             below the fold with no hint more existed to scroll to.
             Scrollbar was fully hidden before; now a thin, visible one
             (same treatment the rest of the site already uses) so an
             expanded menu longer than the visible area is obviously
             scrollable instead of just cutting off silently. --}}
        .sidebar-nav{flex:1;min-height:0;overflow-y:auto;padding:4px 0 8px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,0.35) transparent;}
        .sidebar-nav::-webkit-scrollbar{width:4px;}
        .sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.35);border-radius:4px;}
        {{-- TIGHTENED 12 Aug 2026 per Chris: "the row height in between
             please adjust it smaller like the customer relation sub
             menu row height" — top-level main-menu rows (.nav-item,
             .nav-parent) had more vertical padding/margin than the
             tighter submenu rows (.nav-subitem), making the main menu
             feel loosely spaced by comparison. Padding and the gap
             between category headings now match the submenu's rhythm. --}}
        .nav-item{display:flex;align-items:center;gap:6px;padding:4px 12px;color:rgba(255,255,255,0.75);font-size:10.5px;text-decoration:none;transition:all 0.15s;border-left:3px solid transparent;line-height:1.2;white-space:nowrap;overflow:visible;}
        .nav-item:hover{background:rgba(255,255,255,0.1);color:#fff;border-left-color:rgba(255,255,255,0.3);}
        .nav-item.active{background:rgba(255,255,255,0.15);color:#fff;border-left-color:#00BCD4;}
        .nav-item i{font-size:13px;width:15px;text-align:center;flex-shrink:0;}
        .nav-item-2row{align-items:flex-start;padding-top:5px;padding-bottom:5px;}
        .nav-item-2row i{margin-top:1px;}
        #sidebar.focused .nav-item{padding:3px 12px;}
        {{-- TIGHTENED 8 Aug 2026 per Chris: "all dashboard menu description
             must display in one row" — every label (including the new,
             longer "Communication & Action Center") now needs to fit
             without wrapping. white-space:nowrap enforces one row;
             --sidebar-w/font-size/letter-spacing/padding below were all
             tuned together so nothing gets cut off doing it. --}}
        .nav-parent{display:flex;align-items:center;justify-content:space-between;padding:4px 8px;color:rgba(255,255,255,0.85);font-size:8.5px;font-weight:700;letter-spacing:0px;text-transform:uppercase;cursor:pointer;transition:all 0.15s;border-left:3px solid transparent;user-select:none;margin-top:2px;line-height:1.2;white-space:nowrap;}
        #sidebar.focused .nav-parent.open{padding:3px 8px;margin-top:2px;}
        .nav-parent:hover{background:rgba(255,255,255,0.08);color:#fff;}
        .nav-parent.open{color:#fff;}
        .nav-parent .pleft{display:flex;align-items:center;gap:5px;white-space:nowrap;min-width:0;}
        .nav-parent .parrow{flex-shrink:0;}
        {{-- FIXED 8 Aug 2026 — per Chris: "standard the display all in
             icon not some with right arrow." Every category header now
             carries a real icon (see the ▶-less .pleft markup above)
             sized to match every leaf item's icon (.nav-item i) exactly,
             so collapsed (icon-only) mode looks consistent everywhere
             instead of some rows showing a real icon and others just a
             clipped ▶ character. --}}
        .nav-parent .pleft i{font-size:13px;width:15px;text-align:center;flex-shrink:0;}
        .nav-parent .parrow{font-size:9px;transition:transform 0.25s;opacity:0.6;}
        .nav-parent.open .parrow{transform:rotate(180deg);opacity:1;}
        {{-- NEW 23 Jul 2026 (v10), REDONE 8 Aug 2026 — per Chris: "WHEN I
             CLICK THE MENU DRILL DOWN YOU SHOULD SHOW EVERYTHING TO ME NOT
             SCROLL DOWN", then later: "the opening of any menu ... display
             on the top AND others not related menu selection should not
             displace but have a prev button ... to click back to main
             menu." Opening a category (e.g. Action Center) hides every
             OTHER top-level heading (Network Tree, Business, Master File
             Maintenance, etc.) so the open one gets the full sidebar
             height for its items — no unrelated headings pushed around,
             no scrolling to find items. FIXED at the same time: this rule
             requires the .focused class on #sidebar itself, but the JS
             was toggling it on the inner #sidebarNav div, so it silently
             never applied — the sidebar looked identical whether or not
             this comment's rule existed. Getting back to the main menu no
             longer requires re-clicking the same still-visible header —
             see the sidebar-prev-btn bar below, shown only in this
             drilled-down state. --}}
        #sidebar.focused #sidebarNav > .nav-parent:not(.open){display:none;}
        .sidebar-prev-btn{display:none;flex-shrink:0;align-items:center;gap:6px;padding:8px 14px;color:#fff;font-size:10.5px;font-weight:600;cursor:pointer;background:rgba(0,0,0,0.15);border-top:1px solid rgba(255,255,255,0.15);user-select:none;transition:background 0.15s;}
        .sidebar-prev-btn:hover{background:rgba(0,0,0,0.25);}
        .sidebar-prev-btn i{font-size:14px;}
        #sidebar.focused .sidebar-prev-btn{display:flex;}
        .nav-submenu{max-height:0;overflow:hidden;transition:max-height 0.3s ease;background:rgba(0,0,0,0.08);}
        .nav-submenu.open{max-height:2000px;}
        .nav-subitem{display:flex;align-items:center;gap:6px;padding:4px 12px 4px 24px;color:rgba(255,255,255,0.7);font-size:10px;text-decoration:none;transition:all 0.15s;border-left:3px solid transparent;line-height:1.2;white-space:nowrap;}
        .nav-subitem:hover{background:rgba(255,255,255,0.1);color:#fff;border-left-color:rgba(255,255,255,0.3);}
        .nav-subitem.active{background:rgba(255,255,255,0.15);color:#fff;border-left-color:#00BCD4;}
        .nav-subitem i{font-size:12px;width:14px;text-align:center;flex-shrink:0;}
        #sidebar.focused .nav-subitem{padding:2px 10px 2px 22px;font-size:9.5px;line-height:1.1;}
        .nav-deepitem{display:flex;align-items:center;gap:6px;padding:4px 12px 4px 36px;color:rgba(255,255,255,0.6);font-size:9.5px;text-decoration:none;transition:all 0.15s;border-left:3px solid transparent;line-height:1.2;white-space:nowrap;}
        .nav-deepitem:hover{background:rgba(255,255,255,0.08);color:#fff;}
        .nav-deepitem.active{background:rgba(255,255,255,0.12);color:#fff;border-left-color:#00BCD4;}
        .nav-deepitem i{font-size:11px;width:13px;text-align:center;flex-shrink:0;}
        #sidebar.focused .nav-deepitem{padding:2px 10px 2px 34px;font-size:9px;line-height:1.1;}
        .nav-subparent{display:flex;align-items:center;justify-content:space-between;padding:4px 12px 4px 24px;color:rgba(255,255,255,0.7);font-size:10px;cursor:pointer;transition:all 0.15s;border-left:3px solid transparent;line-height:1.2;user-select:none;white-space:nowrap;}
        #sidebar.focused .nav-subparent{padding:2px 10px 2px 22px;font-size:9.5px;line-height:1.1;}
        .nav-subparent:hover{background:rgba(255,255,255,0.1);color:#fff;}
        .nav-subparent.open{color:#fff;background:rgba(255,255,255,0.08);}
        .nav-subparent .spleft{display:flex;align-items:center;gap:5px;white-space:nowrap;min-width:0;}
        .nav-subparent .sparrow{font-size:8px;transition:transform 0.2s;opacity:0.6;flex-shrink:0;}
        .nav-subparent.open .sparrow{transform:rotate(180deg);opacity:1;}
        .nav-subparent i{font-size:12px;width:14px;text-align:center;flex-shrink:0;}
        .nav-deep{max-height:0;overflow:hidden;transition:max-height 0.25s ease;background:rgba(0,0,0,0.08);}
        .nav-deep.open{max-height:1000px;}
        .sidebar-footer{flex-shrink:0;padding:8px 16px;border-top:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.1);}
        .agent-card{display:flex;align-items:center;gap:10px;}
        .agent-avatar{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);}
        /* CHANGED 9 Aug 2026 per Chris: "reduce the font size for admin
           Director same as menu textfont size" — was 13px/11px, larger
           than the sidebar's own menu items (.nav-item is 10.5px), which
           is also why long names/roles (e.g. "Admin Director") could run
           out of room and get ellipsis-truncated in the narrow footer
           row. Now matches .nav-item exactly. */
        .agent-name{color:#fff;font-size:10.5px;font-weight:500;}
        .agent-code{color:rgba(255,255,255,0.45);font-size:9.5px;}
        #topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:46px;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-bottom:1px solid #B2EBF2;display:flex;align-items:center;justify-content:space-between;padding:0 16px;z-index:90;box-shadow:0 2px 10px rgba(0,150,200,0.08);}
        .topbar-title{font-size:13px;font-weight:600;color:#1565C0;}
        .topbar-right{display:flex;align-items:center;gap:10px;}
        .topbar-btn{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#1E88E5;background:#E0F7FA;border:1px solid #B2EBF2;cursor:pointer;font-size:16px;position:relative;text-decoration:none;transition:all 0.15s;}
        .topbar-btn:hover{background:#B2EBF2;color:#1565C0;}
        .badge-count{position:absolute;top:-4px;right:-4px;width:14px;height:14px;border-radius:50%;background:#F44336;color:#fff;font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;}
        {{-- NEW 18 Aug 2026 — per Chris: the "文A" language icon in the
             topbar was confusing on its own; this styled tooltip appears
             on hover and spells out the actual language choices (ENG /
             BM / 中文) so agents understand what the icon does before
             clicking it. Same font/colour scheme as the rest of the app
             — not a browser-default title tooltip, which is too small
             and slow to appear. Reusable on any .topbar-btn via
             data-tip="...". --}}
        .gl-tip{position:relative;}
        .gl-tip::after{content:attr(data-tip);position:absolute;top:120%;right:0;white-space:nowrap;background:#1565C0;color:#fff;font-family:'Poppins','Outfit',sans-serif;font-size:10.5px;font-weight:600;padding:5px 10px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.18);opacity:0;pointer-events:none;transform:translateY(-4px);transition:opacity .15s, transform .15s;z-index:300;}
        .gl-tip:hover::after{opacity:1;transform:translateY(0);}
        #main{position:absolute;top:46px;left:var(--sidebar-w);right:0;bottom:0;background:#E0F7FA;overflow:hidden;}
        {{-- FIXED 19 Jul 2026 — genuine sitewide bug, same class as the
             earlier Vendor Edit "no save button" issue but one level
             higher up: this container was overflow:hidden with a fixed
             height, so ANY page whose content is taller than the visible
             screen (e.g. Customer Edit — contact fields, address,
             postcode/city/state, then Save Changes) gets silently clipped
             off the bottom with no way to scroll to it. The Save button
             was never missing from the code, just invisible. Pages like
             Submit Sales Transaction that manage their own internal
             scroll region (calc(100vh - 46px) + their own overflow-y:auto
             sub-div) are unaffected, since their content already fits
             exactly and never overflows this outer container. --}}
        .page-content{padding:0;height:100%;overflow-y:auto;box-sizing:border-box;width:100%;}
        .card{background:rgba(255,255,255,0.85);backdrop-filter:blur(8px);border-radius:14px;border:1px solid #B2EBF2;padding:20px;box-shadow:0 4px 15px rgba(0,150,200,0.06);}
        .metric-card{background:rgba(255,255,255,0.85);border-radius:12px;border:1px solid #B2EBF2;padding:16px 20px;box-shadow:0 4px 15px rgba(0,150,200,0.06);}
        .skeleton{background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:4px;min-height:24px;}
        @keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
        #sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:99;}

        {{-- REVERTED 8 Aug 2026 — desktop icon-collapse mode (:root.gl-sb-collapsed)
             removed per Chris: "resume back as previous design." Sidebar is
             back to always showing full text at a fixed width — see the
             drill-down + Prev button redesign below instead, which is the
             new answer to "menu panel takes up room after I've picked
             something." --}}

        {{-- FIXED 13 Sep 2026 (Task #418 follow-up) — per Chris: matches
             the same breakpoint change on layouts/glade.blade.php, so the
             sidebar behaves consistently on both layouts and never
             disappears on a normal desktop window. --}}
        @media(max-width:480px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);}#topbar{left:0;}#main{left:0;}#sidebar-overlay.show{display:block;}}
        ::-webkit-scrollbar{width:4px;}
        ::-webkit-scrollbar-thumb{background:#B2EBF2;border-radius:4px;}
    </style>
    @stack('styles')
</head>
<body>
<div id="sidebar-overlay" onclick="closeSidebar()"></div>
<nav id="sidebar">
    <div class="sidebar-logo">
        <h1>🔗 {{ __('nav.brand_name') }}</h1>
        <p>{{ __('nav.brand_tagline') }}</p>
    </div>

    @php
        $agent = auth('agent')->user();

        // NEW 21 Jul 2026 — Notice Board unread count, computed here
        // (same self-contained pattern as $agent above) so the sidebar
        // link can show a red badge without every controller needing
        // to pass it. Only relevant for non-Admin roles — Admin manages
        // the board rather than reading it.
        $noticeUnreadCount = 0;
        if ($agent->role !== 'ADMIN') {
            $noticeToday = now()->toDateString();
            $noticeUnreadCount = \Illuminate\Support\Facades\DB::table('notices as n')
                ->leftJoin('notice_reads as r', function ($join) use ($agent) {
                    $join->on('n.notice_id', '=', 'r.notice_id')->where('r.agent_id', '=', $agent->agent_id);
                })
                ->where('n.is_deleted', false)
                ->where(function ($q) use ($noticeToday) {
                    $q->whereNull('n.expires_at')->orWhere('n.expires_at', '>=', $noticeToday);
                })
                ->whereNull('r.read_id')
                ->count();
        }

        // NEW 21 Jul 2026 — Help Desk unread count, same self-contained
        // pattern. Unread = a thread where I'm the initiator or the
        // recipient and the other side has sent something since I last
        // looked (CC'd-only threads aren't counted here — read-only,
        // less urgent than a thread that's actually addressed to me).
        $helpDeskUnreadCount = \Illuminate\Support\Facades\DB::table('help_desk_threads')
            ->where(function ($q) use ($agent) {
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
                // MERGED 12 Aug 2026 per Chris: Carolyn AI Tickets folded
                // into Help Desk — an open Carolyn-logged ticket counts
                // as unread for every Admin (no single recipient to mark
                // it "viewed" for), same as the pending-count badges used
                // elsewhere in the app.
                if ($agent->role === 'ADMIN') {
                    $q->orWhere(function ($q2) {
                        $q2->where('created_by_type', 'CAROLYN_AI')->where('status', 'OPEN');
                    });
                }
            })
            ->count();

        // NEW 22 Jul 2026 — Risk Review Queue badge, Admin-only:
        // unresolved (OPEN/UNDER_REVIEW) HIGH or CRITICAL flags need
        // attention soonest; MEDIUM/LOW just sit in the queue without
        // demanding a badge.
        $fraudReviewUrgentCount = 0;
        if ($agent->role === 'ADMIN') {
            $fraudReviewUrgentCount = \Illuminate\Support\Facades\DB::table('fraud_review_flags')
                ->whereIn('status', ['OPEN', 'UNDER_REVIEW'])
                ->whereIn('risk_level', ['HIGH', 'CRITICAL'])
                ->count();
        }

        $badgeClass = match($agent->role) {
            'ADMIN'        => 'badge-admin',
            'GROUP_LEADER' => 'badge-gl',
            'TEAM_LEADER'  => 'badge-tl',
            default        => 'badge-introducer',
        };
        // FIXED 23 Jul 2026 — per Chris: Group Leader/Team Leader/
        // Introducer can now be renamed system-wide (Organization
        // Category Maintenance, Admin-only). Admin keeps a fixed
        // "Administrator" label (not part of the renameable 3);
        // the other 3 now resolve through RoleLabelService instead of
        // being hardcoded here, so a rename shows up in this badge
        // immediately.
        $roleLabel = $agent->role === 'ADMIN'
            ? __('sidebar.administrator')
            : \App\Services\RoleLabelService::label($agent->role);
        // CHANGED AGAIN 6 Aug 2026 — per Chris: the separate tabbed Edit
        // Profile page is retired. "My Profile" is now ONE unified screen
        // (view + inline edit together, see admin/profile/show.blade.php)
        // living at the .show route — there's no second page to link to
        // anymore.
        $profileRoute = match($agent->role) {
            'ADMIN'        => route('admin.profile.show'),
            'GROUP_LEADER' => route('gl.profile.show'),
            'TEAM_LEADER'  => route('tl.profile.show'),
            default        => route('introducer.profile.show'),
        };
        $rewardsRoute = match($agent->role) {
            'GROUP_LEADER' => route('gl.rewards'),
            'TEAM_LEADER'  => route('tl.rewards'),
            'INTRODUCER'   => route('introducer.rewards'),
            default        => '#',
        };
        // MOVED 2 Aug 2026 per Chris: Pending Assignment lives in Action
        // Center now (it's a "need to act" item, not a tree-browsing
        // screen) — excluded here so it no longer auto-opens Network Tree.
        // admin.agents.show/index (agent profile, reached from network
        // drill-down rows) still count as Network Tree activity.
        $networkActive  = (request()->routeIs('admin.agents*') && !request()->routeIs('admin.agents.pending')) || request()->routeIs('admin.network*') || request()->routeIs('gl.network*') || request()->routeIs('tl.introducers*') || request()->routeIs('introducer.recruits*');
        $businessActive = request()->routeIs('*.transactions*') || request()->routeIs('*.commissions*') || request()->routeIs('*.rewards*') || request()->routeIs('*.earning-ledger*');

        // NEW 28 Jul 2026 — CUSTOMER RELATIONSHIP, its own top-level menu.
        // Per Chris: going forward with EspoCRM as the CRM engine (follow-
        // up tasks/calendar move there), so every screen that's actually
        // about the customer RECORD itself (not a task/reminder) is
        // consolidated here instead of being scattered across Network
        // Tree, Business, Master File Maintenance and Growth & Outreach.
        $crmActive = request()->routeIs('*.customers*')
            // MOVED 9 Aug 2026 — for Admin, Customer KPI now opens the
            // Overview KPI menu instead (see $overviewKpiActive above);
            // it no longer has a link inside Customer Relationship, so it
            // shouldn't force that menu open too.
            || (request()->routeIs('customer-kpi*') && $agent->role !== 'ADMIN')
            || request()->routeIs('customer-referrals*')
            || request()->routeIs('admin.masterfile.customer-statuses*')
            || request()->routeIs('admin.masterfile.customer-types*')
            || request()->routeIs('admin.masterfile.customer-categories*')
            || request()->routeIs('admin.masterfile.occupation-groups*')
            || request()->routeIs('admin.masterfile.customer-sources*');
        $crmPrefix = match($agent->role) {
            'ADMIN'        => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER'  => 'tl',
            default        => 'introducer',
        };

        // REORGANIZED 17 Jul 2026 — Tier Structure Maintenance now also
        // covers Group Name Maintenance, Organization Rewards Groups, and
        // Batch Upload (moved in from the old flat Administration list),
        // per Chris's request to sequence them: Group Name, Special
        // Group, GL, TL, Introducer, Batch Upload, Pending Verifications.
        $tierActive = request()->routeIs('*.masterfile.introducers*')
            || request()->routeIs('*.masterfile.team-leaders*')
            || request()->routeIs('*.masterfile.group-leaders*')
            || request()->routeIs('*.masterfile.group-names*')
            || request()->routeIs('*.masterfile.pending-verifications*')
            || request()->routeIs('admin.special-group*')
            || request()->routeIs('special-group.*')
            || request()->routeIs('admin.batch*')
            || request()->routeIs('admin.masterfile.org-category*')
            || request()->routeIs('admin.masterfile.role-ranks*')
            || request()->routeIs('admin.masterfile.rank-assignment*')
            || request()->routeIs('admin.masterfile.promotion-rules*')
            || request()->routeIs('admin.masterfile.breakaway-bonus-rules*')
            // NEW 17 Aug 2026 — Group Set Up drill-down pages.
            || request()->routeIs('admin.group-setup*');

        // REORGANIZED 17 Jul 2026 — Products, Document Templates, Earning
        // Income Structures, and Reward Rates now all live inside "Vendor
        // and Product Maintenance" (renamed from "Vendor Maintenance")
        // instead of sitting as flat siblings.
        //
        // REDESIGNED 12 Aug 2026 per Chris: "program here, program
        // there... all related to the flow must come into vendor
        // maintenance, change maintenance to Management, display in
        // proper sequence." Renamed to "Vendor Management" and Content
        // Library folded in (it used to sit as an unrelated flat item
        // lower down, even though vendors/agents submit content into it
        // — see admin.video-library* below), so the whole vendor
        // lifecycle — registration approval, due diligence, active
        // vendor records, products, documents, commission — lives in one
        // place, in onboarding order. Agent-facing self-service versions
        // (Rebate Offer Search, Submit Marketing Content) deliberately
        // stay in Growth & Outreach Center — same data, different
        // audience, per Chris's confirmed decision.
        //
        // CHANGED AGAIN 12 Aug 2026 per Chris:
        // - Document Templates + New Document Template REMOVED from the
        //   menu entirely (per Chris's explicit "remove it anyway" — the
        //   Sales Transaction document-field-checklist feature and its
        //   data are untouched, there's just no menu entry to reach it
        //   now).
        // - Reward Rates + Vendor Rebate Offers MOVED OUT into their own
        //   new top-level "Reward Point Programs" menu (see
        //   $rewardProgramsActive below) — no longer part of Vendor
        //   Management at all.
        $vendorMaintActive = request()->routeIs('admin.vendors*')
            || request()->routeIs('admin.branches*')
            || request()->routeIs('admin.masterfile.products*')
            || request()->routeIs('admin.masterfile.commissions*')
            || request()->routeIs('admin.video-library*');

        // NEW 12 Aug 2026 per Chris — Reward Rates + Vendor Rebate Offers
        // pulled out of Vendor Management into their own top-level menu,
        // positioned right before My Account.
        $rewardProgramsActive = request()->routeIs('admin.masterfile.reward-rates*')
            || request()->routeIs('admin.masterfile.rebate-offers*');

        // NEW 2 Aug 2026 — Override Members Maintenance, its own group
        // (was previously scattered, unmatched, inside $tierActive —
        // visiting these pages never auto-opened the right submenu).
        $overrideActive = request()->routeIs('admin.masterfile.override-recipient-profile*')
            || request()->routeIs('admin.masterfile.override-members*')
            || request()->routeIs('admin.masterfile.override-claims*')
            || request()->routeIs('admin.masterfile.override-ledger*');

        // Master File Maintenance is now its own top-level menu (was
        // nested inside Administration), and now also holds Reason Code
        // Maintenance (moved in from the old Administration menu, which
        // is now empty and removed).
        $masterFileActive = $tierActive || $vendorMaintActive || $overrideActive
            || request()->routeIs('admin.masterfile.commissions*')
            || request()->routeIs('admin.masterfile.reason-codes*')
            || request()->routeIs('admin.video-library*')
            || request()->routeIs('admin.masterfile.program-library*')
            || request()->routeIs('admin.masterfile.program-unlocks*')
            ;

        // Communication & Action Center (renamed from "Action Center").
        // FIXED 8 Aug 2026 per Chris: "why you disappear everything at the
        // menu dashboard when i click inbox" — this is a full-page-reload
        // app, so which category shows "open" on a fresh page load is
        // decided ENTIRELY by this one variable matching the current
        // route. It was missing several routes that already lived in this
        // menu (help-desk*, notice-board*/admin.notice-board*,
        // admin.document-credit*, admin.fraud-review*) — so navigating to
        // any of THOSE screens left the whole menu collapsed with nothing
        // marked open, which is exactly what made it look like "everything
        // disappeared." Also dropped admin.offer-requests* (that feature
        // was removed). Every route below must be kept in sync with what
        // actually lives inside #remMenu — if a new item is ever added to
        // that menu without adding its route here too, this bug comes
        // straight back for that one item.
        // SPLIT 10 Aug 2026 per Chris: "the bottom menu selection is
        // truncated before the glade engagement analysis." Merged
        // together these two groups ran to 17 rows for Admin — too
        // tall to fit even with every other heading hidden (focused
        // mode), so the sidebar's own scrollbar was the only way to
        // reach the bottom items, and it's thin/easy to miss. Splitting
        // back into two separate top-level groups means each one opens
        // to at most 9 rows — fits standalone, no scroll needed at all.
        $reminderActive = request()->routeIs('help-desk*')
            || request()->routeIs('notice-board.*')
            || request()->routeIs('admin.notice-board*')
            || request()->routeIs('admin.notification-setup*')
            || request()->routeIs('calendar*')
            || request()->routeIs('admin.whatsapp-audit*');

        $actionActive = request()->routeIs('*.renewal-quotations*')
            || request()->routeIs('admin.agents.pending')
            || request()->routeIs('admin.approvals*')
            || request()->routeIs('admin.housekeeping*')
            || request()->routeIs('admin.document-credit*')
            || request()->routeIs('admin.glade-analytics*')
            || request()->routeIs('admin.fraud-review*')
            || request()->routeIs('renewal-forecast*');

        $accountActive  = request()->routeIs('*.profile*') || request()->routeIs('admin.masterfile.audit-logs*');

        // NEW 25 Jul 2026 — Growth & Outreach Center (task #209 onward):
        // Channel Connections, My Referral Link, and everything else in
        // this feature area added over the following tasks.
        // MOVED 25 Jul 2026 — per Chris: "put in Network tree not in
        // marketing" — My Badges is a network/organizational achievement
        // tracker (recruits, TL/GL promotions, network size), not an
        // outreach tool, so growth-badges* now belongs to $networkActive
        // below instead of $growthActive.
        $growthActive   = request()->routeIs('admin.growth.*') || request()->routeIs('referral-link*') || request()->routeIs('growth-contests*') || request()->routeIs('public-profile*') || request()->routeIs('rebate-offers*');

        // NEW 9 Aug 2026 — Overview KPI menu group, Admin-only. Per Chris:
        // "all kpi should in one main menu overview KPI, Vendor KPI and
        // Customer kpi." admin.dashboard itself is the "Overview KPI"
        // screen (it already IS the company-wide KPI overview) — this
        // just gives it a second, KPI-labelled entry point alongside
        // Vendor KPI and Customer KPI so all three sit together.
        //
        // CHANGED 12 Aug 2026 per Chris: "the login screen panel display
        // is wrong, it should be main menu" — admin.dashboard is also
        // the plain landing page right after login (the top "Dashboard"
        // link routes here too), so including it here meant every login
        // auto-drilled into the Overview KPI category and hid every
        // other main-menu heading (Network Tree, Customer Relationship,
        // Business, etc.) — the agent never saw the real main menu.
        // Overview KPI now only auto-opens for its two KPI-specific
        // pages; landing on plain Dashboard always shows the full main
        // menu. Clicking into "Overview KPI" from the KPI category
        // itself still works via the on-click JS (toggleSection).
        $overviewKpiActive = request()->routeIs('admin.vendor-kpi*') || request()->routeIs('customer-kpi*');
        // (admin.growth.* above already covers admin.growth.broadcasts.*)
        $profileActive  = request()->routeIs($agent->role === 'ADMIN' ? 'admin.profile*' : ($agent->role === 'GROUP_LEADER' ? 'gl.profile*' : ($agent->role === 'TEAM_LEADER' ? 'tl.profile*' : 'introducer.profile*')));
    @endphp

    <div class="sidebar-role-badge {{ $badgeClass }}">{{ $roleLabel }}</div>

    <div class="sidebar-nav" id="sidebarNav">

        <a href="{{ match($agent->role) {
            'ADMIN'        => route('admin.dashboard'),
            'GROUP_LEADER' => route('gl.dashboard'),
            'TEAM_LEADER'  => route('tl.dashboard'),
            default        => route('introducer.dashboard'),
        } }}" class="nav-item {{ request()->routeIs('*.dashboard') ? 'active' : '' }}">
            <i class="ti ti-layout-dashboard"></i> {{ __('nav.dashboard') }}
        </a>

        {{-- OVERVIEW KPI — NEW 9 Aug 2026, Admin-only. Per Chris: "all kpi
             should in one main menu overview KPI, Vendor KPI and Customer
             kpi." Pulls the company-wide Overview (admin.dashboard), the
             new Vendor KPI dashboard, and Customer KPI together in one
             place, instead of Customer KPI sitting alone inside Customer
             Relationship. --}}
        @if($agent->role === 'ADMIN')
        <div class="nav-parent {{ $overviewKpiActive ? 'open' : '' }}" id="kpiParent" onclick="toggleSection('kpiMenu','kpiParent')">
            <div class="pleft"><i class="ti ti-chart-bar"></i> {{ __('nav.overview_kpi') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $overviewKpiActive ? 'open' : '' }}" id="kpiMenu">
            <a href="{{ route('admin.dashboard') }}" class="nav-subitem {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="ti ti-layout-dashboard"></i> {{ __('sidebar.overview_kpi_item') }}</a>
            <a href="{{ route('admin.vendor-kpi.index') }}" class="nav-subitem {{ request()->routeIs('admin.vendor-kpi*') ? 'active' : '' }}"><i class="ti ti-building-store"></i> {{ __('sidebar.vendor_kpi') }}</a>
            <a href="{{ route('customer-kpi.index') }}" class="nav-subitem {{ request()->routeIs('customer-kpi*') ? 'active' : '' }}"><i class="ti ti-users"></i> {{ __('sidebar.customer_kpi') }}</a>
        </div>
        @endif

        {{-- NETWORK --}}
        <div class="nav-parent {{ $networkActive ? 'open' : '' }}" id="netParent" onclick="toggleSection('netMenu','netParent')">
            <div class="pleft"><i class="ti ti-hierarchy-2"></i> {{ __('nav.network_tree') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $networkActive ? 'open' : '' }}" id="netMenu">
            @if($agent->role === 'ADMIN')
                {{-- FIXED 2 Aug 2026 per Chris: "Network Drill Down" was a
                     duplicate of this exact same link (both pointed at
                     admin.network) — removed. Pending Assignment moved to
                     Action Center (see below), since it's an action item,
                     not a tree-browsing screen. --}}
                <a href="{{ route('admin.network') }}" class="nav-subitem {{ request()->routeIs('admin.network*') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> {{ __('sidebar.network_tree_item') }}</a>
            @elseif($agent->role === 'GROUP_LEADER')
                <a href="{{ route('gl.network') }}" class="nav-subitem {{ request()->routeIs('gl.network*') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> {{ __('sidebar.my_group_tree') }}</a>
            @elseif($agent->role === 'TEAM_LEADER')
                <a href="{{ route('tl.introducers') }}" class="nav-subitem {{ request()->routeIs('tl.introducers*') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> {{ __('sidebar.my_team_tree') }}</a>
            @else
                <a href="{{ route('introducer.recruits') }}" class="nav-subitem {{ request()->routeIs('introducer.recruits*') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> {{ __('sidebar.my_recruits') }}</a>
            @endif
        </div>

        {{-- CUSTOMER RELATIONSHIP — NEW 28 Jul 2026 per Chris: one menu
             for everything that's actually about the customer record
             (Customer Maintenance, Customer KPI, Customer Referrals, and
             the customer classification lookup tables), pulled out of
             Network Tree/Business/Master File Maintenance/Growth &
             Outreach. This is also the natural home for the EspoCRM
             integration once that's built — follow-up tasks/calendar
             stay out of here since those are moving to EspoCRM itself. --}}
        <div class="nav-parent {{ $crmActive ? 'open' : '' }}" id="crmParent" onclick="toggleSection('crmMenu','crmParent')">
            <div class="pleft"><i class="ti ti-address-book"></i> {{ __('nav.customer_relationship') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $crmActive ? 'open' : '' }}" id="crmMenu">
            <a href="{{ route($crmPrefix . '.customers.index') }}" class="nav-subitem {{ request()->routeIs('*.customers*') ? 'active' : '' }}"><i class="ti ti-users"></i> {{ __('sidebar.customers') }}</a>
            {{-- MOVED 9 Aug 2026 per Chris — for Admin, Customer KPI now
                 lives in the Overview KPI menu above alongside Vendor KPI.
                 Non-Admin roles have no Overview KPI menu, so it stays
                 here for them. --}}
            @unless($agent->role === 'ADMIN')
            <a href="{{ route('customer-kpi.index') }}" class="nav-subitem {{ request()->routeIs('customer-kpi*') ? 'active' : '' }}"><i class="ti ti-chart-bar"></i> {{ __('sidebar.customer_kpi') }}</a>
            @endunless
            <a href="{{ route('customer-referrals.index') }}" class="nav-subitem {{ request()->routeIs('customer-referrals*') ? 'active' : '' }}"><i class="ti ti-users"></i> {{ __('sidebar.customer_referrals') }}</a>
            @if($agent->role === 'ADMIN')
            <a href="{{ route('admin.masterfile.customer-statuses') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.customer-statuses*') ? 'active' : '' }}"><i class="ti ti-user-check"></i> {{ __('sidebar.customer_status_maintenance') }}</a>
            <a href="{{ route('admin.masterfile.customer-types') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.customer-types*') ? 'active' : '' }}"><i class="ti ti-tag"></i> {{ __('sidebar.customer_type_maintenance') }}</a>
            <a href="{{ route('admin.masterfile.customer-categories') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.customer-categories*') ? 'active' : '' }}"><i class="ti ti-category"></i> {{ __('sidebar.customer_category_maintenance') }}</a>
            <a href="{{ route('admin.masterfile.occupation-groups') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.occupation-groups*') ? 'active' : '' }}"><i class="ti ti-briefcase"></i> {{ __('sidebar.occupation_group_maintenance') }}</a>
            <a href="{{ route('admin.masterfile.customer-sources') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.customer-sources*') ? 'active' : '' }}"><i class="ti ti-speakerphone"></i> {{ __('sidebar.customer_source_maintenance') }}</a>
            @endif
            {{-- NEW 29 Jul 2026 — Support Tickets + Help Center (tasks
                 #259/#261). Per Chris: "make full use of EspoCRM" — Help
                 Desk / Case Management + Knowledge Base. Both routes are
                 role-agnostic (single shared auth:agent group), so every
                 role sees these two links, not just Admin. --}}
            <a href="{{ route('support-tickets.index') }}" class="nav-subitem {{ request()->routeIs('support-tickets*') ? 'active' : '' }}"><i class="ti ti-headset"></i> {{ __('sidebar.support_tickets') }}</a>
            {{-- REMOVED 12 Aug 2026 per Chris: "just maintain one, dont
                 confuse" — Help Center folded into Help Desk's "FAQ / Help
                 Articles" tab (Communication group) instead of its own
                 similarly-named menu item here. --}}
            @if($agent->role === 'ADMIN')
            {{-- NEW 29 Jul 2026 — EspoCRM integration (task #251). Admin-only
                 escape hatch to EspoCRM's own UI (opens in a new tab) — for
                 occasional admin/config use, not for daily agent work. Every
                 other agent keeps working entirely inside GeneralLink;
                 follow-ups/calendar sync there automatically in the
                 background. (The one-off Connection Test screen used during
                 setup was removed per Chris — use TEST_ESPOCRM_CONNECTION.bat
                 instead if the link ever needs re-checking.) --}}
            <a href="{{ config('services.espocrm.base_url') }}" target="_blank" rel="noopener" class="nav-subitem"><i class="ti ti-external-link"></i> {{ __('sidebar.open_addon_crm') }}</a>
            @endif
        </div>

        {{-- BUSINESS --}}
        <div class="nav-parent {{ $businessActive ? 'open' : '' }}" id="bizParent" onclick="toggleSection('bizMenu','bizParent')">
            <div class="pleft"><i class="ti ti-briefcase"></i> {{ __('nav.business') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $businessActive ? 'open' : '' }}" id="bizMenu">
            @php
                $stPrefix = match($agent->role) {
                    'ADMIN'        => 'admin',
                    'GROUP_LEADER' => 'gl',
                    'TEAM_LEADER'  => 'tl',
                    default        => 'introducer',
                };
                $stActive = request()->routeIs('*.sales-transactions*');
            @endphp
            {{-- NEW 16 Jul 2026 — Sales Transaction Maintenance (submit new
                 sale + photo/receipt, search/view, Admin confirm). Shared
                 across all 4 roles via SalesTransactionController. --}}
            <a href="{{ route($stPrefix . '.sales-transactions.create') }}" class="nav-subitem {{ request()->routeIs('*.sales-transactions.create') ? 'active' : '' }}"><i class="ti ti-file-plus"></i> {{ __('sidebar.submit_sales_transaction') }}</a>
            <a href="{{ route($stPrefix . '.sales-transactions.index') }}" class="nav-subitem {{ $stActive && !request()->routeIs('*.sales-transactions.create') ? 'active' : '' }}"><i class="ti ti-file-invoice"></i> {{ __('sidebar.sales_transaction_maintenance') }}</a>
            {{-- NEW 18 Jul 2026 — email submission key (SubmissionKeyController). --}}
            <a href="{{ route($stPrefix . '.submission-key') }}" class="nav-subitem {{ request()->routeIs('*.submission-key') ? 'active' : '' }}"><i class="ti ti-mail"></i> {{ __('sidebar.submit_by_email') }}</a>
            @if($agent->role === 'ADMIN')
                <a href="{{ route('admin.earning-ledger') }}" class="nav-subitem {{ request()->routeIs('admin.earning-ledger*') ? 'active' : '' }}"><i class="ti ti-coin"></i> {{ __('sidebar.earning_income_ledger') }}</a>
                <a href="#" class="nav-subitem"><i class="ti ti-star"></i> {{ __('sidebar.reward_points') }}</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> {{ __('sidebar.transfer_buy_points') }}</a>
                <a href="#" class="nav-subitem"><i class="ti ti-receipt"></i> {{ __('sidebar.point_purchases') }}</a>
            @elseif($agent->role === 'GROUP_LEADER')
                <a href="{{ route('gl.transactions') }}" class="nav-subitem {{ request()->routeIs('gl.transactions*') ? 'active' : '' }}"><i class="ti ti-report"></i> {{ __('sidebar.transactions_dashboard') }}</a>
                <a href="{{ route('gl.commissions.index') }}" class="nav-subitem {{ request()->routeIs('gl.commissions*') ? 'active' : '' }}"><i class="ti ti-coin"></i> {{ __('sidebar.earning_income') }}</a>
                {{-- NEW 2 Aug 2026 — separate from "Earning Income" above:
                     that's the summary dashboard (trend/breakdown charts),
                     this is the chronological Debit/Credit ledger, same
                     idea as the Override Member Ledger. --}}
                <a href="{{ route('gl.earning-ledger') }}" class="nav-subitem {{ request()->routeIs('gl.earning-ledger*') ? 'active' : '' }}"><i class="ti ti-report-money"></i> {{ __('sidebar.earning_income_ledger') }}</a>
                <a href="{{ $rewardsRoute }}" class="nav-subitem {{ request()->routeIs('gl.rewards*') ? 'active' : '' }}"><i class="ti ti-star"></i> {{ __('sidebar.reward_points') }}</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> {{ __('sidebar.transfer_buy_points') }}</a>
            @elseif($agent->role === 'TEAM_LEADER')
                <a href="{{ route('tl.transactions') }}" class="nav-subitem {{ request()->routeIs('tl.transactions*') ? 'active' : '' }}"><i class="ti ti-report"></i> {{ __('sidebar.transactions_dashboard') }}</a>
                <a href="{{ route('tl.earning-ledger') }}" class="nav-subitem {{ request()->routeIs('tl.earning-ledger*') ? 'active' : '' }}"><i class="ti ti-coin"></i> {{ __('sidebar.earning_income_ledger') }}</a>
                <a href="{{ $rewardsRoute }}" class="nav-subitem {{ request()->routeIs('tl.rewards*') ? 'active' : '' }}"><i class="ti ti-star"></i> {{ __('sidebar.reward_points') }}</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> {{ __('sidebar.transfer_buy_points') }}</a>
            @else
                <a href="{{ route('introducer.earning-ledger') }}" class="nav-subitem {{ request()->routeIs('introducer.earning-ledger*') ? 'active' : '' }}"><i class="ti ti-coin"></i> {{ __('sidebar.earning_income_ledger') }}</a>
                <a href="{{ $rewardsRoute }}" class="nav-subitem {{ request()->routeIs('introducer.rewards*') ? 'active' : '' }}"><i class="ti ti-star"></i> {{ __('sidebar.reward_points') }}</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> {{ __('sidebar.transfer_buy_points') }}</a>
            @endif
        </div>

        {{-- RENAMED 8 Aug 2026 from "Action Center" per Chris: "reorganise
             Action Center to a menu call Communication & Action Center" —
             every communication-shaped feature (Help Desk, Notice Board,
             Notification Setup, Calendar & Reminders, WhatsApp Audit Log)
             was scattered across THIS menu and My Account, which is
             exactly why Chris said he was "lost and confuse where it is."
             Now 3 sub-groups: COMMUNICATION (every communication touchpoint,
             one place, every role — ordered as close to Chris's requested
             sequence as real screens allow, see mapping notes below on
             each item), ACTION REQUIRED (renamed from "Need To Act" —
             unchanged Admin operational queue, Communication items moved
             out of it into the group above), GOOD TO KNOW (unchanged,
             minus Calendar which moved up into Communication as
             "Reminders"). Notice Board specifically was previously TWO
             separate links (Admin's manage view here, everyone else's
             read view under My Account) — merged into ONE link that
             points at the right screen for the logged-in role, so there
             is now exactly one place to look for it regardless of role.

             Chris's requested sequence was: Inbox, Sent Items, Action
             Required, Notification, Reminders, Follow-ups, Escalations,
             Notice Board, Announcement, Communication History. Of those,
             5 map onto a real existing screen (used below); 5 do not
             exist as their own screen today and were deliberately NOT
             turned into dead links — see the chat response accompanying
             this change for the full explanation of each:
               - Sent Items — Help Desk already shows your own sent
                 replies inline inside each thread; no separate screen.
               - Action Required — kept as ITS OWN SUB-GROUP below
                 (renamed from "Need To Act") rather than one link, since
                 it was always a list of several different queues, not
                 one screen.
               - Follow-ups / Escalations — no dedicated screen exists yet.
               - Announcement — Notice Board IS GeneralLink's one-way
                 announcement feature today; not duplicated as a second
                 link to avoid creating the exact "here and there"
                 confusion this reorg is meant to fix. --}}
        <div class="nav-parent {{ $reminderActive ? 'open' : '' }}" id="remParent" onclick="toggleSection('remMenu','remParent')">
            <div class="pleft"><i class="ti ti-messages"></i> {{ __('nav.communication') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $reminderActive ? 'open' : '' }}" id="remMenu">
            @php $rqPrefix = match($agent->role) { 'ADMIN' => 'admin', 'GROUP_LEADER' => 'gl', 'TEAM_LEADER' => 'tl', default => 'introducer' }; @endphp
            {{-- RENAMED 10 Aug 2026 per Chris: "when i click inbox, it come
                 out help desk...can you make the menu selection as help
                 desk" — the page you land on is titled "Help Desk", so the
                 menu label now matches it exactly instead of calling it
                 something else. --}}
            <a href="{{ route('help-desk.index') }}" class="nav-subitem" style="position:relative;{{ request()->routeIs('help-desk*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}display:flex; align-items:center; justify-content:space-between;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="ti ti-message-circle-2"></i> {{ __('sidebar.help_desk') }}</span>
                @if($helpDeskUnreadCount > 0)
                <span style="background:#F44336; color:#fff; border-radius:20px; min-width:15px; height:15px; padding:0 4px; font-size:8.5px; font-weight:700; display:flex; align-items:center; justify-content:center;">{{ $helpDeskUnreadCount > 9 ? '9+' : $helpDeskUnreadCount }}</span>
                @endif
            </a>
            {{-- MERGED 8 Aug 2026 — previously Admin's manage view lived
                 here and everyone else's read view lived under My Account;
                 now one link that resolves to whichever screen fits the
                 logged-in role. Covers Chris's #8 "Notice Board" and #9
                 "Announcement" (same feature — see comment above). --}}
            <a href="{{ $agent->role === 'ADMIN' ? route('admin.notice-board.index') : route('notice-board.index') }}" class="nav-subitem" style="position:relative;{{ (request()->routeIs('admin.notice-board*') || request()->routeIs('notice-board.*')) ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}display:flex; align-items:center; justify-content:space-between;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="ti ti-speakerphone"></i> {{ __('sidebar.notice_board') }}</span>
                @if($agent->role !== 'ADMIN' && $noticeUnreadCount > 0)
                <span style="background:#F44336; color:#fff; border-radius:20px; min-width:15px; height:15px; padding:0 4px; font-size:8.5px; font-weight:700; display:flex; align-items:center; justify-content:center;">{{ $noticeUnreadCount > 9 ? '9+' : $noticeUnreadCount }}</span>
                @endif
            </a>
            @if($agent->role === 'ADMIN')
            {{-- Chris's #4 "Notification" — labelled "Notification Setup"
                 (not just "Notification") because that's honestly what
                 this screen is: the config screen, not a notifications
                 list. The notifications LIST is the bell icon in the top
                 bar (every role already has that) — it has never been its
                 own full page, so it isn't linked here as one. --}}
            <a href="{{ route('admin.notification-setup.index') }}" class="nav-subitem" style="{{ request()->routeIs('admin.notification-setup*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-bell-cog"></i> {{ __('sidebar.notification_setup') }}</a>
            @endif
            {{-- Chris's #5 "Reminders" — this is Calendar & Reminders,
                 moved up from the old "Good To Know" group into
                 Communication, renamed to match his wording exactly. --}}
            <a href="{{ route('calendar.index') }}" class="nav-subitem {{ request()->routeIs('calendar*') ? 'active' : '' }}"><i class="ti ti-calendar"></i> {{ __('sidebar.reminders') }}</a>
            @if($agent->role === 'ADMIN')
            {{-- Chris's #10 "Communication History" — today this only
                 covers WhatsApp (every send attempt, allowed/blocked,
                 sent/failed). A history covering Help Desk + Notice Board
                 + Notifications together doesn't exist yet — flagged in
                 the chat response as a possible future build, not
                 silently promised here. --}}
            <a href="{{ route('admin.whatsapp-audit.index') }}" class="nav-subitem" style="{{ request()->routeIs('admin.whatsapp-audit*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-brand-whatsapp"></i> {{ __('sidebar.whatsapp_audit_log') }}</a>
            @endif
        </div>

        {{-- SPLIT OUT 12 Aug 2026 per Chris: "the bottom menu selection is
             truncated before the glade engagement analysis...show in one
             row properly" — this used to be folded into the Communication
             group above (17 rows combined, only reachable via a thin
             scrollbar Chris kept missing). Now its own top-level group so
             both fit on screen without scrolling. Admin-only, same as
             before. --}}
        @if($agent->role === 'ADMIN')
        <div class="nav-parent {{ $actionActive ? 'open' : '' }}" id="actParent" onclick="toggleSection('actMenu','actParent')">
            <div class="pleft"><i class="ti ti-alert-circle"></i> {{ __('nav.action_required') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $actionActive ? 'open' : '' }}" id="actMenu">
            {{-- MOVED 24 Jul 2026 per Chris: was its own top-level
                 "Renewals" menu with just this one item — folded into
                 this menu instead, since it's something to act on
                 (a customer waiting on a quotation), same as the rest of
                 this group. --}}
            <a href="{{ route($rqPrefix . '.renewal-quotations.index') }}" class="nav-subitem {{ request()->routeIs('*.renewal-quotations*') ? 'active' : '' }}"><i class="ti ti-calendar-due"></i> {{ __('sidebar.renewal_quotation_requests') }}</a>
            {{-- MOVED 2 Aug 2026 per Chris: was under Network Tree — it's
                 an action item (new self-registered agents waiting on
                 Admin to assign them to a Group Leader), so it belongs
                 here with the rest of Admin's Action Required queue. --}}
            <a href="{{ route('admin.agents.pending') }}" class="nav-subitem" style="{{ request()->routeIs('admin.agents.pending') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-user-question"></i> {{ __('sidebar.pending_assignment') }}</a>
            <a href="{{ route('admin.approvals.index') }}" class="nav-subitem" style="position:relative;{{ request()->routeIs('admin.approvals*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-checkbox"></i> {{ __('sidebar.approvals') }} <span id="approvalsBadge" style="display:none; background:#F44336; color:#fff; font-size:9px; font-weight:700; border-radius:10px; padding:1px 6px; margin-left:4px;">0</span></a>
            {{-- MOVED 17 Aug 2026 per Chris: Batch Upload and Pending
                 Verifications moved here from Group Set Up (formerly
                 Tier Structure) — both are things to act on, not setup
                 screens, same as everything else in this group. --}}
            <a href="{{ route('admin.batch.index') }}" class="nav-subitem" style="{{ request()->routeIs('admin.batch*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-upload"></i> {{ __('sidebar.batch_upload') }}</a>
            <a href="{{ route('admin.masterfile.pending-verifications') }}" class="nav-subitem" style="{{ request()->routeIs('*.masterfile.pending-verifications*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-mail-forward"></i> {{ __('sidebar.pending_verifications') }}</a>
            {{-- REMOVED 8 Aug 2026 per Chris: "remove everything ... i want
                 to redo and revamp" — Offer Request Approval link removed
                 along with the rest of the feature. --}}
            {{-- REMOVED 12 Aug 2026 per Chris: "no need to split into 3" —
                 Carolyn AI Tickets folded into Help Desk (Communication
                 group above) instead of its own menu item. Carolyn-logged
                 tickets now show up there, tagged "Created by Carolyn AI
                 Agent", with a badge on Help Desk itself covering them. --}}
            <a href="{{ route('admin.housekeeping.customers') }}" class="nav-subitem" style="{{ request()->routeIs('admin.housekeeping*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-trash"></i> {{ __('sidebar.customer_housekeeping') }}</a>
            <a href="{{ route('admin.document-credit.index') }}" class="nav-subitem" style="{{ request()->routeIs('admin.document-credit*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-scan"></i> {{ __('sidebar.document_credit') }}</a>
            {{-- RESTORED 19 Sep 2026 — per Chris: back to its original
                 name "GLADE Engagement Analytics" (it was briefly
                 relabelled "Communication KPI" for QC title-consistency,
                 which collided with the separate CBE Communication KPI
                 screen under the GLADE Executive KPI Dashboard — two
                 unrelated pages, two different data sources: this one
                 reads the general company-wide notice board (notices /
                 notice_deliveries / notice_reads / notice_ai_matches),
                 the CBE one reads cbe_temple_notices / cbe_message_
                 threads). Chris asked for this original name back, and
                 for the CBE Communication KPI screen to be redesigned to
                 match THIS screen's card layout/logic instead — see
                 admin/cbe-kpi/communication-kpi.blade.php. --}}
            <a href="{{ route('admin.glade-analytics.index') }}" class="nav-subitem" style="{{ request()->routeIs('admin.glade-analytics*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-chart-infographic"></i> {{ __('sidebar.glade_engagement_analytics') }}</a>
            <a href="{{ route('admin.fraud-review.index') }}" class="nav-subitem" style="position:relative;{{ request()->routeIs('admin.fraud-review*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}display:flex; align-items:center; justify-content:space-between;">
                <span style="display:flex; align-items:center; gap:8px;"><i class="ti ti-shield-exclamation"></i> {{ __('sidebar.risk_review_queue') }}</span>
                @if($fraudReviewUrgentCount > 0)
                <span style="background:#F44336; color:#fff; border-radius:20px; min-width:15px; height:15px; padding:0 4px; font-size:8.5px; font-weight:700; display:flex; align-items:center; justify-content:center;">{{ $fraudReviewUrgentCount > 9 ? '9+' : $fraudReviewUrgentCount }}</span>
                @endif
            </a>
            <div style="padding:6px 20px 2px; font-size:8.5px; font-weight:700; letter-spacing:0.6px; text-transform:uppercase; color:rgba(255,255,255,0.5);">{{ __('sidebar.good_to_know') }}</div>
            {{-- SHORTENED 8 Aug 2026 per Chris: "all dashboard menu
                 description must display in one row" — 34 characters was
                 too long to fit any reasonably-sized sidebar on one line;
                 shortened without losing meaning (route unchanged). --}}
            <a href="{{ route('renewal-forecast.index') }}" class="nav-subitem {{ request()->routeIs('renewal-forecast*') ? 'active' : '' }}"><i class="ti ti-chart-line"></i> {{ __('sidebar.sales_earning_forecast_item') }}</a>
        </div>
        @endif

        {{-- MASTER FILE MAINTENANCE — promoted to a top-level menu (was
             nested inside Administration) per Chris's 17 Jul 2026 reorg
             request, so Administration isn't overloaded. Tier Structure
             Maintenance stays visible to ALL roles (scoped to their own
             downline); Vendor Maintenance/Products/Templates stay
             Admin-only. MOVED 24 Jul 2026 per Chris: now sits AFTER
             Action Center in the sidebar order (was before). --}}
        @php
            $tierPrefix = match($agent->role) {
                'ADMIN'        => 'admin',
                'GROUP_LEADER' => 'gl',
                'TEAM_LEADER'  => 'tl',
                default        => 'introducer',
            };
            // NEW 15 Jul 2026 — true only for agents who belong to a
            // GENUINE Organization Rewards Group (group_labels.promotion_
            // demotion_enabled = false), matching the exact same check
            // SpecialGroupController::appointTLForm()/addIntroducerForm()
            // already enforce server-side. Used to permanently surface
            // Appoint Team Leader / Add Introducer in the sidebar for
            // Organization Rewards Group GL/TL — no more raw URLs needed.
            $isSpecialGroupAgent = $agent->group_label_id && \Illuminate\Support\Facades\DB::table('group_labels')
                ->where('group_label_id', $agent->group_label_id)
                ->where('promotion_demotion_enabled', false)
                ->exists();
        @endphp
        <div class="nav-parent {{ $masterFileActive ? 'open' : '' }}" id="mfmParent" onclick="toggleSection('mfmMenu','mfmParent')">
            <div class="pleft"><i class="ti ti-database"></i> {{ __('nav.master_file_maintenance') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $masterFileActive ? 'open' : '' }}" id="mfmMenu">
            {{-- REBUILT 17 Aug 2026 per Chris — 2nd pass: keep this
                 entirely inside the sidebar, same tier/expand style as
                 every other menu here, right-hand screen unchanged
                 until an actual program is clicked (no separate landing
                 page — that was wrong). "Group Set Up" expands to 3
                 group-type headers (Direct Selling Group / Organization
                 Rewards Group / CBE), each of which expands to its own
                 programs, one level deeper than before. --}}
            @php
                $dsgSubActive = request()->routeIs('admin.masterfile.group-names*') && request('type') !== 'ORG' && request('type') !== 'CBE'
                    || request()->routeIs('admin.masterfile.group-leaders*')
                    || request()->routeIs('*.masterfile.team-leaders*')
                    || request()->routeIs('*.masterfile.introducers*')
                    || request()->routeIs('admin.masterfile.breakaway-bonus-rules*')
                    || request()->routeIs('admin.masterfile.promotion-rules*');
                $orgSubActive = request('type') === 'ORG'
                    || request()->routeIs('admin.masterfile.org-category*')
                    || request()->routeIs('admin.special-group*')
                    || request()->routeIs('special-group.*')
                    || request()->routeIs('admin.masterfile.role-ranks*')
                    || request()->routeIs('admin.masterfile.rank-assignment*');
                $cbeSubActive = request('type') === 'CBE';
            @endphp
            <div class="nav-subparent {{ $tierActive ? 'open' : '' }}" id="tsParent" onclick="toggleDeep('tsMenu','tsParent',event)">
                <div class="spleft"><i class="ti ti-sitemap"></i> {{ __('sidebar.group_set_up') }}</div>
                <span class="sparrow">▼</span>
            </div>
            <div class="nav-deep {{ $tierActive ? 'open' : '' }}" id="tsMenu">
                @if($agent->role === 'ADMIN')
                <div class="nav-deepitem {{ $dsgSubActive ? 'active' : '' }}" style="padding-left:44px; cursor:pointer; justify-content:space-between;" onclick="toggleDeep('dsgSubMenu','dsgSubParent',event)" id="dsgSubParent">
                    <span style="display:flex; align-items:center; gap:6px;"><i class="ti ti-truck-delivery"></i> {{ __('sidebar.direct_selling_group_item') }}</span>
                    <span>▼</span>
                </div>
                <div class="nav-deep {{ $dsgSubActive ? 'open' : '' }}" id="dsgSubMenu">
                    <a href="{{ route('admin.masterfile.group-names', ['type' => 'DSG']) }}" class="nav-deepitem" style="padding-left:58px;"><i class="ti ti-tag"></i> {{ __('sidebar.group_name') }}</a>
                    <a href="{{ route('admin.masterfile.group-leaders') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.group-leaders*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-users-group"></i> {{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</a>
                    <a href="{{ route($tierPrefix . '.masterfile.team-leaders') }}" class="nav-deepitem {{ request()->routeIs('*.masterfile.team-leaders*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-users"></i> {{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</a>
                    <a href="{{ route($tierPrefix . '.masterfile.introducers') }}" class="nav-deepitem {{ request()->routeIs('*.masterfile.introducers*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-user"></i> {{ \App\Services\RoleLabelService::label('INTRODUCER') }}</a>
                    <a href="{{ route('admin.masterfile.breakaway-bonus-rules') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.breakaway-bonus-rules*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-gift"></i> {{ __('sidebar.breakaway_bonus_rules') }}</a>
                    <a href="{{ route('admin.masterfile.promotion-rules') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.promotion-rules*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-arrows-up-down"></i> {{ __('sidebar.promotion_demotion_rules') }}</a>
                </div>

                <div class="nav-deepitem {{ $orgSubActive ? 'active' : '' }}" style="padding-left:44px; cursor:pointer; justify-content:space-between;" onclick="toggleDeep('orgSubMenu','orgSubParent',event)" id="orgSubParent">
                    <span style="display:flex; align-items:center; gap:6px;"><i class="ti ti-building"></i> {{ __('sidebar.organization_rewards_group_item') }}</span>
                    <span>▼</span>
                </div>
                <div class="nav-deep {{ $orgSubActive ? 'open' : '' }}" id="orgSubMenu">
                    <a href="{{ route('admin.masterfile.org-category') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.org-category*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-edit"></i> {{ __('sidebar.organization_category') }}</a>
                    <a href="{{ route('admin.masterfile.group-names', ['type' => 'ORG']) }}" class="nav-deepitem" style="padding-left:58px;"><i class="ti ti-tag"></i> {{ __('sidebar.group_name') }}</a>
                    <a href="{{ route('admin.special-group.index') }}" class="nav-deepitem {{ request()->routeIs('admin.special-group*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-building"></i> {{ __('sidebar.organization_rewards_groups') }}</a>
                    <a href="{{ route('admin.masterfile.role-ranks') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.role-ranks*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-stairs"></i> {{ __('sidebar.rank_hierarchy_structure') }}</a>
                    <a href="{{ route('admin.masterfile.rank-assignment') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.rank-assignment*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-user-check"></i> {{ __('sidebar.rank_assignment') }}</a>
                </div>

                <div class="nav-deepitem {{ $cbeSubActive ? 'active' : '' }}" style="padding-left:44px; cursor:pointer; justify-content:space-between;" onclick="toggleDeep('cbeSubMenu','cbeSubParent',event)" id="cbeSubParent">
                    <span style="display:flex; align-items:center; gap:6px;"><i class="ti ti-world"></i> {{ __('sidebar.cbe_group_item') }}</span>
                    <span>▼</span>
                </div>
                <div class="nav-deep {{ $cbeSubActive ? 'open' : '' }}" id="cbeSubMenu">
                    <a href="{{ route('admin.masterfile.group-names', ['type' => 'CBE']) }}" class="nav-deepitem" style="padding-left:58px;"><i class="ti ti-tag"></i> {{ __('sidebar.group_name_hierarchy_levels') }}</a>
                    {{-- NEW 27 Aug 2026 (Task #233) --}}
                    <a href="{{ route('admin.glade-tiers.index') }}" class="nav-deepitem {{ request()->routeIs('admin.glade-tiers*') ? 'active' : '' }}" style="padding-left:58px;"><i class="ti ti-crown"></i> {{ __('admin_glade_tiers.page_title') }}</a>
                </div>
                @endif
                {{-- FIXED 17 Aug 2026 per Chris: Admin's Introducer (and
                     Team Leader) links now live ONLY inside the Direct
                     Selling Group sub-menu above — this block was still
                     showing them again as stray top-level items for
                     Admin too. GL/TL/Introducer roles (who never see the
                     DSG/ORG/CBE breakdown above) still get them here,
                     unchanged. --}}
                @if($agent->role !== 'ADMIN')
                @if($agent->role === 'GROUP_LEADER')
                <a href="{{ route($tierPrefix . '.masterfile.team-leaders') }}" class="nav-deepitem {{ request()->routeIs('*.masterfile.team-leaders*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-users"></i> {{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</a>
                @endif
                <a href="{{ route($tierPrefix . '.masterfile.introducers') }}" class="nav-deepitem {{ request()->routeIs('*.masterfile.introducers*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-user"></i> {{ \App\Services\RoleLabelService::label('INTRODUCER') }}</a>
                @endif
                @if($isSpecialGroupAgent && $agent->role === 'GROUP_LEADER')
                <a href="{{ route('special-group.appoint-tl') }}" class="nav-deepitem {{ request()->routeIs('special-group.appoint-tl') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-user-plus"></i> {{ __('sidebar.appoint_team_leader') }}</a>
                @endif
                @if($isSpecialGroupAgent && in_array($agent->role, ['GROUP_LEADER', 'TEAM_LEADER']))
                <a href="{{ route('special-group.add-introducer') }}" class="nav-deepitem {{ request()->routeIs('special-group.add-introducer') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-user-plus"></i> {{ __('sidebar.add_introducer_special') }}</a>
                @endif
                {{-- CBE has no separate setup screen here — Group Name
                     above already covers it (pick Group Type = CBE, then
                     type the hierarchy level names right there). --}}
                {{-- MOVED 17 Aug 2026 per Chris: Pending Verifications is
                     an action item, not a setup screen — Admin now sees
                     it under Action Required instead (see actMenu below).
                     GL/TL keep seeing it here unchanged, since Action
                     Required is Admin-only. --}}
                @if($agent->role === 'GROUP_LEADER' || $agent->role === 'TEAM_LEADER')
                <a href="{{ route($tierPrefix . '.masterfile.pending-verifications') }}" class="nav-deepitem {{ request()->routeIs('*.masterfile.pending-verifications*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-mail-forward"></i> Pending Verifications</a>
                @endif
            </div>
            {{-- RELOCATED 28 Jul 2026 per Chris: Customer Maintenance (and
                 the 5 customer classification lookups below) moved again —
                 out of Master File Maintenance entirely, into the new
                 "Customer Relationship" top-level menu. --}}
            @if($agent->role === 'ADMIN')
            <div class="nav-subparent {{ $vendorMaintActive ? 'open' : '' }}" id="vmParent" onclick="toggleDeep('vmMenu','vmParent',event)">
                <div class="spleft"><i class="ti ti-building-store"></i> {{ __('sidebar.vendor_management') }}</div>
                <span class="sparrow">▼</span>
            </div>
            {{-- REORDERED 12 Aug 2026 per Chris: "display in proper
                 sequence" — now follows the actual vendor lifecycle:
                 (1) approve the registration + review Due Diligence,
                 (2) their live directory record, (3) the supporting
                 master data (branches/products/documents/commission/
                 rewards), (4) rebate programs, (5) content they submit. --}}
            <div class="nav-deep {{ $vendorMaintActive ? 'open' : '' }}" id="vmMenu">
                {{-- NEW 8 Aug 2026 (Task #92) — self-registered vendors awaiting Admin approval before they can sign in to the Vendor Portal. MOVED TO FIRST 12 Aug 2026 — this is where the vendor lifecycle actually starts (registration approval + the Due Diligence report button lives on this same screen). --}}
                <a href="{{ route('admin.vendors.pending-logins') }}" class="nav-deepitem {{ request()->routeIs('admin.vendors.pending-logins') ? 'active' : '' }}" style="padding-left:44px; position:relative; display:flex; align-items:center; justify-content:space-between;">
                    <span><i class="ti ti-user-question"></i> {{ __('sidebar.pending_vendor_logins') }}</span>
                    <span id="vendorPendingBadge" style="display:none; background:#F44336; color:#fff; font-size:9px; font-weight:700; border-radius:10px; padding:1px 6px; margin-right:8px;">0</span>
                </a>
                {{-- NEW 13 Aug 2026 — per Chris: "create another workflow
                     program below pending approve in the menu dashboard...
                     called Vendor Onboarding and Communication Workflow.
                     This program manages the entire onboarding lifecycle,
                     from registration through review to approval or
                     rejection." Sits directly under Pending Vendor Logins
                     since it's the same lifecycle, just the full
                     communication/amendment/agreement picture rather than
                     the approve/reject action screen. --}}
                <a href="{{ route('admin.vendors.onboarding-workflow') }}" class="nav-deepitem {{ request()->routeIs('admin.vendors.onboarding-workflow*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-route"></i> {{ __('sidebar.vendor_onboarding_workflow') }}</a>
                {{-- NEW 14 Aug 2026 — per Chris: "New Vendor Approvals menu
                     item Please create." Approve/Reject/Forward-to-Director
                     moved off Vendor Registration Detail (now pure document
                     viewing) into their own program, sitting right below
                     Vendor Onboarding Workflow as asked. --}}
                <a href="{{ route('admin.vendors.approvals') }}" class="nav-deepitem {{ request()->routeIs('admin.vendors.approvals*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-checkbox"></i> {{ __('sidebar.vendor_approvals') }}</a>
                {{-- NEW 14 Aug 2026 — per Chris: "there must be a program
                     to retrieve all past email OTP records to comply the
                     Malaysia's Electronic Commerce Act 2006." --}}
                <a href="{{ route('admin.vendor-agreements.index') }}" class="nav-deepitem {{ request()->routeIs('admin.vendor-agreements*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-file-certificate"></i> {{ __('sidebar.agreement_compliance_log') }}</a>
                <a href="{{ route('admin.vendors.index', ['mode'=>'main']) }}" class="nav-deepitem {{ (request()->routeIs('admin.vendors.index') || request()->routeIs('admin.vendors.search-edit')) ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-building"></i> {{ __('sidebar.head_office_main_outlet') }}</a>
                <a href="{{ route('admin.branches.index') }}" class="nav-deepitem {{ request()->routeIs('admin.branches*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-git-branch"></i> {{ __('sidebar.branch_outlet') }}</a>
                <a href="{{ route('admin.masterfile.products') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.products') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-package"></i> {{ __('sidebar.products') }}</a>
                {{-- REMOVED 12 Aug 2026 per Chris — Document Templates /
                     New Document Template dropped from the menu (the
                     Sales Transaction document-field-checklist feature
                     and its data are untouched, routes admin.document-
                     templates.index/.create still work if visited
                     directly, there's just no menu entry anymore). --}}
                <a href="{{ route('admin.masterfile.commissions') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.commissions') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-percentage"></i> {{ __('sidebar.earning_income_structures') }}</a>
                {{-- Reward Rates + Vendor Rebate Offers MOVED OUT 12 Aug
                     2026 per Chris — now their own top-level "Reward
                     Point Programs" menu, right before My Account. --}}
                {{-- MOVED IN 12 Aug 2026 per Chris — was a flat, unrelated
                     item lower down in Master File Maintenance even though
                     vendors/agents submit content into it for Admin to
                     review. Now the last stop in the vendor lifecycle. --}}
                <a href="{{ route('admin.video-library.index') }}" class="nav-deepitem {{ request()->routeIs('admin.video-library.*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-video"></i> {{ __('sidebar.content_library') }}</a>
            </div>
            {{-- REORGANIZED 2 Aug 2026 per Chris: all 4 Override screens
                 pulled together into their own group (were previously
                 scattered inside Tier Structure Maintenance), positioned
                 right after Vendor and Product Maintenance. --}}
            <div class="nav-subparent {{ $overrideActive ? 'open' : '' }}" id="ovParent" onclick="toggleDeep('ovMenu','ovParent',event)">
                <div class="spleft"><i class="ti ti-report-money"></i> {{ __('sidebar.affiliate_partner') }}</div>
                <span class="sparrow">▼</span>
            </div>
            <div class="nav-deep {{ $overrideActive ? 'open' : '' }}" id="ovMenu">
                <a href="{{ route('admin.masterfile.override-recipient-profile') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.override-recipient-profile*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-user-star"></i> {{ __('sidebar.affiliate_partner_profile') }}</a>
                {{-- RENAMED 8 Aug 2026 per Chris: dropped "Maintenance"
                     from every screen name directly under this subparent
                     (which is itself named "Affiliate Partner" now) — this
                     one specifically also used to be named identically to
                     its own subparent header ("Affiliate Partner
                     Maintenance" twice), which was doubly confusing. This
                     is the members list/CRUD screen (route:
                     override-members). --}}
                <a href="{{ route('admin.masterfile.override-members') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.override-members*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-building-bank"></i> {{ __('sidebar.affiliate_partner_members') }}</a>
                <a href="{{ route('admin.masterfile.override-claims') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.override-claims*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-report-money"></i> {{ __('sidebar.affiliate_partner_claims') }}</a>
                <a href="{{ route('admin.masterfile.override-ledger') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.override-ledger*') ? 'active' : '' }}" style="padding-left:44px;"><i class="ti ti-history"></i> {{ __('sidebar.affiliate_partner_ledger') }}</a>
            </div>
            <a href="{{ route('admin.masterfile.reason-codes') }}" class="nav-subitem" style="padding-left:32px;{{ request()->routeIs('admin.masterfile.reason-codes') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-list-details"></i> {{ __('sidebar.reason_code') }}</a>
            {{-- Content Library MOVED 12 Aug 2026 — now the last item
                 inside Vendor Management above (see $vendorMaintActive),
                 since it's part of the vendor content-submission flow,
                 not an unrelated flat item here. --}}
            {{-- NEW 5 Aug 2026 — Outbound Partner API key management
                 (GeneralLink issuing its own keys to outside systems —
                 reverse direction from the Integration Hub). ADMIN only. --}}
            <a href="{{ route('admin.partner-api.index') }}" class="nav-subitem" style="padding-left:32px;{{ request()->routeIs('admin.partner-api.*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-key"></i> {{ __('sidebar.partner_api_keys') }}</a>
            {{-- MOVED 14 Sep 2026 — per Chris: Program Library goes last
                 in Master File Maintenance, after Partner API Keys. Also
                 dropped the crown icon from this sidebar link itself
                 (per Chris: the crown belongs next to each individual
                 program INSIDE the library screen, not on the menu
                 entry that opens it). NEW 12 Sep 2026 (Task #416) —
                 Program Library master file: catalog of every program/
                 feature, each flaggable Paid or Free. --}}
            <a href="{{ route('admin.masterfile.program-library') }}" class="nav-subitem" style="padding-left:32px;{{ request()->routeIs('admin.masterfile.program-library*') || request()->routeIs('admin.masterfile.program-unlocks*') ? 'background:rgba(255,255,255,0.15);color:#fff;border-left:3px solid #00BCD4;' : '' }}"><i class="ti ti-list-details"></i> {{ __('admin_program_library.page_title') }}</a>
            @endif
        </div>

        {{-- ADMINISTRATION — removed 17 Jul 2026. Its last remaining item
             (Reason Code Maintenance) moved into Master File Maintenance,
             leaving nothing behind — Batch Upload, Audit Logs, Approvals,
             Group Name Maintenance, Organization Rewards Groups, and Calendar
             had already moved out in the previous reorg pass. --}}

        {{-- NEW 25 Jul 2026 — GROWTH & OUTREACH CENTER (task #209
             onward). Referral links, contests, badges, refer-a-friend,
             public profiles, broadcast campaigns and channel connections
             all live here as they're built. --}}
        <div class="nav-parent {{ $growthActive ? 'open' : '' }}" id="growthParent" onclick="toggleSection('growthMenu','growthParent')">
            <div class="pleft"><i class="ti ti-rocket"></i> {{ __('nav.growth_outreach') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $growthActive ? 'open' : '' }}" id="growthMenu">
            <a href="{{ route('referral-link.index') }}" class="nav-subitem {{ request()->routeIs('referral-link*') ? 'active' : '' }}"><i class="ti ti-share"></i> {{ __('sidebar.my_referral_link') }}</a>
            {{-- NEW 9 Aug 2026 — Rebate Offer Search, every logged-in role
                 (Chris: "search program for all login affiliate to search
                 the vendor profile for any rebate offer"). Shows only
                 vendor name, product and rebate details — never address
                 or vendor contact info. --}}
            <a href="{{ route('rebate-offers.search') }}" class="nav-subitem {{ request()->routeIs('rebate-offers*') ? 'active' : '' }}"><i class="ti ti-discount-2"></i> {{ __('sidebar.rebate_offer_search') }}</a>
            @if($agent->role === 'ADMIN')
            <a href="{{ route('admin.growth.contests.index') }}" class="nav-subitem {{ request()->routeIs('admin.growth.contests*') ? 'active' : '' }}"><i class="ti ti-trophy"></i> {{ __('sidebar.recruitment_contests') }}</a>
            @else
            <a href="{{ route('growth-contests.index') }}" class="nav-subitem {{ request()->routeIs('growth-contests*') ? 'active' : '' }}"><i class="ti ti-trophy"></i> {{ __('sidebar.recruitment_contests') }}</a>
            @endif
            <a href="{{ route('public-profile.edit') }}" class="nav-subitem {{ request()->routeIs('public-profile*') ? 'active' : '' }}"><i class="ti ti-address-book"></i> {{ __('sidebar.my_public_profile') }}</a>
            {{-- NEW 10 Aug 2026 — per Chris: "all agents including vendor
                 is allow to send attachment...marketing documents...to
                 admin." Every role can submit content for Admin review. --}}
            <a href="{{ route('content-submission.create') }}" class="nav-subitem {{ request()->routeIs('content-submission.create') ? 'active' : '' }}"><i class="ti ti-upload"></i> {{ __('sidebar.submit_marketing_content') }}</a>
            <a href="{{ route('content-submission.index') }}" class="nav-subitem {{ request()->routeIs('content-submission.index') ? 'active' : '' }}"><i class="ti ti-list-check"></i> {{ __('sidebar.my_submissions') }}</a>
            {{-- REPLACED 25 Jul 2026 — Survey Management Module, Phase 2
                 (task #232). Admin builds/manages/distributes/views
                 responses. Every role can send an Active survey to
                 their own customers via "Send Survey". --}}
            @if($agent->role === 'ADMIN')
            <a href="{{ route('admin.growth.surveys.index') }}" class="nav-subitem {{ request()->routeIs('admin.growth.surveys*') ? 'active' : '' }}"><i class="ti ti-clipboard-list"></i> {{ __('sidebar.survey_management') }}</a>
            @endif
            <a href="{{ route('survey-send.create') }}" class="nav-subitem {{ request()->routeIs('survey-send*') ? 'active' : '' }}"><i class="ti ti-send-2"></i> {{ __('sidebar.send_survey') }}</a>
            @if($agent->role === 'ADMIN')
            <a href="{{ route('admin.growth.broadcasts.index') }}" class="nav-subitem {{ request()->routeIs('admin.growth.broadcasts*') ? 'active' : '' }}"><i class="ti ti-send"></i> {{ __('sidebar.broadcast_campaigns') }}</a>
            <a href="{{ route('admin.growth.channels.index') }}" class="nav-subitem {{ request()->routeIs('admin.growth.channels*') ? 'active' : '' }}"><i class="ti ti-plug"></i> {{ __('sidebar.channel_connections') }}</a>
            @endif
        </div>

        {{-- REWARD POINT PROGRAMS — NEW 12 Aug 2026 per Chris: "7 and 8
             move out as new menu before my account." Reward Rates and
             Vendor Rebate Offers pulled out of Vendor Management into
             their own top-level menu — these are the programs run for
             agents/vendors, not vendor administration itself. --}}
        @if($agent->role === 'ADMIN')
        <div class="nav-parent {{ $rewardProgramsActive ? 'open' : '' }}" id="rewardParent" onclick="toggleSection('rewardMenu','rewardParent')">
            <div class="pleft"><i class="ti ti-star"></i> {{ __('nav.reward_point_programs') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $rewardProgramsActive ? 'open' : '' }}" id="rewardMenu">
            <a href="{{ route('admin.masterfile.reward-rates') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.reward-rates*') ? 'active' : '' }}"><i class="ti ti-star"></i> {{ __('sidebar.reward_rates') }}</a>
            <a href="{{ route('admin.masterfile.rebate-offers') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.rebate-offers*') ? 'active' : '' }}"><i class="ti ti-discount-2"></i> {{ __('sidebar.vendor_rebate_offers') }}</a>
            {{-- NEW 12 Aug 2026 per Chris — vendor-submitted rebate program applications, reviewed here before becoming a live offer above. --}}
            <a href="{{ route('admin.masterfile.rebate-applications') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.rebate-applications*') ? 'active' : '' }}"><i class="ti ti-clipboard-check"></i> {{ __('sidebar.rebate_program_applications') }}</a>
        </div>
        @endif

        {{-- MOVED 19 Sep 2026 — per Chris: "AI FAQ & Answers" (now
             labelled "AI Agent" in the sidebar only — the page itself
             keeps its own "AI FAQ & Answers" title) and "Community
             Business Enterprise Group" (renamed from "Secretarial
             Management") both move down to sit right before My Account,
             per Chris's requested sidebar sequence.

             RESTRICTED 19 Sep 2026 — per Chris: "why AI agents and
             Community business Enterprise menu follow every screen i
             navigate it is ONLY display in Main menu" — both links now
             only render while on the main Dashboard landing page itself,
             not on every sub-screen (Vendor KPI, Customer KPI, etc.). --}}
        @if(request()->routeIs('*.dashboard'))
        {{-- NEW 18 Sep 2026 — per Chris: "AI FAQ & Answers" is for every
             member, not just Secretarial officers (any agent with a CBE
             node can ask an existing assistant questions — creating new
             assistants stays Officer/Secretary-only, enforced in
             CbeAiAssistantController). It used to live only inside the
             Secretarial Management (GLADE) sidebar, which most members
             never see, so it's a flat top-level item here too, visible
             to every role. Same route, same page — it renders with
             whichever layout the visitor is on (see
             ai-assistants/index.blade.php's @extends). --}}
        <a href="{{ route('cbe.ai-assistants.index') }}" class="nav-item {{ request()->routeIs('cbe.ai-assistants*') ? 'active' : '' }}">
            <i class="ti ti-message-chatbot"></i> {{ __('sidebar.ai_agent') }}
        </a>

        {{-- MOVED 18 Sep 2026 — per Chris: everything CBE-specific is
             Secretarial's own responsibility, so this doorway no longer
             sits buried inside the Admin-only "KPI Dashboard" group
             (renamed from Overview KPI). It's now its own flat item,
             visible to Admin AND any active CBE node officer — same
             access rule GladePortalController::enter()/hasGladeAccess()
             already use, just finally matching it here too, so a
             non-Admin Secretary can actually see this link. --}}
        @if($agent->role === 'ADMIN' || \Illuminate\Support\Facades\DB::table('cbe_node_officers')->where('agent_id', $agent->agent_id)->where('is_active', true)->exists())
        <a href="{{ route('glade.enter') }}" class="nav-item nav-item-2row {{ request()->routeIs('glade.*') ? 'active' : '' }}" style="white-space:normal;line-height:1.15;">
            <i class="ti ti-building-community"></i> <span>{{ __('sidebar.community_business_line1') }}<br>{{ __('sidebar.community_business_line2') }}</span>
        </a>
        @endif
        @endif

        {{-- MY ACCOUNT --}}
        <div class="nav-parent {{ $accountActive ? 'open' : '' }}" id="accParent" onclick="toggleSection('accMenu','accParent')">
            <div class="pleft"><i class="ti ti-user-circle"></i> {{ __('nav.my_account') }}</div>
            <span class="parrow">▼</span>
        </div>
        <div class="nav-submenu {{ $accountActive ? 'open' : '' }}" id="accMenu">
            <a href="{{ $profileRoute }}" class="nav-subitem {{ $profileActive ? 'active' : '' }}"><i class="ti ti-id"></i> {{ __('nav.my_profile') }}</a>
            {{-- NEW 4 Aug 2026 — Integration Hub Phase 1: every role connects
                 their own third-party accounts (AI, communication, payments,
                 etc). Never falls back to the company's credentials. --}}
            <a href="{{ route('integrations.index') }}" class="nav-subitem {{ request()->routeIs('integrations.*') ? 'active' : '' }}"><i class="ti ti-plug"></i> {{ __('sidebar.my_integrations') }}</a>
            @if($agent->role !== 'ADMIN')
            <a href="{{ route('wallet.show') }}" class="nav-subitem"><i class="ti ti-wallet"></i> {{ __('sidebar.earning_income_wallet') }}</a>
            {{-- NEW 21 Jul 2026 — Document Credit Wallet, separate from Earning Income Wallet on purpose (see migration comment). Admin never uploads documents, so this is hidden for Admin — Admin manages it from Reminder Processes > Document Credit instead. --}}
            <a href="{{ route('document-credit.show') }}" class="nav-subitem {{ request()->routeIs('document-credit.*') ? 'active' : '' }}"><i class="ti ti-scan"></i> {{ __('sidebar.document_credit') }}</a>
            {{-- NEW 21 Jul 2026 — read-only downline view: GL sees whole group (or one TL's team), TL sees their own Introducers, Introducer sees their own recruited sub-Introducers. --}}
            <a href="{{ route('team-document-credit.index') }}" class="nav-subitem {{ request()->routeIs('team-document-credit.*') ? 'active' : '' }}"><i class="ti ti-users-group"></i> {{ __('sidebar.team_document_credit') }}</a>
            {{-- MOVED 21 Jul 2026 — Help Desk lives under Communication &
                 Action Center (as "Inbox"), visible to every role. MOVED
                 8 Aug 2026 — Notice Board also moved there (was
                 duplicated here AND in that menu; per Chris: "i am lost
                 and confuse where it is" — now just the one link). --}}
            {{-- REMOVED 8 Aug 2026 per Chris: "remove everything ... i want
                 to redo and revamp" — Submit Partner Offer / My Offer
                 Submissions links removed along with the rest of the
                 Offer Request feature. --}}
            @endif
            {{-- MOVED 17 Jul 2026 — Audit Logs relocated here from
                 Administration per Chris's reorg request. --}}
            @if($agent->role === 'ADMIN')
            <a href="{{ route('admin.masterfile.audit-logs') }}" class="nav-subitem {{ request()->routeIs('admin.masterfile.audit-logs*') ? 'active' : '' }}"><i class="ti ti-shield-check"></i> {{ __('sidebar.audit_logs') }}</a>
            @endif
            {{-- NEW 25 Jul 2026 (task #207) — Breakaway Bonus claim
                 vouchers. Only a Group Leader can ever be an
                 original_gl_agent_id, so this is GL + Admin only. --}}
            @if(in_array($agent->role, ['ADMIN', 'GROUP_LEADER']))
            <a href="{{ route('breakaway-claims.index') }}" class="nav-subitem {{ request()->routeIs('breakaway-claims.*') ? 'active' : '' }}"><i class="ti ti-award"></i> {{ __('sidebar.breakaway_bonus_claims') }}</a>
            @endif
            {{-- Appoint Team Leader / Add Introducer (Organization Rewards Group) moved
                 to Master File Maintenance → Tier Structure Maintenance
                 (15 Jul 2026), alongside every other tier-management action. --}}
            <a href="#" class="nav-subitem"><i class="ti ti-heart"></i> {{ __('sidebar.beneficiary') }}</a>
        </div>

    </div>

    <div class="sidebar-prev-btn" id="sidebarPrevBtn" onclick="closeFocusedMenu()">
        <i class="ti ti-arrow-back-up"></i> {{ __('sidebar.prev_main_menu') }}
    </div>

    <div class="sidebar-footer">
        <div class="agent-card">
            <div class="agent-avatar">{{ strtoupper(substr($agent->full_name,0,2)) }}</div>
            <div style="flex:1;min-width:0">
                <div class="agent-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $agent->full_name }}</div>
                <div class="agent-code">{{ $agent->agent_code ?? $agent->member_code ?? '—' }}</div>
            </div>
            {{-- Clear any saved Admin dashboard scope selection (localStorage)
                 on logout — it's tied to the browser, not the login session,
                 so without this the NEXT person to log in on this browser
                 (or the same Admin logging back in) would silently see the
                 previous session's remembered group/month instead of a
                 fresh "select a scope" prompt. --}}
            <form method="POST" action="{{ route('auth.logout') }}" onsubmit="try{localStorage.removeItem('gl_admin_dashboard_scope_v1');}catch(e){}">
                @csrf
                <button type="submit" style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.7);width:32px;height:32px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px" title="{{ __('nav.logout') }}">
                    <i class="ti ti-logout"></i>
                </button>
            </form>
        </div>
    </div>
</nav>

{{-- NEW 12 Sep 2026 — per Chris: on the Edit Group Name screen the
     shared topbar (notifications/WhatsApp/language/profile icons) was
     eating into the ~46px this tightly-fit no-scroll screen needed, and
     Chris explicitly asked for it gone on this one screen. A page opts
     out by defining @section('hide-topbar', '1') — every other screen
     is completely unaffected since this section is empty everywhere
     else. #main's top offset is adjusted to match right below. --}}
@if (! $__env->hasSection('hide-topbar'))
<header id="topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button onclick="toggleSidebar()" style="display:none;width:36px;height:36px;border:none;background:none;cursor:pointer;font-size:22px;color:#1E88E5" id="hamburger">
            <i class="ti ti-menu-2"></i>
        </button>
        <span class="topbar-title">@yield('page-title','Dashboard')</span>
    </div>
    <div class="topbar-right" style="position:relative;">
        <div style="position:relative;">
            <a href="#" class="topbar-btn" title="{{ __('nav.notifications') }}" onclick="toggleNotifDropdown(event)">
                <i class="ti ti-bell"></i>
                <span class="badge-count" id="notifBadge" style="display:none;">0</span>
            </a>
            <div id="notifDropdown" style="display:none; position:absolute; top:110%; right:0; width:340px; max-height:420px; overflow-y:auto; background:#fff; border:1px solid #B2EBF2; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:200;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid #f3f4f6;">
                    <strong style="font-size:12px; color:#1565C0;">{{ __('nav.notifications') }}</strong>
                    <span onclick="markAllNotifsRead()" style="font-size:10.5px; color:#1B9AE4; cursor:pointer;">{{ __('sidebar.mark_all_read') }}</span>
                </div>
                <div id="notifList" style="padding:6px 0;">
                    <div style="padding:16px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('sidebar.loading') }}</div>
                </div>
            </div>
        </div>
        {{-- NEW 6 Aug 2026 — Send WhatsApp Message button, per Chris:
             positioned right after the notification bell. Uses the
             WhatsApp credentials saved in Integration Hub > Communication.
             See WhatsAppController. --}}
        <a href="{{ route('whatsapp.form') }}" class="topbar-btn" title="{{ __('nav.send_whatsapp') }}" style="color:#fff; background:#25D366; border-color:#25D366;" onmouseover="this.style.background='#1FAE55'" onmouseout="this.style.background='#25D366'">
            <i class="ti ti-brand-whatsapp"></i>
        </a>
        {{-- NEW 22 Jul 2026 — quick-switch language control. Same
             preferred_language column as the My Profile dropdown — both
             write to the identical field, so they can never disagree.
             OPENED UP 18 Aug 2026 — per Chris: now visible to every
             role (was TL/Introducer only) — Group Leader, Admin, and
             CBE members can all switch too. Default stays English;
             order is ENG / BM / CHI per Chris's instruction. --}}
        {{-- NEW 26 Aug 2026 — per Chris: "your language ico should change
             to BM/ENG/CHI" — button now shows the current language's
             short code as text instead of a generic globe glyph. --}}
        @php
            $langShortMap = ['EN' => 'ENG', 'MS' => 'BM', 'ZH' => 'CHI'];
            $langShort = $langShortMap[$agent->preferred_language ?? 'EN'] ?? 'ENG';
        @endphp
        <div style="position:relative;">
            <a href="#" class="topbar-btn gl-tip" data-tip="{{ __('nav.language_tooltip') }}" title="{{ __('nav.language') }}" onclick="toggleLangDropdown(event)" style="width:auto; min-width:30px; padding:0 8px; font-size:10px; font-weight:700; letter-spacing:0.3px;">
                {{ $langShort }}
            </a>
            <div id="langDropdown" style="display:none; position:absolute; top:110%; right:0; width:170px; background:#fff; border:1px solid #B2EBF2; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:200; overflow:hidden;">
                @foreach(['EN' => 'English', 'MS' => 'Bahasa Malaysia', 'ZH' => '中文 (Chinese)'] as $code => $label)
                <form method="POST" action="{{ route('language.quick-switch') }}">
                    @csrf
                    <input type="hidden" name="language" value="{{ $code }}">
                    <button type="submit" style="width:100%; text-align:left; background:{{ ($agent->preferred_language ?? 'EN') === $code ? '#E0F7FA' : '#fff' }}; border:none; padding:9px 14px; font-size:11.5px; color:#374151; cursor:pointer; {{ ($agent->preferred_language ?? 'EN') === $code ? 'font-weight:700; color:#1565C0;' : '' }}">
                        {{ ($agent->preferred_language ?? 'EN') === $code ? '✓ ' : '' }}{{ $label }}
                    </button>
                </form>
                @endforeach
            </div>
        </div>
        <a href="{{ $profileRoute }}" class="topbar-btn" title="{{ __('nav.my_profile') }}">
            <i class="ti ti-user-circle"></i>
        </a>
    </div>
</header>
@endif
@if ($__env->hasSection('hide-topbar'))
<style>#main{top:0 !important;}</style>
@endif

<script>
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
});

// Check for new notifications every 30 seconds
loadNotifications();
setInterval(loadNotifications, 30000);

// Approvals badge — Admin-only (element only exists in Admin's sidebar).
if (document.getElementById('approvalsBadge')) {
    function loadApprovalsBadge() {
        fetch('{{ route('admin.approvals.pending-count') }}')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var badge = document.getElementById('approvalsBadge');
                if (data.count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                } else {
                    badge.style.display = 'none';
                }
            });
    }
    loadApprovalsBadge();
    setInterval(loadApprovalsBadge, 30000);
}

// NEW 8 Aug 2026 (Task #92) — Pending Vendor Logins badge — Admin-only, same pattern as Approvals above.
if (document.getElementById('vendorPendingBadge')) {
    function loadVendorPendingBadge() {
        fetch('{{ route('admin.vendors.pending-logins.count') }}')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var badge = document.getElementById('vendorPendingBadge');
                if (data.count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                } else {
                    badge.style.display = 'none';
                }
            });
    }
    loadVendorPendingBadge();
    setInterval(loadVendorPendingBadge, 30000);
}

// REMOVED 8 Aug 2026 per Chris: "remove everything ... i want to redo and
// revamp" — Offer Request Approval badge polling removed along with the
// rest of the feature.
</script>

<main id="main">
    <div class="page-content">
        @yield('content')
    </div>
</main>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebar-overlay').classList.toggle('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebar-overlay').classList.remove('show');}
if(window.innerWidth<=768)document.getElementById('hamburger').style.display='flex';
window.addEventListener('resize',()=>{document.getElementById('hamburger').style.display=window.innerWidth<=768?'flex':'none';});

(function() {
    var sidebarEl = document.getElementById('sidebar');
    if (!sidebarEl) { return; }

    // NEW 8 Aug 2026 — hover tooltip on every sidebar item, per Chris:
    // "incorporate tips if i move my cursor to the menu icon to see what
    // it is about" — most useful once the sidebar is collapsed to icons
    // only and the label text itself is hidden. Auto-generated from each
    // item's own visible text (not hand-typed per line), so every
    // current AND future menu item gets one automatically with nothing
    // to keep in sync.
    sidebarEl.querySelectorAll('.nav-item, .nav-subitem, .nav-deepitem, .nav-parent, .nav-subparent').forEach(function(el) {
        if (!el.title) {
            var label = el.textContent.replace(/[▶▼▲]/g, '').trim();
            if (label) { el.title = label; }
        }
    });
})();

// FIXED 8 Aug 2026 — the SECTION_IDS list below was missing crmMenu/
// crmParent (Customer Relationship), so opening Customer Relationship
// never closed a different open section, and opening a different
// section never closed Customer Relationship if it was the one left
// open — two categories could end up open at once. Single shared list
// now, used everywhere a section needs to be found or closed, so a
// future 8th category only needs adding here once.
const SECTION_IDS = [
    { menu: 'kpiMenu',    parent: 'kpiParent' },
    { menu: 'netMenu',    parent: 'netParent' },
    { menu: 'crmMenu',    parent: 'crmParent' },
    { menu: 'bizMenu',    parent: 'bizParent' },
    { menu: 'remMenu',    parent: 'remParent' },
    { menu: 'actMenu',    parent: 'actParent' },
    { menu: 'mfmMenu',    parent: 'mfmParent' },
    { menu: 'growthMenu', parent: 'growthParent' },
    { menu: 'rewardMenu', parent: 'rewardParent' },
    { menu: 'accMenu',    parent: 'accParent' },
];

function toggleSection(menuId, parentId) {
    const menu   = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    const sidebar = document.getElementById('sidebar');
    const nav    = document.getElementById('sidebarNav');
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    SECTION_IDS.forEach(({ menu: mId, parent: pId }) => {
        if (mId !== menuId) { const m = document.getElementById(mId); if (m) m.classList.remove('open'); }
        if (pId !== parentId) { const p = document.getElementById(pId); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    // NEW 23 Jul 2026 (v10), FIXED 8 Aug 2026 — per Chris: "WHEN I CLICK
    // THE MENU DRILL DOWN YOU SHOULD SHOW EVERYTHING TO ME NOT SCROLL
    // DOWN", then later: "others not related menu selection should not
    // displace but have a prev button ... to click back to main menu."
    // Every OTHER top-level heading is hidden while one menu is open (see
    // #sidebar.focused CSS rule), handing the open section the whole
    // sidebar height. FIXED: .focused now toggles on #sidebar itself
    // (the CSS rules were always written against #sidebar.focused — the
    // old code toggled it on #sidebarNav instead, so this never actually
    // fired). Returning to the main menu is now the Prev button
    // (closeFocusedMenu below), not re-clicking the open heading.
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

// NEW 8 Aug 2026 — Prev button, per Chris: a drilled-down category should
// return to the main menu via an explicit button rather than requiring
// the user to find and re-click the same (now-only-visible) open header.
function closeFocusedMenu() {
    SECTION_IDS.forEach(({ menu: mId, parent: pId }) => {
        const m = document.getElementById(mId); if (m) m.classList.remove('open');
        const p = document.getElementById(pId); if (p) p.classList.remove('open');
    });
    const sidebar = document.getElementById('sidebar');
    if (sidebar) { sidebar.classList.remove('focused'); }
}

// NEW 10 Aug 2026 — per Chris: "you always push up the menu on top so
// that the user can view all option below the menu not scroll." Root
// cause: the "hide every other heading" (.focused) step only ever ran
// INSIDE toggleSection(), i.e. only when the user clicked a heading
// during the current page view. If a page loads with a section
// already open server-side (e.g. clicking straight to "Broadcast
// Campaigns" opens Growth & Outreach Center on arrival, no click
// happens), .focused was never added — every other heading stayed
// visible and pushed the open section's own items off the bottom of
// the sidebar, forcing a scrollbar. Fix: on every page load, check
// whether any section already rendered "open" (server-side, based on
// the current route) and apply .focused immediately, exactly as if
// the user had just clicked it.
(function() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const anyOpen = SECTION_IDS.some(function(s) {
        const p = document.getElementById(s.parent);
        return p && p.classList.contains('open');
    });
    if (anyOpen) { sidebar.classList.add('focused'); }
})();

function toggleDeep(menuId, parentId, event) {
    event.stopPropagation();
    const menu   = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    if (!isOpen) {
        setTimeout(() => {
            const nav = document.getElementById('sidebarNav');
            const parentEl = document.getElementById(parentId);
            const navRect = nav.getBoundingClientRect();
            const parentRect = parentEl.getBoundingClientRect();
            nav.scrollTo({ top: nav.scrollTop + parentRect.top - navRect.top - 10, behavior: 'smooth' });
        }, 50);
    }
}
</script>
@stack('scripts')
<script src="/js/address-lookup.js"></script>

@include('partials.carolyn-write-helper-script')
@include('partials.ai-assistant-widget', ['guestMode' => false])
{{-- NEW 4 Aug 2026 — AI Guided Navigation overlay was only included on the
     3 guest auth pages so far (register/verify-pending), so Carolyn had no
     way to highlight anything for a logged-in agent even after being asked
     to. Including it globally here is what actually makes start_guided_task
     usable for any logged-in role, not just guests. --}}
@include('partials.ai-guidance-overlay')
</body>
</html>