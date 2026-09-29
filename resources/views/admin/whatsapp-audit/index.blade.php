@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_reports.wa_page_title'))

@section('content')

{{-- NEW 8 Aug 2026 (Task #83) — every agent's WhatsApp send attempt, with
     the recipient-scoping outcome (BLOCKED = tried to message someone
     outside their own customers/upline/downline) and the eventual Meta
     send outcome (SENT/FAILED). Filter via GET params, Prev/Next only. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <form method="GET" action="{{ route('admin.whatsapp-audit.index') }}" style="display:flex; gap:6px; align-items:center;">
                <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:9.5px; color:#374151;">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('growth.all_statuses_option') }}</option>
                    <option value="SENT" {{ $status === 'SENT' ? 'selected' : '' }}>{{ __('admin_ops.ga_col_sent') }}</option>
                    <option value="FAILED" {{ $status === 'FAILED' ? 'selected' : '' }}>{{ __('admin_ops.ga_col_failed') }}</option>
                    <option value="BLOCKED" {{ $status === 'BLOCKED' ? 'selected' : '' }}>{{ __('admin_reports.wa_blocked_out_of_scope') }}</option>
                </select>
                <select name="sender" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:9.5px; color:#374151; max-width:180px;">
                    <option value="all" {{ $senderId === 'all' ? 'selected' : '' }}>{{ __('admin_reports.wa_all_agents') }}</option>
                    @foreach($senders as $id => $name)
                    <option value="{{ $id }}" {{ $senderId === $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                @if($status !== 'all' || $senderId !== 'all')
                <a href="{{ route('admin.whatsapp-audit.index') }}" style="font-size:9.5px; color:#6b7280; text-decoration:none;">{{ __('network.clear') }}</a>
                @endif
            </form>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px; table-layout:fixed;">
                {{-- REBALANCED 8 Aug 2026 per Chris: strict no-truncation
                     rule — Message/Detail/Sender/Recipient now wrap instead
                     of ellipsis, so those columns got more width. --}}
                <colgroup>
                    <col style="width:9%;"><col style="width:11%;"><col style="width:11%;"><col style="width:7%;">
                    <col style="width:8%;"><col style="width:22%;"><col style="width:32%;">
                </colgroup>
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.when') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_reports.wa_col_sender') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_reports.wa_col_recipient') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_ops.fr_col_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.status') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_reports.wa_col_detail') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('whatsapp.field_message') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php
                        $statusColor = match($log->status) { 'SENT' => '#2e7d32', 'BLOCKED' => '#e53935', default => '#f57c00' };
                        $statusBg = match($log->status) { 'SENT' => '#e8f5e9', 'BLOCKED' => '#ffebee', default => '#fff3e0' };
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($log->created_at)->format('d M, h:ia') }}</td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238; word-break:break-word;">{{ $log->sender_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280; word-break:break-word;">{{ $log->recipient_name ?? $log->recipient_phone }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ ucfirst(strtolower($log->recipient_type)) }}</td>
                        <td style="padding:5px 8px;"><span style="background:{{ $statusBg }}; color:{{ $statusColor }}; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:700;">{{ $log->status }}</span></td>
                        <td style="padding:5px 8px; color:#6b7280; word-break:break-word;">{{ $log->detail ?? '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280; word-break:break-word;">{{ $log->message }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('admin_reports.wa_no_matches') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($logs->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('admin_reports.wa_page_of_attempts', ['current' => $logs->currentPage(), 'last' => $logs->lastPage(), 'total' => $logs->total()]) }}</span>
            @if($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
