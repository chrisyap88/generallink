<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeneralLink — Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; }
        .gl-blue  { background-color: #0D5A8E; }
        .gl-blue2 { background-color: #1B9AE4; }
        .gl-text  { color: #0D5A8E; }
        .gl-border:focus { border-color: #1B9AE4; box-shadow: 0 0 0 3px rgba(27,154,228,0.2); outline: none; }
        .gl-btn   { background-color: #0D5A8E; transition: background-color 0.2s; }
        .gl-btn:hover { background-color: #0a4570; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">

<div class="w-full max-w-md">

    {{-- Logo & Header --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gl-blue mb-4 shadow-lg">
            <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                <path d="M18 4L32 12V24L18 32L4 24V12L18 4Z" fill="white" fill-opacity="0.2"/>
                <path d="M18 4L32 12V24L18 32L4 24V12L18 4Z" stroke="white" stroke-width="2"/>
                <circle cx="18" cy="18" r="6" fill="white"/>
                <path d="M18 12V18M18 18L23 21" stroke="#0D5A8E" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold gl-text tracking-tight">GeneralLink</h1>
        <p class="text-sm text-gray-500 mt-1">Digital Insurance Affiliate Ecosystem</p>
    </div>

    {{-- Login Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">

        <h2 class="text-lg font-semibold text-gray-800 mb-1">Welcome back</h2>
        <p class="text-sm text-gray-500 mb-6">Sign in to your account</p>

        {{-- Session errors --}}
        @if (session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">
                {{ session('error') }}
            </div>
        @endif

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3 mb-5">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('auth.login.post') }}">
            @csrf

            {{-- Email --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="you@example.com"
                    autocomplete="email"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 gl-border transition-all @error('email') border-red-400 bg-red-50 @enderror"
                >
                @error('email')
                    <p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <a href="#" class="text-xs text-blue-600 hover:underline">Forgot password?</a>
                </div>
                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="••••••••••"
                        autocomplete="current-password"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 gl-border transition-all pr-10 @error('password') border-red-400 bg-red-50 @enderror"
                    >
                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember me --}}
            <div class="flex items-center mb-6">
                <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded border-gray-300 accent-blue-600">
                <label for="remember" class="ml-2 text-sm text-gray-600">Keep me signed in</label>
            </div>

            {{-- Submit --}}
            <button type="submit" class="w-full gl-btn text-white font-medium py-2.5 rounded-lg text-sm tracking-wide shadow-sm">
                Sign in
            </button>
        </form>
    </div>

    {{-- Role indicator --}}
    <div class="mt-6 flex justify-center gap-3 flex-wrap">
        @foreach(['Admin', \App\Services\RoleLabelService::label('GROUP_LEADER'), \App\Services\RoleLabelService::label('TEAM_LEADER'), \App\Services\RoleLabelService::label('INTRODUCER')] as $role)
        <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
            <span class="w-1.5 h-1.5 rounded-full gl-blue2 inline-block"></span>
            {{ $role }}
        </span>
        @endforeach
    </div>
    <p class="text-center text-xs text-gray-400 mt-3">All roles sign in from this page</p>

    {{-- Footer --}}
    <p class="text-center text-xs text-gray-300 mt-8">
        &copy; {{ date('Y') }} GeneralLink Digital Ecosystem &middot; Confidential
    </p>
</div>

<script>
function togglePassword() {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        pwd.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
</script>
</body>
</html>
