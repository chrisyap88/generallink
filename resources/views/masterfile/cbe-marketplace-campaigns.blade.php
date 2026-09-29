@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_marketplace.campaigns_page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:5px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('cbe_marketplace.campaigns_page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('cbe_marketplace.campaigns_intro') }}</div>
        </div>
        <span onclick="cbeMCShowForm()" style="background:#1B5E20; color:#fff; border-radius:5px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_marketplace.add_campaign_button') }}</span>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; color:#1B5E20; border-radius:5px; padding:6px 10px; font-size:10px; margin-bottom:6px; flex-shrink:0;">{{ session('success') }}</div>
    @endif

    @php $pageSize = 7; $total = $campaigns->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1.6fr 1.6fr 1fr 1fr 0.9fr 0.9fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">
            <div>{{ __('cbe_marketplace.col_campaign_name') }}</div>
            <div>{{ __('cbe_marketplace.col_type') }}</div>
            <div>{{ __('cbe_marketplace.col_start_date') }}</div>
            <div>{{ __('cbe_marketplace.col_end_date') }}</div>
            <div>{{ __('cbe_marketplace.col_campaign_status') }}</div>
            <div></div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($campaigns as $c)
            @php
                $stColor = match($c->status) { 'ACTIVE' => '#2e7d32', 'ENDED' => '#9ca3af', default => '#b45309' };
                $stLabel = match($c->status) { 'ACTIVE' => __('cbe_marketplace.campaign_status_active'), 'ENDED' => __('cbe_marketplace.campaign_status_ended'), default => __('cbe_marketplace.campaign_status_draft') };
            @endphp
            <div class="cbeMCRow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:1.6fr 1.6fr 1fr 1fr 0.9fr 0.9fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $c->campaign_name }}">{{ $c->campaign_name }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $c->type_name }}">{{ $c->type_name }}</div>
                <div style="color:#6b7280; font-size:9.5px;">{{ \Illuminate\Support\Carbon::parse($c->start_date)->format('d M Y') }}</div>
                <div style="color:#6b7280; font-size:9.5px;">{{ $c->end_date ? \Illuminate\Support\Carbon::parse($c->end_date)->format('d M Y') : '—' }}</div>
                <div style="color:{{ $stColor }}; font-weight:600; font-size:9.5px;">{{ $stLabel }}</div>
                <div>
                    @if($c->status !== 'ENDED')
                    <form method="POST" action="{{ route('admin.masterfile.cbe-marketplace-campaigns.advance', $c->campaign_id) }}">
                        @csrf
                        <button type="submit" style="background:#f3f4f6; color:#374151; border:none; border-radius:4px; padding:3px 8px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('cbe_marketplace.btn_next_stage') }}</button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_marketplace.no_campaigns') }}</div>
            @endforelse
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeMCPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeMCPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeMCPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    @endif
</div>

{{-- New Campaign — overlay form, not a jump screen. --}}
<div id="cbeMCFormOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:50; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:18px 20px; width:380px; max-width:92vw; box-sizing:border-box;">
        <div style="font-size:12.5px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('cbe_marketplace.campaign_form_title') }}</div>
        <form method="POST" action="{{ route('admin.masterfile.cbe-marketplace-campaigns.store', $node->node_id) }}">
            @csrf
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_campaign_type') }} *</label>
                    <select name="type_id" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">—</option>
                        @foreach($campaignTypes as $ct)
                        <option value="{{ $ct->type_id }}">{{ $ct->type_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_campaign_name') }} *</label>
                    <input type="text" name="campaign_name" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_message') }}</label>
                    <input type="text" name="message" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_start_date') }} *</label>
                        <input type="date" name="start_date" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:9.5px; font-weight:600; color:#374151;">{{ __('cbe_marketplace.field_end_date') }}</label>
                        <input type="date" name="end_date" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:14px; justify-content:flex-end;">
                <span onclick="cbeMCHideForm()" style="background:#f3f4f6; color:#374151; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_marketplace.btn_cancel') }}</span>
                <button type="submit" style="background:#1B5E20; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('cbe_marketplace.btn_save') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function cbeMCShowForm(){ document.getElementById('cbeMCFormOverlay').style.display = 'flex'; }
function cbeMCHideForm(){ document.getElementById('cbeMCFormOverlay').style.display = 'none'; }
@if($errors->any())
document.addEventListener('DOMContentLoaded', function(){ cbeMCShowForm(); });
@endif

(function () {
    var cbeMCCurrentPage = 1;
    var cbeMCTotalPages = {{ $totalPages }};
    window.cbeMCPageNav = function (dir) {
        var next = cbeMCCurrentPage + dir;
        if (next < 1 || next > cbeMCTotalPages) return;
        cbeMCCurrentPage = next;
        document.querySelectorAll('.cbeMCRow').forEach(function (row) {
            row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeMCCurrentPage) ? 'grid' : 'none';
        });
        document.getElementById('cbeMCPageLabel').textContent = cbeMCCurrentPage + ' / ' + cbeMCTotalPages + ' ({{ $total }})';
    };
})();
</script>
@endsection
