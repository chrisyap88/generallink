<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — GeneralLink</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <style>
        :root{--gl-blue:#1565C0;--gl-blue2:#1E88E5;--gl-cyan:#00BCD4;--gl-cyan2:#B2EBF2;--gl-light:#E0F7FA;--sidebar-w:260px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f9ff;margin:0;padding:0;min-height:100vh;color:#263238;overflow-x:hidden;}
        #sidebar{position:fixed;top:0;left:0;height:100vh;width:calc(var(--sidebar-w) + 1px);background:linear-gradient(180deg,#1565C0 0%,#1976D2 60%,#1E88E5 100%);display:flex;flex-direction:column;z-index:100;}
        .sidebar-logo{flex-shrink:0;padding:10px 20px 8px;border-bottom:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.1);}
        .sidebar-logo h1{color:#fff;font-size:15px;font-weight:700;letter-spacing:1px;line-height:1.2;}
        .sidebar-logo p{color:rgba(255,255,255,0.55);font-size:9px;margin-top:1px;letter-spacing:0.5px;}
        .sidebar-role-badge{flex-shrink:0;margin:6px 16px;padding:4px 12px;border-radius:20px;font-size:10px;font-weight:700;text-align:center;letter-spacing:0.5px;}
        .badge-admin{background:#F6AD55;color:#7B341E;}
        .badge-gl{background:#68D391;color:#1C4532;}
        .badge-tl{background:#76E4F7;color:#065666;}
        .badge-introducer{background:#B794F4;color:#322659;}
        .sidebar-nav{flex:1;min-height:0;overflow-y:auto;padding:4px 0 8px;scrollbar-width:none;}
        .sidebar-nav::-webkit-scrollbar{display:none;}
        .nav-item{display:flex;align-items:center;gap:8px;padding:5px 20px;color:rgba(255,255,255,0.75);font-size:11px;text-decoration:none;transition:all 0.15s;border-left:3px solid transparent;line-height:1.3;}
        .nav-item:hover{background:rgba(255,255,255,0.1);color:#fff;border-left-color:rgba(255,255,255,0.3);}
        .nav-item.active{background:rgba(255,255,255,0.15);color:#fff;border-left-color:#00BCD4;}
        .nav-item i{font-size:14px;width:16px;text-align:center;}
        .nav-parent{display:flex;align-items:center;justify-content:space-between;padding:5px 20px;color:rgba(255,255,255,0.85);font-size:10px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;cursor:pointer;transition:all 0.15s;border-left:3px solid transparent;user-select:none;margin-top:4px;}
        .nav-parent:hover{background:rgba(255,255,255,0.08);color:#fff;}
        .nav-parent.open{color:#fff;}
        .nav-parent .pleft{display:flex;align-items:center;gap:6px;}
        .nav-parent .parrow{font-size:9px;transition:transform 0.25s;opacity:0.6;}
        .nav-parent.open .parrow{transform:rotate(90deg);opacity:1;}
        .nav-submenu{max-height:0;overflow:hidden;transition:max-height 0.3s ease;background:rgba(0,0,0,0.08);}
        .nav-submenu.open{max-height:500px;}
        .nav-subitem{display:flex;align-items:center;gap:8px;padding:4px 20px 4px 32px;color:rgba(255,255,255,0.7);font-size:11px;text-decoration:none;transition:all 0.15s;border-left:3px solid transparent;line-height:1.3;}
        .nav-subitem:hover{background:rgba(255,255,255,0.1);color:#fff;border-left-color:rgba(255,255,255,0.3);}
        .nav-subitem.active{background:rgba(255,255,255,0.15);color:#fff;border-left-color:#00BCD4;}
        .nav-subitem i{font-size:13px;width:15px;text-align:center;}
        .nav-deepitem{display:flex;align-items:center;gap:8px;padding:4px 20px 4px 48px;color:rgba(255,255,255,0.6);font-size:10.5px;text-decoration:none;transition:all 0.15s;border-left:3px solid transparent;line-height:1.3;}
        .nav-deepitem:hover{background:rgba(255,255,255,0.08);color:#fff;}
        .nav-deepitem.active{background:rgba(255,255,255,0.12);color:#fff;border-left-color:#00BCD4;}
        .nav-deepitem i{font-size:12px;width:14px;text-align:center;}
        .nav-subparent{display:flex;align-items:center;justify-content:space-between;padding:4px 20px 4px 32px;color:rgba(255,255,255,0.7);font-size:11px;cursor:pointer;transition:all 0.15s;border-left:3px solid transparent;line-height:1.3;user-select:none;}
        .nav-subparent:hover{background:rgba(255,255,255,0.1);color:#fff;}
        .nav-subparent.open{color:#fff;background:rgba(255,255,255,0.08);}
        .nav-subparent .spleft{display:flex;align-items:center;gap:8px;}
        .nav-subparent .sparrow{font-size:8px;transition:transform 0.2s;opacity:0.6;}
        .nav-subparent.open .sparrow{transform:rotate(90deg);opacity:1;}
        .nav-subparent i{font-size:13px;width:15px;text-align:center;}
        .nav-deep{max-height:0;overflow:hidden;transition:max-height 0.25s ease;background:rgba(0,0,0,0.08);}
        .nav-deep.open{max-height:300px;}
        .sidebar-footer{flex-shrink:0;padding:8px 16px;border-top:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.1);}
        .agent-card{display:flex;align-items:center;gap:10px;}
        .agent-avatar{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);}
        .agent-name{color:#fff;font-size:13px;font-weight:500;}
        .agent-code{color:rgba(255,255,255,0.45);font-size:11px;}
        #topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:46px;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-bottom:1px solid #B2EBF2;display:flex;align-items:center;justify-content:space-between;padding:0 16px;z-index:90;box-shadow:0 2px 10px rgba(0,150,200,0.08);}
        .topbar-title{font-size:13px;font-weight:600;color:#1565C0;}
        .topbar-right{display:flex;align-items:center;gap:10px;}
        .topbar-btn{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#1E88E5;background:#E0F7FA;border:1px solid #B2EBF2;cursor:pointer;font-size:16px;position:relative;text-decoration:none;transition:all 0.15s;}
        .topbar-btn:hover{background:#B2EBF2;color:#1565C0;}
        .badge-count{position:absolute;top:-4px;right:-4px;width:14px;height:14px;border-radius:50%;background:#F44336;color:#fff;font-size:8px;font-weight:700;display:flex;align-items:center;justify-content:center;}
        #main{position:absolute;top:46px;left:var(--sidebar-w);right:0;bottom:0;background:#E0F7FA;overflow:hidden;}
        .page-content{padding:0;height:100%;overflow:hidden;box-sizing:border-box;width:100%;}
        .card{background:rgba(255,255,255,0.85);backdrop-filter:blur(8px);border-radius:14px;border:1px solid #B2EBF2;padding:20px;box-shadow:0 4px 15px rgba(0,150,200,0.06);}
        .metric-card{background:rgba(255,255,255,0.85);border-radius:12px;border:1px solid #B2EBF2;padding:16px 20px;box-shadow:0 4px 15px rgba(0,150,200,0.06);}
        .skeleton{background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:4px;min-height:24px;}
        @keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
        #sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:99;}
        @media(max-width:768px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);}#topbar{left:0;}#main{left:0;}#sidebar-overlay.show{display:block;}}
        ::-webkit-scrollbar{width:4px;}
        ::-webkit-scrollbar-thumb{background:#B2EBF2;border-radius:4px;}
    </style>
    @stack('styles')
</head>
<body>
<div id="sidebar-overlay" onclick="closeSidebar()"></div>
<nav id="sidebar">
    <div class="sidebar-logo">
        <h1>🔗 GENERAL LINK</h1>
        <p>DIGITAL AFFILIATE ECOSYSTEM</p>
    </div>

    @php
        $agent = auth('agent')->user();
        $badgeClass = match($agent->role) {
            'ADMIN'        => 'badge-admin',
            'GROUP_LEADER' => 'badge-gl',
            'TEAM_LEADER'  => 'badge-tl',
            default        => 'badge-introducer',
        };
        $roleLabel = match($agent->role) {
            'ADMIN'        => 'Administrator',
            'GROUP_LEADER' => 'Group Leader',
            'TEAM_LEADER'  => 'Team Leader',
            default        => 'Introducer',
        };
        $profileRoute = match($agent->role) {
            'ADMIN'        => route('admin.profile.show'),
            'GROUP_LEADER' => route('gl.profile.show'),
            'TEAM_LEADER'  => route('tl.profile.show'),
            default        => route('introducer.profile.show'),
        };
        $rewardsRoute = match($agent->role) {
            'GROUP_LEADER' => route('gl.rewards'),
            'TEAM_LEADER'  => route('tl.rewards'),
            'INTRODUCER'   => route('introducer.rewards'),
            default        => '#',
        };
        $networkActive  = request()->routeIs('admin.agents*') || request()->routeIs('admin.network*') || request()->routeIs('gl.network*') || request()->routeIs('tl.introducers*') || request()->routeIs('introducer.recruits*');
        $businessActive = request()->routeIs('*.transactions*') || request()->routeIs('*.customers*') || request()->routeIs('*.commissions*') || request()->routeIs('*.rewards*');
        $renewalActive  = request()->routeIs('*.renewal*');
        $adminActive    = request()->routeIs('admin.masterfile*') || request()->routeIs('admin.vendors*') || request()->routeIs('admin.branches*') || request()->routeIs('admin.batch*') || request()->routeIs('admin.audit*');
        $accountActive  = request()->routeIs('*.profile*');
        $profileActive  = request()->routeIs($agent->role === 'ADMIN' ? 'admin.profile*' : ($agent->role === 'GROUP_LEADER' ? 'gl.profile*' : ($agent->role === 'TEAM_LEADER' ? 'tl.profile*' : 'introducer.profile*')));
        $masterFileActive = request()->routeIs('admin.masterfile*') || request()->routeIs('admin.vendors*') || request()->routeIs('admin.branches*');
    @endphp

    <div class="sidebar-role-badge {{ $badgeClass }}">{{ $roleLabel }}</div>

    <div class="sidebar-nav" id="sidebarNav">

        <a href="{{ match($agent->role) {
            'ADMIN'        => route('admin.dashboard'),
            'GROUP_LEADER' => route('gl.dashboard'),
            'TEAM_LEADER'  => route('tl.dashboard'),
            default        => route('introducer.dashboard'),
        } }}" class="nav-item {{ request()->routeIs('*.dashboard') ? 'active' : '' }}">
            <i class="ti ti-layout-dashboard"></i> Dashboard
        </a>

        {{-- NETWORK --}}
        <div class="nav-parent {{ $networkActive ? 'open' : '' }}" id="netParent" onclick="toggleSection('netMenu','netParent')">
            <div class="pleft">▶ Network Tree</div>
            <span class="parrow">▶</span>
        </div>
        <div class="nav-submenu {{ $networkActive ? 'open' : '' }}" id="netMenu">
            @if($agent->role === 'ADMIN')
                <a href="{{ route('admin.agents.index') }}" class="nav-subitem {{ request()->routeIs('admin.agents.index') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> Network Tree</a>
                <a href="{{ route('admin.agents.pending') }}" class="nav-subitem {{ request()->routeIs('admin.agents.pending') ? 'active' : '' }}"><i class="ti ti-user-question"></i> Pending Assignment</a>
                <a href="{{ route('admin.network') }}" class="nav-subitem {{ request()->routeIs('admin.network*') ? 'active' : '' }}"><i class="ti ti-sitemap"></i> Network Drill Down</a>
            @elseif($agent->role === 'GROUP_LEADER')
                <a href="{{ route('gl.network') }}" class="nav-subitem {{ request()->routeIs('gl.network*') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> My Group Tree</a>
            @elseif($agent->role === 'TEAM_LEADER')
                <a href="{{ route('tl.introducers') }}" class="nav-subitem {{ request()->routeIs('tl.introducers*') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> My Team Tree</a>
            @else
                <a href="{{ route('introducer.recruits') }}" class="nav-subitem {{ request()->routeIs('introducer.recruits*') ? 'active' : '' }}"><i class="ti ti-hierarchy-2"></i> My Recruits</a>
            @endif
        </div>

        {{-- BUSINESS --}}
        <div class="nav-parent {{ $businessActive ? 'open' : '' }}" id="bizParent" onclick="toggleSection('bizMenu','bizParent')">
            <div class="pleft">▶ Business</div>
            <span class="parrow">▶</span>
        </div>
        <div class="nav-submenu {{ $businessActive ? 'open' : '' }}" id="bizMenu">
            @if($agent->role === 'ADMIN')
                <a href="#" class="nav-subitem"><i class="ti ti-file-invoice"></i> Transactions</a>
                <a href="#" class="nav-subitem"><i class="ti ti-users"></i> Customers</a>
                <a href="#" class="nav-subitem"><i class="ti ti-coin"></i> Commission</a>
                <a href="#" class="nav-subitem"><i class="ti ti-star"></i> Reward Points</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> Transfer / Buy Points</a>
                <a href="#" class="nav-subitem"><i class="ti ti-receipt"></i> Point Purchases</a>
            @elseif($agent->role === 'GROUP_LEADER')
                <a href="{{ route('gl.transactions') }}" class="nav-subitem {{ request()->routeIs('gl.transactions*') ? 'active' : '' }}"><i class="ti ti-file-invoice"></i> Transactions</a>
                <a href="{{ route('gl.customers.index') }}" class="nav-subitem {{ request()->routeIs('gl.customers*') ? 'active' : '' }}"><i class="ti ti-users"></i> Customers</a>
                <a href="{{ route('gl.commissions.index') }}" class="nav-subitem {{ request()->routeIs('gl.commissions*') ? 'active' : '' }}"><i class="ti ti-coin"></i> Commission</a>
                <a href="{{ $rewardsRoute }}" class="nav-subitem {{ request()->routeIs('gl.rewards*') ? 'active' : '' }}"><i class="ti ti-star"></i> Reward Points</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> Transfer / Buy Points</a>
            @elseif($agent->role === 'TEAM_LEADER')
                <a href="{{ route('tl.transactions') }}" class="nav-subitem {{ request()->routeIs('tl.transactions*') ? 'active' : '' }}"><i class="ti ti-file-invoice"></i> Transactions</a>
                <a href="#" class="nav-subitem"><i class="ti ti-coin"></i> Commission</a>
                <a href="{{ $rewardsRoute }}" class="nav-subitem {{ request()->routeIs('tl.rewards*') ? 'active' : '' }}"><i class="ti ti-star"></i> Reward Points</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> Transfer / Buy Points</a>
            @else
                <a href="#" class="nav-subitem"><i class="ti ti-file-invoice"></i> Transactions</a>
                <a href="#" class="nav-subitem"><i class="ti ti-coin"></i> Commission</a>
                <a href="{{ $rewardsRoute }}" class="nav-subitem {{ request()->routeIs('introducer.rewards*') ? 'active' : '' }}"><i class="ti ti-star"></i> Reward Points</a>
                <a href="#" class="nav-subitem"><i class="ti ti-arrows-exchange"></i> Transfer / Buy Points</a>
            @endif
        </div>

        {{-- RENEWALS --}}
        <div class="nav-parent {{ $renewalActive ? 'open' : '' }}" id="renParent" onclick="toggleSection('renMenu','renParent')">
            <div class="pleft">▶ Renewals</div>
            <span class="parrow">▶</span>
        </div>
        <div class="nav-submenu {{ $renewalActive ? 'open' : '' }}" id="renMenu">
            <a href="#" class="nav-subitem {{ $renewalActive ? 'active' : '' }}"><i class="ti ti-calendar-due"></i> Renewal Overdue / Pending</a>
        </div>

        {{-- ADMINISTRATION (Admin only) --}}
        @if($agent->role === 'ADMIN')
        <div class="nav-parent {{ $adminActive ? 'open' : '' }}" id="admParent" onclick="toggleSection('admMenu','admParent')">
            <div class="pleft">▶ Administration</div>
            <span class="parrow">▶</span>
        </div>
        <div class="nav-submenu {{ $adminActive ? 'open' : '' }}" id="admMenu">
            <div class="nav-subparent {{ $masterFileActive ? 'open' : '' }}" id="mfParent" onclick="toggleDeep('mfMenu','mfParent',event)">
                <div class="spleft"><i class="ti ti-settings"></i> Master File</div>
                <span class="sparrow">▶</span>
            </div>
            <div class="nav-deep {{ $masterFileActive ? 'open' : '' }}" id="mfMenu">
                <div class="nav-subparent {{ request()->routeIs('admin.vendors*') || request()->routeIs('admin.branches*') ? 'open' : '' }}" id="vmParent" onclick="toggleDeep('vmMenu','vmParent',event)">
                    <div class="spleft"><i class="ti ti-building-store"></i> Vendor Maintenance</div>
                    <span class="sparrow">▶</span>
                </div>
                <div class="nav-deep {{ request()->routeIs('admin.vendors*') || request()->routeIs('admin.branches*') ? 'open' : '' }}" id="vmMenu">
                    <a href="{{ route('admin.vendors.index', ['mode'=>'main']) }}" class="nav-deepitem {{ request()->routeIs('admin.vendors*') ? 'active' : '' }}" style="padding-left:60px;"><i class="ti ti-building"></i> Head Office / Main Outlet</a>
                    <a href="{{ route('admin.branches.index') }}" class="nav-deepitem {{ request()->routeIs('admin.branches*') ? 'active' : '' }}" style="padding-left:60px;"><i class="ti ti-git-branch"></i> Branch / Outlet</a>
                </div>
                <div class="nav-subparent {{ request()->routeIs('admin.masterfile.introducers*') || request()->routeIs('admin.masterfile.team-leaders*') || request()->routeIs('admin.masterfile.group-leaders*') ? 'open' : '' }}" id="tsParent" onclick="toggleDeep('tsMenu','tsParent',event)">
                    <div class="spleft"><i class="ti ti-sitemap"></i> Tier Structure Maintenance</div>
                    <span class="sparrow">▶</span>
                </div>
                <div class="nav-deep {{ request()->routeIs('admin.masterfile.introducers*') || request()->routeIs('admin.masterfile.team-leaders*') || request()->routeIs('admin.masterfile.group-leaders*') ? 'open' : '' }}" id="tsMenu">
                    <a href="{{ route('admin.masterfile.introducers') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.introducers*') ? 'active' : '' }}" style="padding-left:60px;"><i class="ti ti-user"></i> Introducer Maintenance</a>
                    <a href="{{ route('admin.masterfile.team-leaders') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.team-leaders*') ? 'active' : '' }}" style="padding-left:60px;"><i class="ti ti-users"></i> Team Leader Maintenance</a>
                    <a href="{{ route('admin.masterfile.group-leaders') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.group-leaders*') ? 'active' : '' }}" style="padding-left:60px;"><i class="ti ti-users-group"></i> Group Leader Maintenance</a>
                </div>
                <a href="{{ route('admin.masterfile.products') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.products') ? 'active' : '' }}"><i class="ti ti-package"></i> Products</a>
                <a href="{{ route('admin.masterfile.commissions') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.commissions') ? 'active' : '' }}"><i class="ti ti-percentage"></i> Commission Structures</a>
                <a href="{{ route('admin.masterfile.reward-rates') }}" class="nav-deepitem {{ request()->routeIs('admin.masterfile.reward-rates') ? 'active' : '' }}"><i class="ti ti-star"></i> Reward Rates</a>
            </div>
            <a href="#" class="nav-subitem"><i class="ti ti-upload"></i> Batch Upload</a>
            <a href="#" class="nav-subitem"><i class="ti ti-shield-check"></i> Audit Logs</a>
        </div>
        @endif

        {{-- MY ACCOUNT --}}
        <div class="nav-parent {{ $accountActive ? 'open' : '' }}" id="accParent" onclick="toggleSection('accMenu','accParent')">
            <div class="pleft">▶ My Account</div>
            <span class="parrow">▶</span>
        </div>
        <div class="nav-submenu {{ $accountActive ? 'open' : '' }}" id="accMenu">
            <a href="{{ $profileRoute }}" class="nav-subitem {{ $profileActive ? 'active' : '' }}"><i class="ti ti-id"></i> My Profile</a>
            <a href="#" class="nav-subitem"><i class="ti ti-heart"></i> Beneficiary</a>
        </div>

    </div>

    <div class="sidebar-footer">
        <div class="agent-card">
            <div class="agent-avatar">{{ strtoupper(substr($agent->full_name,0,2)) }}</div>
            <div style="flex:1;min-width:0">
                <div class="agent-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $agent->full_name }}</div>
                <div class="agent-code">{{ $agent->agent_code ?? $agent->member_code ?? '—' }}</div>
            </div>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.7);width:32px;height:32px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px" title="Logout">
                    <i class="ti ti-logout"></i>
                </button>
            </form>
        </div>
    </div>
</nav>

<header id="topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button onclick="toggleSidebar()" style="display:none;width:36px;height:36px;border:none;background:none;cursor:pointer;font-size:22px;color:#1E88E5" id="hamburger">
            <i class="ti ti-menu-2"></i>
        </button>
        <span class="topbar-title">@yield('page-title','Dashboard')</span>
    </div>
    <div class="topbar-right">
        <a href="#" class="topbar-btn" title="Notifications">
            <i class="ti ti-bell"></i>
            <span class="badge-count">3</span>
        </a>
        <a href="{{ $profileRoute }}" class="topbar-btn" title="My Profile">
            <i class="ti ti-user-circle"></i>
        </a>
    </div>
</header>

<main id="main">
    <div class="page-content">
        @yield('content')
    </div>
</main>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebar-overlay').classList.toggle('show');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebar-overlay').classList.remove('show');}
if(window.innerWidth<=768)document.getElementById('hamburger').style.display='flex';
window.addEventListener('resize',()=>{document.getElementById('hamburger').style.display=window.innerWidth<=768?'flex':'none';});

function toggleSection(menuId, parentId) {
    const menu   = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    ['netMenu','bizMenu','renMenu','admMenu','accMenu'].forEach(id => {
        if (id !== menuId) { const m = document.getElementById(id); if (m) m.classList.remove('open'); }
    });
    ['netParent','bizParent','renParent','admParent','accParent'].forEach(id => {
        if (id !== parentId) { const p = document.getElementById(id); if (p) p.classList.remove('open'); }
    });
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    if (!isOpen) {
        setTimeout(() => {
            const nav = document.getElementById('sidebarNav');
            const parentEl = document.getElementById(parentId);
            const navRect = nav.getBoundingClientRect();
            const parentRect = parentEl.getBoundingClientRect();
            nav.scrollTo({ top: nav.scrollTop + parentRect.top - navRect.top - 10, behavior: 'smooth' });
        }, 50);
    }
}

function toggleDeep(menuId, parentId, event) {
    event.stopPropagation();
    const menu   = document.getElementById(menuId);
    const parent = document.getElementById(parentId);
    if (!menu || !parent) return;
    const isOpen = menu.classList.contains('open');
    menu.classList.toggle('open', !isOpen);
    parent.classList.toggle('open', !isOpen);
    if (!isOpen) {
        setTimeout(() => {
            const nav = document.getElementById('sidebarNav');
            const parentEl = document.getElementById(parentId);
            const navRect = nav.getBoundingClientRect();
            const parentRect = parentEl.getBoundingClientRect();
            nav.scrollTo({ top: nav.scrollTop + parentRect.top - navRect.top - 10, behavior: 'smooth' });
        }, 50);
    }
}
</script>
@stack('scripts')
<script src="/js/address-lookup.js"></script>
</body>
</html>
