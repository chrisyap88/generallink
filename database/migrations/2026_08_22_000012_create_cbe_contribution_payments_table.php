<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: "manage outstanding pledges, partial
// payments". One pledge (a cbe_contributions row) can be settled across
// several payments over time — a sponsor promising RM5,000 might pay
// RM2,000 now and RM3,000 next month. Each payment is its own row here;
// cbe_contributions.received_amount is kept as a running total so the
// register doesn't need to re-sum this table on every page view.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_contribution_payments')) {
            Schema::create('cbe_contribution_payments', function (Blueprint $table) {
                $table->uuid('payment_id')->primary();
                $table->uuid('contribution_id');
                $table->date('payment_date');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 50)->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('contribution_id')->references('contribution_id')->on('cbe_contributions')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index('contribution_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_contribution_payments');
    }
};
