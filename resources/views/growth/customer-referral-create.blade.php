@extends('layouts.dashboard')

@section('page-title', __('growth.log_customer_referral_title'))

@section('content')

@php
    $typeaheadRoute = match($agent->role) {
        'ADMIN' => 'admin.customers.typeahead',
        'GROUP_LEADER' => 'gl.customers.typeahead',
        'TEAM_LEADER' => 'tl.customers.typeahead',
        default => 'introducer.customers.typeahead',
    };
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.log_referral_subtitle') }}</div>
    </div>

    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0; margin-bottom:6px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('customer-referrals.store') }}" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; max-width:480px;">
        @csrf
        <div style="margin-bottom:10px; position:relative;">
            <div style="font-size:9px; color:#6b7280; margin-bottom:3px;">{{ __('growth.referring_customer_label') }} <span style="color:#e53935;">*</span></div>
            <input type="text" id="custSearch" placeholder="{{ __('growth.search_customers_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 8px; font-size:11px; box-sizing:border-box;">
            <input type="hidden" name="referring_customer_id" id="custId">
            <div id="custResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; max-height:160px; overflow-y:auto; z-index:20; box-shadow:0 4px 10px rgba(0,0,0,.1);"></div>
        </div>
        <div style="margin-bottom:10px;">
            <div style="font-size:9px; color:#6b7280; margin-bottom:3px;">{{ __('growth.referred_name_label') }} <span style="color:#e53935;">*</span></div>
            <input type="text" name="referred_name" value="{{ old('referred_name') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 8px; font-size:11px; box-sizing:border-box;">
        </div>
        <div style="margin-bottom:10px;">
            <div style="font-size:9px; color:#6b7280; margin-bottom:3px;">{{ __('growth.referred_contact_label') }} <span style="color:#e53935;">*</span></div>
            <input type="text" name="referred_contact" value="{{ old('referred_contact') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 8px; font-size:11px; box-sizing:border-box;">
        </div>
        <div style="margin-bottom:12px;">
            <div style="font-size:9px; color:#6b7280; margin-bottom:3px;">{{ __('growth.notes_label') }} {{ __('growth.optional_hint') }}</div>
            <textarea name="notes" id="referralNotesField" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 8px; font-size:11px; box-sizing:border-box; resize:none;">{{ old('notes') }}</textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'referralNotesField', 'carolynType' => 'customer_referral_note'])
        </div>
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('growth.log_referral_button') }}</button>
    </form>

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route('customer-referrals.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>
</div>

<script>
(function() {
    var box = document.getElementById('custSearch');
    var hidden = document.getElementById('custId');
    var results = document.getElementById('custResults');
    var timer = null;

    box.addEventListener('input', function() {
        clearTimeout(timer);
        var term = box.value.trim();
        hidden.value = '';
        if (term.length < 2) { results.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('{{ route($typeaheadRoute) }}?term=' + encodeURIComponent(term))
                .then(function(r) { return r.json(); })
                .then(function(list) {
                    if (!list.length) { results.style.display = 'none'; return; }
                    results.innerHTML = list.map(function(c) {
                        return '<div class="custResultRow" data-id="' + c.customer_id + '" data-name="' + c.full_name.replace(/"/g,'') + '" style="padding:6px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;">' + c.full_name + ' <span style="color:#9ca3af;">(' + (c.phone || c.email || '') + ')</span></div>';
                    }).join('');
                    results.style.display = 'block';
                    Array.prototype.forEach.call(results.querySelectorAll('.custResultRow'), function(row) {
                        row.addEventListener('click', function() {
                            hidden.value = row.getAttribute('data-id');
                            box.value = row.getAttribute('data-name');
                            results.style.display = 'none';
                        });
                    });
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!box.contains(e.target) && !results.contains(e.target)) { results.style.display = 'none'; }
    });
})();
</script>
@endsection
