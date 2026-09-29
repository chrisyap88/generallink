<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris asked to delete the duplicate "AIA" vendor,
// keeping only "AIA Insurance Berhad". Before touching anything: the
// vendors table has real foreign key constraints on it —
// sales_transactions.vendor_id BLOCKS deletion outright (restrictOnDelete)
// if any real policy points at that vendor, and products.vendor_id
// CASCADE DELETES every product under that vendor (which would then
// cascade further into commission_structures for those products too).
// A blind DELETE could therefore either fail outright, or silently
// wipe out real products/structures if the duplicate has any linked
// to it. This command shows exactly what's linked to each AIA-named
// vendor first, so nothing gets deleted blind.
// -------------------------------------------------------
class VendorDuplicateAudit extends Command
{
    protected $signature = 'vendors:audit-duplicates {search=AIA}';
    protected $description = 'Show every vendor matching a name search and what is linked to each, before any delete/merge';

    public function handle(): int
    {
        $search = $this->argument('search');

        $vendors = DB::table('vendors')
            ->where('vendor_name', 'like', "%{$search}%")
            ->orderBy('vendor_name')
            ->get();

        if ($vendors->isEmpty()) {
            $this->warn("No vendors found matching \"{$search}\".");
            return self::SUCCESS;
        }

        // Discover EVERY table with a foreign key pointing at
        // vendors.vendor_id directly from the database schema, rather
        // than hand-listing tables — this app has more vendor_id
        // references than are obvious at a glance (products, vendor
        // branches, sales transactions, commission structures, vendor
        // performance, vendor payments, claims, contests sponsorship,
        // etc.), and missing one here could mean a delete either fails
        // unexpectedly or silently cascades away real data.
        $dbName = DB::getDatabaseName();
        $references = DB::select("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME = 'vendors'
              AND REFERENCED_COLUMN_NAME = 'vendor_id'
        ", [$dbName]);

        $this->info("Found {$vendors->count()} vendor(s) matching \"{$search}\":");
        $this->line('');

        foreach ($vendors as $v) {
            $this->line("  Vendor ID: {$v->vendor_id}");
            $this->line("  Name: \"{$v->vendor_name}\" (Code: {$v->vendor_code}, Active: " . ($v->is_active ? 'Yes' : 'No') . ')');

            $anyLinked = false;
            foreach ($references as $ref) {
                $table = $ref->TABLE_NAME;
                $column = $ref->COLUMN_NAME;
                if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    continue;
                }
                $query = DB::table($table)->where($column, $v->vendor_id);
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'is_deleted')) {
                    $query->where('is_deleted', false);
                }
                $count = $query->count();
                if ($count > 0) {
                    $anyLinked = true;
                    $this->line("    {$table}.{$column}: {$count} row(s) linked");
                }
            }

            if (!$anyLinked) {
                $this->line('    Nothing linked to this vendor anywhere in the database — safe to delete outright.');
            }

            $productCount = DB::table('products')->where('vendor_id', $v->vendor_id)->count();
            if ($productCount > 0) {
                $products = DB::table('products')->where('vendor_id', $v->vendor_id)->get(['product_id', 'product_name', 'product_code']);
                foreach ($products as $p) {
                    $ptxnCount = DB::table('sales_transactions')->where('product_id', $p->product_id)->where('is_deleted', false)->count();
                    $this->line("      - Product: {$p->product_name} ({$p->product_code}) — {$ptxnCount} sales transaction(s)");
                }
            }
            $this->line('');
        }

        $this->info('Nothing was changed — this is read-only.');
        return self::SUCCESS;
    }
}
