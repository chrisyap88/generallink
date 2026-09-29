@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_cbe_kpi.title_director'))

@section('content')

<style>
.ed-box{flex:1; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; padding:8px 12px; display:flex; flex-direction:column; min-width:0; box-sizing:border-box; box-shadow:0 2px 10px rgba(21,101,192,0.10);}
.ed-box-title{flex-shrink:0; font-size:9px; font-weight:700; color:var(--gl-blue); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:4px; padding-bottom:4px; border-bottom:1px solid #eef2f7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
{{-- FIXED 28 Aug 2026 — per Chris: "all KPI Box text truncated... box 6
follow the main cbe method, realign and adjust the text for all box."
Same self-fitting method already used for Box 7: every row is its own
flex:1 child (not justify-content:center on a fixed-size block), so a
box with 5 rows (Box 6) shares its real height across exactly 5 rows and
a box with 3 rows shares it across 3 — rows can never overlap the title
or each other, whatever height the box ends up with. Applies to every
box on this screen (Box 1-6) since they all share these two classes. --}}
.ed-rows{flex:1; display:flex; flex-direction:column; gap:2px; min-height:0;}
.ed-row{flex:1; min-height:0; display:flex; justify-content:space-between; align-items:center; font-size:8px; gap:6px;}
.ed-row span:first-child{color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-row span:last-child{font-weight:700; color:#0d3c72; white-space:nowrap; flex-shrink:0;}
/* NEW 27 Sep 2026 — per Chris: figures in plain rows line up with the
   figures in drill-down rows (which end with ' ›') — same invisible ' ›'. */
div.ed-row > span:last-child::after{content:' ›'; visibility:hidden;}
.cbe-level-input{border:1px solid #b2ebf2;background:#fff;font-family:'Poppins',sans-serif;font-size:8px;font-weight:600;color:var(--gl-blue);outline:none;border-radius:5px;padding:3px 6px;width:100%;box-sizing:border-box;}
.cbe-tab-btn{padding:5px 14px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; user-select:none;}
.cbe-tab-btn.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbe-profile-field{display:flex; flex-direction:column; gap:3px; margin-bottom:8px;}
.cbe-profile-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.cbe-profile-field input, .cbe-profile-field textarea{font-family:'Poppins',sans-serif; font-size:9.5px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; box-sizing:border-box; width:100%;}
.cbe-phone-row{display:flex; gap:6px; align-items:center; margin-bottom:5px;}
.cbe-phone-row input{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box;}
.cbe-phone-remove-btn{background:#fee2e2; color:#b91c1c; border:none; border-radius:5px; padding:5px 8px; font-size:9px; font-weight:700; cursor:pointer; flex-shrink:0;}
.cbe-phone-add-btn{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:5px 10px; font-size:8.5px; font-weight:700; cursor:pointer; align-self:flex-start; margin-top:2px;}
.cbe-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:4px 16px; box-sizing:border-box; gap:5px;">

    {{-- CHANGED 28 Aug 2026 — per Chris: "in the entity kpi you no need
    to show the selection filter row" — once a single entity is drilled
    into (the Profile/Contact/KPI/Members/... tab bar is showing), the
    Group/State/Branch/Entity search boxes are redundant — that entity is
    already fixed, and "Back to Dashboard" is how you change it. Row stays
    in the DOM (just visually hidden) rather than removed, since the page
    script always builds the 4 search boxes into #cbe-level-selects on
    load — removing the element outright would break that script for
    every screen, not just this one. --}}
    {{-- FIXED 27 Sep 2026 — room on the right so Carolyn's bubble (fixed, top-right) never covers the Go button / month arrows. --}}
    <div style="flex-shrink:0; padding-right:58px; display:{{ $showTabs ? 'none' : 'flex' }}; align-items:center; gap:8px;">
        <div id="cbe-level-selects" style="display:flex; align-items:center; gap:6px; flex:1; min-width:0;"></div>
        {{-- MOVED 27 Aug 2026 — per Chris: "there is date at the search
        bar? that determine the status on financial standing period as
        MTD, YTD, LYTD selection period" — this now lives IN the search
        bar itself (was previously only shown after a scope was
        selected), so the period is visible and adjustable from the very
        first screen, before Go is even clicked. Preserves whichever
        group/state/branch/temple is currently selected via
        fullUrlWithQuery (only the 'period' param changes). --}}
        <div style="flex-shrink:0; display:flex; align-items:center; gap:5px; font-size:8.5px; font-weight:700; color:var(--gl-blue); white-space:nowrap;">
            <a href="{{ request()->fullUrlWithQuery(['period' => $periodPrevParam]) }}" style="color:var(--gl-blue); text-decoration:none; padding:2px 5px;">←</a>
            <span style="white-space:nowrap;">{{ $periodLabel }}</span>
            @if($periodNextParam)
            <a href="{{ request()->fullUrlWithQuery(['period' => $periodNextParam]) }}" style="color:var(--gl-blue); text-decoration:none; padding:2px 5px;">→</a>
            @else
            <span style="color:#cbd5e1; padding:2px 5px;">→</span>
            @endif
            @if($isCurrentPeriod)
            <span style="font-size:7px; font-weight:600; color:#94A3B8; white-space:nowrap;">({{ __('cbe_exec.current_month') }})</span>
            @else
            <a href="{{ request()->fullUrlWithQuery(['period' => now()->format('Y-m')]) }}" style="font-size:7px; font-weight:700; color:#c62828; text-decoration:none; white-space:nowrap;">{{ __('cbe_exec.back_to_current_month') }}</a>
            @endif
        </div>
        <button type="button" id="cbe-go-btn" onclick="onCbeGo()" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:4px 14px; font-size:8.5px; font-weight:700; cursor:pointer; flex-shrink:0;">▶ {{ __('admin_cbe_kpi.go_button') }}</button>
    </div>

    @if(!$hasSelection)
    <div style="flex:1; display:flex; align-items:center; justify-content:center;">
        <div style="text-align:center; color:#94A3B8; font-size:10px; max-width:420px;">{{ __('admin_cbe_kpi.select_prompt') }}</div>
    </div>
    @else
    {{-- FIXED 28 Aug 2026 — per Chris: "put error message same row with
    title... move screen higher" — the "✕ All CBE Groups" reset link used
    to be its own separate row above this one, costing a whole extra line
    of height for every drilled-down screen. Merged onto this row
    (justify-content:space-between already spaces the two ends) to
    reclaim that space for the boxes below. --}}
    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:8px; padding-right:58px;">{{-- 27 Sep 2026: clear of Carolyn --}}
        <div style="font-size:8.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0;">
            {{ $scopeLabel }}
            @if($groupTier)
                — {{ $groupTier === 'PAID' ? __('admin_cbe_kpi.group_tier_paid') : __('admin_cbe_kpi.group_tier_free') }}
            @endif
        </div>
        @if($selectedGroupId !== 'ALL')
        <a href="{{ route('admin.cbe-kpi', ['scope' => 'all']) }}" style="font-size:8px; color:#c62828; text-decoration:none; font-weight:600; white-space:nowrap; flex-shrink:0;">✕ {{ __('admin_cbe_kpi.all_cbe_groups') }}</a>
        @endif
    </div>

    {{-- NEW 26 Aug 2026, 8th pass — per Chris: "under this temple profile
    you have 2 tap folder on top once is profile, one is kpi." Tabs only
    appear when a single real node is in view (a district-only or "All"
    view has no one node to show a profile for). --}}
    {{-- REORDERED 26 Aug 2026, 12th pass — per Chris: "the folder tap
    sequence is suppose to have profile, add contact tel no and lastly
    KPI." Tab ORDER is now Profile / Contact & Tel No / KPI (left to
    right), though KPI still opens first by default (unchanged landing
    behaviour) — only the button positions moved. --}}
    {{-- CHANGED 26 Aug 2026, 13th pass — per Chris: "when come to this
    screen, you default show the profile screen tap not kpi" — landing
    tab is now Profile (first in the tab order), not KPI. --}}
    @if($showTabs)
    <div style="flex-shrink:0; display:flex; gap:4px; border-bottom:1px solid #e2e8f0;">
        <div class="cbe-tab-btn active" data-tab="profile" onclick="cbeSwitchTab('profile')">{{ __('admin_cbe_kpi.tab_profile') }}</div>
        <div class="cbe-tab-btn" data-tab="contact" onclick="cbeSwitchTab('contact')">{{ __('admin_cbe_kpi.tab_contact') }}</div>
        <div class="cbe-tab-btn" data-tab="kpi" onclick="cbeSwitchTab('kpi')">{{ __('admin_cbe_kpi.tab_kpi') }}</div>
        {{-- NEW 26 Aug 2026, 15th pass — per Chris: "the 5th Tap is
        customer, the 6th tap is sponsor" (Members = 4th). These 3 open
        their own FULL SCREEN (not an inline panel like the 3 tabs
        above) — per Chris: "show the entire profile screen ya." --}}
        <a href="{{ route('admin.cbe-kpi.members', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_members') }}</a>
        <a href="{{ route('admin.cbe-kpi.customers', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_customers') }}</a>
        <a href="{{ route('admin.cbe-kpi.donors', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_donors') }}</a>
        <a href="{{ route('admin.cbe-kpi.appointments', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_appointments') }}</a>
    </div>
    @endif

    @php
        // NEW 26 Aug 2026, 12th pass — per Chris: "why show Non klang
        // temple... Penang sekinchiang all Malaysia temple that is
        // wrong" — every "drill into node list" link below must carry
        // the CURRENT scope (a real node like a State, or a Klang-style
        // district + its parent State) forward, otherwise clicking them
        // silently resets to the whole group nationwide.
        $cbeNodesScope = [];
        if ($drilledNodeId) {
            $cbeNodesScope['node'] = $drilledNodeId;
        } elseif (! empty($currentDistrictName)) {
            $cbeNodesScope['district'] = $currentDistrictName;
            if (! empty($currentScopeParentId)) {
                $cbeNodesScope['parent'] = $currentScopeParentId;
            }
        }
    @endphp
    {{-- FIXED 27 Aug 2026 — per Chris: "i select every thing...it
    display nothing." When $showTabs is false (a district/Branch or
    All-CBE scope, not one single real temple), the Profile/Contact/KPI
    tab bar above doesn't render at all (correctly — those tabs only
    make sense for one temple) — but this panel defaulted to hidden
    regardless, and with no tab button left to reveal it, the KPI
    numbers never appeared. It's now the visible-by-default panel
    whenever there's no tab bar to switch it in. --}}
    <div id="cbe-tab-kpi" style="display:{{ $showTabs ? 'none' : 'flex' }}; flex:1; min-height:0; flex-direction:column; gap:8px;">
        {{-- REDESIGNED 27 Aug 2026 — 7-box layout per Chris's wireframe
        (master spec Sections 53-57). Box titles/content below replace
        the earlier 6-box + wide-tile layout; color scheme, box border
        shading and font are unchanged per Chris's explicit instruction
        to keep those as-is. Rows with no drill-down screen built yet
        show a plain (non-clickable) figure — Secretarial Overview's 5
        sub-tabs, the Income/Expense breakdown screens, and the internal
        approval-message inbox are still in progress (task list #227-233)
        and will become links once those screens ship, rather than
        linking to a page that doesn't exist yet. --}}
        <div style="flex:1.6; min-height:0; display:flex; gap:8px;">
            {{-- Box 1: Events Overview (3 rows) --}}
            <div class="ed-box">
                <div class="ed-box-title">{{ __('cbe_exec.box_events_overview') }}</div>
                <div class="ed-rows">
                    <div class="ed-row"><span>{{ __('cbe_exec.row_this_month') }}</span><span>{{ number_format($thisMonthEvents) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_next_3_months') }}</span><span>{{ number_format($next3MonthsEventsCount) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_previous_month') }}</span><span>{{ number_format($previousMonthEvents) }}</span></div>
                </div>
            </div>
            {{-- Box 2: Secretarial Overview — real zero-safe counts from
            cbe_committee_positions / cbe_meeting_minutes / cbe_correspondence.
            NEW 27 Aug 2026 — all 3 rows now drill into a 3-tab detail
            screen (task #228). --}}
            <div class="ed-box">
                <div class="ed-box-title">{{ __('cbe_exec.box_secretarial_overview') }}</div>
                <div class="ed-rows">
                    <a href="{{ route('admin.cbe-kpi.secretarial-detail', array_merge(request()->only(['node', 'district', 'parent', 'scope', 'period']), ['tab' => 'committee'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_committee_members') }}</span><span>{{ number_format($committeeCurrentTermCount) }} ›</span></a>
                    <a href="{{ route('admin.cbe-kpi.secretarial-detail', array_merge(request()->only(['node', 'district', 'parent', 'scope', 'period']), ['tab' => 'meetings'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_meetings_this_year') }}</span><span>{{ number_format($meetingsThisYear) }} ›</span></a>
                    <a href="{{ route('admin.cbe-kpi.secretarial-detail', array_merge(request()->only(['node', 'district', 'parent', 'scope', 'period']), ['tab' => 'correspondence'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_correspondence_total') }}</span><span>{{ number_format($correspondenceTotalCount) }} ›</span></a>
                    {{-- MOVED 27 Sep 2026 — per Chris: Active Members, Donors and
                    Volunteers moved here from Box 4. --}}
                    @if($showTabs)
                    <a href="{{ route('admin.cbe-kpi.members', ['node' => $profileNode->node_id, 'mode' => 'search', 'do_search' => 1]) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_total_active_members') }}</span><span>{{ number_format($totalMembers) }} ›</span></a>
                    <a href="{{ route('admin.cbe-kpi.donors', ['node' => $profileNode->node_id, 'mode' => 'search', 'do_search' => 1]) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_total_donors') }}</span><span>{{ number_format($totalDonorsBox4) }} ›</span></a>
                    @else
                    <div class="ed-row"><span>{{ __('cbe_exec.row_total_active_members') }}</span><span>{{ number_format($totalMembers) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_total_donors') }}</span><span>{{ number_format($totalDonorsBox4) }}</span></div>
                    @endif
                    <div class="ed-row"><span>{{ __('cbe_exec.row_consultant_volunteers') }}</span><span>{{ number_format($consultantVolunteerCount) }}</span></div>
                </div>
            </div>
            {{-- Box 3: Financial Overview (renamed from Finance Overview).
            NEW 27 Aug 2026 — all 3 rows now drill into a 3-tab detail
            screen (Income entries / Expense entries / Bank Accounts),
            per Chris: "display the details of the entries from the AR
            by individual row... Bank Balance... a CBE may have more
            than one bank account." Cash Balance renamed to Bank Balance,
            now sourced from actual uploaded bank statements per account
            instead of the journal's cash-account figure. --}}
            <div class="ed-box">
                <div class="ed-box-title">{{ __('cbe_exec.box_financial_overview') }}</div>
                <div class="ed-rows">
                    <a href="{{ route('admin.cbe-kpi.financial-detail', array_merge(request()->only(['node', 'district', 'parent', 'scope', 'period']), ['tab' => 'income'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_total_income') }}</span><span>RM {{ number_format($financial['total_income'], 2) }} ›</span></a>
                    <a href="{{ route('admin.cbe-kpi.financial-detail', array_merge(request()->only(['node', 'district', 'parent', 'scope', 'period']), ['tab' => 'expenses'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_total_expenses') }}</span><span>RM {{ number_format($financial['total_expense'], 2) }} ›</span></a>
                    <a href="{{ route('admin.cbe-kpi.financial-detail', array_merge(request()->only(['node', 'district', 'parent', 'scope', 'period']), ['tab' => 'bank'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_bank_balance') }}</span><span>RM {{ number_format($bankBalanceTotal, 2) }} ›</span></a>
                </div>
            </div>
        </div>

        {{-- FIXED 28 Aug 2026 — per Chris: box overlap fix. This row holds
        Box 6 (up to 5 rows), the tallest of the 6 top boxes, so it now
        gets more flex share than Box 1-3's row (which only has 3 rows
        max) instead of an equal 1:1 split that starved it. --}}
        <div style="flex:1.6; min-height:0; display:flex; gap:8px;">
            {{-- Box 4: Current Overview (renamed from Statistic Overview) --}}
            <div class="ed-box">
                <div class="ed-box-title">{{ __('cbe_exec.box_current_overview') }}</div>
                <div class="ed-rows">
                    {{-- CHANGED 27 Sep 2026 — per Chris: every tier from the real
                    records, last row Total Affiliate Entity (see
                    AdminCbeKpiController::tierCounts). --}}
                    <div class="ed-row"><span>{{ __('cbe_exec.row_tier_hq') }}</span><span>{{ number_format($tierCounts['hq']) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_tier_state') }}</span><span>{{ number_format($tierCounts['state']) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_tier_branch') }}</span><span>{{ number_format($tierCounts['branch']) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_tier_city') }}</span><span>{{ number_format($tierCounts['city']) }}</span></div>
                    @if($selectedGroupId !== 'ALL')
                    <a href="{{ route('admin.cbe-kpi.nodes', array_merge(['group' => $selectedGroupId, 'level' => $childBranchLabel], $cbeNodesScope)) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_tier_entity') }} ({{ $childBranchLabel }})</span><span>{{ number_format($tierCounts['entity']) }} ›</span></a>
                    @else
                    {{-- All CBE Groups mixes Temples, Clubs … — plain "Entity". --}}
                    <div class="ed-row"><span>{{ __('cbe_exec.row_tier_entity') }}</span><span>{{ number_format($tierCounts['entity']) }}</span></div>
                    @endif
                    <div class="ed-row" style="border-top:1px solid #eef2f7;"><span style="font-weight:700; color:#263238;">{{ __('cbe_exec.row_total_affiliate') }}</span><span>{{ number_format($tierCounts['total']) }}</span></div>
                </div>
            </div>
            {{-- Box 5: Current Financial Snapshot (renamed from Approvals & Pending Tasks) --}}
            <div class="ed-box">
                <div class="ed-box-title">{{ __('cbe_exec.box_current_financial_snapshot') }}</div>
                <div class="ed-rows">
                    <div class="ed-row"><span>{{ __('cbe_exec.row_outstanding_collection') }}</span><span style="color:{{ $outstandingCollectionTotal > 0 ? '#c62828' : 'var(--gl-blue)' }};">RM {{ number_format($outstandingCollectionTotal, 2) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_outstanding_payables') }}</span><span style="color:{{ $outstandingPayablesTotal > 0 ? '#c62828' : 'var(--gl-blue)' }};">RM {{ number_format($outstandingPayablesTotal, 2) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_available_cash_balance') }}</span><span>RM {{ number_format($availableCashBalance, 2) }}</span></div>
                </div>
            </div>
            {{-- Box 6: Pending Tasks, Notifications & Alerts --}}
            <div class="ed-box">
                <div class="ed-box-title">{{ __('cbe_exec.box_pending_tasks_notifications') }}</div>
                <div class="ed-rows">
                    {{-- NEW 27 Aug 2026 — per Chris: "group the bill due
                    and overdue into one when drill down show 2 tap
                    folder" — merged into 1 row, drilling into a 2-tab
                    (Due Soon / Overdue) screen instead of 2 plain rows. --}}
                    <a href="{{ route('admin.cbe-kpi.bills', request()->only(['node', 'district', 'parent', 'scope', 'period'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_bills_due_soon') }} / {{ __('cbe_exec.row_bills_overdue') }}</span><span style="color:{{ $billsOverdue > 0 ? '#c62828' : 'var(--gl-blue)' }};">{{ number_format($billsDueOverdueTotal) }} ›</span></a>
                    {{-- per Chris: "group approval into one and allow to
                    drill down to show the 2 tab" — Pending / History. --}}
                    <a href="{{ route('admin.cbe-kpi.approvals', request()->only(['node', 'district', 'parent', 'scope', 'period'])) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_pending_approval_messages') }}</span><span style="color:{{ $pendingApprovalMessages > 0 ? '#c62828' : 'var(--gl-blue)' }};">{{ number_format($pendingApprovalMessages) }} ›</span></a>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_outstanding_support_tickets') }}</span><span>{{ number_format($outstandingSupportTickets) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_outstanding_survey_responses') }}</span><span>{{ number_format($outstandingSurveyResponses) }}</span></div>
                    <div class="ed-row"><span>{{ __('cbe_exec.row_vendor_marketplace') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
                </div>
            </div>
        </div>

        {{-- Box 7: Executive Analytics Dashboard — 5-row x 4-col KPI table
        (Last MTD / MTD / YTD / LYTD), pulling from the existing P&L +
        Balance Sheet logic (CbeAccountingService, master spec Section 57).
        FIXED 28 Aug 2026 — per Chris: "box 7 should have 5 rows" — all 5
        rows were always in the data/loop, but this box's old flex:1.1
        share (barely more than a 3-row box like Box 1-6) wasn't enough
        room for a header row PLUS 5 data rows, and overflow:hidden was
        silently clipping rows 3-5 off the bottom instead of showing
        them. Box now gets a much bigger flex share, and each row is
        flex:1 (not fixed padding) so the 5 rows always share whatever
        height the box has and can never be clipped or need a scrollbar,
        however small the window gets.
        UPDATED 28 Aug 2026 — per Chris: "make more space for box 7 in
        Entity KPI" — bumped again (1.6 → 2.1) now that the standalone
        Top Donors box below it is gone and that space is free. --}}
        <div style="flex:1.8; min-height:0;">
            <div class="ed-box" style="height:100%;">
                <div class="ed-box-title">{{ __('cbe_exec.box_executive_analytics') }}</div>
                <div style="flex:1; min-height:0; display:flex; flex-direction:column;">
                    <div style="flex-shrink:0; display:flex; font-size:7.5px; font-weight:700; color:#6b7280; border-bottom:1px solid #eef2f7; padding-bottom:3px;">
                        <span style="flex:1.6;"></span>
                        <span style="flex:1; text-align:right;">{{ __('cbe_exec.col_last_mtd') }}</span>
                        <span style="flex:1; text-align:right;">{{ __('cbe_exec.col_mtd') }}</span>
                        <span style="flex:1; text-align:right;">{{ __('cbe_exec.col_ytd') }}</span>
                        <span style="flex:1; text-align:right;">{{ __('cbe_exec.col_lytd') }}</span>
                    </div>
                    @foreach($executiveAnalytics as $row)
                    <div style="flex:1; min-height:0; display:flex; font-size:8px; border-bottom:1px solid #f5f7fa; align-items:center;">
                        <span style="flex:1.6; color:#374151; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ __('cbe_exec.'.$row['label_key']) }}</span>
                        <span style="flex:1; text-align:right; color:#0d3c72; font-weight:700;">RM {{ number_format($row['last_mtd'], 2) }}</span>
                        <span style="flex:1; text-align:right; color:#0d3c72; font-weight:700;">RM {{ number_format($row['mtd'], 2) }}</span>
                        <span style="flex:1; text-align:right; color:#0d3c72; font-weight:700;">RM {{ number_format($row['ytd'], 2) }}</span>
                        <span style="flex:1; text-align:right; color:#0d3c72; font-weight:700;">RM {{ number_format($row['lytd'], 2) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        {{-- REMOVED 28 Aug 2026 — per Chris: "this row put inside Total
        Donor box 4 as drill down screen, remove from Entity KPI screen.
        make more space for box 7 in Entity KPI" — Top Donors/Sponsors
        used to be its own standalone box here; it now lives on the
        Donors screen Box 4's "Total Donors" row already drills into
        (admin/cbe-kpi/donors/index.blade.php), and Box 7 above gets the
        flex share this box used to take (1.6 → 2.1). --}}

</div>

    {{-- REBUILT 26 Aug 2026, 12th pass — per Chris: "the profile screen
    can you proper align to display currentlyly is too close to bottom
    page and add telephone number will pust the screen down and involve
    sroll bar again... put a new folder tap. the folder tap sequence is
    suppose to have profile, add contact tel no and lastly KPI." Split
    the old single cramped Profile tab into 2 full-height tabs sharing
    ONE form (so nothing gets wiped when saving from either tab) —
    Profile (name/address/city/postcode/state/ref) gets its own full box,
    and Contact & Tel No (contact persons + phone list) gets its own
    full box, so adding phone rows no longer competes for space with the
    address fields and needs no inner scrollbar. --}}
    @if($showTabs)
    <div id="cbe-tab-profile-group" style="display:flex; flex:1; min-height:0;">
        <form method="POST" action="{{ route('admin.cbe-kpi.update-profile') }}" style="height:100%; display:flex; flex-direction:column; min-height:0;">
            @csrf
            <input type="hidden" name="node" value="{{ $profileNode->node_id }}">

            @php
                $pnZh = app()->getLocale() === 'zh' && $profileNode->node_name_zh;
                $pnPrimary = $pnZh ? $profileNode->node_name_zh : $profileNode->node_name;
                $pnSecondary = $pnZh ? $profileNode->node_name : $profileNode->node_name_zh;
            @endphp

            <div id="cbe-tab-profile" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                <div class="ed-box" style="height:100%; display:flex; flex-direction:column;">
                    <div class="ed-box-title">{{ $pnPrimary }}{{ $pnSecondary ? ' ('.$pnSecondary.')' : '' }}</div>
                    @if($profileParentNode || $profileNode->external_reference_no || ! $profileIsTopLevel)
                    <div style="font-size:8.5px; color:#94A3B8; margin:-4px 0 8px;">
                        {{-- CHANGED 12 Sep 2026 — per Chris: never hardcode
                        "State" as the parent label — a parent can be any
                        level (Branch, City, HQ, Club...) depending on the
                        community; use the parent's own real level name.
                        Also shows plainly when there is no parent yet,
                        and whether that's by admin choice (link_locked)
                        or simply because a match doesn't exist yet — see
                        the checkbox just below for the control itself. --}}
                        @if($profileParentNode)
                            {{ $profileParentLevelName }}: {{ $profileParentNode->node_name }}
                        @elseif($profileNode->link_locked)
                            {{ __('admin_cbe_kpi.profile_standalone_by_choice') }}
                        @elseif(! $profileIsTopLevel)
                            {{ __('admin_cbe_kpi.profile_standalone_unlinked') }}
                        @endif
                        @if(($profileParentNode || (! $profileIsTopLevel && ! $profileParentNode)) && $profileNode->external_reference_no)&nbsp;·&nbsp;@endif
                        @if($profileNode->external_reference_no){{ __('admin_cbe_kpi.profile_ref_no') }}: {{ $profileNode->external_reference_no }}@endif
                    </div>
                    @endif
                    {{-- NEW 12 Sep 2026 — per Chris: "sometime the Temple A
                    management decision does not want to link and stay by
                    itself standalone." Not shown for a community's own
                    top level (HQ etc.), which has no parent concept. --}}
                    @if(! $profileIsTopLevel)
                    <label style="display:flex; align-items:center; gap:6px; font-size:8.5px; color:#374151; cursor:pointer; margin:-2px 0 8px;">
                        <input type="checkbox" name="stay_standalone" value="1" @checked($profileNode->link_locked) style="width:12px; height:12px; margin:0;">
                        {{ __('admin_cbe_kpi.profile_stay_standalone') }}
                    </label>
                    @endif
                    <div style="display:flex; gap:14px;">
                        <div class="cbe-profile-field" style="flex:3;">
                            <label>{{ __('admin_cbe_kpi.profile_address') }}</label>
                            <textarea name="address" rows="3">{{ old('address', $profileNode->address) }}</textarea>
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:110px;">
                            <label>{{ __('admin_cbe_kpi.profile_city') }}</label>
                            <input type="text" name="city" value="{{ old('city', $profileNode->city) }}">
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:90px;">
                            <label>{{ __('admin_cbe_kpi.profile_postcode') }}</label>
                            <input type="text" name="postcode" value="{{ old('postcode', $profileNode->postcode) }}">
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:auto;">
                        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('admin_cbe_kpi.profile_save') }}</button>
                        @if(session('cbe_profile_saved'))
                        <span style="font-size:8.5px; color:#2e7d32; font-weight:700;">✓ {{ __('admin_cbe_kpi.profile_saved') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div id="cbe-tab-contact" style="display:none; flex-direction:column; flex:1; min-height:0;">
                <div class="ed-box" style="height:100%; display:flex; flex-direction:column; overflow-y:auto;">
                    <div class="ed-box-title">{{ __('admin_cbe_kpi.tab_contact') }}</div>
                    @php
                        // NEW 26 Aug 2026, 14th pass — per Chris: "i dont
                        // know about this temple whether it has 2nd
                        // contact number... show contact 1, contact 2
                        // name and contact number if any" — pair each
                        // named contact directly with ITS OWN phone
                        // number instead of an unlabeled flat phone list,
                        // so it's obvious which number is missing vs.
                        // which belongs to which person. The import
                        // ordered numbers Telephone-1-cell-first, so slot
                        // 0 = Contact 1's number, slot 1 = Contact 2's —
                        // anything beyond that is "Additional".
                        $phone1 = $profilePhones->slice(0, 1)->values()->first();
                        $phone2 = $profilePhones->slice(1, 1)->values()->first();
                        $extraPhones = $profilePhones->slice(2)->values();
                    @endphp
                    <div style="display:flex; gap:14px; flex-wrap:wrap;">
                        <div class="cbe-profile-field" style="flex:1; min-width:160px;">
                            <label>{{ __('admin_cbe_kpi.profile_contact_person_1') }}</label>
                            <input type="text" name="contact_person_1" value="{{ old('contact_person_1', $profileNode->contact_person_1) }}">
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:140px;">
                            <label>{{ __('admin_cbe_kpi.profile_phone_number') }}</label>
                            <input type="hidden" name="phones[0][id]" value="{{ $phone1->phone_id ?? '' }}">
                            <input type="hidden" name="phones[0][note]" value="{{ $phone1->contact_note ?? '' }}">
                            <input type="text" name="phones[0][number]" value="{{ old('phones.0.number', $phone1->phone_number ?? '') }}" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}">
                        </div>
                    </div>
                    <div style="display:flex; gap:14px; flex-wrap:wrap;">
                        <div class="cbe-profile-field" style="flex:1; min-width:160px;">
                            <label>{{ __('admin_cbe_kpi.profile_contact_person_2') }}</label>
                            <input type="text" name="contact_person_2" value="{{ old('contact_person_2', $profileNode->contact_person_2) }}">
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:140px;">
                            <label>{{ __('admin_cbe_kpi.profile_phone_number') }}</label>
                            <input type="hidden" name="phones[1][id]" value="{{ $phone2->phone_id ?? '' }}">
                            <input type="hidden" name="phones[1][note]" value="{{ $phone2->contact_note ?? '' }}">
                            <input type="text" name="phones[1][number]" value="{{ old('phones.1.number', $phone2->phone_number ?? '') }}" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}">
                        </div>
                    </div>
                    <div class="cbe-profile-field">
                        <label>{{ __('admin_cbe_kpi.profile_additional_phones') }}</label>
                        <div id="cbe-phone-rows">
                            @foreach($extraPhones as $ph)
                            <div class="cbe-phone-row">
                                <input type="hidden" name="phones[{{ $loop->index + 2 }}][id]" value="{{ $ph->phone_id }}">
                                <input type="text" name="phones[{{ $loop->index + 2 }}][number]" value="{{ $ph->phone_number }}" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}" style="flex:1;">
                                <input type="text" name="phones[{{ $loop->index + 2 }}][note]" value="{{ $ph->contact_note }}" placeholder="{{ __('admin_cbe_kpi.profile_note_placeholder') }}" style="flex:1;">
                                <button type="button" class="cbe-phone-remove-btn" onclick="this.parentElement.remove()">✕</button>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" class="cbe-phone-add-btn" onclick="cbeAddPhoneRow()">{{ __('admin_cbe_kpi.profile_add_phone') }}</button>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:8px;">
                        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('admin_cbe_kpi.profile_save') }}</button>
                        @if(session('cbe_profile_saved'))
                        <span style="font-size:8.5px; color:#2e7d32; font-weight:700;">✓ {{ __('admin_cbe_kpi.profile_saved') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
    @endif
    @endif

    {{-- NEW 26 Aug 2026, 8th pass — per Chris: "there is no prev button
    back to previous screen from temple drill down... if i dont have any
    search anymore i should have back to dashboard on the left corner
    bottom screen fill with blue color." Always available, bottom-left,
    solid blue, whenever any selection is in view — clears every filter
    and returns to the blank selection screen. --}}
    @if($hasSelection)
    <div style="flex-shrink:0;">
        <a href="{{ route('admin.cbe-kpi') }}" class="cbe-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('admin_cbe_kpi.back_to_dashboard_btn') }}</a>
    </div>
    @endif
</div>

<script>
(function(){
    // REBUILT 26 Aug 2026, 7th pass — per Chris: "the first thing they
    // need to select is which cbe group... then which state which
    // branch and which temple." 4 always-visible boxes — CBE Group /
    // State / Branch / Temple — each one calling its own explicit
    // search "box" type on the backend (group/state/branch/temple)
    // rather than a generic level position, because Branch is no longer
    // a real hierarchy level: Tao's real structure is only HQ -> State
    // -> Temple (3 levels), so "Branch" is a district grouping of the
    // Temple level's own city values (see CbeDistrictService on the
    // backend) — e.g. "Klang" combines 5 real town names into one pick.
    // Picking Branch narrows the Temple box's search to just that
    // district's temples; every box still also works completely on its
    // own (type a Temple name directly with nothing else picked).
    var searchLevelUrl = '{{ route("admin.cbe-kpi.search-level") }}';
    var goUrl = '{{ route("admin.cbe-kpi") }}';
    var noMatchesTxt = @json(__('dashboard.admin_no_matches'));
    var levelLabels = [
        @json(__('admin_cbe_kpi.filter_level1')),
        @json(__('admin_cbe_kpi.filter_level2')),
        @json(__('admin_cbe_kpi.filter_level3')),
        @json(__('admin_cbe_kpi.filter_level4'))
    ];
    var allLabel = @json(__('admin_cbe_kpi.filter_all'));
    var picks = [null, null, null, null]; // {nodeId,label} per box; nodeId 'ALL' = explicit All, 'district:<name>' = a Branch pick

    // A real, usable node id for narrowing — never 'ALL' and never a
    // synthetic 'district:' marker (a district isn't a real node).
    function realNode(idx){
        var p = picks[idx];
        if (!p || p.nodeId === 'ALL') return '';
        if (p.nodeId.indexOf('district:') === 0) return '';
        return p.nodeId;
    }

    function districtAt(idx){
        var p = picks[idx];
        if (!p || p.nodeId === 'ALL') return '';
        if (p.nodeId.indexOf('district:') === 0) return p.nodeId.slice('district:'.length);
        return '';
    }

    function argsForBox(idx){
        if (idx === 0) return {box: 'group', parent: '', district: ''};
        if (idx === 1) return {box: 'state', parent: realNode(0), district: ''};
        if (idx === 2) return {box: 'branch', parent: realNode(1) || realNode(0), district: ''};
        // CHANGED 27 Sep 2026 — a real Branch pick (e.g. Cawangan Klang)
        // narrows the Entity box to that branch + its affiliated entities.
        return {box: 'temple', parent: realNode(2) || realNode(1) || realNode(0), district: districtAt(2)};
    }

    function cbeSearch(args, q, cb){
        var url = searchLevelUrl+'?box='+encodeURIComponent(args.box)+'&q='+encodeURIComponent(q)+'&_='+Date.now();
        if (args.parent) url += '&parent='+encodeURIComponent(args.parent);
        if (args.district) url += '&district='+encodeURIComponent(args.district);
        fetch(url, {cache:'no-store'})
            .then(function(r){ return r.json(); })
            .then(function(list){ cb(list.map(function(it){ return {nodeId:it.node_id, label:it.label}; })); })
            .catch(function(){ cb([]); });
    }

    function buildBox(idx){
        var wrap = document.createElement('div');
        wrap.style.cssText = 'position:relative;flex:1;min-width:0;';
        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = levelLabels[idx];
        input.autocomplete = 'off';
        input.className = 'cbe-level-input';
        var box = document.createElement('div');
        // FIXED 26 Aug 2026, 7th pass — per Chris: "your drop down pick
        // list truncated to the left" — the leftmost box's suggestion
        // list was anchored to its own right edge, so it extended left
        // past the input and got hidden under the sidebar. Anchored
        // from the left instead (right for the last box, so it doesn't
        // run past the Go button), with a fixed width and wrapping text
        // instead of one unbroken line, so long names never get cut off.
        var anchorSide = (idx === 3) ? 'right:0;' : 'left:0;';
        box.style.cssText = 'display:none;position:absolute;top:100%;'+anchorSide+'margin-top:2px;background:#fff;border:1px solid #b2ebf2;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:220px;overflow-y:auto;z-index:60;width:300px;';
        var timer = null;

        function renderResults(items){
            var full = [{nodeId:'ALL', label:allLabel}].concat(items);
            box.innerHTML = full.map(function(it, i){
                var isAll = it.nodeId === 'ALL';
                return '<div class="cbe-suggestion" data-idx="'+i+'" style="padding:6px 8px;font-size:8px;line-height:1.35;color:'+(isAll ? '#0d3c72' : 'var(--gl-blue)')+';font-weight:'+(isAll ? '700' : '600')+';cursor:pointer;border-bottom:1px solid #eee;white-space:normal;word-break:break-word;'+(isAll ? 'background:#eef4fc;' : '')+'">'+it.label+'</div>';
            }).join('');
            box.style.display = 'block';
            Array.prototype.forEach.call(box.querySelectorAll('.cbe-suggestion'), function(el){
                el.addEventListener('click', function(){
                    var it = full[parseInt(el.getAttribute('data-idx'), 10)];
                    input.value = it.label;
                    box.style.display = 'none';
                    picks[idx] = it;
                    // a pick (real, "All", or a Branch/district) clears every box to its right
                    for(var j = idx + 1; j < 4; j++){
                        picks[j] = null;
                        if(inputs[j]) inputs[j].value = '';
                    }
                });
            });
        }

        function doSearch(q){
            cbeSearch(argsForBox(idx), q, renderResults);
        }

        input.addEventListener('input', function(){
            var q = input.value.trim();
            if(picks[idx]){ picks[idx] = null; }
            if(timer) clearTimeout(timer);
            timer = setTimeout(function(){ doSearch(q); }, 250);
        });

        // click/focus opens the list straight away (browse mode), even
        // with nothing typed yet
        function openBrowse(){
            if(timer) clearTimeout(timer);
            doSearch(input.value.trim());
        }
        input.addEventListener('focus', openBrowse);
        input.addEventListener('click', openBrowse);

        document.addEventListener('click', function(e){
            if(!wrap.contains(e.target)) box.style.display = 'none';
        });

        wrap.appendChild(input);
        wrap.appendChild(box);
        document.getElementById('cbe-level-selects').appendChild(wrap);
        return input;
    }

    var inputs = [];
    for(var i = 0; i < 4; i++){ inputs.push(buildBox(i)); }

    window.onCbeGo = function(){
        // Temple (box 3) real pick — most specific, goes straight to that node
        if (realNode(3)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(3));
            return;
        }
        // Branch (box 2) — a district grouping, not a single node
        var district = districtAt(2);
        if (district) {
            var scopeParent = realNode(1) || realNode(0);
            var url = goUrl+'?district='+encodeURIComponent(district);
            if (scopeParent) url += '&parent='+encodeURIComponent(scopeParent);
            window.location = url;
            return;
        }
        if (realNode(2)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(2));
            return;
        }
        if (realNode(1)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(1));
            return;
        }
        if (realNode(0)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(0));
            return;
        }
        // nothing picked (or only "All" picks anywhere) — a deliberate combined view
        window.location = goUrl+'?scope=all';
    };

    // NEW 26 Aug 2026, 8th pass — Profile/KPI tab switcher
    // UPDATED 26 Aug 2026, 12th pass — per Chris: "put a new folder tap.
    // the folder tap sequence is suppose to have profile, add contact
    // tel no and lastly KPI." Now 3 tabs — Profile/Contact & Tel No share
    // one form (#cbe-tab-profile-group) so nothing gets wiped when
    // saving from either sub-tab, while KPI stays a separate sibling.
    window.cbeSwitchTab = function(tab){
        var kpiPanel = document.getElementById('cbe-tab-kpi');
        var formGroup = document.getElementById('cbe-tab-profile-group');
        var profileSub = document.getElementById('cbe-tab-profile');
        var contactSub = document.getElementById('cbe-tab-contact');
        var btns = document.querySelectorAll('.cbe-tab-btn');
        Array.prototype.forEach.call(btns, function(b){
            b.classList.toggle('active', b.getAttribute('data-tab') === tab);
        });
        if (kpiPanel) kpiPanel.style.display = (tab === 'kpi') ? 'flex' : 'none';
        if (formGroup) formGroup.style.display = (tab === 'kpi') ? 'none' : 'flex';
        if (profileSub) profileSub.style.display = (tab === 'profile') ? 'flex' : 'none';
        if (contactSub) contactSub.style.display = (tab === 'contact') ? 'flex' : 'none';
    };

    // NEW 26 Aug 2026, 10th pass — per Chris: "i have so many contact
    // number in my excel file, you didnt insert? contact 1, contact 2
    // with name?" Lets the admin add extra phone rows beyond the ones
    // already imported from Excel (variable count per temple).
    // UPDATED 26 Aug 2026, 14th pass — indices 0 and 1 are always
    // reserved for Contact Person 1's and 2's own phone fields now, so
    // new rows added here must start at 2 or beyond.
    var cbePhoneIdx = {{ 2 + (isset($extraPhones) ? $extraPhones->count() : 0) }};
    window.cbeAddPhoneRow = function(){
        var wrap = document.getElementById('cbe-phone-rows');
        var div = document.createElement('div');
        div.className = 'cbe-phone-row';
        div.innerHTML = '<input type="hidden" name="phones['+cbePhoneIdx+'][id]" value="">'
            + '<input type="text" name="phones['+cbePhoneIdx+'][number]" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}" style="flex:1;">'
            + '<input type="text" name="phones['+cbePhoneIdx+'][note]" placeholder="{{ __('admin_cbe_kpi.profile_note_placeholder') }}" style="flex:1;">'
            + '<button type="button" class="cbe-phone-remove-btn" onclick="this.parentElement.remove()">✕</button>';
        wrap.appendChild(div);
        cbePhoneIdx++;
    };
})();
</script>
@endsection
