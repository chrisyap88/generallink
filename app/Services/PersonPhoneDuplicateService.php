<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 25 Sep 2026 -- per Chris: "what if the user key in the phone
// number is exist in vendor or customer or membership or branch or
// entity or state or hq or city? how you deal with it?" Decision from
// Chris: show a WARNING only, never block saving. This is a shared
// helper so every "Add Customer / Add Donor / Register Vendor /
// Register New Member" screen runs the exact same check, in one place,
// instead of four different copies of the same SQL.
//
// Two separate checks, deliberately never mixed together:
//   1. checkPerson()  -- Members/Customers/Donors/Vendors are all real
//      PEOPLE, so the same phone number turning up twice there is a
//      strong signal it's actually the same person typed in twice.
//   2. checkEntity()  -- Branch/Entity/State/HQ numbers are an
//      ORGANIZATION's own office line, not a person's -- a caretaker's
//      personal mobile legitimately doubling as a small temple's only
//      contact number is normal, so entity numbers are only ever
//      compared against OTHER entity numbers, never against a person's
//      phone.
class PersonPhoneDuplicateService
{
    /**
     * @return array{type:string,label:string,name:string}|null
     */
    public static function checkPerson(string $phone, ?string $excludeType = null, ?string $excludeId = null): ?array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $checks = [
            ['type' => 'MEMBER', 'label' => 'Member', 'table' => 'agents', 'idCol' => 'agent_id', 'nameCol' => 'full_name', 'extraWhere' => ['is_deleted', false]],
            ['type' => 'CUSTOMER', 'label' => 'Customer', 'table' => 'cbe_customers', 'idCol' => 'customer_id', 'nameCol' => 'customer_name'],
            ['type' => 'DONOR', 'label' => 'Donor/Sponsor', 'table' => 'cbe_donors', 'idCol' => 'donor_id', 'nameCol' => 'donor_name'],
            ['type' => 'VENDOR', 'label' => 'Vendor', 'table' => 'cbe_vendors', 'idCol' => 'vendor_id', 'nameCol' => 'vendor_name'],
        ];

        foreach ($checks as $c) {
            if ($excludeType === $c['type']) {
                continue; // don't flag a record against itself when editing
            }

            $query = DB::table($c['table'])->where('phone', $phone);
            if (isset($c['extraWhere'])) {
                $query->where($c['extraWhere'][0], $c['extraWhere'][1]);
            }
            if ($excludeType === $c['type'] && $excludeId) {
                $query->where($c['idCol'], '!=', $excludeId);
            }

            $hit = $query->first([$c['idCol'], $c['nameCol']]);
            if ($hit) {
                return [
                    'type' => $c['type'],
                    'label' => $c['label'],
                    'name' => $hit->{$c['nameCol']},
                ];
            }
        }

        return null;
    }

    /**
     * @return array{label:string,name:string}|null
     */
    public static function checkEntity(string $phone, ?string $excludeNodeId = null): ?array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $nodeHit = DB::table('cbe_hierarchy_node_phones as p')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'p.node_id')
            ->where('p.phone_number', $phone)
            ->when($excludeNodeId, fn ($q) => $q->where('p.node_id', '!=', $excludeNodeId))
            ->first(['n.node_name']);
        if ($nodeHit) {
            return ['label' => 'Entity/Branch', 'name' => $nodeHit->node_name];
        }

        $groupHit = DB::table('group_label_phones as p')
            ->join('group_labels as g', 'g.group_label_id', '=', 'p.group_label_id')
            ->where('p.phone_number', $phone)
            ->first(['g.group_name']);
        if ($groupHit) {
            return ['label' => 'Head Office', 'name' => $groupHit->group_name];
        }

        return null;
    }
}
