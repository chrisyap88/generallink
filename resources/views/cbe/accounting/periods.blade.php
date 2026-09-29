@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.periods_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.periods_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column;">

        <div style="flex-shrink:0; display:flex; justify-content:center; align-items:center; gap:16px; margin-bottom:10px;">
            <a href="{{ route('cbe.accounting.periods', ['year' => $year - 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            <span style="font-size:12.5px; font-weight:700; color:#263238;">{{ $year }}</span>
            <a href="{{ route('cbe.accounting.periods', ['year' => $year + 1]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
        </div>

        <div style="flex:1; min-height:0; display:grid; grid-template-columns:repeat(4, 1fr); grid-template-rows:repeat(3, 1fr); gap:10px;">
            @foreach($months as $m)
            <div style="border:1px solid #e5e7eb; border-radius:8px; padding:8px; display:flex; flex-direction:column; justify-content:space-between; background:{{ $m['status'] === 'CLOSED' ? '#f7f7f5' : '#fbfdff' }};">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11.5px; font-weight:700; color:#263238;">{{ $m['label'] }}</span>
                    @if($m['status'] === 'CLOSED')
                        <span style="background:#e0e0e0; color:#546E7A; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:700;">{{ __('cbe_accounting.period_closed_label') }}</span>
                    @else
                        <span style="background:#e8f5e9; color:#1b5e20; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:700;">{{ __('cbe_accounting.period_open_label') }}</span>
                    @endif
                </div>
                <div style="font-size:8px; color:#9ca3af; margin:4px 0;">
                    @if($m['status'] === 'CLOSED' && $m['closed_at'])
                        {{ __('cbe_accounting.period_closed_on', ['date' => \Carbon\Carbon::parse($m['closed_at'])->format('d M Y')]) }}
                    @else
                        &nbsp;
                    @endif
                </div>
                <div>
                    @if($m['status'] === 'OPEN')
                    <form method="POST" action="{{ route('cbe.accounting.periods.close') }}" onsubmit="return confirm('{{ __('cbe_accounting.period_close_confirm') }}');">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="month" value="{{ $m['month'] }}">
                        <button type="submit" style="width:100%; background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:5px 0; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.close_period_button') }}</button>
                    </form>
                    @elseif($isAdmin)
                    <form method="POST" action="{{ route('cbe.accounting.periods.reopen') }}" onsubmit="return confirm('{{ __('cbe_accounting.period_reopen_confirm') }}');">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="month" value="{{ $m['month'] }}">
                        <button type="submit" style="width:100%; background:#fff; color:#e53935; border:1px solid #e53935; border-radius:6px; padding:5px 0; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.reopen_period_button') }}</button>
                    </form>
                    @else
                    <span style="display:block; text-align:center; font-size:8.5px; color:#c4c9d0;">—</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
