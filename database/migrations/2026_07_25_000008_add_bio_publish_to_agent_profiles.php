<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #214). Public Agent
// Profile pages reuse the EXISTING agent_profiles.photo_path (already
// used by My Profile) rather than duplicating photo storage — this
// migration just adds the two new fields a public page needs: a short
// bio, and whether the agent has actually chosen to publish it. Nothing
// is public by default — is_published starts false for every existing
// agent.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('photo_path');
            $table->boolean('is_published')->default(false)->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropColumn(['bio', 'is_published']);
        });
    }
};
