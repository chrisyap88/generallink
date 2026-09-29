<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_upload_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();

            $table->string('template_name', 200);
            $table->uuid('product_id');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->restrictOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        Schema::create('template_field_mappings', function (Blueprint $table) {
            $table->uuid('mapping_id')->primary();

            $table->uuid('template_id');
            $table->string('source_column_name', 200);       // Column header from upload file
            $table->string('target_field_name', 200);         // System field name
            $table->enum('target_table', ['CORE', 'EXTENDED']);
            $table->boolean('is_mandatory')->default(false);
            $table->json('validation_rule')->nullable();       // {type, format, max_length}

            $table->timestamps();

            $table->index('template_id');

            $table->foreign('template_id')
                  ->references('template_id')
                  ->on('policy_upload_templates')
                  ->cascadeOnDelete();
        });

        // Upload batch tracking
        Schema::create('policy_upload_batches', function (Blueprint $table) {
            $table->uuid('batch_id')->primary();

            $table->uuid('template_id');
            $table->uuid('uploaded_by');
            $table->string('filename', 500);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('rows_accepted')->default(0);
            $table->unsignedInteger('rows_rejected')->default(0);

            $table->enum('status', [
                'PROCESSING',
                'COMPLETED',
                'COMPLETED_WITH_ERRORS',
                'FAILED',
            ])->default('PROCESSING');

            $table->timestamps();

            $table->index('uploaded_by');
            $table->index('status');

            $table->foreign('template_id')
                  ->references('template_id')
                  ->on('policy_upload_templates')
                  ->restrictOnDelete();

            $table->foreign('uploaded_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_upload_batches');
        Schema::dropIfExists('template_field_mappings');
        Schema::dropIfExists('policy_upload_templates');
    }
};
