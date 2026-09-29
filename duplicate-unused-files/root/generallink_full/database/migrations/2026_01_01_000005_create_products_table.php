<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('product_id')->primary();

            $table->uuid('vendor_id');
            $table->string('product_name', 200);
            $table->string('product_code', 20)->unique();
            $table->enum('product_type', [
                'MOTOR',
                'PERSONAL_ACCIDENT',
                'FIRE',
                'OTHER',
            ]);
            $table->text('description')->nullable();

            // Campaign support
            $table->date('campaign_start')->nullable();
            $table->date('campaign_end')->nullable();

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('product_type');
            $table->index('is_active');

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
