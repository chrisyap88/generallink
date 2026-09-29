@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', ($isNewVersion ?? false) ? __('admin_documents.new_version_page_title') : ($template ? __('admin_documents.edit_template_page_title') : __('admin_documents.new_template_page_title')))

@push('styles')
<style>
.dtw-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:10px;height:calc(100vh - 66px);box-sizing:border-box;overflow-y:auto;}
.dtw-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:14px 16px;flex-shrink:0;}
.dtw-row{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;}
.dtw-row label{display:block;font-size:10.5px;color:#6b7280;margin-bottom:3px;font-weight:600;}
.dtw-row input,.dtw-row select{width:100%;box-sizing:border-box;padding:8px 10px;font-size:12px;border:1px solid #E2E8F0;border-radius:5px;}
.dtw-btn{background:#1565C0;color:#fff;border:none;border-radius:6px;padding:9px 22px;font-size:12px;font-weight:700;cursor:pointer;}
.dtw-nav{display:flex;justify-content:space-between;flex-shrink:0;padding-top:4px;}
.dtw-section-card{border:1px solid #E2E8F0;border-radius:8px;padding:10px 14px;background:#fff;margin-bottom:10px;}
.dtw-section-title{font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;}
.dtw-section-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 12px;}
.dtw-check-row{display:flex;align-items:flex-start;gap:7px;font-size:11px;color:#374151;}
.dtw-check-row input{width:auto;margin-top:2px;}
</style>
@endpush

@section('content')
<div class="dtw-wrap">

@php
    $isNewVersion = $isNewVersion ?? false;
    if ($isNewVersion) {
        $formAction = route('admin.document-templates.new-version.store', $template->template_id);
    } elseif ($template) {
        $formAction = route('admin.document-templates.update', $template->template_id);
    } else {
        $formAction = route('admin.document-templates.store');
    }
    $exitUrl = request('back') ? urldecode(request('back')) : route('admin.document-templates.index');
    $selectedRoles = $selectedRoles ?? [];
@endphp

<div class="dtw-card" style="border-left:3px solid #1565C0;background:#EFF6FF;color:#1e40af;font-size:11px;">
    {{ __('admin_documents.intro_field_note') }}
</div>

@if($isNewVersion)
<div class="dtw-card" style="border-left:3px solid #B45309;color:#92400E;">
    {{ __('admin_documents.creating_version_note', ['number' => $template->version_number + 1, 'name' => $template->template_name, 'prev' => $template->version_number]) }}
</div>
@endif

@if($errors->any())
<div class="dtw-card" style="border-left:3px solid #DC2626;color:#991B1B;">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ $formAction }}" id="templateForm">
    @csrf
    @if($template && !$isNewVersion) @method('PUT') @endif
    <input type="hidden" name="back" value="{{ request('back') }}">

    <div class="dtw-card">
        <div class="dtw-row">
            <div style="flex:1;min-width:150px;">
                <label>{{ __('admin_documents.vendor_label') }}</label>
                <select name="vendor_id" required>
                    <option value="">{{ __('growth.select_dash_dash') }}</option>
                    @foreach($vendors as $v)
                    <option value="{{ $v->vendor_id }}" {{ old('vendor_id', $template->vendor_id ?? '') == $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;min-width:150px;position:relative;">
                <label>{{ __('admin_documents.product_label') }}</label>
                <input type="text" id="productSearch" placeholder="{{ __('admin_documents.product_search_placeholder') }}" autocomplete="off" style="width:100%;box-sizing:border-box;"
                       value="{{ $template && $template->product_id ? \Illuminate\Support\Facades\DB::table('products')->where('product_id', $template->product_id)->value('product_name') : '' }}">
                <input type="hidden" name="product_id" id="productIdHidden" value="{{ old('product_id', $template->product_id ?? '') }}">
                <div id="productResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #B2EBF2;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);z-index:50;max-height:180px;overflow-y:auto;"></div>
            </div>
        </div>
        <div class="dtw-row">
            <div style="flex:1;min-width:150px;">
                <label>{{ __('admin_documents.document_type_label') }}</label>
                <select name="document_type" required>
                    <option value="">{{ __('growth.select_dash_dash') }}</option>
                    @foreach($documentTypes as $key => $label)
                    <option value="{{ $key }}" {{ old('document_type', $template->document_type ?? '') == $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:2;min-width:200px;">
                <label>{{ __('admin_documents.template_name_label') }}</label>
                <input type="text" name="template_name" required maxlength="150" value="{{ old('template_name', $template->template_name ?? '') }}" placeholder="{{ __('admin_documents.template_name_placeholder') }}">
            </div>
        </div>
        @if($template && isset($createdBy))
        <div style="font-size:9.5px;color:#9ca3af;">{{ __('admin_documents.created_by_on_version_note', ['name' => $createdBy ?? __('customer_kpi.unknown_word'), 'date' => \Illuminate\Support\Carbon::parse($template->created_at)->format('d M Y'), 'version' => $template->version_number]) }}</div>
        @endif
    </div>

    <div class="dtw-card">
        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:10px;">{{ __('admin_documents.which_fields_heading') }}</div>
        @foreach($fieldSections as $sectionName => $roles)
        <div class="dtw-section-card">
            <div class="dtw-section-title">{{ $sectionName }}</div>
            <div class="dtw-section-grid">
                @foreach($roles as $role)
                <label class="dtw-check-row">
                    <input type="checkbox" name="field_roles[]" value="{{ $role }}" {{ in_array($role, old('field_roles', $selectedRoles)) ? 'checked' : '' }}>
                    <span>{{ $fieldRoles[$role] ?? $role }}</span>
                </label>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    <div class="dtw-nav" style="margin-bottom:10px;">
        <a href="{{ $exitUrl }}" style="background:#f3f4f6;color:#374151;text-decoration:none;border-radius:6px;padding:8px 18px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;">{{ __('admin_documents.cancel_link') }}</a>
        <button type="submit" class="dtw-btn">{{ $isNewVersion ? __('admin_documents.save_new_version_button') : ($template ? __('admin_documents.update_template_button') : __('admin_documents.save_template_button')) }}</button>
    </div>
</form>
</div>

<script>
const DTW_I18N = { noProductsFound: @json(__('admin_documents.no_products_found_note')) };
const PRODUCT_TYPEAHEAD_URL = '{{ route('admin.sales-transactions.product-typeahead') }}';
const vendorSelect = document.querySelector('select[name="vendor_id"]');
const productSearchInput = document.getElementById('productSearch');
const productResults = document.getElementById('productResults');
const productIdHidden = document.getElementById('productIdHidden');
let productSearchTimer = null;

function runProductSearch(term, vendorId) {
    if (!vendorId) return;
    fetch(`${PRODUCT_TYPEAHEAD_URL}?vendor_id=${encodeURIComponent(vendorId)}&term=${encodeURIComponent(term)}`)
        .then(r => r.json())
        .then(rows => {
            if (!rows.length) {
                productResults.innerHTML = '<div style="padding:8px;font-size:10px;color:#9ca3af;">' + DTW_I18N.noProductsFound + '</div>';
                productResults.style.display = 'block';
                return;
            }
            productResults.innerHTML = rows.map(r =>
                `<div class="prod-opt" data-id="${r.product_id}" data-name="${r.product_name}" style="padding:6px 8px;font-size:10.5px;cursor:pointer;border-bottom:1px solid #f3f4f6;">${r.product_name} <span style="color:#9ca3af;">&middot; ${r.product_type}</span></div>`
            ).join('');
            productResults.style.display = 'block';
            productResults.querySelectorAll('.prod-opt').forEach(el => {
                el.onmouseover = () => { el.style.background = '#EBF5FB'; };
                el.onmouseout = () => { el.style.background = ''; };
                el.onclick = () => {
                    productIdHidden.value = el.dataset.id;
                    productSearchInput.value = el.dataset.name;
                    productResults.style.display = 'none';
                };
            });
        });
}

productSearchInput.addEventListener('focus', function() {
    if (vendorSelect.value) runProductSearch(this.value.trim(), vendorSelect.value);
});
productSearchInput.addEventListener('input', function() {
    clearTimeout(productSearchTimer);
    const term = this.value.trim();
    if (!vendorSelect.value) return;
    if (term === '') productIdHidden.value = '';
    productSearchTimer = setTimeout(() => runProductSearch(term, vendorSelect.value), 250);
});
vendorSelect.addEventListener('change', function() {
    productSearchInput.value = '';
    productIdHidden.value = '';
});
document.addEventListener('click', function(e) {
    if (!productSearchInput.contains(e.target) && !productResults.contains(e.target)) {
        productResults.style.display = 'none';
    }
});
</script>
@endsection
