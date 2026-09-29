@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai_assistant.add_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai_assistant.add_button') }}</div>
        <a href="{{ route('cbe.ai-assistants.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow-y:auto;">
        <form method="POST" action="{{ route('cbe.ai-assistants.store') }}" style="max-width:640px;">
            @csrf

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_ai_assistant.field_name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required placeholder="{{ __('cbe_ai_assistant.field_name_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_ai_assistant.field_instructions') }}</label>
            <textarea name="instructions" maxlength="2000" rows="3" placeholder="{{ __('cbe_ai_assistant.field_instructions_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box; resize:vertical;">{{ old('instructions') }}</textarea>

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_ai_assistant.field_documents') }}</label>
            <div style="font-size:8.5px; color:#9ca3af; margin-bottom:6px;">{{ __('cbe_ai_assistant.field_documents_hint') }}</div>
            <div style="border:1px solid #d1d5db; border-radius:6px; padding:8px; max-height:180px; overflow-y:auto; margin-bottom:14px;">
                @forelse($documents as $doc)
                <label style="display:flex; align-items:center; gap:6px; font-size:10px; color:#374151; padding:4px 0; cursor:pointer;">
                    <input type="checkbox" name="document_ids[]" value="{{ $doc->document_id }}" @checked(in_array($doc->document_id, old('document_ids', [])))> {{ $doc->title }} <span style="color:#9ca3af;">(v{{ $doc->version }})</span>
                </label>
                @empty
                <div style="color:#9ca3af; font-size:10px; padding:6px 0;">{{ __('cbe_ai_assistant.no_documents_note') }}</div>
                @endforelse
            </div>

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_ai_assistant.field_web_sources') }}</label>
            <div style="font-size:8.5px; color:#9ca3af; margin-bottom:6px;">{{ __('cbe_ai_assistant.field_web_sources_hint') }}</div>
            <div style="border:1px solid #d1d5db; border-radius:6px; padding:10px; margin-bottom:14px; display:flex; flex-direction:column; gap:10px;">
                @for ($i = 0; $i < 2; $i++)
                <div style="display:flex; gap:6px; align-items:flex-start;">
                    <select name="web_sources[{{ $i }}][type]" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 6px; font-size:9.5px; flex:0 0 118px;">
                        <option value="URL" {{ old("web_sources.$i.type", 'URL') === 'URL' ? 'selected' : '' }}>{{ __('cbe_ai_assistant.web_source_type_url') }}</option>
                        <option value="YOUTUBE" {{ old("web_sources.$i.type") === 'YOUTUBE' ? 'selected' : '' }}>{{ __('cbe_ai_assistant.web_source_type_youtube') }}</option>
                    </select>
                    <input type="text" name="web_sources[{{ $i }}][title]" value="{{ old("web_sources.$i.title") }}" maxlength="150" placeholder="{{ __('cbe_ai_assistant.web_source_title_placeholder') }}" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; flex:1; min-width:0;">
                    <input type="url" name="web_sources[{{ $i }}][url]" value="{{ old("web_sources.$i.url") }}" maxlength="1000" placeholder="{{ __('cbe_ai_assistant.web_source_url_placeholder') }}" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; flex:1.4; min-width:0;">
                </div>
                @endfor
            </div>

            <div style="display:flex; gap:8px;">
                <a href="{{ route('cbe.ai-assistants.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
