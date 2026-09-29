<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GLADE — Ecosystem Home</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    html, body { height:100%; }
    /* Same soft mint-to-white tint used behind your existing GeneralLink
       login page, so this screen feels like the same family of screens
       rather than an invented new palette. */
    body { background:linear-gradient(160deg,#e0f7fa 0%,#f5fcfb 35%,#ffffff 100%); color:#1f2937; font-size:12.5px; line-height:1.35; overflow-y:auto; }
    a { color:inherit; text-decoration:none; }

    /* ---------- Preview banner ---------- */
    .preview-banner { background:#fff3cd; color:#7a5b00; text-align:center; padding:4px; font-size:10.5px; font-weight:600; border-bottom:1px solid #f0d98c; }

    /* ---------- Top bar ---------- */
    .topbar { display:flex; align-items:center; gap:12px; background:rgba(255,255,255,.9); border-bottom:1px solid #d1e9e8; padding:5px 16px; height:36px; }
    .topbar .logo { display:flex; align-items:center; gap:6px; font-weight:700; font-size:14px; color:#1565C0; }
    .topbar .logo .mark { width:20px; height:20px; border-radius:50%; background:radial-gradient(circle at 35% 35%, #4a9de8, #1565C0); display:inline-block; }
    .topbar .search { flex:1; max-width:300px; }
    .topbar .search input { width:100%; border:1px solid #d1d5db; border-radius:20px; padding:5px 12px; font-size:11px; }
    .topbar .spacer { flex:1; }
    .topbar .icon-btn { position:relative; width:28px; height:28px; border-radius:50%; background:#f0f4f8; display:flex; align-items:center; justify-content:center; font-size:12px; cursor:pointer; }
    .topbar .icon-btn .dot { position:absolute; top:2px; right:2px; width:6px; height:6px; border-radius:50%; background:#1565C0; }
    .topbar .lang { font-size:10px; font-weight:600; color:#374151; border:1px solid #d1d5db; border-radius:14px; padding:3px 9px; cursor:pointer; }
    .topbar .avatar { width:28px; height:28px; border-radius:50%; background:#1565C0; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:11px; }

    /* ---------- Layout ---------- */
    .shell { display:flex; height:calc(100vh - 36px); }
    /* Light mint rail (matches the login page's left panel tint)
       instead of a solid dark block — navy is only for the icons
       themselves and the active-state pill, same accent-only role
       navy plays on the login page's buttons. */
    /* Same blue gradient as the real Admin/GL/TL/Introducer sidebar
       (#1565C0 -> #1976D2 -> #1E88E5), not an invented color. */
    .icon-rail { width:48px; background:linear-gradient(180deg,#1565C0 0%,#1976D2 60%,#1E88E5 100%); display:flex; flex-direction:column; align-items:center; padding:10px 0; gap:12px; flex-shrink:0; }
    .icon-rail .r-icon { width:28px; height:28px; border-radius:8px; background:rgba(255,255,255,.15); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; cursor:pointer; }
    .icon-rail .r-icon.active { background:#fff; color:#1565C0; }

    .sidebar { width:200px; background:linear-gradient(180deg,#1565C0 0%,#1976D2 60%,#1E88E5 100%); padding:10px 8px; overflow-y:auto; flex-shrink:0; }
    .sb-section { font-size:9px; font-weight:700; color:rgba(255,255,255,.6); text-transform:uppercase; letter-spacing:.04em; margin:9px 6px 3px; }
    .sb-section:first-child { margin-top:0; }
    /* Same white-text-on-blue pattern as the real sidebar's .nav-item. */
    .sb-item { display:flex; align-items:center; gap:7px; padding:5px 6px; border-radius:6px; font-size:11px; color:rgba(255,255,255,.85); font-weight:600; cursor:pointer; }
    .sb-item:hover { background:rgba(255,255,255,.12); color:#fff; }
    .sb-item .tag { margin-left:auto; font-size:8px; background:rgba(255,255,255,.2); color:#fff; border-radius:8px; padding:1px 5px; font-weight:600; }
    .sb-item.live .tag { background:#e3f2fd; color:#1565C0; }

    /* Safety net: never clip/hide content. Everything is sized to fit
       one screen on a normal monitor, but if a smaller window makes it
       not quite fit, this scrolls rather than silently cutting data off. */
    .main { flex:1; padding:6px 14px; overflow-y:auto; display:flex; flex-direction:column; min-width:0; min-height:0; }
    .content-split { min-height:160px; }

    /* ---------- Profile header (collapsible) ----------
       Same light mint-to-white tint as the login page, with navy text —
       not a solid dark/blue block. Navy and blue are reserved for text,
       the verified badge, and the buttons, same as the login screen. */
    .profile-header { background:linear-gradient(120deg,#e0f7fa,#ffffff); border:1px solid #d1e9e8; border-radius:10px; overflow:hidden; margin-bottom:10px; color:#1565C0; flex-shrink:0; }
    .cover { height:32px; position:relative; }
    .profile-block { display:flex; align-items:flex-end; gap:10px; padding:0 14px 6px; margin-top:-16px; }
    .profile-avatar { width:38px; height:38px; border-radius:50%; background:#1565C0; color:#fff; border:2px solid #fff; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; flex-shrink:0; }
    .profile-name { font-size:13.5px; font-weight:700; display:flex; align-items:center; gap:5px; color:#1565C0; }
    .verified { color:#fff; background:#0F7A5C; border-radius:50%; width:14px; height:14px; display:inline-flex; align-items:center; justify-content:center; font-size:9px; }
    .profile-sub { font-size:10px; color:#2E7D32; margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .profile-actions { margin-left:auto; padding-bottom:2px; display:flex; gap:6px; flex-shrink:0; }
    .btn { border:none; border-radius:5px; padding:5px 11px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap; }
    .btn-primary { background:#1565C0; color:#fff; }
    .btn-ghost { background:#fff; border:1px solid #b2ebf2; color:#1565C0; }

    .greeting { font-size:13.5px; font-weight:700; margin-bottom:1px; flex-shrink:0; color:#1565C0; }
    /* Small green accent, same role as the "Sign in to your GeneralLink
       account" subtitle on the login page — never a dominant color. */
    .greeting-sub { font-size:10px; color:#2E7D32; margin-bottom:6px; flex-shrink:0; }

    /* ---------- Cards / feed ---------- */
    .row { display:flex; gap:10px; margin-bottom:6px; flex-shrink:0; }
    .card { background:#fff; border:1px solid #d1e9e8; border-radius:8px; padding:7px 9px; }
    .card-title { font-size:10.5px; font-weight:700; color:#1f2937; margin-bottom:4px; display:flex; align-items:center; }
    .card-title .view-all { margin-left:auto; font-size:9.5px; font-weight:600; color:#1565C0; }

    .mw-cards { display:flex; gap:8px; }
    .mw-card { flex:1; border-radius:7px; padding:5px 10px; }
    .mw-blue { background:#e3f2fd; }
    .mw-coral { background:#e3f2fd; }
    .mw-num { font-size:15px; font-weight:700; color:#1565C0; }
    .mw-label { font-size:9.5px; color:#4b5563; margin-top:1px; }

    .content-split { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; flex:1; min-height:0; }
    .content-split .card { overflow-y:auto; height:100%; }

    .feed-item { display:flex; gap:7px; padding:5px 0; border-bottom:1px solid #f3f4f6; }
    .feed-item:last-child { border-bottom:none; }
    .feed-icon { width:22px; height:22px; border-radius:6px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#fff; }
    .feed-body { flex:1; min-width:0; }
    .feed-title { font-size:10.5px; font-weight:600; color:#1f2937; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .feed-desc { font-size:9.5px; color:#6b7280; margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .prio { font-size:8px; font-weight:700; border-radius:8px; padding:1px 6px; flex-shrink:0; height:fit-content; }
    .prio-urgent { background:#e3f2fd; color:#1565C0; }
    .prio-important { background:#e3f2fd; color:#1565C0; }
    .prio-reminder { background:#e3f2fd; color:#1565C0; }
    .prio-info { background:#f3f4f6; color:#6b7280; }

    /* Per Chris: solid blue with bold white text. */
    .level-chip { display:inline-block; background:#1565C0; color:#fff; border-radius:12px; padding:3px 9px; font-size:10px; font-weight:700; margin:2px 4px 2px 0; }
    .level-chip.mine { background:#1565C0; color:#fff; }

    .quick-actions { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
    /* Per Chris: same solid blue + bold white text as the Hierarchy chips. */
    .qa-btn { background:#1565C0; border:none; border-radius:7px; padding:7px; text-align:center; font-size:10px; font-weight:700; color:#fff; }

    .empty-note { font-size:10px; color:#9ca3af; text-align:center; padding:10px 0; }

    /* ---------- Carolyn bubble ---------- */
    {{-- MOVED 28 Aug 2026 — per Chris: "put carolyn in the space that
    not cover the display records" — bottom-right sits on top of this
    screen's own feed/card content, same problem as the shared widget.
    Moved just below the topbar instead (top-right, same corner used
    everywhere else now), which stays clear on every screen. --}}
    .carolyn { position:fixed; top:46px; right:16px; width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#E3F2FD,#90CAF9); display:flex; align-items:center; justify-content:center; font-weight:700; color:#1565C0; box-shadow:0 4px 14px rgba(0,0,0,.18); cursor:pointer; font-size:12px; }
</style>
</head>
<body>

@if($isPreview)
<div class="preview-banner">{{ __('cbe.preview_banner') }}</div>
@endif

<div class="topbar">
    <div class="logo"><span class="mark"></span> GLADE</div>
    <div class="search"><input type="text" placeholder="{{ __('cbe.search_placeholder') }}"></div>
    <div class="spacer"></div>
    <div class="icon-btn">🔔<span class="dot"></span></div>
    <div class="icon-btn">✉<span class="dot"></span></div>
    {{-- NEW 18 Aug 2026 — same quick-switch pattern as the main sidebar's
         globe icon (writes to the identical agents.preferred_language
         column), so a CBE member can flip this screen to Chinese. --}}
    <div style="position:relative;">
        <a href="#" class="lang" title="{{ __('nav.language_tooltip') }}" onclick="toggleCbeLangDropdown(event)">{{ ['EN'=>'EN','MS'=>'BM','ZH'=>'中文'][$agent->preferred_language ?? 'EN'] ?? 'EN' }} ▾</a>
        <div id="cbeLangDropdown" style="display:none; position:absolute; top:130%; right:0; width:150px; background:#fff; border:1px solid #d1d5db; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:200; overflow:hidden;">
            @foreach(['EN' => 'English', 'MS' => 'Bahasa Malaysia', 'ZH' => '中文 (Chinese)'] as $code => $label)
            <form method="POST" action="{{ route('language.quick-switch') }}">
                @csrf
                <input type="hidden" name="language" value="{{ $code }}">
                <button type="submit" style="width:100%; text-align:left; background:{{ ($agent->preferred_language ?? 'EN') === $code ? '#e3f2fd' : '#fff' }}; border:none; padding:7px 12px; font-size:10.5px; color:#374151; cursor:pointer; {{ ($agent->preferred_language ?? 'EN') === $code ? 'font-weight:700; color:#1565C0;' : '' }}">
                    {{ ($agent->preferred_language ?? 'EN') === $code ? '✓ ' : '' }}{{ $label }}
                </button>
            </form>
            @endforeach
        </div>
    </div>
    <div class="avatar">{{ $agent ? strtoupper(substr($agent->full_name,0,1)) : 'CY' }}</div>
</div>

<div class="shell">
    <div class="icon-rail">
        <div class="r-icon active" title="{{ __('cbe.rail_home') }}">H</div>
        <div class="r-icon" title="{{ __('cbe.rail_profile') }}">P</div>
        <div class="r-icon" title="{{ __('cbe.rail_reports') }}">R</div>
        <div class="r-icon" title="{{ __('cbe.rail_communication') }}">C</div>
        <div class="r-icon" title="{{ __('cbe.rail_growth') }}">G</div>
    </div>

    {{-- NEW 28 Aug 2026 — per Chris: "check every module program those
         under soon do it immediately to complete." The items below are
         wired to the exact same live routes GLADE's own sidebar already
         uses (generic auth:agent routes, not role-gated), so a CBE
         member landing here (via the normal /login page, not /glade)
         gets working screens too.
         My Network, Customers (CRM), Customer Referrals, Submit/Maintain
         Sales Transaction, My Referral Link, and Marketplace stay "soon"
         on purpose — they're DSG/ORG commission & referral concepts, and
         CBE explicitly has no commission/promotion structure (see the
         Phase 1 note atop CbeDashboardController). Building them here
         would contradict that design. Document Credit stays "soon" too
         — its CBE applicability hasn't been confirmed with Chris yet. --}}
    @php($profileRoute = $agent ? match($agent->role) {
        'ADMIN'        => route('admin.profile.show'),
        'GROUP_LEADER' => route('gl.profile.show'),
        'TEAM_LEADER'  => route('tl.profile.show'),
        default        => route('introducer.profile.show'),
    } : null)
    <div class="sidebar">
        <div class="sb-section">{{ __('cbe.sb_overview') }}</div>
        <a class="sb-item live"><span>🏠</span> {{ __('cbe.sb_ecosystem_home') }}</a>
        <a href="{{ route('cbe.exec-dashboard') }}" class="sb-item live"><span>🎯</span> {{ __('cbe.sb_exec_dashboard') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_network_tree') }}</div>
        <a class="sb-item soon"><span>🌳</span> {{ __('cbe.sb_my_network') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_customer_relationship') }}</div>
        <a class="sb-item soon"><span>👥</span> {{ __('cbe.sb_customers') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>
        <a href="{{ route('customer-kpi.index') }}" class="sb-item live"><span>📊</span> {{ __('cbe.sb_customer_kpi') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a class="sb-item soon"><span>🔗</span> {{ __('cbe.sb_customer_referrals') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>
        <a href="{{ route('support-tickets.index') }}" class="sb-item live"><span>🎫</span> {{ __('cbe.sb_support_tickets') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_business') }}</div>
        <a class="sb-item soon"><span>🧾</span> {{ __('cbe.sb_submit_sales_transaction') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>
        <a class="sb-item soon"><span>📋</span> {{ __('cbe.sb_sales_transaction_maint') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_communication') }}</div>
        <a class="sb-item live"><span>🛎️</span> {{ __('cbe.sb_help_desk') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a class="sb-item live"><span>📌</span> {{ __('cbe.sb_notice_board') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a class="sb-item live"><span>⏰</span> {{ __('cbe.sb_reminders') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_master_file_maintenance') }}</div>
        <a class="sb-item live"><span>🏛️</span> {{ __('cbe.sb_hierarchy_levels') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_records_reports') }}</div>
        <a href="{{ route('cbe.minutes.index') }}" class="sb-item live"><span>📝</span> {{ __('cbe.sb_meeting_minutes') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a href="{{ route('cbe.activities.index') }}" class="sb-item live"><span>🎉</span> {{ __('cbe.sb_activities') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a href="{{ route('cbe.finance.index') }}" class="sb-item live"><span>🏦</span> {{ __('cbe.sb_bank_statement') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a href="{{ route('cbe.annual-report.index') }}" class="sb-item live"><span>📊</span> {{ __('cbe.sb_annual_report') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a href="{{ route('cbe.accounting.index') }}" class="sb-item live"><span>📒</span> {{ __('cbe.sb_accounting') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_events_donations') }}</div>
        <a href="{{ route('cbe.events.index') }}" class="sb-item live"><span>🎪</span> {{ __('cbe.sb_events') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a href="{{ route('cbe.donors.index') }}" class="sb-item live"><span>🙏</span> {{ __('cbe.sb_donor_register') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_growth_outreach') }}</div>
        <a class="sb-item soon"><span>🔗</span> {{ __('cbe.sb_my_referral_link') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>
        <a class="sb-item soon"><span>🛍️</span> {{ __('cbe.sb_marketplace') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>
        <a href="{{ route('content-submission.create') }}" class="sb-item live"><span>📢</span> {{ __('cbe.sb_submit_marketing_content') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a href="{{ route('survey-send.create') }}" class="sb-item live"><span>📝</span> {{ __('cbe.sb_send_survey') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>

        <div class="sb-section">{{ __('cbe.sb_my_account') }}</div>
        @if($profileRoute)
        <a href="{{ $profileRoute }}" class="sb-item live"><span>👤</span> {{ __('cbe.sb_my_profile') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        @else
        <a class="sb-item soon"><span>👤</span> {{ __('cbe.sb_my_profile') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>
        @endif
        <a href="{{ route('integrations.index') }}" class="sb-item live"><span>🔌</span> {{ __('cbe.sb_my_integrations') }} <span class="tag">{{ __('cbe.tag_live') }}</span></a>
        <a class="sb-item soon"><span>📄</span> {{ __('cbe.sb_document_credit') }} <span class="tag">{{ __('cbe.tag_soon') }}</span></a>
    </div>

    <div class="main">

        <div class="profile-header">
            <div class="cover"></div>
            <div class="profile-block">
                <div class="profile-avatar">{{ $agent ? strtoupper(substr($agent->full_name,0,1)) : 'CY' }}</div>
                <div>
                    <div class="profile-name">{{ $agent->full_name ?? 'Chris Yap' }} <span class="verified">✓</span></div>
                    <div class="profile-sub">{{ $groupLabel->group_name ?? __('cbe.community_fallback') }}{{ $myLevelName ? ' · '.$myLevelName : '' }}</div>
                </div>
                <div class="profile-actions">
                    <button class="btn btn-ghost">{{ __('cbe.close_profile_header') }}</button>
                    <button class="btn btn-primary">{{ __('cbe.edit_profile') }}</button>
                </div>
            </div>
        </div>

        <div class="greeting">{{ now()->format('H') < 12 ? __('cbe.good_morning') : (now()->format('H') < 18 ? __('cbe.good_afternoon') : __('cbe.good_evening')) }}, {{ $agent->full_name ?? 'Chris' }}</div>
        <div class="greeting-sub">{{ __('cbe.greeting_sub') }}</div>

        <div class="row">
            <div class="card" style="flex:1;">
                <div class="card-title">{{ __('cbe.my_world') }}</div>
                <div class="mw-cards">
                    <div class="mw-card mw-blue">
                        <div class="mw-num">{{ $reminders->count() }}</div>
                        <div class="mw-label">{{ __('cbe.reminders_due') }}</div>
                    </div>
                    <div class="mw-card mw-coral">
                        <div class="mw-num">{{ $notices->count() }}</div>
                        <div class="mw-label">{{ __('cbe.new_notices') }}</div>
                    </div>
                </div>
            </div>
            <div class="card" style="width:280px;">
                <div class="card-title">{{ __('cbe.quick_actions') }}</div>
                <div class="quick-actions">
                    <div class="qa-btn">{{ __('cbe.qa_edit_my_profile') }}</div>
                    <div class="qa-btn">{{ __('cbe.qa_help_desk') }}</div>
                    <div class="qa-btn">{{ __('cbe.qa_view_notices') }}</div>
                    <div class="qa-btn">{{ __('cbe.qa_my_reminders') }}</div>
                </div>
            </div>
        </div>

        <div class="content-split">
            <div class="card">
                <div class="card-title">{{ __('cbe.smart_reminders') }} <a class="view-all">{{ __('cbe.view_all') }}</a></div>
                @forelse($reminders as $r)
                <div class="feed-item">
                    <div class="feed-icon" style="background:#1565C0;">⏰</div>
                    <div class="feed-body">
                        <div class="feed-title">{{ ucwords(strtolower(str_replace('_',' ',$r->reminder_type))) }}</div>
                        <div class="feed-desc">{{ __('cbe.due') }} {{ \Illuminate\Support\Carbon::parse($r->reminder_date)->format('d M Y') }}</div>
                    </div>
                    <span class="prio prio-reminder">{{ __('cbe.reminder_badge') }}</span>
                </div>
                @empty
                <div class="empty-note">{{ __('cbe.no_reminders') }}</div>
                @endforelse
            </div>

            <div class="card">
                <div class="card-title">{{ __('cbe.notice_board') }} <a class="view-all">{{ __('cbe.view_all') }}</a></div>
                @forelse($notices as $n)
                <div class="feed-item">
                    <div class="feed-icon" style="background:#1565C0;">📌</div>
                    <div class="feed-body">
                        <div class="feed-title">{{ $n->title }}</div>
                        <div class="feed-desc">{{ \Illuminate\Support\Str::limit(strip_tags($n->body), 60) }}</div>
                    </div>
                    <span class="prio prio-info">{{ ucwords(strtolower(str_replace('_',' ',$n->category))) }}</span>
                </div>
                @empty
                <div class="empty-note">{{ __('cbe.no_notices') }}</div>
                @endforelse
            </div>

            <div class="card">
                <div class="card-title">{{ __('cbe.hierarchy_levels') }}</div>
                @forelse($hierarchyLevels as $lvl)
                    <span class="level-chip {{ ($myLevelName ?? null) === $lvl->level_name ? 'mine' : '' }}">{{ $lvl->level_order }}. {{ $lvl->level_name }}</span>
                @empty
                    <div class="empty-note">{{ __('cbe.no_hierarchy_levels') }}</div>
                @endforelse
            </div>
        </div>

    </div>
</div>

<div class="carolyn" title="Carolyn">C</div>

<script>
function toggleCbeLangDropdown(e){
    e.preventDefault();
    var d = document.getElementById('cbeLangDropdown');
    d.style.display = (d.style.display === 'none' || !d.style.display) ? 'block' : 'none';
    document.addEventListener('click', function closeIt(ev){
        if (!d.contains(ev.target) && ev.target.id !== 'cbeLangDropdown') {
            d.style.display = 'none';
            document.removeEventListener('click', closeIt);
        }
    });
}
</script>

</body>
</html>
