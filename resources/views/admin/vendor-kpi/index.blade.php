@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_reports.vk_page_title'))

@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:10px 16px; flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">📊 {{ __('admin_reports.vk_page_title') }}</div>
        <div style="font-size:10px; color:#6b7280;">{{ __('admin_reports.vk_subtitle') }}</div>
    </div>

    <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">

        {{-- Vendor Overview --}}
        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('admin_reports.vk_vendor_overview') }}</div>
            <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:8px;">
                <div style="background:#f0f9ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#1565C0;">{{ number_format($totalVendors) }}</div>
                    <div style="font-size:9.5px; color:#374151; margin-top:2px;">{{ __('admin_reports.vk_total_active_vendors') }}</div>
                </div>
                <div style="background:#d1fae5; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#065f46;">{{ number_format($activeLogins) }}</div>
                    <div style="font-size:9.5px; color:#065f46; margin-top:2px;">{{ __('admin_reports.vk_active_logins') }}</div>
                </div>
                <div style="background:#fef3c7; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#92400e;">{{ number_format($pendingLogins) }}</div>
                    <div style="font-size:9.5px; color:#92400e; margin-top:2px;">{{ __('admin_reports.vk_pending_approval') }}</div>
                </div>
                <div style="background:#e0e7ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#3730a3;">{{ number_format($awaitingPwd) }}</div>
                    <div style="font-size:9.5px; color:#3730a3; margin-top:2px;">{{ __('admin_reports.vk_awaiting_password_set') }}</div>
                </div>
                <div style="background:#f5f3ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#6D28D9;">{{ number_format($restrictedLogins) }}</div>
                    <div style="font-size:9.5px; color:#6D28D9; margin-top:2px;">{{ __('admin_reports.vk_restricted_access') }}</div>
                </div>
                <div style="background:#fee2e2; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:18px; font-weight:700; color:#991b1b;">{{ number_format($rejectedLogins) }}</div>
                    <div style="font-size:9.5px; color:#991b1b; margin-top:2px;">{{ __('growth.status_rejected') }}</div>
                </div>
            </div>
        </div>

        {{-- Document Verification / Due Diligence Risk / Rebate Offers --}}
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">📄 {{ __('admin_reports.vk_document_verification') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>⏳ {{ __('growth.pending') }}</span><span style="font-weight:700; color:#92400e;">{{ number_format($docPending) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>✅ {{ __('admin_reports.vk_verified') }}</span><span style="font-weight:700; color:#065f46;">{{ number_format($docVerified) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>❌ {{ __('growth.status_rejected') }}</span><span style="font-weight:700; color:#991b1b;">{{ number_format($docRejected) }}</span></div>
                </div>
            </div>
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🛡️ {{ __('admin_reports.vk_ai_due_diligence_risk') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>✅ {{ __('admin_reports.vk_approve') }}</span><span style="font-weight:700; color:#065f46;">{{ number_format($riskApprove) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>⚠️ {{ __('admin_reports.vk_approve_with_review') }}</span><span style="font-weight:700; color:#92400e;">{{ number_format($riskReview) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>🚨 {{ __('admin_reports.vk_high_risk_escalate') }}</span><span style="font-weight:700; color:#991b1b;">{{ number_format($riskEscalate) }}</span></div>
                </div>
            </div>
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">💸 {{ __('admin_reports.vk_rebate_offers') }}</div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('network.active') }}</span><span style="font-weight:700; color:#065f46;">{{ number_format($rebateActive) }}</span></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;"><span>{{ __('network.inactive') }}</span><span style="font-weight:700; color:#991b1b;">{{ number_format($rebateInactive) }}</span></div>
                </div>
                <a href="{{ route('admin.masterfile.rebate-offers') }}" style="display:block; margin-top:10px; font-size:9.5px; color:#1565C0; text-decoration:none; font-weight:600;">{{ __('admin_reports.vk_manage_rebate_offers') }}</a>
            </div>
        </div>

        {{-- Entity Type breakdown + Top 5 Vendors by Sales --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🏢 {{ __('admin_reports.vk_vendors_by_entity_type') }}</div>
                @if($byEntityType->isEmpty())
                <div style="font-size:10.5px; color:#9ca3af;">{{ __('admin_reports.vk_no_data_yet') }}</div>
                @else
                <div style="display:flex; flex-direction:column; gap:6px;">
                    @foreach($byEntityType as $row)
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:10.5px;">
                        <span style="word-break:break-word;">{{ \App\Services\VendorDocumentChecklistService::ENTITY_TYPES[$row->entity_type] ?? $row->entity_type }}</span>
                        <span style="font-weight:700; color:#374151;">{{ number_format($row->total) }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 16px;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🏆 {{ __('admin_reports.vk_top5_vendors_by_sales') }}</div>
                @if($topVendorsBySales->isEmpty())
                <div style="font-size:10.5px; color:#9ca3af;">{{ __('admin_reports.vk_no_sales_transactions') }}</div>
                @else
                <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                    <thead>
                        <tr style="border-bottom:1px solid #e0f2fe;">
                            <th style="text-align:left; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('network.col_vendor') }}</th>
                            <th style="text-align:center; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('admin_reports.vk_col_tx_count') }}</th>
                            <th style="text-align:right; padding:4px 6px; color:#6b7280; font-weight:600;">{{ __('admin_reports.vk_col_total_sales') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topVendorsBySales as $i => $row)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 6px; font-weight:600; word-break:break-word;">{{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '')) }} {{ $row->vendor_name }}</td>
                            <td style="padding:5px 6px; text-align:center;">{{ number_format($row->tx_count) }}</td>
                            <td style="padding:5px 6px; text-align:right; font-weight:700; color:#065f46;">RM {{ number_format($row->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
