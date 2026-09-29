<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: Box 6 (Pending Tasks, Notifications &
// Alerts) needs "outstanding support tickets" scoped to a CBE node, so
// a Temple's Chairman sees only tickets tied to their own
// temple/organization. customer_support_tickets previously had no
// CBE-node link at all (a general platform feature). Nullable — most
// existing tickets are not CBE-related and stay unscoped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_support_tickets', 'cbe_node_id')) {
                $table->uuid('cbe_node_id')->nullable()->after('customer_id');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('set null');
                $table->index('cbe_node_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('customer_support_tickets', 'cbe_node_id')) {
                $table->dropForeign(['cbe_node_id']);
                $table->dropColumn('cbe_node_id');
            }
        });
    }
};
