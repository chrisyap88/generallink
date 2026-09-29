<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 15 Sep 2026 — per Chris: "the temple/NGO committee team or SME CBE
// group management team name position like the President, deputy,
// secretary, treasurer not hardcoded... same for SME CBE group like
// CEO, Finance director etc. you should have a master file to set up
// the position according to the cbe group." Same "never hardcode a
// fixed list" rule already applied to Faith/Practice Types and GLADE
// Membership Tiers: one shared, fully Admin-editable catalog of
// committee/management position titles. A community picks whichever
// positions apply to it (checkboxes, same UI pattern as Appointment
// Positions) — nothing here assumes a group is a temple/NGO vs an
// SME, so the same catalog covers both; Admin can add more positions
// at any time via the Committee/Management Positions screen.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_committee_position_types')) {
            Schema::create('cbe_committee_position_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code')->unique();
                $table->string('position_label', 150);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            $rows = [
                // NGO / Temple-style committee positions
                ['code' => 'PRESIDENT', 'position_label' => 'President'],
                ['code' => 'DEPUTY_PRESIDENT', 'position_label' => 'Deputy President'],
                ['code' => 'SECRETARY', 'position_label' => 'Secretary'],
                ['code' => 'TREASURER', 'position_label' => 'Treasurer'],
                // SME / Business-style management positions
                ['code' => 'CEO', 'position_label' => 'Chief Executive Officer (CEO)'],
                ['code' => 'FINANCE_DIRECTOR', 'position_label' => 'Finance Director'],
            ];

            DB::table('cbe_committee_position_types')->insert(array_map(function ($r, $i) use ($now) {
                return [
                    'id' => (string) Str::uuid(),
                    'code' => $r['code'],
                    'position_label' => $r['position_label'],
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $rows, array_keys($rows)));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_committee_position_types');
    }
};
