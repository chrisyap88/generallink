<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 16 Sep 2026 — per Chris: import the 14th Central Committee
// (2024-2026) of 马来西亚道教总会 / Persekutuan Pertubuhan Agama Tao
// Malaysia (Federation of Taoist Associations Malaysia) — the SAME
// group_label already created by cbe:import-klang-committee (the Klang
// branch's federation), never a new group. Updates that group's own
// head-office address/email/phone from the KL letterhead, adds the 13
// real HQ position titles (Chinese, exact wording from the source
// document — never mapped to the Klang branch's own position types),
// and inserts all 24 committee members.
//
// This document has no NRIC, so — unlike cbe:import-klang-committee,
// which dedupes on the encrypted NRIC hash — this dedupes on an exact
// full_name match against existing agents. Per Chris's explicit
// instruction ("dont duplicate if the name is the same such as
// 陈和章道长"). A name match reuses that agent record as-is; nothing
// here overwrites an existing agent's real contact info.
//
// Only 2 of the 24 have a phone number on this document (from the
// WhatsApp submission note) — everyone else gets an empty phone and a
// placeholder @noemail.generallink.local email, same fallback already
// used by AdminCbeMembersController::store() for walk-in members with
// no contact info on file.
//
// Fully idempotent — safe to run more than once.
class ImportTaoHqCommittee extends Command
{
    protected $signature = 'cbe:import-tao-hq-committee';

    protected $description = 'Import the 14th Central Committee (2024-2026) of 马来西亚道教总会 into the existing Persekutuan Pertubuhan Agama Tao Malaysia group';

    private const GROUP_NAME = 'Persekutuan Pertubuhan Agama Tao Malaysia';

    private const HQ_NAME_ZH = '马来西亚道教总会';

    private const HQ_ADDRESS = 'No. 107-2, Tingkat 2, Jalan 17/42, Taman Lawa, Off Jalan Kuching, 51200 Kuala Lumpur, Malaysia.';

    private const HQ_CITY = 'Kuala Lumpur';

    private const HQ_POSTCODE = '51200';

    private const HQ_EMAIL = 'daoism.malaysia@gmail.com';

    // Code => exact Chinese position title, in display order.
    private const POSITIONS = [
        'ZONG_HUI_ZHANG' => '总会长',
        'SHU_LI_ZONG_HUI_ZHANG' => '署理总会长',
        'FU_ZONG_HUI_ZHANG' => '副总会长',
        'MI_SHU_ZHANG' => '秘书长',
        'FU_MI_SHU_ZHANG' => '副秘书长',
        'ZONG_CAI_ZHENG' => '总财政',
        'FU_ZONG_CAI_ZHENG' => '副总财政',
        'FU_LI_ZHU_REN' => '福利主任',
        'ZHENG_JIAO_JI' => '正交际',
        'FU_JIAO_JI' => '副交际',
        'LI_SHI' => '理事',
        'NEI_BU_CHA_ZHANG' => '内部查账',
        'QING_NIAN_ZU_ZHU_REN' => '青年组主任',
    ];

    // Straight from "选举第14届中央委员(2024-2026)". phone is only
    // filled in where the source document's WhatsApp submission note
    // actually names that person.
    private const PEOPLE = [
        ['position' => 'ZONG_HUI_ZHANG', 'full_name' => '陈和章', 'phone' => '012-6555 555'],
        ['position' => 'SHU_LI_ZONG_HUI_ZHANG', 'full_name' => '陈荣盛', 'phone' => null],
        ['position' => 'FU_ZONG_HUI_ZHANG', 'full_name' => '陈詠隆', 'phone' => null],
        ['position' => 'FU_ZONG_HUI_ZHANG', 'full_name' => '黄泽彪', 'phone' => null],
        ['position' => 'FU_ZONG_HUI_ZHANG', 'full_name' => '江先雄', 'phone' => null],
        ['position' => 'FU_ZONG_HUI_ZHANG', 'full_name' => '林福美', 'phone' => null],
        ['position' => 'MI_SHU_ZHANG', 'full_name' => '刘振吉', 'phone' => '019-333 2452'],
        ['position' => 'FU_MI_SHU_ZHANG', 'full_name' => '邹文冲', 'phone' => null],
        ['position' => 'ZONG_CAI_ZHENG', 'full_name' => '拿督林來成', 'phone' => null],
        ['position' => 'FU_ZONG_CAI_ZHENG', 'full_name' => '曾明华', 'phone' => null],
        ['position' => 'FU_LI_ZHU_REN', 'full_name' => '刘莲花', 'phone' => null],
        ['position' => 'ZHENG_JIAO_JI', 'full_name' => '陈明洲', 'phone' => null],
        ['position' => 'FU_JIAO_JI', 'full_name' => '刘鹏材', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '李国庆', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '谭惠怡', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '宋奎璁', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '郑秀明', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '拿督林金辉', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '陈宝月', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '林振江', 'phone' => null],
        ['position' => 'LI_SHI', 'full_name' => '颜伟鸿', 'phone' => null],
        ['position' => 'NEI_BU_CHA_ZHANG', 'full_name' => '黄吉阳', 'phone' => null],
        ['position' => 'NEI_BU_CHA_ZHANG', 'full_name' => '余胜才', 'phone' => null],
        ['position' => 'QING_NIAN_ZU_ZHU_REN', 'full_name' => '江佑侑', 'phone' => null],
    ];

    public function handle(): int
    {
        $groupId = DB::table('group_labels')->where('group_name', self::GROUP_NAME)->where('group_type', 'CBE')->value('group_label_id');

        if (! $groupId) {
            $this->error('No existing group found named "'.self::GROUP_NAME.'". Run cbe:import-klang-committee first — that command creates this group.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $this->updateGroupContactInfo($groupId);
            $positionTypeIds = $this->findOrCreatePositionTypes();

            $created = 0;
            $matched = 0;

            foreach (self::PEOPLE as $row) {
                [$agentId, $wasCreated] = $this->findOrCreateAgent($row);
                $wasCreated ? $created++ : $matched++;

                $this->ensureMembership($agentId, $groupId);
                $this->ensureCommitteeAssignment($agentId, $groupId, $positionTypeIds[$row['position']]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed, rolled back — nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done.');
        $this->line('Group:   '.self::GROUP_NAME.' / '.self::HQ_NAME_ZH.' (group_label_id: '.$groupId.')');
        $this->line("People:  $created new agent(s) created, $matched matched to an existing agent by name (not duplicated).");
        $this->newLine();
        $this->line('Open Group Name & Hierarchy Levels -> "'.self::GROUP_NAME.'" -> Committee tab to see the full Central Committee.');

        return self::SUCCESS;
    }

    private function updateGroupContactInfo(string $groupId): void
    {
        $group = DB::table('group_labels')->where('group_label_id', $groupId)->first();

        DB::table('group_labels')->where('group_label_id', $groupId)->update(array_filter([
            'address' => $group->address ?: self::HQ_ADDRESS,
            'city' => $group->city ?: self::HQ_CITY,
            'postcode' => $group->postcode ?: self::HQ_POSTCODE,
            'email' => $group->email ?: self::HQ_EMAIL,
            'updated_at' => now(),
        ], fn ($v) => $v !== null && $v !== false));

        $this->info('Updated group head-office address/email from the HQ letterhead.');
    }

    /** @return array<string, string> code => id */
    private function findOrCreatePositionTypes(): array
    {
        $ids = [];
        $sort = DB::table('cbe_committee_position_types')->max('sort_order') ?? 0;

        foreach (self::POSITIONS as $code => $label) {
            $existing = DB::table('cbe_committee_position_types')->where('code', $code)->first();
            if ($existing) {
                $ids[$code] = $existing->id;

                continue;
            }

            $sort++;
            $id = (string) Str::uuid();
            DB::table('cbe_committee_position_types')->insert([
                'id' => $id,
                'code' => $code,
                'position_label' => $label,
                'is_system' => false,
                'is_active' => true,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[$code] = $id;
            $this->info('Added committee position type: '.$label);
        }

        return $ids;
    }

    /** @return array{0: string, 1: bool} [agent_id, wasCreated] */
    private function findOrCreateAgent(array $row): array
    {
        $existing = DB::table('agents')->where('full_name', $row['full_name'])->where('is_deleted', false)->first();
        if ($existing) {
            return [$existing->agent_id, false];
        }

        $agentId = (string) Str::uuid();
        do {
            $agentCode = 'CBE-'.strtoupper(Str::random(8));
        } while (DB::table('agents')->where('agent_code', $agentCode)->exists());

        $email = 'member-'.strtolower(Str::random(10)).'@noemail.generallink.local';

        DB::table('agents')->insert([
            'agent_id' => $agentId,
            'agent_code' => $agentCode,
            'full_name' => $row['full_name'],
            'email' => $email,
            'password_hash' => bcrypt((string) Str::uuid()),
            'phone' => $row['phone'] ?: '',
            'role' => 'INTRODUCER',
            'status' => 'ACTIVE',
            'parent_id' => null,
            'hierarchy_path' => "/$agentId/",
            'group_id' => null,
            'group_label_id' => null,
            'cbe_node_id' => null,
            'qr_code_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$agentId, true];
    }

    private function ensureMembership(string $agentId, string $groupId): void
    {
        $existing = DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('group_label_id', $groupId)->first();
        if ($existing) {
            if ($existing->member_type !== 'COMMITTEE') {
                DB::table('cbe_group_memberships')->where('membership_id', $existing->membership_id)->update([
                    'member_type' => 'COMMITTEE',
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $isPrimary = ! DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('is_primary', true)->exists();
        DB::table('cbe_group_memberships')->insert([
            'membership_id' => (string) Str::uuid(),
            'agent_id' => $agentId,
            'group_label_id' => $groupId,
            'cbe_node_id' => null,
            'is_primary' => $isPrimary,
            'status' => 'ACTIVE',
            'member_type' => 'COMMITTEE',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureCommitteeAssignment(string $agentId, string $groupId, string $positionTypeId): void
    {
        $exists = DB::table('group_committee_members')
            ->where('group_label_id', $groupId)
            ->where('position_type_id', $positionTypeId)
            ->where('agent_id', $agentId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('group_committee_members')->insert([
            'id' => (string) Str::uuid(),
            'group_label_id' => $groupId,
            'position_type_id' => $positionTypeId,
            'agent_id' => $agentId,
            'term_start_date' => now()->toDateString(),
            'term_end_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
