@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.vendors_title'))

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

    <div style="display:grid; grid-template-columns:1fr 300px; gap:8px; flex:1; min-height:0;">

        {{-- LEFT: Search + Vendor List --}}
        <div style="display:flex; flex-direction:column; gap:8px; min-height:0;">

            {{-- Single Search Bar --}}
            <form method="GET" action="{{ route('admin.masterfile.vendors') }}">
                <div style="background:#fff; border-radius:10px; padding:10px 12px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; gap:8px; align-items:center;">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('masterfile.vendor_search_placeholder') }}"
                        style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:8px 12px; font-size:12px; outline:none; box-sizing:border-box;">
                    <select name="status" style="border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:12px; outline:none; background:#fff; min-width:100px;">
                        <option value="">{{ __('masterfile.all_status') }}</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                    </select>
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;">
                        🔍 {{ __('masterfile.search') }}
                    </button>
                    <a href="{{ route('admin.masterfile.vendors') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px 14px; font-size:12px; font-weight:500; white-space:nowrap;">
                        {{ __('masterfile.clear_btn') }}
                    </a>
                </div>
            </form>

            {{-- Vendor List --}}
            <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column;">

                @if(!isset($vendors) || $vendors->isEmpty())
                <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
                    @if(!request()->hasAny(['search','status']))
                    <div>
                        <div style="font-size:32px; margin-bottom:8px;">🏢</div>
                        <div style="font-weight:600; color:#374151; margin-bottom:4px;">{{ __('masterfile.search_for_vendors') }}</div>
                        <div style="font-size:11px;">{{ __('masterfile.vendor_search_hint') }}</div>
                    </div>
                    @else
                    <div>
                        <div style="font-size:28px; margin-bottom:8px;">🔍</div>
                        <div>{{ __('masterfile.no_vendors_found') }}</div>
                    </div>
                    @endif
                </div>
                @else
                <div style="flex:1; overflow-y:auto; min-height:0;">
                    <table style="width:100%; border-collapse:collapse; font-size:11px;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0;">
                                <th style="text-align:left; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.vendor_name_label') }}</th>
                                <th style="text-align:left; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.col_code') }}</th>
                                <th style="text-align:left; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.pic_label') }}</th>
                                <th style="text-align:left; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.phone') }}</th>
                                <th style="text-align:left; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.email') }}</th>
                                <th style="text-align:center; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.status') }}</th>
                                <th style="text-align:center; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.col_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vendors as $vendor)
                            <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                                <td style="padding:8px 10px; font-weight:600; color:#111827;">{{ $vendor->vendor_name }}</td>
                                <td style="padding:8px 10px; color:#6b7280; font-family:monospace;">{{ $vendor->vendor_code }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $vendor->pic_name ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $vendor->vendor_phone ?: '—' }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $vendor->vendor_email ?: '—' }}</td>
                                <td style="padding:8px 10px; text-align:center;">
                                    @if($vendor->is_active)
                                        <span style="background:#d1fae5; color:#065f46; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.active') }}</span>
                                    @else
                                        <span style="background:#fee2e2; color:#991b1b; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.inactive') }}</span>
                                    @endif
                                </td>
                                <td style="padding:8px 10px; text-align:center;">
                                    <form method="POST" action="{{ route('admin.masterfile.vendor.toggle', $vendor->vendor_id) }}" style="display:inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" style="background:{{ $vendor->is_active ? '#fee2e2' : '#d1fae5' }}; color:{{ $vendor->is_active ? '#991b1b' : '#065f46' }}; border:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer;">
                                            {{ $vendor->is_active ? __('masterfile.deactivate_btn') : __('masterfile.activate_btn') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bottom: record count + pagination --}}
                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid #f3f4f6; flex-shrink:0; margin-top:6px;">
                    <div style="font-size:11px; color:#6b7280;">
                        @if(method_exists($vendors, 'total'))
                            {{ __('masterfile.showing_found', ['first' => $vendors->firstItem(), 'last' => $vendors->lastItem(), 'total' => $vendors->total()]) }}
                        @else
                            {{ __('masterfile.records_found_count', ['count' => $vendors->count()]) }}
                        @endif
                    </div>
                    @if(method_exists($vendors, 'lastPage') && $vendors->lastPage() > 1)
                    <div style="display:flex; gap:6px; align-items:center;">
                        @if($vendors->onFirstPage())
                            <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:4px 12px; font-size:11px;">{{ __('masterfile.prev') }}</span>
                        @else
                            <a href="{{ $vendors->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 12px; font-size:11px;">{{ __('masterfile.prev') }}</a>
                        @endif
                        <span style="font-size:11px; color:#374151;">{{ __('masterfile.page_of', ['current' => $vendors->currentPage(), 'last' => $vendors->lastPage()]) }}</span>
                        @if($vendors->hasMorePages())
                            <a href="{{ $vendors->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 12px; font-size:11px;">{{ __('masterfile.next') }}</a>
                        @else
                            <span style="background:#f3f4f6; color:#9ca3af; border-radius:6px; padding:4px 12px; font-size:11px;">{{ __('masterfile.next') }}</span>
                        @endif
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- RIGHT: Add Vendor Form --}}
        <div style="background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow-y:auto;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:12px; padding-bottom:6px; border-bottom:2px solid #e0f2fe;">
                ➕ {{ __('masterfile.add_new_vendor') }}
            </div>
            <form method="POST" action="{{ route('admin.masterfile.vendors.store') }}" style="display:flex; flex-direction:column; gap:10px;">
                @csrf
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.vendor_name_label') }} <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="vendor_name" value="{{ old('vendor_name') }}" required placeholder="{{ __('masterfile.placeholder_vendor_name_example') }}"
                        style="width:100%; border:1px solid {{ $errors->has('vendor_name') ? '#f87171' : '#d1d5db' }}; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.vendor_code_label') }} <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="vendor_code" value="{{ old('vendor_code') }}" required placeholder="{{ __('masterfile.placeholder_vendor_code_example') }}"
                        style="width:100%; border:1px solid {{ $errors->has('vendor_code') ? '#f87171' : '#d1d5db' }}; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box; text-transform:uppercase;">
                    <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.group_code_hint') }}</div>
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.pic_name_label') }}</label>
                    <input type="text" name="pic_name" value="{{ old('pic_name') }}" placeholder="{{ __('masterfile.placeholder_person_in_charge') }}"
                        style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.phone') }}</label>
                    <input type="text" name="vendor_phone" value="{{ old('vendor_phone') }}" placeholder="+603-XXXX XXXX"
                        style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.email') }}</label>
                    <input type="email" name="vendor_email" value="{{ old('vendor_email') }}" placeholder="vendor@example.com"
                        style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.address_label') }}</label>
                    <textarea name="vendor_address" rows="2" placeholder="{{ __('masterfile.placeholder_vendor_address') }}"
                        style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box; resize:none;">{{ old('vendor_address') }}</textarea>
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:10px 16px; font-size:12px; font-weight:600; cursor:pointer; width:100%;">
                    ➕ {{ __('masterfile.add_vendor_btn') }}
                </button>
            </form>
        </div>

    </div>
</div>
@endsection
