@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('notice_board.page_title'))

@section('content')

{{-- NEW 21 Jul 2026 — Notice Board, Admin side: post one-way broadcast
     announcements. Active/Expired toggle via GET param (same convention
     as the Enquiries status filter) rather than JS tabs, since each
     side paginates independently. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:flex-end; align-items:center;">
        <a href="{{ route('admin.notice-board.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('notice_board.post_new_notice_button') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="display:flex; gap:3px;">
            <a href="{{ route('admin.notice-board.index', ['tab' => 'active']) }}" style="text-decoration:none; padding:5px 14px; font-size:9.5px; font-weight:700; border-radius:6px 6px 0 0; border:1px solid #E2E8F0; border-bottom:none; {{ $tab === 'active' ? 'background:#fff; color:var(--gl-blue);' : 'background:#F7FAFC; color:#6b7280;' }}">{{ __('notice_board.active_tab') }}</a>
            <a href="{{ route('admin.notice-board.index', ['tab' => 'expired']) }}" style="text-decoration:none; padding:5px 14px; font-size:9.5px; font-weight:700; border-radius:6px 6px 0 0; border:1px solid #E2E8F0; border-bottom:none; {{ $tab === 'expired' ? 'background:#fff; color:var(--gl-blue);' : 'background:#F7FAFC; color:#6b7280;' }}">{{ __('notice_board.expired_tab') }}</a>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:0 8px 8px 8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('notice_board.col_title') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('help-desk.col_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('notice_board.col_posted') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('notice_board.col_expires') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $nbCatLabels = [
                            'GENERAL' => __('notice_board.category_general'),
                            'IMPORTANT_UPDATE' => __('notice_board.category_important_update'),
                            'PROMOTION' => __('notice_board.category_promotion'),
                            'HOLIDAY_FESTIVE' => __('notice_board.category_holiday_festive'),
                            'CONTACT_INFO' => __('notice_board.category_contact_info'),
                        ];
                    @endphp
                    @forelse($notices as $n)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $n->title }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $nbCatLabels[$n->category] ?? str_replace('_', ' ', $n->category) }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ \Carbon\Carbon::parse($n->created_at)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $n->expires_at ? \Carbon\Carbon::parse($n->expires_at)->format('d M Y') : __('notice_board.never_word') }}</td>
                        <td style="padding:5px 8px; text-align:right;">
                            <a href="{{ route('admin.notice-board.edit', $n->notice_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none; font-size:9.5px; margin-right:8px;">{{ __('gl.edit_link') }}</a>
                            <form method="POST" action="{{ route('admin.notice-board.destroy', $n->notice_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('notice_board.delete_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('growth.delete_button') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ $tab === 'expired' ? __('notice_board.no_expired_notices_note') : __('notice_board.no_active_notices_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($notices->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $notices->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('notice_board.page_x_of_y_notices', ['current' => $notices->currentPage(), 'last' => $notices->lastPage(), 'total' => $notices->total()]) }}</span>
            @if($notices->hasMorePages())
                <a href="{{ $notices->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
