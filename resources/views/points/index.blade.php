@extends('layouts.dashboard')
@section('title', __('points.points_commission_title'))
@section('page-title', __('points.transfer_buy_points_title'))

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
        <div class="metric-label">{{ __('points.commission_balance_label') }}</div>
        <div class="metric-value" style="color:#0D5A8E">RM {{ number_format($agent->commission_balance, 2) }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('points.reward_points_balance_label') }}</div>
        <div class="metric-value" style="color:#7C3AED">{{ __('points.pts_word', ['value' => number_format($balance, 0)]) }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('points.my_member_code_label') }}</div>
        <div class="metric-value" style="font-size:16px">{{ $agent->member_code ?? $agent->agent_code ?? '—' }}</div>
        <div class="metric-sub" style="color:#718096">{{ __('points.share_for_transfers_note') }}</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

    {{-- Transfer Points --}}
    <div class="card">
        <div class="card-title"><i class="ti ti-arrows-exchange" style="color:#7C3AED"></i> {{ __('points.transfer_points_heading') }}</div>
        <form method="POST" action="{{ route('points.transfer-points') }}">
            @csrf
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.recipient_member_code_label') }}</label>
                <input type="text" name="to_member_code" placeholder="{{ __('points.member_code_placeholder') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.points_to_transfer_label') }}</label>
                <input type="number" name="points" placeholder="{{ __('points.points_amount_placeholder') }}" min="1" step="1"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                <p style="font-size:11px;color:#A0AEC0;margin-top:3px">{{ __('points.available_pts_note', ['value' => number_format($balance, 0)]) }}</p>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.notes_optional_label') }}</label>
                <input type="text" name="notes" placeholder="{{ __('points.transfer_reason_placeholder') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>
            <button type="submit"
                style="background:#7C3AED;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;width:100%">
                <i class="ti ti-send"></i> {{ __('points.transfer_points_button') }}
            </button>
        </form>
    </div>

    {{-- Transfer Commission --}}
    <div class="card">
        <div class="card-title"><i class="ti ti-coin" style="color:#059669"></i> {{ __('points.transfer_commission_heading') }}</div>
        <form method="POST" action="{{ route('points.transfer-commission') }}">
            @csrf
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.recipient_member_code_label') }}</label>
                <input type="text" name="to_member_code" placeholder="{{ __('points.member_code_placeholder') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.amount_rm_label') }}</label>
                <input type="number" name="amount" placeholder="{{ __('points.amount_rm_placeholder') }}" min="1" step="0.01"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
                <p style="font-size:11px;color:#A0AEC0;margin-top:3px">{{ __('points.available_rm_note', ['value' => number_format($agent->commission_balance, 2)]) }}</p>
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.notes_optional_label') }}</label>
                <input type="text" name="notes" placeholder="{{ __('points.transfer_reason_placeholder') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
            </div>
            <button type="submit"
                style="background:#059669;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;width:100%">
                <i class="ti ti-send"></i> {{ __('points.transfer_commission_button') }}
            </button>
        </form>
    </div>
</div>

{{-- Buy Points --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-star" style="color:#D97706"></i> {{ __('points.buy_reward_points_heading') }}</div>

    @if($adminBank)
    <div style="background:#FFFBEB;border:1px solid #F6E05E;border-radius:8px;padding:14px 16px;margin-bottom:16px;font-size:13px">
        <div style="font-weight:600;color:#744210;margin-bottom:8px">
            <i class="ti ti-building-bank"></i> {{ __('points.transfer_payment_note') }}
        </div>
        <div style="display:grid;grid-template-columns:auto 1fr;gap:6px 16px;color:#744210">
            <span style="font-weight:600">{{ __('points.bank_label') }}</span>      <span>{{ $adminBank->admin_bank_name }}</span>
            <span style="font-weight:600">{{ __('points.account_label') }}</span>   <span style="font-family:monospace">{{ $adminBankAccount }}</span>
            <span style="font-weight:600">{{ __('points.account_name_label') }}</span> <span>{{ $adminBank->full_name }}</span>
        </div>
        <div style="margin-top:8px;font-size:12px;color:#92400E">
            {{ __('points.after_transfer_note') }}
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('points.submit-purchase') }}" enctype="multipart/form-data">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.amount_paid_rm_label') }}</label>
                <input type="number" name="amount_paid" placeholder="{{ __('points.amount_paid_placeholder') }}" min="1" step="0.01"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.bank_in_ref_label') }}</label>
                <input type="text" name="slip_ref" placeholder="{{ __('points.bank_in_ref_placeholder') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.bank_in_date_label') }}</label>
                <input type="date" name="bank_in_date" value="{{ date('Y-m-d') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px" required>
            </div>
        </div>
        <div style="margin-bottom:14px">
            <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.bank_slip_label') }}</label>
            <input type="file" name="bank_slip" accept=".jpg,.jpeg,.png,.pdf"
                style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#F7FAFC" required>
        </div>
        <button type="submit"
            style="background:#D97706;color:#fff;padding:9px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
            <i class="ti ti-upload"></i> {{ __('points.submit_purchase_request_button') }}
        </button>
    </form>
</div>

{{-- My purchase requests --}}
@if($myPurchases->count() > 0)
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-receipt" style="color:#4A5568"></i> {{ __('points.my_purchase_requests_heading') }}</div>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach([__('points.th_date'),__('points.th_amount_paid'),__('points.th_points'),__('points.th_slip_ref'),__('points.th_status'),__('points.th_reason')] as $h)
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:11px;font-weight:600">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($myPurchases as $p)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:8px">{{ \Carbon\Carbon::parse($p->created_at)->format('d M Y') }}</td>
                    <td style="padding:8px;font-weight:600">RM {{ number_format($p->amount_paid_rm, 2) }}</td>
                    <td style="padding:8px;color:#7C3AED;font-weight:600">{{ __('points.pts_word', ['value' => number_format($p->points_to_credit, 0)]) }}</td>
                    <td style="padding:8px;font-family:monospace">{{ $p->bank_slip_ref }}</td>
                    <td style="padding:8px">
                        @php $sc = match($p->status) { 'APPROVED'=>'status-active', 'REJECTED'=>'status-inactive', default=>'status-risk_debt' }; @endphp
                        <span class="status-badge {{ $sc }}">{{ __('points.status_'.strtolower($p->status)) }}</span>
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
    <div class="card-title"><i class="ti ti-list" style="color:#4A5568"></i> {{ __('points.points_ledger_heading') }}</div>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach([__('points.th_date'),__('points.th_type'),__('points.th_points_in'),__('points.th_points_out'),__('points.th_balance'),__('points.th_notes')] as $h)
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
                <tr><td colspan="6" style="padding:20px;text-align:center;color:#A0AEC0">{{ __('points.no_transactions_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
