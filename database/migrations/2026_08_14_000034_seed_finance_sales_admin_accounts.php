<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// REWRITTEN 14 Aug 2026, 3rd pass — per Chris's own tinker output: he
// already has three real ADMIN accounts —
//   Admin Finance  / finance@generallink.my  / department FINANCE  (created 09 Jul 2026)
//   Admin Sales    / sales@generallink.my    / department SALES    (created 09 Jul 2026)
//   Admin Director / admin@generallink.my    / department blank    (created 06 Jun 2026, predates the department column)
// — each with real linked data (commission_transactions etc). The first
// two versions of this migration wrongly tried to CREATE brand-new
// Finance/Sales admin accounts from scratch. That collided with these
// real accounts by unlucky agent_code coincidence, and its DELETE
// attempt very nearly wiped a real account before the commission_
// transactions foreign key constraint stopped it. No data was actually
// lost — the FK blocked it — but this migration is rewritten to stop
// trying to create anything at all.
//
// What it actually does now: resets the Finance and Sales admin
// passwords to Password@123 so Chris can log into each for testing (he
// asked for this explicitly). Admin Director's (admin@generallink.my)
// password is left completely untouched — that's Chris's own existing
// login, already known to him — only its blank department is backfilled
// to DIRECTOR, a plain single-column update with no relation to any
// other table, so no foreign key can ever block it.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('agents')->where('email', 'finance@generallink.my')->update([
            'password_hash' => Hash::make('Password@123'),
            'updated_at' => now(),
        ]);

        DB::table('agents')->where('email', 'sales@generallink.my')->update([
            'password_hash' => Hash::make('Password@123'),
            'updated_at' => now(),
        ]);

        DB::table('agents')
            ->where('email', 'admin@generallink.my')
            ->where(function ($q) {
                $q->whereNull('department')->orWhere('department', '');
            })
            ->update(['department' => 'DIRECTOR', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Deliberately a no-op — there is no reliable way to recover the
        // original password hashes to restore them, and leaving
        // admin@generallink.my's department as DIRECTOR causes no harm
        // even on rollback.
    }
};
