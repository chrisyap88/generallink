@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.notice_board_page_title'))

@section('content')

{{-- NEW 17 Sep 2026 — per Chris: temple Notice Board, every member reads,
     only officers/Secretary can post/edit/delete (see
     CbeNoticeBoardController). Same no-scroll shell + Prev/Next
     pagination pattern as the other CBE portal screens.

     UPDATED 17 Sep 2026 — per Chris's 4-part request: (1) a seasonal
     header banner when a cbe_season_themes range is currently active,
     (2) the member's own private birthday card (visible only to them),
     (3) each notice tinted by its own content-aware style, with an
     "Auto" badge on anything notice_source=SYSTEM posted by itself,
     (4) the birthday opt-in toggle, right here — no separate profile
     screen needed for one checkbox. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    @if($seasonTheme)
    <div style="flex-shrink:0; margin-bottom:6px; border-radius:8px; padding:10px 14px; background:{{ $seasonTheme->bg_color }}; color:#fff;">
        <div style="font-size:11.5px; font-weight:800;">{{ $seasonTheme->label }}</div>
        @if($seasonTheme->greeting_text)
        <div style="font-size:9.5px; opacity:0.9; margin-top:2px;">{{ $seasonTheme->greeting_text }}</div>
        @endif
    </div>
    @endif

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.notice_board_page_title') }}</div>
        @if($hasNode && $canManage)
        <a href="{{ route('cbe.notice-board.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.notice_board_add_button') }}</a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    @if($hasNode && $myBirthdayCard)
    <div style="flex-shrink:0; margin-bottom:6px; border-radius:8px; padding:10px 14px; background:#F1F7FF; border:1px solid #CFE1F7; color:#1E3A66;">
        <div style="font-size:11px; font-weight:800;">🎂 {{ $myBirthdayCard->title }}</div>
        <div style="font-size:9.5px; margin-top:2px;">{{ $myBirthdayCard->body }}</div>
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px;">
            <div style="display:flex; gap:6px;">
                <a href="{{ route('cbe.notice-board.index', ['tab' => 'active']) }}" style="text-decoration:none; padding:4px 12px; border-radius:14px; font-size:9.5px; font-weight:600; {{ $tab === 'active' ? 'background:var(--gl-blue); color:#fff;' : 'background:var(--gl-light); color:#546E7A;' }}">{{ __('cbe_records.notice_board_tab_active') }}</a>
                <a href="{{ route('cbe.notice-board.index', ['tab' => 'expired']) }}" style="text-decoration:none; padding:4px 12px; border-radius:14px; font-size:9.5px; font-weight:600; {{ $tab === 'expired' ? 'background:var(--gl-blue); color:#fff;' : 'background:var(--gl-light); color:#546E7A;' }}">{{ __('cbe_records.notice_board_tab_expired') }}</a>
            </div>
            <label style="display:flex; align-items:center; gap:5px; font-size:9.5px; color:#546E7A; cursor:pointer;">
                <input type="checkbox" id="shareBirthdayToggle" @checked($shareBirthday) onchange="cbeToggleBirthdayShare(this.checked)"> {{ __('cbe_records.share_birthday_toggle_label') }}
            </label>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_notice_title') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_notice_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_notice_expires') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_attachment') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_notice_offer') }}</th>
                        @if($canManage)
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($notices as $n)
                    <tr style="border-bottom:1px solid #f3f4f6; border-left:3px solid {{ $n->styleRow->accent_color }};">
                        <td style="padding:5px 8px; font-weight:600; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px;" title="{{ $n->title }}">
                            {{ $n->title }}
                            @if($n->notice_source === 'SYSTEM')
                            <span style="background:{{ $n->styleRow->accent_color }}; color:#fff; border-radius:8px; padding:1px 6px; font-size:7.5px; font-weight:800; margin-left:5px; text-transform:uppercase;">{{ __('cbe_records.auto_badge_label') }}</span>
                            @endif
                        </td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_records.notice_category_'.strtolower($n->category)) }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ $n->expires_at ? \Carbon\Carbon::parse($n->expires_at)->format('d M Y') : __('cbe_records.no_expiry_note') }}</td>
                        <td style="padding:5px 8px; white-space:nowrap;">
                            @if($n->attachment_file_path)<a href="{{ route('cbe.notice-board.attachment', $n->notice_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none; margin-right:6px;">{{ __('cbe_records.field_attach_flyer') }}</a>@endif
                            @if($n->flyer_link_url)<a href="{{ $n->flyer_link_url }}" target="_blank" rel="noopener" style="color:var(--gl-blue); font-weight:600; text-decoration:none; margin-right:6px;">{{ __('cbe_records.field_attach_flyer') }}</a>@endif
                            @if($n->catalog_file_path)<a href="{{ route('cbe.notice-board.catalog', $n->notice_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none; margin-right:6px;">{{ __('cbe_records.field_attach_catalog') }}</a>@endif
                            @if($n->catalog_link_url)<a href="{{ $n->catalog_link_url }}" target="_blank" rel="noopener" style="color:var(--gl-blue); font-weight:600; text-decoration:none; margin-right:6px;">{{ __('cbe_records.field_attach_catalog') }}</a>@endif
                            @if($n->video_file_path)<a href="{{ route('cbe.notice-board.video-file', $n->notice_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none; margin-right:6px;">{{ __('cbe_records.field_attach_video') }}</a>@endif
                            @if($n->video_link_url)<a href="{{ $n->video_link_url }}" target="_blank" rel="noopener" style="color:var(--gl-blue); font-weight:600; text-decoration:none; margin-right:6px;">{{ __('cbe_records.field_attach_video') }}</a>@endif
                            @if(!$n->attachment_file_path && !$n->flyer_link_url && !$n->catalog_file_path && !$n->catalog_link_url && !$n->video_file_path && !$n->video_link_url)
                            <span style="color:#9ca3af;">{{ __('cbe_records.no_attachment') }}</span>
                            @endif
                        </td>
                        <td style="padding:5px 8px; white-space:nowrap;">
                            @if($n->listingRow)
                            <span style="color:#546E7A; margin-right:6px;">{{ __('cbe_records.notice_offer_price_label') }}: RM {{ number_format($n->listingRow->price, 2) }}</span>
                            <form method="POST" action="{{ route('cbe.notice-board.join', $n->notice_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:#16a34a; color:#fff; border:none; border-radius:14px; padding:3px 10px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('cbe_records.notice_board_join_button') }}</button>
                            </form>
                            @else
                            <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                        @if($canManage)
                        <td style="padding:5px 8px; text-align:right; white-space:nowrap;">
                            @if($n->notice_source !== 'SYSTEM')
                            <a href="{{ route('cbe.notice-board.edit', $n->notice_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600; margin-right:8px;">{{ __('cbe_records.notice_board_edit_button') }}</a>
                            @endif
                            <form method="POST" action="{{ route('cbe.notice-board.destroy', $n->notice_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_records.notice_board_delete_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; cursor:pointer; font-size:9.5px;">{{ __('cbe_records.notice_board_delete_button') }}</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canManage ? 5 : 4 }}" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_notices_note') }}</td></tr>
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
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $notices->currentPage(), 'last' => $notices->lastPage(), 'total' => $notices->total()]) }}</span>
            @if($notices->hasMorePages())
                <a href="{{ $notices->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function cbeToggleBirthdayShare(checked) {
    fetch(@json(route('cbe.notice-board.toggle-birthday-share')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ share_birthday_public: checked })
    }).catch(function() {});
}
</script>
@endpush
@endsection
