@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.integration_status_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #386) — Integration Status: one screen showing,
     for every GL-integrated sub-ledger document type, how many rows are
     Posted / Not Posted / Reversed / Error, so a treasurer can spot a
     posting problem without opening 16 different screens. Fixed set of
     rows (no pagination needed) so it fits without scrolling. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.integration_status_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_source_type') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.gl_status_posted') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.gl_status_not_posted') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.gl_status_reversed') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.gl_status_error') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $r->label }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#2e7d32;">{{ $r->posted }}</td>
                        <td style="padding:5px 8px; text-align:right; color:{{ $r->not_posted > 0 ? '#e65100' : '#9ca3af' }}; font-weight:{{ $r->not_posted > 0 ? '700' : '400' }};">{{ $r->not_posted }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ $r->reversed }}</td>
                        <td style="padding:5px 8px; text-align:right; color:{{ $r->error > 0 ? '#c62828' : '#9ca3af' }}; font-weight:{{ $r->error > 0 ? '700' : '400' }};">{{ $r->error }}</td>
                        <td style="padding:5px 8px; text-align:right;">
                            <a href="{{ route($r->route) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('cbe_accounting.jv_view_button') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; padding-top:8px; font-size:8.5px; color:#9ca3af;">{{ __('cbe_accounting.integration_status_footnote') }}</div>
    </div>
</div>
@endsection
