<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 13 Sep 2026 (Task #418) — per Chris's own standing rule: never
// hardcode a fixed choice list without a way to add more (same lesson
// already applied to GLADE Tiers and Faith/Practice Types). Campaign
// type is a small editable catalog, not a PHP enum, so Chris can add a
// new campaign type later without needing another migration.
// "Membership Recruitment Initiative" (targets a CBE entity's own
// non-member customers, converting marketplace buyers into members) is
// seeded as the first type since Chris named it explicitly.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_marketplace_campaign_types')) {
            Schema::create('cbe_marketplace_campaign_types', function (Blueprint $table) {
                $table->uuid('type_id')->primary();
                $table->string('type_name', 100)->unique();
                $table->string('type_name_zh', 100)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            $now = now();
            DB::table('cbe_marketplace_campaign_types')->insert([
                [
                    'type_id' => (string) Str::uuid(),
                    'type_name' => 'Membership Recruitment Initiative',
                    'type_name_zh' => '会员招募计划',
                    'description' => 'Targets non-member marketplace customers of this entity, encouraging them to become members.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'type_id' => (string) Str::uuid(),
                    'type_name' => 'General Promotion',
                    'type_name_zh' => '一般促销',
                    'description' => 'General marketplace promotion, not specifically aimed at membership conversion.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_marketplace_campaign_types');
    }
};
