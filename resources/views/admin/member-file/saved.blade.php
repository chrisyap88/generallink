@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.title'))
@section('content')
{{-- NEW 28 Sep 2026 — after Add New Member: the person is saved (no CBE).
     Next step is his own choice: Add Affiliation, another new member, or Done. --}}
@include('admin.member-file._style')
<div class="mf-page">
    <div class="mf-title">{{ __('member_file.title') }} — {{ __('member_file.saved_title') }}</div>
    <div class="mf-ok" style="font-size:11px;">✓ {{ __('member_file.saved_msg', ['name' => $person->full_name]) }} · {{ $person->agent_code }}</div>
    <div class="mf-box" style="flex:0 0 auto;">
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('admin.member-file.edit', array_filter(['id' => $person->agent_id, 'panel' => 'register', 'group' => request('group')])) }}" class="mf-btn-sq">+ {{ __('member_file.add_affiliation') }}</a>
            <a href="{{ route('admin.member-file.create', array_filter(['group' => request('group')])) }}" class="mf-btn-sq">{{ __('member_file.add_new') }}</a>
            <a href="{{ route('admin.member-file.landing', array_filter(['group' => request('group')])) }}" class="mf-btn-sq">{{ __('member_file.done') }}</a>
        </div>
    </div>
    <div style="flex:1;"></div>
    <div class="mf-bar">
        <a href="{{ route('admin.member-file.edit', $person->agent_id) }}" class="mf-btn">{{ __('masterfile.prev') }}</a><span></span>
        <a href="{{ route('admin.member-file.edit', array_filter(['id' => $person->agent_id, 'panel' => 'register', 'group' => request('group')])) }}" class="mf-btn">{{ __('masterfile.next') }}</a>
    </div>
</div>
@endsection
