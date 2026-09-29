@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_cbe_notice_styles.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('admin_cbe_notice_styles.page_title') }}</div>
        <a href="{{ route('admin.cbe-notice-styles.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('admin_cbe_notice_styles.add_button') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ __('admin_cbe_notice_styles.page_hint') }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f5f6f8;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_cbe_notice_styles.col_preview') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_cbe_notice_styles.col_label') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_cbe_notice_styles.col_keywords') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_cbe_notice_styles.col_status') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($styles as $s)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px;"><span style="display:inline-block; padding:3px 10px; border-radius:999px; font-size:9px; font-weight:800; background:{{ $s->accent_color }}; color:{{ $s->bg_color }};">{{ $s->label }}</span></td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $s->label }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:280px;" title="{{ $s->keywords }}">{{ $s->keywords ?: '—' }}</td>
                        <td style="padding:5px 8px;">{{ $s->is_active ? __('admin_cbe_notice_styles.status_active') : __('admin_cbe_notice_styles.status_off') }}</td>
                        <td style="padding:5px 8px; text-align:right;"><a href="{{ route('admin.cbe-notice-styles.edit', $s->id) }}" style="color:#1565C0; text-decoration:none; font-weight:600;">{{ __('admin_cbe_notice_styles.edit_button') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('admin_cbe_notice_styles.none_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($styles->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $styles->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $styles->currentPage(), 'last' => $styles->lastPage(), 'total' => $styles->total()]) }}</span>
            @if($styles->hasMorePages())
                <a href="{{ $styles->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
