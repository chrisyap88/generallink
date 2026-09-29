@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.doc_seq_reset_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.doc_seq_reset_title') }} — {{ $label }}</div>
        <a href="{{ route('cbe.accounting.document-number-control', ['year' => $year, 'month' => $month]) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="flex-shrink:0; background:var(--gl-light); border-left:3px solid var(--gl-blue); color:#37474F; border-radius:6px; padding:6px 10px; font-size:9.5px; margin-bottom:8px;">
        {{ __('cbe_accounting.doc_seq_reset_warning') }}
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.document-number-control.reset.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <input type="hidden" name="doc_type" value="{{ $docType }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">

            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; align-content:start;">
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.col_doc_last_number') }}</label>
                    <div style="padding:6px 9px; font-size:11px; color:#6b7280;">{{ $status['last_number'] }}</div>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.doc_seq_current_next') }}</label>
                    <div style="padding:6px 9px; font-size:11px; color:#6b7280;">{{ $status['next_number'] }}</div>
                </div>
                <div style="grid-column:1 / -1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.doc_seq_new_next') }}</label>
                    <input type="number" name="new_next_number" min="1" required value="{{ old('new_next_number', $status['next_number']) }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="grid-column:1 / -1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.doc_seq_reason') }}</label>
                    <input type="text" name="reason" maxlength="500" required value="{{ old('reason') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="grid-column:1 / -1;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:9.5px; color:#b71c1c; font-weight:600;">
                        <input type="checkbox" name="confirm_lower" value="1" {{ old('confirm_lower') ? 'checked' : '' }} style="width:14px; height:14px;"> {{ __('cbe_accounting.doc_seq_confirm_lower_label') }}
                    </label>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.document-number-control', ['year' => $year, 'month' => $month]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
