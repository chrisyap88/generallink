<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Jul 2026 — Support Tickets (task #259). Per Chris: "make full
// use of EspoCRM" — this is the Help Desk / Case Management / Complaint
// Management / Service Request module from the EspoCRM Feature Reuse
// Review (EspoCRM_Feature_Reuse_Review.docx, Section 3). Every ticket
// created here also gets a matching Case in EspoCRM (free, core feature)
// via EspoCrmService::createCase() — same one-way-mirror pattern as
// Contacts/Tasks/Meetings/Opportunities/Campaigns. GeneralLink stays the
// only screen any agent ever uses; EspoCRM just keeps its own copy.
//
// ticket_type and priority are plain strings (not EspoCRM's own fixed
// enums) so GeneralLink's own vocabulary never breaks if Espo's enum
// options differ — EspoCrmService maps between the two defensively.
//
// due_at is the SLA target computed at creation time from priority (see
// SupportTicketController::slaHoursFor()) — checked by the
// tickets:check-sla scheduled command (task #260), which is
// GeneralLink's OWN escalation logic, not EspoCRM's paid Workflow/BPM
// add-on (see Feature Reuse Review, Section 5: "don't implement
// SLA/escalation inside EspoCRM").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_support_tickets', function (Blueprint $table) {
            $table->uuid('ticket_id')->primary();
            $table->uuid('customer_id');
            $table->uuid('owned_by_agent_id'); // who is handling this ticket — defaults to whoever created it

            $table->string('subject', 200);
            $table->text('description')->nullable();
            $table->string('ticket_type', 30)->default('QUESTION'); // COMPLAINT, SERVICE_REQUEST, QUESTION, OTHER
            $table->string('priority', 10)->default('MEDIUM'); // LOW, MEDIUM, HIGH
            $table->string('status', 20)->default('OPEN'); // OPEN, IN_PROGRESS, RESOLVED, CLOSED

            $table->timestamp('due_at')->nullable(); // SLA target, set at creation from priority
            $table->timestamp('sla_notified_at')->nullable(); // last time an SLA-breach reminder was sent (re-arms every 24h, same cooldown pattern as unclaimed commission reminders)
            $table->timestamp('resolved_at')->nullable();

            $table->string('espocrm_case_id', 100)->nullable();

            $table->boolean('is_deleted')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('owned_by_agent_id');
            $table->index('status');
            $table->index('due_at');

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->cascadeOnDelete();

            $table->foreign('owned_by_agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_support_tickets');
    }
};
