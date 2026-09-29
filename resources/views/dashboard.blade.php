<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeneralLink — Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body style="background:#f0fbff;">

<!-- TOP NAVIGATION -->
<nav style="background: linear-gradient(135deg, #0D5A8E, #1B9AE4);" class="shadow-lg">
    <div class="flex items-center justify-between px-6 py-3">
        <!-- Logo -->
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/generallink-logo.jpg') }}"
                 alt="GeneralLink"
                 class="h-12 rounded-xl shadow">
        </div>
        <!-- Right Menu -->
        <div class="flex items-center gap-4">
            <span class="text-white text-sm">
                👋 Welcome, <strong>{{ auth()->user()->name }}</strong>
            </span>
            <span class="text-blue-200 text-xs px-3 py-1 rounded-full"
                  style="background: rgba(255,255,255,0.2);">
                {{ ucfirst(auth()->user()->role ?? 'Agent') }}
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="text-white text-xs px-4 py-2 rounded-xl hover:opacity-80"
                        style="background: rgba(255,255,255,0.2);">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>
</nav>

<div class="flex">

<!-- LEFT SIDEBAR -->
<div class="w-64 min-h-screen shadow-lg" style="background: #0D5A8E;">
    <div class="p-4">

        <!-- Agent Info -->
        <div class="p-4 rounded-xl mb-6 text-center" style="background: rgba(255,255,255,0.1);">
            <div class="w-16 h-16 rounded-full mx-auto mb-2 flex items-center justify-center text-2xl font-bold text-white"
                 style="background: #1B9AE4;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <p class="text-white text-sm font-bold">{{ auth()->user()->name }}</p>
            <p class="text-blue-200 text-xs">{{ auth()->user()->email }}</p>
            <span class="text-xs px-2 py-1 rounded-full mt-2 inline-block"
                  style="background: #38A169; color: white;">
                {{ ucfirst(auth()->user()->role ?? 'Introducer') }}
            </span>
        </div>

        <!-- Menu Items -->
        <ul class="space-y-1">
            <li>
                <a href="/dashboard"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-white text-sm font-medium"
                   style="background: rgba(255,255,255,0.2);">
                    <i class="fas fa-home w-5"></i> Dashboard
                </a>
            </li>
            <li class="pt-3">
                <p class="text-blue-300 text-xs px-4 mb-2 uppercase tracking-widest">Affiliate</p>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-users w-5"></i> My Team
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-qrcode w-5"></i> My QR Code
                </a>
            </li>
            <li class="pt-3">
                <p class="text-blue-300 text-xs px-4 mb-2 uppercase tracking-widest">Insurance</p>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-file-alt w-5"></i> Policies
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-users w-5"></i> Customers
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-bell w-5"></i> Renewals
                </a>
            </li>
            <li class="pt-3">
                <p class="text-blue-300 text-xs px-4 mb-2 uppercase tracking-widest">Finance</p>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-dollar-sign w-5"></i> Commission
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-star w-5"></i> Reward Points
                </a>
            </li>
            <li class="pt-3">
                <p class="text-blue-300 text-xs px-4 mb-2 uppercase tracking-widest">Reports</p>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-chart-bar w-5"></i> Analytics
                </a>
            </li>
            <li>
                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 text-sm hover:text-white hover:bg-white hover:bg-opacity-10">
                    <i class="fas fa-cog w-5"></i> Settings
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="flex-1 p-6">

    <!-- Page Title -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: #0D5A8E;">Dashboard</h1>
        <p class="text-sm" style="color: #38A169;">Welcome back, {{ auth()->user()->name }}! Here is your performance overview.</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-5 shadow-sm border-l-4" style="border-color: #1B9AE4;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold mb-1" style="color: #888;">Total Policies</p>
                    <p class="text-2xl font-bold" style="color: #0D5A8E;">0</p>
                    <p class="text-xs mt-1" style="color: #38A169;">Active policies</p>
                </div>
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl"
                     style="background: #e0f7fa;">
                    📄
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border-l-4" style="border-color: #38A169;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold mb-1" style="color: #888;">Commission Earned</p>
                    <p class="text-2xl font-bold" style="color: #0D5A8E;">RM 0.00</p>
                    <p class="text-xs mt-1" style="color: #38A169;">This month</p>
                </div>
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl"
                     style="background: #e0f7fa;">
                    💰
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border-l-4" style="border-color: #D97706;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold mb-1" style="color: #888;">Reward Points</p>
                    <p class="text-2xl font-bold" style="color: #0D5A8E;">0</p>
                    <p class="text-xs mt-1" style="color: #38A169;">Available points</p>
                </div>
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl"
                     style="background: #e0f7fa;">
                    ⭐
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border-l-4" style="border-color: #8B5CF6;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold mb-1" style="color: #888;">Team Members</p>
                    <p class="text-2xl font-bold" style="color: #0D5A8E;">0</p>
                    <p class="text-xs mt-1" style="color: #38A169;">Active agents</p>
                </div>
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl"
                     style="background: #e0f7fa;">
                    👥
                </div>
            </div>
        </div>
    </div>

    <!-- Two columns -->
    <div class="grid grid-cols-2 gap-4">

        <!-- Recent Activity -->
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <h3 class="font-bold mb-4 text-sm" style="color: #0D5A8E;">
                📊 Recent Activity
            </h3>
            <div class="text-center py-8">
                <p class="text-4xl mb-3">📭</p>
                <p class="text-sm" style="color: #888;">No activity yet.</p>
                <p class="text-xs mt-1" style="color: #aaa;">Start by uploading your first policy!</p>
            </div>
        </div>

        <!-- Renewal Pipeline -->
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <h3 class="font-bold mb-4 text-sm" style="color: #0D5A8E;">
                🔔 Upcoming Renewals
            </h3>
            <div class="text-center py-8">
                <p class="text-4xl mb-3">✅</p>
                <p class="text-sm" style="color: #888;">No renewals due soon.</p>
                <p class="text-xs mt-1" style="color: #aaa;">All your policies are up to date!</p>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <div class="mt-6 text-center">
        <p class="text-xs" style="color: #aaa;">
            GeneralLink Digital Ecosystem &nbsp;·&nbsp; AI-POWERED &nbsp;·&nbsp; MALAYSIA &nbsp;·&nbsp; SOUTHEAST ASIA
        </p>
    </div>

</div>
</div>

</body>
</html>