@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $groupLabel ? __('masterfile.edit_group_name_title') : __('masterfile.add_group_name_title'))

{{-- NEW 12 Sep 2026 — per Chris: "your header no need to display this
header" — the shared topbar (notifications/WhatsApp/language/profile
icons) was taking ~46px this tightly-fit screen needs to stay
scroll-free. Opts this one screen out of the shared topbar; see
layouts/dashboard.blade.php for the mechanism (every other screen is
unaffected). --}}
@section('hide-topbar', '1')

@section('content')
{{-- FIXED 25 Sep 2026 -- per Chris: "I TOLD YOU MANY TIMES AND MAKE IT AS COMPULSORY I SAY NO SCROLL USE PREV NEXT" -- this wrapper had overflow-y:auto on a height:100vh box, so the WHOLE page (including the tab bar itself) scrolled whenever one tab's content ran long -- worst of all on Committee/Management Team, which just dumped every row with no limit. Changed to overflow:hidden (never scrolls, in any direction) and the Committee table below now uses the exact same Prev/Next paging already used elsewhere on this same screen (Appointment Positions/GLADE Tiers/Affiliate Entity). --}}
<div style="height:100vh; overflow:hidden; padding:4px 16px; box-sizing:border-box;">

    <div style="margin-bottom:6px;">
        {{-- FIXED 27 Sep 2026 -- per Chris: Prev must return to the list of THIS group's type (e.g. CBE only), not every type. --}}
        <a href="{{ route('admin.masterfile.group-names', ($groupLabel->group_type ?? null) ? ['type' => $groupLabel->group_type] : []) }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('masterfile.back_to_group_name_maintenance') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif
    {{-- NEW 10 Sep 2026 (Task #399) — the group's other fields still
         saved even when a level removal was blocked; this is a warning
         about that specific part, not a full validation failure. --}}
    @if(session('level_warning'))
    <div style="background:#fff8e1; border-left:3px solid #f9a825; border-radius:6px; padding:5px 10px; font-size:11px; color:#8d6e00; margin-bottom:6px;">{{ session('level_warning') }}</div>
    @endif

    {{-- CHANGED 12 Sep 2026 — per Chris: "why still have scroll... you
    waster a screen space just create a folder tap wo drill down if have
    and IT is NOT call agents is Affiliate Entity, separate tap and
    extend your current screen width and in one screen." Leadership and
    the renamed Affiliate Entity list used to sit in a permanent second
    column, wasting half the screen width on every visit even though
    most edits only touch the Profile fields. Replaced with a 2-tab
    layout (Profile / Affiliate Entity) sharing the full screen width —
    the second tab is only rendered/shown on demand, never taking up
    space on the Profile tab. --}}
    @if($groupLabel)
    <div style="display:flex; gap:4px; border-bottom:2px solid #e5e7eb; margin-bottom:5px;">
        <div id="gnTabBtnProfile" class="gn-tab-btn gn-tab-btn-active" onclick="gnSwitchTab('profile')">{{ __('masterfile.tab_profile') }}</div>
        {{-- CHANGED 27 Sep 2026 — per Chris: NO Add in this set-up. For a CBE the
             Entities / Branches tab opens the Search / View / Edit screen
             straight away (no landing, no Add button). --}}
        <div id="gnTabBtnAffiliate" class="gn-tab-btn" onclick="@if($groupLabel && $groupLabel->group_type === 'CBE')window.location.href='{{ route('admin.cbe-kpi.group-entities', ['group' => $groupLabel->group_label_id]) }}'@else gnSwitchTab('affiliate')@endif">{{ $groupLabel && $groupLabel->group_type === 'CBE' ? __('masterfile.tab_affiliate_group').' ('.number_format($cbeEntityTotal ?? $cbeEntityNodes->count()).')' : __('masterfile.tab_affiliate_entity').' ('.$linkedAgents->count().')' }}</div>
        {{-- NEW 15 Sep 2026 — per Chris: group-level head-office contact
             and a Committee/Management Team picked from existing Agent/
             Member records — CBE-only, same as the other CBE-specific
             fields on the Profile tab. --}}
        @if($groupLabel->group_type === 'CBE')
        <div id="gnTabBtnContact" class="gn-tab-btn" onclick="gnSwitchTab('contact')">{{ __('masterfile.tab_contact_details') }}</div>
        <div id="gnTabBtnCommittee" class="gn-tab-btn" onclick="gnSwitchTab('committee')">{{ __('masterfile.tab_committee_team') }} ({{ $committeeMembersCurrent->count() }})</div>
        @endif
    </div>
    <style>
        .gn-tab-btn{padding:6px 16px; font-size:10.5px; font-weight:600; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px;}
        .gn-tab-btn-active{color:#1565C0; border-bottom-color:#1565C0;}
    </style>
    @endif

    <div id="gnTabPanelProfile">

        {{-- FIXED 12 Sep 2026 — per Chris: "why i save the logo.jpg many
        times still not save" — added a real file upload (logo_upload,
        now one shared field for every group type, in the top row above),
        which needs enctype="multipart/form-data" to actually transmit
        the file. --}}
        <form method="POST" action="{{ $groupLabel ? route('admin.masterfile.group-names.update', $groupLabel->group_label_id) : route('admin.masterfile.group-names.store') }}" enctype="multipart/form-data">
            @csrf
            @if($groupLabel) @method('PUT') @endif

            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px;">

                @php
                    // NEW 17 Aug 2026 — per Chris: three group types now
                    // exist. DSG and ORG are the same underlying
                    // mechanism (just the Promotion/Demotion flag),
                    // CBE is genuinely new (its own configurable-depth
                    // hierarchy instead of GL/TL/Introducer). Default to
                    // DSG for a brand new group.
                    $currentType = old('group_type', $groupLabel->group_type ?? $presetType ?? 'DSG');
                @endphp

                {{-- FIXED 12 Sep 2026 — per Chris: measured his actual
                screen with him — his browser genuinely only has ~400px of
                vertical room available for this page at 100% zoom (not a
                zoom problem, confirmed with him directly), while all the
                Profile fields together need ~650px. No amount of spacing
                trimming can close a 250px gap without cutting content or
                shrinking text to the point of being unreadable — so
                instead of ever scrolling, the Profile tab is now its own
                small Prev/Next wizard (same principle already used for
                Team Leaders/Affiliate Entities on the other tab, and the
                app's stated rule: "Prev/Next navigation only, no jump
                screens"). Each step is short enough to fit comfortably in
                ~400px on its own; Save is reachable from every group
                type's last step; nothing is ever clipped or hidden
                permanently. --}}
                <div id="gnStepContainer">

                <div id="gnStep1" class="gnStep">
                {{-- CHANGED 12 Sep 2026 — per Chris: "no need to display
                the description... your logo place next to [Group Name]...
                reformat everything in one screen." Description is kept
                (existing value preserved via hidden input, in case a
                group already has one on file) but no longer shown on
                screen — it was never essential to every edit. Login Page
                Logo moved up here from its old spot lower in the
                DSG/ORG/CBE boxes, freeing a whole extra row of height
                down there. Login Page Logo is now ONE shared control
                (was two near-duplicate selects+uploads, one per group
                type, kept in sync by JS) since the underlying logo_path/
                logo_upload fields were never actually type-specific —
                simpler, and one less row of height. --}}
                <div style="display:grid; grid-template-columns:1fr 1.3fr 1.3fr; gap:8px; margin-bottom:5px;">
                    <div>
                        <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.group_type_label') }} <span style="color:#e53935;">*</span></label>
                        <select name="group_type" id="group_type" required onchange="toggleGroupTypeFields();" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11.5px; background:#fff; box-sizing:border-box;">
                            <option value="DSG" {{ $currentType == 'DSG' ? 'selected' : '' }}>{{ __('sidebar.direct_selling_group_item') }} (DSG)</option>
                            <option value="ORG" {{ $currentType == 'ORG' ? 'selected' : '' }}>{{ __('sidebar.organization_rewards_group_item') }} (ORG)</option>
                            <option value="CBE" {{ $currentType == 'CBE' ? 'selected' : '' }}>{{ __('sidebar.cbe_group_item') }} (CBE)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.col_group_name') }} <span style="color:#e53935;">*</span></label>
                        <input type="text" name="group_name" value="{{ old('group_name', $groupLabel->group_name ?? '') }}" required placeholder="e.g. prihatin2u" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:9px; font-weight:600; color:#374151; display:block; white-space:nowrap; margin-bottom:2px;">{{ __('masterfile.login_page_logo') }}</label>
                        <div style="display:flex; gap:4px; align-items:center;">
                            {{-- NEW 25 Sep 2026 -- per Chris: "why o choose
                            logo it never upload" -- there was never any
                            visible proof an upload actually saved. This
                            shows the CURRENT saved logo (from the last
                            successful Save), and JS below swaps it to a
                            live preview the instant a new file is picked,
                            before Save is even clicked. --}}
                            @php $currentLogo = old('logo_path', $groupLabel->logo_path ?? ''); @endphp
                            <img id="logoPreviewImg" src="{{ $currentLogo ? asset('images/'.$currentLogo) : '' }}" style="width:28px; height:28px; object-fit:contain; border:1px solid #d1d5db; border-radius:4px; background:#fff; {{ $currentLogo ? '' : 'display:none;' }} flex-shrink:0;" alt="">
                            <select name="logo_path" id="logoPathSelect" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11.5px; background:#fff; box-sizing:border-box; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                                <option value="">{{ __('masterfile.none_option') }}</option>
                                @foreach($availableLogos as $logo)
                                    <option value="{{ $logo }}" {{ $currentLogo == $logo ? 'selected' : '' }}>{{ $logo }}</option>
                                @endforeach
                            </select>
                            <input type="file" name="logo_upload" id="logoUploadInput" accept="image/png,image/jpeg,image/gif,image/svg+xml" title="{{ __('masterfile.logo_helper') }}" style="flex:1; min-width:0; font-size:9px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <input type="hidden" name="description" value="{{ old('description', $groupLabel->description ?? '') }}">
                </div>

                <div id="gnStep2" class="gnStep">

                {{-- DSG / ORG fields — unchanged from before, just now
                shown/hidden based on Group Type instead of always-on. --}}
                <div id="dsgOrgFields" style="background:#fff8e1; border-radius:6px; padding:6px; margin-bottom:5px;">
                <div style="margin-bottom:5px; max-width:50%;">
                    {{-- FIXED 1 Aug 2026 (4) — per Chris: "the
                    description from pick list overlap design". The
                    "Disabled (Organization Rewards Group — 1 X, 1 Y,
                    direct roles)" option text was too long for the
                    closed select box and rendered visually broken
                    (overlapping/cut off mid-word). Shortened the
                    option text itself and moved the full explanation
                    to a helper line below. Added text-overflow:ellipsis
                    as a safety net for any future long option text.

                    CHANGED 12 Sep 2026 — per Chris: Login Page Logo
                    moved up to the top row (see gnStep1 above) and is
                    now one shared control for all group types, so this
                    box is just the one Promotion/Demotion field now —
                    saves a whole row of height. --}}
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; white-space:nowrap; margin-bottom:2px;">{{ __('masterfile.promotion_demotion_rules') }} <span style="color:#e53935;">*</span></label>
                    <select name="promotion_demotion_enabled" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11.5px; background:#fff; box-sizing:border-box; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">
                        <option value="1" {{ old('promotion_demotion_enabled', $groupLabel->promotion_demotion_enabled ?? 1) == 1 ? 'selected' : '' }}>{{ __('masterfile.enabled_normal') }}</option>
                        <option value="0" {{ old('promotion_demotion_enabled', $groupLabel->promotion_demotion_enabled ?? 1) == 0 ? 'selected' : '' }}>{{ __('masterfile.disabled_org_rewards') }}</option>
                    </select>
                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('masterfile.disabled_helper', ['gl' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'), 'tl' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]) }}</div>
                </div>
                <div>
                    {{-- SIMPLIFIED 1 Aug 2026 — per Chris: the On/Off
                    dropdown with long sentences was confusing. Plain
                    checkbox instead: ticked = every new GL/TL/Introducer
                    in this group must be given a rank when created, and
                    an agent with no rank is excluded from payout
                    (existing strict behavior). Unticked (default) =
                    rank is optional; an unranked agent's share is paid
                    to the nearest active agent above them instead.
                    RELABELED 1 Aug 2026 (2) — per Chris, to match the
                    terminology already used on the Earning Income
                    Structure screen ("pays by Rank only" / Rank
                    Allocation is a Multi-Tier %-per-rank breakdown).
                    Field name and logic are unchanged — display text
                    only. --}}
                    <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#374151; cursor:pointer;">
                        <input type="hidden" name="requires_rank_assignment" value="0">
                        <input type="checkbox" name="requires_rank_assignment" value="1" {{ old('requires_rank_assignment', $groupLabel->requires_rank_assignment ?? 0) ? 'checked' : '' }} style="width:14px; height:14px; margin:0;">
                        {{ __('masterfile.earning_income_multi_tier') }}
                    </label>
                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px; margin-left:20px;">{{ __('masterfile.rank_assignment_helper') }}</div>
                </div>
                </div>

                {{-- CBE fields — NEW 17 Aug 2026. CBE has no promotion/
                demotion and no rank system, so those two fields above
                are replaced (not stacked alongside) with the hierarchy
                level editor. This keeps the screen the same overall
                height regardless of which type is picked, so it stays
                scroll-free.

                CHANGED 12 Sep 2026 — per Chris: typing level names
                separated by commas was "very bad and open for mistake"
                — a stray or missing comma silently changed what got
                saved. REPLACED the free-typed comma field entirely:
                each level is now its own small editable box (no
                separator ever typed by the user), laid out as a
                wrapping row so it takes the same space as before no
                matter how many levels a community has. Add/remove any
                number of levels; order = top to bottom. The value
                actually saved is still the same comma-separated
                cbe_levels field the server already expects — a hidden
                input is kept in sync by JS below, so nothing on the
                backend needed to change. This is a UI/input-mechanism
                change only, not a hardcoded choice list — level names
                remain entirely free text, admin-defined per community,
                any language. --}}
                <div id="cbeFields" style="background:#e8f4fd; border-radius:6px; padding:6px; margin-bottom:5px;">
                    <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.hierarchy_level_names') }} <span style="color:#e53935;">*</span></label>
                    <div style="font-size:8px; color:#9ca3af; margin-bottom:3px;">{{ __('masterfile.hierarchy_level_helper') }}</div>
                    <div id="cbeLevelsList" style="display:flex; flex-wrap:wrap; align-items:center; gap:4px; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; background:#fff; box-sizing:border-box; min-height:26px;"></div>
                    <input type="hidden" id="cbeLevelsInput" name="cbe_levels" value="{{ old('cbe_levels', $cbeLevelsCsv ?? '') }}">
                    @error('cbe_levels')
                        <div style="font-size:8.5px; color:#c62828; font-weight:600; margin-top:2px;">{{ $message }}</div>
                    @enderror
                    {{-- CHANGED 12 Sep 2026 — per Chris: Login Page Logo
                    moved up to the shared top row (see gnStep1 above) —
                    removed the CBE-only duplicate that used to sit here,
                    saving a whole row of height. --}}
                </div>
                </div>

                <div id="gnStep3" class="gnStep">
                <div style="background:#e8f4fd; border-radius:6px; padding:6px; margin-bottom:5px;">
                    {{-- CHANGED 11 Sep 2026 — per Chris: the old label
                    "Faith Practice Type" was misleading — this does NOT
                    record the group's religion. It only picks which
                    WORDS the Appointments tab uses ("Sensei" vs
                    "Confessor" vs "Priest" etc, via
                    CbeFaithTerminologyService). Renamed. The choice list
                    itself is a fully Admin-editable catalog
                    (cbe_faith_practice_types, managed at
                    admin.faith-practice-types.index) — never hardcoded.

                    CHANGED 12 Sep 2026 — per Chris: "why this is
                    hardcoded? i can add others like Advisor, Councilor,
                    Consultant, in house Legal Advisor, Volunteer Lawyer,
                    Medical Advisor... it should have multiple choice
                    because one CBE may have few other position appointed
                    in house." The catalog was already admin-editable —
                    the real problem was this being a single-choice
                    dropdown when a community (CBE covers cooperatives,
                    alumni, clubs, SMEs, charities too, not just
                    religious groups) may genuinely need SEVERAL
                    positions available for members to book appointments
                    with at once. Replaced with checkboxes — same pattern
                    already approved for GLADE Membership Tiers — so any
                    number can be turned on together. A "+ Add Position"
                    link mirrors the GLADE tier list so it's obvious new
                    positions can be added any time, never a fixed set. --}}
                    <div style="margin-top:4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                            <label title="{{ __('admin_cbe_directory.faith_practice_type_helper') }}" style="font-size:9px; font-weight:600; color:#374151; white-space:nowrap; cursor:help; border-bottom:1px dotted #9ca3af; width:fit-content;">{{ __('admin_cbe_directory.faith_practice_type_label') }}</label>
                            <a href="{{ route('admin.faith-practice-types.index') }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:600;">+ {{ __('admin_cbe_faith_types.add_title') }}</a>
                        </div>
                        @php
                            $selectedTypeIds = old('appointment_type_ids', $groupAppointmentTypeIds ?? []);
                            $apPageSize = 6;
                            $apCount = count($appointmentTypeCatalog ?? []);
                            $apTotalPages = $apCount > 0 ? (int) ceil($apCount / $apPageSize) : 1;
                        @endphp
                        {{-- FIXED 12 Sep 2026 — per Chris: "why still have
                        scroll" (removed the max-height+scroll box) and then
                        "must vertical align better display" + "if the
                        appointment check more than the screen box, are you
                        display scroll to the right and left? i dont want
                        scroll up and down" — replaced the ragged flex-wrap
                        row with a fixed 3-column grid (clean vertical
                        alignment down each column, labels wrap instead of
                        truncating) capped at 2 rows, with the same Prev/
                        Next paging already used for Team Leaders/Affiliate
                        Entities elsewhere on this screen. However many
                        positions Admin adds later, this box's height never
                        changes and never scrolls in any direction. --}}
                        <div style="display:flex; align-items:center; justify-content:flex-end; margin-bottom:2px; {{ $apCount > $apPageSize ? '' : 'display:none;' }}" id="apPagerWrap">
                            <div style="display:flex; align-items:center; gap:4px;">
                                <span onclick="apPageNav(-1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.prev') }}</span>
                                <span id="apPageLabel" style="font-size:9px; color:#6b7280;">1 / {{ $apTotalPages }}</span>
                                <span onclick="apPageNav(1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.next') }}</span>
                            </div>
                        </div>
                        <div style="border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; background:#fff; box-sizing:border-box;">
                            <div id="apChecksWrap" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1px 10px; min-height:{{ min($apCount, $apPageSize) > 0 ? (ceil(min($apCount, $apPageSize) / 3) * 16) : 14 }}px;">
                                @forelse($appointmentTypeCatalog ?? [] as $t)
                                    <label class="apRow" data-page="{{ intdiv($loop->index, $apPageSize) + 1 }}" style="display:flex; align-items:flex-start; gap:3px; font-size:10px; line-height:1.15; color:#374151; {{ $loop->index >= $apPageSize ? 'display:none;' : '' }}">
                                        <input type="checkbox" name="appointment_type_ids[]" value="{{ $t->id }}" {{ in_array($t->id, $selectedTypeIds) ? 'checked' : '' }} style="margin:1px 0 0 0; width:11px; height:11px; flex-shrink:0;">
                                        <span>{{ $t->option_label }}</span>
                                    </label>
                                @empty
                                    <span style="font-size:9px; color:#94A3B8;">{{ __('admin_cbe_faith_types.add_title') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <div id="gnStep4" class="gnStep">
                <div style="background:#e8f4fd; border-radius:6px; padding:6px; margin-bottom:5px;">
                    {{-- REMOVED 12 Sep 2026 (Task #416) — per Chris: "your
                    subcription type you have paid or free is wrong remove
                    it... NOT the entire CBE is free or paid... there is
                    paid features and free feature." The whole-community
                    Subscription Type (Free/Paid) dropdown wrongly implied
                    one community = one tier for everything. Removed from
                    this screen — Paid/Free is now flagged per INDIVIDUAL
                    program in the new Program Library master file (Admin
                    > Master File Maintenance > Program Library), with a
                    separate per-community unlock screen (Program Unlocks).
                    The existing subscription_tier column is left as-is on
                    the record (preserved via hidden input below, not
                    wiped) since CbeFeatureGateService still reads it
                    elsewhere — only this screen's input for it is gone. --}}
                    <input type="hidden" name="subscription_tier" value="{{ old('subscription_tier', $groupLabel->subscription_tier ?? 'FREE') }}">

                    {{-- CHANGED 11 Sep 2026 — per Chris: a community must
                    be able to offer its members a CHOICE of several GLADE
                    tiers, not just one — checkboxes instead of a single
                    dropdown, plus a Select All shortcut. Each tier here
                    is customer-facing billing (group_label_glade_tiers),
                    separate from subscription_tier above. CHANGED AGAIN
                    24 Sep 2026 — per Chris: checking a tier now links and
                    activates it immediately, no separate approval step;
                    unchecking an already-linked tier removes it
                    immediately too. New tiers can be added to
                    this list at any time from that same screen — nothing
                    here is a fixed/hardcoded set. --}}
                    <div style="margin-top:4px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                            <label title="{{ __('admin_glade_tiers.catalog_helper') }}" style="font-size:9px; font-weight:600; color:#374151; white-space:nowrap; cursor:help; border-bottom:1px dotted #9ca3af; width:fit-content;">{{ __('admin_glade_tiers.glade_tier_label') }}</label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <a href="{{ route('admin.glade-tiers.index') }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:600;">+ {{ __('admin_glade_tiers.add_tier_title') }}</a>
                                <a href="#" onclick="glSelectAllTiers(event)" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('admin_glade_tiers.select_all') }}</a>
                            </div>
                        </div>
                        {{-- FIXED 12 Sep 2026 — per Chris: "why still have
                        scroll" (removed max-height+scroll), then "must
                        vertical align better display" + "i dont want
                        scroll up and down" (or left/right) — same fixed
                        3-column grid + Prev/Next paging redesign as the
                        Appointment Positions box above, so this box's
                        height never changes and never scrolls, no matter
                        how many tiers are added later. --}}
                        @php
                            $gtPageSize = 6;
                            $gtCount = count($gladeTiers ?? []);
                            $gtTotalPages = $gtCount > 0 ? (int) ceil($gtCount / $gtPageSize) : 1;
                        @endphp
                        <div style="display:flex; align-items:center; justify-content:flex-end; margin-bottom:2px; {{ $gtCount > $gtPageSize ? '' : 'display:none;' }}" id="gtPagerWrap">
                            <div style="display:flex; align-items:center; gap:4px;">
                                <span onclick="gtPageNav(-1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.prev') }}</span>
                                <span id="gtPageLabel" style="font-size:9px; color:#6b7280;">1 / {{ $gtTotalPages }}</span>
                                <span onclick="gtPageNav(1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.next') }}</span>
                            </div>
                        </div>
                        <div style="border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; background:#fff; box-sizing:border-box;">
                            <div id="gladeTierChecks" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1px 10px; min-height:{{ min($gtCount, $gtPageSize) > 0 ? (ceil(min($gtCount, $gtPageSize) / 3) * 16) : 14 }}px;">
                                @foreach($gladeTiers ?? [] as $gt)
                                    {{-- FIXED 12 Sep 2026 — per Chris (500 error report): the
                                    one-line @php(...) shortcut cannot reliably handle
                                    parentheses nested inside parentheses (e.g.
                                    "collect()->get(...)" or "in_array(...)" inside the
                                    @php(...) call) — Blade's compiler silently corrupts the
                                    rest of the compiled file when that happens, which is
                                    exactly what caused the "unexpected token endif" 500
                                    error. Switched to the block @php ... @endphp form,
                                    which has no such limit, same pattern already used
                                    safely elsewhere in this file. --}}
                                    @php
                                        $link = ($gladeTierLinks ?? collect())->get($gt->tier_id);
                                        $oldIds = old('glade_tier_ids');
                                        $isChecked = $oldIds !== null ? in_array($gt->tier_id, $oldIds) : (bool) $link;
                                    @endphp
                                    <label class="gtRow" data-page="{{ intdiv($loop->index, $gtPageSize) + 1 }}" style="display:flex; align-items:flex-start; gap:3px; font-size:10px; line-height:1.15; color:#374151; {{ $loop->index >= $gtPageSize ? 'display:none;' : '' }}">
                                        <input type="checkbox" name="glade_tier_ids[]" value="{{ $gt->tier_id }}" {{ $isChecked ? 'checked' : '' }} style="margin:1px 0 0 0; width:11px; height:11px; flex-shrink:0;">
                                        <span>{{ $gt->tier_name }}{{ $gt->is_custom_quotation ? ' ('.__('admin_glade_tiers.custom_quotation').')' : ' — RM '.number_format($gt->annual_fee, 2) }}
                                        {{-- CHANGED 24 Sep 2026 -- per Chris: ticking a tier now
                                        activates it immediately (see syncGladeTiers()), so a
                                        linked tier is always ACTIVE -- the PENDING_APPROVAL
                                        state/badge no longer occurs. --}}
                                        @if($link)
                                            <span style="color:#2e7d32; font-weight:700;">✓</span>
                                        @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                </div>
                {{-- END gnStepContainer --}}

                {{-- REVERTED 12 Sep 2026 — per Chris: "why you do in this
                display, i say one screen NOT next next prev prev" — the
                Prev/Next wizard from earlier today is removed; back to a
                single always-visible Save/Cancel row. The gnStepN wrapper
                divs above are kept (harmless, all shown at once now) so
                nothing else on the page had to be restructured again. --}}
                <div style="display:flex; gap:8px; margin-top:4px;">
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 18px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
                    <a href="{{ route('admin.masterfile.group-names') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:5px; padding:5px 14px; font-size:11px; font-weight:500;">{{ __('masterfile.cancel') }}</a>
                </div>
            </div>
        </form>

        <script>
            // Shows exactly one of the two conditional boxes at a time,
            // and only marks the visible one's fields as required, so
            // validation matches whichever Group Type is actually
            // selected. Login Page Logo is a single shared field in the
            // top row now (see gnStep1), so no mirroring needed here.
            function toggleGroupTypeFields() {
                var type = document.getElementById('group_type').value;
                var dsgOrg = document.getElementById('dsgOrgFields');
                var cbe = document.getElementById('cbeFields');
                var promoSelect = dsgOrg.querySelector('select[name="promotion_demotion_enabled"]');
                // FIXED 12 Sep 2026 — per master spec Section 56 / v133
                // changelog: Appointment Positions (CbeFaithTerminologyService)
                // and GLADE Membership Tiers are CBE/community features only,
                // not applicable to DSG/ORG groups. They were rendering
                // unconditionally for every group type, adding ~250-300px of
                // unnecessary height on a DSG/ORG edit and breaking the
                // one-screen-no-scroll rule for the common case. Now hidden
                // (and excluded from layout flow via display:none) whenever
                // Group Type is not CBE.
                var step3 = document.getElementById('gnStep3');
                var step4 = document.getElementById('gnStep4');

                // FIXED 12 Sep 2026 — cbeLevelsInput is now a hidden
                // field (see glSyncLevelsHidden below). A hidden field
                // left required="true" can silently block form submit
                // in some browsers with no visible error, so instead of
                // toggling .required on it we validate the level COUNT
                // ourselves at submit time (see the form submit listener
                // below), only when Group Type is CBE.
                if (type === 'CBE') {
                    dsgOrg.style.display = 'none';
                    cbe.style.display = 'block';
                    promoSelect.required = false;
                    if (step3) step3.style.display = 'block';
                    if (step4) step4.style.display = 'block';
                } else {
                    dsgOrg.style.display = 'block';
                    cbe.style.display = 'none';
                    promoSelect.required = true;
                    if (step3) step3.style.display = 'none';
                    if (step4) step4.style.display = 'none';
                }
            }
            document.addEventListener('DOMContentLoaded', toggleGroupTypeFields);

            // NEW 25 Sep 2026 -- per Chris: instant preview so it's
            // obvious a logo choice/upload actually registered, before
            // Save is even clicked.
            document.addEventListener('DOMContentLoaded', function () {
                var img = document.getElementById('logoPreviewImg');
                var select = document.getElementById('logoPathSelect');
                var upload = document.getElementById('logoUploadInput');
                if (!img || !select || !upload) return;

                select.addEventListener('change', function () {
                    if (select.value) {
                        img.src = '{{ asset('images') }}/' + select.value;
                        img.style.display = 'inline-block';
                    } else {
                        img.style.display = 'none';
                    }
                });

                upload.addEventListener('change', function () {
                    if (upload.files && upload.files[0]) {
                        img.src = URL.createObjectURL(upload.files[0]);
                        img.style.display = 'inline-block';
                    }
                });
            });

            // NEW 12 Sep 2026 — per Chris: replaced the free-typed,
            // comma-separated Hierarchy Level Names field (one stray or
            // missing comma silently changed what got saved) with a row
            // of individually editable boxes — one level name per box,
            // no separator ever typed. The hidden #cbeLevelsInput is
            // kept in sync as the same comma-joined string the backend
            // already expects, so the controller/validation needed no
            // changes.
            var glLevels = [];

            function glLoadLevelsFromHidden() {
                var raw = document.getElementById('cbeLevelsInput').value;
                glLevels = raw.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
                if (glLevels.length === 0) glLevels = [''];
                glRenderLevelRows();
            }

            function glSyncLevelsHidden() {
                var cleaned = glLevels.map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
                document.getElementById('cbeLevelsInput').value = cleaned.join(',');
            }

            function glRenderLevelRows() {
                var list = document.getElementById('cbeLevelsList');
                list.innerHTML = '';
                glLevels.forEach(function (name, idx) {
                    var wrap = document.createElement('span');
                    wrap.style.cssText = 'display:inline-flex; align-items:center; gap:2px; background:#e0f7fa; border:1px solid #b2ebf2; border-radius:5px; padding:1px 2px 1px 6px;';

                    var box = document.createElement('input');
                    box.type = 'text';
                    box.value = name;
                    box.placeholder = idx === 0 ? '{{ __('masterfile.hierarchy_level_add_placeholder') }}' : '';
                    box.style.cssText = 'border:none; outline:none; background:transparent; font-size:10.5px; color:#0d3c72; width:' + Math.max(3, name.length + 2) + 'ch; box-sizing:content-box;';

                    box.addEventListener('input', function () {
                        glLevels[idx] = box.value;
                        box.style.width = Math.max(3, box.value.length + 2) + 'ch';
                        glSyncLevelsHidden();
                    });
                    box.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            glAddLevelRow(idx);
                        } else if (e.key === 'Backspace' && box.value === '' && glLevels.length > 1) {
                            e.preventDefault();
                            glLevels.splice(idx, 1);
                            glSyncLevelsHidden();
                            glRenderLevelRows();
                            var prev = list.querySelectorAll('input[type=text]')[Math.max(0, idx - 1)];
                            if (prev) prev.focus();
                        }
                    });

                    var removeBtn = document.createElement('span');
                    removeBtn.textContent = '×';
                    removeBtn.title = '{{ __('masterfile.hierarchy_level_remove') }}';
                    removeBtn.style.cssText = 'cursor:pointer; color:#0d3c72; font-weight:700; font-size:11px; padding:0 3px; line-height:1;';
                    removeBtn.onclick = function () {
                        if (glLevels.length <= 1) { glLevels[0] = ''; } else { glLevels.splice(idx, 1); }
                        glSyncLevelsHidden();
                        glRenderLevelRows();
                    };

                    wrap.appendChild(box);
                    wrap.appendChild(removeBtn);
                    list.appendChild(wrap);
                });

                var addBtn = document.createElement('span');
                addBtn.textContent = '{{ __('masterfile.hierarchy_level_add') }}';
                addBtn.style.cssText = 'cursor:pointer; font-size:9.5px; color:#1565C0; font-weight:700; padding:3px 6px;';
                addBtn.onclick = function () { glAddLevelRow(glLevels.length - 1); };
                list.appendChild(addBtn);
            }

            function glAddLevelRow(afterIdx) {
                glLevels.splice(afterIdx + 1, 0, '');
                glSyncLevelsHidden();
                glRenderLevelRows();
                var boxes = document.getElementById('cbeLevelsList').querySelectorAll('input[type=text]');
                if (boxes[afterIdx + 1]) boxes[afterIdx + 1].focus();
            }

            document.addEventListener('DOMContentLoaded', glLoadLevelsFromHidden);

            // Block submit only when CBE is selected and no real level
            // names were entered — mirrors what used to be a plain
            // required="true" on the (now hidden) input.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelector('form').addEventListener('submit', function (e) {
                    var type = document.getElementById('group_type').value;
                    var hasLevels = glLevels.some(function (s) { return s.trim() !== ''; });
                    if (type === 'CBE' && !hasLevels) {
                        e.preventDefault();
                        alert('{{ __('masterfile.hierarchy_level_names') }}: ' + '{{ __('masterfile.hierarchy_level_add_placeholder') }}');
                        document.getElementById('cbeLevelsList').querySelector('input[type=text]').focus();
                    }
                });
            });

            // NEW 11 Sep 2026 — "Select All" shortcut for the GLADE tier
            // checkbox list (offer every currently active catalog tier
            // to this community). Purely a convenience click, not a
            // stored "all tiers forever" flag — a tier added to the
            // catalog LATER is not automatically offered to a community
            // that used this; it still needs its own explicit tick (and
            // approval) here.
            function glSelectAllTiers(e) {
                e.preventDefault();
                document.querySelectorAll('#gladeTierChecks input[type=checkbox]').forEach(function (cb) {
                    cb.checked = true;
                });
            }

            // NEW 12 Sep 2026 — Prev/Next paging for the Appointment
            // Positions and GLADE Tier checkbox grids (see redesign above)
            // — same fixed-height, no-scroll pattern already used for
            // Team Leaders/Affiliate Entities on the other tab.
            (function () {
                var apCurrentPage = 1;
                var apTotalPages = {{ $apTotalPages }};
                window.apPageNav = function (dir) {
                    var next = apCurrentPage + dir;
                    if (next < 1 || next > apTotalPages) return;
                    apCurrentPage = next;
                    document.querySelectorAll('.apRow').forEach(function (row) {
                        row.style.display = (parseInt(row.getAttribute('data-page'), 10) === apCurrentPage) ? 'flex' : 'none';
                    });
                    document.getElementById('apPageLabel').textContent = apCurrentPage + ' / ' + apTotalPages;
                };
            })();

            (function () {
                var gtCurrentPage = 1;
                var gtTotalPages = {{ $gtTotalPages }};
                window.gtPageNav = function (dir) {
                    var next = gtCurrentPage + dir;
                    if (next < 1 || next > gtTotalPages) return;
                    gtCurrentPage = next;
                    document.querySelectorAll('.gtRow').forEach(function (row) {
                        row.style.display = (parseInt(row.getAttribute('data-page'), 10) === gtCurrentPage) ? 'flex' : 'none';
                    });
                    document.getElementById('gtPageLabel').textContent = gtCurrentPage + ' / ' + gtTotalPages;
                };
            })();

            // NEW 12 Sep 2026 — switches between the Profile and
            // Affiliate Entity tabs (see style block above). Plain
            // show/hide, no page reload, no data refetch.
            // CHANGED 15 Sep 2026 — extended from 2 tabs to up to 4
            // (Profile / Affiliate Entity / Contact Details / Committee
            // Management Team) — same plain show/hide, no reload.
            // NEW 27 Sep 2026 -- ?tab=affiliate reopens the Entities / Branches tab (e.g. Prev from an entity's View / Edit).
            @if(request('tab') === 'affiliate' && $groupLabel && $groupLabel->group_type === 'CBE')
            window.location.replace(@json(route('admin.cbe-kpi.group-entities', ['group' => $groupLabel->group_label_id])));
            @elseif(in_array(request('tab'), ['affiliate', 'contact', 'committee']))
            document.addEventListener('DOMContentLoaded', function () { gnSwitchTab(@json(request('tab'))); });
            @endif
            function gnSwitchTab(tab) {
                ['profile', 'affiliate', 'contact', 'committee'].forEach(function (t) {
                    var panel = document.getElementById('gnTabPanel' + t.charAt(0).toUpperCase() + t.slice(1));
                    var btn = document.getElementById('gnTabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
                    if (panel) panel.style.display = (t === tab) ? 'block' : 'none';
                    if (btn) btn.classList.toggle('gn-tab-btn-active', t === tab);
                });
            }

            // NEW 15 Sep 2026 — adds another blank phone row to the
            // group's head-office contact, same "+ Add Contact" pattern
            // as the per-entity Profile tab under Entity Maintenance.
            var gnPhoneIdx = {{ $groupPhones->count() }};
            function gnAddPhoneRow() {
                var wrap = document.getElementById('gnPhoneRows');
                if (!wrap) return;
                var div = document.createElement('div');
                div.className = 'gnPhoneRow';
                div.style.cssText = 'display:flex; gap:6px;';
                div.innerHTML = '<input type="hidden" name="phones[' + gnPhoneIdx + '][id]" value="">'
                    + '<input type="text" name="phones[' + gnPhoneIdx + '][number]" placeholder="{{ __('masterfile.contact_phone_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">'
                    + '<input type="text" name="phones[' + gnPhoneIdx + '][note]" placeholder="{{ __('masterfile.contact_note_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">';
                wrap.appendChild(div);
                gnPhoneIdx++;
            }

            // NEW 15 Sep 2026 — Committee/Management Team agent typeahead:
            // picks an EXISTING Agent/Member only (per Chris, no manual
            // typing) — hidden agent_id is what actually submits.
            @if($groupLabel && $groupLabel->group_type === 'CBE')
            (function () {
                var input = document.getElementById('gnCommitteeAgentSearch');
                var hiddenId = document.getElementById('gnCommitteeAgentId');
                var dropdown = document.getElementById('gnCommitteeDropdown');
                if (!input) return;
                var timer;

                input.addEventListener('input', function () {
                    hiddenId.value = '';
                    clearTimeout(timer);
                    var q = this.value.trim();
                    if (q.length < 1) { dropdown.style.display = 'none'; return; }
                    timer = setTimeout(function () {
                        fetch('{{ route('admin.masterfile.group-names.committee-agent-typeahead', $groupLabel->group_label_id) }}?q=' + encodeURIComponent(q))
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data.length) {
                                    dropdown.innerHTML = '<div style="padding:8px 10px; font-size:11px; color:#9ca3af;">{{ __('masterfile.committee_no_match') }}</div>';
                                    dropdown.style.display = 'block';
                                    return;
                                }
                                dropdown.innerHTML = '';
                                data.forEach(function (item) {
                                    var d = document.createElement('div');
                                    d.style.cssText = 'padding:8px 10px; font-size:12px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                                    d.innerHTML = '<span style="font-weight:600; color:#1565C0;">' + item.full_name + '</span> <span style="color:#9ca3af; font-size:10.5px;">(' + item.agent_code + ')</span>';
                                    d.onmousedown = function (e) {
                                        e.preventDefault();
                                        input.value = item.full_name + ' (' + item.agent_code + ')';
                                        hiddenId.value = item.agent_id;
                                        dropdown.style.display = 'none';
                                    };
                                    dropdown.appendChild(d);
                                });
                                dropdown.style.display = 'block';
                            });
                    }, 250);
                });

                document.addEventListener('click', function (e) { if (e.target !== input) dropdown.style.display = 'none'; });

                document.getElementById('gnCommitteeAddForm').addEventListener('submit', function (e) {
                    if (!hiddenId.value) {
                        e.preventDefault();
                        alert('{{ __('masterfile.committee_no_match') }}');
                    }
                });
            })();
            @endif
        </script>

    </div>

    @if($groupLabel)
    <div id="gnTabPanelAffiliate" style="display:none;">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px;">

@if($groupLabel->group_type === 'CBE')
            {{-- NEW 25 Sep 2026 -- per Chris: "AFFILIATE ENTITY IS THE RANGE OF
            POST CODE OR CITY BELOW THE CAWANGAN... ALL THE TEMPLE (ENTITY)" --
            for a CBE group this tab means the real branch/temple hierarchy
            (Entity/Branch/State/HQ), never a list of people -- that's a
            completely different, DSG/ORG-only concept (see the @else
            below, untouched). --}}
            {{-- REMOVED 27 Sep 2026 — per Chris: no Add here; the tab button opens
                 the Search / View / Edit screen directly (admin.cbe-kpi.group-entities). --}}
            @else
                        {{-- Leadership surfaced separately — with hundreds of
                 Introducers below, the one GL (and any TLs) used to be
                 buried alphabetically in that list.

                 FIXED 18 Aug 2026 — per Chris: "no scroll" — with a
                 group like PVATM carrying 14 Team Leaders, this box
                 used to just keep growing and push the whole page into
                 a scrollbar. Now capped to 5 Team Leader rows at a
                 time with Prev/Next, same as every other list screen
                 in the app — fixed height regardless of how many Team
                 Leaders a group has, nothing ever clipped or hidden
                 permanently, just paged. --}}
            @php $tlPageSize = 5; $tlTotalPages = $teamLeaders->count() > 0 ? (int) ceil($teamLeaders->count() / $tlPageSize) : 1; @endphp
            <div style="background:#f0f9ff; border:1px solid #e0f2fe; border-radius:6px; padding:6px 8px; margin-bottom:8px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:3px;">
                    <div style="font-size:9px; font-weight:700; color:#6b7280;">{{ __('masterfile.leadership') }}</div>
                    @if($teamLeaders->count() > $tlPageSize)
                    <div style="display:flex; align-items:center; gap:4px;">
                        <span onclick="glTlPage(-1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.prev') }}</span>
                        <span id="tlPageLabel" style="font-size:9px; color:#6b7280;">1 / {{ $tlTotalPages }}</span>
                        <span onclick="glTlPage(1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.next') }}</span>
                    </div>
                    @endif
                </div>
                <div style="display:flex; align-items:center; gap:6px; padding:2px 0; font-size:10.5px;">
                    <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $groupLeader->full_name ?? '' }}">{{ \App\Services\RoleLabelService::label('GROUP_LEADER', $groupLeader->group_label_id ?? null) }}: <strong>{{ $groupLeader->full_name ?? __('masterfile.none_assigned') }}</strong></span>
                    @if($groupLeader)<span style="flex-shrink:0; color:#1565C0; font-weight:600;">{{ \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', 3, $groupLeader->group_label_id) }}</span>@endif
                </div>
                <div id="tlRowsWrap" style="min-height:{{ min($teamLeaders->count(), $tlPageSize) * 20 }}px;">
                @forelse($teamLeaders as $tl)
                <div class="tlRow" data-page="{{ intdiv($loop->index, $tlPageSize) + 1 }}" style="display:flex; align-items:center; gap:6px; padding:2px 0; font-size:10.5px; {{ $loop->index >= $tlPageSize ? 'display:none;' : '' }}">
                    <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $tl->full_name }}">{{ \App\Services\RoleLabelService::label('TEAM_LEADER', $tl->group_label_id) }}: <strong>{{ $tl->full_name }}</strong></span>
                    <span style="flex-shrink:0; color:#0891b2; font-weight:600;">{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', 3, $tl->group_label_id) }}</span>
                </div>
                @empty
                @endforelse
                </div>
            </div>
            @if($teamLeaders->count() > $tlPageSize)
            <script>
            (function () {
                var glTlCurrentPage = 1;
                var glTlTotalPages = {{ $tlTotalPages }};
                window.glTlPage = function (dir) {
                    var next = glTlCurrentPage + dir;
                    if (next < 1 || next > glTlTotalPages) return;
                    glTlCurrentPage = next;
                    document.querySelectorAll('.tlRow').forEach(function (row) {
                        row.style.display = (parseInt(row.getAttribute('data-page'), 10) === glTlCurrentPage) ? 'flex' : 'none';
                    });
                    document.getElementById('tlPageLabel').textContent = glTlCurrentPage + ' / ' + glTlTotalPages;
                };
            })();
            </script>
            @endif

            {{-- CHANGED 12 Sep 2026 — per Chris: a single vertical list
            here wasted the whole column width and forced vertical
            scrolling once a group had more than a handful of agents —
            breaks the no-scroll rule. Replaced with a multi-column grid
            (fills the full box width, several agents per row) plus the
            same Prev/Next paging already used for Team Leaders above —
            no scrolling in any direction, many more agents visible per
            screen than the old list. --}}
            @php $agPageSize = 15; $agTotalPages = $linkedAgents->count() > 0 ? (int) ceil($linkedAgents->count() / $agPageSize) : 1; @endphp
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:5px;">
                <div style="font-size:10px; font-weight:700; color:#1565C0;">{{ __('masterfile.agents_under', ['group' => $groupLabel->group_name, 'count' => $linkedAgents->count()]) }}</div>
                @if($linkedAgents->count() > $agPageSize)
                <div style="display:flex; align-items:center; gap:4px;">
                    <span onclick="glAgPage(-1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.prev') }}</span>
                    <span id="agPageLabel" style="font-size:9px; color:#6b7280;">1 / {{ $agTotalPages }}</span>
                    <span onclick="glAgPage(1)" style="font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:1px 6px; border:1px solid #B2EBF2; border-radius:4px; background:#fff;">{{ __('masterfile.next') }}</span>
                </div>
                @endif
            </div>
            <div id="agRowsWrap" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(130px, 1fr)); gap:2px 8px;">
            @forelse($linkedAgents as $a)
            <div class="agRow" data-page="{{ intdiv($loop->index, $agPageSize) + 1 }}" style="display:flex; align-items:center; gap:4px; padding:2px 0; border-bottom:1px solid #f3f4f6; font-size:10px; min-width:0; {{ $loop->index >= $agPageSize ? 'display:none;' : '' }}">
                <span style="flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $a->full_name }} ({{ $a->agent_code }})">{{ $a->full_name }}</span>
                <span style="flex-shrink:0; color:#9ca3af; font-size:9px;">{{ \App\Services\RoleLabelService::shortLabel($a->role, 3, $a->group_label_id) }}</span>
            </div>
            @empty
            <div style="grid-column:1/-1; color:#9ca3af; font-size:10.5px; text-align:center; padding:6px 0;">{{ __('masterfile.no_agents_linked') }}</div>
            @endforelse
            </div>
            @if($linkedAgents->count() > $agPageSize)
            <script>
            (function () {
                var glAgCurrentPage = 1;
                var glAgTotalPages = {{ $agTotalPages }};
                window.glAgPage = function (dir) {
                    var next = glAgCurrentPage + dir;
                    if (next < 1 || next > glAgTotalPages) return;
                    glAgCurrentPage = next;
                    document.querySelectorAll('.agRow').forEach(function (row) {
                        row.style.display = (parseInt(row.getAttribute('data-page'), 10) === glAgCurrentPage) ? 'flex' : 'none';
                    });
                    document.getElementById('agPageLabel').textContent = glAgCurrentPage + ' / ' + glAgTotalPages;
                };
            })();
            </script>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- NEW 15 Sep 2026 — per Chris: "setting up group name does not have
         the full contacts details... add contact... also the temple/NGO
         committee team or SME CBE group management team name position
         like the President, deputy, secretary, treasurer not hardcoded...
         you should have a master file to set up the position according
         to the cbe group." Two new tabs, CBE-only, both gated behind
         $groupLabel (create screen has nothing to attach a contact/
         committee to yet — Save the group first, then come back to add
         these on the edit screen). --}}
    @if($groupLabel && $groupLabel->group_type === 'CBE')
    <div id="gnTabPanelContact" style="display:none;">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; max-width:560px;">

            @if(session('contact_saved'))
            <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:11px; margin-bottom:8px;">{{ __('masterfile.contact_saved') }}</div>
            @endif
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ __('masterfile.contact_helper') }}</div>

            <form method="POST" action="{{ route('admin.masterfile.group-names.update-contact', $groupLabel->group_label_id) }}">
                @csrf
                @method('PUT')

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div style="grid-column:1/-1;">
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.contact_address') }}</label>
                        <input type="text" name="address" value="{{ old('address', $groupLabel->address ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.contact_city') }}</label>
                        <input type="text" name="city" value="{{ old('city', $groupLabel->city ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.contact_postcode') }}</label>
                        <input type="text" name="postcode" value="{{ old('postcode', $groupLabel->postcode ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.contact_person_1') }}</label>
                        <input type="text" name="contact_person_1" value="{{ old('contact_person_1', $groupLabel->contact_person_1 ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.contact_person_2') }}</label>
                        <input type="text" name="contact_person_2" value="{{ old('contact_person_2', $groupLabel->contact_person_2 ?? '') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="margin-top:8px; display:flex; align-items:center; justify-content:space-between;">
                    <label style="font-size:10px; font-weight:600; color:#374151;">{{ __('masterfile.contact_phone_numbers') }}</label>
                    <span onclick="gnAddPhoneRow()" style="font-size:10.5px; font-weight:600; color:#1565C0; cursor:pointer;">{{ __('masterfile.contact_add_contact') }}</span>
                </div>
                <div id="gnPhoneRows" style="margin-top:4px; display:flex; flex-direction:column; gap:4px;">
                    @forelse($groupPhones as $ph)
                    <div class="gnPhoneRow" style="display:flex; gap:6px;">
                        <input type="hidden" name="phones[{{ $loop->index }}][id]" value="{{ $ph->phone_id }}">
                        <input type="text" name="phones[{{ $loop->index }}][number]" value="{{ $ph->phone_number }}" placeholder="{{ __('masterfile.contact_phone_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                        <input type="text" name="phones[{{ $loop->index }}][note]" value="{{ $ph->contact_note }}" placeholder="{{ __('masterfile.contact_note_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    @empty
                    @endforelse
                </div>

                <div style="margin-top:10px; display:flex; gap:8px;">
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="gnTabPanelCommittee" style="display:none;">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px;">

            @if(session('committee_saved'))
            <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:11px; margin-bottom:8px;">{{ __('masterfile.committee_saved') }}</div>
            @endif
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:8px;">{{ __('masterfile.committee_helper') }}</div>

            {{-- NEW 15 Sep 2026 — per the master spec's committee-structure
                 decision (Section 53, Box 2): every assignment carries a
                 mandatory term-of-service date range, and "current term"
                 vs "previous terms" is a simple date-range query — a
                 past term is kept as permanent history, never deleted.
                 Small sub-tab inside this tab, same plain show/hide as
                 the main Profile/Affiliate/Contact/Committee tabs. --}}
            {{-- REDESIGNED 26 Sep 2026 -- per Chris: follow the standard
                 Add / Search-View-Edit method. Landing shows the two
                 choices only; Add opens the add form on its own screen;
                 Search / View / Edit opens the searchable list. Prev
                 always goes back to this landing. --}}
            {{-- CHANGED 27 Sep 2026 — per Chris: no landing; the list opens straight away and + Add / New Term sit on the tab row. --}}
            <div id="gcLanding" style="display:none; gap:10px; padding:4px 0;">
                <button type="button" onclick="gcShow('add')" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.committee_btn_add') }}</button>
                <button type="button" onclick="gcShow('list')" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.search_view_edit') }}</button>
            </div>

            <div id="gcAddPanel" style="display:none;">
                <form method="POST" action="{{ route('admin.masterfile.group-names.committee-members.add', $groupLabel->group_label_id) }}" id="gnCommitteeAddForm">
                    @csrf
                    <div style="display:flex; gap:8px; align-items:flex-end; position:relative; flex-wrap:wrap;">
                        <div style="flex:0 0 160px;">
                            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.committee_position') }}</label>
                            <select name="position_type_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; background:#fff; box-sizing:border-box;">
                                @foreach($committeePositionCatalog as $pos)
                                <option value="{{ $pos->id }}">{{ $pos->position_label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="flex:1 1 180px; position:relative;">
                            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.committee_search_agent') }}</label>
                            <input type="text" id="gnCommitteeAgentSearch" autocomplete="off" placeholder="{{ __('masterfile.committee_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                            <input type="hidden" name="agent_id" id="gnCommitteeAgentId">
                            <div id="gnCommitteeDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:200px; overflow-y:auto; margin-top:2px;"></div>
                        </div>
                        <div style="flex:0 0 130px;">
                            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.committee_term_start') }}</label>
                            <input type="date" name="term_start_date" value="{{ date('Y-m-d') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                        </div>
                        <div style="flex:0 0 190px;">
                            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.committee_term_end') }}</label>
                            <input type="date" name="term_end_date" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                        </div>
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:600; cursor:pointer; height:29px;">{{ __('masterfile.committee_add') }}</button>
                    </div>
                </form>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-top:10px;">
                    <span onclick="gcShow('list')" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:5px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
                    <span></span>
                </div>
            </div>

            {{-- NEW 27 Sep 2026 — per Chris: New Term for the whole committee (key the dates once). --}}
            <div id="gcNewTermPanel" style="display:none;">
                <form method="POST" action="{{ route('admin.masterfile.group-names.committee-new-term', $groupLabel->group_label_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.committee_new_term_confirm', ['count' => $committeeMembersCurrent->count()])) }});">
                    @csrf
                    <div style="font-size:11px; color:#374151; margin-bottom:8px;">{{ __('masterfile.committee_new_term_help', ['count' => $committeeMembersCurrent->count()]) }}</div>
                    <div style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                        <div style="flex:0 0 150px;">
                            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.committee_term_start') }}</label>
                            <input type="date" name="new_term_start" value="{{ date('Y-m-d') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                        </div>
                        <div style="flex:0 0 220px;">
                            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.committee_term_end') }}</label>
                            <input type="date" name="new_term_end" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                        </div>
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:600; cursor:pointer; height:29px;">{{ __('masterfile.save') }}</button>
                    </div>
                </form>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-top:10px;">
                    <span onclick="gcShow('list')" style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
                    <span></span>
                </div>
            </div>

            <div id="gcListPanel" style="display:block;">
            <input type="text" id="gcSearchInput" autocomplete="off" placeholder="{{ __('masterfile.committee_filter_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box; margin-bottom:6px;">
            @php
                // Term shown next to the Current Term folder name (the term most members share).
                $gcTermOf = fn ($m) => \Illuminate\Support\Carbon::parse($m->term_start_date)->format('d/m/Y').' – '.($m->term_end_date ? \Illuminate\Support\Carbon::parse($m->term_end_date)->format('d/m/Y') : __('masterfile.committee_ongoing'));
                $gcCommonTerm = $committeeMembersCurrent->isNotEmpty() ? $committeeMembersCurrent->map($gcTermOf)->countBy()->sortDesc()->keys()->first() : null;
            @endphp
            <div style="display:flex; align-items:center; gap:4px; border-bottom:1px solid #e5e7eb; margin-bottom:8px;">
                <div id="gcSubBtnCurrent" class="gn-tab-btn gn-tab-btn-active" onclick="gcSwitchSubTab('current')" style="white-space:nowrap;">{{ __('masterfile.committee_tab_current') }} ({{ $committeeMembersCurrent->count() }})@if($gcCommonTerm) <span style="font-weight:400; color:#6b7280;">· {{ $gcCommonTerm }}</span>@endif</div>
                <div id="gcSubBtnPrevious" class="gn-tab-btn" onclick="gcSwitchSubTab('previous')" style="white-space:nowrap;">{{ __('masterfile.committee_tab_previous') }} ({{ $committeeMembersPrevious->count() }})</div>
                <span style="flex:1;"></span>
                {{-- NEW 27 Sep 2026 — per Chris: Add / New Term on the same row as the folders, right, blue. --}}
                <button type="button" onclick="gcShow('add')" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap;">+ {{ __('masterfile.committee_add') }}</button>
                <button type="button" onclick="gcShow('newterm')" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; cursor:pointer; white-space:nowrap;">{{ __('masterfile.committee_new_term') }}</button>
            </div>

            <div id="gcSubPanelCurrent">

                @php $ccPageSize = 5; $ccCount = $committeeMembersCurrent->count(); $ccTotalPages = $ccCount > 0 ? (int) ceil($ccCount / $ccPageSize) : 1; @endphp
                <table style="width:100%; border-collapse:collapse; font-size:11.5px;">
                    <thead>
                        <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                            <th style="text-align:left; padding:0.4em 0.6em; font-size:0.92em; color:#374151;">{{ __('masterfile.committee_col_position') }}</th>
                            <th style="text-align:left; padding:0.4em 0.6em; font-size:0.92em; color:#374151;">{{ __('masterfile.committee_col_name') }}</th>
                            <th style="text-align:left; padding:0.4em 0.6em; font-size:0.92em; color:#374151;">{{ __('masterfile.committee_col_phone') }}</th>
                            <th style="text-align:left; padding:0.4em 0.6em; font-size:0.92em; color:#374151;">{{ __('masterfile.committee_col_email') }}</th>
                            <th style="text-align:left; padding:0.4em 0.6em; font-size:0.92em; color:#374151;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($committeeMembersCurrent as $m)
                        <tr class="ccRow" style="border-bottom:1px solid #f3f4f6; white-space:nowrap;">
                            <td style="padding:0.4em 0.6em; font-weight:600; color:#1565C0; white-space:nowrap;">{{ $m->position_label }}</td>
                            <td style="padding:0.4em 0.6em; white-space:nowrap;">{{ $m->full_name }}@if($gcTermOf($m) !== $gcCommonTerm) <span style="color:#94A3B8; font-size:0.85em;">· {{ $gcTermOf($m) }}</span>@endif</td>
                            <td style="padding:0.4em 0.6em; color:#4b5563; white-space:nowrap;">{{ $m->phone ?: '—' }}</td>
                            <td style="padding:0.4em 0.6em; color:#4b5563; white-space:nowrap;">{{ $m->email ?: '—' }}</td>
                            <td style="padding:0.4em 0.6em; white-space:nowrap; text-align:right;">
                                {{-- End Term = not continuing -> Previous Terms at once. --}}
                                <form method="POST" action="{{ route('admin.masterfile.group-names.committee-members.remove', [$groupLabel->group_label_id, $m->member_id]) }}" onsubmit="return confirm({{ json_encode(__('masterfile.committee_end_term_confirm')) }});" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none; border:none; color:#e53935; font-size:inherit; font-weight:700; cursor:pointer; padding:0;">{{ __('masterfile.committee_end_term') }}</button>
                                </form>
                                &nbsp;&nbsp;
                                {{-- CHANGED 27 Sep 2026 — Continue New Term opens a small box: Position (pre-filled, can change) + Term Start. --}}
                                <button type="button" onclick="gcContinueBox('{{ $m->member_id }}')" style="background:none; border:none; color:#1565C0; font-size:inherit; font-weight:700; cursor:pointer; padding:0;">{{ __('masterfile.committee_continue') }}</button>
                            </td>
                        </tr>
                        <tr class="ccEdit" id="ccEdit-{{ $m->member_id }}" style="display:none; background:#f0f9ff;">
                            <td colspan="5" style="padding:0.5em 0.6em;">
                                <form method="POST" action="{{ route('admin.masterfile.group-names.committee-members.continue', [$groupLabel->group_label_id, $m->member_id]) }}" onsubmit="return confirm({{ json_encode(__('masterfile.committee_continue_confirm')) }});" style="display:flex; align-items:flex-end; gap:10px; margin:0; flex-wrap:nowrap;">
                                    @csrf
                                    <span style="font-weight:700; color:#263238; white-space:nowrap;">{{ $m->full_name }} — {{ __('masterfile.committee_continue') }}</span>
                                    <label style="display:flex; flex-direction:column; gap:2px; font-size:0.85em; color:#374151; font-weight:600;">{{ __('masterfile.committee_position') }}
                                        <select name="position_type_id" required style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:1em;">
                                            @foreach($committeePositionCatalog as $pos)
                                            <option value="{{ $pos->id }}" {{ $pos->id === $m->position_type_id ? 'selected' : '' }}>{{ $pos->position_label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label style="display:flex; flex-direction:column; gap:2px; font-size:0.85em; color:#374151; font-weight:600;">{{ __('masterfile.committee_term_start') }}
                                        <input type="date" name="term_start_date" value="{{ date('Y-m-d') }}" required style="border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:1em;">
                                    </label>
                                    <span style="flex:1;"></span>
                                    <span onclick="gcContinueBox('{{ $m->member_id }}')" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 14px; font-weight:700; cursor:pointer; white-space:nowrap;">{{ __('masterfile.prev') }}</span>
                                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 14px; font-weight:700; cursor:pointer; white-space:nowrap;">{{ __('masterfile.save') }}</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('masterfile.committee_no_members') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{-- CHANGED 26 Sep 2026 — per Chris's compulsory rule: Prev/Next always at the BOTTOM, Prev left, Next right, bold blue. --}}
                <div id="ccPagerWrap" style="display:none; align-items:center; justify-content:space-between; margin-top:8px;">
                    <span onclick="ccPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
                    <span id="ccPageLabel" style="font-size:10.5px; color:#4b5563; font-weight:600;"></span>
                    <span onclick="ccPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
                </div>
            </div>

            <div id="gcSubPanelPrevious" style="display:none;">
                @php $cpPageSize = 5; $cpCount = $committeeMembersPrevious->count(); $cpTotalPages = $cpCount > 0 ? (int) ceil($cpCount / $cpPageSize) : 1; @endphp
                <table style="width:100%; border-collapse:collapse; font-size:11.5px;">
                    <thead>
                        <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                            <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.committee_col_position') }}</th>
                            <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.committee_col_name') }}</th>
                            <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.committee_col_term') }}</th>
                            <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($committeeMembersPrevious as $m)
                        <tr class="cpRow" style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 8px; font-weight:600; color:#1565C0;">{{ $m->position_label }}</td>
                            <td style="padding:5px 8px; white-space:nowrap;">{{ $m->full_name }}</td>
                            <td style="padding:5px 8px; color:#4b5563; white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($m->term_start_date)->format('d/m/Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($m->term_end_date)->format('d/m/Y') }}</td>
                            <td style="padding:5px 8px; white-space:nowrap; text-align:right;">
                                {{-- NEW 27 Sep 2026 — Undo only for a term closed by the End Term button. --}}
                                @if(($m->end_reason ?? null) === 'END')
                                <form method="POST" action="{{ route('admin.masterfile.group-names.committee-members.undo-end', [$groupLabel->group_label_id, $m->member_id]) }}" onsubmit="return confirm({{ json_encode(__('masterfile.committee_undo_confirm', ['name' => $m->full_name])) }});" style="display:inline; margin:0;">
                                    @csrf
                                    <button type="submit" style="background:none; border:none; color:#1565C0; font-weight:700; cursor:pointer; padding:0; font-size:inherit;">{{ __('masterfile.committee_undo') }}</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('masterfile.committee_no_previous_terms') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{-- CHANGED 26 Sep 2026 — per Chris's compulsory rule: Prev/Next always at the BOTTOM, Prev left, Next right, bold blue. --}}
                <div id="cpPagerWrap" style="display:none; align-items:center; justify-content:space-between; margin-top:8px;">
                    <span onclick="cpPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
                    <span id="cpPageLabel" style="font-size:10.5px; color:#4b5563; font-weight:600;"></span>
                    <span onclick="cpPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
                </div>
            </div>

            </div>

            <script>
                // NEW 15 Sep 2026 — Current Term / Previous Terms sub-tab,
                // scoped inside the Committee/Management Team tab only.
                // NEW 27 Sep 2026 — open / close the Continue New Term box under a row
                function gcContinueBox(id) {
                    var box = document.getElementById('ccEdit-' + id);
                    var open = box.style.display === 'none';
                    document.querySelectorAll('.ccEdit').forEach(function (b) { b.style.display = 'none'; });
                    box.style.display = open ? 'table-row' : 'none';
                }
                function gcSwitchSubTab(tab) {
                    document.getElementById('gcSubPanelCurrent').style.display = (tab === 'current') ? 'block' : 'none';
                    document.getElementById('gcSubPanelPrevious').style.display = (tab === 'previous') ? 'block' : 'none';
                    document.getElementById('gcSubBtnCurrent').classList.toggle('gn-tab-btn-active', tab === 'current');
                    document.getElementById('gcSubBtnPrevious').classList.toggle('gn-tab-btn-active', tab === 'previous');
                }

                // REBUILT 26 Sep 2026 -- per Chris: "NO SCROLL" + Prev/Next
                // at the bottom. Rows per page are worked out from the real
                // space left on the screen (not a fixed 5), so the list and
                // its Prev/Next bar always fit with no scrolling.
                (function () {
                    function makePager(prefix) {
                        var state = { pages: [[]], cur: 0 };
                        function rows() { return Array.prototype.slice.call(document.querySelectorAll('.' + prefix + 'Row')).filter(function (r) { return r.getAttribute('data-nomatch') !== '1'; }); }
                        function allRows() { return Array.prototype.slice.call(document.querySelectorAll('.' + prefix + 'Row')); }
                        function show() {
                            allRows().forEach(function (r) { r.style.display = 'none'; });
                            (state.pages[state.cur] || []).forEach(function (r) { r.style.display = 'table-row'; });
                            // CHANGED 27 Sep 2026 — record range, not page numbers
                            var first = 0; for (var k = 0; k < state.cur; k++) first += state.pages[k].length;
                            var tot = state.pages.reduce(function (a, p) { return a + p.length; }, 0);
                            document.getElementById(prefix + 'PageLabel').textContent = tot ? @json(__('masterfile.showing_records')).replace(':first', first + 1).replace(':last', first + (state.pages[state.cur] || []).length).replace(':total', tot) : '';
                        }
                        function build() {
                            var all = rows();
                            var wrap = document.getElementById(prefix + 'PagerWrap');
                            allRows().forEach(function (r) { r.style.display = 'none'; });
                            if (!wrap) return;
                            if (!all.length) { wrap.style.display = 'flex'; state.pages = [[]]; state.cur = 0; document.getElementById(prefix + 'PageLabel').textContent = ''; return; }
                            all.forEach(function (r) { r.style.display = 'table-row'; });
                            var tbody = all[0].parentNode;
                            // NEW 27 Sep 2026 — every row on ONE line: shrink the whole table's font together if needed
                            var tbl = tbody.parentNode; tbl.style.fontSize = '';
                            if (tbody.offsetParent) {
                                var fs = parseFloat(getComputedStyle(tbl).fontSize), box = tbl.parentNode;
                                while (tbl.scrollWidth > box.clientWidth + 1 && fs > 6.5) { fs -= 0.25; tbl.style.fontSize = fs + 'px'; }
                            }
                            if (!tbody.offsetParent) return; // tab not visible yet — rebuilt when opened
                            wrap.style.display = 'flex';
                            var avail = window.innerHeight - tbody.getBoundingClientRect().top - wrap.offsetHeight - 22;
                            state.pages = [[]];
                            var used = 0;
                            all.forEach(function (r) {
                                var h = r.offsetHeight;
                                if (used + h > avail && state.pages[state.pages.length - 1].length) { state.pages.push([]); used = 0; }
                                state.pages[state.pages.length - 1].push(r);
                                used += h;
                            });
                            if (state.cur > state.pages.length - 1) state.cur = state.pages.length - 1;
                            wrap.style.display = 'flex';
                            show();
                        }
                        window[prefix + 'PageNav'] = function (dir) {
                            var n = state.cur + dir;
                            if (n < 0) return;
                            if (n > state.pages.length - 1) return;
                            state.cur = n; show();
                        };
                        return build;
                    }
                    var ccBuild = makePager('cc'), cpBuild = makePager('cp');
                    window.gcShow = function (which) {
                        document.getElementById('gcLanding').style.display = which === 'landing' ? 'flex' : 'none';
                        document.getElementById('gcAddPanel').style.display = which === 'add' ? 'block' : 'none';
                        document.getElementById('gcListPanel').style.display = which === 'list' ? 'block' : 'none';
                        document.getElementById('gcNewTermPanel').style.display = which === 'newterm' ? 'block' : 'none';
                        if (which === 'list') { rebuildAll(); }
                    };
                    var gcSearch = document.getElementById('gcSearchInput');
                    if (gcSearch) {
                        gcSearch.addEventListener('input', function () {
                            var q = this.value.trim().toLowerCase();
                            document.querySelectorAll('.ccRow, .cpRow').forEach(function (r) {
                                r.setAttribute('data-nomatch', (q === '' || r.textContent.toLowerCase().indexOf(q) !== -1) ? '0' : '1');
                            });
                            rebuildAll();
                        });
                    }
                    function rebuildAll() { ccBuild(); cpBuild(); }
                    var origSub = gcSwitchSubTab;
                    window.gcSwitchSubTab = function (tab) { origSub(tab); rebuildAll(); };
                    window.addEventListener('load', function () {
                        var origMain = window.gnSwitchTab;
                        if (typeof origMain === 'function') {
                            window.gnSwitchTab = function (tab) { origMain(tab); if (tab === 'committee') rebuildAll(); };
                        }
                        @if(session('committee_saved'))
                        window.gnSwitchTab('committee');
                        gcShow('list');
                        @endif
                        rebuildAll();
                    });
                    var t; window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(rebuildAll, 300); });
                })();
            </script>
        </div>
    </div>
    @endif

</div>
@endsection
