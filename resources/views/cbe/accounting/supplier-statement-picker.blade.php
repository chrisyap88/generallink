@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.supplier_statement_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #375) — Supplier Statement picker, mirrors
     donor-statement-picker.blade.php / debtor-ledger-picker.blade.php's
     Statement section, substituting supplier bill/payment history. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.supplier_statement_title') }}</div>
        <a href="{{ route('cbe.accounting.reports.ap-reports') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; display:flex; flex-direction:column; gap:14px; overflow:hidden;">
        <div style="flex:1; min-height:0; display:flex; flex-direction:column;">
            <div style="font-size:11px; font-weight:700; color:#263238; margin-bottom:4px;">{{ __('cbe_accounting.supplier_statement_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ __('cbe_accounting.supplier_statement_desc') }}</div>
            <form method="GET" action="{{ route('cbe.accounting.reports.supplier-statement') }}" style="display:flex; gap:8px; align-items:flex-end; max-width:480px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_supplier') }}</label>
                    <select name="supplier_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->supplier_id }}">{{ $s->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
