{{-- Shared submission form — included by both the Vendor Portal and the
     agent-facing Submit Partner Offer screen. $isVendor/$submitter/$vendors
     come from OfferRequestController::create(). --}}
<form method="POST" action="{{ $isVendor ? route('vendor.offers.store') : route('offer-requests.store') }}" enctype="multipart/form-data">
    @csrf

    @if(!$isVendor)
        <div style="margin-bottom:10px;">
            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('sales_transactions.field_vendor_label') }}</label>
            <select name="vendor_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; background:#fff;">
                <option value="">{{ __('offer_requests.select_active_vendor_option') }}</option>
                @foreach($vendors as $v)
                <option value="{{ $v->vendor_id }}" {{ old('vendor_id') === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }} ({{ $v->vendor_code }})</option>
                @endforeach
            </select>
            <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('offer_requests.vendor_not_listed_note') }}</div>
        </div>
    @endif

    <div style="margin-bottom:10px;">
        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('offer_requests.field_offer_title_label') }}</label>
        <input type="text" name="title" id="offerTitle" maxlength="150" value="{{ old('title') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box;">
    </div>

    <div style="margin-bottom:10px;">
        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('offer_requests.field_offer_details_label') }}</label>
        <textarea name="body" id="offerBody" maxlength="3000" rows="5" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box; resize:vertical; font-family:inherit;">{{ old('body') }}</textarea>
        @include('partials.carolyn-write-helper', [
            'uid' => 'offer',
            'bodyFieldId' => 'offerBody',
            'titleFieldId' => 'offerTitle',
            'contentType' => 'promotion_offer',
            'assistUrl' => $isVendor ? route('vendor.write-assist') : route('ai-write-assist'),
        ])
    </div>

    <div style="display:flex; gap:12px; margin-bottom:10px;">
        <div style="flex:1;">
            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('offer_requests.field_offer_expires_label') }}</label>
            <input type="date" name="expiry_date" value="{{ old('expiry_date') }}" required min="{{ now()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box;">
        </div>
        <div style="flex:1;">
            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('offer_requests.field_attachment_optional_label') }}</label>
            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; font-size:10.5px;">
        </div>
    </div>

    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('offer_requests.submit_for_approval_button') }}</button>
</form>
