@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.minutes_add_button'))

@section('content')

{{-- REBUILT 28 Aug 2026 — per Chris: full 3-step wizard replacing the old
     4-field form. Prev/Next between steps only (his standing no-scroll,
     no-jump-screens rule) — all three panels live on this ONE page and
     are toggled with JS (display:none/flex), so nothing is lost moving
     between steps and there's only ever one POST, fired by the Save
     button on Step 3.
       Step 1 — Meeting Details: Type, Date, Time, Venue, Subject, clip
                attachment (all optional except Date/Subject).
       Step 2 — Attendees: this entity's existing member roster (tick
                Attended/Not Attended) + "+ Add Guest" for visitors.
       Step 3 — Minutes Content: numbered sections (1.0, 2.0...) each
                with auto-numbered sub-paragraphs (1.1, 1.2...), a
                Carolyn rephrase button per paragraph, then End Minutes
                (locks further additions) and Save (the actual submit). --}}

<style>
.mm-box{flex:1; min-height:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; box-sizing:border-box; display:flex; flex-direction:column;}
.mm-label{display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;}
.mm-input{width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box; font-family:'Poppins',sans-serif; color:#263238;}
.mm-field{margin-bottom:10px;}
.mm-nav-btn{border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.mm-btn-prev{background:#1565C0; font-weight:700; color:#fff;}
.mm-btn-next{background:var(--gl-blue); color:#fff;}
.mm-btn-save{background:#16a34a; color:#fff;}
.mm-btn-end{background:#7c3aed; color:#fff;}
.mm-step-label{font-size:9.5px; color:#6b7280; font-weight:600;}
.mm-roster-row{display:flex; align-items:center; justify-content:space-between; padding:6px 10px; border-bottom:1px solid #f3f4f6; font-size:10px;}
.mm-roster-row:last-child{border-bottom:none;}
.mm-section-box{border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:8px; padding:10px; margin-bottom:10px;}
.mm-para-box{margin-top:6px; padding-left:10px; border-left:2px solid #eef2f7;}
.mm-para-num{font-size:9px; font-weight:700; color:var(--gl-blue); margin-bottom:2px;}
.mm-rephrase-btn{background:#f5f3ff; color:#7c3aed; border:1px solid #c4b5fd; border-radius:14px; padding:3px 10px; font-size:8.5px; font-weight:700; cursor:pointer; margin-top:3px;}
.mm-add-btn{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:700; cursor:pointer;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:6px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.minutes_add_button') }}</div>
            <div id="mmStepLabel" class="mm-step-label"></div>
        </div>
        <a href="{{ route('cbe.minutes.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="flex-shrink:0; background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form id="mmForm" method="POST" action="{{ route('cbe.minutes.store') }}" enctype="multipart/form-data" style="flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <input type="hidden" name="content_sections_json" id="mmContentSectionsJson" value="">

        {{-- STEP 1 — Meeting Details --}}
        <div id="mmStep1" class="mm-box">
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
                <div class="mm-field" style="flex:1; min-width:200px;">
                    <label class="mm-label">{{ __('cbe_records.field_meeting_type') }}</label>
                    <select name="meeting_type_id" class="mm-input">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($meetingTypes as $mt)
                        <option value="{{ $mt->meeting_type_id }}" @selected(old('meeting_type_id') == $mt->meeting_type_id)>{{ app()->getLocale() === 'zh' && $mt->type_name_zh ? $mt->type_name_zh : $mt->type_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mm-field" style="width:150px;">
                    <label class="mm-label">{{ __('cbe_records.field_meeting_date') }}</label>
                    <input type="date" name="meeting_date" value="{{ old('meeting_date') }}" required class="mm-input">
                </div>
                <div class="mm-field" style="width:130px;">
                    <label class="mm-label">{{ __('cbe_records.field_meeting_time') }}</label>
                    <input type="time" name="meeting_time" value="{{ old('meeting_time') }}" class="mm-input">
                </div>
                <div class="mm-field" style="width:130px;">
                    <label class="mm-label">{{ __('cbe_records.field_meeting_end_time') }}</label>
                    <input type="time" name="meeting_end_time" value="{{ old('meeting_end_time') }}" class="mm-input">
                </div>
            </div>
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
                <div class="mm-field" style="width:150px;">
                    <label class="mm-label">{{ __('cbe_records.field_meeting_mode') }}</label>
                    <select name="meeting_mode" id="mmMeetingMode" class="mm-input" onchange="mmToggleMode();">
                        <option value="PHYSICAL" @selected(old('meeting_mode', 'PHYSICAL') === 'PHYSICAL')>{{ __('cbe_records.mode_physical') }}</option>
                        <option value="ONLINE" @selected(old('meeting_mode') === 'ONLINE')>{{ __('cbe_records.mode_online') }}</option>
                    </select>
                </div>
                <div class="mm-field" style="flex:1; min-width:200px;" id="mmVenueField">
                    <label class="mm-label">{{ __('cbe_records.field_venue') }}</label>
                    <input type="text" name="venue" value="{{ old('venue') }}" maxlength="255" class="mm-input">
                </div>
                <div class="mm-field" style="flex:1; min-width:200px; display:none;" id="mmLinkField">
                    <label class="mm-label">{{ __('cbe_records.field_meeting_link') }}</label>
                    <input type="text" name="meeting_link" value="{{ old('meeting_link') }}" maxlength="500" class="mm-input" placeholder="https://...">
                </div>
                <div class="mm-field" style="flex:1.4; min-width:220px;">
                    <label class="mm-label">{{ __('cbe_records.field_title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="255" required class="mm-input">
                </div>
            </div>
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
                <div class="mm-field" style="flex:1; min-width:220px;">
                    <label class="mm-label">{{ __('cbe_records.field_agenda') }}</label>
                    <textarea name="agenda" maxlength="3000" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:10.5px; box-sizing:border-box; resize:vertical;">{{ old('agenda') }}</textarea>
                </div>
                <div class="mm-field" style="width:160px;">
                    <label class="mm-label">{{ __('cbe_records.field_quorum_required') }}</label>
                    <input type="number" name="quorum_required" value="{{ old('quorum_required') }}" min="1" max="9999" class="mm-input" placeholder="{{ __('cbe_records.field_quorum_optional') }}">
                </div>
            </div>
            <div class="mm-field">
                <label class="mm-label">📎 {{ __('cbe_records.field_attachment') }}</label>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="font-size:10.5px;">
                <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('cbe_records.attachment_hint') }}</div>
            </div>
            <div style="flex:1; min-height:0;"></div>
            <div style="flex-shrink:0; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.minutes.index') }}" class="mm-nav-btn mm-btn-prev">{{ __('network.prev') }}</a>
                <button type="button" class="mm-nav-btn mm-btn-next" onclick="mmGoToStep(2)">{{ __('network.next') }}</button>
            </div>
        </div>

        {{-- STEP 2 — Attendees --}}
        <div id="mmStep2" class="mm-box" style="display:none;">
            <div style="flex-shrink:0; font-size:9px; color:#94A3B8; margin-bottom:6px;">{{ __('cbe_records.attendees_hint') }}</div>
            <div style="flex:1; min-height:0; overflow-y:auto; border:1px solid #eef2f7; border-radius:6px;">
                @forelse($members as $m)
                <div class="mm-roster-row">
                    <span style="font-weight:600; color:#263238;">{{ $m->full_name }}</span>
                    <label style="display:flex; align-items:center; gap:4px; cursor:pointer; font-size:9.5px; color:#6b7280;">
                        <input type="hidden" name="roster_agent_ids[]" value="{{ $m->agent_id }}">
                        <input type="checkbox" name="attended_agent_ids[]" value="{{ $m->agent_id }}"> {{ __('cbe_records.attended_label') }}
                    </label>
                </div>
                @empty
                <div style="padding:16px; text-align:center; color:#9ca3af; font-size:9.5px;">{{ __('cbe_records.no_members_note') }}</div>
                @endforelse
            </div>
            <div id="mmGuestRows" style="flex-shrink:0; margin-top:8px;"></div>
            <div style="flex-shrink:0; margin-top:6px;">
                <button type="button" class="mm-add-btn" onclick="mmAddGuestRow()">+ {{ __('cbe_records.add_guest_button') }}</button>
            </div>
            <div style="flex-shrink:0; display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                <button type="button" class="mm-nav-btn mm-btn-prev" onclick="mmGoToStep(1)">{{ __('network.prev') }}</button>
                <button type="button" class="mm-nav-btn mm-btn-next" onclick="mmGoToStep(3)">{{ __('network.next') }}</button>
            </div>
        </div>

        {{-- STEP 3 — Minutes Content --}}
        <div id="mmStep3" class="mm-box" style="display:none;">
            <div style="flex-shrink:0; font-size:9px; color:#94A3B8; margin-bottom:6px;">{{ __('cbe_records.content_hint') }}</div>
            <div id="mmSections" style="flex:1; min-height:0; overflow-y:auto;"></div>
            <div id="mmSectionControls" style="flex-shrink:0; margin-top:6px;">
                <button type="button" class="mm-add-btn" onclick="mmAddSection()">+ {{ __('cbe_records.add_section_button') }}</button>
            </div>
            <div id="mmEndedNote" style="display:none; flex-shrink:0; font-size:9.5px; color:#7c3aed; font-weight:600; margin-top:6px;">✔ {{ __('cbe_records.minutes_ended_note') }}</div>
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:10px;">
                <button type="button" class="mm-nav-btn mm-btn-prev" onclick="mmGoToStep(2)">{{ __('network.prev') }}</button>
                <div style="display:flex; gap:8px;">
                    <button type="button" id="mmEndBtn" class="mm-nav-btn mm-btn-end" onclick="mmEndMinutes()">{{ __('cbe_records.end_minutes_button') }}</button>
                    <button type="submit" class="mm-nav-btn mm-btn-save">{{ __('cbe_records.save_button') }}</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function mmToggleMode(){
    var mode = document.getElementById('mmMeetingMode').value;
    document.getElementById('mmVenueField').style.display = (mode === 'ONLINE') ? 'none' : '';
    document.getElementById('mmLinkField').style.display = (mode === 'ONLINE') ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', mmToggleMode);
(function(){
    var TOTAL_STEPS = 3;
    var STEP_LABELS = [
        @json(__('cbe_records.step1_label')),
        @json(__('cbe_records.step2_label')),
        @json(__('cbe_records.step3_label'))
    ];
    var rephraseUrl = '{{ route('ai-write-assist') }}';
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    window.mmGoToStep = function(n){
        for (var i = 1; i <= TOTAL_STEPS; i++) {
            document.getElementById('mmStep' + i).style.display = (i === n) ? 'flex' : 'none';
        }
        document.getElementById('mmStepLabel').textContent = STEP_LABELS[n - 1];
    };
    mmGoToStep(1);

    // ---- Step 2: guest rows ----
    var guestCount = 0;
    window.mmAddGuestRow = function(){
        var idx = guestCount++;
        var row = document.createElement('div');
        row.style.cssText = 'display:flex; align-items:center; gap:8px; margin-bottom:4px;';
        row.innerHTML =
            '<input type="text" name="guest_names[]" maxlength="150" placeholder="' + @json(__('cbe_records.guest_name_placeholder')) + '" class="mm-input" style="flex:1;">' +
            '<label style="display:flex; align-items:center; gap:4px; font-size:9.5px; color:#6b7280; white-space:nowrap;"><input type="checkbox" name="guest_attended[' + idx + ']" checked> ' + @json(__('cbe_records.attended_label')) + '</label>' +
            '<button type="button" onclick="this.parentNode.remove()" style="background:none; border:none; color:#e53935; font-size:14px; cursor:pointer; line-height:1;">×</button>';
        document.getElementById('mmGuestRows').appendChild(row);
    };

    // ---- Step 3: sections / paragraphs ----
    var sectionCount = 0;
    var minutesEnded = false;

    window.mmAddSection = function(){
        if (minutesEnded) { return; }
        var secIdx = sectionCount++;
        var sec = document.createElement('div');
        sec.className = 'mm-section-box';
        sec.setAttribute('data-sec', secIdx);
        sec.innerHTML =
            '<div style="display:flex; align-items:center; gap:8px;">' +
                '<span class="mm-sec-number" style="font-size:11px; font-weight:800; color:var(--gl-blue); flex-shrink:0;"></span>' +
                '<input type="text" class="mm-sec-heading mm-input" maxlength="200" placeholder="' + @json(__('cbe_records.section_heading_placeholder')) + '" style="flex:1;">' +
                '<button type="button" onclick="this.closest(\'.mm-section-box\').remove(); mmRenumber();" style="background:none; border:none; color:#e53935; font-size:14px; cursor:pointer;">×</button>' +
            '</div>' +
            '<div class="mm-paragraphs"></div>' +
            '<div style="margin-top:6px;"><button type="button" class="mm-add-btn" onclick="mmAddParagraph(this.closest(\'.mm-section-box\'))">+ ' + @json(__('cbe_records.add_paragraph_button')) + '</button></div>';
        document.getElementById('mmSections').appendChild(sec);
        mmAddParagraph(sec);
        mmRenumber();
    };

    window.mmAddParagraph = function(sectionEl){
        if (minutesEnded) { return; }
        var para = document.createElement('div');
        para.className = 'mm-para-box';
        var uid = 'mmp' + Math.random().toString(36).slice(2, 9);
        para.innerHTML =
            '<div class="mm-para-num"></div>' +
            '<textarea class="mm-para-text mm-input" id="' + uid + '" rows="2" style="resize:vertical;" maxlength="2000"></textarea>' +
            '<div>' +
                '<button type="button" class="mm-rephrase-btn" onclick="mmRephrase(\'' + uid + '\', this)">✨ ' + @json(__('partials.carolyn_help_write_label')) + '</button>' +
                '<button type="button" onclick="this.closest(\'.mm-para-box\').remove(); mmRenumber();" style="background:none; border:none; color:#e53935; font-size:12px; cursor:pointer; margin-left:6px;">×</button>' +
            '</div>' +
            '<div class="mm-rephrase-box" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:6px; padding:6px 8px; margin-top:4px; font-size:9.5px;"></div>';
        sectionEl.querySelector('.mm-paragraphs').appendChild(para);
        mmRenumber();
    };

    window.mmRenumber = function(){
        var sections = document.querySelectorAll('#mmSections .mm-section-box');
        sections.forEach(function(sec, si){
            var secNum = (si + 1) + '.0';
            sec.querySelector('.mm-sec-number').textContent = secNum;
            var paras = sec.querySelectorAll('.mm-para-box');
            paras.forEach(function(p, pi){
                p.querySelector('.mm-para-num').textContent = (si + 1) + '.' + (pi + 1);
            });
        });
    };

    window.mmEndMinutes = function(){
        minutesEnded = true;
        document.getElementById('mmSectionControls').style.display = 'none';
        document.querySelectorAll('.mm-paragraphs').forEach(function(el){
            var btn = el.parentNode.querySelector('button.mm-add-btn');
            if (btn) { btn.style.display = 'none'; }
        });
        document.getElementById('mmEndedNote').style.display = 'block';
        document.getElementById('mmEndBtn').disabled = true;
        document.getElementById('mmEndBtn').style.opacity = '0.5';
    };

    // ---- Carolyn rephrase, per paragraph ----
    window.mmRephrase = function(textareaId, btnEl){
        var ta = document.getElementById(textareaId);
        var box = btnEl.parentNode.nextElementSibling;
        var draft = ta.value.trim();
        if (!draft) { alert(@json(__('partials.carolyn_no_draft_alert'))); return; }

        box.style.display = 'block';
        box.innerHTML = '<span style="color:#7c3aed; font-weight:600;">✨ ' + @json(__('partials.carolyn_polishing_status')) + '</span>';

        fetch(rephraseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ body: draft, content_type: 'meeting_minute_paragraph' })
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.status !== 'OK') {
                box.innerHTML = '<span style="color:#b71c1c;">⚠ ' + (data.message || @json(__('partials.carolyn_rewrite_failed_fallback'))) + '</span>';
                return;
            }
            var suggested = data.body;
            box.innerHTML =
                '<div style="white-space:pre-wrap; color:#111827; margin-bottom:6px;"></div>' +
                '<div style="display:flex; gap:6px;">' +
                    '<button type="button" class="mm-accept-btn" style="background:#16a34a; color:#fff; border:none; border-radius:14px; padding:3px 10px; font-size:8.5px; font-weight:700; cursor:pointer;">✅ ' + @json(__('partials.use_this_button')) + '</button>' +
                    '<button type="button" class="mm-cancel-btn" style="background:#c4c9d0; color:#fff; border:none; border-radius:14px; padding:3px 10px; font-size:8.5px; font-weight:700; cursor:pointer;">✖ ' + @json(__('partials.cancel_button')) + '</button>' +
                '</div>';
            box.querySelector('div').textContent = suggested;
            box.querySelector('.mm-accept-btn').addEventListener('click', function(){
                ta.value = suggested;
                box.style.display = 'none';
            });
            box.querySelector('.mm-cancel-btn').addEventListener('click', function(){
                box.style.display = 'none';
            });
        })
        .catch(function(){
            box.innerHTML = '<span style="color:#b71c1c;">⚠ ' + @json(__('partials.carolyn_connection_error')) + '</span>';
        });
    };

    // ---- Build content_sections_json just before submit ----
    document.getElementById('mmForm').addEventListener('submit', function(){
        var sections = [];
        document.querySelectorAll('#mmSections .mm-section-box').forEach(function(sec){
            var heading = sec.querySelector('.mm-sec-heading').value.trim();
            var paragraphs = [];
            sec.querySelectorAll('.mm-para-box').forEach(function(p){
                var text = p.querySelector('.mm-para-text').value.trim();
                if (text) { paragraphs.push({ text: text }); }
            });
            if (heading || paragraphs.length) { sections.push({ heading: heading, paragraphs: paragraphs }); }
        });
        document.getElementById('mmContentSectionsJson').value = JSON.stringify(sections);
    });

    // Start with one section so the screen isn't empty on first load.
    mmAddSection();
})();
</script>
@endsection
