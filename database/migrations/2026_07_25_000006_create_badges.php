<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #212). Milestone
// badges off data that already exists (recruit count, sales count,
// lifetime earning income) — no schedule/quota needed, just a
// celebratory notification the moment a milestone is crossed. Purely
// personal (not whole-team like Breakaway/Contests), read-only against
// existing tables — never writes to commission_transactions or
// sales_transactions, never touches CommissionEngine.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_definitions', function (Blueprint $table) {
            $table->string('badge_code', 40)->primary();
            $table->string('badge_name', 100);
            $table->string('description', 200)->nullable();
            $table->enum('badge_type', ['RECRUIT_COUNT', 'SALES_COUNT', 'EARNING_INCOME']);
            $table->decimal('threshold_value', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_badges', function (Blueprint $table) {
            $table->uuid('badge_award_id')->primary();
            $table->uuid('agent_id');
            $table->string('badge_code', 40);
            $table->timestamp('achieved_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'badge_code'], 'ab_agent_badge_unique');

            $table->foreign('agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('badge_code')->references('badge_code')->on('badge_definitions')->cascadeOnDelete();
        });

        $now = now();
        $badges = [
            ['FIRST_RECRUIT',   'First Recruit',        'Recruited your first active team member', 'RECRUIT_COUNT', 1, 1],
            ['FIVE_RECRUITS',   '5 Recruits',            'Recruited 5 active team members', 'RECRUIT_COUNT', 5, 2],
            ['TEN_RECRUITS',    '10 Recruits',           'Recruited 10 active team members', 'RECRUIT_COUNT', 10, 3],
            ['TWENTYFIVE_RECRUITS', '25 Recruits',       'Recruited 25 active team members', 'RECRUIT_COUNT', 25, 4],
            ['FIFTY_RECRUITS',  '50 Recruits',           'Recruited 50 active team members', 'RECRUIT_COUNT', 50, 5],
            ['FIRST_SALE',      'First Sale',            'Closed your first confirmed policy', 'SALES_COUNT', 1, 6],
            ['TEN_SALES',       '10 Sales',              'Closed 10 confirmed policies', 'SALES_COUNT', 10, 7],
            ['FIFTY_SALES',     '50 Sales',              'Closed 50 confirmed policies', 'SALES_COUNT', 50, 8],
            ['EARNING_1K',      'RM1,000 Earned',        'Reached RM1,000 in lifetime Earning Income', 'EARNING_INCOME', 1000, 9],
            ['EARNING_10K',     'RM10,000 Earned',       'Reached RM10,000 in lifetime Earning Income', 'EARNING_INCOME', 10000, 10],
            ['EARNING_50K',     'RM50,000 Earned',       'Reached RM50,000 in lifetime Earning Income', 'EARNING_INCOME', 50000, 11],
        ];
        foreach ($badges as [$code, $name, $desc, $type, $threshold, $sort]) {
            DB::table('badge_definitions')->insert([
                'badge_code'      => $code,
                'badge_name'      => $name,
                'description'     => $desc,
                'badge_type'      => $type,
                'threshold_value' => $threshold,
                'sort_order'      => $sort,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_badges');
        Schema::dropIfExists('badge_definitions');
    }
};
