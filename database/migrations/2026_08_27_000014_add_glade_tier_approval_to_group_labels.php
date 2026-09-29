<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris (Task #233): the GLADE Public Model's 6
// membership categories (cbe_glade_membership_tiers — Individual/Family/
// Business/Business Plus/Enterprise/Community, each with its own Annual
// Platform Fee) existed only as a seeded catalog with nothing on a real
// community record pointing at one. This links a CBE community
// (group_labels) to its chosen catalog tier, plus a lightweight
// two-person approval flow: whoever proposes/changes a community's
// tier is not the same click that activates it — a second Admin must
// approve before it's billed/active. Deliberately separate from the
// existing subscription_tier (FREE/PAID) column, which stays the one
// technical feature gate CbeFeatureGateService reads; this is the
// business/billing category shown to the community and Finance, purely
// informational to the app's feature logic for now.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_labels') && ! Schema::hasColumn('group_labels', 'glade_tier_id')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->uuid('glade_tier_id')->nullable()->after('subscription_tier');
                $table->enum('glade_tier_status', ['NONE', 'PENDING_APPROVAL', 'ACTIVE'])->default('NONE')->after('glade_tier_id');
                $table->uuid('glade_tier_requested_by')->nullable()->after('glade_tier_status');
                $table->timestamp('glade_tier_requested_at')->nullable()->after('glade_tier_requested_by');
                $table->uuid('glade_tier_approved_by')->nullable()->after('glade_tier_requested_at');
                $table->timestamp('glade_tier_approved_at')->nullable()->after('glade_tier_approved_by');

                $table->foreign('glade_tier_id')->references('tier_id')->on('cbe_glade_membership_tiers')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_labels') && Schema::hasColumn('group_labels', 'glade_tier_id')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->dropForeign(['glade_tier_id']);
                $table->dropColumn(['glade_tier_id', 'glade_tier_status', 'glade_tier_requested_by', 'glade_tier_requested_at', 'glade_tier_approved_by', 'glade_tier_approved_at']);
            });
        }
    }
};
