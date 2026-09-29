@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_practitioner_types.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:4px 16px; box-sizing:border-box;">

    @if(session('practitioner_type_saved'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ __('admin_practitioner_types.saved') }}</div>
    @endif

    @if(!$hasAnyFilter)

    <div style="margin-bottom:6px;">
        <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('admin_practitioner_types.page_title') }}</div>
        <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('admin_practitioner_types.catalog_helper') }}</div>
    </div>

    <div style="display:flex; gap:10px;">
        <a href="{{ route('admin.practitioner-types.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('admin_practitioner_types.add_new_type') }}</a>
        <a href="{{ route('admin.practitioner-types.search') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
    </div>

    @else

    <div style="margin-bottom:6px; display:flex; align-items:center; gap:14px;">
        <a href="{{ route('admin.practitioner-types.search') }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">{{ __('masterfile.modify_search') }}</a>
        <a href="{{ route('admin.practitioner-types.create') }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">{{ __('admin_practitioner_types.add_new_type') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:30px;">#</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioner_types.col_type_label') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:70px;">{{ __('admin_practitioner_types.col_type') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:70px;">{{ __('masterfile.status') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $t)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; color:#9ca3af;">{{ $types->firstItem() + $loop->index }}</td>
                    <td style="padding:5px 8px; font-weight:600; color:#1565C0;">{{ $t->type_label }}</td>
                    <td style="padding:5px 8px;">
                        <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600; {{ $t->is_system ? 'background:#E0F7FA;color:#1565C0;' : 'background:#F3E5F5;color:#6A1B9A;' }}">{{ $t->is_system ? __('admin_practitioner_types.col_system') : __('admin_practitioner_types.custom') }}</span>
                    </td>
                    <td style="padding:5px 8px;">
                        <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600; {{ $t->is_active ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#f3f4f6;color:#6b7280;' }}">{{ $t->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span>
                    </td>
                    <td style="padding:5px 8px;">
                        <a href="{{ route('admin.practitioner-types.edit', $t->id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('masterfile.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('admin_practitioner_types.no_types_match') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($types instanceof \Illuminate\Pagination\LengthAwarePaginator && $types->total() > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($types->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $types->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif
        <span style="font-size:10.5px; color:#4b5563;">{{ __('masterfile.showing_records', ['first' => $types->firstItem(), 'last' => $types->lastItem(), 'total' => $types->total()]) }} &nbsp;|&nbsp; {{ __('masterfile.page_of', ['current' => $types->currentPage(), 'last' => $types->lastPage()]) }}</span>
        @if($types->hasMorePages())
            <a href="{{ $types->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif

    @endif

</div>
@endsection
