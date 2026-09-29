<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();

            $table->uuid('agent_id')->nullable();        // Who made the change
            $table->string('table_name', 100);           // Which table was changed
            $table->uuid('record_id');                   // Which record was changed
            $table->string('action', 20);                // CREATE, UPDATE, DELETE
            $table->json('before_value')->nullable();     // State before change
            $table->json('after_value')->nullable();      // State after change
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            // Write-once
            $table->timestamp('created_at')->useCurrent();

            $table->index('agent_id');
            $table->index('table_name');
            $table->index('record_id');
            $table->index('created_at');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // Login event log
        Schema::create('login_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();

            $table->uuid('agent_id')->nullable();
            $table->string('email_attempted', 200)->nullable();
            $table->boolean('success')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('agent_id');
            $table->index('created_at');
            $table->index('success');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // Notification templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();

            $table->string('template_name', 200);
            $table->enum('channel', ['EMAIL', 'SMS', 'WHATSAPP', 'IN_APP']);
            $table->enum('event_type', [
                'RENEWAL_90_DAYS',
                'RENEWAL_60_DAYS',
                'RENEWAL_30_DAYS',
                'RENEWAL_7_DAYS',
                'WELCOME',
                'EMAIL_VERIFICATION',
                'PASSWORD_RESET',
                'COMMISSION_CREDITED',
                'POINTS_CREDITED',
                'RANK_PROMOTED',
                'RANK_DEMOTED',
            ]);
            $table->string('subject', 500)->nullable();   // Email subject
            $table->text('body_template');                // With {placeholders}

            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['channel', 'event_type', 'is_active']);

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('audit_logs');
    }
};
