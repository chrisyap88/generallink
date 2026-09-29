@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.fixed_asset_audit_log_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #395 Phase 4) — Fixed Asset Audit Trail (spec
     section 24/29). Read-only browse over the append-only
     cbe_fixed_asset_audit_log table (one row per asset action, written
     alongside — not instead of — the asset's own status columns).
     Mirrors purchasing-audit-log.blade.php exactly. Each row links back
     to the asset's own show screen when an asset_id is present (it is
     null only for an ACQUISITION rejected before any asset row ever
     existed). --}}

@php
    $actionLabels = [
        'CREATED' => __('cbe_accounting.fa_audit_action_created'),
        'SUBMITTED_FOR_APPROVAL' => __('cbe_accounting.fa_audit_action_submitted'),
        'APPROVED' => __('cbe_accounting.fa_audit_action_approved'),
        'REJECTED' => __('cbe_accounting.fa_audit_action_rejected'),
        'EDITED' => __('cbe_accounting.fa_audit_action_edited'),
        'DEPRECIATED' => __('cbe_accounting.fa_audit_action_depreciated'),
        'IMPROVED' => __('cbe_accounting.fa_audit_action_improved'),
        'TRANSFERRED' => __('cbe_accounting.fa_audit_action_transferred'),
        'DISPOSED' => __('cbe_accounting.fa_audit_action_disposed'),
    ];
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.fixed_asset_audit_log_page_title') }}</div>
        <a href="{{ route('cbe.accounting.fixed-asset-reports-hub') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_fa_reports') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_datetime') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_asset_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_action') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_actor') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $l)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($l->created_at)->format('d M Y g:ia') }}</td>
                        <td style="padding:5px 8px; font-weight:600;">
                            @if($l->asset_id)
                            <a href="{{ route('cbe.accounting.fixed-assets.show', $l->asset_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $l->asset_name ?: '—' }}</a>
                            @else
                            {{ $l->asset_name ?: '—' }}
                            @endif
                        </td>
                        <td style="padding:5px 8px; color:#263238;">{{ $actionLabels[$l->action] ?? $l->action }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $l->actor_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $l->notes ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_fa_audit_log_note') }}</td></tr>
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
