@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('integrations.title'))

@section('content')

{{-- NEW 4 Aug 2026 — Integration Hub, Phase 1. 16 categories shown as
     a no-scroll grid (matches Chris's "every screen must fit with no
     scrolling" rule). Only categories with at least one working
     connector are clickable in Phase 1 — the rest show "Coming soon"
     so the full planned shape of the Hub is visible without pretending
     it's already built. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('integrations.byok_note') }}</div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0;">
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); grid-template-rows:repeat(4, 1fr); gap:8px; height:100%;">
            @foreach($categories as $c)
            <a href="{{ route('integrations.category', $c['key']) }}" data-ai-nav="category-tile-{{ $c['key'] }}" style="text-decoration:none; display:flex; flex-direction:column; justify-content:center; border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px; background:#f8fafc; transition:all .15s;" onmouseover="this.style.borderColor='var(--gl-blue)'; this.style.background='#eef4fc';" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#f8fafc';">
                <div style="display:flex; align-items:center; gap:6px; margin-bottom:3px;">
                    <i class="ti {{ $c['icon'] }}" style="font-size:14px; color:var(--gl-blue);"></i>
                    <span style="font-size:11px; font-weight:700; color:#263238;">{{ $c['label'] }}</span>
                </div>
                <div style="font-size:9px; color:#6b7280; line-height:1.3; margin-bottom:4px;">{{ $c['description'] }}</div>
                <div style="display:flex; gap:4px; flex-wrap:wrap;">
                    @if($c['connected'] > 0)
                    <span style="background:#e8f5e9; color:#1b5e20; border-radius:20px; padding:1px 8px; font-size:8.5px; font-weight:700;">{{ __('integrations.verified_badge', ['count' => $c['connected']]) }}</span>
                    @endif
                    @if($c['saved'] > $c['connected'])
                    <span style="background:#fff8e1; color:#8d6e00; border-radius:20px; padding:1px 8px; font-size:8.5px; font-weight:700;">{{ __('integrations.saved_badge', ['count' => $c['saved'] - $c['connected']]) }}</span>
                    @endif
                    @if($c['saved'] === 0)
                    <span style="background:#eef2f7; color:#374151; border-radius:20px; padding:1px 8px; font-size:8.5px; font-weight:600;">{{ __('integrations.providers_badge', ['count' => $c['total']]) }}</span>
                    @endif
                    @if(!$c['verifiable'])
                    <span style="background:#f3f4f6; color:#9ca3af; border-radius:20px; padding:1px 8px; font-size:8.5px; font-weight:600;">{{ __('integrations.verification_coming_soon_badge') }}</span>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    </div>

</div>
@endsection
