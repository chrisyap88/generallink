@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.reason_code_maintenance'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:4px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif

    @if(!$hasAnyFilter)

    <div style="margin-bottom:6px;">
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.dashboard_link') }}</a>
    </div>

    <div style="display:flex; gap:10px;">
        <a href="{{ route('admin.masterfile.reason-codes.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.add_new_reason_code') }}</a>
        <a href="{{ route('admin.masterfile.reason-codes.search') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
    </div>

    @else

    <div style="margin-bottom:4px;">
        <a href="{{ route('admin.masterfile.reason-codes.search') }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">{{ __('masterfile.modify_search') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:30px;">#</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_category') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_code') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_description') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.status') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reasonCodes as $r)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; color:#9ca3af;">{{ $reasonCodes->firstItem() + $loop->index }}</td>
                    <td style="padding:5px 8px;">
                        <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600; background:#E0F7FA; color:#1565C0;">{{ $r->category }}</span>
                    </td>
                    <td style="padding:5px 8px; font-family:monospace; font-size:11px; color:#4b5563;">{{ $r->code }}</td>
                    <td style="padding:5px 8px; font-weight:600; color:#1565C0;">{{ $r->description }}</td>
                    <td style="padding:5px 8px;">
                        <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600; {{ $r->is_active ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#f3f4f6;color:#6b7280;' }}">{{ $r->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span>
                    </td>
                    <td style="padding:5px 8px;">
                        <a href="{{ route('admin.masterfile.reason-codes.edit', $r->reason_code_id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('masterfile.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('masterfile.no_reason_codes_match') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($reasonCodes instanceof \Illuminate\Pagination\LengthAwarePaginator && $reasonCodes->total() > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($reasonCodes->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $reasonCodes->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif
        <span style="font-size:10.5px; color:#4b5563;">{{ __('masterfile.showing_records', ['first' => $reasonCodes->firstItem(), 'last' => $reasonCodes->lastItem(), 'total' => $reasonCodes->total()]) }} &nbsp;|&nbsp; {{ __('masterfile.page_of', ['current' => $reasonCodes->currentPage(), 'last' => $reasonCodes->lastPage()]) }}</span>
        @if($reasonCodes->hasMorePages())
            <a href="{{ $reasonCodes->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif

    @endif

</div>
@endsection
