@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.checkin_page_title'))

@section('content')

{{-- NEW 18 Sep 2026 — per Chris: the member's side of the WhatsApp/
     Email attendance confirmation link. One tap while logged in as
     themselves marks them Present — no code to type. --}}

<div style="height:calc(100vh - 46px); display:flex; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:24px; max-width:380px; width:100%; text-align:center;">

        @if(session('success') || $attendee->checkin_confirmed_at)
            <div style="font-size:34px; margin-bottom:8px;">&#9989;</div>
            <div style="font-size:13px; font-weight:700; color:#2e7d32; margin-bottom:6px;">{{ __('cbe_records.checkin_confirmed_note') }}</div>
            <div style="font-size:10.5px; color:#6b7280;">{{ $minute->title }}</div>
        @elseif($expired)
            <div style="font-size:34px; margin-bottom:8px;">&#8987;</div>
            <div style="font-size:13px; font-weight:700; color:#c62828; margin-bottom:6px;">{{ __('cbe_records.checkin_expired_note') }}</div>
            <div style="font-size:10.5px; color:#6b7280;">{{ $minute->title }}</div>
        @else
            <div style="font-size:13px; font-weight:700; color:#263238; margin-bottom:4px;">{{ $minute->title }}</div>
            <div style="font-size:10px; color:#6b7280; margin-bottom:16px;">{{ \Carbon\Carbon::parse($minute->meeting_date)->format('d M Y') }}@if($minute->meeting_time) &middot; {{ \Carbon\Carbon::parse($minute->meeting_time)->format('g:i A') }}@endif</div>
            <form method="POST" action="{{ route('cbe.minutes.checkin.confirm', $token) }}">
                @csrf
                <button type="submit" style="background:#2e7d32; color:#fff; border:none; border-radius:8px; padding:12px 28px; font-size:12px; font-weight:700; cursor:pointer;">{{ __('cbe_records.checkin_confirm_button') }}</button>
            </form>
        @endif

    </div>
</div>
@endsection
