@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_blast.add_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_blast.add_button') }}</div>
        <a href="{{ route('cbe.blast.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#e3f2fd; border-left:3px solid var(--gl-blue); color:#0d47a1; border-radius:6px; padding:5px 10px; font-size:9.5px; margin-bottom:8px;">{{ __('cbe_blast.recipient_note', ['count' => $recipientCount]) }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow-y:auto;">
        <form method="POST" action="{{ route('cbe.blast.store') }}" style="max-width:520px;">
            @csrf

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_blast.field_subject') }}</label>
            <input type="text" name="subject" id="blastSubject" value="{{ old('subject') }}" maxlength="200" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_blast.field_message') }}</label>
            <textarea name="message" id="blastMessage" maxlength="3000" rows="6" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:4px; box-sizing:border-box; resize:vertical;">{{ old('message') }}</textarea>

            @include('partials.carolyn-write-assist', ['carolynBodyId' => 'blastMessage', 'carolynTitleId' => 'blastSubject', 'carolynType' => 'cbe_secretarial_blast'])

            <div style="font-size:8.5px; color:#9ca3af; margin:8px 0 14px;">{{ __('cbe_blast.field_message_hint') }}</div>

            <div style="display:flex; gap:8px;">
                <a href="{{ route('cbe.blast.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" onclick="return confirm({{ json_encode(__('cbe_blast.send_confirm_js', ['count' => $recipientCount])) }});" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('cbe_blast.send_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
