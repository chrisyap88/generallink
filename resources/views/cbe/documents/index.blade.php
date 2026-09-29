@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_documents.page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_documents.page_title') }}</div>
        @if($hasNode && $canManage)
        <a href="{{ route('cbe.documents.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_documents.add_button') }}</a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    @if($hasNode)
    <div style="flex-shrink:0; margin-bottom:8px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
        <a href="{{ route('cbe.documents.index') }}" style="text-decoration:none; padding:4px 10px; border-radius:14px; font-size:9.5px; font-weight:600; {{ !$selectedCategoryId ? 'background:var(--gl-blue); color:#fff;' : 'background:#f5f6f8; color:#546E7A;' }}">{{ __('cbe_documents.filter_all') }}</a>
        @foreach($categories as $cat)
        <a href="{{ route('cbe.documents.index', ['category_id' => $cat->id]) }}" style="text-decoration:none; padding:4px 10px; border-radius:14px; font-size:9.5px; font-weight:600; {{ $selectedCategoryId === $cat->id ? 'background:var(--gl-blue); color:#fff;' : 'background:#f5f6f8; color:#546E7A;' }}">{{ $cat->label }}</a>
        @endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_documents.col_title') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_documents.col_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_documents.col_version') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_documents.col_uploaded') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $d)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $d->title }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $d->category_label ?? '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">v{{ $d->version }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($d->created_at)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; text-align:right;"><a href="{{ route('cbe.documents.download', $d->document_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('cbe_documents.download_button') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_documents.none_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($documents->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $documents->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $documents->currentPage(), 'last' => $documents->lastPage(), 'total' => $documents->total()]) }}</span>
            @if($documents->hasMorePages())
                <a href="{{ $documents->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
