@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.rank_assignment_title'))
@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; overflow-x:hidden; padding:6px 12px; display:flex; flex-direction:column; gap:6px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:4px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif

    {{-- REBUILT 1 Aug 2026 (2) — per Chris: "how i assign to one specific
    introducer to rank no?" — the old bucket-wide screen (pick ONE rank
    for a whole Role+Group combination) couldn't do that. This lists
    individual agents, filterable, each with their own rank dropdown
    scoped to only the ranks valid for THEIR role+group. --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:4px 14px; border-bottom:1px solid #e0f2fe;">
            <div style="font-size:8.5px; color:#6b7280; margin-top:1px;">{{ __('masterfile.rank_assignment_intro') }}</div>
        </div>
        <form method="GET" action="{{ route('admin.masterfile.rank-assignment') }}" style="padding:5px 14px; display:flex; align-items:flex-end; gap:8px; flex-wrap:wrap;">
            <input type="hidden" name="filtered" value="1">
            <div>
                <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_role') }}</label>
                <select name="role" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10.5px; background:#fff; min-width:130px;">
                    <option value="">{{ __('masterfile.all_roles_option') }}</option>
                    @foreach($roleLabels as $r => $label)
                    <option value="{{ $r }}" {{ $role === $r ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.org_rewards_group_label') }}</label>
                <select name="group_label_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10.5px; background:#fff; min-width:150px;">
                    <option value="__any__" {{ !request()->has('group_label_id') ? 'selected' : '' }}>{{ __('masterfile.all_groups_option') }}</option>
                    @foreach($groupLabels as $g)
                    <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.search_name_agent_code_label') }}</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('masterfile.type_to_search_dots') }}" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10.5px; min-width:180px; box-sizing:border-box;">
            </div>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
            <a href="{{ route('admin.masterfile.rank-assignment') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:10.5px;">{{ __('masterfile.clear') }}</a>
        </form>
    </div>

    @if(is_null($agents))
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0; padding:30px; text-align:center; color:#9ca3af; font-size:11px;">
        {{ __('masterfile.pick_role_group_prompt') }}
    </div>
    @else
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <form method="POST" action="{{ route('admin.masterfile.rank-assignment.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="role" value="{{ $role }}">
            <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="page" value="{{ $agents->currentPage() }}">

            @if($agents->isEmpty())
            <div style="text-align:center; padding:30px; color:#9ca3af; font-size:11px;">{{ __('masterfile.no_agents_match_filter') }}</div>
            @else
            <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:10.5px;">
                <colgroup>
                    <col style="width:12%;"><col style="width:24%;"><col style="width:14%;">
                    <col style="width:16%;"><col style="width:14%;"><col style="width:20%;">
                </colgroup>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:1px solid #e0f2fe;">
                        <th style="text-align:left; padding:3px 8px; color:#374151; font-weight:600;">{{ __('masterfile.col_agent_code') }}</th>
                        <th style="text-align:left; padding:3px 8px; color:#374151; font-weight:600;">{{ __('masterfile.col_full_name') }}</th>
                        <th style="text-align:left; padding:3px 8px; color:#374151; font-weight:600;">{{ __('masterfile.col_role') }}</th>
                        <th style="text-align:left; padding:3px 8px; color:#374151; font-weight:600;">{{ __('masterfile.col_group') }}</th>
                        <th style="text-align:left; padding:3px 8px; color:#374151; font-weight:600;">{{ __('masterfile.col_current_rank') }}</th>
                        <th style="text-align:left; padding:3px 8px; color:#374151; font-weight:600;">{{ __('masterfile.col_new_rank') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($agents as $loop_i => $a)
                    @php
                        $bucketKey = $a->role . '|' . ($a->group_label_id ?? 'default');
                        $ranksForRow = $ranksByBucket[$bucketKey] ?? collect();
                    @endphp
                    <tr style="background:{{ $loop->even ? '#e8f1fb' : '#ffffff' }}; border-bottom:1px solid #f3f4f6;">
                        <td style="padding:2px 8px; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $a->agent_code }}</td>
                        <td style="padding:2px 8px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $a->full_name }}">{{ $a->full_name }}</td>
                        <td style="padding:2px 8px; color:#374151;">{{ $roleLabels[$a->role] ?? $a->role }}</td>
                        <td style="padding:2px 8px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $a->group_name ?? __('masterfile.system_default_option') }}</td>
                        <td style="padding:2px 8px; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $a->rank_name ?? __('masterfile.no_rank_option') }}</td>
                        <td style="padding:2px 8px;">
                            @if($ranksForRow->isEmpty())
                            <span style="font-size:9px; color:#9ca3af;">{{ __('masterfile.no_ranks_defined_short') }}</span>
                            @else
                            <select name="rank[{{ $a->agent_id }}]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:2px 6px; font-size:10px; background:#fff; box-sizing:border-box;">
                                <option value="" {{ !$a->rank_id ? 'selected' : '' }}>{{ __('masterfile.no_rank_option') }}</option>
                                @foreach($ranksForRow as $opt)
                                <option value="{{ $opt->rank_id }}" {{ $a->rank_id === $opt->rank_id ? 'selected' : '' }}>{{ $opt->rank_no }} — {{ $opt->rank_name }}</option>
                                @endforeach
                            </select>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="padding:4px 14px; border-top:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between;">
                <span style="font-size:9.5px; color:#9ca3af;">{{ __('masterfile.agents_showing_count', ['first' => $agents->firstItem(), 'lastItem' => $agents->lastItem(), 'total' => $agents->total(), 'current' => $agents->currentPage(), 'lastPage' => $agents->lastPage()]) }}</span>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:4px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.save_page_ranks_button') }}</button>
            </div>
            @endif
        </form>
    </div>
    @endif

    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ route('admin.dashboard') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 18px; font-size:10.5px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
        @if(!is_null($agents) && !$agents->isEmpty())
        <div style="display:flex; gap:8px;">
            @if($agents->onFirstPage())
            <span style="background:#1565C0; color:#fff; opacity:.5; border-radius:6px; padding:5px 16px; font-size:10.5px; font-weight:700;">{{ __('masterfile.prev_page_button') }}</span>
            @else
            <a href="{{ $agents->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 16px; font-size:10.5px; font-weight:700;">{{ __('masterfile.prev_page_button') }}</a>
            @endif
            @if($agents->hasMorePages())
            <a href="{{ $agents->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 16px; font-size:10.5px; font-weight:700;">{{ __('masterfile.next_page_button') }}</a>
            @else
            <span style="background:#1565C0; color:#fff; opacity:.5; border-radius:6px; padding:5px 16px; font-size:10.5px; font-weight:700;">{{ __('masterfile.next_page_button') }}</span>
            @endif
        </div>
        @endif
    </div>
    <div style="flex-shrink:0; height:10px;"></div>
</div>
@endsection
