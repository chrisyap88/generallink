@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai.document_detail_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $document->original_filename }}">{{ __('cbe_ai.document_detail_page_title') }} — {{ $document->original_filename }}
            {{-- CHANGED 17 Sep 2026 — per Chris: "why top have prev and
                 next and bottom also have prev next, it should have
                 only in the bottom not duplicate". Removed the second
                 Prev/Next bar that used to sit up here — the single
                 Prev/Next pair at the bottom of the screen now does
                 both jobs (see the comment down there): pages through
                 this statement's lines first, then rolls on to the
                 next/previous statement. This text just keeps you
                 oriented on which statement you're viewing. --}}
            @if($statementPosition)<span style="font-size:10px; font-weight:400; color:#9ca3af;"> — {{ __('cbe_ai.statement_x_of_y', ['current' => $statementPosition, 'total' => $statementTotal]) }}</span>@endif
        </div>
        <div style="display:flex; gap:12px; align-items:center; flex-shrink:0;">
            <a href="{{ route('cbe.ai-accounting.documents.download', $document->document_id) }}" target="_blank" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_ai.view_source_pdf_link') }}</a>
            <a href="{{ route('cbe.ai-accounting.batches.show', $document->batch_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif
    @if($document->continuity_note)
    <div style="background:#fff8e1; border-left:3px solid #D97706; color:#8d6e00; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">⚠ {{ $document->continuity_note }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        {{-- REBUILT 17 Sep 2026 — per Chris: "please present the header
             as per the bank statement layout at the header", matching
             the Category / No. of Transaction / Balance table printed
             on his actual bank statement (Opening Balance, Total
             Debits, Total Credits, Closing Balance, Cheques Not
             Cleared). "Cheques Not Cleared" has no equivalent detected
             by this system yet, so it is shown as "Not detected"
             rather than a guessed 0.00. --}}
        <div style="flex-shrink:0; margin-bottom:6px;">
            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:4px; font-size:9.5px;">
                <div style="color:#263238;">
                    <span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_ai.col_detected_bank') }}:</span>
                    {{ $document->detected_bank_name ?: __('cbe_ai.not_detected') }}@if($document->detected_account_number) ({{ $document->detected_account_number }})@endif
                    &nbsp;&nbsp;
                    <span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_ai.col_period') }}:</span>
                    {{ $document->statement_period_from ? \Carbon\Carbon::parse($document->statement_period_from)->format('d/m/Y').' – '.\Carbon\Carbon::parse($document->statement_period_to)->format('d/m/Y') : __('cbe_ai.not_detected') }}
                </div>
                <div style="color:#6b7280; flex-shrink:0;">{{ __('cbe_ai.pending_count_note', ['count' => $pendingCount]) }}</div>
            </div>
            {{-- CHANGED 17 Sep 2026 — per Chris: "format the header
                 across one row, dont leave space empty and make it one
                 row" — one horizontal strip instead of a 5-row table,
                 each stat filling equal width (flex:1) so there's no
                 empty space regardless of screen width. CHANGED again
                 17 Sep 2026 — per Chris: "the header display value
                 should centralized" — label and value now centred
                 within their own column instead of left-aligned. --}}
            <div style="display:flex; border:1px solid #d1d5db; border-radius:6px; overflow:hidden;">
                <div style="flex:1; padding:4px 10px; border-right:1px solid #e5e7eb; text-align:center;">
                    <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_ai.summary_row_opening_balance') }}</div>
                    <div style="font-size:11px; font-weight:700; color:#263238;">{{ $document->detected_opening_balance !== null ? 'RM '.number_format($document->detected_opening_balance, 2) : __('cbe_ai.not_detected') }}</div>
                </div>
                <div style="flex:1; padding:4px 10px; border-right:1px solid #e5e7eb; text-align:center;">
                    <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_ai.summary_col_no_transactions') }}</div>
                    <div style="font-size:11px; font-weight:700; color:#263238;">{{ $lineTotals->debit_count + $lineTotals->credit_count }}</div>
                </div>
                <div style="flex:1; padding:4px 10px; border-right:1px solid #e5e7eb; text-align:center;">
                    <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_ai.summary_row_total_debits') }}</div>
                    <div style="font-size:11px; font-weight:700; color:#c62828;">RM {{ number_format($lineTotals->total_debits, 2) }}</div>
                </div>
                <div style="flex:1; padding:4px 10px; border-right:1px solid #e5e7eb; text-align:center;">
                    <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_ai.summary_row_total_credits') }}</div>
                    <div style="font-size:11px; font-weight:700; color:#2e7d32;">RM {{ number_format($lineTotals->total_credits, 2) }}</div>
                </div>
                <div style="flex:1; padding:4px 10px; background:var(--gl-light); text-align:center;">
                    <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_ai.summary_row_closing_balance') }}</div>
                    <div style="font-size:11px; font-weight:700; color:#263238;">
                        {{ $closingBalance !== null ? 'RM '.number_format($closingBalance, 2) : __('cbe_ai.not_detected') }}@if($closingBalanceCalculated)<span title="{{ __('cbe_ai.closing_balance_calculated_note') }}" style="font-weight:400; color:#D97706;">&nbsp;*</span>@endif
                    </div>
                </div>
            </div>
            @if($closingBalanceCalculated)
            <div style="font-size:7.5px; color:#D97706; margin-top:2px;">* {{ __('cbe_ai.closing_balance_calculated_note') }}</div>
            @endif
        </div>

        <form method="POST" action="{{ route('cbe.ai-accounting.documents.commit', $document->document_id) }}" style="flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
            @csrf
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="padding:4px 6px; width:20px;"></th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_statement_date') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                            {{-- CHANGED 17 Sep 2026 — per Chris: "you should
                                 have 2 column IN OUT so that the user
                                 know" — replaces the single signed
                                 +IN/-OUT amount column with two plain
                                 columns. --}}
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_amount_in') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_amount_out') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_ai.col_category') }}</th>
                            <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_confidence') }}</th>
                            <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $lineStatusColors = ['PENDING' => '#D97706', 'COMMITTED' => '#2e7d32', 'REJECTED' => '#9ca3af', 'DUPLICATE' => '#c62828', 'POSSIBLE_DUPLICATE' => '#c62828']; @endphp
                        @forelse($lines as $l)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; text-align:center;">
                                @if($l->status === 'PENDING')
                                <input type="checkbox" name="include_ids[]" value="{{ $l->extraction_id }}" checked>
                                @endif
                            </td>
                            <td style="padding:4px 6px; color:#6b7280; white-space:nowrap;">{{ $l->transaction_date ? \Carbon\Carbon::parse($l->transaction_date)->format('d M Y') : __('cbe_ai.not_detected') }}</td>
                            <td style="padding:4px 6px; color:#263238;">
                                {{-- NEW 17 Sep 2026 — per Chris: allow the
                                     description to be changed before
                                     committing. Hover still shows the
                                     original AI-extracted raw line for
                                     reference. Only editable while the
                                     line is still PENDING — once
                                     committed/rejected/etc. it's shown
                                     as plain text as before. --}}
                                @if($l->status === 'PENDING')
                                <input type="text" name="description[{{ $l->extraction_id }}]" value="{{ old('description.'.$l->extraction_id, $l->description) }}" title="{{ $l->raw_line_text }}" style="font-size:9px; padding:2px 4px; border:1px solid #d1d5db; border-radius:4px; width:100%; box-sizing:border-box; color:#263238;">
                                @else
                                <span title="{{ $l->raw_line_text }}">{{ $l->description ?: '—' }}</span>
                                @endif
                            </td>
                            <td style="padding:4px 6px; text-align:right; color:#2e7d32;">{{ $l->credit ? number_format($l->credit, 2) : '—' }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#c62828;">{{ $l->debit ? number_format($l->debit, 2) : '—' }}</td>
                            <td style="padding:4px 6px;">
                                @if($l->status === 'PENDING')
                                <select name="category[{{ $l->extraction_id }}]" style="font-size:8.5px; padding:2px 3px; border:1px solid #d1d5db; border-radius:4px; width:100px; color:#263238;">
                                    <option value="">{{ __('cbe_ai.category_select_placeholder') }}</option>
                                    @foreach($categories as $cat)
                                    <option value="{{ $cat }}" @selected($l->suggested_ai_category === $cat)>{{ __('cbe_ai.category_'.strtolower($cat)) }}</option>
                                    @endforeach
                                </select>
                                {{-- ADDED 19 Sep 2026 -- per Chris: "why only category, it
                                     suppose to allocate to the right GL code" -- lets this line
                                     post to the exact GL account (e.g. a specific Expense
                                     sub-account like "Meeting & Refreshment" under
                                     "Entertainment Expenses") instead of a generic default.
                                     Only takes effect for Bank Charge/Donation/Membership/
                                     Adjustment/Other -- Supplier/Customer/Asset/Returned Cheque
                                     always create their own Bill/Invoice/Asset record instead,
                                     which has its own GL account field on that document. Leave
                                     blank to keep using the automatic default account. --}}
                                <select name="chart_category_id[{{ $l->extraction_id }}]" style="font-size:8.5px; padding:2px 3px; border:1px solid #d1d5db; border-radius:4px; width:100px; color:#263238; margin-top:2px;">
                                    <option value="">{{ __('cbe_ai.gl_account_default_option') }}</option>
                                    @foreach($glCategories as $gc)
                                    <option value="{{ $gc->category_id }}" @selected((old('chart_category_id.'.$l->extraction_id, $l->suggested_chart_category_id ?? '')) === $gc->category_id)>{{ $gc->category_name_zh ? $gc->category_name.' ('.$gc->category_name_zh.')' : $gc->category_name }}</option>
                                    @endforeach
                                </select>
                                @if(in_array($l->suggested_ai_category, ['SUPPLIER', 'CUSTOMER', 'DONATION', 'ASSET'], true))
                                <input type="text" name="party_name[{{ $l->extraction_id }}]" value="{{ old('party_name.'.$l->extraction_id, $l->suggested_party_name) }}" placeholder="{{ __('cbe_ai.party_name_placeholder') }}" style="font-size:8px; padding:2px 3px; border:1px solid #d1d5db; border-radius:4px; width:100px; margin-top:2px; color:#263238;">
                                @endif
                                @if($l->flag_note)
                                <div style="font-size:7.5px; color:#c2760c; margin-top:1px;" title="{{ $l->flag_note }}">⚠ {{ __('cbe_ai.flag_badge_short') }}</div>
                                @endif
                                @else
                                <span style="color:#546E7A;">{{ ($l->confirmed_ai_category ?: $l->suggested_ai_category) ? __('cbe_ai.category_'.strtolower($l->confirmed_ai_category ?: $l->suggested_ai_category)) : '—' }}</span>
                                @if($l->matched_party_name)
                                <div style="font-size:7.5px; color:#6b7280; margin-top:1px;">→ {{ $l->matched_party_name }}@if($l->matched_party_is_draft) <span style="color:#D97706;">({{ __('cbe_ai.draft_badge') }})</span>@endif</div>
                                @endif
                                @if($l->matched_doc_no)
                                <div style="font-size:7.5px; color:#6b7280; margin-top:1px;">{{ $l->matched_doc_no }}@if($l->ap_ar_document_created) <span style="color:#D97706;">({{ __('cbe_ai.auto_created_badge') }})</span>@endif</div>
                                @endif
                                @if($l->issued_receipt_no)
                                <div style="font-size:7.5px; color:#2e7d32; margin-top:1px;">
                                    ✓ {{ __('cbe_ai.col_receipt_no') }}
                                    @if($l->issued_receipt_id)
                                    <a href="{{ route('admin.cbe-kpi.receipts.show', ['id' => $l->issued_receipt_id]) }}" target="_blank" style="color:#2e7d32; font-weight:700; text-decoration:underline;">{{ $l->issued_receipt_no }}</a>
                                    @else
                                    {{ $l->issued_receipt_no }}
                                    @endif
                                </div>
                                @endif
                                @endif
                            </td>
                            <td style="padding:4px 6px; text-align:center;">
                                <span style="background:{{ $l->extraction_confidence >= 80 ? '#e8f5e9' : ($l->extraction_confidence >= 60 ? '#fff8e1' : '#fdecea') }}; color:{{ $l->extraction_confidence >= 80 ? '#1b5e20' : ($l->extraction_confidence >= 60 ? '#8d6e00' : '#b71c1c') }}; border-radius:10px; padding:2px 7px; font-size:8px; font-weight:600;">{{ $l->extraction_confidence }}%</span>
                                @if($l->classification_confidence > 0)
                                <div style="font-size:7px; color:#9ca3af; margin-top:1px;">{{ __('cbe_ai.ai_confidence_short') }} {{ $l->classification_confidence }}%</div>
                                @endif
                            </td>
                            <td style="padding:4px 6px; text-align:center;">
                                <span style="color:{{ $lineStatusColors[$l->status] ?? '#546E7A' }}; font-weight:600;">{{ __('cbe_ai.line_status_'.strtolower($l->status)) }}</span>
                                @if($l->status === 'COMMITTED' && $l->posted_journal_no)
                                <div style="font-size:7px; color:#2e7d32; margin-top:1px;">✓ {{ __('cbe_ai.posted_to_gl_badge') }} ({{ $l->posted_journal_no }})</div>
                                @elseif($l->status === 'COMMITTED' && $l->posting_note)
                                <div style="font-size:7px; color:#9ca3af; margin-top:1px;" title="{{ $l->posting_note }}">{{ \Illuminate\Support\Str::limit($l->posting_note, 24) }}</div>
                                @elseif($l->status === 'POSSIBLE_DUPLICATE' && $l->possible_duplicate_note)
                                <div style="font-size:7px; color:#c62828; margin-top:1px;" title="{{ $l->possible_duplicate_note }}">{{ \Illuminate\Support\Str::limit($l->possible_duplicate_note, 24) }}</div>
                                @endif
                            </td>
                            <td style="padding:4px 6px; text-align:right;">
                                @if($l->status === 'PENDING')
                                <button type="submit" formaction="{{ route('cbe.ai-accounting.extracted-lines.reject', $l->extraction_id) }}" style="background:none; border:none; color:#e53935; font-weight:600; font-size:8.5px; cursor:pointer;">{{ __('cbe_ai.reject_line_button') }}</button>
                                @elseif($l->status === 'POSSIBLE_DUPLICATE')
                                <button type="submit" formaction="{{ route('cbe.ai-accounting.extracted-lines.confirm-not-duplicate', $l->extraction_id) }}" style="background:none; border:none; color:#2e7d32; font-weight:600; font-size:8.5px; cursor:pointer; margin-right:6px;">{{ __('cbe_ai.confirm_not_duplicate_button') }}</button>
                                <button type="submit" formaction="{{ route('cbe.ai-accounting.extracted-lines.reject', $l->extraction_id) }}" style="background:none; border:none; color:#e53935; font-weight:600; font-size:8.5px; cursor:pointer;">{{ __('cbe_ai.reject_line_button') }}</button>
                                @else
                                <span style="color:#9ca3af;">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_lines_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- CHANGED 17 Sep 2026 — per Chris: "it should have only in
                 the bottom not duplicate". One single Prev/Next pair
                 now does both jobs: it first pages through THIS
                 statement's own lines (still 8 per page, so a ~62-line
                 December statement pages across ~8 screens instead of
                 ever needing to scroll), and once there's no further
                 line-page to go to, it rolls straight on to the
                 previous/next STATEMENT in the batch — so clicking
                 Next repeatedly walks through everything, lines then
                 statements, with no second button anywhere else. --}}
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
                @if(! $lines->onFirstPage())
                    <a href="{{ $lines->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
                @elseif($prevDocumentId)
                    <a href="{{ route('cbe.ai-accounting.documents.show', $prevDocumentId) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
                @else
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
                @endif
                <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:14px; padding:6px 16px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_ai.commit_checked_button') }}</button>
                @if($lines->hasMorePages())
                    <a href="{{ $lines->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
                @elseif($nextDocumentId)
                    <a href="{{ route('cbe.ai-accounting.documents.show', $nextDocumentId) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
                @else
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
