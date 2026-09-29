@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.doc_number_control_title'))

@section('content')

{{-- REBUILT 22 Sep 2026 -- per Chris: no screen may show its records
     before a selection is made, and Prev/Next must be at the bottom,
     left and right, never at the top. A Year/Month picker now gates the
     table; once a month is chosen, Prev/Next (still meaning "previous
     month" / "next month" here) sit at the bottom of the card. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.doc_number_control_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    <div style="flex-shrink:0; font-size:9px; color:#546E7A; margin-bottom:6px;">{{ __('cbe_accounting.doc_number_control_note') }}</div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    @if(! $hasSelection)

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; display:flex; flex-direction:column; align-items:flex-start; gap:14px;">
        <div style="font-size:11px; color:#6b7280; max-width:420px;">{{ __('cbe_accounting.doc_number_control_select_hint') }}</div>
        <form method="GET" action="{{ route('cbe.accounting.document-number-control') }}" style="display:flex; gap:10px; align-items:flex-end;">
            <div style="width:150px;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.doc_number_control_year_label') }}</label>
                <select name="year" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
                    @foreach(range($defaultYear - 3, $defaultYear + 1) as $y)
                    <option value="{{ $y }}" {{ $y === $defaultYear ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div style="width:170px;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.doc_number_control_month_label') }}</label>
                <select name="month" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
                    @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}" {{ $m === $defaultMonth ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F') }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:8px 22px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.doc_number_control_view_button') }}</button>
        </form>
    </div>

    @else

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">

        <div style="flex-shrink:0; margin-bottom:6px;">
            <a href="{{ route('cbe.accounting.document-number-control') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10px; font-weight:600;">{{ __('cbe_accounting.doc_number_control_change_month') }}</a>
        </div>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_band') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_last_number') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_next_number') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ __('cbe_accounting.'.$r['label_key']) }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ sprintf('%02d000', $month) }} – {{ sprintf('%02d999', $month) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ $r['last_number'] }}</td>
                        <td style="padding:5px 8px; color:#263238; font-weight:600; white-space:nowrap;">{{ $r['sample_next'] }}</td>
                        <td style="padding:5px 8px; text-align:right; white-space:nowrap;">
                            @if($isAdmin)
                            <a href="{{ route('cbe.accounting.document-number-control.reset', ['doc_type' => $r['doc_type'], 'year' => $year, 'month' => $month]) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600; font-size:9.5px;">{{ __('cbe_accounting.doc_seq_reset_button') }}</a>
                            @else
                            <span style="color:#c4c9d0; font-size:9.5px;">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            <a href="{{ route('cbe.accounting.document-number-control', ['year' => $prevYear, 'month' => $prevMonth]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            <span style="font-size:11px; font-weight:700; color:#263238;">{{ $monthLabel }}</span>
            <a href="{{ route('cbe.accounting.document-number-control', ['year' => $nextYear, 'month' => $nextMonth]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
        </div>
    </div>

    @endif

</div>
@endsection
