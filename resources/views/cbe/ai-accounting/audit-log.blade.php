@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai.audit_log_page_title'))

@section('content')

{{-- NEW 8 Sep 2026 (Task #397) — AI Module Phase 9: Complete Audit
     Trail + Control Against Fabrication. Read-only browse over the
     append-only cbe_ai_audit_log table, same shape and pagination
     convention as Purchasing / Fixed Asset / Bank Reconciliation audit
     trails. Each row's Notes column is the honest "why" text written
     at the moment of the action — nothing here is computed after the
     fact, and anything the AI could not confidently resolve was
     written as "Unverified" at that time, not guessed. --}}

@php
    $eventLabels = [
        'UPLOADED' => __('cbe_ai.audit_event_uploaded'), 'PARSED' => __('cbe_ai.audit_event_parsed'),
        'PARSE_FAILED' => __('cbe_ai.audit_event_parse_failed'), 'CONTINUITY_FLAGGED' => __('cbe_ai.audit_event_continuity_flagged'),
        'COMMITTED' => __('cbe_ai.audit_event_committed'), 'REJECTED' => __('cbe_ai.audit_event_rejected'),
        'DUPLICATE_SKIPPED' => __('cbe_ai.audit_event_duplicate_skipped'), 'THRESHOLD_CHANGED' => __('cbe_ai.audit_event_threshold_changed'),
        'RULE_DEACTIVATED' => __('cbe_ai.audit_event_rule_deactivated'), 'RULE_REACTIVATED' => __('cbe_ai.audit_event_rule_reactivated'),
        'BANK_ACCOUNT_DETECTED' => __('cbe_ai.audit_event_bank_account_detected'), 'BANK_ACCOUNT_UNRESOLVED' => __('cbe_ai.audit_event_bank_account_unresolved'),
        'RECEIPT_ISSUED' => __('cbe_ai.audit_event_receipt_issued'),
        'POSSIBLE_DUPLICATE_FLAGGED' => __('cbe_ai.audit_event_possible_duplicate_flagged'), 'NOT_DUPLICATE_CONFIRMED' => __('cbe_ai.audit_event_not_duplicate_confirmed'),
    ];
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai.audit_log_page_title') }}</div>
        <a href="{{ route('cbe.ai-accounting.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.ai-accounting.audit-log') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <div style="flex:1; max-width:220px;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_ai.col_event_type') }}</label>
                <select name="event_type" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_ai.filter_all_event_types') }}</option>
                    @foreach($eventLabels as $code => $label)
                    <option value="{{ $code }}" {{ $eventType === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
            <a href="{{ route('cbe.ai-accounting.audit-log') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
        </form>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_datetime') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_source_file') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_action') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_actor') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $l)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('d M Y g:ia') }}</td>
                        <td style="padding:5px 8px; font-weight:600;">
                            @if($l->document_id)
                            <a href="{{ route('cbe.ai-accounting.documents.show', $l->document_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $l->original_filename ?: '—' }}</a>
                            @else
                            —
                            @endif
                        </td>
                        <td style="padding:5px 8px; color:#263238;">{{ $eventLabels[$l->event_type] ?? $l->event_type }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $l->actor_name ?: __('cbe_ai.audit_actor_system') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $l->notes ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_audit_log_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($logs->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $logs->currentPage(), 'last' => $logs->lastPage(), 'total' => $logs->total()]) }}</span>
            @if($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
