@extends('layouts.dashboard')

@section('page-title', 'Master File — Vendors')

@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:6px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">
        ✅ {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:6px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">
        @foreach($errors->all() as $error) ⚠ {{ $error }}<br> @endforeach
    </div>
    @endif

    {{-- VENDOR SECTION --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        {{-- Tab Header --}}
        <div style="display:flex; border-bottom:2px solid #e0f2fe; background:#f8fafc; flex-shrink:0;">
            <a href="{{ route('admin.vendors.index', ['mode'=>'add']) }}"
               style="padding:10px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $mode==='add' ? '#1565C0' : 'transparent' }}; color:{{ $mode==='add' ? '#1565C0' : '#6b7280' }}; white-space:nowrap;">
                ➕ Add Vendor
            </a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit']) }}"
               style="padding:10px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $mode==='edit' ? '#1565C0' : 'transparent' }}; color:{{ $mode==='edit' ? '#1565C0' : '#6b7280' }}; white-space:nowrap;">
                ✏️ Search to Edit
            </a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'search']) }}"
               style="padding:10px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $mode==='search' ? '#1565C0' : 'transparent' }}; color:{{ $mode==='search' ? '#1565C0' : '#6b7280' }}; white-space:nowrap;">
                🔍 Search All
            </a>
        </div>

        {{-- Tab Content --}}
        <div style="flex:1; overflow-y:auto; padding:14px;">

            {{-- ── ADD MODE ── --}}
            @if($mode === 'add')
            <form method="POST" action="{{ route('admin.vendors.store') }}">
                @csrf
                @include('masterfile.partials.vendor-form', ['vendor' => null, 'states' => $states, 'submitLabel' => '➕ Add Vendor', 'showStatus' => false])
            </form>

            {{-- ── SEARCH TO EDIT MODE ── --}}
            @elseif($mode === 'edit')
            <div style="margin-bottom:12px;">
                <div style="font-size:11px; font-weight:600; color:#374151; margin-bottom:6px;">Type to find vendor:</div>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="vendorSearchInput"
                        placeholder="Search by name, code, city, state, PIC..."
                        style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 12px; font-size:12px; outline:none;"
                        oninput="searchVendors(this.value)">
                </div>
                <div id="vendorDropdown" style="display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-shadow:0 4px 12px rgba(0,0,0,.1); max-height:200px; overflow-y:auto; z-index:100; position:relative;"></div>
            </div>

            @if($selectedVendor)
            <form method="POST" action="{{ route('admin.vendors.update', $selectedVendor->vendor_id) }}">
                @csrf
                @method('PUT')
                @include('masterfile.partials.vendor-form', ['vendor' => $selectedVendor, 'states' => $states, 'submitLabel' => '💾 Update Vendor', 'showStatus' => true])
            </form>
            @else
            <div style="text-align:center; padding:30px; color:#9ca3af; font-size:12px;">
                <div style="font-size:28px; margin-bottom:8px;">🔍</div>
                Search for a vendor above to load its details for editing.
            </div>
            @endif

            {{-- ── SEARCH ALL MODE ── --}}
            @elseif($mode === 'search')
            <form method="GET" action="{{ route('admin.vendors.index') }}">
                <input type="hidden" name="mode" value="search">

                {{-- Search Fields - All vendor fields --}}
                <div style="background:#f8fafc; border-radius:8px; padding:12px; margin-bottom:12px; border:1px solid #e0f2fe;">
                    <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:10px;">🔍 Search Criteria — fill any field or combination</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px;">
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Vendor Name</label>
                            <input type="text" name="s_vendor_name" value="{{ request('s_vendor_name') }}" placeholder="Any part of name"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Vendor Code</label>
                            <input type="text" name="s_vendor_code" value="{{ request('s_vendor_code') }}" placeholder="e.g. ALZ"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Office Phone</label>
                            <input type="text" name="s_phone" value="{{ request('s_phone') }}" placeholder="e.g. 012, +603"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Email</label>
                            <input type="text" name="s_email" value="{{ request('s_email') }}" placeholder="Any part of email"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Website</label>
                            <input type="text" name="s_website" value="{{ request('s_website') }}" placeholder="Any part of website"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Address</label>
                            <input type="text" name="s_address" value="{{ request('s_address') }}" placeholder="Any part of address"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Postcode</label>
                            <input type="text" name="s_postcode" value="{{ request('s_postcode') }}" placeholder="e.g. 41050"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">City</label>
                            <input type="text" name="s_city" value="{{ request('s_city') }}" placeholder="e.g. Klang"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">State</label>
                            <select name="s_state" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                                <option value="">— All States —</option>
                                @foreach($states as $state)
                                <option value="{{ $state }}" {{ request('s_state') === $state ? 'selected' : '' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">PIC Name</label>
                            <input type="text" name="s_pic_name" value="{{ request('s_pic_name') }}" placeholder="e.g. Teow"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">PIC Phone</label>
                            <input type="text" name="s_pic_phone" value="{{ request('s_pic_phone') }}" placeholder="e.g. 012"
                                style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">Status</label>
                            <select name="s_status" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                                <option value="">— All —</option>
                                <option value="1" {{ request('s_status')==='1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ request('s_status')==='0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:10px; display:flex; gap:8px;">
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 24px; font-size:12px; font-weight:600; cursor:pointer;">
                            🔍 Search
                        </button>
                        <a href="{{ route('admin.vendors.index', ['mode'=>'search']) }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px 16px; font-size:12px; font-weight:500;">
                            Clear
                        </a>
                    </div>
                </div>

                {{-- Results --}}
                @if($vendors->isNotEmpty())
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:11px;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe;">
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Vendor Name</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Code</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Phone</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Email</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Postcode</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">City</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">State</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">PIC Name</th>
                                <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">PIC Phone</th>
                                <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151;">Status</th>
                                <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vendors as $v)
                            <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                                <td style="padding:8px 10px; font-weight:600; color:#111827;">{{ $v->vendor_name }}</td>
                                <td style="padding:8px 10px; font-family:monospace; color:#6b7280;">{{ $v->vendor_code }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $v->vendor_office_phone ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $v->vendor_email ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $v->vendor_postcode ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $v->vendor_city ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $v->vendor_state ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $v->pic_name ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $v->pic_phone ?: '—' }}</td>
                                <td style="padding:8px 10px; text-align:center;">
                                    <span style="background:{{ $v->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $v->is_active ? '#065f46' : '#991b1b' }}; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">
                                        {{ $v->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td style="padding:8px 10px; text-align:center;">
                                    <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$v->vendor_id]) }}"
                                       style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600;">
                                        ✏️ Edit
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Record count + pagination --}}
                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid #f3f4f6; margin-top:6px;">
                    <div style="font-size:11px; color:#6b7280;">
                        Showing {{ $vendors->firstItem() }}–{{ $vendors->lastItem() }} of <strong>{{ $vendors->total() }}</strong> records found
                    </div>
                    @if($vendors->lastPage() > 1)
                    <div style="display:flex; gap:6px; align-items:center;">
                        @if($vendors->onFirstPage())
                            <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:4px 12px; font-size:11px;">← Prev</span>
                        @else
                            <a href="{{ $vendors->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 12px; font-size:11px;">← Prev</a>
                        @endif
                        <span style="font-size:11px; color:#374151;">Page {{ $vendors->currentPage() }} / {{ $vendors->lastPage() }}</span>
                        @if($vendors->hasMorePages())
                            <a href="{{ $vendors->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 12px; font-size:11px;">Next →</a>
                        @else
                            <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:4px 12px; font-size:11px;">Next →</span>
                        @endif
                    </div>
                    @endif
                </div>
                @elseif(request()->hasAny(['s_vendor_name','s_vendor_code','s_phone','s_email','s_website','s_address','s_postcode','s_city','s_state','s_pic_name','s_pic_phone','s_status']))
                <div style="text-align:center; padding:20px; color:#9ca3af; font-size:12px;">No vendors found matching your search criteria.</div>
                @endif
            </form>
            @endif

        </div>
    </div>

    {{-- BRANCH SECTION --}}
    @if($selectedVendor)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:flex; border-bottom:2px solid #e0f2fe; background:#f8fafc; align-items:center; flex-shrink:0;">
            <div style="padding:10px 14px; font-size:12px; font-weight:700; color:#1565C0; border-right:1px solid #e0f2fe;">
                🏢 Branches — {{ $selectedVendor->vendor_name }}
            </div>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'add']) }}"
               style="padding:10px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $branchMode==='add' ? '#1565C0' : 'transparent' }}; color:{{ $branchMode==='add' ? '#1565C0' : '#6b7280' }};">
                ➕ Add Branch
            </a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'edit']) }}"
               style="padding:10px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $branchMode==='edit' ? '#1565C0' : 'transparent' }}; color:{{ $branchMode==='edit' ? '#1565C0' : '#6b7280' }};">
                ✏️ Search to Edit
            </a>
            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'search_all']) }}"
               style="padding:10px 20px; font-size:12px; font-weight:600; text-decoration:none; border-bottom:3px solid {{ $branchMode==='search_all' ? '#1565C0' : 'transparent' }}; color:{{ $branchMode==='search_all' ? '#1565C0' : '#6b7280' }};">
                🔍 Search All
            </a>
        </div>

        <div style="flex:1; overflow-y:auto; padding:14px;">
            @if($branchMode === 'add')
            <form method="POST" action="{{ route('admin.vendors.branches.store', $selectedVendor->vendor_id) }}">
                @csrf
                <input type="hidden" name="vendor_id" value="{{ $selectedVendor->vendor_id }}">
                @include('masterfile.partials.branch-form', ['branch' => null, 'states' => $states, 'submitLabel' => '➕ Add Branch', 'showStatus' => false])
            </form>

            @elseif($branchMode === 'edit')
            <div style="margin-bottom:12px;">
                <input type="text" id="branchSearchInput" placeholder="Search branch by name, code, city..."
                    style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 12px; font-size:12px; outline:none;"
                    oninput="searchBranches(this.value, '{{ $selectedVendor->vendor_id }}')">
                <div id="branchDropdown" style="display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; margin-top:4px; box-shadow:0 4px 12px rgba(0,0,0,.1); max-height:200px; overflow-y:auto; position:relative; z-index:100;"></div>
            </div>
            @if($selectedBranch)
            <form method="POST" action="{{ route('admin.vendors.branches.update', $selectedBranch->branch_id) }}">
                @csrf
                @method('PUT')
                @include('masterfile.partials.branch-form', ['branch' => $selectedBranch, 'states' => $states, 'submitLabel' => '💾 Update Branch', 'showStatus' => true])
            </form>
            @endif

            @elseif($branchMode === 'search_all')
            <form method="GET" action="{{ route('admin.vendors.index') }}" style="margin-bottom:12px;">
                <input type="hidden" name="mode" value="edit">
                <input type="hidden" name="vendor_id" value="{{ $selectedVendor->vendor_id }}">
                <input type="hidden" name="branch_mode" value="search_all">
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" name="branch_search" value="{{ request('branch_search') }}"
                        placeholder="Search branches by name, code, address, city, state, PIC..."
                        style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 12px; font-size:12px; outline:none;">
                    <select name="branch_status" style="border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:12px; outline:none; background:#fff;">
                        <option value="">All Status</option>
                        <option value="1" {{ request('branch_status')==='1'?'selected':'' }}>Active</option>
                        <option value="0" {{ request('branch_status')==='0'?'selected':'' }}>Inactive</option>
                    </select>
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer;">🔍 Search</button>
                </div>
            </form>
            @endif

            {{-- Branch Table --}}
            @if($branches->isNotEmpty())
            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe;">
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Branch Name</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Code</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Address</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Postcode</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">City</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">State</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Phone</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">Email</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">PIC Name</th>
                        <th style="text-align:left; padding:8px 10px; font-weight:600; color:#374151;">PIC Phone</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151;">Status</th>
                        <th style="text-align:center; padding:8px 10px; font-weight:600; color:#374151;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($branches as $b)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                        <td style="padding:8px 10px; font-weight:600; color:#111827;">{{ $b->branch_name }}</td>
                        <td style="padding:8px 10px; font-family:monospace; color:#6b7280;">{{ $b->branch_code }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->address ?: '—' }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->postcode ?: '—' }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->city ?: '—' }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->state ?: '—' }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->phone ?: '—' }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->email ?: '—' }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->pic_name ?: '—' }}</td>
                        <td style="padding:8px 10px; color:#374151;">{{ $b->pic_phone ?: '—' }}</td>
                        <td style="padding:8px 10px; text-align:center;">
                            <span style="background:{{ $b->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $b->is_active ? '#065f46' : '#991b1b' }}; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">
                                {{ $b->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td style="padding:8px 10px; text-align:center;">
                            <a href="{{ route('admin.vendors.index', ['mode'=>'edit', 'vendor_id'=>$selectedVendor->vendor_id, 'branch_mode'=>'edit', 'branch_id'=>$b->branch_id]) }}"
                               style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600;">
                                ✏️ Edit
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="font-size:11px; color:#6b7280; padding-top:8px; border-top:1px solid #f3f4f6; margin-top:6px;">
                <strong>{{ $branches->total() }}</strong> branch(es) found
            </div>
            @else
            <div style="text-align:center; padding:20px; color:#9ca3af; font-size:12px;">
                No branches found. Add the first branch using ➕ Add Branch tab.
            </div>
            @endif
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
let vendorSearchTimeout;
function searchVendors(query) {
    clearTimeout(vendorSearchTimeout);
    const dropdown = document.getElementById('vendorDropdown');
    if (query.length < 2) { dropdown.style.display = 'none'; return; }
    vendorSearchTimeout = setTimeout(() => {
        fetch(`{{ route('admin.vendors.search-edit') }}?search=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.length) { dropdown.style.display = 'none'; return; }
                dropdown.innerHTML = data.map(v =>
                    `<div onclick="selectVendor('${v.vendor_id}')"
                        style="padding:8px 12px; cursor:pointer; font-size:12px; border-bottom:1px solid #f3f4f6;"
                        onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='#fff'">
                        <strong>${v.vendor_name}</strong> <span style="color:#6b7280; font-size:10px;">${v.vendor_code} — ${v.vendor_city||''} ${v.vendor_state||''}</span>
                    </div>`
                ).join('');
                dropdown.style.display = 'block';
            });
    }, 300);
}
function selectVendor(vendorId) {
    window.location.href = `{{ route('admin.vendors.index') }}?mode=edit&vendor_id=${vendorId}`;
}
</script>
@endpush
@endsection
