<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #389) — per Chris's Temple/NGO GL spec, Chart of
// Accounts section: a "Created By / Date" field. created_at already
// existed (Laravel timestamps), but no user attribution column did.
// Nullable because every pre-existing account (the platform's shared
// default chart, plus anything created before this migration) has no
// real "who created it" answer — new accounts from now on always set it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_chart_of_accounts', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('description');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_chart_of_accounts', 'created_by')) {
                $table->dropForeign(['cbe_chart_of_accounts_created_by_foreign']);
                $table->dropColumn('created_by');
            }
        });
    }
};
