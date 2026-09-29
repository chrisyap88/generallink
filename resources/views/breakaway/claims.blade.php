@extends('layouts.dashboard')

@section('page-title', __('breakaway.page_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #207) — one row per period a breakaway target
     was hit. Admin sees every Group Leader's claims (with a GL search
     box); a Group Leader sees only their own. "Mark as Claimed" is
     bookkeeping only — the real payment is filed separately with the
     insurance vendor; nothing here ever touches a wallet. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('breakaway.intro_note') }}</div>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif

    <div style="display:flex; gap:8px; flex-shrink:0; margin-bottom:8px;">
        <div style="flex:1; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 12px;">
            <div style="font-size:8.5px; color:#92400e; text-transform:uppercase; font-weight:600;">{{ __('breakaway.stat_pending_label') }}</div>
            <div style="font-size:14px; font-weight:700; color:#92400e;">{{ $pendingCount }} &middot; RM {{ number_format($pendingAmount, 2) }}</div>
        </div>
        <div style="flex:1; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:8px 12px;">
            <div style="font-size:8.5px; color:#166534; text-transform:uppercase; font-weight:600;">{{ __('breakaway.stat_claimed_label') }}</div>
            <div style="font-size:14px; font-weight:700; color:#166534;">{{ $claimedCount }} &middot; RM {{ number_format($claimedAmount, 2) }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('breakaway-claims.index') }}" style="position:relative; margin-bottom:8px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; flex-shrink:0;" autocomplete="off">
        @if($isAdmin)
        <div style="position:relative;">
            <input type="text" id="glSearchBox" value="{{ $selectedGL->full_name ?? '' }}" placeholder="{{ __('breakaway.search_gl_placeholder') }}" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; min-width:200px; box-sizing:border-box;" autocomplete="off">
            <input type="hidden" name="gl_id" id="glIdField" value="{{ $glId }}">
            <div id="glResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; max-height:180px; overflow-y:auto; z-index:20; box-shadow:0 4px 10px rgba(0,0,0,.1);"></div>
        </div>
        @endif
        <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff;">
            <option value="" {{ $status === '' ? 'selected' : '' }}>{{ __('breakaway.all_statuses_option') }}</option>
            <option value="PENDING" {{ $status === 'PENDING' ? 'selected' : '' }}>{{ __('breakaway.status_pending_option') }}</option>
            <option value="CLAIMED" {{ $status === 'CLAIMED' ? 'selected' : '' }}>{{ __('breakaway.status_claimed_option') }}</option>
        </select>
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('gl.search_button') }}</button>
        @if($glId || $status)
        <a href="{{ route('breakaway-claims.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; display:flex; align-items:center;">{{ __('dashboard.clear_word') }}</a>
        @endif
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_breakaway_group') }}</th>
                        @if($isAdmin)
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_original_gl') }}</th>
                        @endif
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_period') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_achieved') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_bonus') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_status') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($claims as $c)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#111827;">{{ $c->promoted_gl_name }} <span style="color:#9ca3af;">({{ $c->promoted_gl_code }})</span></td>
                        @if($isAdmin)
                        <td style="padding:5px 8px; color:#111827;">{{ $c->original_gl_name }} <span style="color:#9ca3af;">({{ $c->original_gl_code }})</span></td>
                        @endif
                        <td style="padding:5px 8px; color:#6b7280;">{{ \Illuminate\Support\Carbon::parse($c->period_start)->format('d M Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($c->period_end)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#111827;">RM {{ number_format($c->achieved_amount, 2) }} <span style="color:#9ca3af;">({{ $c->target_metric === 'PREMIUM' ? __('breakaway.metric_premium') : __('breakaway.metric_earning_income') }})</span></td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#1565C0;">RM {{ number_format($c->bonus_amount, 2) }} <span style="color:#9ca3af; font-weight:400;">({{ rtrim(rtrim(number_format($c->bonus_pct, 2), '0'), '.') }}%)</span></td>
                        <td style="padding:5px 8px; text-align:center;">
                            @if($c->status === 'PENDING')
                            <span style="background:#fffbeb; color:#92400e; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('breakaway.status_badge_pending') }}</span>
                            @else
                            <span style="background:#f0fdf4; color:#166534; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('breakaway.status_badge_claimed') }}</span>
                            @endif
                        </td>
                        <td style="padding:5px 8px; text-align:center; white-space:nowrap;">
                            <a href="{{ route('breakaway-claims.voucher', $c->claim_id) }}" target="_blank" style="color:#1565C0; font-size:9px; font-weight:600; text-decoration:none; margin-right:8px;">{{ __('breakaway.view_voucher_link') }}</a>
                            @if($c->status === 'PENDING')
                            <form method="POST" action="{{ route('breakaway-claims.mark-claimed', $c->claim_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('breakaway.mark_as_claimed_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#166534; font-size:9px; font-weight:600; cursor:pointer;">{{ __('breakaway.mark_as_claimed_button') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('breakaway.no_claims_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($claims->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $claims->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('special_group.page_x_of_y_paren', ['current' => $claims->currentPage(), 'last' => $claims->lastPage(), 'total' => $claims->total()]) }}</span>
            @if($claims->hasMorePages())
                <a href="{{ $claims->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>

@if($isAdmin)
<script>
(function() {
    var box = document.getElementById('glSearchBox');
    var hidden = document.getElementById('glIdField');
    var results = document.getElementById('glResults');
    var timer = null;

    box.addEventListener('input', function() {
        clearTimeout(timer);
        var q = box.value.trim();
        hidden.value = '';
        if (q.length < 1) { results.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('{{ route('breakaway-claims.gl-typeahead') }}?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(list) {
                    if (!list.length) { results.style.display = 'none'; return; }
                    results.innerHTML = list.map(function(a) {
                        return '<div class="glResultRow" data-id="' + a.agent_id + '" data-name="' + a.full_name.replace(/"/g,'') + '" style="padding:6px 10px; font-size:10px; cursor:pointer; border-bottom:1px solid #f3f4f6;">' + a.full_name + ' (' + a.agent_code + ')</div>';
                    }).join('');
                    results.style.display = 'block';
                    Array.prototype.forEach.call(results.querySelectorAll('.glResultRow'), function(row) {
                        row.addEventListener('click', function() {
                            hidden.value = row.getAttribute('data-id');
                            box.value = row.getAttribute('data-name');
                            results.style.display = 'none';
                            box.form.submit();
                        });
                    });
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!box.contains(e.target) && !results.contains(e.target)) { results.style.display = 'none'; }
    });
})();
</script>
@endif
@endsection
