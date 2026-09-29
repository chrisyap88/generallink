@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_marketplace.orders_page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:5px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('cbe_marketplace.orders_page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('cbe_marketplace.orders_intro') }}</div>
        </div>
        <span onclick="cbeMOShowForm()" style="background:#1B5E20; color:#fff; border-radius:5px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_marketplace.add_order_button') }}</span>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; color:#1B5E20; border-radius:5px; padding:6px 10px; font-size:10px; margin-bottom:6px; flex-shrink:0;">{{ session('success') }}</div>
    @endif
    @if($errors->has('buyer'))
    <div style="background:#fdecea; color:#c62828; border-radius:5px; padding:6px 10px; font-size:10px; margin-bottom:6px; flex-shrink:0;">{{ $errors->first('buyer') }}</div>
    @endif

    @php $pageSize = 7; $total = $orders->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1.6fr 1.4fr 0.7fr 1fr 1.1fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">
            <div>{{ __('cbe_marketplace.col_order_item') }}</div>
            <div>{{ __('cbe_marketplace.col_buyer') }}</div>
            <div>{{ __('cbe_marketplace.col_quantity') }}</div>
            <div>{{ __('cbe_marketplace.col_total') }}</div>
            <div>{{ __('cbe_marketplace.col_payment_status') }}</div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($orders as $o)
            @php
                $payColor = match($o->payment_status) { 'PAID' => '#2e7d32', 'CANCELLED' => '#c62828', default => '#b45309' };
                $payLabel = match($o->payment_status) { 'PAID' => __('cbe_marketplace.payment_paid'), 'CANCELLED' => __('cbe_marketplace.payment_cancelled'), default => __('cbe_marketplace.payment_pending') };
                $buyerLabel = $o->customer_name ?? $o->buyer_name;
            @endphp
            <div class="cbeMORow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:1.6fr 1.4fr 0.7fr 1fr 1.1fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $o->title }}">{{ $o->title }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $buyerLabel }}">{{ $buyerLabel }}</div>
                <div>{{ $o->quantity }}</div>
                <div>{{ number_format($o->total_amount, 2) }}</div>
                <div style="color:{{ $payColor }}; font-weight:600; font-size:9.5px;">{{ $payLabel }}</div>
            </div>
            @empty
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_marketplace.no_orders') }}</div>
            @endforelse
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeMOPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeMOPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeMOPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    @endif
</div>

{{-- Record Order — overlay form, not a jump screen. --}}
<div id="cbeMOFormOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:50; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:18px 20px; width:380px; max-width:92vw; box-sizing:border-box;">
        <div style="font-size:12.5px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('cbe_marketplace.order_form_title') }}</div>
        <form method="POST" action="{{ route('admin.masterfile.cbe-marketplace-orders.store', $node->node_id) }}">
            @csrf
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_listing') }} *</label>
                    <select name="listing_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">—</option>
                        @foreach($activeListings as $al)
                        <option value="{{ $al->listing_id }}">{{ $al->title }} (RM {{ number_format($al->price, 2) }})</option>
                        @endforeach
                    </select>
                </div>
                <div style="position:relative;">
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_buyer_search') }}</label>
                    <input type="text" id="cbeMOBuyerSearch" autocomplete="off" placeholder="{{ __('cbe_marketplace.field_buyer_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    <input type="hidden" name="buyer_customer_id" id="cbeMOBuyerCustomerId">
                    <div id="cbeMOBuyerResults" style="display:none; position:absolute; left:0; right:0; top:100%; background:#fff; border:1px solid #d1d5db; border-radius:5px; z-index:5; max-height:120px; overflow-y:auto;"></div>
                </div>
                <div id="cbeMOBuyerPicked" style="display:none; font-size:9.5px; color:#1B5E20; font-weight:600;"></div>
                <div style="font-size:9px; color:#9ca3af; margin-top:-2px;">{{ __('cbe_marketplace.buyer_or_walkin') }}</div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_buyer_name') }}</label>
                    <input type="text" name="buyer_name" id="cbeMOBuyerName" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_buyer_phone') }}</label>
                        <input type="text" name="buyer_phone" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_buyer_email') }}</label>
                        <input type="email" name="buyer_email" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_quantity') }} *</label>
                    <input type="number" min="1" name="quantity" value="1" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end;">
                <span onclick="cbeMOHideForm()" style="background:#f3f4f6; color:#374151; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_marketplace.btn_cancel') }}</span>
                <button type="submit" style="background:#1B5E20; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('cbe_marketplace.btn_save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function cbeMOShowForm(){ document.getElementById('cbeMOFormOverlay').style.display = 'flex'; }
function cbeMOHideForm(){ document.getElementById('cbeMOFormOverlay').style.display = 'none'; }
@if($errors->any())
document.addEventListener('DOMContentLoaded', function(){ cbeMOShowForm(); });
@endif

(function () {
    var searchBox = document.getElementById('cbeMOBuyerSearch');
    var resultsBox = document.getElementById('cbeMOBuyerResults');
    var hiddenId = document.getElementById('cbeMOBuyerCustomerId');
    var pickedLabel = document.getElementById('cbeMOBuyerPicked');
    var buyerNameInput = document.getElementById('cbeMOBuyerName');
    var searchTimer = null;

    searchBox.addEventListener('input', function () {
        hiddenId.value = '';
        pickedLabel.style.display = 'none';
        var q = searchBox.value.trim();
        if (searchTimer) clearTimeout(searchTimer);
        if (q.length < 2) { resultsBox.style.display = 'none'; return; }
        searchTimer = setTimeout(function () {
            fetch('{{ route('admin.masterfile.cbe-marketplace-orders.customer-typeahead', $node->node_id) }}?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (list) {
                    resultsBox.innerHTML = '';
                    if (!list.length) { resultsBox.style.display = 'none'; return; }
                    list.forEach(function (item) {
                        var row = document.createElement('div');
                        row.textContent = item.label;
                        row.style.cssText = 'padding:5px 8px; font-size:10px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        row.onclick = function () {
                            hiddenId.value = item.customer_id;
                            searchBox.value = item.label;
                            pickedLabel.textContent = '✓ ' + item.label;
                            pickedLabel.style.display = 'block';
                            resultsBox.style.display = 'none';
                            buyerNameInput.value = '';
                        };
                        resultsBox.appendChild(row);
                    });
                    resultsBox.style.display = 'block';
                });
        }, 250);
    });

    var cbeMOCurrentPage = 1;
    var cbeMOTotalPages = {{ $totalPages }};
    window.cbeMOPageNav = function (dir) {
        var next = cbeMOCurrentPage + dir;
        if (next < 1 || next > cbeMOTotalPages) return;
        cbeMOCurrentPage = next;
        document.querySelectorAll('.cbeMORow').forEach(function (row) {
            row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeMOCurrentPage) ? 'grid' : 'none';
        });
        document.getElementById('cbeMOPageLabel').textContent = cbeMOCurrentPage + ' / ' + cbeMOTotalPages + ' ({{ $total }})';
    };
})();
</script>
@endsection
