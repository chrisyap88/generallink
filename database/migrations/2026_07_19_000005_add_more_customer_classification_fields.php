<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Jul 2026 — per Chris: three more Admin-configurable customer
// classification dimensions, same code/description/is_active/is_system
// convention as customer_statuses/customer_types (nothing hardcoded,
// nothing limited to a fixed count):
//   - customer_categories: Individual, Family, SME, Corporate,
//     Government, NGO/Charity, Association, Educational Institution
//   - occupation_groups: Government Officer, Healthcare, Education,
//     Banking & Finance, Insurance, Military, Police, Fire & Rescue,
//     Private Employee, Self-Employed, Business Owner, Professional,
//     Retired, Student, Housewife, Unemployed, Others
//   - customer_sources: Walk-in, Referral, Introducer, Agent, Broker,
//     Website, Facebook, Google, TikTok, WhatsApp, Existing Customer,
//     Campaign
// All three are optional tags (nullable FK on customers) — no business
// logic depends on any specific value, same treatment as customer_type.
//
// Also expands the EXISTING customer_statuses list (built earlier today)
// with 17 more pipeline/lifecycle stages Chris asked for (Pending,
// Quotation Issued, Policy Issued, Renewal Due, Claim Active, etc.).
// The 2 system-protected codes already seeded (ACTIVE, PROSPECT) are
// left untouched — the Prospect-to-Active auto-conversion on first Sales
// Transaction still depends on those exact codes — everything new here
// is a normal (is_system=false), freely editable/removable addition.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_categories', function (Blueprint $table) {
            $table->uuid('category_id')->primary();
            $table->string('code', 50)->unique();
            $table->string('description', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('occupation_groups', function (Blueprint $table) {
            $table->uuid('occupation_group_id')->primary();
            $table->string('code', 50)->unique();
            $table->string('description', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_sources', function (Blueprint $table) {
            $table->uuid('source_id')->primary();
            $table->string('code', 50)->unique();
            $table->string('description', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();

        $categories = ['Individual', 'Family', 'SME', 'Corporate', 'Government', 'NGO / Charity', 'Association', 'Educational Institution'];
        foreach ($categories as $desc) {
            DB::table('customer_categories')->insert([
                'category_id' => (string) Str::uuid(),
                'code'        => $this->toCode($desc),
                'description' => $desc,
                'is_active'   => true,
                'is_system'   => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $occupations = ['Government Officer', 'Healthcare', 'Education', 'Banking & Finance', 'Insurance', 'Military', 'Police', 'Fire & Rescue', 'Private Employee', 'Self-Employed', 'Business Owner', 'Professional', 'Retired', 'Student', 'Housewife', 'Unemployed', 'Others'];
        foreach ($occupations as $desc) {
            DB::table('occupation_groups')->insert([
                'occupation_group_id' => (string) Str::uuid(),
                'code'                => $this->toCode($desc),
                'description'         => $desc,
                'is_active'           => true,
                'is_system'           => false,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        $sources = ['Walk-in', 'Referral', 'Introducer', 'Agent', 'Broker', 'Website', 'Facebook', 'Google', 'TikTok', 'WhatsApp', 'Existing Customer', 'Campaign'];
        foreach ($sources as $desc) {
            DB::table('customer_sources')->insert([
                'source_id'   => (string) Str::uuid(),
                'code'        => $this->toCode($desc),
                'description' => $desc,
                'is_active'   => true,
                'is_system'   => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // Expand customer_statuses — skip any code that already exists
        // (ACTIVE, SUSPENDED, WITHDRAWN were already seeded earlier today).
        $statuses = ['Pending', 'In Progress', 'Waiting Documents', 'Under Review', 'Quotation Issued', 'Waiting Customer Response', 'Approved', 'Rejected', 'Policy Issued', 'Policy Expired', 'Renewal Due', 'Renewed', 'Claim Active', 'Claim Closed', 'Archived', 'Blacklisted', 'Deceased'];
        foreach ($statuses as $desc) {
            $code = $this->toCode($desc);
            if (DB::table('customer_statuses')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('customer_statuses')->insert([
                'status_id'   => (string) Str::uuid(),
                'code'        => $code,
                'description' => $desc,
                'is_active'   => true,
                'is_system'   => false,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('customer_category_id')->nullable()->after('customer_type_id');
            $table->uuid('occupation_group_id')->nullable()->after('customer_category_id');
            $table->uuid('source_id')->nullable()->after('occupation_group_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('customer_category_id')->references('category_id')->on('customer_categories')->nullOnDelete();
            $table->foreign('occupation_group_id')->references('occupation_group_id')->on('occupation_groups')->nullOnDelete();
            $table->foreign('source_id')->references('source_id')->on('customer_sources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['customer_category_id']);
            $table->dropForeign(['occupation_group_id']);
            $table->dropForeign(['source_id']);
            $table->dropColumn(['customer_category_id', 'occupation_group_id', 'source_id']);
        });
        Schema::dropIfExists('customer_sources');
        Schema::dropIfExists('occupation_groups');
        Schema::dropIfExists('customer_categories');
    }

    private function toCode(string $description): string
    {
        $code = strtoupper($description);
        $code = str_replace('&', 'AND', $code);
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code);
        return trim($code, '_');
    }
};
