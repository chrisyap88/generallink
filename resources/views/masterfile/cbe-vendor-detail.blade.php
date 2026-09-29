@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_vendors.detail_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:5px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ $vendor->vendor_name }}</div>
            <div style="font-size:9.5px; color:#6b7280;">{{ $vendor->contact_person }} @if($vendor->phone) · {{ $vendor->phone }} @endif @if($vendor->email) · {{ $vendor->email }} @endif</div>
        </div>
        <a href="{{ route('admin.masterfile.cbe-vendors') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:600; white-space:nowrap;">{{ __('cbe_vendors.back_to_list') }}</a>
    </div>

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:5px; flex-shrink:0;">
        <div style="font-size:11px; font-weight:700; color:#374151;">{{ __('cbe_vendors.entities_title') }}</div>
        <a href="{{ route('admin.masterfile.cbe-vendors.request-node', $vendor->vendor_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:5px 14px; font-size:10.5px; font-weight:600;">{{ __('cbe_vendors.request_entity_button') }}</a>
    </div>

    @php $pageSize = 8; $total = $links->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1.6fr 1.4fr 1.2fr 1.4fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">
            <div>{{ __('cbe_vendors.col_entity') }}</div>
            <div>{{ __('cbe_vendors.col_group') }}</div>
            <div>{{ __('cbe_vendors.col_status') }}</div>
            <div>{{ __('cbe_vendors.col_requested_at') }}</div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($links as $l)
            @php
                $statusColor = match($l->status) { 'ACTIVE' => '#2e7d32', 'REJECTED' => '#c62828', default => '#b45309' };
                $statusLabel = match($l->status) { 'ACTIVE' => __('cbe_vendors.status_active'), 'REJECTED' => __('cbe_vendors.status_rejected'), default => __('cbe_vendors.status_pending') };
            @endphp
            <div class="cbeVLRow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:1.6fr 1.4fr 1.2fr 1.4fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $l->node_name }}">{{ $l->node_name }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $l->group_name }}</div>
                <div style="color:{{ $statusColor }}; font-weight:600; font-size:9.5px;">{{ $statusLabel }}</div>
                <div style="color:#9ca3af; font-size:9.5px;">{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('d M Y') }}</div>
            </div>
            @empty
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_vendors.no_entities') }}</div>
            @endforelse
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeVLPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeVLPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeVLPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    <script>
    (function () {
        var cbeVLCurrentPage = 1;
        var cbeVLTotalPages = {{ $totalPages }};
        window.cbeVLPageNav = function (dir) {
            var next = cbeVLCurrentPage + dir;
            if (next < 1 || next > cbeVLTotalPages) return;
            cbeVLCurrentPage = next;
            document.querySelectorAll('.cbeVLRow').forEach(function (row) {
                row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeVLCurrentPage) ? 'grid' : 'none';
            });
            document.getElementById('cbeVLPageLabel').textContent = cbeVLCurrentPage + ' / ' + cbeVLTotalPages + ' ({{ $total }})';
        };
    })();
    </script>
    @endif
</div>
@endsection
