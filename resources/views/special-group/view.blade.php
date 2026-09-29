@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('special_group.audit_view_title', ['group' => $label->group_name]))

@section('content')
{{-- REBUILT AGAIN 23 Jul 2026 (v3) — per Chris: "i want drill down from
     introducer" — an Introducer can recruit their OWN sub-Introducers,
     so this is now a genuinely recursive drill-down (see
     SpecialGroupController::view()): every node (the GL, a Team
     Leader, or any Introducer) shows its own direct children with
     each child's own child-count, and clicking a row with children > 0
     drills one level deeper, however many levels actually exist. "Back"
     just walks to the current node's own parent_id — works at any
     depth, no separate breadcrumb trail needed.

     Page size dropped to 5/page (from 8, previously 10) — per Chris,
     8/page still didn't fit and the internal scrollbar hid rows 13/14
     from view, which looked like missing data. 5/page is small enough
     to always render with zero scroll regardless of exact screen
     height. The whole page is a fixed-height flex column with
     overflow:hidden — it cannot scroll at all. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <a href="{{ route('admin.special-group.search') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('special_group.back_to_search_link') }}</a>
    </div>

    <div style="flex-shrink:0; font-size:14px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ $label->group_name }}</div>

    {{-- `back` param so Edit's Back/Cancel/save-redirect return to this
         exact page (including which node/page we drilled into). --}}
    @php $sgBackUrl = urlencode(url()->full()); @endphp

    <div style="flex-shrink:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; margin-bottom:8px; display:flex; align-items:center; gap:14px;">
        @php
            $sgStatusLabels = [
                'ACTIVE' => __('growth.status_active'),
                'INACTIVE' => __('growth.status_inactive'),
                'TERMINATED' => __('special_group.status_terminated'),
                'RISK_DEBT' => __('special_group.status_risk_debt'),
                'RESIGNED' => __('special_group.status_resigned'),
                'DECEASED' => __('special_group.status_deceased'),
            ];
        @endphp
        <div style="font-size:10.5px; font-weight:700; color:#1565C0; white-space:nowrap;">{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</div>
        @if($gl)
        <div style="font-size:12px; font-weight:600; white-space:nowrap;">{{ $gl->full_name }}</div>
        <div style="font-size:10.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $gl->email }}</div>
        <span style="padding:2px 8px; border-radius:20px; font-size:9px; font-weight:600; white-space:nowrap; {{ $gl->status === 'ACTIVE' ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#fff8e1;color:#92400e;' }}">{{ $sgStatusLabels[$gl->status] ?? $gl->status }}</span>
        <a href="{{ route('admin.masterfile.group-leaders.edit', $gl->agent_id) }}?back={{ $sgBackUrl }}" style="margin-left:auto; color:#1B9AE4; text-decoration:none; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('gl.edit_link') }}</a>
        @else
        <div style="color:#9ca3af; font-size:11px;">{{ __('special_group.no_role_created_yet_note', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</div>
        @endif
    </div>

    @if($node && $children)
    <div style="flex:1 1 auto; min-height:0; display:flex; flex-direction:column; background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        @php
            // NEW 23 Jul 2026 (v5) — per Chris: "back should display
            // bottom in the center" — the drill-up Back link moved out
            // of this header row down into the Prev/Next footer below,
            // centered alongside the page count. This header row now
            // just shows where you are, not a nav control.
            $upOneLevelUrl = ($gl && $node->agent_id !== $gl->agent_id)
                ? route('admin.special-group.view', $label->group_label_id) . ($node->parent_id && $node->parent_id !== $gl->agent_id ? '?node=' . $node->parent_id : '')
                : null;
        @endphp
        <div style="flex-shrink:0; display:flex; align-items:center; gap:10px; padding:8px 12px; background:#f0f9ff;">
            <div style="font-size:10.5px; font-weight:700; color:#1565C0;">{{ $childLabel }} ({{ $children->total() }})</div>
            @if($upOneLevelUrl)
            <span style="font-size:10px; color:#9ca3af;">{{ __('special_group.viewing_under_label') }} <strong style="color:#374151;">{{ $node->full_name }}</strong></span>
            @endif
        </div>
        {{-- NEW 23 Jul 2026 (v4) — per Chris: rows were rendering at
             inconsistent heights (Agent Code values like "3-13-8" were
             wrapping onto 2 lines because the table had no fixed
             column widths — the browser's auto layout gave that column
             too little room once Full Name refused to wrap). That's
             why different pages showed different numbers of visible
             rows and the right-hand columns (Status/Downline/Action)
             were getting clipped off the edge. table-layout:fixed with
             explicit % widths on every column makes every row exactly
             the same height and guarantees the table never exceeds the
             box — no wrapping, no horizontal overflow, ever. --}}
        <div style="flex:1 1 auto; min-height:0; overflow-y:auto; overflow-x:hidden;">
            <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:12px;">
                <colgroup>
                    <col style="width:4%;">
                    <col style="width:20%;">
                    <col style="width:10%;">
                    <col style="width:28%;">
                    <col style="width:12%;">
                    <col style="width:10%;">
                    <col style="width:16%;">
                </colgroup>
                <thead>
                    <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                        <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">#</th>
                        <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_full_name') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('network.col_code') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('gl.field_email') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('network.col_status') }}</th>
                        <th style="text-align:right; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('special_group.col_downline') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('gl.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($children as $i => $child)
                    <tr style="border-bottom:1px solid #f3f4f6; height:30px;">
                        <td style="padding:5px 8px; color:#9ca3af; white-space:nowrap; overflow:hidden;">{{ $children->firstItem() + $i }}</td>
                        <td style="padding:5px 8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $child->full_name }}">{{ $child->full_name }}</td>
                        <td style="padding:5px 8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $child->agent_code }}</td>
                        <td style="padding:5px 8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $child->email }}">{{ $child->email }}</td>
                        <td style="padding:5px 8px; white-space:nowrap; overflow:hidden;">
                            <span style="padding:2px 8px; border-radius:20px; font-size:9px; font-weight:600; {{ $child->status === 'ACTIVE' ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#fff8e1;color:#92400e;' }}">{{ $sgStatusLabels[$child->status] ?? $child->status }}</span>
                        </td>
                        <td style="padding:5px 8px; text-align:right; white-space:nowrap; overflow:hidden;">
                            {{-- NEW 23 Jul 2026 (v6) — per Chris: remove
                                 the separate "View" text link; the
                                 Downline NUMBER itself is now the
                                 drill-down trigger (clickable, cursor
                                 changes to a pointer) when there's
                                 somewhere to drill into. --}}
                            @if($child->child_count > 0)
                            <a href="{{ route('admin.special-group.view', $label->group_label_id) }}?node={{ $child->agent_id }}" style="color:#1565C0; text-decoration:none; font-weight:700; cursor:pointer;" title="View {{ $child->full_name }}'s downline">{{ $child->child_count }}</a>
                            @else
                            <span style="color:#9ca3af; font-weight:700;">{{ $child->child_count }}</span>
                            @endif
                        </td>
                        <td style="padding:5px 8px; white-space:nowrap; overflow:hidden;">
                            <a href="{{ route($child->role === 'TEAM_LEADER' ? 'admin.masterfile.team-leaders.edit' : 'admin.masterfile.introducers.edit', $child->agent_id) }}?back={{ $sgBackUrl }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">Edit</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:16px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('special_group.no_children_yet_note', ['label' => $childLabel]) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-top:1px solid #f3f4f6;">
            @if($children->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $children->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="display:flex; align-items:center; gap:10px; font-size:9.5px; color:#6b7280;">
                @if($upOneLevelUrl)
                <a href="{{ $upOneLevelUrl }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ $node->parent_id === $gl->agent_id ? __('special_group.back_to_role_plural_link', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) : __('network.back') }}</a>
                @endif
                {{ __('special_group.page_x_of_y_paren', ['current' => $children->currentPage(), 'last' => $children->lastPage(), 'total' => $children->total()]) }}
            </span>
            @if($children->hasMorePages())
                <a href="{{ $children->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection
