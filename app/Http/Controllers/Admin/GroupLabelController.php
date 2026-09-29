<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\CbeFaithTerminologyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupLabelController extends Controller
{
    // Clean landing (Add / Search buttons only) until an actual search
    // is performed — same pattern as Introducer/TL/GL Maintenance.
    //
    // NEW 17 Aug 2026 — per Chris: reached from the new Group Set Up
    // drill-down (DSG/ORG/CBE), each of which links here with its own
    // ?type= so "cannot have ORG and CBE mixed" when browsing from a
    // specific group type's own program list. When ?type= is present,
    // results show immediately (skips the blank-landing step) and are
    // always scoped to that type, on top of any search text typed in.
    public function index(Request $request)
    {
        $type = $request->get('type');
        // CHANGED 24 Sep 2026 -- per Chris: "dont immediate display all
        // records before the selection filter ... MUST standardize like
        // the rest." Arriving here with only ?type= (as the sidebar's
        // Group Set Up DSG/ORG/CBE links do) no longer skips straight to
        // the results table -- it now lands on the same Add/Search
        // choice as every other master-file screen, just scoped to that
        // group type once Search is actually used. Only an actual search
        // action (the search form submitted, even with a blank field)
        // triggers the results view.
        $hasAnyFilter = $request->has('search');
        $groupLabels = null;

        if ($hasAnyFilter) {
            $query = DB::table('group_labels');
            if ($type) {
                $query->where('group_type', $type);
            }
            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $field = $request->get('field', '');
                $allowedFields = ['group_name', 'description'];
                $query->where(function ($q) use ($s, $field, $allowedFields) {
                    if (in_array($field, $allowedFields, true)) {
                        $q->where($field, 'like', $s);
                    } else {
                        $q->where('group_name', 'like', $s)
                          ->orWhere('description', 'like', $s);
                    }
                });
            }
            // CHANGED 26 Sep 2026 — per Chris: no scroll; rows per page measured by the screen.
            $perPageIn = (int) $request->get('per_page');
            $perPage = $perPageIn > 0 ? max(3, min(50, $perPageIn)) : 10;
            $groupLabels = $query->orderBy('group_name')->paginate($perPage)->withQueryString();
        }

        return view('masterfile.group-names', compact('hasAnyFilter', 'groupLabels', 'type'));
    }

    public function searchForm()
    {
        return view('masterfile.group-name-search-form');
    }

    // Live typeahead — matches the exact same instant-search pattern
    // used across Introducer/TL/GL Maintenance.
    public function typeahead(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        // CHANGED 24 Sep 2026 -- scoped by type (when the search was
        // reached from a specific DSG/ORG/CBE context) so type-ahead
        // results never suggest a group from a different group type.
        // CHANGED 24 Sep 2026 -- per Chris: "add search all criteria" --
        // now matches Description too, same as the full search-list
        // (index) already does, so the quick dropdown never misses a
        // group the full search would have found.
        $type = $request->get('type');
        // NEW 25 Sep 2026 -- per Chris: every search screen must offer
        // an explicit "Search All" choice, listed first, followed by
        // each specific field -- not just a silently-merged OR match.
        $field = $request->get('field', '');
        $allowedFields = ['group_name', 'description'];

        $s = '%' . $q . '%';
        $results = DB::table('group_labels')
            ->where(function ($sub) use ($s, $field, $allowedFields) {
                if (in_array($field, $allowedFields, true)) {
                    $sub->where($field, 'like', $s);
                } else {
                    $sub->where('group_name', 'like', $s)
                        ->orWhere('description', 'like', $s);
                }
            })
            ->when($type, function ($query) use ($type) {
                $query->where('group_type', $type);
            })
            ->orderBy('group_name')
            ->limit(15)
            ->get(["group_label_id", "group_name"]);

        return response()->json($results);
    }

    // NEW 17 Aug 2026 — per Chris: reached from Group Set Up's DSG/ORG/
    // CBE pages with ?type=, pre-selecting the right Group Type so a
    // new group created from "Direct Selling Group" can't accidentally
    // end up as ORG or CBE.
    public function create(Request $request)
    {
        return $this->edit(null, $request->get('type'));
    }

    // Scans the actual public/image folder, so Admin picks from files
    // that genuinely exist — no hardcoding, no typos.
    private function availableLogos(): array
    {
        $path = public_path('images');
        if (!is_dir($path)) {
            return [];
        }
        $files = scandir($path);
        return array_values(array_filter($files, function ($f) use ($path) {
            return is_file($path . '/' . $f) && preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $f);
        }));
    }

    // NEW 12 Sep 2026 — per Chris: "why i save the logo.jpg many times
    // still not save". The dropdown above only ever offered files that
    // ALREADY existed in public/images — Chris has no way to put a file
    // there himself (he never opens files/folders on the server), so his
    // own logo could never actually appear or be picked, no matter how
    // many times he tried. Added a real upload control (see the two
    // logo_upload / logo_upload_cbe file inputs on the form) — when a
    // file is uploaded this save, it's copied into public/images right
    // here and that filename is what gets stored; when no new file is
    // uploaded, this simply falls back to whatever was chosen from the
    // existing-files dropdown, unchanged from before.
    private function resolveLogoPath(Request $request): ?string
    {
        $uploaded = $request->file('logo_upload') ?: $request->file('logo_upload_cbe');
        if ($uploaded && $uploaded->isValid()) {
            $destDir = public_path('images');
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $ext = strtolower($uploaded->getClientOriginalExtension() ?: 'png');
            $filename = Str::slug($request->group_name ?: 'group') . '-' . time() . '.' . $ext;
            $uploaded->move($destDir, $filename);
            return $filename;
        }

        return $request->logo_path;
    }

    // Handles both "Add New" (no id) and "Edit" (with id) in one screen,
    // same as how the rest of this app's simpler master-data screens work.
    public function edit(?string $id = null, ?string $presetType = null)
    {
        $groupLabel = $id ? DB::table('group_labels')->where('group_label_id', $id)->first() : null;

        // Shows which agents currently carry this label — useful context
        // since one label can span many completely unrelated agents.
        // group_id is fetched per-agent (not one value for the whole
        // label) since it drives each agent's OWN group-specific role
        // label (RoleLabelService) — normally uniform within one Special
        // Privilege Group, but resolved correctly either way.
        $linkedAgents = $id
            ? Agent::where('group_label_id', $id)->where('is_deleted', false)->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code', 'role', 'group_id'])
            : collect();

        // NEW 24 Jul 2026 — per Chris: "where to see special group name GL
        // system role" — with hundreds of Introducers under one Special
        // Privilege Group, the one Group Leader (and any Team Leaders)
        // used to be buried alphabetically in the list below. Surfaced
        // separately here so they're immediately visible without
        // scrolling through everyone else.
        $groupLeader = $linkedAgents->firstWhere('role', 'GROUP_LEADER');
        $teamLeaders = $linkedAgents->where('role', 'TEAM_LEADER')->values();

        $availableLogos = $this->availableLogos();

        // NEW 17 Aug 2026 — per Chris: CBE (Community & Business
        // Enterprise Group) hierarchy level names, in order. Only ever
        // populated when group_type = 'CBE'. Joined into one
        // comma-separated string for the single-line input on the form
        // (e.g. "HQ, State, Branch, Sub-section, Temple") — kept to one
        // line so this screen doesn't grow taller and stays scroll-free.
        $cbeLevels = $id
            ? DB::table('cbe_hierarchy_levels')->where('group_label_id', $id)->orderBy('level_order')->pluck('level_name')
            : collect();
        $cbeLevelsCsv = $cbeLevels->implode(', ');

        // CHANGED 12 Sep 2026 — per Chris: "why this is hardcoded? ...
        // it should have multiple choice because one CBE may have few
        // other position appointed in house." A community can now
        // enable ANY NUMBER of appointment positions at once (formerly
        // a single faith_practice_type dropdown) — see
        // CbeFaithTerminologyService and the group_label_appointment_types
        // pivot table. Only relevant when group_type = CBE; the
        // checkbox list is hidden by JS for DSG/ORG same as the other
        // CBE-only fields.
        $appointmentTypeCatalog = CbeFaithTerminologyService::catalogRows();
        $groupAppointmentTypeIds = $id
            ? DB::table('group_label_appointment_types')->where('group_label_id', $id)->where('is_active', true)->pluck('practice_type_id')->all()
            : [];

        // NEW 27 Aug 2026 (Task #233) — GLADE Public Model billing
        // category picker, only relevant for CBE. Active tiers only —
        // an Admin who deactivates a catalog tier shouldn't be able to
        // newly assign it, though a community already on it keeps
        // showing its name (LEFT JOIN in the controller/index screen
        // handles that side, not this dropdown).
        $gladeTiers = DB::table('cbe_glade_membership_tiers')->where('is_active', true)->orderBy('sort_order')->get();

        // CHANGED 11 Sep 2026 — per Chris: a community can offer its
        // members a CHOICE of several tiers, not just one (see
        // group_label_glade_tiers migration). Keyed by tier_id so the
        // view can check "is this tier's checkbox on for this group"
        // and show each tier's own PENDING_APPROVAL/ACTIVE badge.
        $gladeTierLinks = $id
            ? DB::table('group_label_glade_tiers')->where('group_label_id', $id)->get(['tier_id', 'status'])->keyBy('tier_id')
            : collect();

        // NEW 15 Sep 2026 — per Chris: a separate HEAD OFFICE / main
        // contact for the whole group (distinct from each individual
        // entity's own Address/Contact 1/2/phones under Entity
        // Maintenance — that per-entity info is untouched by this).
        // Same repeatable-phone-list pattern as
        // AdminCbeKpiController::updateProfile().
        $groupPhones = $id
            ? DB::table('group_label_phones')->where('group_label_id', $id)->orderBy('display_order')->get()
            : collect();

        // NEW 15 Sep 2026 — per Chris: "temple/NGO committee team or SME
        // CBE group management team name position like the President,
        // deputy, secretary, treasurer... not hardcoded... you should
        // have a master file to set up the position according to the cbe
        // group." Positions come from the fully Admin-editable
        // cbe_committee_position_types catalog (same "never hardcode"
        // pattern as Appointment Terminology Types / GLADE Tiers), and
        // every member is picked from an existing Agent record — phone
        // and email are always read live from that agent, never typed.
        // CHANGED 24 Sep 2026 -- per Chris: "different cbe may have
        // different position" -- this group's Committee dropdown now
        // only offers positions that were created specifically FOR this
        // group, plus the shared System Default ones (group_label_id
        // NULL) -- never another CBE group's own positions. Same
        // group_label_id-scoping already used for role_ranks.
        // CHANGED 25 Sep 2026 -- per Chris: positions no longer fall
        // back to a shared "System Default" row -- each CBE group only
        // ever sees the positions actually set up for IT.
        $committeePositionCatalog = DB::table('cbe_committee_position_types')
            ->where('is_active', true)
            ->where('group_label_id', $id)
            ->orderBy('sort_order')
            ->get();

        // CHANGED 15 Sep 2026 — per the master spec's earlier committee-
        // structure decision (Section 53, Box 2): every assignment
        // carries a mandatory term-of-service date range, and "current
        // term" vs "previous terms" is simply a query by that date
        // range — a past assignment is never deleted, only ended
        // (term_end_date set), so it stays as permanent history instead
        // of disappearing.
        $today = now()->toDateString();
        $committeeHasEnded = \Illuminate\Support\Facades\Schema::hasColumn('group_committee_members', 'ended_at');
        $committeeMembersBase = fn () => DB::table('group_committee_members')
            ->join('agents', 'agents.agent_id', '=', 'group_committee_members.agent_id')
            ->join('cbe_committee_position_types', 'cbe_committee_position_types.id', '=', 'group_committee_members.position_type_id')
            ->where('group_committee_members.group_label_id', $id)
            ->select([
                'group_committee_members.id as member_id',
                'group_committee_members.term_start_date',
                'group_committee_members.term_end_date',
                'group_committee_members.position_type_id',
                \Illuminate\Support\Facades\Schema::hasColumn('group_committee_members', 'end_reason') ? 'group_committee_members.end_reason' : DB::raw('NULL as end_reason'),
                'cbe_committee_position_types.position_label',
                'agents.agent_id',
                'agents.full_name',
                'agents.agent_code',
                'agents.phone',
                'agents.email',
            ]);
        $committeeMembersCurrent = $id
            ? $committeeMembersBase()
                ->where(function ($q) use ($today) {
                    $q->whereNull('group_committee_members.term_end_date')
                        ->orWhere('group_committee_members.term_end_date', '>=', $today);
                })
                // NEW 27 Sep 2026 — a term ended by hand is Previous at once
                ->when($committeeHasEnded, fn ($q) => $q->whereNull('group_committee_members.ended_at'))
                ->orderBy('cbe_committee_position_types.sort_order')
                ->orderBy('agents.full_name')
                ->get()
            : collect();
        $committeeMembersPrevious = $id
            ? $committeeMembersBase()
                ->where(function ($q) use ($today, $committeeHasEnded) {
                    $q->where('group_committee_members.term_end_date', '<', $today);
                    if ($committeeHasEnded) {
                        $q->orWhereNotNull('group_committee_members.ended_at');
                    }
                })
                ->orderByDesc('group_committee_members.term_end_date')
                ->orderBy('cbe_committee_position_types.sort_order')
                ->get()
            : collect();
        // Kept for the tab-button count badge (current + previous combined).
        $committeeMembers = $committeeMembersCurrent->concat($committeeMembersPrevious);

        // NEW 25 Sep 2026 -- per Chris: "AFFILIATE ENTITY IS THE RANGE OF
        // POST CODE OR CITY BELOW THE CAWANGAN... ALL THE TEMPLE (ENTITY)"
        // -- the Affiliate Entity tab was showing linkedAgents (PEOPLE,
        // an MLM/DSG-ORG concept: "which agents carry this label"), wrongly
        // reused as-is for CBE groups too. For a CBE group "Affiliate
        // Entity" actually means the real branch/temple hierarchy
        // (cbe_hierarchy_nodes) -- e.g. Klang, Meru, Kapar, Pulau Ketam,
        // Pelabuhan Klang under one federation -- a completely different
        // thing from a person. CBE-only change, DSG/ORG untouched (still
        // shows linkedAgents exactly as before).
        $cbeEntityNodes = ($id && $groupLabel && $groupLabel->group_type === 'CBE')
            ? DB::table('cbe_hierarchy_nodes as n')
                ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                ->where('n.group_label_id', $id)
                ->orderBy('l.level_order')
                ->orderBy('n.node_name')
                ->get(['n.node_id', 'n.node_name', 'n.node_name_zh', 'n.city', 'n.node_code', 'l.level_name'])
            : collect();
        // NEW 27 Sep 2026 — how many HQ-family entities (e.g. temples) are
        // affiliated to each of this CBE's entities (CBE Entity Affiliation).
        if ($cbeEntityNodes->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id')) {
            $affCounts = DB::table('cbe_hierarchy_nodes')->whereIn('affiliated_node_id', $cbeEntityNodes->pluck('node_id'))
                ->selectRaw('affiliated_node_id, COUNT(*) as c')->groupBy('affiliated_node_id')->pluck('c', 'affiliated_node_id');
            $cbeEntityNodes = $cbeEntityNodes->map(function ($n) use ($affCounts) { $n->aff_count = (int) ($affCounts[$n->node_id] ?? 0); return $n; });
        }

        // NEW 27 Sep 2026 — tab count = the entities that belong to this CBE
        // (same set as the Search / View / Edit list and dashboard Box 4).
        // CHANGED 27 Sep 2026 — automatic identification + auto-save first,
        // so the "Affiliate Group (N)" count is already complete.
        if ($id && $groupLabel && $groupLabel->group_type === 'CBE') {
            $agNew = \App\Services\CbeTierService::autoAffiliate($id);
            if ($agNew) {
                // shown as "New" on the Affiliate Group screen opened next
                session()->put('ag_new_ids.'.$id, array_values(array_unique(array_merge(session('ag_new_ids.'.$id, []), $agNew))));
            }
        }
        $cbeEntityTotal = ($id && $groupLabel && $groupLabel->group_type === 'CBE')
            ? count(\App\Services\CbeTierService::groupEntityKinds($id)) : 0;

        return view('masterfile.group-name-edit', compact('cbeEntityTotal', 'groupLabel', 'linkedAgents', 'groupLeader', 'teamLeaders', 'availableLogos', 'cbeLevelsCsv', 'presetType', 'appointmentTypeCatalog', 'groupAppointmentTypeIds', 'gladeTiers', 'gladeTierLinks', 'groupPhones', 'committeePositionCatalog', 'committeeMembers', 'committeeMembersCurrent', 'committeeMembersPrevious', 'cbeEntityNodes'));
    }

    // NEW 11 Sep 2026 — per Chris's decision: a CBE community can now be
    // linked to SEVERAL GLADE tiers at once (its members choose among
    // them), replacing the old single glade_tier_id column. Called from
    // both store() and update() — also handles a group switching AWAY
    // from CBE, by passing an empty list (removes every link, same as
    // the old code's "always NONE for non-CBE" behavior).
    //
    // CHANGED 24 Sep 2026 -- per Chris: "i didnt see how you assign the
    // membership fees tier to each group ... remove this 2 tap [on the
    // GLADE Membership Tiers screen]" -- the separate PENDING_APPROVAL /
    // second-Admin-confirms step is removed. Ticking a tier here now
    // links it as ACTIVE immediately; unticking still removes it
    // immediately as before. Tiers already linked that are still in the
    // submitted list are left completely untouched, so re-saving the
    // form after an unrelated edit never disturbs an existing link.
    private function syncGladeTiers(string $groupLabelId, array $submittedTierIds): void
    {
        // Never trust a tampered tier_id through — only tiers that
        // genuinely exist in the active catalog can be linked.
        $validTierIds = DB::table('cbe_glade_membership_tiers')
            ->where('is_active', true)
            ->whereIn('tier_id', array_values(array_unique(array_filter($submittedTierIds))))
            ->pluck('tier_id')
            ->all();

        $existing = DB::table('group_label_glade_tiers')
            ->where('group_label_id', $groupLabelId)
            ->get(['id', 'tier_id']);

        $toRemove = $existing->whereNotIn('tier_id', $validTierIds);
        if ($toRemove->isNotEmpty()) {
            DB::table('group_label_glade_tiers')->whereIn('id', $toRemove->pluck('id'))->delete();
        }

        $existingTierIds = $existing->pluck('tier_id')->all();
        $toAdd = array_diff($validTierIds, $existingTierIds);
        $now = now();
        foreach ($toAdd as $tierId) {
            DB::table('group_label_glade_tiers')->insert([
                'id' => (string) Str::uuid(),
                'group_label_id' => $groupLabelId,
                'tier_id' => $tierId,
                'status' => 'ACTIVE',
                'requested_by' => auth('agent')->id(),
                'requested_at' => $now,
                'approved_by' => auth('agent')->id(),
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // NEW 12 Sep 2026 — per Chris's decision: a CBE community can now
    // enable SEVERAL appointment positions at once (e.g. both a "Legal
    // Advisor" and a "Medical Advisor"), replacing the old single
    // faith_practice_type column. Called from both store() and update().
    // Unlike GLADE tiers there's no approval workflow here — this is
    // purely internal terminology/config, not member-facing billing —
    // so enabling or disabling a position takes effect immediately.
    private function syncAppointmentTypes(string $groupLabelId, array $submittedTypeIds): void
    {
        // Never trust a tampered id through — only positions that
        // genuinely exist in the active catalog can be linked.
        $validTypeIds = DB::table('cbe_faith_practice_types')
            ->where('is_active', true)
            ->whereIn('id', array_values(array_unique(array_filter($submittedTypeIds))))
            ->pluck('id')
            ->all();

        DB::table('group_label_appointment_types')->where('group_label_id', $groupLabelId)->delete();

        $now = now();
        foreach (array_values($validTypeIds) as $i => $typeId) {
            DB::table('group_label_appointment_types')->insert([
                'id' => (string) Str::uuid(),
                'group_label_id' => $groupLabelId,
                'practice_type_id' => $typeId,
                'is_active' => true,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // Shared by store() and update() — parses the comma-separated
    // "HQ, State, Branch" input into a clean, ordered, non-empty list.
    // Generic on purpose: no fixed count, no Tao-specific defaults.
    private function parseCbeLevels(?string $csv): array
    {
        if (!$csv) {
            return [];
        }
        $levels = array_map('trim', explode(',', $csv));
        return array_values(array_filter($levels, fn($l) => $l !== ''));
    }

    // NEW 11 Sep 2026 — per Chris: typing this field free-form is "very
    // bad and open for mistake." Level names genuinely have to stay
    // free text (a CBE community can be named anything, in any
    // language/script — HQ/State/Branch/Temple, or a Rotary Club's own
    // terms — nothing here is a fixed enum), so this can't become a
    // dropdown. What CAN be caught server-side without restricting
    // legitimate names: two levels that are literally the same name
    // (a copy-paste slip — e.g. "Branch, Branch, Temple" — would
    // otherwise silently create two same-named, unrelated levels), and
    // a single "level name" that's absurdly long (a sign the commas
    // were mistyped or missed entirely, e.g. a whole sentence pasted in
    // by accident instead of level names). Returns an error message, or
    // null if the list is clean.
    private function validateCbeLevelNames(array $levelNames): ?string
    {
        $seen = [];
        foreach ($levelNames as $name) {
            if (mb_strlen($name) > 50) {
                return __('cbe_masterfile.err_level_name_too_long', ['name' => Str::limit($name, 30)]);
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                return __('cbe_masterfile.err_level_name_duplicate', ['name' => $name]);
            }
            $seen[$key] = true;
        }
        return null;
    }

    // FIXED 10 Sep 2026 (Task #399) — SAFETY FIX. This used to
    // unconditionally delete every level row for the group, then
    // reinsert a fresh set with brand-new level_ids, on EVERY save.
    // That's harmless for a CBE group with zero real entities yet, but
    // cbe_hierarchy_nodes.level_id is a foreign key with
    // onDelete('restrict') — the instant a group has even one real
    // entity (Tao already has 591+), that delete throws a database
    // error, meaning this screen would crash on ANY save of that
    // group, even one only touching its description. Rewritten to
    // update levels IN PLACE by position: an existing level at a given
    // position keeps its level_id (so no node's foreign key ever
    // breaks) and just gets renamed if the text changed; a new
    // trailing position is inserted; a trailing position that's being
    // removed is only actually deleted if nothing uses it yet.
    // Returns an error message if a requested removal isn't safe (the
    // group's other fields still save either way — see call sites);
    // null on success.
    private function saveCbeLevels(string $groupLabelId, array $levelNames): ?string
    {
        $existing = DB::table('cbe_hierarchy_levels')
            ->where('group_label_id', $groupLabelId)
            ->orderBy('level_order')
            ->get(['level_id', 'level_order', 'level_name']);

        $removed = $existing->where('level_order', '>', count($levelNames));
        $blockedNames = [];
        foreach ($removed as $lvl) {
            if (DB::table('cbe_hierarchy_nodes')->where('level_id', $lvl->level_id)->exists()) {
                $blockedNames[] = $lvl->level_name;
            }
        }
        if ($blockedNames) {
            return __('cbe_masterfile.err_levels_in_use', ['levels' => implode(', ', $blockedNames)]);
        }

        foreach ($levelNames as $index => $name) {
            $order = $index + 1;
            $current = $existing->firstWhere('level_order', $order);
            if ($current) {
                if ($current->level_name !== $name) {
                    DB::table('cbe_hierarchy_levels')->where('level_id', $current->level_id)
                        ->update(['level_name' => $name, 'updated_at' => now()]);
                }
            } else {
                DB::table('cbe_hierarchy_levels')->insert([
                    'level_id'        => (string) Str::uuid(),
                    'group_label_id'  => $groupLabelId,
                    'level_order'     => $order,
                    'level_name'      => $name,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        foreach ($removed as $lvl) {
            DB::table('cbe_hierarchy_levels')->where('level_id', $lvl->level_id)->delete();
        }

        return null;
    }

    // NEW 15 Sep 2026 — per Chris: a group-level head-office contact,
    // separate and additional to each individual entity's own contact
    // info (Entity Maintenance keeps that untouched). Same
    // update-address-then-sync-phones pattern as
    // AdminCbeKpiController::updateProfile().
    public function updateContact(Request $request, string $id)
    {
        $groupLabel = DB::table('group_labels')->where('group_label_id', $id)->first();
        abort_if(! $groupLabel, 404);

        $request->validate([
            'address'           => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:100'],
            'postcode'          => ['nullable', 'string', 'max:20'],
            'contact_person_1'  => ['nullable', 'string', 'max:150'],
            'contact_person_2'  => ['nullable', 'string', 'max:150'],
        ]);

        DB::table('group_labels')->where('group_label_id', $id)->update([
            'address'            => $request->get('address') ?: null,
            'city'               => $request->get('city') ?: null,
            'postcode'           => $request->get('postcode') ?: null,
            'contact_person_1'   => $request->get('contact_person_1') ?: null,
            'contact_person_2'   => $request->get('contact_person_2') ?: null,
            'updated_at'         => now(),
        ]);

        $submittedIds = [];
        $order = 0;
        foreach ($request->input('phones', []) as $row) {
            $number = trim($row['number'] ?? '');
            if ($number === '') {
                continue;
            }
            $note = trim($row['note'] ?? '') ?: null;
            $phoneId = $row['id'] ?? null;

            if ($phoneId && DB::table('group_label_phones')->where('phone_id', $phoneId)->where('group_label_id', $id)->exists()) {
                DB::table('group_label_phones')->where('phone_id', $phoneId)->update([
                    'phone_number'   => $number,
                    'contact_note'   => $note,
                    'display_order'  => $order,
                    'updated_at'     => now(),
                ]);
            } else {
                $phoneId = (string) Str::uuid();
                DB::table('group_label_phones')->insert([
                    'phone_id'        => $phoneId,
                    'group_label_id'  => $id,
                    'phone_number'    => $number,
                    'contact_note'    => $note,
                    'display_order'   => $order,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
            $submittedIds[] = $phoneId;
            $order++;
        }
        DB::table('group_label_phones')->where('group_label_id', $id)->whereNotIn('phone_id', $submittedIds ?: ['__none__'])->delete();

        return redirect()->route('admin.masterfile.group-names.edit', $id)->with('contact_saved', true);
    }

    // NEW 15 Sep 2026 — agent search for the Committee/Management Team
    // picker. Deliberately NOT scoped by GL/TL/introducer hierarchy (a
    // committee member can be any active agent in the system) — per
    // Chris: committee members are picked from existing Agent/Member
    // records only, never manually typed.
    public function committeeAgentTypeahead(Request $request, string $id)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $agents = Agent::where('is_deleted', false)
            ->where(function ($query) use ($q) {
                $query->where('full_name', 'like', "%{$q}%")
                    ->orWhere('agent_code', 'like', "%{$q}%");
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get(['agent_id', 'full_name', 'agent_code', 'phone', 'email']);

        return response()->json($agents);
    }

    // NEW 15 Sep 2026 — assigns an existing Agent to a committee/
    // management position for this group. Phone/email are never stored
    // here — always read live from the agents table via the join in
    // edit() above, so an agent's updated contact info is reflected
    // everywhere automatically.
    public function addCommitteeMember(Request $request, string $id)
    {
        $groupLabel = DB::table('group_labels')->where('group_label_id', $id)->first();
        abort_if(! $groupLabel, 404);

        $request->validate([
            'position_type_id'  => ['required', 'uuid', 'exists:cbe_committee_position_types,id'],
            'agent_id'          => ['required', 'uuid', 'exists:agents,agent_id'],
            // NEW 15 Sep 2026 — mandatory term-of-service date range
            // (master spec Section 53, Box 2). term_end_date is
            // optional — left blank means "still serving" (open-ended
            // term) until someone later ends it.
            'term_start_date'   => ['required', 'date'],
            'term_end_date'     => ['nullable', 'date', 'after_or_equal:term_start_date'],
        ]);

        DB::table('group_committee_members')->insert([
            'id'                 => (string) Str::uuid(),
            'group_label_id'     => $id,
            'position_type_id'   => $request->position_type_id,
            'agent_id'           => $request->agent_id,
            'term_start_date'    => $request->term_start_date,
            'term_end_date'      => $request->term_end_date ?: null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return redirect()->route('admin.masterfile.group-names.edit', $id)->with('committee_saved', true);
    }

    // CHANGED 15 Sep 2026 — per the master spec's committee-structure
    // decision: a past assignment is never deleted, only "ended" (so
    // "current term" vs "previous terms" stays a simple date-range
    // query, and history is never lost). This now ENDS the term
    // (sets term_end_date to today) instead of removing the row —
    // only ever called from the Current Term list, since a Previous
    // Term row has no action button at all.
    public function removeCommitteeMember(Request $request, string $id, string $memberId)
    {
        $m = DB::table('group_committee_members')->where('id', $memberId)->where('group_label_id', $id)->first();
        abort_if(! $m, 404);
        DB::table('group_committee_members')->where('id', $m->id)->update(array_merge([
            'term_end_date' => max(now()->toDateString(), (string) $m->term_start_date),
            'updated_at'    => now(),
        ], self::committeeCloseFields('END', $m)));

        return redirect()->route('admin.masterfile.group-names.edit', $id)->with('committee_saved', true);
    }

    // NEW 27 Sep 2026 — how a term was closed (only END can be undone) and
    // the end date it had before, so Undo restores it exactly.
    private static function committeeCloseFields(string $reason, object $m): array
    {
        $out = [];
        if (\Illuminate\Support\Facades\Schema::hasColumn('group_committee_members', 'ended_at')) {
            $out['ended_at'] = now();
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('group_committee_members', 'end_reason')) {
            $out['end_reason'] = $reason;
            $out['original_term_end_date'] = $m->term_end_date;
        }

        return $out;
    }

    // NEW 27 Sep 2026 — per Chris: Undo an End Term clicked by mistake. Only
    // a term closed by the End Term button; the person returns to Current
    // Term with the original start date and original end date.
    public function undoEndCommitteeMember(Request $request, string $id, string $memberId)
    {
        $m = DB::table('group_committee_members')->where('id', $memberId)->where('group_label_id', $id)->first();
        abort_if(! $m || ($m->end_reason ?? null) !== 'END', 404);
        DB::table('group_committee_members')->where('id', $m->id)->update([
            'term_end_date' => $m->original_term_end_date,
            'ended_at' => null,
            'end_reason' => null,
            'original_term_end_date' => null,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.group-names.edit', $id)->with('committee_saved', true);
    }

    // NEW 27 Sep 2026 — per Chris: "Continue New Term" on one row, with the
    // POSITION and TERM START chosen in a small box (pre-filled with the
    // current position and today). The current term closes into Previous
    // Terms (showing the old position) and the new term starts with the
    // chosen position.
    public function continueCommitteeMember(Request $request, string $id, string $memberId)
    {
        $m = DB::table('group_committee_members')->where('id', $memberId)->where('group_label_id', $id)->first();
        abort_if(! $m, 404);
        $request->validate([
            'position_type_id' => ['required', 'uuid', 'exists:cbe_committee_position_types,id'],
            'term_start_date'  => ['required', 'date'],
        ]);
        // the position must be one of THIS group's positions
        abort_if(! DB::table('cbe_committee_position_types')->where('id', $request->position_type_id)->where('group_label_id', $id)->exists(), 422);
        $start = $request->term_start_date;
        $oldEnd = max(\Illuminate\Support\Carbon::parse($start)->subDay()->toDateString(), (string) $m->term_start_date);
        DB::transaction(function () use ($m, $id, $start, $oldEnd, $request) {
            DB::table('group_committee_members')->where('id', $m->id)->update(array_merge([
                'term_end_date' => $oldEnd,
                'updated_at' => now(),
            ], self::committeeCloseFields('CONTINUE', $m)));
            DB::table('group_committee_members')->insert([
                'id' => (string) Str::uuid(), 'group_label_id' => $id, 'position_type_id' => $request->position_type_id,
                'agent_id' => $m->agent_id, 'term_start_date' => $start, 'term_end_date' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.masterfile.group-names.edit', $id)->with('committee_saved', true);
    }

    // NEW 27 Sep 2026 — per Chris: "New Term" for the whole committee. Key
    // the dates once: every current member's term closes into Previous
    // Terms (ending the day before the new start) and a new term starts for
    // the same people in the same positions.
    public function newCommitteeTerm(Request $request, string $id)
    {
        abort_if(! DB::table('group_labels')->where('group_label_id', $id)->exists(), 404);
        $request->validate([
            'new_term_start' => ['required', 'date'],
            'new_term_end'   => ['nullable', 'date', 'after_or_equal:new_term_start'],
        ]);
        $today = now()->toDateString();
        $start = $request->new_term_start;
        $hasEnded = \Illuminate\Support\Facades\Schema::hasColumn('group_committee_members', 'ended_at');
        $current = DB::table('group_committee_members')->where('group_label_id', $id)
            ->where(fn ($q) => $q->whereNull('term_end_date')->orWhere('term_end_date', '>=', $today))
            ->when($hasEnded, fn ($q) => $q->whereNull('ended_at'))
            ->get();
        DB::transaction(function () use ($current, $id, $start, $request, $hasEnded) {
            foreach ($current as $m) {
                $end = \Illuminate\Support\Carbon::parse($start)->subDay()->toDateString();
                DB::table('group_committee_members')->where('id', $m->id)->update(array_merge([
                    'term_end_date' => max($end, (string) $m->term_start_date),
                    'updated_at' => now(),
                ], self::committeeCloseFields('NEWTERM', $m)));
                DB::table('group_committee_members')->insert([
                    'id' => (string) Str::uuid(), 'group_label_id' => $id, 'position_type_id' => $m->position_type_id,
                    'agent_id' => $m->agent_id, 'term_start_date' => $start, 'term_end_date' => $request->new_term_end ?: null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('admin.masterfile.group-names.edit', $id)->with('committee_saved', true);
    }

    public function store(Request $request)
    {
        $request->validate([
            'group_name'                  => ['required', 'string', 'max:200'],
            'description'                 => ['nullable', 'string', 'max:1000'],
            'group_type'                  => ['required', 'in:DSG,ORG,CBE'],
            'promotion_demotion_enabled'  => ['required_unless:group_type,CBE', 'nullable', 'in:0,1'],
            'requires_rank_assignment'    => ['nullable', 'in:0,1'],
            'logo_path'                   => ['nullable', 'string'],
            // NEW 12 Sep 2026 — per Chris: real file upload for the logo
            // (see resolveLogoPath() above), replacing the requirement
            // that he manually place files on the server himself.
            'logo_upload'                 => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,svg', 'max:5120'],
            'logo_upload_cbe'             => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,svg', 'max:5120'],
            // CHANGED 11 Sep 2026 — per Chris: a CBE group must actually
            // have levels (this used to be enforced only client-side by
            // the browser's "required" attribute, which a bypassed/
            // scripted submission could skip entirely).
            'cbe_levels'                  => ['required_if:group_type,CBE', 'nullable', 'string', 'max:1000'],
            // CHANGED 12 Sep 2026 — per Chris: never hardcode a fixed
            // choice list again, AND a community must be able to enable
            // several appointment positions at once, not just one.
            // Validates against the cbe_faith_practice_types catalog
            // (Admin-editable, unlimited entries) instead of a fixed
            // in: list. Real membership in the active catalog is
            // re-checked in syncAppointmentTypes() itself, not just here.
            'appointment_type_ids'        => ['nullable', 'array'],
            'appointment_type_ids.*'      => ['uuid', 'exists:cbe_faith_practice_types,id'],
            'subscription_tier'           => ['nullable', 'in:FREE,PAID'],
            // CHANGED 11 Sep 2026 — a community can now select several
            // tiers, not one. Real membership in the active catalog is
            // re-checked in syncGladeTiers() itself, not just here.
            'glade_tier_ids'              => ['nullable', 'array'],
            'glade_tier_ids.*'            => ['uuid'],
        ]);

        // CBE has no promotion/demotion rank system at all — always
        // stored as disabled, same value ORG already uses. This also
        // means CBE groups automatically get the same "isolated from
        // every other group" behavior GroupIsolationScope already gives
        // any promotion_demotion_enabled=0 group today.
        $isCbe = $request->group_type === 'CBE';
        $promotionDemotionEnabled = $isCbe ? 0 : $request->promotion_demotion_enabled;

        // NEW 11 Sep 2026 — catch a duplicate or absurdly-long level name
        // BEFORE anything is written, not after the group already exists.
        $cbeLevelNames = $isCbe ? $this->parseCbeLevels($request->cbe_levels) : [];
        if ($isCbe && ($levelNameError = $this->validateCbeLevelNames($cbeLevelNames))) {
            return back()->withErrors(['cbe_levels' => $levelNameError])->withInput();
        }

        $id = (string) Str::uuid();

        // Auto-generate a clean, unique, URL-friendly slug (e.g.
        // "PVATM" -> "pvatm") — this is what appears in the public
        // login/join links, so it must be readable and never clash
        // with an existing one.
        $base = Str::slug($request->group_name);
        $slug = $base;
        $i = 1;
        while (DB::table('group_labels')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        DB::table('group_labels')->insert([
            'group_label_id'              => $id,
            'group_name'                  => $request->group_name,
            'group_type'                  => $request->group_type,
            'slug'                        => $slug,
            'description'                 => $request->description,
            'promotion_demotion_enabled'  => $promotionDemotionEnabled,
            'requires_rank_assignment'    => $isCbe ? false : $request->boolean('requires_rank_assignment'),
            'logo_path'                   => $this->resolveLogoPath($request),
            // CHANGED 12 Sep 2026 — faith_practice_type is no longer the
            // source of truth (see group_label_appointment_types below);
            // left at 'NONE' purely as a historical/rollback column,
            // same pattern as the old glade_tier_id columns.
            'faith_practice_type'         => 'NONE',
            // NEW 27 Aug 2026 (Task #233) — subscription_tier previously
            // had no create/edit UI at all, silently sitting on its
            // schema default (FREE) forever.
            'subscription_tier'           => $isCbe ? ($request->subscription_tier ?: 'FREE') : 'FREE',
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        // CHANGED 11 Sep 2026 — GLADE Public Model billing tier(s): see
        // syncGladeTiers() above. glade_tier_id and friends on this table
        // are no longer written to (group_label_glade_tiers is now the
        // single source of truth).
        $this->syncGladeTiers($id, $isCbe ? $request->input('glade_tier_ids', []) : []);

        // CHANGED 12 Sep 2026 — appointment position(s): see
        // syncAppointmentTypes() above.
        $this->syncAppointmentTypes($id, $isCbe ? $request->input('appointment_type_ids', []) : []);

        if ($isCbe) {
            $this->saveCbeLevels($id, $cbeLevelNames);
        }

        return redirect()->route('admin.masterfile.group-names')->with('success', 'Group Name created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'group_name'                  => ['required', 'string', 'max:200'],
            'description'                 => ['nullable', 'string', 'max:1000'],
            'group_type'                  => ['required', 'in:DSG,ORG,CBE'],
            'promotion_demotion_enabled'  => ['required_unless:group_type,CBE', 'nullable', 'in:0,1'],
            'requires_rank_assignment'    => ['nullable', 'in:0,1'],
            'logo_path'                   => ['nullable', 'string'],
            'logo_upload'                 => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,svg', 'max:5120'],
            'logo_upload_cbe'             => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,svg', 'max:5120'],
            'cbe_levels'                  => ['required_if:group_type,CBE', 'nullable', 'string', 'max:1000'],
            // CHANGED 12 Sep 2026 — per Chris: never hardcode a fixed
            // choice list again, AND a community must be able to enable
            // several appointment positions at once, not just one.
            'appointment_type_ids'        => ['nullable', 'array'],
            'appointment_type_ids.*'      => ['uuid', 'exists:cbe_faith_practice_types,id'],
            'subscription_tier'           => ['nullable', 'in:FREE,PAID'],
            'glade_tier_ids'              => ['nullable', 'array'],
            'glade_tier_ids.*'            => ['uuid'],
        ]);

        $isCbe = $request->group_type === 'CBE';
        $promotionDemotionEnabled = $isCbe ? 0 : $request->promotion_demotion_enabled;

        // NEW 11 Sep 2026 — same duplicate/absurd-length check as
        // store(), checked BEFORE any write so a bad edit never
        // half-applies.
        $cbeLevelNames = $isCbe ? $this->parseCbeLevels($request->cbe_levels) : [];
        if ($isCbe && ($levelNameError = $this->validateCbeLevelNames($cbeLevelNames))) {
            return back()->withErrors(['cbe_levels' => $levelNameError])->withInput();
        }

        // NEW 10 Sep 2026 (Task #399) — SAFETY FIX, checked BEFORE any
        // write: switching a CBE group away from CBE would otherwise
        // delete every cbe_hierarchy_levels row for it, which the
        // database blocks the instant a real entity references one of
        // those levels (onDelete('restrict')) — this used to happen
        // AFTER group_labels was already updated, leaving the group
        // half-changed. Block the whole save instead if real entities
        // would be orphaned by the type change.
        $wasCbe = DB::table('group_labels')->where('group_label_id', $id)->value('group_type') === 'CBE';
        if ($wasCbe && ! $isCbe) {
            $entityCount = DB::table('cbe_hierarchy_nodes')->where('group_label_id', $id)->count();
            if ($entityCount > 0) {
                return back()->withErrors(['group_type' => __('cbe_masterfile.err_cannot_change_group_type', ['count' => $entityCount])])->withInput();
            }
        }

        DB::table('group_labels')->where('group_label_id', $id)->update([
            'group_name'                  => $request->group_name,
            'group_type'                  => $request->group_type,
            'description'                 => $request->description,
            'promotion_demotion_enabled'  => $promotionDemotionEnabled,
            'requires_rank_assignment'    => $isCbe ? false : $request->boolean('requires_rank_assignment'),
            'logo_path'                   => $this->resolveLogoPath($request),
            'faith_practice_type'         => 'NONE',
            'subscription_tier'           => $isCbe ? ($request->subscription_tier ?: 'FREE') : 'FREE',
            'updated_at'                  => now(),
        ]);

        // CHANGED 11 Sep 2026 — see syncGladeTiers() above: tiers already
        // linked (pending or active) that are still in the submitted list
        // are left untouched, so re-saving the form after an unrelated
        // edit (like the description) never resets an ACTIVE tier back
        // to pending.
        $this->syncGladeTiers($id, $isCbe ? $request->input('glade_tier_ids', []) : []);

        // CHANGED 12 Sep 2026 — appointment position(s): see
        // syncAppointmentTypes() above.
        $this->syncAppointmentTypes($id, $isCbe ? $request->input('appointment_type_ids', []) : []);

        if ($isCbe) {
            $levelError = $this->saveCbeLevels($id, $cbeLevelNames);
            if ($levelError) {
                // The group's other fields above already saved — only
                // the specific level(s) that are still in use were left
                // untouched instead of being silently deleted. Flash
                // the reason back rather than crash or lose data.
                return redirect()->route('admin.masterfile.group-names.edit', $id)->with('level_warning', $levelError);
            }
        } else {
            // Switched away from CBE (or was never CBE) — no dangling
            // level rows left behind for a non-CBE group, but only if
            // none of them are actually in use by a real entity yet
            // (same protection as saveCbeLevels() above).
            $inUseCount = DB::table('cbe_hierarchy_nodes')->where('group_label_id', $id)->count();
            if ($inUseCount > 0) {
                return redirect()->route('admin.masterfile.group-names.edit', $id)
                    ->with('level_warning', __('cbe_masterfile.err_cannot_change_group_type', ['count' => $inUseCount]));
            }
            DB::table('cbe_hierarchy_levels')->where('group_label_id', $id)->delete();
        }

        // NEW 18 Aug 2026 — group_type drives which baseline role
        // labels this group starts from (Management/Operation/Staff-
        // Affiliate for ORG, vs Group Leader/Team Leader/Introducer
        // otherwise) — RoleLabelService caches group_type forever, so
        // a type change here must bust that specific cache entry too.
        \App\Services\RoleLabelService::forgetGroupType($id);
        \App\Services\RoleLabelService::forgetCache($id);

        return redirect()->route('admin.masterfile.group-names')->with('success', 'Group Name updated successfully.');
    }
}
