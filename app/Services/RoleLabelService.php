<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 23 Jul 2026 — per Chris: lets Admin rename the 3 system role
// labels (Group Leader / Team Leader / Introducer) for this
// deployment, e.g. to "Agency Management" / "Unit Manager" / "Agents"
// for an insurance company. This service is the ONLY place that reads
// role_label_overrides — every screen that wants the current label
// for a role should call RoleLabelService::label($role) instead of
// hardcoding the text, so a rename here immediately shows up
// everywhere that's been wired to it.
//
// IMPORTANT — scope of this first pass (23 Jul 2026): only the
// sidebar role badge and the 3 "Group/Team Leader/Introducer
// Maintenance" menu items under Master File Maintenance were wired to
// this on day one. The literal words "Group Leader" / "Team Leader" /
// "Introducer" still appear hardcoded in many other screens (dashboard
// titles, dropdowns, reports, badges) — those need to be updated one
// screen at a time in follow-up passes, prioritized by visibility, so
// each one can be checked for truncation before going live.
//
// RESCOPED 31 Jul 2026 — this had the exact same group_id-vs-
// group_label_id flaw as role_ranks (fixed 31 Jul 2026 too, see
// RoleRankController): overrides were scoped to groups.group_id (one
// individual GL's own personal team), so two GLs under the SAME
// Organization Rewards Group (e.g. both under PVATM) could silently see
// two different label sets, and there was no way to set "PVATM's"
// labels as a single shared thing at all. Every method below now takes
// an optional $groupLabelId instead of a groups.group_id. Leave it
// null (the default everywhere this is already called) and it
// auto-resolves to the CURRENTLY LOGGED-IN agent's own
// group_labels.group_label_id — so existing call sites don't need to
// change, they just automatically start showing the right group's
// labels. Admin (no group_label) always sees the system default. Pass
// an explicit $groupLabelId only when you need a SPECIFIC group's
// labels regardless of who's logged in (e.g. Organization Category
// Maintenance and the Rank Hierarchy/Rank Allocation screens, when
// Admin picks a group to view/edit).
class RoleLabelService
{
    private const DEFAULTS = [
        'GROUP_LEADER' => 'Group Leader',
        'TEAM_LEADER'  => 'Team Leader',
        'INTRODUCER'   => 'Introducer',
    ];

    // NEW 18 Aug 2026 — per Chris: an Organization Rewards Group (ORG) is
    // a company structure, not an MLM downline, so "Group Leader" etc.
    // never fit as the STARTING point for a brand-new ORG group. This is
    // now the baseline every ORG-type group_labels row starts from —
    // before Admin has ever touched Organization Category Maintenance
    // for it — instead of the DSG wording above. Still just labels; the
    // underlying `role` enum (GROUP_LEADER/TEAM_LEADER/INTRODUCER) and
    // all permissions/commission logic are completely untouched.
    private const ORG_DEFAULTS = [
        'GROUP_LEADER' => 'Management',
        'TEAM_LEADER'  => 'Operation',
        'INTRODUCER'   => 'Staff/Affiliate',
    ];

    private const CACHE_KEY = 'role_label_overrides_v2';
    private const SHORT_CACHE_KEY = 'role_short_label_overrides_v2';
    private const KNOWN_GROUPS_KEY = 'role_label_known_grouplabels_v2';
    private const GROUP_TYPE_CACHE_KEY = 'role_label_group_type_v1';

    // Which baseline (DSG's generic words, or ORG's Management/
    // Operation/Staff-Affiliate) a group starts from before any of its
    // own override rows are layered on top. DSG and CBE (and Admin,
    // with no group at all) use the original DEFAULTS; ORG uses its
    // own baseline and — see all() below — does NOT also inherit
    // System Default's NULL-scoped rows, since those represent DSG's
    // naming, not ORG's.
    private static function baseDefaultsFor(?string $groupLabelId): array
    {
        return ($groupLabelId && self::groupType($groupLabelId) === 'ORG') ? self::ORG_DEFAULTS : self::DEFAULTS;
    }

    private static function groupType(?string $groupLabelId): ?string
    {
        if (! $groupLabelId) {
            return null;
        }
        return Cache::rememberForever(self::GROUP_TYPE_CACHE_KEY . ':' . $groupLabelId, function () use ($groupLabelId) {
            return DB::table('group_labels')->where('group_label_id', $groupLabelId)->value('group_type');
        });
    }

    public static function label(?string $role, ?string $groupLabelId = null): string
    {
        $labels = self::all($groupLabelId);
        return $labels[$role] ?? self::baseDefaultsFor($groupLabelId)[$role] ?? ucwords(strtolower(str_replace('_', ' ', (string) $role)));
    }

    // Plural form for headings/counts like "12 Team Leaders found" —
    // uses Str::plural so an irregular custom label (e.g. "Agency" ->
    // "Agencies") still comes out right, not just a blind "+s".
    public static function plural(?string $role, ?string $groupLabelId = null): string
    {
        return Str::plural(self::label($role, $groupLabelId));
    }

    // NEW 24 Jul 2026 — per Chris: narrow-space badges (tree charts,
    // drilldown table role tags) can't fit the full label. First cut
    // auto-derived a short tag from label() (initials for multi-word,
    // truncated for one word). Chris then asked to type his OWN short
    // tag instead — so if he's saved one via Organization Category
    // Maintenance, that wins; otherwise this still falls back to the
    // auto-derived form so nothing breaks for a role he hasn't set one
    // for yet.
    public static function shortLabel(?string $role, int $max = 3, ?string $groupLabelId = null): string
    {
        $overrides = self::shortOverrides($groupLabelId);
        if (! empty($overrides[$role])) {
            return $overrides[$role];
        }

        return self::autoShortLabel($role, $max, $groupLabelId);
    }

    // The auto-derived short form, ignoring any manually-typed
    // override — used by the maintenance screen to show Chris what
    // he'll get if he leaves the Short Label box blank.
    public static function autoShortLabel(?string $role, int $max = 3, ?string $groupLabelId = null): string
    {
        $label = trim(self::label($role, $groupLabelId));
        $words = preg_split('/\s+/', $label) ?: [];

        if (count($words) > 1) {
            $initials = '';
            foreach ($words as $word) {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            }
            return mb_substr($initials, 0, $max);
        }

        return mb_strtoupper(mb_substr($label, 0, $max));
    }

    // The group actually looked at when $groupLabelId isn't explicitly
    // passed — whoever is logged in right now. Admin has no
    // group_label_id, so Admin always sees the system default even
    // while viewing a specific group's own screens.
    private static function currentAgentGroupLabelId(): ?string
    {
        if (Auth::guard('agent')->check()) {
            return Auth::guard('agent')->user()->group_label_id;
        }
        return null;
    }

    private static function cacheKeyFor(string $base, ?string $groupLabelId): string
    {
        return $base . ':' . ($groupLabelId ?? 'default');
    }

    // Remembers every group_label_id we've ever cached a lookup for, so
    // forgetCache() (with no group — i.e. the SYSTEM DEFAULT changed) can
    // find and clear every group's cached view too. Every group's merged
    // result includes the default as its base layer, so a default-label
    // change would otherwise leave other groups showing a stale default.
    private static function rememberGroupLabelId(?string $groupLabelId): void
    {
        if (! $groupLabelId) {
            return;
        }
        $known = Cache::rememberForever(self::KNOWN_GROUPS_KEY, fn () => []);
        if (! in_array($groupLabelId, $known, true)) {
            $known[] = $groupLabelId;
            Cache::forever(self::KNOWN_GROUPS_KEY, $known);
        }
    }

    /** @return array<string,string> role => label, for the given (or auto-resolved) group */
    public static function all(?string $groupLabelId = null): array
    {
        $groupLabelId = $groupLabelId ?? self::currentAgentGroupLabelId();
        self::rememberGroupLabelId($groupLabelId);

        return Cache::rememberForever(self::cacheKeyFor(self::CACHE_KEY, $groupLabelId), function () use ($groupLabelId) {
            $isOrg = $groupLabelId && self::groupType($groupLabelId) === 'ORG';

            if ($isOrg) {
                // ORG groups start from Management/Operation/Staff-
                // Affiliate, NOT from System Default's NULL-scoped rows
                // — those represent DSG's own naming and must not leak
                // into an Organization Rewards Group's baseline.
                $merged = self::ORG_DEFAULTS;
            } else {
                $defaultRows = DB::table('role_label_overrides')->whereNull('group_label_id')->pluck('label', 'role')->all();
                $merged = array_merge(self::DEFAULTS, $defaultRows);
            }

            if ($groupLabelId) {
                $groupRows = DB::table('role_label_overrides')->where('group_label_id', $groupLabelId)->pluck('label', 'role')->all();
                $merged = array_merge($merged, $groupRows);
            }

            return $merged;
        });
    }

    /** @return array<string,string> role => short_label (only roles that have one set), for the given (or auto-resolved) group */
    public static function shortOverrides(?string $groupLabelId = null): array
    {
        $groupLabelId = $groupLabelId ?? self::currentAgentGroupLabelId();
        self::rememberGroupLabelId($groupLabelId);

        return Cache::rememberForever(self::cacheKeyFor(self::SHORT_CACHE_KEY, $groupLabelId), function () use ($groupLabelId) {
            $merged = DB::table('role_label_overrides')
                ->whereNull('group_label_id')
                ->whereNotNull('short_label')
                ->where('short_label', '<>', '')
                ->pluck('short_label', 'role')
                ->all();

            if ($groupLabelId) {
                $groupRows = DB::table('role_label_overrides')
                    ->where('group_label_id', $groupLabelId)
                    ->whereNotNull('short_label')
                    ->where('short_label', '<>', '')
                    ->pluck('short_label', 'role')
                    ->all();
                $merged = array_merge($merged, $groupRows);
            }

            return $merged;
        });
    }

    // Optionally pass a group so Organization Category Maintenance's
    // "Original Name" column shows the CORRECT baseline for that
    // group (Management/Operation/Staff-Affiliate for ORG, Group
    // Leader/Team Leader/Introducer otherwise) — not always the DSG
    // wording regardless of which group is being edited.
    public static function defaults(?string $groupLabelId = null): array
    {
        return self::baseDefaultsFor($groupLabelId);
    }

    // Call this if a group's group_type itself changes (e.g. Admin
    // flips a group between DSG/ORG/CBE on the Edit Group Name screen)
    // — otherwise the cached group_type lookup would keep using the
    // OLD type and show the wrong baseline until the cache naturally
    // expires (it never does — rememberForever) or the server restarts.
    public static function forgetGroupType(string $groupLabelId): void
    {
        Cache::forget(self::GROUP_TYPE_CACHE_KEY . ':' . $groupLabelId);
    }

    // Pass the group whose labels just changed — null means the SYSTEM
    // DEFAULT changed, which clears every known group's cache too (see
    // rememberGroupLabelId() above for why). Pass a specific
    // $groupLabelId when only that one group's own override rows changed.
    public static function forgetCache(?string $groupLabelId = null): void
    {
        Cache::forget(self::cacheKeyFor(self::CACHE_KEY, $groupLabelId));
        Cache::forget(self::cacheKeyFor(self::SHORT_CACHE_KEY, $groupLabelId));

        if ($groupLabelId === null) {
            $known = Cache::get(self::KNOWN_GROUPS_KEY, []);
            foreach ($known as $gid) {
                Cache::forget(self::cacheKeyFor(self::CACHE_KEY, $gid));
                Cache::forget(self::cacheKeyFor(self::SHORT_CACHE_KEY, $gid));
            }
        }
    }
}
