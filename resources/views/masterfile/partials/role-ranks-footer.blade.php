@if($paginator->onFirstPage())
    <a href="{{ route('admin.dashboard') }}" title="{{ __('masterfile.back_to_dashboard') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
@else
    <button type="button" class="rr-page-btn" data-page="{{ $paginator->currentPage() - 1 }}" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</button>
@endif
<span style="font-size:11px; color:#6b7280;">{{ __('masterfile.ranks_showing_count', ['first' => $paginator->firstItem() ?? 0, 'lastItem' => $paginator->lastItem() ?? 0, 'total' => $paginator->total(), 'current' => $paginator->currentPage(), 'lastPage' => max($paginator->lastPage(), 1)]) }}</span>
@if($paginator->hasMorePages())
    <button type="button" class="rr-page-btn" data-page="{{ $paginator->currentPage() + 1 }}" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</button>
@else
    <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; opacity:.5;">{{ __('masterfile.next') }}</span>
@endif
