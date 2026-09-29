<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 2 Aug 2026 — per Chris: "Admin, GL, TL and Introducer also have
// similar ledger to keep track their earning income claims." This is
// the regular-agent counterpart to the Override Member Ledger, but it
// does NOT get its own new database table — commission_transactions
// (status=CONFIRMED) and withdrawal_requests (status=PAID) are already
// the real system of record for an agent's earned income and payouts,
// so this ledger is simply a chronological Debit/Credit VIEW over
// those two existing tables, exactly the same accounting idea as the
// Override ledger (Debit = money becomes owed to the agent, Credit =
// money paid out or reversed).
//
// One shared controller for all 4 roles (same pattern already used by
// Shared\WalletController and Shared\CustomerController) — the ONLY
// thing that changes per role is which agent_ids are in scope:
//   ADMIN       -> filter by Group Label / GL / TL / Introducer (any agent)
//   GROUP_LEADER -> himself + his Team Leaders + their Introducers
//   TEAM_LEADER  -> himself + his Introducers
//   INTRODUCER   -> himself + introducers HE recruited (any depth)
// -------------------------------------------------------
class EarningLedgerController extends Controller
{
    public function index(Request $request)
    {
        $me = Auth::guard('agent')->user();
        $role = $me->role;

        $groupLabelId = $request->query('group_label_id', '');
        $glId = $request->query('gl_agent_id', '');
        $tlId = $request->query('tl_agent_id', '');
        $introId = $request->query('introducer_agent_id', '');
        $teamMemberId = $request->query('team_member_id', ''); // GL/TL/Introducer roles only
        $asAtDate = $request->query('as_at_date') ?: now()->format('Y-m-d');

        // -------------------------------------------------------
        // COMPULSORY per Chris (2 Aug 2026): never auto-display any
        // records — Admin, GL, TL and Introducer must ALWAYS pick a
        // real selection first and press Go. This screen must NEVER run
        // its Debit/Credit query on page load with no filter, no matter
        // how small the agent list looks today — commission_transactions
        // is the kind of table that can hold millions of rows, and an
        // unfiltered aggregate over it is exactly the query that can
        // hang the whole system. Below, the roster + Debit/Credit
        // aggregation query is skipped ENTIRELY (not just hidden in the
        // view) until a real filter value is present.
        // -------------------------------------------------------
        $hasFilter = $role === 'ADMIN'
            ? ($groupLabelId !== '' || $glId !== '' || $tlId !== '' || $introId !== '')
            : ($teamMemberId !== '');

        $routePrefix = $this->routePrefix($role);

        // Dropdown option lists are always safe to compute — they only
        // ever query the (workforce-sized) agents table, never the
        // (potentially huge) transaction tables.
        $groupLabels = $role === 'ADMIN' ? DB::table('group_labels')->orderBy('group_name')->get() : collect();

        $glOptions = collect();
        if ($role === 'ADMIN') {
            $glOptions = DB::table('agents')->where('role', 'GROUP_LEADER')->where('is_deleted', false)
                ->when($groupLabelId, fn($q) => $q->where('group_label_id', $groupLabelId))
                ->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code']);
        }

        $tlOptions = collect();
        $introOptions = collect();
        $teamMemberOptions = collect();

        if ($role === 'ADMIN') {
            $scopeForDropdowns = $this->narrowedScopeAgentIds($me, $request);
            $tlOptions = DB::table('agents')->where('role', 'TEAM_LEADER')->where('is_deleted', false)
                ->when($scopeForDropdowns !== null, fn($q) => $q->whereIn('agent_id', $scopeForDropdowns))
                ->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code']);
            $introOptions = DB::table('agents')->where('role', 'INTRODUCER')->where('is_deleted', false)
                ->when($scopeForDropdowns !== null, fn($q) => $q->whereIn('agent_id', $scopeForDropdowns))
                ->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code']);
        } else {
            // One unified "Team Member" picker: Myself + direct team,
            // matching Chris's wording exactly ("selection only for
            // himself and his team leader or introducer" / "himself and
            // his introducer" / "himself and his recruit introducer").
            $teamMemberOptions->push((object) ['agent_id' => 'self', 'full_name' => 'Myself (' . $me->full_name . ')', 'agent_code' => $me->agent_code, 'role_label' => '']);
            if ($role === 'GROUP_LEADER') {
                $tls = DB::table('agents')->where('role', 'TEAM_LEADER')->where('is_deleted', false)
                    ->where('parent_id', $me->agent_id)->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code']);
                foreach ($tls as $t) { $t->role_label = 'TL'; $teamMemberOptions->push($t); }
            }
            $introParentIds = $role === 'GROUP_LEADER'
                ? array_merge([$me->agent_id], $tls->pluck('agent_id')->all())
                : [$me->agent_id];
            $introIds = $this->descendantIntroducerIds($introParentIds);
            if (!empty($introIds)) {
                $intros = DB::table('agents')->whereIn('agent_id', $introIds)->where('is_deleted', false)
                    ->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code']);
                foreach ($intros as $i) { $i->role_label = 'Introducer'; $teamMemberOptions->push($i); }
            }
        }

        if (!$hasFilter) {
            $agents = new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 8, 1, ['path' => $request->url()]);
            return view('earning-ledger.index', compact(
                'agents', 'groupLabels', 'glOptions', 'tlOptions', 'introOptions', 'teamMemberOptions',
                'groupLabelId', 'glId', 'tlId', 'introId', 'teamMemberId', 'asAtDate', 'role', 'routePrefix', 'hasFilter'
            ));
        }

        // Resolve which agent_ids the roster query is allowed to touch.
        if ($role === 'ADMIN') {
            $scopeIds = $this->narrowedScopeAgentIds($me, $request);
        } elseif ($teamMemberId === 'self') {
            $scopeIds = $this->fullScopeAgentIds($me);
        } else {
            // A specific TL or Introducer chosen — security: intersect
            // with the caller's own full scope so URL tampering can
            // never widen visibility.
            $full = $this->fullScopeAgentIds($me);
            $pickedRole = DB::table('agents')->where('agent_id', $teamMemberId)->value('role');
            $picked = $pickedRole === 'TEAM_LEADER'
                ? array_merge([$teamMemberId], $this->descendantIntroducerIds([$teamMemberId]))
                : [$teamMemberId];
            $scopeIds = array_values(array_intersect($picked, $full));
        }

        $asAtEnd = \Carbon\Carbon::parse($asAtDate)->endOfDay();

        // -------------------------------------------------------
        // FIXED 2 Aug 2026 — per Chris: even with a filter chosen (e.g.
        // one whole Group Label — could be thousands of agents), the
        // OLD version aggregated Debit/Credit with a GROUP BY over the
        // ENTIRE commission_transactions / withdrawal_requests tables
        // FIRST, then joined that onto the agents — meaning the full
        // table got scanned every page load no matter how few rows
        // ended up displayed. That's the exact "kills the system with
        // millions of transactions" pattern Chris warned about.
        //
        // Correct 2-step order now: (1) paginate the AGENTS table alone
        // — cheap, bounded by LIMIT 8, no transaction table touched at
        // all — to find out exactly WHICH 8 agent_ids are on this page.
        // (2) Only THEN run the Debit/Credit aggregation, scoped with
        // WHERE agent_id IN (those exact 8 ids) — an indexed lookup
        // that costs the same whether the table has 100 rows or 100
        // million, because it never has to scan or group the whole
        // table, only the rows for 8 specific agents.
        // -------------------------------------------------------
        $agentQuery = DB::table('agents as a')
            ->leftJoin('group_labels as glbl', 'glbl.group_label_id', '=', 'a.group_label_id')
            ->where('a.is_deleted', false)
            ->select('a.agent_id', 'a.agent_code', 'a.full_name', 'a.role', 'glbl.group_name');

        if ($scopeIds !== null) {
            $agentQuery->whereIn('a.agent_id', $scopeIds);
        }

        $agents = $agentQuery
            ->orderByRaw("FIELD(a.role,'ADMIN','GROUP_LEADER','TEAM_LEADER','INTRODUCER')")
            ->orderBy('a.full_name')
            ->paginate(8)->withQueryString();

        $pageAgentIds = $agents->pluck('agent_id')->all();

        if (!empty($pageAgentIds)) {
            $debitByAgent = DB::table('commission_transactions')
                ->where('status', 'CONFIRMED')->where('created_at', '<=', $asAtEnd)
                ->whereIn('agent_id', $pageAgentIds)
                ->groupBy('agent_id')
                ->select('agent_id', DB::raw('SUM(commission_amount) as total'))
                ->pluck('total', 'agent_id');
            $reversedByAgent = DB::table('commission_transactions')
                ->where('status', 'REVERSED')->where('updated_at', '<=', $asAtEnd)
                ->whereIn('agent_id', $pageAgentIds)
                ->groupBy('agent_id')
                ->select('agent_id', DB::raw('SUM(commission_amount) as total'))
                ->pluck('total', 'agent_id');
            $paidByAgent = DB::table('withdrawal_requests')
                ->where('status', 'PAID')->where('paid_date', '<=', $asAtEnd)
                ->whereIn('agent_id', $pageAgentIds)
                ->groupBy('agent_id')
                ->select('agent_id', DB::raw('SUM(withdrawal_amount) as total'))
                ->pluck('total', 'agent_id');

            $agents->getCollection()->transform(function ($a) use ($debitByAgent, $reversedByAgent, $paidByAgent) {
                $debit = (float) ($debitByAgent[$a->agent_id] ?? 0);
                $credit = (float) ($reversedByAgent[$a->agent_id] ?? 0) + (float) ($paidByAgent[$a->agent_id] ?? 0);
                $a->total_debit = $debit;
                $a->total_credit = $credit;
                $a->balance_as_at = $debit - $credit;
                return $a;
            });
        }

        return view('earning-ledger.index', compact(
            'agents', 'groupLabels', 'glOptions', 'tlOptions', 'introOptions', 'teamMemberOptions',
            'groupLabelId', 'glId', 'tlId', 'introId', 'teamMemberId', 'asAtDate', 'role', 'routePrefix', 'hasFilter'
        ));
    }

    public function statement(Request $request)
    {
        $me = Auth::guard('agent')->user();
        $agentId = $request->query('agent_id');
        abort_if(!$agentId, 404);

        // Security: a non-Admin can only ever open a statement for
        // someone inside their OWN full downline, no matter what
        // agent_id is typed into the URL.
        if ($me->role !== 'ADMIN') {
            $fullScope = $this->fullScopeAgentIds($me);
            abort_unless(in_array($agentId, $fullScope), 403);
        }

        $agent = DB::table('agents as a')
            ->leftJoin('group_labels as glbl', 'glbl.group_label_id', '=', 'a.group_label_id')
            ->where('a.agent_id', $agentId)
            ->select('a.*', 'glbl.group_name')
            ->first();
        abort_if(!$agent, 404);

        $asAtDate = $request->query('as_at_date') ?: now()->format('Y-m-d');
        $asAtEnd = \Carbon\Carbon::parse($asAtDate)->endOfDay();

        // NOTE: descriptions are built in PHP, not SQL CONCAT — keeps the
        // "—" dash character safe regardless of MySQL connection charset.
        $debits = DB::table('commission_transactions as c')
            ->leftJoin('sales_transactions as s', 's.policy_id', '=', 'c.policy_id')
            ->where('c.agent_id', $agentId)->where('c.status', 'CONFIRMED')->where('c.created_at', '<=', $asAtEnd)
            ->select('c.created_at as entry_date', 's.policy_number', 'c.commission_amount as amount')
            ->get()
            ->map(function ($r) {
                $r->description = 'Commission earned — Policy ' . ($r->policy_number ?? '—');
                $r->entry_type = 'DEBIT';
                return $r;
            });

        $reversals = DB::table('commission_transactions as c')
            ->leftJoin('sales_transactions as s', 's.policy_id', '=', 'c.policy_id')
            ->where('c.agent_id', $agentId)->where('c.status', 'REVERSED')->where('c.updated_at', '<=', $asAtEnd)
            ->select('c.updated_at as entry_date', 's.policy_number', 'c.commission_amount as amount')
            ->get()
            ->map(function ($r) {
                $r->description = 'Commission reversed — Policy ' . ($r->policy_number ?? '—');
                $r->entry_type = 'CREDIT';
                return $r;
            });

        $payouts = DB::table('withdrawal_requests as wd')
            ->where('wd.agent_id', $agentId)->where('wd.status', 'PAID')->where('wd.paid_date', '<=', $asAtEnd)
            ->select('wd.paid_date as entry_date', 'wd.payment_reference', 'wd.withdrawal_amount as amount')
            ->get()
            ->map(function ($r) {
                $r->description = 'Withdrawal paid' . ($r->payment_reference ? ' — Ref: ' . $r->payment_reference : '');
                $r->entry_type = 'CREDIT';
                return $r;
            });

        $allEntries = $debits->concat($reversals)->concat($payouts)
            ->sortBy('entry_date')->values();

        $running = 0; $totalDebit = 0; $totalCredit = 0;
        $withBalance = $allEntries->map(function ($e) use (&$running, &$totalDebit, &$totalCredit) {
            if ($e->entry_type === 'DEBIT') { $running += (float) $e->amount; $totalDebit += (float) $e->amount; }
            else { $running -= (float) $e->amount; $totalCredit += (float) $e->amount; }
            $e->running_balance = $running;
            return $e;
        });

        // REDUCED 2 Aug 2026 — per Chris: 15 rows overflowed the one-screen
        // no-scroll layout. Further reduced from 10 to 9 the same day to
        // make room for the new Page Sub Total footer row without
        // pushing the table past one screen again.
        $perPage = 9;
        $page = (int) $request->query('page', 1);
        $openingBalance = $page > 1 ? (float) ($withBalance->get(($page - 1) * $perPage - 1)?->running_balance ?? 0) : 0.0;
        $pageItems = $withBalance->forPage($page, $perPage)->values();

        $entries = new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItems, $withBalance->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $routePrefix = $this->routePrefix($me->role);

        return view('earning-ledger.statement', compact(
            'agent', 'entries', 'totalDebit', 'totalCredit', 'openingBalance', 'asAtDate', 'routePrefix'
        ));
    }

    // -------------------------------------------------------
    // Every agent_id reachable by this agent through the parent_id
    // chain, no filters applied — used both to build the role's FULL
    // permitted set (for the 403 check in statement()) and as the base
    // that narrowedScopeAgentIds() below trims further.
    // -------------------------------------------------------
    private function fullScopeAgentIds($me): ?array
    {
        if ($me->role === 'ADMIN') return null; // unrestricted

        if ($me->role === 'GROUP_LEADER') {
            $tlIds = DB::table('agents')->where('role', 'TEAM_LEADER')->where('is_deleted', false)
                ->where('parent_id', $me->agent_id)->pluck('agent_id')->all();
            $introIds = $this->descendantIntroducerIds(array_merge([$me->agent_id], $tlIds));
            return array_merge([$me->agent_id], $tlIds, $introIds);
        }

        if ($me->role === 'TEAM_LEADER') {
            return array_merge([$me->agent_id], $this->descendantIntroducerIds([$me->agent_id]));
        }

        // INTRODUCER — himself + everyone he (or his recruits) recruited, any depth
        return array_merge([$me->agent_id], $this->descendantIntroducerIds([$me->agent_id]));
    }

    // Applies the Group Label / GL / TL / Introducer filter on top of
    // the caller's full scope. Returns null ONLY for an Admin with no
    // filter picked (meaning "every agent"); every other case returns
    // a concrete array — and for non-Admins that array is always
    // intersected with their own full scope, so URL tampering can
    // never widen what they're allowed to see.
    private function narrowedScopeAgentIds($me, Request $request): ?array
    {
        $full = $this->fullScopeAgentIds($me);

        $groupLabelId = $request->query('group_label_id', '');
        $glId = $request->query('gl_agent_id', '');
        $tlId = $request->query('tl_agent_id', '');
        $introId = $request->query('introducer_agent_id', '');

        $narrowed = null;

        if ($introId) {
            $narrowed = [$introId];
        } elseif ($tlId) {
            $narrowed = array_merge([$tlId], $this->descendantIntroducerIds([$tlId]));
        } elseif ($glId) {
            $tlIds = DB::table('agents')->where('role', 'TEAM_LEADER')->where('is_deleted', false)
                ->where('parent_id', $glId)->pluck('agent_id')->all();
            $introIds = $this->descendantIntroducerIds(array_merge([$glId], $tlIds));
            $narrowed = array_merge([$glId], $tlIds, $introIds);
        } elseif ($groupLabelId) {
            $glIds = DB::table('agents')->where('role', 'GROUP_LEADER')->where('is_deleted', false)
                ->where('group_label_id', $groupLabelId)->pluck('agent_id')->all();
            $narrowed = [];
            foreach ($glIds as $gid) {
                $tlIds = DB::table('agents')->where('role', 'TEAM_LEADER')->where('is_deleted', false)
                    ->where('parent_id', $gid)->pluck('agent_id')->all();
                $introIds = $this->descendantIntroducerIds(array_merge([$gid], $tlIds));
                $narrowed = array_merge($narrowed, [$gid], $tlIds, $introIds);
            }
        }

        if ($me->role !== 'ADMIN') {
            $base = $narrowed ?? $full; // $full is always a concrete array for non-Admins
            return array_values(array_intersect($base, $full));
        }

        return $narrowed; // Admin: null = no filter = every agent
    }

    // Introducers can recruit other introducers to any depth — walk the
    // parent_id chain level by level (same technique already used in
    // NetworkController::exportGL()) until a level returns nobody new.
    private function descendantIntroducerIds(array $startParentIds): array
    {
        $collected = [];
        $frontier = $startParentIds;
        $guard = 0;
        while (!empty($frontier) && $guard < 25) {
            $guard++;
            $next = DB::table('agents')->where('role', 'INTRODUCER')->where('is_deleted', false)
                ->whereIn('parent_id', $frontier)->pluck('agent_id')->all();
            $next = array_values(array_diff($next, $collected));
            if (empty($next)) break;
            $collected = array_merge($collected, $next);
            $frontier = $next;
        }
        return $collected;
    }

    private function routePrefix(string $role): string
    {
        return match ($role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }
}
