@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.customers_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.customers_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.customer-categories') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.customer_categories_page_title') }}</a>
            <a href="{{ route('cbe.accounting.customers.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_customer_button') }}</a>
            <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_customer_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_customer_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_contact_person') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_phone') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">
                            <a href="{{ route('cbe.accounting.customers.enquiry', $c->customer_id) }}" style="color:#263238; text-decoration:none;">{{ $c->customer_name }}</a>
                        </td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $c->category_name ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $c->contact_person ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $c->phone ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_customers_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($customers->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $customers->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $customers->currentPage(), 'last' => $customers->lastPage(), 'total' => $customers->total()]) }}</span>
            @if($customers->hasMorePages())
                <a href="{{ $customers->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
