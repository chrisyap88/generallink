<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — GeneralLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <style>
        :root {
            --gl-blue:  #0D5A8E;
            --gl-blue2: #1B9AE4;
            --gl-light: #EBF5FB;
            --sidebar-w: 240px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #F4F7FB; color: #2D3748; }

        /* Sidebar */
        #sidebar {
            position: fixed; top: 0; left: 0; height: 100vh;
            width: var(--sidebar-w); background: var(--gl-blue);
            display: flex; flex-direction: column; z-index: 100;
            transition: transform 0.25s ease;
        }
        .sidebar-logo {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-logo h1 { color: #fff; font-size: 18px; font-weight: 700; letter-spacing: 0.5px; }
        .sidebar-logo p  { color: rgba(255,255,255,0.5); font-size: 11px; margin-top: 2px; }

        .sidebar-role-badge {
            margin: 12px 16px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .badge-admin    { background: #F6AD55; color: #7B341E; }
        .badge-gl       { background: #68D391; color: #1C4532; }
        .badge-tl       { background: #76E4F7; color: #065666; }
        .badge-introducer { background: #B794F4; color: #322659; }

        .sidebar-nav { flex: 1; overflow-y: auto; padding: 8px 0; }
        .nav-section-label {
            padding: 14px 20px 6px;
            font-size: 10px; font-weight: 600;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase; letter-spacing: 1px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 20px;
            color: rgba(255,255,255,0.75);
            font-size: 13.5px;
            text-decoration: none;
            border-radius: 0;
            transition: background 0.15s, color 0.15s;
            cursor: pointer;
            border: none; background: none; width: 100%; text-align: left;
        }
        .nav-item:hover  { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(255,255,255,0.15); color: #fff; border-left: 3px solid var(--gl-blue2); }
        .nav-item i { font-size: 18px; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 14px 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .agent-card {
            display: flex; align-items: center; gap: 10px;
        }
        .agent-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 13px; font-weight: 600; flex-shrink: 0;
        }
        .agent-name  { color: #fff; font-size: 13px; font-weight: 500; line-height: 1.2; }
        .agent-code  { color: rgba(255,255,255,0.45); font-size: 11px; }

        /* Topbar */
        #topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0; height: 56px;
            background: #fff; border-bottom: 1px solid #E2E8F0;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; z-index: 90;
        }
        .topbar-title { font-size: 16px; font-weight: 600; color: #1A202C; }
        .topbar-right  { display: flex; align-items: center; gap: 14px; }
        .topbar-btn {
            width: 36px; height: 36px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #718096; background: #F7FAFC; border: 1px solid #E2E8F0;
            cursor: pointer; font-size: 18px; position: relative;
            text-decoration: none;
        }
        .topbar-btn:hover { background: #EBF5FB; color: var(--gl-blue); }
        .badge-count {
            position: absolute; top: -4px; right: -4px;
            width: 16px; height: 16px; border-radius: 50%;
            background: #E53E3E; color: #fff;
            font-size: 9px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        /* Main content */
        #main {
            margin-left: var(--sidebar-w);
            padding-top: 56px;
            min-height: 100vh;
        }
        .page-content { padding: 24px; }

        /* Cards */
        .card {
            background: #fff; border-radius: 12px;
            border: 1px solid #E2E8F0; padding: 20px;
        }
        .card-title {
            font-size: 14px; font-weight: 600; color: #1A202C;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .metric-card {
            background: #fff; border-radius: 10px;
            border: 1px solid #E2E8F0; padding: 16px 20px;
        }
        .metric-label { font-size: 12px; color: #718096; margin-bottom: 4px; }
        .metric-value { font-size: 22px; font-weight: 700; color: #1A202C; }
        .metric-sub   { font-size: 11px; color: #48BB78; margin-top: 4px; }

        /* Status badges */
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600;
        }
        .status-active     { background: #C6F6D5; color: #22543D; }
        .status-inactive   { background: #FED7D7; color: #742A2A; }
        .status-terminated { background: #E2E8F0; color: #4A5568; }
        .status-risk_debt  { background: #FEEBC8; color: #7B341E; }
        .status-resigned   { background: #E9D8FD; color: #44337A; }
        .status-deceased   { background: #FED7D7; color: #742A2A; }

        /* Role colours */
        .role-admin    { color: #D97706; }
        .role-gl       { color: #059669; }
        .role-tl       { color: #0284C7; }
        .role-introducer { color: #7C3AED; }

        /* Mobile sidebar toggle */
        #sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.4); z-index: 99;
        }
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.open { transform: translateX(0); }
            #topbar { left: 0; }
            #main   { margin-left: 0; }
            #sidebar-overlay.show { display: block; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 4px; }
    </style>
    @stack('styles')
</head>
<body>

{{-- Sidebar overlay (mobile) --}}
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

{{-- ===================== SIDEBAR ===================== --}}
<nav id="sidebar">
    <div class="sidebar-logo">
        <h1>GeneralLink</h1>
        <p>Digital Ecosystem</p>
    </div>

    @php $agent = auth('agent')->user(); @endphp

    {{-- Role badge --}}
    @php
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
    @endphp
    <div class="sidebar-role-badge {{ $badgeClass }}">{{ $roleLabel }}</div>

    <div class="sidebar-nav">

        {{-- MAIN --}}
        <div class="nav-section-label">Main</div>
        <a href="{{ match($agent->role) {
            'ADMIN' => route('admin.dashboard'),
            'GROUP_LEADER' => route('gl.dashboard'),
            'TEAM_LEADER' => route('tl.dashboard'),
            default => route('introducer.dashboard'),
        } }}" class="nav-item {{ request()->routeIs('*.dashboard') ? 'active' : '' }}">
            <i class="ti ti-layout-dashboard"></i> Dashboard
        </a>

        {{-- NETWORK --}}
        <div class="nav-section-label">Network</div>

        @if($agent->isAdmin())
            <a href="#" class="nav-item"><i class="ti ti-hierarchy-2"></i> All Groups</a>
            <a href="{{ route('admin.agents.index') }}" class="nav-item {{ request()->routeIs('admin.agents.*') ? 'active' : '' }}"><i class="ti ti-users"></i> All Agents</a>
        @endif

        @if($agent->isGroupLeader())
            <a href="#" class="nav-item"><i class="ti ti-hierarchy-2"></i> My Group Tree</a>
            <a href="#" class="nav-item"><i class="ti ti-users"></i> Team Leaders</a>
            <a href="#" class="nav-item"><i class="ti ti-user"></i> Introducers</a>
        @endif

        @if($agent->isTeamLeader())
            <a href="#" class="nav-item"><i class="ti ti-hierarchy-2"></i> My Team Tree</a>
            <a href="#" class="nav-item"><i class="ti ti-user"></i> My Introducers</a>
        @endif

        @if($agent->isIntroducer())
            <a href="#" class="nav-item"><i class="ti ti-hierarchy-2"></i> My Downlines</a>
        @endif

        {{-- POLICIES & COMMISSION --}}
        <div class="nav-section-label">Business</div>
        <a href="{{ route('policies.index') }}" class="nav-item {{ request()->routeIs('policies.*') ? 'active' : '' }}"><i class="ti ti-file-invoice"></i> Policies</a>
        <a href="{{ route('points.index') }}" class="nav-item {{ request()->routeIs('points.*') ? 'active' : '' }}"><i class="ti ti-coin"></i> Commission</a>
        <a href="{{ route('points.index') }}" class="nav-item"><i class="ti ti-star"></i> Reward Points</a>
        <a href="{{ route('points.index') }}" class="nav-item {{ request()->routeIs('points.*') ? 'active' : '' }}"><i class="ti ti-arrows-exchange"></i> Transfer / Buy Points</a>

        {{-- RENEWALS --}}
        <div class="nav-section-label">Renewals</div>
        <a href="#" class="nav-item"><i class="ti ti-calendar-due"></i> Renewal Pipeline</a>

        {{-- ADMIN ONLY --}}
        @if($agent->isAdmin())
        <div class="nav-section-label">Administration</div>
        <a href="{{ route('admin.masterfile.index') }}" class="nav-item {{ request()->routeIs('admin.masterfile.*') ? 'active' : '' }}"><i class="ti ti-settings"></i> Master File</a>
        <a href="{{ route('admin.batch.index') }}" class="nav-item {{ request()->routeIs('admin.batch.*') ? 'active' : '' }}"><i class="ti ti-upload"></i> Batch Upload</a>
        <a href="#" class="nav-item"><i class="ti ti-chart-pie"></i> Reports</a>
        <a href="{{ route('admin.points.index') }}" class="nav-item {{ request()->routeIs('admin.points.*') ? 'active' : '' }}"><i class="ti ti-receipt"></i> Point Purchases</a>
        <a href="#" class="nav-item"><i class="ti ti-shield-check"></i> Audit Logs</a>
        @endif

        {{-- PROFILE --}}
        <div class="nav-section-label">Account</div>
        <a href="{{ route('profile.index') }}" class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}"><i class="ti ti-user-circle"></i> My Profile</a>
        <a href="{{ route('beneficiary.index') }}" class="nav-item {{ request()->routeIs('beneficiary.*') ? 'active' : '' }}"><i class="ti ti-heart"></i> Beneficiary</a>
        <a href="{{ route('profile.index') }}" class="nav-item"><i class="ti ti-lock"></i> Change Password</a>
    </div>

    {{-- Agent info footer --}}
    <div class="sidebar-footer">
        <div class="agent-card">
            <div class="agent-avatar">
                {{ strtoupper(substr($agent->full_name, 0, 2)) }}
            </div>
            <div style="flex:1;min-width:0">
                <div class="agent-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    {{ $agent->full_name }}
                </div>
                <div class="agent-code">{{ $agent->agent_code ?? $agent->member_code ?? '—' }}</div>
            </div>
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="topbar-btn" style="background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.6)" title="Logout">
                    <i class="ti ti-logout"></i>
                </button>
            </form>
        </div>
    </div>
</nav>

{{-- ===================== TOPBAR ===================== --}}
<header id="topbar">
    <div style="display:flex;align-items:center;gap:12px">
        {{-- Mobile hamburger --}}
        <button onclick="toggleSidebar()" style="display:none;width:36px;height:36px;border:none;background:none;cursor:pointer;font-size:22px;color:#4A5568" id="hamburger">
            <i class="ti ti-menu-2"></i>
        </button>
        <span class="topbar-title">@yield('page-title', 'Dashboard')</span>
    </div>

    <div class="topbar-right">
        {{-- Notifications --}}
        <a href="#" class="topbar-btn" title="Notifications">
            <i class="ti ti-bell"></i>
            <span class="badge-count">3</span>
        </a>
        {{-- Profile quick link --}}
        <a href="#" class="topbar-btn" title="My Profile">
            <i class="ti ti-user-circle"></i>
        </a>
        {{-- Balance display --}}
        <div style="display:flex;flex-direction:column;align-items:flex-end">
            <span style="font-size:11px;color:#718096">Commission balance</span>
            <span style="font-size:14px;font-weight:700;color:#0D5A8E">
                RM {{ number_format($agent->commission_balance, 2) }}
            </span>
        </div>
    </div>
</header>

{{-- ===================== MAIN CONTENT ===================== --}}
<main id="main">
    <div class="page-content">
        @yield('content')
    </div>
</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('show');
}
// Show hamburger on mobile
if (window.innerWidth <= 768) {
    document.getElementById('hamburger').style.display = 'flex';
}
window.addEventListener('resize', () => {
    document.getElementById('hamburger').style.display =
        window.innerWidth <= 768 ? 'flex' : 'none';
});
</script>

@stack('scripts')
</body>
</html>
