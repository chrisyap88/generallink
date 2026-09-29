@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.product_management_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- MAIN --}}
    @if($mode === 'main')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:20px; flex-shrink:0;">
        <div style="font-size:14px; font-weight:700; color:#1565C0; margin-bottom:16px;">{{ __('masterfile.product_management_title') }}</div>
        <div style="display:flex; gap:16px;">
            <a href="{{ route('admin.masterfile.products', ['mode'=>'add']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(21,101,192,.3);">{{ __('masterfile.add_new_product') }}</a>
            <a href="{{ route('admin.masterfile.products', ['mode'=>'search']) }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:2px solid #1565C0;">{{ __('masterfile.search_product') }}</a>
        </div>
    </div>

    {{-- ADD --}}
    @elseif($mode === 'add')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.add_new_product') }}</div>
            <a href="{{ route('admin.masterfile.products', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">{{ __('masterfile.back') }}</a>
        </div>
        <div style="padding:12px 16px;">
            <form method="POST" action="{{ route('admin.masterfile.products.store') }}" autocomplete="off">
                @csrf
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_vendor') }} <span style="color:#ef4444;">*</span></label>
                        <select name="vendor_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.select_vendor_placeholder') }}</option>
                            @foreach($vendors as $v)
                            <option value="{{ $v->vendor_id }}" {{ old('vendor_id') === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }} ({{ $v->vendor_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_product_name') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="product_name" value="{{ old('product_name') }}" required maxlength="200" placeholder="e.g. Comprehensive Motor" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_code') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="product_code" value="{{ old('product_code') }}" required maxlength="20" placeholder="e.g. ALZ-MTR-01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_product_type') }} <span style="color:#ef4444;">*</span></label>
                        <select name="product_type" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.select_type_placeholder') }}</option>
                            <option value="MOTOR" {{ old('product_type')==='MOTOR' ? 'selected' : '' }}>{{ __('masterfile.motor_type') }}</option>
                            <option value="PERSONAL_ACCIDENT" {{ old('product_type')==='PERSONAL_ACCIDENT' ? 'selected' : '' }}>{{ __('masterfile.pa_type') }}</option>
                            <option value="FIRE" {{ old('product_type')==='FIRE' ? 'selected' : '' }}>{{ __('masterfile.fire_type') }}</option>
                            <option value="OTHER" {{ old('product_type')==='OTHER' ? 'selected' : '' }}>{{ __('masterfile.other_type') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.campaign_start_label') }}</label>
                        <input type="date" name="campaign_start" value="{{ old('campaign_start') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.campaign_end_label') }}</label>
                        <input type="date" name="campaign_end" value="{{ old('campaign_end') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="grid-column:span 3;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.description_label') }}</label>
                        <textarea name="description" rows="2" placeholder="Optional description" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; resize:none;">{{ old('description') }}</textarea>
                    </div>
                    <div style="grid-column:span 3; display:flex; gap:8px; margin-top:4px;">
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 28px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.add_new_product') }}</button>
                        <a href="{{ route('admin.masterfile.products', ['mode'=>'main']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.cancel') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SEARCH CRITERIA --}}
    @elseif($mode === 'search' && $products === null)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.search_product') }}</div>
            <a href="{{ route('admin.masterfile.products', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">{{ __('masterfile.back') }}</a>
        </div>
        <div style="padding:12px 16px;">
            <form method="GET" action="{{ route('admin.masterfile.products') }}" autocomplete="off">
                <input type="hidden" name="mode" value="search">
                <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:8px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_product_name') }}</label>
                        <input type="text" name="product_name" value="{{ request('product_name') }}" placeholder="Any part of name" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_code') }}</label>
                        <input type="text" name="product_code" value="{{ request('product_code') }}" placeholder="e.g. ALZ-MTR" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_vendor') }}</label>
                        <select name="vendor_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_vendors_option') }}</option>
                            @foreach($vendors as $v)
                            <option value="{{ $v->vendor_id }}" {{ request('vendor_id') === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_product_type') }}</label>
                        <select name="product_type" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_types_option') }}</option>
                            <option value="MOTOR" {{ request('product_type')==='MOTOR' ? 'selected' : '' }}>{{ __('masterfile.motor_type') }}</option>
                            <option value="PERSONAL_ACCIDENT" {{ request('product_type')==='PERSONAL_ACCIDENT' ? 'selected' : '' }}>{{ __('masterfile.pa_type') }}</option>
                            <option value="FIRE" {{ request('product_type')==='FIRE' ? 'selected' : '' }}>{{ __('masterfile.fire_type') }}</option>
                            <option value="OTHER" {{ request('product_type')==='OTHER' ? 'selected' : '' }}>{{ __('masterfile.other_type') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }}</label>
                        <select name="is_active" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_option') }}</option>
                            <option value="1" {{ request('is_active')==='1' ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ request('is_active')==='0' ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div style="grid-column:span 2; display:flex; align-items:flex-end; gap:8px;">
                        <button type="submit" name="do_search" value="1" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 24px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
                        <a href="{{ route('admin.masterfile.products', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:12px;">{{ __('masterfile.clear') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SEARCH RESULTS --}}
    @elseif($mode === 'search' && $products !== null)
    @php $backToResults = request()->fullUrl(); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0; padding:10px 16px; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:11px; font-weight:700; color:#1565C0;">{{ __('masterfile.product_search_results_count', ['count' => $products->total()]) }}</div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('admin.masterfile.products', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.new_search') }}</a>
            <a href="{{ route('admin.masterfile.products', ['mode'=>'main']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.main_menu') }}</a>
        </div>
    </div>
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px; table-layout:fixed;">
                <colgroup>
                    <col style="width:4%;">
                    <col style="width:20%;"><col style="width:11%;"><col style="width:15%;">
                    <col style="width:11%;"><col style="width:15%;"><col style="width:9%;"><col style="width:11%;">
                </colgroup>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0; z-index:1;">
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">No.</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_product_name') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_code') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_vendor') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_product_type') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_campaign') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    @php
                        $typeLabel = match($product->product_type) {
                            'MOTOR'             => __('masterfile.motor_type'),
                            'PERSONAL_ACCIDENT' => __('masterfile.pa_type_short'),
                            'FIRE'              => __('masterfile.fire_type'),
                            default             => __('masterfile.other_type'),
                        };
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                        <td style="padding:6px; text-align:center; color:#9ca3af;">{{ $loop->iteration }}</td>
                        <td style="padding:6px; font-weight:600; color:#111827; word-break:break-word;">{{ $product->product_name }}</td>
                        <td style="padding:6px; font-family:monospace; color:#6b7280; word-break:break-word;">{{ $product->product_code }}</td>
                        <td style="padding:6px; word-break:break-word;">{{ $product->vendor_name }}</td>
                        <td style="padding:6px; white-space:nowrap;">{{ $typeLabel }}</td>
                        <td style="padding:6px; font-size:9.5px; word-break:break-word;">
                            @if($product->campaign_start)
                                {{ \Carbon\Carbon::parse($product->campaign_start)->format('d M Y') }}
                                @if($product->campaign_end) — {{ \Carbon\Carbon::parse($product->campaign_end)->format('d M Y') }} @endif
                            @else —
                            @endif
                        </td>
                        <td style="padding:6px; text-align:center;"><span style="background:{{ $product->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $product->is_active ? '#065f46' : '#991b1b' }}; font-size:9.5px; font-weight:600; padding:2px 6px; border-radius:20px; white-space:nowrap;">{{ $product->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span></td>
                        <td style="padding:6px; text-align:center; white-space:nowrap;">
                            <a href="{{ route('admin.masterfile.products', ['mode'=>'edit', 'product_id'=>$product->product_id, 'back'=>urlencode($backToResults)]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 7px; font-size:9.5px; font-weight:600;">✏️ {{ __('masterfile.edit') }}</a>
                            <a href="{{ route('admin.document-templates.index', ['product_id'=>$product->product_id]) }}" style="background:#fef3c7; color:#92400e; text-decoration:none; border-radius:6px; padding:3px 7px; font-size:9.5px; font-weight:600;">📄</a>
                            {{-- NEW 12 Aug 2026 — per Chris: reviews browsable from the product's own record too. --}}
                            <a href="{{ route('vendor-reviews.index', ['vendorId'=>$product->vendor_id, 'product_id'=>$product->product_id]) }}" style="background:#fff7ed; color:#b45309; text-decoration:none; border-radius:6px; padding:3px 7px; font-size:9.5px; font-weight:600;">⭐</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($products->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
            @else
                <a href="{{ $products->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{{ __('masterfile.showing_records', ['first' => $products->firstItem(), 'last' => $products->lastItem(), 'total' => $products->total()]) }} &middot; {{ __('masterfile.page_of', ['current' => $products->currentPage(), 'last' => $products->lastPage()]) }}</span>
            @if($products->hasMorePages())
                <a href="{{ $products->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
    </div>

    {{-- EDIT PRODUCT --}}
    @elseif($mode === 'edit' && $selectedProduct)
    @php $backUrl = request('back') ? urldecode(request('back')) : route('admin.masterfile.products', ['mode'=>'search']); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.edit_product_title', ['name' => $selectedProduct->product_name]) }}</div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a href="{{ route('admin.document-templates.index', ['product_id'=>$selectedProduct->product_id]) }}" style="font-size:11px; color:#92400e; text-decoration:none;">{{ __('masterfile.view_document_templates') }}</a>
                <a href="{{ route('admin.masterfile.products', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">{{ __('masterfile.main_menu') }}</a>
            </div>
        </div>
        <div style="padding:12px 16px;">
            <form method="POST" action="{{ route('admin.masterfile.products.update', $selectedProduct->product_id) }}" autocomplete="off">
                @csrf @method('PUT')
                <input type="hidden" name="back" value="{{ urlencode($backUrl) }}">
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_vendor') }}</label>
                        <div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:5px; padding:6px 10px; font-size:11px; color:#374151; font-weight:600;">{{ $selectedProduct->vendor_name }}</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_product_name') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="product_name" value="{{ old('product_name', $selectedProduct->product_name) }}" required maxlength="200" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_code') }} <span style="color:#9ca3af;">({{ __('masterfile.locked_label') }})</span></label>
                        <div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:5px; padding:6px 10px; font-size:11px; font-family:monospace;">{{ $selectedProduct->product_code }}</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_product_type') }} <span style="color:#ef4444;">*</span></label>
                        <select name="product_type" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="MOTOR" {{ old('product_type', $selectedProduct->product_type)==='MOTOR' ? 'selected' : '' }}>{{ __('masterfile.motor_type') }}</option>
                            <option value="PERSONAL_ACCIDENT" {{ old('product_type', $selectedProduct->product_type)==='PERSONAL_ACCIDENT' ? 'selected' : '' }}>{{ __('masterfile.pa_type') }}</option>
                            <option value="FIRE" {{ old('product_type', $selectedProduct->product_type)==='FIRE' ? 'selected' : '' }}>{{ __('masterfile.fire_type') }}</option>
                            <option value="OTHER" {{ old('product_type', $selectedProduct->product_type)==='OTHER' ? 'selected' : '' }}>{{ __('masterfile.other_type') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.campaign_start_label') }}</label>
                        <input type="date" name="campaign_start" value="{{ old('campaign_start', $selectedProduct->campaign_start ? \Carbon\Carbon::parse($selectedProduct->campaign_start)->format('Y-m-d') : '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.campaign_end_label') }}</label>
                        <input type="date" name="campaign_end" value="{{ old('campaign_end', $selectedProduct->campaign_end ? \Carbon\Carbon::parse($selectedProduct->campaign_end)->format('Y-m-d') : '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }} <span style="color:#ef4444;">*</span></label>
                        <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="1" {{ old('is_active', $selectedProduct->is_active)==1 ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ old('is_active', $selectedProduct->is_active)==0 ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.description_label') }}</label>
                        <textarea name="description" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; resize:none;">{{ old('description', $selectedProduct->description) }}</textarea>
                    </div>
                    <div style="grid-column:span 3; display:flex; gap:10px; margin-top:4px; align-items:center;">
                        <a href="{{ $backUrl }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 26px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.update_product_button') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
