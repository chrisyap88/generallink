<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — per Chris: "sales is 3 earning is 3 but the KPI
// dashboard already have top 3 sales and top 3 earning and view all so
// is duplicate display" — removes the SALES_COUNT and EARNING_INCOME
// badge categories entirely (already shown elsewhere) and replaces them
// with leadership/network badges that AREN'T shown anywhere else yet:
// TL Promotions, GL Promotions, and Network Size. Recruiting badges
// (RECRUIT_COUNT) are untouched. Deletes any already-earned
// SALES_COUNT/EARNING_INCOME awards along with the definitions — these
// are recognition-only badges with no reward value, so nothing
// financial is lost.
return new class extends Migration
{
    public function up(): void
    {
        $obsoleteCodes = ['FIRST_SALE', 'TEN_SALES', 'FIFTY_SALES', 'EARNING_1K', 'EARNING_10K', 'EARNING_50K'];

        DB::table('agent_badges')->whereIn('badge_code', $obsoleteCodes)->delete();
        DB::table('badge_definitions')->whereIn('badge_code', $obsoleteCodes)->delete();

        DB::statement("ALTER TABLE badge_definitions MODIFY COLUMN badge_type ENUM('RECRUIT_COUNT','TL_PROMOTIONS','GL_PROMOTIONS','NETWORK_SIZE') NOT NULL");

        $now = now();
        $badges = [
            ['FIRST_TL_PROMOTED',  'First TL Promoted',    'Someone in your downline was promoted to Team Leader', 'TL_PROMOTIONS', 1, 6],
            ['FIVE_TL_PROMOTED',   '5 TLs Promoted',       '5 people in your downline promoted to Team Leader', 'TL_PROMOTIONS', 5, 7],
            ['FIRST_GL_PROMOTED',  'First GL Promoted',    'Someone in your downline was promoted to Group Leader', 'GL_PROMOTIONS', 1, 8],
            ['THREE_GL_PROMOTED',  '3 GLs Promoted',       '3 people in your downline promoted to Group Leader', 'GL_PROMOTIONS', 3, 9],
            ['NETWORK_25',         'Network of 25',        'Your entire downline network reached 25 active members', 'NETWORK_SIZE', 25, 10],
            ['NETWORK_100',        'Network of 100',       'Your entire downline network reached 100 active members', 'NETWORK_SIZE', 100, 11],
        ];
        foreach ($badges as [$code, $name, $desc, $type, $threshold, $sort]) {
            DB::table('badge_definitions')->insert([
                'badge_code'      => $code,
                'badge_name'      => $name,
                'description'     => $desc,
                'badge_type'      => $type,
                'threshold_value' => $threshold,
                'sort_order'      => $sort,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function down(): void
    {
        $newCodes = ['FIRST_TL_PROMOTED', 'FIVE_TL_PROMOTED', 'FIRST_GL_PROMOTED', 'THREE_GL_PROMOTED', 'NETWORK_25', 'NETWORK_100'];
        DB::table('agent_badges')->whereIn('badge_code', $newCodes)->delete();
        DB::table('badge_definitions')->whereIn('badge_code', $newCodes)->delete();
        DB::statement("ALTER TABLE badge_definitions MODIFY COLUMN badge_type ENUM('RECRUIT_COUNT','SALES_COUNT','EARNING_INCOME') NOT NULL");
    }
};
