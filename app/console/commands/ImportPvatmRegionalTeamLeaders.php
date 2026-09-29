<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\SpecialGroupController;
use App\Models\Agent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 16 Jul 2026 — one-time bulk import of PVATM's 14 regional/state
// Team Leaders (from PVATM_Offices_Detail.xlsx, supplied by Chris),
// appointed under CA Teow's PVATM Group Leader account. Requires the
// multi-TL support just added to SpecialGroupController — without it,
// only the FIRST row here would succeed and the rest would be blocked
// by the old "one TL per group" rule.
//
// Run once via: php artisan pvatm:import-tls
// Add --dry-run to preview without writing anything.
//
// Email convention matches existing agents (e.g. cateow@, lim.hui.min@,
// saravanan.muthu@generallink.my): lowercase, dot-separated, honorifics
// (Haji, bin, binti, Pn., etc.) stripped from the real name.
//
// Phone numbers are the OFFICE TELEPHONE numbers from the source file
// (landline format, e.g. "07-232 4818"), not personal mobiles — per
// Chris's explicit instruction to use the given tel number as-is. This
// only works because we insert directly via createAgentForImport(),
// which bypasses the HTTP form's mobile-number regex (that regex would
// reject a landline number if submitted through the normal web form).
// -------------------------------------------------------
class ImportPvatmRegionalTeamLeaders extends Command
{
    protected $signature = 'pvatm:import-tls {--dry-run : Preview without inserting anything}';
    protected $description = 'Bulk-import PVATM\'s 14 regional/state Team Leaders under CA Teow';

    private const GL_EMAIL = 'cateow@generallink.my';

    // full_name, address, postcode, city, state, phone (office telephone)
    private const ROWS = [
        ['Haji Kadirin bin Haji Tamio',      'kadirin.tamio',      'Tingkat 2, Wisma Pahlawan, Jalan Kupang 2, Batu 4 ½, Skudai Kiri', '81200', 'Johor Bahru',       'Johor',            '07-232 4818'],
        ['Haji Abd Rahman bin Abd Hamid',    'abd.rahman.hamid',   "1152, Wisma Pahlawan, Jalan Dato' Wan Muhamad Saman",              '05400', 'Alor Setar',        'Kedah',            '04-772 4609'],
        ['Wan Muhamad bin Haji Wan Yusoff',  'wan.muhamad.yusoff', 'Tingkat 2, Wisma Pahlawan, Jalan Ismail',                          '15000', 'Kota Bharu',        'Kelantan',         '09-748 5914'],
        ['Haji Anuar bin Haji Abu Noor',     'anuar.abu.noor',     'Tingkat 3, Wisma Pahlawan, Jalan Sultan Sulaiman',                 '50000', 'Kuala Lumpur',      'Kuala Lumpur',     '03-2272 3932'],
        ['Pn. Rumlah binti Ahmad',           'rumlah.ahmad',       'No. 42-1, Jalan PMS 3, Plaza Melaka Sentral',                      '75400', 'Peringgit',         'Melaka',           '06-282 1518'],
        ['Zamree bin Nordin',                'zamree.nordin',      'No. 63, Tingkat 2, Wisma Pahlawan, Jalan Tuanku Antah',            '70100', 'Seremban',          'Negeri Sembilan',  '06-763 0977'],
        ['Hazeli bin Abdul Razak',           'hazeli.abdul.razak', 'Tingkat Bawah Wisma Puriwirawan, Jalan Gambut',                    '25000', 'Kuantan',           'Pahang',           '09-513 5316'],
        ['Yusri bin Yahaya',                 'yusri.yahaya',       'No. 49-A, Tkt 1, Wisma Pahlawan Perak, Lengkok Kledang Raya',      '30100', 'Ipoh',              'Perak',            '05-528 5589'],
        ['Rosli bin Salim',                  'rosli.salim',        'Medan Syed Alwi',                                                  '01000', 'Kangar',            'Perlis',           '04-976 5371'],
        ['Mokhtar bin Haji Bengan',          'mokhtar.bengan',     'No. 20, Lorong PJ 1/1, Taman Pauh Jaya',                           '13700', 'Perai',             'Pulau Pinang',     '04-398 5001'],
        ['Razali bin Japeth',                'razali.japeth',      'Lot A-2017(IV), Tingkat 2, Blok A, Wisma MUIS',                    '88863', 'Kota Kinabalu',     'Sabah',            '088-234 644'],
        ['Garid Anak Sunang',                'garid.anak.sunang',  'BN 208, Tingkat 1, Batu Kawah New Township, Jalan Batu Kawa',      '93250', 'Kuching',           'Sarawak',          '082-456 634'],
        ['Asmawi bin Aripin',                'asmawi.aripin',      'No. 10B, Tingkat 2, Jalan Plumbum N 7/N, Seksyen 7',               '40000', 'Shah Alam',         'Selangor',         '03-5511 1600'],
        ['Najib bin Haji Seni',              'najib.seni',         'No. 1124, Tingkat Bawah Wisma Pahlawan, Jalan Kemajuan',           '21000', 'Kuala Terengganu',  'Terengganu',       '09-622 6946'],
    ];

    public function handle(): int
    {
        $gl = Agent::where('email', self::GL_EMAIL)->where('is_deleted', false)->first();
        if (!$gl) {
            $this->error('Could not find GL account: ' . self::GL_EMAIL);
            return self::FAILURE;
        }
        if ($gl->role !== 'GROUP_LEADER' || !$gl->group_label_id) {
            $this->error('cateow@generallink.my is not a Group Leader of an Organization Rewards Group.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Importing ' . count(self::ROWS) . ' regional Team Leaders under ' . $gl->full_name . ' (' . $gl->agent_code . ')...');

        $controller = app(SpecialGroupController::class);
        $created = 0;
        $skipped = 0;

        foreach (self::ROWS as [$fullName, $emailSlug, $address, $postcode, $city, $state, $phone]) {
            $email = $emailSlug . '@generallink.my';

            if (Agent::where('email', $email)->exists()) {
                $this->warn("SKIP (email already exists): {$fullName} <{$email}>");
                $skipped++;
                continue;
            }

            $this->line("  {$fullName} <{$email}> — {$city}, {$state} — {$phone}");

            if ($dryRun) {
                $created++;
                continue;
            }

            $controller->createAgentForImport([
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => $phone,
                'address'   => $address,
                'postcode'  => $postcode,
                'city'      => $city,
                'state'     => $state,
            ], 'TEAM_LEADER', $gl->agent_id, $gl->group_id, $gl->group_label_id);

            $created++;
        }

        $this->info(($dryRun ? '[DRY RUN] Would create' : 'Created') . " {$created} Team Leader(s), skipped {$skipped} (already existed).");
        return self::SUCCESS;
    }
}
