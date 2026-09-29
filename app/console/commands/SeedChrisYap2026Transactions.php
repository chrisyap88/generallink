<?php

namespace App\Console\Commands;

use App\Services\CommissionEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 23 Jul 2026 — per Chris: "create transaction for in sales
// forecast display 2026 12 months transaction, every month must have
// 5 to 12 transaction for Group Leader Chris Yap... for all his team
// leader and introducer" — demo/test data so the Sales and Earning
// Income Forecast screen has something real to show across all 12
// months of 2026, spread across Chris Yap's whole downline (his Team
// Leaders and Introducers), not just himself.
//
// Every transaction created here goes through the SAME
// CommissionEngine::calculate() the real Submit Sales Transaction
// screen uses — nothing about the commission math is reimplemented or
// guessed, so Earning Income figures on the dashboard will be
// realistic, correctly split by role, and match how the real system
// actually calculates them.
//
// All demo data is clearly tagged so it can be found and removed
// later: every document_reference_number starts with "DEMO2026-" and
// every dummy customer's name starts with "(Demo) ". A companion
// cleanup command (demo:remove-2026-transactions) undoes this exact
// batch cleanly if Chris ever wants it gone.
//
// Run via: php artisan demo:seed-2026-transactions
// Shows a full preview (agent count, vendor/product used, roughly how
// many transactions total) and asks for a y/n confirmation before
// writing anything.
// -------------------------------------------------------
class SeedChrisYap2026Transactions extends Command
{
    protected $signature = 'demo:seed-2026-transactions';
    protected $description = 'Create 12 months of demo Sales Transactions (5-12/month) for Chris Yap\'s whole team, so the Sales and Earning Income Forecast screen has real 2026 data to show';

    private const YEAR = 2026;
    private const REF_PREFIX = 'DEMO2026-';
    private const NAME_PREFIX = '(Demo) ';

    private const CUSTOMER_NAMES = [
        'Ahmad Faiz bin Zainal', 'Tan Wei Ling', 'Kumaresan a/l Muthu', 'Siti Aishah binti Hassan',
        'Chong Mei Yee', 'Raja Nazrin Shah', 'Nurul Huda binti Ismail', 'Vijay Kumar a/l Raman',
        'Wong Ka Yan', 'Farah Diyana binti Rosli', 'Ong Beng Hock', 'Priya Devi a/p Suresh',
        'Muhammad Hafiz bin Yusof', 'Cheah Sze Ying', 'Balasubramaniam a/l Krishnan',
        'Nor Aina binti Rahman', 'Lim Jia Wei', 'Sharifah Nadia binti Syed Omar',
        'Muniandy a/l Perumal', 'Teoh Kai Xin',
    ];

    public function handle(): int
    {
        $gl = DB::table('agents')
            ->where('role', 'GROUP_LEADER')
            ->where('full_name', 'Chris Yap')
            ->where('is_deleted', false)
            ->first();

        if (!$gl) {
            $this->error('No Group Leader named "Chris Yap" found — nothing created.');
            $this->line('If the name is slightly different, tell me the exact name and I\'ll adjust this command.');
            return self::FAILURE;
        }

        // NEW 23 Jul 2026 — per Chris: "Chris Yap has no active Team
        // Leaders or Introducers under him" turned out to be a bad
        // filter, not a missing-data problem. Every newly registered
        // agent starts as status=INACTIVE until they finish their own
        // email verification + password setup (HierarchyService::
        // registerUnderSponsor()) — a real, still-onboarding team can
        // easily be mostly INACTIVE. The Sales and Earning Income
        // Forecast dashboard itself never filters by agent status
        // (RenewalForecastController::childAgents() doesn't either), so
        // this shouldn't either — status is irrelevant to "does this
        // agent exist under Chris Yap", only relevant to who actually
        // receives a commission share (handled separately below).
        $downline = DB::table('agents')
            ->whereIn('role', ['TEAM_LEADER', 'INTRODUCER'])
            ->where('is_deleted', false)
            ->where(function ($q) use ($gl) {
                $q->where('hierarchy_path', 'like', '%/' . $gl->agent_id . '/%');
            })
            ->get(['agent_id', 'full_name', 'role', 'status']);

        if ($downline->isEmpty()) {
            $this->error('Chris Yap has no Team Leaders or Introducers under him at all — nothing to spread transactions across.');
            return self::FAILURE;
        }

        $inactiveCount = $downline->where('status', '!=', 'ACTIVE')->count();

        $vendor = DB::table('vendors')->where('industry', 'INSURANCE')->where('is_active', true)->orderBy('vendor_name')->first();
        if (!$vendor) {
            $this->error('No active insurance vendor found — set one up in Vendor and Product Maintenance first, then run this again.');
            return self::FAILURE;
        }

        $product = DB::table('products')->where('vendor_id', $vendor->vendor_id)->where('is_active', true)->orderBy('product_name')->first();
        if (!$product) {
            $this->error("Vendor \"{$vendor->vendor_name}\" has no active products — add one in Vendor and Product Maintenance first, then run this again.");
            return self::FAILURE;
        }

        $activeStatusId = DB::table('customer_statuses')->where('code', 'ACTIVE')->value('status_id');
        if (!$activeStatusId) {
            $this->error('No "ACTIVE" customer status found — this looks like a setup problem, not something this command can fix.');
            return self::FAILURE;
        }

        // A commission_structures row must exist for this vendor+product
        // or CommissionEngine::calculate() silently produces zero Earning
        // Income (by design — see the engine's own comment). Reuse an
        // existing active one if there is one; otherwise create a sane
        // default so the demo data actually shows non-zero earnings.
        $structure = DB::table('commission_structures')
            ->where('vendor_id', $vendor->vendor_id)
            ->where('product_id', $product->product_id)
            ->where('is_active', true)
            ->where('valid_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
            })
            ->first();

        $willCreateStructure = !$structure;

        $teamLeaderCount = $downline->where('role', 'TEAM_LEADER')->count();
        $introducerCount = $downline->where('role', 'INTRODUCER')->count();

        // Roll the per-month transaction counts now so the preview shows
        // exactly what will be created (same numbers get used below).
        $monthlyCounts = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyCounts[$m] = random_int(5, 12);
        }
        $totalCount = array_sum($monthlyCounts);

        $this->info("Group Leader: {$gl->full_name}");
        $this->line("Downline: {$teamLeaderCount} Team Leader(s), {$introducerCount} Introducer(s) — {$downline->count()} agents total to spread transactions across.");
        if ($inactiveCount > 0) {
            $this->line("Note: {$inactiveCount} of them are not yet status=ACTIVE (still pending their own email verification). Transactions will still be created for them and will show up on the Forecast screen — but per the normal commission rules, their OWN commission share only pays out once they're ACTIVE; until then it's credited to the system/admin account instead (same rule that applies to real transactions, not something special to this demo data).");
        }
        $this->line("Vendor / Product: {$vendor->vendor_name} — {$product->product_name}");
        if ($willCreateStructure) {
            $this->line('Commission structure: none found for this vendor/product — will create a default one (12% pool, split 40/35/25 Introducer/Team Leader/Group Leader).');
        } else {
            $this->line('Commission structure: using existing active structure (' . $structure->total_commission_pct . '% pool).');
        }
        $this->line('');
        $this->line('Transactions per month (Jan-Dec ' . self::YEAR . '): ' . implode(', ', $monthlyCounts));
        $this->line("Total: {$totalCount} transactions, each with its own dummy customer, renewal schedule, and commission records (via the real CommissionEngine).");
        $this->line('All tagged "' . self::REF_PREFIX . '..." (reference) and "' . self::NAME_PREFIX . '..." (customer name) so this batch can be found and removed later.');
        $this->line('');

        if (!$this->confirm('Create this demo data now?', false)) {
            $this->warn('Cancelled — nothing created.');
            return self::SUCCESS;
        }

        if ($willCreateStructure) {
            $structureId = (string) Str::uuid();
            DB::table('commission_structures')->insert([
                'structure_id'         => $structureId,
                'vendor_id'            => $vendor->vendor_id,
                'product_id'           => $product->product_id,
                'commission_basis'     => 'PREMIUM_PCT',
                'total_commission_pct' => 12.0000,
                'introducer_pct'       => 40.0000,
                'team_leader_pct'      => 35.0000,
                'group_leader_pct'     => 25.0000,
                'valid_from'           => '2020-01-01',
                'valid_to'             => null,
                'is_active'            => true,
                'created_by'           => null,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        $commissionEngine = app(CommissionEngine::class);
        $today = Carbon::today();
        $created = 0;

        $this->getOutput()->progressStart($totalCount);

        foreach ($monthlyCounts as $month => $count) {
            for ($i = 0; $i < $count; $i++) {
                $agent = $downline->random();

                $daysInMonth = Carbon::create(self::YEAR, $month, 1)->daysInMonth;
                $coverageEnd = Carbon::create(self::YEAR, $month, random_int(1, $daysInMonth));
                $coverageStart = $coverageEnd->copy()->subYear();

                $customerId = (string) Str::uuid();
                $customerName = self::NAME_PREFIX . self::CUSTOMER_NAMES[array_rand(self::CUSTOMER_NAMES)];
                DB::table('customers')->insert([
                    'customer_id'       => $customerId,
                    'nric_encrypted'    => null,
                    'nric_hash'         => null,
                    'full_name'         => $customerName,
                    'email'             => null,
                    'phone'             => '01' . random_int(1, 9) . '-' . random_int(1000000, 9999999),
                    'address'           => null,
                    'postcode'          => null,
                    'city'              => null,
                    'state'             => null,
                    'owned_by_agent_id' => $agent->agent_id,
                    'status_id'         => $activeStatusId,
                    'is_deleted'        => false,
                    'created_by'        => null,
                    'updated_by'        => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                $policyId = (string) Str::uuid();
                $refNumber = self::REF_PREFIX . strtoupper(Str::random(10));
                $premium = round(random_int(50000, 500000) / 100, 2);

                DB::table('sales_transactions')->insert([
                    'policy_id'                 => $policyId,
                    'policy_number'             => $refNumber,
                    'document_reference_number'=> $refNumber,
                    'vendor_id'                 => $vendor->vendor_id,
                    'product_id'                => $product->product_id,
                    'customer_id'               => $customerId,
                    'agent_id'                  => $agent->agent_id,
                    'premium_amount'            => $premium,
                    'sum_insured'               => $premium * 10,
                    'coverage_start'            => $coverageStart->toDateString(),
                    'coverage_end'              => $coverageEnd->toDateString(),
                    'renewal_date'              => null,
                    'status'                    => 'ACTIVE',
                    'flagged_for_review'        => false,
                    'is_deleted'                => false,
                    'version'                   => 1,
                    'created_by'                => null,
                    'updated_by'                => null,
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ]);

                $renewalStatus = 'UPCOMING';
                if ($coverageEnd->lt($today)) {
                    $renewalStatus = 'OVERDUE';
                } elseif ($coverageEnd->lte($today->copy()->addDays(30))) {
                    $renewalStatus = 'DUE';
                }

                DB::table('insurance_renewal_schedules')->insert([
                    'renewal_id'             => (string) Str::uuid(),
                    'policy_id'              => $policyId,
                    'coverage_start'         => $coverageStart->toDateString(),
                    'coverage_end'           => $coverageEnd->toDateString(),
                    'reminder_scheduled_date'=> $coverageEnd->copy()->subDays(30)->toDateString(),
                    'reminder_message'       => null,
                    'renewal_date'           => null,
                    'status'                 => $renewalStatus,
                    'reminder_sent_at'       => null,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);

                $commissionEngine->calculate($policyId);

                $created++;
                $this->getOutput()->progressAdvance();
            }
        }

        $this->getOutput()->progressFinish();
        $this->info("Done — created {$created} demo Sales Transactions for {$gl->full_name}'s team across all 12 months of " . self::YEAR . '.');
        $this->line('Open the Sales and Earning Income Forecast screen (as Chris Yap or Admin viewing his group) to see them.');
        $this->line('To remove this batch later: php artisan demo:remove-2026-transactions');

        return self::SUCCESS;
    }
}
