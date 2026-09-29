<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Jul 2026 — EspoCRM integration, expanded scope (task #254). Every
// Broadcast Campaign (Growth & Outreach Center) also gets a matching
// Campaign record in EspoCRM, with its status kept in sync
// (SCHEDULED/DRAFT -> Planning, SENT -> Complete). Remembers the EspoCRM
// Campaign id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->string('espocrm_campaign_id', 100)->nullable()->after('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropColumn('espocrm_campaign_id');
        });
    }
};
