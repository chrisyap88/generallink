@extends('layouts.dashboard')
@section('title', __('policy.page_title'))
@section('page-title', __('policy.page_title'))

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ session('error') }}</div>
@endif

{{-- Submit new policy form --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-file-plus" style="color:#0D5A8E"></i> {{ __('policy.submit_new_policy_title') }}</div>

    <form method="POST" action="{{ route('policies.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px">

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_customer_required') }}</label>
                <select name="customer_id" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff" required>
                    <option value="">{{ __('policy.select_customer_placeholder') }}</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->customer_id }}" {{ old('customer_id') == $c->customer_id ? 'selected':'' }}>{{ $c->full_name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_vendor_required') }}</label>
                <select name="vendor_id" id="vendor-select" onchange="filterProducts()" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff" required>
                    <option value="">{{ __('policy.select_vendor_placeholder') }}</option>
                    @foreach($vendors as $v)
                    <option value="{{ $v->vendor_id }}">{{ $v->vendor_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_product_required') }}</label>
                <select name="product_id" id="product-select" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff" required>
                    <option value="">{{ __('policy.select_product_placeholder') }}</option>
                    @foreach($products as $p)
                    <option value="{{ $p->product_id }}" data-vendor="{{ $p->vendor_id }}" data-type="{{ $p->product_type }}">{{ $p->product_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_policy_number_required') }}</label>
                <input type="text" name="policy_number" value="{{ old('policy_number') }}" required
                    placeholder="{{ __('policy.policy_number_example_placeholder') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                @error('policy_number')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_premium_amount_required') }}</label>
                <input type="number" name="premium_amount" value="{{ old('premium_amount') }}" required step="0.01" min="0.01"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                @error('premium_amount')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_sum_insured') }}</label>
                <input type="number" name="sum_insured" value="{{ old('sum_insured') }}" step="0.01" min="0"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_coverage_start_required') }}</label>
                <input type="date" name="coverage_start" value="{{ old('coverage_start', date('Y-m-d')) }}" required
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('policy.field_coverage_end_required') }}</label>
                <input type="date" name="coverage_end" value="{{ old('coverage_end', date('Y-m-d', strtotime('+1 year'))) }}" required
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>
        </div>

        {{-- Dynamic product-specific fields --}}
        <div id="motor-fields" style="display:none;background:#F7FAFC;border-radius:8px;padding:14px;margin-bottom:14px">
            <div style="font-size:12px;font-weight:600;color:#4A5568;margin-bottom:10px"><i class="ti ti-car"></i> {{ __('policy.motor_insurance_details_title') }}</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                @foreach(['vehicle_reg_no'=>__('policy.field_vehicle_reg_no'),'engine_no'=>__('policy.field_engine_no'),'chassis_no'=>__('policy.field_chassis_no'),'vehicle_make'=>__('policy.field_vehicle_make'),'vehicle_model'=>__('policy.field_vehicle_model'),'ncd_pct'=>__('policy.field_ncd_pct')] as $field=>$label)
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ $label }}</label>
                    <input type="text" name="attributes[{{ $field }}]" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
                @endforeach
            </div>
        </div>

        <div id="pa-fields" style="display:none;background:#F7FAFC;border-radius:8px;padding:14px;margin-bottom:14px">
            <div style="font-size:12px;font-weight:600;color:#4A5568;margin-bottom:10px"><i class="ti ti-shield-check"></i> {{ __('policy.personal_accident_details_title') }}</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                @foreach(['occupation'=>__('policy.field_occupation'),'coverage_scope'=>__('policy.field_coverage_scope')] as $field=>$label)
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ $label }}</label>
                    <input type="text" name="attributes[{{ $field }}]" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
                @endforeach
            </div>
        </div>

        <div id="fire-fields" style="display:none;background:#F7FAFC;border-radius:8px;padding:14px;margin-bottom:14px">
            <div style="font-size:12px;font-weight:600;color:#4A5568;margin-bottom:10px"><i class="ti ti-home"></i> {{ __('policy.fire_insurance_details_title') }}</div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">
                @foreach(['property_address'=>__('policy.field_property_address'),'property_type'=>__('policy.field_property_type'),'postcode'=>__('policy.field_postcode')] as $field=>$label)
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ $label }}</label>
                    <input type="text" name="attributes[{{ $field }}]" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
                @endforeach
            </div>
        </div>

        <button type="submit"
            style="background:#0D5A8E;color:#fff;padding:9px 22px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            <i class="ti ti-file-check"></i> {{ __('policy.submit_policy_button') }}
        </button>
    </form>
</div>

{{-- Filters + policy list --}}
@php
    $policyStatusLabels = [
        'ACTIVE' => __('growth.status_active'),
        'CANCELLED' => __('gl.status_cancelled'),
        'LAPSED' => __('policy.status_lapsed'),
        'RENEWED' => __('gl.status_renewed'),
        'PENDING_RENEWAL' => __('policy.status_pending_renewal'),
    ];
@endphp
<div class="card">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
        <div class="card-title" style="margin-bottom:0"><i class="ti ti-file-invoice" style="color:#0D5A8E"></i> {{ __('policy.all_policies_title') }}</div>
        <form method="GET" style="display:flex;gap:8px;margin-left:auto;flex-wrap:wrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('policy.search_policy_placeholder') }}"
                style="padding:6px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;width:180px">
            <select name="status" style="padding:6px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;background:#fff">
                <option value="">{{ __('breakaway.all_statuses_option') }}</option>
                @foreach(['ACTIVE','CANCELLED','LAPSED','RENEWED','PENDING_RENEWAL'] as $s)
                <option value="{{ $s }}" {{ request('status') == $s ? 'selected':'' }}>{{ $policyStatusLabels[$s] }}</option>
                @endforeach
            </select>
            <button type="submit" style="background:#0D5A8E;color:#fff;padding:6px 14px;border:none;border-radius:8px;font-size:12px;cursor:pointer">{{ __('gl.filter_button') }}</button>
        </form>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach([__('policy.col_policy_no'),__('gl.col_customer'),__('gl.col_product'),__('gl.col_vendor'),__('policy.col_premium_rm'),__('policy.col_coverage'),__('policy.col_renewal'),__('gl.col_status'),__('gl.col_action')] as $h)
                    <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:11px;color:#4A5568;font-weight:600;white-space:nowrap">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($policies as $p)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:8px 10px;font-weight:600;font-family:monospace;font-size:11px">{{ $p->policy_number }}</td>
                    <td style="padding:8px 10px;font-weight:500">{{ $p->customer_name }}</td>
                    <td style="padding:8px 10px;color:#4A5568">{{ $p->product_name }}</td>
                    <td style="padding:8px 10px;color:#718096">{{ $p->vendor_name }}</td>
                    <td style="padding:8px 10px;font-weight:600;color:#059669">{{ number_format($p->premium_amount, 2) }}</td>
                    <td style="padding:8px 10px;font-size:11px;color:#718096;white-space:nowrap">
                        {{ \Carbon\Carbon::parse($p->coverage_start)->format('d/m/Y') }} –
                        {{ \Carbon\Carbon::parse($p->coverage_end)->format('d/m/Y') }}
                    </td>
                    <td style="padding:8px 10px;white-space:nowrap">
                        @php $renDate = \Carbon\Carbon::parse($p->renewal_date); $daysLeft = now()->diffInDays($renDate, false); @endphp
                        <span style="color:{{ $daysLeft < 30 ? '#E53E3E' : '#718096' }};font-size:11px;font-weight:{{ $daysLeft < 30 ? '700':'400' }}">
                            {{ $renDate->format('d/m/Y') }}
                            @if($daysLeft >= 0 && $daysLeft < 30) <br><span style="font-size:10px">({{ $daysLeft }}{{ __('policy.days_left_suffix_js') }})</span> @endif
                        </span>
                    </td>
                    <td style="padding:8px 10px">
                        @php $sc = match($p->status) { 'ACTIVE'=>'status-active','CANCELLED'=>'status-inactive','LAPSED'=>'status-risk_debt',default=>'status-risk_debt' }; @endphp
                        <span class="status-badge {{ $sc }}" style="font-size:10px">{{ $policyStatusLabels[$p->status] ?? $p->status }}</span>
                    </td>
                    <td style="padding:8px 10px">
                        <a href="{{ route('policies.show', $p->policy_id) }}"
                            style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:4px 8px;border-radius:6px;font-size:11px;text-decoration:none;font-weight:600">
                            <i class="ti ti-eye"></i> {{ __('gl.view_link') }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="padding:30px;text-align:center;color:#A0AEC0">{{ __('policy.no_policies_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $policies->links() }}</div>
</div>

@push('scripts')
<script>
const products = @json($products);

function filterProducts() {
    const vendorId = document.getElementById('vendor-select').value;
    const sel = document.getElementById('product-select');
    sel.innerHTML = '<option value="">' + @json(__('policy.select_product_placeholder')) + '</option>';

    products.forEach(p => {
        if (!vendorId || p.vendor_id === vendorId) {
            const opt = document.createElement('option');
            opt.value = p.product_id;
            opt.dataset.type = p.product_type;
            opt.dataset.vendor = p.vendor_id;
            opt.textContent = p.product_name;
            sel.appendChild(opt);
        }
    });
    showProductFields();
}

function showProductFields() {
    const sel = document.getElementById('product-select');
    const opt = sel.options[sel.selectedIndex];
    const type = opt?.dataset?.type || '';

    document.getElementById('motor-fields').style.display = type === 'MOTOR' ? 'block' : 'none';
    document.getElementById('pa-fields').style.display    = type === 'PERSONAL_ACCIDENT' ? 'block' : 'none';
    document.getElementById('fire-fields').style.display  = type === 'FIRE' ? 'block' : 'none';
}

document.getElementById('product-select').addEventListener('change', showProductFields);
</script>
@endpush

@endsection
