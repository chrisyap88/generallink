@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.promotion_rules_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="flex-shrink:0;">
    </div>

    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
        <form method="GET" action="{{ route('admin.masterfile.promotion-rules') }}" style="display:flex; align-items:center; gap:8px;">
            <label style="font-size:10.5px; font-weight:600; color:#374151;">{{ __('masterfile.rules_for_label') }}</label>
            <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; background:#fff;">
                <option value="" {{ !$groupLabelId ? 'selected' : '' }}>{{ __('masterfile.system_default_option') }}</option>
                @foreach($groupLabels as $g)
                <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                @endforeach
            </select>
        </form>
        <div style="font-size:9.5px; color:#9ca3af;">{{ __('masterfile.promotion_rules_intro') }}</div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; flex:1; min-height:0;">
        @foreach($transitions as $toRole => $fromRole)
        <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 14px; display:flex; flex-direction:column; min-height:0;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; flex-shrink:0;">
                <div style="font-size:12px; font-weight:700; color:#1565C0;">{{ \App\Services\RoleLabelService::label($fromRole) }} → {{ \App\Services\RoleLabelService::label($toRole) }}</div>
                @if($usingDefaultFor[$toRole])
                <span style="background:#f3f4f6; color:#6b7280; font-size:9px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.following_system_default') }}</span>
                @else
                <span style="background:#e0f2fe; color:#0369a1; font-size:9px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.own_rules') }}</span>
                @endif
            </div>

            <div style="flex:1; overflow-y:auto; min-height:0; margin-bottom:8px;">
                @forelse($rulesByTransition[$toRole] as $rule)
                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:6px; padding:6px 8px; margin-bottom:5px;">
                    <div style="flex:1; font-size:10.5px; color:#374151;">
                        @if($rule->criteria_type === 'RECRUIT_COUNT')
                        <strong>{{ __('masterfile.criteria_recruit_count') }}</strong> — {{ __('masterfile.criteria_at_least_count', ['value' => rtrim(rtrim(number_format($rule->threshold_value, 2), '0'), '.')]) }}
                        @elseif($rule->criteria_type === 'SALES_VOLUME')
                        <strong>{{ __('masterfile.criteria_sales_volume') }}</strong> — {{ __('masterfile.criteria_sales_detail', ['amount' => number_format($rule->threshold_value, 2), 'metric' => $rule->sales_metric === 'PREMIUM' ? __('masterfile.premium_metric') : __('masterfile.earning_income_metric'), 'months' => $rule->sales_period_months]) }}
                        @else
                        <strong>{{ __('masterfile.criteria_min_tenure') }}</strong> — {{ __('masterfile.criteria_tenure_detail', ['value' => rtrim(rtrim(number_format($rule->threshold_value, 2), '0'), '.')]) }}
                        @endif
                    </div>
                    @if(!$usingDefaultFor[$toRole])
                    <form method="POST" action="{{ route('admin.masterfile.promotion-rules.destroy', $rule->rule_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.remove_rule_confirm')) }});">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none; border:none; color:#dc2626; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.remove') }}</button>
                    </form>
                    @endif
                </div>
                @empty
                <div style="color:#9ca3af; font-size:10.5px; text-align:center; padding:10px 0;">{{ __('masterfile.no_rules_step') }}</div>
                @endforelse
            </div>

            @if(count($rulesByTransition[$toRole]) >= 2)
            <div style="flex-shrink:0; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:6px 8px; margin-bottom:8px;">
                <div style="font-size:9px; font-weight:700; color:#92400e; margin-bottom:4px;">{{ __('masterfile.combine_logic_question') }}</div>
                @if($usingDefaultFor[$toRole])
                <div style="font-size:9.5px; color:#92400e;">{{ __('masterfile.following_default_logic', ['logic' => $combineLogicFor[$toRole] === 'OR' ? __('masterfile.any_one_rule_or') : __('masterfile.all_rules_and')]) }}</div>
                @else
                <form method="POST" action="{{ route('admin.masterfile.promotion-rules.logic') }}" style="display:flex; gap:12px;">
                    @csrf @method('PUT')
                    <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">
                    <input type="hidden" name="to_role" value="{{ $toRole }}">
                    <label style="display:flex; align-items:center; gap:4px; font-size:10px; color:#92400e; cursor:pointer;">
                        <input type="radio" name="combine_logic" value="AND" onchange="this.form.submit()" {{ $combineLogicFor[$toRole] === 'AND' ? 'checked' : '' }}>
                        {{ __('masterfile.must_meet_all_and') }}
                    </label>
                    <label style="display:flex; align-items:center; gap:4px; font-size:10px; color:#92400e; cursor:pointer;">
                        <input type="radio" name="combine_logic" value="OR" onchange="this.form.submit()" {{ $combineLogicFor[$toRole] === 'OR' ? 'checked' : '' }}>
                        {{ __('masterfile.any_one_enough_or') }}
                    </label>
                </form>
                @endif
            </div>
            @endif

            <form method="POST" action="{{ route('admin.masterfile.promotion-rules.store') }}" style="flex-shrink:0; border-top:1px dashed #d1d5db; padding-top:8px;" onsubmit="return validateRuleForm(this);">
                @csrf
                <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">
                <input type="hidden" name="to_role" value="{{ $toRole }}">
                <div style="display:grid; grid-template-columns:1.3fr 1fr auto; gap:6px; align-items:end;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.metric_label') }}</div>
                        <select name="criteria_type" class="critSelect" data-target="fields_{{ $toRole }}" onchange="toggleSalesFields('{{ $toRole }}')" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="RECRUIT_COUNT">{{ __('masterfile.criteria_recruit_count') }}</option>
                            <option value="SALES_VOLUME">{{ __('masterfile.criteria_sales_volume') }}</option>
                            <option value="TENURE_MONTHS">{{ __('masterfile.tenure_months_option') }}</option>
                        </select>
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.at_least_label') }}</div>
                        <input type="number" name="threshold" required min="0" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 12px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.add_button') }}</button>
                </div>
                <div id="fields_{{ $toRole }}" style="display:none; grid-template-columns:1fr 1fr; gap:6px; margin-top:6px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.based_on_label') }}</div>
                        <select name="sales_metric" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="EARNING_INCOME">{{ __('masterfile.earning_income_metric') }}</option>
                            <option value="PREMIUM">{{ __('masterfile.premium_metric') }}</option>
                        </select>
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.over_months_label') }}</div>
                        <input type="number" name="sales_period" value="12" min="1" max="60" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
            </form>
        </div>
        @endforeach
    </div>

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.dashboard') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
    </div>

</div>

<script>
function toggleSalesFields(toRole) {
    var select = document.querySelector('select.critSelect[data-target="fields_' + toRole + '"]');
    var box = document.getElementById('fields_' + toRole);
    box.style.display = (select.value === 'SALES_VOLUME') ? 'grid' : 'none';
}
function validateRuleForm(form) {
    return true;
}
</script>
@endsection
