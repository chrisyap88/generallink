<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // agents indexes
        $this->addIndex('agents', 'idx_agents_role_parent',
            'ALTER TABLE agents ADD INDEX idx_agents_role_parent (role, parent_id)');

        $this->addIndex('agents', 'idx_agents_status',
            'ALTER TABLE agents ADD INDEX idx_agents_status (status)');

        $this->addIndex('agents', 'idx_agents_role_status',
            'ALTER TABLE agents ADD INDEX idx_agents_role_status (role, status)');

        // sales_transactions indexes
        $this->addIndex('sales_transactions', 'idx_st_agent_month',
            'ALTER TABLE sales_transactions ADD INDEX idx_st_agent_month (agent_id, created_at)');

        $this->addIndex('sales_transactions', 'idx_st_month_year',
            'ALTER TABLE sales_transactions ADD INDEX idx_st_month_year (created_at)');

        $this->addIndex('sales_transactions', 'idx_st_status',
            'ALTER TABLE sales_transactions ADD INDEX idx_st_status (status)');

        $this->addIndex('sales_transactions', 'idx_st_vendor',
            'ALTER TABLE sales_transactions ADD INDEX idx_st_vendor (vendor_id, created_at)');

        $this->addIndex('sales_transactions', 'idx_st_product',
            'ALTER TABLE sales_transactions ADD INDEX idx_st_product (product_id, created_at)');

        $this->addIndex('sales_transactions', 'idx_st_renewal',
            'ALTER TABLE sales_transactions ADD INDEX idx_st_renewal (status, renewal_date)');

        // commission_transactions indexes
        $this->addIndex('commission_transactions', 'idx_ct_agent_month',
            'ALTER TABLE commission_transactions ADD INDEX idx_ct_agent_month (agent_id, created_at)');

        $this->addIndex('commission_transactions', 'idx_ct_status',
            'ALTER TABLE commission_transactions ADD INDEX idx_ct_status (status)');
    }

    public function down(): void
    {
        $indexes = [
            'agents'                 => ['idx_agents_role_parent','idx_agents_status','idx_agents_role_status'],
            'sales_transactions'     => ['idx_st_agent_month','idx_st_month_year','idx_st_status','idx_st_vendor','idx_st_product','idx_st_renewal'],
            'commission_transactions'=> ['idx_ct_agent_month','idx_ct_status'],
        ];
        foreach ($indexes as $table => $idxList) {
            foreach ($idxList as $idx) {
                try {
                    DB::statement("ALTER TABLE {$table} DROP INDEX {$idx}");
                } catch (\Exception $e) {}
            }
        }
    }

    private function addIndex(string $table, string $name, string $sql): void
    {
        $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$name]);
        if (empty($exists)) {
            DB::statement($sql);
        }
    }
};
