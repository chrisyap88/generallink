{{--
NEW 27 Aug 2026 — per Chris: "i say before search you should have the
tap, after search you show the tap with (n) records not disappear from
the screen when i drill in from temple tap. this apply to all tap."
Confirmed via mockup: "yes but the number show in same row."

Later per Chris: "i dont want the triple Sponsor & Donor wording and i
ONLY want one row in proper sequence tap" — Customers/Donors used to
render this bar PLUS a second row of their own local Search/Find/
Register mode buttons right underneath. Merged into ONE row: the local
mode buttons (if any) now render as extra tabs between the primary tab
and Participation/Appointments, in the same pill style, so it reads as
a single tab sequence instead of two stacked tab bars.

Then per Chris: "if i already search the donor in the first tap do i
need to re search again?" — on a person's PROFILE screen (Members/
Customers/Donors show.blade.php), the Participation/Appointments tabs
used to be real links to the TEMPLE-WIDE search screens, duplicating
(with different counts!) the profile's own existing Participation/
Appointments history tabs. Now, when $participationLocalKey /
$appointmentsLocalKey are passed, those two tabs become local
JS-toggles showing THIS person's own already-loaded history (no
re-search, no navigation) instead of linking away to a blank temple-
wide form — resolving both the confusion and the duplicate labels.

All local buttons switch panels via the host page's own client-side
cbdSwitchPanel(key) JS (no page reload) — this partial reuses the same
`.cbd-tabtoggle` / `data-p` marker convention the host page's JS
already toggles, keeping the pill highlight in sync without a reload.

Persistent, temple-wide tab bar. Included on Members/Customers/Donors
index + show, and on the shared Appointments/Participation screens.
NEVER conditionally omitted — every controller branch that renders one
of these views passes $tabCounts (+ $faithTerms), so this partial can
always render regardless of search state.

Required variables: $node, $tabCounts, $activeTab ('members'|'customers'|'donors'|'participation'|'appointments'), $faithTerms.
Optional: $primaryTabKey ('members' default), $primaryTabLabel, $primaryTabRoute.
Optional: $primaryLocalKey — if the host page has its own client-side
panel toggle (cbdSwitchPanel) and the primary tab should switch back to
its default panel via JS instead of a full page reload, pass the panel
key here (e.g. 'existing'). Leave null for plain navigation (the normal
case on Members/Participation/Appointments index screens).
Optional: $localTabs — array of ['label' => string, 'key' => panel key passed to cbdSwitchPanel, 'active' => bool],
rendered as extra same-row tabs between the primary tab and Participation/Appointments
(used by Customers "Find Customer", Donors "Link Existing Donor"/"Add New Donor",
and the profile screens' own "Profile"/"Contributions" tabs).
Optional: $participationLocalKey / $appointmentsLocalKey — panel keys
passed to cbdSwitchPanel; when set, those two tabs become local
JS-toggles (person's own history, already loaded) instead of links to
the temple-wide search screens. $participationCount / $appointmentCount
override $tabCounts for these two tabs when local mode is used.
--}}
@php
    $primaryTabKey = $primaryTabKey ?? 'members';
    $primaryTabLabel = $primaryTabLabel ?? __('admin_cbe_directory.members_title');
    $primaryTabRoute = $primaryTabRoute ?? 'admin.cbe-kpi.members';
    $primaryLocalKey = $primaryLocalKey ?? null;
    $localTabs = $localTabs ?? [];
    $participationLocalKey = $participationLocalKey ?? null;
    $appointmentsLocalKey = $appointmentsLocalKey ?? null;
    $participationCount = $participationCount ?? $tabCounts['participationCount'];
    $appointmentCount = $appointmentCount ?? $tabCounts['appointmentCount'];
    // FIX 27 Aug 2026 — index pages (Customers/Donors) define window.cbdSwitchPanel()
    // for their own Search/Find/Register toggle; profile pages (Members/Customers/
    // Donors show.blade.php) define a DIFFERENT function, cbdSwitchTab(), for their
    // Profile/Contributions/Participation/Appointments panels. This partial is shared
    // by both, so the JS function it calls must match whichever one the host page
    // actually defines — hardcoding one name silently broke the other page type.
    $switchFn = $switchFn ?? 'cbdSwitchPanel';

    $anyLocalActive = false;
    foreach ($localTabs as $lt) {
        if (! empty($lt['active'])) {
            $anyLocalActive = true;
            break;
        }
    }
    if ($activeTab === 'participation' && $participationLocalKey) {
        $anyLocalActive = true;
    }
    if ($activeTab === 'appointments' && $appointmentsLocalKey) {
        $anyLocalActive = true;
    }
    $primaryActive = $activeTab === $primaryTabKey && ! $anyLocalActive;
@endphp
<div style="flex-shrink:0; display:flex; gap:4px; flex-wrap:wrap;">
    @if($primaryLocalKey)
    <div class="cbd-tabtoggle{{ $primaryActive ? ' active' : '' }}" data-p="{{ $primaryLocalKey }}" onclick="{{ $switchFn }}('{{ $primaryLocalKey }}')"
       style="cursor:pointer; white-space:nowrap; padding:7px 13px; font-size:9px; font-weight:700; border-radius:6px 6px 0 0; box-sizing:border-box;
       {{ $primaryActive ? 'background:var(--gl-blue); color:#fff;' : 'background:#EEF2F7; color:#475569;' }}">
        {{ $primaryTabLabel }}
    </div>
    @else
    <a href="{{ route($primaryTabRoute, ['node' => $node->node_id]) }}"
       style="white-space:nowrap; padding:7px 13px; font-size:9px; font-weight:700; text-decoration:none; border-radius:6px 6px 0 0; box-sizing:border-box;
       {{ $primaryActive ? 'background:var(--gl-blue); color:#fff;' : 'background:#EEF2F7; color:#475569;' }}">
        {{ $primaryTabLabel }}
    </a>
    @endif
    @foreach($localTabs as $lt)
    <div class="cbd-tabtoggle{{ ! empty($lt['active']) ? ' active' : '' }}" data-p="{{ $lt['key'] }}" onclick="{{ $switchFn }}('{{ $lt['key'] }}')"
       style="cursor:pointer; white-space:nowrap; padding:7px 13px; font-size:9px; font-weight:700; border-radius:6px 6px 0 0; box-sizing:border-box;
       {{ ! empty($lt['active']) ? 'background:var(--gl-blue); color:#fff;' : 'background:#EEF2F7; color:#475569;' }}">
        {{ $lt['label'] }}
    </div>
    @endforeach

    @if($participationLocalKey)
    <div class="cbd-tabtoggle{{ $activeTab === 'participation' ? ' active' : '' }}" data-p="{{ $participationLocalKey }}" onclick="{{ $switchFn }}('{{ $participationLocalKey }}')"
       style="cursor:pointer; white-space:nowrap; padding:7px 13px; font-size:9px; font-weight:700; border-radius:6px 6px 0 0; box-sizing:border-box;
       {{ $activeTab === 'participation' ? 'background:var(--gl-blue); color:#fff;' : 'background:#EEF2F7; color:#475569;' }}">
        {{ __('admin_cbe_directory.subtab_participation') }} ({{ number_format($participationCount) }})
    </div>
    @else
    {{-- NEW 27 Aug 2026 — per Chris: clicking Participation/Appointments
    from the Donors screen was landing on a tab bar that showed
    "Members" as the first tab, losing the fact you came from Donors.
    Carrying ?from=members|customers|donors through these two links (and
    reading it back below when THIS partial is the one rendered on the
    Participation/Appointments screens themselves) keeps the first tab
    correct no matter which of the 3 screens you started from. --}}
    <a href="{{ route('admin.cbe-kpi.participation', ['node' => $node->node_id, 'from' => $primaryTabKey]) }}"
       style="white-space:nowrap; padding:7px 13px; font-size:9px; font-weight:700; text-decoration:none; border-radius:6px 6px 0 0; box-sizing:border-box;
       {{ $activeTab === 'participation' ? 'background:var(--gl-blue); color:#fff;' : 'background:#EEF2F7; color:#475569;' }}">
        {{ __('admin_cbe_directory.subtab_participation') }} ({{ number_format($participationCount) }})
    </a>
    @endif

    @if($appointmentsLocalKey)
    <div class="cbd-tabtoggle{{ $activeTab === 'appointments' ? ' active' : '' }}" data-p="{{ $appointmentsLocalKey }}" onclick="{{ $switchFn }}('{{ $appointmentsLocalKey }}')"
       style="cursor:pointer; white-space:nowrap; padding:7px 13px; font-size:9px; font-weight:700; border-radius:6px 6px 0 0; box-sizing:border-box;
       {{ $activeTab === 'appointments' ? 'background:var(--gl-blue); color:#fff;' : 'background:#EEF2F7; color:#475569;' }}">
        {{ __($faithTerms['tab_label_key']) }} ({{ number_format($appointmentCount) }})
    </div>
    @else
    <a href="{{ route('admin.cbe-kpi.appointments', ['node' => $node->node_id, 'from' => $primaryTabKey]) }}"
       style="white-space:nowrap; padding:7px 13px; font-size:9px; font-weight:700; text-decoration:none; border-radius:6px 6px 0 0; box-sizing:border-box;
       {{ $activeTab === 'appointments' ? 'background:var(--gl-blue); color:#fff;' : 'background:#EEF2F7; color:#475569;' }}">
        {{ __($faithTerms['tab_label_key']) }} ({{ number_format($appointmentCount) }})
    </a>
    @endif
</div>
