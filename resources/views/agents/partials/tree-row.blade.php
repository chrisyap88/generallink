@php
$roleColors = [
    'GROUP_LEADER' => ['bg'=>'#C8E6C9','color'=>'#1B5E20','label'=>\App\Services\RoleLabelService::shortLabel('GROUP_LEADER')],
    'TEAM_LEADER'  => ['bg'=>'#BBDEFB','color'=>'#1565C0','label'=>\App\Services\RoleLabelService::shortLabel('TEAM_LEADER')],
    'INTRODUCER'   => ['bg'=>'#E9D5FF','color'=>'#4C1D95','label'=>\App\Services\RoleLabelService::shortLabel('INTRODUCER')],
];
$rc = $roleColors[$agent->role] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>'?'];

$statusColors = [
    'ACTIVE'     => ['bg'=>'#C8E6C9','color'=>'#1B5E20'],
    'INACTIVE'   => ['bg'=>'#FFF9C4','color'=>'#F57F17'],
    'TERMINATED' => ['bg'=>'#FFCDD2','color'=>'#B71C1C'],
    'RESIGNED'   => ['bg'=>'#FFCDD2','color'=>'#B71C1C'],
    'DECEASED'   => ['bg'=>'#FFCDD2','color'=>'#B71C1C'],
];
$sc = $statusColors[$agent->status] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
$indent = $depth * 24;
$hasChildren = DB::table('agents')->where('parent_id', $agent->agent_id)->where('is_deleted', false)->exists();
@endphp
<tr id="row_{{ $agent->agent_id }}" data-depth="{{ $depth }}"
    style="border-bottom:1px solid #F7FAFC;"
    onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
    <td style="padding:7px 10px;width:30px;">
        <div style="padding-left:{{ $indent }}px;">
            @if($hasChildren)
            <button onclick="toggleNode('{{ $agent->agent_id }}', this)"
                style="background:none;border:1px solid #b2ebf2;border-radius:4px;cursor:pointer;width:20px;height:20px;font-size:10px;color:#0D5A8E;display:inline-flex;align-items:center;justify-content:center;">▶</button>
            @else
            <span style="display:inline-block;width:20px;text-align:center;color:#d1d5db;">•</span>
            @endif
        </div>
    </td>
    <td style="padding:7px 10px;font-weight:600;white-space:nowrap;">
        <div style="padding-left:{{ $indent }}px;">{{ $agent->full_name }}</div>
    </td>
    <td style="padding:7px 10px;font-family:monospace;font-size:10px;color:#0D5A8E;font-weight:600;white-space:nowrap;">{{ $agent->member_code ?? $agent->agent_code ?? '—' }}</td>
    <td style="padding:7px 10px;">
        <span style="background:{{ $rc['bg'] }};color:{{ $rc['color'] }};padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;">{{ $rc['label'] }}</span>
    </td>
    <td style="padding:7px 10px;color:#718096;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $agent->email ?? '—' }}</td>
    <td style="padding:7px 10px;color:#718096;white-space:nowrap;">{{ $agent->phone ?? '—' }}</td>
    <td style="padding:7px 10px;text-align:center;">
        <span style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;">{{ $agent->status }}</span>
    </td>
    <td style="padding:7px 10px;color:#718096;white-space:nowrap;font-size:10px;">{{ \Carbon\Carbon::parse($agent->created_at)->format('d M Y') }}</td>
    <td style="padding:7px 10px;text-align:center;white-space:nowrap;">
        <a href="{{ route('admin.agents.show', $agent->agent_id) }}"
            style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:3px 8px;border-radius:5px;font-size:10px;text-decoration:none;font-weight:600;">👁</a>
        <a href="{{ route('admin.network.agent.edit', [$agent->agent_id, 'back'=>url()->current()]) }}"
            style="background:#e0f2fe;color:#1565C0;padding:3px 8px;border-radius:5px;font-size:10px;text-decoration:none;font-weight:600;">✏️</a>
    </td>
</tr>
