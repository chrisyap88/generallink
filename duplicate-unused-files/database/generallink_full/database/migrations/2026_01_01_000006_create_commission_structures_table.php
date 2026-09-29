<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission structure — fully data-driven per vendor + product.
     *
     * total_commission_pct  = gross pool % of premium or sum insured
     * introducer_pct        = Introducer's share of the pool (%)
     * team_leader_pct       = Team Leader's share of the pool (%)
     * group_leader_pct      = Group Leader's share of the pool (%)
     *
     * RULE: introducer_pct + team_leader_pct + group_leader_pct = 100.0000
     * Enforced at application layer before save.
     */
    public function up(): void
    {
        Schema::create('commission_structures', function (Blueprint $table) {
            $table->uuid('structure_id')->primary();

            $table->uuid('vendor_id');
            $table->uuid('product_id');

            // Whether pool is % of premium paid or % of sum insured
            $table->enum('commission_basis', [
                'PREMIUM_PCT',
                'SUM_INSURED_PCT',
            ])->default('PREMIUM_PCT');

            // Gross commission pool as % of the basis amount
            // e.g. 10.0000 for Motor, 25.0000 for PA, 15.0000 for Fire
            $table->decimal('total_commission_pct', 10, 4);

            // Role entitlement splits — must sum to 100.0000
            $table->decimal('introducer_pct', 10, 4)->default(0);
            $table->decimal('team_leader_pct', 10, 4)->default(0);
            $table->decimal('group_leader_pct', 10, 4)->default(0);

            // Time-bound campaign support
            $table->date('valid_from');
            $table->date('valid_to')->nullable();   // NULL = no expiry

            $table->boolean('is_active')->default(true);

            // Audit trail
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'product_id', 'is_active']);
            $table->index('valid_from');

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->cascadeOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_structures');
    }
};
