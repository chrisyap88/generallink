@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_documents.page_title'))

@push('styles')
<style>
.dt-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:6px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.dt-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.dt-table-card{flex:1;overflow:hidden;padding:4px 10px 8px 10px;min-height:0;display:flex;flex-direction:column;}
.dt-table-scroll{flex:1;overflow-y:auto;overflow-x:hidden;min-height:0;scrollbar-gutter:stable;}
.dt-table{width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed;}
.dt-table th{padding:5px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;position:sticky;top:0;}
.dt-table td{padding:5px 6px;line-height:1.3;border-bottom:1px solid #F7FAFC;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.dt-badge{padding:2px 8px;border-radius:20px;font-size:9.5px;font-weight:600;white-space:nowrap;}
</style>
@endpush

@section('content')
<div class="dt-wrap">

    {{-- RESTORED 18 Jul 2026 — the wizard behind this button is now the
         simplified field-checklist version (no more sample upload or
         bracket-labeling), so it's safe to link to again. Filtered view
         still deliberately has no add button, so reviewing one
         product's template never tempts an accidental duplicate. --}}
    <div class="dt-card" style="flex-shrink:0;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="font-size:11px;color:#374151;">{{ __('admin_documents.intro_note') }}</div>
        @unless($filterProductId)
        <a href="{{ route('admin.document-templates.create') }}" style="background:#1565C0;color:#fff;text-decoration:none;border-radius:6px;padding:7px 14px;font-size:11px;font-weight:700;white-space:nowrap;flex-shrink:0;">{{ __('admin_documents.new_template_button') }}</a>
        @endunless
    </div>

    @if($filterProductId)
    <div class="dt-card" style="border-left:3px solid #1565C0;color:#1565C0;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;">
        <span>{!! __('admin_documents.viewing_matched_note', ['product' => '<strong>'.e($filterProductName ?? __('admin_documents.this_product_word')).'</strong>']) !!}</span>
        <a href="{{ route('admin.document-templates.index') }}" style="color:#6b7280;text-decoration:underline;font-size:10.5px;white-space:nowrap;">{{ __('admin_documents.show_all_templates_link') }}</a>
    </div>
    @endif

    @if(session('success'))
    <div class="dt-card" style="border-left:3px solid #38A169;color:#1b5e20;flex-shrink:0;">{{ session('success') }}</div>
    @endif

    @php
        // If we arrived here filtered to one product, every drill-down
        // link (Edit / New Version) needs to carry that back reference
        // along so, once Admin is done, they land back on THIS filtered
        // view — not the general unfiltered list, which would make the
        // "+ New Template" button reappear and lose their place.
        $backToThisView = urlencode(request()->fullUrl());
    @endphp
    <div class="dt-card dt-table-card">
        <div class="dt-table-scroll">
        <table class="dt-table">
            <colgroup>
                <col style="width:17%;"><col style="width:15%;"><col style="width:15%;"><col style="width:13%;">
                <col style="width:9%;"><col style="width:9%;"><col style="width:22%;">
            </colgroup>
            <thead>
                <tr>
                    <th>{{ __('admin_documents.col_template_name') }}</th><th>{{ __('gl.col_vendor') }}</th><th>{{ __('gl.col_product') }}</th><th>{{ __('admin_documents.col_document_type') }}</th>
                    <th style="text-align:center;">{{ __('admin_documents.col_version') }}</th><th style="text-align:center;">{{ __('gl.col_status') }}</th><th style="text-align:center;">{{ __('gl.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $t)
                <tr>
                    <td style="font-weight:600;color:#1565C0;">
                        {{ $t->template_name }}
                        <br><span style="font-size:8.5px;color:#9ca3af;font-weight:400;">{{ __('admin_documents.created_by_date_note', ['name' => $t->created_by_name ?? __('customer_kpi.unknown_word'), 'date' => \Illuminate\Support\Carbon::parse($t->created_at)->format('d M Y')]) }}</span>
                    </td>
                    <td>{{ $t->vendor_name }}</td>
                    <td>{{ $t->product_name ?? __('admin_documents.any_product_word') }}</td>
                    <td>{{ \App\Http\Controllers\Admin\DocumentTemplateController::DOCUMENT_TYPES[$t->document_type] ?? $t->document_type }}</td>
                    <td style="text-align:center;">
                        v{{ $t->version_number }}
                        @if(($versionCounts[$t->template_group_id] ?? 1) > 1)
                        <br><a href="{{ route('admin.document-templates.history', $t->template_group_id) }}" style="font-size:9px;color:#6b7280;text-decoration:underline;">{{ __('admin_documents.versions_count_link', ['count' => $versionCounts[$t->template_group_id]]) }}</a>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <span class="dt-badge" style="background:{{ $t->is_active ? '#d1fae5' : '#f3f4f6' }};color:{{ $t->is_active ? '#065f46' : '#6b7280' }};">{{ $t->is_active ? __('growth.active_badge') : __('growth.inactive_badge') }}</span>
                    </td>
                    <td style="text-align:center;">
                        <a href="{{ route('admin.document-templates.edit', ['id'=>$t->template_id, 'back'=>$backToThisView]) }}" style="color:#1565C0;text-decoration:none;font-weight:600;">{{ __('gl.edit_link') }}</a>
                        &nbsp;|&nbsp;
                        <a href="{{ route('admin.document-templates.new-version', ['id'=>$t->template_id, 'back'=>$backToThisView]) }}" style="color:#B45309;text-decoration:none;font-weight:600;">{{ __('admin_documents.new_version_link') }}</a>
                        &nbsp;|&nbsp;
                        <form action="{{ route('admin.document-templates.toggle', $t->template_id) }}" method="POST" style="display:inline;">
                            @csrf @method('PATCH')
                            <button type="submit" style="background:none;border:none;color:#6b7280;cursor:pointer;font-size:11px;padding:0;">{{ $t->is_active ? __('admin_documents.deactivate_button') : __('admin_documents.activate_button') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:20px;">{{ __('gl.no_records_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

</div>
@endsection
