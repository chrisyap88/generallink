<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// -------------------------------------------------------
// NEW 3 Aug 2026 — per Chris (on behalf of the boss's brief): the
// embedded AI Assistant "listens to complaints, records them, and
// automatically creates a support ticket". Neither existing ticket
// system fits: help_desk_threads needs a specific human recipient
// (agent-to-agent only), and customer_support_tickets needs a real
// customer_id (customer complaints, not app-user complaints). This is
// a dedicated, lightweight table just for things the AI Assistant
// itself logs — from a logged-in agent OR an anonymous visitor on the
// login page (agent_id nullable for that case).
// -------------------------------------------------------
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_assistant_tickets')) {
            Schema::create('ai_assistant_tickets', function (Blueprint $table) {
                $table->uuid('ticket_id')->primary();
                $table->string('ticket_code', 20)->unique()->nullable(); // e.g. AIT-00001, shown to the user

                // Nullable — a visitor on the login page (not yet
                // logged in / not yet registered) can still report a
                // problem or ask a question.
                $table->uuid('agent_id')->nullable();
                $table->string('guest_name', 200)->nullable();
                $table->string('guest_email', 200)->nullable();

                $table->enum('source', ['LOGIN_PAGE', 'IN_APP'])->default('IN_APP');
                $table->string('page_context', 150)->nullable(); // e.g. "Override Claim Submission"
                $table->string('role_context', 30)->nullable();  // agent's role at the time, if logged in

                $table->enum('category', ['COMPLAINT', 'QUESTION', 'BUG_REPORT', 'OTHER'])->default('OTHER');
                $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH'])->default('MEDIUM');
                $table->string('summary', 255);
                $table->longText('transcript')->nullable(); // JSON — the conversation that led to this ticket

                $table->enum('status', ['OPEN', 'IN_PROGRESS', 'RESOLVED'])->default('OPEN');
                $table->uuid('handled_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('agent_id');
                $table->index('created_at');

                $table->foreign('agent_id')->references('agent_id')->on('agents')->nullOnDelete();
                $table->foreign('handled_by')->references('agent_id')->on('agents')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_tickets');
    }
};
