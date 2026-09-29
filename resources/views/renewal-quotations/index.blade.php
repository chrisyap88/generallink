@extends('layouts.dashboard')

@section('title', __('renewal_quotations.page_title'))
@section('page-title')
{{ __('renewal_quotations.page_title') }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ __('renewal_quotations.subtitle_note') }}</span>
@endsection

@section('content')
{{-- REBUILT 8 Aug 2026 per Chris: strict no-scroll rule — this screen
     used to have no fixed-height wrapper at all (fell back to the
     layout's default page scroll) and used Laravel's default numbered
     ->links() widget instead of the app's bottom blue Prev/Next pair.
     Rebuilt to match every other list screen's pattern. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:8px;">
        @if(session('success'))
        <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10.5px;">{{ session('success') }}</div>
        @endif
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead>
                    <tr style="background:#F7FAFC; border-bottom:1px solid #E2E8F0;">
                        <th style="text-align:left; padding:6px 10px; color:#0D5A8E; font-weight:700; font-size:9.5px; text-transform:uppercase;">{{ __('renewal_quotations.col_policy') }}</th>
                        <th style="text-align:left; padding:6px 10px; color:#0D5A8E; font-weight:700; font-size:9.5px; text-transform:uppercase;">{{ __('gl.col_customer') }}</th>
                        <th style="text-align:left; padding:6px 10px; color:#0D5A8E; font-weight:700; font-size:9.5px; text-transform:uppercase;">{{ __('renewal_quotations.col_owned_by') }}</th>
                        <th style="text-align:left; padding:6px 10px; color:#0D5A8E; font-weight:700; font-size:9.5px; text-transform:uppercase;">{{ __('renewal_quotations.col_requested') }}</th>
                        <th style="text-align:left; padding:6px 10px; color:#0D5A8E; font-weight:700; font-size:9.5px; text-transform:uppercase;">{{ __('renewal_quotations.col_escalation') }}</th>
                        <th style="text-align:center; padding:6px 10px; color:#0D5A8E; font-weight:700; font-size:9.5px; text-transform:uppercase;">{{ __('gl.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    @php
                        $daysWaiting = now()->diffInDays($req->requested_at);
                        $escLevel = $req->admin_escalation_sent_at ? 'Admin' : ($req->gl_reminder_sent_at ? \App\Services\RoleLabelService::shortLabel('GROUP_LEADER') : ($req->tl_reminder_sent_at ? \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') : ($req->introducer_reminder_sent_at ? \App\Services\RoleLabelService::label('INTRODUCER') : '—')));
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px 10px; font-weight:600; color:#1565C0;">{{ $req->document_reference_number }}</td>
                        <td style="padding:6px 10px; word-break:break-word;">{{ $req->customer_name }}<br><span style="color:#9ca3af; font-size:9.5px;">{{ $req->customer_phone }}</span></td>
                        <td style="padding:6px 10px; word-break:break-word;">{{ $req->agent_name }}<br><span style="color:#9ca3af; font-size:9.5px;">{{ $req->agent_code }}</span></td>
                        <td style="padding:6px 10px;">{{ \Illuminate\Support\Carbon::parse($req->requested_at)->format('d M Y') }}<br><span style="color:{{ $daysWaiting >= 7 ? '#c62828' : ($daysWaiting >= 3 ? '#e65100' : '#9ca3af') }}; font-size:9.5px;">{{ $daysWaiting }} {{ __('renewal_quotations.days_waiting_suffix') }}</span></td>
                        <td style="padding:6px 10px;">
                            @if($escLevel === '—')
                            <span style="color:#9ca3af;">{{ __('renewal_quotations.not_yet_word') }}</span>
                            @else
                            <span style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600;">{{ __('renewal_quotations.escalated_to_prefix', ['level' => $escLevel]) }}</span>
                            @endif
                        </td>
                        <td style="padding:6px 10px; text-align:center;">
                            <form method="POST" action="{{ route($rolePrefix . '.renewal-quotations.mark-sent', $req->request_id) }}" enctype="multipart/form-data" style="display:flex; gap:6px; align-items:center; justify-content:center;">
                                @csrf
                                <input type="file" name="quotation" accept="application/pdf" required style="font-size:9.5px; max-width:120px;">
                                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('renewal_quotations.mark_sent_button') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#9ca3af;">{{ __('renewal_quotations.no_pending_requests') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($requests->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $requests->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('renewal_quotations.page_x_of_y_requests', ['current' => $requests->currentPage(), 'last' => $requests->lastPage(), 'total' => $requests->total()]) }}</span>
            @if($requests->hasMorePages())
                <a href="{{ $requests->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
