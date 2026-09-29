@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_correspondence.add_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_correspondence.add_button') }}</div>
        <a href="{{ route('cbe.correspondences.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow-y:auto;">
        <form method="POST" action="{{ route('cbe.correspondences.store') }}" enctype="multipart/form-data" style="max-width:520px;">
            @csrf

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_correspondence.field_direction') }}</label>
            <select name="direction" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">
                <option value="IN" @selected(old('direction') === 'IN')>{{ __('cbe_correspondence.direction_in') }}</option>
                <option value="OUT" @selected(old('direction') === 'OUT')>{{ __('cbe_correspondence.direction_out') }}</option>
            </select>

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_correspondence.field_date') }}</label>
            <input type="date" name="correspondence_date" value="{{ old('correspondence_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_correspondence.field_correspondent') }}</label>
            <input type="text" name="correspondent_name" value="{{ old('correspondent_name') }}" maxlength="200" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_correspondence.field_subject') }}</label>
            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="255" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:12px; box-sizing:border-box;">

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_correspondence.field_summary') }}</label>
            <textarea name="summary" id="corrSummary" maxlength="3000" rows="4" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; margin-bottom:4px; box-sizing:border-box; resize:vertical;">{{ old('summary') }}</textarea>

            @include('partials.carolyn-write-assist', ['carolynBodyId' => 'corrSummary', 'carolynType' => 'cbe_correspondence_summary'])

            <div style="height:10px;"></div>

            <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_correspondence.field_attachment') }}</label>
            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:14px; box-sizing:border-box;">

            <div style="display:flex; gap:8px;">
                <a href="{{ route('cbe.correspondences.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
