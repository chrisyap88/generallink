<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->char('payment_id', 36)->primary();
            $table->string('payment_reference', 50)->unique()->comment('Auto-generated reference');
            $table->char('vendor_id', 36);
            $table->date('payment_date')->comment('Date vendor paid us');
            $table->string('bank_reference', 100)->comment('IBG/TT reference no.');
            $table->decimal('total_amount', 15, 4)->comment('Total lump sum received');
            $table->decimal('allocated_amount', 15, 4)->default(0)->comment('Running total allocated to claims');
            $table->decimal('unallocated_amount', 15, 4)->storedAs('total_amount - allocated_amount')->comment('Computed: cannot exceed total_amount');
            $table->tinyInteger('remittance_provided')->default(0)->comment('Did vendor send breakdown?');
            $table->string('remittance_file_path', 500)->nullable()->comment('Uploaded remittance file');
            $table->enum('matching_stage', [
                'REMITTANCE_MATCHED',
                'PARTIAL_EVIDENCE',
                'LAST_ATTEMPT',
                'COMPLETED'
            ])->default('REMITTANCE_MATCHED');
            $table->enum('status', [
                'PENDING',
                'IN_PROGRESS',
                'COMPLETED',
                'DISPUTED'
            ])->default('PENDING');
            $table->integer('flag_count')->default(0)->comment('Times vendor flagged uncooperative');
            $table->tinyInteger('is_deleted')->default(0);
            $table->char('recorded_by', 36)->comment('Admin who recorded this payment');
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors');
            $table->foreign('recorded_by')->references('agent_id')->on('agents');

            $table->index('vendor_id');
            $table->index('status');
            $table->index('matching_stage');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};
