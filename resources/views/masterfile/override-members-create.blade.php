@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.add_affiliate_partner_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.add_affiliate_partner_heading') }}</div>
    </div>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:16px; flex-shrink:0;">
        <form method="POST" action="{{ route('admin.masterfile.override-members.store') }}">
            @csrf
            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.full_name') }} <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.position_title_label') }} <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="position_title" value="{{ old('position_title') }}" required placeholder="{{ __('masterfile.position_title_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.company_vendor_label') }} <span style="color:#ef4444;">*</span></label>
                    <select name="vendor_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('masterfile.select_option') }}</option>
                        @foreach($vendors as $v)
                        <option value="{{ $v->vendor_id }}" {{ old('vendor_id') === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.organization_rewards_group_label') }} <span style="color:#ef4444;">*</span></label>
                    <select name="group_label_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('masterfile.select_option') }}</option>
                        @foreach($groupLabels as $g)
                        <option value="{{ $g->group_label_id }}" {{ old('group_label_id') === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('masterfile.group_business_note') }}</div>
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; box-sizing:border-box;">
                </div>
                <div style="grid-column:span 2; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:8px 10px;">
                    <label style="display:block; font-size:10px; font-weight:600; color:#92400e; margin-bottom:4px;">{{ __('masterfile.settlement_method_field_label') }} <span style="color:#ef4444;">*</span></label>
                    <div style="display:flex; gap:16px;">
                        <label style="display:flex; align-items:center; gap:5px; font-size:10.5px; color:#92400e; cursor:pointer;">
                            <input type="radio" name="settlement_method" value="DEDUCT_FROM_CLAIM" required {{ old('settlement_method') === 'DEDUCT_FROM_CLAIM' ? 'checked' : '' }}>
                            {{ __('masterfile.deduct_directly_claim') }}
                        </label>
                        <label style="display:flex; align-items:center; gap:5px; font-size:10.5px; color:#92400e; cursor:pointer;">
                            <input type="radio" name="settlement_method" value="CLAIM_BACK_REPORT" required {{ old('settlement_method') === 'CLAIM_BACK_REPORT' ? 'checked' : '' }}>
                            {{ __('masterfile.claim_back_report_only') }}
                        </label>
                    </div>
                    <div style="font-size:9px; color:#92400e; margin-top:4px;">{{ __('masterfile.settlement_method_note') }}</div>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:16px;">
                <a href="{{ route('admin.masterfile.override-members') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 28px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save_affiliate_partner_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
