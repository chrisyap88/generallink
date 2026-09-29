<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 — Task #83. Audit log for every WhatsApp send attempt
// through the Send WhatsApp Message tool (WhatsAppController::send()).
// Records who tried to message whom, whether it was allowed by the
// recipient-scoping rule (an agent may only message their own customers
// or their own upline/downline agents — same rule as Help Desk, via
// DataScopeService::verifyWhatsAppRecipient()), and the eventual send
// outcome. Admin can view the full log; nobody else can (Admin-only
// route, see Admin\WhatsAppAuditController).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_log', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('sender_agent_id');
            $table->string('recipient_phone', 20);
            $table->enum('recipient_type', ['CUSTOMER', 'AGENT', 'UNKNOWN'])->default('UNKNOWN');
            $table->uuid('recipient_id')->nullable(); // customer_id or agent_id, depending on recipient_type
            $table->string('recipient_name', 200)->nullable();
            $table->text('message');
            $table->enum('status', ['SENT', 'FAILED', 'BLOCKED'])->default('FAILED');
            $table->string('detail', 255)->nullable(); // block reason or send failure reason
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sender_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->index(['sender_agent_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_log');
    }
};
