{{-- NEW 28 Sep 2026 — Committee Positions in every CBE, read live from each
     CBE's Committee tab (changed only there). --}}
@php $fmtC = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : ''; @endphp
@if($committee->isEmpty())
    <div class="mf-sub" style="padding:8px 0;">{{ __('member_file.no_committee') }}</div>
@else
<table class="mf-table">
    <thead><tr><th>#</th><th>{{ __('member_file.group') }}</th><th>{{ __('member_file.position') }}</th><th>{{ __('member_file.term') }}</th><th>{{ __('member_file.status') }}</th></tr></thead>
    <tbody>
    @foreach($committee->sortByDesc('current')->values() as $i => $c)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $c->group_name }}</td>
            <td>{{ $c->position_label }}</td>
            <td>{{ $fmtC($c->term_start_date) }} – {{ $fmtC($c->ended_at ?: $c->term_end_date) }}</td>
            <td><span class="mf-fee {{ $c->current ? 'PAID' : '' }}" style="{{ $c->current ? '' : 'color:#6b7280;' }}">{{ $c->current ? __('member_file.cur') : __('member_file.prev') }}</span></td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="mf-sub" style="margin-top:4px;">{{ __('member_file.set_in', ['where' => __('member_file.how_COMMITTEE_TAB')]) }}</div>
@endif
