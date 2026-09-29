@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai.batch_detail_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai.batch_detail_page_title') }} — {{ $batch->label }}</div>
        <a href="{{ route('cbe.ai-accounting.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
    <div style="background:#fff8e1; border-left:3px solid #D97706; color:#8d6e00; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('warning') }}</div>
    @endif

    {{-- TIGHTENED 16 Sep 2026 — per Chris: with a full 12-file batch (the
         maximum this screen allows), most rows also carried a second
         line underneath (a Continuity or branch-detection warning),
         which doubled their height and pushed the last several rows past
         the visible area — no scrolling is allowed on any screen, so
         rows must fit however many files were uploaded. Row padding/font
         shrunk, and every warning that used to get its own stacked line
         is now combined onto ONE line under the status (full text still
         available on hover via title=), so every row stays a single
         line no matter how many warnings it has. --}}
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_filename') }}</th>
                        <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_detected_bank') }}</th>
                        <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_period') }}</th>
                        <th style="text-align:right; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_lines_found') }}</th>
                        <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                        <th style="text-align:right; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $docStatusColors = ['UPLOADED' => '#9ca3af', 'PARSED' => '#D97706', 'PARSE_FAILED' => '#c62828', 'CONFIRMED' => '#2e7d32']; @endphp
                    @forelse($documents as $d)
                    @php
                        $warnings = [];
                        if ($d->status === 'PARSE_FAILED' && $d->parse_error_note) { $warnings[] = $d->parse_error_note; }
                        if ($d->continuity_note ?? null) { $warnings[] = __('cbe_ai.continuity_badge_short').': '.$d->continuity_note; }
                        if ($d->branch_detection_note ?? null) { $warnings[] = $d->branch_detection_note; }
                        $warningText = implode(' · ', $warnings);
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:3px 6px; font-weight:600; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px;" title="{{ $d->original_filename }}">{{ $d->original_filename }}</td>
                        <td style="padding:3px 6px; color:#263238; white-space:nowrap;">{{ $d->detected_bank_name ?: '—' }}{{ $d->detected_account_number ? ' ('.$d->detected_account_number.')' : '' }}</td>
                        <td style="padding:3px 6px; color:#6b7280; white-space:nowrap;">{{ $d->statement_period_from ? \Carbon\Carbon::parse($d->statement_period_from)->format('d/m/y').' – '.\Carbon\Carbon::parse($d->statement_period_to)->format('d/m/y') : '—' }}</td>
                        <td style="padding:3px 6px; text-align:right; color:#263238;">{{ $d->extracted_transaction_count }}</td>
                        <td style="padding:3px 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px;" @if($warningText) title="{{ $warningText }}" @endif>
                            <span style="color:{{ $docStatusColors[$d->status] ?? '#546E7A' }}; font-weight:600;">{{ __('cbe_ai.doc_status_'.strtolower($d->status)) }}</span>
                            @if($warningText)
                            <span style="color:{{ $d->status === 'PARSE_FAILED' ? '#c62828' : '#D97706' }};"> · ⚠ {{ $warningText }}</span>
                            @endif
                        </td>
                        <td style="padding:3px 6px; text-align:right;">
                            @if(in_array($d->status, ['PARSED', 'CONFIRMED']))
                            <a href="{{ route('cbe.ai-accounting.documents.show', $d->document_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('cbe_ai.review_link') }}</a>
                            @else
                            <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_documents_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
