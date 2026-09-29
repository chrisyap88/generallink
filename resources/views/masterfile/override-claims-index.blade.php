@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.affiliate_partner_claim_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:6px; font-size:10px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; font-weight:500; flex-shrink:0;">⚠ {{ session('error') }}</div>
    @endif

    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <a href="{{ route('admin.masterfile.override-claims.export', request()->query()) }}" style="background:#38A169; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.export_excel_button') }}</a>
    </div>

    {{-- Status summary chips --}}
    <div style="display:flex; gap:6px; flex-shrink:0; flex-wrap:nowrap;">
        <span style="background:#FFF8E1; color:#F57F17; font-size:9.5px; font-weight:600; padding:3px 10px; border-radius:20px;">{{ __('masterfile.awaiting_submission_chip', ['count' => (int) ($summary->awaiting_submission ?? 0)]) }}</span>
        <span style="background:#E3F2FD; color:#1565C0; font-size:9.5px; font-weight:600; padding:3px 10px; border-radius:20px;">{{ __('masterfile.awaiting_approval_chip', ['count' => (int) ($summary->awaiting_approval ?? 0)]) }}</span>
        <span style="background:#F3E5F5; color:#4A148C; font-size:9.5px; font-weight:600; padding:3px 10px; border-radius:20px;">{{ __('masterfile.awaiting_payment_chip', ['count' => (int) ($summary->awaiting_payment ?? 0)]) }}</span>
        <span style="background:#E8F5E9; color:#1B5E20; font-size:9.5px; font-weight:600; padding:3px 10px; border-radius:20px;">{{ __('masterfile.total_paid_chip', ['amount' => number_format($summary->total_paid ?? 0, 2)]) }}</span>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.masterfile.override-claims') }}" style="display:flex; align-items:flex-end; gap:6px; flex-shrink:0; flex-wrap:nowrap;">
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('masterfile.status') }}</div>
            <select name="status" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:130px;">
                <option value="">{{ __('masterfile.all_status_option') }}</option>
                @foreach(['CALCULATED','SUBMITTED','APPROVED','PAID','REJECTED'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ __('masterfile.status_'.strtolower($s)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('masterfile.col_vendor') }}</div>
            <select name="vendor_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:130px;">
                <option value="">{{ __('masterfile.all_vendors_option') }}</option>
                @foreach($vendors as $v)
                <option value="{{ $v->vendor_id }}" {{ $vendorId === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div style="font-size:8.5px; color:#718096; margin-bottom:1px;">{{ __('masterfile.affiliate_partner_label') }}</div>
            <select name="member_id" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; height:24px; width:150px;">
                <option value="">{{ __('masterfile.all_members_option') }}</option>
                @foreach($members as $m)
                <option value="{{ $m->override_member_id }}" {{ $memberId === $m->override_member_id ? 'selected' : '' }}>{{ $m->full_name }} ({{ $m->override_member_code }})</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="background:#1B9AE4; color:#fff; border:none; border-radius:5px; padding:4px 14px; font-size:10px; font-weight:600; cursor:pointer; height:24px;">{{ __('masterfile.go_button') }}</button>
        <a href="{{ route('admin.masterfile.override-claims') }}" style="background:#f3f4f6; color:#4A5568; text-decoration:none; border-radius:5px; padding:4px 14px; font-size:10px; font-weight:600; height:24px; display:inline-flex; align-items:center;">{{ __('masterfile.clear') }}</a>
    </form>

    {{-- Table --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; overflow:hidden; min-height:0;">
            <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
                <thead style="background:#F7FAFC;">
                    <tr>
                        <th style="width:150px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_member') }}</th>
                        <th style="width:95px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_vendor') }}</th>
                        <th style="width:90px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.product_label') }}</th>
                        <th style="width:105px; text-align:left; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_period') }}</th>
                        <th style="width:85px; text-align:right; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_amount_rm') }}</th>
                        <th style="width:75px; text-align:center; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.status') }}</th>
                        <th style="width:170px; text-align:right; padding:5px 8px; color:#6b7280; font-weight:700; border-bottom:2px solid #E2E8F0;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($claims as $c)
                    @php
                        $statusColors = [
                            'CALCULATED' => ['#F3F4F6', '#374151'],
                            'SUBMITTED'  => ['#E3F2FD', '#1565C0'],
                            'APPROVED'   => ['#F3E5F5', '#4A148C'],
                            'PAID'       => ['#C8E6C9', '#1B5E20'],
                            'REJECTED'   => ['#FFCDD2', '#B71C1C'],
                            'SETTLED'    => ['#C8E6C9', '#1B5E20'],
                        ];
                        [$bg, $fg] = $statusColors[$c->status] ?? ['#F3F4F6', '#374151'];
                    @endphp
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><strong>{{ $c->member_name }}</strong><br><span style="color:#9ca3af; font-size:9px;">{{ $c->override_member_code }} · {{ $c->group_name }}</span></td>
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $c->vendor_name }}</td>
                        <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $c->product_name ?? __('masterfile.any_product_fallback') }}</td>
                        <td style="padding:5px 8px; font-size:9px; color:#6b7280;">{{ \Carbon\Carbon::parse($c->period_start)->format('d M y') }}–{{ \Carbon\Carbon::parse($c->period_end)->format('d M y') }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700;">{{ number_format($c->calculated_amount, 2) }}</td>
                        <td style="padding:5px 8px; text-align:center;">
                            <span style="background:{{ $bg }}; color:{{ $fg }}; font-size:8.5px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ __('masterfile.status_'.strtolower($c->status)) }}</span>
                        </td>
                        <td style="padding:5px 8px; text-align:right;">
                            @if(in_array($c->status, ['CALCULATED','REJECTED']))
                            <form method="POST" action="{{ route('admin.masterfile.override-claims.submit', $c->claim_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ $c->status === 'REJECTED' ? __('masterfile.resubmit_button') : __('masterfile.submit_button') }}</button>
                            </form>
                            @elseif($c->status === 'SUBMITTED')
                            <span style="color:#9ca3af; font-size:9px;" title="{{ $approverNames }}">{{ __('masterfile.awaiting_colon_label', ['names' => \Illuminate\Support\Str::limit($approverNames, 28)]) }}</span>
                            @elseif($c->status === 'APPROVED')
                            <form method="POST" action="{{ route('admin.masterfile.override-claims.mark-paid', $c->claim_id) }}" style="display:flex; gap:3px; justify-content:flex-end;">
                                @csrf
                                <input type="text" name="payment_reference" placeholder="{{ __('masterfile.payment_ref_placeholder') }}" style="width:65px; border:1px solid #d1d5db; border-radius:4px; padding:2px 4px; font-size:9px;">
                                <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:5px; padding:3px 8px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.mark_paid_button') }}</button>
                            </form>
                            @elseif($c->status === 'PAID' || $c->status === 'SETTLED')
                            <span style="color:#1B5E20; font-size:9px;">{{ $c->payment_reference ?? __('masterfile.paid_fallback') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#9ca3af;">{!! __('masterfile.no_override_claims_yet') !!}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; padding:6px 12px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:10px; color:#9ca3af;">{{ __('masterfile.claims_count_page_label', ['count' => $claims->total(), 'current' => $claims->currentPage(), 'last' => max(1, $claims->lastPage())]) }}</div>
            <div style="display:flex; gap:6px;">
                @if($claims->onFirstPage())
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.prev') }}</span>
                @else
                <a href="{{ $claims->appends(request()->query())->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.prev') }}</a>
                @endif
                @if($claims->hasMorePages())
                <a href="{{ $claims->appends(request()->query())->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.next') }}</a>
                @else
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.next') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
