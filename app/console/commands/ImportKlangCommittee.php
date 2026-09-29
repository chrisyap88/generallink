<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PhoneNumberService;

// NEW 16 Sep 2026 — per Chris: one-time import of his real committee
// list ("SENARAI JAWATANKUASA 2026", Persekutuan Pertubuhan Agama Tao
// Malaysia, Cawangan Bandar Di Raja Klang) from his Excel file, so the
// Appointment Booking / Committee features built this session have
// real test data instead of an empty Membership Module. Sets up:
//   1. The CBE group (federation) + one Branch-level node (this Klang
//      chapter), carrying its registration no., address, phone, fax,
//      email from the Excel header.
//   2. The 9 real position titles used by this committee (Pengerusi,
//      Timbalan Pengerusi, Naib Pengerusi, Setiausaha, Bendahari,
//      Timbalan Bendahari, Ahli Jawatankuasa, Juru Audit, Ketua
//      Sekretariat) as new entries in the Committee Position Types
//      master file — per Chris's explicit choice, exact Malay titles,
//      not mapped to the generic English seed rows.
//   3. All 13 people (12 from the sheet + Chris himself, 13th, as
//      Ketua Sekretariat) as real Agent/Member records — NRIC is
//      stored per Chris's explicit decision this session, but only
//      encrypted (AES-256 + SHA-256 dedupe hash), same pattern already
//      used on the customers table (see CustomerResolutionService).
//   4. Each person's committee assignment (group_committee_members),
//      current term (term_start_date = today, term_end_date = null).
//
// Fully idempotent — matches by nric_hash first (the real identity
// key), so running this command twice never creates duplicates; it
// only fills in anything that was missing.
class ImportKlangCommittee extends Command
{
    protected $signature = 'cbe:import-klang-committee';

    protected $description = 'One-time import: Persekutuan Pertubuhan Agama Tao Malaysia, Cawangan Bandar Di Raja Klang — group, branch node, committee position types, and all 13 committee members';

    private const GROUP_NAME = 'Persekutuan Pertubuhan Agama Tao Malaysia';

    private const NODE_NAME = 'Cawangan Bandar Di Raja Klang';

    private const NODE_ADDRESS = "NO. 35 & 1ST, LORONG TEMENGGUNG 15A, OFF JALAN SUNGAI JATI, TAMAN SENTOSA PERDANA, 41200 KLANG, SELANGOR DARUL EHSAN. MALAYSIA.";

    private const NODE_PHONE = '03-51610075';

    private const NODE_FAX = '03-51623489';

    private const NODE_EMAIL = 'taoism.malaysiaklang@gmail.com';

    private const NODE_REG_NO = 'PPM-009-14-26081997-000002';

    // Code => Malay/English position label, in display order.
    private const POSITIONS = [
        'PENGERUSI' => 'Pengerusi (Chairman)',
        'TIMBALAN_PENGERUSI' => 'Timbalan Pengerusi (Deputy Chairman)',
        'NAIB_PENGERUSI' => 'Naib Pengerusi (Vice Chairman)',
        'SETIAUSAHA' => 'Setiausaha (Secretary)',
        'BENDAHARI' => 'Bendahari (Treasurer)',
        'TIMBALAN_BENDAHARI' => 'Timbalan Bendahari (Deputy Treasurer)',
        'AHLI_JAWATANKUASA' => 'Ahli Jawatankuasa (Committee Member)',
        'JURU_AUDIT' => 'Juru Audit (Auditor)',
        'KETUA_SEKRETARIAT' => 'Ketua Sekretariat (Head of Secretariat)',
    ];

    // Straight from the Excel — one row per person. dob is d/m/Y.
    private const PEOPLE = [
        ['position' => 'PENGERUSI', 'full_name' => 'Dato Lim Kim Hwi', 'nric' => '600707-10-5295', 'occupation' => 'Agency (NIRVANA ASIA GROUP)', 'dob' => '07/07/1960', 'address' => 'No. 2A, Jalan Sg Ramal 32/54B, Bukit Rimau, 40460 Shah Alam, Selangor', 'hp' => '012-3352270', 'office' => '017-3352270', 'email' => 'pengerusi@ftamklangdiraja.com'],
        ['position' => 'TIMBALAN_PENGERUSI', 'full_name' => 'Ter Hong To', 'nric' => '701019-10-5059', 'occupation' => 'Business Man', 'dob' => '19/10/1970', 'address' => '24, Jalan Temenggung 39G, Taman Sentosa Perdana, 41200 Klang', 'hp' => '016-2099459', 'office' => null, 'email' => 'timb.pengerusi@ftamklangdiraja.com'],
        ['position' => 'NAIB_PENGERUSI', 'full_name' => 'Lim Fok Yen', 'nric' => '750110-10-5149', 'occupation' => 'Director (QE Interior Sdn Bhd)', 'dob' => '10/01/1975', 'address' => 'Batu 11, Jalan Raja Musa, 45000 Kuala Selangor', 'hp' => '017-3792055', 'office' => '03-51618159', 'email' => 'naib.pengerusi@ftamklamgdiraja.com'],
        ['position' => 'SETIAUSAHA', 'full_name' => 'Gan Huat Juan', 'nric' => '960604-10-5013', 'occupation' => 'Bekerja Sendiri', 'dob' => '04/06/1996', 'address' => '12, Jalan Dato Dagang 20, Taman Sentosa, 41200 Klang, Selangor', 'hp' => '013-7720266', 'office' => null, 'email' => 'setiausaha@ftamklangdiraja.com'],
        ['position' => 'BENDAHARI', 'full_name' => 'Tan Kim Hai', 'nric' => '750418-10-5321', 'occupation' => 'Swasta (Herbalceutical (M) Sdn Bhd)', 'dob' => '18/04/1975', 'address' => '2A, Lorong Bendahara 48E, Taman Sejati, 41200 Selangor', 'hp' => '019-2753133', 'office' => null, 'email' => 'bendahari@ftamklangdiraja.com'],
        ['position' => 'TIMBALAN_BENDAHARI', 'full_name' => 'Su Chun Hing', 'nric' => '850905-10-5423', 'occupation' => 'Sales Executive', 'dob' => '05/09/1985', 'address' => '19, Jalan Sg Kelubi 32/103A, Kemuning Greenhills 2, Seksyen 32, 40460 Shah Alam', 'hp' => '016-2522884', 'office' => null, 'email' => 'tim.bendahari@ftamklangdiraja.com'],
        ['position' => 'AHLI_JAWATANKUASA', 'full_name' => "H'ng Boon Khim", 'nric' => '720328-10-5283', 'occupation' => 'Senior Vice President Service Department', 'dob' => '28/03/1972', 'address' => '14, Jalan Muda 68, Kawasan 19, 41050 Klang, Selangor', 'hp' => '+6012-3373977', 'office' => null, 'email' => 'boonkhim@ftamklangdiraja.com'],
        ['position' => 'AHLI_JAWATANKUASA', 'full_name' => 'Tan Kok Chan', 'nric' => '780520-10-5085', 'occupation' => 'Perniagaan Sendiri', 'dob' => '20/05/1978', 'address' => 'No. 2, Jalan Dato Dagang 20, Taman Sentosa, Klang', 'hp' => '016-444 2436', 'office' => null, 'email' => 'calvintan@ftamklangdiraja.com'],
        ['position' => 'AHLI_JAWATANKUASA', 'full_name' => 'Tee Ching Lai', 'nric' => '730304-10-5165', 'occupation' => 'Perniagaan Sendiri', 'dob' => '04/03/1973', 'address' => 'No. 2, Jalan Dato Abdul Hamid 11, Taman Sentosa, Klang', 'hp' => '012-331 2331', 'office' => null, 'email' => 'cltee@ftaamklangdiraja.com'],
        ['position' => 'AHLI_JAWATANKUASA', 'full_name' => 'Chua Swee Thin', 'nric' => '800906-10-5366', 'occupation' => 'Pekerja Restoran', 'dob' => '06/09/1980', 'address' => '8158, 8 Jalan Raja Udang 1, Pantai Sepang Putra Pkn, 43950 Sungai Pelek, Selangor', 'hp' => '017-639 4134', 'office' => null, 'email' => 'sweethin@ftamklangdiraja.com'],
        ['position' => 'JURU_AUDIT', 'full_name' => 'Lim Geok See', 'nric' => '720404-10-5016', 'occupation' => 'Sales', 'dob' => '04/04/1972', 'address' => 'No. 34, Jalan Puding, Kawasan 6, 41100 Klang', 'hp' => '012-364 4472', 'office' => '014-902 2990', 'email' => 'limgsee@ftamklangdiraja.com'],
        ['position' => 'JURU_AUDIT', 'full_name' => 'Kee Get Hong', 'nric' => '740927-14-5760', 'occupation' => 'Nirvana Agent', 'dob' => '27/09/1974', 'address' => 'No 59, Lorong Batu Nilam 23A, Bukit Tinggi 2, 41200 Klang', 'hp' => '016-238 5818', 'office' => null, 'email' => 'amandakee@ftamklangdiraja.com'],
        // 13th — Chris Yap himself, per his explicit instruction.
        ['position' => 'KETUA_SEKRETARIAT', 'full_name' => 'Yap Wai Jyh', 'nric' => '631102-10-6369', 'occupation' => 'IT Consultant', 'dob' => '02/11/1963', 'address' => 'No 5, Jalan Aman Perdana 7C/KU5, Taman Aman Perdana, 41050, Meru Klang', 'hp' => '012 2252275', 'office' => '0166621311', 'email' => 'chrisyap@ftamklangdiraja.com'],
    ];

    public function handle(): int
    {
        DB::beginTransaction();
        try {
            $groupId = $this->findOrCreateGroup();
            $levelId = $this->findOrCreateLevel($groupId);
            $nodeId = $this->findOrCreateNode($groupId, $levelId);
            $positionTypeIds = $this->findOrCreatePositionTypes();

            $created = 0;
            $updated = 0;
            $skipped = [];

            foreach (self::PEOPLE as $row) {
                $result = $this->upsertPerson($row, $groupId, $nodeId, $positionTypeIds[$row['position']]);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                } else {
                    $skipped[] = $row['full_name'].' — '.$result;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed, rolled back — nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done.');
        $this->line('Group:    '.self::GROUP_NAME.' (group_label_id: '.$groupId.')');
        $this->line('Branch:   '.self::NODE_NAME.' (node_id: '.$nodeId.')');
        $this->line("People:   $created new agent(s) created, $updated existing agent(s) matched/updated.");
        if ($skipped) {
            $this->warn('Skipped (needs your review — no changes made for these):');
            foreach ($skipped as $s) {
                $this->warn('  - '.$s);
            }
        }
        $this->newLine();
        $this->line('Open Membership Module -> Practitioner / Appointment Setup or Member Maintenance,');
        $this->line('pick "'.self::GROUP_NAME.'" -> "'.self::NODE_NAME.'" to see them.');

        return self::SUCCESS;
    }

    private function findOrCreateGroup(): string
    {
        $existing = DB::table('group_labels')->where('group_name', self::GROUP_NAME)->where('group_type', 'CBE')->first();
        if ($existing) {
            $this->line('Group already exists — using it.');

            return $existing->group_label_id;
        }

        $id = (string) Str::uuid();
        $base = Str::slug(self::GROUP_NAME);
        $slug = $base;
        $i = 1;
        while (DB::table('group_labels')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        DB::table('group_labels')->insert([
            'group_label_id' => $id,
            'group_name' => self::GROUP_NAME,
            'group_type' => 'CBE',
            'slug' => $slug,
            'description' => null,
            'promotion_demotion_enabled' => 0,
            'requires_rank_assignment' => false,
            'logo_path' => null,
            'faith_practice_type' => 'NONE',
            'subscription_tier' => 'FREE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Created group: '.self::GROUP_NAME);

        return $id;
    }

    private function findOrCreateLevel(string $groupId): string
    {
        $existing = DB::table('cbe_hierarchy_levels')->where('group_label_id', $groupId)->where('level_order', 1)->first();
        if ($existing) {
            return $existing->level_id;
        }

        $levelId = (string) Str::uuid();
        DB::table('cbe_hierarchy_levels')->insert([
            'level_id' => $levelId,
            'group_label_id' => $groupId,
            'level_order' => 1,
            'level_name' => 'Branch',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Created hierarchy level: Branch');

        return $levelId;
    }

    private function findOrCreateNode(string $groupId, string $levelId): string
    {
        $existing = DB::table('cbe_hierarchy_nodes')
            ->where('group_label_id', $groupId)
            ->where('node_name', self::NODE_NAME)
            ->first();

        if ($existing) {
            $this->line('Branch node already exists — filling in any missing contact info.');
            DB::table('cbe_hierarchy_nodes')->where('node_id', $existing->node_id)->update(array_filter([
                'address' => $existing->address ?: self::NODE_ADDRESS,
                'contact_phone' => $existing->contact_phone ?: self::NODE_PHONE,
                'email' => $existing->email ?: self::NODE_EMAIL,
                'fax' => $existing->fax ?: self::NODE_FAX,
                'external_reference_no' => $existing->external_reference_no ?: self::NODE_REG_NO,
                'updated_at' => now(),
            ]));

            return $existing->node_id;
        }

        $nodeId = (string) Str::uuid();
        DB::table('cbe_hierarchy_nodes')->insert([
            'node_id' => $nodeId,
            'group_label_id' => $groupId,
            'level_id' => $levelId,
            'parent_node_id' => null,
            'node_code' => 'KLANG-01',
            'node_name' => self::NODE_NAME,
            'node_name_zh' => null,
            'city' => 'Klang',
            'postcode' => '41200',
            'address' => self::NODE_ADDRESS,
            'contact_phone' => self::NODE_PHONE,
            'email' => self::NODE_EMAIL,
            'fax' => self::NODE_FAX,
            'contact_person_1' => null,
            'contact_person_2' => null,
            'external_reference_no' => self::NODE_REG_NO,
            'hierarchy_path' => "/$nodeId/",
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Created branch node: '.self::NODE_NAME);

        return $nodeId;
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

    private function upsertPerson(array $row, string $groupId, string $nodeId, string $positionTypeId): string
    {
        $nricRaw = trim($row['nric']);
        $nricHash = hash('sha256', $nricRaw);
        $email = strtolower(trim($row['email']));

        $agent = DB::table('agents')->where('nric_hash', $nricHash)->first();

        if (! $agent) {
            $emailConflict = DB::table('agents')->where('email', $email)->first();
            if ($emailConflict) {
                return 'email "'.$email.'" already belongs to a different existing member (agent_id: '.$emailConflict->agent_id.') — not touched';
            }
        }

        $dob = Carbon::createFromFormat('d/m/Y', $row['dob'])->toDateString();
        $hp = PhoneNumberService::isValid($row['hp']) ? PhoneNumberService::normalize($row['hp']) : $row['hp'];
        $office = $row['office'] && PhoneNumberService::isValid($row['office']) ? PhoneNumberService::normalize($row['office']) : $row['office'];

        $status = 'created';

        if ($agent) {
            $agentId = $agent->agent_id;
            DB::table('agents')->where('agent_id', $agentId)->update([
                'full_name' => $row['full_name'],
                'occupation' => $row['occupation'],
                'date_of_birth' => $dob,
                'address' => $row['address'],
                'phone' => $hp,
                'office_phone' => $office,
                'group_label_id' => $agent->group_label_id ?: $groupId,
                'cbe_node_id' => $agent->cbe_node_id ?: $nodeId,
                'updated_at' => now(),
            ]);
            $status = 'updated';
        } else {
            $agentId = (string) Str::uuid();
            do {
                $agentCode = 'CBE-'.strtoupper(Str::random(8));
            } while (DB::table('agents')->where('agent_code', $agentCode)->exists());

            DB::table('agents')->insert([
                'agent_id' => $agentId,
                'agent_code' => $agentCode,
                'full_name' => $row['full_name'],
                'email' => $email,
                'password_hash' => bcrypt((string) Str::uuid()),
                'nric_encrypted' => encrypt($nricRaw),
                'nric_hash' => $nricHash,
                'occupation' => $row['occupation'],
                'date_of_birth' => $dob,
                'address' => $row['address'],
                'phone' => $hp,
                'office_phone' => $office,
                'role' => 'INTRODUCER',
                'status' => 'ACTIVE',
                'parent_id' => null,
                'hierarchy_path' => "/$agentId/",
                'group_id' => null,
                'group_label_id' => $groupId,
                'cbe_node_id' => $nodeId,
                'qr_code_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $membership = DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('group_label_id', $groupId)->first();
        if (! $membership) {
            $isPrimary = ! DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('is_primary', true)->exists();
            DB::table('cbe_group_memberships')->insert([
                'membership_id' => (string) Str::uuid(),
                'agent_id' => $agentId,
                'group_label_id' => $groupId,
                'cbe_node_id' => $nodeId,
                'is_primary' => $isPrimary,
                'status' => 'ACTIVE',
                'member_type' => 'COMMITTEE',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ($membership->member_type !== 'COMMITTEE') {
            DB::table('cbe_group_memberships')->where('membership_id', $membership->membership_id)->update([
                'member_type' => 'COMMITTEE',
                'updated_at' => now(),
            ]);
        }

        $committeeRow = DB::table('group_committee_members')
            ->where('group_label_id', $groupId)
            ->where('position_type_id', $positionTypeId)
            ->where('agent_id', $agentId)
            ->first();

        if (! $committeeRow) {
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

        return $status;
    }
}
