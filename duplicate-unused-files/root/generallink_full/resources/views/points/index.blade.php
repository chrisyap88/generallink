@extends('layouts.dashboard')
@section('title', 'Points & Commission')
@section('page-title', 'Transfer & Buy Points')

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ session('error') }}</div>
@endif

{{-- Balance summary --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px">
    <div class="metric-card">
        <div class="metric-label">Commission balance</div>
        <div class="metric-value" style="color:#0D5A8E">RM {{ number_format($agent->commission_balance, 2) }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Reward points balance</div>
        <div class="metric-value" style="color:#7C3AED">{{ number_format($balance, 0) }} pts</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">My member code</div>
        <div class="metric-value" style="font-size:16px">{{ $agent->member_code ?? $agent->agent_code ?? '—' }}</div>
        <div class="metric-sub" style="color:#718096">Share this for transfers to you</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

    {{-- Transfer Points --}}
    <div class="card">
        <div class="card-title"><i class="ti ti-arrows-exchange" style="color:#7C3AED"></i> Transfer Points</div>
        <form method="POST" action="{{ route('points.transfer-points') }}">
            @csrf
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Recipient member code *</label>
                <input type="text" name="to_member_code" placeholder="e.g. C0001-0-1 or I-00001"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Points to transfer *</label>
                <input type="number" name="points" placeholder="e.g. 500" min="1" step="1"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                <p style="font-size:11px;color:#A0AEC0;margin-top:3px">Available: {{ number_format($balance, 0) }} pts</p>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Notes (optional)</label>
                <input type="text" name="notes" placeholder="Reason for transfer"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>
            <button type="submit"
                style="background:#7C3AED;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;width:100%">
                <i class="ti ti-send"></i> Transfer Points
            </button>
        </form>
    </div>

    {{-- Transfer Commission --}}
    <div class="card">
        <div class="card-title"><i class="ti ti-coin" style="color:#059669"></i> Transfer Commission</div>
        <form method="POST" action="{{ route('points.transfer-commission') }}">
            @csrf
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Recipient member code *</label>
                <input type="text" name="to_member_code" placeholder="e.g. C0001-0-1 or I-00001"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Amount (RM) *</label>
                <input type="number" name="amount" placeholder="e.g. 100.00" min="1" step="0.01"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                <p style="font-size:11px;color:#A0AEC0;margin-top:3px">Available: RM {{ number_format($agent->commission_balance, 2) }}</p>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Notes (optional)</label>
                <input type="text" name="notes" placeholder="Reason for transfer"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>
            <button type="submit"
                style="background:#059669;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;width:100%">
                <i class="ti ti-send"></i> Transfer Commission
            </button>
        </form>
    </div>
</div>

{{-- Buy Points --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-star" style="color:#D97706"></i> Buy Reward Points</div>

    @if($adminBank)
    <div style="background:#FFFBEB;border:1px solid #F6E05E;border-radius:8px;padding:14px 16px;margin-bottom:16px;font-size:13px">
        <div style="font-weight:600;color:#744210;margin-bottom:8px">
            <i class="ti ti-building-bank"></i> Transfer payment to this bank account:
        </div>
        <div style="display:grid;grid-template-columns:auto 1fr;gap:6px 16px;color:#744210">
            <span style="font-weight:600">Bank:</span>      <span>{{ $adminBank->admin_bank_name }}</span>
            <span style="font-weight:600">Account:</span>   <span style="font-family:monospace">{{ $adminBankAccount }}</span>
            <span style="font-weight:600">Account name:</span> <span>{{ $adminBank->full_name }}</span>
        </div>
        <div style="margin-top:8px;font-size:12px;color:#92400E">
            After transfer, upload your bank-in slip below. Admin will verify and credit your points.
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('points.submit-purchase') }}" enctype="multipart/form-data">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Amount paid (RM) *</label>
                <input type="number" name="amount_paid" placeholder="e.g. 200.00" min="1" step="0.01"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank-in reference no. *</label>
                <input type="text" name="slip_ref" placeholder="e.g. TT20260605001"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank-in date *</label>
                <input type="date" name="bank_in_date" value="{{ date('Y-m-d') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
        </div>
        <div style="margin-bottom:14px">
            <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Bank slip (JPG/PNG/PDF, max 5MB) *</label>
            <input type="file" name="bank_slip" accept=".jpg,.jpeg,.png,.pdf"
                style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#F7FAFC" required>
        </div>
        <button type="submit"
            style="background:#D97706;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            <i class="ti ti-upload"></i> Submit Purchase Request
        </button>
    </form>
</div>

{{-- My purchase requests --}}
@if($myPurchases->count() > 0)
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-receipt" style="color:#4A5568"></i> My Purchase Requests</div>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach(['Date','Amount Paid','Points','Slip Ref','Status','Reason'] as $h)
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:11px;font-weight:600">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($myPurchases as $p)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:8px">{{ \Carbon\Carbon::parse($p->created_at)->format('d M Y') }}</td>
                    <td style="padding:8px;font-weight:600">RM {{ number_format($p->amount_paid_rm, 2) }}</td>
                    <td style="padding:8px;color:#7C3AED;font-weight:600">{{ number_format($p->points_to_credit, 0) }} pts</td>
                    <td style="padding:8px;font-family:monospace">{{ $p->bank_slip_ref }}</td>
                    <td style="padding:8px">
                        @php $sc = match($p->status) { 'APPROVED'=>'status-active', 'REJECTED'=>'status-inactive', default=>'status-risk_debt' }; @endphp
                        <span class="status-badge {{ $sc }}">{{ $p->status }}</span>
                    </td>
                    <td style="padding:8px;color:#718096">{{ $p->rejection_reason ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Points ledger --}}
<div class="card">
    <div class="card-title"><i class="ti ti-list" style="color:#4A5568"></i> Points Ledger (last 20)</div>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach(['Date','Type','Points In','Points Out','Balance','Notes'] as $h)
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:11px;font-weight:600">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($ledger as $row)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:8px">{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                    <td style="padding:8px">
                        <span style="background:#EBF8FF;color:#2B6CB0;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600">{{ $row->txn_type }}</span>
                    </td>
                    <td style="padding:8px;color:#22543D;font-weight:600">{{ $row->points_in > 0 ? '+'.number_format($row->points_in,2) : '—' }}</td>
                    <td style="padding:8px;color:#742A2A;font-weight:600">{{ $row->points_out > 0 ? '-'.number_format($row->points_out,2) : '—' }}</td>
                    <td style="padding:8px;font-weight:700">{{ number_format($row->running_balance,2) }}</td>
                    <td style="padding:8px;color:#718096">{{ Str::limit($row->notes ?? '—', 50) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:20px;text-align:center;color:#A0AEC0">No transactions yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
