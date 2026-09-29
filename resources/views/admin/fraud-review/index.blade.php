@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.fr_page_title'))

@section('content')

{{-- NEW 22 Jul 2026 — Admin's Risk Review Queue, per Chris's scoped-
     down fraud detection build: business-rule checks only (duplicate
     file hashes, duplicate reference numbers, math/date anomalies) —
     deliberately NOT AI tamper detection or merchant fingerprinting,
     which were flagged as unreliable/oversized for this project. This
     screen shows flagged items by default (unlike Agent Balances/
     Document Credit's "nothing loads until filtered" rule) since it's
     a worklist, not a browse-everyone screen — same reasoning as the
     existing Approvals screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px 0; box-sizing:border-box;">

<div style="flex-shrink:0;">
    <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px; max-width:640px; line-height:1.4;">
        {{ __('admin_ops.fr_intro') }}
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
        <button type="button" class="frTabBtn" data-tab="frQueue" style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#1565C0; cursor:pointer; position:relative; top:1px;">{{ __('admin_ops.fr_tab_review_queue') }}</button>
        <button type="button" class="frTabBtn" data-tab="frSettings" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('admin_ops.tab_settings') }}</button>
    </div>
</div>

<div style="flex:1 1 auto; min-height:0; padding-bottom:10px;">

    <div id="frQueue" class="frTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:10px; height:100%; box-sizing:border-box;">
        <div style="height:100%; display:flex; flex-direction:column;">

        <div style="flex-shrink:0; display:flex; gap:14px; margin-bottom:10px;">
            @foreach(['CRITICAL' => '#c62828', 'HIGH' => '#e65100', 'MEDIUM' => '#f9a825', 'LOW' => '#546e7a'] as $lvl => $color)
            <div style="display:flex; align-items:center; gap:5px;">
                <span style="width:8px; height:8px; border-radius:50%; background:{{ $color }};"></span>
                <span style="font-size:9.5px; color:#374151;">{{ __('admin_ops.fr_level_'.strtolower($lvl)) }}: <strong>{{ $counts[$lvl] ?? 0 }}</strong></span>
            </div>
            @endforeach
        </div>

        <div style="flex-shrink:0; display:flex; gap:6px; margin-bottom:8px;">
            @foreach(['OPEN' => __('admin_ops.fr_status_open'), 'UNDER_REVIEW' => __('admin_ops.fr_status_under_review'), 'CLEARED' => __('admin_ops.fr_status_cleared'), 'CONFIRMED_FRAUD' => __('admin_ops.fr_status_confirmed_fraud'), 'ALL' => __('admin_ops.fr_status_all')] as $key => $label)
            <a href="{{ route('admin.fraud-review.index', ['status' => $key]) }}" style="text-decoration:none; padding:3px 10px; border-radius:20px; font-size:9px; font-weight:600; background:{{ $status === $key ? '#1565C0' : '#f3f4f6' }}; color:{{ $status === $key ? '#fff' : '#6b7280' }};">{{ $label }}</a>
            @endforeach
        </div>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_ops.fr_col_risk') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_ops.fr_col_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_ops.fr_col_submitted_by') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_ops.fr_col_flagged') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.status') }}</th>
                        <th style="width:24px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flags as $f)
                    @php
                        $levelColor = match($f->risk_level) { 'CRITICAL' => '#c62828', 'HIGH' => '#e65100', 'MEDIUM' => '#f9a825', default => '#546e7a' };
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('admin.fraud-review.show', $f->flag_id) }}'">
                        <td style="padding:5px 8px;">
                            <span style="padding:2px 8px; border-radius:20px; font-size:8.5px; font-weight:700; background:{{ $levelColor }}1A; color:{{ $levelColor }};">{{ $f->risk_level }} ({{ $f->risk_score }})</span>
                        </td>
                        <td style="padding:5px 8px; color:#374151;">{{ str_replace('_', ' ', $f->flaggable_type) }}</td>
                        <td style="padding:5px 8px; color:#374151;">{{ $f->agent_name ?? '—' }} @if($f->agent_code)<span style="color:#9ca3af;">({{ $f->agent_code }})</span>@endif</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ \Carbon\Carbon::parse($f->created_at)->format('d M Y, h:i A') }}</td>
                        <td style="padding:5px 8px;">
                            <span style="padding:2px 8px; border-radius:20px; font-size:8.5px; font-weight:600;
                                background:{{ $f->status === 'OPEN' ? '#fff8e1' : ($f->status === 'UNDER_REVIEW' ? '#e3f2fd' : ($f->status === 'CLEARED' ? '#e8f5e9' : '#fde8e8')) }};
                                color:{{ $f->status === 'OPEN' ? '#92400e' : ($f->status === 'UNDER_REVIEW' ? '#1565C0' : ($f->status === 'CLEARED' ? '#1b5e20' : '#b71c1c')) }};">{{ str_replace('_', ' ', $f->status) }}</span>
                        </td>
                        <td style="padding:5px 8px; color:#9ca3af;">&rarr;</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ $status !== 'ALL' ? __('admin_ops.fr_no_flags_status', ['status' => str_replace('_',' ',$status)]) : __('admin_ops.fr_no_flags') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($flags->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.prev') }}</span>
            @else
                <a href="{{ $flags->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('admin_ops.fr_page_of_flags', ['current' => $flags->currentPage(), 'last' => $flags->lastPage(), 'total' => $flags->total()]) }}</span>
            @if($flags->hasMorePages())
                <a href="{{ $flags->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('growth.next') }}</span>
            @endif
        </div>
        </div>
    </div>

    <div id="frSettings" class="frTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:14px; height:100%; box-sizing:border-box; overflow:hidden;">
        @php
            $fraudService = app(\App\Services\FraudDetectionService::class);
            $thresholds = $fraudService->riskThresholds();
            $reminderHours = (int) (\Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'fraud_review_reminder_hours')->value('setting_value') ?? 24);
            $escalationHours = (int) (\Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'fraud_review_escalation_hours')->value('setting_value') ?? 48);
        @endphp
        <div style="max-width:480px;">
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('admin_ops.fr_risk_score_thresholds_heading') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px; line-height:1.4;">
                {{ __('admin_ops.fr_thresholds_intro') }}
            </div>
            <form method="POST" action="{{ route('admin.fraud-review.settings') }}">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('admin_ops.fr_medium_at_label') }}</label>
                        <input type="number" name="medium_threshold" value="{{ $thresholds['medium'] }}" min="1" max="99" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('admin_ops.fr_high_at_label') }}</label>
                        <input type="number" name="high_threshold" value="{{ $thresholds['high'] }}" min="1" max="99" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('admin_ops.fr_critical_at_label') }}</label>
                        <input type="number" name="critical_threshold" value="{{ $thresholds['critical'] }}" min="1" max="100" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px; padding-top:8px; border-top:1px solid #f3f4f6;">{{ __('admin_ops.fr_reminder_escalation_timing_heading') }}</div>
                <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px; line-height:1.4;">
                    {{ __('admin_ops.fr_reminder_escalation_intro') }}
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('growth.remind_after_hours_label') }}</label>
                        <input type="number" name="reminder_hours" value="{{ $reminderHours }}" min="1" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('admin_ops.fr_escalate_after_hours_label') }}</label>
                        <input type="number" name="escalation_hours" value="{{ $escalationHours }}" min="1" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>

                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
            </form>
        </div>
    </div>

</div>
</div>

<script>
(function() {
    var tabBtns = document.querySelectorAll('.frTabBtn');
    var tabPanels = document.querySelectorAll('.frTabPanel');
    function activateTab(tabId) {
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'block' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? '#1565C0' : '#6b7280';
        });
    }
    tabBtns.forEach(function(b) { b.addEventListener('click', function() { activateTab(b.dataset.tab); }); });
    activateTab('frQueue');
})();
</script>
@endsection
