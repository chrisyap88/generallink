<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 16 Sep 2026 — per Chris: "let me know what other master file not
// set up because it is tedious for me to check one by one and key in
// one by one." A read-only status report, scoped to the group/entity
// created by cbe:import-klang-committee, so Chris can see at a glance
// which master files already have data and which are still empty —
// instead of opening 25+ screens one at a time.
//
// Read-only: never inserts, updates, or deletes anything.
class MasterFileStatus extends Command
{
    protected $signature = 'cbe:master-file-status';

    protected $description = 'Read-only report: which CBE master files have data and which are still empty, for the Tao Malaysia group/Klang entity';

    private const GROUP_NAME = 'Persekutuan Pertubuhan Agama Tao Malaysia';

    private const NODE_NAME = 'Cawangan Bandar Di Raja Klang';

    public function handle(): int
    {
        $groupId = DB::table('group_labels')->where('group_name', self::GROUP_NAME)->where('group_type', 'CBE')->value('group_label_id');
        $nodeId = $groupId ? DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupId)->where('node_name', self::NODE_NAME)->value('node_id') : null;

        if (! $groupId) {
            $this->error('No group found named "'.self::GROUP_NAME.'". Run cbe:import-klang-committee first.');

            return self::FAILURE;
        }

        $this->info('Group: '.self::GROUP_NAME.' ('.$groupId.')');
        $this->info('Entity: '.self::NODE_NAME.' ('.($nodeId ?: 'not found').')');
        $this->newLine();

        $rows = [];

        // --- Platform-wide catalogs (no group scoping — shared by every CBE community) ---
        $rows[] = ['— Platform-wide catalogs —', '', ''];
        $rows[] = $this->globalCount('Committee Position Types', 'cbe_committee_position_types');
        $rows[] = $this->globalCount('Practitioner Types', 'cbe_practitioner_types');
        $rows[] = $this->globalCount('Faith / Practice Types', 'cbe_faith_practice_types');
        $rows[] = $this->globalCount('GLADE Membership Tiers', 'cbe_glade_membership_tiers');
        $rows[] = $this->globalCount('Reason Codes', 'reason_codes');

        // --- Financial Master File — group-level (a default with group_label_id NULL counts too) ---
        $rows[] = ['— Financial Master File (group-level) —', '', ''];
        $rows[] = $this->groupScopedCount('Debtor Category', 'cbe_customer_categories', $groupId);
        $rows[] = $this->groupScopedCount('Creditor Category', 'cbe_supplier_categories', $groupId);
        $rows[] = $this->groupScopedCount('Chart of Accounts', 'cbe_chart_of_accounts', $groupId);
        $rows[] = $this->groupScopedCount('Transaction Type', 'cbe_account_categories', $groupId);
        $rows[] = $this->groupScopedCount('FA Category', 'cbe_asset_categories', $groupId);
        $rows[] = $this->groupScopedCount('Payment Terms Master', 'cbe_payment_terms', $groupId);
        $rows[] = $this->groupScopedCount('Payment Methods Master', 'cbe_payment_methods', $groupId);
        $rows[] = $this->groupScopedCount('Bank Transaction Types Master', 'cbe_bank_transaction_types', $groupId);

        // --- Financial Master File — entity/branch-level (specific to the Klang node) ---
        $rows[] = ['— Financial Master File (entity-level: '.self::NODE_NAME.') —', '', ''];
        $rows[] = $this->nodeScopedCount('Debtor Master', 'cbe_customers', $nodeId);
        $rows[] = $this->nodeScopedCount('Creditor Master', 'cbe_suppliers', $nodeId);
        $rows[] = $this->nodeScopedCount('FA Location', 'cbe_asset_locations', $nodeId);
        $rows[] = $this->nodeScopedCount('Bank Account Number', 'cbe_bank_accounts', $nodeId);
        $rows[] = $this->nodeScopedCount('Fund Master', 'cbe_funds', $nodeId);
        $rows[] = $this->nodeScopedCount('Tax Rates Master', 'cbe_tax_rates', $nodeId);
        $rows[] = $this->nodeScopedCount('Marketplace Listings', 'cbe_marketplace_listings', $nodeId);
        $rows[] = $this->nodeScopedCount('Marketplace Orders', 'cbe_marketplace_orders', $nodeId);
        $rows[] = $this->nodeScopedCount('Marketplace Campaigns', 'cbe_marketplace_campaigns', $nodeId);

        // --- Vendor Master File — platform-wide vendor pool ---
        $rows[] = ['— Vendor Master File —', '', ''];
        $rows[] = $this->globalCount('CBE Vendors', 'cbe_vendors');

        // --- Membership / Appointment (already touched this session) ---
        $rows[] = ['— Membership / Appointment —', '', ''];
        $rows[] = $this->nodeScopedCount('Practitioner / Appointment Setup', 'cbe_practitioner_profiles', $nodeId, 'cbe_node_id');
        $rows[] = $this->groupScopedCount('Committee / Management Team', 'group_committee_members', $groupId, 'group_label_id', false);

        // --- Not simple catalogs — auto-generated or per-node settings, nothing to pre-populate ---
        $rows[] = ['— Auto / settings (nothing to key in ahead of time) —', '', ''];
        $rows[] = ['Document Number Control', 'auto', 'generates its own sequence — nothing to set up first'];
        $rows[] = ['Bank Reconciliation Rules', 'auto', 'one settings form per entity — fill in only when you reconcile'];

        $this->table(['Master File', 'Rows', 'Status'], $rows);

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: int|string, 2: string} */
    private function globalCount(string $label, string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [$label, 'n/a', 'table not found'];
        }
        $count = DB::table($table)->count();

        return [$label, $count, $count > 0 ? 'has data' : 'EMPTY — needs setup'];
    }

    /** @return array{0: string, 1: int|string, 2: string} */
    private function groupScopedCount(string $label, string $table, ?string $groupId, string $groupCol = 'group_label_id', bool $includeDefaults = true): array
    {
        if (! Schema::hasTable($table)) {
            return [$label, 'n/a', 'table not found'];
        }
        $specificCount = $groupId ? DB::table($table)->where($groupCol, $groupId)->count() : 0;
        $defaultCount = $includeDefaults ? DB::table($table)->whereNull($groupCol)->count() : 0;
        $total = $specificCount + $defaultCount;
        $note = $defaultCount > 0
            ? "$specificCount for this group + $defaultCount platform default(s)"
            : ($specificCount > 0 ? 'has data' : 'EMPTY — needs setup');

        return [$label, $total, $note];
    }

    /** @return array{0: string, 1: int|string, 2: string} */
    private function nodeScopedCount(string $label, string $table, ?string $nodeId, string $nodeCol = 'cbe_node_id'): array
    {
        if (! Schema::hasTable($table)) {
            return [$label, 'n/a', 'table not found'];
        }
        if (! $nodeId) {
            return [$label, 'n/a', 'entity not found'];
        }
        $count = DB::table($table)->where($nodeCol, $nodeId)->count();

        return [$label, $count, $count > 0 ? 'has data' : 'EMPTY — needs setup'];
    }
}
