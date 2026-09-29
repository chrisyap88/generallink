<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 27 Aug 2026 — per Chris (dictated verbatim, see master spec
// Section 56): GLADE Public Model — 6 membership categories based on
// number of covered users, with an admin-editable Annual Platform Fee
// per category. ALL figures below are DEFAULT/SEED values only — the
// administrator must be able to edit every figure; nothing is hardcoded
// in application code, only seeded here as sensible starting defaults
// (same self-seeding convention as customer_categories/survey_categories).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_glade_membership_tiers')) {
            Schema::create('cbe_glade_membership_tiers', function (Blueprint $table) {
                $table->uuid('tier_id')->primary();
                $table->string('tier_code', 30)->unique(); // INDIVIDUAL, FAMILY, BUSINESS, BUSINESS_PLUS, ENTERPRISE, COMMUNITY
                $table->string('tier_name', 100);
                $table->unsignedInteger('max_users')->nullable(); // null = admin-configurable / no fixed cap (Enterprise, Community)
                $table->decimal('annual_fee', 12, 2)->nullable(); // null = custom quotation, no fixed fee
                $table->boolean('is_custom_quotation')->default(false);
                $table->string('available_for', 200)->nullable(); // comma list: individual,family,business,enterprise,community
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            $tiers = [
                ['code' => 'INDIVIDUAL', 'name' => 'Individual', 'max' => 1, 'fee' => 60.00, 'custom' => false, 'for' => 'individual', 'sort' => 1],
                ['code' => 'FAMILY', 'name' => 'Family', 'max' => 5, 'fee' => 240.00, 'custom' => false, 'for' => 'family', 'sort' => 2],
                ['code' => 'BUSINESS', 'name' => 'Business', 'max' => 20, 'fee' => 720.00, 'custom' => false, 'for' => 'business', 'sort' => 3],
                ['code' => 'BUSINESS_PLUS', 'name' => 'Business Plus', 'max' => 100, 'fee' => 3000.00, 'custom' => false, 'for' => 'business', 'sort' => 4],
                ['code' => 'ENTERPRISE', 'name' => 'Enterprise', 'max' => 1000, 'fee' => null, 'custom' => true, 'for' => 'enterprise', 'sort' => 5],
                ['code' => 'COMMUNITY', 'name' => 'Community', 'max' => null, 'fee' => null, 'custom' => true, 'for' => 'community', 'sort' => 6],
            ];
            foreach ($tiers as $t) {
                DB::table('cbe_glade_membership_tiers')->insert([
                    'tier_id' => (string) Str::uuid(),
                    'tier_code' => $t['code'],
                    'tier_name' => $t['name'],
                    'max_users' => $t['max'],
                    'annual_fee' => $t['fee'],
                    'is_custom_quotation' => $t['custom'],
                    'available_for' => $t['for'],
                    'is_active' => true,
                    'sort_order' => $t['sort'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_glade_membership_tiers');
    }
};
