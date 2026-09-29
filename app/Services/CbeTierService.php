<?php

// NEW 27 Sep 2026 — per Chris (master spec §96.9): tiers and the upline rule.
//
// Tier of an entity = from its level name: HQ / State / Branch / City /
// Entity (anything else, e.g. Temple, Club).
// A State / Branch / City is a REAL CBE only if it has an address or a
// reference no. (the 13 import-only State codes SGR, JHR … are groupings).
//
// Upline rule (always kept automatically):
//   A Branch points to the real State of its own postcode under the same
//   HQ; if no such State exists, it points to the HQ. When a real State is
//   created later, the Branch moves under it; if that State is no longer
//   real / moved away, the Branch goes back to the HQ. Entities affiliated
//   to the Branch stay affiliated the whole time.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CbeTierService
{
    public static function tierOf(?string $levelName): string
    {
        $n = mb_strtolower((string) $levelName);

        return match (true) {
            (bool) preg_match('/\bhq\b|head\s*quarter|ibu pejabat|总会|總會|总部/u', $n) => 'hq',
            (bool) preg_match('/\bstate\b|negeri|州/u', $n) => 'state',
            (bool) preg_match('/branch|cawangan|分会|分會|支会/u', $n) => 'branch',
            (bool) preg_match('/\bcity\b|bandar|城市|市/u', $n) => 'city',
            default => 'entity',
        };
    }

    public static function isReal(object $node): bool
    {
        return trim((string) ($node->address ?? '')) !== '' || trim((string) ($node->external_reference_no ?? '')) !== '';
    }

    private static function levelName(?string $levelId): ?string
    {
        static $cache = [];
        if (! $levelId) {
            return null;
        }

        return $cache[$levelId] ??= DB::table('cbe_hierarchy_levels')->where('level_id', $levelId)->value('level_name');
    }

    public static function tierOfNode(object $node): string
    {
        return self::tierOf(self::levelName($node->level_id ?? null));
    }

    // State of an entity, from its own postcode (national reference).
    public static function stateOf(object $node): ?string
    {
        $pc = preg_replace('/\D/', '', (string) ($node->postcode ?? ''));
        if ($pc !== '' && Schema::hasTable('postcode_localities')) {
            $s = DB::table('postcode_localities')->where('postcode', $pc)->value('state');
            if ($s) {
                return $s;
            }
        }

        return null;
    }

    // Nearest HQ-tier upline of a node (not the node itself).
    public static function hqOf(object $node): ?object
    {
        $ids = array_values(array_filter(explode('/', (string) $node->hierarchy_path)));
        array_pop($ids); // the node itself
        foreach (array_reverse($ids) as $id) {
            $up = DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->first();
            if ($up && self::tierOfNode($up) === 'hq') {
                return $up;
            }
        }

        return null;
    }

    // Does this State entity cover the given state name?
    private static function stateMatches(object $stateNode, string $state): bool
    {
        $own = self::stateOf($stateNode);
        if ($own) {
            return strcasecmp($own, $state) === 0;
        }
        $bare = preg_replace('/^(wp|w\.p\.|wilayah persekutuan)\s+/i', '', $state);

        return mb_stripos((string) $stateNode->node_name, $bare) !== false
            || mb_stripos((string) ($stateNode->node_name_zh ?? ''), $bare) !== false;
    }

    // Apply the upline rule to one Branch. Returns 'state', 'hq' or null
    // (not a Branch / no HQ above it / nothing changed).
    public static function applyUpline(string $nodeId): ?string
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if (! $node || self::tierOfNode($node) !== 'branch') {
            return null;
        }
        $hq = self::hqOf($node);
        if (! $hq) {
            return null; // stand-alone Branch — the user has not linked it to an HQ
        }

        // Only re-point a Branch that currently sits under the HQ or a State
        // (never override a Branch the user put under something else).
        $parent = $node->parent_node_id ? DB::table('cbe_hierarchy_nodes')->where('node_id', $node->parent_node_id)->first() : null;
        if (! $parent || ! in_array(self::tierOfNode($parent), ['hq', 'state'], true)) {
            return null;
        }

        $target = $hq;
        $state = self::stateOf($node);
        if ($state) {
            $states = DB::table('cbe_hierarchy_nodes')
                ->where('hierarchy_path', 'like', $hq->hierarchy_path.'%')
                ->where('node_id', '!=', $hq->node_id)
                ->where('hierarchy_path', 'not like', $node->hierarchy_path.'%')
                ->get();
            foreach ($states as $s) {
                if (self::tierOfNode($s) === 'state' && self::isReal($s) && self::stateMatches($s, $state)) {
                    $target = $s;
                    break;
                }
            }
        }

        if ($node->parent_node_id === $target->node_id) {
            return null;
        }
        CbeHierarchyLinkService::setParent($node->node_id, $target->node_id);

        return $target->node_id === $hq->node_id ? 'hq' : 'state';
    }

    // Re-apply the rule to every Branch under an HQ (call after any State
    // or Branch in that family is saved).
    public static function refreshFamily(string $hqId): int
    {
        $hq = DB::table('cbe_hierarchy_nodes')->where('node_id', $hqId)->first();
        if (! $hq) {
            return 0;
        }
        $changed = 0;
        $nodes = DB::table('cbe_hierarchy_nodes')->where('hierarchy_path', 'like', $hq->hierarchy_path.'%')->get(['node_id', 'level_id']);
        foreach ($nodes as $n) {
            if (self::tierOfNode($n) === 'branch' && self::applyUpline($n->node_id)) {
                $changed++;
            }
        }

        return $changed;
    }

    // After an entity is saved: refresh the HQ family it belongs to.
    public static function afterSave(string $nodeId): void
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if (! $node) {
            return;
        }
        $hq = self::tierOfNode($node) === 'hq' ? $node : self::hqOf($node);
        if ($hq) {
            self::refreshFamily($hq->node_id);
        }
    }

    public static function refreshAll(): int
    {
        $changed = 0;
        foreach (DB::table('cbe_hierarchy_nodes')->get(['node_id', 'level_id']) as $n) {
            if (self::tierOfNode($n) === 'hq') {
                $changed += self::refreshFamily($n->node_id);
            }
        }

        return $changed;
    }

    // NEW 27 Sep 2026 — per Chris: the entities that belong to a CBE (the
    // Entities / Branches tab list and its count). Same set as the CBE KPI
    // Dashboard Box 4 "Total Affiliate Entity":
    //   own   = this CBE's own entities under its top entity
    //   up    = another CBE's entity linked under it (e.g. Cawangan Klang
    //           under 马来西亚道教总会)
    //   aff   = entities affiliated to it (e.g. the 137 temples affiliated
    //           to Cawangan Klang)
    // The top entity itself and import-only groupings (a State / Branch /
    // City with no address and no reference no.) are not listed.
    // Returns [node_id => kind].
    public static function groupEntityKinds(string $groupId): array
    {
        $levels = DB::table('cbe_hierarchy_levels')->where('group_label_id', $groupId)->get(['level_id', 'level_order']);
        if ($levels->isEmpty()) {
            return [];
        }
        $topLevelIds = $levels->where('level_order', $levels->min('level_order'))->pluck('level_id')->all();
        $tops = DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupId)->whereIn('level_id', $topLevelIds)->get(['node_id', 'hierarchy_path']);
        $topIds = $tops->pluck('node_id')->flip()->all();

        $kinds = [];
        foreach ($tops as $t) {
            if (! $t->hierarchy_path) {
                continue;
            }
            foreach (DB::table('cbe_hierarchy_nodes')->where('hierarchy_path', 'like', $t->hierarchy_path.'%')->get(['node_id', 'group_label_id']) as $d) {
                if (isset($topIds[$d->node_id])) {
                    continue;
                }
                $kinds[$d->node_id] = $d->group_label_id === $groupId ? 'own' : 'up';
            }
        }
        $all = array_merge(array_keys($kinds), array_keys($topIds));
        if ($all && Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id')) {
            foreach (array_chunk($all, 1000) as $chunk) {
                foreach (DB::table('cbe_hierarchy_nodes')->whereIn('affiliated_node_id', $chunk)->pluck('node_id') as $a) {
                    if (! isset($kinds[$a]) && ! isset($topIds[$a])) {
                        $kinds[$a] = 'aff';
                    }
                }
            }
        }

        // drop import-only groupings
        foreach (array_chunk(array_keys($kinds), 1000) as $chunk) {
            foreach (DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $chunk)->get(['node_id', 'level_id', 'address', 'external_reference_no']) as $n) {
                if (self::tierOfNode($n) !== 'entity' && self::tierOfNode($n) !== 'hq' && ! self::isReal($n)) {
                    unset($kinds[$n->node_id]);
                }
                if (self::tierOfNode($n) === 'hq') {
                    unset($kinds[$n->node_id]);
                }
            }
        }

        return $kinds;
    }

    // ------------------------------------------------------------------
    // NEW 27 Sep 2026 — per Chris: Affiliate Group tab — automatic
    // identification + auto-save.
    //
    // Anchors = a CBE's own top entities that are linked under ANOTHER
    // CBE's HQ (e.g. Persekutuan … Cawangan Bandar Di Raja Klang under
    // 马来西亚道教总会). For each anchor, the HQ's bottom-level entities
    // (temples) in the anchor's own district (anchor postcode -> district,
    // national reference; entity matched by postcode OR town) are its
    // district candidates. Unaffiliated candidates that the user has not
    // unticked before are affiliated automatically.
    // ------------------------------------------------------------------
    public static function anchors(string $groupId): array
    {
        $levels = DB::table('cbe_hierarchy_levels')->where('group_label_id', $groupId)->get(['level_id', 'level_order']);
        if ($levels->isEmpty()) {
            return [];
        }
        $topLevelIds = $levels->where('level_order', $levels->min('level_order'))->pluck('level_id')->all();
        $out = [];
        foreach (DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupId)->whereIn('level_id', $topLevelIds)->get() as $top) {
            $hq = self::hqOf($top);
            if ($hq && $hq->group_label_id !== $groupId) {
                $out[] = [$top, $hq];
            }
        }

        return $out;
    }

    public static function districtCandidates(object $anchor, object $hq)
    {
        if (! Schema::hasColumn('postcode_localities', 'district') || ! Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id')) {
            return null;
        }
        $pc = preg_replace('/\D/', '', (string) $anchor->postcode);
        $ref = $pc !== '' ? DB::table('postcode_localities')->where('postcode', $pc)->whereNotNull('district')->first(['district', 'state']) : null;
        if (! $ref) {
            return null;
        }
        $d = DB::table('postcode_localities')->where('district', $ref->district)->where('state', $ref->state);
        $postcodes = (clone $d)->distinct()->pluck('postcode')->all();
        $towns = (clone $d)->distinct()->pluck('post_office')->all();
        $bottom = DB::table('cbe_hierarchy_levels')->where('group_label_id', $hq->group_label_id)->orderByDesc('level_order')->value('level_id');

        return DB::table('cbe_hierarchy_nodes as n')
            ->where('n.group_label_id', $hq->group_label_id)
            ->where('n.level_id', $bottom)
            ->where('n.hierarchy_path', 'like', $hq->hierarchy_path.'%')
            ->where(fn ($w) => $w->whereIn('n.postcode', $postcodes ?: ['#none#'])->orWhereIn('n.city', $towns ?: ['#none#']));
    }

    // Auto-affiliate; returns the ids newly affiliated now.
    public static function autoAffiliate(string $groupId): array
    {
        $new = [];
        $hasEx = Schema::hasTable('cbe_affiliation_exclusions');
        foreach (self::anchors($groupId) as [$anchor, $hq]) {
            $q = self::districtCandidates($anchor, $hq);
            if (! $q) {
                continue;
            }
            $ids = (clone $q)->whereNull('n.affiliated_node_id')
                ->when($hasEx, fn ($w) => $w->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_affiliation_exclusions as e')
                    ->whereColumn('e.node_id', 'n.node_id')->where('e.anchor_node_id', $anchor->node_id)))
                ->pluck('n.node_id')->all();
            if ($ids) {
                DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $ids)->whereNull('affiliated_node_id')
                    ->update(['affiliated_node_id' => $anchor->node_id, 'updated_at' => now()]);
                $new = array_merge($new, $ids);
            }
        }

        return $new;
    }
}
