<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

// NEW 25 Jul 2026 — per Chris: "i want to see sample data to incorporate
// customer category occupation, type and sales contribution sales
// amount and earning amount. i want you to create about 300 customer
// in details, source, for all fields randomly assign to introducer, tl
// and GL and state is randomly distributed to 14 states including
// Kuala Lumpur." Pure sample/test data for exercising the Customer KPI
// Dashboard (categories/types/occupations/sources/states/statuses all
// populated with real variety) — never touches CommissionEngine.php;
// commission_transactions rows are inserted directly with a randomized
// realistic percentage rather than run through the real engine, which
// is fine for display/testing purposes only.
//
// Every seeded customer's email ends in @sampledata.local so these rows
// are easy to find and remove later if ever needed:
//   DB::table('customers')->where('email','like','%@sampledata.local')->delete();
// (their sales_transactions/commission_transactions cascade-delete
// automatically since both FKs reference customers/sales_transactions
// with onDelete behavior already defined in their own migrations —
// verify in a staging copy first if this is ever run for real cleanup).
//
// Run with: php artisan db:seed --class=CustomerKpiSampleDataSeeder
class CustomerKpiSampleDataSeeder extends Seeder
{
    private const TOTAL_CUSTOMERS = 300;

    // 13 states + Kuala Lumpur (Federal Territory) = 14, per Chris's
    // explicit request.
    private const STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Malacca', 'Negeri Sembilan', 'Pahang',
        'Penang', 'Perak', 'Perlis', 'Sabah', 'Sarawak', 'Selangor',
        'Terengganu', 'Kuala Lumpur',
    ];

    private const MALAY_FIRST = ['Ahmad', 'Muhammad', 'Wan', 'Nur', 'Siti', 'Aina', 'Amir', 'Farah', 'Hafiz', 'Aisyah', 'Azman', 'Fatimah', 'Ismail', 'Rosli', 'Hasnah', 'Zainab', 'Rashid', 'Halim', 'Latifah', 'Sabrina', 'Firdaus', 'Sofia', 'Iskandar', 'Zul'];
    private const MALAY_LAST = ['Abdullah', 'Rahman', 'Yusof', 'Ismail', 'Hassan', 'Kassim', 'Ibrahim', 'Salleh', 'Osman', 'Zainal', 'Bakar', 'Mahmud', 'Aziz', 'Karim', 'Hamid'];
    private const CHINESE_FIRST = ['Wei Ling', 'Jia Hui', 'Mei Ling', 'Kai Xin', 'Zhi Hao', 'Xin Yi', 'Jun Wei', 'Hui Min', 'Cheng Long', 'Li Wen', 'Xiao Ming', 'Yan Ling', 'Kok Wai', 'Siew Ling', 'Boon Huat', 'Choon Hong'];
    private const CHINESE_LAST = ['Tan', 'Lim', 'Lee', 'Wong', 'Ng', 'Chan', 'Ong', 'Goh', 'Teo', 'Yeoh', 'Chong', 'Low', 'Koh', 'Chua', 'Sim'];
    private const INDIAN_FIRST = ['Raj', 'Priya', 'Kumar', 'Devi', 'Suresh', 'Kavitha', 'Arun', 'Meena', 'Vijay', 'Lakshmi', 'Ravi', 'Anitha', 'Ganesh', 'Shanti', 'Prakash'];
    private const INDIAN_LAST = ['Muthu', 'Raman', 'Krishnan', 'Pillai', 'Nair', 'Subramaniam', 'Devan', 'Chandran', 'Kumaran', 'Selvam'];

    public function run(): void
    {
        $agents = DB::table('agents')
            ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
            ->where('is_deleted', false)
            ->get(['agent_id', 'role'])
            ->groupBy('role');

        $introducers = $agents->get('INTRODUCER', collect())->values();
        $teamLeaders = $agents->get('TEAM_LEADER', collect())->values();
        $groupLeaders = $agents->get('GROUP_LEADER', collect())->values();

        if ($introducers->isEmpty() && $teamLeaders->isEmpty() && $groupLeaders->isEmpty()) {
            $this->command->error('No Introducer/Team Leader/Group Leader agents found — create at least one agent of any of these roles first, then re-run this seeder.');
            return;
        }

        $categories = DB::table('customer_categories')->where('is_active', true)->pluck('category_id');
        $types = DB::table('customer_types')->where('is_active', true)->pluck('type_id');
        $occupations = DB::table('occupation_groups')->where('is_active', true)->pluck('occupation_group_id');
        $sources = DB::table('customer_sources')->where('is_active', true)->pluck('source_id');

        $statusRows = DB::table('customer_statuses')->whereIn('code', [
            'ACTIVE', 'PROSPECT', 'INACTIVE', 'RENEWAL_DUE', 'POLICY_ISSUED', 'IN_PROGRESS', 'WAITING_DOCUMENTS',
        ])->pluck('status_id', 'code');
        // Weighted pool — mostly Active, a realistic pipeline spread for
        // everything else, so the "By Status" box has real variety.
        $statusWeighted = array_merge(
            array_fill(0, 55, $statusRows['ACTIVE'] ?? null),
            array_fill(0, 10, $statusRows['PROSPECT'] ?? null),
            array_fill(0, 5, $statusRows['INACTIVE'] ?? null),
            array_fill(0, 10, $statusRows['RENEWAL_DUE'] ?? null),
            array_fill(0, 8, $statusRows['POLICY_ISSUED'] ?? null),
            array_fill(0, 7, $statusRows['IN_PROGRESS'] ?? null),
            array_fill(0, 5, $statusRows['WAITING_DOCUMENTS'] ?? null),
        );
        $statusWeighted = array_values(array_filter($statusWeighted));

        $vendors = DB::table('vendors')->where('is_active', true)->pluck('vendor_id');
        if ($vendors->isEmpty()) {
            $this->command->error('No active vendors found — seed at least one vendor/product first (see DatabaseSeeder), then re-run.');
            return;
        }
        $productsByVendor = DB::table('products')->where('is_active', true)->get()->groupBy('vendor_id');
        $structuresByVendorProduct = DB::table('commission_structures')->where('is_active', true)->get()
            ->groupBy(fn ($s) => $s->vendor_id . ':' . $s->product_id);
        $anyStructure = DB::table('commission_structures')->where('is_active', true)->inRandomOrder()->first();

        if ($anyStructure === null) {
            $this->command->error('No active commission_structures found — set up at least one Earning Income Structure first, then re-run.');
            return;
        }

        $now = now();
        $customerRows = [];
        $customerIds = [];

        for ($i = 0; $i < self::TOTAL_CUSTOMERS; $i++) {
            $customerId = (string) Str::uuid();
            $customerIds[] = $customerId;

            // Owner: weighted 70% Introducer / 20% TL / 10% GL, per
            // Chris — falls back gracefully if a role has zero agents.
            $roll = mt_rand(1, 100);
            $ownerPool = $roll <= 70 && $introducers->isNotEmpty() ? $introducers
                : ($roll <= 90 && $teamLeaders->isNotEmpty() ? $teamLeaders
                : ($groupLeaders->isNotEmpty() ? $groupLeaders
                : ($introducers->isNotEmpty() ? $introducers : $teamLeaders)));
            $owner = $ownerPool->random();

            [$fullName, $email] = $this->randomIdentity($i);
            $createdAt = Carbon::now()->subDays(mt_rand(0, 540));

            $customerRows[] = [
                'customer_id'           => $customerId,
                'status_id'             => $statusWeighted[array_rand($statusWeighted)],
                'customer_type_id'      => mt_rand(1, 100) <= 85 ? $types[array_rand($types->all())] : null,
                'customer_category_id'  => mt_rand(1, 100) <= 90 ? $categories[array_rand($categories->all())] : null,
                'occupation_group_id'   => mt_rand(1, 100) <= 85 ? $occupations[array_rand($occupations->all())] : null,
                'source_id'             => mt_rand(1, 100) <= 90 ? $sources[array_rand($sources->all())] : null,
                'nric_encrypted'        => null,
                'nric_hash'             => null,
                'full_name'             => $fullName,
                'email'                 => $email,
                'phone'                 => '01' . mt_rand(0, 9) . '-' . mt_rand(1000000, 9999999),
                'address'               => null,
                'postcode'              => (string) mt_rand(10000, 98000),
                'city'                  => null,
                'state'                 => self::STATES[array_rand(self::STATES)],
                'owned_by_agent_id'     => $owner->agent_id,
                'is_deleted'            => false,
                'created_by'            => $owner->agent_id,
                'updated_by'            => null,
                'created_at'            => $createdAt,
                'updated_at'            => $createdAt,
                '_owner_agent_id'       => $owner->agent_id, // stripped before insert; used below
                '_owner_role'           => $owner->role,     // stripped before insert; used below
            ];
        }

        // Insert customers in chunks (strip the two helper keys first),
        // then build sales_transactions + commission_transactions per
        // customer using the same in-memory rows (avoids re-querying).
        foreach (array_chunk($customerRows, 50) as $chunk) {
            $insertable = array_map(function ($row) {
                unset($row['_owner_agent_id'], $row['_owner_role']);
                return $row;
            }, $chunk);
            DB::table('customers')->insert($insertable);
        }

        $salesRows = [];
        $earnRows = [];

        foreach ($customerRows as $row) {
            $customerId = $row['customer_id'];
            $agentId = $row['_owner_agent_id'];
            $role = $row['_owner_role'];
            $numPolicies = mt_rand(1, 4);

            for ($p = 0; $p < $numPolicies; $p++) {
                $vendorId = $vendors[array_rand($vendors->all())];
                $productsForVendor = $productsByVendor->get($vendorId, collect());
                if ($productsForVendor->isEmpty()) {
                    continue;
                }
                $product = $productsForVendor->random();

                $policyId = (string) Str::uuid();
                $policyNumber = 'POL-' . strtoupper(Str::random(8));
                $premium = round(mt_rand(30000, 600000) / 100, 2); // RM 300.00 - RM 6,000.00
                $coverageStart = Carbon::now()->subDays(mt_rand(0, 500));
                $coverageEnd = (clone $coverageStart)->addYear();
                $txnCreatedAt = $coverageStart;

                $salesRows[] = [
                    'policy_id'                  => $policyId,
                    'policy_number'              => $policyNumber,
                    'document_reference_number' => $policyNumber,
                    'vendor_id'                  => $vendorId,
                    'product_id'                 => $product->product_id,
                    'customer_id'                => $customerId,
                    'agent_id'                   => $agentId,
                    'premium_amount'             => $premium,
                    'sum_insured'                => null,
                    'coverage_start'             => $coverageStart->toDateString(),
                    'coverage_end'               => $coverageEnd->toDateString(),
                    'renewal_date'               => null,
                    'status'                     => 'ACTIVE',
                    'flagged_for_review'         => false,
                    'flag_reason'                => null,
                    'reviewed_by'                => null,
                    'reviewed_at'                => null,
                    'upload_batch_id'            => null,
                    'template_id'                => null,
                    'version'                    => 1,
                    'previous_version_id'        => null,
                    'is_deleted'                 => false,
                    'created_by'                 => $agentId,
                    'updated_by'                 => null,
                    'created_at'                 => $txnCreatedAt,
                    'updated_at'                 => $txnCreatedAt,
                ];

                $structure = $structuresByVendorProduct->get($vendorId . ':' . $product->product_id, collect())->first() ?? $anyStructure;
                $commissionPct = mt_rand(8, 25); // % of premium, randomized for realistic spread
                $commissionAmount = round($premium * $commissionPct / 100, 2);

                $earnRows[] = [
                    'txn_id'                => (string) Str::uuid(),
                    'policy_id'             => $policyId,
                    'agent_id'              => $agentId,
                    'structure_id'          => $structure->structure_id,
                    'role_at_transaction'   => $role,
                    'policy_premium'        => $premium,
                    'total_pool_amount'     => round($premium * ((float) $structure->total_commission_pct) / 100, 2),
                    'entitlement_pct'       => $commissionPct,
                    'commission_amount'     => $commissionAmount,
                    'is_breakage'           => false,
                    'redistribution_reason' => null,
                    'reward_points_rate_id' => null,
                    'reward_points_earned'  => 0,
                    'status'                => 'CONFIRMED',
                    'reversed_by_txn_id'    => null,
                    'created_by'            => $agentId,
                    'created_at'            => $txnCreatedAt,
                    'updated_at'            => $txnCreatedAt,
                ];
            }
        }

        foreach (array_chunk($salesRows, 100) as $chunk) {
            DB::table('sales_transactions')->insert($chunk);
        }
        foreach (array_chunk($earnRows, 100) as $chunk) {
            DB::table('commission_transactions')->insert($chunk);
        }

        $this->command->info('Seeded ' . count($customerRows) . ' sample customers, ' . count($salesRows) . ' sales transactions, ' . count($earnRows) . ' commission transactions.');
        $this->command->info('All sample customer emails end in @sampledata.local — safe to identify/remove later if needed.');
    }

    /** @return array{0:string,1:string} [$fullName, $email] */
    private function randomIdentity(int $i): array
    {
        $roll = mt_rand(1, 100);
        if ($roll <= 60) {
            $first = self::MALAY_FIRST[array_rand(self::MALAY_FIRST)];
            $last = self::MALAY_LAST[array_rand(self::MALAY_LAST)];
        } elseif ($roll <= 85) {
            $first = self::CHINESE_FIRST[array_rand(self::CHINESE_FIRST)];
            $last = self::CHINESE_LAST[array_rand(self::CHINESE_LAST)];
        } else {
            $first = self::INDIAN_FIRST[array_rand(self::INDIAN_FIRST)];
            $last = self::INDIAN_LAST[array_rand(self::INDIAN_LAST)];
        }
        $fullName = $first . ' ' . $last;
        $emailSlug = Str::slug($first . '-' . $last) . '-' . $i;
        return [$fullName, $emailSlug . '@sampledata.local'];
    }
}
