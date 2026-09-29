<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: Box 6 (Pending Tasks, Notifications &
// Alerts) needs a way for Finance/Sales/Membership Admin to send the
// Chairman something needing approval (payment voucher, waiver, claim,
// membership approval, credit/debit note, purchase order, etc.),
// optionally attaching a PDF printed from the relevant AP-module
// screen. Chris explicitly said this is NOT a fully automatic workflow
// engine — it's a simple internal message the Chairman approves via
// OTP, the SAME pattern as the existing Vendor Welcome Letter OTP
// acceptance flow (vendor_agreement_acceptances / vendor_agreement_otp_log).
// cbe_internal_approval_messages mirrors vendor_agreement_acceptances
// (one live/mutable row per message, OTP challenge fields); the log
// table mirrors vendor_agreement_otp_log (append-only compliance trail).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_internal_approval_messages')) {
            Schema::create('cbe_internal_approval_messages', function (Blueprint $table) {
                $table->uuid('message_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('sender_agent_id'); // Finance/Sales/Membership Admin raising the request
                $table->uuid('recipient_agent_id'); // Chairman/Director who must approve
                $table->string('category', 40)->default('OTHER'); // PAYMENT_VOUCHER, WAIVER, CLAIM, MEMBERSHIP_APPROVAL, CREDIT_NOTE, DEBIT_NOTE, PURCHASE_ORDER, OTHER
                $table->string('subject', 255);
                $table->text('body')->nullable();
                $table->string('attachment_path', 500)->nullable();
                $table->string('attachment_original_name', 255)->nullable();

                // OTP challenge, in progress until approved — same shape as vendor_agreement_acceptances
                $table->string('otp_code', 6)->nullable();
                $table->timestamp('otp_sent_at')->nullable();
                $table->timestamp('otp_expires_at')->nullable();
                $table->unsignedTinyInteger('otp_attempts')->default(0);

                $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
                $table->timestamp('responded_at')->nullable();
                $table->text('response_note')->nullable();

                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('sender_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('recipient_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['recipient_agent_id', 'status'], 'cbe_approval_msg_recipient_status_idx');
                $table->index(['cbe_node_id', 'status']);
            });
        }

        if (! Schema::hasTable('cbe_internal_approval_otp_log')) {
            Schema::create('cbe_internal_approval_otp_log', function (Blueprint $table) {
                $table->uuid('log_id')->primary();
                $table->uuid('message_id');
                $table->string('event_type', 40); // OTP_SENT, OTP_VERIFY_SUCCESS, OTP_VERIFY_FAILED, OTP_VERIFY_EXPIRED
                $table->string('recipient_email', 200)->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('message_id')->references('message_id')->on('cbe_internal_approval_messages')->onDelete('cascade');
                $table->index(['message_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_internal_approval_otp_log');
        Schema::dropIfExists('cbe_internal_approval_messages');
    }
};
