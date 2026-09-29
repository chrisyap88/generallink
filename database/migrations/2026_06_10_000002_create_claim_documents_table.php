<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_documents', function (Blueprint $table) {
            $table->char('document_id', 36)->primary();
            $table->char('claim_id', 36);
            $table->enum('document_type', [
                'POLICE_REPORT',
                'RECEIPT',
                'IC',
                'MEDICAL_REPORT',
                'REPAIR_ESTIMATE',
                'OTHER'
            ]);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->integer('file_size')->comment('In bytes');
            $table->char('uploaded_by', 36);
            $table->timestamp('uploaded_at')->nullable()->comment('Document date reference');
            $table->tinyInteger('is_deleted')->default(0);
            $table->timestamps();

            $table->foreign('claim_id')->references('claim_id')->on('claims');
            $table->foreign('uploaded_by')->references('agent_id')->on('agents');

            $table->index('claim_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_documents');
    }
};
