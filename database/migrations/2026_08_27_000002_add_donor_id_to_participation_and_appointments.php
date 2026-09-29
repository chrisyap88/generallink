<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: "i want similar like member and
// customers with the tap with events participant... and appoint he
// have with the sensei." A donor (cbe_donors) is neither an agent nor
// a customer, so it can't reuse the existing agent_id/customer_id
// pair — this extends the same nullable-owner convention with a third
// optional column. Exactly one of agent_id/customer_id/donor_id is
// set per row, never more than one.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cbe_event_participants') && ! Schema::hasColumn('cbe_event_participants', 'donor_id')) {
            Schema::table('cbe_event_participants', function (Blueprint $table) {
                $table->uuid('donor_id')->nullable()->after('customer_id');
                $table->foreign('donor_id')->references('donor_id')->on('cbe_donors')->onDelete('cascade');
                $table->index('donor_id');
            });
        }

        if (Schema::hasTable('cbe_appointments') && ! Schema::hasColumn('cbe_appointments', 'donor_id')) {
            Schema::table('cbe_appointments', function (Blueprint $table) {
                $table->uuid('donor_id')->nullable()->after('customer_id');
                $table->foreign('donor_id')->references('donor_id')->on('cbe_donors')->onDelete('cascade');
                $table->index('donor_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cbe_event_participants') && Schema::hasColumn('cbe_event_participants', 'donor_id')) {
            Schema::table('cbe_event_participants', function (Blueprint $table) {
                $table->dropForeign(['donor_id']);
                $table->dropColumn('donor_id');
            });
        }

        if (Schema::hasTable('cbe_appointments') && Schema::hasColumn('cbe_appointments', 'donor_id')) {
            Schema::table('cbe_appointments', function (Blueprint $table) {
                $table->dropForeign(['donor_id']);
                $table->dropColumn('donor_id');
            });
        }
    }
};
