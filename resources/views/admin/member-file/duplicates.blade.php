@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.title'))
@section('content')
{{-- NEW 28 Sep 2026 — per Chris: the same person is never saved twice.
     Same Mobile / NRIC / Email = already in the file (open it, no save).
     Same name only = "possible same person": open one of them, or save as a
     new person. --}}
@include('admin.member-file._style')
@php $isExact = $exact->isNotEmpty(); $list = $isExact ? $exact : $sameName; @endphp
<div class="mf-page">
    <div class="mf-title" style="padding-right:58px;">{{ __('member_file.title') }} — {{ $isExact ? __('member_file.dup_exact_title') : __('member_file.dup_name_title') }}</div>
    <div class="mf-sub" style="font-size:10px; color:#374151; padding-right:58px;">{{ $isExact ? __('member_file.dup_exact_help') : __('member_file.dup_name_help') }}</div>
    <div class="mf-box" style="display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
        <table class="mf-table mf-fit">
            <thead><tr><th>#</th><th>{{ __('member_file.col_name') }}</th><th>{{ __('member_file.col_mobile') }}</th><th>{{ __('member_file.email') }}</th><th>{{ __('member_file.city') }}</th><th>{{ __('member_file.col_affiliated') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($list as $i => $d)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight:600; color:#1565C0;">{{ $d->full_name }}@if($d->nick_name ?? null) ({{ $d->nick_name }})@endif</td>
                    <td>{{ $d->phone ?: '—' }}</td>
                    <td>{{ \App\Services\MemberFileService::isPlaceholderEmail($d->email) ? '—' : $d->email }}</td>
                    <td>{{ ($d->city ?? null) ?: '—' }}</td>
                    <td class="wrap">{{ implode(', ', \App\Services\MemberFileService::affiliatedTo($d->agent_id)) ?: __('member_file.not_affiliated') }}</td>
                    <td><a href="{{ ! empty($input['add_node']) ? route('admin.member-file.edit', ['id' => $d->agent_id, 'panel' => 'register', 'add_node' => $input['add_node'], 'back' => $input['return'] ?? '', 'return' => $input['return'] ?? '']) : route('admin.member-file.edit', $d->agent_id) }}" style="color:#1565C0; font-weight:700; text-decoration:none;">{{ __('member_file.open_member') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @unless($isExact)
        <form method="POST" action="{{ route('admin.member-file.store') }}" id="mf-anyway" style="margin-top:10px;">
            @csrf
            @foreach($input as $k => $val)
                @if(! in_array($k, ['_token', 'save_anyway'], true) && ! is_array($val))<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endif
            @endforeach
            <input type="hidden" name="save_anyway" value="1">
            <button type="submit" class="mf-btn">{{ __('member_file.save_anyway') }}</button>
        </form>
        @endunless
    </div>
    <div class="mf-bar">
        <button type="button" class="mf-btn" onclick="history.back()">{{ __('masterfile.prev') }}</button><span></span>
        <span class="mf-btn" style="visibility:hidden;">{{ __('masterfile.next') }}</span>
    </div>
</div>
@endsection
