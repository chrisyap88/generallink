@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.annual_report_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:10px;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.annual_report_page_title') }}</div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:20px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <form method="GET" action="{{ route('cbe.annual-report.index') }}" style="flex-shrink:0; display:flex; align-items:flex-end; gap:10px; margin-bottom:20px;">
            <div>
                <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_year') }}</label>
                <select name="year" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px;">
                    @foreach(range(now()->year, now()->year - 9) as $y)
                    <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div style="display:flex; gap:16px; flex:1; min-height:0;">
            <div style="flex:1; border:1px solid #E2E8F0; border-radius:8px; padding:16px; display:flex; flex-direction:column;">
                <div style="font-size:11.5px; font-weight:700; color:#263238; margin-bottom:6px;">{{ __('cbe_records.secretary_report_title') }}</div>
                <div style="font-size:10px; color:#6b7280; flex:1;">{{ __('cbe_records.secretary_report_desc') }}</div>
                <a href="{{ route('cbe.annual-report.secretary', ['year' => $selectedYear]) }}" style="align-self:flex-start; background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:7px 18px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.download_button') }}</a>
            </div>
            <div style="flex:1; border:1px solid #E2E8F0; border-radius:8px; padding:16px; display:flex; flex-direction:column;">
                <div style="font-size:11.5px; font-weight:700; color:#263238; margin-bottom:6px;">{{ __('cbe_records.income_expenditure_title') }}</div>
                <div style="font-size:10px; color:#6b7280; flex:1;">{{ __('cbe_records.income_expenditure_desc') }}</div>
                <a href="{{ route('cbe.annual-report.income-expenditure', ['year' => $selectedYear]) }}" style="align-self:flex-start; background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:7px 18px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.download_button') }}</a>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
