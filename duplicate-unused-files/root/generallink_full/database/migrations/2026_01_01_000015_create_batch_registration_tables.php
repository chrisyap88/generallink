<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_registration_staging', function (Blueprint $table) {
            $table->uuid('batch_id')->primary();
            $table->uuid('uploaded_by');
            $table->string('filename', 500);
            $table->enum('status', ['PENDING','VALIDATED','COMMITTED','FAILED'])->default('PENDING');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('committed_rows')->default(0);
            $table->timestamps();

            $table->index('uploaded_by');
            $table->index('status');

            $table->foreign('uploaded_by')->references('agent_id')->on('agents')->restrictOnDelete();
        });

        Schema::create('batch_registration_records', function (Blueprint $table) {
            $table->uuid('record_id')->primary();
            $table->uuid('batch_id');
            $table->unsignedInteger('row_number');
            $table->string('full_name', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('nric', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('sponsor_code', 100)->nullable();
            $table->string('role', 30)->default('INTRODUCER');
            $table->enum('status', ['PENDING','VALID','INVALID','COMMITTED'])->default('PENDING');
            $table->json('validation_errors')->nullable();
            $table->uuid('committed_agent_id')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('status');

            $table->foreign('batch_id')->references('batch_id')->on('batch_registration_staging')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_registration_records');
        Schema::dropIfExists('batch_registration_staging');
    }
};
