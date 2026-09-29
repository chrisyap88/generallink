<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 27 Aug 2026 — per Chris: Appointments tab must also cover
// external appointments (press interview, VIP visit) — cbe_appointments
// previously REQUIRED the person seen to already be a registered Member
// or Customer (agent_id/customer_id). agent_id/customer_id were already
// nullable at the DB level (the "must pick one" rule was enforced in the
// controller, not the schema), so no change needed there — this
// migration adds the external-visitor name/org fields plus 2 new
// appointment_type values.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_appointments', 'external_visitor_name')) {
                $table->string('external_visitor_name', 200)->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('cbe_appointments', 'external_visitor_org')) {
                $table->string('external_visitor_org', 200)->nullable()->after('external_visitor_name');
            }
        });

        DB::statement("ALTER TABLE cbe_appointments MODIFY appointment_type ENUM('PRAYER','COUNSELING','BLESSING','PRESS_INTERVIEW','VIP_VISIT','OTHER') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cbe_appointments MODIFY appointment_type ENUM('PRAYER','COUNSELING','BLESSING','OTHER') NOT NULL");

        Schema::table('cbe_appointments', function (Blueprint $table) {
            foreach (['external_visitor_org', 'external_visitor_name'] as $col) {
                if (Schema::hasColumn('cbe_appointments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
