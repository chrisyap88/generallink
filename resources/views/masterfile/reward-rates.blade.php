@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.reward_rates_title'))

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

    <div style="display:grid; grid-template-columns:1fr 340px; gap:8px; flex:1; min-height:0;">

        {{-- LEFT: Filter + List --}}
        <div style="display:flex; flex-direction:column; gap:8px; min-height:0;">

            {{-- Filter Bar --}}
            <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex-shrink:0;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">🔍 {{ __('masterfile.filter_reward_rates') }}</div>
                <form method="GET" action="{{ route('admin.masterfile.reward-rates') }}">
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr auto auto; gap:8px; align-items:end;">
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">{{ __('masterfile.vendor_label') }}</label>
                            <select name="vendor_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                                <option value="">{{ __('masterfile.all_vendors') }}</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->vendor_id }}" {{ request('vendor_id') === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">{{ __('masterfile.product_label') }}</label>
                            <select name="product_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                                <option value="">{{ __('masterfile.all_products') }}</option>
                                @foreach($products as $p)
                                <option value="{{ $p->product_id }}" {{ request('product_id') === $p->product_id ? 'selected' : '' }}>{{ $p->product_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:10px; font-weight:600; color:#6b7280; margin-bottom:3px;">{{ __('masterfile.status') }}</label>
                            <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                                <option value="">{{ __('masterfile.all_option') }}</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                            </select>
                        </div>
                        <button type="submit"
                            style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">
                            🔍 {{ __('masterfile.filter_btn') }}
                        </button>
                        <a href="{{ route('admin.masterfile.reward-rates') }}"
                            style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:500; white-space:nowrap; text-align:center;">
                            {{ __('masterfile.clear_btn') }}
                        </a>
                    </div>
                </form>
            </div>

            {{-- List --}}
            <div style="background:#fff; border-radius:10px; padding:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column;">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px; flex-shrink:0;">
                    ⭐ {{ __('masterfile.reward_points_rates_heading') }}
                    @if(isset($rates) && $rates->count() > 0)
                    <span style="background:#e0f2fe; color:#0369a1; font-size:10px; padding:1px 8px; border-radius:20px; margin-left:6px;">{{ __('masterfile.found_badge', ['count' => $rates->count()]) }}</span>
                    @endif
                </div>

                @if(!isset($rates) || $rates->isEmpty())
                <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
                    @if(!request()->hasAny(['vendor_id','product_id','status']))
                        <div>
                            <div style="font-size:32px; margin-bottom:8px;">🔍</div>
                            <div>{{ __('masterfile.use_filter_prompt') }}</div>
                            <div style="font-size:10px; margin-top:4px;">{{ __('masterfile.add_rate_hint') }}</div>
                        </div>
                    @else
                        <div>{{ __('masterfile.no_rates_found') }}</div>
                    @endif
                </div>
                @else
                <div style="flex:1; overflow-y:auto; min-height:0;">
                    <table style="width:100%; border-collapse:collapse; font-size:11px;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe;">
                                <th style="text-align:left; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.vendor_label') }}</th>
                                <th style="text-align:left; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.product_label') }}</th>
                                <th style="text-align:center; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.col_points_per_rm') }}</th>
                                <th style="text-align:center; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.valid_from_label') }}</th>
                                <th style="text-align:center; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.valid_to_label') }}</th>
                                <th style="text-align:center; padding:8px 10px; color:#374151; font-weight:600;">{{ __('masterfile.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rates as $rate)
                            <tr style="border-bottom:1px solid #f3f4f6; {{ $loop->even ? 'background:#fafafa;' : '' }}">
                                <td style="padding:8px 10px; color:#374151;">{{ $rate->vendor_name ?? __('masterfile.all_vendors') }}</td>
                                <td style="padding:8px 10px; color:#374151;">{{ $rate->product_name ?? __('masterfile.all_products') }}</td>
                                <td style="padding:8px 10px; text-align:center; font-weight:700; color:#d97706;">{{ __('masterfile.pts_value', ['value' => number_format($rate->points_per_rm, 4)]) }}</td>
                                <td style="padding:8px 10px; text-align:center; font-size:10px;">{{ \Carbon\Carbon::parse($rate->valid_from)->format('d M Y') }}</td>
                                <td style="padding:8px 10px; text-align:center; font-size:10px;">{{ $rate->valid_to ? \Carbon\Carbon::parse($rate->valid_to)->format('d M Y') : '—' }}</td>
                                <td style="padding:8px 10px; text-align:center;">
                                    @if($rate->is_active)
                                        <span style="background:#d1fae5; color:#065f46; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.active') }}</span>
                                    @else
                                        <span style="background:#fee2e2; color:#991b1b; font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.inactive') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        {{-- RIGHT: Add Reward Rate Form --}}
        <div style="background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; min-height:0; overflow-y:auto;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:12px; padding-bottom:6px; border-bottom:2px solid #e0f2fe;">
                ➕ {{ __('masterfile.add_reward_rate') }}
            </div>

            <form method="POST" action="{{ route('admin.masterfile.reward-rates.store') }}" style="display:flex; flex-direction:column; gap:10px;">
                @csrf

                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.vendor_label') }}</label>
                    <select name="vendor_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.all_vendors_global') }}</option>
                        @foreach($vendors as $v)
                        <option value="{{ $v->vendor_id }}" {{ old('vendor_id') === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.leave_blank_vendors') }}</div>
                </div>

                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.product_label') }}</label>
                    <select name="product_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.all_products_global') }}</option>
                        @foreach($products as $p)
                        <option value="{{ $p->product_id }}" {{ old('product_id') === $p->product_id ? 'selected' : '' }}>{{ $p->product_name }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.leave_blank_products') }}</div>
                </div>

                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.points_per_rm_label') }} <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="points_per_rm" value="{{ old('points_per_rm') }}" required step="0.0001" min="0.0001" placeholder="e.g. 1.0000"
                        style="width:100%; border:1px solid {{ $errors->has('points_per_rm') ? '#f87171' : '#d1d5db' }}; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                    <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.points_per_rm_hint') }}</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.valid_from_label') }} <span style="color:#dc2626;">*</span></label>
                        <input type="date" name="valid_from" value="{{ old('valid_from') }}" required
                            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.valid_to_label') }}</label>
                        <input type="date" name="valid_to" value="{{ old('valid_to') }}"
                            style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                        <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.leave_blank_expiry') }}</div>
                    </div>
                </div>

                <div style="background:#fef9c3; border-radius:8px; padding:10px; font-size:10px; color:#854d0e; border:1px solid #fef08a;">
                    ⚠️ {{ __('masterfile.global_rate_warning') }}
                </div>

                <button type="submit"
                    style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:10px 16px; font-size:12px; font-weight:600; cursor:pointer; width:100%; margin-top:4px;">
                    ➕ {{ __('masterfile.add_reward_rate') }}
                </button>
            </form>
        </div>

    </div>
</div>
@endsection
