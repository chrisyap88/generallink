@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.rank_promotion_rules_title'))
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

    <div style="font-size:9.5px; color:#9ca3af; flex-shrink:0;">
        {{ __('masterfile.rank_promotion_rules_intro') }}
    </div>

    <form method="GET" action="{{ route('admin.masterfile.rank-promotion-rules') }}" style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
        <label style="font-size:10.5px; font-weight:600; color:#374151;">{{ __('masterfile.role_colon_label') }}</label>
        <select name="role" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; background:#fff;">
            <option value="GROUP_LEADER" {{ $role === 'GROUP_LEADER' ? 'selected' : '' }}>{{ \App\Services\RoleLabelService::label('GROUP_LEADER', $groupId) }}</option>
            <option value="TEAM_LEADER" {{ $role === 'TEAM_LEADER' ? 'selected' : '' }}>{{ \App\Services\RoleLabelService::label('TEAM_LEADER', $groupId) }}</option>
            <option value="INTRODUCER" {{ $role === 'INTRODUCER' ? 'selected' : '' }}>{{ \App\Services\RoleLabelService::label('INTRODUCER', $groupId) }}</option>
        </select>

        <label style="font-size:10.5px; font-weight:600; color:#374151;">{{ __('masterfile.group_colon_label') }}</label>
        <select name="group_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; background:#fff;">
            <option value="" {{ !$groupId ? 'selected' : '' }}>{{ __('masterfile.system_default_option') }}</option>
            @foreach($groups as $g)
            <option value="{{ $g->group_id }}" {{ $groupId === $g->group_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
            @endforeach
        </select>

        <label style="font-size:10.5px; font-weight:600; color:#374151;">{{ __('masterfile.rank_colon_label') }}</label>
        <select name="rank_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; background:#fff;">
            @forelse($ranks as $r)
            <option value="{{ $r->rank_id }}" {{ $rankId === $r->rank_id ? 'selected' : '' }}>{{ $r->rank_name }}</option>
            @empty
            <option value="">{{ __('masterfile.no_ranks_for_role_group') }}</option>
            @endforelse
        </select>
    </form>

    @if(!$rankId)
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:30px; text-align:center; color:#9ca3af; font-size:11px;">
        {{ __('masterfile.no_ranks_exist_prompt') }}
    </div>
    @else
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 14px; display:flex; flex-direction:column; flex:1; min-height:0;">

        <div style="flex:1; overflow-y:auto; min-height:0; margin-bottom:8px;">
            @forelse($rules as $rule)
            <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:6px; padding:6px 8px; margin-bottom:5px;">
                <div style="flex:1; font-size:10.5px; color:#374151;">
                    @if($rule->criteria_type === 'RECRUIT_COUNT')
                    <strong>{{ __('masterfile.criteria_recruit_count') }}</strong> — {{ __('masterfile.criteria_at_least_count', ['value' => rtrim(rtrim(number_format($rule->threshold_value, 2), '0'), '.')]) }}
                    @elseif($rule->criteria_type === 'SALES_VOLUME')
                    <strong>{{ __('masterfile.criteria_sales_volume') }}</strong> — {{ __('masterfile.criteria_sales_detail', ['amount' => number_format($rule->threshold_value, 2), 'metric' => $rule->sales_metric === 'PREMIUM' ? __('masterfile.premium_metric') : __('masterfile.earning_income_metric'), 'months' => $rule->sales_period_months]) }}
                    @elseif($rule->criteria_type === 'TENURE_MONTHS')
                    <strong>{{ __('masterfile.criteria_min_tenure') }}</strong> — {{ __('masterfile.criteria_tenure_detail', ['value' => rtrim(rtrim(number_format($rule->threshold_value, 2), '0'), '.')]) }}
                    @else
                    <strong>{{ __('masterfile.criteria_top_n', ['n' => (int) $rule->threshold_value]) }}</strong> — {!! __('masterfile.top_n_detail', ['n' => (int) $rule->threshold_value, 'metric' => $rule->sales_metric === 'PREMIUM' ? __('masterfile.premium_metric') : __('masterfile.earning_income_metric'), 'months' => $rule->sales_period_months]) !!}
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.masterfile.rank-promotion-rules.destroy', $rule->rule_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.remove_rule_confirm')) }});">
                    @csrf @method('DELETE')
                    <button type="submit" style="background:none; border:none; color:#dc2626; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.remove') }}</button>
                </form>
            </div>
            @empty
            <div style="color:#9ca3af; font-size:10.5px; text-align:center; padding:10px 0;">{{ __('masterfile.no_rules_rank_yet') }}</div>
            @endforelse
        </div>

        @if(count($rules) >= 2)
        <div style="flex-shrink:0; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:6px 8px; margin-bottom:8px;">
            <div style="font-size:9px; font-weight:700; color:#92400e; margin-bottom:4px;">{{ __('masterfile.combine_logic_question') }}</div>
            <form method="POST" action="{{ route('admin.masterfile.rank-promotion-rules.logic') }}" style="display:flex; gap:12px;">
                @csrf @method('PUT')
                <input type="hidden" name="rank_id" value="{{ $rankId }}">
                <label style="display:flex; align-items:center; gap:4px; font-size:10px; color:#92400e; cursor:pointer;">
                    <input type="radio" name="combine_logic" value="AND" onchange="this.form.submit()" {{ $combineLogic === 'AND' ? 'checked' : '' }}>
                    {{ __('masterfile.must_meet_all_and') }}
                </label>
                <label style="display:flex; align-items:center; gap:4px; font-size:10px; color:#92400e; cursor:pointer;">
                    <input type="radio" name="combine_logic" value="OR" onchange="this.form.submit()" {{ $combineLogic === 'OR' ? 'checked' : '' }}>
                    {{ __('masterfile.any_one_enough_or') }}
                </label>
            </form>
        </div>
        @endif

        @if($hasTopN)
        <div style="flex-shrink:0; font-size:9.5px; color:#9ca3af; margin-bottom:8px;">{{ __('masterfile.top_n_rule_exists_note') }}</div>
        @else
        <form method="POST" action="{{ route('admin.masterfile.rank-promotion-rules.store') }}" style="flex-shrink:0; border-top:1px dashed #d1d5db; padding-top:8px;">
            @csrf
            <input type="hidden" name="rank_id" value="{{ $rankId }}">
            <div style="display:grid; grid-template-columns:1.3fr 1fr auto; gap:6px; align-items:end;">
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.criteria_label') }}</div>
                    <select name="criteria_type" class="critSelect" onchange="toggleSalesFields()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="RECRUIT_COUNT">{{ __('masterfile.criteria_recruit_count') }}</option>
                        <option value="SALES_VOLUME">{{ __('masterfile.criteria_sales_volume') }}</option>
                        <option value="TENURE_MONTHS">{{ __('masterfile.tenure_months_option') }}</option>
                        <option value="TOP_N_BY_METRIC">{{ __('masterfile.top_n_option') }}</option>
                    </select>
                </div>
                <div>
                    <div id="thresholdLabel" style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.at_least_label') }}</div>
                    <input type="number" name="threshold" required min="0" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 12px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.add_button') }}</button>
            </div>
            <div id="salesFields" style="display:none; grid-template-columns:1fr 1fr; gap:6px; margin-top:6px;">
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
        @endif
    </div>
    @endif

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.dashboard') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
    </div>
</div>

<script>
var rankPromoI18n = { topN: @json(__('masterfile.top_n_label_js')), atLeast: @json(__('masterfile.at_least_label')) };
function toggleSalesFields() {
    var select = document.querySelector('select.critSelect');
    var box = document.getElementById('salesFields');
    var label = document.getElementById('thresholdLabel');
    var needsPeriod = (select.value === 'SALES_VOLUME' || select.value === 'TOP_N_BY_METRIC');
    box.style.display = needsPeriod ? 'grid' : 'none';
    label.textContent = (select.value === 'TOP_N_BY_METRIC') ? rankPromoI18n.topN : rankPromoI18n.atLeast;
}
</script>
@endsection
