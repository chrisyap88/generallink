<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Jul 2026 — per Chris, two separate, fully user-configurable
// classifications for customers (same code/description/is_active
// convention as reason_codes — Admin can add/edit/deactivate rows via
// a Master File Maintenance screen, never hardcoded in PHP):
//
//   1. customer_statuses — the customer's LIFECYCLE STATE. Seeded with
//      ACTIVE, PROSPECT, SUSPENDED, WITHDRAWN. ACTIVE and PROSPECT are
//      marked is_system=true because business logic keys off them
//      specifically (a Prospect auto-converts to Active the moment a
//      real Sales Transaction is submitted for them) — their CODE can't
//      be renamed or deleted, but description/is_active still can be
//      edited, and Admin can freely add more statuses (e.g. a custom
//      "Blacklisted" status) alongside them.
//   2. customer_types — an optional demographic/segment TAG, fully
//      open-ended (VIP, Expatriate, Government Servant, Army,
//      Professional, or whatever Admin adds later). No status-like
//      business logic depends on any specific one of these, so none
//      are marked is_system — seeded only as a helpful starting point.
//
// customers.customer_type (the hardcoded 2-value enum from the
// previous migration, not yet relied on anywhere) is replaced here with
// customers.status_id (required, FK) and customers.customer_type_id
// (optional, FK, nullable).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_statuses', function (Blueprint $table) {
            $table->uuid('status_id')->primary();
            $table->string('code', 50)->unique();
            $table->string('description', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // protects code from rename/delete
            $table->timestamps();
        });

        Schema::create('customer_types', function (Blueprint $table) {
            $table->uuid('type_id')->primary();
            $table->string('code', 50)->unique();
            $table->string('description', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();

        $activeId = (string) Str::uuid();
        $prospectId = (string) Str::uuid();
        DB::table('customer_statuses')->insert([
            ['status_id' => $activeId,             'code' => 'ACTIVE',     'description' => 'Active',     'is_active' => true, 'is_system' => true,  'created_at' => $now, 'updated_at' => $now],
            ['status_id' => $prospectId,           'code' => 'PROSPECT',   'description' => 'Prospect',   'is_active' => true, 'is_system' => true,  'created_at' => $now, 'updated_at' => $now],
            ['status_id' => (string) Str::uuid(),  'code' => 'SUSPENDED',  'description' => 'Suspended',  'is_active' => true, 'is_system' => true,  'created_at' => $now, 'updated_at' => $now],
            ['status_id' => (string) Str::uuid(),  'code' => 'WITHDRAWN',  'description' => 'Withdrawn',  'is_active' => true, 'is_system' => true,  'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('customer_types')->insert([
            ['type_id' => (string) Str::uuid(), 'code' => 'VIP',                 'description' => 'VIP',                  'is_active' => true, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['type_id' => (string) Str::uuid(), 'code' => 'EXPATRIATE',          'description' => 'Expatriate',           'is_active' => true, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['type_id' => (string) Str::uuid(), 'code' => 'GOVERNMENT_SERVANT',  'description' => 'Government Servant',   'is_active' => true, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['type_id' => (string) Str::uuid(), 'code' => 'ARMY',                'description' => 'Army',                 'is_active' => true, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
            ['type_id' => (string) Str::uuid(), 'code' => 'PROFESSIONAL',        'description' => 'Professional',         'is_active' => true, 'is_system' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('status_id')->nullable()->after('customer_id');
            $table->uuid('customer_type_id')->nullable()->after('status_id');
        });

        // Backfill every existing customer row. Defensive: the earlier
        // migration's hardcoded customer_type enum column may or may not
        // exist depending on whether it already ran — either way, every
        // existing row becomes ACTIVE (a real customer already on file)
        // except any already flagged PROSPECT under the old scheme.
        if (Schema::hasColumn('customers', 'customer_type')) {
            DB::table('customers')->where('customer_type', 'PROSPECT')->update(['status_id' => $prospectId]);
            DB::table('customers')->where(function ($q) {
                $q->where('customer_type', '!=', 'PROSPECT')->orWhereNull('customer_type');
            })->update(['status_id' => $activeId]);
        } else {
            DB::table('customers')->update(['status_id' => $activeId]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('status_id')->nullable(false)->change();
            $table->foreign('status_id')->references('status_id')->on('customer_statuses')->restrictOnDelete();
            $table->foreign('customer_type_id')->references('type_id')->on('customer_types')->nullOnDelete();
        });

        if (Schema::hasColumn('customers', 'customer_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('customer_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropForeign(['customer_type_id']);
            $table->dropColumn(['status_id', 'customer_type_id']);
        });
        Schema::dropIfExists('customer_types');
        Schema::dropIfExists('customer_statuses');
    }
};
