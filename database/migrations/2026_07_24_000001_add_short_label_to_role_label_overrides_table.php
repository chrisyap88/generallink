<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 24 Jul 2026 — per Chris: he wants to type his OWN short badge
// text (e.g. for the org tree chart / dashboard drilldown role tags)
// instead of the auto-generated 3-letter short form. Nullable —
// leaving it blank keeps the auto-derived short label from
// RoleLabelService::shortLabel() (initials/truncation), so existing
// deployments that never touch this column behave exactly as before.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->string('short_label', 3)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->dropColumn('short_label');
        });
    }
};
