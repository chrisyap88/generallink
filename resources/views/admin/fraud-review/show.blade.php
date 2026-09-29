@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.fr_show_title', ['type' => str_replace('_', ' ', $flag->flaggable_type)]))

@section('content')

{{-- NEW 22 Jul 2026 — evidence detail for one fraud_review_flags row.
     Per Chris's own requirement: this screen presents evidence and a
     risk score, never a verdict — "Clear" and "Confirmed Fraud" are
     both explicit Admin decisions, with notes recorded either way. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:4px 10px; font-size:10px; color:#b71c1c; margin-bottom:8px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="flex:1; min-height:0; overflow-y:auto;">

        @php
            $levelColor = match($flag->risk_level) { 'CRITICAL' => '#c62828', 'HIGH' => '#e65100', 'MEDIUM' => '#f9a825', default => '#546e7a' };
        @endphp

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div>
                    <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ str_replace('_', ' ', $flag->flaggable_type) }}</div>
                    <div style="font-size:9.5px; color:#9ca3af;">{{ __('admin_ops.fr_submitted_by_meta', ['name' => $flag->agent_name ?? '—', 'code' => $flag->agent_code ?? '—', 'datetime' => \Carbon\Carbon::parse($flag->created_at)->format('d M Y, h:i A')]) }}</div>
                </div>
                <span style="padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; background:{{ $levelColor }}1A; color:{{ $levelColor }};">{{ $flag->risk_level }} — {{ $flag->risk_score }}/100</span>
            </div>

            @if($flaggableSummary)
            <div style="background:#f8fafc; border-radius:6px; padding:8px 10px; margin-bottom:10px; font-size:10.5px; color:#374151;">
                @foreach((array) $flaggableSummary as $k => $v)
                <div><span style="color:#9ca3af;">{{ ucwords(str_replace('_', ' ', $k)) }}:</span> {{ $v }}</div>
                @endforeach
            </div>
            @endif

            <div style="font-size:10.5px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('admin_ops.fr_detected_anomalies_heading') }}</div>
            @foreach($anomalies as $a)
            <div style="border-left:3px solid {{ $levelColor }}; background:#f8fafc; border-radius:0 6px 6px 0; padding:6px 10px; margin-bottom:6px;">
                <div style="font-size:10.5px; font-weight:600; color:#374151;">{{ $a['label'] ?? $a['code'] }}</div>
                <div style="font-size:9.5px; color:#6b7280;">{{ $a['detail'] ?? '' }}</div>
            </div>
            @endforeach

            <div style="font-size:9px; color:#9ca3af; margin-top:8px;">
                {{ __('growth.status_colon') }} <strong style="color:#374151;">{{ str_replace('_', ' ', $flag->status) }}</strong>
                @if($flag->reviewer_name) — {{ __('admin_ops.fr_reviewed_by_on', ['name' => $flag->reviewer_name, 'date' => \Carbon\Carbon::parse($flag->reviewed_at)->format('d M Y, h:i A')]) }} @endif
            </div>
            @if($flag->decision_notes)
            <div style="font-size:9.5px; color:#374151; margin-top:4px; background:#fffbeb; border-radius:6px; padding:6px 10px;">"{{ $flag->decision_notes }}"</div>
            @endif
        </div>

        @if(in_array($flag->status, ['OPEN', 'UNDER_REVIEW']))
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px;">
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('admin_ops.fr_your_decision_heading') }}</div>

            @if($flag->status === 'OPEN')
            <form method="POST" action="{{ route('admin.fraud-review.under-review', $flag->flag_id) }}" style="margin-bottom:10px;">
                @csrf
                <button type="submit" style="background:#e3f2fd; color:#1565C0; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('admin_ops.fr_mark_under_review_button') }}</button>
            </form>
            @endif

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <form method="POST" action="{{ route('admin.fraud-review.clear', $flag->flag_id) }}">
                    @csrf
                    <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('admin_ops.fr_clear_notes_label') }}</label>
                    <textarea name="decision_notes" id="decisionNotesClear" rows="2" maxlength="2000" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; margin-bottom:6px; box-sizing:border-box; resize:none;"></textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'decisionNotesClear', 'carolynType' => 'fraud_review_note', 'carolynInstance' => 'clear'])
                    <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; width:100%;">{{ __('admin_ops.fr_clear_proceed_button') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.fraud-review.confirm-fraud', $flag->flag_id) }}" onsubmit="return confirm({{ json_encode(__('admin_ops.fr_confirm_block_confirm')) }});">
                    @csrf
                    <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('admin_ops.fr_confirm_fraud_notes_label') }}</label>
                    <textarea name="decision_notes" id="decisionNotesConfirm" rows="2" maxlength="2000" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; margin-bottom:6px; box-sizing:border-box; resize:none;"></textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'decisionNotesConfirm', 'carolynType' => 'fraud_review_note', 'carolynInstance' => 'confirm'])
                    <button type="submit" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; width:100%;">{{ __('admin_ops.fr_confirm_fraud_block_button') }}</button>
                </form>
            </div>
        </div>
        @endif

    </div>

    <div style="flex-shrink:0; padding-top:8px;">
        <a href="{{ route('admin.fraud-review.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>
</div>
@endsection
