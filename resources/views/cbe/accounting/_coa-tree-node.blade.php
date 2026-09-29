{{-- Recursive tree-node partial for the Chart of Accounts Structure
     Tree. $node = ['account' => <row>, 'children' => ['nodes' => [...], 'gap' => [...]|null]] --}}
<li style="margin:0;">
    <div class="coa-tree-row" data-account-id="{{ $node['account']->account_id }}" onclick="toggleCoaNode(this)" style="display:flex; align-items:center; gap:6px; padding:4px 6px; cursor:pointer; border-radius:5px;">
        <span class="coa-tree-toggle" style="width:14px; text-align:center; font-weight:700; color:var(--gl-blue); font-size:11px;">{{ count($node['children']['nodes']) ? '+' : '' }}</span>
        <span style="font-family:monospace; font-size:10.5px; color:#4b5563; min-width:56px;">{{ $node['account']->account_code }}</span>
        <span style="font-size:10.5px; color:#263238; {{ !$node['account']->is_posting_account ? 'font-weight:700;' : '' }}">{{ $node['account']->account_name }}@if($node['account']->account_name_zh) <span style="color:#6b7280; font-weight:400;">/ {{ $node['account']->account_name_zh }}</span>@endif</span>
        @if(!$node['account']->is_posting_account)
        <span style="font-size:8px; font-weight:700; color:#9a6b00; background:#fff3cd; border-radius:10px; padding:1px 7px;">{{ __('cbe_accounting.coa_tree_folder_badge') }}</span>
        <span style="font-size:9px; color:#9ca3af;">({{ $node['count'] }})</span>
        @endif
        @if(!$node['account']->is_active)
        <span style="font-size:8px; font-weight:700; color:#6b7280; background:#f3f4f6; border-radius:10px; padding:1px 7px;">{{ __('masterfile.inactive') }}</span>
        @endif
    </div>
    @if(count($node['children']['nodes']))
    <ul class="coa-tree-children" style="display:none; list-style:none; margin:0; padding-left:22px; border-left:1px dashed #d1d5db;">
        @if($node['children']['gap'])
        <li style="padding:3px 6px; font-size:9.5px; color:#9a6b00; background:#fffbea;">
            @if(count($node['children']['gap']['missing']))
            {{ __('cbe_accounting.coa_tree_gap_label') }} {{ implode(', ', $node['children']['gap']['missing']) }} &nbsp;|&nbsp;
            @endif
            @if($node['children']['gap']['next'])
            {{ __('cbe_accounting.coa_tree_next_label') }} <strong>{{ $node['children']['gap']['next'] }}</strong>
            @endif
        </li>
        @endif
        @foreach($node['children']['nodes'] as $child)
            @include('cbe.accounting._coa-tree-node', ['node' => $child])
        @endforeach
        @if(($node['children']['hasPrev'] ?? false) || ($node['children']['hasNext'] ?? false))
        <li style="padding:3px 6px; display:flex; justify-content:space-between; align-items:center; gap:6px;">
            @if($node['children']['hasPrev'])
            <a href="{{ $node['children']['prevUrl'] }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:600;">{{ __('network.prev') }}</a>
            @else
            <span style="background:#1565C0; color:#fff; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:700;">{{ __('network.prev') }}</span>
            @endif
            <span style="font-size:8.5px; color:#9ca3af;">{{ $node['children']['page'] }} / {{ $node['children']['lastPage'] }}</span>
            @if($node['children']['hasNext'])
            <a href="{{ $node['children']['nextUrl'] }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:600;">{{ __('network.next') }}</a>
            @else
            <span style="background:#1565C0; color:#fff; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </li>
        @endif
    </ul>
    @endif
</li>
