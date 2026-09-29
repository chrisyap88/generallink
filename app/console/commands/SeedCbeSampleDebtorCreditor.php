<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 16 Sep 2026 — per Chris, after cbe:setup-master-files: one generic
// placeholder each for Debtor Master / Creditor Master wasn't useful
// enough — he asked for a realistic starter set covering the range of
// parties any CBE community (temple, Rotary/Lions-style club, NGO, SME
// community enterprise) actually deals with: religious/ceremonial
// suppliers, food suppliers, event/hall suppliers, office/admin
// suppliers on the Creditor side; corporate sponsors (banks, insurers,
// CSR-programme companies) and institutional/grant bodies on the
// Debtor side.
//
// These are GENERIC CATEGORY EXAMPLES, not real companies — every name
// is deliberately generic ("Sample ... Supplier") and tagged
// "(placeholder - edit or delete)" so nobody mistakes one for a real
// business. Chris renames each one to his own real supplier/customer
// (or deletes it) from the normal Admin screen — never by editing code.
//
// Supersedes the single generic placeholder cbe:setup-master-files
// inserted: that exact row (matched by its untouched placeholder note)
// is removed first, then this richer set is inserted. If Chris has
// already edited/renamed that row, it's left alone (no longer matches
// the untouched-placeholder check) and this command just adds the new
// rows alongside it.
//
// Idempotent — checks by name before inserting, safe to run more than
// once. Wrapped in a single DB transaction.
class SeedCbeSampleDebtorCreditor extends Command
{
    protected $signature = 'cbe:seed-sample-debtor-creditor';

    protected $description = 'Replace the single generic Debtor/Creditor placeholder with a realistic starter set of sample customers/suppliers covering temple, club/NGO, and SME community-enterprise needs';

    private const GROUP_NAME = 'Persekutuan Pertubuhan Agama Tao Malaysia';

    private const NODE_NAME = 'Cawangan Bandar Di Raja Klang';

    private const OWNER_FULL_NAME = 'Yap Wai Jyh';

    private const OLD_PLACEHOLDER_NOTE_MARKER = 'Placeholder inserted 16 Sep 2026';

    // supplier_name => supplier category name (must match an existing
    // cbe_supplier_categories.category_name, seeded 3 Sep 2026).
    private const SUPPLIERS = [
        'Sample Prayer / Ceremonial Items Supplier (placeholder - edit or delete)' => 'Religious Supplies',
        'Sample Fresh Fruit Supplier - for Offerings/Events (placeholder - edit or delete)' => 'Food & Catering',
        'Sample Pastry / Confectionery Supplier (placeholder - edit or delete)' => 'Food & Catering',
        'Sample Flower Supplier (placeholder - edit or delete)' => 'Other Suppliers',
        'Sample Catering / Event Food Supplier (placeholder - edit or delete)' => 'Food & Catering',
        'Sample Office Supplies Vendor (placeholder - edit or delete)' => 'Other Suppliers',
        'Sample Event Hall / Function Room Rental (placeholder - edit or delete)' => 'Event Suppliers',
        'Sample Event Management Company (placeholder - edit or delete)' => 'Event Suppliers',
        'Sample Printing & Signage Supplier (placeholder - edit or delete)' => 'Other Suppliers',
        'Sample Cleaning & Maintenance Services (placeholder - edit or delete)' => 'Cleaning Services',
    ];

    // customer_name => debtor category name (must match an existing
    // cbe_customer_categories.category_name, seeded by cbe:setup-master-files).
    private const CUSTOMERS = [
        'Sample Corporate Sponsor - Bank (placeholder - edit or delete)' => 'Corporate Sponsor',
        'Sample Corporate Sponsor - Insurance Company (placeholder - edit or delete)' => 'Corporate Sponsor',
        'Sample Corporate CSR Programme Sponsor (placeholder - edit or delete)' => 'Corporate Sponsor',
        'Sample Government / Grant Body (placeholder - edit or delete)' => 'Government / Institutional Body',
        'Sample Facility / Hall Rental Customer (placeholder - edit or delete)' => 'Registered Member',
    ];

    public function handle(): int
    {
        $groupId = DB::table('group_labels')->where('group_name', self::GROUP_NAME)->where('group_type', 'CBE')->value('group_label_id');
        $nodeId = $groupId ? DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupId)->where('node_name', self::NODE_NAME)->value('node_id') : null;
        $ownerAgentId = DB::table('agents')->where('full_name', self::OWNER_FULL_NAME)->where('is_deleted', false)->value('agent_id');

        if (! $nodeId || ! $ownerAgentId) {
            $this->error('Group/entity/owner agent not found. Run cbe:import-klang-committee and cbe:setup-master-files first.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $this->removeUntouchedGenericPlaceholder('cbe_customers', 'customer_id', 'customer_name', $nodeId);
            $this->removeUntouchedGenericPlaceholder('cbe_suppliers', 'supplier_id', 'supplier_name', $nodeId);

            $this->seedSuppliers($nodeId, $ownerAgentId);
            $this->seedCustomers($nodeId, $ownerAgentId);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed, rolled back — nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Debtor Master and Creditor Master now have a realistic starter set.');
        $this->line('Every row is clearly marked "(placeholder - edit or delete)" — rename each one to your real customer/supplier, or delete it, from the normal Admin screen.');

        return self::SUCCESS;
    }

    private function removeUntouchedGenericPlaceholder(string $table, string $idCol, string $nameCol, string $nodeId): void
    {
        $row = DB::table($table)->where('cbe_node_id', $nodeId)
            ->where('notes', 'like', '%'.self::OLD_PLACEHOLDER_NOTE_MARKER.'%')
            ->first();

        if ($row) {
            DB::table($table)->where($idCol, $row->{$idCol})->delete();
            $this->info(ucfirst($table).": removed the old single generic placeholder ({$row->{$nameCol}}).");
        }
    }

    private function seedSuppliers(string $nodeId, string $ownerAgentId): void
    {
        $sort = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->count();
        $added = 0;

        foreach (self::SUPPLIERS as $name => $categoryName) {
            if (DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('supplier_name', $name)->exists()) {
                continue;
            }

            $categoryId = DB::table('cbe_supplier_categories')->where('category_name', $categoryName)->whereNull('group_label_id')->value('category_id');

            $sort++;
            DB::table('cbe_suppliers')->insert([
                'supplier_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'supplier_code' => sprintf('SUP-%04d', $sort),
                'supplier_name' => $name,
                'business_reg_no' => null,
                'category_id' => $categoryId,
                'payment_term_id' => null,
                'payment_method_id' => null,
                'bank_name' => null,
                'bank_account_no' => null,
                'bank_account_holder' => null,
                'is_active' => true,
                'contact_person' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'notes' => 'Sample starter row inserted 16 Sep 2026 — a generic example of the kind of supplier a community/temple/NGO group typically has, not a real business. Replace with your real supplier, or delete this row.',
                'created_by' => $ownerAgentId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $added++;
        }

        $this->info("Creditor Master: added {$added} sample starter supplier(s).");
    }

    private function seedCustomers(string $nodeId, string $ownerAgentId): void
    {
        $added = 0;

        foreach (self::CUSTOMERS as $name => $categoryName) {
            if (DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->where('customer_name', $name)->exists()) {
                continue;
            }

            $categoryId = DB::table('cbe_customer_categories')->where('category_name', $categoryName)->whereNull('group_label_id')->value('category_id');

            DB::table('cbe_customers')->insert([
                'customer_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'customer_name' => $name,
                'category_id' => $categoryId,
                'payment_terms_id' => null,
                'contact_person' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'notes' => 'Sample starter row inserted 16 Sep 2026 — a generic example of the kind of debtor/customer a community/temple/NGO group typically has, not a real company. Replace with your real customer, or delete this row.',
                'created_by' => $ownerAgentId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $added++;
        }

        $this->info("Debtor Master: added {$added} sample starter customer(s).");
    }
}
