@extends('layouts.dashboard')

@section('page-title', $survey ? __('growth.edit_survey_title') : __('growth.create_survey_title'))

@section('content')

{{-- NEW 25 Jul 2026 — Survey Management Module, Phase 1 (task #231).
     Survey Information screen — spec section 6 (Create Survey) + 7
     (Survey Objective) + 11 (Target Respondents). Saving takes you
     straight to the Question Builder next. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        {{-- Plain text link, NOT a blue pill — Prev/Next belong at the
             bottom only (see the footer below), bottom-left/bottom-right,
             filled blue. This top link is just a "go to a different
             screen" shortcut. --}}
        <a href="{{ route('admin.growth.surveys.index') }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600; display:inline-block; margin-bottom:4px;">{{ __('growth.survey_management_link') }}</a>
        <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.objective_helper_note') }}</div>
    </div>

    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0; margin-bottom:6px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <form method="POST" action="{{ $survey ? route('admin.growth.surveys.update', $survey->survey_id) : route('admin.growth.surveys.store') }}" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 14px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:4px; justify-content:space-between;">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <div>
                    <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.survey_name_internal_label') }}</div>
                    <input type="text" name="name" required value="{{ old('name', $survey->name ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; box-sizing:border-box;">
                </div>
                <div>
                    <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.survey_title_respondents_label') }}</div>
                    <input type="text" name="title" required value="{{ old('title', $survey->title ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.description_optional_label') }}</div>
                <textarea name="description" rows="1" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; box-sizing:border-box; resize:none;">{{ old('description', $survey->description ?? '') }}</textarea>
            </div>

            <div>
                <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.survey_objective_label') }}</div>
                <textarea name="objective" required rows="1" placeholder="{{ __('growth.objective_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; box-sizing:border-box; resize:none;">{{ old('objective', $survey->objective ?? '') }}</textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px;">
                <div>
                    <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.category_field_label') }}</div>
                    <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('growth.none_option') }}</option>
                        @foreach($categories as $c)
                        <option value="{{ $c->category_id }}" {{ old('category_id', $survey->category_id ?? '') === $c->category_id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.survey_owner_label') }}</div>
                    <select name="owner_agent_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; background:#fff; box-sizing:border-box;">
                        <option value="">{{ __('growth.none_option') }}</option>
                        @foreach($owners as $o)
                        <option value="{{ $o->agent_id }}" {{ old('owner_agent_id', $survey->owner_agent_id ?? '') === $o->agent_id ? 'selected' : '' }}>{{ $o->full_name }} ({{ $o->role }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.language_label') }}</div>
                    <select name="language" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; background:#fff; box-sizing:border-box;">
                        <option value="en" {{ old('language', $survey->language ?? 'en') === 'en' ? 'selected' : '' }}>{{ __('growth.lang_english') }}</option>
                        <option value="ms" {{ old('language', $survey->language ?? '') === 'ms' ? 'selected' : '' }}>{{ __('growth.lang_bahasa_malaysia') }}</option>
                        <option value="zh" {{ old('language', $survey->language ?? '') === 'zh' ? 'selected' : '' }}>{{ __('growth.lang_chinese') }}</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <div>
                    <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.start_date_optional_label') }}</div>
                    <input type="date" name="start_date" value="{{ old('start_date', $survey->start_date ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; box-sizing:border-box;">
                </div>
                <div>
                    <div style="font-size:8px; color:#6b7280; margin-bottom:1px;">{{ __('growth.end_date_optional_label') }}</div>
                    <input type="date" name="end_date" value="{{ old('end_date', $survey->end_date ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 7px; font-size:10px; box-sizing:border-box;">
                </div>
            </div>

            <div style="border-top:1px dashed #d1d5db; padding-top:4px;">
                <div style="font-size:8.5px; font-weight:700; color:#1565C0; margin-bottom:2px;">{{ __('growth.target_respondents_heading') }}</div>
                <div style="display:flex; gap:14px; font-size:9.5px; color:#374151;">
                    <label><input type="checkbox" name="target_respondent_types[]" value="EXISTING_CUSTOMER" {{ in_array('EXISTING_CUSTOMER', $selectedTypes) ? 'checked' : '' }}> {{ __('growth.existing_customer_option') }}</label>
                    <label><input type="checkbox" name="target_respondent_types[]" value="NEW_CUSTOMER" {{ in_array('NEW_CUSTOMER', $selectedTypes) ? 'checked' : '' }}> {{ __('growth.new_customer_option') }}</label>
                    <label><input type="checkbox" name="target_respondent_types[]" value="PROSPECT" {{ in_array('PROSPECT', $selectedTypes) ? 'checked' : '' }}> {{ __('growth.prospective_customer_option') }}</label>
                </div>
            </div>

            <div style="border-top:1px dashed #d1d5db; padding-top:4px;">
                <div style="font-size:8.5px; font-weight:700; color:#1565C0; margin-bottom:2px;">{{ __('growth.response_settings_heading') }}</div>
                <div style="display:flex; gap:14px; font-size:9.5px; color:#374151;">
                    <label><input type="checkbox" name="require_respondent_contact" {{ old('require_respondent_contact', $survey->require_respondent_contact ?? true) ? 'checked' : '' }}> {{ __('growth.ask_for_contact_label') }}</label>
                    <label><input type="checkbox" name="allow_anonymous" {{ old('allow_anonymous', $survey->allow_anonymous ?? false) ? 'checked' : '' }}> {{ __('growth.allow_anonymous_label') }}</label>
                </div>
            </div>

        </div>

        <div style="flex-shrink:0; padding-top:6px; display:flex; gap:8px; align-items:center;">
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ $survey ? __('growth.save_changes_button') : __('growth.save_draft_continue_button') }}</button>
            @if(!$survey)
            <span style="font-size:8.5px; color:#9ca3af;">{{ __('growth.next_land_on_builder_note') }}</span>
            @endif
        </div>
    </form>

    {{-- Prev, filled blue, bottom-left — per Chris's standing rule, every
         screen's Prev lives here, never at the top. --}}
    <div style="flex-shrink:0; padding-top:6px;">
        <a href="{{ route('admin.growth.surveys.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>
</div>
@endsection
