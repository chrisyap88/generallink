@forelse($paginator as $r)
<tr data-rank-id="{{ $r->rank_id }}" data-role="{{ $r->role }}" data-rank-name="{{ $r->rank_name }}" data-is-active="{{ $r->is_active ? 1 : 0 }}" style="background:{{ $loop->even ? '#e8f1fb' : '#ffffff' }}; border-bottom:1px solid #f3f4f6;">
    <td style="padding:3px 6px; text-align:center; font-weight:700; color:#1565C0;">{{ $r->display_rank_no }}</td>
    <td style="padding:3px 6px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $r->rank_name }}</td>
    <td style="padding:3px 6px; text-align:center; color:#16a34a;">{{ $r->role === 'GROUP_LEADER' ? '✓' : '' }}</td>
    <td style="padding:3px 6px; text-align:center; color:#0891b2;">{{ $r->role === 'TEAM_LEADER' ? '✓' : '' }}</td>
    <td style="padding:3px 6px; text-align:center; color:#7c3aed;">{{ $r->role === 'INTRODUCER' ? '✓' : '' }}</td>
    <td style="padding:3px 6px; text-align:center;">
        <span style="background:{{ $r->is_active ? '#d1fae5' : '#fee2e2' }}; color:{{ $r->is_active ? '#065f46' : '#991b1b' }}; font-size:9px; font-weight:600; padding:1px 6px; border-radius:20px; white-space:nowrap;">{{ $r->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span>
    </td>
    <td style="padding:3px 6px; text-align:center;">
        <div style="display:flex; align-items:center; justify-content:center; gap:3px; white-space:nowrap;">
            <button type="button" class="rr-icon" data-action="up" title="{{ __('masterfile.move_up_title') }}" style="background:none; border:1px solid #1565C0; color:#1565C0; width:16px; height:16px; border-radius:3px; font-size:9.5px; line-height:1; cursor:pointer; padding:0;">&#8593;</button>
            <button type="button" class="rr-icon" data-action="down" title="{{ __('masterfile.move_down_title') }}" style="background:none; border:1px solid #1565C0; color:#1565C0; width:16px; height:16px; border-radius:3px; font-size:9.5px; line-height:1; cursor:pointer; padding:0;">&#8595;</button>
            <button type="button" class="rr-icon" data-action="add-after" title="{{ __('masterfile.insert_rank_below_title') }}" style="background:none; border:1px solid #16a34a; color:#16a34a; width:16px; height:16px; border-radius:3px; font-size:10.5px; line-height:1; cursor:pointer; padding:0;">+</button>
            <button type="button" class="rr-icon" data-action="edit" title="{{ __('masterfile.edit') }}" style="background:none; border:1px solid #1B9AE4; color:#1B9AE4; width:16px; height:16px; border-radius:3px; font-size:9.5px; line-height:1; cursor:pointer; padding:0;">&#9998;</button>
            <button type="button" class="rr-icon" data-action="delete" title="{{ __('masterfile.delete_title') }}" style="background:none; border:1px solid #dc2626; color:#dc2626; width:16px; height:16px; border-radius:3px; font-size:10.5px; line-height:1; cursor:pointer; padding:0;">&minus;</button>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="7" style="text-align:center; padding:16px; color:#9ca3af;">
        {{ __('masterfile.no_ranks_defined_yet') }}
        <div style="margin-top:8px;">
            <button type="button" id="rr-add-first" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.add_first_rank_button') }}</button>
        </div>
    </td>
</tr>
@endforelse
