{{-- NEW 2 Aug 2026 — per Chris: Laravel's built-in pagination view assumes
     Tailwind CSS is loaded (uses classes like "hidden sm:flex", "w-5 h-5").
     This app never loads Tailwind (every screen uses plain inline styles),
     so those classes do nothing: BOTH the "mobile" and "desktop" blocks in
     the default view rendered at once, including two completely unstyled
     SVG arrow icons at their raw browser-default size (huge, solid black) —
     that's what looked like a giant black shape swallowing the table rows
     wherever a list spanned more than one page. This replacement uses only
     plain HTML/CSS matching the rest of the app, registered as the default
     pagination view for the whole app in AppServiceProvider, so every
     existing and future ->links() call anywhere is fixed at once. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;font-size:10px;">
        <div style="color:#718096;">
            {{ __('Showing') }}
            <strong>{{ $paginator->firstItem() }}</strong>
            {{ __('to') }}
            <strong>{{ $paginator->lastItem() }}</strong>
            {{ __('of') }}
            <strong>{{ $paginator->total() }}</strong>
            {{ __('results') }}
        </div>

        <div style="display:flex;align-items:center;gap:3px;">
            @if ($paginator->onFirstPage())
                <span style="padding:3px 9px;border-radius:4px;border:1px solid #e2e8f0;color:#cbd5e0;background:#f7fafc;">{!! '&laquo;' !!} {{ __('pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="padding:3px 9px;border-radius:4px;border:1px solid #B2EBF2;color:#0D5A8E;background:#fff;text-decoration:none;">{!! '&laquo;' !!} {{ __('pagination.previous') }}</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span style="padding:3px 6px;color:#9ca3af;">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" style="padding:3px 9px;border-radius:4px;background:#1565C0;color:#fff;font-weight:700;">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" style="padding:3px 9px;border-radius:4px;border:1px solid #e2e8f0;color:#374151;background:#fff;text-decoration:none;">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="padding:3px 9px;border-radius:4px;border:1px solid #B2EBF2;color:#0D5A8E;background:#fff;text-decoration:none;">{{ __('pagination.next') }} {!! '&raquo;' !!}</a>
            @else
                <span style="padding:3px 9px;border-radius:4px;border:1px solid #e2e8f0;color:#cbd5e0;background:#f7fafc;">{{ __('pagination.next') }} {!! '&raquo;' !!}</span>
            @endif
        </div>
    </nav>
@endif
