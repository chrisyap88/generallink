@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_marketplace.listings_page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:5px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('cbe_marketplace.listings_page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('cbe_marketplace.listings_intro') }}</div>
        </div>
        <span onclick="cbeMLShowForm()" style="background:#1B5E20; color:#fff; border-radius:5px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_marketplace.add_listing_button') }}</span>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; color:#1B5E20; border-radius:5px; padding:6px 10px; font-size:10px; margin-bottom:6px; flex-shrink:0;">{{ session('success') }}</div>
    @endif

    @php $pageSize = 7; $total = $listings->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1.8fr 1.3fr 0.9fr 0.8fr 0.9fr 0.9fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">
            <div>{{ __('cbe_marketplace.col_title') }}</div>
            <div>{{ __('cbe_marketplace.col_seller') }}</div>
            <div>{{ __('cbe_marketplace.col_price') }}</div>
            <div>{{ __('cbe_marketplace.col_stock') }}</div>
            <div>{{ __('cbe_marketplace.col_status') }}</div>
            <div></div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($listings as $li)
            <div class="cbeMLRow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:1.8fr 1.3fr 0.9fr 0.8fr 0.9fr 0.9fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $li->title }}">{{ $li->title }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $li->vendor_name ?? __('cbe_marketplace.seller_entity') }}</div>
                <div>{{ number_format($li->price, 2) }}</div>
                <div style="color:#6b7280;">{{ $li->stock_quantity !== null ? $li->stock_quantity : __('cbe_marketplace.no_stock_limit') }}</div>
                <div style="color:{{ $li->status === 'ACTIVE' ? '#2e7d32' : '#9ca3af' }}; font-weight:600; font-size:9.5px;">{{ $li->status === 'ACTIVE' ? __('cbe_marketplace.status_active') : __('cbe_marketplace.status_inactive') }}</div>
                <div>
                    <form method="POST" action="{{ route('admin.masterfile.cbe-marketplace-listings.toggle', $li->listing_id) }}">
                        @csrf
                        <input type="hidden" name="node" value="{{ $node->node_id }}">
                        <button type="submit" style="background:#f3f4f6; color:#374151; border:none; border-radius:4px; padding:3px 8px; font-size:9px; font-weight:600; cursor:pointer;">{{ $li->status === 'ACTIVE' ? __('cbe_marketplace.btn_deactivate') : __('cbe_marketplace.btn_activate') }}</button>
                    </form>
                </div>
            </div>
            @empty
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_marketplace.no_listings') }}</div>
            @endforelse
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeMLPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeMLPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeMLPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    @endif
</div>

{{-- Add Listing — overlay form, not a jump screen. --}}
<div id="cbeMLFormOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:50; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:18px 20px; width:380px; max-width:92vw; box-sizing:border-box;">
        <div style="font-size:12.5px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('cbe_marketplace.form_title') }}</div>
        <form method="POST" action="{{ route('admin.masterfile.cbe-marketplace-listings.store', $node->node_id) }}">
            @csrf
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_seller') }}</label>
                    <select name="vendor_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_marketplace.seller_entity') }}</option>
                        @foreach($approvedVendors as $av)
                        <option value="{{ $av->vendor_id }}">{{ $av->vendor_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_title') }} *</label>
                    <input type="text" name="title" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_title_zh') }}</label>
                    <input type="text" name="title_zh" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_description') }}</label>
                    <input type="text" name="description" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_price') }} *</label>
                        <input type="number" step="0.01" min="0" name="price" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_stock_quantity') }}</label>
                        <input type="number" min="0" name="stock_quantity" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end;">
                <span onclick="cbeMLHideForm()" style="background:#f3f4f6; color:#374151; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_marketplace.btn_cancel') }}</span>
                <button type="submit" style="background:#1B5E20; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('cbe_marketplace.btn_save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function cbeMLShowForm(){ document.getElementById('cbeMLFormOverlay').style.display = 'flex'; }
function cbeMLHideForm(){ document.getElementById('cbeMLFormOverlay').style.display = 'none'; }
@if($errors->any())
document.addEventListener('DOMContentLoaded', function(){ cbeMLShowForm(); });
@endif

(function () {
    var cbeMLCurrentPage = 1;
    var cbeMLTotalPages = {{ $totalPages }};
    window.cbeMLPageNav = function (dir) {
        var next = cbeMLCurrentPage + dir;
        if (next < 1 || next > cbeMLTotalPages) return;
        cbeMLCurrentPage = next;
        document.querySelectorAll('.cbeMLRow').forEach(function (row) {
            row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeMLCurrentPage) ? 'grid' : 'none';
        });
        document.getElementById('cbeMLPageLabel').textContent = cbeMLCurrentPage + ' / ' + cbeMLTotalPages + ' ({{ $total }})';
    };
})();
</script>
@endsection
