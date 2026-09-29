<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 27 Aug 2026 — per Chris: Outstanding Collection (Current
// Financial Snapshot) needs to know when an event participant hasn't
// paid in full yet — cbe_event_participants previously only recorded
// amount_paid, with no "amount owed" to compare against. Adds
// amount_due; existing rows are backfilled with amount_due =
// amount_paid (assumed fully paid, since no due amount was ever
// tracked before) so nothing shows as falsely outstanding retroactively
// — new entries going forward will set amount_due to the real price.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_event_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_event_participants', 'amount_due')) {
                $table->decimal('amount_due', 12, 2)->default(0)->after('quantity');
            }
        });

        DB::statement('UPDATE cbe_event_participants SET amount_due = amount_paid WHERE amount_due = 0');
    }

    public function down(): void
    {
        Schema::table('cbe_event_participants', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_event_participants', 'amount_due')) {
                $table->dropColumn('amount_due');
            }
        });
    }
};
