@extends('layouts.dashboard')
@section('title', __('points.point_purchases_title'))
@section('page-title', __('points.point_purchase_requests_title'))

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#FED7D7;border:1px solid #FC8181;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#742A2A;font-size:13px">{{ session('error') }}</div>
@endif

{{-- Admin bank info --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-title"><i class="ti ti-building-bank" style="color:#0D5A8E"></i> {{ __('points.my_bank_account_heading') }}</div>
    <div style="display:grid;grid-template-columns:auto 1fr;gap:6px 20px;font-size:13px;max-width:400px">
        <span style="font-weight:600;color:#4A5568">{{ __('points.bank_label') }}</span>
        <span>{{ $adminAgent->admin_bank_name ?? __('points.not_set_placeholder') }}</span>
        <span style="font-weight:600;color:#4A5568">{{ __('points.account_no_label') }}</span>
        <span style="font-family:monospace">{{ $adminBankAccount ?? __('points.not_set_placeholder') }}</span>
        <span style="font-weight:600;color:#4A5568">{{ __('points.account_name_label') }}</span>
        <span>{{ $adminAgent->full_name }}</span>
    </div>
    <a href="#" style="display:inline-block;margin-top:10px;font-size:12px;color:#0D5A8E">
        <i class="ti ti-edit"></i> {{ __('points.update_bank_details_link') }}
    </a>
</div>

{{-- Purchase requests table --}}
<div class="card">
    <div class="card-title">
        <i class="ti ti-receipt" style="color:#0D5A8E"></i> {{ __('points.all_purchase_requests_heading') }}
        <span style="margin-left:auto;font-size:12px;color:#718096">{{ __('points.total_suffix', ['count' => $purchases->total()]) }}</span>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach([__('points.th_date'),__('points.th_member'),__('points.th_amount_paid'),__('points.th_points'),__('points.th_slip_ref'),__('points.th_bank_in_date'),__('points.th_status'),__('points.th_slip'),__('points.th_action')] as $h)
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#4A5568;font-size:11px;font-weight:600">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $p)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:9px 10px">{{ \Carbon\Carbon::parse($p->created_at)->format('d M Y') }}</td>
                    <td style="padding:9px 10px">
                        <div style="font-weight:500">{{ $p->agent_name }}</div>
                        <div style="font-size:11px;color:#718096">{{ $p->member_code ?? $p->agent_code }}</div>
                    </td>
                    <td style="padding:9px 10px;font-weight:600;color:#059669">RM {{ number_format($p->amount_paid_rm, 2) }}</td>
                    <td style="padding:9px 10px;font-weight:600;color:#7C3AED">{{ __('points.pts_word', ['value' => number_format($p->points_to_credit, 0)]) }}</td>
                    <td style="padding:9px 10px;font-family:monospace">{{ $p->bank_slip_ref }}</td>
                    <td style="padding:9px 10px">{{ $p->bank_in_date ? \Carbon\Carbon::parse($p->bank_in_date)->format('d M Y') : '—' }}</td>
                    <td style="padding:9px 10px">
                        @php $sc = match($p->status) { 'APPROVED'=>'status-active', 'REJECTED'=>'status-inactive', default=>'status-risk_debt' }; @endphp
                        <span class="status-badge {{ $sc }}">{{ __('points.status_'.strtolower($p->status)) }}</span>
                    </td>
                    <td style="padding:9px 10px">
                        @if($p->bank_slip_path)
                        <a href="{{ Storage::url($p->bank_slip_path) }}" target="_blank"
                            style="color:#0D5A8E;font-size:12px"><i class="ti ti-eye"></i> {{ __('points.view_link') }}</a>
                        @else — @endif
                    </td>
                    <td style="padding:9px 10px">
                        @if($p->status === 'PENDING')
                        <div style="display:flex;gap:6px">
                            <form method="POST" action="{{ route('admin.points.approve', $p->purchase_id) }}">
                                @csrf
                                <button type="submit"
                                    style="background:#C6F6D5;border:1px solid #9AE6B4;color:#22543D;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600">
                                    {{ __('points.approve_button') }}
                                </button>
                            </form>
                            <button onclick="showReject('{{ $p->purchase_id }}')"
                                style="background:#FED7D7;border:1px solid #FC8181;color:#742A2A;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600">
                                {{ __('points.reject_button') }}
                            </button>
                        </div>
                        @elseif($p->status === 'REJECTED')
                        <span style="font-size:11px;color:#718096">{{ Str::limit($p->rejection_reason, 30) }}</span>
                        @else
                        <span style="font-size:11px;color:#48BB78">{{ __('points.credited_label') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="padding:30px;text-align:center;color:#A0AEC0">{{ __('points.no_purchase_requests_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $purchases->links() }}</div>
</div>

{{-- Reject modal --}}
<div id="reject-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:12px;padding:24px;width:400px;max-width:90vw">
        <div style="font-weight:600;font-size:16px;margin-bottom:14px;color:#1A202C">{{ __('points.reject_purchase_request_heading') }}</div>
        <form method="POST" id="reject-form">
            @csrf
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('points.reason_for_rejection_label') }}</label>
                <textarea name="reason" rows="3" required
                    placeholder="{{ __('points.rejection_reason_placeholder') }}"
                    style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;resize:vertical"></textarea>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit"
                    style="background:#E53E3E;color:#fff;padding:8px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                    {{ __('points.reject_modal_button') }}
                </button>
                <button type="button" onclick="hideReject()"
                    style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;padding:8px 20px;border-radius:8px;font-size:13px;cursor:pointer">
                    {{ __('points.cancel_button') }}
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function showReject(purchaseId) {
    document.getElementById('reject-form').action = '/admin/points/' + purchaseId + '/reject';
    document.getElementById('reject-modal').style.display = 'flex';
}
function hideReject() {
    document.getElementById('reject-modal').style.display = 'none';
}
</script>
@endpush

@endsection
