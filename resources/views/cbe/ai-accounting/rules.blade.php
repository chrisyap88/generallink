@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai.rules_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai.rules_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.ai-accounting.exceptions') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_ai.exceptions_page_title') }}</a>
            <a href="{{ route('cbe.ai-accounting.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        <form method="POST" action="{{ route('cbe.ai-accounting.rules.threshold') }}" style="flex-shrink:0; display:flex; align-items:center; gap:10px; background:var(--gl-light); border-radius:6px; padding:8px 10px; margin-bottom:8px; font-size:10px;">
            @csrf
            <span style="font-weight:700; color:#546E7A; text-transform:uppercase; font-size:8px;">{{ __('cbe_ai.field_threshold') }}</span>
            <input type="number" name="auto_post_confidence_threshold" min="0" max="100" value="{{ $threshold }}" style="width:60px; border:1px solid #d1d5db; border-radius:6px; padding:4px 6px; font-size:10px; text-align:center;">
            <span style="color:#6b7280;">%</span>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:12px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('cbe_ai.save_button') }}</button>
            <span style="flex:1; text-align:right; font-size:8.5px; color:#9ca3af;">{{ __('cbe_ai.field_threshold_helper') }}</span>
        </form>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_pattern') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_category') }}</th>
                        <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_confirmed') }}</th>
                        <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_rejected') }}</th>
                        <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:4px 6px; color:#263238;">{{ $r->match_pattern }}</td>
                        <td style="padding:4px 6px; color:#263238;">{{ __('cbe_ai.category_'.strtolower($r->ai_category)) }}</td>
                        <td style="padding:4px 6px; text-align:center; color:#2e7d32; font-weight:600;">{{ $r->times_confirmed }}</td>
                        <td style="padding:4px 6px; text-align:center; color:{{ $r->times_rejected > 0 ? '#c62828' : '#9ca3af' }}; font-weight:600;">{{ $r->times_rejected }}</td>
                        <td style="padding:4px 6px; text-align:center;">
                            <span style="color:{{ $r->is_active ? '#2e7d32' : '#9ca3af' }}; font-weight:600;">{{ $r->is_active ? __('cbe_ai.rule_status_active') : __('cbe_ai.rule_status_inactive') }}</span>
                        </td>
                        <td style="padding:4px 6px; text-align:right;">
                            <form method="POST" action="{{ route('cbe.ai-accounting.rules.toggle', $r->rule_id) }}">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:{{ $r->is_active ? '#e53935' : '#38A169' }}; font-weight:600; font-size:8.5px; cursor:pointer;">{{ $r->is_active ? __('cbe_ai.deactivate_button') : __('cbe_ai.reactivate_button') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_rules_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($rules->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $rules->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            @if($rules->hasMorePages())
                <a href="{{ $rules->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
