@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.compliance_log_title'))

@section('content')

{{-- NEW 14 Aug 2026 — per Chris: "there must be a program to retrieve
     all past email OTP records to comply the Malaysia's Electronic
     Commerce Act 2006." One row per vendor who has actually accepted;
     click View Evidence for the full Clause 10.4 record plus the
     complete OTP send/verify history behind it. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('admin_vendors.compliance_log_title') }}</div>
        <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.compliance_log_subtitle') }}</div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            @if($acceptances->isEmpty())
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('admin_vendors.no_acceptances_yet') }}</div>
            @else
            <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
                <colgroup>
                    <col style="width:16%;"><col style="width:24%;"><col style="width:16%;"><col style="width:28%;"><col style="width:16%;">
                </colgroup>
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_agreement_no') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_vendor') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_accepted') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_accepted_by') }}</th>
                        <th style="text-align:center; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($acceptances as $a)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px; vertical-align:top; font-weight:700; color:#263238;">{{ $a->agreement_number }}</td>
                        <td style="padding:6px; vertical-align:top; color:#263238; word-break:break-word;">{{ $a->vendor_name }}</td>
                        <td style="padding:6px; vertical-align:top; color:#6b7280;">{{ \Carbon\Carbon::parse($a->accepted_at)->format('d M Y, h:ia') }}</td>
                        <td style="padding:6px; vertical-align:top; color:#4b5563; word-break:break-word;">{{ $a->accepted_by_name }}<br><span style="font-size:9px; color:#9ca3af;">{{ $a->accepted_by_email }}</span></td>
                        <td style="padding:6px; vertical-align:top; text-align:center;">
                            <a href="{{ route('admin.vendor-agreements.show', $a->acceptance_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 10px; font-size:9.5px; font-weight:600; white-space:nowrap; display:inline-block;">{{ __('admin_vendors.view_evidence_button') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        @if($acceptances->hasPages() || $acceptances->total() > 0)
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
            @if($acceptances->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $acceptances->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('admin_vendors.compliance_page_of', ['current' => $acceptances->currentPage(), 'last' => $acceptances->lastPage(), 'total' => $acceptances->total()]) }}</span>
            @if($acceptances->hasMorePages())
                <a href="{{ $acceptances->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>

</div>

@endsection
