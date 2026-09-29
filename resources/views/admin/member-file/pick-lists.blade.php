@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.pick_list_title'))
@section('content')
{{-- NEW 28 Sep 2026 — Master File › Member Pick Lists: the choices used in the
     member file (Gender, Race, Religion, Nationality, Marital Status,
     Dietary Preference, Affiliation Type). Pick the list, then Add / Edit on
     the same screen. Nothing is deleted. --}}
@include('admin.member-file._style')
<div class="mf-page">
    <div class="mf-title">{{ __('member_file.pick_list_title') }}</div>
    <div style="display:flex; gap:14px; border-bottom:1px solid #e5e7eb; flex-shrink:0;">
        @foreach($lists as $l)
            <a href="{{ route('admin.member-pick-lists.index', array_filter(['list' => $l, 'return' => $ret])) }}" style="font-size:10.5px; font-weight:700; text-decoration:none; padding-bottom:3px; white-space:nowrap; {{ $l === $list ? 'color:#263238; border-bottom:2px solid #263238;' : 'color:#1565C0;' }}">{{ __('member_file.list_'.$l) }}</a>
        @endforeach
    </div>
    @if(session('saved'))<div class="mf-ok">✓ {{ __('member_file.saved_title') }}</div>@endif
    <div class="mf-box" style="display:flex; flex-direction:column; gap:8px;">
        <form method="POST" action="{{ route('admin.member-pick-lists.save') }}" style="display:flex; gap:10px; align-items:flex-end; margin:0;">
            @csrf
            <input type="hidden" name="list" value="{{ $list }}">
            <input type="hidden" name="return" value="{{ $ret }}">
            <input type="hidden" name="id" id="pl-id">
            <div class="mf-f" style="flex:1;"><label>{{ __('member_file.label') }}</label><input name="label" id="pl-label" required maxlength="120"></div>
            <div class="mf-f" style="flex:1;"><label>{{ __('member_file.label_zh') }}</label><input name="label_zh" id="pl-zh" maxlength="120"></div>
            <button type="submit" class="mf-btn" id="pl-btn">{{ __('member_file.add_row') }}</button>
        </form>
        <div id="pl-wrap" style="flex:1; min-height:0; overflow:hidden;">
            <table class="mf-table" id="pl-table">
                <thead><tr><th style="width:30px;">#</th><th>{{ __('member_file.label') }}</th><th>{{ __('member_file.label_zh') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($rows as $i => $r)
                    <tr><td>{{ $i + 1 }}</td><td>{{ $r->label }}</td><td>{{ $r->label_zh }}</td>
                        <td><a href="#" style="color:#1565C0; font-weight:700; text-decoration:none;" onclick='plEdit({{ json_encode(["id" => $r->id, "label" => $r->label, "zh" => $r->label_zh], JSON_HEX_APOS | JSON_HEX_QUOT) }}); return false;'>{{ __('member_file.edit') }}</a></td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mf-bar">
        <a href="{{ $ret ?: route('admin.member-file.landing') }}" class="mf-btn">{{ __('masterfile.prev') }}</a><span></span>
        <a href="{{ route('admin.membership-plans.index') }}" class="mf-btn">{{ __('masterfile.next') }}</a>
    </div>
</div>
<script>
function plEdit(r){ document.getElementById('pl-id').value = r.id; document.getElementById('pl-label').value = r.label; document.getElementById('pl-zh').value = r.zh || ''; document.getElementById('pl-btn').textContent = @json(__('member_file.save')); document.getElementById('pl-label').focus(); }
(function(){ var w = document.getElementById('pl-wrap'), t = document.getElementById('pl-table'), fs = 11; while (t.offsetHeight > w.clientHeight && fs > 7) { fs -= 0.25; t.style.fontSize = fs + 'px'; } })();
</script>
@endsection
