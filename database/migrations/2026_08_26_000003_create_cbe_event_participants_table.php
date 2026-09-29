<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Aug 2026, 15th pass — per Chris: "would we able to see this
// member how many times he or she visit or participate the temple/
// club event and history of he participate events?? or participate
// any purchase from the temple/Club like anniversary dinner ticket?"
// No table anywhere linked a specific person to a specific event
// before this — cbe_contributions only tracks DONORS (cbe_donors),
// never an agent or customer buying a ticket/package. One row here =
// one person's participation/purchase at one event. Either agent_id
// OR customer_id is set (never both) — same event can have Members
// (agents) and Customers (policyholders) attending side by side, e.g.
// an anniversary dinner where both an agent and a customer bought a
// ticket. Counting/history for a person is just
// `WHERE agent_id = X` or `WHERE customer_id = X`, ordered by paid_at.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_event_participants')) {
            Schema::create('cbe_event_participants', function (Blueprint $table) {
                $table->uuid('participant_record_id')->primary();
                $table->uuid('event_id');
                $table->uuid('agent_id')->nullable();
                $table->uuid('customer_id')->nullable();
                $table->string('item_name', 150); // e.g. "Anniversary Dinner Ticket", "Prayer Package"
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->date('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable();
                $table->timestamps();
                // (recorded_by is nullable above so the 'set null'
                // onDelete on its FK below is valid.)

                $table->foreign('event_id')->references('event_id')->on('cbe_events')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->index(['event_id']);
                $table->index(['agent_id']);
                $table->index(['customer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_event_participants');
    }
};
