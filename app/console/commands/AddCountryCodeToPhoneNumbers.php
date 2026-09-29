<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — per Chris, following the EspoCRM phone-format bug
// found via live testing (EspoCRM's Contact phone field rejected local
// Malaysian numbers like "016-6860771" — it wanted the +60 country
// code). That specific problem was already fixed in EspoCrmService
// (it converts on the fly for the EspoCRM API call only). This command
// is separate: a ONE-TIME, one-off data fix Chris asked for on top of
// that — permanently adding the "+6" prefix to phone numbers already
// stored in GeneralLink's own database, so every screen (Customer
// list/profile, Agent profile, Vendor Maintenance) displays the
// country code too, not just the EspoCRM copy.
//
// Deliberately simple: prepends the literal "+6" in front of whatever
// is already stored (keeps the leading 0, keeps any dashes) — matches
// the common Malaysian convention of writing "+6016-6860771" — rather
// than a strict E.164 reformat, since Chris only asked to "add the +6",
// not restructure the number.
//
// Safe to run more than once: any value that already starts with "+"
// is left untouched, so re-running this never double-prefixes.
//
// Scope, per Chris's explicit choice: Customers, Agents, and Vendors
// only (not Beneficiaries, Batch Registration, Surveys, or Broadcast
// Campaign contact fields — those weren't included in what he approved).
// -------------------------------------------------------
class AddCountryCodeToPhoneNumbers extends Command
{
    protected $signature = 'phones:add-country-code';
    protected $description = 'One-time fix: prepends +6 to existing Customer/Agent/Vendor phone numbers stored without a country code';

    public function handle(): int
    {
        $this->updateColumn('customers', 'customer_id', 'phone');
        $this->updateColumn('agents', 'agent_id', 'phone');
        $this->updateColumn('vendors', 'vendor_id', 'vendor_phone');
        $this->updateColumn('vendors', 'vendor_id', 'vendor_office_phone');
        $this->updateColumn('vendors', 'vendor_id', 'pic_phone');

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function updateColumn(string $table, string $idColumn, string $phoneColumn): void
    {
        // Guard: some of these columns (e.g. vendor_office_phone) were
        // added in later migrations — skip cleanly if a table/column
        // combination doesn't exist yet on whichever database this runs
        // against, rather than erroring the whole command out.
        if (!\Illuminate\Support\Facades\Schema::hasColumn($table, $phoneColumn)) {
            $this->line("  -> Skipped {$table}.{$phoneColumn} (column doesn't exist).");
            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($phoneColumn)
            ->where($phoneColumn, '!=', '')
            ->get([$idColumn, $phoneColumn]);

        $updated = 0;
        foreach ($rows as $row) {
            $current = trim((string) $row->$phoneColumn);
            if ($current === '' || str_starts_with($current, '+')) {
                continue; // already has a country code (or was already run) — skip
            }

            DB::table($table)->where($idColumn, $row->$idColumn)->update([
                $phoneColumn => '+6' . $current,
            ]);
            $updated++;
        }

        $this->info("{$table}.{$phoneColumn}: updated {$updated} record(s), " . ($rows->count() - $updated) . ' already had a country code or were blank.');
    }
}
