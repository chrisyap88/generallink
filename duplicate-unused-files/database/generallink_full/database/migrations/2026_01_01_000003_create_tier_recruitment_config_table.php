<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_recruitment_config', function (Blueprint $table) {
            $table->uuid('config_id')->primary();

            // NULL = global rule; populated for group-specific override
            $table->uuid('group_id')->nullable();

            // Maximum recruitable tier depth for Introducers (default 2)
            $table->tinyInteger('max_tier_limit')->default(2);

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('group_id');
            $table->index('is_active');

            $table->foreign('group_id')
                  ->references('group_id')
                  ->on('groups')
                  ->nullOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_recruitment_config');
    }
};
