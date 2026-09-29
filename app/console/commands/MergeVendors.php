<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// -------------------------------------------------------
// NEW 29 Jul 2026 — per Chris: "merge all AIA into one". Consolidates
// every vendor matching a name search into one designated keeper.
//
// Unlike the earlier vendors:rename command (a pure text change, zero
// risk), this one moves real linked data around, so it's built to be
// thorough rather than fast:
//   1. Discovers EVERY table with a foreign key pointing at
//      vendors.vendor_id directly from the database schema (same
//      technique as vendors:audit-duplicates) — not a hand-typed list,
//      so nothing gets missed the way broadcast_campaigns almost was
//      during the earlier audit.
//   2. Reassigns all of it from each duplicate vendor onto the keeper.
//   3. Moves the duplicate's products onto the keeper too, then
//      de-duplicates: if the keeper ends up with two products of the
//      same name (one from each merged vendor), everything under the
//      "loser" product (its own sales transactions, commission
//      structures, etc. — again discovered from the schema, not
//      hand-typed) is moved onto whichever of the two has more real
//      sales transactions, and the now-empty duplicate product row is
//      removed.
//   4. Deletes each duplicate vendor row once nothing points at it
//      anywhere in the database any more.
// Runs inside one DB transaction — either all of it applies, or none
// of it does.
// -------------------------------------------------------
class MergeVendors extends Command
{
    protected $signature = 'vendors:merge {keep} {search=AIA}';
    protected $description = 'Merge every vendor matching a name search into one designated keeper vendor';

    public function handle(): int
    {
        $keepName = $this->argument('keep');
        $search = $this->argument('search');

        $keeper = DB::table('vendors')->whereRaw('LOWER(vendor_name) = ?', [strtolower($keepName)])->first();
        if (!$keeper) {
            $this->error("No vendor found named exactly \"{$keepName}\" to keep.");
            return self::FAILURE;
        }

        $losers = DB::table('vendors')
            ->where('vendor_name', 'like', "%{$search}%")
            ->where('vendor_id', '!=', $keeper->vendor_id)
            ->get();

        if ($losers->isEmpty()) {
            $this->info("Nothing to merge — \"{$keeper->vendor_name}\" is already the only vendor matching \"{$search}\".");
            return self::SUCCESS;
        }

        $this->info("Keeping: \"{$keeper->vendor_name}\" (Vendor ID: {$keeper->vendor_id})");
        $this->info('Merging in and removing:');
        foreach ($losers as $l) {
            $this->line("  - \"{$l->vendor_name}\" (Vendor ID: {$l->vendor_id})");
        }
        $this->line('');

        DB::transaction(function () use ($keeper, $losers) {
            foreach ($losers as $loser) {
                // Move every product this vendor owns onto the keeper first
                // (excluded from the generic FK sweep below since it needs
                // its own de-dup pass afterward).
                $movedProducts = DB::table('products')->where('vendor_id', $loser->vendor_id)->update(['vendor_id' => $keeper->vendor_id]);
                if ($movedProducts > 0) {
                    $this->line("  Moved {$movedProducts} product(s) from \"{$loser->vendor_name}\" onto \"{$keeper->vendor_name}\".");
                }

                // Everything else that points at this vendor directly.
                $this->reassignForeignKeys('vendors', 'vendor_id', $loser->vendor_id, $keeper->vendor_id, ['products']);

                DB::table('vendors')->where('vendor_id', $loser->vendor_id)->delete();
                $this->line("  Removed vendor \"{$loser->vendor_name}\".");
            }

            $this->line('');
            $this->dedupeProducts($keeper->vendor_id);
            $this->dedupeCommissionStructures($keeper->vendor_id);
        });

        $this->line('');
        $this->info('Merge complete.');
        return self::SUCCESS;
    }

    /**
     * Update every column across the whole database that has a real
     * foreign key pointing at $referencedTable.$referencedColumn,
     * moving rows from $fromValue to $toValue. Discovered from
     * information_schema so nothing has to be hand-listed and nothing
     * gets missed.
     */
    private function reassignForeignKeys(string $referencedTable, string $referencedColumn, string $fromValue, string $toValue, array $excludeTables = []): void
    {
        $dbName = DB::getDatabaseName();
        $references = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME = ?
              AND REFERENCED_COLUMN_NAME = ?
        ", [$dbName, $referencedTable, $referencedColumn]);

        foreach ($references as $ref) {
            if (in_array($ref->TABLE_NAME, $excludeTables) || !Schema::hasTable($ref->TABLE_NAME)) {
                continue;
            }
            $moved = DB::table($ref->TABLE_NAME)->where($ref->COLUMN_NAME, $fromValue)->update([$ref->COLUMN_NAME => $toValue]);
            if ($moved > 0) {
                $this->line("    Moved {$moved} row(s) in {$ref->TABLE_NAME}.{$ref->COLUMN_NAME}");
            }
        }
    }

    /**
     * After all vendors are merged, the keeper may now own two products
     * with the same name (one that already belonged to it, one moved
     * over from a merged-in vendor). Keep whichever has more real sales
     * transactions (tie-break: created first), move everything else off
     * the other one, then remove it.
     */
    private function dedupeProducts(string $keeperVendorId): void
    {
        $products = DB::table('products')->where('vendor_id', $keeperVendorId)->get();
        $groups = $products->groupBy(fn($p) => strtolower(trim($p->product_name)));

        foreach ($groups as $name => $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $this->line("  Duplicate product found: \"{$group->first()->product_name}\" ({$group->count()} copies)");

            $ranked = $group->map(function ($p) {
                $p->txn_count = DB::table('sales_transactions')->where('product_id', $p->product_id)->where('is_deleted', false)->count();
                return $p;
            })->sortByDesc(fn($p) => [$p->txn_count, -strtotime($p->created_at)])->values();

            $survivor = $ranked->first();
            $this->line("    Keeping: {$survivor->product_name} ({$survivor->product_code}) — {$survivor->txn_count} transaction(s)");

            foreach ($ranked->slice(1) as $dup) {
                $this->line("    Merging in: {$dup->product_name} ({$dup->product_code}) — {$dup->txn_count} transaction(s)");
                $this->reassignForeignKeys('products', 'product_id', $dup->product_id, $survivor->product_id);
                DB::table('products')->where('product_id', $dup->product_id)->delete();
            }
        }
    }

    /**
     * After merging products, the keeper vendor could end up with two
     * ACTIVE commission structures for the exact same vendor+product
     * (one moved over from each merged product). CommissionEngine would
     * still pick one (most recent valid_from) without erroring, but the
     * other sits there as a confusing, redundant duplicate — deactivate
     * (never delete — keeps the audit trail) every one except whichever
     * has the latest valid_from for that vendor+product.
     */
    private function dedupeCommissionStructures(string $keeperVendorId): void
    {
        $structures = DB::table('commission_structures')
            ->where('vendor_id', $keeperVendorId)
            ->where('is_active', true)
            ->get();

        $groups = $structures->groupBy('product_id');
        foreach ($groups as $productId => $group) {
            if ($group->count() <= 1) {
                continue;
            }
            $keepStructure = $group->sortByDesc(fn($s) => strtotime($s->valid_from))->first();
            foreach ($group as $s) {
                if ($s->structure_id === $keepStructure->structure_id) {
                    continue;
                }
                DB::table('commission_structures')->where('structure_id', $s->structure_id)->update(['is_active' => false, 'updated_at' => now()]);
                $this->line("    Deactivated a redundant duplicate Earning Income Structure (structure_id: {$s->structure_id}) for the same vendor+product.");
            }
        }
    }
}
