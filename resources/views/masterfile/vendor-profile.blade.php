@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.vendor_management_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- MAIN SCREEN --}}
    @if($mode === 'main')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:20px; flex-shrink:0;">
        <div style="display:flex; gap:16px; margin-bottom:16px;">
            <a href="{{ route('admin.vendors.index', ['mode'=>'add']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(21,101,192,.3);">{{ __('masterfile.add_new_vendor') }}</a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'search']) }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:2px solid #1565C0;">{{ __('masterfile.search_vendor') }}</a>
        </div>
        <a href="{{ route('admin.dashboard') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:12px; font-weight:600; display:inline-flex; align-items:center;">{{ __('masterfile.back_to_dashboard') }}</a>
    </div>

    {{-- ADD NEW VENDOR --}}
    @elseif($mode === 'add')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.add_new_vendor') }}</div>
            <a href="{{ route('admin.vendors.index', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">{{ __('masterfile.back') }}</a>
        </div>
        <div style="padding:12px 16px;">
            <form method="POST" action="{{ route('admin.vendors.store') }}" autocomplete="off">
                @csrf
                @include('masterfile.partials.vendor-fields', ['v'=>null, 'states'=>$states, 'submitLabel'=>'➕ '.__('masterfile.add_new_vendor'), 'cancelUrl'=>route('admin.vendors.index', ['mode'=>'main'])])
            </form>
        </div>
    </div>

    {{-- SEARCH CRITERIA --}}
    @elseif($mode === 'search' && $vendors === null)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.search_vendor') }}</div>
        </div>
        <div style="padding:12px 16px;">
            <form method="GET" action="{{ route('admin.vendors.index') }}" autocomplete="off">
                <input type="hidden" name="mode" value="search">
                <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:8px;">
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_vendor_name') }}</label>
                        <input type="text" name="vendor_name" value="{{ request('vendor_name') }}" placeholder="{{ __('masterfile.col_vendor_name') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_vendor_code') }}</label>
                        <input type="text" name="vendor_code" value="{{ request('vendor_code') }}" placeholder="e.g. ALZ" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_office_phone') }}</label>
                        <input type="text" name="vendor_office_phone" value="{{ request('vendor_office_phone') }}" placeholder="+603" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.email') }}</label>
                        <input type="text" name="vendor_email" value="{{ request('vendor_email') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_pic_name') }}</label>
                        <input type="text" name="pic_name" value="{{ request('pic_name') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_address') }}</label>
                        <input type="text" name="vendor_address" value="{{ request('vendor_address') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.postcode') }}</label>
                        <div style="position:relative;">
                            <input type="text" name="vendor_postcode" id="gl_search_postcode" value="{{ request('vendor_postcode') }}" placeholder="e.g. 410" maxlength="5" autocomplete="off" oninput="glPCSearch(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 28px 6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
                        </div>
                        <div id="gl_spc_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
                    </div>
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.city') }}</label>
                        <div style="position:relative;">
                            <input type="text" name="vendor_city" id="gl_search_city" value="{{ request('vendor_city') }}" autocomplete="off" oninput="glCitySearch(this)" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 28px 6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:10px; pointer-events:none;">▼</span>
                        </div>
                        <div id="gl_sct_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:180px;overflow-y:auto;"></div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.state') }}</label>
                        <select name="vendor_state" id="gl_search_state" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_states_option') }}</option>
                            @foreach($states as $state)
                            <option value="{{ $state }}" {{ request('vendor_state') === $state ? 'selected' : '' }}>{{ $state }}</option>
                            @endforeach
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
                        <a href="{{ route('admin.vendors.index', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:12px;">{{ __('masterfile.clear') }}</a>
                    </div>
                </div>
            </form>
        </div>
        <div style="padding:0 16px 14px;">
            <a href="{{ route('admin.vendors.index', ['mode'=>'main']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
        </div>
    </div>

    {{-- SEARCH RESULTS --}}
    @elseif($mode === 'search' && $vendors !== null)
    @php $backToResults = request()->fullUrl(); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0; padding:10px 16px; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:11px; font-weight:700; color:#1565C0;">{{ __('masterfile.vendor_search_results_count', ['count' => $vendors->total()]) }}</div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('admin.vendors.index', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.new_search') }}</a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'main']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.main_menu') }}</a>
        </div>
    </div>
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px; table-layout:fixed;">
                <colgroup>
                    <col style="width:4%;">
                    <col style="width:15%;"><col style="width:8%;"><col style="width:10%;">
                    <col style="width:14%;"><col style="width:7%;"><col style="width:9%;">
                    <col style="width:8%;"><col style="width:9%;"><col style="width:8%;"><col style="width:8%;">
                </colgroup>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0; z-index:1;">
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">No.</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_vendor_name') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_code') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.phone') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.email') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.postcode') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.city') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.state') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_pic_name') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vendors as $v)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                        <td style="padding:6px; text-align:center; color:#9ca3af;">{{ $loop->iteration }}</td>
                        <td style="padding:6px; font-weight:600; color:#111827; word-break:break-word;">{{ $v->vendor_name }}</td>
                        <td style="padding:6px; font-family:monospace; color:#6b7280; word-break:break-word;">{{ $v->vendor_code }}</td>
                        <td style="padding:6px; word-break:break-word;">{{ $v->vendor_office_phone ?? '—' }}</td>
                        <td style="padding:6px; word-break:break-word;">{{ $v->vendor_email ?? '—' }}</td>
                        <td style="padding:6px; word-break:break-word;">{{ $v->vendor_postcode ?? '—' }}</td>
                        <td style="padding:6px; word-break:break-word;">{{ $v->vendor_city ?? '—' }}</td>
                        <td style="padding:6px; word-break:break-word;">{{ $v->vendor_state ?? '—' }}</td>
                        <td style="padding:6px; word-break:break-word;">{{ $v->pic_name ?? '—' }}</td>
                        <td style="padding:6px; text-align:center;"><span style="background:{{ $v->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $v->is_active ? '#065f46' : '#991b1b' }}; font-size:9.5px; font-weight:600; padding:2px 6px; border-radius:20px; white-space:nowrap;">{{ $v->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span></td>
                        <td style="padding:6px; text-align:center;">
                            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$v->vendor_id, 'back'=>urlencode($backToResults)]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 8px; font-size:9.5px; font-weight:600; white-space:nowrap;">✏️ {{ __('masterfile.edit') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($vendors->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
            @else
                <a href="{{ $vendors->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{{ __('masterfile.showing_records', ['first' => $vendors->firstItem(), 'last' => $vendors->lastItem(), 'total' => $vendors->total()]) }} &middot; {{ __('masterfile.page_of', ['current' => $vendors->currentPage(), 'last' => $vendors->lastPage()]) }}</span>
            @if($vendors->hasMorePages())
                <a href="{{ $vendors->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
    </div>

    {{-- EDIT VENDOR — tabbed (Vendor Details / Products Offered) instead
         of stacking both cards, so neither screen ever needs to scroll
         no matter how many products a vendor has. --}}
    @elseif($mode === 'edit' && $selectedVendor)
    @php $backUrl = request('back') ? urldecode(request('back')) : route('admin.vendors.index', ['mode'=>'search']); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:6px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.edit_vendor_title', ['name' => $selectedVendor->vendor_name]) }}</div>
            <a href="{{ route('admin.vendors.index', ['mode'=>'main']) }}" style="font-size:11px; color:#6b7280; text-decoration:none;">{{ __('masterfile.main_menu') }}</a>
        </div>
        <div style="display:flex; gap:4px; padding:4px 16px 0; flex-shrink:0;">
            <button type="button" onclick="showVendorTab('details')" id="tabBtnDetails" style="background:#1565C0; color:#fff; border:none; border-radius:8px 8px 0 0; padding:4px 16px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.tab_vendor_details') }}</button>
            <button type="button" onclick="showVendorTab('products')" id="tabBtnProducts" style="background:#f3f4f6; color:#374151; border:none; border-radius:8px 8px 0 0; padding:4px 16px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.tab_products_offered', ['count' => $vendorProducts->count()]) }}</button>
            {{-- NEW 12 Aug 2026 — per Chris: reviews stored in "the vendor
                 and product profile" as a browsable folder. A plain link
                 (not a JS tab) since the review list is its own full
                 no-scroll + Prev/Next page — see VendorReviewController. --}}
            <a href="{{ route('vendor-reviews.index', $selectedVendor->vendor_id) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:8px 8px 0 0; padding:4px 16px; font-size:11.5px; font-weight:600;">{{ __('masterfile.feedback_reviews') }}</a>
        </div>

        {{-- NEW 8 Aug 2026 (Task #92) — Vendor Portal login status + a
             one-click way for Admin to create a login directly, without
             the vendor having to self-register. Only shown when there's
             no login yet (login_status NONE); once created, this simply
             confirms it's active instead. --}}
        <div style="flex-shrink:0; margin:4px 16px 0; padding:4px 10px; border-radius:6px; background:{{ $selectedVendor->login_status === 'ACTIVE' ? '#e8f5e9' : '#f0f9ff' }}; font-size:10px; color:#374151; display:flex; align-items:center; justify-content:space-between; gap:10px;">
            @if($selectedVendor->login_status === 'ACTIVE')
                <span>{{ __('masterfile.vendor_login_active', ['email' => $selectedVendor->vendor_email]) }}</span>
            @elseif($selectedVendor->login_status === 'PENDING')
                <span>{{ __('masterfile.vendor_login_pending') }}</span>
            @elseif($selectedVendor->login_status === 'AWAITING_PASSWORD')
                <span>{{ __('masterfile.vendor_login_awaiting_password', ['name' => $selectedVendor->vendor_name]) }}</span>
            @elseif($selectedVendor->login_status === 'RESTRICTED')
                <span>{{ __('masterfile.vendor_login_restricted') }}</span>
            @elseif($selectedVendor->login_status === 'REJECTED')
                <span>{{ __('masterfile.vendor_login_rejected', ['reason' => $selectedVendor->rejection_reason ? ' — ' . $selectedVendor->rejection_reason : '']) }}</span>
            @else
                <span>{{ __('masterfile.vendor_login_none') }}</span>
                <form method="POST" action="{{ route('admin.vendors.create-login', $selectedVendor->vendor_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.create_login_confirm', ['name' => $selectedVendor->vendor_name])) }});">
                    @csrf
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:4px 12px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.create_login_button') }}</button>
                </form>
            @endif
        </div>

        {{-- FIXED AGAIN 8 Aug 2026 per Chris: strict "no scroll in any
             direction" rule — the 19 Jul fix below traded clipping for
             scrolling, but scrolling isn't allowed either. Real fix this
             time: vendor-fields.blade.php widened to a 5-column grid and
             every field/label shrunk (see that file's 8 Aug comment),
             which reclaims enough height that the form + Update button
             now fit without scrolling — back to overflow:hidden so a
             future regression is visible (clipped) instead of silently
             scrolling again. --}}
        <div id="vendorTabDetails" style="flex:1; min-height:0; overflow:hidden; padding:4px 16px;">
            <form method="POST" action="{{ route('admin.vendors.update', $selectedVendor->vendor_id) }}" autocomplete="off">
                @csrf @method('PUT')
                <input type="hidden" name="back" value="{{ urlencode($backUrl) }}">
                @include('masterfile.partials.vendor-fields', ['v'=>$selectedVendor, 'states'=>$states, 'submitLabel'=>'💾 '.__('masterfile.save'), 'cancelUrl'=>$backUrl])
            </form>
        </div>

        <div id="vendorTabProducts" style="display:none; flex:1; min-height:0; overflow:hidden; padding:10px 16px; flex-direction:column; position:relative;">
            <div style="display:flex; align-items:center; justify-content:flex-end; flex-shrink:0; margin-bottom:8px;">
                <button type="button" onclick="toggleAddProducts()" id="addProductsToggleBtn" style="background:#e0f2fe; color:#1565C0; border:none; border-radius:6px; padding:6px 16px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.add_products') }}</button>
            </div>

            <div style="flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column;">
                @if($vendorProducts->isEmpty())
                <div style="font-size:11.5px; color:#9ca3af; padding:6px 0;">{{ __('masterfile.no_vendor_products') }}</div>
                @else
                <table id="vendorProductsTable" style="width:100%; border-collapse:collapse; font-size:10.5px; table-layout:fixed;">
                    <colgroup>
                        <col style="width:30%;"><col style="width:16%;"><col style="width:18%;"><col style="width:12%;"><col style="width:24%;">
                    </colgroup>
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe;">
                            <th style="text-align:left; padding:6px; font-weight:600; color:#374151;">{{ __('masterfile.col_product_name') }}</th>
                            <th style="text-align:left; padding:6px; font-weight:600; color:#374151;">{{ __('masterfile.col_code') }}</th>
                            <th style="text-align:left; padding:6px; font-weight:600; color:#374151;">{{ __('masterfile.col_product_type') }}</th>
                            <th style="text-align:center; padding:6px; font-weight:600; color:#374151;">{{ __('masterfile.status') }}</th>
                            <th style="text-align:center; padding:6px; font-weight:600; color:#374151;">{{ __('masterfile.col_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vendorProducts as $p)
                        <tr class="vp-row" data-page="{{ intdiv($loop->index, 6) }}" style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                            <td style="padding:6px; font-weight:600; color:#111827; word-break:break-word;">{{ $p->product_name }}</td>
                            <td style="padding:6px; font-family:monospace; color:#6b7280; word-break:break-word;">{{ $p->product_code }}</td>
                            <td style="padding:6px; word-break:break-word;">{{ ucwords(strtolower(str_replace('_',' ', $p->product_type))) }}</td>
                            <td style="padding:6px; text-align:center;"><span style="background:{{ $p->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $p->is_active ? '#065f46' : '#991b1b' }}; font-size:9.5px; font-weight:600; padding:2px 6px; border-radius:20px; white-space:nowrap;">{{ $p->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span></td>
                            <td style="padding:6px; text-align:center; white-space:nowrap;">
                                <a href="{{ route('admin.masterfile.products', ['mode'=>'edit', 'product_id'=>$p->product_id, 'back'=>urlencode(route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'tab'=>'products']))]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 8px; font-size:9.5px; font-weight:600; white-space:nowrap;">✏️ {{ __('masterfile.edit') }}</a>
                                <form method="POST" action="{{ route('admin.masterfile.product.toggle', $p->product_id) }}" style="display:inline;" onsubmit="return confirm({{ $p->is_active ? json_encode(__('masterfile.withdraw_product_confirm', ['name' => $p->product_name])) : json_encode(__('masterfile.reactivate_product_confirm', ['name' => $p->product_name])) }});">
                                    @csrf @method('PATCH')
                                    <button type="submit" style="background:{{ $p->is_active ? '#fee2e2' : '#d1fae5' }}; color:{{ $p->is_active ? '#991b1b' : '#065f46' }}; border:none; border-radius:6px; padding:3px 8px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap; margin-left:4px;">{{ $p->is_active ? __('masterfile.withdraw') : __('masterfile.reactivate') }}</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($vendorProducts->count() > 6)
                {{-- The table paginates itself in fixed chunks (JS, no
                     scroll) so its total height stays bounded and the
                     Prev button below never gets pushed off-screen as
                     a vendor's product count grows. --}}
                <div style="flex-shrink:0; display:flex; align-items:center; justify-content:center; gap:10px; margin-top:6px;">
                    <button type="button" id="vpPrevBtn" onclick="vpChangePage(-1)" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:3px 10px; font-size:11px; font-weight:600; cursor:pointer;">‹</button>
                    <span id="vpPageLabel" style="font-size:10.5px; color:#6b7280;">{{ __('masterfile.vp_page_label', ['current' => 1, 'total' => 1]) }}</span>
                    <button type="button" id="vpNextBtn" onclick="vpChangePage(1)" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:3px 10px; font-size:11px; font-weight:600; cursor:pointer;">›</button>
                </div>
                @endif
                @endif
            </div>

            {{-- Prev lives bottom-left here too, same as every other
                 edit screen — safe now that the table above is paginated
                 in fixed chunks instead of growing without bound. --}}
            <div style="flex-shrink:0; margin-top:8px; display:flex;">
                <a href="{{ $backUrl }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 18px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
            </div>

            {{-- Quick-add checklist — collapsed by default so it never
                 competes with the product table for space; opens as an
                 overlay panel when "Add Products" is clicked. --}}
            <div id="addProductsPanel" style="display:none; position:absolute; left:16px; right:16px; bottom:16px; top:52px; background:#fff; border:1px solid #B2EBF2; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); padding:14px 16px; overflow:hidden; z-index:50;">
                <form method="POST" action="{{ route('admin.vendors.products.quick-add', $selectedVendor->vendor_id) }}" style="height:100%; display:flex; flex-direction:column;">
                    @csrf
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0; margin-bottom:8px;">
                        <div style="font-size:11px; font-weight:600; color:#374151;">{{ __('masterfile.add_products_intro') }}</div>
                        <button type="button" onclick="toggleAddProducts()" style="background:#f3f4f6; color:#374151; border:none; border-radius:6px; padding:3px 10px; font-size:11px; cursor:pointer;">{{ __('masterfile.close') }}</button>
                    </div>

                    @php
                        $genericTypes = ['MOTOR'=>'Motor','PERSONAL_ACCIDENT'=>'Personal Accident','FIRE'=>'Fire','OTHER'=>'Other'];
                    @endphp

                    <div style="flex:1; min-height:0; overflow:hidden;">
                        @if($catalogSuggestions->isNotEmpty())
                        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:6px 12px; margin-bottom:10px;">
                            @foreach($catalogSuggestions as $c)
                            <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#374151; cursor:pointer;">
                                <input type="checkbox" name="picks[]" value="{{ $c->product_name }}::{{ $c->product_type }}">
                                {{ $c->product_name }} <span style="color:#9ca3af;">({{ ucwords(strtolower(str_replace('_',' ', $c->product_type))) }})</span>
                            </label>
                            @endforeach
                        </div>
                        @endif

                        <div style="font-size:10px; font-weight:600; color:#9ca3af; margin-bottom:4px;">{{ __('masterfile.or_new_generic_product') }}</div>
                        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:6px 12px;">
                            @foreach($genericTypes as $typeKey => $typeLabel)
                            <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#374151; cursor:pointer;">
                                <input type="checkbox" name="picks[]" value="New {{ $typeLabel }} Product::{{ $typeKey }}">
                                {{ __('masterfile.new_generic_product', ['type' => $typeLabel]) }}
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div style="flex-shrink:0; margin-top:10px; display:flex; align-items:center; gap:10px;">
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.add_selected_products') }}</button>
                        <span style="font-size:10px; color:#9ca3af;">{{ __('masterfile.add_products_hint') }}</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if($mode === 'edit' && $selectedVendor)
    <script>
    var glVendorI18n = {
        pageLabel: @json(__('masterfile.vp_page_label', ['current' => ':c', 'total' => ':t']))
    };
    function showVendorTab(tab) {
        var details = document.getElementById('vendorTabDetails');
        var products = document.getElementById('vendorTabProducts');
        var btnDetails = document.getElementById('tabBtnDetails');
        var btnProducts = document.getElementById('tabBtnProducts');
        var showDetails = tab === 'details';
        details.style.display = showDetails ? 'block' : 'none';
        products.style.display = showDetails ? 'none' : 'flex';
        btnDetails.style.background = showDetails ? '#1565C0' : '#f3f4f6';
        btnDetails.style.color = showDetails ? '#fff' : '#374151';
        btnProducts.style.background = showDetails ? '#f3f4f6' : '#1565C0';
        btnProducts.style.color = showDetails ? '#374151' : '#fff';
    }
    function toggleAddProducts() {
        var panel = document.getElementById('addProductsPanel');
        var btn = document.getElementById('addProductsToggleBtn');
        var isOpen = panel.style.display === 'flex';
        panel.style.display = isOpen ? 'none' : 'flex';
        panel.style.flexDirection = 'column';
        btn.textContent = isOpen ? @json(__('masterfile.add_products')) : @json(__('masterfile.close'));
    }

    // Client-side pagination for the vendor's own product table — keeps
    // the tab's total height bounded (no scrollbar) no matter how many
    // products the vendor has, so the Prev button below it always stays
    // reachable without scrolling.
    var vpCurrentPage = 0;
    var vpPageSize = 6;
    function vpRenderPage() {
        var rows = document.querySelectorAll('.vp-row');
        if (!rows.length) return;
        var maxPage = 0;
        rows.forEach(function(r) { maxPage = Math.max(maxPage, parseInt(r.dataset.page, 10)); });
        rows.forEach(function(r) {
            r.style.display = (parseInt(r.dataset.page, 10) === vpCurrentPage) ? '' : 'none';
        });
        var label = document.getElementById('vpPageLabel');
        if (label) label.textContent = glVendorI18n.pageLabel.replace(':c', (vpCurrentPage + 1)).replace(':t', (maxPage + 1));
        var prevBtn = document.getElementById('vpPrevBtn');
        var nextBtn = document.getElementById('vpNextBtn');
        if (prevBtn) prevBtn.disabled = vpCurrentPage === 0;
        if (nextBtn) nextBtn.disabled = vpCurrentPage === maxPage;
    }
    function vpChangePage(delta) {
        vpCurrentPage += delta;
        vpRenderPage();
    }
    vpRenderPage();
    {{-- Reopen the Products tab if we arrived back here from editing a
         product (e.g. clicked "Edit" on a product, then "Prev") — so
         the admin doesn't lose their place and land back on the
         Vendor Details tab instead. --}}
    @if(request('tab') === 'products')
    showVendorTab('products');
    @endif
    </script>
    @endif

</div>

@push('scripts')
<script>
var _glPCt,_glCTt,_glSPCt,_glSCTt,_glBPCt,_glBCTt;
function _pcLookup(v,ddId,pcId,cityId,stateId){
    var dd=document.getElementById(ddId);
    if(v.length<3){dd.style.display='none';return;}
    fetch('/admin/postcode-lookup?postcode='+encodeURIComponent(v)+'&partial=1')
    .then(function(r){return r.json();}).then(function(data){
        if(!data||!data.length){dd.style.display='none';return;}
        dd.innerHTML='';
        data.forEach(function(item){
            var d=document.createElement('div');
            d.style.cssText='padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;';
            d.innerHTML='<strong>'+item.postcode+'</strong> — '+item.city+' ('+item.state+')';
            d.onmouseover=function(){this.style.background='#f0f9ff';};
            d.onmouseout=function(){this.style.background='';};
            d.onmousedown=function(e){
                e.preventDefault();
                var pi=document.getElementById(pcId), ci=document.getElementById(cityId), si=document.getElementById(stateId);
                if(pi)pi.value=item.postcode;
                if(ci)ci.value=item.city;
                if(si){for(var i=0;i<si.options.length;i++){if(si.options[i].value===item.state){si.selectedIndex=i;break;}}}
                dd.style.display='none';
            };
            dd.appendChild(d);
        });
        dd.style.display='block';
    }).catch(function(){dd.style.display='none';});
}
function _cityLookup(v,ddId,cityId,stateId){
    var dd=document.getElementById(ddId);
    if(v.length<2){dd.style.display='none';return;}
    fetch('/admin/postcode-lookup?city='+encodeURIComponent(v))
    .then(function(r){return r.json();}).then(function(data){
        if(!data||!data.length){dd.style.display='none';return;}
        dd.innerHTML='';var seen={};
        data.forEach(function(item){
            if(seen[item.city])return;seen[item.city]=true;
            var d=document.createElement('div');
            d.style.cssText='padding:7px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;';
            d.innerHTML=item.city+' ('+item.state+')';
            d.onmouseover=function(){this.style.background='#f0f9ff';};
            d.onmouseout=function(){this.style.background='';};
            d.onmousedown=function(e){
                e.preventDefault();
                var ci=document.getElementById(cityId), si=document.getElementById(stateId);
                if(ci)ci.value=item.city;
                if(si){for(var i=0;i<si.options.length;i++){if(si.options[i].value===item.state){si.selectedIndex=i;break;}}}
                dd.style.display='none';
            };
            dd.appendChild(d);
        });
        dd.style.display='block';
    }).catch(function(){dd.style.display='none';});
}
function glPC(inp){clearTimeout(_glPCt);_glPCt=setTimeout(function(){_pcLookup(inp.value.trim(),'gl_pc_dd','gl_vendor_postcode','gl_vendor_city','gl_vendor_state');},300);}
function glCity(inp){clearTimeout(_glCTt);_glCTt=setTimeout(function(){_cityLookup(inp.value.trim(),'gl_city_dd','gl_vendor_city','gl_vendor_state');},300);}
function glPCSearch(inp){clearTimeout(_glSPCt);_glSPCt=setTimeout(function(){_pcLookup(inp.value.trim(),'gl_spc_dd','gl_search_postcode','gl_search_city','gl_search_state');},300);}
function glCitySearch(inp){clearTimeout(_glSCTt);_glSCTt=setTimeout(function(){_cityLookup(inp.value.trim(),'gl_sct_dd','gl_search_city','gl_search_state');},300);}
function glBPC(inp){clearTimeout(_glBPCt);_glBPCt=setTimeout(function(){_pcLookup(inp.value.trim(),'gl_bpc_dd','gl_branch_postcode','gl_branch_city','gl_branch_state');},300);}
function glBCity(inp){clearTimeout(_glBCTt);_glBCTt=setTimeout(function(){_cityLookup(inp.value.trim(),'gl_bct_dd','gl_branch_city','gl_branch_state');},300);}
document.addEventListener('click',function(e){
    ['gl_pc_dd','gl_city_dd','gl_spc_dd','gl_sct_dd','gl_bpc_dd','gl_bct_dd'].forEach(function(id){
        var el=document.getElementById(id);
        if(el&&!el.contains(e.target))el.style.display='none';
    });
});
</script>
@endpush
@endsection
