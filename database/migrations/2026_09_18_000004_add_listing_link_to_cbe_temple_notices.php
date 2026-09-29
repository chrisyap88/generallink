<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per Chris: "where you have handle vendor that is
// interested to promote their marketplace package the entire workflow
// up to noticeboard blasting? where you have if a member interested to
// join as member as per the cbe offer?" A Temple Notice can now
// optionally promote one Marketplace Listing (a vendor's product, or
// the entity's own event/package) — when set, members reading the
// notice get a one-tap "I'm Interested" button that creates a
// Marketplace Order for themselves, no separate promotion feature
// built (reuses cbe_marketplace_listings/orders exactly as already
// approved under Task #418).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_temple_notices', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_temple_notices', 'listing_id')) {
                $table->uuid('listing_id')->nullable()->after('category');
                $table->foreign('listing_id')->references('listing_id')->on('cbe_marketplace_listings')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_temple_notices', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_temple_notices', 'listing_id')) {
                $table->dropForeign(['listing_id']);
                $table->dropColumn('listing_id');
            }
        });
    }
};
