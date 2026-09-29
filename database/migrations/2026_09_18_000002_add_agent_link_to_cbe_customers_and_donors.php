<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per Chris: "customer or donor from temple A ... same
// in temple B ... other cbe like rotary club" and "it has to store in
// member profile ... this is called normalization." Phase 1 (safe,
// additive): link cbe_customers and cbe_donors back to an existing
// Member profile (agents) when the same real person already has one,
// so their name/phone/email are read from ONE place instead of being
// re-typed on every CBE they interact with. Nothing existing is
// changed or removed — every current screen/report/query that reads
// cbe_customers or cbe_donors keeps working exactly as before.
// Phase 2 (separate, larger task) will actually merge cbe_donors into
// cbe_customers and update the ~10 dependent screens — deliberately
// NOT done in this migration to avoid breaking KPI/Accounting/Event
// Report screens that read cbe_donors directly today.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_customers', 'agent_id')) {
                $table->uuid('agent_id')->nullable()->after('cbe_node_id');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            }
            if (! Schema::hasColumn('cbe_customers', 'is_donor')) {
                $table->boolean('is_donor')->default(false)->after('agent_id');
            }
        });

        Schema::table('cbe_donors', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_donors', 'agent_id')) {
                $table->uuid('agent_id')->nullable()->after('cbe_node_id');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_donors', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_donors', 'agent_id')) {
                $table->dropForeign(['agent_id']);
                $table->dropColumn('agent_id');
            }
        });

        Schema::table('cbe_customers', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_customers', 'is_donor')) {
                $table->dropColumn('is_donor');
            }
            if (Schema::hasColumn('cbe_customers', 'agent_id')) {
                $table->dropForeign(['agent_id']);
                $table->dropColumn('agent_id');
            }
        });
    }
};
