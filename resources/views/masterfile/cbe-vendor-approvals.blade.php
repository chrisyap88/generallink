@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_vendors.approvals_page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:6px;">
        <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('cbe_vendors.approvals_page_title') }}</div>
        <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('cbe_vendors.approvals_intro') }}</div>
    </div>

    @if($errors->has('approval'))
    <div style="background:#fdecea; color:#c62828; border-radius:5px; padding:6px 10px; font-size:10px; margin-bottom:6px; flex-shrink:0;">{{ $errors->first('approval') }}</div>
    @endif

    @php $pageSize = 6; $total = $pending->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1.4fr 1.4fr 1.2fr 1.2fr 1.6fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">
            <div>{{ __('cbe_vendors.col_vendor') }}</div>
            <div>{{ __('cbe_vendors.col_entity') }}</div>
            <div>{{ __('cbe_vendors.col_requested_by') }}</div>
            <div>{{ __('cbe_vendors.col_requested_at') }}</div>
            <div></div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($pending as $p)
            <div class="cbeVARow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:1.4fr 1.4fr 1.2fr 1.2fr 1.6fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $p->vendor_name }}">{{ $p->vendor_name }}</div>
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $p->node_name }} ({{ $p->group_name }})">{{ $p->node_name }}</div>
                <div style="color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $p->requested_by_name }}</div>
                <div style="color:#9ca3af; font-size:9.5px;">{{ \Illuminate\Support\Carbon::parse($p->requested_at)->format('d M Y') }}</div>
                <div style="display:flex; gap:5px; align-items:center;">
                    <form method="POST" action="{{ route('admin.masterfile.cbe-vendor-approvals.approve', $p->id) }}">
                        @csrf
                        <button type="submit" style="background:#1B5E20; color:#fff; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_vendors.btn_approve') }}</button>
                    </form>
                    <span onclick="cbeVARejectShow('{{ $p->id }}')" style="background:#f3f4f6; color:#c62828; border-radius:4px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_vendors.btn_reject') }}</span>
                </div>
            </div>
            <div id="cbeVAReject-{{ $p->id }}" style="display:none; padding:5px 10px 8px; border-bottom:1px solid #f3f4f6;">
                <form method="POST" action="{{ route('admin.masterfile.cbe-vendor-approvals.reject', $p->id) }}" style="display:flex; gap:6px;">
                    @csrf
                    <input type="text" name="rejection_reason" placeholder="{{ __('cbe_vendors.rejection_reason_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                    <button type="submit" style="background:#c62828; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('cbe_vendors.btn_reject') }}</button>
                </form>
            </div>
            @empty
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_vendors.no_pending') }}</div>
            @endforelse
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="cbeVAPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="cbeVAPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="cbeVAPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    @endif
</div>

<script>
function cbeVARejectShow(id){ document.getElementById('cbeVAReject-' + id).style.display = 'block'; }

(function () {
    var cbeVACurrentPage = 1;
    var cbeVATotalPages = {{ $totalPages }};
    window.cbeVAPageNav = function (dir) {
        var next = cbeVACurrentPage + dir;
        if (next < 1 || next > cbeVATotalPages) return;
        cbeVACurrentPage = next;
        document.querySelectorAll('.cbeVARow').forEach(function (row) {
            row.style.display = (parseInt(row.getAttribute('data-page'), 10) === cbeVACurrentPage) ? 'grid' : 'none';
        });
        document.getElementById('cbeVAPageLabel').textContent = cbeVACurrentPage + ' / ' + cbeVATotalPages + ' ({{ $total }})';
    };
})();
</script>
@endsection
