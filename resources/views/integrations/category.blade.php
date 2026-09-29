@extends('layouts.dashboard')

@section('page-title', $categoryLabel)

@section('content')
{{-- REBUILT 6 Aug 2026 — per Chris: this page listed every provider
     stacked vertically with a scrolling wrapper around the whole list,
     which broke the standing no-scroll rule (visible scrollbar in his
     screenshot). Rebuilt as folder tabs, one provider per tab — same
     .ptab-btn/.ptab-panel pattern already used on My Profile — so each
     tab only ever shows ONE provider's card and always fits with no
     scroll, regardless of how much help text or how many providers a
     category has. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('integrations.security_note') }}</div>
        </div>
        <a href="{{ route('integrations.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('integrations.all_categories_link') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:5px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:5px;">{{ session('error') }}</div>
    @endif

    @php
        // FIX 6 Aug 2026 — per Chris: saving/testing/disconnecting a
        // provider used to redirect back to this page always showing the
        // FIRST tab (OpenAI), even if he'd just acted on a different one
        // (e.g. ElevenLabs) — the success/error message appeared above a
        // tab he wasn't even looking at, so it looked like nothing
        // happened. The controller now passes ?tab=<provider key> on
        // every redirect; this picks that provider's tab as the one to
        // actually show, falling back to the first tab if none given.
        $requestedTab = request('tab');
        $activeIndex = 0;
        foreach ($providers as $i => $p) {
            if ($requestedTab && $p['key'] === $requestedTab) { $activeIndex = $i; break; }
        }
    @endphp
    <div style="display:flex; gap:2px; flex-shrink:0; padding:0 2px; flex-wrap:wrap;">
        @foreach($providers as $i => $p)
        <button type="button" class="ptab-btn {{ $i === $activeIndex ? 'active' : '' }}" data-tab="p{{ $i }}">{{ $p['label'] }}</button>
        @endforeach
    </div>

    <div style="flex:1; min-height:0; background:#fff; border-radius:0 8px 8px 8px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 16px; overflow:hidden; display:flex; flex-direction:column;">
        @foreach($providers as $i => $p)
        <div class="ptab-panel {{ $i === $activeIndex ? 'active' : '' }}" data-tab="p{{ $i }}">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                <span style="font-size:13.5px; font-weight:700; color:#263238;">{{ $p['label'] }}</span>
                @if($p['status'] === 'CONNECTED')
                <span style="background:#e8f5e9; color:#1b5e20; border-radius:20px; padding:2px 10px; font-size:9.5px; font-weight:700;">{{ __('integrations.connected_badge') }}</span>
                @elseif($p['status'] === 'ERROR')
                <span style="background:#fde8e8; color:#b71c1c; border-radius:20px; padding:2px 10px; font-size:9.5px; font-weight:700;">{{ __('integrations.connection_error_badge') }}</span>
                @elseif($p['has_key'])
                <span style="background:#fff8e1; color:#8d6e00; border-radius:20px; padding:2px 10px; font-size:9.5px; font-weight:700;">{{ __('integrations.not_tested_yet_badge') }}</span>
                @else
                <span style="background:#eef2f7; color:#374151; border-radius:20px; padding:2px 10px; font-size:9.5px; font-weight:600;">{{ __('integrations.not_connected_badge') }}</span>
                @endif
                @if(!$p['available'])
                <span style="background:#f3f4f6; color:#9ca3af; border-radius:20px; padding:2px 10px; font-size:9.5px; font-weight:600;">{{ __('integrations.coming_soon_badge') }}</span>
                @endif
                @if($p['last_tested_at'])
                <span style="font-size:9.5px; color:#9ca3af; margin-left:auto; white-space:nowrap;">{{ __('integrations.last_tested_note', ['time' => \Carbon\Carbon::parse($p['last_tested_at'])->diffForHumans()]) }}{{ $p['last_test_result'] ? ' — ' . $p['last_test_result'] : '' }}</span>
                @endif
            </div>

            @if($p['note'])
            <div style="font-size:11px; color:#6b7280; line-height:1.7;">{{ $p['note'] }}</div>
            @else
            @if($p['help'] ?? null)
            <div style="font-size:11px; color:#6b7280; margin-bottom:14px; line-height:1.7;">{{ $p['help'] }}</div>
            @endif
            {{-- FIX 6 Aug 2026 (revised) — per Chris: putting Save on its own
                 row (previous fix) fixed the bubble overlap but made the
                 WhatsApp card taller than the panel, forcing a scrollbar —
                 the opposite problem. Real fix: keep Save inline like every
                 other provider (no extra row, no extra height), but cap the
                 row's width so it stops short of the panel's far-right
                 corner where Carolyn's fixed chat bubble lives. Two 180px
                 inputs + Save need well under 500px, so this row never
                 wraps in practice — it just can't drift into that corner. --}}
            <form method="POST" action="{{ route('integrations.connect', [$categoryKey, $p['key']]) }}" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; {{ $p['shape']['type'] === 'pair' ? 'max-width:calc(100% - 100px);' : '' }}">
                @csrf
                @if($p['shape']['type'] === 'pair')
                <input type="text" name="field1" placeholder="{{ $p['shape']['label1'] }}" autocomplete="off" style="flex:1; min-width:180px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                <input type="password" name="field2" placeholder="{{ $p['shape']['label2'] }}" autocomplete="new-password" style="flex:1; min-width:180px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('integrations.save_button') }}</button>
                @elseif(!empty($p['shape']['textarea']))
                <textarea name="field1" placeholder="{{ $p['shape']['label1'] }}" rows="1" style="flex:1; min-width:220px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; resize:vertical; font-family:inherit; box-sizing:border-box;"></textarea>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('integrations.save_button') }}</button>
                @else
                <input type="password" name="field1" placeholder="{{ $p['has_key'] ? __('integrations.already_set_placeholder', ['field' => $p['shape']['label1']]) : $p['shape']['label1'] }}" autocomplete="new-password" style="flex:1; min-width:220px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('integrations.save_button') }}</button>
                @endif
            </form>
            @if($p['has_key'])
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                @if($p['available'])
                <form method="POST" action="{{ route('integrations.test', [$categoryKey, $p['key']]) }}">
                    @csrf
                    <button type="submit" style="background:#00838f; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('integrations.test_connection_button') }}</button>
                </form>
                @else
                <span style="font-size:9.5px; color:#9ca3af; font-style:italic;">{{ __('integrations.verification_coming_later_note') }}</span>
                @endif
                <form method="POST" action="{{ route('integrations.disconnect', [$categoryKey, $p['key']]) }}" onsubmit="return confirm('{{ __('integrations.disconnect_confirm', ['label' => $p['label']]) }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:#fff; color:#b71c1c; border:1px solid #f3d4d4; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('integrations.disconnect_button') }}</button>
                </form>
            </div>
            @endif
            @endif
        </div>
        @endforeach
    </div>

</div>

<style>
.ptab-btn { padding:6px 14px; font-size:10.5px; font-weight:600; color:#6b7280; background:#e5e7eb; border:none; border-radius:7px 7px 0 0; cursor:pointer; white-space:nowrap; font-family:inherit; }
.ptab-btn.active { background:#fff; color:#1565C0; }
.ptab-panel { display:none; flex-direction:column; flex:1; min-height:0; overflow-y:auto; overflow-x:hidden; }
.ptab-panel.active { display:flex; }
</style>

<script>
(function() {
    var tabButtons = document.querySelectorAll('.ptab-btn');
    var panels = document.querySelectorAll('.ptab-panel');
    function showTab(name) {
        tabButtons.forEach(function(b) { b.classList.toggle('active', b.dataset.tab === name); });
        panels.forEach(function(p) { p.classList.toggle('active', p.dataset.tab === name); });
    }
    tabButtons.forEach(function(b) {
        b.addEventListener('click', function() { showTab(b.dataset.tab); });
    });
})();
</script>
@endsection
