@extends('layouts.dashboard')
@section('title','Policies')
@section('page-title','Policy Management')

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ session('error') }}</div>
@endif

{{-- Submit new policy form --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-file-plus" style="color:#0D5A8E"></i> Submit New Policy</div>

    <form method="POST" action="{{ route('policies.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px">

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Customer *</label>
                <select name="customer_id" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff" required>
                    <option value="">— Select customer —</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->customer_id }}" {{ old('customer_id') == $c->customer_id ? 'selected':'' }}>{{ $c->full_name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Vendor *</label>
                <select name="vendor_id" id="vendor-select" onchange="filterProducts()" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff" required>
                    <option value="">— Select vendor —</option>
                    @foreach($vendors as $v)
                    <option value="{{ $v->vendor_id }}">{{ $v->vendor_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Product *</label>
                <select name="product_id" id="product-select" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff" required>
                    <option value="">— Select product —</option>
                    @foreach($products as $p)
                    <option value="{{ $p->product_id }}" data-vendor="{{ $p->vendor_id }}" data-type="{{ $p->product_type }}">{{ $p->product_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Policy number *</label>
                <input type="text" name="policy_number" value="{{ old('policy_number') }}" required
                    placeholder="e.g. ALZ/2026/001234"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                @error('policy_number')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Premium amount (RM) *</label>
                <input type="number" name="premium_amount" value="{{ old('premium_amount') }}" required step="0.01" min="0.01"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                @error('premium_amount')<p style="color:#E53E3E;font-size:11px;margin-top:3px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Sum insured (RM)</label>
                <input type="number" name="sum_insured" value="{{ old('sum_insured') }}" step="0.01" min="0"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Coverage start *</label>
                <input type="date" name="coverage_start" value="{{ old('coverage_start', date('Y-m-d')) }}" required
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Coverage end *</label>
                <input type="date" name="coverage_end" value="{{ old('coverage_end', date('Y-m-d', strtotime('+1 year'))) }}" required
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>
        </div>

        {{-- Dynamic product-specific fields --}}
        <div id="motor-fields" style="display:none;background:#F7FAFC;border-radius:8px;padding:14px;margin-bottom:14px">
            <div style="font-size:12px;font-weight:600;color:#4A5568;margin-bottom:10px"><i class="ti ti-car"></i> Motor Insurance Details</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                @foreach(['vehicle_reg_no'=>'Vehicle Reg No','engine_no'=>'Engine No','chassis_no'=>'Chassis No','vehicle_make'=>'Make','vehicle_model'=>'Model','ncd_pct'=>'NCD %'] as $field=>$label)
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ $label }}</label>
                    <input type="text" name="attributes[{{ $field }}]" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
                @endforeach
            </div>
        </div>

        <div id="pa-fields" style="display:none;background:#F7FAFC;border-radius:8px;padding:14px;margin-bottom:14px">
            <div style="font-size:12px;font-weight:600;color:#4A5568;margin-bottom:10px"><i class="ti ti-shield-check"></i> Personal Accident Details</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                @foreach(['occupation'=>'Occupation','coverage_scope'=>'Coverage Scope (Death/TPD/Medical)'] as $field=>$label)
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ $label }}</label>
                    <input type="text" name="attributes[{{ $field }}]" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
                @endforeach
            </div>
        </div>

        <div id="fire-fields" style="display:none;background:#F7FAFC;border-radius:8px;padding:14px;margin-bottom:14px">
            <div style="font-size:12px;font-weight:600;color:#4A5568;margin-bottom:10px"><i class="ti ti-home"></i> Fire Insurance Details</div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">
                @foreach(['property_address'=>'Property Address','property_type'=>'Property Type','postcode'=>'Postcode'] as $field=>$label)
                <div>
                    <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ $label }}</label>
                    <input type="text" name="attributes[{{ $field }}]" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                </div>
                @endforeach
            </div>
        </div>

        <button type="submit"
            style="background:#0D5A8E;color:#fff;padding:9px 22px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            <i class="ti ti-file-check"></i> Submit Policy & Calculate Commission
        </button>
    </form>
</div>

{{-- Filters + policy list --}}
<div class="card">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
        <div class="card-title" style="margin-bottom:0"><i class="ti ti-file-invoice" style="color:#0D5A8E"></i> All Policies</div>
        <form method="GET" style="display:flex;gap:8px;margin-left:auto;flex-wrap:wrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Policy no. or customer..."
                style="padding:6px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;width:180px">
            <select name="status" style="padding:6px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;background:#fff">
                <option value="">All statuses</option>
                @foreach(['ACTIVE','CANCELLED','LAPSED','RENEWED','PENDING_RENEWAL'] as $s)
                <option value="{{ $s }}" {{ request('status') == $s ? 'selected':'' }}>{{ $s }}</option>
                @endforeach
            </select>
            <button type="submit" style="background:#0D5A8E;color:#fff;padding:6px 14px;border:none;border-radius:8px;font-size:12px;cursor:pointer">Filter</button>
        </form>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach(['Policy No.','Customer','Product','Vendor','Premium (RM)','Coverage','Renewal','Status','Action'] as $h)
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
                            @if($daysLeft >= 0 && $daysLeft < 30) <br><span style="font-size:10px">({{ $daysLeft }}d left)</span> @endif
                        </span>
                    </td>
                    <td style="padding:8px 10px">
                        @php $sc = match($p->status) { 'ACTIVE'=>'status-active','CANCELLED'=>'status-inactive','LAPSED'=>'status-risk_debt',default=>'status-risk_debt' }; @endphp
                        <span class="status-badge {{ $sc }}" style="font-size:10px">{{ $p->status }}</span>
                    </td>
                    <td style="padding:8px 10px">
                        <a href="{{ route('policies.show', $p->policy_id) }}"
                            style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:4px 8px;border-radius:6px;font-size:11px;text-decoration:none;font-weight:600">
                            <i class="ti ti-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="padding:30px;text-align:center;color:#A0AEC0">No policies found</td></tr>
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
    sel.innerHTML = '<option value="">— Select product —</option>';

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
