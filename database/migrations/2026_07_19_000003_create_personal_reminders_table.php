<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 19 Jul 2026 — per Chris: an agent (e.g. Murali, an Introducer)
// wants to set his OWN personal follow-up reminders against a customer
// or Prospect — separate from the automatic, customer-facing renewal
// reminder already stored on insurance_renewal_schedules (task #94).
// These are reminders for the AGENT himself, e.g. "call this Prospect
// back in 3 months" or "call this customer before their car insurance
// lapses to try to close the renewal personally". Chris asked for
// multiple reminders per customer, each with its own type (call
// follow-up, renewal, and so on — kept as an open-ended string list
// rather than a rigid enum so more types can be added later without a
// migration).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_reminders', function (Blueprint $table) {
            $table->uuid('reminder_id')->primary();
            $table->uuid('agent_id');       // who set it / sees it on their Calendar
            $table->uuid('customer_id');    // which customer or Prospect this is about

            $table->string('reminder_type', 40)->default('CALL_FOLLOW_UP'); // CALL_FOLLOW_UP, RENEWAL, OTHER, ...
            $table->date('reminder_date');
            $table->text('note')->nullable();

            $table->enum('status', ['PENDING', 'DONE', 'DISMISSED'])->default('PENDING');
            $table->boolean('is_deleted')->default(false);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('customer_id');
            $table->index('reminder_date');
            $table->index('status');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->cascadeOnDelete();

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_reminders');
    }
};
