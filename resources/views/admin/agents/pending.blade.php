@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('title', __('agents.pending_assignment_label'))
@section('page-title', __('agents.pending_assignment_label'))
@section('content')

{{-- REBUILT 8 Aug 2026 per Chris: strict no-scroll rule — this screen had
     no fixed-height wrapper (default page scroll), the table itself was
     wrapped in overflow-x:auto (horizontal scroll on top of that), and
     the agent list was unbounded (no pagination at all). Condensed the 3
     metric cards into one compact strip and the notice into a one-line
     banner so there's room for the table with real bottom Prev/Next. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; gap:10px; align-items:stretch;">
        <div style="flex:1; background:#fff; border:1px solid #FED7AA; border-radius:8px; padding:6px 12px;">
            <div style="font-size:8.5px; color:#92400e; text-transform:uppercase; font-weight:700;">{{ __('agents.pending_assignment_label') }}</div>
            <div style="font-size:18px; font-weight:700; color:#D97706;">{{ $pendingAgents->total() }}</div>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #C6F6D5; border-radius:8px; padding:6px 12px;">
            <div style="font-size:8.5px; color:#1b5e20; text-transform:uppercase; font-weight:700;">{{ __('agents.available_role_template', ['role' => \App\Services\RoleLabelService::plural('GROUP_LEADER')]) }}</div>
            <div style="font-size:18px; font-weight:700; color:#38A169;">{{ count($gls) }}</div>
        </div>
        <div style="flex:2; background:#fff8e1; border:1px solid #D97706; border-radius:8px; padding:6px 12px; display:flex; align-items:center; font-size:10px; color:#92400e;">
            &#9888; {!! __('agents.assignment_permanent_html', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) !!}
        </div>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; border-left:3px solid #38A169; border-radius:6px; padding:5px 10px; font-size:10.5px; color:#1b5e20; margin-bottom:8px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">

        @if($pendingAgents->total() === 0)
        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#A0AEC0; font-size:13px;">
            <i class="ti ti-circle-check" style="font-size:36px; display:block; margin-bottom:8px; color:#48BB78"></i>
            {{ __('agents.all_agents_assigned_note') }}
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="background:#FFF8E1;">
                        <th style="padding:6px 10px; text-align:left; border-bottom:2px solid #F6E05E; color:#744210; font-weight:700; font-size:9px; text-transform:uppercase;">{{ __('agents.agent_name_col') }}</th>
                        <th style="padding:6px 10px; text-align:left; border-bottom:2px solid #F6E05E; color:#744210; font-weight:700; font-size:9px; text-transform:uppercase;">{{ __('network.email') }}</th>
                        <th style="padding:6px 10px; text-align:left; border-bottom:2px solid #F6E05E; color:#744210; font-weight:700; font-size:9px; text-transform:uppercase;">{{ __('network.phone') }}</th>
                        <th style="padding:6px 10px; text-align:left; border-bottom:2px solid #F6E05E; color:#744210; font-weight:700; font-size:9px; text-transform:uppercase;">{{ __('agents.registered_col') }}</th>
                        <th style="padding:6px 10px; text-align:left; border-bottom:2px solid #F6E05E; color:#744210; font-weight:700; font-size:9px; text-transform:uppercase;">{{ __('agents.assign_to_role_col_template', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER')]) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingAgents as $agent)
                    <tr style="border-bottom:1px solid #FFFFF0;">
                        <td style="padding:6px 10px; font-weight:600; word-break:break-word;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <div style="width:24px; height:24px; border-radius:50%; background:linear-gradient(135deg,#D97706,#F6AD55); display:flex; align-items:center; justify-content:center; color:#fff; font-size:9.5px; font-weight:700; flex-shrink:0;">
                                    {{ strtoupper(substr($agent->full_name,0,2)) }}
                                </div>
                                {{ $agent->full_name }}
                            </div>
                        </td>
                        <td style="padding:6px 10px; color:#4A5568; word-break:break-word;">{{ $agent->email }}</td>
                        <td style="padding:6px 10px; color:#4A5568;">{{ $agent->phone }}</td>
                        <td style="padding:6px 10px; color:#718096; font-size:9.5px;">{{ \Carbon\Carbon::parse($agent->created_at)->format('d M Y, h:i A') }}</td>
                        <td style="padding:6px 10px;">
                            <form method="POST" action="{{ route('admin.agents.assign', $agent->agent_id) }}"
                                  onsubmit="return confirm({{ json_encode(__('agents.confirm_assign_template', ['name' => $agent->full_name, 'role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER')])) }})">
                                @csrf
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <select name="gl_agent_id" required
                                            style="flex:1; min-width:0; padding:5px 8px; border:1.5px solid #b2ebf2; border-radius:6px; font-size:10px; font-family:inherit; background:#f7fdff;">
                                        <option value="">{{ __('agents.select_role_dash_template', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER')]) }}</option>
                                        @foreach($gls as $gl)
                                            <option value="{{ $gl->agent_id }}">{{ $gl->full_name }} ({{ $gl->agent_code }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                            style="background:#0D5A8E; color:#fff; border:none; border-radius:6px; padding:5px 10px; font-size:10px; cursor:pointer; white-space:nowrap; font-weight:600;">
                                        {{ __('agents.assign_button') }} &#10003;
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($pendingAgents->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $pendingAgents->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('agents.page_x_of_y_agents_template', ['current' => $pendingAgents->currentPage(), 'total' => $pendingAgents->lastPage(), 'count' => $pendingAgents->total()]) }}</span>
            @if($pendingAgents->hasMorePages())
                <a href="{{ $pendingAgents->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>

</div>
@endsection
