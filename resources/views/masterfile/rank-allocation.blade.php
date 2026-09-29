@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.rank_allocation_title'))
@section('content')
{{-- FIXED 8 Aug 2026 — per Chris: "one screen no scroll." Previously the
     WHOLE page scrolled (overflow-y:auto on the outer wrapper) whenever
     a group had many ranks, or the cross-role overrides panel was open —
     violating the standing no-scroll rule. Rebuilt so the outer page
     never scrolls at all: header/selector/note/column-headers/subtotals/
     buttons are all fixed (flex-shrink:0), and ONLY the rank rows
     themselves scroll inside their own bounded box — same pattern
     already used on every other list screen this project (WhatsApp
     Audit Log, GLADE Analytics, Notice Board). --}}
<div style="height:calc(100vh - 46px); overflow:hidden; padding:5px 12px; display:flex; flex-direction:column; gap:5px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:3px 10px; color:#065f46; font-size:10px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:4px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">
        {{-- FIXED 8 Aug 2026 (2) — per Chris: "remove Rank Allocation header,
             no truncation at the bottom." The page-title bar (top of
             dashboard.blade.php) already says "Rank Allocation", so
             repeating it here was redundant. Dropped it, kept just the
             vendor/product identity, and shrank the font — this reclaims
             enough vertical space that the Save button row no longer gets
             clipped by the outer overflow:hidden on screens with a full
             page of ranks. --}}
        <div style="padding:3px 14px; border-bottom:1px solid #e0f2fe; flex-shrink:0;">
            <div style="font-size:10px; font-weight:700; color:#1565C0;">{{ $structure->vendor_name }} / {{ $structure->product_name }}</div>
            <div style="font-size:8.5px; color:#6b7280; margin-top:1px;">
                {{ __('masterfile.total_earning_income_pct_label') }} <strong>{{ number_format($structure->total_commission_pct, 2) }}%</strong> &middot;
                {{ __('masterfile.mode_label') }} <strong>{{ $structure->is_rank_only ? __('masterfile.rank_only_mode') : __('masterfile.role_nested_mode') }}</strong>
                @if(!$structure->is_rank_only)
                &middot; {{ $roleShortLabels['GROUP_LEADER'] }} {{ number_format($structure->group_leader_pct, 2) }}% / {{ $roleShortLabels['TEAM_LEADER'] }} {{ number_format($structure->team_leader_pct, 2) }}% / {{ $roleShortLabels['INTRODUCER'] }} {{ number_format($structure->introducer_pct, 2) }}%
                @endif
            </div>
        </div>

        <div style="padding:3px 14px 0; flex-shrink:0;">
            <form method="GET" action="{{ route('admin.masterfile.commissions.rank-allocation', $structure->structure_id) }}" style="display:flex; align-items:center; gap:6px;">
                <label style="font-size:9.5px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('masterfile.defining_allocation_for_label') }}</label>
                <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 7px; font-size:10.5px; background:#fff;">
                    @foreach($groupLabels as $g)
                    <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                    @endforeach
                </select>
                <span style="font-size:8.5px; color:#9ca3af;">{{ __('masterfile.ranks_untouched_note') }}</span>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.masterfile.commissions.rank-allocation.update', $structure->structure_id) }}" autocomplete="off" style="flex:1; min-height:0; display:flex; flex-direction:column; padding:4px 14px 0;">
            @csrf @method('PUT')
            <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">

            @if($structure->is_rank_only)
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:3px 9px; margin-bottom:3px; font-size:8.5px; color:#92400e; flex-shrink:0;">
                {{ __('masterfile.rank_only_instruction', ['pct' => number_format($structure->total_commission_pct, 2)]) }}
            </div>
            @else
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:3px 9px; margin-bottom:3px; font-size:8.5px; color:#92400e; flex-shrink:0;">
                {{ __('masterfile.role_nested_instruction') }}
            </div>
            @endif

            @if($ranks->isEmpty())
            <div style="text-align:center; padding:30px; color:#9ca3af; font-size:11px; flex:1;">{{ __('masterfile.no_ranks_defined') }}</div>
            @else
            <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:10px; flex-shrink:0;">
                <colgroup>
                    <col style="width:9%;"><col style="width:28%;">
                    <col style="width:8%;"><col style="width:8%;"><col style="width:8%;">
                    <col style="width:17%;">
                </colgroup>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e0f2fe;">
                        <th style="text-align:center; padding:3px; color:#374151; font-weight:600; white-space:nowrap;">{{ __('masterfile.col_rank_no') }}</th>
                        <th style="text-align:left; padding:3px 6px; color:#374151; font-weight:600; white-space:nowrap;">{{ __('masterfile.col_rank_name') }}</th>
                        <th style="text-align:center; padding:3px 2px; color:#16a34a; font-weight:600; white-space:nowrap;">{{ $roleShortLabels['GROUP_LEADER'] }}</th>
                        <th style="text-align:center; padding:3px 2px; color:#0891b2; font-weight:600; white-space:nowrap;">{{ $roleShortLabels['TEAM_LEADER'] }}</th>
                        <th style="text-align:center; padding:3px 2px; color:#7c3aed; font-weight:600; white-space:nowrap;">{{ $roleShortLabels['INTRODUCER'] }}</th>
                        <th style="text-align:center; padding:3px; color:#374151; font-weight:600; white-space:nowrap;">%</th>
                    </tr>
                </thead>
            </table>
            {{-- CHANGED 8 Aug 2026 — per Chris: no internal scroll either,
                 use Prev/Next like every other list screen instead. Every
                 rank row stays in the DOM at all times (so the single
                 form submit + the %-sum recalculation always see every
                 rank, even ones on a different page) — JS just shows/
                 hides which page of rows is VISIBLE. RANKS_PER_PAGE rows
                 fit one screen with no scroll; Prev/Next below flips
                 which page is shown. --}}
            <div style="flex-shrink:0; border-bottom:1px solid #f3f4f6;">
                <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:10px;">
                    <colgroup>
                        <col style="width:9%;"><col style="width:28%;">
                        <col style="width:8%;"><col style="width:8%;"><col style="width:8%;">
                        <col style="width:17%;">
                    </colgroup>
                    <tbody id="rankRowsBody">
                        @foreach($ranks as $rank)
                        <tr class="rankRow" data-page="{{ intdiv($loop->index, 6) + 1 }}" style="background:{{ $loop->even ? '#e8f1fb' : '#ffffff' }}; border-bottom:1px solid #f3f4f6;">
                            <td style="padding:2px 6px; text-align:center; font-weight:700; color:#1565C0;">{{ $rank->display_rank_no }}</td>
                            <td style="padding:2px 6px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $rank->rank_name }}</td>
                            <td style="padding:2px 6px; text-align:center; color:#16a34a;">{{ $rank->role === 'GROUP_LEADER' ? '✓' : '' }}</td>
                            <td style="padding:2px 6px; text-align:center; color:#0891b2;">{{ $rank->role === 'TEAM_LEADER' ? '✓' : '' }}</td>
                            <td style="padding:2px 6px; text-align:center; color:#7c3aed;">{{ $rank->role === 'INTRODUCER' ? '✓' : '' }}</td>
                            <td style="padding:2px 6px; text-align:center;">
                                <input type="number" name="rank_pct[{{ $rank->rank_id }}]" class="rankPctInput" data-role="{{ $rank->role }}" value="{{ old('rank_pct.'.$rank->rank_id, $existingRankAllocations[$rank->rank_id] ?? 0) }}" step="0.01" min="0" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10px; outline:none; box-sizing:border-box; text-align:center;" oninput="rankAllocRecalc();">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- CHANGED 8 Aug 2026 (2) — threshold lowered from 8 to 6 rows/page
                 so pagination kicks in sooner; keeps every screen bounded to
                 a size that always fits with no truncation, regardless of
                 exact monitor/zoom. --}}
            @if($ranks->count() > 6)
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:3px 0;">
                <button type="button" id="rankPrevBtn" onclick="rankGoToPage(rankCurrentPage-1)" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 14px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</button>
                <span id="rankPageLabel" style="font-size:9px; color:#6b7280;">{{ __('masterfile.vp_page_label', ['current' => 1, 'total' => 1]) }}</span>
                <button type="button" id="rankNextBtn" onclick="rankGoToPage(rankCurrentPage+1)" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 14px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</button>
            </div>
            @endif

            <div style="flex-shrink:0; padding-top:2px; {{ $structure->is_rank_only ? 'display:none;' : '' }}" id="roleSubtotals">
                @foreach(['GROUP_LEADER','TEAM_LEADER','INTRODUCER'] as $role)
                @if($ranks->where('role', $role)->isNotEmpty())
                <div style="display:flex; justify-content:space-between; font-size:9px; color:#374151;">
                    <span>{{ __('masterfile.role_total_must_equal', ['role' => $roleShortLabels[$role], 'pct' => number_format($structure->{['GROUP_LEADER'=>'group_leader_pct','TEAM_LEADER'=>'team_leader_pct','INTRODUCER'=>'introducer_pct'][$role]}, 2)]) }}</span>
                    <span id="subtotal_{{ $role }}" data-role="{{ $role }}" style="font-weight:700;">0.00%</span>
                </div>
                @endif
                @endforeach
            </div>

            <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; padding-top:2px; {{ !$structure->is_rank_only ? 'display:none;' : '' }}" id="rankOnlyGrandRow">
                <span style="font-size:10px; font-weight:600; color:#374151;">{{ __('masterfile.rank_total_combined') }}</span>
                <span id="rankOnlyGrandTotal" style="font-size:11px; font-weight:700; color:#1565C0;">0.00%</span>
            </div>

            @if(!$structure->is_rank_only)
            <details style="flex-shrink:0; margin-top:3px; border-top:1px dotted #c4b5fd; padding-top:3px;">
                <summary style="cursor:pointer; font-size:9px; font-weight:700; color:#7c3aed;">{{ __('masterfile.cross_role_overrides_summary') }}</summary>
                {{-- Bounded height + its own scroll, so opening this panel
                     can never push the Save button off-screen. --}}
                <div style="margin-top:3px; max-height:90px; overflow-y:auto;">
                    @foreach($ranks as $rank)
                    @php $otherRoles = array_diff(['GROUP_LEADER','TEAM_LEADER','INTRODUCER'], [$rank->role]); @endphp
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:3px; font-size:9px;">
                        <span style="flex:1; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $rank->rank_name }} <span style="color:#9ca3af;">({{ $roleShortLabels[$rank->role] }})</span> {{ __('masterfile.donates_into_label') }}</span>
                        @foreach($otherRoles as $targetRole)
                        <label style="display:flex; align-items:center; gap:3px; color:#6b7280;">
                            {{ $roleShortLabels[$targetRole] }}
                            <input type="number" name="override_pct[{{ $rank->rank_id }}][{{ $targetRole }}]" class="overridePctInput" data-role="{{ $targetRole }}" value="{{ old('override_pct.'.$rank->rank_id.'.'.$targetRole, $existingOverrides[$rank->rank_id][$targetRole] ?? 0) }}" step="0.01" min="0" style="width:46px; border:1px solid #d1d5db; border-radius:4px; padding:2px 4px; font-size:9px; outline:none; box-sizing:border-box;" oninput="rankAllocRecalc();">
                        </label>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </details>
            @endif
            @endif

            <div style="flex-shrink:0; display:flex; gap:8px; align-items:center; padding:4px 0;">
                <a href="{{ route('admin.masterfile.commissions', ['mode'=>'edit', 'structure_id'=>$structure->structure_id, 'group_label_id'=>$groupLabelId]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 16px; font-size:10.5px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:4px 20px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.save_rank_allocation_button') }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
var rankAllocI18n = { pageLabel: @json(__('masterfile.vp_page_label', ['current' => ':c', 'total' => ':t'])) };
var ROLE_TARGET_PCT = {
    GROUP_LEADER: {{ (float) $structure->group_leader_pct }},
    TEAM_LEADER:  {{ (float) $structure->team_leader_pct }},
    INTRODUCER:   {{ (float) $structure->introducer_pct }}
};
var STRUCTURE_TOTAL_PCT = {{ (float) $structure->total_commission_pct }};
var IS_RANK_ONLY = {{ $structure->is_rank_only ? 'true' : 'false' }};

// NEW 8 Aug 2026 — Prev/Next paging for the rank rows (per Chris: no
// scroll anywhere, use Prev/Next like every other list screen instead).
// Every row stays in the DOM the whole time — this only toggles which
// page is VISIBLE — so the single form submit and the %-sum recalc
// below always see every rank, not just the currently-shown page.
var rankCurrentPage = 1;
function rankGoToPage(page) {
    var rows = document.querySelectorAll('.rankRow');
    var totalPages = 1;
    rows.forEach(function(r) { totalPages = Math.max(totalPages, parseInt(r.dataset.page, 10)); });
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    rankCurrentPage = page;
    rows.forEach(function(r) { r.style.display = (parseInt(r.dataset.page, 10) === page) ? '' : 'none'; });
    var label = document.getElementById('rankPageLabel');
    if (label) label.textContent = rankAllocI18n.pageLabel.replace(':c', page).replace(':t', totalPages);
    var prevBtn = document.getElementById('rankPrevBtn');
    var nextBtn = document.getElementById('rankNextBtn');
    if (prevBtn) { prevBtn.style.background = page === 1 ? '#1565C0' : '#1565C0'; prevBtn.disabled = page === 1; }
    if (nextBtn) { nextBtn.style.background = page === totalPages ? '#1565C0' : '#1565C0'; nextBtn.disabled = page === totalPages; }
}
if (document.querySelector('.rankRow')) { rankGoToPage(1); }

function rankAllocRecalc() {
    if (IS_RANK_ONLY) {
        var sum = 0;
        document.querySelectorAll('.rankPctInput').forEach(function(inp) { sum += parseFloat(inp.value) || 0; });
        var el = document.getElementById('rankOnlyGrandTotal');
        el.textContent = sum.toFixed(2) + '%';
        el.style.color = Math.abs(sum - STRUCTURE_TOTAL_PCT) < 0.001 ? '#16a34a' : (sum > STRUCTURE_TOTAL_PCT ? '#dc2626' : '#f59e0b');
        return;
    }
    ['GROUP_LEADER','TEAM_LEADER','INTRODUCER'].forEach(function(role) {
        var el = document.getElementById('subtotal_' + role);
        if (!el) { return; }
        var sum = 0;
        document.querySelectorAll('.rankPctInput[data-role="' + role + '"]').forEach(function(inp) { sum += parseFloat(inp.value) || 0; });
        document.querySelectorAll('.overridePctInput[data-role="' + role + '"]').forEach(function(inp) { sum += parseFloat(inp.value) || 0; });
        var target = ROLE_TARGET_PCT[role] || 0;
        el.textContent = sum.toFixed(2) + '%';
        el.style.color = Math.abs(sum - target) < 0.001 ? '#16a34a' : (sum > target ? '#dc2626' : '#f59e0b');
    });
}

rankAllocRecalc();
</script>
@endpush
@endsection
