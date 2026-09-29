<?php

namespace App\Console\Commands;

use App\Services\CbeAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 16 Sep 2026 — per Chris: "your role is a professional 30 over
// years in Financial Accounting, specialist in managing NGO/Temple/SME
// community business enterprise group. base on your experience you set
// up all the records for me. It must be relevant to all this
// environment NOT just temple." This seeds every master file that
// cbe:master-file-status reported as EMPTY, using general-purpose
// defaults that make sense for any NGO/Temple/SME/community business
// enterprise — nothing temple-specific.
//
// Two different kinds of "empty" are handled differently:
// 1. Category/reference catalogs with no real-world identity of their
//    own (Debtor Category, FA Category, Payment Terms, Payment
//    Methods, Bank Transaction Types, Fund Master, Tax Rates, FA
//    Location) — these get real, sensible professional defaults,
//    exactly like cbe_supplier_categories was seeded on 3 Sep 2026.
// 2. Entity records that name a real person/company/bank (Debtor
//    Master, Creditor Master, Bank Account Number, CBE Vendors) — per
//    Chris's explicit instruction not to leave these blank, ONE clearly
//    labelled placeholder row is inserted per file so the screen is
//    never empty, but every value is obviously a placeholder ("(edit
//    or delete this placeholder)" / "PENDING-UPDATE") so nobody
//    mistakes it for a real record. Chris edits these through the
//    normal Admin screen (not code) once he has the real details.
//
// Marketplace Listings/Orders/Campaigns are left alone — they are
// transactions, not master data, and fill in naturally as the
// marketplace is used.
//
// Fully idempotent (checks before inserting) — safe to run more than
// once. Wrapped in a single DB transaction.
class SetupCbeMasterFiles extends Command
{
    protected $signature = 'cbe:setup-master-files';

    protected $description = 'Seed every empty CBE master file (accounting + vendor + practitioner) with professional NGO/SME-generic defaults, for the Tao Malaysia group / Klang entity';

    private const GROUP_NAME = 'Persekutuan Pertubuhan Agama Tao Malaysia';

    private const NODE_NAME = 'Cawangan Bandar Di Raja Klang';

    // Chris's own agent record, created by cbe:import-klang-committee —
    // used as created_by on the placeholder entity records, and as the
    // practitioner for the appointment-booking test setup.
    private const OWNER_FULL_NAME = 'Yap Wai Jyh';

    public function handle(): int
    {
        $groupId = DB::table('group_labels')->where('group_name', self::GROUP_NAME)->where('group_type', 'CBE')->value('group_label_id');
        $nodeId = $groupId ? DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupId)->where('node_name', self::NODE_NAME)->value('node_id') : null;
        $ownerAgentId = DB::table('agents')->where('full_name', self::OWNER_FULL_NAME)->where('is_deleted', false)->value('agent_id');

        if (! $groupId || ! $nodeId) {
            $this->error('Group/entity not found. Run cbe:import-klang-committee first.');

            return self::FAILURE;
        }
        if (! $ownerAgentId) {
            $this->error('Could not find agent "'.self::OWNER_FULL_NAME.'" to use as created_by / practitioner. Run cbe:import-klang-committee first.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $this->seedCustomerCategories();
            $this->seedAssetCategories();
            $this->seedPaymentTerms();
            $this->seedPaymentMethods();
            CbeAccountingService::ensureBankTransactionTypes($groupId);
            $this->info('Bank Transaction Types Master: ensured (Deposit, Withdrawal, Bank Transfer, Bank Charge, Bank Interest, Direct Debit, Direct Credit, Cheque, Online Transfer, Other Bank Transaction).');

            $this->seedAssetLocations($nodeId);
            $this->seedFunds($nodeId, $ownerAgentId);
            $this->seedTaxRates($nodeId, $ownerAgentId);

            $this->seedPlaceholderCustomer($nodeId, $ownerAgentId);
            $this->seedPlaceholderSupplier($nodeId, $ownerAgentId);
            $this->seedPlaceholderBankAccount($nodeId, $ownerAgentId);
            $this->seedPlaceholderVendor($ownerAgentId);

            $this->setupPractitioner($nodeId, $ownerAgentId);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed, rolled back — nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. All previously-empty master files now have starter records.');
        $this->line('Entity/company-specific placeholders (Debtor Master, Creditor Master, Bank Account Number, CBE Vendors) are clearly marked "(placeholder)" — edit or delete them from their normal Admin screens once you have the real details.');

        return self::SUCCESS;
    }

    private function seedCustomerCategories(): void
    {
        if (DB::table('cbe_customer_categories')->whereNull('group_label_id')->exists()) {
            $this->info('Debtor Category: already has platform defaults, left as-is.');

            return;
        }

        $names = ['Walk-in / Cash Customer', 'Registered Member', 'Corporate Sponsor', 'Government / Institutional Body'];
        foreach ($names as $i => $name) {
            DB::table('cbe_customer_categories')->insert([
                'category_id' => (string) Str::uuid(),
                'group_label_id' => null,
                'category_name' => $name,
                'category_name_zh' => null,
                'is_active' => true,
                'display_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info('Debtor Category: added 4 platform defaults.');
    }

    private function seedAssetCategories(): void
    {
        if (DB::table('cbe_asset_categories')->whereNull('group_label_id')->exists()) {
            $this->info('FA Category: already has platform defaults, left as-is.');

            return;
        }

        $names = [
            'Land & Building', 'Building Improvements / Renovation', 'Furniture & Fittings',
            'Office Equipment & IT', 'Motor Vehicles', 'Machinery & Tools', 'Other Fixed Assets',
        ];
        foreach ($names as $i => $name) {
            DB::table('cbe_asset_categories')->insert([
                'category_id' => (string) Str::uuid(),
                'group_label_id' => null,
                'category_name' => $name,
                'category_name_zh' => null,
                'fixed_asset_account_id' => null,
                'accum_depreciation_account_id' => null,
                'depreciation_expense_account_id' => null,
                'default_useful_life_months' => null,
                'default_depreciation_method' => 'STRAIGHT_LINE',
                'is_active' => true,
                'display_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info('FA Category: added 7 platform defaults (unmapped to a GL account — will use the existing global Fixed Asset accounts until you map each one individually).');
    }

    private function seedPaymentTerms(): void
    {
        if (DB::table('cbe_payment_terms')->whereNull('group_label_id')->exists()) {
            $this->info('Payment Terms Master: already has platform defaults, left as-is.');

            return;
        }

        $terms = [
            ['Cash / Immediate', 0], ['7 Days', 7], ['14 Days', 14], ['30 Days', 30], ['60 Days', 60],
        ];
        foreach ($terms as $i => [$name, $days]) {
            DB::table('cbe_payment_terms')->insert([
                'term_id' => (string) Str::uuid(),
                'group_label_id' => null,
                'term_name' => $name,
                'net_days' => $days,
                'is_active' => true,
                'display_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info('Payment Terms Master: added 5 platform defaults.');
    }

    private function seedPaymentMethods(): void
    {
        if (DB::table('cbe_payment_methods')->whereNull('group_label_id')->exists()) {
            $this->info('Payment Methods Master: already has platform defaults, left as-is.');

            return;
        }

        $names = ['Cash', 'Bank Transfer / Online Banking', 'Cheque', 'Credit / Debit Card', "E-Wallet (Touch 'n Go / Boost / GrabPay)", 'Standing Instruction'];
        foreach ($names as $i => $name) {
            DB::table('cbe_payment_methods')->insert([
                'method_id' => (string) Str::uuid(),
                'group_label_id' => null,
                'method_name' => $name,
                'is_active' => true,
                'display_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info('Payment Methods Master: added 6 platform defaults.');
    }

    private function seedAssetLocations(string $nodeId): void
    {
        if (DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)->exists()) {
            $this->info('FA Location: already has entries, left as-is.');

            return;
        }

        $names = ['Main Office', 'Meeting / Multi-Purpose Hall', 'Storage Room'];
        foreach ($names as $i => $name) {
            DB::table('cbe_asset_locations')->insert([
                'location_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'location_name' => $name,
                'location_name_zh' => null,
                'is_active' => true,
                'display_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info('FA Location: added 3 starter locations for '.self::NODE_NAME.'.');
    }

    private function seedFunds(string $nodeId, string $ownerAgentId): void
    {
        if (DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->exists()) {
            $this->info('Fund Master: already has entries, left as-is.');

            return;
        }

        $funds = [
            ['General Fund', 'UNRESTRICTED', 'Day-to-day operating income and expenses.'],
            ['Building / Development Fund', 'RESTRICTED', 'Donations and income earmarked for building, renovation, or capital projects.'],
            ['Welfare / Charity Fund', 'RESTRICTED', 'Donations and income earmarked for welfare, relief, or charitable activities.'],
        ];
        foreach ($funds as [$name, $type, $desc]) {
            DB::table('cbe_funds')->insert([
                'fund_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'fund_name' => $name,
                'fund_type' => $type,
                'description' => $desc,
                'is_active' => true,
                'created_by' => $ownerAgentId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info('Fund Master: added 3 starter funds for '.self::NODE_NAME.'.');
    }

    private function seedTaxRates(string $nodeId, string $ownerAgentId): void
    {
        if (DB::table('cbe_tax_rates')->where('cbe_node_id', $nodeId)->exists()) {
            $this->info('Tax Rates Master: already has entries, left as-is.');

            return;
        }

        $rates = [['No Tax / Exempt', 0.00], ['SST 6% (Service Tax)', 6.00], ['SST 10% (Sales Tax)', 10.00]];
        foreach ($rates as [$name, $pct]) {
            DB::table('cbe_tax_rates')->insert([
                'rate_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'rate_name' => $name,
                'rate_percent' => $pct,
                'is_active' => true,
                'created_by' => $ownerAgentId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info('Tax Rates Master: added 3 starter rates for '.self::NODE_NAME.' (most NGO/donation income is exempt — the SST rates are there only if you ever sell taxable goods/services, e.g. via the marketplace).');
    }

    private function seedPlaceholderCustomer(string $nodeId, string $ownerAgentId): void
    {
        if (DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->exists()) {
            $this->info('Debtor Master: already has entries, left as-is.');

            return;
        }

        DB::table('cbe_customers')->insert([
            'customer_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'customer_name' => 'Sample Customer (placeholder - edit or delete)',
            'contact_person' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => 'Placeholder inserted 16 Sep 2026 so this screen is not empty. Replace with your real customer, or delete this row.',
            'created_by' => $ownerAgentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info('Debtor Master: added 1 placeholder row.');
    }

    private function seedPlaceholderSupplier(string $nodeId, string $ownerAgentId): void
    {
        if (DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->exists()) {
            $this->info('Creditor Master: already has entries, left as-is.');

            return;
        }

        DB::table('cbe_suppliers')->insert([
            'supplier_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'supplier_code' => 'SUP-0001',
            'supplier_name' => 'Sample Supplier (placeholder - edit or delete)',
            'business_reg_no' => null,
            'category_id' => null,
            'payment_term_id' => null,
            'payment_method_id' => null,
            'bank_name' => null,
            'bank_account_no' => null,
            'bank_account_holder' => null,
            'is_active' => true,
            'contact_person' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => 'Placeholder inserted 16 Sep 2026 so this screen is not empty. Replace with your real supplier, or delete this row.',
            'created_by' => $ownerAgentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info('Creditor Master: added 1 placeholder row.');
    }

    private function seedPlaceholderBankAccount(string $nodeId, string $ownerAgentId): void
    {
        if (DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->exists()) {
            $this->info('Bank Account Number: already has entries, left as-is.');

            return;
        }

        DB::table('cbe_bank_accounts')->insert([
            'bank_account_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'bank_name' => 'To Be Confirmed',
            'account_name' => 'Main Operating Account (placeholder - update with real bank details)',
            'account_number' => 'PENDING-UPDATE',
            'is_active' => true,
            'created_by' => $ownerAgentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info('Bank Account Number: added 1 placeholder row — update it with your real bank name/account number from the Bank Account screen.');
    }

    private function seedPlaceholderVendor(string $ownerAgentId): void
    {
        if (DB::table('cbe_vendors')->exists()) {
            $this->info('CBE Vendors: already has entries, left as-is.');

            return;
        }

        DB::table('cbe_vendors')->insert([
            'vendor_id' => (string) Str::uuid(),
            'vendor_name' => 'Sample Vendor (placeholder - edit or delete)',
            'contact_person' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => 'Placeholder inserted 16 Sep 2026 so this screen is not empty. Replace with your real vendor, or delete this row.',
            'status' => 'ACTIVE',
            'created_by' => $ownerAgentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->info('CBE Vendors: added 1 placeholder row.');
    }

    private function setupPractitioner(string $nodeId, string $ownerAgentId): void
    {
        $existing = DB::table('cbe_practitioner_profiles')->where('cbe_node_id', $nodeId)->where('agent_id', $ownerAgentId)->first();
        if ($existing) {
            $this->info('Practitioner / Appointment Setup: already set up, left as-is.');

            return;
        }

        $consultantTypeId = DB::table('cbe_practitioner_types')->where('code', 'CONSULTANT')->value('id');
        if (! $consultantTypeId) {
            $this->warn('Practitioner / Appointment Setup: skipped — "Consultant" practitioner type not found.');

            return;
        }

        $profileId = (string) Str::uuid();
        DB::table('cbe_practitioner_profiles')->insert([
            'id' => $profileId,
            'cbe_node_id' => $nodeId,
            'agent_id' => $ownerAgentId,
            'practitioner_type_id' => $consultantTypeId,
            'slot_duration_minutes' => 30,
            'max_slots_per_day' => 16,
            'booking_window_days' => 60,
            'max_upcoming_per_member' => 1,
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Mon-Fri 9am-5pm starter schedule. day_of_week: 0=Sun..6=Sat.
        foreach ([1, 2, 3, 4, 5] as $day) {
            DB::table('cbe_practitioner_weekly_hours')->insert([
                'id' => (string) Str::uuid(),
                'practitioner_profile_id' => $profileId,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->info('Practitioner / Appointment Setup: set up '.self::OWNER_FULL_NAME.' as a Consultant at '.self::NODE_NAME.', Mon-Fri 9am-5pm, 30-min slots — ready to test booking.');
    }
}
