<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('vendor_id')->primary();

            $table->string('vendor_name', 200);
            $table->string('vendor_code', 20)->unique();     // Short code e.g. ALZ
            $table->string('vendor_email', 200)->nullable();
            $table->string('vendor_phone', 20)->nullable();
            $table->text('vendor_address')->nullable();
            $table->string('pic_name', 200)->nullable();     // Person in charge

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('is_active');

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
