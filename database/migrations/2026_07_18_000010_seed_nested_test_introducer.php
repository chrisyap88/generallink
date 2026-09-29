<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * NEW 18 Jul 2026 — one-off test data for Chris's requested test
 * scenario: an Introducer recruited by ANOTHER Introducer (not just
 * directly under a Team Leader), so he can confirm the just-fixed
 * DataScopeService recursive-visibility change actually works, using
 * the real Pacific & Orient policy (BFU6208 / Vasudavan a/l Sangaran)
 * we already tested together.
 *
 * Finds (or, if none exist yet, creates) an upline Introducer, then
 * creates a brand-new Introducer under THAT Introducer — an Indian
 * name, per Chris's explicit request — with a known email/password so
 * he can log straight in. Safe to re-run: skips if the test email
 * already exists.
 */
return new class extends Migration
{
    private const TEST_EMAIL = 'murali.krishnan@generallink.my';
    private const TEST_PASSWORD = 'Password@123';

    public function up(): void
    {
        if (DB::table('agents')->where('email', self::TEST_EMAIL)->exists()) {
            // Already created by a previous run of this migration.
            return;
        }

        // 1. Find an existing upline Introducer to recruit our new test
        //    Introducer under (this is what makes it "nested" — an
        //    Introducer below another Introducer, not just below a TL).
        $upline = DB::table('agents')
            ->where('role', 'INTRODUCER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->orderBy('created_at')
            ->first();

        // 2. None exist at all — create one first, under an existing
        //    Team Leader (or, failing that, Admin), so there's always
        //    a valid Introducer to nest the real test agent under.
        if (! $upline) {
            $tl = DB::table('agents')
                ->where('role', 'TEAM_LEADER')
                ->where('status', 'ACTIVE')
                ->where('is_deleted', false)
                ->orderBy('created_at')
                ->first();

            $baseParent = $tl ?: DB::table('agents')->where('role', 'ADMIN')->where('is_deleted', false)->first();

            if (! $baseParent) {
                // No agents at all yet (fresh, unseeded database) —
                // nothing sensible to attach to. Skip quietly rather
                // than fail the whole migration batch.
                return;
            }

            $baseId = Str::uuid()->toString();
            DB::table('agents')->insert([
                'agent_id'              => $baseId,
                'agent_code'            => 'I-TESTBASE',
                'full_name'             => 'Devi A/P Rajan',
                'email'                 => 'devi.rajan@generallink.my',
                'password_hash'         => Hash::make(self::TEST_PASSWORD),
                'phone'                 => '+60123456780',
                'role'                  => 'INTRODUCER',
                'status'                => 'ACTIVE',
                'parent_id'             => $baseParent->agent_id,
                'hierarchy_path'        => $baseParent->hierarchy_path . $baseId . '/',
                'group_id'              => $baseParent->group_id,
                'recruitable_tier_depth'=> 1,
                'recruitment_blocked'   => 0,
                'qr_code_token'         => Str::random(40),
                'commission_balance'    => 0,
                'email_verified_at'     => now(),
                'security_phrase_set'   => true,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            $upline = DB::table('agents')->where('agent_id', $baseId)->first();
        }

        // 3. Create the actual test Introducer — nested UNDER the
        //    upline Introducer above, Indian name per Chris's request,
        //    known login credentials.
        $newId = Str::uuid()->toString();
        DB::table('agents')->insert([
            'agent_id'              => $newId,
            'agent_code'            => 'I-TESTNEST',
            'full_name'             => 'Murali A/L Krishnan',
            'email'                 => self::TEST_EMAIL,
            'password_hash'         => Hash::make(self::TEST_PASSWORD),
            'phone'                 => '+60177654321',
            'role'                  => 'INTRODUCER',
            'status'                => 'ACTIVE',
            'parent_id'             => $upline->agent_id,
            'hierarchy_path'        => $upline->hierarchy_path . $newId . '/',
            'group_id'              => $upline->group_id,
            'recruitable_tier_depth'=> 0,
            'recruitment_blocked'   => 0,
            'qr_code_token'         => Str::random(40),
            'commission_balance'    => 0,
            'email_verified_at'     => now(),
            'security_phrase_set'   => true,
            'created_by'            => $upline->agent_id,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        DB::table('agent_profiles')->insert([
            'profile_id' => Str::uuid()->toString(),
            'agent_id'   => $newId,
            'address'    => 'No. 12, Jalan Test, Taman Uji',
            'city'       => 'Petaling Jaya',
            'state'      => 'Selangor',
            'postcode'   => '46000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('agents')->where('email', self::TEST_EMAIL)->delete();
        DB::table('agents')->where('email', 'devi.rajan@generallink.my')->delete();
    }
};
