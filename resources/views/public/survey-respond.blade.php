<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $survey->title }}</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Outfit',sans-serif;background:linear-gradient(160deg,#e0f7fa 0%,#f7fdff 40%);min-height:100vh;padding:24px 16px;}
    .card{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(13,90,142,.15);max-width:600px;width:100%;margin:0 auto;padding:28px 26px;}
    h3{font-size:19px;color:#0D5A8E;margin-bottom:4px;}
    .desc{font-size:12.5px;color:#6b7280;margin-bottom:16px;white-space:pre-line;}
    .error-box{background:#fee2e2;border:1px solid #fecaca;border-radius:6px;padding:8px 12px;color:#991b1b;font-size:11.5px;margin-bottom:14px;}
    .respondent-block{background:#f0f9ff;border-radius:10px;padding:14px 16px;margin-bottom:18px;}
    label.field-label{display:block;font-size:10.5px;color:#374151;font-weight:600;margin-bottom:3px;margin-top:8px;}
    label.field-label:first-child{margin-top:0;}
    input[type=text],input[type=email],input[type=date],input[type=time],input[type=number],select,textarea{
        width:100%;border:1px solid #d1d5db;border-radius:6px;padding:8px 10px;font-size:12.5px;font-family:inherit;background:#fff;
    }
    .hint{font-size:10px;color:#9ca3af;margin-top:2px;}
    .question{border-top:1px solid #f3f4f6;padding:14px 0;}
    .qtext{font-size:13px;font-weight:600;color:#111827;}
    .req{color:#dc2626;}
    .qdesc{font-size:11px;color:#6b7280;margin-top:2px;}
    .qinput{margin-top:8px;}
    label.opt{display:block;font-size:12px;color:#374151;margin-bottom:6px;cursor:pointer;}
    label.opt input{margin-right:6px;}
    .scale-row{display:flex;gap:8px;flex-wrap:wrap;}
    label.scale-opt{border:1px solid #d1d5db;border-radius:6px;padding:6px 10px;font-size:12px;color:#374151;cursor:pointer;}
    label.scale-opt input{margin-right:4px;}
    .qhelp{font-size:10.5px;color:#9ca3af;margin-top:6px;}
    .matrix-table{width:100%;border-collapse:collapse;font-size:10.5px;margin-top:4px;}
    .matrix-table td{padding:5px;}
    .mcol{text-align:center;color:#6b7280;font-weight:600;}
    .mrow{color:#374151;}
    .submit-btn{display:block;width:100%;margin-top:20px;background:linear-gradient(90deg,#1B9AE4 0%,#0D5A8E 100%);color:#fff;border:none;border-radius:8px;padding:12px;font-size:13px;font-weight:700;letter-spacing:.03em;cursor:pointer;}
    .footer-note{text-align:center;font-size:9px;color:#b0bec5;letter-spacing:.08em;text-transform:uppercase;margin-top:16px;}
</style>
</head>
<body>
<form method="POST" action="{{ route('public.survey.submit', $survey->public_token) }}" id="surveyForm">
@csrf
<input type="hidden" name="response_id" value="{{ $responseId }}">
<div class="card">
    <h3>{{ $survey->title }}</h3>
    @if($survey->description)<div class="desc">{{ $survey->description }}</div>@endif

    @if($errors->any())
    <div class="error-box">@foreach($errors->all() as $e)⚠ {{ $e }}<br>@endforeach</div>
    @endif

    @if(!$survey->allow_anonymous)
    <div class="respondent-block">
        <label class="field-label">{{ __('public.your_name_required_label') }}</label>
        <input type="text" name="respondent_name" value="{{ old('respondent_name') }}" required>
        <label class="field-label">{{ __('public.you_are_a_required_label') }}</label>
        <select name="respondent_type" required>
            <option value="">{{ __('growth.select_dash_dash') }}</option>
            <option value="EXISTING_CUSTOMER" {{ old('respondent_type')==='EXISTING_CUSTOMER'?'selected':'' }}>{{ __('growth.existing_customer_option') }}</option>
            <option value="NEW_CUSTOMER" {{ old('respondent_type')==='NEW_CUSTOMER'?'selected':'' }}>{{ __('growth.new_customer_option') }}</option>
            <option value="PROSPECT" {{ old('respondent_type')==='PROSPECT'?'selected':'' }}>{{ __('customer_kpi.prospect_label') }}</option>
        </select>
        @if($survey->require_respondent_contact)
        <label class="field-label">{{ __('growth.email') }}</label>
        <input type="email" name="respondent_email" value="{{ old('respondent_email') }}">
        <label class="field-label">{{ __('network.phone') }}</label>
        <input type="text" name="respondent_phone" value="{{ old('respondent_phone') }}">
        <div class="hint">{{ __('public.contact_hint') }}</div>
        @endif
    </div>
    @else
    <div class="hint" style="margin-bottom:14px;">{{ __('public.anonymous_survey_hint') }}</div>
    @endif

    @foreach($questions as $i => $q)
    <div class="question" id="q_{{ $q->question_id }}">
        <div class="qtext">{{ $i+1 }}. {{ $q->question_text }} @if($q->is_required)<span class="req">*</span>@endif</div>
        @if($q->question_description)<div class="qdesc">{{ $q->question_description }}</div>@endif
        <div class="qinput">
            @switch($q->question_type)
                @case('SINGLE_CHOICE')
                    @foreach($q->question_options as $o)
                    <label class="opt"><input type="radio" name="answers[{{ $q->question_id }}]" value="{{ $o->option_id }}" {{ $q->is_required ? 'required' : '' }}> {{ $o->option_text }}</label>
                    @endforeach
                    @break
                @case('DROPDOWN')
                    <select name="answers[{{ $q->question_id }}]" {{ $q->is_required ? 'required' : '' }}>
                        <option value="">{{ __('growth.select_dash_dash') }}</option>
                        @foreach($q->question_options as $o)<option value="{{ $o->option_id }}">{{ $o->option_text }}</option>@endforeach
                    </select>
                    @break
                @case('YES_NO')
                    <label class="opt"><input type="radio" name="answers[{{ $q->question_id }}]" value="Yes" {{ $q->is_required ? 'required' : '' }}> {{ __('growth.yes_option') }}</label>
                    <label class="opt"><input type="radio" name="answers[{{ $q->question_id }}]" value="No"> {{ __('growth.no_option') }}</label>
                    @break
                @case('MULTIPLE_CHOICE')
                    @foreach($q->question_options as $o)
                    <label class="opt"><input type="checkbox" name="answers[{{ $q->question_id }}][]" value="{{ $o->option_id }}"> {{ $o->option_text }}</label>
                    @endforeach
                    @break
                @case('RATING_SCALE')
                @case('NUMERIC_RATING')
                @case('LIKERT_SCALE')
                    <div class="scale-row">
                    @for($n=1; $n<=($q->scale_max ?? 5); $n++)
                        <label class="scale-opt"><input type="radio" name="answers[{{ $q->question_id }}]" value="{{ $n }}" {{ $q->is_required ? 'required' : '' }}> {{ $n }}</label>
                    @endfor
                    </div>
                    @break
                @case('STAR_RATING')
                    <div class="scale-row">
                    @for($n=1; $n<=($q->scale_max ?? 5); $n++)
                        <label class="scale-opt"><input type="radio" name="answers[{{ $q->question_id }}]" value="{{ $n }}" {{ $q->is_required ? 'required' : '' }}> {{ str_repeat('★', $n) }}</label>
                    @endfor
                    </div>
                    @break
                @case('SHORT_TEXT')
                @case('EMAIL')
                @case('PHONE')
                    <input type="{{ $q->question_type === 'EMAIL' ? 'email' : 'text' }}" name="answers[{{ $q->question_id }}]" @if($q->min_length) minlength="{{ $q->min_length }}" @endif @if($q->max_length) maxlength="{{ $q->max_length }}" @endif {{ $q->is_required ? 'required' : '' }}>
                    @break
                @case('LONG_TEXT')
                    <textarea name="answers[{{ $q->question_id }}]" rows="3" @if($q->max_length) maxlength="{{ $q->max_length }}" @endif {{ $q->is_required ? 'required' : '' }}></textarea>
                    @break
                @case('DATE')
                    <input type="date" name="answers[{{ $q->question_id }}]" {{ $q->is_required ? 'required' : '' }}>
                    @break
                @case('TIME')
                    <input type="time" name="answers[{{ $q->question_id }}]" {{ $q->is_required ? 'required' : '' }}>
                    @break
                @case('NUMBER')
                    <input type="number" step="any" name="answers[{{ $q->question_id }}]" @if($q->min_value !== null) min="{{ $q->min_value }}" @endif @if($q->max_value !== null) max="{{ $q->max_value }}" @endif {{ $q->is_required ? 'required' : '' }}>
                    @break
                @case('MATRIX')
                    @if($q->matrix)
                    <table class="matrix-table">
                        <tr><td></td>@foreach($q->matrix['columns'] ?? [] as $col)<td class="mcol">{{ $col }}</td>@endforeach</tr>
                        @foreach($q->matrix['rows'] ?? [] as $row)
                        <tr><td class="mrow">{{ $row }}</td>@foreach($q->matrix['columns'] ?? [] as $col)<td style="text-align:center;"><input type="radio" name="answers[{{ $q->question_id }}][{{ $row }}]" value="{{ $col }}" {{ $q->is_required ? 'required' : '' }}></td>@endforeach</tr>
                        @endforeach
                    </table>
                    @endif
                    @break
            @endswitch
        </div>
        @if($q->help_text)<div class="qhelp">💡 {{ $q->help_text }}</div>@endif
    </div>
    @endforeach

    @if($questions->isEmpty())
    <div style="text-align:center; color:#9ca3af; padding:24px 0; font-size:12px;">{{ __('public.no_questions_note') }}</div>
    @endif

    <button type="submit" class="submit-btn">{{ __('masterfile.submit_button') }}</button>
    <div class="footer-note">GeneralLink</div>
</div>
</form>

@php
    // Pre-built as a plain PHP array (not an inline arrow-function/array
    // literal passed straight into @json(...)) — keeps the expression
    // simple enough for Blade's directive-argument scanner to parse
    // reliably regardless of how many keys/lines it spans.
    $logicRulesForJs = $logicRules->map(function ($r) {
        return [
            'source'   => $r->source_question_id,
            'target'   => $r->target_question_id,
            'option'   => $r->condition_option_id,
            'operator' => $r->condition_operator,
            'value'    => $r->condition_value,
            'action'   => $r->action,
        ];
    });
@endphp
<script>
// Conditional/Skip logic — mirrors PublicSurveyController's server-side
// resolveVisibleQuestions() so what the respondent sees always matches
// what actually gets saved. A hidden question's inputs are disabled
// (not just visually hidden) so the browser neither submits their
// value nor blocks submission on a "required" attribute they can't see.
var logicRules = @json($logicRulesForJs);

var form = document.getElementById('surveyForm');

function getAnswerValue(qid) {
    // Matches answers[qid] (single value/radio/checkbox-array) and the
    // matrix variant answers[qid][rowName] — both use qid as a prefix.
    var els = form.querySelectorAll('[name^="answers[' + qid + ']"]');
    if (!els.length) return null;
    if (els[0].type === 'checkbox') {
        var vals = [];
        els.forEach(function(el) { if (el.checked) vals.push(el.value); });
        return vals;
    }
    if (els[0].type === 'radio') {
        var checked = null;
        els.forEach(function(el) { if (el.checked) checked = el.value; });
        return checked;
    }
    return els[0].value;
}

function conditionMet(rule) {
    var val = getAnswerValue(rule.source);
    if (rule.option) {
        if (Array.isArray(val)) return val.indexOf(rule.option) !== -1;
        return val === rule.option;
    }
    if (rule.operator && rule.value !== null) {
        var num = parseFloat(val), cmp = parseFloat(rule.value);
        if (rule.operator === 'EQUALS') return String(val) === String(rule.value);
        if (rule.operator === 'NOT_EQUALS') return String(val) !== String(rule.value);
        if (rule.operator === 'GREATER_THAN') return !isNaN(num) && !isNaN(cmp) && num > cmp;
        if (rule.operator === 'LESS_THAN') return !isNaN(num) && !isNaN(cmp) && num < cmp;
    }
    return false;
}

function applyLogic() {
    var byTarget = {};
    logicRules.forEach(function(r) { (byTarget[r.target] = byTarget[r.target] || []).push(r); });
    Object.keys(byTarget).forEach(function(qid) {
        var rules = byTarget[qid];
        var showRules = rules.filter(function(r) { return r.action === 'SHOW'; });
        var skipRules = rules.filter(function(r) { return r.action === 'SKIP'; });
        var visible = showRules.length === 0 ? true : showRules.some(conditionMet);
        if (skipRules.some(conditionMet)) visible = false;
        var wrap = document.getElementById('q_' + qid);
        if (!wrap) return;
        wrap.style.display = visible ? '' : 'none';
        wrap.querySelectorAll('input,select,textarea').forEach(function(el) { el.disabled = !visible; });
    });
}
form.addEventListener('change', applyLogic);
applyLogic();
</script>
</body>
</html>
