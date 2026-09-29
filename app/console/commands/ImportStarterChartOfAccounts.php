<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 8 Sep 2026 — one-off Chart of Accounts import, run once by Chris
// from the command line. Source: the "Temple Management Accounting
// System COA" Word file he uploaded, with three changes he explicitly
// asked for on review:
//   1. Structural/generic labels renamed Temple -> Entity (English)
//      and 庙 -> 机构 (Chinese) — e.g. "Temple Buildings" -> "Entity
//      Buildings" — since CBE serves many kinds of organizations
//      (associations, chambers of commerce, federations), not only
//      temples. Genuinely religious-practice accounts (Incense, Joss
//      Paper, Lamp Offering, Priest Fees, Ceremony, Ancestral Tablet)
//      are kept exactly as given — they describe what THIS
//      organization actually does, not the system.
//   2. Added "Membership Fee Income" (4050) — the AI Accounting
//      Automation module already classifies transactions into a
//      MEMBERSHIP category; this file had no matching account for it.
//   3. Added "Grant Income" (4940) and "Investment Income" (4950)
//      under Other Income, per Chris's request.
//
// This import is scoped to ONE CBE community (group_labels.group_type
// = 'CBE') via cbe_node_id = NULL / group_label_id = that community's
// id — i.e. a shared, federation-wide master set, exactly like the
// existing ensureChartOfAccounts() default accounts. It never touches
// or is visible to any other CBE community that signs up later —
// group_label_id is the isolation boundary the rest of the app already
// relies on (see GroupLabelController).
//
// Idempotent: re-running skips any account_code that already exists
// for the target group, so it's safe to run more than once.
class ImportStarterChartOfAccounts extends Command
{
    protected $signature = 'cbe:import-coa {--group= : group_labels.group_label_id to import into (required if more than one CBE community exists)}';

    protected $description = 'One-off import of a starter Chart of Accounts (with Entity-neutral wording) for one CBE community.';

    /**
     * Each entry: [code, english name, chinese name, optional children[]].
     * Children are [code, english name, chinese name].
     * account_type / normal_balance are derived from the leading digit
     * of the code (1=ASSET, 2=LIABILITY, 3=EQUITY, 4=INCOME, 5/6=EXPENSE).
     */
    private function accountBlocks(): array
    {
        return [
            // ---------------- ASSETS ----------------
            ['1100', 'Cash and Cash Equivalents', '现金及现金等价物', [
                ['1110', 'Entity Petty Cash', '机构备用现金'],
                ['1120', 'General Cash', '普通现金'],
                ['1130', 'Event Cash', '活动现金'],
                ['1140', 'Donation Box Cash', '功德箱现金'],
                ['1150', 'Offering Box Cash', '供奉箱现金'],
            ]],
            ['1200', 'Bank Accounts', '银行账户', [
                ['1210', 'General Operating Bank Account', '机构普通银行账户'],
                ['1220', 'Event Bank Account', '活动银行账户'],
                ['1230', 'Building Fund Bank Account', '建筑基金银行账户'],
                ['1240', 'Charity Fund Bank Account', '慈善基金银行账户'],
                ['1250', 'Restricted Fund Bank Account', '指定用途基金银行账户'],
                ['1260', 'Savings Account', '储蓄账户'],
                ['1270', 'Fixed Deposit', '定期存款'],
                ['1280', 'Payment Gateway Account', '电子支付账户'],
            ]],
            ['1300', 'Accounts Receivable', '应收账款', [
                ['1310', 'Service Receivables', '服务应收款'],
                ['1320', 'Donation Pledges Receivable', '承诺捐款应收款'],
                ['1330', 'Rental Receivables', '租金应收款'],
                ['1340', 'Other Receivables', '其他应收款'],
                ['1350', 'Staff Advance', '员工预支款'],
                ['1360', 'Deposit Receivable', '应收押金'],
            ]],
            ['1400', 'Inventory', '存货', [
                ['1410', 'Incense Inventory', '香存货'],
                ['1420', 'Candle Inventory', '蜡烛存货'],
                ['1430', 'Joss Paper Inventory', '金纸存货'],
                ['1440', 'Religious Items Inventory', '宗教用品存货'],
                ['1450', 'Souvenir Inventory', '纪念品存货'],
                ['1460', 'Books and Publications Inventory', '经书及出版物存货'],
                ['1470', 'Other Merchandise Inventory', '其他商品存货'],
            ]],
            ['1500', 'Prepaid Expenses', '预付费用', [
                ['1510', 'Prepaid Insurance', '预付保险费'],
                ['1520', 'Prepaid Rent', '预付租金'],
                ['1530', 'Prepaid Software', '预付软件费用'],
                ['1540', 'Prepaid Service Contracts', '预付服务合约费用'],
                ['1550', 'Other Prepayments', '其他预付款'],
            ]],
            ['1600', 'Property and Equipment', '固定资产', [
                ['1610', 'Land', '土地'],
                ['1620', 'Entity Buildings', '机构建筑物'],
                ['1630', 'Prayer Hall', '大殿／礼拜殿'],
                ['1640', 'Ancillary Buildings', '附属建筑物'],
                ['1650', 'Renovation', '装修工程'],
                ['1660', 'Furniture and Fixtures', '家具及装置'],
                ['1670', 'Office Equipment', '办公设备'],
                ['1680', 'Computer Equipment', '电脑设备'],
                ['1690', 'Network Equipment', '网络设备'],
                ['1700', 'CCTV and Security Equipment', '监控及保安设备'],
                ['1710', 'Air Conditioning Equipment', '空调设备'],
                ['1720', 'Sound System', '音响系统'],
                ['1730', 'Religious Equipment', '宗教设备'],
                ['1740', 'Entity Furniture', '机构家具'],
                ['1750', 'Vehicles', '车辆'],
            ]],
            ['1800', 'Accumulated Depreciation', '累计折旧', [
                ['1810', 'Accumulated Depreciation - Buildings', '建筑物累计折旧'],
                ['1820', 'Accumulated Depreciation - Equipment', '设备累计折旧'],
                ['1830', 'Accumulated Depreciation - Furniture', '家具累计折旧'],
                ['1840', 'Accumulated Depreciation - Vehicles', '车辆累计折旧'],
            ]],

            // ---------------- LIABILITIES ----------------
            ['2100', 'Accounts Payable', '应付账款', [
                ['2110', 'Supplier Payables', '供应商应付款'],
                ['2120', 'Contractor Payables', '承包商应付款'],
                ['2130', 'Priest / Monk Fees Payable', '法师／僧侣费用应付款'],
                ['2140', 'Other Payables', '其他应付款'],
            ]],
            ['2200', 'Accrued Expenses', '应计费用', [
                ['2210', 'Accrued Utilities', '应计水电费'],
                ['2220', 'Accrued Salaries', '应计薪金'],
                ['2230', 'Accrued Professional Fees', '应计专业服务费'],
            ]],
            ['2300', 'Deposits Received', '已收押金', [
                ['2310', 'Rental Deposits Received', '租赁押金'],
                ['2320', 'Event Deposits Received', '活动押金'],
            ]],
            ['2400', 'Deferred Income', '递延收入', [
                ['2410', 'Advance Event Income', '预收活动收入'],
                ['2420', 'Advance Service Income', '预收服务收入'],
            ]],

            // ---------------- EQUITY / FUNDS ----------------
            ['3100', 'Net Assets Without Donor Restrictions', '不受捐赠者限制的净资产', [
                ['3110', 'General Entity Fund', '机构普通基金'],
                ['3120', 'Operating Reserve Fund', '营运储备基金'],
                ['3130', 'Capital Reserve Fund', '资本储备基金'],
                ['3140', 'Board Designated Fund', '管理层指定基金'],
            ]],
            ['3200', 'Net Assets With Donor Restrictions', '受捐赠者限制的净资产', [
                ['3210', 'Building Fund', '建筑基金'],
                ['3220', 'Renovation Fund', '修建基金'],
                ['3230', 'Charity Fund', '慈善基金'],
                ['3240', 'Education Fund', '教育基金'],
                ['3250', 'Religious Activity Fund', '宗教活动基金'],
                ['3260', 'Memorial Fund', '纪念基金'],
                ['3270', 'Other Restricted Fund', '其他指定用途基金'],
            ]],
            ['3900', 'Current Year Surplus / Deficit', '本年度盈余／亏损', []],

            // ---------------- INCOME ----------------
            ['4050', 'Membership Fee Income', '会员费收入', []],
            ['4100', 'Donation Income', '捐款收入', [
                ['4110', 'General Donations', '一般捐款'],
                ['4120', 'Designated Donations', '指定用途捐款'],
                ['4130', 'Building Fund Donations', '建筑基金捐款'],
                ['4140', 'Renovation Fund Donations', '修建捐款'],
                ['4150', 'Charity Fund Donations', '慈善基金捐款'],
                ['4160', 'Education Fund Donations', '教育基金捐款'],
                ['4170', 'Memorial Donations', '纪念捐款'],
                ['4180', 'Corporate Donations', '企业捐款'],
                ['4190', 'Anonymous Donations', '匿名捐款'],
            ]],
            ['4200', 'Offering Income', '供奉收入', [
                ['4210', 'Incense Offering', '香油钱'],
                ['4220', 'Merit Offering', '功德金'],
                ['4230', 'Prayer Offering', '祈福功德金'],
                ['4240', 'General Offering', '一般供奉'],
                ['4250', 'Donation Box Offering', '功德箱收入'],
                ['4260', 'Entity Offering', '机构供奉收入'],
            ]],
            ['4300', 'Lamp Offering Income', '点灯收入', [
                ['4310', 'Peace Lamp Offering', '平安灯收入'],
                ['4320', 'Blessing Lamp Offering', '祈福灯收入'],
                ['4330', 'Longevity Lamp Offering', '添寿灯收入'],
                ['4340', 'Wisdom Lamp Offering', '智慧灯收入'],
                ['4350', 'Annual Lamp Offering', '年度点灯收入'],
            ]],
            ['4400', 'Religious Service Income', '宗教服务收入', [
                ['4410', 'Prayer Service Income', '祈福服务收入'],
                ['4420', 'Memorial Service Income', '追思服务收入'],
                ['4430', 'Memorial Ceremony Income', '超度法会收入'],
                ['4440', 'Blessing Ceremony Income', '祈福法会收入'],
                ['4450', 'Consecration Service Income', '开光服务收入'],
                ['4460', 'Religious Ceremony Income', '宗教仪式收入'],
            ]],
            ['4500', 'Ancestral Tablet Income', '祖先牌位收入', [
                ['4510', 'Ancestral Tablet Installation', '祖先牌位安奉'],
                ['4520', 'Memorial Tablet Income', '往生牌位收入'],
                ['4530', 'Annual Tablet Offering', '年度牌位供奉'],
                ['4540', 'Tablet Maintenance Offering', '牌位维护供奉'],
            ]],
            ['4600', 'Event Income', '活动收入', [
                ['4610', 'Religious Ceremony Income', '法会收入'],
                ['4620', 'Festival Income', '神诞／节庆收入'],
                ['4630', 'Annual Festival Income', '年度庆典收入'],
                ['4640', 'Food Offering Income', '供斋／供品收入'],
                ['4650', 'Event Registration Income', '活动报名收入'],
            ]],
            ['4700', 'Religious Item Sales', '宗教用品销售收入', [
                ['4710', 'Incense Sales', '香销售收入'],
                ['4720', 'Candle Sales', '蜡烛销售收入'],
                ['4730', 'Joss Paper Sales', '金纸销售收入'],
                ['4740', 'Religious Book Sales', '经书销售收入'],
                ['4750', 'Souvenir Sales', '纪念品销售收入'],
            ]],
            ['4800', 'Rental Income', '租金收入', [
                ['4810', 'Hall Rental Income', '礼堂租金收入'],
                ['4820', 'Premises Rental Income', '场地租金收入'],
            ]],
            ['4900', 'Other Income', '其他收入', [
                ['4910', 'Interest Income', '利息收入'],
                ['4920', 'Bank Interest Income', '银行利息收入'],
                ['4930', 'Miscellaneous Income', '杂项收入'],
                ['4940', 'Grant Income', '拨款收入'],
                ['4950', 'Investment Income', '投资收入'],
            ]],

            // ---------------- EXPENSES ----------------
            ['5100', 'Incense and Religious Supplies', '香烛及宗教用品', [
                ['5110', 'Incense Expense', '香费用'],
                ['5120', 'Candle Expense', '蜡烛费用'],
                ['5130', 'Joss Paper Expense', '金纸费用'],
                ['5140', 'Oil Offering Expense', '香油费用'],
                ['5150', 'Religious Items Expense', '宗教用品费用'],
                ['5160', 'Flowers Expense', '鲜花费用'],
                ['5170', 'Fruits and Offerings', '水果及供品'],
                ['5180', 'Religious Books Expense', '经书费用'],
            ]],
            ['5200', 'Religious Ceremony Expenses', '宗教法会费用', [
                ['5210', 'Priest / Monk Fees', '法师／僧侣费用'],
                ['5220', 'Taoist Priest Fees', '道长费用'],
                ['5230', 'Ceremony Materials', '法会用品'],
                ['5240', 'Ceremony Decoration', '法会布置'],
                ['5250', 'Ceremony Sound System', '法会音响'],
                ['5260', 'Ceremony Lighting', '法会灯光'],
                ['5270', 'Ceremony Catering', '法会膳食'],
                ['5280', 'Ceremony Transportation', '法会交通'],
                ['5290', 'Other Ceremony Expenses', '其他法会费用'],
            ]],
            ['5300', 'Entity Maintenance', '机构维修', [
                ['5310', 'Building Maintenance', '建筑维修'],
                ['5320', 'Electrical Maintenance', '电气维修'],
                ['5330', 'Plumbing Maintenance', '水管维修'],
                ['5340', 'Roof Maintenance', '屋顶维修'],
                ['5350', 'Painting and Decoration', '油漆及装饰'],
                ['5360', 'Religious Facility Maintenance', '宗教设施维修'],
                ['5370', 'Statue Maintenance', '神像维修'],
                ['5380', 'Entity Cleaning', '机构清洁'],
                ['5390', 'General Repairs', '一般维修'],
            ]],
            ['5400', 'Utilities', '公用事业费用', [
                ['5410', 'Electricity', '电费'],
                ['5420', 'Water', '水费'],
                ['5430', 'Telephone', '电话费'],
                ['5440', 'Internet', '网络费'],
                ['5450', 'Waste Disposal', '垃圾处理费'],
                ['5460', 'Security Services', '保安服务费'],
            ]],
            ['5500', 'Staff Costs', '员工费用', [
                ['5510', 'Salaries and Wages', '薪金及工资'],
                ['5520', 'Staff Allowances', '员工津贴'],
                ['5530', 'Staff Overtime', '加班费'],
                ['5540', 'Staff Benefits', '员工福利'],
                ['5550', 'Staff Training', '员工培训'],
                ['5560', 'Staff Meals', '员工膳食'],
                ['5570', 'Staff Medical', '员工医疗福利'],
            ]],
            ['5600', 'Administrative Expenses', '行政费用', [
                ['5610', 'Stationery', '文具'],
                ['5620', 'Printing', '印刷'],
                ['5630', 'Postage and Courier', '邮寄及快递'],
                ['5640', 'Telephone and Communication', '通讯费用'],
                ['5650', 'Software Subscription', '软件订阅'],
                ['5660', 'IT Services', '信息科技服务'],
                ['5670', 'Accounting Fees', '会计费用'],
                ['5680', 'Audit Fees', '审计费用'],
                ['5690', 'Legal and Professional Fees', '法律及专业费用'],
            ]],
            ['5700', 'Banking and Finance Costs', '银行及金融费用', [
                ['5710', 'Bank Charges', '银行手续费'],
                ['5720', 'Payment Gateway Charges', '电子支付手续费'],
                ['5730', 'Merchant Charges', '商户手续费'],
                ['5740', 'Interest Expense', '利息费用'],
            ]],
            ['5800', 'Charity and Welfare', '慈善及福利', [
                ['5810', 'Cash Charity', '现金慈善援助'],
                ['5820', 'Food Distribution', '食品援助'],
                ['5830', 'Medical Assistance', '医疗援助'],
                ['5840', 'Education Assistance', '教育援助'],
                ['5850', 'Emergency Relief', '紧急援助'],
                ['5860', 'Community Assistance', '社区援助'],
                ['5870', 'Donation to Other Charities', '捐助其他慈善机构'],
            ]],
            ['5900', 'Fundraising Expenses', '筹款费用', [
                ['5910', 'Fundraising Event Expenses', '筹款活动费用'],
                ['5920', 'Advertising', '广告费用'],
                ['5930', 'Printing and Promotion', '印刷及宣传'],
                ['5940', 'Website Expenses', '网站费用'],
                ['5950', 'Social Media Promotion', '社交媒体宣传'],
                ['5960', 'Public Relations', '公共关系费用'],
            ]],
            ['6100', 'Depreciation Expense', '折旧费用', [
                ['6110', 'Building Depreciation', '建筑物折旧'],
                ['6120', 'Equipment Depreciation', '设备折旧'],
                ['6130', 'Furniture Depreciation', '家具折旧'],
                ['6140', 'Vehicle Depreciation', '车辆折旧'],
            ]],
            ['6200', 'Insurance Expense', '保险费用', [
                ['6210', 'Building Insurance', '建筑保险'],
                ['6220', 'Public Liability Insurance', '公共责任保险'],
                ['6230', 'Vehicle Insurance', '车辆保险'],
            ]],
            ['6300', 'Security Expense', '保安费用', []],
            ['6400', 'Vehicle Expenses', '车辆费用', [
                ['6410', 'Fuel', '燃油'],
                ['6420', 'Vehicle Maintenance', '车辆维修'],
                ['6430', 'Road Tax and Registration', '路税及注册费用'],
            ]],
            ['6500', 'Miscellaneous Expenses', '杂项费用', []],
        ];
    }

    private function typeFor(string $code): array
    {
        $leading = (int) substr($code, 0, 1);
        return match ($leading) {
            1 => ['ASSET', 'DEBIT'],
            2 => ['LIABILITY', 'CREDIT'],
            3 => ['EQUITY', 'CREDIT'],
            4 => ['INCOME', 'CREDIT'],
            default => ['EXPENSE', 'DEBIT'],
        };
    }

    public function handle(): int
    {
        $group = DB::table('group_labels')->where('group_type', 'CBE');
        $groupOption = $this->option('group');

        if ($groupOption) {
            $group = $group->where('group_label_id', $groupOption)->first();
        } else {
            $all = $group->get();
            if ($all->count() === 0) {
                $this->error('No CBE community found. Nothing imported.');
                return self::FAILURE;
            }
            if ($all->count() > 1) {
                $this->error('More than one CBE community exists — re-run with --group=<group_label_id>. Choices:');
                foreach ($all as $g) {
                    $this->line("  {$g->group_label_id}  {$g->group_name}");
                }
                return self::FAILURE;
            }
            $group = $all->first();
        }

        if (! $group) {
            $this->error('Specified --group not found. Nothing imported.');
            return self::FAILURE;
        }

        $groupLabelId = $group->group_label_id;
        $this->info('Importing into CBE community: '.($group->group_name ?? $groupLabelId));

        $inserted = 0;
        $skipped = 0;

        DB::transaction(function () use ($groupLabelId, &$inserted, &$skipped) {
            $existingCodes = DB::table('cbe_chart_of_accounts')
                ->where('group_label_id', $groupLabelId)
                ->whereNull('cbe_node_id')
                ->pluck('account_code')
                ->all();

            $codeToId = [];

            // Pass 1: parent/summary accounts.
            foreach ($this->accountBlocks() as [$code, $nameEn, $nameZh, $children]) {
                if (in_array($code, $existingCodes, true)) {
                    $codeToId[$code] = DB::table('cbe_chart_of_accounts')
                        ->where('group_label_id', $groupLabelId)->whereNull('cbe_node_id')
                        ->where('account_code', $code)->value('account_id');
                    $skipped++;
                    continue;
                }
                [$type, $normal] = $this->typeFor($code);
                $id = (string) Str::uuid();
                DB::table('cbe_chart_of_accounts')->insert([
                    'account_id' => $id,
                    'group_label_id' => $groupLabelId,
                    'cbe_node_id' => null,
                    'account_code' => $code,
                    'account_name' => $nameEn,
                    'account_name_zh' => $nameZh,
                    'account_type' => $type,
                    'normal_balance' => $normal,
                    'parent_account_id' => null,
                    'is_posting_account' => true,
                    'is_control_account' => count($children) > 0,
                    'is_system' => false,
                    'is_active' => true,
                    'display_order' => (int) $code,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $codeToId[$code] = $id;
                $inserted++;
            }

            // Pass 2: child/detail accounts, linked to their parent.
            foreach ($this->accountBlocks() as [$parentCode, , , $children]) {
                foreach ($children as [$code, $nameEn, $nameZh]) {
                    if (in_array($code, $existingCodes, true)) {
                        $skipped++;
                        continue;
                    }
                    [$type, $normal] = $this->typeFor($code);
                    DB::table('cbe_chart_of_accounts')->insert([
                        'account_id' => (string) Str::uuid(),
                        'group_label_id' => $groupLabelId,
                        'cbe_node_id' => null,
                        'account_code' => $code,
                        'account_name' => $nameEn,
                        'account_name_zh' => $nameZh,
                        'account_type' => $type,
                        'normal_balance' => $normal,
                        'parent_account_id' => $codeToId[$parentCode] ?? null,
                        'is_posting_account' => true,
                        'is_control_account' => false,
                        'is_system' => false,
                        'is_active' => true,
                        'display_order' => (int) $code,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $inserted++;
                }
            }
        });

        $this->info("Done. Inserted: {$inserted}. Skipped (already existed): {$skipped}.");
        return self::SUCCESS;
    }
}
