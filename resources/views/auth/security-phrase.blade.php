<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.security_phrase_page_title') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; }
        .gl-blue { background-color: #0D5A8E; }
        .gl-text  { color: #0D5A8E; }
        .gl-border:focus { border-color: #1B9AE4; box-shadow: 0 0 0 3px rgba(27,154,228,0.2); outline: none; }
        .gl-btn { background-color: #0D5A8E; } .gl-btn:hover { background-color: #0a4570; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
<div class="w-full max-w-md">

    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl gl-blue mb-4 shadow-lg">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold gl-text">{{ __('auth.security_phrase_heading') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('auth.security_phrase_subheading') }}</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 mb-6 text-sm text-blue-700">
            {{ __('auth.security_phrase_info') }}
        </div>

        <form method="POST" action="{{ route('auth.security-phrase.save') }}">
            @csrf
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('auth.security_phrase_label') }}</label>
                <input
                    type="text"
                    name="security_phrase"
                    placeholder="{{ __('auth.security_phrase_placeholder') }}"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 gl-border @error('security_phrase') border-red-400 @enderror"
                >
                @error('security_phrase')
                    <p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-400 mt-2">{{ __('auth.security_phrase_hint') }}</p>
            </div>
            <button type="submit" class="w-full gl-btn text-white font-medium py-2.5 rounded-lg text-sm transition-colors">
                {{ __('auth.save_and_continue_button') }}
            </button>
        </form>
    </div>
</div>

@include('partials.ai-assistant-widget', ['guestMode' => true])
</body>
</html>
