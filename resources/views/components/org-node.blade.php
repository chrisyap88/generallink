@php
$roleColor = match($node['role'] ?? 'INTRODUCER') {
    'ADMIN'        => '#D97706',
    'GROUP_LEADER' => '#059669',
    'TEAM_LEADER'  => '#0284C7',
    default        => '#7C3AED',
};
$roleLabel = match($node['role'] ?? 'INTRODUCER') {
    'ADMIN'        => __('gl.role_admin'),
    'GROUP_LEADER' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'),
    'TEAM_LEADER'  => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER'),
    default        => \App\Services\RoleLabelService::shortLabel('INTRODUCER'),
};
$statusColor = match($node['status'] ?? 'ACTIVE') {
    'ACTIVE'     => '#48BB78',
    'INACTIVE'   => '#FC8181',
    'TERMINATED' => '#A0AEC0',
    'RISK_DEBT'  => '#F6AD55',
    'RESIGNED'   => '#B794F4',
    'DECEASED'   => '#FC8181',
    default      => '#A0AEC0',
};
$children = $node['children'] ?? [];
$agentId  = $node['id'] ?? '';
@endphp
<div style="padding-left: {{ $depth * 18 }}px; margin-bottom: 4px;">
    <div onclick="window.location='/admin/agents/{{ $agentId }}/profile'"
         style="display:inline-flex;align-items:center;gap:8px;padding:5px 10px;
                border-radius:8px;border:1px solid #E2E8F0;background:#fff;
                cursor:pointer;transition:all 0.15s;max-width:100%"
         onmouseover="this.style.background='#EBF5FB';this.style.borderColor='#1B9AE4'"
         onmouseout="this.style.background='#fff';this.style.borderColor='#E2E8F0'">
        <span style="background:{{ $roleColor }}20;color:{{ $roleColor }};
                     font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;flex-shrink:0">
            {{ $roleLabel }}
        </span>
        <span style="font-size:13px;font-weight:500;color:#1A202C;
                     white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:120px"
              title="{{ $node['name'] ?? '' }}">
            {{ $node['name'] ?? '—' }}
        </span>
        <span style="width:7px;height:7px;border-radius:50%;background:{{ $statusColor }};flex-shrink:0"
              title="{{ $node['status'] ?? '' }}"></span>
        @if(!empty($node['code']))
        <span style="font-size:11px;color:#A0AEC0;flex-shrink:0">{{ $node['code'] }}</span>
        @endif
        @if(count($children) > 0)
        <span style="font-size:11px;color:#A0AEC0;flex-shrink:0">({{ count($children) }})</span>
        @endif
    </div>
    @if(count($children) > 0)
        <div style="border-left:1px dashed #CBD5E0;margin-left:14px;padding-left:4px;margin-top:4px">
            @foreach($children as $child)
                @include('components.org-node', ['node' => $child, 'depth' => 0])
            @endforeach
        </div>
    @endif
</div>
