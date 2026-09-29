@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_documents.version_history_page_title'))

@push('styles')
<style>
.dth-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:6px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.dth-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:10px 14px;flex-shrink:0;}
.dth-scroll{flex:1;overflow-y:auto;min-height:0;display:flex;flex-direction:column;gap:8px;}
.dth-version{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:12px 14px;}
.dth-badge{padding:2px 8px;border-radius:20px;font-size:9.5px;font-weight:600;white-space:nowrap;}
.dth-field-list{font-size:10.5px;color:#4A5568;margin-top:8px;}
.dth-field-list div{padding:2px 0;}
</style>
@endpush

@section('content')
<div class="dth-wrap">

    <div class="dth-card" style="display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:11px;color:#374151;">{!! __('admin_documents.every_version_note', ['template' => '<strong>'.e($versions->first()->template_name).'</strong>', 'vendor' => e($versions->first()->vendor_name), 'product' => e($versions->first()->product_name ?? __('admin_documents.any_product_word')), 'doctype' => e(\App\Http\Controllers\Admin\DocumentTemplateController::DOCUMENT_TYPES[$versions->first()->document_type] ?? $versions->first()->document_type)]) !!}</div>
        <a href="{{ route('admin.document-templates.index') }}" style="color:#1565C0;text-decoration:none;font-size:11px;font-weight:600;white-space:nowrap;">{{ __('admin_documents.back_to_list_link') }}</a>
    </div>

    @if(session('success'))
    <div class="dth-card" style="border-left:3px solid #38A169;color:#1b5e20;">{{ session('success') }}</div>
    @endif

    <div class="dth-scroll">
        @foreach($versions as $v)
        @php
            $fields = \Illuminate\Support\Facades\DB::table('document_template_fields')->where('template_id', $v->template_id)->orderBy('sort_order')->get();
        @endphp
        <div class="dth-version">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <span style="font-weight:700;color:#1565C0;">{{ __('admin_documents.version_label', ['number' => $v->version_number]) }}</span>
                    <span class="dth-badge" style="margin-left:8px;background:{{ $v->is_active ? '#d1fae5' : '#f3f4f6' }};color:{{ $v->is_active ? '#065f46' : '#6b7280' }};">{{ $v->is_active ? __('admin_documents.active_used_now_badge') : __('admin_documents.retired_badge') }}</span>
                </div>
                <div style="font-size:10px;color:#9ca3af;">
                    {{ __('admin_documents.created_by_on_note', ['name' => \Illuminate\Support\Facades\DB::table('agents')->where('agent_id', $v->created_by)->value('full_name') ?? __('customer_kpi.unknown_word'), 'date' => \Illuminate\Support\Carbon::parse($v->created_at)->format('d M Y')]) }}
                    @if($v->superseded_at) {{ __('admin_documents.retired_on_suffix', ['date' => \Illuminate\Support\Carbon::parse($v->superseded_at)->format('d M Y')]) }} @endif
                </div>
            </div>
            <div class="dth-field-list">
                @foreach($fields as $f)
                <div>{{ \App\Http\Controllers\Admin\DocumentTemplateController::FIELD_ROLES[$f->field_role] ?? $f->field_role }} {{ __('admin_documents.looks_for_suffix', ['text' => $f->anchor_text]) }}</div>
                @endforeach
            </div>
            <div style="margin-top:10px;display:flex;gap:10px;">
                @if(!$v->is_active)
                <form action="{{ route('admin.document-templates.reactivate', $v->template_id) }}" method="POST">
                    @csrf
                    <button type="submit" style="background:#F7FAFC;border:1px solid #E2E8F0;color:#B45309;border-radius:6px;padding:5px 12px;font-size:10.5px;cursor:pointer;">{{ __('admin_documents.reactivate_button') }}</button>
                </form>
                @endif
                <a href="{{ route('admin.document-templates.edit', $v->template_id) }}" style="color:#1565C0;text-decoration:none;font-size:10.5px;font-weight:600;align-self:center;">{{ __('admin_documents.view_edit_link') }}</a>
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
