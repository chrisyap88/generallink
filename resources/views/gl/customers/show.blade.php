@extends('layouts.dashboard')

@section('title', __('gl.customers_title') . ' — ' . $customer->full_name)
@section('page-title', __('gl.customer_detail_title'))

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('gl.customers.index') }}" style="font-size:12px;color:#546E7A;text-decoration:none;">{{ __('gl.back_to_customers_link') }}</a>
        <h4 class="fw-bold mb-0 mt-1" style="color:#1565C0;">{{ $customer->full_name }}</h4>
    </div>
    <a href="{{ route('gl.customers.edit', $customer->customer_id) }}" class="btn btn-primary btn-sm">{{ __('gl.edit_customer_button') }}</a>
</div>

@if(session('success'))
<div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;">

    {{-- Left — Info --}}
    <div>
        <div style="background:#fff;border-radius:12px;border:1px solid #B2EBF2;padding:16px;box-shadow:0 2px 8px rgba(0,150,200,0.06);margin-bottom:12px;">
            <p style="font-size:11px;font-weight:700;color:#1565C0;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">{{ __('gl.personal_info_heading') }}</p>
            @foreach([[__('gl.field_full_name'),$customer->full_name],[__('gl.field_phone'),$customer->phone],[__('gl.field_email'),$customer->email??'—'],[__('gl.field_city'),$customer->city??'—'],[__('gl.field_state'),$customer->state??'—'],[__('gl.field_postcode'),$customer->postcode??'—']] as $row)
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:0.5px solid #f0f4f8;font-size:12px;">
                <span style="color:#546E7A;">{{ $row[0] }}</span>
                <span style="font-weight:500;">{{ $row[1] }}</span>
            </div>
            @endforeach
        </div>

        <div style="background:#fff;border-radius:12px;border:1px solid #B2EBF2;padding:16px;box-shadow:0 2px 8px rgba(0,150,200,0.06);">
            <p style="font-size:11px;font-weight:700;color:#1565C0;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">{{ __('gl.col_owned_by') }}</p>
            <p style="font-weight:600;font-size:13px;margin-bottom:3px;">{{ $customer->agent_name }}</p>
            <p style="color:#546E7A;font-size:12px;margin-bottom:6px;">{{ $customer->agent_code }}</p>
            @php $rc=['GROUP_LEADER'=>['#E1F5EE','#0F6E56'],'TEAM_LEADER'=>['#E6F1FB','#0C447C'],'INTRODUCER'=>['#F3E8FF','#6B21A8']][$customer->agent_role]??['#f3f4f6','#374151']; @endphp
            <span style="font-size:11px;padding:2px 10px;border-radius:20px;background:{{ $rc[0] }};color:{{ $rc[1] }};">
                {{ \App\Services\RoleLabelService::label($customer->agent_role) }}
            </span>
        </div>
    </div>

    {{-- Right --}}
    <div>
        {{-- Transaction summary bar --}}
        @php
            $txnCollection = collect($transactions);
            $totalPremium = $txnCollection->sum('premium_amount');
        @endphp
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:12px;">
            @foreach([[__('gl.stat_total'),$txnCollection->count(),'#1565C0'],[__('gl.stat_active'),$txnCollection->where('status','ACTIVE')->count(),'#2e7d32'],[__('gl.stat_pending_renewal'),$txnCollection->where('status','PENDING_RENEWAL')->count(),'#e65100'],[__('gl.stat_lapsed'),$txnCollection->where('status','LAPSED')->count(),'#c62828'],[__('gl.stat_total_premium'),'RM '.number_format($totalPremium,2),'#1565C0']] as $s)
            <div style="background:#fff;border-radius:10px;border:1px solid #B2EBF2;padding:10px;text-align:center;">
                <p style="font-size:10px;color:#546E7A;margin:0 0 3px;text-transform:uppercase;letter-spacing:0.5px;">{{ $s[0] }}</p>
                <p style="font-size:{{ is_numeric($s[1]) ? '18px' : '13px' }};font-weight:700;color:{{ $s[2] }};margin:0;">{{ $s[1] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Transactions table with scroll --}}
        <div style="background:#fff;border-radius:12px;border:1px solid #B2EBF2;box-shadow:0 2px 8px rgba(0,150,200,0.06);margin-bottom:12px;">
            <div style="padding:12px 16px;border-bottom:1px solid #f0f4f8;display:flex;justify-content:space-between;align-items:center;">
                <p style="font-size:12px;font-weight:700;color:#1565C0;text-transform:uppercase;letter-spacing:0.5px;margin:0;">
                    {{ __('gl.col_transactions') }} <span style="font-weight:400;color:#546E7A;">({{ count($transactions) }})</span>
                </p>
            </div>

            {{-- Scrollable table --}}
            <div style="max-height:380px;overflow-y:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed;">
                    <thead style="position:sticky;top:0;background:#f0f7ff;z-index:1;">
                        <tr>
                            <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #B2EBF2;width:18%;">{{ __('gl.col_product_code') }}</th>
                            <th style="padding:8px 8px;text-align:left;font-size:10px;font-weight:700;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #B2EBF2;">{{ __('gl.col_product_vendor') }}</th>
                            <th style="padding:8px 8px;text-align:left;font-size:10px;font-weight:700;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #B2EBF2;">{{ __('gl.col_coverage') }}</th>
                            <th style="padding:8px 8px;text-align:right;font-size:10px;font-weight:700;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #B2EBF2;">{{ __('gl.col_premium') }}</th>
                            <th style="padding:8px 8px;text-align:left;font-size:10px;font-weight:700;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;border-bottom:1px solid #B2EBF2;">{{ __('gl.col_status') }}</th>
                            <th style="padding:8px 4px;width:24px;border-bottom:1px solid #B2EBF2;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $txn)
                        <tr style="border-bottom:0.5px solid #f0f4f8;cursor:pointer;" onclick="toggleDetail('{{ $txn->policy_id }}')" class="txn-row">
                            <td style="padding:10px 12px;">
                                <a href="{{ route('gl.transactions.show', $txn->policy_id) }}?from=customer"
                                   style="font-weight:600;color:#185FA5;text-decoration:none;"
                                   onclick="event.stopPropagation();">
                                    {{ $txn->product_code }}
                                </a><br>
                                <span style="font-size:10px;color:#546E7A;">{{ $txn->policy_number }}</span>
                            </td>
                            <td style="padding:10px 8px;">
                                {{ $txn->product_name }}<br>
                                <span style="font-size:10px;color:#546E7A;">{{ $txn->product_type }} · {{ $txn->vendor_name }}</span>
                            </td>
                            <td style="padding:10px 8px;color:#546E7A;">
                                @if($txn->coverage_start && $txn->coverage_end)
                                {{ \Carbon\Carbon::parse($txn->coverage_start)->format('d M Y') }}<br>
                                <span style="font-size:10px;">{{ __('gl.to_word') }} {{ \Carbon\Carbon::parse($txn->coverage_end)->format('d M Y') }}</span>
                                @else
                                <span style="color:#9ca3af;">—</span>
                                @endif
                            </td>
                            <td style="padding:10px 8px;text-align:right;font-weight:600;color:#1565C0;">
                                RM {{ number_format($txn->premium_amount,2) }}
                            </td>
                            <td style="padding:10px 8px;">
                                @php $b=['ACTIVE'=>['#d1fae5','#065f46'],'SUBMITTED'=>['#dbeafe','#1e40af'],'DRAFT'=>['#f3f4f6','#374151'],'PENDING_RENEWAL'=>['#fef3c7','#92400e'],'RENEWED'=>['#e0f2fe','#075985'],'LAPSED'=>['#fee2e2','#991b1b'],'CANCELLED'=>['#f3f4f6','#6b7280']][$txn->status]??['#f3f4f6','#374151']; @endphp
                                <span style="background:{{ $b[0] }};color:{{ $b[1] }};padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;">
                                    {{ ucwords(strtolower(str_replace('_',' ',$txn->status))) }}
                                </span>
                            </td>
                            <td style="padding:10px 8px;text-align:center;">
                                <span id="arrow-{{ $txn->policy_id }}" style="font-size:12px;color:#546E7A;">▼</span>
                            </td>
                        </tr>
                        {{-- Expandable detail row --}}
                        <tr id="detail-{{ $txn->policy_id }}" style="display:none;background:#f8fbff;">
                            <td colspan="6" style="padding:12px 16px;">
                                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;font-size:12px;">
                                    <div>
                                        <p style="font-size:10px;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">{{ __('network.col_vendor') }}</p>
                                        <p style="font-weight:500;margin:0;">{{ $txn->vendor_name }}</p>
                                    </div>
                                    <div>
                                        <p style="font-size:10px;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">{{ __('gl.sum_insured_label') }}</p>
                                        <p style="font-weight:500;margin:0;">RM {{ number_format($txn->sum_insured,2) }}</p>
                                    </div>
                                    <div>
                                        <p style="font-size:10px;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">{{ __('gl.renewal_date_label') }}</p>
                                        <p style="font-weight:500;margin:0;">{{ $txn->renewal_date ? \Carbon\Carbon::parse($txn->renewal_date)->format('d M Y') : '—' }}</p>
                                    </div>
                                    <div>
                                        <p style="font-size:10px;color:#546E7A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">{{ __('gl.full_detail_label') }}</p>
                                        <a href="{{ route('gl.transactions.show', $txn->policy_id) }}?from=customer"
                                           style="font-size:12px;color:#185FA5;text-decoration:none;font-weight:500;">
                                            {{ __('gl.view_full_detail_link') }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="padding:30px;text-align:center;color:#999;">{{ __('gl.no_transactions_found_dot') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Claims --}}
        <div style="background:#fff;border-radius:12px;border:1px solid #B2EBF2;box-shadow:0 2px 8px rgba(0,150,200,0.06);">
            <div style="padding:12px 16px;border-bottom:1px solid #f0f4f8;">
                <p style="font-size:12px;font-weight:700;color:#1565C0;text-transform:uppercase;letter-spacing:0.5px;margin:0;">
                    {{ __('gl.claims_heading') }} <span style="font-weight:400;color:#546E7A;">({{ count($claims) }})</span>
                </p>
            </div>
            <div style="max-height:200px;overflow-y:auto;">
                @forelse($claims as $claim)
                <div style="padding:10px 16px;border-bottom:0.5px solid #f0f4f8;font-size:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-weight:600;color:#185FA5;">{{ $claim->claim_reference }}</span>
                        @php $cs=['PENDING'=>['#fef3c7','#92400e'],'MATCHED'=>['#dbeafe','#1e40af'],'CONFIRMED'=>['#e0f2fe','#075985'],'PAID'=>['#d1fae5','#065f46'],'REJECTED'=>['#fee2e2','#991b1b'],'DISPUTED'=>['#f3f4f6','#374151']][$claim->status]??['#f3f4f6','#374151']; @endphp
                        <span style="background:{{ $cs[0] }};color:{{ $cs[1] }};padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;">{{ $claim->status }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;color:#546E7A;margin-top:3px;">
                        <span>{{ $claim->product_name }} · {{ $claim->vendor_name }}</span>
                        <span style="font-weight:600;color:#1565C0;">RM {{ number_format($claim->claim_amount,2) }}</span>
                    </div>
                    @if($claim->commission_held)
                    <span style="font-size:10px;background:#FCEBEB;color:#A32D2D;padding:2px 8px;border-radius:20px;margin-top:4px;display:inline-block;">
                        <i class="ti ti-lock"></i> {{ __('gl.earning_income_on_hold_note') }}
                    </span>
                    @endif
                </div>
                @empty
                <div style="padding:20px;text-align:center;color:#999;font-size:12px;">{{ __('gl.no_claims_found') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    const arrow = document.getElementById('arrow-' + id);
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
        arrow.textContent = '▲';
    } else {
        row.style.display = 'none';
        arrow.textContent = '▼';
    }
}
</script>
@endpush

@endsection
