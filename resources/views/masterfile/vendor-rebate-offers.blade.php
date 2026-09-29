@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('vendor.admin_offers_page_title'))

@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:6px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:6px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="display:grid; grid-template-columns:1fr 340px; gap:8px; flex:1; min-height:0;">

        {{-- LEFT: List --}}
        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
            <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
                <div style="font-size:13px; font-weight:700; color:#1565C0;">💸 {{ __('vendor.admin_offers_heading') }}</div>
                <div style="font-size:10.5px; color:#6b7280;">{{ __('vendor.admin_offers_intro') }}</div>
            </div>

            @if($offers->isEmpty())
            <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
                <div>
                    <div style="font-size:32px; margin-bottom:8px;">💸</div>
                    <div>{{ __('vendor.no_rebate_offers_yet_note') }}</div>
                    <div style="font-size:10px; margin-top:4px;">{{ __('vendor.add_one_using_form_note') }}</div>
                </div>
            </div>
            @else
            <div style="flex:1; overflow-y:auto; min-height:0;">
                <table style="width:100%; border-collapse:collapse; font-size:10.5px; table-layout:fixed;">
                    <colgroup>
                        <col style="width:18%;"><col style="width:18%;"><col style="width:32%;">
                        <col style="width:12%;"><col style="width:9%;"><col style="width:11%;">
                    </colgroup>
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0; z-index:1;">
                            <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('vendor.col_vendor') }}</th>
                            <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('vendor.col_product') }}</th>
                            <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('vendor.col_rebate_details') }}</th>
                            <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('vendor.col_valid_until') }}</th>
                            <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('network.status') }}</th>
                            <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('growth.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($offers as $offer)
                        <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                            <td style="padding:6px; font-weight:600; color:#111827; word-break:break-word;">{{ $offer->vendor_name }}</td>
                            <td style="padding:6px; word-break:break-word;">{{ $offer->product_name ?? __('vendor.all_products_option') }}</td>
                            <td style="padding:6px; word-break:break-word;">{{ $offer->rebate_details }}</td>
                            <td style="padding:6px; text-align:center; font-size:9.5px;">{{ $offer->valid_until ? \Carbon\Carbon::parse($offer->valid_until)->format('d M Y') : __('vendor.no_expiry_label') }}</td>
                            <td style="padding:6px; text-align:center;">
                                <span style="background:{{ $offer->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $offer->is_active ? '#065f46' : '#991b1b' }}; font-size:9.5px; font-weight:600; padding:2px 8px; border-radius:20px; white-space:nowrap;">{{ $offer->is_active ? __('growth.active_badge') : __('growth.inactive_badge') }}</span>
                            </td>
                            <td style="padding:6px; text-align:center; white-space:nowrap;">
                                <form method="POST" action="{{ route('admin.masterfile.rebate-offers.toggle', $offer->rebate_offer_id) }}" style="display:inline;">
                                    @csrf @method('PATCH')
                                    <button type="submit" style="background:{{ $offer->is_active ? '#fee2e2' : '#d1fae5' }}; color:{{ $offer->is_active ? '#991b1b' : '#065f46' }}; border:none; border-radius:6px; padding:3px 8px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ $offer->is_active ? __('growth.deactivate_button') : __('growth.activate_button_alt') }}</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
                @if($offers->onFirstPage())
                    <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</span>
                @else
                    <a href="{{ $offers->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:11px; color:#6b7280;">{!! __('vendor.showing_page_template', ['first' => '<strong>'.$offers->firstItem().'</strong>', 'last' => '<strong>'.$offers->lastItem().'</strong>', 'totalRecords' => '<strong>'.$offers->total().'</strong>', 'current' => $offers->currentPage(), 'totalPages' => $offers->lastPage()]) !!}</span>
                @if($offers->hasMorePages())
                    <a href="{{ $offers->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</a>
                @else
                    <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</span>
                @endif
            </div>
            @endif
        </div>

        {{-- RIGHT: Add Rebate Offer Form --}}
        <div style="background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; min-height:0; overflow-y:auto;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:12px; padding-bottom:6px; border-bottom:2px solid #e0f2fe;">{{ __('vendor.add_rebate_offer_heading') }}</div>

            <form method="POST" action="{{ route('admin.masterfile.rebate-offers.store') }}" autocomplete="off" id="rebateForm"
                onsubmit="if(!document.getElementById('rbVendorId').value){alert({{ json_encode(__('vendor.pick_vendor_alert')) }});return false;}">
                @csrf

                <div style="position:relative; margin-bottom:10px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('vendor.col_vendor') }} <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="rbVendorBox" autocomplete="off" placeholder="{{ __('vendor.vendor_search_placeholder') }}" value="{{ old('vendor_id') ? ($vendorNameOld ?? '') : '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                    <input type="hidden" name="vendor_id" id="rbVendorId" value="{{ old('vendor_id') }}">
                    <div id="rbVendorList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:200px; overflow-y:auto;"></div>
                </div>

                <div style="position:relative; margin-bottom:10px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('vendor.col_product') }} <span class="opt" style="font-weight:400; color:#9ca3af;">{{ __('profile.optional_note') }}</span></label>
                    <input type="text" id="rbProductBox" autocomplete="off" placeholder="{{ __('vendor.product_search_placeholder') }}" value="" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                    <input type="hidden" name="product_id" id="rbProductId" value="{{ old('product_id') }}">
                    <div id="rbProductList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:200px; overflow-y:auto;"></div>
                    <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('vendor.leave_blank_vendor_generally_note') }}</div>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('vendor.col_rebate_details') }} <span style="color:#dc2626;">*</span></label>
                    <textarea name="rebate_details" id="rebateDetailsAdmin" rows="4" required maxlength="2000" placeholder="{{ __('vendor.rebate_details_admin_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box; resize:none;">{{ old('rebate_details') }}</textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'rebateDetailsAdmin', 'carolynType' => 'vendor_rebate_offer'])
                    <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('vendor.exactly_what_agents_see_note') }}</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.valid_from_label') }}</label>
                        <input type="date" name="valid_from" value="{{ old('valid_from') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('vendor.col_valid_until') }}</label>
                        <input type="date" name="valid_until" value="{{ old('valid_until') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('vendor.leave_blank_no_expiry_note') }}</div>
                    </div>
                </div>

                <div style="background:#eff6ff; border-radius:8px; padding:10px; font-size:10px; color:#1e40af; border:1px solid #bfdbfe; margin-bottom:10px;">
                    {{ __('vendor.agents_never_see_contact_note') }}
                </div>

                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:10px 16px; font-size:12px; font-weight:600; cursor:pointer; width:100%;">{{ __('vendor.add_rebate_offer_heading') }}</button>
            </form>
        </div>

    </div>
</div>

@push('scripts')
<script>
(function() {
    var VENDOR_URL  = '{{ route('admin.masterfile.commissions.vendor-typeahead') }}';
    var PRODUCT_URL = '{{ route('admin.masterfile.commissions.product-typeahead') }}';

    function initTypeahead(boxId, hiddenId, listId, url, labelField, idField, vendorHiddenId) {
        var box = document.getElementById(boxId);
        if (!box) { return; }
        var hidden = document.getElementById(hiddenId);
        var list = document.getElementById(listId);
        var timer = null;

        box.addEventListener('input', function() {
            hidden.value = '';
            clearTimeout(timer);
            var q = box.value.trim();
            if (q.length < 1) { list.style.display = 'none'; return; }
            timer = setTimeout(function() {
                var fetchUrl = url + '?q=' + encodeURIComponent(q);
                if (vendorHiddenId) {
                    var vId = document.getElementById(vendorHiddenId).value || '';
                    fetchUrl += '&vendor_id=' + encodeURIComponent(vId);
                }
                fetch(fetchUrl)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        list.innerHTML = '';
                        if (!data.length) { list.style.display = 'none'; return; }
                        var rect = box.getBoundingClientRect();
                        list.style.left = rect.left + 'px';
                        list.style.top = rect.bottom + 'px';
                        list.style.width = Math.max(rect.width, 200) + 'px';
                        data.forEach(function(item) {
                            var row = document.createElement('div');
                            row.style.cssText = 'padding:5px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            row.textContent = item[labelField];
                            row.addEventListener('mouseover', function() { row.style.background = '#EBF5FB'; });
                            row.addEventListener('mouseout', function() { row.style.background = ''; });
                            row.addEventListener('mousedown', function() {
                                box.value = item[labelField];
                                hidden.value = item[idField];
                                list.style.display = 'none';
                            });
                            list.appendChild(row);
                        });
                        list.style.display = 'block';
                    });
            }, 200);
        });

        document.addEventListener('click', function(e) {
            if (e.target !== box) { list.style.display = 'none'; }
        });
    }

    initTypeahead('rbVendorBox',  'rbVendorId',  'rbVendorList',  VENDOR_URL,  'vendor_name',  'vendor_id');
    initTypeahead('rbProductBox', 'rbProductId', 'rbProductList', PRODUCT_URL, 'product_name', 'product_id', 'rbVendorId');
})();
</script>
@endpush
@endsection
