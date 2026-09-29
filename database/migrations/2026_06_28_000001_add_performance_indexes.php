<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // agents table indexes
        Schema::table('agents', function (Blueprint $table) {
            if (!$this->indexExists('agents', 'idx_agents_role_deleted'))
                $table->index(['role', 'is_deleted'], 'idx_agents_role_deleted');

            if (!$this->indexExists('agents', 'idx_agents_group_id'))
                $table->index(['group_id'], 'idx_agents_group_id');

            if (!$this->indexExists('agents', 'idx_agents_parent_id'))
                $table->index(['parent_id'], 'idx_agents_parent_id');

            if (!$this->indexExists('agents', 'idx_agents_role_group'))
                $table->index(['role', 'group_id', 'is_deleted'], 'idx_agents_role_group');

            if (!$this->indexExists('agents', 'idx_agents_status'))
                $table->index(['status', 'is_deleted'], 'idx_agents_status');
        });

        // sales_transactions indexes
        Schema::table('sales_transactions', function (Blueprint $table) {
            if (!$this->indexExists('sales_transactions', 'idx_sales_agent_date'))
                $table->index(['agent_id', 'created_at', 'is_deleted'], 'idx_sales_agent_date');

            if (!$this->indexExists('sales_transactions', 'idx_sales_month_year'))
                $table->index(['is_deleted'], 'idx_sales_deleted');
        });

        // commission_transactions indexes
        Schema::table('commission_transactions', function (Blueprint $table) {
            if (!$this->indexExists('commission_transactions', 'idx_comm_agent_date'))
                $table->index(['agent_id', 'created_at', 'status'], 'idx_comm_agent_date');

            if (!$this->indexExists('commission_transactions', 'idx_comm_status'))
                $table->index(['status'], 'idx_comm_status');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_agents_role_deleted');
            $table->dropIndexIfExists('idx_agents_group_id');
            $table->dropIndexIfExists('idx_agents_parent_id');
            $table->dropIndexIfExists('idx_agents_role_group');
            $table->dropIndexIfExists('idx_agents_status');
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_sales_agent_date');
            $table->dropIndexIfExists('idx_sales_deleted');
        });

        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_comm_agent_date');
            $table->dropIndexIfExists('idx_comm_status');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }
};
