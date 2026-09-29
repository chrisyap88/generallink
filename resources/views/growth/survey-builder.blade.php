@extends('layouts.dashboard')

@section('page-title', __('growth.survey_question_builder_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Survey Management Module, Phase 1 (task #231).
     Question Builder — spec section 8 (all 16 question types), section
     9 (validation rules), section 10 (Phase 1 single-condition skip/
     show logic). Not a literal drag-and-drop Google Forms clone — this
     app has never used a drag-and-drop library, so reordering uses the
     same up/down-arrow technique already used elsewhere (e.g. Products
     Offered tab), which is more reliable at this app's compact,
     no-scroll scale than adding a new JS dependency for one screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    {{-- Compacted 26 Jul 2026 per Chris — this whole header collapsed to
         one single row (breadcrumb + title + status badge + actions all
         inline) to reclaim vertical space for the two panels below,
         since he explicitly does not want to reduce browser zoom to see
         everything. --}}
    <div style="flex-shrink:0; margin-bottom:4px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;">
        <div style="display:flex; align-items:baseline; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('admin.growth.surveys.index') }}" style="color:#1565C0; text-decoration:none; font-size:9px; font-weight:600;">&lsaquo; {{ __('growth.survey_management_link') }}</a>
            <span style="font-weight:700; font-size:11.5px; color:#1565C0;">{{ $survey->name }} — {{ __('growth.questions_link') }}</span>
            <span style="font-size:8px; color:#9ca3af;">{{ __('growth.survey_status_'.strtolower($survey->status)) }} &middot; {{ __('growth.questions_count_suffix', ['count' => $questions->count()]) }}</span>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-shrink:0;">
            <a href="{{ route('admin.growth.surveys.edit', $survey->survey_id) }}" style="font-size:9px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.edit_info_link') }}</a>
            <a href="{{ route('admin.growth.surveys.preview', $survey->survey_id) }}" style="font-size:9px; color:#7c3aed; font-weight:600; text-decoration:none;">{{ __('growth.preview_link') }}</a>
            @if(in_array($survey->status, ['PUBLISHED','ACTIVE','PAUSED','CLOSED']))
            <a href="{{ route('admin.growth.surveys.distribute', $survey->survey_id) }}" style="font-size:9px; color:#0369a1; font-weight:600; text-decoration:none;">{{ __('growth.distribute_link_plain') }}</a>
            <a href="{{ route('admin.growth.surveys.responses', $survey->survey_id) }}" style="font-size:9px; color:#0369a1; font-weight:600; text-decoration:none;">{{ __('growth.responses_link_plain') }}</a>
            @endif
            @if($survey->status === 'DRAFT')
            <form method="POST" action="{{ route('admin.growth.surveys.publish', $survey->survey_id) }}">@csrf<button type="submit" style="background:#0369a1; color:#fff; border:none; border-radius:6px; padding:3px 10px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('growth.publish_button') }}</button></form>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:3px 10px; color:#065f46; font-size:10px; font-weight:500; flex-shrink:0; margin-bottom:4px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:3px 10px; color:#991b1b; font-size:10px; flex-shrink:0; margin-bottom:4px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="display:flex; gap:10px; flex:1; min-height:0;">

        {{-- LEFT — question list --}}
        <div style="flex:1; display:flex; flex-direction:column; min-height:0;">
            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; overflow-y:auto;">
                @forelse($questions as $i => $q)
                <div style="border-bottom:1px solid #f3f4f6; padding:7px 4px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:6px;">
                        <div style="flex:1;">
                            <div style="font-size:10.5px; font-weight:700; color:#111827;">{{ $i+1 }}. {{ $q->question_text }} @if($q->is_required)<span style="color:#dc2626;">*</span>@endif</div>
                            @if($q->question_description)<div style="font-size:9px; color:#6b7280; margin-top:1px;">{{ $q->question_description }}</div>@endif
                            <div style="margin-top:3px; display:flex; gap:5px; flex-wrap:wrap;">
                                <span style="background:#f0f9ff; color:#0369a1; font-size:7.5px; font-weight:700; padding:1px 6px; border-radius:20px;">{{ __('growth.qtype_'.strtolower($q->question_type)) }}</span>
                                @if($q->question_options->isNotEmpty())<span style="font-size:8px; color:#9ca3af;">{{ __('growth.options_count_suffix', ['count' => $q->question_options->count()]) }}</span>@endif
                            </div>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:2px; align-items:flex-end; flex-shrink:0;">
                            <div style="display:flex; gap:1px;">
                                <form method="POST" action="{{ route('admin.growth.surveys.questions.move', [$survey->survey_id, $q->question_id]) }}"><input type="hidden" name="direction" value="up">@csrf<button type="submit" style="background:none; border:none; color:{{ $i===0 ? '#d1d5db' : '#374151' }}; font-size:11px; cursor:pointer;" {{ $i===0 ? 'disabled' : '' }}>▲</button></form>
                                <form method="POST" action="{{ route('admin.growth.surveys.questions.move', [$survey->survey_id, $q->question_id]) }}"><input type="hidden" name="direction" value="down">@csrf<button type="submit" style="background:none; border:none; color:{{ $i===$questions->count()-1 ? '#d1d5db' : '#374151' }}; font-size:11px; cursor:pointer;" {{ $i===$questions->count()-1 ? 'disabled' : '' }}>▼</button></form>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <a href="{{ route('admin.growth.surveys.builder', $survey->survey_id) }}?edit={{ $q->question_id }}" style="font-size:9px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.edit_link') }}</a>
                                <form method="POST" action="{{ route('admin.growth.surveys.questions.duplicate', [$survey->survey_id, $q->question_id]) }}"><button type="submit" style="background:none; border:none; color:#374151; font-size:9px; font-weight:600; cursor:pointer;">{{ __('growth.duplicate_button') }}</button>@csrf</form>
                                <form method="POST" action="{{ route('admin.growth.surveys.questions.destroy', [$survey->survey_id, $q->question_id]) }}" onsubmit="return confirm({{ Js::from(__('growth.delete_question_confirm')) }});">@csrf @method('DELETE')<button type="submit" style="background:none; border:none; color:#dc2626; font-size:9px; font-weight:600; cursor:pointer;">{{ __('growth.delete_button') }}</button></form>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div style="text-align:center; color:#9ca3af; padding:24px 0; font-size:10.5px;">{{ __('growth.no_questions_yet_add_first') }}</div>
                @endforelse
            </div>
        </div>

        {{-- RIGHT — Add/Edit Question + Conditional Logic. Both cards are
             sized to their natural (compact) content height, not forced to
             stretch/shrink — so on a normal window both are visible
             together with no scrolling at all. This column only scrolls
             as a last resort if the browser window is unusually short,
             which guarantees Conditional Logic is always reachable
             instead of ever being invisibly squeezed to zero height. --}}
        <div style="flex:0 0 340px; display:flex; flex-direction:column; gap:8px; min-height:0; overflow-y:auto;">

            <form method="POST" action="{{ $editingQuestion ? route('admin.growth.surveys.questions.update', [$survey->survey_id, $editingQuestion->question_id]) : route('admin.growth.surveys.questions.store', $survey->survey_id) }}" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:7px 10px; display:flex; flex-direction:column; flex-shrink:0;">
                @csrf
                <div style="font-size:10px; font-weight:700; color:#1565C0; margin-bottom:4px; display:flex; justify-content:space-between;">
                    <span>{{ $editingQuestion ? __('growth.edit_question_heading') : __('growth.add_question_heading') }}</span>
                    @if($editingQuestion)<a href="{{ route('admin.growth.surveys.builder', $survey->survey_id) }}" style="font-size:9px; color:#6b7280; font-weight:600; text-decoration:none;">{{ __('growth.cancel_link') }}</a>@endif
                </div>
                {{-- No inner scroll here on purpose — only one of qOptionsWrap
                     / qScaleWrap / qTextLenWrap / qNumberRangeWrap / qMatrixWrap
                     is ever visible at a time (toggleQuestionType() below),
                     so this panel is sized to always fit without scrolling. --}}
                <div style="display:flex; flex-direction:column; gap:4px;">

                    <div>
                        <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.question_text_label') }}</div>
                        <textarea name="question_text" required rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box; resize:none;">{{ old('question_text', $editingQuestion->question_text ?? '') }}</textarea>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:5px;">
                        <div>
                            <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.description_optional_label') }}</div>
                            <input type="text" name="question_description" value="{{ old('question_description', $editingQuestion->question_description ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box;">
                        </div>
                        <div>
                            <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.help_text_optional_label') }}</div>
                            <input type="text" name="help_text" value="{{ old('help_text', $editingQuestion->help_text ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>
                    <div>
                        <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.question_type_label') }}</div>
                        <select name="question_type" id="qType" onchange="toggleQuestionType()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; background:#fff; box-sizing:border-box;">
                            @php($qt = old('question_type', $editingQuestion->question_type ?? 'SHORT_TEXT'))
                            <option value="SINGLE_CHOICE" {{ $qt==='SINGLE_CHOICE'?'selected':'' }}>{{ __('growth.qtype_single_choice') }}</option>
                            <option value="MULTIPLE_CHOICE" {{ $qt==='MULTIPLE_CHOICE'?'selected':'' }}>{{ __('growth.qtype_multiple_choice') }}</option>
                            <option value="DROPDOWN" {{ $qt==='DROPDOWN'?'selected':'' }}>{{ __('growth.qtype_dropdown') }}</option>
                            <option value="YES_NO" {{ $qt==='YES_NO'?'selected':'' }}>{{ __('growth.qtype_yes_no') }}</option>
                            <option value="RATING_SCALE" {{ $qt==='RATING_SCALE'?'selected':'' }}>{{ __('growth.qtype_rating_scale') }}</option>
                            <option value="STAR_RATING" {{ $qt==='STAR_RATING'?'selected':'' }}>{{ __('growth.qtype_star_rating') }}</option>
                            <option value="NUMERIC_RATING" {{ $qt==='NUMERIC_RATING'?'selected':'' }}>{{ __('growth.qtype_numeric_rating') }}</option>
                            <option value="LIKERT_SCALE" {{ $qt==='LIKERT_SCALE'?'selected':'' }}>{{ __('growth.qtype_likert_scale') }}</option>
                            <option value="SHORT_TEXT" {{ $qt==='SHORT_TEXT'?'selected':'' }}>{{ __('growth.qtype_short_text') }}</option>
                            <option value="LONG_TEXT" {{ $qt==='LONG_TEXT'?'selected':'' }}>{{ __('growth.qtype_long_text') }}</option>
                            <option value="EMAIL" {{ $qt==='EMAIL'?'selected':'' }}>{{ __('growth.qtype_email') }}</option>
                            <option value="PHONE" {{ $qt==='PHONE'?'selected':'' }}>{{ __('growth.qtype_phone') }}</option>
                            <option value="DATE" {{ $qt==='DATE'?'selected':'' }}>{{ __('growth.qtype_date') }}</option>
                            <option value="TIME" {{ $qt==='TIME'?'selected':'' }}>{{ __('growth.qtype_time') }}</option>
                            <option value="NUMBER" {{ $qt==='NUMBER'?'selected':'' }}>{{ __('growth.qtype_number') }}</option>
                            <option value="MATRIX" {{ $qt==='MATRIX'?'selected':'' }}>{{ __('growth.qtype_matrix') }}</option>
                        </select>
                    </div>

                    <div id="qOptionsWrap">
                        <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.options_one_per_line_label') }}</div>
                        <textarea name="options_text" rows="2" placeholder="{!! __('growth.options_placeholder') !!}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box; resize:none;">{{ old('options_text', isset($editingQuestion) ? $editingQuestion->question_options->pluck('option_text')->implode("\n") : '') }}</textarea>
                    </div>

                    <div id="qScaleWrap">
                        <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.scale_max_label') }}</div>
                        <input type="number" name="scale_max" min="2" max="10" value="{{ old('scale_max', $editingQuestion->scale_max ?? 5) }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box;">
                    </div>

                    {{-- REMOVED 26 Jul 2026 per Chris — Min/Max Length was
                         taking up a full row's worth of vertical space for
                         a rarely-used validation constraint on the most
                         common question types (Short/Long Text), which is
                         exactly what was pushing Conditional Logic out of
                         view. Dropped from the UI to reclaim that space;
                         the underlying min_length/max_length columns are
                         untouched (nullable, simply always null going
                         forward) — no data loss, matches this app's
                         existing pattern of trimming over-engineered
                         fields (see task #79). --}}

                    <div id="qNumberRangeWrap" style="display:grid; grid-template-columns:1fr 1fr; gap:5px;">
                        <div>
                            <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.min_value_label') }}</div>
                            <input type="number" step="0.01" name="min_value" value="{{ old('min_value', $editingQuestion->min_value ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box;">
                        </div>
                        <div>
                            <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.max_value_label') }}</div>
                            <input type="number" step="0.01" name="max_value" value="{{ old('max_value', $editingQuestion->max_value ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>

                    <div id="qMatrixWrap" style="display:grid; grid-template-columns:1fr 1fr; gap:5px;">
                        <div>
                            <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.rows_one_per_line_label') }}</div>
                            <textarea name="matrix_rows_text" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box; resize:none;">{{ old('matrix_rows_text', isset($editingQuestion) && $editingQuestion->matrix ? implode("\n", $editingQuestion->matrix['rows'] ?? []) : '') }}</textarea>
                        </div>
                        <div>
                            <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.columns_one_per_line_label') }}</div>
                            <textarea name="matrix_columns_text" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:10px; box-sizing:border-box; resize:none;">{{ old('matrix_columns_text', isset($editingQuestion) && $editingQuestion->matrix ? implode("\n", $editingQuestion->matrix['columns'] ?? []) : '') }}</textarea>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label style="font-size:9.5px; color:#374151;"><input type="checkbox" name="is_required" {{ old('is_required', $editingQuestion->is_required ?? true) ? 'checked' : '' }}> {{ __('growth.required_checkbox_label') }}</label>
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:10px; font-weight:600; cursor:pointer;">{{ $editingQuestion ? __('growth.save_question_button') : __('growth.add_question_heading') }}</button>
                    </div>
                </div>
            </form>

            {{-- Conditional / Skip Logic — spec section 10 (Phase 1:
                 single-condition rules). Natural (not stretched) height so
                 it always sits directly below Add Question, visible at
                 the same time. Its rule list has its own small bounded
                 scroll (max-height, not flex:1) so a long list of rules
                 never grows this card past a reasonable size — only that
                 inner list scrolls, never the page. --}}
            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:7px 10px; flex-shrink:0; display:flex; flex-direction:column;">
                <div style="font-size:9.5px; font-weight:700; color:#1565C0; margin-bottom:3px; flex-shrink:0;">{{ __('growth.conditional_logic_heading') }}</div>
                <div style="max-height:110px; overflow-y:auto; font-size:8.5px; color:#374151; margin-bottom:5px;">
                    @forelse($logicRules as $l)
                    @php
                        $conditionText = $l->option_text ? '= '.$l->option_text : ($l->condition_operator ? strtolower(str_replace('_',' ',$l->condition_operator)).' '.$l->condition_value : '');
                        $actionText = $l->action === 'SHOW' ? __('growth.action_show_word') : __('growth.action_skip_word');
                    @endphp
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f3f4f6; padding:3px 0;">
                        <span>{{ __('growth.logic_rule_line', ['source' => \Illuminate\Support\Str::limit($l->source_text, 22), 'condition' => $conditionText, 'action' => $actionText, 'target' => \Illuminate\Support\Str::limit($l->target_text, 18)]) }}</span>
                        <form method="POST" action="{{ route('admin.growth.surveys.logic.destroy', [$survey->survey_id, $l->logic_id]) }}">@csrf @method('DELETE')<button type="submit" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:9px;">✕</button></form>
                    </div>
                    @empty
                    <div style="color:#9ca3af;">{{ __('growth.no_logic_rules_yet') }}</div>
                    @endforelse
                </div>
                @if($questions->count() >= 2)
                <form method="POST" action="{{ route('admin.growth.surveys.logic.store', $survey->survey_id) }}" style="display:grid; grid-template-columns:1fr 1fr; gap:4px; flex-shrink:0; font-size:9px;">
                    @csrf
                    <select name="source_question_id" required style="grid-column:1/3; border:1px solid #d1d5db; border-radius:4px; padding:3px; font-size:9px;">
                        <option value="">{{ __('growth.if_question_placeholder') }}</option>
                        @foreach($questions as $q)<option value="{{ $q->question_id }}">{{ \Illuminate\Support\Str::limit($q->question_text, 40) }}</option>@endforeach
                    </select>
                    <select name="condition_option_id" style="border:1px solid #d1d5db; border-radius:4px; padding:3px; font-size:9px;">
                        <option value="">{{ __('growth.answer_is_option_placeholder') }}</option>
                        @foreach($questions as $q)@foreach($q->question_options as $o)<option value="{{ $o->option_id }}">{{ \Illuminate\Support\Str::limit($o->option_text, 20) }}</option>@endforeach @endforeach
                    </select>
                    <select name="action" required style="border:1px solid #d1d5db; border-radius:4px; padding:3px; font-size:9px;">
                        <option value="SHOW">{{ __('growth.then_show_option') }}</option>
                        <option value="SKIP">{{ __('growth.then_skip_option') }}</option>
                    </select>
                    <select name="target_question_id" required style="grid-column:1/3; border:1px solid #d1d5db; border-radius:4px; padding:3px; font-size:9px;">
                        <option value="">{{ __('growth.this_question_placeholder') }}</option>
                        @foreach($questions as $q)<option value="{{ $q->question_id }}">{{ \Illuminate\Support\Str::limit($q->question_text, 40) }}</option>@endforeach
                    </select>
                    <button type="submit" style="grid-column:1/3; background:#1565C0; color:#fff; border:none; border-radius:4px; padding:4px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('growth.add_rule_button') }}</button>
                </form>
                @else
                <div style="font-size:8px; color:#9ca3af;">{{ __('growth.add_2_questions_note') }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route('admin.growth.surveys.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>
</div>

<script>
function toggleQuestionType() {
    var type = document.getElementById('qType').value;
    var optionTypes = ['SINGLE_CHOICE','MULTIPLE_CHOICE','DROPDOWN','LIKERT_SCALE'];
    var scaleTypes = ['RATING_SCALE','STAR_RATING','NUMERIC_RATING','LIKERT_SCALE'];
    document.getElementById('qOptionsWrap').style.display = optionTypes.includes(type) ? 'block' : 'none';
    document.getElementById('qScaleWrap').style.display = scaleTypes.includes(type) ? 'block' : 'none';
    document.getElementById('qNumberRangeWrap').style.display = type === 'NUMBER' ? 'grid' : 'none';
    document.getElementById('qMatrixWrap').style.display = type === 'MATRIX' ? 'grid' : 'none';
}
toggleQuestionType();
</script>
@endsection
