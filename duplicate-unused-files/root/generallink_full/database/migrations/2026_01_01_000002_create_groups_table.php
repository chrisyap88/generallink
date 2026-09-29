<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('group_id')->primary();

            // Group identity
            $table->string('group_name', 200);                    // GL full name
            $table->string('group_code', 20)->unique();           // e.g. C0001
            $table->string('group_email', 200);

            // Member code formatting (Module 2B)
            $table->char('separator_char', 1)->default('-');      // '-', '.', or '_'
            $table->string('root_member_suffix', 10)->default('0');

            // Status
            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('group_code');
            $table->index('is_active');
        });

        // Add FK from groups to agents (created_by)
        Schema::table('groups', function (Blueprint $table) {
            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
