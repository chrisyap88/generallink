@extends('layouts.dashboard')

@section('page-title', __('ai.page_title'))

@section('content')
{{-- NEW 5 Aug 2026 — transparency + control screen for Carolyn's
     long-term memory. Nothing here is hidden from the agent, and
     everything can be erased with one click — see AiMemoryController /
     AiMemoryService. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:10px;">
        <a href="{{ url()->previous() }}" style="color:#6b7280; text-decoration:none; font-size:12px;">{{ __('ai.back_link') }}</a>
        <div style="font-size:11px; color:#6b7280; line-height:1.5;">{{ __('ai.intro_note') }}</div>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:7px 12px; font-size:11.5px; margin-bottom:10px;">{{ session('success') }}</div>
    @endif

    <div style="flex:1; min-height:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; display:flex; flex-direction:column;">
        @if(count($notes) === 0)
        <div style="flex:1; display:flex; align-items:center; justify-content:center; text-align:center; color:#9ca3af; font-size:12px; padding:20px;">
            {{ __('ai.no_notes_yet_note') }}<br>{{ __('ai.no_notes_yet_note_2') }}
        </div>
        @else
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @foreach($notes as $note)
            @php
                $categoryLabels = ['family' => __('ai.category_family'), 'preference' => __('ai.category_preference'), 'life_event' => __('ai.category_life_event'), 'work_context' => __('ai.category_work_context'), 'health' => __('ai.category_health'), 'other' => __('ai.category_other')];
                $label = $categoryLabels[$note['category']] ?? __('ai.category_other');
            @endphp
            <div style="border:1px solid #f3f4f6; border-radius:8px; padding:9px 12px; margin-bottom:7px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
                <div style="flex:1; min-width:0;">
                    <span style="background:#eef2f7; color:#374151; border-radius:20px; padding:1px 8px; font-size:8.5px; font-weight:700; white-space:nowrap;">{{ strtoupper($label) }}</span>
                    <div style="font-size:12px; color:#263238; margin-top:5px;">{{ $note['note'] }}</div>
                    <div style="font-size:9px; color:#9ca3af; margin-top:3px;">{{ \Carbon\Carbon::parse($note['created_at'])->format('d M Y') }}</div>
                </div>
                <form method="POST" action="{{ route('ai.memory.forget-one', $note['memory_id']) }}" onsubmit="return confirm({{ json_encode(__('ai.forget_one_confirm_js')) }});">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:#fff; color:#b71c1c; border:1px solid #f3d4d4; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('ai.forget_button') }}</button>
                </form>
            </div>
            @endforeach
        </div>

        <div style="flex-shrink:0; border-top:1px solid #f3f4f6; margin-top:10px; padding-top:10px;">
            <form method="POST" action="{{ route('ai.memory.forget-all') }}" onsubmit="return confirm({{ json_encode(__('ai.forget_everything_confirm_js')) }});">
                @csrf
                @method('DELETE')
                <button type="submit" style="background:#fff; color:#b71c1c; border:1px solid #b71c1c; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('ai.forget_everything_button') }}</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
