<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // Pending email — holds a NEW email while it waits for
        // re-verification. agents.email itself is never changed until
        // confirmed via the verification link (Decision: email edits
        // "follow the public registration method").
        // -----------------------------------------------------
        Schema::table('agents', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->string('pending_email_token')->nullable()->after('pending_email');
        });

        // -----------------------------------------------------
        // Reason codes — dropdown + optional free-text notes, for
        // Status Update (Terminate) and Delete actions. Agreed: dropdown
        // primary (clean, reportable, no typos) + optional notes for
        // detail (per discussion on "difficult to type standard reason").
        // -----------------------------------------------------
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->uuid('reason_code_id')->primary();
            $table->string('category'); // TERMINATION or DELETE
            $table->string('code');
            $table->string('description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // -----------------------------------------------------
        // audit_logs gets two new nullable columns. NULL for every
        // existing action type (PROFILE_UPDATE, ROLE_CHANGE, etc.) —
        // only Status/Delete actions will populate these going forward.
        // -----------------------------------------------------
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('reason_code_id')->nullable()->after('after_value');
            $table->text('reason_notes')->nullable()->after('reason_code_id');
        });

        // -----------------------------------------------------
        // Seed the starting reason codes we agreed on.
        // -----------------------------------------------------
        $now = now();
        DB::table('reason_codes')->insert([
            ['reason_code_id' => Str::uuid(), 'category' => 'TERMINATION', 'code' => 'VOLUNTARY_EXIT',      'description' => 'Voluntary Exit',                 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'TERMINATION', 'code' => 'NON_PERFORMANCE',      'description' => 'Non-Performance',                'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'TERMINATION', 'code' => 'POLICY_VIOLATION',     'description' => 'Policy Violation',               'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'TERMINATION', 'code' => 'CUSTOMER_COMPLAINT',   'description' => 'Customer Complaint',             'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'TERMINATION', 'code' => 'FRAUDULENT_ACTIVITY',  'description' => 'Fraudulent Activity',            'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'TERMINATION', 'code' => 'DECEASED',             'description' => 'Deceased',                       'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'TERMINATION', 'code' => 'OTHER',                'description' => 'Other',                          'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'DELETE',      'code' => 'DUPLICATE_ENTRY',      'description' => 'Duplicate Entry',                'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'DELETE',      'code' => 'DATA_ENTRY_ERROR',     'description' => 'Data Entry Error',               'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'DELETE',      'code' => 'REGISTRATION_ABANDONED','description' => 'Registration Abandoned',        'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'DELETE',      'code' => 'TEST_RECORD',          'description' => 'Test Record',                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['reason_code_id' => Str::uuid(), 'category' => 'DELETE',      'code' => 'OTHER',                'description' => 'Other',                          'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['reason_code_id', 'reason_notes']);
        });
        Schema::dropIfExists('reason_codes');
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_token']);
        });
    }
};
