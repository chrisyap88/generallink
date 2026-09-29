<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Jul 2026 — per Chris: no role can ever delete a customer/
// prospect record. Instead, any agent (within their own scope) can set
// a customer's status to Inactive as the "soft removal" action, and
// Admin later runs a separate Housekeeping/Purge screen to actually
// clean up records that have sat Inactive for a while. INACTIVE is
// system-protected (is_system=true) because the Deactivate button and
// the Housekeeping screen both look this code up by name — Admin can
// still rename its description, just not its code or delete the row.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('customer_statuses')->where('code', 'INACTIVE')->exists()) {
            return;
        }

        DB::table('customer_statuses')->insert([
            'status_id'   => (string) Str::uuid(),
            'code'        => 'INACTIVE',
            'description' => 'Inactive',
            'is_active'   => true,
            'is_system'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('customer_statuses')->where('code', 'INACTIVE')->where('is_system', true)->delete();
    }
};
