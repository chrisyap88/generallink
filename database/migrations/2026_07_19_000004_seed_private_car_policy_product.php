<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Jul 2026 — per Chris: the BFU6208 document's actual product/
// policy type is printed as "Private Car Policy Schedule", not the
// generic "Motor Insurance" name the earlier migration invented. Adds
// this exact product under the vendor (matched flexibly, same as the
// vendor-seeding migration, in case Chris's own manually-added vendor
// name differs slightly) so the create-transaction screen can match and
// lock the Product field to the real name printed on the document.
return new class extends Migration
{
    public function up(): void
    {
        $vendor = DB::table('vendors')
            ->where('vendor_name', 'Pacific & Orient Insurance Co. Berhad')
            ->orWhere('vendor_code', 'P&O')
            ->orWhere('vendor_name', 'like', '%P & O%')
            ->orWhere('vendor_name', 'like', '%P&O%')
            ->orWhere('vendor_name', 'like', '%Pacific%Orient%')
            ->first();

        if (!$vendor) return; // Vendor migration should already have run first.

        $productName = 'Private Car Policy Schedule';

        $existing = DB::table('products')
            ->where('vendor_id', $vendor->vendor_id)
            ->where('product_name', $productName)
            ->first();

        if (!$existing) {
            DB::table('products')->insert([
                'product_id'   => (string) Str::uuid(),
                'vendor_id'    => $vendor->vendor_id,
                'product_name' => $productName,
                'product_code' => 'PACORIENT-PCAR',
                'product_type' => 'MOTOR',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        $vendor = DB::table('vendors')
            ->where('vendor_name', 'Pacific & Orient Insurance Co. Berhad')
            ->orWhere('vendor_code', 'P&O')
            ->first();
        if ($vendor) {
            DB::table('products')
                ->where('vendor_id', $vendor->vendor_id)
                ->where('product_name', 'Private Car Policy Schedule')
                ->delete();
        }
    }
};
