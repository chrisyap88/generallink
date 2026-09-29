@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_reports.vl_add_content_page_title'))

{{-- NEW 10 Aug 2026 per Chris: Add Video has its own screen. Saving
     redirects back to admin.video-library.index. REBUILT same day to
     also capture, per Chris: "what admin can identify it is for the
     vendor vendor name, date submit and the purpose and the expired
     date for this video" — Vendor/Purpose/Submitted/Expiry are all
     OPTIONAL, since GeneralLink's own corporate videos (e.g. the
     Introduction video) don't belong to any vendor at all. Laid out in
     2-column rows (like the rest of the app's compact forms) so all 9
     fields + the file picker + buttons still fit on one screen with no
     scroll despite the extra fields. --}}
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; align-items:center; box-sizing:border-box;">

    <div style="width:100%; max-width:640px; display:flex; flex-direction:column; height:100%; min-height:0; gap:6px; padding-top:4px;">

        <div style="flex-shrink:0;">
            <a href="{{ route('admin.video-library.index') }}" style="font-size:11px; color:#1565C0; text-decoration:none; font-weight:600;">{{ __('admin_reports.vl_back_to_content_library') }}</a>
        </div>

        @if($errors->any())
        <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:10.5px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
        @endif

        @if(!$folderReady)
        <div id="vlFolderWarning" style="background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:6px 12px; color:#92400e; font-size:10px; flex-shrink:0;">{!! __('admin_reports.vl_folder_warning_html', ['url' => route('admin.video-library.index')]) !!}</div>
        @endif

        <div style="background:#fff; border-radius:10px; padding:12px 16px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">
            <div style="font-size:12.5px; font-weight:700; color:#1565C0; margin-bottom:7px; padding-bottom:5px; border-bottom:2px solid #e0f2fe; flex-shrink:0;">➕ {{ __('admin_reports.vl_add_content') }}</div>

            <form method="POST" action="{{ route('admin.video-library.store') }}" enctype="multipart/form-data" autocomplete="off" id="videoAddForm" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                @csrf

                <div style="margin-bottom:7px;">
                    <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_name_label') }} <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="video_name" required maxlength="200" value="{{ old('video_name') }}" placeholder="{{ __('admin_reports.vl_name_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                </div>

                {{-- NEW 10 Aug 2026 — per Chris: "do you allow upload
                     slide show and marketing flyer, google link form?"
                     Content Type is what KIND of thing this is (drives
                     which file formats are accepted, or whether a link
                     is expected instead); Category (below) is what it's
                     FOR — unchanged, orthogonal to content type. --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:7px;">
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_content_type_label') }} <span style="color:#dc2626;">*</span></label>
                        <select name="content_type" id="vlContentType" required onchange="vlToggleContentType()" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                            @foreach($contentTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('content_type', 'VIDEO') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_category_label') }} <span style="color:#dc2626;">*</span></label>
                        <select name="video_type" id="vlVideoType" required onchange="vlToggleFeatureKey()" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                            <option value="">{{ __('growth.select_placeholder') }}</option>
                            @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ old('video_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:7px;">
                    {{-- NEW 10 Aug 2026 — per Chris: "video is easier to
                         understand all this marketing initiative." When
                         Category = Feature Guide, this cell swaps from
                         "Vendor" to "Which Screen?" (same grid slot, no
                         extra row added — keeps the no-scroll layout). --}}
                    <div style="position:relative;">
                        <div id="vlVendorFieldWrap">
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_vendor_label') }} <span class="opt" style="font-weight:400; color:#9ca3af;">{{ __('growth.optional_hint') }}</span></label>
                            <input type="text" id="vlVendorBox" autocomplete="off" placeholder="{{ __('admin_reports.vl_vendor_placeholder') }}" value="{{ old('vendor_name') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                            <input type="hidden" name="vendor_id" id="vlVendorId" value="{{ old('vendor_id') }}">
                            <div id="vlVendorList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:180px; overflow-y:auto;"></div>
                        </div>
                        <div id="vlFeatureKeyFieldWrap" style="display:none;">
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_which_screen') }} <span style="color:#dc2626;">*</span></label>
                            <select name="feature_key" id="vlFeatureKey" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                                <option value="">{{ __('growth.select_placeholder') }}</option>
                                @foreach($featureKeys as $key => $label)
                                <option value="{{ $key }}" {{ old('feature_key') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_purpose_label') }} <span class="opt" style="font-weight:400; color:#9ca3af;">{{ __('growth.optional_hint') }}</span></label>
                        <input type="text" name="purpose" maxlength="300" value="{{ old('purpose') }}" placeholder="{{ __('admin_reports.vl_purpose_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                    </div>
                </div>
                <div style="font-size:8.5px; color:#6b7280; margin:-4px 0 7px; line-height:1.25;" id="vlCategoryHint">{{ __('admin_reports.vl_category_hint_default') }}</div>

                <div style="display:grid; grid-template-columns:1fr 1.4fr; gap:8px; margin-bottom:7px;">
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_where_from') }} <span style="color:#dc2626;">*</span></label>
                        <select name="source_type" id="vlSourceType" required onchange="vlToggleSourceNote()" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                            @foreach($sourceTypes as $key => $label)
                            <option value="{{ $key }}" {{ old('source_type', 'DIRECT_UPLOAD') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="vlSourceNoteWrap" style="display:none;">
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_source_details') }} <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="source_note" id="vlSourceNote" maxlength="300" value="{{ old('source_note') }}" placeholder="{{ __('admin_reports.vl_source_details_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                    </div>
                </div>
                <div style="font-size:8.5px; color:#6b7280; margin:-4px 0 7px; line-height:1.25;">{{ __('admin_reports.vl_source_note_hint') }}</div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:7px;">
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_date_submitted') }} <span class="opt" style="font-weight:400; color:#9ca3af;">{{ __('growth.optional_hint') }}</span></label>
                        <input type="date" name="submitted_date" value="{{ old('submitted_date', now()->toDateString()) }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_expiry_date') }} <span class="opt" style="font-weight:400; color:#9ca3af;">{{ __('growth.optional_hint') }}</span></label>
                        <input type="date" name="expiry_date" value="{{ old('expiry_date') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:7px;">
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_ownership_label') }} <span class="opt" style="font-weight:400; color:#9ca3af;">{{ __('growth.optional_hint') }}</span></label>
                        <input type="text" name="ownership" maxlength="150" value="{{ old('ownership') }}" placeholder="{{ __('admin_reports.vl_ownership_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('network.status') }} <span style="color:#dc2626;">*</span></label>
                        <select name="status" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                            <option value="ACTIVE" {{ old('status', 'ACTIVE') === 'ACTIVE' ? 'selected' : '' }}>{{ __('network.active') }}</option>
                            <option value="INACTIVE" {{ old('status') === 'INACTIVE' ? 'selected' : '' }}>{{ __('network.inactive') }}</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:8px;">
                    <div id="vlFileFieldWrap">
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_file_label') }} <span style="color:#dc2626;">*</span></label>
                        <input type="file" name="video_file" id="vlFileInput" accept=".mp4,.mov,.webm,.avi,.mkv,.m4v" required style="width:100%; font-size:9.5px;">
                        <div style="font-size:8.5px; color:#6b7280; margin-top:1px; line-height:1.25;" id="vlFileHint">{{ __('admin_reports.vl_file_hint_video') }}</div>
                    </div>
                    <div id="vlLinkFieldWrap" style="display:none;">
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('admin_reports.vl_link_label') }} <span style="color:#dc2626;">*</span></label>
                        <input type="url" name="external_url" id="vlLinkInput" value="{{ old('external_url') }}" placeholder="{{ __('growth.link_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; outline:none; box-sizing:border-box;">
                        <div style="font-size:8.5px; color:#6b7280; margin-top:1px; line-height:1.25;">{{ __('admin_reports.vl_link_hint') }}</div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:auto; flex-shrink:0;">
                    <a href="{{ route('admin.video-library.index') }}" style="text-align:center; background:#fff; color:#1565C0; border:1.5px solid #1565C0; text-decoration:none; border-radius:6px; padding:8px 14px; font-size:11.5px; font-weight:600;">{{ __('network.cancel') }}</a>
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 14px; font-size:11.5px; font-weight:600; cursor:pointer;">➕ {{ __('growth.save_button') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const VL_I18N = {
    categoryHintDefault: @json(__('admin_reports.vl_category_hint_default')),
    categoryHintFeatureGuide: @json(__('admin_reports.vl_category_hint_feature_guide')),
    hintVideo: @json(__('admin_reports.vl_file_hint_video')),
    hintSlideshow: @json(__('admin_reports.vl_hint_slideshow')),
    hintFlyer: @json(__('admin_reports.vl_hint_flyer')),
};
(function() {
    var VENDOR_URL = '{{ route('admin.masterfile.commissions.vendor-typeahead') }}';
    var box = document.getElementById('vlVendorBox');
    var hidden = document.getElementById('vlVendorId');
    var list = document.getElementById('vlVendorList');
    var timer = null;

    box.addEventListener('input', function() {
        hidden.value = '';
        clearTimeout(timer);
        var q = box.value.trim();
        if (q.length < 1) { list.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch(VENDOR_URL + '?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    list.innerHTML = '';
                    if (!data.length) { list.style.display = 'none'; return; }
                    var rect = box.getBoundingClientRect();
                    list.style.left = rect.left + 'px';
                    list.style.top = rect.bottom + 'px';
                    list.style.width = Math.max(rect.width, 200) + 'px';
                    data.forEach(function(item) {
                        var row = document.createElement('div');
                        row.style.cssText = 'padding:5px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        row.textContent = item.vendor_name;
                        row.addEventListener('mouseover', function() { row.style.background = '#EBF5FB'; });
                        row.addEventListener('mouseout', function() { row.style.background = ''; });
                        row.addEventListener('mousedown', function() {
                            box.value = item.vendor_name;
                            hidden.value = item.vendor_id;
                            list.style.display = 'none';
                        });
                        list.appendChild(row);
                    });
                    list.style.display = 'block';
                });
        }, 200);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== box) { list.style.display = 'none'; }
    });

    // A vendor was typed but never actually picked from the dropdown —
    // clear the text so we don't silently save a video with no vendor_id
    // but a misleading vendor name still sitting in the box.
    document.getElementById('videoAddForm').addEventListener('submit', function() {
        if (box.value.trim() && !hidden.value) {
            box.value = '';
        }
    });
})();

// "Where From?" — Source Details only matters (and is only required)
// when the video didn't come from a direct upload with no external
// source. Toggled on load too, so a validation-error round-trip
// (old('source_type')) shows the note box correctly re-opened.
function vlToggleSourceNote() {
    var type = document.getElementById('vlSourceType').value;
    var wrap = document.getElementById('vlSourceNoteWrap');
    var note = document.getElementById('vlSourceNote');
    var show = type !== 'DIRECT_UPLOAD';
    wrap.style.display = show ? 'block' : 'none';
    if (show) { note.setAttribute('required', 'required'); } else { note.removeAttribute('required'); note.value = note.value; }
}
document.addEventListener('DOMContentLoaded', vlToggleSourceNote);

// NEW 10 Aug 2026 — Category = Feature Guide swaps the Vendor field for
// a "Which Screen?" picker in the same grid cell (run on load too, so
// a validation-error round-trip with old('video_type')==='FEATURE_GUIDE'
// re-opens showing the right field, not the hidden-but-filled one).
function vlToggleFeatureKey() {
    var type = document.getElementById('vlVideoType').value;
    var isGuide = type === 'FEATURE_GUIDE';
    var vendorWrap = document.getElementById('vlVendorFieldWrap');
    var featureWrap = document.getElementById('vlFeatureKeyFieldWrap');
    var featureSelect = document.getElementById('vlFeatureKey');
    var hint = document.getElementById('vlCategoryHint');
    vendorWrap.style.display = isGuide ? 'none' : 'block';
    featureWrap.style.display = isGuide ? 'block' : 'none';
    if (isGuide) {
        featureSelect.setAttribute('required', 'required');
        document.getElementById('vlVendorId').value = '';
        document.getElementById('vlVendorBox').value = '';
        hint.textContent = VL_I18N.categoryHintFeatureGuide;
    } else {
        featureSelect.removeAttribute('required');
        hint.textContent = VL_I18N.categoryHintDefault;
    }
}
document.addEventListener('DOMContentLoaded', vlToggleFeatureKey);

// NEW 10 Aug 2026 — per Chris: "do you allow upload slide show and
// marketing flyer, google link form?" Content Type drives: which file
// formats are accepted (or a Link field instead of a file entirely),
// and hides the "storage folder not set up" warning for Link entries
// since they don't need the folder at all.
var VL_ACCEPT = {
    VIDEO:     '.mp4,.mov,.webm,.avi,.mkv,.m4v',
    SLIDESHOW: '.pdf',
    FLYER:     '.jpg,.jpeg,.png,.pdf'
};
var VL_HINT = {
    VIDEO:     VL_I18N.hintVideo,
    SLIDESHOW: VL_I18N.hintSlideshow,
    FLYER:     VL_I18N.hintFlyer
};
function vlToggleContentType() {
    var type = document.getElementById('vlContentType').value;
    var isLink = type === 'LINK';
    var fileWrap = document.getElementById('vlFileFieldWrap');
    var linkWrap = document.getElementById('vlLinkFieldWrap');
    var fileInput = document.getElementById('vlFileInput');
    var linkInput = document.getElementById('vlLinkInput');
    var folderWarning = document.getElementById('vlFolderWarning');

    fileWrap.style.display = isLink ? 'none' : 'block';
    linkWrap.style.display = isLink ? 'block' : 'none';
    if (isLink) {
        fileInput.removeAttribute('required');
        linkInput.setAttribute('required', 'required');
    } else {
        fileInput.setAttribute('required', 'required');
        linkInput.removeAttribute('required');
        fileInput.setAttribute('accept', VL_ACCEPT[type] || VL_ACCEPT.VIDEO);
        document.getElementById('vlFileHint').textContent = VL_HINT[type] || VL_HINT.VIDEO;
    }
    if (folderWarning) { folderWarning.style.display = isLink ? 'none' : 'block'; }
}
document.addEventListener('DOMContentLoaded', vlToggleContentType);
</script>
@endpush
@endsection
