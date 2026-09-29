@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.donor_statement_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #368) — Donor Statement picker, mirrors
     debtor-ledger-picker.blade.php's Statement section exactly, but for
     a donor's Pledge/Receipt running balance instead of a customer's
     Invoice/Payment running balance. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.donor_statement_title') }}</div>
        <a href="{{ route('cbe.accounting.donation-pledges') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; display:flex; flex-direction:column; gap:14px; overflow:hidden;">
        <div style="flex:1; min-height:0; display:flex; flex-direction:column;">
            <div style="font-size:11px; font-weight:700; color:#263238; margin-bottom:4px;">{{ __('cbe_accounting.donor_statement_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ __('cbe_accounting.donor_statement_desc') }}</div>
            <form method="GET" action="{{ route('cbe.accounting.reports.donor-statement') }}" style="display:flex; gap:8px; align-items:flex-end; max-width:480px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe.sb_donor_register') }}</label>
                    <select name="donor_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($donors as $d)
                        <option value="{{ $d->donor_id }}">{{ $d->donor_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
