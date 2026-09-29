<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Recruitment Contests follow-up. Per Chris's answers:
// (1) add a Ranked Top-3 mode alongside the existing uncapped Threshold
// mode — Admin picks per contest; (2) a manual Announce/Remind button
// (not automatic) so Admin can both "inform and remind" eligible agents;
// (3) a dedicated Rules & Regulations field, separate from the general
// Description.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->string('contest_mode', 20)->default('THRESHOLD')->after('metric'); // THRESHOLD or RANKED_TOP3
            $table->text('rules_text')->nullable()->after('description');
            $table->decimal('reward_value_2nd', 15, 2)->nullable()->after('reward_value');
            $table->decimal('reward_value_3rd', 15, 2)->nullable()->after('reward_value_2nd');
            $table->timestamp('last_announced_at')->nullable()->after('is_active');
            $table->unsignedInteger('announce_count')->default(0)->after('last_announced_at');
        });

        Schema::table('recruitment_contest_awards', function (Blueprint $table) {
            // 1/2/3 for a RANKED_TOP3 contest's placement; null for a
            // THRESHOLD contest (no ranking there, so no placement).
            $table->unsignedTinyInteger('placement')->nullable()->after('achieved_value');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_contest_awards', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
        Schema::table('recruitment_contests', function (Blueprint $table) {
            $table->dropColumn(['contest_mode', 'rules_text', 'reward_value_2nd', 'reward_value_3rd', 'last_announced_at', 'announce_count']);
        });
    }
};
