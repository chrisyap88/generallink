@extends('layouts.dashboard')
@section('title', 'My Beneficiary')
@section('page-title', 'Beneficiary Management')

@section('content')

<div style="max-width:860px">

{{-- Info banner --}}
<div style="background:#EBF8FF;border:1px solid #BEE3F8;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#2C5282">
    <strong>What is a beneficiary?</strong> If your account status is changed to
    <span style="background:#E9D8FD;color:#44337A;padding:1px 6px;border-radius:4px;font-weight:600">Resigned</span> or
    <span style="background:#FED7D7;color:#742A2A;padding:1px 6px;border-radius:4px;font-weight:600">Deceased</span>,
    your designated beneficiary automatically inherits your full account — including all downlines, commission balance,
    reward points, and affiliate rights.
</div>

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">
    ✓ {{ session('success') }}
</div>
@endif

@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">
    {{ session('error') }}
</div>
@endif

{{-- Add new beneficiary form --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-user-plus" style="color:#0D5A8E"></i> Add Beneficiary</div>

    <form method="POST" action="{{ route('beneficiary.store') }}">
        @csrf

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Full name *</label>
                <input type="text" name="full_name" value="{{ old('full_name') }}"
                    placeholder="As per MyKad"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px"
                    required>
                @error('full_name')<p style="color:#E53E3E;font-size:11px;margin-top:4px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">NRIC (MyKad) *</label>
                <input type="text" name="nric" value="{{ old('nric') }}"
                    placeholder="e.g. 900101075678"
                    maxlength="12" pattern="[0-9]{12}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px"
                    required>
                @error('nric')<p style="color:#E53E3E;font-size:11px;margin-top:4px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Relationship *</label>
                <select name="relationship"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff"
                    required>
                    <option value="">— Select —</option>
                    @foreach(['Spouse','Child','Parent','Sibling','Relative','Friend','Other'] as $rel)
                        <option value="{{ $rel }}" {{ old('relationship') == $rel ? 'selected' : '' }}>{{ $rel }}</option>
                    @endforeach
                </select>
                @error('relationship')<p style="color:#E53E3E;font-size:11px;margin-top:4px">{{ $message }}</p>@enderror
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                    placeholder="+601X-XXXXXXX"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                    placeholder="beneficiary@email.com"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Priority order</label>
                <input type="number" name="priority_order" value="{{ old('priority_order', 1) }}"
                    min="1" max="10"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                <p style="font-size:11px;color:#A0AEC0;margin-top:3px">1 = highest priority</p>
            </div>

            <div style="grid-column:span 2">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Address</label>
                <textarea name="address" rows="2" placeholder="Full address"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;resize:vertical">{{ old('address') }}</textarea>
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank name</label>
                <select name="bank_name"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff">
                    <option value="">— Select bank —</option>
                    @foreach(['Maybank','CIMB Bank','Public Bank','RHB Bank','Hong Leong Bank','AmBank','Bank Islam','Bank Rakyat','BSN','OCBC Bank','Standard Chartered','HSBC','UOB'] as $bank)
                        <option value="{{ $bank }}" {{ old('bank_name') == $bank ? 'selected' : '' }}>{{ $bank }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank account number</label>
                <input type="text" name="bank_account" value="{{ old('bank_account') }}"
                    placeholder="Account number"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>
        </div>

        <div style="margin-top:16px">
            <button type="submit"
                style="background:#0D5A8E;color:#fff;padding:9px 24px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                <i class="ti ti-user-plus"></i> Add Beneficiary
            </button>
        </div>
    </form>
</div>

{{-- Existing beneficiaries list --}}
@if($beneficiaries->count() > 0)
<div class="card">
    <div class="card-title"><i class="ti ti-users" style="color:#0D5A8E"></i> My Beneficiaries</div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:#F7FAFC">
                    <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">Priority</th>
                    <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">Name</th>
                    <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">NRIC</th>
                    <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">Relationship</th>
                    <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">Phone</th>
                    <th style="padding:10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">Bank</th>
                    <th style="padding:10px;text-align:center;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">Status</th>
                    <th style="padding:10px;text-align:center;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:12px">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($beneficiaries as $ben)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:10px;text-align:center">
                        <span style="background:#EBF8FF;color:#2B6CB0;font-weight:700;padding:2px 8px;border-radius:20px;font-size:12px">
                            #{{ $ben->priority_order }}
                        </span>
                    </td>
                    <td style="padding:10px;font-weight:500">{{ $ben->full_name }}</td>
                    <td style="padding:10px;font-family:monospace;color:#4A5568">{{ $ben->nric_display }}</td>
                    <td style="padding:10px;color:#4A5568">{{ $ben->relationship }}</td>
                    <td style="padding:10px;color:#4A5568">{{ $ben->phone ?? '—' }}</td>
                    <td style="padding:10px;color:#4A5568">{{ $ben->bank_name ?? '—' }}</td>
                    <td style="padding:10px;text-align:center">
                        @if($ben->takeover_triggered)
                            <span class="status-badge" style="background:#C6F6D5;color:#22543D">
                                <i class="ti ti-check"></i> Active owner
                            </span>
                        @else
                            <span class="status-badge status-active">Standby</span>
                        @endif
                    </td>
                    <td style="padding:10px;text-align:center">
                        @if(! $ben->takeover_triggered)
                        <form method="POST" action="{{ route('beneficiary.destroy', $ben->beneficiary_id) }}"
                              onsubmit="return confirm('Remove this beneficiary?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                style="background:#FFF5F5;border:1px solid #FC8181;color:#C53030;padding:4px 10px;border-radius:6px;font-size:12px;cursor:pointer">
                                <i class="ti ti-trash"></i> Remove
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

</div>
@endsection
