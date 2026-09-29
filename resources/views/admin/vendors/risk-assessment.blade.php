@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.risk_assessment_title'))

@section('content')

{{-- NEW 12 Aug 2026 (Task #105) — per Chris: "there is no drill down on
     each due diligence, display all tap on top not a pop up window, it
     is a new screen when click from pending approval screen." Risk
     Assessment Results is no longer a JS-toggled tab living inside the
     Pending Vendor Login popup — it is its own real screen/route now,
     reached by a plain link from that popup (see pending-logins.blade.php).
     Two states on this ONE screen, both server-rendered via a plain ?cat
     query string (no JS toggling at all): with no ?cat it shows the
     9-category index; with ?cat=<key> it shows that one category's full
     finding/status/comments — the actual "drill down". No
     approve/reject/forward actions here — this is a clean, action-free
     report; those live back on the Pending Approval screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    {{-- CHANGED 12 Aug 2026 per Chris: "remove prev on top use prev
         bottom" — Prev no longer sits up here.
         FIXED 13 Aug 2026 per Chris: Prev must always return to the
         actual previous screen — on the category index that's Pending
         Approval; on a category drill-down that's this 9-category index,
         not the sequentially-previous category (see the bottom nav row
         in the drill-down branch below). Next still steps forward
         through the categories for continuous reading. --}}
    <div style="flex-shrink:0; margin-bottom:8px; text-align:right;">
        <div style="font-size:10.5px; color:#263238; font-weight:700;">{{ $vendor->vendor_name }}</div>
        <div style="font-size:9px; color:#9ca3af; font-weight:400;">{{ __('admin_vendors.risk_assessment_title') }}@if($dd){{ __('admin_vendors.run_date_suffix', ['date' => \Carbon\Carbon::parse($dd->run_at)->format('d M Y, h:ia')]) }}@endif</div>
    </div>

    @if(!$dd)
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:24px; text-align:center; color:#9ca3af; font-size:11px; flex:1;">{{ __('admin_vendors.no_dd_run') }}</div>
    @else

    @if(!$cat)
    {{-- ===================== CATEGORY INDEX ===================== --}}
    {{-- FIXED 12 Aug 2026 per Chris: "cannot see row 9" — the table used
         to share this card with the Recommendation/score banner above
         it and no scroll fallback, so on shorter screens row 9 (CTOS)
         got pushed past the bottom edge with no way to reach it.
         REDONE 13 Aug 2026 per Chris: "i want to see 9 due diligent
         report in one screen no scroll" — an internal scrollbar on the
         table (flex:1 + overflow-y:auto) was the wrong fix; there are
         always exactly 9 rows, never more, so the real fix is sizing
         them compactly enough to always fit with no scroll of any kind,
         same as the rest of the app. Table now renders at its natural
         (compact) height instead of stretching/scrolling within a fixed
         region — the banner and footnote just sit below it. --}}
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column;">
        {{-- FIXED 14 Aug 2026 per Chris: "i need prev button here" — the
             Prev bar below used to be a normal sibling with no flex sizing
             of its own, stacked after the table/banner/footnote inside a
             card whose overflow:hidden clips anything past its height. On
             a shorter viewport (smaller laptop screen, browser zoom, etc.)
             the table pushed the Prev bar past the visible edge and it
             was clipped away entirely — not scrollable, just gone. This
             table wrapper now takes the flexible middle slot (flex:1,
             overflow-y:auto as a safety net) while the recommendation
             banner, footnote, and Prev bar below are flex-shrink:0, so
             they always render in full — the table would scroll internally
             in a genuine worst case, long before Prev could ever be
             pushed off-screen again. Same protective pattern already used
             on pending-login-detail.blade.php's Prev bar. --}}
        <div style="flex:1; min-height:0; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px; table-layout:fixed;">
                <colgroup><col style="width:5%;"><col style="width:47%;"><col style="width:19%;"><col style="width:12%;"><col style="width:17%;"></colgroup>
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:3px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">#</th>
                        <th style="text-align:left; padding:3px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_category') }}</th>
                        <th style="text-align:left; padding:3px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_status') }}</th>
                        <th style="text-align:left; padding:3px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_score') }}</th>
                        <th style="text-align:center; padding:3px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_drill_down') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($riskCategories as $rc)
                    @php
                        $rcColor = match($rc['status']) { 'CLEAR' => '#166534', 'CONCERNS_FOUND', 'POTENTIAL_MATCH' => '#b71c1c', default => '#854d0e' };
                        $rcBg = match($rc['status']) { 'CLEAR' => '#f0fdf4', 'CONCERNS_FOUND', 'POTENTIAL_MATCH' => '#fef2f2', default => '#fef9c3' };
                        $rcLabel = match($rc['status']) { 'CLEAR' => __('admin_vendors.risk_status_clear'), 'CONCERNS_FOUND' => __('admin_vendors.risk_status_concern'), 'POTENTIAL_MATCH' => __('admin_vendors.risk_status_potential_match'), default => __('admin_vendors.risk_status_unavailable') };
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:3px 6px; color:#6b7280;">{{ $rc['num'] }}</td>
                        <td style="padding:3px 6px; color:#263238; font-weight:600; word-break:break-word;">{{ $rc['label'] }}</td>
                        <td style="padding:3px 6px;"><span style="font-size:8.5px; font-weight:700; padding:1px 7px; border-radius:9px; white-space:nowrap; color:{{ $rcColor }}; background:{{ $rcBg }};">{{ $rcLabel }}</span></td>
                        <td style="padding:3px 6px; color:#263238;">{{ $rc['mark'] }}/100</td>
                        <td style="padding:3px 6px; text-align:center;"><a href="{{ route('admin.vendors.pending-logins.risk-assessment', $vendor->vendor_id) }}?cat={{ $rc['key'] }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:2px 11px; font-size:8.5px; font-weight:600; white-space:nowrap; display:inline-block;">{{ __('admin_vendors.view_arrow_link') }}</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- REDESIGNED 13 Aug 2026 per Chris: "when your recommendation is
             100% you are cheating because the bankruptcy report is not
             upload... you should design the scoring mark per due
             diligent check and the final score is the total of each 9
             diligent divide by 9" — score is now a genuine average of
             all 9 category marks (100 each if CLEAR, 0 if UNAVAILABLE or
             a concern was found), computed by
             VendorDueDiligenceService::scoreBreakdown(), so an
             incomplete assessment can never read as a perfect one.
             ALSO per Chris: "green is recommend to approve in green,
             then propose different grade different colour different
             text" — recommendation now has 5 distinct colour-coded
             tiers (green Approve, blue Approve With Minor Review, amber
             Moderate Risk, orange High Risk, dark-red Critical Risk),
             both boxes sharing the same colour so they read as one
             consistent verdict, not two separate numbers. --}}
        @php
            $risk = \App\Services\VendorDueDiligenceService::scoreBreakdown($dd);
        @endphp
        <div style="flex-shrink:0; display:flex; gap:8px; margin-top:6px; padding-top:6px; border-top:1px solid #f3f4f6;">
            <div style="flex:1; background:{{ $risk['recommendation_color'] }}1A; border-radius:6px; padding:6px 12px; font-weight:700; color:{{ $risk['recommendation_color'] }}; font-size:11px; display:flex; align-items:center; word-break:break-word;">{{ __('admin_vendors.recommendation_label', ['label' => $risk['recommendation_label']]) }}</div>
            <div style="flex-shrink:0; background:{{ $risk['recommendation_color'] }}; border-radius:6px; padding:6px 12px; font-weight:700; color:#fff; font-size:11px; display:flex; align-items:center; white-space:nowrap;">{{ __('admin_vendors.score_band_label', ['score' => $risk['score'], 'band' => $risk['band']]) }}</div>
        </div>
        <div style="flex-shrink:0; font-size:8.5px; color:#9ca3af; margin-top:5px; line-height:1.3;">{{ __('admin_vendors.risk_footnote') }}</div>
        <div style="flex-shrink:0; margin-top:8px; padding-top:8px; border-top:1px solid #f3f4f6;">
            <a href="{{ route('admin.vendors.pending-logins') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700; white-space:nowrap; display:inline-block;">{{ __('network.prev') }}</a>
        </div>
    </div>

    @else
    {{-- ===================== CATEGORY DRILL-DOWN ===================== --}}
    @php
        $rc = collect($riskCategories)->firstWhere('key', $cat);
    @endphp
    @if(!$rc)
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:24px; text-align:center; color:#9ca3af; font-size:11px; flex:1;">{{ __('admin_vendors.category_not_found') }} <a href="{{ route('admin.vendors.pending-logins.risk-assessment', $vendor->vendor_id) }}" style="color:#1565C0;">{{ __('admin_vendors.back_to_all_categories') }}</a></div>
    @else
    @php
        $rcColor = match($rc['status']) { 'CLEAR' => '#166534', 'CONCERNS_FOUND', 'POTENTIAL_MATCH' => '#b71c1c', default => '#854d0e' };
        $rcBg = match($rc['status']) { 'CLEAR' => '#f0fdf4', 'CONCERNS_FOUND', 'POTENTIAL_MATCH' => '#fef2f2', default => '#fef9c3' };
        $rcLabel = match($rc['status']) { 'CLEAR' => __('admin_vendors.risk_status_clear'), 'CONCERNS_FOUND' => __('admin_vendors.risk_status_concern'), 'POTENTIAL_MATCH' => __('admin_vendors.risk_status_potential_match'), default => __('admin_vendors.risk_status_unavailable') };
        $catIndex = collect($riskCategories)->search(fn($r) => $r['key'] === $cat);
        $nextCat = $catIndex < count($riskCategories) - 1 ? $riskCategories[$catIndex + 1]['key'] : null;
    @endphp
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f3f4f6;">
            <div style="font-size:12px; font-weight:700; color:#263238; word-break:break-word;">{{ $rc['num'] }}. {{ $rc['label'] }}{{ !empty($rc['extra']) ? ' — ' . $rc['extra'] : '' }}</div>
            <span style="font-size:9.5px; font-weight:700; padding:3px 10px; border-radius:10px; color:{{ $rcColor }}; background:{{ $rcBg }}; white-space:nowrap; flex-shrink:0; margin-left:8px;">{{ $rcLabel }}</span>
        </div>

        {{-- CHANGED 13 Aug 2026 per Chris: "incorporate prev and next...
             all information in one screen no scroll" — was
             overflow-y:auto on this whole pane; now overflow:hidden. The
             finding note is a short paragraph and the bankruptcy/CTOS
             upload lists below already have their own capped 160px
             internal scroll for the rare case of many uploaded files, so
             nothing here relies on the outer scrollbar that was removed. --}}
        <div style="flex:1; min-height:0; overflow:hidden;">
            <div style="font-size:10.5px; color:#374151; line-height:1.5; margin-bottom:10px;"><strong>{{ __('admin_vendors.finding_comments_label') }}</strong><br>{{ $rc['note'] }}</div>

            @if($cat === 'bankruptcy')
            <div style="margin-bottom:10px;">
                <form method="POST" action="{{ route('admin.vendors.pending-logins.bankruptcy-upload', $vendor->vendor_id) }}" enctype="multipart/form-data" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; background:#f9fafb; border-radius:6px; padding:8px;">
                    @csrf
                    <span style="font-size:9.5px; color:#4b5563; font-weight:600;">{{ __('admin_vendors.bankruptcy_upload_label') }}</span>
                    <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" required style="font-size:9px; flex:1; min-width:160px;">
                    <button type="submit" style="background:#6D28D9; color:#fff; border:none; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.upload_review_button') }}</button>
                </form>
            </div>
            @if($bankruptcyDocs->isNotEmpty())
            <div style="max-height:160px; overflow-y:auto;">
                @foreach($bankruptcyDocs as $bd)
                @php
                    $bdColor = match($bd->ai_conclusion) { 'CLEAR' => '#166534', 'CONCERN' => '#b71c1c', default => '#854d0e' };
                    $bdBg = match($bd->ai_conclusion) { 'CLEAR' => '#f0fdf4', 'CONCERN' => '#fef2f2', default => '#fef9c3' };
                @endphp
                <div style="border:1px solid #f3f4f6; border-radius:6px; padding:6px 8px; margin-bottom:5px; background:{{ $bdBg }};">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                        <div style="min-width:0; flex:1;">
                            <div style="font-size:10px; font-weight:600; color:#263238; word-break:break-word;">{{ $bd->ai_person_name ?: $bd->file_name }}</div>
                            <div style="font-size:9px; color:#374151; margin-top:2px;">{{ $bd->ai_finding ?: __('admin_vendors.not_yet_reviewed') }}</div>
                        </div>
                        <span style="font-size:8.5px; font-weight:700; padding:2px 7px; border-radius:10px; white-space:nowrap; color:{{ $bdColor }}; background:#fff;">{{ $bd->ai_conclusion ?: __('admin_vendors.pending_badge') }}</span>
                    </div>
                    <div style="margin-top:4px;"><a href="{{ route('admin.vendors.pending-logins.bankruptcy-file', [$vendor->vendor_id, $bd->document_id]) }}" target="_blank" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:5px; padding:2px 8px; font-size:8.5px; font-weight:600;">{{ __('admin_vendors.view_file_button') }}</a></div>
                </div>
                @endforeach
            </div>
            @endif
            @endif

            @if($cat === 'ctos')
            <div style="margin-bottom:10px;">
                <form method="POST" action="{{ route('admin.vendors.pending-logins.ctos-upload', $vendor->vendor_id) }}" enctype="multipart/form-data" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap; background:#f9fafb; border-radius:6px; padding:8px;">
                    @csrf
                    <span style="font-size:9.5px; color:#4b5563; font-weight:600;">{{ __('admin_vendors.ctos_upload_label') }}</span>
                    <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" required style="font-size:9px; flex:1; min-width:160px;">
                    <button type="submit" style="background:#6D28D9; color:#fff; border:none; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.upload_review_button') }}</button>
                </form>
            </div>
            @if($ctosDocs->isNotEmpty())
            <div style="max-height:160px; overflow-y:auto;">
                @foreach($ctosDocs as $cd)
                @php
                    $cdColor = match($cd->ai_conclusion) { 'CLEAR' => '#166534', 'CONCERN' => '#b71c1c', default => '#854d0e' };
                    $cdBg = match($cd->ai_conclusion) { 'CLEAR' => '#f0fdf4', 'CONCERN' => '#fef2f2', default => '#fef9c3' };
                @endphp
                <div style="border:1px solid #f3f4f6; border-radius:6px; padding:6px 8px; margin-bottom:5px; background:{{ $cdBg }};">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                        <div style="min-width:0; flex:1;">
                            <div style="font-size:10px; font-weight:600; color:#263238; word-break:break-word;">{{ $cd->ai_entity_name ?: $cd->file_name }}</div>
                            <div style="font-size:9px; color:#374151; margin-top:2px;">{{ $cd->ai_finding ?: __('admin_vendors.not_yet_reviewed') }}</div>
                        </div>
                        <span style="font-size:8.5px; font-weight:700; padding:2px 7px; border-radius:10px; white-space:nowrap; color:{{ $cdColor }}; background:#fff;">{{ $cd->ai_conclusion ?: __('admin_vendors.pending_badge') }}</span>
                    </div>
                    <div style="margin-top:4px;"><a href="{{ route('admin.vendors.pending-logins.ctos-file', [$vendor->vendor_id, $cd->document_id]) }}" target="_blank" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:5px; padding:2px 8px; font-size:8.5px; font-weight:600;">{{ __('admin_vendors.view_file_button') }}</a></div>
                </div>
                @endforeach
            </div>
            @endif
            @endif

            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:10px; padding-top:8px; border-top:1px solid #f3f4f6;">
                <div style="font-size:10px; color:#6b7280;">{!! __('admin_vendors.category_score_label', ['mark' => '<strong style="color:#263238;">' . $rc['mark'] . '/100</strong>']) !!}{{ $rc['status'] !== 'CLEAR' ? __('admin_vendors.category_score_counts_against') : '' }}</div>
            </div>
        </div>

        {{-- FIXED 13 Aug 2026 per Chris: "your navigation logic is not
             correct, when i click prev it just goes back my previous
             screen not jump straight back to the program, example when
             i read the diligent remarks, it should go back the 9
             diligent screen" — Prev used to cycle to the sequentially
             PREVIOUS CATEGORY, which isn't the screen the admin actually
             came from. Prev now always returns to the 9-category index
             (the real previous screen); Next still steps forward through
             the categories for continuous reading. --}}
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
            <a href="{{ route('admin.vendors.pending-logins.risk-assessment', $vendor->vendor_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            <span style="font-size:9.5px; color:#6b7280; white-space:nowrap;">{{ __('admin_vendors.category_of_total', ['num' => $rc['num'], 'total' => count($riskCategories)]) }}</span>
            @if($nextCat)
            <a href="{{ route('admin.vendors.pending-logins.risk-assessment', $vendor->vendor_id) }}?cat={{ $nextCat }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
    @endif
    @endif
    @endif
</div>
@endsection
