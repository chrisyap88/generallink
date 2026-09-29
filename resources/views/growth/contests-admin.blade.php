@extends('layouts.dashboard')

@section('page-title', __('growth.contests_admin_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #211) — Growth & Outreach Center. Create
     time-boxed contests per group (or company-wide). Awards are never
     auto-credited — see Winners screen to mark them awarded once
     you've actually paid out points/document credit/cash through the
     relevant existing screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.contests_admin_subtitle') }}</div>
        </div>
        @include('partials.feature-video-widget', ['featureKey' => 'RECRUITMENT_CONTESTS'])
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0; margin-bottom:6px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="display:flex; gap:12px; flex:1; min-height:0;">

        <div style="flex:1; display:flex; flex-direction:column; min-height:0;">
            <form method="GET" action="{{ route('admin.growth.contests.index') }}" style="margin-bottom:8px; flex-shrink:0;">
                <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; background:#fff;">
                    <option value="" {{ !$groupLabelId ? 'selected' : '' }}>{{ __('growth.system_default_company_wide') }}</option>
                    @foreach($groupLabels as $g)
                    <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                    @endforeach
                </select>
            </form>

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
                <div style="flex:1; min-height:0; overflow-y:auto;">
                    @forelse($contests as $c)
                    <div style="border-bottom:1px solid #f3f4f6; padding:8px 4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div style="font-size:11px; font-weight:700; color:#111827;">{{ $c->title }}</div>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <span style="background:{{ $c->contest_mode === 'RANKED_TOP3' ? '#fef3c7' : '#f0f9ff' }}; color:{{ $c->contest_mode === 'RANKED_TOP3' ? '#92400e' : '#0369a1' }}; font-size:8px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ $c->contest_mode === 'RANKED_TOP3' ? __('growth.top3_badge') : __('growth.threshold_badge') }}</span>
                                @if($c->is_active)
                                <span style="background:#f0fdf4; color:#166534; font-size:8px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ __('growth.active_badge') }}</span>
                                @else
                                <span style="background:#f3f4f6; color:#6b7280; font-size:8px; font-weight:700; padding:2px 7px; border-radius:20px;">{{ __('growth.inactive_badge') }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="font-size:9.5px; color:#6b7280; margin:3px 0;">
                            {{ \Illuminate\Support\Carbon::parse($c->start_date)->format('d M Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($c->end_date)->format('d M Y') }}
                            &middot; {{ $c->metric === 'RECRUIT_COUNT' ? __('growth.target_colon_recruits', ['value' => rtrim(rtrim(number_format($c->target_value,2),'0'),'.')]) : __('growth.target_label').' RM '.number_format($c->target_value,2).' '.($c->metric === 'SALES_VOLUME' ? __('growth.premium_word') : __('growth.earning_income_word')) }}
                            &middot; {{ $c->reward_type === 'POINTS' ? __('growth.reward_colon_1st', ['value' => number_format($c->reward_value,0).' pts']) : ($c->reward_type === 'DOCUMENT_CREDIT' ? __('growth.reward_colon_1st', ['value' => number_format($c->reward_value,2).' doc credit']) : __('growth.reward_colon_1st', ['value' => 'RM '.number_format($c->reward_value,2)])) }}{{ $c->contest_mode !== 'RANKED_TOP3' ? '' : '' }}
                        </div>
                        @if($c->sponsor_vendor_name)
                        <div style="font-size:9px; color:#7c3aed; background:#f5f3ff; border-radius:5px; padding:3px 7px; display:inline-block; margin-bottom:4px;">
                            {{ $c->sponsor_amount ? __('growth.sponsored_by_amount', ['name' => $c->sponsor_vendor_name, 'amount' => number_format($c->sponsor_amount, 2)]) : __('growth.sponsored_by', ['name' => $c->sponsor_vendor_name]) }}
                        </div>
                        @endif
                        <div style="font-size:8.5px; color:#9ca3af; margin-bottom:4px;">
                            {{ $c->last_announced_at ? __('growth.last_notified', ['time' => \Illuminate\Support\Carbon::parse($c->last_announced_at)->diffForHumans(), 'count' => $c->announce_count]) : __('growth.not_announced_yet') }}
                        </div>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <a href="{{ route('admin.growth.contests.winners', $c->contest_id) }}" style="font-size:9.5px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.view_winners_link', ['count' => $c->winner_count]) }}</a>
                            <form method="POST" action="{{ route('admin.growth.contests.toggle-active', $c->contest_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:{{ $c->is_active ? '#dc2626' : '#166534' }}; font-size:9.5px; font-weight:600; cursor:pointer;">{{ $c->is_active ? __('growth.deactivate_button') : __('growth.activate_button_alt') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.growth.contests.announce', $c->contest_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#92400e; font-size:9.5px; font-weight:600; cursor:pointer;">{{ $c->last_announced_at ? __('growth.send_reminder_button') : __('growth.announce_button') }}</button>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div style="text-align:center; color:#9ca3af; padding:20px 0; font-size:10.5px;">{{ __('growth.no_contests_this_scope') }}</div>
                    @endforelse
                </div>
                <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
                    @if($contests->onFirstPage())
                        <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</span>
                    @else
                        <a href="{{ $contests->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</a>
                    @endif
                    <span style="font-size:9px; color:#6b7280;">{{ __('growth.page_of', ['current' => $contests->currentPage(), 'last' => $contests->lastPage()]) }}</span>
                    @if($contests->hasMorePages())
                        <a href="{{ $contests->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</a>
                    @else
                        <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="flex:0 0 300px; display:flex; flex-direction:column; min-height:0;">
            <form method="POST" action="{{ route('admin.growth.contests.store') }}" enctype="multipart/form-data" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; flex-direction:column; min-height:0; flex:1;">
                @csrf
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:6px; flex-shrink:0;">{{ __('growth.new_contest_heading') }}</div>
                {{-- Fields scroll internally within this card only — the
                     page/dashboard shell itself never scrolls. There are
                     simply too many fields (title, metric, reward,
                     dates, optional vendor sponsorship) to guarantee a
                     fit on every screen size otherwise. --}}
                <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:6px; padding-right:2px;">
                <input type="hidden" name="group_label_id" value="{{ $groupLabelId }}">
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.title_label') }}</div>
                    <input type="text" name="title" id="contestTitle" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.description_optional_label2') }}</div>
                    <textarea name="description" id="contestDescription" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box; resize:none;"></textarea>
                    @include('partials.carolyn-write-helper', [
                        'uid' => 'contest',
                        'bodyFieldId' => 'contestDescription',
                        'titleFieldId' => 'contestTitle',
                        'contentType' => 'contest_description',
                        'assistUrl' => route('ai-write-assist'),
                    ])
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.rules_regulations_label') }}</div>
                    <textarea name="rules_text" rows="2" placeholder="{{ __('growth.rules_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box; resize:none;"></textarea>
                </div>
                <div style="border-top:1px dashed #d1d5db; padding-top:6px;">
                    <div style="font-size:9px; font-weight:700; color:#1565C0; margin-bottom:4px;">{{ __('growth.banner_video_optional_heading') }}</div>
                    <div style="margin-bottom:6px;">
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.banner_image_label2') }}</div>
                        <input type="file" name="banner_image" accept="image/*" style="width:100%; font-size:9.5px;">
                    </div>
                    <div style="display:flex; gap:8px; font-size:9px; color:#374151; margin-bottom:4px; flex-wrap:wrap;">
                        <label><input type="radio" name="video_source" value="LIBRARY" {{ $libraryVideos->isEmpty() ? 'disabled' : 'checked' }} onchange="toggleContestVideoSource()"> {{ __('growth.from_content_library_option') }}</label>
                        <label><input type="radio" name="video_source" value="LINK" {{ $libraryVideos->isEmpty() ? 'checked' : '' }} onchange="toggleContestVideoSource()"> {{ __('growth.paste_link_option') }}</label>
                        <label><input type="radio" name="video_source" value="UPLOAD" onchange="toggleContestVideoSource()"> {{ __('growth.upload_file_option') }}</label>
                    </div>
                    <div id="contestVideoLibrary" style="display:none;">
                        @if($libraryVideos->isEmpty())
                        <div style="font-size:8.5px; color:#9ca3af;">{{ __('growth.no_active_library_videos') }}</div>
                        @else
                        <select name="video_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="">{{ __('growth.select_a_video_placeholder') }}</option>
                            @foreach($libraryVideos as $lv)
                            <option value="{{ $lv->video_id }}">{{ $lv->video_name }}</option>
                            @endforeach
                        </select>
                        @endif
                    </div>
                    <div id="contestVideoLink" style="display:none;">
                        <input type="url" name="video_url" placeholder="{{ __('growth.video_url_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div id="contestVideoUpload" style="display:none;">
                        <input type="file" name="video_file" accept="video/mp4,video/quicktime,video/webm" style="width:100%; font-size:9.5px;">
                        <div style="font-size:8px; color:#9ca3af; margin-top:2px;">{{ __('growth.video_max_size_note') }}</div>
                    </div>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.contest_mode_label') }}</div>
                    <select name="contest_mode" id="contestMode" onchange="toggleContestMode()" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="THRESHOLD">{{ __('growth.threshold_mode_option') }}</option>
                        <option value="RANKED_TOP3">{{ __('growth.ranked_mode_option') }}</option>
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.metric_label') }}</div>
                        <select name="metric" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                            <option value="RECRUIT_COUNT">{{ __('growth.recruit_count_option') }}</option>
                            <option value="SALES_VOLUME">{{ __('growth.sales_volume_premium_option') }}</option>
                            <option value="EARNING_INCOME">{{ __('growth.earning_income_option') }}</option>
                        </select>
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;" id="targetLabel">{{ __('growth.target_field_label') }}</div>
                        <input type="number" name="target_value" required min="0.01" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.reward_type_same_label') }}</div>
                    <select name="reward_type" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                        <option value="POINTS">{{ __('growth.reward_points_option') }}</option>
                        <option value="DOCUMENT_CREDIT">{{ __('growth.document_credit_option') }}</option>
                        <option value="CASH">{{ __('growth.cash_option') }}</option>
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;" id="rewardValueLabel">{{ __('growth.reward_amount_label') }}</div>
                        <input type="number" name="reward_value" required min="0.01" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div id="reward2ndField" style="display:none;">
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.second_place_label') }}</div>
                        <input type="number" name="reward_value_2nd" min="0.01" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div id="reward3rdField" style="display:none;">
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.third_place_label') }}</div>
                        <input type="number" name="reward_value_3rd" min="0.01" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div id="rankedHint" style="display:none; font-size:8px; color:#9ca3af; margin-top:-4px;">{{ __('growth.ranked_hint_note') }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.start_date_label') }}</div>
                        <input type="date" name="start_date" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.end_date_label') }}</div>
                        <input type="date" name="end_date" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="border-top:1px dashed #d1d5db; padding-top:6px; margin-top:2px;">
                    <div style="font-size:9px; font-weight:700; color:#7c3aed; margin-bottom:4px;">{{ __('growth.vendor_sponsorship_heading') }}</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:6px;">
                        <div>
                            <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.sponsoring_vendor_label') }}</div>
                            <select name="sponsor_vendor_id" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; background:#fff; box-sizing:border-box;">
                                <option value="">{{ __('growth.none_option') }}</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->vendor_id }}">{{ $v->vendor_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.sponsored_amount_rm_label') }}</div>
                            <input type="number" name="sponsor_amount" min="0.01" step="0.01" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                        </div>
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.sponsorship_notes_label') }}</div>
                        <textarea name="sponsor_notes" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box; resize:none;"></textarea>
                    </div>
                </div>
                </div>
                {{-- End of scrollable field area — Submit stays fixed
                     and always visible below it, never scrolled away. --}}
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 14px; font-size:10.5px; font-weight:600; cursor:pointer; margin-top:6px; flex-shrink:0;">{{ __('growth.create_contest_button') }}</button>
            </form>
        </div>
    </div>

</div>

<script>
var i18nFirstPlace = @json(__('growth.first_place_label'));
var i18nRewardAmount = @json(__('growth.reward_amount_label'));
var i18nMinToQualify = @json(__('growth.min_to_qualify_label'));
var i18nTargetLabel = @json(__('growth.target_field_label'));
function toggleContestVideoSource() {
    var checked = document.querySelector('input[name="video_source"]:checked');
    var v = checked ? checked.value : 'LINK';
    document.getElementById('contestVideoLibrary').style.display = (v === 'LIBRARY') ? 'block' : 'none';
    document.getElementById('contestVideoLink').style.display = (v === 'LINK') ? 'block' : 'none';
    document.getElementById('contestVideoUpload').style.display = (v === 'UPLOAD') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleContestVideoSource);
function toggleContestMode() {
    var ranked = document.getElementById('contestMode').value === 'RANKED_TOP3';
    document.getElementById('reward2ndField').style.display = ranked ? 'block' : 'none';
    document.getElementById('reward3rdField').style.display = ranked ? 'block' : 'none';
    document.getElementById('rankedHint').style.display = ranked ? 'block' : 'none';
    document.getElementById('rewardValueLabel').textContent = ranked ? i18nFirstPlace : i18nRewardAmount;
    document.getElementById('targetLabel').textContent = ranked ? i18nMinToQualify : i18nTargetLabel;
}
</script>
@endsection
