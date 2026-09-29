<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Jul 2026 — per Chris: the create-transaction screen tried to
// auto-match the vendor printed on his real test document (POLICY
// BFU6208.pdf — "PACIFIC & ORIENT INSURANCE CO. BERHAD") and correctly
// found no match, because this insurer was never seeded into the
// vendors table (seed_vendors_products.php only added 15 other
// insurers). This isn't a matching-logic bug — the vendor genuinely
// didn't exist yet. Adding it here (with a MOTOR product, since
// BFU6208 is a motor policy) so future reads of this same document —
// and any other Pacific & Orient policy — auto-match and lock
// correctly, same as the other 15 vendors already do.
return new class extends Migration
{
    public function up(): void
    {
        $vendorName = 'Pacific & Orient Insurance Co. Berhad';

        // FIXED 19 Jul 2026 — Chris may have already added this insurer
        // himself via Vendor Management under a shorter name ("P & O
        // Insurance Berhad" / code "P&O") before this migration ran.
        // Check for that first — inserting a second vendor record for
        // the same real insurer would create a confusing duplicate and
        // split its transaction history across two vendor_ids.
        $existing = DB::table('vendors')
            ->where('vendor_name', $vendorName)
            ->orWhere('vendor_code', 'P&O')
            ->orWhere('vendor_name', 'like', '%P & O%')
            ->orWhere('vendor_name', 'like', '%P&O%')
            ->orWhere('vendor_name', 'like', '%Pacific%Orient%')
            ->first();

        $vendorId = $existing->vendor_id ?? (string) Str::uuid();

        if (!$existing) {
            DB::table('vendors')->insert([
                'vendor_id'                => $vendorId,
                'vendor_name'               => $vendorName,
                'vendor_code'               => 'PACORIENT',
                'industry'                  => 'INSURANCE',
                // From the BFU6208 document schedule, shown in this
                // session's earlier extraction table.
                'sst_registration_number'   => 'W10-1808-31021805',
                'is_active'                 => true,
                'created_at'                => now(),
                'updated_at'                => now(),
            ]);
        } elseif (empty($existing->sst_registration_number)) {
            // Already exists (Chris's own entry) but missing the SST reg
            // no. — fill it in from the document rather than leaving it
            // blank, without touching anything else he's already set up.
            DB::table('vendors')->where('vendor_id', $vendorId)
                ->update(['sst_registration_number' => 'W10-1808-31021805', 'updated_at' => now()]);
        }

        $motorProductExists = DB::table('products')
            ->where('vendor_id', $vendorId)
            ->where('product_type', 'MOTOR')
            ->exists();

        if (!$motorProductExists) {
            DB::table('products')->insert([
                'product_id'   => (string) Str::uuid(),
                'vendor_id'    => $vendorId,
                'product_name' => 'Motor Insurance',
                'product_code' => 'PACORIENT-MOTOR',
                'product_type' => 'MOTOR',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        $vendor = DB::table('vendors')->where('vendor_name', 'Pacific & Orient Insurance Co. Berhad')->first();
        if ($vendor) {
            DB::table('products')->where('vendor_id', $vendor->vendor_id)->delete();
            DB::table('vendors')->where('vendor_id', $vendor->vendor_id)->delete();
        }
    }
};
