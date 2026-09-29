@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile_hub.vendor_hub_title'))

@section('content')
@php
$vmfAgent = auth('agent')->user();
$vmfIsAdmin = $vmfAgent && $vmfAgent->role === 'ADMIN';
$vmfItems = [];
if ($vmfIsAdmin) {
    $vmfItems[] = ['route' => route('admin.masterfile.cbe-vendors'), 'label' => __('cbe_vendors.page_title')];
}
$vmfItems[] = ['route' => route('admin.masterfile.cbe-vendor-approvals'), 'label' => __('cbe_vendors.approvals_page_title')];
$vmfItems[] = ['route' => route('admin.masterfile.cbe-marketplace-listings'), 'label' => __('cbe_marketplace.listings_page_title')];
$vmfItems[] = ['route' => route('admin.masterfile.cbe-marketplace-orders'), 'label' => __('cbe_marketplace.orders_page_title')];
$vmfItems[] = ['route' => route('admin.masterfile.cbe-marketplace-campaigns'), 'label' => __('cbe_marketplace.campaigns_page_title')];
@endphp
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:6px;">
        <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('cbe_masterfile_hub.vendor_hub_title') }}</div>
        <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('cbe_masterfile_hub.vendor_hub_intro') }}</div>
    </div>

    @php $pageSize = 8; $total = count($vmfItems); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            @foreach($vmfItems as $i => $item)
            <a href="{{ $item['route'] }}" class="cbeVMFRow" data-page="{{ intdiv($i, $pageSize) + 1 }}" style="display:{{ $i < $pageSize ? 'flex' : 'none' }}; align-items:center; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #f3f4f6; font-size:11px; color:#374151; text-decoration:none;">
                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $item['label'] }}</span>
                <span style="color:#1565C0; font-weight:600; font-size:9.5px; flex-shrink:0; margin-left:10px;">{{ __('cbe_masterfile_hub.go_link') }} ›</span>
            </a>
            @endforeach
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeVMFPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeVMFPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeVMFPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    <script>
    (function () {
        var cbeVMFCurrentPage = 1;
        var cbeVMFTotalPages = {{ $totalPages }};
        window.cbeVMFPageNav = function (dir) {
            var next = cbeVMFCurrentPage + dir;
            if (next < 1 || next > cbeVMFTotalPages) return;
            cbeVMFCurrentPage = next;
            document.querySelectorAll('.cbeVMFRow').forEach(function (row) {
                row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeVMFCurrentPage) ? 'flex' : 'none';
            });
            document.getElementById('cbeVMFPageLabel').textContent = cbeVMFCurrentPage + ' / ' + cbeVMFTotalPages + ' ({{ $total }})';
        };
    })();
    </script>
    @endif
</div>
@endsection
