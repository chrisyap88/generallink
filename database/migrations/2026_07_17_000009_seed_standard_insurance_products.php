<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Jul 2026 — data-only migration, no schema change.
//
// 1. The vendors.industry column existed since the start of this
//    project, but the Vendor Add/Edit form never had an Industry
//    field until today — so any vendor added after the very first
//    seed (e.g. P & O Insurance Berhad) was left with industry =
//    NULL. This tags every vendor whose name clearly reads as an
//    insurance company (contains "Insurance", "Assurance", or
//    "Takaful") and still has no industry set.
//
// 2. Every vendor now tagged INSURANCE gets a standard suite of
//    insurance products seeded in, skipping any product name that
//    vendor already has (case-insensitive) so nothing already
//    calibrated/seeded gets duplicated. This is what fixes vendors
//    like P&O showing up empty in Product Maintenance / the
//    Document Template product search.
return new class extends Migration
{
    private const STANDARD_INSURANCE_PRODUCTS = [
        ['name' => 'Motor Comprehensive',              'type' => 'MOTOR'],
        ['name' => 'Motor Third Party, Fire & Theft',  'type' => 'MOTOR'],
        ['name' => 'Personal Accident',                'type' => 'PERSONAL_ACCIDENT'],
        ['name' => 'Group Personal Accident',          'type' => 'PERSONAL_ACCIDENT'],
        ['name' => 'Fire / Householder',               'type' => 'FIRE'],
        ['name' => 'Fire - Industrial All Risks',      'type' => 'FIRE'],
        ['name' => 'Travel Insurance',                 'type' => 'OTHER'],
        ['name' => 'Marine Cargo',                     'type' => 'OTHER'],
        ['name' => 'Public Liability',                 'type' => 'OTHER'],
        ['name' => 'Medical / Health Insurance',        'type' => 'OTHER'],
    ];

    public function up(): void
    {
        DB::table('vendors')
            ->whereNull('industry')
            ->where(function ($q) {
                $q->where('vendor_name', 'like', '%Insurance%')
                  ->orWhere('vendor_name', 'like', '%Assurance%')
                  ->orWhere('vendor_name', 'like', '%Takaful%');
            })
            ->update(['industry' => 'INSURANCE', 'updated_at' => now()]);

        $vendors = DB::table('vendors')
            ->where('industry', 'INSURANCE')
            ->get(['vendor_id', 'vendor_code']);

        foreach ($vendors as $vendor) {
            $existingNames = DB::table('products')
                ->where('vendor_id', $vendor->vendor_id)
                ->pluck('product_name')
                ->map(fn ($n) => mb_strtolower($n))
                ->all();

            foreach (self::STANDARD_INSURANCE_PRODUCTS as $product) {
                if (in_array(mb_strtolower($product['name']), $existingNames, true)) {
                    continue;
                }

                DB::table('products')->insert([
                    'product_id'   => Str::uuid()->toString(),
                    'vendor_id'    => $vendor->vendor_id,
                    'product_name' => $product['name'],
                    'product_code' => $this->generateProductCode($vendor->vendor_code, $product['name']),
                    'product_type' => $product['type'],
                    'is_active'    => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $existingNames[] = mb_strtolower($product['name']);
            }
        }
    }

    private function generateProductCode(string $vendorCode, string $name): string
    {
        $base = strtoupper($vendorCode) . '-' . strtoupper(Str::slug($name, ''));
        $base = substr($base, 0, 16);
        $code = $base;
        $suffix = 1;
        while (DB::table('products')->where('product_code', $code)->exists()) {
            $suffix++;
            $code = substr($base, 0, 16 - strlen((string) $suffix)) . $suffix;
        }
        return $code;
    }

    public function down(): void
    {
        // Data-only migration, no schema to revert. The seeded products
        // and industry tags are left in place on rollback, matching this
        // project's rule of never auto-deleting real records.
    }
};
