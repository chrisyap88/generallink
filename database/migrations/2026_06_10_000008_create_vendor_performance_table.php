<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_performance', function (Blueprint $table) {
            $table->char('perf_id', 36)->primary();
            $table->char('vendor_id', 36)->unique()->comment('One record per vendor — updated on each payment');
            $table->integer('total_payments')->default(0)->comment('Total payments received from vendor');
            $table->integer('remittance_provided_count')->default(0)->comment('How many payments had remittance');
            $table->decimal('auto_match_rate', 5, 2)->default(0)->comment('% of claims auto-matched');
            $table->decimal('avg_days_to_pay', 5, 2)->default(0)->comment('Average days from claim to payment');
            $table->integer('flag_count')->default(0)->comment('Times flagged uncooperative');
            $table->integer('dispute_count')->default(0)->comment('Formal disputes raised');
            $table->enum('cooperation_score', [
                'GOOD',
                'MODERATE',
                'POOR',
                'NONE'
            ])->default('GOOD');
            $table->tinyInteger('termination_warned')->default(0)->comment('Termination notice sent');
            $table->timestamp('termination_warned_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors');

            $table->index('cooperation_score');
            $table->index('flag_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_performance');
    }
};
