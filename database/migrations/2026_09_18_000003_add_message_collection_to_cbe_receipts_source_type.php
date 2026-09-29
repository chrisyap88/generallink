<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per Chris: Treasury "confirm & issue" from a CBE
// Message (a payment notification/receipt request the Secretary,
// donor, or sponsor raised) reuses the SAME CbeReceiptService::issue()
// engine already built for donations/event sales/appointment fees —
// no second receipt-issuing engine. Adds one more source_type so this
// new origin is traceable, same idea as the existing three.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cbe_receipts MODIFY source_type ENUM('DONATION','EVENT_SALE','APPOINTMENT','MESSAGE_COLLECTION') NOT NULL");
        }
        // sqlite/others: the column already stores plain strings at the
        // application layer (Laravel's enum cast, not a DB-level
        // constraint on sqlite) — nothing to alter.
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cbe_receipts MODIFY source_type ENUM('DONATION','EVENT_SALE','APPOINTMENT') NOT NULL");
        }
    }
};
