@extends('layouts.dashboard')

@section('page-title', __('renewal_forecast.page_title'))

{{-- NEW 19 Jul 2026 — per Chris: every agent needs to see their yearly
     Projected Sales and Earning Income Forecast by month, with drill-down to individual
     transactions, so they're aware of upcoming renewal business and
     earning income well ahead of time. Shared across every role —
     scoped via DataScopeService in the controller (Introducer: own
     customers; TL: self + own Introducers; GL: whole group; Admin:
     everything).

     REBUILT 22 Jul 2026 (v2) — contributor ranking drill-down, Vendor/
     Product filters, Prev/Next moved to the bottom, renamed off
     "insurance renewal" wording — see prior comments in git history.

     REBUILT AGAIN 22 Jul 2026 (v3) — per Chris: "no screen scroll,
     remember please read my instruction in my setting". With filters +
     3 summary cards + chart + 12-month table + ranking/detail table +
     bottom nav all stacked in one column, this page was taller than
     one viewport and had to scroll — not allowed. Converted the chart
     and the monthly/detail tables into TWO TABS ("Overview" and
     "Monthly Breakdown & Detail") below the always-visible filters/
     cards, so only one heavy section is on screen at a time. The
     whole page is now a fixed-height flex column with
     overflow:hidden — it physically cannot scroll; each tab's own
     content area is bounded and paginated instead. --}}

@section('content')
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; overflow:hidden;">

    {{-- NEW 23 Jul 2026 (v11) — per Chris: "delete duplicate display
         Prev and Next" — Dashboard-back and Year Prev/Next used to sit
         in their OWN row at the very bottom, directly under whichever
         tab's own Prev/Next pagination row (Contributor Ranking, or
         Sales Detail) — two Prev/Next-labelled rows stacked on top of
         each other read as a duplicate and cost an entire extra row
         of the vertical space every tab is fighting for. Folded into
         this top row instead (which had room to spare); the bottom of
         the screen now shows exactly ONE Prev/Next row — whichever
         tab's own pagination applies, if any. --}}
    {{-- FIXED 8 Aug 2026 per Chris: strict rule — the only Prev/Next
         controls allowed anywhere on a screen are the one blue pair at
         the bottom. This row used to have a "← Dashboard" back-link AND
         a second Prev/Next-styled year switcher (◀ 2025 / 2027 ▶) up
         top — both removed. Year switching is now a plain dropdown
         (same style as the Vendor/Product filters below), which isn't a
         back/prev/next control, just a filter — changing it reloads
         straight to that year. Leave via the sidebar, same as every
         other screen. --}}
    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; flex-wrap:wrap; gap:6px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <div style="font-size:9.5px; color:#546E7A;">{{ __('renewal_forecast.viewing_label') }} <span style="font-weight:700; color:#1565C0;">{{ $scopeLabel }}</span></div>
            <form method="GET" action="{{ route('renewal-forecast.index') }}" style="display:flex; align-items:center; gap:4px;">
                @foreach(request()->except(['year','page','rfPage','month']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <label for="rfYearSelect" style="font-size:9px; color:#546E7A;">{{ __('renewal_forecast.year_label') }}</label>
                <select name="year" id="rfYearSelect" onchange="this.form.submit();" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:9.5px; background:#fff;">
                    @for($yy = $year - 2; $yy <= $year + 3; $yy++)
                    <option value="{{ $yy }}" {{ $yy === $year ? 'selected' : '' }}>{{ $yy }}</option>
                    @endfor
                </select>
            </form>
        </div>
    </div>

    {{-- ALL SELECTION CRITERIA IN ONE ROW — per Chris: agent cascade
         (only the levels below the viewer's own role) plus Vendor and
         Product, since this covers every line of business, not just
         insurance. Explicit Search button, not just auto-submit. --}}
    {{-- NEW 23 Jul 2026 (v7) — per Chris: "where to click Go to search"
         — the Search button was INSIDE the same overflow-x:auto strip
         as the filter boxes, so once enough filters were on screen
         (typeahead boxes are wider than the old <select>s) it could
         scroll off the right edge, out of sight. Search + Clear are
         now separate, flex-shrink:0 siblings OUTSIDE that scrolling
         strip — always visible, no matter how many filter fields there
         are. Only the filter boxes themselves scroll horizontally if
         they ever don't all fit (rare — they're narrower now too). --}}
    <form method="GET" action="{{ route('renewal-forecast.index') }}" style="flex-shrink:0; margin-bottom:4px; display:flex; gap:6px; align-items:center;" autocomplete="off">
        <input type="hidden" name="year" value="{{ $year }}">
        {{-- NEW 22 Jul 2026 (v4) — per Chris: nothing computes until
             Search is actually clicked, even with every dropdown left
             blank (a deliberate "show me the overall default" request
             is fine — an automatic one on page load is not). --}}
        <input type="hidden" name="search" value="1">

        {{-- NEW 23 Jul 2026 (v9) — per Chris: "search button put in same
             row can reduce text fonts" — every filter field shrunk
             again (narrower boxes, 8.5px font, tighter padding) so the
             whole row — including Search — comfortably fits with room
             to spare, and overflow-x switched from auto to visible so
             there's no more empty scrollbar track sitting under the
             filters when nothing actually needs to scroll. --}}
        <div style="display:flex; gap:4px; flex-wrap:nowrap; align-items:center; overflow-x:visible; flex:1 1 auto; min-width:0;">

            {{-- NEW 23 Jul 2026 (v6) — per Chris: "10000 Group Leader
                 600000 Team Leader and 1 million introducer... you
                 going to display 10000 group records one by one to
                 pick?" — plain <select> dropdowns replaced with
                 typeahead search boxes. Each fetches at most 20 name/
                 code matches as you type (see
                 RenewalForecastController::agentTypeahead()); picking
                 one clears anything already chosen below it. Left
                 blank = "All" for that level, exactly as before. --}}
            @if($agent->role === 'ADMIN')
            <div style="position:relative; flex-shrink:0;">
                <input type="text" id="rfGlBox" autocomplete="off" placeholder="{{ __('renewal_forecast.group_search_placeholder') }}" value="{{ $selectedGL->full_name ?? '' }}" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:8.5px; background:#fff; width:95px; box-sizing:border-box;">
                <input type="hidden" name="gl_id" id="rfGlId" value="{{ $glId }}">
                <div id="rfGlList" class="rfTaList" style="display:none; position:fixed; min-width:190px; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; z-index:9999; max-height:200px; overflow-y:auto; box-shadow:0 4px 10px rgba(0,0,0,0.15);"></div>
            </div>
            @endif

            @if(in_array($agent->role, ['ADMIN', 'GROUP_LEADER'], true))
            <div style="position:relative; flex-shrink:0;">
                <input type="text" id="rfTlBox" autocomplete="off" placeholder="{{ __('renewal_forecast.role_search_placeholder', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}" value="{{ $selectedTL->full_name ?? '' }}" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:8.5px; background:#fff; width:95px; box-sizing:border-box;">
                <input type="hidden" name="tl_id" id="rfTlId" value="{{ $tlId }}">
                <div id="rfTlList" class="rfTaList" style="display:none; position:fixed; min-width:190px; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; z-index:9999; max-height:200px; overflow-y:auto; box-shadow:0 4px 10px rgba(0,0,0,0.15);"></div>
            </div>
            @endif

            <div style="position:relative; flex-shrink:0;">
                <input type="text" id="rfIntroBox" autocomplete="off" placeholder="{{ $agent->role === 'INTRODUCER' ? __('renewal_forecast.my_downline_placeholder') : __('renewal_forecast.role_search_placeholder', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}" value="{{ $selectedIntro->full_name ?? '' }}" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:8.5px; background:#fff; width:110px; box-sizing:border-box;">
                <input type="hidden" name="introducer_id" id="rfIntroId" value="{{ $introId }}">
                <div id="rfIntroList" class="rfTaList" style="display:none; position:fixed; min-width:190px; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; z-index:9999; max-height:200px; overflow-y:auto; box-shadow:0 4px 10px rgba(0,0,0,0.15);"></div>
            </div>

            {{-- Vendor + Product, per Chris: this covers every vendor/
                 product in the system, not just insurance. --}}
            <select name="vendor_id" onchange="this.form.product_id.value='';" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:8.5px; background:#fff; min-width:85px; flex-shrink:0;">
                <option value="">{{ __('renewal_forecast.vendor_option_placeholder') }}</option>
                @foreach($vendorOptions as $v)
                <option value="{{ $v->vendor_id }}" {{ $vendorId === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                @endforeach
            </select>
            <select name="product_id" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:8.5px; background:#fff; min-width:85px; flex-shrink:0;">
                <option value="">{{ __('renewal_forecast.product_option_placeholder') }}</option>
                @foreach($productOptions as $pr)
                <option value="{{ $pr->product_id }}" {{ $productId === $pr->product_id ? 'selected' : '' }}>{{ $pr->product_name }}</option>
                @endforeach
            </select>

        </div>

        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:4px 12px; font-size:8.5px; font-weight:600; cursor:pointer; flex-shrink:0;">{{ __('gl.search_button') }}</button>

        @if($glId || $tlId || $introId || $vendorId || $productId || $month)
        <a href="{{ route('renewal-forecast.index', ['year' => $year]) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:5px; padding:4px 10px; font-size:8.5px; font-weight:600; display:flex; align-items:center; flex-shrink:0;">{{ __('dashboard.clear_word') }}</a>
        @endif
    </form>

    {{-- NEW 23 Jul 2026 (v5) — per Chris: "before execute my search why
         you show figure? what if million of potential forecast" —
         nothing below this point renders, and nothing above computed
         a single row, unless the agent has actually clicked Search.
         Landing on this page with no search performed must cost the
         database nothing heavier than the (cheap, bounded) filter
         dropdown option lists. --}}
    @if($searched)

    {{-- SUMMARY — NEW 23 Jul 2026 (v8) — per Chris: this dashboard
         covers every line of business, not just insurance, so "Renewals
         Due" / "Premium" wording is gone. Cards shrunk (smaller
         padding + font) per his "reduce the size" request. --}}
    <div style="flex-shrink:0; display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin-bottom:3px;">
        <div style="background:#fff; border:1px solid #B2EBF2; border-radius:6px; padding:3px 6px; text-align:center;">
            <p style="font-size:7.5px; color:#546E7A; margin:0; text-transform:uppercase; letter-spacing:0.3px;">{{ __('renewal_forecast.sales_opportunities_label') }}</p>
            <p style="font-size:12px; font-weight:700; color:#1565C0; margin:0;">{{ $totalPolicies }}</p>
        </div>
        <div style="background:#fff; border:1px solid #B2EBF2; border-radius:6px; padding:3px 6px; text-align:center;">
            <p style="font-size:7.5px; color:#546E7A; margin:0; text-transform:uppercase; letter-spacing:0.3px;">{{ __('renewal_forecast.projected_sales_label') }}</p>
            <p style="font-size:12px; font-weight:700; color:#1565C0; margin:0;">RM {{ number_format($totalPremium,2) }}</p>
        </div>
        <div style="background:#fff; border:1px solid #B2EBF2; border-radius:6px; padding:3px 6px; text-align:center;">
            <p style="font-size:7.5px; color:#546E7A; margin:0; text-transform:uppercase; letter-spacing:0.3px;">{{ __('renewal_forecast.projected_earning_income_label') }}</p>
            <p style="font-size:12px; font-weight:700; color:#2e7d32; margin:0;">RM {{ number_format($totalEarning,2) }}</p>
        </div>
    </div>

    @php
        // NEW 23 Jul 2026 (v6) — per Chris: picking a GL/TL/Introducer
        // (or Vendor/Product) from the filter is just narrowing WHICH
        // opportunities you're viewing — it must still land on the
        // 12-month Overview chart first, same as the unfiltered case.
        // Only an explicit drill-down click on one specific MONTH
        // should jump to the Detail tab (contributor ranking, or the
        // customer-level list once a true leaf agent is reached).
        // Previously this also flipped to Detail the instant any
        // agent/vendor/product filter was chosen, so selecting e.g.
        // "Chris Yap" from the Group dropdown skipped straight past
        // the 12-month picture to a flat list of his TLs.
        //
        // NEW 23 Jul 2026 (v15) — an explicit ?tab= in the URL (set via
        // history.pushState when a tab is clicked — see the script
        // below) always wins over this guess, so that clicking the
        // browser/page Back button lands on the exact tab the viewer
        // was actually looking at, not just whichever tab this
        // month-based guess would otherwise pick.
        $requestedTab = request('tab');
        $defaultTab = in_array($requestedTab, ['rfOverview', 'rfByMonth', 'rfDetail'], true)
            ? $requestedTab
            : ($month ? 'rfDetail' : 'rfOverview');
    @endphp

    {{-- TABS — per Chris: no page scroll allowed, so only one heavy
         section renders at a time and the whole page always fits in
         one screen. NEW 23 Jul 2026 (v10) — per Chris: a new "By
         Month" tab added in between Overview and Contributor Ranking:
         the 12-month table used to live squeezed under the chart on
         the Overview tab; it now has its own tab, freeing Overview to
         show just the chart, taller, with a Bar/Pie/Line type switch. --}}
    <div style="flex-shrink:0; display:flex; align-items:center; gap:3px; margin:0 2px;">
        <button type="button" class="rfTabBtn" data-tab="rfOverview" style="background:{{ $defaultTab === 'rfOverview' ? '#fff' : '#F7FAFC' }}; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 14px; font-size:9.5px; font-weight:700; color:{{ $defaultTab === 'rfOverview' ? '#1565C0' : '#6b7280' }}; cursor:pointer; position:relative; top:1px;">{{ __('renewal_forecast.tab_overview') }}</button>
        <button type="button" class="rfTabBtn" data-tab="rfByMonth" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 14px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('renewal_forecast.tab_by_month') }}</button>
        <button type="button" class="rfTabBtn" data-tab="rfDetail" style="background:{{ $defaultTab === 'rfDetail' ? '#fff' : '#F7FAFC' }}; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 14px; font-size:9.5px; font-weight:700; color:{{ $defaultTab === 'rfDetail' ? '#1565C0' : '#6b7280' }}; cursor:pointer; position:relative; top:1px;">{{ $viewMode === 'ranking' ? __('renewal_forecast.tab_contributor_ranking') : ($viewMode === 'pipeline' ? __('renewal_forecast.tab_pipeline_detail') : __('renewal_forecast.tab_sales_detail')) }}</button>
    </div>

    <div style="flex:1 1 auto; min-height:0; padding-bottom:6px;">

        {{-- OVERVIEW TAB — chart only now (moved the 12-month table out
             to its own "By Month" tab, per Chris), so it can grow to
             fill the whole tab height instead of a fixed small strip.
             Radio bar above lets the viewer switch between Bar / Pie /
             Line — same underlying month/premium data, just a
             different Chart.js chart type, redrawn client-side. --}}
        <div id="rfOverview" class="rfTabPanel" style="display:{{ $defaultTab === 'rfOverview' ? 'flex' : 'none' }}; flex-direction:column; height:100%; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px; box-sizing:border-box;">
            <div style="flex-shrink:0; display:flex; align-items:center; gap:12px; margin-bottom:6px;">
                <span style="font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px;">{{ __('renewal_forecast.chart_type_label') }}</span>
                <label style="display:flex; align-items:center; gap:4px; font-size:10px; color:#374151; cursor:pointer;"><input type="radio" name="rfChartType" value="bar" checked style="cursor:pointer;"> {{ __('renewal_forecast.chart_type_bar') }}</label>
                <label style="display:flex; align-items:center; gap:4px; font-size:10px; color:#374151; cursor:pointer;"><input type="radio" name="rfChartType" value="pie" style="cursor:pointer;"> {{ __('renewal_forecast.chart_type_pie') }}</label>
                <label style="display:flex; align-items:center; gap:4px; font-size:10px; color:#374151; cursor:pointer;"><input type="radio" name="rfChartType" value="line" style="cursor:pointer;"> {{ __('renewal_forecast.chart_type_line') }}</label>
            </div>
            <div style="flex:1 1 auto; min-height:0;">
                <canvas id="forecastChart"></canvas>
            </div>
        </div>

        {{-- BY MONTH TAB — the 12-month breakdown table, moved out of
             Overview (per Chris 23 Jul 2026 v10) into its own tab so
             the chart above isn't squeezed. Clicking a month row still
             drills into that month's Detail, same as before. --}}
        <div id="rfByMonth" class="rfTabPanel" style="display:none; flex-direction:column; height:100%; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:5px 8px; box-sizing:border-box;">
            {{-- NEW 23 Jul 2026 (v12) — per Chris: "display jan feb
                 until dec in one screen" — still cut off at 22px/row.
                 Rows dropped to 17px fixed height (no vertical cell
                 padding at all, just centered line-height) and the
                 panel's own padding trimmed too, so all 12 months +
                 header definitely fit with zero scrolling. --}}
            <div style="flex:1 1 auto; min-height:0; overflow-y:auto; border:1px solid #d1d5db; border-radius:6px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <thead>
                        <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db; position:sticky; top:0;">
                            <th style="text-align:left; padding:1px 8px; font-size:8px; color:#374151;">{{ __('renewal_forecast.col_month') }}</th>
                            <th style="text-align:right; padding:1px 8px; font-size:8px; color:#374151;">{{ __('renewal_forecast.sales_opportunities_label') }}</th>
                            <th style="text-align:right; padding:1px 8px; font-size:8px; color:#374151;">{{ __('renewal_forecast.projected_sales_label') }}</th>
                            <th style="text-align:right; padding:1px 8px; font-size:8px; color:#374151;">{{ __('renewal_forecast.col_earning_income') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthly as $m => $data)
                        <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer; height:17px; {{ $month === $m ? 'background:#EBF5FB;' : '' }}" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background='{{ $month === $m ? '#EBF5FB' : '' }}'" onclick="window.location='{{ route('renewal-forecast.index', array_merge(request()->except(['page','rfPage']), ['month' => $m, 'year' => $year])) }}'">
                            <td style="padding:0 8px; font-weight:600; color:#1565C0;">{{ $data['label'] }} {{ $year }}</td>
                            <td style="padding:0 8px; text-align:right;">{{ $data['count'] }}</td>
                            <td style="padding:0 8px; text-align:right;">RM {{ number_format($data['premium'],2) }}</td>
                            <td style="padding:0 8px; text-align:right; color:#2e7d32;">RM {{ number_format($data['earning_income'],2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{-- NEW 23 Jul 2026 (v16) — per Chris: every screen must
                 have the same Prev/Next navigation mechanism, no
                 exceptions. This tab never paginated (all 12 months
                 always fit), so Prev/Next here is pure screen-to-screen
                 navigation: Prev walks back to the previous screen
                 (Search results), Next has nowhere further to go from
                 here so it just stays on this same screen. --}}
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:5px;">
                <button type="button" onclick="history.back();" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('network.prev') }}</button>
                <span style="font-size:9px; color:#6b7280;">{{ __('renewal_forecast.all_12_months_word') }}</span>
                <a href="{{ request()->fullUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
            </div>
        </div>

        {{-- DETAIL TAB — contributor ranking (whole-team scope) or
             individual transaction list (leaf), whichever applies. --}}
        <div id="rfDetail" class="rfTabPanel" style="display:{{ $defaultTab === 'rfDetail' ? 'flex' : 'none' }}; flex-direction:column; height:100%; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:5px 8px; box-sizing:border-box;">

            @if($viewMode === 'ranking')
            {{-- CONTRIBUTOR RANKING — per Chris: "who contribute the
                 amount in amount sequence, highest to lowest". One row
                 per Group Leader/Team Leader/Introducer directly under
                 the current scope, sortable. Click the name to drill
                 one level deeper into the org structure; click the
                 Pipeline count to jump straight to the customer/
                 opportunity-type list for that person's WHOLE subtree
                 without drilling level by level (per Chris 23 Jul
                 2026 v8 — Agent Code column removed, no longer useful
                 here). --}}
            <div style="flex-shrink:0; font-size:9px; color:#546E7A; margin-bottom:2px;">{{ __('renewal_forecast.click_name_drill_down_note') }}</div>
            {{-- NEW 23 Jul 2026 (v12) — per Chris: "MUST SHOW 10 ROW IN
                 ONE SCREEN" — rows tightened to 17px (no vertical cell
                 padding), matching the By Month tab's fix, plus a new
                 leading "#" row-number column. This table paginates
                 10/page with a Prev/Next footer below, same treatment
                 already given to every other list on this site that
                 could ever have more rows than fit (Team Leaders,
                 Introducers, etc.) — no more relying on an internal
                 scrollbar. --}}
            <div style="flex:1 1 auto; min-height:0; overflow-y:auto; border:1px solid #d1d5db; border-radius:8px;">
                <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:9px;">
                    <colgroup>
                        <col style="width:5%;">
                        <col style="width:26%;">
                        <col style="width:16%;">
                        <col style="width:20%;">
                        <col style="width:18%;">
                        <col style="width:15%;">
                    </colgroup>
                    <thead>
                        <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db; position:sticky; top:0;">
                            <th style="text-align:left; padding:2px 4px; font-size:8px; color:#374151;">{{ __('renewal_forecast.col_hash') }}</th>
                            <th style="text-align:left; padding:2px 8px; font-size:8px; color:#374151;">{{ __('renewal_forecast.col_agent') }}</th>
                            <th style="text-align:right; padding:2px 8px; font-size:8px; color:#374151;">{{ __('renewal_forecast.col_opportunities') }}</th>
                            <th style="text-align:right; padding:2px 8px; font-size:8px; color:#374151;">
                                <a href="{{ route('renewal-forecast.index', array_merge(request()->except(['page','rfPage']), ['sort' => 'premium', 'dir' => ($sort === 'premium' && $dir === 'desc') ? 'asc' : 'desc'])) }}" style="color:#374151; text-decoration:none;">{{ __('renewal_forecast.projected_sales_label') }} {{ $sort === 'premium' ? ($dir === 'desc' ? '▼' : '▲') : '' }}</a>
                            </th>
                            <th style="text-align:right; padding:2px 8px; font-size:8px; color:#374151;">
                                <a href="{{ route('renewal-forecast.index', array_merge(request()->except(['page','rfPage']), ['sort' => 'earning', 'dir' => ($sort === 'earning' && $dir === 'desc') ? 'asc' : 'desc'])) }}" style="color:#374151; text-decoration:none;">{{ __('renewal_forecast.col_earning_income') }} {{ $sort === 'earning' ? ($dir === 'desc' ? '▼' : '▲') : '' }}</a>
                            </th>
                            <th style="text-align:right; padding:2px 8px; font-size:8px; color:#374151;">{{ __('renewal_forecast.col_pipeline') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rankedChildren as $i => $child)
                        <tr style="border-bottom:1px solid #f3f4f6; height:17px; {{ ($child->is_self ?? false) ? 'background:#FAFAFA;' : '' }}">
                            <td style="padding:0 4px; color:#9ca3af;">{{ $rankedChildren->firstItem() + $i }}</td>
                            <td style="padding:0 8px; word-break:break-word; line-height:1.2;">
                                @if($child->is_self ?? false)
                                <span style="color:#374151; font-weight:600;">{{ $child->full_name }}</span> <span style="color:#9ca3af; font-weight:400;">{{ __('renewal_forecast.personal_suffix') }}</span>
                                @else
                                {{-- NEW 23 Jul 2026 (v13) — role shown next to the
                                     name since one level can now mix Team Leaders
                                     and Introducers (e.g. a TL reporting directly
                                     to another TL), which was the exact source of
                                     confusion this fix addresses. --}}
                                <a href="{{ route('renewal-forecast.index', array_merge(request()->except(['page','rfPage','pipeline_id']), [$child->drill_param => $child->agent_id])) }}" style="color:#1565C0; text-decoration:none; font-weight:600;" title="{{ __('renewal_forecast.drill_into_title', ['name' => $child->full_name]) }}">{{ $child->full_name }} &rarr;</a>
                                <span style="color:#9ca3af; font-weight:400;">({{ \App\Services\RoleLabelService::shortLabel($child->role) }})</span>
                                @endif
                            </td>
                            <td style="padding:0 8px; text-align:right;">{{ $child->policies_due }}</td>
                            <td style="padding:0 8px; text-align:right; font-weight:700; color:#1565C0;">RM {{ number_format($child->premium,2) }}</td>
                            <td style="padding:0 8px; text-align:right; color:#2e7d32;">RM {{ number_format($child->earning_income,2) }}</td>
                            <td style="padding:0 8px; text-align:right;">
                                @if($child->policies_due > 0)
                                <a href="{{ route('renewal-forecast.index', array_merge(request()->except(['page','rfPage']), ['pipeline_id' => $child->agent_id], ($child->is_self ?? false) ? ['self_pipeline' => 1] : [])) }}" style="color:#1565C0; text-decoration:none; font-weight:700;" title="{{ __('renewal_forecast.view_opportunities_under_title', ['name' => $child->full_name]) }}">{{ __('renewal_forecast.view_arrow_link', ['count' => $child->policies_due]) }}</a>
                                @else
                                <span style="color:#9ca3af;">0</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" style="padding:20px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('renewal_forecast.no_team_members_found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:5px;">
                {{-- FIXED 23 Jul 2026 (v16) — per Chris: ONE Prev/Next
                     control per screen, no separate "Back" button. On
                     page 1 (nothing earlier to page through), Prev now
                     walks back to the previous SCREEN instead (the
                     browser's own back — see activateTab()'s history
                     tracking in the script below, which is what lets
                     this correctly retrace Detail → By Month → Search
                     → Dashboard). Once there IS an earlier page of
                     rows, Prev goes back to that page as usual. --}}
                @if($rankedChildren->onFirstPage())
                <button type="button" onclick="history.back();" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('network.prev') }}</button>
                @else
                <a href="{{ $rankedChildren->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:9px; color:#6b7280;">{{ __('renewal_forecast.page_x_of_y_paren', ['current' => $rankedChildren->currentPage(), 'last' => $rankedChildren->lastPage(), 'total' => $rankedChildren->total()]) }}</span>
                <a href="{{ $rankedChildren->hasMorePages() ? $rankedChildren->nextPageUrl() : request()->fullUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
            </div>
            @else
            {{-- LEAF / PIPELINE — either a single individual with
                 nobody further under them, or a Pipeline View jump
                 straight from the Contributor Ranking table (an
                 agent's whole subtree, per Chris 23 Jul 2026 v8):
                 either way, a customer/opportunity-level transaction
                 list, paginated. --}}
            <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                <span style="font-size:9.5px; color:#546E7A;">
                    @if($viewMode === 'pipeline' && $pipelineAgent)
                        {{ __('renewal_forecast.pipeline_for_prefix') }} <strong style="color:#374151;">{{ $pipelineAgent->full_name }}</strong>{{ request()->boolean('self_pipeline') ? __('renewal_forecast.personal_not_including_suffix') : '' }} &middot;
                    @endif
                    @if($month)
                        {{ $monthly[$month]['label'] }} {{ $year }}
                    @else
                        {{ __('renewal_forecast.all_months_word') }}
                    @endif
                </span>
                <div style="display:flex; align-items:center; gap:10px;">
                    {{-- REMOVED 8 Aug 2026 per Chris: strict rule — no
                         back link anywhere except the bottom blue Prev,
                         which already returns here (history.back()) on
                         page 1 of this table. --}}
                    @if($month)
                    <a href="{{ route('renewal-forecast.index', array_merge(request()->except(['page','rfPage','month']), ['year' => $year])) }}" style="font-size:9.5px; color:#1565C0; text-decoration:none; font-weight:600;">{{ __('renewal_forecast.show_all_months_link') }}</a>
                    @endif
                </div>
            </div>

            <div style="flex:1 1 auto; min-height:0; overflow-y:auto; border:1px solid #d1d5db; border-radius:8px;">
                <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                    <thead>
                        <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db; position:sticky; top:0;">
                            <th style="text-align:left; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('renewal_forecast.col_customer') }}</th>
                            <th style="text-align:left; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('renewal_forecast.col_reference_number') }}</th>
                            <th style="text-align:left; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('renewal_forecast.col_product_vendor') }}</th>
                            <th style="text-align:left; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('renewal_forecast.col_transaction_date') }}</th>
                            <th style="text-align:left; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('renewal_forecast.col_due_date') }}</th>
                            <th style="text-align:right; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('renewal_forecast.col_sales_amount') }}</th>
                            <th style="text-align:right; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('renewal_forecast.col_earning_income') }}</th>
                            <th style="text-align:center; padding:4px 8px; font-size:9.5px; color:#374151;">{{ __('gl.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($detailPage as $row)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 8px;">
                                <a href="{{ route($rolePrefix . '.customers.show', $row->customer_id) }}" style="color:#185FA5; text-decoration:none; font-weight:600;">{{ $row->customer_name }}</a>
                            </td>
                            <td style="padding:4px 8px;">
                                <a href="{{ route($rolePrefix . '.sales-transactions.show', $row->policy_id) }}" style="color:#185FA5; text-decoration:none; font-weight:600;">{{ $row->document_reference_number }}</a>
                            </td>
                            <td style="padding:4px 8px;">{{ $row->product_name }}<br><span style="font-size:8.5px; color:#9ca3af;">{{ $row->vendor_name }}</span></td>
                            <td style="padding:4px 8px;">{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('d M Y') }}</td>
                            <td style="padding:4px 8px;">
                                {{ \Illuminate\Support\Carbon::parse($row->coverage_end)->format('d M Y') }}
                                <br>
                                @if($row->days_until_due < 0)
                                <span style="font-size:8.5px; color:#991b1b;">{{ abs($row->days_until_due) }} {{ __('renewal_forecast.days_overdue_suffix') }}</span>
                                @else
                                <span style="font-size:8.5px; color:#9ca3af;">{{ __('renewal_forecast.in_days_suffix', ['days' => $row->days_until_due]) }}</span>
                                @endif
                            </td>
                            <td style="padding:4px 8px; text-align:right; font-weight:700; color:#1565C0;">RM {{ number_format($row->premium_amount,2) }}</td>
                            <td style="padding:4px 8px; text-align:right; color:#2e7d32;">RM {{ number_format($row->earning_income,2) }}</td>
                            <td style="padding:4px 8px; text-align:center;">
                                @php
                                    $b=['UPCOMING'=>['#e0f2fe','#075985'],'DUE'=>['#fef3c7','#92400e'],'OVERDUE'=>['#fee2e2','#991b1b']][$row->status]??['#f3f4f6','#374151'];
                                    $dueStatusLabels = ['UPCOMING'=>__('renewal_forecast.status_upcoming'),'DUE'=>__('renewal_forecast.status_due'),'OVERDUE'=>__('renewal_forecast.status_overdue')];
                                @endphp
                                <span style="background:{{ $b[0] }}; color:{{ $b[1] }}; padding:1px 8px; border-radius:20px; font-size:8.5px; font-weight:600;">{{ $dueStatusLabels[$row->status] ?? $row->status }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" style="padding:20px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('renewal_forecast.no_upcoming_renewals_found') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
                {{-- FIXED 23 Jul 2026 (v16) — per Chris: ONE Prev/Next
                     control, no separate "Back" button. On page 1
                     (nothing earlier to page through), Prev walks back
                     to the previous SCREEN instead (the browser's own
                     back — see activateTab()'s history tracking in the
                     script below). Once there IS an earlier page of
                     rows, Prev goes back to that page as usual. --}}
                @if($detailPage->onFirstPage())
                <button type="button" onclick="history.back();" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('network.prev') }}</button>
                @else
                <a href="{{ $detailPage->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:9px; color:#6b7280;">{{ __('renewal_forecast.page_x_of_y_paren', ['current' => $detailPage->currentPage(), 'last' => $detailPage->lastPage(), 'total' => $detailPage->total()]) }}</span>
                <a href="{{ $detailPage->hasMorePages() ? $detailPage->nextPageUrl() : request()->fullUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
            </div>
            @endif
        </div>

    </div>

    @else
    {{-- NOT SEARCHED YET — compact centered prompt, no query has run,
         no rows were touched. Sits inside the same flex column so it
         trivially fits with room to spare; there is nothing here that
         could ever need to scroll. --}}
    <div style="flex:1 1 auto; min-height:0; display:flex; align-items:center; justify-content:center;">
        <div style="text-align:center; color:#6b7280;">
            <div style="font-size:28px; margin-bottom:6px;">🔍</div>
            <p style="font-size:11px; font-weight:600; color:#374151; margin:0 0 4px;">{{ __('renewal_forecast.choose_criteria_search_note') }}</p>
            <p style="font-size:9.5px; color:#9ca3af; margin:0;">{{ __('renewal_forecast.leave_blank_overall_forecast_note', ['year' => $year]) }}</p>
        </div>
    </div>
    {{-- NEW 23 Jul 2026 (v16) — per Chris: "search prev is the
         dashboard screen" — this pre-search prompt is itself a
         screen, so it gets the same Prev/Next mechanism too. Prev
         walks back to whichever screen was open before this one
         (normally the Dashboard, since that's how this page is always
         reached). --}}
    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:5px;">
        <button type="button" onclick="history.back();" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('network.prev') }}</button>
        <span style="font-size:9px; color:#6b7280;">{{ __('renewal_forecast.enter_criteria_click_search_note') }}</span>
        <a href="{{ route('renewal-forecast.index', array_merge(request()->all(), ['search' => 1])) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
    </div>
    @endif

</div>

<script>
(function() {
    // NEW 23 Jul 2026 (v5) — the chart canvas only exists in the DOM
    // once a search has been performed (see the searched-gate above);
    // guard so this script doesn't throw on the pre-search prompt screen.
    var chartCanvas = document.getElementById('forecastChart');
    if (!chartCanvas) { return; }

    var monthlyLabels = @json(collect($monthly)->pluck('label'));
    var monthlyPremium = @json(collect($monthly)->pluck('premium'));
    var monthlyCount = @json(collect($monthly)->pluck('count'));

    // NEW 23 Jul 2026 (v10) — per Chris: radio bar to switch the
    // Overview chart between Bar / Pie / Line, same underlying month
    // data each time. Chart.js needs a different dataset shape per
    // type (pie wants one colour per slice; line wants a fill/line
    // style, not per-bar colouring) so the whole chart is destroyed
    // and rebuilt on each switch rather than trying to mutate one
    // config in place.
    var PIE_COLORS = ['#1565C0','#1E88E5','#00BCD4','#26A69A','#66BB6A','#FFA726','#EF5350','#AB47BC','#8D6E63','#78909C','#5C6BC0','#26C6DA'];
    var forecastChartInstance = null;

    function buildChart(type) {
        if (forecastChartInstance) { forecastChartInstance.destroy(); }

        var dataset = { label: @json(__('renewal_forecast.chart_dataset_label_js')), data: monthlyPremium };
        if (type === 'pie') {
            dataset.backgroundColor = PIE_COLORS;
        } else if (type === 'line') {
            dataset.borderColor = '#1565C0';
            dataset.backgroundColor = 'rgba(21,101,192,0.15)';
            dataset.pointBackgroundColor = '#1565C0';
            dataset.fill = true;
            dataset.tension = 0.3;
        } else {
            dataset.backgroundColor = '#1565C0';
            dataset.borderRadius = 3;
        }

        forecastChartInstance = new Chart(chartCanvas.getContext('2d'), {
            type: type,
            data: { labels: monthlyLabels, datasets: [dataset] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: type === 'pie', position: 'right', labels: { font: { size: 8.5 }, boxWidth: 10 } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var idx = ctx.dataIndex;
                                var val = type === 'pie' ? ctx.parsed : ctx.parsed.y;
                                return 'RM ' + Number(val).toFixed(2) + ' (' + monthlyCount[idx] + ')';
                            }
                        }
                    }
                },
                scales: type === 'pie' ? {} : { y: { beginAtZero: true, ticks: { font: { size: 9 } } }, x: { ticks: { font: { size: 9 } } } }
            }
        });
    }

    buildChart('bar');

    document.querySelectorAll('input[name="rfChartType"]').forEach(function(radio) {
        radio.addEventListener('change', function() { if (this.checked) { buildChart(this.value); } });
    });

    // NEW 22 Jul 2026 — tab toggle for Overview / By Month / Detail,
    // per Chris's no-scroll instruction (see top comment).
    //
    // FIXED 23 Jul 2026 (v15) — per Chris: "click july drill down and
    // click prev it go back to contributor ranking it suppose to by
    // month screen and by month screen it should go back to search
    // screen" — tab switches used to be invisible to browser history
    // (pure show/hide, no URL change), so the new "← Back" button
    // (history.back()) had nothing to walk back through between tabs.
    // Every tab click now also pushes a real history entry (via
    // history.pushState, updating just the ?tab= part of the URL —
    // no reload happens), so Back correctly retraces Detail → By
    // Month → Search results → blank Search → Dashboard, in that exact
    // order, however many steps deep.
    var tabBtns = document.querySelectorAll('.rfTabBtn');
    var tabPanels = document.querySelectorAll('.rfTabPanel');
    function activateTab(tabId, skipHistory) {
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'flex' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? '#1565C0' : '#6b7280';
        });
        // Chart.js renders at 0x0 if the canvas was hidden (display:none)
        // when first drawn or resized; force a resize once its tab
        // becomes visible again so it actually appears.
        if (tabId === 'rfOverview' && forecastChartInstance) {
            setTimeout(function() { forecastChartInstance.resize(); }, 0);
        }
        if (!skipHistory) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            history.pushState({ rfTab: tabId }, '', url);
        }
    }
    tabBtns.forEach(function(b) {
        b.addEventListener('click', function() { activateTab(b.dataset.tab); });
    });
    // When the user (or the new "← Back" button) triggers real browser
    // back/forward navigation across one of these pushState steps, land
    // on whichever tab that history entry recorded — skipHistory=true
    // so this doesn't push ANOTHER entry on top and break forward nav.
    window.addEventListener('popstate', function(e) {
        var tabId = (e.state && e.state.rfTab) ? e.state.rfTab : (new URLSearchParams(window.location.search).get('month') ? 'rfDetail' : 'rfOverview');
        if (document.getElementById(tabId)) {
            activateTab(tabId, true);
        }
    });
})();

// NEW 23 Jul 2026 (v6) — typeahead for the GL/TL/Introducer filter
// boxes, per Chris: no <select> may ever list every candidate when
// the company could have tens of thousands of them. Same debounced
// fetch()+mousedown-to-pick pattern already used on the Agent
// Balances / Help Desk / Customer search screens.
(function() {
    var TYPEAHEAD_URL = '{{ route('renewal-forecast.agent-typeahead') }}';

    function initTypeahead(boxId, hiddenId, listId, mode, parentHiddenId, clearHiddenIds) {
        var box = document.getElementById(boxId);
        if (!box) { return; }
        var hidden = document.getElementById(hiddenId);
        var list = document.getElementById(listId);
        var timer = null;

        box.addEventListener('input', function() {
            hidden.value = ''; // typing invalidates whatever was previously picked
            clearTimeout(timer);
            var q = box.value.trim();
            if (q.length < 1) { list.style.display = 'none'; return; }
            timer = setTimeout(function() {
                var parentVal = parentHiddenId ? (document.getElementById(parentHiddenId).value || '') : '';
                var url = TYPEAHEAD_URL + '?mode=' + encodeURIComponent(mode) + '&q=' + encodeURIComponent(q) + '&parent_id=' + encodeURIComponent(parentVal);
                fetch(url)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        list.innerHTML = '';
                        if (!data.length) { list.style.display = 'none'; return; }
                        // NEW 23 Jul 2026 (v8) — per Chris: "no type
                        // ahead at the search criteria" — the result
                        // list was position:absolute inside a div that
                        // scrolls horizontally (overflow-x:auto), and
                        // setting overflow on one axis makes the
                        // browser clip the OTHER axis too, so the
                        // dropdown was being cut off invisibly instead
                        // of floating below the box. Fixed by making
                        // the list position:fixed and placing it with
                        // real screen coordinates here, so it always
                        // floats on top regardless of any scrolling
                        // container it happens to sit inside.
                        var rect = box.getBoundingClientRect();
                        list.style.left = rect.left + 'px';
                        list.style.top = rect.bottom + 'px';
                        list.style.width = Math.max(rect.width, 200) + 'px';
                        data.forEach(function(item) {
                            var row = document.createElement('div');
                            row.style.cssText = 'padding:5px 8px; font-size:9.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            row.textContent = item.full_name + ' (' + item.agent_code + ')';
                            row.addEventListener('mouseover', function() { row.style.background = '#EBF5FB'; });
                            row.addEventListener('mouseout', function() { row.style.background = ''; });
                            row.addEventListener('mousedown', function() {
                                // FIXED 23 Jul 2026 — per Chris: "your
                                // search is only begin when i click
                                // search now is after i choose GL
                                // immediate you display, that is wrong".
                                // Picking a name here used to auto-submit
                                // the form immediately, which searched
                                // right away and broke the "nothing
                                // computes until Search is clicked" rule.
                                // Now it only fills the field — Search
                                // must still be clicked explicitly.
                                box.value = item.full_name;
                                hidden.value = item.agent_id;
                                list.style.display = 'none';
                                (clearHiddenIds || []).forEach(function(id) {
                                    var el = document.getElementById(id);
                                    if (el) { el.value = ''; }
                                });
                            });
                            list.appendChild(row);
                        });
                        list.style.display = 'block';
                    });
            }, 200);
        });

        document.addEventListener('click', function(e) {
            if (e.target !== box) { list.style.display = 'none'; }
        });
    }

    // Picking a Group clears whatever TL/Introducer was already chosen;
    // picking a TL clears whatever Introducer was already chosen.
    initTypeahead('rfGlBox', 'rfGlId', 'rfGlList', 'gl', null, ['rfTlId', 'rfIntroId']);
    initTypeahead('rfTlBox', 'rfTlId', 'rfTlList', 'tl', 'rfGlId', ['rfIntroId']);
    initTypeahead('rfIntroBox', 'rfIntroId', 'rfIntroList', 'introducer', 'rfTlId', []);
})();
</script>
@endsection
