<?php

// NEW 28 Sep 2026 — per Chris (master spec §96.21–96.22): ONE member file
// for all CBEs. The person (agents row) is unique; affiliations are many.
//
// Affiliation Register = cbe_group_memberships (person ↔ entity link) +
// cbe_member_role_tags (one line per TYPE, with status / from / to / source
// / plan / paid until). Committee positions (Committee tab) and practitioner
// profiles (Practitioner Setup) are shown in the same register, read live
// from their own modules — never copied — so nothing is recorded twice.

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MemberFileService
{
    public const TYPES_OWNED = ['MEMBER', 'FOLLOWER', 'BELIEVER', 'DONOR', 'SPONSOR', 'VOLUNTEER', 'CONSULTANT'];

    public static function normPhone(?string $p): string
    {
        return preg_replace('/\D/', '', (string) $p) ?: '';
    }

    public static function nricHashes(?string $nric): array
    {
        $raw = trim((string) $nric);
        if ($raw === '') {
            return [];
        }
        $digits = preg_replace('/\D/', '', $raw);

        return array_values(array_unique(array_filter([hash('sha256', $raw), $digits !== '' ? hash('sha256', $digits) : null])));
    }

    public static function isPlaceholderEmail(?string $e): bool
    {
        return str_ends_with(strtolower((string) $e), '@noemail.generallink.local');
    }

    // Duplicate check: [exact => people with the same Mobile / NRIC / Email,
    //                   sameName => people with the same name only]
    public static function duplicates(array $in, ?string $exceptId = null): array
    {
        $phone = self::normPhone($in['phone'] ?? '');
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $hashes = self::nricHashes($in['nric'] ?? '');
        $name = trim((string) ($in['full_name'] ?? ''));

        $base = fn () => DB::table('agents')->where('is_deleted', false)->when($exceptId, fn ($q) => $q->where('agent_id', '!=', $exceptId));
        $exact = collect();
        if ($phone !== '' && strlen($phone) >= 7) {
            $tail = substr($phone, -9);   // 012-3456789 / +6012 3456789 / 60123456789 all match
            $exact = $exact->concat($base()->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone,'-',''),' ',''),'+',''),'(','') LIKE ?", ['%'.$tail])->get());
        }
        if ($email !== '' && ! self::isPlaceholderEmail($email)) {
            $exact = $exact->concat($base()->where('email', $email)->get());
        }
        if ($hashes) {
            $exact = $exact->concat($base()->whereIn('nric_hash', $hashes)->get());
        }
        $exact = $exact->unique('agent_id')->values();
        $sameName = $name !== ''
            ? $base()->where('full_name', $name)->whereNotIn('agent_id', $exact->pluck('agent_id')->all() ?: ['#'])->limit(20)->get()
            : collect();

        return ['exact' => $exact, 'sameName' => $sameName];
    }

    // Short "Affiliated To" summary: entity names with an active line.
    public static function affiliatedTo(string $agentId): array
    {
        return DB::table('cbe_group_memberships as m')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'm.cbe_node_id')
            ->where('m.agent_id', $agentId)
            ->whereExists(fn ($x) => $x->select(DB::raw(1))->from('cbe_member_role_tags as t')->whereColumn('t.membership_id', 'm.membership_id')->where('t.status', 'ACTIVE'))
            ->orderBy('n.node_name')->pluck('n.node_name')->unique()->values()->all();
    }

    public static function typeLabels(): array
    {
        $zh = app()->getLocale() === 'zh';

        return DB::table('member_profile_options')->where('list_code', 'AFFTYPE')->orderBy('sort_order')->get()
            ->mapWithKeys(fn ($o) => [$o->code => ($zh && $o->label_zh) ? $o->label_zh : $o->label])->all()
            + ['PRACTITIONER' => __('member_file.type_practitioner'), 'COMMITTEE' => __('member_file.type_committee')];
    }

    // The register for one person: rows grouped by entity.
    // Each row: entity, group, lines[] (type, status, from, to, how, plan, fee...).
    public static function register(string $agentId): array
    {
        $rows = [];
        $links = DB::table('cbe_group_memberships as m')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'm.cbe_node_id')
            ->leftJoin('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->where('m.agent_id', $agentId)
            ->get(['m.membership_id', 'm.cbe_node_id', 'n.node_name', 'n.node_name_zh', 'g.group_name', 'g.group_label_id']);
        foreach ($links as $l) {
            $rows[$l->cbe_node_id] = ['node_id' => $l->cbe_node_id, 'membership_id' => $l->membership_id, 'entity' => $l->node_name, 'entity_zh' => $l->node_name_zh,
                'group' => $l->group_name, 'group_id' => $l->group_label_id, 'lines' => []];
            foreach (DB::table('cbe_member_role_tags as t')->leftJoin('cbe_membership_plans as p', 'p.id', '=', 't.plan_id')
                ->leftJoin('member_profile_options as mt', 'mt.id', '=', 'p.membership_type_id')
                ->leftJoin('member_companies as co', 'co.company_id', '=', 't.company_id')
                ->where('t.membership_id', $l->membership_id)->orderBy('t.from_date')
                ->get(['t.*', 'p.plan_name', 'p.fee', 'p.period', 'mt.code as mt_code', 'mt.label as mt_label', 'co.company_name']) as $t) {
                $rows[$l->cbe_node_id]['lines'][] = [
                    'tag_id' => $t->tag_id, 'type' => $t->tag, 'status' => $t->status ?? 'ACTIVE', 'from' => $t->from_date, 'to' => $t->to_date,
                    'how' => $t->source ?: 'STAFF', 'plan' => $t->plan_name, 'plan_id' => $t->plan_id, 'fee' => $t->fee, 'period' => $t->period,
                    'paid_until' => $t->paid_until, 'fee_status' => self::feeStatus($t), 'module' => null,
                    'mt_code' => $t->mt_code, 'mt_label' => $t->mt_label, 'company_id' => $t->company_id ?? null, 'company' => $t->company_name,
                    'family_count' => ($t->tag === 'MEMBER' && $t->mt_code === 'FAMILY') ? DB::table('cbe_membership_family')->where('principal_tag_id', $t->tag_id)->count() : 0,
                    'family_of' => $t->source === 'FAMILY' ? DB::table('cbe_membership_family as f')->join('cbe_member_role_tags as pt', 'pt.tag_id', '=', 'f.principal_tag_id')
                        ->join('cbe_group_memberships as pm', 'pm.membership_id', '=', 'pt.membership_id')->join('agents as pa', 'pa.agent_id', '=', 'pm.agent_id')
                        ->where('f.member_tag_id', $t->tag_id)->value('pa.full_name') : null,
                ];
            }
        }
        // practitioner profiles (Practitioner Setup) — read live
        if (Schema::hasTable('cbe_practitioner_profiles')) {
            foreach (DB::table('cbe_practitioner_profiles as p')->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'p.cbe_node_id')
                ->leftJoin('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
                ->leftJoin('cbe_practitioner_types as pt', 'pt.id', '=', 'p.practitioner_type_id')
                ->where('p.agent_id', $agentId)->get(['p.id', 'p.cbe_node_id', 'p.is_active', 'p.created_at', 'n.node_name', 'n.node_name_zh', 'g.group_name', 'g.group_label_id', 'pt.type_label']) as $p) {
                $rows[$p->cbe_node_id] ??= ['node_id' => $p->cbe_node_id, 'membership_id' => null, 'entity' => $p->node_name, 'entity_zh' => $p->node_name_zh, 'group' => $p->group_name, 'group_id' => $p->group_label_id, 'lines' => []];
                $rows[$p->cbe_node_id]['lines'][] = ['tag_id' => null, 'type' => 'PRACTITIONER', 'status' => $p->is_active ? 'ACTIVE' : 'ENDED',
                    'from' => substr((string) $p->created_at, 0, 10), 'to' => null, 'how' => 'PRACTITIONER_SETUP', 'plan' => null, 'fee_status' => null, 'module' => 'practitioner',
                    'label' => $p->type_label, 'profile_id' => $p->id];
            }
        }
        // NEW 28 Sep 2026 — item 26: Donor / Sponsor from the Donor Register (linked to this person) — read live
        if (Schema::hasTable('cbe_donors')) {
            foreach (DB::table('cbe_donors as d')->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'd.cbe_node_id')
                ->leftJoin('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
                ->where('d.agent_id', $agentId)->get(['d.donor_id', 'd.cbe_node_id', 'd.created_at', 'n.node_name', 'n.node_name_zh', 'g.group_name', 'g.group_label_id']) as $d) {
                $rows[$d->cbe_node_id] ??= ['node_id' => $d->cbe_node_id, 'membership_id' => null, 'entity' => $d->node_name, 'entity_zh' => $d->node_name_zh, 'group' => $d->group_name, 'group_id' => $d->group_label_id, 'lines' => []];
                $rows[$d->cbe_node_id]['lines'][] = ['tag_id' => null, 'type' => 'DONOR', 'status' => 'ACTIVE', 'from' => substr((string) $d->created_at, 0, 10), 'to' => null,
                    'how' => 'DONOR_REGISTER', 'plan' => null, 'fee_status' => null, 'module' => 'donor'];
                if (Schema::hasTable('cbe_donor_sponsorships')) {
                    foreach (DB::table('cbe_donor_sponsorships as sp')->join('cbe_hierarchy_nodes as n2', 'n2.node_id', '=', 'sp.cbe_node_id')
                        ->leftJoin('group_labels as g2', 'g2.group_label_id', '=', 'n2.group_label_id')
                        ->where('sp.donor_id', $d->donor_id)->get(['sp.cbe_node_id', 'sp.created_at', 'n2.node_name', 'n2.node_name_zh', 'g2.group_name', 'g2.group_label_id']) as $sp) {
                        $rows[$sp->cbe_node_id] ??= ['node_id' => $sp->cbe_node_id, 'membership_id' => null, 'entity' => $sp->node_name, 'entity_zh' => $sp->node_name_zh, 'group' => $sp->group_name, 'group_id' => $sp->group_label_id, 'lines' => []];
                        $rows[$sp->cbe_node_id]['lines'][] = ['tag_id' => null, 'type' => 'SPONSOR', 'status' => 'ACTIVE', 'from' => substr((string) $sp->created_at, 0, 10), 'to' => null,
                            'how' => 'DONOR_REGISTER', 'plan' => null, 'fee_status' => null, 'module' => 'donor'];
                    }
                }
            }
        }
        usort($rows, fn ($a, $b) => [$a['group'], $a['entity']] <=> [$b['group'], $b['entity']]);

        return $rows;
    }

    // Committee positions in every CBE (Committee tab) — read live.
    public static function committeePositions(string $agentId)
    {
        if (! Schema::hasTable('group_committee_members')) {
            return collect();
        }
        $today = now()->toDateString();
        $hasEnded = Schema::hasColumn('group_committee_members', 'ended_at');

        return DB::table('group_committee_members as c')
            ->join('cbe_committee_position_types as pt', 'pt.id', '=', 'c.position_type_id')
            ->join('group_labels as g', 'g.group_label_id', '=', 'c.group_label_id')
            ->where('c.agent_id', $agentId)
            ->orderByDesc('c.term_start_date')
            ->get(['g.group_name', 'g.group_label_id', 'pt.position_label', 'c.term_start_date', 'c.term_end_date', $hasEnded ? 'c.ended_at' : DB::raw('NULL as ended_at')])
            ->map(function ($r) use ($today) {
                $r->current = ! $r->ended_at && (! $r->term_end_date || $r->term_end_date >= $today);
                return $r;
            });
    }

    // Everything he already holds at one entity, from every module (search first).
    public static function existingAt(string $agentId, string $nodeId): array
    {
        $out = [];
        foreach (self::register($agentId) as $row) {
            if ($row['node_id'] !== $nodeId) {
                continue;
            }
            foreach ($row['lines'] as $l) {
                if ($l['status'] === 'ACTIVE') {
                    $out[$l['type']] = $l;
                }
            }
        }
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if ($node) {
            foreach (self::committeePositions($agentId) as $c) {
                if ($c->current && $c->group_label_id === $node->group_label_id) {
                    $out['COMMITTEE'] = ['type' => 'COMMITTEE', 'plan' => $c->position_label, 'how' => 'COMMITTEE_TAB', 'from' => $c->term_start_date];
                }
            }
        }

        return $out;
    }

    public static function feeStatus(object $t): ?string
    {
        if (($t->tag ?? null) !== 'MEMBER' || ! ($t->plan_id ?? null)) {
            return null;
        }
        if (! empty($t->fee_waived)) {
            return ($t->source ?? null) === 'FAMILY' ? 'FAMILY' : 'WAIVED';
        }
        $period = $t->period ?? null;
        if ($period === 'FREE' || (float) ($t->fee ?? 0) <= 0) {
            return 'FREE';
        }
        if (! $t->paid_until) {
            return 'DUE';
        }
        $until = Carbon::parse($t->paid_until);
        if ($until->lt(now()->startOfDay())) {
            return 'OVERDUE';
        }

        return $until->lte(now()->addDays(30)) ? 'DUE' : 'PAID';
    }

    // Add one or more types at an entity. Types already ACTIVE there (from any
    // module) are skipped. Returns the number of lines added.
    public static function addTypes(string $agentId, string $nodeId, array $types, ?string $planId, string $source = 'STAFF'): int
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if (! $node) {
            return 0;
        }
        $have = self::existingAt($agentId, $nodeId);
        $types = array_values(array_intersect(array_unique($types), self::TYPES_OWNED));
        $added = 0;
        DB::transaction(function () use ($agentId, $node, $types, $planId, $source, $have, &$added) {
            $m = DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('cbe_node_id', $node->node_id)->first();
            if (! $m) {
                $mid = (string) Str::uuid();
                DB::table('cbe_group_memberships')->insert([
                    'membership_id' => $mid, 'agent_id' => $agentId, 'group_label_id' => $node->group_label_id, 'cbe_node_id' => $node->node_id,
                    'is_primary' => ! DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('is_primary', true)->exists(),
                    'status' => 'ACTIVE', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $mid = $m->membership_id;
                DB::table('cbe_group_memberships')->where('membership_id', $mid)->update(['status' => 'ACTIVE', 'updated_at' => now()]);
            }
            foreach ($types as $type) {
                if (isset($have[$type])) {
                    continue;
                }
                DB::table('cbe_member_role_tags')->insert([
                    'tag_id' => (string) Str::uuid(), 'membership_id' => $mid, 'tag' => $type, 'status' => 'ACTIVE',
                    'from_date' => now()->toDateString(), 'source' => $source,
                    'plan_id' => $type === 'MEMBER' ? ($planId ?: null) : null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $added++;
            }
        });

        return $added;
    }

    public static function endLine(string $tagId): void
    {
        $t = DB::table('cbe_member_role_tags')->where('tag_id', $tagId)->first();
        if (! $t || ($t->status ?? 'ACTIVE') !== 'ACTIVE') {
            return;
        }
        DB::table('cbe_member_role_tags')->where('tag_id', $tagId)->update(['status' => 'ENDED', 'to_date' => now()->toDateString(), 'updated_at' => now()]);
        // link stays ACTIVE while any type there is still active
        $still = DB::table('cbe_member_role_tags')->where('membership_id', $t->membership_id)->where('status', 'ACTIVE')->exists();
        if (! $still) {
            DB::table('cbe_group_memberships')->where('membership_id', $t->membership_id)->update(['status' => 'INACTIVE', 'updated_at' => now()]);
        }
    }

    // Record a fee payment on a Member line: Paid Until moves forward one period.
    public static function recordPayment(string $tagId, float $amount, string $paidOn, ?string $receiptNo, ?string $by): ?string
    {
        $t = DB::table('cbe_member_role_tags as t')->leftJoin('cbe_membership_plans as p', 'p.id', '=', 't.plan_id')
            ->where('t.tag_id', $tagId)->first(['t.*', 'p.period']);
        if (! $t) {
            return null;
        }
        $base = ($t->paid_until && Carbon::parse($t->paid_until)->gte(Carbon::parse($paidOn))) ? Carbon::parse($t->paid_until) : Carbon::parse($paidOn)->subDay();
        $until = match ($t->period) {
            'YEARLY' => $base->copy()->addYear()->toDateString(),
            'ONE_TIME', 'LIFETIME' => '2099-12-31',
            default => null,
        };
        DB::table('cbe_membership_payments')->insert([
            'id' => (string) Str::uuid(), 'tag_id' => $tagId, 'amount' => $amount, 'paid_on' => $paidOn, 'receipt_no' => $receiptNo,
            'paid_until' => $until, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cbe_member_role_tags')->where('tag_id', $tagId)->update(['paid_until' => $until, 'updated_at' => now()]);

        return $until;
    }

    // ---- NEW 28 Sep 2026 — item 25: make the person a practitioner at an entity ----
    public static function addPractitioner(string $agentId, string $nodeId, string $typeId): ?string
    {
        $have = DB::table('cbe_practitioner_profiles')->where('cbe_node_id', $nodeId)->where('agent_id', $agentId)->where('practitioner_type_id', $typeId)->first();
        if ($have) {
            DB::table('cbe_practitioner_profiles')->where('id', $have->id)->update(['is_active' => true, 'updated_at' => now()]);

            return $have->id;
        }
        $id = (string) Str::uuid();
        DB::table('cbe_practitioner_profiles')->insert([   // same defaults as Practitioner Setup's own Add form
            'id' => $id, 'cbe_node_id' => $nodeId, 'agent_id' => $agentId, 'practitioner_type_id' => $typeId,
            'slot_duration_minutes' => 30, 'max_slots_per_day' => 16, 'booking_window_days' => 60, 'max_upcoming_per_member' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    // ---- item 29: Family membership ----
    public static function familyOf(string $principalTagId)
    {
        return DB::table('cbe_membership_family as f')->join('agents as a', 'a.agent_id', '=', 'f.agent_id')
            ->leftJoin('member_profile_options as r', 'r.id', '=', 'f.relationship')
            ->leftJoin('cbe_member_role_tags as t', 't.tag_id', '=', 'f.member_tag_id')
            ->where('f.principal_tag_id', $principalTagId)->orderBy('a.full_name')
            ->get(['f.id', 'a.agent_id', 'a.agent_code', 'a.full_name', 'a.phone', 'r.label as relationship', 't.status']);
    }

    // Adds a family member under a Family membership: he gets his own Member
    // line at the same entity, same plan, fee covered by the family.
    public static function addFamily(string $principalTagId, string $agentId, ?string $relationshipId): bool
    {
        $pt = DB::table('cbe_member_role_tags as t')->join('cbe_group_memberships as m', 'm.membership_id', '=', 't.membership_id')
            ->where('t.tag_id', $principalTagId)->first(['t.*', 'm.agent_id as principal_agent', 'm.cbe_node_id']);
        if (! $pt || $pt->principal_agent === $agentId || DB::table('cbe_membership_family')->where('principal_tag_id', $principalTagId)->where('agent_id', $agentId)->exists()) {
            return false;
        }
        DB::transaction(function () use ($pt, $agentId, $relationshipId, $principalTagId) {
            $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $pt->cbe_node_id)->first();
            $m = DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('cbe_node_id', $node->node_id)->first();
            if (! $m) {
                $mid = (string) Str::uuid();
                DB::table('cbe_group_memberships')->insert(['membership_id' => $mid, 'agent_id' => $agentId, 'group_label_id' => $node->group_label_id, 'cbe_node_id' => $node->node_id,
                    'is_primary' => ! DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('is_primary', true)->exists(),
                    'status' => 'ACTIVE', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $mid = $m->membership_id;
                DB::table('cbe_group_memberships')->where('membership_id', $mid)->update(['status' => 'ACTIVE', 'updated_at' => now()]);
            }
            // an existing active Member line there is ended first (one Member line at a time)
            foreach (DB::table('cbe_member_role_tags')->where('membership_id', $mid)->where('tag', 'MEMBER')->where('status', 'ACTIVE')->pluck('tag_id') as $old) {
                DB::table('cbe_member_role_tags')->where('tag_id', $old)->update(['status' => 'ENDED', 'to_date' => now()->toDateString(), 'updated_at' => now()]);
            }
            $tid = (string) Str::uuid();
            DB::table('cbe_member_role_tags')->insert(['tag_id' => $tid, 'membership_id' => $mid, 'tag' => 'MEMBER', 'status' => 'ACTIVE',
                'from_date' => now()->toDateString(), 'source' => 'FAMILY', 'plan_id' => $pt->plan_id, 'fee_waived' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cbe_membership_family')->insert(['id' => (string) Str::uuid(), 'principal_tag_id' => $principalTagId, 'agent_id' => $agentId,
                'member_tag_id' => $tid, 'relationship' => $relationshipId, 'created_at' => now(), 'updated_at' => now()]);
        });

        return true;
    }

    public static function removeFamily(string $familyId): void
    {
        $f = DB::table('cbe_membership_family')->where('id', $familyId)->first();
        if (! $f) {
            return;
        }
        if ($f->member_tag_id) {
            self::endLine($f->member_tag_id);
        }
        DB::table('cbe_membership_family')->where('id', $familyId)->delete();
    }

    // ---- item 29 / 26: company record (one for all CBEs) ----
    public static function companyDuplicate(?string $name, ?string $regNo, ?string $exceptId = null)
    {
        $name = trim((string) $name);
        $reg = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $regNo));

        return DB::table('member_companies')->when($exceptId, fn ($q) => $q->where('company_id', '!=', $exceptId))
            ->where(function ($w) use ($name, $reg) {
                if ($reg !== '') {
                    $w->orWhereRaw("UPPER(REPLACE(REPLACE(registration_no,'-',''),' ','')) = ?", [$reg]);
                }
                if ($name !== '') {
                    $w->orWhere('company_name', $name);
                }
            })->first();
    }

    public static function saveCompany(array $in, ?string $by): string
    {
        if ($dup = self::companyDuplicate($in['company_name'] ?? '', $in['registration_no'] ?? '')) {
            return $dup->company_id;   // same company is never saved twice
        }
        $id = (string) Str::uuid();
        DB::table('member_companies')->insert(['company_id' => $id, 'company_name' => trim($in['company_name']), 'registration_no' => trim((string) ($in['registration_no'] ?? '')) ?: null,
            'phone' => $in['phone'] ?? null, 'email' => $in['email'] ?? null, 'address' => $in['address'] ?? null, 'contact_agent_id' => $in['contact_agent_id'] ?? null,
            'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    // ---- NEW 28 Sep 2026 — item 26: Donor / Sponsor ↔ person or company ----
    // Suggested match for one donor: a person (Mobile / Email, then same name)
    // for an individual donor; a company (Reg No / same name) for a company.
    public static function donorSuggestion(object $d): ?array
    {
        if (in_array($d->donor_type, ['COMPANY', 'ORGANIZATION'], true)) {
            $c = DB::table('member_companies')->where('company_name', trim((string) $d->donor_name))->first();

            return $c ? ['kind' => 'company', 'id' => $c->company_id, 'label' => $c->company_name.($c->registration_no ? ' ('.$c->registration_no.')' : ''), 'why' => 'NAME'] : null;
        }
        $dup = self::duplicates(['phone' => $d->phone, 'email' => $d->email, 'full_name' => $d->donor_name]);
        if ($dup['exact']->count() === 1) {
            $a = $dup['exact']->first();

            return ['kind' => 'person', 'id' => $a->agent_id, 'label' => $a->full_name.' · '.$a->agent_code, 'why' => 'MOBILE_EMAIL'];
        }
        if ($dup['exact']->isEmpty() && $dup['sameName']->count() === 1) {
            $a = $dup['sameName']->first();

            return ['kind' => 'person', 'id' => $a->agent_id, 'label' => $a->full_name.' · '.$a->agent_code, 'why' => 'NAME'];
        }

        return null;
    }

    public static function linkDonor(string $donorId, ?string $agentId, ?string $companyId): void
    {
        $upd = ['updated_at' => now()];
        if ($agentId !== null) {
            $upd['agent_id'] = $agentId ?: null;
        }
        if ($companyId !== null && Schema::hasColumn('cbe_donors', 'company_id')) {
            $upd['company_id'] = $companyId ?: null;
        }
        DB::table('cbe_donors')->where('donor_id', $donorId)->update($upd);
    }

    // Called right after a donor is saved in the Donor Register: an individual
    // donor whose Mobile / Email matches exactly ONE person is linked at once.
    public static function autoLinkDonor(string $donorId): void
    {
        $d = DB::table('cbe_donors')->where('donor_id', $donorId)->first();
        if (! $d || $d->agent_id) {
            return;
        }
        $s = self::donorSuggestion($d);
        if ($s && $s['why'] !== 'NAME') {
            $s['kind'] === 'person' ? self::linkDonor($donorId, $s['id'], null) : self::linkDonor($donorId, null, $s['id']);
        }
    }

    // Donor rows that look like the same donor (same Mobile, same Email or the
    // same name + type) — grouped, oldest first.
    public static function donorDuplicateGroups(array $nodeIds)
    {
        $rows = DB::table('cbe_donors as d')->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'd.cbe_node_id')
            ->whereIn('d.cbe_node_id', $nodeIds ?: ['#'])->orderBy('d.created_at')
            ->get(['d.donor_id', 'd.donor_name', 'd.donor_type', 'd.phone', 'd.email', 'd.agent_id', 'd.cbe_node_id', 'n.node_name', 'd.created_at']);
        $groups = [];
        foreach ($rows as $r) {
            $keys = array_filter([
                ($p = self::normPhone($r->phone)) !== '' && strlen($p) >= 7 ? 'P'.substr($p, -9) : null,
                $r->email ? 'E'.strtolower(trim($r->email)) : null,
                'N'.$r->donor_type.'|'.mb_strtolower(trim($r->donor_name)),
            ]);
            $hit = null;
            foreach ($groups as $gi => $g) {
                if (array_intersect($keys, $g['keys'])) {
                    $hit = $gi;
                    break;
                }
            }
            if ($hit === null) {
                $groups[] = ['keys' => $keys, 'rows' => [$r]];
            } else {
                $groups[$hit]['keys'] = array_values(array_unique(array_merge($groups[$hit]['keys'], $keys)));
                $groups[$hit]['rows'][] = $r;
            }
        }

        return collect($groups)->filter(fn ($g) => count($g['rows']) > 1)->values();
    }

    // Merge donor $dropId into $keepId: every record pointing at the dropped
    // donor (contributions, pledges, sponsorships, participations …) is moved
    // to the kept donor; the dropped donor's entity becomes a Sponsorship of
    // the kept donor when different; then the dropped donor row is removed.
    public static function mergeDonors(string $keepId, string $dropId): bool
    {
        if ($keepId === $dropId) {
            return false;
        }
        $keep = DB::table('cbe_donors')->where('donor_id', $keepId)->first();
        $drop = DB::table('cbe_donors')->where('donor_id', $dropId)->first();
        if (! $keep || ! $drop) {
            return false;
        }
        DB::transaction(function () use ($keep, $drop) {
            $tables = collect(DB::select("SELECT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'donor_id' AND TABLE_NAME <> 'cbe_donors'"))->pluck('t');
            foreach ($tables as $t) {
                if ($t === 'cbe_donor_sponsorships') {
                    // keep one sponsorship per entity
                    foreach (DB::table($t)->where('donor_id', $drop->donor_id)->get() as $sp) {
                        if (DB::table($t)->where('donor_id', $keep->donor_id)->where('cbe_node_id', $sp->cbe_node_id)->exists() || $sp->cbe_node_id === $keep->cbe_node_id) {
                            DB::table($t)->where('sponsorship_id', $sp->sponsorship_id)->delete();
                        } else {
                            DB::table($t)->where('sponsorship_id', $sp->sponsorship_id)->update(['donor_id' => $keep->donor_id]);
                        }
                    }
                    continue;
                }
                DB::table($t)->where('donor_id', $drop->donor_id)->update(['donor_id' => $keep->donor_id]);
            }
            if ($drop->cbe_node_id !== $keep->cbe_node_id && Schema::hasTable('cbe_donor_sponsorships')
                && ! DB::table('cbe_donor_sponsorships')->where('donor_id', $keep->donor_id)->where('cbe_node_id', $drop->cbe_node_id)->exists()) {
                DB::table('cbe_donor_sponsorships')->insert(['sponsorship_id' => (string) Str::uuid(), 'donor_id' => $keep->donor_id, 'cbe_node_id' => $drop->cbe_node_id,
                    'notes' => 'Merged from duplicate donor record', 'created_by' => auth('agent')->id(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $fill = [];
            foreach (['phone', 'email', 'address', 'contact_person', 'agent_id'] as $f) {
                if (empty($keep->{$f}) && ! empty($drop->{$f})) {
                    $fill[$f] = $drop->{$f};
                }
            }
            if (Schema::hasColumn('cbe_donors', 'company_id') && empty($keep->company_id) && ! empty($drop->company_id)) {
                $fill['company_id'] = $drop->company_id;
            }
            if ($fill) {
                DB::table('cbe_donors')->where('donor_id', $keep->donor_id)->update($fill + ['updated_at' => now()]);
            }
            DB::table('cbe_donors')->where('donor_id', $drop->donor_id)->delete();
        });

        return true;
    }
}
