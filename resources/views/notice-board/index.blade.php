@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('notice_board.page_title'))

@section('content')

{{-- NEW 21 Jul 2026 — Notice Board: read-only broadcast announcements
     from Admin. Whatever is on this page gets marked read the moment
     it loads (see controller), which clears both the sidebar's red
     unread badge and each notice's own NEW flag. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ route('notification-preferences.edit') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('notice_board.notification_preferences_link') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">

        {{-- NEW 8 Aug 2026 (Task #87) — search + category filter. --}}
        <form method="GET" action="{{ route('notice-board.index') }}" style="flex-shrink:0; display:flex; gap:6px; margin-bottom:8px;">
            <div style="position:relative; flex:1;">
                <input type="text" name="q" id="nbSearchInput" autocomplete="off" value="{{ $search }}" placeholder="{{ __('notice_board.search_title_content_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                <div id="nbSearchDropdown" style="display:none; position:absolute; top:100%; left:0; width:100%; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.12); max-height:220px; overflow-y:auto; z-index:50;"></div>
            </div>
            <select name="category" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; outline:none; background:#fff;">
                <option value="">{{ __('notice_board.all_categories_option') }}</option>
                @foreach(['IMPORTANT_UPDATE' => __('notice_board.category_important_update'), 'PROMOTION' => __('notice_board.category_promotion'), 'HOLIDAY_FESTIVE' => __('notice_board.category_holiday_festive'), 'CONTACT_INFO' => __('notice_board.category_contact_info'), 'GENERAL' => __('notice_board.category_general')] as $key => $label)
                <option value="{{ $key }}" {{ $category === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('gl.search_button') }}</button>
            @if($search || $category)
            <a href="{{ route('notice-board.index') }}" style="background:#eef2f7; color:#374151; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:11px; font-weight:600; white-space:nowrap;">{{ __('dashboard.clear_word') }}</a>
            @endif
        </form>

        {{-- CHANGED 8 Aug 2026 per Chris: strict no-scroll rule — was
             overflow-y:auto. Page size dropped to 3 (see controller) so
             this region no longer needs to scroll. --}}
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($notices as $n)
            @php
                $catBg = match($n->category) { 'IMPORTANT_UPDATE' => '#fde8e8', 'PROMOTION' => '#e8f5e9', 'HOLIDAY_FESTIVE' => '#e3f2fd', 'CONTACT_INFO' => '#e0f7fa', default => '#f3f4f6' };
                $catColor = match($n->category) { 'IMPORTANT_UPDATE' => '#b71c1c', 'PROMOTION' => '#1b5e20', 'HOLIDAY_FESTIVE' => 'var(--gl-blue)', 'CONTACT_INFO' => '#00838f', default => '#374151' };
                $catLabel = match($n->category) { 'IMPORTANT_UPDATE' => __('notice_board.category_important_update'), 'PROMOTION' => __('notice_board.category_promotion'), 'HOLIDAY_FESTIVE' => __('notice_board.category_holiday_festive_greeting'), 'CONTACT_INFO' => __('notice_board.category_contact_info'), default => __('notice_board.category_general') };
                $isNew = is_null($n->read_at);
            @endphp
            <div style="border:1px solid #f3f4f6; border-radius:8px; padding:10px 12px; margin-bottom:8px; {{ $isNew ? 'background:#f0f9ff;' : '' }}">
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px; flex-wrap:wrap;">
                    @if($isNew)
                    <span style="background:#F44336; color:#fff; border-radius:20px; padding:1px 8px; font-size:8px; font-weight:700;">{{ __('notice_board.new_badge') }}</span>
                    @endif
                    <span style="padding:1px 8px; border-radius:20px; font-size:8.5px; font-weight:600; background:{{ $catBg }}; color:{{ $catColor }};">{{ $catLabel }}</span>
                    <span style="font-size:9px; color:#9ca3af;">{{ \Carbon\Carbon::parse($n->created_at)->format('d M Y') }}{{ $n->expires_at ? ' ' . __('notice_board.valid_until_suffix', ['date' => \Carbon\Carbon::parse($n->expires_at)->format('d M Y')]) : '' }}</span>
                </div>
                <div style="font-size:11.5px; font-weight:700; color:#263238; margin-bottom:4px;">{{ $n->title }}</div>
                <div style="font-size:10.5px; color:#4b5563; white-space:pre-wrap; line-height:1.4;">{{ $n->body }}</div>
                @if(!empty($n->ai_blurb))
                {{-- NEW 8 Aug 2026 (Task #90) — GLADE Phase 3: AI-generated, one-time-cached "why this matters to you" note for Promotion notices only. --}}
                <div style="margin-top:6px; background:#fff8e1; border-left:3px solid #f9a825; border-radius:4px; padding:5px 8px; font-size:9.5px; color:#7a5c00; display:flex; align-items:flex-start; gap:5px;">
                    <span style="font-weight:700; white-space:nowrap;">{{ __('notice_board.ai_insight_label') }}</span> <span>{{ $n->ai_blurb }}</span>
                </div>
                @endif
                @if($n->attachment_file_path)
                <div style="margin-top:6px;">
                    <a href="{{ route('notice-board.attachment', $n->notice_id) }}" target="_blank" style="font-size:9.5px; font-weight:600; color:var(--gl-blue); text-decoration:underline;">&#128206; {{ $n->attachment_file_name }}</a>
                </div>
                @endif
            </div>
            @empty
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ ($search || $category) ? __('notice_board.no_notices_match_search') : __('notice_board.no_notices_right_now') }}</div>
            @endforelse
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($notices->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $notices->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('notice_board.page_x_of_y_notices', ['current' => $notices->currentPage(), 'last' => $notices->lastPage(), 'total' => $notices->total()]) }}</span>
            @if($notices->hasMorePages())
                <a href="{{ $notices->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
<script>
(function () {
    var input = document.getElementById('nbSearchInput');
    var dropdown = document.getElementById('nbSearchDropdown');
    if (!input || !dropdown) { return; }
    var timer = null;

    function hide() { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }

    function render(items) {
        if (!items.length) { hide(); return; }
        dropdown.innerHTML = items.map(function (item) {
            return '<div class="nb-suggestion" data-title="' + item.title.replace(/"/g, '&quot;') + '" ' +
                'style="padding:6px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;">' + item.title + '</div>';
        }).join('');
        dropdown.style.display = 'block';
        Array.prototype.forEach.call(dropdown.querySelectorAll('.nb-suggestion'), function (row) {
            row.onmousedown = function () {
                input.value = row.getAttribute('data-title');
                hide();
                input.form.submit();
            };
        });
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        if (timer) { clearTimeout(timer); }
        if (q.length < 1) { hide(); return; }
        timer = setTimeout(function () {
            fetch('{{ route('notice-board.typeahead') }}?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); }).then(render).catch(hide);
        }, 250);
    });

    input.addEventListener('blur', function () { setTimeout(hide, 100); });
})();
</script>
@endsection
