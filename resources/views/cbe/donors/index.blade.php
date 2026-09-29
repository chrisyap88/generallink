@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_events.donors_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_events.donors_page_title') }}</div>
        @if($hasNode)
        <a href="{{ route('cbe.donors.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_events.donor_add_button') }}</a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    @if($hasNode)
    <form method="GET" action="{{ route('cbe.donors.index') }}" style="flex-shrink:0; margin-bottom:8px;">
        <input type="text" name="search" value="{{ $search }}" onchange="this.form.submit()" placeholder="{{ __('cbe_events.search_donor_placeholder') }}" style="width:280px; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:10.5px; box-sizing:border-box;">
    </form>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_donor_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_donor_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_phone') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_email') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($donors as $d)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $d->donor_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_events.donor_type_' . strtolower($d->donor_type)) }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $d->phone ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $d->email ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_events.no_donors_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($donors->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $donors->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $donors->currentPage(), 'last' => $donors->lastPage(), 'total' => $donors->total()]) }}</span>
            @if($donors->hasMorePages())
                <a href="{{ $donors->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
