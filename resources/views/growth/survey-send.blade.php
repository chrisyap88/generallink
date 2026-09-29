@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('growth.send_survey_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Survey Management Module, Phase 2 (task #232).
     Agent-facing distribution: pick an Active survey + your own
     customers, get a personalized tracked link per customer. Email
     sends for real (Mail::raw, same as every other real notification
     in this app); WhatsApp/Telegram/SMS open your own device's app with
     the link pre-filled — same reasoning as My Referral Link's share
     buttons. Customer picker reuses the existing customers.typeahead
     endpoint (already scoped to your own visible customers). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.send_survey_subtitle') }}</div>
        </div>
        @include('partials.feature-video-widget', ['featureKey' => 'SEND_SURVEY'])
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0; margin-bottom:6px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    @if($surveys->isEmpty())
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('growth.no_active_surveys') }}</div>
    @else

    <form method="GET" style="margin-bottom:6px; flex-shrink:0;">
        <select name="survey_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:10.5px; background:#fff; font-weight:600;">
            @foreach($surveys as $s)
            <option value="{{ $s->survey_id }}" {{ $selectedSurveyId === $s->survey_id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
        </select>
    </form>

    <div style="display:flex; gap:12px; flex:1; min-height:0;">

        <div style="flex:0 0 320px; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; display:flex; flex-direction:column; min-height:0;">
            <form method="POST" action="{{ route('survey-send.store') }}" id="sendForm" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                @csrf
                <input type="hidden" name="survey_id" value="{{ $selectedSurveyId }}">
                <div style="font-size:10px; color:#6b7280; margin-bottom:3px;">{{ __('growth.search_customer_label') }}</div>
                <div style="position:relative; margin-bottom:6px;">
                    <input type="text" id="custSearch" autocomplete="off" placeholder="{{ __('growth.type_to_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    <div id="custDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:5px; box-shadow:0 4px 12px rgba(0,0,0,.1); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
                <div id="selectedChips" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px; min-height:20px;"></div>
                <div id="chipInputs"></div>

                <label style="font-size:10px; color:#374151; margin-bottom:8px;"><input type="checkbox" name="send_email_now" value="1"> {{ __('growth.also_send_email_now_label') }}</label>

                <button type="submit" id="sendBtn" disabled style="background:#c4c9d0; color:#fff; border:none; border-radius:6px; padding:7px 12px; font-size:10.5px; font-weight:600; cursor:not-allowed; margin-top:auto;">{{ __('growth.generate_links_button') }}</button>
            </form>
        </div>

        <div style="flex:1; display:flex; flex-direction:column; gap:8px; min-height:0;">

            @if(session('generatedLinks'))
            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:10px 14px; flex-shrink:0; max-height:44%; overflow-y:auto;">
                <div style="font-size:9.5px; font-weight:700; color:#0369a1; margin-bottom:6px;">{{ __('growth.just_generated_heading') }}</div>
                @foreach(session('generatedLinks') as $g)
                <div style="border-bottom:1px solid #dbeafe; padding:6px 0;">
                    <div style="font-size:10px; font-weight:600; color:#111827;">{{ $g['name'] }} <span style="font-weight:400; color:#6b7280;">({{ $g['channel'] === 'EMAIL' ? __('growth.emailed_word') : __('growth.link_ready_word') }})</span></div>
                    <div style="display:flex; gap:6px; align-items:center; margin-top:3px;">
                        <input type="text" readonly value="{{ $g['url'] }}" style="flex:1; border:1px solid #d1d5db; border-radius:4px; padding:3px 6px; font-size:9px; color:#374151; background:#fff;">
                        <button type="button" onclick="copyGenLink(this)" style="background:var(--gl-blue); color:#fff; border:none; border-radius:4px; padding:3px 8px; font-size:8.5px; font-weight:600; cursor:pointer;">{{ __('growth.copy_button') }}</button>
                        @if($g['phone'])
                        <button type="button" onclick="window.open('https://wa.me/{{ preg_replace('/[^0-9]/', '', $g['phone']) }}?text=' + encodeURIComponent('{{ __('growth.whatsapp_feedback_message', ['title' => $g['survey_title'], 'url' => $g['url']]) }}'), '_blank')" style="background:#25D366; color:#fff; border:none; border-radius:4px; padding:3px 8px; font-size:8.5px; font-weight:600; cursor:pointer;">WhatsApp</button>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; flex:1; min-height:0; display:flex; flex-direction:column;">
                <div style="font-size:9px; color:#6b7280; text-transform:uppercase; font-weight:600; margin-bottom:6px; flex-shrink:0;">{{ __('growth.your_recent_sends_heading') }}</div>
                <div style="flex:1; min-height:0; overflow-y:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                        <thead>
                            <tr style="background:#f0f9ff;">
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.recipient_col') }}</th>
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.channel_col') }}</th>
                                <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.status') }}</th>
                                <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.sent_col') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSends as $r)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:5px 8px; color:#111827;">{{ $r->recipient_identifier }}</td>
                                <td style="padding:5px 8px; color:#6b7280;">{{ $r->channel }}</td>
                                <td style="padding:5px 8px; color:{{ $r->status === 'COMPLETED' ? '#166534' : '#6b7280' }}; font-weight:600;">{{ $r->status }}</td>
                                <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d M, g:ia') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" style="padding:12px; text-align:center; color:#9ca3af;">{{ __('growth.no_sends_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route($rolePrefix.'.dashboard') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>
</div>

<script>
var selected = {};
var typeaheadUrl = @json(route($rolePrefix.'.customers.typeahead'));
var i18nCopied = @json(__('growth.copied_notice'));
var searchBox = document.getElementById('custSearch');
var dropdown = document.getElementById('custDropdown');
var timer = null;

if (searchBox) {
    searchBox.addEventListener('input', function() {
        clearTimeout(timer);
        var term = this.value.trim();
        if (term.length < 2) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch(typeaheadUrl + '?term=' + encodeURIComponent(term))
                .then(function(r) { return r.json(); })
                .then(function(rows) {
                    if (!rows.length) { dropdown.style.display = 'none'; return; }
                    dropdown.innerHTML = rows.map(function(c) {
                        return '<div onclick="addCustomer(\'' + c.customer_id + '\', ' + JSON.stringify(c.full_name) + ', ' + JSON.stringify(c.phone||'') + ', ' + JSON.stringify(c.email||'') + ')" style="padding:6px 8px; font-size:10px; cursor:pointer; border-bottom:1px solid #f3f4f6;">' + c.full_name + ' <span style="color:#9ca3af;">' + (c.phone||c.email||'') + '</span></div>';
                    }).join('');
                    dropdown.style.display = 'block';
                });
        }, 250);
    });
}

function addCustomer(id, name, phone, email) {
    if (selected[id]) return;
    selected[id] = { name: name, phone: phone, email: email };
    renderChips();
    searchBox.value = '';
    dropdown.style.display = 'none';
}
function removeCustomer(id) {
    delete selected[id];
    renderChips();
}
function renderChips() {
    var chipsBox = document.getElementById('selectedChips');
    var inputsBox = document.getElementById('chipInputs');
    var ids = Object.keys(selected);
    chipsBox.innerHTML = ids.map(function(id) {
        return '<span style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:20px; padding:3px 8px; font-size:9.5px; color:#0369a1;">' + selected[id].name + ' <a href="javascript:void(0)" onclick="removeCustomer(\'' + id + '\')" style="color:#dc2626; font-weight:700; text-decoration:none; margin-left:4px;">&times;</a></span>';
    }).join('');
    inputsBox.innerHTML = ids.map(function(id) { return '<input type="hidden" name="customer_ids[]" value="' + id + '">'; }).join('');
    var btn = document.getElementById('sendBtn');
    if (ids.length > 0) {
        btn.disabled = false; btn.style.background = 'var(--gl-blue)'; btn.style.cursor = 'pointer';
    } else {
        btn.disabled = true; btn.style.background = '#c4c9d0'; btn.style.cursor = 'not-allowed';
    }
}
document.addEventListener('click', function(e) {
    if (searchBox && !searchBox.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});
function copyGenLink(btn) {
    var box = btn.previousElementSibling;
    box.select(); box.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(box.value).then(function() {
        var o = btn.textContent; btn.textContent = i18nCopied; setTimeout(function() { btn.textContent = o; }, 1500);
    });
}
</script>
@endsection
