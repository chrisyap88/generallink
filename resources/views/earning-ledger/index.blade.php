@extends('layouts.dashboard')
@section('page-title', __('earning_ledger.title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:6px; font-size:10px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
    </div>
    <div style="font-size:9.5px; color:#9ca3af; flex-shrink:0;">
        {{ __('earning_ledger.intro_note') }}
    </div>

    {{-- Filters — which fields show depends on who's logged in --}}
    <form method="GET" action="{{ route($routePrefix . '.earning-ledger') }}" style="display:flex; align-items:flex-end; gap:6px; flex-shrink:0; flex-wrap:nowrap;">
        @if($role === 'ADMIN')
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('earning_ledger.field_group_label') }}</div>
            <select name="group_label_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:130px;">
                <option value="">{{ __('growth.select_placeholder') }}</option>
                @foreach($groupLabels as $g)
                <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('earning_ledger.field_group_leader_gl') }}</div>
            <select name="gl_agent_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:150px;">
                <option value="">{{ __('growth.select_placeholder') }}</option>
                @foreach($glOptions as $g)
                <option value="{{ $g->agent_id }}" {{ $glId === $g->agent_id ? 'selected' : '' }}>{{ $g->full_name }} ({{ $g->agent_code }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('earning_ledger.field_team_leader_tl') }}</div>
            <select name="tl_agent_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:150px;">
                <option value="">{{ __('growth.select_placeholder') }}</option>
                @foreach($tlOptions as $t)
                <option value="{{ $t->agent_id }}" {{ $tlId === $t->agent_id ? 'selected' : '' }}>{{ $t->full_name }} ({{ $t->agent_code }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('earning_ledger.field_introducer') }}</div>
            <select name="introducer_agent_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:150px;">
                <option value="">{{ __('growth.select_placeholder') }}</option>
                @foreach($introOptions as $i)
                <option value="{{ $i->agent_id }}" {{ $introId === $i->agent_id ? 'selected' : '' }}>{{ $i->full_name }} ({{ $i->agent_code }})</option>
                @endforeach
            </select>
        </div>
        @else
        {{-- GL / TL / Introducer — ONE picker: Myself + their own direct
             team, exactly matching what Chris asked for. Nothing loads
             until one of these is actually chosen. --}}
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('earning_ledger.field_team_member') }}</div>
            <select name="team_member_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:210px;">
                <option value="">{{ __('growth.select_placeholder') }}</option>
                @foreach($teamMemberOptions as $t)
                <option value="{{ $t->agent_id }}" {{ $teamMemberId === $t->agent_id ? 'selected' : '' }}>{{ $t->full_name }}{{ !empty($t->role_label) ? ' ('.$t->role_label.')' : '' }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('earning_ledger.field_ledger_as_at_date') }}</div>
            <input type="date" name="as_at_date" value="{{ $asAtDate }}" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10px; background:#fff; height:24px; width:130px; box-sizing:border-box;">
        </div>
        <button type="submit" style="background:#1B9AE4; color:#fff; border:none; border-radius:5px; padding:4px 14px; font-size:10px; font-weight:600; cursor:pointer; height:24px;">{{ __('network.go_button') }}</button>
        <a href="{{ route($routePrefix . '.earning-ledger') }}" style="background:#f3f4f6; color:#4A5568; text-decoration:none; border-radius:5px; padding:4px 14px; font-size:10px; font-weight:600; height:24px; display:inline-flex; align-items:center;">{{ __('dashboard.clear_word') }}</a>
    </form>

    @if(!$hasFilter)
    {{-- COMPULSORY per Chris: never auto-display records. This screen
         never even runs the Debit/Credit query until a real selection
         is made above. --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:6px;">
        <div style="font-size:26px;">🔍</div>
        <div style="font-size:11.5px; font-weight:700; color:#4A5568;">{{ __('earning_ledger.make_selection_note') }}</div>
        <div style="font-size:10px; color:#9ca3af;">
            @if($role === 'ADMIN')
            {{ __('earning_ledger.admin_pick_note') }}
            @else
            {{ __('earning_ledger.non_admin_pick_note') }}
            @endif
        </div>
    </div>
    @else
    {{-- Roster table --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; overflow:hidden; min-height:0;">
            <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
                <thead style="background:#F7FAFC;">
                    <tr>
                        <th style="width:170px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('gl.col_agent') }}</th>
                        <th style="width:100px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('earning_ledger.col_role') }}</th>
                        <th style="width:110px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('earning_ledger.field_group_label') }}</th>
                        <th style="width:100px; text-align:right; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('earning_ledger.col_total_debit') }}</th>
                        <th style="width:100px; text-align:right; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('earning_ledger.col_total_credit') }}</th>
                        <th style="width:100px; text-align:right; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('earning_ledger.col_balance_rm') }}</th>
                        <th style="width:110px; text-align:center; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('earning_ledger.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agents as $a)
                    @php
                        $roleLabels = ['ADMIN'=>__('gl.role_admin'),'GROUP_LEADER'=>__('gl.role_group_leader'),'TEAM_LEADER'=>__('gl.role_team_leader'),'INTRODUCER'=>__('gl.role_introducer')];
                    @endphp
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><strong>{{ $a->full_name }}</strong> <span style="color:#9ca3af; font-size:9px;">({{ $a->agent_code }})</span></td>
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $roleLabels[$a->role] ?? $a->role }}</td>
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $a->group_name ?? '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#B71C1C;">{{ number_format($a->total_debit, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#1B5E20;">{{ number_format($a->total_credit, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700;">{{ number_format($a->balance_as_at, 2) }}</td>
                        <td style="padding:5px 8px; text-align:center;">
                            <a href="{{ route($routePrefix . '.earning-ledger.statement', ['agent_id' => $a->agent_id, 'as_at_date' => $asAtDate]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:3px 10px; font-size:9.5px; font-weight:600;">{{ __('earning_ledger.statement_link') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#9ca3af;">{{ __('earning_ledger.no_agents_match_filter_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; padding:6px 12px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:10px; color:#9ca3af;">{{ __('earning_ledger.agent_count_page_x_of_y', ['count' => $agents->total(), 'current' => $agents->currentPage(), 'last' => max(1, $agents->lastPage())]) }}</div>
            <div style="display:flex; gap:6px;">
                @if($agents->onFirstPage())
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('network.prev') }}</span>
                @else
                <a href="{{ $agents->appends(request()->query())->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                @endif
                @if($agents->hasMorePages())
                <a href="{{ $agents->appends(request()->query())->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('network.next') }}</a>
                @else
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('network.next') }}</span>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
