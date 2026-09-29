@extends('layouts.glade')

@section('page-title', __('cbe.glade_home_title'))

@section('content')

{{-- NEW 28 Aug 2026 — per Chris: "when i login the default is the main
     menu, not this" — this is the true landing screen after /glade
     login (see GladePortalController::home()/landingFor()). No sidebar
     group is auto-opened here (no $xxxActive flag in layouts/glade.blade.php
     matches this route), so the sidebar renders in its default collapsed
     "main menu" state and the agent picks where to go next themselves.
     Same font/colour scheme as every other GLADE screen (--gl-blue etc.,
     no new styles introduced), fits one screen with no scrolling. --}}
<style>
.gh-card{background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; padding:10px 12px; display:flex; flex-direction:column; min-width:0; box-sizing:border-box; box-shadow:0 2px 10px rgba(21,101,192,0.10);}
.gh-card-title{font-size:9px; font-weight:700; color:var(--gl-blue); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:6px; padding-bottom:5px; border-bottom:1px solid #eef2f7; display:flex; align-items:center; justify-content:space-between;}
.gh-feed-item{display:flex; gap:7px; padding:5px 0; border-bottom:1px solid #f3f4f6; align-items:center;}
.gh-feed-item:last-child{border-bottom:none;}
.gh-feed-icon{width:22px; height:22px; border-radius:6px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#fff; background:var(--gl-blue);}
.gh-feed-body{flex:1; min-width:0;}
.gh-feed-title{font-size:10.5px; font-weight:600; color:#1f2937; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
.gh-feed-desc{font-size:9.5px; color:#6b7280; margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
.gh-empty{font-size:10px; color:#9ca3af; text-align:center; padding:14px 0;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:14px 16px; box-sizing:border-box; gap:10px;">

    <div style="flex-shrink:0;">
        <div style="font-size:15px; font-weight:700; color:#263238;">
            {{ now()->format('H') < 12 ? __('cbe.good_morning') : (now()->format('H') < 18 ? __('cbe.good_afternoon') : __('cbe.good_evening')) }}, {{ $agent->full_name }}
        </div>
        <div style="font-size:10.5px; color:#6b7280; margin-top:2px;">{{ __('cbe.glade_home_hint') }}</div>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:10px;">
        <div class="gh-card" style="flex:1;">
            <div class="gh-card-title">{{ __('cbe.smart_reminders') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                @forelse($reminders as $r)
                <div class="gh-feed-item">
                    <div class="gh-feed-icon">⏰</div>
                    <div class="gh-feed-body">
                        <div class="gh-feed-title">{{ ucwords(strtolower(str_replace('_',' ',$r->reminder_type))) }}</div>
                        <div class="gh-feed-desc">{{ __('cbe.due') }} {{ \Illuminate\Support\Carbon::parse($r->reminder_date)->format('d M Y') }}</div>
                    </div>
                </div>
                @empty
                <div class="gh-empty">{{ __('cbe.no_reminders') }}</div>
                @endforelse
            </div>
        </div>

        <div class="gh-card" style="flex:1;">
            <div class="gh-card-title">{{ __('cbe.notice_board') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                @forelse($notices as $n)
                <div class="gh-feed-item">
                    <div class="gh-feed-icon">📌</div>
                    <div class="gh-feed-body">
                        <div class="gh-feed-title">{{ $n->title }}</div>
                        <div class="gh-feed-desc">{{ \Illuminate\Support\Str::limit(strip_tags($n->body), 60) }}</div>
                    </div>
                </div>
                @empty
                <div class="gh-empty">{{ __('cbe.no_notices') }}</div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
