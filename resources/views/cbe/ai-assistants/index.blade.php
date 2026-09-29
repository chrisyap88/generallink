@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai_assistant.page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai_assistant.page_title') }}</div>
        @if($hasNode && $canManage)
        <a href="{{ route('cbe.ai-assistants.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_ai_assistant.add_button') }}</a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ __('cbe_ai_assistant.page_hint') }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($assistants as $a)
            <a href="{{ route('cbe.ai-assistants.chat', $a->assistant_id) }}" style="display:flex; align-items:center; justify-content:space-between; text-decoration:none; padding:10px 8px; border-bottom:1px solid #f3f4f6;">
                <span style="font-weight:700; color:#263238; font-size:11px;">🤖 {{ $a->name }}</span>
                <span style="color:var(--gl-blue); font-size:9.5px; font-weight:600;">{{ __('cbe_ai_assistant.chat_link') }} ›</span>
            </a>
            @empty
            <div style="padding:20px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('cbe_ai_assistant.none_note') }}</div>
            @endforelse
        </div>
        @endif
    </div>
</div>
@endsection
