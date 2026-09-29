{{-- One badge tier tile. NEW 25 Jul 2026 — extracted so the same tile
     markup is shared by the full-width Recruiting row and the 3-column
     TL/GL/Network row. Icon logic FIXED per Chris: driven by $b->achieved
     (current >= threshold) instead of a personal award row, so Admin's
     company-wide view never shows "100%" next to a locked icon. No lock
     icon at all — badges are automatic, so an hourglass ("in progress")
     reads correctly instead of implying something needs to be unlocked. --}}
<div class="bdg-tile" style="background:{{ $b->achieved ? 'rgba(220,252,231,0.8)' : 'rgba(255,255,255,0.55)' }}; border:1px solid {{ $b->achieved ? '#86efac' : 'rgba(15,156,150,0.2)' }};">
    <div style="font-size:15px;">{{ $b->achieved ? '✅' : '⏳' }}</div>
    <div style="font-size:9.5px; font-weight:700; color:{{ $b->achieved ? '#166534' : '#0f5c5a' }}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $b->badge_name }}</div>
    @if($b->achieved && $b->earned_row)
        <div style="font-size:7.5px; color:#166534; font-weight:600;">{{ __('growth.earned_on_date', ['date' => \Illuminate\Support\Carbon::parse($b->earned_row->achieved_at)->format('d M Y')]) }}</div>
    @elseif($b->achieved)
        <div style="font-size:7.5px; color:#166534; font-weight:600;">{{ __('growth.milestone_reached') }}</div>
    @else
        <div style="background:rgba(15,156,150,0.15); border-radius:20px; height:6px; overflow:hidden; margin-top:3px;">
            <div style="background:#0f9c96; height:100%; width:{{ $b->pct }}%;"></div>
        </div>
        <div style="font-size:7.5px; color:#607d8b; margin-top:1px;">{{ __('growth.progress_fraction', ['current' => (int) $b->current_value, 'threshold' => (int) $b->threshold_value, 'pct' => $b->pct]) }}</div>
    @endif
</div>
