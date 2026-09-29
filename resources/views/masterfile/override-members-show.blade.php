@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.affiliate_partner_label'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ $member->override_member_code }} — {{ $member->full_name }}</div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1.3fr; gap:10px; flex:1; min-height:0;">

        {{-- LEFT: Profile — 2 Aug 2026: rebuilt as a 2-column grid (was a
             single stacked column that overflowed and forced an internal
             scrollbar, hiding the Save button below the fold). Every
             field now fits in one screen, no scroll. --}}
        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px; overflow:hidden; display:flex; flex-direction:column;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px; flex-shrink:0;">{{ __('masterfile.profile_label') }}</div>
            <form method="POST" action="{{ route('admin.masterfile.override-members.update', $member->override_member_id) }}" style="flex:1; min-height:0; display:flex; flex-direction:column;">
                @csrf @method('PUT')
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:7px 10px; flex:1;">
                    <div>
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.full_name') }}</label>
                        <input type="text" name="full_name" value="{{ old('full_name', $member->full_name) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.position_title_label') }}</label>
                        <input type="text" name="position_title" value="{{ old('position_title', $member->position_title) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.company_vendor_label') }}</label>
                        <div style="background:#f3f4f6; border-radius:5px; padding:5px 7px; font-size:10.5px; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $member->vendor_name }} <span style="color:#9ca3af;">{{ __('masterfile.company_fixed_note') }}</span></div>
                    </div>
                    <div>
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.organization_rewards_group_label') }}</label>
                        <select name="group_label_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            @foreach(\DB::table('group_labels')->orderBy('group_name')->get() as $g)
                            <option value="{{ $g->group_label_id }}" {{ old('group_label_id', $member->group_label_id) === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }}</label>
                        <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="1" {{ old('is_active', $member->is_active) == 1 ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ old('is_active', $member->is_active) == 0 ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.email') }}</label>
                        <input type="email" name="email" value="{{ old('email', $member->email) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.phone') }}</label>
                        <input type="text" name="phone" value="{{ old('phone', $member->phone) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="grid-column:1 / -1; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:6px 9px;">
                        <label style="display:block; font-size:9.5px; font-weight:600; color:#92400e; margin-bottom:3px;">{{ __('masterfile.settlement_method_field_label') }}</label>
                        <div style="display:flex; gap:16px;">
                            <label style="display:flex; align-items:center; gap:4px; font-size:9.5px; color:#92400e; cursor:pointer;">
                                <input type="radio" name="settlement_method" value="DEDUCT_FROM_CLAIM" {{ old('settlement_method', $member->settlement_method) === 'DEDUCT_FROM_CLAIM' ? 'checked' : '' }}>
                                {{ __('masterfile.deduct_directly_claim') }}
                            </label>
                            <label style="display:flex; align-items:center; gap:4px; font-size:9.5px; color:#92400e; cursor:pointer;">
                                <input type="radio" name="settlement_method" value="CLAIM_BACK_REPORT" {{ old('settlement_method', $member->settlement_method) === 'CLAIM_BACK_REPORT' ? 'checked' : '' }}>
                                {{ __('masterfile.claim_back_report_only') }}
                            </label>
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:8px; flex-shrink:0; margin-top:8px;">
                    <a href="{{ route('admin.masterfile.override-members') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:10.5px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:10.5px; font-weight:600; cursor:pointer; flex:1;">{{ __('masterfile.save_profile_button') }}</button>
                </div>
            </form>
        </div>

        {{-- RIGHT: Per-product eligibility rules --}}
        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px; display:flex; flex-direction:column; min-height:0;">
            <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:4px;">{{ __('masterfile.per_product_eligibility_rules_title') }}</div>
            <div style="font-size:9.5px; color:#9ca3af; margin-bottom:8px;">{{ __('masterfile.per_product_eligibility_intro') }}</div>

            <div style="flex:1; overflow-y:auto; min-height:0; margin-bottom:8px;">
                @forelse($rules as $rule)
                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px; margin-bottom:6px;">
                    <div style="flex:1; font-size:10.5px; color:#374151;">
                        <strong>{{ $rule->product_name ?? __('masterfile.all_products_plain') }}</strong> —
                        @if($rule->criteria_type === 'PERCENTAGE')
                        {{ __('masterfile.pct_of_metric_label', ['pct' => rtrim(rtrim(number_format($rule->threshold_value, 2), '0'), '.'), 'metric' => $rule->sales_metric === 'PREMIUM' ? __('masterfile.premium_metric') : __('masterfile.earning_income_metric')]) }}{{ $rule->period_months ? __('masterfile.over_trailing_months_suffix', ['months' => $rule->period_months]) : '' }}
                        @elseif($rule->criteria_type === 'FIXED_AMOUNT')
                        {{ __('masterfile.flat_amount_label', ['amount' => number_format($rule->threshold_value, 2)]) }}{{ $rule->period_months ? __('masterfile.per_months_suffix', ['months' => $rule->period_months]) : '' }}
                        @elseif($rule->criteria_type === 'SALES_TARGET')
                        {{ __('masterfile.sales_target_qualify', ['amount' => number_format($rule->threshold_value, 2), 'metric' => $rule->sales_metric === 'PREMIUM' ? __('masterfile.premium_metric') : __('masterfile.earning_income_metric'), 'months' => $rule->period_months]) }}
                        @else
                        <span title="{{ $rule->custom_kpi_description }}">{{ __('masterfile.custom_kpi_prefix', ['desc' => \Illuminate\Support\Str::limit($rule->custom_kpi_description, 60)]) }}</span>
                        @endif
                        <br>
                        <span style="background:{{ $rule->is_active ? '#d1fae5' : '#f3f4f6' }}; color:{{ $rule->is_active ? '#065f46' : '#6b7280' }}; font-size:8.5px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ $rule->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.masterfile.override-members.rules.toggle', [$member->override_member_id, $rule->rule_id]) }}">
                        @csrf @method('PATCH')
                        <button type="submit" style="background:none; border:1px solid #d1d5db; border-radius:5px; color:#374151; font-size:9.5px; font-weight:600; cursor:pointer; padding:4px 8px; white-space:nowrap;">{{ $rule->is_active ? __('masterfile.deactivate_btn') : __('masterfile.activate_btn') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.masterfile.override-members.rules.destroy', [$member->override_member_id, $rule->rule_id]) }}" onsubmit="return confirm({{ json_encode(__('masterfile.remove_rule_confirm')) }});">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none; border:none; color:#dc2626; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.remove') }}</button>
                    </form>
                </div>
                @empty
                <div style="color:#9ca3af; font-size:10.5px; text-align:center; padding:20px 0;">{{ __('masterfile.no_eligibility_rules') }}</div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.masterfile.override-members.rules.store', $member->override_member_id) }}" style="flex-shrink:0; border-top:1px dashed #d1d5db; padding-top:10px;">
                @csrf
                <div style="font-size:10px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('masterfile.add_rule_heading') }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.product_label') }}</div>
                        <select name="product_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="">{{ __('masterfile.all_products_plain') }}</option>
                            @foreach($products as $p)
                            <option value="{{ $p->product_id }}">{{ $p->product_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.criteria_label') }}</div>
                        <select name="criteria_type" id="ovCriteria" onchange="ovToggleFields()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="PERCENTAGE">{{ __('masterfile.percentage_of_sales_option') }}</option>
                            <option value="FIXED_AMOUNT">{{ __('masterfile.fixed_rm_amount_option') }}</option>
                            <option value="SALES_TARGET">{{ __('masterfile.sales_target_kpi_option') }}</option>
                            <option value="CUSTOM_KPI">{{ __('masterfile.custom_kpi_manual_option') }}</option>
                        </select>
                    </div>
                </div>
                <div id="ovStructuredFields" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.value_label') }}</div>
                        <input type="number" name="threshold_value" min="0" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.based_on_label') }}</div>
                        <select name="sales_metric" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="EARNING_INCOME">{{ __('masterfile.earning_income_metric') }}</option>
                            <option value="PREMIUM">{{ __('masterfile.premium_metric') }}</option>
                        </select>
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.period_months_field_label') }}</div>
                        <input type="number" name="period_months" value="12" min="1" max="60" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div id="ovCustomField" style="display:none; margin-bottom:8px;">
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.describe_kpi_label') }}</div>
                    <textarea name="custom_kpi_description" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;"></textarea>
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.add_rule_heading') }}</button>
            </form>
        </div>
    </div>
</div>

<script>
function ovToggleFields() {
    var val = document.getElementById('ovCriteria').value;
    document.getElementById('ovStructuredFields').style.display = (val === 'CUSTOM_KPI') ? 'none' : 'grid';
    document.getElementById('ovCustomField').style.display = (val === 'CUSTOM_KPI') ? 'block' : 'none';
}
</script>
@endsection
