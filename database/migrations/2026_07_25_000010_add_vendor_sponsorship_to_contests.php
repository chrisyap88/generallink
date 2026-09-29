<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #216). Vendor
// Co-Sponsored Campaigns — lets Admin attach a vendor-sponsored prize
// pool to a Recruitment Contest (insurance vendors often fund
// recruitment/sales pushes). Kept as a light extension of the existing
// recruitment_contests table rather than a new one, since a
// sponsorship is just extra information about a contest, not a
// separate concept with its own lifecycle.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->uuid('sponsor_vendor_id')->nullable()->after('reward_value');
            $table->decimal('sponsor_amount', 15, 2)->nullable()->after('sponsor_vendor_id');
            $table->text('sponsor_notes')->nullable()->after('sponsor_amount');

            $table->foreign('sponsor_vendor_id')->references('vendor_id')->on('vendors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->dropForeign(['sponsor_vendor_id']);
            $table->dropColumn(['sponsor_vendor_id', 'sponsor_amount', 'sponsor_notes']);
        });
    }
};
