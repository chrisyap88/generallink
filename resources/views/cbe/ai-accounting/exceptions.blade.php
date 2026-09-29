@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai.exceptions_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai.exceptions_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.ai-accounting.rules') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_ai.rules_page_title') }}</a>
            <a href="{{ route('cbe.ai-accounting.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    <div style="flex-shrink:0; display:flex; gap:6px; margin-bottom:8px; flex-wrap:wrap;">
        @foreach(['unclassified','low_confidence','restricted_fund','draft_masters','continuity','unmatched_ap_ar','possible_duplicate'] as $t)
        <a href="{{ route('cbe.ai-accounting.exceptions', ['type' => $t]) }}" style="text-decoration:none; display:inline-flex; align-items:center; gap:5px; border-radius:14px; padding:5px 12px; font-size:9.5px; font-weight:600; {{ $type === $t ? 'background:var(--gl-blue); color:#fff;' : 'background:var(--gl-light); color:#546E7A; border:1px solid #d1d5db;' }}">
            {{ __('cbe_ai.exception_type_'.$t) }}
            <span style="background:{{ $type === $t ? 'rgba(255,255,255,0.25)' : '#fff' }}; border-radius:10px; padding:1px 6px; font-size:9px;">{{ $counts[$t] }}</span>
        </a>
        @endforeach
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        @if($type === 'draft_masters')
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_party_name') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_party_type') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_created') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($draftMasters as $row)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:4px 6px; color:#263238;">{{ $row->name }}</td>
                        <td style="padding:4px 6px; color:#263238;">{{ __('cbe_ai.party_type_'.strtolower($row->party_type)) }}</td>
                        <td style="padding:4px 6px; color:#546E7A;">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y') }}</td>
                        <td style="padding:4px 6px; text-align:right; color:#9ca3af; font-size:8.5px;">{{ __('cbe_ai.draft_master_edit_note') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_exceptions_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @elseif($type === 'continuity')
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_batch') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_file') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_period') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $row)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.ai-accounting.documents.show', $row->document_id) }}'">
                        <td style="padding:4px 6px; color:#263238;">{{ $row->batch_label }}</td>
                        <td style="padding:4px 6px; color:var(--gl-blue); text-decoration:underline;">{{ $row->original_filename }}</td>
                        <td style="padding:4px 6px; color:#546E7A;">{{ \Illuminate\Support\Carbon::parse($row->statement_period_from)->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($row->statement_period_to)->format('d/m/Y') }}</td>
                        <td style="padding:4px 6px; color:#D97706;">{{ $row->continuity_note }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_exceptions_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_statement_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.br_col_amount_signed') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_category') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $row)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.ai-accounting.documents.show', $row->document_id) }}'">
                        <td style="padding:4px 6px; color:#263238;">{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('d/m/Y') }}</td>
                        <td style="padding:4px 6px; color:var(--gl-blue); text-decoration:underline;">{{ $row->description }}</td>
                        <td style="padding:4px 6px; text-align:right; color:#263238;">{{ number_format((float) ($row->debit ?: $row->credit), 2) }}</td>
                        <td style="padding:4px 6px; color:#263238;">{{ $row->suggested_ai_category ? __('cbe_ai.category_'.strtolower($row->suggested_ai_category)) : __('cbe_ai.category_select_placeholder') }}</td>
                        <td style="padding:4px 6px; color:#D97706; font-size:8.5px;">{{ $row->flag_note ?: ($row->posting_note ?: ($row->possible_duplicate_note ?: ($type === 'unmatched_ap_ar' ? __('cbe_ai.exception_note_ap_ar_created') : null))) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_exceptions_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @php $pager = $type === 'draft_masters' ? $draftMasters : $items; @endphp
            @if($pager->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $pager->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            @if($pager->hasMorePages())
                <a href="{{ $pager->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
