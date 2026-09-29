@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.notice_board_edit_button'))

@section('content')

<div style="height:calc(100vh - 46px); overflow:hidden; display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.notice_board_edit_button') }}</div>
        <a href="{{ route('cbe.notice-board.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px; flex-shrink:0;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; display:flex; gap:14px;">

        <form method="POST" action="{{ route('cbe.notice-board.update', $notice->notice_id) }}" enctype="multipart/form-data" id="noticeForm" style="flex:1.1; min-width:0; overflow-y:auto; padding-right:6px;">
            @csrf
            @include('cbe.notice-board._form-fields', ['notice' => $notice, 'styleOptions' => $styleOptions])
            <div style="display:flex; gap:8px; margin-top:12px; padding-bottom:4px;">
                <a href="{{ route('cbe.notice-board.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>

        <div style="flex:0.9; min-width:0; overflow-y:auto; border-left:1px solid #e5e7eb; padding-left:14px;">
            @include('cbe.notice-board._preview-panel')
        </div>
    </div>
</div>

@push('scripts')
@include('cbe.notice-board._form-scripts', ['aiAssistUrl' => route('cbe.notice-board.ai-assist'), 'styleDetectUrl' => route('cbe.notice-board.style-detect'), 'styleOptions' => $styleOptions])
@endpush
@endsection
