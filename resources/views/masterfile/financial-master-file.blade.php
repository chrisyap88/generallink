@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile_hub.financial_hub_title'))

@section('content')
@php
$fmfItems = [
    ['route' => route('cbe.accounting.customers'), 'label' => __('cbe_accounting.tile_debtor_master')],
    ['route' => route('cbe.accounting.customer-categories'), 'label' => __('cbe_accounting.tile_debtor_category')],
    ['route' => route('cbe.accounting.suppliers'), 'label' => __('cbe_accounting.tile_creditor_master')],
    ['route' => route('cbe.accounting.supplier-categories'), 'label' => __('cbe_accounting.tile_creditor_category')],
    ['route' => route('cbe.accounting.chart-of-accounts'), 'label' => __('cbe_accounting.tile_chart_of_accounts')],
    ['route' => route('cbe.accounting.account-categories'), 'label' => __('cbe_accounting.tile_transaction_type')],
    ['route' => route('cbe.accounting.document-number-control'), 'label' => __('cbe_accounting.tile_doc_number_control')],
    ['route' => route('cbe.accounting.asset-categories'), 'label' => __('cbe_accounting.tile_fa_category')],
    ['route' => route('cbe.accounting.asset-locations'), 'label' => __('cbe_accounting.tile_fa_location')],
    ['route' => route('cbe.finance.bank-accounts'), 'label' => __('cbe_accounting.tile_bank_account_number')],
    ['route' => route('cbe.finance.bank-transaction-types'), 'label' => __('cbe_accounting.tile_bank_transaction_types_master')],
    ['route' => route('cbe.accounting.bank-reconciliation-rules'), 'label' => __('cbe_accounting.tile_reconciliation_rules')],
    ['route' => route('cbe.accounting.payment-terms'), 'label' => __('cbe_accounting.tile_payment_terms_master')],
    ['route' => route('cbe.accounting.payment-methods'), 'label' => __('cbe_accounting.tile_payment_methods_master')],
    ['route' => route('cbe.accounting.funds'), 'label' => __('cbe_accounting.tile_fund_master')],
    ['route' => route('cbe.accounting.tax-rates'), 'label' => __('cbe_accounting.tile_tax_rates_master')],
];
@endphp
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:6px;">
        <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('cbe_masterfile_hub.financial_hub_title') }}</div>
        <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('cbe_masterfile_hub.financial_hub_intro') }}</div>
    </div>

    @php $pageSize = 8; $total = count($fmfItems); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            @foreach($fmfItems as $i => $item)
            <a href="{{ $item['route'] }}" class="cbeFMFRow" data-page="{{ intdiv($i, $pageSize) + 1 }}" style="display:{{ $i < $pageSize ? 'flex' : 'none' }}; align-items:center; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #f3f4f6; font-size:11px; color:#374151; text-decoration:none;">
                <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $item['label'] }}</span>
                <span style="color:#1565C0; font-weight:600; font-size:9.5px; flex-shrink:0; margin-left:10px;">{{ __('cbe_masterfile_hub.go_link') }} ›</span>
            </a>
            @endforeach
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeFMFPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeFMFPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeFMFPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    <script>
    (function () {
        var cbeFMFCurrentPage = 1;
        var cbeFMFTotalPages = {{ $totalPages }};
        window.cbeFMFPageNav = function (dir) {
            var next = cbeFMFCurrentPage + dir;
            if (next < 1 || next > cbeFMFTotalPages) return;
            cbeFMFCurrentPage = next;
            document.querySelectorAll('.cbeFMFRow').forEach(function (row) {
                row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeFMFCurrentPage) ? 'flex' : 'none';
            });
            document.getElementById('cbeFMFPageLabel').textContent = cbeFMFCurrentPage + ' / ' + cbeFMFTotalPages + ' ({{ $total }})';
        };
    })();
    </script>
    @endif
</div>
@endsection
