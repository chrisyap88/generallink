<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Jul 2026 — tracks a customer's response to a renewal reminder
// ("Yes Renew, please give me the renewal insurance coverage
// quotation" / No / Need to discuss), and whether the owning
// Introducer has actually sent that quotation back yet. Per-level
// reminder timestamps (Introducer -> TL -> GL -> Admin) mirror the
// existing pending_approvals + CheckApprovalReminders escalation
// pattern already used elsewhere in this app — same idea, applied to
// a new use case, so a reminder can never double-send.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_quotation_requests', function (Blueprint $table) {
            $table->uuid('request_id')->primary();

            $table->uuid('policy_id');     // the policy being renewed
            $table->uuid('customer_id');
            // Owning agent AT THE TIME of this request — copied from
            // customers.owned_by_agent_id rather than looked up live,
            // so this request's history stays accurate even if
            // ownership is reassigned later.
            $table->uuid('agent_id');

            $table->enum('decision', ['YES', 'NO', 'DISCUSS'])->default('YES');
            $table->enum('status', ['REQUESTED', 'QUOTATION_SENT', 'CANCELLED'])->default('REQUESTED');

            $table->timestamp('requested_at');
            $table->timestamp('quotation_sent_at')->nullable();
            $table->uuid('quotation_sent_by')->nullable();

            // Escalation trail — each set once, never re-sent.
            $table->timestamp('introducer_reminder_sent_at')->nullable();
            $table->timestamp('tl_reminder_sent_at')->nullable();
            $table->timestamp('gl_reminder_sent_at')->nullable();
            $table->timestamp('admin_escalation_sent_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index('policy_id');
            $table->index('customer_id');
            $table->index('agent_id');

            $table->foreign('policy_id')->references('policy_id')->on('sales_transactions')->cascadeOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customers')->cascadeOnDelete();
            $table->foreign('agent_id')->references('agent_id')->on('agents')->restrictOnDelete();
            $table->foreign('quotation_sent_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_quotation_requests');
    }
};
