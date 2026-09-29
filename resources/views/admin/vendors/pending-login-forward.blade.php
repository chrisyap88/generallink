@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.forward_to_director_title'))

@section('content')

{{-- NEW 12 Aug 2026 (Task #106) — per Chris: "remove all popup content,
     new screen with proper tab." Was fwdModal-{vendorId}, a popup opened
     from the (also now-removed) detail modal. Same form, just a real
     page now. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px;">
        <a href="{{ route('admin.vendors.approvals.show', $vendor->vendor_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700; white-space:nowrap; display:inline-block;">{{ __('network.prev') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex-shrink:0; font-size:13px; font-weight:700; color:#6D28D9; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f3f4f6;">{{ __('admin_vendors.forward_heading', ['name' => $vendor->vendor_name]) }}</div>

        @php
            $fwdEntityLabel = \App\Services\VendorDocumentChecklistService::ENTITY_TYPES[$vendor->entity_type] ?? $vendor->entity_type ?? __('admin_vendors.entity_type_not_set');
            $fwdDdLine = $dd
                ? __('admin_vendors.forward_msg_dd_present', ['recommendation' => \App\Services\VendorDueDiligenceService::scoreBreakdown($dd)['recommendation_label'], 'sanctions' => $dd->sanctions_note ?? '', 'negnews' => $dd->negative_news_note ?? ''])
                : __('admin_vendors.forward_msg_dd_absent');
            $fwdMessage = __('admin_vendors.forward_msg_line1', ['name' => $vendor->vendor_name, 'entity' => $fwdEntityLabel])
                . "\n\n" . $fwdDdLine
                . "\n\n" . __('admin_vendors.forward_msg_closing');
        @endphp
        <form method="POST" action="{{ route('admin.vendors.pending-logins.forward-director', $vendor->vendor_id) }}" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:6px;">
            @csrf
            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_vendors.director_email_label') }}</label>
                <input type="email" name="director_email" value="{{ $directorEmail }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_vendors.subject_label') }}</label>
                <input type="text" name="subject" value="{{ __('admin_vendors.subject_default', ['name' => $vendor->vendor_name]) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="flex:1; min-height:0; display:flex; flex-direction:column;">
                <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_vendors.message_label') }}</label>
                <textarea name="message" id="fwdMessageBody" required style="flex:1; min-height:100px; width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box; resize:none;">{{ $fwdMessage }}</textarea>
            </div>
            @include('partials.carolyn-write-assist', ['carolynBodyId' => 'fwdMessageBody', 'carolynType' => 'vendor_onboarding_message'])
            <button type="submit" style="background:#6D28D9; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10.5px; font-weight:600; cursor:pointer; align-self:flex-end;">{{ __('admin_vendors.send_button') }}</button>
        </form>
    </div>
</div>

@endsection
