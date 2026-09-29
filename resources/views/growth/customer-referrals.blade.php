@extends('layouts.dashboard')

@section('page-title', __('growth.customer_referrals_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ $isAdmin ? __('growth.referrals_subtitle_admin') : __('growth.referrals_subtitle_agent') }}</div>
        </div>
        <a href="{{ route('customer-referrals.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:10.5px; font-weight:600;">{{ __('growth.log_referral_plus_button') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('customer-referrals.index') }}" style="margin-bottom:8px; flex-shrink:0;">
        <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff;">
            <option value="" {{ $status === '' ? 'selected' : '' }}>{{ __('growth.all_statuses_option') }}</option>
            <option value="SUBMITTED" {{ $status === 'SUBMITTED' ? 'selected' : '' }}>{{ __('growth.status_submitted') }}</option>
            <option value="CONTACTED" {{ $status === 'CONTACTED' ? 'selected' : '' }}>{{ __('growth.status_contacted') }}</option>
            <option value="CONVERTED" {{ $status === 'CONVERTED' ? 'selected' : '' }}>{{ __('growth.status_converted') }}</option>
            <option value="DECLINED" {{ $status === 'DECLINED' ? 'selected' : '' }}>{{ __('growth.status_declined') }}</option>
        </select>
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.referred_by_col') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.referred_person_col') }}</th>
                        @if($isAdmin)
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.agent_col') }}</th>
                        @endif
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.status') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.reward_col') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.actions_col') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($referrals as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#111827;">{{ $r->referring_customer_name }}</td>
                        <td style="padding:5px 8px; color:#111827;">{{ $r->referred_name }} <span style="color:#9ca3af;">({{ $r->referred_contact }})</span></td>
                        @if($isAdmin)
                        <td style="padding:5px 8px; color:#6b7280;">{{ $r->agent_name }}</td>
                        @endif
                        <td style="padding:5px 8px; text-align:center;">
                            <form method="POST" action="{{ route('customer-referrals.status', $r->referral_id) }}" style="display:inline;">
                                @csrf
                                <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:9px; background:#fff;">
                                    <option value="SUBMITTED" {{ $r->status === 'SUBMITTED' ? 'selected' : '' }}>{{ __('growth.status_submitted') }}</option>
                                    <option value="CONTACTED" {{ $r->status === 'CONTACTED' ? 'selected' : '' }}>{{ __('growth.status_contacted') }}</option>
                                    <option value="CONVERTED" {{ $r->status === 'CONVERTED' ? 'selected' : '' }}>{{ __('growth.status_converted') }}</option>
                                    <option value="DECLINED" {{ $r->status === 'DECLINED' ? 'selected' : '' }}>{{ __('growth.status_declined') }}</option>
                                </select>
                            </form>
                        </td>
                        <td style="padding:5px 8px;">
                            @if($r->reward_status === 'NONE')
                                @if($isAdmin && $r->status === 'CONVERTED')
                                <form method="POST" action="{{ route('customer-referrals.set-reward', $r->referral_id) }}" style="display:flex; gap:3px; align-items:center;">
                                    @csrf
                                    <select name="reward_type" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 4px; font-size:8.5px; background:#fff;">
                                        <option value="POINTS">{{ __('growth.reward_type_points') }}</option>
                                        <option value="DOCUMENT_CREDIT">{{ __('growth.reward_type_doc_credit') }}</option>
                                        <option value="CASH">{{ __('growth.reward_type_cash') }}</option>
                                    </select>
                                    <input type="number" name="reward_value" min="0.01" step="0.01" placeholder="{{ __('growth.amount_placeholder') }}" style="width:50px; border:1px solid #d1d5db; border-radius:5px; padding:3px 4px; font-size:8.5px;">
                                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:3px 8px; font-size:8.5px; font-weight:600; cursor:pointer;">{{ __('growth.set_button') }}</button>
                                </form>
                                @else
                                <span style="color:#9ca3af;">&mdash;</span>
                                @endif
                            @else
                                <span style="color:#111827; font-weight:600;">{{ $r->reward_type === 'POINTS' ? __('growth.pts_suffix', ['value' => number_format($r->reward_value,0)]) : ($r->reward_type === 'DOCUMENT_CREDIT' ? __('growth.doc_credit_suffix', ['value' => number_format($r->reward_value,2)]) : 'RM '.number_format($r->reward_value,2)) }}</span>
                                @if($r->reward_status === 'PENDING')
                                <span style="background:#fffbeb; color:#92400e; font-size:8px; font-weight:700; padding:1px 6px; border-radius:20px; margin-left:4px;">{{ __('growth.pending') }}</span>
                                @else
                                <span style="background:#f0fdf4; color:#166534; font-size:8px; font-weight:700; padding:1px 6px; border-radius:20px; margin-left:4px;">{{ __('growth.awarded') }}</span>
                                @endif
                            @endif
                        </td>
                        <td style="padding:5px 8px; text-align:center;">
                            @if($isAdmin && $r->reward_status === 'PENDING')
                            <form method="POST" action="{{ route('customer-referrals.mark-rewarded', $r->referral_id) }}">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#166534; font-size:9px; font-weight:600; cursor:pointer;">{{ __('growth.mark_rewarded_button') }}</button>
                            </form>
                            @else
                            &mdash;
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('growth.no_referrals_logged_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($referrals->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</span>
            @else
                <a href="{{ $referrals->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</a>
            @endif
            <span style="font-size:9px; color:#6b7280;">{{ __('growth.page_of', ['current' => $referrals->currentPage(), 'last' => $referrals->lastPage()]) }}</span>
            @if($referrals->hasMorePages())
                <a href="{{ $referrals->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
