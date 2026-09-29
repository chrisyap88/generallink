@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.affiliate_partner_profile_setting_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ $agent->full_name }} — {{ __('masterfile.affiliate_partner_profile_setting_title') }}</div>
    </div>
    <div style="font-size:10px; color:#6b7280; flex-shrink:0;">{{ $agent->agent_code }} · {{ $roleLabel }}</div>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 14px; display:flex; flex-direction:column; flex:1; min-height:0;">

        <div style="flex:1; overflow-y:auto; min-height:0; margin-bottom:8px;">
            @forelse($rows as $row)
            <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:6px; padding:7px 10px; margin-bottom:6px;">
                <div style="flex:1; font-size:10.5px; color:#374151;">
                    <strong>{{ \App\Services\RoleLabelService::label($row->source_role) }}{{ $row->rank_name ? __('masterfile.rank_suffix', ['rank' => $row->rank_name]) : __('masterfile.any_rank_suffix') }}</strong>
                    →
                    @if($row->override_type === 'PERCENTAGE')
                    {{ rtrim(rtrim(number_format($row->override_value, 2), '0'), '.') }}%
                    @else
                    {{ __('masterfile.flat_amount_label', ['amount' => number_format($row->override_value, 2)]) }}
                    @endif
                    · {{ $row->vendor_name ?? __('masterfile.all_companies_option') }}{{ $row->product_name ? __('masterfile.product_suffix', ['product' => $row->product_name]) : ($row->vendor_name ? __('masterfile.all_products_suffix') : '') }}
                    <br>
                    <span style="color:#9ca3af;">{{ $row->expiry_date ? __('masterfile.effective_to_priority', ['date' => \Carbon\Carbon::parse($row->effective_date)->format('d M Y'), 'expiry' => \Carbon\Carbon::parse($row->expiry_date)->format('d M Y'), 'priority' => $row->priority]) : __('masterfile.effective_no_expiry_priority', ['date' => \Carbon\Carbon::parse($row->effective_date)->format('d M Y'), 'priority' => $row->priority]) }}</span>
                </div>
                <span style="background:{{ $row->status === 'ACTIVE' ? '#d1fae5' : '#f3f4f6' }}; color:{{ $row->status === 'ACTIVE' ? '#065f46' : '#6b7280' }}; font-size:9px; font-weight:700; padding:3px 9px; border-radius:20px; white-space:nowrap;">{{ $row->status }}</span>
                <form method="POST" action="{{ route('admin.masterfile.override-recipient-profile.toggle', $row->override_id) }}">
                    @csrf @method('PATCH')
                    <button type="submit" style="background:none; border:1px solid #d1d5db; border-radius:5px; color:#374151; font-size:9.5px; font-weight:600; cursor:pointer; padding:4px 8px; white-space:nowrap;">{{ $row->status === 'ACTIVE' ? __('masterfile.deactivate_btn') : __('masterfile.activate_btn') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.masterfile.override-recipient-profile.destroy', $row->override_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.remove_override_rule_confirm')) }});">
                    @csrf @method('DELETE')
                    <button type="submit" style="background:none; border:none; color:#dc2626; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.remove') }}</button>
                </form>
            </div>
            @empty
            <div style="color:#9ca3af; font-size:10.5px; text-align:center; padding:20px 0;">{{ __('masterfile.no_override_rules_normal_commission') }}</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.masterfile.override-recipient-profile.store') }}" style="flex-shrink:0; border-top:1px dashed #d1d5db; padding-top:10px;">
            @csrf
            <input type="hidden" name="recipient_agent_id" value="{{ $agent->agent_id }}">
            <div style="font-size:10px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('masterfile.add_override_source_heading') }}</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.draws_from_role_label') }}</div>
                    <select name="source_role" id="sourceRole" onchange="refreshRankOptions()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        @foreach($ranksByRole as $role => $ranks)
                        <option value="{{ $role }}">{{ \App\Services\RoleLabelService::label($role) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.narrow_to_rank_label') }}</div>
                    <select name="source_rank_id" id="sourceRank" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('masterfile.any_rank_option') }}</option>
                    </select>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.company_label') }}</div>
                    <select name="vendor_id" id="vendorSelect" onchange="refreshProductOptions()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('masterfile.all_companies_option') }}</option>
                        @foreach($vendors as $v)
                        <option value="{{ $v->vendor_id }}">{{ $v->vendor_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.product_label') }}</div>
                    <select name="product_id" id="productSelect" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('masterfile.all_products_plain') }}</option>
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr auto; gap:8px; align-items:end;">
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.type_label') }}</div>
                    <select name="override_type" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="PERCENTAGE">{{ __('masterfile.percentage_option') }}</option>
                        <option value="FIXED_AMOUNT">{{ __('masterfile.fixed_rm_amount_option') }}</option>
                    </select>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.value_label') }}</div>
                    <input type="number" name="override_value" required min="0" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.effective_date_label') }}</div>
                    <input type="date" name="effective_date" required value="{{ date('Y-m-d') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('masterfile.expiry_optional_label') }}</div>
                    <input type="date" name="expiry_date" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.add_button') }}</button>
            </div>
        </form>
    </div>

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.masterfile.override-recipient-profile') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
    </div>
</div>

<script>
var ranksByRole = @json($ranksByRole);
var productsByVendor = @json($productsByVendor);
var orpI18n = {
    anyRank: @json(__('masterfile.any_rank_option')),
    allProducts: @json(__('masterfile.all_products_plain'))
};

function refreshRankOptions() {
    var role = document.getElementById('sourceRole').value;
    var rankSelect = document.getElementById('sourceRank');
    rankSelect.innerHTML = '<option value="">' + orpI18n.anyRank + '</option>';
    (ranksByRole[role] || []).forEach(function (r) {
        var opt = document.createElement('option');
        opt.value = r.rank_id;
        opt.textContent = r.rank_name;
        rankSelect.appendChild(opt);
    });
}

function refreshProductOptions() {
    var vendorId = document.getElementById('vendorSelect').value;
    var productSelect = document.getElementById('productSelect');
    productSelect.innerHTML = '<option value="">' + orpI18n.allProducts + '</option>';
    (productsByVendor[vendorId] || []).forEach(function (p) {
        var opt = document.createElement('option');
        opt.value = p.product_id;
        opt.textContent = p.product_name;
        productSelect.appendChild(opt);
    });
}

refreshRankOptions();
</script>
@endsection
