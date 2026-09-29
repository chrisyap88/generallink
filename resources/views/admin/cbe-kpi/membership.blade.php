@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_cbe_kpi.title_membership'))

@section('content')

<style>
.ed-box{flex:1; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; padding:10px 12px; display:flex; flex-direction:column; min-width:0; box-sizing:border-box; box-shadow:0 2px 10px rgba(21,101,192,0.10);}
.ed-box-title{font-size:9px; font-weight:700; color:var(--gl-blue); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:6px; padding-bottom:5px; border-bottom:1px solid #eef2f7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-rows{flex:1; display:flex; flex-direction:column; gap:3px; justify-content:center; min-height:0;}
.ed-row{display:flex; justify-content:space-between; align-items:center; font-size:8px; gap:6px;}
.ed-row span:first-child{color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-row span:last-child{font-weight:700; color:#0d3c72; white-space:nowrap; flex-shrink:0;}
.cbe-level-input{border:1px solid #b2ebf2;background:#fff;font-family:'Poppins',sans-serif;font-size:8px;font-weight:600;color:var(--gl-blue);outline:none;border-radius:5px;padding:3px 6px;width:100%;box-sizing:border-box;}
.cbe-tab-btn{padding:5px 14px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; user-select:none;}
.cbe-tab-btn.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbe-profile-field{display:flex; flex-direction:column; gap:3px; margin-bottom:8px;}
.cbe-profile-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.cbe-profile-field input, .cbe-profile-field textarea{font-family:'Poppins',sans-serif; font-size:9.5px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; box-sizing:border-box; width:100%;}
.cbe-phone-row{display:flex; gap:6px; align-items:center; margin-bottom:5px;}
.cbe-phone-row input{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box;}
.cbe-phone-remove-btn{background:#fee2e2; color:#b91c1c; border:none; border-radius:5px; padding:5px 8px; font-size:9px; font-weight:700; cursor:pointer; flex-shrink:0;}
.cbe-phone-add-btn{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:5px 10px; font-size:8.5px; font-weight:700; cursor:pointer; align-self:flex-start; margin-top:2px;}
.cbe-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:4px 16px; box-sizing:border-box; gap:5px;">

    @if($hasSelection && $selectedGroupId !== 'ALL')
    <div style="flex-shrink:0; display:flex; justify-content:flex-end;">
        <a href="{{ route('admin.cbe-kpi', ['scope' => 'all']) }}" style="font-size:8px; color:#c62828; text-decoration:none; font-weight:600; white-space:nowrap;">✕ {{ __('admin_cbe_kpi.all_cbe_groups') }}</a>
    </div>
    @endif

    {{-- FIXED 27 Sep 2026 — room on the right so Carolyn's bubble (fixed, top-right) never covers the Go button / month arrows. --}}
    <div style="flex-shrink:0; padding-right:58px; display:flex; align-items:center; gap:6px;">
        <div id="cbe-level-selects" style="display:flex; align-items:center; gap:6px; flex:1; min-width:0;"></div>
        <button type="button" id="cbe-go-btn" onclick="onCbeGo()" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:4px 14px; font-size:8.5px; font-weight:700; cursor:pointer; flex-shrink:0;">▶ {{ __('admin_cbe_kpi.go_button') }}</button>
    </div>

    @if(!$hasSelection)
    <div style="flex:1; display:flex; align-items:center; justify-content:center;">
        <div style="text-align:center; color:#94A3B8; font-size:10px; max-width:420px;">{{ __('admin_cbe_kpi.select_prompt') }}</div>
    </div>
    @else
    <div style="flex-shrink:0; font-size:8.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
        {{ $scopeLabel }}
        @if($groupTier)
            — {{ $groupTier === 'PAID' ? __('admin_cbe_kpi.group_tier_paid') : __('admin_cbe_kpi.group_tier_free') }}
        @endif
    </div>

    {{-- CHANGED 26 Aug 2026, 13th pass — per Chris: "when come to this
    screen, you default show the profile screen tap not kpi" — landing
    tab is now Profile (first in the tab order), not KPI. --}}
    @if($showTabs)
    <div style="flex-shrink:0; display:flex; gap:4px; border-bottom:1px solid #e2e8f0;">
        <div class="cbe-tab-btn active" data-tab="profile" onclick="cbeSwitchTab('profile')">{{ __('admin_cbe_kpi.tab_profile') }}</div>
        <div class="cbe-tab-btn" data-tab="contact" onclick="cbeSwitchTab('contact')">{{ __('admin_cbe_kpi.tab_contact') }}</div>
        <div class="cbe-tab-btn" data-tab="kpi" onclick="cbeSwitchTab('kpi')">{{ __('admin_cbe_kpi.tab_kpi') }}</div>
        {{-- NEW 26 Aug 2026, 15th pass — per Chris: "the 5th Tap is
        customer, the 6th tap is sponsor" (Members = 4th). These 3 open
        their own FULL SCREEN (not an inline panel like the 3 tabs
        above) — per Chris: "show the entire profile screen ya." --}}
        <a href="{{ route('admin.cbe-kpi.members', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_members') }}</a>
        <a href="{{ route('admin.cbe-kpi.customers', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_customers') }}</a>
        <a href="{{ route('admin.cbe-kpi.donors', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_donors') }}</a>
        <a href="{{ route('admin.cbe-kpi.appointments', ['node' => $profileNode->node_id]) }}" class="cbe-tab-btn" style="text-decoration:none;">{{ __('admin_cbe_directory.tab_appointments') }}</a>
    </div>
    @endif

    @php
        // NEW 26 Aug 2026, 12th pass — per Chris: "why show Non klang
        // temple... Penang sekinchiang all Malaysia temple that is
        // wrong" — the drill-into-node-list link must carry the CURRENT
        // scope (a real node like a State, or a Klang-style district +
        // its parent State) forward, otherwise clicking it silently
        // resets to the whole group nationwide.
        $cbeNodesScope = [];
        if ($drilledNodeId) {
            $cbeNodesScope['node'] = $drilledNodeId;
        } elseif (! empty($currentDistrictName)) {
            $cbeNodesScope['district'] = $currentDistrictName;
            if (! empty($currentScopeParentId)) {
                $cbeNodesScope['parent'] = $currentScopeParentId;
            }
        }
    @endphp
    {{-- FIXED 27 Aug 2026 — see director.blade.php for full context:
    when $showTabs is false (district/Branch or All-CBE scope), the tab
    bar that would normally reveal this panel doesn't render, so it must
    default to visible instead of hidden. --}}
    <div id="cbe-tab-kpi" style="display:{{ $showTabs ? 'none' : 'flex' }}; flex:1; min-height:0; flex-direction:column; gap:8px;">
    <div style="flex:1; min-height:0; display:flex; gap:8px;">
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_membership_admin') }}</div>
            <div class="ed-rows">
                {{-- NEW 26 Aug 2026, 17th pass — per Chris: "did you
                show as a drill down from Temple KPI?" Jump straight
                into the Members search results — only when a single
                real temple is in view. --}}
                @if($showTabs)
                <a href="{{ route('admin.cbe-kpi.members', ['node' => $profileNode->node_id, 'mode' => 'search', 'do_search' => 1]) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_total_members') }}</span><span>{{ number_format($totalMembers) }} ›</span></a>
                <a href="{{ route('admin.cbe-kpi.members', ['node' => $profileNode->node_id, 'mode' => 'search', 'do_search' => 1, 'joined_from' => now()->startOfMonth()->toDateString()]) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_new_members_month') }}</span><span>{{ number_format($newMembersThisMonth) }} ›</span></a>
                <a href="{{ route('admin.cbe-kpi.members', ['node' => $profileNode->node_id, 'mode' => 'search', 'do_search' => 1, 'status' => 'INACTIVE']) }}" class="ed-row" style="text-decoration:none; cursor:pointer;"><span>{{ __('cbe_exec.row_suspended') }}</span><span>{{ number_format($inactiveMembers) }} ›</span></a>
                @else
                <div class="ed-row"><span>{{ __('cbe_exec.row_total_members') }}</span><span>{{ number_format($totalMembers) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_new_members_month') }}</span><span>{{ number_format($newMembersThisMonth) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_suspended') }}</span><span>{{ number_format($inactiveMembers) }}</span></div>
                @endif
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_documents') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_new_applications') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_announcements') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_meetings_calendar') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_meetings_this_year') }}</span><span>{{ number_format($meetingsThisYear) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_upcoming_events') }}</span><span>{{ number_format($upcomingEvents) }}</span></div>
            </div>
        </div>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:8px;">
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_tasks_workflow') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>—</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_approvals_applications') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_pending_approvals') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_notifications_reminders') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_memberships_expiring') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
            </div>
        </div>
    </div>

    <div style="flex:1.1; min-height:0;">
        <div class="ed-box" style="height:100%;">
            <div class="ed-box-title">{{ __('cbe_exec.box_admin_overview') }}</div>
            <div style="flex:1; display:flex; align-items:center; gap:14px; padding:4px 0;">
                @if($showTabs)
                <a href="{{ route('admin.cbe-kpi.members', ['node' => $profileNode->node_id, 'mode' => 'search', 'do_search' => 1]) }}" style="text-align:center; flex:1; text-decoration:none;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($totalMembers) }} ›</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.row_total_members') }}</div>
                </a>
                @else
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($totalMembers) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.row_total_members') }}</div>
                </div>
                @endif
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:#2e7d32;">{{ number_format($newMembersThisMonth) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.row_growth_trend') }}</div>
                </div>
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($branchesReporting) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ __('cbe_exec.row_branch_breakdown') }}</div>
                </div>
                @if($selectedGroupId !== 'ALL')
                <a href="{{ route('admin.cbe-kpi.nodes', array_merge(['group' => $selectedGroupId, 'level' => $childBranchLabel], $cbeNodesScope)) }}" style="text-align:center; flex:1; text-decoration:none;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($childBranchCount) }} ›</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ $childBranchLabel }}</div>
                </a>
                @else
                <div style="text-align:center; flex:1;">
                    <div style="font-size:16px; font-weight:700; color:var(--gl-blue);">{{ number_format($childBranchCount) }}</div>
                    <div style="font-size:7.5px; color:#6b7280;">{{ $childBranchLabel }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
    </div>

    @if($showTabs)
    <div id="cbe-tab-profile-group" style="display:flex; flex:1; min-height:0;">
        <form method="POST" action="{{ route('admin.cbe-kpi.update-profile') }}" style="height:100%; display:flex; flex-direction:column; min-height:0;">
            @csrf
            <input type="hidden" name="node" value="{{ $profileNode->node_id }}">

            @php
                $pnZh = app()->getLocale() === 'zh' && $profileNode->node_name_zh;
                $pnPrimary = $pnZh ? $profileNode->node_name_zh : $profileNode->node_name;
                $pnSecondary = $pnZh ? $profileNode->node_name : $profileNode->node_name_zh;
            @endphp

            <div id="cbe-tab-profile" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                <div class="ed-box" style="height:100%; display:flex; flex-direction:column;">
                    <div class="ed-box-title">{{ $pnPrimary }}{{ $pnSecondary ? ' ('.$pnSecondary.')' : '' }}</div>
                    @if($profileParentNode || $profileNode->external_reference_no || ! $profileIsTopLevel)
                    <div style="font-size:8.5px; color:#94A3B8; margin:-4px 0 8px;">
                        @if($profileParentNode)
                            {{ $profileParentLevelName }}: {{ $profileParentNode->node_name }}
                        @elseif($profileNode->link_locked)
                            {{ __('admin_cbe_kpi.profile_standalone_by_choice') }}
                        @elseif(! $profileIsTopLevel)
                            {{ __('admin_cbe_kpi.profile_standalone_unlinked') }}
                        @endif
                        @if(($profileParentNode || (! $profileIsTopLevel && ! $profileParentNode)) && $profileNode->external_reference_no)&nbsp;·&nbsp;@endif
                        @if($profileNode->external_reference_no){{ __('admin_cbe_kpi.profile_ref_no') }}: {{ $profileNode->external_reference_no }}@endif
                    </div>
                    @endif
                    @if(! $profileIsTopLevel)
                    <label style="display:flex; align-items:center; gap:6px; font-size:8.5px; color:#374151; cursor:pointer; margin:-2px 0 8px;">
                        <input type="checkbox" name="stay_standalone" value="1" @checked($profileNode->link_locked) style="width:12px; height:12px; margin:0;">
                        {{ __('admin_cbe_kpi.profile_stay_standalone') }}
                    </label>
                    @endif
                    <div style="display:flex; gap:14px;">
                        <div class="cbe-profile-field" style="flex:3;">
                            <label>{{ __('admin_cbe_kpi.profile_address') }}</label>
                            <textarea name="address" rows="3">{{ old('address', $profileNode->address) }}</textarea>
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:110px;">
                            <label>{{ __('admin_cbe_kpi.profile_city') }}</label>
                            <input type="text" name="city" value="{{ old('city', $profileNode->city) }}">
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:90px;">
                            <label>{{ __('admin_cbe_kpi.profile_postcode') }}</label>
                            <input type="text" name="postcode" value="{{ old('postcode', $profileNode->postcode) }}">
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:auto;">
                        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('admin_cbe_kpi.profile_save') }}</button>
                        @if(session('cbe_profile_saved'))
                        <span style="font-size:8.5px; color:#2e7d32; font-weight:700;">✓ {{ __('admin_cbe_kpi.profile_saved') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div id="cbe-tab-contact" style="display:none; flex-direction:column; flex:1; min-height:0;">
                <div class="ed-box" style="height:100%; display:flex; flex-direction:column; overflow-y:auto;">
                    <div class="ed-box-title">{{ __('admin_cbe_kpi.tab_contact') }}</div>
                    @php
                        $phone1 = $profilePhones->slice(0, 1)->values()->first();
                        $phone2 = $profilePhones->slice(1, 1)->values()->first();
                        $extraPhones = $profilePhones->slice(2)->values();
                    @endphp
                    <div style="display:flex; gap:14px; flex-wrap:wrap;">
                        <div class="cbe-profile-field" style="flex:1; min-width:160px;">
                            <label>{{ __('admin_cbe_kpi.profile_contact_person_1') }}</label>
                            <input type="text" name="contact_person_1" value="{{ old('contact_person_1', $profileNode->contact_person_1) }}">
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:140px;">
                            <label>{{ __('admin_cbe_kpi.profile_phone_number') }}</label>
                            <input type="hidden" name="phones[0][id]" value="{{ $phone1->phone_id ?? '' }}">
                            <input type="hidden" name="phones[0][note]" value="{{ $phone1->contact_note ?? '' }}">
                            <input type="text" name="phones[0][number]" value="{{ old('phones.0.number', $phone1->phone_number ?? '') }}" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}">
                        </div>
                    </div>
                    <div style="display:flex; gap:14px; flex-wrap:wrap;">
                        <div class="cbe-profile-field" style="flex:1; min-width:160px;">
                            <label>{{ __('admin_cbe_kpi.profile_contact_person_2') }}</label>
                            <input type="text" name="contact_person_2" value="{{ old('contact_person_2', $profileNode->contact_person_2) }}">
                        </div>
                        <div class="cbe-profile-field" style="flex:1; min-width:140px;">
                            <label>{{ __('admin_cbe_kpi.profile_phone_number') }}</label>
                            <input type="hidden" name="phones[1][id]" value="{{ $phone2->phone_id ?? '' }}">
                            <input type="hidden" name="phones[1][note]" value="{{ $phone2->contact_note ?? '' }}">
                            <input type="text" name="phones[1][number]" value="{{ old('phones.1.number', $phone2->phone_number ?? '') }}" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}">
                        </div>
                    </div>
                    <div class="cbe-profile-field">
                        <label>{{ __('admin_cbe_kpi.profile_additional_phones') }}</label>
                        <div id="cbe-phone-rows">
                            @foreach($extraPhones as $ph)
                            <div class="cbe-phone-row">
                                <input type="hidden" name="phones[{{ $loop->index + 2 }}][id]" value="{{ $ph->phone_id }}">
                                <input type="text" name="phones[{{ $loop->index + 2 }}][number]" value="{{ $ph->phone_number }}" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}" style="flex:1;">
                                <input type="text" name="phones[{{ $loop->index + 2 }}][note]" value="{{ $ph->contact_note }}" placeholder="{{ __('admin_cbe_kpi.profile_note_placeholder') }}" style="flex:1;">
                                <button type="button" class="cbe-phone-remove-btn" onclick="this.parentElement.remove()">✕</button>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" class="cbe-phone-add-btn" onclick="cbeAddPhoneRow()">{{ __('admin_cbe_kpi.profile_add_phone') }}</button>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:8px;">
                        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('admin_cbe_kpi.profile_save') }}</button>
                        @if(session('cbe_profile_saved'))
                        <span style="font-size:8.5px; color:#2e7d32; font-weight:700;">✓ {{ __('admin_cbe_kpi.profile_saved') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
    @endif
    @endif

    @if($hasSelection)
    <div style="flex-shrink:0;">
        <a href="{{ route('admin.cbe-kpi') }}" class="cbe-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('admin_cbe_kpi.back_to_dashboard_btn') }}</a>
    </div>
    @endif
</div>

<script>
(function(){
    // REBUILT 26 Aug 2026, 7th pass — 4 always-visible boxes (CBE Group /
    // State / Branch / Temple), each calling an explicit box type
    // (group/state/branch/temple) rather than a generic level position —
    // Branch is a district grouping of city values, not a real
    // hierarchy level. See director.blade.php for the full rationale.
    var searchLevelUrl = '{{ route("admin.cbe-kpi.search-level") }}';
    var goUrl = '{{ route("admin.cbe-kpi") }}';
    var noMatchesTxt = @json(__('dashboard.admin_no_matches'));
    var levelLabels = [
        @json(__('admin_cbe_kpi.filter_level1')),
        @json(__('admin_cbe_kpi.filter_level2')),
        @json(__('admin_cbe_kpi.filter_level3')),
        @json(__('admin_cbe_kpi.filter_level4'))
    ];
    var allLabel = @json(__('admin_cbe_kpi.filter_all'));
    var picks = [null, null, null, null];

    function realNode(idx){
        var p = picks[idx];
        if (!p || p.nodeId === 'ALL') return '';
        if (p.nodeId.indexOf('district:') === 0) return '';
        return p.nodeId;
    }

    function districtAt(idx){
        var p = picks[idx];
        if (!p || p.nodeId === 'ALL') return '';
        if (p.nodeId.indexOf('district:') === 0) return p.nodeId.slice('district:'.length);
        return '';
    }

    function argsForBox(idx){
        if (idx === 0) return {box: 'group', parent: '', district: ''};
        if (idx === 1) return {box: 'state', parent: realNode(0), district: ''};
        if (idx === 2) return {box: 'branch', parent: realNode(1) || realNode(0), district: ''};
        return {box: 'temple', parent: realNode(2) || realNode(1) || realNode(0), district: districtAt(2)}; // CHANGED 27 Sep 2026 — real Branch pick
    }

    function cbeSearch(args, q, cb){
        var url = searchLevelUrl+'?box='+encodeURIComponent(args.box)+'&q='+encodeURIComponent(q)+'&_='+Date.now();
        if (args.parent) url += '&parent='+encodeURIComponent(args.parent);
        if (args.district) url += '&district='+encodeURIComponent(args.district);
        fetch(url, {cache:'no-store'})
            .then(function(r){ return r.json(); })
            .then(function(list){ cb(list.map(function(it){ return {nodeId:it.node_id, label:it.label}; })); })
            .catch(function(){ cb([]); });
    }

    function buildBox(idx){
        var wrap = document.createElement('div');
        wrap.style.cssText = 'position:relative;flex:1;min-width:0;';
        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = levelLabels[idx];
        input.autocomplete = 'off';
        input.className = 'cbe-level-input';
        var box = document.createElement('div');
        var anchorSide = (idx === 3) ? 'right:0;' : 'left:0;';
        box.style.cssText = 'display:none;position:absolute;top:100%;'+anchorSide+'margin-top:2px;background:#fff;border:1px solid #b2ebf2;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:220px;overflow-y:auto;z-index:60;width:300px;';
        var timer = null;

        function renderResults(items){
            var full = [{nodeId:'ALL', label:allLabel}].concat(items);
            box.innerHTML = full.map(function(it, i){
                var isAll = it.nodeId === 'ALL';
                return '<div class="cbe-suggestion" data-idx="'+i+'" style="padding:6px 8px;font-size:8px;line-height:1.35;color:'+(isAll ? '#0d3c72' : 'var(--gl-blue)')+';font-weight:'+(isAll ? '700' : '600')+';cursor:pointer;border-bottom:1px solid #eee;white-space:normal;word-break:break-word;'+(isAll ? 'background:#eef4fc;' : '')+'">'+it.label+'</div>';
            }).join('');
            box.style.display = 'block';
            Array.prototype.forEach.call(box.querySelectorAll('.cbe-suggestion'), function(el){
                el.addEventListener('click', function(){
                    var it = full[parseInt(el.getAttribute('data-idx'), 10)];
                    input.value = it.label;
                    box.style.display = 'none';
                    picks[idx] = it;
                    for(var j = idx + 1; j < 4; j++){
                        picks[j] = null;
                        if(inputs[j]) inputs[j].value = '';
                    }
                });
            });
        }

        function doSearch(q){
            cbeSearch(argsForBox(idx), q, renderResults);
        }

        input.addEventListener('input', function(){
            var q = input.value.trim();
            if(picks[idx]){ picks[idx] = null; }
            if(timer) clearTimeout(timer);
            timer = setTimeout(function(){ doSearch(q); }, 250);
        });

        function openBrowse(){
            if(timer) clearTimeout(timer);
            doSearch(input.value.trim());
        }
        input.addEventListener('focus', openBrowse);
        input.addEventListener('click', openBrowse);

        document.addEventListener('click', function(e){
            if(!wrap.contains(e.target)) box.style.display = 'none';
        });

        wrap.appendChild(input);
        wrap.appendChild(box);
        document.getElementById('cbe-level-selects').appendChild(wrap);
        return input;
    }

    var inputs = [];
    for(var i = 0; i < 4; i++){ inputs.push(buildBox(i)); }

    window.onCbeGo = function(){
        if (realNode(3)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(3));
            return;
        }
        var district = districtAt(2);
        if (district) {
            var scopeParent = realNode(1) || realNode(0);
            var url = goUrl+'?district='+encodeURIComponent(district);
            if (scopeParent) url += '&parent='+encodeURIComponent(scopeParent);
            window.location = url;
            return;
        }
        if (realNode(2)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(2));
            return;
        }
        if (realNode(1)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(1));
            return;
        }
        if (realNode(0)) {
            window.location = goUrl+'?node='+encodeURIComponent(realNode(0));
            return;
        }
        window.location = goUrl+'?scope=all';
    };

    // NEW 26 Aug 2026, 8th pass — Profile/KPI tab switcher
    window.cbeSwitchTab = function(tab){
        var kpiPanel = document.getElementById('cbe-tab-kpi');
        var formGroup = document.getElementById('cbe-tab-profile-group');
        var profileSub = document.getElementById('cbe-tab-profile');
        var contactSub = document.getElementById('cbe-tab-contact');
        var btns = document.querySelectorAll('.cbe-tab-btn');
        Array.prototype.forEach.call(btns, function(b){
            b.classList.toggle('active', b.getAttribute('data-tab') === tab);
        });
        if (kpiPanel) kpiPanel.style.display = (tab === 'kpi') ? 'flex' : 'none';
        if (formGroup) formGroup.style.display = (tab === 'kpi') ? 'none' : 'flex';
        if (profileSub) profileSub.style.display = (tab === 'profile') ? 'flex' : 'none';
        if (contactSub) contactSub.style.display = (tab === 'contact') ? 'flex' : 'none';
    };

    // NEW 26 Aug 2026, 10th pass — per Chris: "i have so many contact
    // number in my excel file, you didnt insert? contact 1, contact 2
    // with name?" Lets the admin add extra phone rows beyond the ones
    // already imported from Excel (variable count per temple).
    var cbePhoneIdx = {{ 2 + (isset($extraPhones) ? $extraPhones->count() : 0) }};
    window.cbeAddPhoneRow = function(){
        var wrap = document.getElementById('cbe-phone-rows');
        var div = document.createElement('div');
        div.className = 'cbe-phone-row';
        div.innerHTML = '<input type="hidden" name="phones['+cbePhoneIdx+'][id]" value="">'
            + '<input type="text" name="phones['+cbePhoneIdx+'][number]" placeholder="{{ __('admin_cbe_kpi.profile_phone_placeholder') }}" style="flex:1;">'
            + '<input type="text" name="phones['+cbePhoneIdx+'][note]" placeholder="{{ __('admin_cbe_kpi.profile_note_placeholder') }}" style="flex:1;">'
            + '<button type="button" class="cbe-phone-remove-btn" onclick="this.parentElement.remove()">✕</button>';
        wrap.appendChild(div);
        cbePhoneIdx++;
    };
})();
</script>
@endsection
