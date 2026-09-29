@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.payment_allocation_page_title'))

@section('content')

{{-- NEW 2 Sep 2026 (Task #358) — pick a customer, then split one lump
     sum payment across several of their outstanding invoices. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.payment_allocation_page_title') }}</div>
        <a href="{{ route('cbe.accounting.invoices') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_invoices') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; display:flex; flex-direction:column; gap:8px; overflow:hidden;">
        <div style="font-size:11px; font-weight:700; color:#263238;">{{ __('cbe_accounting.payment_allocation_picker_title') }}</div>
        <div style="font-size:9.5px; color:#6b7280; margin-bottom:4px;">{{ __('cbe_accounting.payment_allocation_picker_desc') }}</div>
        <div style="display:flex; gap:8px; align-items:flex-end; max-width:480px;">
            <div style="flex:1;">
                <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_customer') }}</label>
                <select id="paCustomerSelect" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->customer_id }}" data-url="{{ route('cbe.accounting.payment-allocation.create', $c->customer_id) }}">{{ $c->customer_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" onclick="var s=document.getElementById('paCustomerSelect'); var o=s.options[s.selectedIndex]; if(o && o.dataset.url) window.location.href=o.dataset.url;" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.go_button') }}</button>
        </div>
    </div>
</div>
@endsection
