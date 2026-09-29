@extends('layouts.vendor')

@section('page-title', __('vendor.my_profile_title'))

@section('content')
{{-- UPDATED 9 Aug 2026 — per Chris: this screen used to show only a
     handful of fields (name/code/industry/vendor_type + editable contact).
     Now surfaces EVERYTHING captured at registration — entity type,
     Nature of Business, all 3 Contact Persons, and the uploaded document
     list with verification status — in a 2x2 grid so it still fits one
     screen, no scroll. Contact 1 moved into the locked panel since its
     email doubles as the login ID. --}}
<div style="height:100%; display:flex; flex-direction:column; padding:14px 22px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <p style="font-size:10px; color:#718096; margin:2px 0 0;">{{ __('vendor.company_label') }} <strong style="color:#374151;">{{ $vendor->vendor_name }}</strong> ({{ $vendor->vendor_code }})</p>
    </div>

    <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; grid-template-rows:1fr 1fr; gap:10px; overflow:hidden;">

        {{-- 1. RESTRICTED — legal identity, read-only --}}
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; overflow:hidden;">
            <div style="font-size:10.5px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('vendor.company_identity_heading') }} <span style="font-weight:400; color:#9ca3af; font-size:9px;">{{ __('vendor.locked_suffix') }}</span></div>
            <div style="display:flex; flex-direction:column; gap:5px;">
                <div>
                    <div style="font-size:8.5px; color:#9ca3af; text-transform:uppercase;">{{ __('vendor.registered_name_label') }}</div>
                    <div style="font-size:10.5px; color:#374151; font-weight:600;">{{ $vendor->vendor_name }}</div>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#9ca3af; text-transform:uppercase;">{{ __('masterfile.industry_label') }}</div>
                    <div style="font-size:10.5px; color:#374151;">{{ \App\Http\Controllers\Admin\VendorController::INDUSTRIES[$vendor->industry] ?? $vendor->industry }}</div>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#9ca3af; text-transform:uppercase;">{{ __('vendor.entity_type_label') }}</div>
                    <div style="font-size:10.5px; color:#374151;">{{ \App\Services\VendorDocumentChecklistService::ENTITY_TYPES[$vendor->entity_type] ?? __('vendor.not_set') }}</div>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#9ca3af; text-transform:uppercase;">{{ __('vendor.nature_of_business_label') }}</div>
                    <div style="font-size:10px; color:#374151; line-height:1.3; word-break:break-word;">{{ $vendor->nature_of_business ?? __('vendor.not_set') }}</div>
                </div>
            </div>
            <div style="margin-top:8px; background:#EFF6FF; border-left:3px solid #1565C0; border-radius:6px; padding:5px 8px; font-size:9px; color:#1e3a5f; line-height:1.3;">
                {{ __('vendor.locked_identity_note') }}
            </div>
        </div>

        {{-- 2. RESTRICTED — Contact Persons, read-only (Contact 1's email is the login ID) --}}
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; overflow:hidden;">
            <div style="font-size:10.5px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('vendor.contact_persons_heading') }} <span style="font-weight:400; color:#9ca3af; font-size:9px;">{{ __('vendor.locked_suffix') }}</span></div>
            <div style="display:flex; flex-direction:column; gap:5px;">
                @foreach([['n'=>$vendor->pic_name,'d'=>$vendor->pic_designation,'p'=>$vendor->pic_phone,'e'=>$vendor->pic_email,'label'=>__('vendor.contact1_label')],['n'=>$vendor->contact2_name,'d'=>$vendor->contact2_designation,'p'=>$vendor->contact2_phone,'e'=>$vendor->contact2_email,'label'=>__('vendor.contact2_label')],['n'=>$vendor->contact3_name,'d'=>$vendor->contact3_designation,'p'=>$vendor->contact3_phone,'e'=>$vendor->contact3_email,'label'=>__('vendor.contact3_label')]] as $c)
                @if($c['n'])
                <div style="border-bottom:1px solid #eef2f7; padding-bottom:4px;">
                    <div style="font-size:8.5px; color:#1565C0; font-weight:700;">{{ $c['label'] }}</div>
                    <div style="font-size:10px; color:#374151; font-weight:600;">{{ $c['n'] }}{{ $c['d'] ? ' — ' . $c['d'] : '' }}</div>
                    <div style="font-size:9px; color:#6b7280; word-break:break-word;">{{ $c['p'] }} &middot; {{ $c['e'] }}</div>
                </div>
                @endif
                @endforeach
            </div>
        </div>

        {{-- 3. RESTRICTED — Uploaded Documents, read-only with verification status --}}
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; overflow:hidden; display:flex; flex-direction:column;">
            <div style="font-size:10.5px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('vendor.uploaded_documents_heading') }} <span style="font-weight:400; color:#9ca3af; font-size:9px;">{{ __('vendor.locked_suffix') }}</span></div>
            <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:4px;">
                @php
                    $docStatusLabels = [
                        'VERIFIED' => __('vendor.status_verified'),
                        'REJECTED' => __('masterfile.status_rejected'),
                    ];
                @endphp
                @forelse($documents as $doc)
                <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:4px 7px;">
                    <span style="font-size:9.5px; color:#374151; word-break:break-word;">{{ $doc->document_label }}</span>
                    <span style="font-size:8.5px; font-weight:700; padding:1px 7px; border-radius:8px; white-space:nowrap; {{ $doc->verification_status === 'VERIFIED' ? 'background:#dcfce7;color:#166534;' : ($doc->verification_status === 'REJECTED' ? 'background:#fee2e2;color:#991b1b;' : 'background:#fef9c3;color:#854d0e;') }}">{{ $docStatusLabels[$doc->verification_status] ?? __('finance.status_pending') }}</span>
                </div>
                @empty
                <div style="font-size:9.5px; color:#9ca3af;">{{ $vendor->ssm_document_name ? __('vendor.doc_on_file', ['doc' => $vendor->ssm_document_name]) : __('vendor.no_documents_on_file') }}</div>
                @endforelse
            </div>
        </div>

        {{-- 4. EDITABLE — operational contact details --}}
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:10px 12px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="font-size:10.5px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('vendor.business_details_heading') }} <span style="font-weight:400; color:#9ca3af; font-size:9px;">{{ __('vendor.editable_suffix') }}</span></div>
            <form method="POST" action="{{ route('vendor.profile.update') }}" style="display:flex; flex-direction:column; gap:6px; flex:1; min-height:0;">
                @csrf
                @method('PUT')
                <div>
                    <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('vendor.business_phone_label') }}</label>
                    <input type="text" name="vendor_phone" value="{{ old('vendor_phone', $vendor->vendor_phone) }}" required placeholder="0123456789" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 7px; font-size:10.5px; box-sizing:border-box; outline:none;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('vendor.website_label') }}</label>
                    <input type="text" name="vendor_website" value="{{ old('vendor_website', $vendor->vendor_website) }}" placeholder="https://example.com" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 7px; font-size:10.5px; box-sizing:border-box; outline:none;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('vendor.website_fb_label') }}</label>
                    <input type="url" name="fb_page_url" value="{{ old('fb_page_url', $vendor->fb_page_url) }}" placeholder="https://facebook.com/yourbusiness" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 7px; font-size:10.5px; box-sizing:border-box; outline:none;">
                </div>
                <button type="submit" style="margin-top:auto; background:#1B9AE4; color:#fff; border:none; border-radius:8px; padding:7px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('gl.save_changes_button') }}</button>
            </form>
        </div>

    </div>

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
        <a href="{{ route('vendor.portal.index') }}" style="background:#1B9AE4; color:#fff; text-decoration:none; border-radius:20px; padding:5px 16px; font-size:10.5px; font-weight:600;">{{ __('network.prev') }}</a>
        <span style="font-size:9.5px; color:#9ca3af;">{{ __('vendor.dashboard_label') }}</span>
        <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:10.5px; font-weight:700;">{{ __('network.next') }}</span>
    </div>
</div>
@endsection
