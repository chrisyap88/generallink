{{-- NEW 17 Sep 2026 — per Chris: "you must have a preview screen how it is
     going to display." Lives right beside the form (no separate screen/
     navigation needed) and updates live as the officer types or changes the
     style — exact same colours/icon the Notice Board list will show once
     posted. --}}
<div style="font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:8px;">{{ __('cbe_records.preview_panel_title') }}</div>

<div id="cbeNoticePreviewCard" style="border-radius:10px; padding:16px; display:flex; flex-direction:column; gap:8px; min-height:140px;">
    <span id="cbeNoticePreviewPill" style="display:inline-flex; align-items:center; border-radius:999px; font-size:9.5px; font-weight:800; letter-spacing:0.03em; text-transform:uppercase; padding:4px 10px; width:fit-content;"></span>
    <div id="cbeNoticePreviewTitle" style="font-size:14.5px; font-weight:800;"></div>
    <div id="cbeNoticePreviewBody" style="font-size:10.5px; line-height:1.5; white-space:pre-wrap;"></div>
    <div id="cbeNoticePreviewAttachments" style="font-size:9px; font-weight:700; margin-top:auto;"></div>
</div>

<div style="font-size:9px; color:#9ca3af; margin-top:8px;">{{ __('cbe_records.preview_panel_hint') }}</div>
