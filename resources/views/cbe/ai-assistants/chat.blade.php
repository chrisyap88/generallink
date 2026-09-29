@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $assistant->name)

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:6px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">🤖 {{ $assistant->name }}</div>
            @php
                $okSourceNames = $documents->pluck('title')
                    ->merge($webSources->where('fetch_status', 'OK')->pluck('title'));
                $failedSources = $webSources->where('fetch_status', 'FAILED');
            @endphp
            <div style="font-size:8.5px; color:#9ca3af;">{{ __('cbe_ai_assistant.chat_sources_note', ['names' => $okSourceNames->implode(', ')]) }}</div>
            @if($failedSources->isNotEmpty())
            <div style="font-size:8px; color:#c77700; margin-top:2px;">
                @foreach($failedSources as $fs)
                    ⚠ {{ $fs->title }} — {{ $fs->fetch_error ?: __('cbe_ai_assistant.source_fetch_failed') }}
                @endforeach
            </div>
            @endif
        </div>
        <a href="{{ route('cbe.ai-assistants.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('error'))
    <div style="flex-shrink:0; background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10px;">{{ session('error') }}</div>
    @endif

    <div style="flex:1; min-height:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;">
        @forelse($history as $h)
        <div style="align-self:flex-end; max-width:80%; background:var(--gl-blue); color:#fff; border-radius:10px 10px 2px 10px; padding:8px 12px; font-size:10.5px;">{{ $h->question }}</div>
        <div style="align-self:flex-start; max-width:80%; background:#f5f6f8; color:#263238; border-radius:10px 10px 10px 2px; padding:8px 12px; font-size:10.5px; line-height:1.5; white-space:pre-wrap;">{{ $h->answer }}</div>
        @empty
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_ai_assistant.empty_chat_note') }}</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('cbe.ai-assistants.ask', $assistant->assistant_id) }}" style="flex-shrink:0; display:flex; gap:8px;">
        @csrf
        <input type="text" name="question" value="{{ old('question') }}" required maxlength="1000" placeholder="{{ __('cbe_ai_assistant.field_question_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:20px; padding:9px 16px; font-size:11px; box-sizing:border-box;">
        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:9px 22px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('cbe_ai_assistant.ask_button') }}</button>
    </form>
</div>
@endsection
