@extends('layouts.dashboard')

@section('page-title', __('rebate_offers.page_title'))

@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    {{-- Search bar --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:10px 16px; flex-shrink:0;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:2px;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('rebate_offers.header_title') }}</div>
            @include('partials.feature-video-widget', ['featureKey' => 'REBATE_OFFERS'])
        </div>
        <div style="font-size:10px; color:#6b7280; margin-bottom:8px;">{{ __('rebate_offers.search_scope_note') }}</div>
        <form method="GET" action="{{ route('rebate-offers.search') }}" style="display:flex; gap:8px;">
            <div style="position:relative; flex:1;">
                <input type="text" name="q" id="roSearchInput" autocomplete="off" value="{{ $q }}" placeholder="{{ __('rebate_offers.search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                <div id="roSearchDropdown" style="display:none; position:absolute; top:100%; left:0; width:100%; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.12); max-height:220px; overflow-y:auto; z-index:50;"></div>
            </div>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('rebate_offers.search_button') }}</button>
            @if($q)
            <a href="{{ route('rebate-offers.search') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:500; white-space:nowrap; display:flex; align-items:center;">{{ __('dashboard.clear_word') }}</a>
            @endif
        </form>
    </div>

    {{-- Results --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        @if($offers->isEmpty())
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
            <div>
                <div style="font-size:32px; margin-bottom:8px;">💸</div>
                <div>@if($q) {{ __('rebate_offers.no_offers_match_query', ['q' => $q]) }} @else {{ __('rebate_offers.no_active_offers_note') }} @endif</div>
                <div style="font-size:10px; margin-top:4px;">{{ __('rebate_offers.not_finding_note') }}</div>
            </div>
        </div>
        @else
        {{-- CHANGED 13 Aug 2026 per Chris: "incorporate prev and next...
             all information in one screen no scroll" — was
             overflow-y:auto (an inner scrollbar), now overflow:hidden to
             match every other list screen; paginate(6) in
             RebateOfferSearchController is already sized to fit. --}}
        <div style="flex:1; overflow:hidden; min-height:0; padding:10px 12px; display:flex; flex-direction:column; gap:8px;">
            @foreach($offers as $offer)
            <div style="border:1px solid #e0f2fe; border-radius:9px; padding:10px 14px; display:flex; align-items:flex-start; gap:12px;">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                        <span style="font-size:12px; font-weight:700; color:#111827;">{{ $offer->vendor_name }}</span>
                        @if($offer->rebate_program_number)
                        <span style="background:#e0f2fe; color:#0369a1; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ $offer->rebate_program_number }}</span>
                        @endif
                        @if($offer->product_name)
                        <span style="background:#e0f2fe; color:#0369a1; font-size:9.5px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ $offer->product_name }}</span>
                        @endif
                        @if($offer->valid_until)
                        <span style="font-size:9.5px; color:#9ca3af;">{{ __('rebate_offers.valid_until_prefix', ['date' => \Carbon\Carbon::parse($offer->valid_until)->format('d M Y')]) }}</span>
                        @endif
                    </div>
                    {{-- NEW 13 Aug 2026 — own tiny internal scroll only if
                         this one description is unusually long (same
                         safeguard already used on review comments), so
                         the outer list's overflow:hidden above can never
                         silently cut real text off. --}}
                    <div style="font-size:10.5px; color:#374151; line-height:1.4; word-break:break-word; max-height:58px; overflow-y:auto;">{{ $offer->rebate_details }}</div>
                </div>
                <div style="flex-shrink:0; display:flex; flex-direction:column; gap:5px; width:130px;">
                    <a href="{{ route('vendor-reviews.index', $offer->vendor_id) }}" style="background:#fff; color:#b45309; border:1px solid #fbbf24; text-decoration:none; border-radius:6px; padding:6px 8px; font-size:9.5px; font-weight:600; text-align:center; width:100%; box-sizing:border-box;">{{ __('rebate_offers.reviews_link') }}</a>
                    <button type="button" onclick="enquireAdmin({{ json_encode($offer->vendor_name) }}, {{ json_encode($offer->product_name) }})" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 8px; font-size:9.5px; font-weight:600; cursor:pointer; width:100%;">{{ __('rebate_offers.enquire_admin_button') }}</button>
                    <button type="button" onclick="askCarolynAboutOffer({{ json_encode($offer->vendor_name) }}, {{ json_encode($offer->product_name) }})" style="background:#fff; color:#1565C0; border:1px solid #1565C0; border-radius:6px; padding:6px 8px; font-size:9.5px; font-weight:600; cursor:pointer; width:100%;">{{ __('rebate_offers.ask_carolyn_button') }}</button>
                </div>
            </div>
            @endforeach
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($offers->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $offers->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{!! __('rebate_offers.showing_x_to_y_of_z_records', ['first' => '<strong>'.$offers->firstItem().'</strong>', 'last' => '<strong>'.$offers->lastItem().'</strong>', 'total' => '<strong>'.$offers->total().'</strong>', 'current' => $offers->currentPage(), 'lastpage' => $offers->lastPage()]) !!}</span>
            @if($offers->hasMorePages())
                <a href="{{ $offers->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>

<script>
// "Enquire with Admin" — deep-links into Help Desk's New Message tab,
// pre-filled and pre-addressed to Admin, so the agent isn't retyping
// context Admin can already see (Chris: "affiliate can click to admin
// for further enquiry").
function enquireAdmin(vendorName, productName) {
    var subjectTpl = @json(__('rebate_offers.enquiry_subject_js'));
    var subjectProductSuffixTpl = @json(__('rebate_offers.subject_product_slash_suffix_js'));
    var bodyTpl = @json(__('rebate_offers.enquiry_body_js'));
    var bodyProductSuffixTpl = @json(__('rebate_offers.body_product_paren_suffix_js'));
    var subject = subjectTpl.replace('__VENDOR__', vendorName) + (productName ? subjectProductSuffixTpl.replace('__PRODUCT__', productName) : '');
    var body = bodyTpl.replace('__VENDOR__', vendorName).replace('__PRODUCT_PART__', productName ? bodyProductSuffixTpl.replace('__PRODUCT__', productName) : '');
    var url = '{{ route('help-desk.index') }}'
        + '?prefill_admin=1'
        + '&prefill_subject=' + encodeURIComponent(subject)
        + '&prefill_body=' + encodeURIComponent(body);
    window.location.href = url;
}

// "Ask Carolyn" — opens the existing AI Assistant widget already present
// on this layout and sends a pre-filled question about this specific offer.
function askCarolynAboutOffer(vendorName, productName) {
    var panel = document.getElementById('aiAssistantPanel');
    if (panel && panel.style.display !== 'flex' && typeof aiAssistantToggle === 'function') {
        aiAssistantToggle();
    }
    var questionTpl = @json(__('rebate_offers.ask_carolyn_question_js'));
    var questionProductSuffixTpl = @json(__('rebate_offers.question_product_for_suffix_js'));
    var question = questionTpl.replace('__VENDOR__', vendorName).replace('__PRODUCT_PART__', productName ? questionProductSuffixTpl.replace('__PRODUCT__', productName) : '');
    setTimeout(function () {
        var input = document.getElementById('aiAssistantInput');
        if (input) { input.value = question; }
        if (typeof aiAssistantSend === 'function') { aiAssistantSend(); }
    }, 350);
}
</script>
<script>
(function () {
    var input = document.getElementById('roSearchInput');
    var dropdown = document.getElementById('roSearchDropdown');
    if (!input || !dropdown) { return; }
    var timer = null;

    function hide() { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }

    function render(items) {
        if (!items.length) { hide(); return; }
        dropdown.innerHTML = items.map(function (item) {
            var label = item.vendor_name + (item.product_name ? ' \u2014 ' + item.product_name : '');
            return '<div class="ro-suggestion" data-label="' + label.replace(/"/g, '&quot;') + '" ' +
                'style="padding:6px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;">' + label + '</div>';
        }).join('');
        dropdown.style.display = 'block';
        Array.prototype.forEach.call(dropdown.querySelectorAll('.ro-suggestion'), function (row) {
            row.onmousedown = function () {
                input.value = row.getAttribute('data-label').split(' \u2014 ')[0];
                hide();
                input.form.submit();
            };
        });
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        if (timer) { clearTimeout(timer); }
        if (q.length < 1) { hide(); return; }
        timer = setTimeout(function () {
            fetch('{{ route('rebate-offers.typeahead') }}?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); }).then(render).catch(hide);
        }, 250);
    });

    input.addEventListener('blur', function () { setTimeout(hide, 100); });
})();
</script>
@endsection
