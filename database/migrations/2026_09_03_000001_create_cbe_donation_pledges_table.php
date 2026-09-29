<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #366) — per Chris's Donor/Pledge spec: a
// "Donation Pledge" is a promise by a donor to give a specific amount,
// GL-integrated and donor-scoped — deliberately separate from
// cbe_contributions (the event-scoped sponsorship/auction register,
// which is NOT individually posted to GL — see CbeReceiptService header
// for why: event income is posted once in aggregate when the event
// closes, so posting each pledge there too would double-count it).
// A Donation Pledge is not tied to any event — it behaves like an AR
// Invoice but for a donor instead of a trade customer: the pledge
// itself posts a receivable the moment it's recorded (Dr Pledges
// Receivable / Cr Income), and each amount actually received against
// it posts Dr Bank / Cr Pledges Receivable, via its own dedicated GL
// control account (CbeAccountingService::PLEDGE_RECEIVABLE_CODE) so it
// never mixes with the AR Trade Debtors balance.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_donation_pledges')) {
            Schema::create('cbe_donation_pledges', function (Blueprint $table) {
                $table->uuid('pledge_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('donor_id');
                $table->uuid('category_id')->nullable();
                $table->uuid('fund_id')->nullable();
                $table->string('pledge_no', 30);
                $table->date('pledge_date');
                $table->date('due_date')->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('received_amount', 12, 2)->default(0);
                $table->enum('status', ['PLEDGED', 'PARTIALLY_RECEIVED', 'FULFILLED', 'CANCELLED'])->default('PLEDGED');
                $table->text('notes')->nullable();
                $table->uuid('journal_id')->nullable();
                $table->string('gl_posting_status', 20)->default('NOT_POSTED');
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('donor_id')->references('donor_id')->on('cbe_donors')->onDelete('restrict');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status']);
                $table->index('donor_id');
                $table->index('pledge_no');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_donation_pledges');
    }
};
