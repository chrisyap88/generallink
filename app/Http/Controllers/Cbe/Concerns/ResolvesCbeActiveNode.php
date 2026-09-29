<?php

namespace App\Http\Controllers\Cbe\Concerns;

use Illuminate\Support\Facades\DB;

// NEW 28 Aug 2026 — per Chris: "develop all the program, all the program
// that label with the word soon." Meeting Minutes, Activities, Bank
// Statement, Annual Report, Accounting, Events, and Donor Register were
// all hardcoded to $agent->cbe_node_id — a column that is only ever
// populated for an OFFICER (one CBE community per account). Admin has no
// single community, so agent->cbe_node_id is null for every Admin login
// and these 7 screens showed as a locked "soon" placeholder.
//
// This trait gives every one of those controllers a second way to know
// "which node": an Admin who has picked a CBE Group + entity via the
// shared picker (resources/views/admin/cbe-kpi/node-picker.blade.php,
// same screen already used by Entity/Donor/Member Maintenance) has that
// choice remembered in the session for the rest of their visit to these
// screens, so create/store/download actions on OTHER pages of the same
// controller keep working without needing ?node= threaded through every
// form. An officer's own agents.cbe_node_id always wins when present —
// this trait never lets an officer accidentally act on a different
// community's data.
trait ResolvesCbeActiveNode
{
    protected function resolveCbeNodeId($agent): ?string
    {
        if ($agent && $agent->cbe_node_id) {
            return $agent->cbe_node_id;
        }

        if (request()->boolean('reset_node')) {
            session()->forget('cbe_admin_active_node');
        }

        if (request()->filled('node')) {
            session(['cbe_admin_active_node' => request()->get('node')]);

            return request()->get('node');
        }

        return session('cbe_admin_active_node');
    }

    // NEW 17 Sep 2026 — per Chris: Notice Board and Events Calendar are
    // supposed to be readable by "every member", but resolveCbeNodeId()
    // above only ever resolves a node for an OFFICER (agents.cbe_node_id)
    // or Admin (session picker) — an ordinary member has neither, so they
    // fell through to "no entity selected" on both screens, a real gap.
    // A member's own node lives in cbe_group_memberships instead, keyed
    // by their PRIMARY community (agents.group_label_id) — the same
    // "which community" concept every other CBE screen already uses.
    protected function resolveMemberCbeNodeId($agent): ?string
    {
        if (! $agent || ! $agent->group_label_id) {
            return null;
        }

        return DB::table('cbe_group_memberships')
            ->where('agent_id', $agent->agent_id)
            ->where('group_label_id', $agent->group_label_id)
            ->where('status', 'ACTIVE')
            ->value('cbe_node_id');
    }

    // Read-access variant for screens every member (not just officers)
    // should see their own entity's data on — Notice Board, Events
    // Calendar, and the new Entity Messaging/Enquiries screens. Officer
    // and Admin behavior is unchanged (resolveCbeNodeId() still wins when
    // it has an answer); this only adds the member fallback that used to
    // return null.
    protected function resolveCbeNodeIdForMember($agent): ?string
    {
        $nodeId = $this->resolveCbeNodeId($agent);
        if ($nodeId) {
            return $nodeId;
        }

        if ($agent && $agent->role !== 'ADMIN') {
            return $this->resolveMemberCbeNodeId($agent);
        }

        return null;
    }

    // Shared group→entity picker, same screen already used by Entity/
    // Donor/Member Maintenance (resources/views/admin/cbe-kpi/node-picker
    // .blade.php) — one view, reused by every CBE controller so Admin
    // gets one consistent picking experience app-wide instead of 7
    // slightly different ones.
    //
    // UPDATED 28 Aug 2026 — per Chris: "why display temple? no need to
    // display just display the persatuan name and number of minutes at
    // right end" + "i should have similar filter parameter like cbe kpi?
    // else how I know where to select?" Two new optional params:
    //   $leafOnly — when true, only the deepest real entity level within
    //     the group is listed (never HQ/State/Branch groupings, which
    //     never hold their own minutes/members/donors/etc. — only the
    //     entity itself does), so the level tag becomes redundant and is
    //     dropped from the view.
    //   $countResolver — an optional closure(array $nodeIds): array
    //     returning [node_id => count]. When given, each row shows that
    //     count at the right end instead of a bare ›, e.g. "(2)" meaning
    //     2 records already exist for that entity — same number you'll
    //     see after drilling in, since it's computed from the identical
    //     table/column the destination screen itself queries.
    // The view also gets a type-to-search box instead of a plain
    // scrollable list — nothing renders until you type, exactly like the
    // Donors/Members/Customers search screens already work, so this
    // never needs a scrollbar even for a group with hundreds of entities.
    protected function renderCbeNodePicker(string $pickerRoute, string $pickerTitle, bool $leafOnly = false, ?\Closure $countResolver = null)
    {
        $groupId = request()->get('group');

        $groups = DB::table('group_labels')
            ->where('group_type', 'CBE')
            ->orderBy('group_name')
            ->get(['group_label_id', 'group_name']);

        $group = null;
        $nodes = collect();

        if ($groupId) {
            $group = DB::table('group_labels')
                ->where('group_label_id', $groupId)
                ->where('group_type', 'CBE')
                ->first();

            if ($group) {
                $query = DB::table('cbe_hierarchy_nodes as n')
                    ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                    ->where('n.group_label_id', $groupId);

                if ($leafOnly) {
                    $leafOrder = DB::table('cbe_hierarchy_levels')
                        ->where('group_label_id', $groupId)
                        ->max('level_order');
                    $query->where('l.level_order', $leafOrder);
                }

                $nodes = $query->orderBy('l.level_order')
                    ->orderBy('n.node_name')
                    ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'n.city', 'l.level_name')
                    ->get();

                if ($countResolver && $nodes->isNotEmpty()) {
                    $counts = $countResolver($nodes->pluck('node_id')->all());
                    $nodes = $nodes->map(function ($n) use ($counts) {
                        $n->record_count = $counts[$n->node_id] ?? 0;

                        return $n;
                    });
                }
            }
        }

        return view('admin.cbe-kpi.node-picker', [
            'groups' => $groups,
            'group' => $group,
            'nodes' => $nodes,
            'pickerRoute' => $pickerRoute,
            'pickerTitle' => $pickerTitle,
            'leafOnly' => $leafOnly,
            'showCounts' => (bool) $countResolver,
        ]);
    }
}
