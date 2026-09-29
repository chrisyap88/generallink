<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            // Primary key
            $table->uuid('agent_id')->primary();

            // Hierarchical member code (e.g. C0001-0-1-2) — immutable once set
            $table->string('member_code', 100)->unique()->nullable();

            // Human-readable agent code (e.g. GL-00001)
            $table->string('agent_code', 20)->unique()->nullable();

            // Identity
            $table->string('full_name', 200);
            $table->string('email', 200)->unique();
            $table->string('password_hash', 255);
            $table->text('nric_encrypted');           // AES-256 encrypted
            $table->string('phone', 20);

            // Role & status
            $table->enum('role', [
                'INTRODUCER',
                'TEAM_LEADER',
                'GROUP_LEADER',
                'ADMIN',
            ]);
            $table->enum('status', [
                'ACTIVE',
                'INACTIVE',
                'TERMINATED',
                'RISK_DEBT',
                'RESIGNED',
                'DECEASED',
            ])->default('ACTIVE');

            // Hierarchy
            $table->uuid('parent_id')->nullable();           // Direct sponsor/upline
            $table->string('hierarchy_path', 1000)->default('/'); // /GL_ID/TL_ID/I_ID/
            $table->uuid('group_id')->nullable();            // Root GL of this agent's tree

            // Tier restriction (Module 2A)
            $table->tinyInteger('recruitable_tier_depth')->default(0);
            $table->boolean('recruitment_blocked')->default(false);

            // QR onboarding
            $table->string('qr_code_token', 100)->unique()->nullable();

            // Bank details (for commission payout)
            $table->string('bank_name', 100)->nullable();
            $table->text('bank_account_encrypted')->nullable(); // AES-256 encrypted

            // Admin bank details (only used when role = ADMIN)
            $table->string('admin_bank_name', 100)->nullable();
            $table->text('admin_bank_account_encrypted')->nullable();

            // Commission wallet
            $table->decimal('commission_balance', 15, 4)->default(0);

            // Email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token', 100)->nullable();

            // Security phrase (first-time login)
            $table->string('security_phrase', 255)->nullable();
            $table->boolean('security_phrase_set')->default(false);

            // Failed login tracking
            $table->tinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // Soft delete
            $table->boolean('is_deleted')->default(false);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps(); // created_at, updated_at

            // Indexes
            $table->index('parent_id');
            $table->index('group_id');
            $table->index('role');
            $table->index('status');
            $table->index('hierarchy_path');
            $table->index('member_code');
        });

        // Self-referencing FK for parent_id
        Schema::table('agents', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('agents');
    }
};
