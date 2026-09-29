@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.earning_income_structures_title'))
@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:5px 12px; display:flex; flex-direction:column; gap:4px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:4px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:4px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- MAIN --}}
    @if($mode === 'main')
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:20px; flex-shrink:0;">
        <div style="font-size:14px; font-weight:700; color:#1565C0; margin-bottom:16px;">{{ __('masterfile.earning_income_structure_mgmt_title') }}</div>
        <div style="display:flex; gap:16px;">
            <a href="{{ route('admin.masterfile.commissions', ['mode'=>'add']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(21,101,192,.3);">{{ __('masterfile.add_new_earning_income_structure') }}</a>
            <a href="{{ route('admin.masterfile.commissions', ['mode'=>'search']) }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:8px; padding:12px 30px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:2px solid #1565C0;">{{ __('masterfile.search_earning_income_structures') }}</a>
        </div>
    </div>

    {{-- ADD --}}
    @elseif($mode === 'add')
    @php
        $addVendorName = $vendors->firstWhere('vendor_id', old('vendor_id'))->vendor_name ?? '';
        $addProductName = $products->firstWhere('product_id', old('product_id'))->product_name ?? '';
    @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; flex-shrink:0;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.add_new_earning_income_structure') }}</div>
        </div>
        <div style="padding:12px 16px; flex:1; min-height:0; overflow-y:auto;">
            <form method="POST" action="{{ route('admin.masterfile.commissions.store') }}" autocomplete="off" id="csForm"
                onsubmit="if(!document.getElementById('addVendorId').value){alert({{ json_encode(__('masterfile.pick_vendor_alert')) }});return false;} if(!document.getElementById('addProductId').value){alert({{ json_encode(__('masterfile.pick_product_alert')) }});return false;}">
                @csrf
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_vendor') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="addVendorBox" autocomplete="off" placeholder="{{ __('masterfile.type_to_search_vendor') }}" value="{{ $addVendorName }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                        <input type="hidden" name="vendor_id" id="addVendorId" value="{{ old('vendor_id') }}">
                        <div id="addVendorList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:220px; overflow-y:auto;"></div>
                    </div>
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.product_label') }} <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="addProductBox" autocomplete="off" placeholder="{{ __('masterfile.type_to_search_product') }}" value="{{ $addProductName }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                        <input type="hidden" name="product_id" id="addProductId" value="{{ old('product_id') }}">
                        <div id="addProductList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:220px; overflow-y:auto;"></div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.effective_from_label') }} <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="valid_from" value="{{ old('valid_from') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.effective_to_label') }}</label>
                        <input type="date" name="valid_to" value="{{ old('valid_to') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.total_earning_income_pct_field_label') }} <span style="color:#ef4444;">*</span></label>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <input type="number" id="totalCommPct" name="total_commission_pct" value="{{ old('total_commission_pct') }}" required step="0.01" min="0.01" max="100" placeholder="e.g. 10.00" style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                            <span style="font-size:10px; color:#6b7280; white-space:nowrap;">{{ __('masterfile.pct_of_label') }}</span>
                            <div style="flex:1; background:#f3f4f6; border:1px solid #d1d5db; border-radius:5px; padding:6px 10px; font-size:11px; color:#374151; font-weight:600;">{{ __('masterfile.sales_amount_label') }}</div>
                            <input type="hidden" name="commission_basis" value="PREMIUM_PCT">
                        </div>
                    </div>

                    <div style="grid-column:span 3; background:#f0f9ff; border-radius:8px; padding:10px 14px; border:1px solid #e0f2fe;">
                        <div style="font-size:11px; font-weight:600; color:#1565C0; margin-bottom:2px;">{{ __('masterfile.role_allocation_label') }}</div>

                        <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#374151; margin-bottom:8px; cursor:pointer; background:#fffbeb; border:1px solid #fde68a; border-radius:5px; padding:6px 8px;">
                            <input type="checkbox" name="is_rank_only" id="isRankOnly" value="1" {{ old('is_rank_only') ? 'checked' : '' }} onchange="toggleRankOnly()">
                            {!! __('masterfile.rank_only_checkbox_label', ['rankOnly' => '<strong>'.__('masterfile.rank_only_bold').'</strong>', 'gl' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'), 'tl' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER'), 'introducer' => \App\Services\RoleLabelService::label('INTRODUCER')]) !!}
                        </label>

                        <div id="roleSplitBox">
                            <div style="font-size:10px; color:#6b7280; margin-bottom:8px;">{!! __('masterfile.alloc_pct_from_total_label', ['pct' => '<span id="allocTargetPct" style="font-weight:700; color:#1565C0;">0.00</span>', 'gl' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'), 'tl' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER'), 'introducer' => \App\Services\RoleLabelService::label('INTRODUCER')]) !!}</div>
                            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                                <div>
                                    <label style="display:block; font-size:10px; font-weight:600; color:#16a34a; margin-bottom:2px;" title="{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }} %">{{ \App\Services\RoleLabelService::shortLabel('GROUP_LEADER') }} %</label>
                                    <input type="number" name="group_leader_pct" id="glPct" value="{{ old('group_leader_pct', 0) }}" step="0.01" min="0" placeholder="0.00" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                                </div>
                                <div>
                                    <label style="display:block; font-size:10px; font-weight:600; color:#0891b2; margin-bottom:2px;" title="{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }} %">{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }} %</label>
                                    <input type="number" name="team_leader_pct" id="tlPct" value="{{ old('team_leader_pct', 0) }}" step="0.01" min="0" placeholder="0.00" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                                </div>
                                <div>
                                    <label style="display:block; font-size:10px; font-weight:600; color:#7c3aed; margin-bottom:2px;" title="{{ \App\Services\RoleLabelService::label('INTRODUCER') }} %">{{ \App\Services\RoleLabelService::label('INTRODUCER') }} %</label>
                                    <input type="number" name="introducer_pct" id="introPct" value="{{ old('introducer_pct', 0) }}" step="0.01" min="0" placeholder="0.00" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; padding-top:8px; margin-top:8px; border-top:1px solid #e0f2fe;">
                                <span style="font-size:11px; font-weight:600; color:#374151;">{{ __('masterfile.allocated_total_label') }}</span>
                                <span id="splitTotal" style="font-size:13px; font-weight:700; color:#1565C0;">0.00%</span>
                            </div>
                            <div id="splitMsg" style="font-size:10px; color:#6b7280; text-align:right;"></div>
                        </div>

                        {{-- NEW 31 Jul 2026 — per Chris: rank % is set on a
                        totally separate screen now, not embedded here (it
                        was making this form tall enough to clip its own
                        Save button). A brand-new structure has no
                        structure_id yet for the rank allocation to attach
                        to, so this is just a heads-up — the actual "Configure
                        Rank Allocation" button appears after saving, on Edit. --}}
                        <div id="rankOnlyTotalBox" style="display:none;">
                            <div style="font-size:10px; color:#6b7280;">{{ __('masterfile.save_structure_first_note') }}</div>
                        </div>
                    </div>

                    <div style="grid-column:span 3; display:flex; gap:8px; margin-top:4px;">
                        <a href="{{ route('admin.masterfile.commissions', ['mode'=>'main']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 28px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save_earning_income_structure_button') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SEARCH CRITERIA --}}
    @elseif($mode === 'search' && $structures === null)
    @php
        $searchVendorName = $vendors->firstWhere('vendor_id', request('vendor_id'))->vendor_name ?? '';
        $searchProductName = $products->firstWhere('product_id', request('product_id'))->product_name ?? '';
    @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.search_earning_income_structures') }}</div>
        </div>
        <div style="padding:12px 16px;">
            <form method="GET" action="{{ route('admin.masterfile.commissions') }}" autocomplete="off">
                <input type="hidden" name="mode" value="search">
                <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:8px;">
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.col_vendor') }}</label>
                        <input type="text" id="searchVendorBox" autocomplete="off" placeholder="{{ __('masterfile.type_to_search_vendor') }}" value="{{ $searchVendorName }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                        <input type="hidden" name="vendor_id" id="searchVendorId" value="{{ request('vendor_id') }}">
                        <div id="searchVendorList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:220px; overflow-y:auto;"></div>
                    </div>
                    <div style="position:relative;">
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.product_label') }}</label>
                        <input type="text" id="searchProductBox" autocomplete="off" placeholder="{{ __('masterfile.type_to_search_product') }}" value="{{ $searchProductName }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;">
                        <input type="hidden" name="product_id" id="searchProductId" value="{{ request('product_id') }}">
                        <div id="searchProductList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:220px; overflow-y:auto;"></div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }}</label>
                        <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="">{{ __('masterfile.all_option') }}</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div>
                        {{-- NEW 1 Aug 2026 — per Chris: the Search Results
                        HQ/Cwg/Ali column headers were always showing System
                        Default's labels because there was no way to tell
                        this screen which group's labels to use. This picker
                        sets group_label_id, which the controller already
                        reads at the top of commissionStructures() and now
                        the Search Results header actually uses. --}}
                        <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.labels_for_label') }}</label>
                        <select name="group_label_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                            @foreach($groupLabels as $g)
                            <option value="{{ $g->group_label_id }}" {{ request('group_label_id') === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="grid-column:span 2; display:flex; align-items:flex-end; gap:8px;">
                        <button type="submit" name="do_search" value="1" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 24px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
                        <a href="{{ route('admin.masterfile.commissions', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:12px;">{{ __('masterfile.clear') }}</a>
                    </div>
                </div>
            </form>
        </div>
        <div style="padding:10px 16px; border-top:1px solid #f3f4f6;">
            <a href="{{ route('admin.masterfile.commissions', ['mode'=>'main']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        </div>
    </div>

    {{-- SEARCH RESULTS --}}
    @elseif($mode === 'search' && $structures !== null)
    @php $backToResults = request()->fullUrl(); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0; padding:10px 16px; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:11px; font-weight:700; color:#1565C0;">{{ __('masterfile.vendor_search_results_count', ['count' => $structures->total()]) }}</div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('admin.masterfile.commissions', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.new_search') }}</a>
            <a href="{{ route('admin.masterfile.commissions', ['mode'=>'main']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.main_menu') }}</a>
        </div>
    </div>
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px; table-layout:fixed;">
                <colgroup>
                    <col style="width:3%;">
                    <col style="width:13%;"><col style="width:13%;">
                    <col style="width:10%;"><col style="width:7%;">
                    <col style="width:7%;"><col style="width:7%;"><col style="width:7%;">
                    <col style="width:9%;"><col style="width:9%;">
                    <col style="width:6%;"><col style="width:9%;">
                </colgroup>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0; z-index:1;">
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">No.</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_vendor') }}</th>
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.product_label') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.basis_label') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.total_pct_label') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#16a34a;">{{ $roleShortLabels['GROUP_LEADER'] }} %</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#0891b2;">{{ $roleShortLabels['TEAM_LEADER'] }} %</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#7c3aed;">{{ $roleShortLabels['INTRODUCER'] }} %</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.effective_from_label') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.effective_to_label') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($structures as $cs)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                        <td style="padding:6px; text-align:center; color:#9ca3af;">{{ $loop->iteration }}</td>
                        <td style="padding:6px; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $cs->vendor_name }}">{{ $cs->vendor_name }}</td>
                        <td style="padding:6px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $cs->product_name }}">{{ $cs->product_name }}</td>
                        <td style="padding:6px 4px; text-align:center; overflow:hidden;"><span style="font-size:8.5px; background:#f3f4f6; padding:2px 5px; border-radius:4px; white-space:nowrap; display:inline-block; max-width:100%; overflow:hidden; text-overflow:ellipsis;">{{ $cs->commission_basis === 'PREMIUM_PCT' ? __('masterfile.sales_amount_label') : __('masterfile.sum_insured_label') }}</span></td>
                        <td style="padding:6px; text-align:center; font-weight:700; color:#1565C0;" @if(!empty($cs->rank_group_name)) title="{{ __('masterfile.rank_group_tooltip', ['name' => $cs->rank_group_name]) }}" @endif>
                            {{ number_format($cs->total_commission_pct, 2) }}%
                            @if(!empty($cs->rank_group_name))
                            <span style="font-size:8px; color:#9ca3af; cursor:help;">ⓘ</span>
                            @endif
                        </td>
                        <td style="padding:6px; text-align:center; color:#16a34a; font-weight:600;">{{ number_format($cs->group_leader_pct, 2) }}%</td>
                        <td style="padding:6px; text-align:center; color:#0891b2; font-weight:600;">{{ number_format($cs->team_leader_pct, 2) }}%</td>
                        <td style="padding:6px; text-align:center; color:#7c3aed; font-weight:600;">{{ number_format($cs->introducer_pct, 2) }}%</td>
                        <td style="padding:6px; text-align:center; font-size:9.5px;">{{ \Carbon\Carbon::parse($cs->valid_from)->format('d M Y') }}</td>
                        <td style="padding:6px; text-align:center; font-size:9.5px;">{{ $cs->valid_to ? \Carbon\Carbon::parse($cs->valid_to)->format('d M Y') : '∞' }}</td>
                        <td style="padding:6px; text-align:center;"><span style="background:{{ $cs->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $cs->is_active ? '#065f46' : '#991b1b' }}; font-size:9.5px; font-weight:600; padding:2px 8px; border-radius:20px; white-space:nowrap;">{{ $cs->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span></td>
                        <td style="padding:6px; text-align:center; white-space:nowrap;">
                            <a href="{{ route('admin.masterfile.commissions', ['mode'=>'edit', 'structure_id'=>$cs->structure_id, 'group_label_id'=>$groupLabelId, 'back'=>urlencode($backToResults)]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 8px; font-size:9.5px; font-weight:600;">✏️ {{ __('masterfile.edit') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="12" style="text-align:center; padding:30px; color:#9ca3af;">{{ __('masterfile.no_earning_income_structures_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($structures->onFirstPage())
                {{-- No previous data page — Prev must still go somewhere.
                     Chris: "why cannot click Prev to go back previous
                     screen" — falls back to the Search Criteria screen,
                     with whatever was searched still filled in. --}}
                <a href="{{ route('admin.masterfile.commissions', array_merge(['mode'=>'search'], request()->only(['vendor_id','product_id','status']))) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @else
                <a href="{{ $structures->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{{ __('masterfile.showing_records', ['first' => $structures->firstItem() ?? 0, 'last' => $structures->lastItem() ?? 0, 'total' => $structures->total()]) }} &middot; {{ __('masterfile.page_of', ['current' => $structures->currentPage(), 'last' => max($structures->lastPage(),1)]) }}</span>
            @if($structures->hasMorePages())
                <a href="{{ $structures->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
    </div>
    {{-- VIEW / EDIT --}}
    @elseif($mode === 'edit' && $selectedStructure)
    @php $backUrl = request('back') ? urldecode(request('back')) : route('admin.masterfile.commissions', ['mode'=>'search']); @endphp
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; overflow:hidden; flex-shrink:0;">
        <div style="padding:5px 14px; border-bottom:1px solid #e0f2fe; flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:8px;">
            <div style="font-size:12px; font-weight:700; color:#1565C0;">{{ __('masterfile.edit_earning_income_structure_title', ['vendor' => $selectedStructure->vendor_name, 'product' => $selectedStructure->product_name]) }}</div>
            {{-- NEW 1 Aug 2026 — per Chris: PVATM's own short labels (HQ/
            Cwg/Ali) weren't showing here, only System Default's, because
            this screen had no group context to resolve labels against.
            Picking a group here reloads with ?group_label_id= so the
            labels below AND the Rank Allocation link both use that
            group's own names. --}}
            <form method="GET" action="{{ route('admin.masterfile.commissions') }}" style="display:flex; align-items:center; gap:5px; flex-shrink:0;">
                <input type="hidden" name="mode" value="edit">
                <input type="hidden" name="structure_id" value="{{ $selectedStructure->structure_id }}">
                <label style="font-size:8.5px; font-weight:600; color:#6b7280; white-space:nowrap;">{{ __('masterfile.labels_for_label') }}:</label>
                <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:2px 6px; font-size:9.5px; background:#fff;">
                    @foreach($groupLabels as $g)
                    <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div style="padding:5px 14px; overflow-x:hidden; font-size:10px;">
            <form method="POST" action="{{ route('admin.masterfile.commissions.update', $selectedStructure->structure_id) }}" autocomplete="off">
                @csrf @method('PUT')
                <input type="hidden" name="back" value="{{ urlencode($backUrl) }}">
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:4px;">
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.col_vendor') }} <span style="color:#9ca3af;">({{ __('masterfile.locked_label') }})</span></label>
                        <div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10px; color:#374151; font-weight:600;">{{ $selectedStructure->vendor_name }}</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.product_label') }} <span style="color:#9ca3af;">({{ __('masterfile.locked_label') }})</span></label>
                        <div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10px; color:#374151; font-weight:600;">{{ $selectedStructure->product_name }}</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.status') }} <span style="color:#ef4444;">*</span></label>
                        <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; outline:none; box-sizing:border-box; background:#fff;">
                            <option value="1" {{ old('is_active', $selectedStructure->is_active) == 1 ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ old('is_active', $selectedStructure->is_active) == 0 ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.effective_from_label') }} <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="valid_from" value="{{ old('valid_from', \Carbon\Carbon::parse($selectedStructure->valid_from)->format('Y-m-d')) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; outline:none; box-sizing:border-box;">
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.total_earning_income_pct_field_label') }} <span style="color:#ef4444;">*</span></label>
                        <div style="display:flex; align-items:center; gap:5px;">
                            <input type="number" id="totalCommPct" name="total_commission_pct" value="{{ old('total_commission_pct', $selectedStructure->total_commission_pct) }}" required step="0.01" min="0.01" max="100" style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                            <span style="font-size:9px; color:#6b7280; white-space:nowrap;">{{ __('masterfile.pct_of_label') }}</span>
                            <div style="flex:1; background:#f3f4f6; border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10px; color:#374151; font-weight:600;">{{ __('masterfile.sales_amount_label') }}</div>
                            <input type="hidden" name="commission_basis" value="PREMIUM_PCT">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:600; color:#374151; margin-bottom:1px;">{{ __('masterfile.effective_to_label') }}</label>
                        <input type="date" name="valid_to" value="{{ old('valid_to', $selectedStructure->valid_to ? \Carbon\Carbon::parse($selectedStructure->valid_to)->format('Y-m-d') : '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; outline:none; box-sizing:border-box;">
                    </div>

                    <div style="grid-column:span 3; background:#f0f9ff; border-radius:8px; padding:5px 10px; border:1px solid #e0f2fe;">
                        <div style="font-size:9.5px; font-weight:600; color:#1565C0; margin-bottom:1px;">{{ __('masterfile.role_allocation_label') }}</div>

                        {{-- NEW 1 Aug 2026 (3) — per Chris: "if the user
                        select the group label that no rank assignment
                        you should not allow him to configure by rank."
                        Whichever group is picked in "Labels for" above
                        must actually have its own rank ladder defined in
                        Rank Hierarchy Maintenance first — otherwise
                        "Rank only" / Configure Rank Allocation would open
                        onto an empty grid with nothing to save against.
                        $groupHasRanks is computed in the controller,
                        scoped to exactly the group currently selected. --}}
                        @if($groupHasRanks)
                        <label style="display:flex; align-items:center; gap:5px; font-size:9px; color:#374151; margin-bottom:3px; cursor:pointer; background:#fffbeb; border:1px solid #fde68a; border-radius:5px; padding:3px 7px;">
                            <input type="checkbox" name="is_rank_only" id="isRankOnly" value="1" {{ old('is_rank_only', $selectedStructure->is_rank_only) ? 'checked' : '' }} onchange="toggleRankOnly()">
                            {!! __('masterfile.rank_only_checkbox_label', ['rankOnly' => '<strong>'.__('masterfile.rank_only_bold').'</strong>', 'gl' => $roleShortLabels['GROUP_LEADER'], 'tl' => $roleShortLabels['TEAM_LEADER'], 'introducer' => $roleShortLabels['INTRODUCER']]) !!}
                        </label>

                        {{-- FIXED 1 Aug 2026 — this link used to live ONLY
                        inside #rankOnlyTotalBox, which is hidden whenever
                        "Rank only" is unchecked. That meant if a structure
                        used per-rank % NESTED inside the HQ/Cwg/Introducer
                        split (the "Role-nested" rank mode — a real,
                        already-supported option, not a mistake), there was
                        NO way to get back into the Rank Allocation screen
                        to edit it at all. Chris: "why I cannot edit the
                        Rank allocation set up? why no edit function".
                        Now always visible regardless of the checkbox, so
                        both rank modes stay reachable. Also now carries the
                        same group_label_id so Rank Allocation opens on the
                        same group you were just viewing labels for. --}}
                        {{-- NEW 1 Aug 2026 (2) — per Chris: one earning
                        income structure can have AT MOST ONE rank
                        allocation record per group — never zero-or-many,
                        always zero-or-one. Clicking this link never
                        creates a duplicate/new one: it always opens
                        whatever already exists (pre-filled from
                        $existingRankAllocations) or, if nothing exists yet,
                        a blank form to create the single record. Saving
                        does a delete+re-insert scoped ONLY to this exact
                        structure_id + this group's rank_ids (see
                        saveRankAllocations()) — always amending that one
                        record in place, never adding a second. Label kept
                        as one static "Configure/Edit" wording (per Chris)
                        rather than switching text based on state, since
                        it's really the same single action either way. --}}
                        <div style="display:flex; align-items:center; justify-content:space-between; background:#eef2ff; border:1px solid #c7d2fe; border-radius:5px; padding:4px 8px; margin-bottom:5px;">
                            <span style="font-size:8.5px; color:#4338ca;">{{ __('masterfile.rank_pct_allocated_label', ['pct' => number_format($rankAllocatedTotal ?? 0, 2)]) }}</span>
                            <a href="{{ route('admin.masterfile.commissions.rank-allocation', ['structureId' => $selectedStructure->structure_id, 'group_label_id' => $groupLabelId]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:3px 12px; font-size:9px; font-weight:600;">{{ __('masterfile.configure_edit_rank_allocation_button') }}</a>
                        </div>
                        @else
                        {{-- Checkbox is hidden, but is_rank_only is a
                        STRUCTURE-WIDE field (not per-group) — this hidden
                        input preserves whatever it's currently set to, so
                        viewing a group with no ranks and saving other
                        fields (dates, status, etc.) never silently
                        un-sets Rank Only if it was legitimately turned on
                        while looking at a different, valid group. --}}
                        <input type="hidden" name="is_rank_only" value="{{ old('is_rank_only', $selectedStructure->is_rank_only) ? '1' : '0' }}">
                        <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:5px; padding:6px 8px; margin-bottom:5px; font-size:8.5px; color:#991b1b;">
                            ⚠ {{ __('masterfile.group_has_no_ranks_warning', ['group' => $groupLabelId ? $groupLabels->firstWhere('group_label_id', $groupLabelId)->group_name ?? __('masterfile.this_group_fallback') : __('masterfile.this_group_fallback')]) }}
                            <a href="{{ route('admin.masterfile.role-ranks', ['group_label_id' => $groupLabelId]) }}" style="color:#991b1b; font-weight:700; text-decoration:underline;">{{ __('masterfile.go_to_rank_hierarchy_link') }}</a>
                        </div>
                        @endif

                        <div id="roleSplitBox">
                            <div style="font-size:8.5px; color:#6b7280; margin-bottom:3px;">{!! __('masterfile.alloc_pct_from_total_label', ['pct' => '<span id="allocTargetPct" style="font-weight:700; color:#1565C0;">0.00</span>', 'gl' => $roleShortLabels['GROUP_LEADER'], 'tl' => $roleShortLabels['TEAM_LEADER'], 'introducer' => $roleShortLabels['INTRODUCER']]) !!}</div>
                            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:4px;">
                                <div>
                                    <label style="display:block; font-size:9px; font-weight:600; color:#16a34a; margin-bottom:1px;">{{ $roleShortLabels['GROUP_LEADER'] }} %</label>
                                    <input type="number" name="group_leader_pct" id="glPct" value="{{ old('group_leader_pct', $selectedStructure->group_leader_pct) }}" step="0.01" min="0" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                                </div>
                                <div>
                                    <label style="display:block; font-size:9px; font-weight:600; color:#0891b2; margin-bottom:1px;">{{ $roleShortLabels['TEAM_LEADER'] }} %</label>
                                    <input type="number" name="team_leader_pct" id="tlPct" value="{{ old('team_leader_pct', $selectedStructure->team_leader_pct) }}" step="0.01" min="0" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                                </div>
                                <div>
                                    <label style="display:block; font-size:9px; font-weight:600; color:#7c3aed; margin-bottom:1px;">{{ $roleShortLabels['INTRODUCER'] }} %</label>
                                    <input type="number" name="introducer_pct" id="introPct" value="{{ old('introducer_pct', $selectedStructure->introducer_pct) }}" step="0.01" min="0" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; outline:none; box-sizing:border-box;" oninput="updateSplitTotal()">
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; padding-top:3px; margin-top:3px; border-top:1px solid #e0f2fe;">
                                <span style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('masterfile.allocated_total_label') }}</span>
                                <span id="splitTotal" style="font-size:10.5px; font-weight:700; color:#1565C0;">0.00%</span>
                            </div>
                            <div id="splitMsg" style="font-size:8.5px; color:#6b7280; text-align:right;"></div>

                            {{-- NEW 31 Jul 2026 — Rank system Phase 2, Admin-only.
                            Lets one specific GL or TL run their own downline
                            split instead of the shared %. Rare — most agents
                            never need this — so it's a small link, not a
                            prominent button. --}}
                            <a href="{{ route('admin.masterfile.commissions.cascade-overrides', $selectedStructure->structure_id) }}" style="display:inline-block; margin-top:3px; font-size:8.5px; color:#7c3aed; text-decoration:none; font-weight:600;">{{ __('masterfile.multi_tier_overrides_link') }}</a>
                        </div>

                    </div>

                    <div style="grid-column:span 3; display:flex; gap:8px; margin-top:0; align-items:center;">
                        <a href="{{ $backUrl }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 16px; font-size:10.5px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:4px 20px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.update_earning_income_structure_button') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
// Vendor/Product typeahead — same debounced fetch() + position:fixed
// dropdown pattern already used on Renewal Forecast. Chris: plain
// dropdowns couldn't be searched by typing.
(function() {
    var VENDOR_URL  = '{{ route('admin.masterfile.commissions.vendor-typeahead') }}';
    var PRODUCT_URL = '{{ route('admin.masterfile.commissions.product-typeahead') }}';

    function initTypeahead(boxId, hiddenId, listId, url, labelField, idField, vendorHiddenId) {
        var box = document.getElementById(boxId);
        if (!box) { return; }
        var hidden = document.getElementById(hiddenId);
        var list = document.getElementById(listId);
        var timer = null;

        box.addEventListener('input', function() {
            hidden.value = '';
            clearTimeout(timer);
            var q = box.value.trim();
            if (q.length < 1) { list.style.display = 'none'; return; }
            timer = setTimeout(function() {
                var fetchUrl = url + '?q=' + encodeURIComponent(q);
                if (vendorHiddenId) {
                    var vId = document.getElementById(vendorHiddenId).value || '';
                    fetchUrl += '&vendor_id=' + encodeURIComponent(vId);
                }
                fetch(fetchUrl)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        list.innerHTML = '';
                        if (!data.length) { list.style.display = 'none'; return; }
                        var rect = box.getBoundingClientRect();
                        list.style.left = rect.left + 'px';
                        list.style.top = rect.bottom + 'px';
                        list.style.width = Math.max(rect.width, 200) + 'px';
                        data.forEach(function(item) {
                            var row = document.createElement('div');
                            row.style.cssText = 'padding:5px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            row.textContent = item[labelField];
                            row.addEventListener('mouseover', function() { row.style.background = '#EBF5FB'; });
                            row.addEventListener('mouseout', function() { row.style.background = ''; });
                            row.addEventListener('mousedown', function() {
                                box.value = item[labelField];
                                hidden.value = item[idField];
                                list.style.display = 'none';
                            });
                            list.appendChild(row);
                        });
                        list.style.display = 'block';
                    });
            }, 200);
        });

        document.addEventListener('click', function(e) {
            if (e.target !== box) { list.style.display = 'none'; }
        });
    }

    initTypeahead('addVendorBox',  'addVendorId',  'addVendorList',  VENDOR_URL,  'vendor_name',  'vendor_id');
    initTypeahead('addProductBox', 'addProductId', 'addProductList', PRODUCT_URL, 'product_name', 'product_id', 'addVendorId');
    initTypeahead('searchVendorBox',  'searchVendorId',  'searchVendorList',  VENDOR_URL,  'vendor_name',  'vendor_id');
    initTypeahead('searchProductBox', 'searchProductId', 'searchProductList', PRODUCT_URL, 'product_name', 'product_id', 'searchVendorId');
})();

var csI18n = {
    enterTotalFirst: @json(__('masterfile.enter_total_pct_first')),
    matches: @json(__('masterfile.allocation_matches_total')),
    exceeds: @json(__('masterfile.allocation_exceeds_total', ['pct' => ':pct'])),
    under: @json(__('masterfile.allocation_under_total', ['pct' => ':pct']))
};
function updateSplitTotal() {
    var totalEl = document.getElementById('totalCommPct');
    if (!totalEl) { return; }
    var total  = parseFloat(totalEl.value) || 0;
    var gl     = parseFloat(document.getElementById('glPct').value)    || 0;
    var tl     = parseFloat(document.getElementById('tlPct').value)    || 0;
    var intro  = parseFloat(document.getElementById('introPct').value) || 0;
    var allocated = gl + tl + intro;
    var el  = document.getElementById('splitTotal');
    var msg = document.getElementById('splitMsg');
    // Chris: the "must equal Total Earning Income %" line must show the
    // ACTUAL number typed above (10% shows 10%, 20% shows 20%), not a
    // fixed/generic label.
    var targetEl = document.getElementById('allocTargetPct');
    if (targetEl) { targetEl.textContent = total.toFixed(2); }
    el.textContent = allocated.toFixed(2) + '%';
    var diff = Math.abs(allocated - total);
    if (total === 0) {
        el.style.color = '#6b7280';
        msg.textContent = csI18n.enterTotalFirst;
        msg.style.color = '#6b7280';
    } else if (diff < 0.001) {
        el.style.color = '#16a34a';
        msg.textContent = csI18n.matches;
        msg.style.color = '#16a34a';
    } else if (allocated > total) {
        el.style.color = '#dc2626';
        msg.textContent = csI18n.exceeds.replace(':pct', (allocated - total).toFixed(2));
        msg.style.color = '#dc2626';
    } else {
        el.style.color = '#f59e0b';
        msg.textContent = csI18n.under.replace(':pct', (total - allocated).toFixed(2));
        msg.style.color = '#f59e0b';
    }
}

// NEW 31 Jul 2026 — "Rank-Only Structure" mode. Toggling the checkbox
// hides the role % boxes (forcing them to 0, which is what gets
// submitted) and shows the rank status/link box instead. The rank grid
// itself no longer lives on this page — see rank-allocation.blade.php.
function toggleRankOnly() {
    var checked = document.getElementById('isRankOnly').checked;
    var roleBox = document.getElementById('roleSplitBox');
    var rankOnlyBox = document.getElementById('rankOnlyTotalBox');
    if (roleBox) { roleBox.style.display = checked ? 'none' : ''; }
    if (rankOnlyBox) { rankOnlyBox.style.display = checked ? '' : 'none'; }

    if (checked) {
        ['glPct', 'tlPct', 'introPct'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) { el.value = 0; }
        });
    }

    updateSplitTotal();
}

// Show the current allocation immediately on Edit (pre-filled values),
// not just after the user touches a field.
updateSplitTotal();
if (document.getElementById('isRankOnly')) {
    toggleRankOnly();
}
</script>
@endpush
@endsection
