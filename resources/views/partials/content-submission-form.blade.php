{{-- NEW 10 Aug 2026 — shared by growth/submit-content.blade.php (agent)
     and vendor/submit-content.blade.php (vendor). Deliberately a SHORT
     form compared to Admin's own Add Content screen — no Vendor picker
     (the submitter IS the vendor, or is an agent — either way it's
     known automatically), no Ownership/Status/Feature-Key/Where-From
     fields (all Admin-only concepts). Goes to Admin as Pending Review;
     see ContentSubmissionController::store(). --}}
{{-- Errors: agent's wrapper view renders its own banner (matches house
     convention); vendor's layout (layouts.vendor) already shows a
     global error banner, so it isn't duplicated here. --}}
@if(!$folderReady)
<div style="background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:6px 12px; color:#92400e; font-size:10px; flex-shrink:0; margin-bottom:8px;" id="csFolderWarning">{{ __('growth.storage_not_ready_warning') }}</div>
@endif

<form method="POST" action="{{ route($isVendor ? 'vendor.content-submission.store' : 'content-submission.store') }}" enctype="multipart/form-data" autocomplete="off" id="csForm" style="display:flex; flex-direction:column; gap:8px;">
    @csrf

    <div>
        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('growth.name_field_required') }} <span style="color:#dc2626;">*</span></label>
        <input type="text" name="video_name" required maxlength="200" value="{{ old('video_name') }}" placeholder="{{ __('growth.name_placeholder_example') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
        <div>
            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('growth.content_type_label') }} <span style="color:#dc2626;">*</span></label>
            <select name="content_type" id="csContentType" required onchange="csToggleContentType()" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; outline:none; box-sizing:border-box;">
                @foreach($contentTypes as $key => $label)
                <option value="{{ $key }}" {{ old('content_type', 'VIDEO') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('growth.category_field_label') }} <span style="color:#dc2626;">*</span></label>
            <select name="video_type" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; outline:none; box-sizing:border-box;">
                <option value="">{{ __('growth.select_placeholder') }}</option>
                @foreach($types as $key => $label)
                <option value="{{ $key }}" {{ old('video_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('growth.purpose_label') }} <span style="font-weight:400; color:#9ca3af;">{{ __('growth.optional_hint') }}</span></label>
        <input type="text" name="purpose" maxlength="300" value="{{ old('purpose') }}" placeholder="{{ __('growth.purpose_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; outline:none; box-sizing:border-box;">
    </div>

    <div>
        <div id="csFileFieldWrap">
            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('growth.file_label') }} <span style="color:#dc2626;">*</span></label>
            <input type="file" name="content_file" id="csFileInput" accept=".mp4,.mov,.webm,.avi,.mkv,.m4v" required style="width:100%; font-size:10px;">
            <div style="font-size:8.5px; color:#6b7280; margin-top:2px; line-height:1.25;" id="csFileHint">{{ __('growth.file_hint_video') }}</div>
        </div>
        <div id="csLinkFieldWrap" style="display:none;">
            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('growth.link_label') }} <span style="color:#dc2626;">*</span></label>
            <input type="url" name="external_url" id="csLinkInput" value="{{ old('external_url') }}" placeholder="{{ __('growth.link_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; outline:none; box-sizing:border-box;">
        </div>
    </div>

    <div style="font-size:8.5px; color:#6b7280; line-height:1.3;">{{ __('growth.review_note') }}</div>

    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:9px 14px; font-size:11.5px; font-weight:600; cursor:pointer; margin-top:2px;">{{ __('growth.send_to_admin_button') }}</button>
</form>

<script>
var CS_ACCEPT = { VIDEO: '.mp4,.mov,.webm,.avi,.mkv,.m4v', SLIDESHOW: '.pdf', FLYER: '.jpg,.jpeg,.png,.pdf' };
var CS_HINT = { VIDEO: @json(__('growth.file_hint_video')), SLIDESHOW: @json(__('growth.file_hint_slideshow')), FLYER: @json(__('growth.file_hint_flyer')) };
function csToggleContentType() {
    var type = document.getElementById('csContentType').value;
    var isLink = type === 'LINK';
    var fileWrap = document.getElementById('csFileFieldWrap');
    var linkWrap = document.getElementById('csLinkFieldWrap');
    var fileInput = document.getElementById('csFileInput');
    var linkInput = document.getElementById('csLinkInput');
    fileWrap.style.display = isLink ? 'none' : 'block';
    linkWrap.style.display = isLink ? 'block' : 'none';
    if (isLink) {
        fileInput.removeAttribute('required');
        linkInput.setAttribute('required', 'required');
    } else {
        fileInput.setAttribute('required', 'required');
        linkInput.removeAttribute('required');
        fileInput.setAttribute('accept', CS_ACCEPT[type] || CS_ACCEPT.VIDEO);
        document.getElementById('csFileHint').textContent = CS_HINT[type] || CS_HINT.VIDEO;
    }
}
document.addEventListener('DOMContentLoaded', csToggleContentType);
</script>
