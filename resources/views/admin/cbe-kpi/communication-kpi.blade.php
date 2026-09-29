@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.box_communication_kpi'))

@section('content')

{{-- REDESIGNED 19 Sep 2026 -- per Chris: "the communication kpi screen
     suppose like this only change the screen title to Communication KPI
     and you can include this in the communication kpi as well realign
     properly" -- this screen is now the exact same glassmorphism
     .glade-card layout as admin/glade-analytics/index.blade.php (GLADE
     Engagement Analytics), same 3 cards (Notices Posted, Deliveries by
     Channel, Top 5 Most-Read Notices, AI Insight), just retitled
     "Communication KPI" -- PLUS a 4th column added for this screen's own
     CBE-specific metrics (Message Threads, CBE Notices This Month/
     Active) so nothing that was already here got dropped. Data for the
     first 3 columns comes from
     GladeAnalyticsController::computeNoticeBoardMetrics() (shared, not
     duplicated -- see that controller). No header card / Prev pill here
     on purpose, to match GLADE Engagement Analytics exactly -- getting
     back to the Executive KPI Dashboard uses the sidebar's own
     "Prev -- Main Menu" link, same as that screen. --}}

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


    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:8px 16px; flex-shrink:0; display:flex; align-items:center; gap:8px;">
        {{-- ADDED 19 Sep 2026 -- per Chris: "why you dont ask the selection criteria" -- lets you switch which CBE Group this screen shows, without going back to the Executive KPI Dashboard's own picker first. Drilling into one specific Branch/Temple still uses that fuller picker. --}}
        <i class="ti ti-building-community" style="color:#1565C0; font-size:13px;"></i>
        <select id="cbeGroupSelect-communication" style="font-size:9.5px; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; color:#263238; max-width:260px;">
            <option value="ALL" {{ ($selectedGroupId ?? 'ALL') === 'ALL' ? 'selected' : '' }}>All CBE Groups (Combined)</option>
            @foreach($groupOptions as $g)
            <option value="{{ $g->node_id }}" {{ ($selectedGroupId ?? '') === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
            @endforeach
        </select>
        <button type="button" onclick="var v=document.getElementById('cbeGroupSelect-communication').value; window.location.href = v==='ALL' ? '{{ route('admin.cbe-kpi.communication') }}?scope=all' : '{{ route('admin.cbe-kpi.communication') }}?node=' + encodeURIComponent(v);" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:9.5px; font-weight:700; cursor:pointer;">Go &rarr;</button>
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
            <div style="font-size:8px; color:#9ca3af; margin-top:4px; line-height:1.3;">{{ $scopeLabel }}</div>
        </div>

    </div>

</div>
@endsection
