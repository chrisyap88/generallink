<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 24 Jul 2026 — bug fix. audit_logs.action was created as
// varchar(20), but many action names already used throughout the app
// are longer than that (e.g. COMMISSION_STRUCTURE_CREATED = 28 chars,
// PRODUCT_STATUS_TOGGLED = 22, EMAIL_CHANGE_CONFIRMED = 23). On a MySQL
// connection running in strict mode this throws a hard "Data too long
// for column 'action'" error and the whole save fails — first surfaced
// via the commissions:seed-motor-structures command, but every one of
// those longer action names (Product/Vendor/Commission Structure
// create-update-toggle, email-change-confirmed, etc.) was equally at
// risk any time it ran. Widened to 50 to safely fit everything in use
// today with headroom for new action names later.
//
// Raw SQL (not Schema::table()->change()) because that helper needs the
// doctrine/dbal package, which isn't installed in this project.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE audit_logs MODIFY action VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_logs MODIFY action VARCHAR(20) NOT NULL');
    }
};
