<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — per Chris: rename the vendor "AIA Malaysia" to
// "AIA Berhad" (simpler and safer than deleting, since renaming only
// touches vendor_name — vendor_id stays the same, so nothing linked to
// it via foreign keys is affected at all).
//
// One safety check: if a vendor already named "AIA Berhad" exists,
// renaming would just recreate the exact duplicate-vendor problem
// Chris originally flagged, so this stops and reports rather than
// silently creating a second "AIA Berhad".
// -------------------------------------------------------
class RenameVendor extends Command
{
    protected $signature = 'vendors:rename {from} {to}';
    protected $description = 'Rename a vendor by exact current name, refusing if the new name would collide with an existing vendor';

    public function handle(): int
    {
        $from = $this->argument('from');
        $to = $this->argument('to');

        $vendor = DB::table('vendors')->whereRaw('LOWER(vendor_name) = ?', [strtolower($from)])->first();

        if (!$vendor) {
            $this->error("No vendor found named exactly \"{$from}\".");
            $close = DB::table('vendors')->where('vendor_name', 'like', '%' . $from . '%')->pluck('vendor_name');
            if ($close->isNotEmpty()) {
                $this->line('Did you mean one of these?');
                foreach ($close as $name) {
                    $this->line("  - {$name}");
                }
            }
            return self::FAILURE;
        }

        $collision = DB::table('vendors')
            ->whereRaw('LOWER(vendor_name) = ?', [strtolower($to)])
            ->where('vendor_id', '!=', $vendor->vendor_id)
            ->first();

        if ($collision) {
            $this->error("Cannot rename — a vendor already named \"{$to}\" exists (Vendor ID: {$collision->vendor_id}).");
            $this->line('Renaming would just create a second vendor with that same name.');
            $this->line('If you actually want these two merged into one, let me know and I\'ll build that separately.');
            return self::FAILURE;
        }

        DB::table('vendors')->where('vendor_id', $vendor->vendor_id)->update([
            'vendor_name' => $to,
            'updated_at' => now(),
        ]);

        $this->info("Done. \"{$from}\" (Vendor ID: {$vendor->vendor_id}) is now named \"{$to}\".");
        $this->line('Nothing else changed — vendor_id stayed the same, so every product, sales transaction, and');
        $this->line('commission structure already linked to this vendor is still correctly linked, unaffected.');

        return self::SUCCESS;
    }
}
