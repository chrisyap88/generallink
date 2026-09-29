<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — per Chris's Free vs Subscription business model for
// the 3 CBE admin roles (Director/Finance/Membership): every organization
// on the platform (a row in group_labels) is either FREE (standalone,
// single-unit — e.g. Rotary Club Uptown with no HQ/State/Branch) or PAID
// (unlocks multi-level HQ->State->Branch->Temple management, consolidated
// reports, advanced analytics, etc.). This is the ONE gate every
// "Subscription Version" feature checks — see CbeFeatureGateService.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_labels') && ! Schema::hasColumn('group_labels', 'subscription_tier')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->enum('subscription_tier', ['FREE', 'PAID'])->default('FREE')->after('group_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_labels') && Schema::hasColumn('group_labels', 'subscription_tier')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->dropColumn('subscription_tier');
            });
        }
    }
};
