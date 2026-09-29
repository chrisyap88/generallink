<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 12 Sep 2026 — per Chris: "why you dont design your own group link id
// rather the user select, if select wrong, will be big problem. you must
// propose to me how to link back the relationship." This replaces the old
// manual "Parent" dropdown (AdminCbeHierarchyNodeController's create/store
// and linkToGroup/storeLinkToGroup) with automatic parent resolution, using
// data the community already enters for its own address purposes — this
// node's own `postcode`, matched against the `coverage_postcode_start`/
// `coverage_postcode_end` range already declared on higher-level nodes
// (added 11 Sep 2026 for exactly this purpose, but never actually wired
// into the linking decision until now).
//
// How it decides a parent, in one pass:
//   1. Look at levels ABOVE this node's own level, nearest first.
//   2. At each level, find nodes whose coverage range contains this node's
//      postcode. A level with only ONE node and no coverage range set
//      (typical for HQ, or a State that hasn't subdivided by postcode)
//      matches automatically — there is nothing to disambiguate.
//   3. The first level above with exactly one matching node becomes the
//      parent. A level with candidates but no postcode match is skipped
//      (try the next level up) — this is what lets Temple A skip a
//      missing Branch/City and land straight under State or HQ.
//   4. Multiple equally-valid candidates at the same level (overlapping
//      coverage ranges — an admin data mistake) are left UNRESOLVED
//      rather than guessed at; the node is saved with no parent yet and
//      flagged so it isn't silently mis-linked.
//   5. No matching node anywhere above = left unlinked (an orphan
//      awaiting its parent), exactly like a brand new HQ has no parent.
//      relinkOrphans() is what fixes these up automatically the moment a
//      qualifying parent is created later, in any order, at any time.
//
// Nothing here ever asks the user to pick a specific node — the only
// inputs are the level (already locked by the screen) and the postcode
// (a plain address fact), so there is no "wrong click" for a wrong parent
// to come from.
class CbeHierarchyLinkService
{
    // Returns ['status' => 'linked', 'parent_node_id' => ..|null] or
    // ['status' => 'ambiguous', 'candidates' => [...]]. A null
    // parent_node_id with status 'linked' means "no parent found yet —
    // orphan, will self-heal later via relinkOrphans()".
    public static function resolveParent(string $groupLabelId, string $levelId, ?string $postcode): array
    {
        $levels = DB::table('cbe_hierarchy_levels')
            ->where('group_label_id', $groupLabelId)
            ->orderBy('level_order')
            ->get(['level_id', 'level_order']);

        $thisLevel = $levels->firstWhere('level_id', $levelId);
        if (! $thisLevel) {
            return ['status' => 'linked', 'parent_node_id' => null];
        }

        $levelsAbove = $levels->where('level_order', '<', $thisLevel->level_order)
            ->sortByDesc('level_order')
            ->values();

        $postcodeNum = self::toPostcodeNumber($postcode);

        foreach ($levelsAbove as $lv) {
            $nodesAtLevel = DB::table('cbe_hierarchy_nodes')
                ->where('group_label_id', $groupLabelId)
                ->where('level_id', $lv->level_id)
                ->get(['node_id', 'coverage_postcode_start', 'coverage_postcode_end']);

            if ($nodesAtLevel->isEmpty()) {
                continue; // nothing at this level yet — try the level above it
            }

            // A level with exactly one entity and no coverage range set is
            // never ambiguous — there's only ever one possible parent here
            // regardless of postcode (e.g. a single HQ, or a State that
            // doesn't subdivide by postcode at all).
            if ($nodesAtLevel->count() === 1 && ! $nodesAtLevel->first()->coverage_postcode_start) {
                return ['status' => 'linked', 'parent_node_id' => $nodesAtLevel->first()->node_id];
            }

            if ($postcodeNum === null) {
                // Several candidates exist at this level but we have no
                // postcode to disambiguate with — don't guess.
                continue;
            }

            // NEW 26 Sep 2026 — a node whose coverage was narrowed on the
            // postcode selection screen (unticked postcodes) has its exact
            // ticked list in cbe_node_coverage_postcodes; match on that
            // list. A node with no rows there keeps the plain From/To range.
            $selectedByNode = \Illuminate\Support\Facades\Schema::hasTable('cbe_node_coverage_postcodes')
                ? DB::table('cbe_node_coverage_postcodes')
                    ->whereIn('node_id', $nodesAtLevel->pluck('node_id'))
                    ->get(['node_id', 'postcode'])
                    ->groupBy('node_id')
                    ->map(fn ($rows) => $rows->map(fn ($r) => (int) $r->postcode)->unique()->all())
                : collect();

            $matches = $nodesAtLevel->filter(function ($n) use ($postcodeNum, $selectedByNode) {
                if (! $n->coverage_postcode_start || ! $n->coverage_postcode_end) {
                    return false;
                }
                if ($selectedByNode->has($n->node_id)) {
                    return in_array($postcodeNum, $selectedByNode[$n->node_id], true);
                }
                return $postcodeNum >= (int) $n->coverage_postcode_start && $postcodeNum <= (int) $n->coverage_postcode_end;
            })->values();

            if ($matches->count() === 1) {
                return ['status' => 'linked', 'parent_node_id' => $matches->first()->node_id];
            }

            if ($matches->count() > 1) {
                return ['status' => 'ambiguous', 'candidates' => $matches->pluck('node_id')->all()];
            }

            // No match at this level — keep going up (lets a node skip a
            // level that doesn't exist yet, or whose coverage doesn't
            // reach this postcode).
        }

        return ['status' => 'linked', 'parent_node_id' => null];
    }

    // Call after a node is created/updated (new node itself, or its
    // postcode/coverage range changed). Finds every currently-unlinked
    // node in the same group that NOW resolves to a real parent and links
    // it — this is what makes "created before its parent existed" fix
    // itself the moment the parent shows up, with no manual step.
    public static function relinkOrphans(string $groupLabelId): int
    {
        $levels = DB::table('cbe_hierarchy_levels')
            ->where('group_label_id', $groupLabelId)
            ->orderBy('level_order')
            ->pluck('level_order', 'level_id');

        $topLevelOrder = $levels->min();

        $orphans = DB::table('cbe_hierarchy_nodes')
            ->where('group_label_id', $groupLabelId)
            ->whereNull('parent_node_id')
            // NEW 12 Sep 2026 — per Chris: a community can deliberately
            // decide to stay standalone even though it would otherwise
            // match a parent (link_locked, set only via the Profile tab's
            // explicit toggle — never set automatically). Skip those.
            ->where('link_locked', false)
            ->get(['node_id', 'level_id', 'postcode', 'hierarchy_path']);

        $relinked = 0;

        foreach ($orphans as $orphan) {
            // The single topmost entity (HQ) never gets a parent — it IS
            // the root — so leave level_order === topLevelOrder alone.
            if (($levels[$orphan->level_id] ?? null) === $topLevelOrder) {
                continue;
            }

            $result = self::resolveParent($groupLabelId, $orphan->level_id, $orphan->postcode);
            if ($result['status'] !== 'linked' || ! $result['parent_node_id']) {
                continue;
            }

            self::reparent($groupLabelId, $orphan->node_id, $result['parent_node_id']);
            $relinked++;
        }

        return $relinked;
    }

    // Re-points one node (and cascades hierarchy_path to every one of its
    // own descendants) — same materialized-path rewrite already used by
    // the Insert/Restructure screen, reused here so relinking behaves
    // identically to a manual restructure.
    public static function reparent(string $groupLabelId, string $nodeId, string $newParentId): void
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->where('group_label_id', $groupLabelId)->first();
        $parent = DB::table('cbe_hierarchy_nodes')->where('node_id', $newParentId)->where('group_label_id', $groupLabelId)->first();
        if (! $node || ! $parent) {
            return;
        }

        $oldPrefix = $node->hierarchy_path;
        $newPrefix = ($parent->hierarchy_path ?? '/').$nodeId.'/';

        $descendants = DB::table('cbe_hierarchy_nodes')
            ->where('group_label_id', $groupLabelId)
            ->where('hierarchy_path', 'like', $oldPrefix.'%')
            ->get(['node_id', 'hierarchy_path']);

        foreach ($descendants as $d) {
            $rest = substr($d->hierarchy_path, strlen($oldPrefix));
            DB::table('cbe_hierarchy_nodes')->where('node_id', $d->node_id)->update([
                'hierarchy_path' => $newPrefix.$rest,
                'updated_at' => now(),
            ]);
        }

        DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->update([
            'parent_node_id' => $newParentId,
            'updated_at' => now(),
        ]);
    }

    // NEW 12 Sep 2026 — per Chris: an admin/officer can deliberately
    // detach an entity from its current parent and flag it to STAY that
    // way — e.g. Temple A's management decides not to formally join TAO
    // Association even though its postcode would otherwise match. Makes
    // this node (and only this node — its own children, if any, keep
    // reporting to it exactly as before) a standalone root, and sets
    // link_locked so relinkOrphans() leaves it alone from now on, even
    // after a new matching parent is created later.
    public static function makeStandalone(string $groupLabelId, string $nodeId): void
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->where('group_label_id', $groupLabelId)->first();
        if (! $node || ! $node->parent_node_id) {
            // Already has no parent — just make sure the lock is set.
            DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->update([
                'link_locked' => true,
                'updated_at' => now(),
            ]);
            return;
        }

        $oldPrefix = $node->hierarchy_path;
        $newPrefix = '/'.$nodeId.'/';

        $descendants = DB::table('cbe_hierarchy_nodes')
            ->where('group_label_id', $groupLabelId)
            ->where('hierarchy_path', 'like', $oldPrefix.'%')
            ->get(['node_id', 'hierarchy_path']);

        foreach ($descendants as $d) {
            $rest = substr($d->hierarchy_path, strlen($oldPrefix));
            DB::table('cbe_hierarchy_nodes')->where('node_id', $d->node_id)->update([
                'hierarchy_path' => $newPrefix.$rest,
                'updated_at' => now(),
            ]);
        }

        DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->update([
            'parent_node_id' => null,
            'link_locked' => true,
            'updated_at' => now(),
        ]);
    }

    // Reverses makeStandalone() — clears the lock and immediately tries
    // to auto-link this one node (rather than waiting for the next time
    // any node in the group is created/edited).
    public static function reenableAutoLink(string $groupLabelId, string $nodeId): void
    {
        DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->where('group_label_id', $groupLabelId)->update([
            'link_locked' => false,
            'updated_at' => now(),
        ]);

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if (! $node || $node->parent_node_id) {
            return;
        }

        $result = self::resolveParent($groupLabelId, $node->level_id, $node->postcode);
        if ($result['status'] === 'linked' && $result['parent_node_id']) {
            self::reparent($groupLabelId, $nodeId, $result['parent_node_id']);
        }
    }

    // NEW 27 Sep 2026 — per Chris: the upline (parent) is ALWAYS chosen by
    // the user ("Affiliated To (Parent)"), and may be an entity of ANOTHER
    // CBE group (e.g. Persekutuan ... Cawangan Klang -> 马来西亚道教总会 HQ).
    // null = stand-alone / HQ pending affiliation. Rewrites this node's
    // hierarchy_path and every descendant's. Returns false if the chosen
    // parent is the node itself or one of its own descendants (a loop).
    public static function setParent(string $nodeId, ?string $parentId): bool
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if (! $node) {
            return false;
        }
        $parent = $parentId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $parentId)->first() : null;
        if ($parentId && ! $parent) {
            return false;
        }
        if ($parent && ($parent->node_id === $node->node_id || str_starts_with((string) $parent->hierarchy_path, (string) $node->hierarchy_path))) {
            return false;
        }

        $oldPrefix = (string) $node->hierarchy_path;
        $newPrefix = ($parent ? $parent->hierarchy_path : '/').$nodeId.'/';

        if ($oldPrefix !== '' && $oldPrefix !== $newPrefix) {
            $descendants = DB::table('cbe_hierarchy_nodes')
                ->where('hierarchy_path', 'like', $oldPrefix.'%')
                ->where('node_id', '!=', $nodeId)
                ->get(['node_id', 'hierarchy_path']);
            foreach ($descendants as $d) {
                DB::table('cbe_hierarchy_nodes')->where('node_id', $d->node_id)->update([
                    'hierarchy_path' => $newPrefix.substr($d->hierarchy_path, strlen($oldPrefix)),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->update([
            'parent_node_id' => $parent->node_id ?? null,
            'hierarchy_path' => $newPrefix,
            // Chosen by hand -> never auto-relinked by postcode again.
            'link_locked' => true,
            'updated_at' => now(),
        ]);

        return true;
    }

    private static function toPostcodeNumber(?string $postcode): ?int
    {
        $digits = preg_replace('/\D/', '', (string) $postcode);
        return $digits !== '' ? (int) $digits : null;
    }
}
