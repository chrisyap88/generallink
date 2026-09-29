@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.customer_source_maintenance'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('masterfile.back_to_dashboard') }}</a>
        <a href="{{ route('admin.masterfile.customer-sources.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:600;">{{ __('masterfile.add_new_source') }}</a>
    </div>

    {{-- REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
         records by default. Nothing is queried or shown until a search
         term is entered and submitted. --}}
    <form method="GET" action="{{ route('admin.masterfile.customer-sources') }}" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; margin-bottom:8px; display:flex; gap:8px; align-items:flex-end;">
        <div style="flex:1;">
            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:4px;">{{ __('masterfile.search_by_desc_or_code') }}</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('masterfile.start_typing') }}" autofocus style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
        </div>
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
    </form>

    @if(is_null($sources))
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:30px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('masterfile.list_search_start_hint') }}</div>
    @else
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_code') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_description') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.status') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sources as $s)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-family:monospace; font-size:11px; color:#4b5563;">{{ $s->code }}</td>
                    <td style="padding:5px 8px; font-weight:600; color:#1565C0;">{{ $s->description }}</td>
                    <td style="padding:5px 8px;">
                        <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600; {{ $s->is_active ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#f3f4f6;color:#6b7280;' }}">{{ $s->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span>
                    </td>
                    <td style="padding:5px 8px;">
                        <a href="{{ route('admin.masterfile.customer-sources.edit', $s->source_id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600; margin-right:8px;">{{ __('masterfile.edit') }}</a>
                        <form method="POST" action="{{ route('admin.masterfile.customer-sources.destroy', $s->source_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('masterfile.remove_source_confirm')) }});">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:11px; cursor:pointer; padding:0;">{{ __('masterfile.remove') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('masterfile.no_customer_sources_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sources->total() > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($sources->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $sources->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif
        <span style="font-size:10.5px; color:#4b5563;">{{ __('masterfile.showing_records', ['first' => $sources->firstItem(), 'last' => $sources->lastItem(), 'total' => $sources->total()]) }} &nbsp;|&nbsp; {{ __('masterfile.page_of', ['current' => $sources->currentPage(), 'last' => $sources->lastPage()]) }}</span>
        @if($sources->hasMorePages())
            <a href="{{ $sources->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif
    @endif

</div>
@endsection
