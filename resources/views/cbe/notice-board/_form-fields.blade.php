{{-- Shared by create.blade.php and edit.blade.php — per Chris's 4-part
     request: Carolyn help-to-write, style picker (feeds the live preview
     panel), and 3 independent attachment slots (Flyer/Catalog/Video),
     each either a pasted link or an uploaded file. --}}
@php
    $n = $notice;
    $curFlyerType = old('flyer_type', $n && ($n->flyer_link_url ?? null) ? 'LINK' : ($n && ($n->attachment_file_path ?? null) ? 'FILE' : 'NONE'));
    $curCatalogType = old('catalog_type', $n && ($n->catalog_link_url ?? null) ? 'LINK' : ($n && ($n->catalog_file_path ?? null) ? 'FILE' : 'NONE'));
    $curVideoType = old('video_type', $n && ($n->video_link_url ?? null) ? 'LINK' : ($n && ($n->video_file_path ?? null) ? 'FILE' : 'NONE'));
    $curStyle = old('style_key', ($n && ($n->style_is_override ?? false)) ? $n->style_key : 'AUTO');
@endphp

<div style="display:flex; flex-direction:column; gap:9px; max-width:520px;">
    <div>
        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_notice_title') }}</label>
        <input type="text" name="title" id="noticeTitle" value="{{ old('title', $n->title ?? '') }}" maxlength="150" required oninput="cbeNoticePreviewRefresh()" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_notice_category') }}</label>
        <select name="category" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
            @foreach(['ANNOUNCEMENT', 'EVENT', 'GENERAL'] as $cat)
            <option value="{{ $cat }}" @selected(old('category', $n->category ?? null) === $cat)>{{ __('cbe_records.notice_category_'.strtolower($cat)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_notice_listing') }}</label>
        <select name="listing_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box; background:#fff;">
            <option value="">{{ __('cbe_records.field_notice_listing_none') }}</option>
            @foreach($listings as $l)
            <option value="{{ $l->listing_id }}" @selected(old('listing_id', $n->listing_id ?? null) === $l->listing_id)>{{ $l->title }} (RM {{ number_format($l->price, 2) }})</option>
            @endforeach
        </select>
    </div>

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:-2px; flex-wrap:wrap; gap:4px;">
        <label style="font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_notice_body') }}</label>
        <div style="display:flex; align-items:center; gap:10px; font-size:9px;">
            <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#546E7A;">
                <input type="radio" name="writeMode" id="writeModeMyself" checked onchange="noticeWriteModeChanged()"> {{ __('notice_board.write_myself_option') }}
            </label>
            <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#7c3aed; font-weight:600;">
                <input type="radio" name="writeMode" id="writeModeCarolyn" onchange="noticeWriteModeChanged()"> {{ __('notice_board.carolyn_help_write_option') }}
            </label>
        </div>
    </div>
    <textarea name="body" id="noticeBody" maxlength="3000" required oninput="cbeNoticePreviewRefresh()" style="width:100%; height:90px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box; resize:none;">{{ old('body', $n->body ?? '') }}</textarea>

    <div id="carolynSuggestBox" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:8px 10px; font-size:10px;">
        <div id="carolynStatus" style="color:#7c3aed; font-weight:600;">{{ __('notice_board.carolyn_polishing_status') }}</div>
        <div id="carolynResult" style="display:none;">
            <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('notice_board.suggested_title_label') }}</div>
            <div id="carolynTitlePreview" style="font-weight:600; color:#111827; margin-bottom:6px;"></div>
            <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('notice_board.suggested_message_label') }}</div>
            <div id="carolynBodyPreview" style="white-space:pre-wrap; color:#111827; margin-bottom:8px;"></div>
            <div style="display:flex; gap:6px;">
                <button type="button" onclick="carolynAccept()" style="background:#16a34a; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.use_this_button') }}</button>
                <button type="button" onclick="carolynAsk()" style="background:#7c3aed; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.try_again_button') }}</button>
                <button type="button" onclick="carolynCancel()" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.cancel_button') }}</button>
            </div>
        </div>
        <div id="carolynError" style="display:none; color:#b71c1c;"></div>
    </div>

    <div>
        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_notice_style') }}</label>
        <select name="style_key" id="noticeStyleSelect" onchange="cbeNoticePreviewRefresh()" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
            <option value="AUTO" @selected($curStyle === 'AUTO')>{{ __('cbe_records.style_auto_option') }}</option>
            @foreach($styleOptions as $s)
            <option value="{{ $s->style_key }}" @selected($curStyle === $s->style_key)>{{ $s->label }}</option>
            @endforeach
        </select>
        <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('cbe_records.style_auto_hint') }}</div>
    </div>

    <div>
        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_notice_expires') }}</label>
        <input type="date" name="expires_at" value="{{ old('expires_at', $n->expires_at ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
    </div>

    @foreach ([
        ['key' => 'flyer', 'label' => __('cbe_records.field_attach_flyer'), 'cur' => $curFlyerType, 'file' => 'attachment', 'accept' => '.jpg,.jpeg,.png,.pdf', 'existing' => $n->attachment_file_name ?? null],
        ['key' => 'catalog', 'label' => __('cbe_records.field_attach_catalog'), 'cur' => $curCatalogType, 'file' => 'catalog_attachment', 'accept' => '.jpg,.jpeg,.png,.pdf', 'existing' => $n->catalog_file_name ?? null],
        ['key' => 'video', 'label' => __('cbe_records.field_attach_video'), 'cur' => $curVideoType, 'file' => 'video_attachment', 'accept' => '.mp4,.mov,.jpg,.jpeg,.png,.pdf', 'existing' => $n->video_file_name ?? null],
    ] as $att)
    <div style="border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px;">
        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:5px;">{{ $att['label'] }}</label>
        <div style="display:flex; gap:12px; font-size:9.5px; margin-bottom:6px;">
            <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#6b7280;"><input type="radio" name="{{ $att['key'] }}_type" value="NONE" onchange="cbeAttachmentTypeChanged('{{ $att['key'] }}')" @checked($att['cur'] === 'NONE')> {{ __('cbe_records.attach_type_none') }}</label>
            <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#6b7280;"><input type="radio" name="{{ $att['key'] }}_type" value="LINK" onchange="cbeAttachmentTypeChanged('{{ $att['key'] }}')" @checked($att['cur'] === 'LINK')> {{ __('cbe_records.attach_type_link') }}</label>
            <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#6b7280;"><input type="radio" name="{{ $att['key'] }}_type" value="FILE" onchange="cbeAttachmentTypeChanged('{{ $att['key'] }}')" @checked($att['cur'] === 'FILE')> {{ __('cbe_records.attach_type_file') }}</label>
        </div>
        <div id="{{ $att['key'] }}_link_wrap" style="{{ $att['cur'] === 'LINK' ? '' : 'display:none;' }}">
            <input type="url" name="{{ $att['key'] }}_link_url" value="{{ old($att['key'].'_link_url', ($n->{$att['key'].'_link_url'} ?? '')) }}" placeholder="https://..." style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
        </div>
        <div id="{{ $att['key'] }}_file_wrap" style="{{ $att['cur'] === 'FILE' ? '' : 'display:none;' }}">
            @if($att['existing'])
            <div style="font-size:9px; color:#546E7A; margin-bottom:3px;">{{ __('cbe_records.current_file_note') }}: {{ $att['existing'] }}</div>
            @endif
            <input type="file" name="{{ $att['file'] }}" accept="{{ $att['accept'] }}" style="width:100%; font-size:10px;">
        </div>
        @if($att['key'] === 'video')
        <div style="font-size:8.5px; color:#9ca3af; margin-top:3px;">{{ __('cbe_records.video_attach_hint') }}</div>
        @endif
    </div>
    @endforeach
</div>
