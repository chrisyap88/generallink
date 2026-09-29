<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Sep 2026 -- per Chris: "a member can participate in
// marketplace therefore he or she can be a vendor" -- same Phase 1
// normalization already done for cbe_customers/cbe_donors on 18 Sep
// 2026 (migration 2026_09_18_000002), now extended to cbe_vendors.
// Additive only: nothing existing changes, every screen that already
// reads cbe_vendors keeps working exactly as before.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_vendors', 'agent_id')) {
                $table->uuid('agent_id')->nullable()->after('vendor_id');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_vendors', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_vendors', 'agent_id')) {
                $table->dropForeign(['agent_id']);
                $table->dropColumn('agent_id');
            }
        });
    }
};
