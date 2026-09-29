<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeneralLink — Verify Email</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Segoe UI', Arial, sans-serif; } .gl-blue { background-color: #0D5A8E; } .gl-text { color: #0D5A8E; }</style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
<div class="w-full max-w-md text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gl-blue mb-6 shadow-lg">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
        </svg>
    </div>
    <h1 class="text-2xl font-bold gl-text mb-2">Check your email</h1>
    <p class="text-gray-500 text-sm mb-6">
        We've sent a verification link to <strong>{{ session('email') ?? 'your email address' }}</strong>.<br>
        Click the link in the email to activate your account.
    </p>
    <div class="bg-white rounded-xl border border-gray-100 p-5 text-sm text-gray-500 shadow-sm mb-6">
        Didn't receive it? Check your spam folder.<br>
        The link expires in <strong>24 hours</strong>.
    </div>
    <a href="{{ route('auth.login') }}" class="text-sm gl-text font-medium hover:underline">← Back to login</a>
</div>
</body>
</html>
