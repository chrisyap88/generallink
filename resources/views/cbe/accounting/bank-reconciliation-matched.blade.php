@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.matched_items_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.matched_items_page_title') }}@if($reconciliation->reconciliation_no)<span style="color:#9ca3af; font-weight:600;"> — {{ $reconciliation->reconciliation_no }}</span>@endif</div>
        <a href="{{ route('cbe.accounting.bank-reconciliations.match', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bank_side') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_system_side') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groupPage as $g)
                    @php
                        $groupRows = $rows->get($g->match_group_id, collect());
                        $bankRows = $groupRows->where('side', 'BANK');
                        $sysRows = $groupRows->where('side', 'SYSTEM');
                        $total = round((float) $bankRows->sum('amount'), 2);
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; color:#263238;">
                            @foreach($bankRows as $b)
                            <div>{{ $b->bank_date ? \Carbon\Carbon::parse($b->bank_date)->format('d/m/Y') : '—' }} — {{ \Illuminate\Support\Str::limit($b->bank_description, 26) }}</div>
                            @endforeach
                        </td>
                        <td style="padding:5px 6px; color:#263238;">
                            @foreach($sysRows as $s)
                            <div>{{ $s->system_date ? \Carbon\Carbon::parse($s->system_date)->format('d/m/Y') : '—' }} — {{ \Illuminate\Support\Str::limit($s->system_description ?: \Illuminate\Support\Str::title(str_replace('_',' ',strtolower($s->source_type ?: ''))), 26) }}</div>
                            @endforeach
                        </td>
                        <td style="padding:5px 6px; text-align:right; font-weight:700; color:{{ $total >= 0 ? '#2e7d32' : '#b71c1c' }};">{{ number_format($total, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right;">
                            @if($reconciliation->status !== 'COMPLETED')
                            <form method="POST" action="{{ route('cbe.accounting.bank-reconciliation-matches.unmatch', $g->match_group_id) }}" onsubmit="return confirm({{ json_encode(__('cbe_accounting.unmatch_group_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_accounting.unmatch_group_button') }}</button>
                            </form>
                            @else
                            <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_matched_items_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($groupPage->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $groupPage->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $groupPage->currentPage(), 'last' => $groupPage->lastPage(), 'total' => $groupPage->total()]) }}</span>
            @if($groupPage->hasMorePages())
                <a href="{{ $groupPage->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
