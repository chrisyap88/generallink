@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.box_communication_kpi'))

@section('content')

{{-- REDESIGNED 19 Sep 2026 -- per Chris: same as the admin-side twin
     (admin/cbe-kpi/communication-kpi.blade.php) -- exact glassmorphism
     .glade-card layout as GLADE Engagement Analytics, retitled
     "Communication KPI", plus a 4th column for this screen's own CBE
     message-thread / notices metrics. See that file's header comment
     for the full rationale. --}}

<style>
.glade-card{background:rgba(255,255,255,0.65);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.4);border-radius:12px;box-shadow:0 4px 16px rgba(120,180,190,0.15);padding:8px 10px;display:flex;flex-direction:column;overflow:hidden;min-height:0;}
.glade-ct{font-size:9.5px;font-weight:700;color:#1565C0;letter-spacing:0.3px;margin-bottom:6px;}
.glade-row{background:rgba(255,255,255,0.55);border:1px solid rgba(125,231,231,0.25);border-radius:7px;display:flex;align-items:center;justify-content:space-between;padding:4px 10px;margin-bottom:4px;}
.glade-lbl{font-size:9px;color:#374151;white-space:nowrap;}
.glade-val{font-size:9.5px;color:#263238;font-weight:700;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px;">
    </div>

    <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr 1fr 1fr; grid-template-rows:1fr 1fr; gap:8px;">

        <div class="glade-card" style="border-top:3px solid #1565C0;">
            <div class="glade-ct">{{ __('admin_ops.ga_notices_posted_heading') }}</div>
            <div class="glade-row"><span class="glade-lbl">{{ __('admin_ops.ga_total_all_time_label') }}</span><span class="glade-val">{{ $totalNotices }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('admin_ops.ga_last_30_days_label') }}</span><span class="glade-val">{{ $notices30 }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('admin_ops.ga_total_reads_logged_label') }}</span><span class="glade-val">{{ $totalReads }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('admin_ops.ga_read_rate_all_time_label') }}</span><span class="glade-val">{{ $readRate }}%</span></div>
        </div>

        <div class="glade-card" style="border-top:3px solid #25D366; grid-row:span 2;">
            <div class="glade-ct">{{ __('admin_ops.ga_deliveries_by_channel_heading') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:8.5px;">
                    <thead>
                        <tr style="background:#f0f9ff;">
                            <th style="text-align:left; padding:3px 6px; color:#546E7A; text-transform:uppercase; font-size:7.5px;">{{ __('admin_ops.ga_col_channel') }}</th>
                            <th style="text-align:right; padding:3px 6px; color:#2e7d32; text-transform:uppercase; font-size:7.5px;">{{ __('admin_ops.ga_col_sent') }}</th>
                            <th style="text-align:right; padding:3px 6px; color:#e53935; text-transform:uppercase; font-size:7.5px;">{{ __('admin_ops.ga_col_failed') }}</th>
                            <th style="text-align:right; padding:3px 6px; color:#f57c00; text-transform:uppercase; font-size:7.5px;">{{ __('admin_ops.ga_col_skipped') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveryMatrix as $channel => $stats)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; font-weight:600; color:#263238;">{{ ucfirst(strtolower($channel)) }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#2e7d32;">{{ $stats['SENT'] }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#e53935;">{{ $stats['FAILED'] }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#f57c00;">{{ $stats['SKIPPED'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glade-card" style="border-top:3px solid #f9a825; grid-row:span 2;">
            <div class="glade-ct">{{ __('admin_ops.ga_ai_insight_heading') }}</div>
            <div class="glade-row"><span class="glade-lbl">{{ __('admin_ops.ga_agent_notice_pairs_label') }}</span><span class="glade-val">{{ $aiTotal }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('admin_ops.ga_insight_shown_label') }}</span><span class="glade-val">{{ $aiWithBlurb }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('admin_ops.ga_judged_not_relevant_label') }}</span><span class="glade-val">{{ $aiSkipped }}</span></div>
            <div style="font-size:8px; color:#9ca3af; margin-top:4px; line-height:1.3;">{{ __('admin_ops.ga_ai_insight_note') }}</div>
        </div>

        <div class="glade-card" style="border-top:3px solid #1565C0;">
            <div class="glade-ct">{{ __('cbe_exec.row_total_message_threads') }}</div>
            <div class="glade-row"><span class="glade-lbl">{{ __('cbe_exec.row_total_message_threads') }}</span><span class="glade-val">{{ number_format($communicationKpi['total_threads']) }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('cbe_exec.row_open_threads') }}</span><span class="glade-val" style="color:#3730a3;">{{ number_format($communicationKpi['open']) }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('cbe_exec.row_outstanding_threads') }}</span><span class="glade-val" style="color:#92400e;">{{ number_format($communicationKpi['outstanding']) }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('cbe_exec.row_escalated_threads') }}</span><span class="glade-val" style="color:#991b1b;">{{ number_format($communicationKpi['escalated']) }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('cbe_exec.row_resolved_threads') }}</span><span class="glade-val" style="color:#065f46;">{{ number_format($communicationKpi['resolved']) }}</span></div>
        </div>

        <div class="glade-card" style="border-top:3px solid #7C4DFF;">
            <div class="glade-ct">{{ __('admin_ops.ga_top5_notices_heading') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                @forelse($topNotices as $n)
                <div class="glade-row">
                    <span class="glade-lbl" style="word-break:break-word; max-width:70%;">{{ $n->title }}</span>
                    <span class="glade-val">{{ $n->read_count }}</span>
                </div>
                @empty
                <div style="font-size:9px; color:#9ca3af; text-align:center; padding:10px;">{{ __('admin_ops.ga_no_reads_logged') }}</div>
                @endforelse
            </div>
        </div>

        <div class="glade-card" style="border-top:3px solid #7C4DFF;">
            <div class="glade-ct">{{ __('cbe_exec.row_notices_this_month') }}</div>
            <div class="glade-row"><span class="glade-lbl">{{ __('cbe_exec.row_notices_this_month') }}</span><span class="glade-val">{{ number_format($communicationKpi['notices_this_month']) }}</span></div>
            <div class="glade-row"><span class="glade-lbl">{{ __('cbe_exec.row_notices_active') }}</span><span class="glade-val" style="color:#065f46;">{{ number_format($communicationKpi['notices_active']) }}</span></div>
        </div>

    </div>

</div>
@endsection
