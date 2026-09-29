@extends('layouts.vendor')

@section('page-title', __('vendor.rebate_applications_title'))

@section('content')
<div style="height:100%; display:flex; flex-direction:column; padding:16px 24px; box-sizing:border-box; gap:10px;">

    @if(session('success'))
    <div style="background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; flex-shrink:0;">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
    <div style="background:#fde8e8; color:#b71c1c; border-radius:6px; padding:6px 12px; font-size:11px; flex-shrink:0;">
        @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    {{-- New application — collapsible so the list below still fits with
         no scroll. NEW 12 Aug 2026 per Chris: proper redo of the
         vendor-submitted rebate application flow, with a real
         auto-generated submission number. --}}
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; flex-shrink:0;">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:12.5px; font-weight:700; color:#0D5A8E;">{{ __('vendor.propose_rebate_heading') }}</div>
            <button type="button" onclick="var f=document.getElementById('newAppForm'); f.style.display = f.style.display==='none' ? 'flex' : 'none';" style="background:#0D5A8E; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('vendor.new_application_button') }}</button>
        </div>
        <form id="newAppForm" method="POST" action="{{ route('vendor.rebate-applications.store') }}" style="display:none; flex-direction:column; gap:6px; margin-top:8px;">
            @csrf
            <div style="display:flex; gap:8px;">
                <select name="product_id" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; flex:1;">
                    <option value="">{{ __('vendor.general_rebate_option') }}</option>
                    @foreach($products as $p)
                    <option value="{{ $p->product_id }}">{{ $p->product_name }}</option>
                    @endforeach
                </select>
                <input type="date" name="valid_from" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px;">
                <input type="date" name="valid_until" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px;">
            </div>
            <textarea name="rebate_details" id="rebateDetailsNew" required maxlength="2000" placeholder="{{ __('vendor.rebate_details_placeholder') }}" style="height:56px; resize:none; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;"></textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'rebateDetailsNew', 'carolynType' => 'vendor_rebate_application', 'carolynRoute' => route('vendor.write-assist'), 'carolynInstance' => 'new'])
            <button type="submit" style="align-self:flex-end; background:#0D5A8E; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('vendor.submit_application_button') }}</button>
        </form>
    </div>

    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="font-size:11px; color:#718096; margin-bottom:8px; flex-shrink:0;">{{ __('vendor.submission_number_note') }}</div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @php
                $rebateStatusLabels = [
                    'APPROVED' => __('masterfile.status_approved'),
                    'REJECTED' => __('masterfile.status_rejected'),
                ];
            @endphp
            @forelse($applications as $a)
            @php
                $statusColor = match($a->status) { 'APPROVED' => '#2e7d32', 'REJECTED' => '#e53935', default => '#D97706' };
                $statusBg = match($a->status) { 'APPROVED' => '#e8f5e9', 'REJECTED' => '#fde8e8', default => '#fff8e1' };
            @endphp
            <div style="border:1px solid #f3f4f6; border-radius:8px; padding:9px 12px; margin-bottom:7px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                    <div style="min-width:0;">
                        <span style="font-size:11.5px; font-weight:700; color:#263238;">{{ $a->application_number }}</span>
                        @if($a->version_number > 1)
                        <span style="background:#eef2ff; color:#4338ca; font-size:8.5px; font-weight:700; padding:1px 7px; border-radius:10px; margin-left:5px;">{{ __('vendor.version_badge', ['num' => $a->version_number]) }}</span>
                        @endif
                        @if($a->rebate_program_number)
                        <span style="background:#e0f2fe; color:#0369a1; font-size:8.5px; font-weight:700; padding:1px 7px; border-radius:10px; margin-left:5px;">{{ __('vendor.program_badge', ['num' => $a->rebate_program_number]) }}</span>
                        @endif
                        <span style="font-size:10px; color:#6b7280; margin-left:6px;">{{ $a->product_name ?? __('vendor.general_vendor_wide') }}</span>
                    </div>
                    <span style="background:{{ $statusBg }}; color:{{ $statusColor }}; border-radius:10px; padding:2px 10px; font-size:9.5px; font-weight:700; white-space:nowrap;">{{ $rebateStatusLabels[$a->status] ?? __('finance.status_pending') }}</span>
                </div>
                <div style="font-size:10px; color:#374151; margin-top:4px; line-height:1.4; word-break:break-word;">{{ \Illuminate\Support\Str::limit($a->rebate_details, 140) }}</div>
                @if($a->status === 'REJECTED' && $a->rejection_reason)
                <div style="font-size:9.5px; color:#b71c1c; margin-top:3px;">{{ __('vendor.reason_label') }} {{ $a->rejection_reason }}</div>
                @endif
                <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px;">
                    <span style="font-size:9px; color:#9ca3af;">{{ __('vendor.submitted_label') }} {{ \Carbon\Carbon::parse($a->created_at)->format('d M Y') }}</span>
                    <div style="display:flex; gap:6px;">
                        @if($a->version_number > 1)
                        <a href="{{ route('vendor.rebate-applications.history', $a->application_number) }}" style="font-size:9.5px; color:#0D5A8E; text-decoration:none; font-weight:600;">{{ __('vendor.view_history_link') }}</a>
                        @endif
                        <button type="button" onclick="var f=document.getElementById('revise-{{ $a->application_id }}'); f.style.display = f.style.display==='none' ? 'flex' : 'none';" style="background:#F5F9FF; color:#0D5A8E; border:1px solid #DBEAFE; border-radius:5px; padding:2px 9px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('vendor.revise_button') }}</button>
                    </div>
                </div>
                <form id="revise-{{ $a->application_id }}" method="POST" action="{{ route('vendor.rebate-applications.revise', $a->application_number) }}" style="display:none; flex-direction:column; gap:5px; margin-top:7px; padding-top:7px; border-top:1px solid #f3f4f6;">
                    @csrf
                    <div style="display:flex; gap:6px;">
                        <input type="date" name="valid_from" value="{{ $a->valid_from }}" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px;">
                        <input type="date" name="valid_until" value="{{ $a->valid_until }}" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px;">
                    </div>
                    <input type="hidden" name="product_id" value="{{ $a->product_id }}">
                    <textarea name="rebate_details" id="rebateDetailsRevise_{{ $a->application_id }}" required maxlength="2000" style="height:40px; resize:none; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">{{ $a->rebate_details }}</textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'rebateDetailsRevise_'.$a->application_id, 'carolynType' => 'vendor_rebate_application', 'carolynRoute' => route('vendor.write-assist'), 'carolynInstance' => 'revise-'.$a->application_id])
                    <button type="submit" style="align-self:flex-end; background:#0D5A8E; color:#fff; border:none; border-radius:5px; padding:4px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('vendor.submit_revision_button', ['num' => $a->version_number + 1]) }}</button>
                </form>
            </div>
            @empty
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('vendor.no_rebate_applications') }}</div>
            @endforelse
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid #f3f4f6;">
            @if($applications->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $applications->previousPageUrl() }}" style="background:#0D5A8E; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('growth.page_of', ['current' => $applications->currentPage(), 'last' => $applications->lastPage()]) }}</span>
            @if($applications->hasMorePages())
                <a href="{{ $applications->nextPageUrl() }}" style="background:#0D5A8E; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
