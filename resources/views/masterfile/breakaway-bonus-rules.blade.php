@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.breakaway_bonus_title'))
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
        <form method="GET" action="{{ route('admin.masterfile.breakaway-bonus-rules') }}" style="display:flex; align-items:center; gap:8px;">
            <label style="font-size:10.5px; font-weight:600; color:#374151;">{{ __('masterfile.rule_for_label') }}</label>
            <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:11.5px; background:#fff;">
                <option value="" {{ !$groupLabelId ? 'selected' : '' }}>{{ __('masterfile.system_default_option') }}</option>
                @foreach($groupLabels as $g)
                <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                @endforeach
            </select>
        </form>
        <div style="font-size:9.5px; color:#9ca3af;">
            {{ __('masterfile.breakaway_bonus_intro') }}
            @if($usingDefault)<strong style="color:#0369a1;">{{ __('masterfile.currently_following_default') }}</strong>@endif
        </div>
    </div>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:16px; max-width:560px;">
        <form method="POST" action="{{ route('admin.masterfile.breakaway-bonus-rules.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.target_based_on_label') }}</label>
                    <select name="target_metric" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; box-sizing:border-box;">
                        <option value="EARNING_INCOME" {{ old('target_metric', $rule->target_metric ?? 'EARNING_INCOME') === 'EARNING_INCOME' ? 'selected' : '' }}>{{ __('masterfile.earning_income_metric') }}</option>
                        <option value="PREMIUM" {{ old('target_metric', $rule->target_metric ?? 'EARNING_INCOME') === 'PREMIUM' ? 'selected' : '' }}>{{ __('masterfile.sales_premium_metric') }}</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.target_amount_label') }}</label>
                    <input type="number" name="target_amount" value="{{ old('target_amount', $rule->target_amount ?? 0) }}" required min="0" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.breakaway_bonus_pct_label') }}</label>
                    <input type="number" name="bonus_pct" value="{{ old('bonus_pct', $rule->bonus_pct ?? 0) }}" required min="0" max="100" step="0.001" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.recheck_every_label') }}</label>
                    <input type="number" name="period_months" value="{{ old('period_months', $rule->period_months ?? 12) }}" required min="1" max="60" step="1" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                </div>
            </div>

            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.status') }}</label>
            <select name="is_active" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; background:#fff; box-sizing:border-box; margin-bottom:12px;">
                <option value="1" {{ old('is_active', $rule->is_active ?? true) ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                <option value="0" {{ !old('is_active', $rule->is_active ?? true) ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
            </select>

            <div style="display:flex; gap:8px; margin-top:4px;">
                <a href="{{ route('admin.dashboard') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 24px; font-size:12px; font-weight:600; cursor:pointer;">💾 {{ __('masterfile.save') }}</button>
            </div>
        </form>
    </div>

</div>
@endsection
