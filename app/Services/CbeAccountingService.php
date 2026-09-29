<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Aug 2026 — native double-entry accounting engine for CBE,
// built instead of paying for Akaunting's Double-Entry add-on ($72/yr,
// not part of its free plan) or hosting a separate ERPNext/Odoo server
// (neither runs on Chris's XAMPP PHP/MySQL stack) — per Chris's own
// decision. Everything here is plain Laravel/MySQL, same stack as the
// rest of GeneralLink, no external dependency, no recurring fee.
//
// DESIGN NOTE — what's in the formal journal and what isn't:
// - cbe_transactions (existing, cash-basis day-to-day entries a
//   treasurer types in, INCLUDING the summary rows Event::close()
//   already posts) mirrors 1:1 into a journal entry the moment a row
//   is inserted — see postTransaction(). This is the ONLY place event
//   income/expense enters the books, exactly as it did before this
//   engine existed, so nothing that already worked changes.
// - cbe_purchase_bills / cbe_bill_payments (new Accounts Payable) post
//   on an accrual basis — a bill is a liability the moment it's
//   entered, before it's paid. This is genuinely new ground (there was
//   no "money we owe but haven't paid yet" concept before), so there's
//   no double-counting risk against the cash-basis side.
// - cbe_contributions (donor pledges/sponsorships) are deliberately
//   NOT posted to this journal at all. They already flow into the cash
//   side automatically when an Event closes (via cbe_transactions).
//   Posting them again here at pledge time would double-count that
//   income. "Accounts Receivable" on the Finance dashboard is instead
//   a live computed figure (pledged - received - credit notes) read
//   straight from cbe_contributions — labelled as event donation/
//   sponsorship receivables, not folded into the formal Balance Sheet.
//
// Net effect: the Trial Balance / General Ledger / Balance Sheet /
// Profit & Loss built from this journal are cash-basis for income and
// accrual-basis for the new Accounts Payable side — a common, honest
// mix for a small nonprofit's books, and it never touches or risks
// breaking the existing Annual Income & Expenditure Excel report,
// which still reads cbe_transactions directly exactly as before.
class CbeAccountingService
{
    // Standard core accounts every CBE community gets on first setup.
    // Everything after these (income/expense accounts) is generated
    // 1-for-1 from that community's existing categories — never
    // hardcoded business data, per Chris's standing rule.
    public const CASH_CODE = '101014';
    public const AP_CODE = '201005';
    public const FUND_BALANCE_CODE = '301005';
    public const UNCATEGORISED_EXPENSE_CODE = '515002';

    // NEW 30 Aug 2026 (Task #317-320) — core accounts for Accounts
    // Receivable and Fixed Assets, added to the SAME chart every
    // community already has, so AR/Fixed Asset postings land in the
    // same Trial Balance/Balance Sheet/P&L/GL as everything else.
    public const AR_CODE = '102009';
    public const FIXED_ASSET_CODE = '105017';
    public const ACCUM_DEPRECIATION_CODE = '106008';
    public const UNCATEGORISED_INCOME_CODE = '411003';

    // NEW 2 Sep 2026 (Task #329) — Depreciation Expense (P&L) and a
    // Gain/Loss on Disposal account (also P&L — a gain posts as a
    // credit here, a loss as a debit, same account either way).
    public const DEPRECIATION_EXPENSE_CODE = '509008';
    public const DISPOSAL_GAIN_LOSS_CODE = '509007';

    // NEW 3 Sep 2026 (Task #366) — Pledges Receivable: a dedicated
    // control account for Donation Pledges, kept separate from the AR
    // Trade Debtors account (1200) since a donor's pledge and a
    // customer's invoice are different kinds of receivable and Chris's
    // spec calls for them to reconcile independently.
    public const PLEDGE_RECEIVABLE_CODE = '102001';

    // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
    // spec section 13 (GL Account Mapping): Bank Charges and Bank
    // Interest each need their own dedicated P&L account so a Bank
    // Adjustment can post without falling back to Uncategorised
    // Income/Expense.
    public const BANK_CHARGES_EXPENSE_CODE = '507001';
    public const BANK_INTEREST_INCOME_CODE = '410001';

    // Idempotent — safe to run repeatedly (e.g. after adding a new
    // category later). Creates the 3 core accounts for this
    // group_label_id if missing, and one INCOME/EXPENSE account per
    // active category that doesn't already have chart_account_id set.
    public static function ensureChartOfAccounts(?string $groupLabelId): void
    {
        self::getOrCreateAccount($groupLabelId, self::CASH_CODE, 'Cash / Bank (General)', '现金/银行（总）', 'ASSET', true);
        self::getOrCreateAccount($groupLabelId, self::AP_CODE, 'Accounts Payable (Control)', '应付账款（统制账户）', 'LIABILITY', true);
        self::getOrCreateAccount($groupLabelId, self::FUND_BALANCE_CODE, 'Fund Balance (Control)', '基金结余（统制账户）', 'EQUITY', true);
        self::getOrCreateAccount($groupLabelId, self::UNCATEGORISED_EXPENSE_CODE, 'Uncategorised Expense', '未分类支出', 'EXPENSE', true);
        // NEW 30 Aug 2026 (Task #317-320) — AR + Fixed Assets core accounts.
        self::getOrCreateAccount($groupLabelId, self::AR_CODE, 'Accounts Receivable (Control)', '应收账款（统制账户）', 'ASSET', true);
        self::getOrCreateAccount($groupLabelId, self::FIXED_ASSET_CODE, 'Fixed Assets (Control)', '固定资产（统制账户）', 'ASSET', true);
        self::getOrCreateAccount($groupLabelId, self::ACCUM_DEPRECIATION_CODE, 'Accumulated Depreciation (Control)', '累计折旧（统制账户）', 'ASSET', true);
        self::getOrCreateAccount($groupLabelId, self::UNCATEGORISED_INCOME_CODE, 'Uncategorised Income', '未分类收入', 'INCOME', true);
        // NEW 2 Sep 2026 (Task #329) — Depreciation posting + disposal.
        self::getOrCreateAccount($groupLabelId, self::DEPRECIATION_EXPENSE_CODE, 'Depreciation Expense (General)', '折旧费用（总）', 'EXPENSE', true);
        self::getOrCreateAccount($groupLabelId, self::DISPOSAL_GAIN_LOSS_CODE, 'Gain / Loss on Disposal of Assets', '资产处置损益', 'EXPENSE', true);
        // NEW 3 Sep 2026 (Task #366) — Donation Pledge control account.
        self::getOrCreateAccount($groupLabelId, self::PLEDGE_RECEIVABLE_CODE, 'Pledges Receivable', '认捐应收款', 'ASSET', true);
        // NEW 4 Sep 2026 (Task #396) — Bank Charges Expense / Bank
        // Interest Income, for Bank Adjustment postings.
        self::getOrCreateAccount($groupLabelId, self::BANK_CHARGES_EXPENSE_CODE, 'Bank Charges', '银行手续费', 'EXPENSE', true);
        self::getOrCreateAccount($groupLabelId, self::BANK_INTEREST_INCOME_CODE, 'Bank Interest Income', '银行利息收入', 'INCOME', true);

        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })
            ->whereNull('chart_account_id')
            ->get();

        $incomeSeq = 411003 + (int) DB::table('cbe_chart_of_accounts')
            ->where('group_label_id', $groupLabelId)->where('account_type', 'INCOME')->count();
        $expenseSeq = 515002 + (int) DB::table('cbe_chart_of_accounts')
            ->where('group_label_id', $groupLabelId)->where('account_type', 'EXPENSE')->count();

        foreach ($categories as $cat) {
            if ($cat->type === 'INCOME') {
                $incomeSeq++;
                $accountId = self::getOrCreateAccount($groupLabelId, (string) $incomeSeq, $cat->category_name, $cat->category_name_zh, 'INCOME', false);
            } else {
                $expenseSeq++;
                $accountId = self::getOrCreateAccount($groupLabelId, (string) $expenseSeq, $cat->category_name, $cat->category_name_zh, 'EXPENSE', false);
            }

            DB::table('cbe_transaction_categories')->where('category_id', $cat->category_id)
                ->update(['chart_account_id' => $accountId]);
        }
    }

    // NEW 4 Sep 2026 (Task #390) — $nodeId added (still optional, default
    // null) so a per-node resource's auto-created GL account (a specific
    // bank account, a specific petty cash fund) can be scoped as that
    // one node's own local account instead of landing in the shared
    // master list every other temple/branch would then also see. System
    // default accounts (Cash, AP, Fund Balance, etc.) keep calling this
    // without $nodeId, so they stay shared exactly as before.
    private static function getOrCreateAccount(?string $groupLabelId, string $code, string $name, ?string $nameZh, string $type, bool $isSystem, ?string $nodeId = null): string
    {
        $existing = DB::table('cbe_chart_of_accounts')
            ->where('group_label_id', $groupLabelId)->where('account_code', $code)
            ->when($nodeId, fn ($q) => $q->where('cbe_node_id', $nodeId), fn ($q) => $q->whereNull('cbe_node_id'))
            ->value('account_id');
        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();
        DB::table('cbe_chart_of_accounts')->insert([
            'account_id'       => $id,
            'group_label_id'   => $groupLabelId,
            'cbe_node_id'      => $nodeId,
            'account_code'     => $code,
            'account_name'     => $name,
            'account_name_zh'  => $nameZh,
            'account_type'     => $type,
            'is_system'        => $isSystem,
            'is_active'        => true,
            'display_order'    => (int) $code,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return $id;
    }

    private static function cashAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::CASH_CODE, 'Cash / Bank (General)', '现金/银行（总）', 'ASSET', true);
    }

    // NEW 1 Sep 2026 (Task #333) — Bank Accounts Master. cbe_bank_accounts
    // existed already (27 Aug 2026) as a reference record for filing bank
    // statements, but was never linked to the GL — every node only ever
    // had ONE real ledger cash account. These methods make each bank
    // account row its own GL asset account, while keeping every
    // already-posted transaction (bank_account_id = null) valid by
    // falling back to that node's original single account.

    // Idempotent. Returns the bank_account_id every legacy (pre-upgrade)
    // posting should resolve to. Adopts the oldest existing
    // cbe_bank_accounts row for this node if one exists (from the Bank
    // Statement upload flow) instead of creating a duplicate.
    public static function ensureDefaultBankAccount(string $nodeId, ?string $groupLabelId): string
    {
        $linked = DB::table('cbe_bank_accounts')
            ->where('cbe_node_id', $nodeId)->whereNotNull('gl_account_id')
            ->orderBy('created_at')->value('bank_account_id');
        if ($linked) {
            return $linked;
        }

        $cashAccountId = self::cashAccountId($groupLabelId);
        $row = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->orderBy('created_at')->first();

        if ($row) {
            DB::table('cbe_bank_accounts')->where('bank_account_id', $row->bank_account_id)->update([
                'account_code' => $row->account_code ?: self::CASH_CODE,
                'account_type' => $row->account_type ?: 'BANK_CURRENT',
                'gl_account_id' => $cashAccountId,
                'updated_at' => now(),
            ]);
            return $row->bank_account_id;
        }

        $id = (string) Str::uuid();
        DB::table('cbe_bank_accounts')->insert([
            'bank_account_id' => $id,
            'cbe_node_id' => $nodeId,
            'account_code' => self::CASH_CODE,
            'bank_name' => 'Cash / Bank',
            'account_name' => 'Cash / Bank',
            'account_number' => '-',
            'account_type' => 'CASH',
            'gl_account_id' => $cashAccountId,
            'opening_balance' => 0,
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    // Resolves a bank_account_id (nullable, from a transaction/payment
    // row) to its GL account. Null (or an account with no GL link yet)
    // falls back to the node's default account — this is what keeps
    // every transaction posted before this upgrade valid.
    public static function bankAccountGlAccountId(?string $bankAccountId, string $nodeId, ?string $groupLabelId): string
    {
        if ($bankAccountId) {
            $glId = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->value('gl_account_id');
            if ($glId) {
                return $glId;
            }
        }
        self::ensureDefaultBankAccount($nodeId, $groupLabelId);
        return self::cashAccountId($groupLabelId);
    }

    // Next free GL sub-account code in the bank-account block. Narrowed
    // 2 Sep 2026 (Task #337) from '< 1200' to '< 1150' — codes 1150-1199
    // are now reserved for Petty Cash funds (see nextPettyCashCode()) so
    // the two sub-account families never collide, and 1200 is still
    // reserved for Accounts Receivable (see AR_CODE).
    // NEW 4 Sep 2026 (Task #390) — $nodeId scopes the max() scan to
    // accounts this node can actually see (shared master + its own local
    // accounts), since a new bank-account code must not collide with
    // anything this node would be offered in its own dropdown, whether
    // that account is shared or another of this node's own local ones.
    public static function nextBankAccountCode(?string $groupLabelId, ?string $nodeId = null): string
    {
        $max = DB::table('cbe_chart_of_accounts')
            ->where('group_label_id', $groupLabelId)
            ->when($nodeId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('cbe_node_id')->orWhere('cbe_node_id', $nodeId)), fn ($q) => $q->whereNull('cbe_node_id'))
            ->where('account_code', '>=', (string) self::CASH_CODE)
            ->where('account_code', '<', '1150')
            ->max('account_code');
        return $max ? (string) (((int) $max) + 10) : self::CASH_CODE;
    }

    // Called by the controller when a new bank account is added — creates
    // its own Chart of Accounts entry and returns that account's id, to
    // be stored on the cbe_bank_accounts row. Scoped to $nodeId — one
    // temple's own named bank account (e.g. "Maybank Current #12345") is
    // always that temple's own local account, never shared to the rest
    // of the federation, regardless of whether the creating node is HQ.
    public static function createBankAccountGlLink(?string $groupLabelId, string $accountName, string $nodeId): string
    {
        $code = self::nextBankAccountCode($groupLabelId, $nodeId);
        return self::getOrCreateAccount($groupLabelId, $code, $accountName, null, 'ASSET', false, $nodeId);
    }

    public static function bankAccountBalanceAsOf(string $bankAccountId, string $asOfDate): float
    {
        $glAccountId = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->value('gl_account_id');
        if (! $glAccountId) {
            return 0.0;
        }
        $row = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('l.account_id', $glAccountId)
            ->where('j.entry_date', '<=', $asOfDate)
            ->select(DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->first();
        return (float) ($row->d ?? 0) - (float) ($row->c ?? 0);
    }

    // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
    // spec section 1.3 (Bank Transaction Type master) and section 13
    // (GL Account Mapping). Idempotent, same lazy-seed pattern as
    // ensureAssetCategories(). Only the 2 adjustment-posting types
    // (Bank Charge, Bank Interest) get a default GL account seeded
    // automatically — "Other Bank Adjustment" is deliberately left
    // unmapped so the treasurer picks the correct account by hand each
    // time, since "other" by definition has no single right answer.
    public static function ensureBankTransactionTypes(?string $groupLabelId): void
    {
        $exists = DB::table('cbe_bank_transaction_types')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })->exists();

        if (! $exists) {
            $defaults = [
                'Deposit', 'Withdrawal', 'Bank Transfer', 'Bank Charge', 'Bank Interest',
                'Direct Debit', 'Direct Credit', 'Cheque', 'Online Transfer', 'Other Bank Transaction',
            ];
            foreach ($defaults as $order => $name) {
                DB::table('cbe_bank_transaction_types')->insert([
                    'type_id' => (string) Str::uuid(),
                    'group_label_id' => $groupLabelId,
                    'type_name' => $name,
                    'type_name_zh' => null,
                    'is_system' => true,
                    'is_active' => true,
                    'display_order' => $order,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $chargeType = DB::table('cbe_bank_transaction_types')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })->where('type_name', 'Bank Charge')->whereNull('default_gl_account_id')->first();
        if ($chargeType) {
            DB::table('cbe_bank_transaction_types')->where('type_id', $chargeType->type_id)
                ->update(['default_gl_account_id' => self::bankChargesExpenseAccountId($groupLabelId), 'updated_at' => now()]);
        }

        $interestType = DB::table('cbe_bank_transaction_types')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })->where('type_name', 'Bank Interest')->whereNull('default_gl_account_id')->first();
        if ($interestType) {
            DB::table('cbe_bank_transaction_types')->where('type_id', $interestType->type_id)
                ->update(['default_gl_account_id' => self::bankInterestIncomeAccountId($groupLabelId), 'updated_at' => now()]);
        }
    }

    public static function bankChargesExpenseAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::BANK_CHARGES_EXPENSE_CODE, 'Bank Charges', '银行手续费', 'EXPENSE', true);
    }

    public static function bankInterestIncomeAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::BANK_INTEREST_INCOME_CODE, 'Bank Interest Income', '银行利息收入', 'INCOME', true);
    }

    // NEW 4 Sep 2026 (Task #396) — spec section 1.4 (Reconciliation
    // Rules). Same get-with-defaults / save pattern as
    // approvalSettings()/saveApprovalSettings() above — one row per
    // node, defaults used until a treasurer configures their own.
    public static function bankReconciliationRules(string $cbeNodeId): object
    {
        $row = DB::table('cbe_bank_reconciliation_rules')->where('cbe_node_id', $cbeNodeId)->first();
        if ($row) {
            return $row;
        }
        return (object) [
            'amount_tolerance' => 0.01,
            'date_tolerance_days' => 5,
            'match_on_reference' => true,
            'match_on_cheque_no' => true,
            'match_on_description' => false,
        ];
    }

    public static function saveBankReconciliationRules(string $cbeNodeId, float $amountTolerance, int $dateToleranceDays, bool $matchOnReference, bool $matchOnChequeNo, bool $matchOnDescription, string $updatedBy): void
    {
        $existing = DB::table('cbe_bank_reconciliation_rules')->where('cbe_node_id', $cbeNodeId)->first();
        $data = [
            'amount_tolerance' => $amountTolerance,
            'date_tolerance_days' => $dateToleranceDays,
            'match_on_reference' => $matchOnReference,
            'match_on_cheque_no' => $matchOnChequeNo,
            'match_on_description' => $matchOnDescription,
            'updated_by' => $updatedBy,
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('cbe_bank_reconciliation_rules')->where('rule_id', $existing->rule_id)->update($data);
            return;
        }
        DB::table('cbe_bank_reconciliation_rules')->insert(array_merge($data, [
            'rule_id' => (string) Str::uuid(), 'cbe_node_id' => $cbeNodeId, 'created_at' => now(),
        ]));
    }

    // NEW 2 Sep 2026 (Task #337) — Petty Cash (imprest system). See the
    // migration's header comment for the full design. Code space
    // 1150-1199, mirroring nextBankAccountCode()/createBankAccountGlLink()
    // exactly, one block over so the two families never collide.
    // NEW 4 Sep 2026 (Task #390) — same $nodeId scoping as
    // nextBankAccountCode() above.
    public static function nextPettyCashCode(?string $groupLabelId, ?string $nodeId = null): string
    {
        $max = DB::table('cbe_chart_of_accounts')
            ->where('group_label_id', $groupLabelId)
            ->when($nodeId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('cbe_node_id')->orWhere('cbe_node_id', $nodeId)), fn ($q) => $q->whereNull('cbe_node_id'))
            ->where('account_code', '>=', '1150')
            ->where('account_code', '<', '1200')
            ->max('account_code');
        return $max ? (string) (((int) $max) + 10) : '1150';
    }

    // Scoped to $nodeId — a petty cash fund belongs to one node only,
    // same reasoning as createBankAccountGlLink() above.
    public static function createPettyCashGlLink(?string $groupLabelId, string $fundName, string $nodeId): string
    {
        $code = self::nextPettyCashCode($groupLabelId, $nodeId);
        return self::getOrCreateAccount($groupLabelId, $code, $fundName, null, 'ASSET', false, $nodeId);
    }

    public static function pettyCashBalanceAsOf(string $fundId, string $asOfDate): float
    {
        $glAccountId = DB::table('cbe_petty_cash_funds')->where('fund_id', $fundId)->value('gl_account_id');
        if (! $glAccountId) {
            return 0.0;
        }
        $row = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('l.account_id', $glAccountId)
            ->where('j.entry_date', '<=', $asOfDate)
            ->select(DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->first();
        return (float) ($row->d ?? 0) - (float) ($row->c ?? 0);
    }

    // A voucher is the custodian recording a small spend out of the tin —
    // Dr the expense category's own account, Cr Petty Cash. No
    // maker-checker step (the imprest ceiling itself is the control).
    public static function postPettyCashVoucher(string $voucherId): void
    {
        $v = DB::table('cbe_petty_cash_vouchers as v')
            ->join('cbe_petty_cash_funds as f', 'f.fund_id', '=', 'v.fund_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'v.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'v.category_id')
            ->where('v.voucher_id', $voucherId)
            ->select('v.*', 'f.gl_account_id as petty_cash_gl_id', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $v) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($v->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($v->group_label_id);
        $expenseAccountId = $v->chart_account_id ?: self::uncategorisedExpenseAccountId($v->group_label_id);
        $memo = $v->description ?: ('Petty cash — '.$v->payee);

        $journalId = self::postJournal($v->cbe_node_id, $v->voucher_date, $memo, 'PETTY_CASH_VOUCHER', $voucherId, $v->created_by, [
            [$expenseAccountId, $v->amount, 0, $memo],
            [$v->petty_cash_gl_id, 0, $v->amount, $memo],
        ], $v->doc_ref_no);

        DB::table('cbe_petty_cash_vouchers')->where('voucher_id', $voucherId)->update([
            'journal_id' => $journalId, 'updated_at' => now(),
        ]);
    }

    // A top-up moves cash FROM the bank INTO the custodian's hands — Dr
    // Petty Cash, Cr the source bank account. Used both to establish a
    // brand-new fund (its first top-up) and to replenish an existing one.
    public static function postPettyCashTopup(string $topupId): void
    {
        $t = DB::table('cbe_petty_cash_topups as t')
            ->join('cbe_petty_cash_funds as f', 'f.fund_id', '=', 't.fund_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 't.cbe_node_id')
            ->where('t.topup_id', $topupId)
            ->select('t.*', 'f.gl_account_id as petty_cash_gl_id', 'f.fund_name', 'n.group_label_id')
            ->first();

        if (! $t) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($t->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($t->group_label_id);
        $bankGlId = self::bankAccountGlAccountId($t->bank_account_id, $t->cbe_node_id, $t->group_label_id);
        $memo = $t->notes ?: ('Petty cash top-up — '.$t->fund_name);

        $journalId = self::postJournal($t->cbe_node_id, $t->topup_date, $memo, 'PETTY_CASH_TOPUP', $topupId, $t->created_by, [
            [$t->petty_cash_gl_id, $t->amount, 0, $memo],
            [$bankGlId, 0, $t->amount, $memo],
        ], $t->doc_ref_no);

        DB::table('cbe_petty_cash_topups')->where('topup_id', $topupId)->update([
            'journal_id' => $journalId, 'updated_at' => now(),
        ]);
    }

    // NEW 1 Sep 2026 (Task #333) — Bank Transfer: one journal entry, Dr
    // the receiving account, Cr the sending account.
    public static function postBankTransfer(string $transferId): void
    {
        $transfer = DB::table('cbe_bank_transfers as tr')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'tr.cbe_node_id')
            ->where('tr.transfer_id', $transferId)
            ->select('tr.*', 'n.group_label_id')
            ->first();

        if (! $transfer) {
            return;
        }

        self::ensureChartOfAccounts($transfer->group_label_id);
        $fromGl = self::bankAccountGlAccountId($transfer->from_account_id, $transfer->cbe_node_id, $transfer->group_label_id);
        $toGl = self::bankAccountGlAccountId($transfer->to_account_id, $transfer->cbe_node_id, $transfer->group_label_id);

        self::postJournal($transfer->cbe_node_id, $transfer->transfer_date, $transfer->purpose ?: 'Bank transfer', 'BANK_TRANSFER', $transferId, $transfer->prepared_by, [
            [$toGl, $transfer->amount, 0, $transfer->purpose ?: 'Bank transfer'],
            [$fromGl, 0, $transfer->amount, $transfer->purpose ?: 'Bank transfer'],
        ]);
    }

    private static function apAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::AP_CODE, 'Accounts Payable (Control)', '应付账款（统制账户）', 'LIABILITY', true);
    }

    private static function uncategorisedExpenseAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::UNCATEGORISED_EXPENSE_CODE, 'Uncategorised Expense', '未分类支出', 'EXPENSE', true);
    }

    private static function arAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::AR_CODE, 'Accounts Receivable (Control)', '应收账款（统制账户）', 'ASSET', true);
    }

    private static function fixedAssetAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::FIXED_ASSET_CODE, 'Fixed Assets (Control)', '固定资产（统制账户）', 'ASSET', true);
    }

    private static function accumDepreciationAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::ACCUM_DEPRECIATION_CODE, 'Accumulated Depreciation (Control)', '累计折旧（统制账户）', 'ASSET', true);
    }

    private static function depreciationExpenseAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::DEPRECIATION_EXPENSE_CODE, 'Depreciation Expense (General)', '折旧费用（总）', 'EXPENSE', true);
    }

    private static function disposalGainLossAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::DISPOSAL_GAIN_LOSS_CODE, 'Gain / Loss on Disposal of Assets', '资产处置损益', 'EXPENSE', true);
    }

    private static function uncategorisedIncomeAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::UNCATEGORISED_INCOME_CODE, 'Uncategorised Income', '未分类收入', 'INCOME', true);
    }

    // NEW 30 Aug 2026 (Task #317) — recognises revenue (accrual basis)
    // the moment an invoice is issued, mirroring postBill() exactly.
    public static function postInvoice(string $invoiceId): void
    {
        $invoice = DB::table('cbe_invoices as i')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'i.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'i.category_id')
            ->where('i.invoice_id', $invoiceId)
            ->select('i.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $invoice) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($invoice->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($invoice->group_label_id);
        $arAccountId = self::arAccountId($invoice->group_label_id);
        $memo = $invoice->description ?: ('Invoice '.$invoice->invoice_no);

        // NEW 2 Sep 2026 (Task #335) — multi-line invoices, mirrors
        // postBill() exactly: one Dr to Accounts Receivable for the
        // total, one Cr line per distinct income account. Falls back to
        // the original single-line behaviour for any invoice saved
        // before this feature existed.
        $lines = DB::table('cbe_invoice_lines')->where('invoice_id', $invoiceId)->orderBy('display_order')->get();

        if ($lines->isEmpty()) {
            $incomeAccountId = $invoice->chart_account_id ?: self::uncategorisedIncomeAccountId($invoice->group_label_id);
            $journalId = self::postJournal($invoice->cbe_node_id, $invoice->invoice_date, $memo, 'INVOICE', $invoiceId, $invoice->recorded_by, [
                [$arAccountId, $invoice->amount, 0, $invoice->description],
                [$incomeAccountId, 0, $invoice->amount, $invoice->description],
            ]);
            self::markGlPosted('cbe_invoices', 'invoice_id', $invoiceId, $journalId);
            return;
        }

        $journalLines = [[$arAccountId, $invoice->amount, 0, $memo]];
        foreach ($lines as $line) {
            $accountId = $line->category_id
                ? (DB::table('cbe_transaction_categories')->where('category_id', $line->category_id)->value('chart_account_id') ?: self::uncategorisedIncomeAccountId($invoice->group_label_id))
                : self::uncategorisedIncomeAccountId($invoice->group_label_id);
            $journalLines[] = [$accountId, 0, $line->line_total, $line->description ?: $memo];
        }

        $journalId = self::postJournal($invoice->cbe_node_id, $invoice->invoice_date, $memo, 'INVOICE', $invoiceId, $invoice->recorded_by, $journalLines);
        self::markGlPosted('cbe_invoices', 'invoice_id', $invoiceId, $journalId);
    }

    // NEW 2 Sep 2026 (Task #365) — per Chris's AR/GL Integration spec:
    // every AR document (invoice, payment, debit/credit note) records
    // which journal it posted and shows a GL Posting Status (Not
    // Posted/Posted/Reversed/Error) — this is the one place that writes
    // those two columns back onto the source row after postJournal()
    // returns successfully. Called right after every AR postJournal()
    // call; if postJournal() throws, the row is simply never reached
    // and stays NOT_POSTED (the migration default) for the caller to
    // retry or investigate — there is no queued/async posting in this
    // app, so ERROR is set only by voidJournal()'s failure path, never
    // written here.
    private static function markGlPosted(string $table, string $pkColumn, string $pkValue, string $journalId): void
    {
        DB::table($table)->where($pkColumn, $pkValue)->update([
            'journal_id' => $journalId,
            'gl_posting_status' => 'POSTED',
            'updated_at' => now(),
        ]);
    }

    // NEW 4 Sep 2026 (Task #394 gap-fix) — Purchasing Audit Trail (spec
    // section 26). Append-only event log: call this right after any
    // purchasing action succeeds (create/approve/reject/convert/cancel/
    // receive/match), never to replace a document's own status columns.
    public static function logPurchasingAudit(string $cbeNodeId, string $docType, string $docId, ?string $docRefNo, string $action, string $actorId, ?string $notes = null): void
    {
        DB::table('cbe_purchasing_audit_log')->insert([
            'log_id' => (string) Str::uuid(),
            'cbe_node_id' => $cbeNodeId,
            'doc_type' => $docType,
            'doc_id' => $docId,
            'doc_ref_no' => $docRefNo,
            'action' => $action,
            'actor_id' => $actorId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    // NEW 4 Sep 2026 (Task #395 Phase 4) — Fixed Asset Audit Trail (spec
    // section 24/29). Exact same append-only event-log pattern as
    // logPurchasingAudit() above: call this right after any fixed asset
    // action succeeds (create/submit/approve/reject/capitalise/
    // depreciate/improve/transfer/dispose), never to replace the
    // asset's own status columns. $assetId is nullable so an
    // ACQUISITION submitted for approval — before any asset row exists
    // — can still be logged.
    public static function logFixedAssetAudit(string $cbeNodeId, ?string $assetId, ?string $assetName, string $action, string $actorId, ?string $notes = null): void
    {
        DB::table('cbe_fixed_asset_audit_log')->insert([
            'log_id' => (string) Str::uuid(),
            'cbe_node_id' => $cbeNodeId,
            'asset_id' => $assetId,
            'asset_name' => $assetName,
            'action' => $action,
            'actor_id' => $actorId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
    // Phase 7: Bank Reconciliation Audit Trail. Exact same append-only
    // event-log pattern as logFixedAssetAudit()/logPurchasingAudit().
    public static function logBankReconciliationAudit(string $cbeNodeId, ?string $reconciliationId, ?string $reconciliationNo, string $action, string $actorId, ?string $notes = null): void
    {
        DB::table('cbe_bank_reconciliation_audit_log')->insert([
            'log_id' => (string) Str::uuid(),
            'cbe_node_id' => $cbeNodeId,
            'reconciliation_id' => $reconciliationId,
            'reconciliation_no' => $reconciliationNo,
            'action' => $action,
            'actor_id' => $actorId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    // NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
    // Management Module, Phase 9: Complete Audit Trail + Control
    // Against Fabrication. Exact same append-only pattern as the three
    // helpers above; $documentId/$extractionId are both nullable
    // because a single event only ever concerns one of them (a
    // document-level event like PARSED, or a line-level event like
    // COMMITTED) — never both, never neither.
    public static function logAiAudit(string $cbeNodeId, ?string $documentId, ?string $extractionId, string $eventType, ?string $actorId, ?string $notes = null): void
    {
        DB::table('cbe_ai_audit_log')->insert([
            'log_id' => (string) Str::uuid(),
            'cbe_node_id' => $cbeNodeId,
            'document_id' => $documentId,
            'extraction_id' => $extractionId,
            'event_type' => $eventType,
            'actor_id' => $actorId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    // NEW 9 Sep 2026 (Task #397 follow-up) — AI-Powered Accounting
    // Automation Management Module, Phase 12: Branch/Bank-Account
    // Auto-Detection. Per Chris: the bank statement itself already
    // prints which account it belongs to — the uploader should never
    // have to manually tell the system which bank account a file is
    // for. This matches the PDF extractor's digits-only account number
    // (see PdfStatementExtractionService::detectAccountNumber()) against
    // every registered cbe_bank_accounts row, normalizing both sides the
    // same way (strip spaces/dashes) since admin-entered numbers may
    // keep their original formatting while the extractor never does.
    //
    // Search order: this node's own accounts first (the common case —
    // an officer uploading their own branch's statement). If nothing
    // matches there, widen to the rest of the CBE community (same
    // group_label_id) so an HQ admin uploading on behalf of a branch
    // still gets an accurate answer — but this method never silently
    // reassigns a document to a different node; it only reports what it
    // found so the caller can decide, and a cross-branch match comes
    // back with bank_account_id = null plus an explanatory note, never
    // an auto-assigned account belonging to somebody else's books.
    //
    // Returns ['bank_account_id' => ?string, 'note' => ?string].
    public static function detectBankAccount(string $nodeId, ?string $groupLabelId, ?string $rawAccountNumber): array
    {
        if (! $rawAccountNumber) {
            return ['bank_account_id' => null, 'note' => null];
        }

        $normalized = preg_replace('/[^0-9]/', '', $rawAccountNumber);
        if ($normalized === '') {
            return ['bank_account_id' => null, 'note' => null];
        }

        $inNode = DB::table('cbe_bank_accounts')
            ->where('cbe_node_id', $nodeId)
            ->where('is_active', true)
            ->get(['bank_account_id', 'account_number']);

        foreach ($inNode as $a) {
            if (preg_replace('/[^0-9]/', '', (string) $a->account_number) === $normalized) {
                return ['bank_account_id' => $a->bank_account_id, 'note' => null];
            }
        }

        if ($groupLabelId) {
            $elsewhere = DB::table('cbe_bank_accounts as a')
                ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'a.cbe_node_id')
                ->where('n.group_label_id', $groupLabelId)
                ->where('a.cbe_node_id', '!=', $nodeId)
                ->where('a.is_active', true)
                ->get(['a.account_number', 'n.node_name']);

            foreach ($elsewhere as $a) {
                if (preg_replace('/[^0-9]/', '', (string) $a->account_number) === $normalized) {
                    return ['bank_account_id' => null, 'note' => __('cbe_ai.branch_detection_cross_node', ['node' => $a->node_name])];
                }
            }
        }

        return ['bank_account_id' => null, 'note' => __('cbe_ai.branch_detection_unregistered', ['number' => $rawAccountNumber])];
    }

    // NEW 30 Aug 2026 (Task #317) — settles the receivable and moves
    // cash, mirroring postBillPayment() exactly. Also updates the
    // parent invoice's paid_amount/status.
    public static function postInvoicePayment(string $paymentId): void
    {
        $payment = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'i.cbe_node_id')
            ->where('p.payment_id', $paymentId)
            ->select('p.*', 'i.invoice_id', 'i.amount as invoice_amount', 'i.paid_amount as invoice_paid_amount', 'n.group_label_id')
            ->first();

        if (! $payment) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($payment->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($payment->group_label_id);
        $invoiceNodeId = DB::table('cbe_invoices')->where('invoice_id', $payment->invoice_id)->value('cbe_node_id');
        $cashAccountId = self::bankAccountGlAccountId($payment->bank_account_id ?? null, $invoiceNodeId, $payment->group_label_id);
        $arAccountId = self::arAccountId($payment->group_label_id);

        $journalId = self::postJournal(
            $invoiceNodeId,
            $payment->payment_date,
            'Invoice payment received',
            'INVOICE_PAYMENT',
            $paymentId,
            $payment->recorded_by,
            [
                [$cashAccountId, $payment->amount, 0, 'Invoice payment received'],
                [$arAccountId, 0, $payment->amount, 'Invoice payment received'],
            ]
        );
        self::markGlPosted('cbe_invoice_payments', 'payment_id', $paymentId, $journalId);

        $newPaid = (float) $payment->invoice_paid_amount + (float) $payment->amount;
        $status = $newPaid >= (float) $payment->invoice_amount ? 'PAID' : ($newPaid > 0 ? 'PARTIALLY_PAID' : 'UNPAID');
        DB::table('cbe_invoices')->where('invoice_id', $payment->invoice_id)
            ->update(['paid_amount' => $newPaid, 'status' => $status, 'updated_at' => now()]);
    }

    private static function pledgeReceivableAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::PLEDGE_RECEIVABLE_CODE, 'Pledges Receivable', '认捐应收款', 'ASSET', true);
    }

    // NEW 3 Sep 2026 (Task #366) — recognises the pledge the moment it's
    // recorded (accrual basis), mirroring postInvoice() but against the
    // Pledges Receivable control account instead of AR Trade Debtors.
    // Falls back to Uncategorised Income if the pledge wasn't given an
    // income category.
    public static function postDonationPledge(string $pledgeId): void
    {
        $pledge = DB::table('cbe_donation_pledges as p')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'p.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'p.category_id')
            ->leftJoin('cbe_donors as d', 'd.donor_id', '=', 'p.donor_id')
            ->where('p.pledge_id', $pledgeId)
            ->select('p.*', 'c.chart_account_id', 'n.group_label_id', 'd.donor_name')
            ->first();

        if (! $pledge) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($pledge->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($pledge->group_label_id);
        $pledgeAccountId = self::pledgeReceivableAccountId($pledge->group_label_id);
        $incomeAccountId = $pledge->chart_account_id ?: self::uncategorisedIncomeAccountId($pledge->group_label_id);
        $memo = 'Donation pledge '.$pledge->pledge_no.' — '.($pledge->donor_name ?: 'Donor');

        $journalId = self::postJournal($pledge->cbe_node_id, $pledge->pledge_date, $memo, 'DONATION_PLEDGE', $pledgeId, $pledge->recorded_by, [
            [$pledgeAccountId, $pledge->amount, 0, $memo],
            [$incomeAccountId, 0, $pledge->amount, $memo],
        ]);
        self::markGlPosted('cbe_donation_pledges', 'pledge_id', $pledgeId, $journalId);
    }

    // NEW 3 Sep 2026 (Task #366) — settles the pledge receivable and
    // moves cash, mirroring postInvoicePayment() exactly, then rolls the
    // parent pledge's received_amount/status forward the same way an
    // invoice's paid_amount/status is kept in sync.
    public static function postPledgeReceipt(string $receiptId): void
    {
        $receipt = DB::table('cbe_pledge_receipts as r')
            ->join('cbe_donation_pledges as p', 'p.pledge_id', '=', 'r.pledge_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'p.cbe_node_id')
            ->where('r.receipt_id', $receiptId)
            ->select('r.*', 'p.pledge_id', 'p.amount as pledge_amount', 'p.received_amount as pledge_received_amount', 'p.cbe_node_id', 'n.group_label_id')
            ->first();

        if (! $receipt) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($receipt->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($receipt->group_label_id);
        $cashAccountId = self::bankAccountGlAccountId($receipt->bank_account_id ?? null, $receipt->cbe_node_id, $receipt->group_label_id);
        $pledgeAccountId = self::pledgeReceivableAccountId($receipt->group_label_id);

        $journalId = self::postJournal(
            $receipt->cbe_node_id,
            $receipt->receipt_date,
            'Donation pledge receipt received',
            'PLEDGE_RECEIPT',
            $receiptId,
            $receipt->recorded_by,
            [
                [$cashAccountId, $receipt->amount, 0, 'Donation pledge receipt received'],
                [$pledgeAccountId, 0, $receipt->amount, 'Donation pledge receipt received'],
            ]
        );
        self::markGlPosted('cbe_pledge_receipts', 'receipt_id', $receiptId, $journalId);

        $newReceived = (float) $receipt->pledge_received_amount + (float) $receipt->amount;
        $status = $newReceived >= (float) $receipt->pledge_amount ? 'FULFILLED' : ($newReceived > 0 ? 'PARTIALLY_RECEIVED' : 'PLEDGED');
        DB::table('cbe_donation_pledges')->where('pledge_id', $receipt->pledge_id)
            ->update(['received_amount' => $newReceived, 'status' => $status, 'updated_at' => now()]);
    }

    // NEW 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade, spec
    // sections 2 + 6 (Asset Category / GL Account Mapping). Idempotent,
    // same lazy-seed pattern as ensureChartOfAccounts(): the first time
    // a group needs an asset category list, it gets Chris's own example
    // categories (Land, Buildings, Renovation, Furniture & Fittings,
    // Office/Computer/Audio Visual/Electrical Equipment, Vehicles,
    // Religious Equipment, Kitchen Equipment, Other Fixed Assets)
    // pre-filled, each mapped to its own 3 GL accounts (Fixed Asset /
    // Accumulated Depreciation / Depreciation Expense) generated on the
    // same sequential-numbering scheme already used for income/expense
    // category accounts. Never re-seeds if the group already has
    // categories (an admin may have renamed or removed the defaults) —
    // only fills in GL accounts for whichever categories don't have all
    // 3 mapped yet, so adding a brand new category later still gets
    // wired up automatically.
    public static function ensureAssetCategories(?string $groupLabelId): void
    {
        $exists = DB::table('cbe_asset_categories')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })->exists();

        if (! $exists) {
            $defaults = [
                'Land', 'Buildings', 'Renovation', 'Furniture & Fittings', 'Office Equipment',
                'Computer Equipment', 'Audio Visual Equipment', 'Electrical Equipment', 'Vehicles',
                'Religious Equipment', 'Kitchen Equipment', 'Other Fixed Assets',
            ];
            foreach ($defaults as $order => $name) {
                DB::table('cbe_asset_categories')->insert([
                    'category_id' => (string) Str::uuid(),
                    'group_label_id' => $groupLabelId,
                    'category_name' => $name,
                    'category_name_zh' => null,
                    'default_useful_life_months' => 60,
                    'default_depreciation_method' => 'STRAIGHT_LINE',
                    'is_active' => true,
                    'display_order' => $order,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $categories = DB::table('cbe_asset_categories')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })
            ->where(function ($q) {
                $q->whereNull('fixed_asset_account_id')
                    ->orWhereNull('accum_depreciation_account_id')
                    ->orWhereNull('depreciation_expense_account_id');
            })->get();

        if ($categories->isEmpty()) {
            return;
        }

        $assetSeq = 105017 + (int) DB::table('cbe_asset_categories')->where('group_label_id', $groupLabelId)->whereNotNull('fixed_asset_account_id')->count();
        $accumSeq = 106008 + (int) DB::table('cbe_asset_categories')->where('group_label_id', $groupLabelId)->whereNotNull('accum_depreciation_account_id')->count();
        $expSeq = 509008 + (int) DB::table('cbe_asset_categories')->where('group_label_id', $groupLabelId)->whereNotNull('depreciation_expense_account_id')->count();

        foreach ($categories as $cat) {
            $update = [];
            if (! $cat->fixed_asset_account_id) {
                $assetSeq++;
                $update['fixed_asset_account_id'] = self::getOrCreateAccount($groupLabelId, (string) $assetSeq, $cat->category_name, $cat->category_name_zh, 'ASSET', false);
            }
            if (! $cat->accum_depreciation_account_id) {
                $accumSeq++;
                $update['accum_depreciation_account_id'] = self::getOrCreateAccount($groupLabelId, (string) $accumSeq, 'Accumulated Depreciation — '.$cat->category_name, null, 'ASSET', false);
            }
            if (! $cat->depreciation_expense_account_id) {
                $expSeq++;
                $update['depreciation_expense_account_id'] = self::getOrCreateAccount($groupLabelId, (string) $expSeq, 'Depreciation Expense — '.$cat->category_name, null, 'EXPENSE', false);
            }
            if ($update) {
                $update['updated_at'] = now();
                DB::table('cbe_asset_categories')->where('category_id', $cat->category_id)->update($update);
            }
        }
    }

    // Section 4 (Asset Location) — a simpler admin list with no GL
    // mapping, scoped per cbe_node_id (locations are physical rooms/
    // buildings specific to one temple/branch, unlike categories which
    // make sense shared across a whole organisation's chart of
    // accounts). Lazy-seeded with Chris's example location list the
    // first time a node opens its Fixed Asset screens.
    public static function ensureAssetLocations(string $nodeId): void
    {
        if (DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)->exists()) {
            return;
        }
        $defaults = ['Main Temple', 'Administration Office', 'Hall', 'Kitchen', 'Dormitory', 'Activity Centre', 'Branch', 'Warehouse'];
        foreach ($defaults as $order => $name) {
            DB::table('cbe_asset_locations')->insert([
                'location_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'location_name' => $name,
                'is_active' => true,
                'display_order' => $order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    // NEW 4 Sep 2026 (Task #395) — spec section 9 (Asset Improvement /
    // Additional Cost). Original Cost (cbe_fixed_assets.acquisition_cost)
    // is never overwritten by an improvement — the spec explicitly wants
    // Original Cost, Additional Cost and Revised Cost all visible
    // separately. This is the "Revised Cost" every depreciation and
    // disposal calculation should use going forward.
    public static function revisedAssetCost(string $assetId): float
    {
        $original = (float) DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->value('acquisition_cost');
        $improvements = (float) DB::table('cbe_fixed_asset_improvements')->where('asset_id', $assetId)->sum('additional_cost');
        return round($original + $improvements, 2);
    }

    // Resolves one of the 3 GL accounts for an asset: the category's own
    // mapped account if the asset has a category and that category has
    // one mapped, otherwise the single global fallback account every
    // asset used before this upgrade — so an asset with no category set
    // keeps posting exactly where it always did.
    private static function resolveAssetGlAccount(?string $categoryId, string $column, string $fallbackAccountId): string
    {
        if ($categoryId) {
            $mapped = DB::table('cbe_asset_categories')->where('category_id', $categoryId)->value($column);
            if ($mapped) {
                return $mapped;
            }
        }
        return $fallbackAccountId;
    }

    // NEW 30 Aug 2026 (Task #318) — capitalises a fixed asset: Dr Fixed
    // Assets, Cr Cash for the full acquisition cost, assuming an
    // immediate cash purchase (same assumption the old package build
    // made). Depreciation itself is NOT posted here — see the migration
    // comment on cbe_fixed_assets for why.
    public static function postFixedAssetCapitalization(string $assetId): void
    {
        $asset = DB::table('cbe_fixed_assets as f')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'f.cbe_node_id')
            ->where('f.asset_id', $assetId)
            ->select('f.*', 'n.group_label_id')
            ->first();

        if (! $asset) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($asset->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($asset->group_label_id);
        self::ensureAssetCategories($asset->group_label_id);
        $fixedAssetAccountId = self::resolveAssetGlAccount($asset->category_id, 'fixed_asset_account_id', self::fixedAssetAccountId($asset->group_label_id));
        $cashAccountId = self::cashAccountId($asset->group_label_id);

        $journalId = self::postJournal($asset->cbe_node_id, $asset->acquired_date, 'Fixed asset acquired: '.$asset->asset_name, 'FIXED_ASSET', $assetId, $asset->recorded_by, [
            [$fixedAssetAccountId, $asset->acquisition_cost, 0, $asset->asset_name],
            [$cashAccountId, 0, $asset->acquisition_cost, $asset->asset_name],
        ]);
        // NEW 4 Sep 2026 (Task #394 gap-fix) — this call was missing since
        // the duplicate-posting guard above was added (Task #386): the
        // guard checked $asset->journal_id but nothing ever set it, so a
        // second call to this method would have posted a second
        // acquisition journal for the same asset. Fixed by actually
        // marking the asset GL-posted here, matching every other
        // GL-integrated document in the system.
        self::markGlPosted('cbe_fixed_assets', 'asset_id', $assetId, $journalId);
    }

    // NEW 4 Sep 2026 (Task #394 gap-fix) — Capital Asset Purchase flow
    // (Purchasing -> Invoice -> AP -> Fixed Asset -> GL): a capital-item
    // Supplier Bill already posts Dr [category's mapped GL account] / Cr
    // Accounts Payable via postBill() above — if that category is mapped
    // to a Fixed Asset control account, the balance sheet is already
    // correctly capitalised. This creates the register entry (for
    // depreciation tracking) and links it to that SAME journal, rather
    // than calling postFixedAssetCapitalization() and posting a second,
    // duplicate Dr Fixed Asset / Cr Cash entry.
    public static function createFixedAssetFromBill(string $billId, array $fields, string $recordedBy): string
    {
        $bill = DB::table('cbe_purchase_bills')->where('bill_id', $billId)->first();
        $assetId = (string) Str::uuid();

        DB::table('cbe_fixed_assets')->insert([
            'asset_id' => $assetId,
            'cbe_node_id' => $bill->cbe_node_id,
            'asset_name' => $fields['asset_name'],
            'asset_class' => $fields['asset_class'] ?? null,
            'asset_tag' => $fields['asset_tag'] ?? null,
            'location' => $fields['location'] ?? null,
            'category_id' => $fields['category_id'] ?? null,
            'location_id' => $fields['location_id'] ?? null,
            'cost_centre_id' => $fields['cost_centre_id'] ?? null,
            'fund_id' => $fields['fund_id'] ?? null,
            'supplier_id' => $bill->supplier_id,
            'invoice_no' => $bill->bill_no,
            'funding_source' => $fields['funding_source'] ?? 'Purchasing',
            'acquisition_cost' => $fields['acquisition_cost'],
            'salvage_value' => $fields['salvage_value'] ?? 0,
            'useful_life_months' => $fields['useful_life_months'],
            'depreciation_method' => $fields['depreciation_method'] ?? 'STRAIGHT_LINE',
            'acquired_date' => $bill->bill_date,
            'capitalisation_date' => $bill->bill_date,
            'depreciation_start_date' => $bill->bill_date,
            'bill_id' => $billId,
            'journal_id' => $bill->journal_id,
            'gl_posting_status' => $bill->journal_id ? 'POSTED' : 'NOT_POSTED',
            'recorded_by' => $recordedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $assetId;
    }

    // NEW 4 Sep 2026 (Task #395) — the raw "insert a new register row"
    // step, factored out of the controller so both the normal direct
    // path (storeFixedAsset(), below threshold or Maker-Checker off) and
    // the approval-queue replay (approveFixedAssetRequest(), once a
    // second officer approves) share exactly the same insert instead of
    // two copies of the same field list drifting apart over time.
    public static function createFixedAssetDirect(string $cbeNodeId, array $fields, string $recordedBy): string
    {
        $assetId = (string) Str::uuid();
        DB::table('cbe_fixed_assets')->insert([
            'asset_id' => $assetId,
            'cbe_node_id' => $cbeNodeId,
            'asset_name' => $fields['asset_name'],
            'asset_class' => $fields['asset_class'] ?? null,
            'asset_tag' => $fields['asset_tag'] ?? null,
            'location' => $fields['location'] ?? null,
            'category_id' => $fields['category_id'] ?? null,
            'location_id' => $fields['location_id'] ?? null,
            'cost_centre_id' => $fields['cost_centre_id'] ?? null,
            'fund_id' => $fields['fund_id'] ?? null,
            'supplier_id' => $fields['supplier_id'] ?? null,
            'invoice_no' => $fields['invoice_no'] ?? null,
            'funding_source' => $fields['funding_source'] ?? null,
            'acquisition_cost' => $fields['acquisition_cost'],
            'salvage_value' => $fields['salvage_value'] ?? 0,
            'useful_life_months' => $fields['useful_life_months'],
            'depreciation_method' => $fields['depreciation_method'] ?? 'STRAIGHT_LINE',
            'acquired_date' => $fields['acquired_date'],
            'capitalisation_date' => $fields['capitalisation_date'] ?? $fields['acquired_date'],
            'depreciation_start_date' => $fields['depreciation_start_date'] ?? $fields['acquired_date'],
            'recorded_by' => $recordedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $assetId;
    }

    // NEW 4 Sep 2026 (Task #395) — spec section 9 (Asset Improvement /
    // Additional Cost). Posts its own GL entry (Dr Fixed Asset, Cr Cash)
    // exactly like the original acquisition — Original Cost on
    // cbe_fixed_assets is never touched; see revisedAssetCost().
    public static function postAssetImprovement(string $assetId, string $description, float $additionalCost, string $transactionDate, ?string $sourceReference, string $recordedBy): array
    {
        $asset = DB::table('cbe_fixed_assets as f')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'f.cbe_node_id')
            ->where('f.asset_id', $assetId)
            ->select('f.*', 'n.group_label_id')
            ->first();

        if (! $asset || $asset->status === 'DISPOSED') {
            return ['ok' => false, 'error' => 'asset_not_active'];
        }

        self::ensureChartOfAccounts($asset->group_label_id);
        self::ensureAssetCategories($asset->group_label_id);
        $fixedAssetAccountId = self::resolveAssetGlAccount($asset->category_id, 'fixed_asset_account_id', self::fixedAssetAccountId($asset->group_label_id));
        $cashAccountId = self::cashAccountId($asset->group_label_id);

        $improvementId = (string) Str::uuid();
        $journalId = self::postJournal($asset->cbe_node_id, $transactionDate, 'Asset improvement — '.$asset->asset_name.': '.$description, 'FIXED_ASSET_IMPROVEMENT', $improvementId, $recordedBy, [
            [$fixedAssetAccountId, $additionalCost, 0, $description],
            [$cashAccountId, 0, $additionalCost, $description],
        ]);

        DB::table('cbe_fixed_asset_improvements')->insert([
            'improvement_id' => $improvementId,
            'asset_id' => $assetId,
            'cbe_node_id' => $asset->cbe_node_id,
            'description' => $description,
            'additional_cost' => $additionalCost,
            'transaction_date' => $transactionDate,
            'source_reference' => $sourceReference,
            'journal_id' => $journalId,
            'gl_posting_status' => 'POSTED',
            'recorded_by' => $recordedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['ok' => true, 'improvement_id' => $improvementId];
    }

    // NEW 4 Sep 2026 (Task #395) — spec section 12 (Asset Transfer). No
    // GL entry — moving an asset between locations/departments/funds is
    // not a monetary event. Updates the SAME asset row ("must not
    // duplicate the asset record") and logs a full from/to history row.
    public static function transferFixedAsset(string $assetId, string $transferDate, ?string $toLocationId, ?string $toCostCentreId, ?string $toFundId, ?string $reason, string $authorizedBy): array
    {
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->first();
        if (! $asset || $asset->status === 'DISPOSED') {
            return ['ok' => false, 'error' => 'asset_not_active'];
        }

        DB::table('cbe_fixed_asset_transfers')->insert([
            'transfer_id' => (string) Str::uuid(),
            'asset_id' => $assetId,
            'cbe_node_id' => $asset->cbe_node_id,
            'transfer_date' => $transferDate,
            'from_location_id' => $asset->location_id,
            'to_location_id' => $toLocationId,
            'from_cost_centre_id' => $asset->cost_centre_id,
            'to_cost_centre_id' => $toCostCentreId,
            'from_fund_id' => $asset->fund_id,
            'to_fund_id' => $toFundId,
            'reason' => $reason,
            'authorized_by' => $authorizedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->update([
            'location_id' => $toLocationId,
            'cost_centre_id' => $toCostCentreId,
            'fund_id' => $toFundId,
            'updated_at' => now(),
        ]);

        return ['ok' => true];
    }

    // NEW 4 Sep 2026 (Task #395) — spec section 27 (Approval Control).
    // Generic queue for any Fixed Asset action gated by the SAME
    // Maker-Checker threshold already used for Bill Payments/Bank
    // Transfers/Journal Vouchers (Task #334) — one JSON-payload staging
    // table (mirrors cbe_journal_voucher_drafts exactly) replayed
    // through the real posting method once a different officer
    // approves, and plugged straight into the existing Pending
    // Approvals screen.
    public static function queueFixedAssetRequest(string $cbeNodeId, string $actionType, ?string $assetId, string $description, string $entryDate, float $amount, array $payload, string $preparedBy): string
    {
        $requestId = (string) Str::uuid();
        DB::table('cbe_fixed_asset_requests')->insert([
            'request_id' => $requestId,
            'cbe_node_id' => $cbeNodeId,
            'action_type' => $actionType,
            'asset_id' => $assetId,
            'description' => $description,
            'entry_date' => $entryDate,
            'amount' => $amount,
            'payload' => json_encode($payload),
            'prepared_by' => $preparedBy,
            'status' => 'PENDING',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $requestId;
    }

    public static function approveFixedAssetRequest(string $requestId, string $approvedBy, bool $isAdmin = false): array
    {
        $req = DB::table('cbe_fixed_asset_requests')->where('request_id', $requestId)->first();
        if (! $req || $req->status !== 'PENDING') {
            return ['ok' => false, 'error' => 'not_pending'];
        }
        if (! self::canApprove($req->prepared_by, $approvedBy, $isAdmin)) {
            return ['ok' => false, 'error' => 'self_approval'];
        }

        $payload = json_decode($req->payload, true);
        $resultAssetId = $req->asset_id;

        switch ($req->action_type) {
            case 'ACQUISITION':
                $resultAssetId = self::createFixedAssetDirect($req->cbe_node_id, $payload, $req->prepared_by);
                self::postFixedAssetCapitalization($resultAssetId);
                break;
            case 'IMPROVEMENT':
                self::postAssetImprovement($req->asset_id, $payload['description'], (float) $payload['additional_cost'], $payload['transaction_date'], $payload['source_reference'] ?? null, $req->prepared_by);
                break;
            case 'TRANSFER':
                self::transferFixedAsset($req->asset_id, $payload['transfer_date'], $payload['to_location_id'] ?? null, $payload['to_cost_centre_id'] ?? null, $payload['to_fund_id'] ?? null, $payload['reason'] ?? null, $req->prepared_by);
                break;
            case 'DISPOSAL':
                self::disposeFixedAsset($req->asset_id, $payload['disposed_date'], (float) $payload['disposal_proceeds'], $payload['disposal_reason'] ?? null, $req->prepared_by, $payload['disposal_type'] ?? 'OTHER');
                if (! empty($payload['asset_condition']) || ! empty($payload['attachment_path'])) {
                    DB::table('cbe_fixed_assets')->where('asset_id', $req->asset_id)->update([
                        'asset_condition' => $payload['asset_condition'] ?? null,
                        'disposal_attachment_path' => $payload['attachment_path'] ?? null,
                        'disposal_attachment_original_name' => $payload['attachment_original_name'] ?? null,
                        'updated_at' => now(),
                    ]);
                }
                break;
            default:
                return ['ok' => false, 'error' => 'unknown_action'];
        }

        DB::table('cbe_fixed_asset_requests')->where('request_id', $requestId)->update([
            'status' => 'APPROVED', 'approved_by' => $approvedBy, 'approved_at' => now(),
            'result_asset_id' => $resultAssetId, 'updated_at' => now(),
        ]);

        return ['ok' => true, 'asset_id' => $resultAssetId];
    }

    public static function rejectFixedAssetRequest(string $requestId, string $rejectedBy, string $reason): void
    {
        DB::table('cbe_fixed_asset_requests')->where('request_id', $requestId)->where('status', 'PENDING')->update([
            'status' => 'REJECTED', 'approved_by' => $rejectedBy, 'approved_at' => now(),
            'rejection_reason' => $reason, 'updated_at' => now(),
        ]);
    }

    // NEW 2 Sep 2026 (Task #329) — total actually posted to the ledger
    // so far for this asset, i.e. the real Accumulated Depreciation
    // balance (as opposed to the old on-the-fly time-based estimate
    // used only for display before this feature existed).
    public static function accumulatedDepreciation(string $assetId): float
    {
        return (float) DB::table('cbe_fixed_asset_depreciation_entries')->where('asset_id', $assetId)->sum('amount');
    }

    // Straight-line monthly depreciation, or (NEW 4 Sep 2026, Task #395,
    // spec section 3 — configurable depreciation method) Reducing
    // Balance, both capped so total posted never exceeds (cost - salvage
    // value) — the last posting an asset ever gets may be smaller than a
    // full period's worth to land exactly on the depreciable ceiling.
    // Reducing Balance is implemented as double-declining balance
    // (monthly rate = 2 / useful life in months, applied to the current
    // Net Book Value) — a disclosed simplification: true reducing
    // balance never mathematically reaches exactly the salvage value on
    // its own, so like Straight Line, the final posting is capped to
    // land on it exactly rather than leaving a small residual forever.
    public static function nextDepreciationAmount(object $asset): float
    {
        $cost = self::revisedAssetCost($asset->asset_id);
        $salvage = (float) $asset->salvage_value;
        $depreciable = $cost - $salvage;
        if ($asset->useful_life_months <= 0 || $depreciable <= 0) {
            return 0;
        }
        $alreadyPosted = self::accumulatedDepreciation($asset->asset_id);
        $remaining = round($depreciable - $alreadyPosted, 2);
        if ($remaining <= 0) {
            return 0;
        }

        $method = $asset->depreciation_method ?? 'STRAIGHT_LINE';
        if ($method === 'REDUCING_BALANCE') {
            $netBookValue = $cost - $alreadyPosted;
            $monthlyRate = 2 / $asset->useful_life_months;
            $amount = round($netBookValue * $monthlyRate, 2);
        } else {
            $amount = round($depreciable / $asset->useful_life_months, 2);
        }

        return max(0, min($amount, $remaining));
    }

    // Posts one month's depreciation for one asset: Dr Depreciation
    // Expense, Cr Accumulated Depreciation. Refuses a second posting
    // for the same asset+period_month (unique key backs this up too).
    // Auto-flips the asset to FULLY_DEPRECIATED once nothing is left
    // to depreciate.
    public static function postDepreciation(string $assetId, string $periodMonth, string $postedBy): array
    {
        $asset = DB::table('cbe_fixed_assets as f')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'f.cbe_node_id')
            ->where('f.asset_id', $assetId)
            ->select('f.*', 'n.group_label_id')
            ->first();

        if (! $asset || $asset->status !== 'ACTIVE') {
            return ['ok' => false, 'error' => 'not_active'];
        }

        $already = DB::table('cbe_fixed_asset_depreciation_entries')
            ->where('asset_id', $assetId)->where('period_month', $periodMonth)->exists();
        if ($already) {
            return ['ok' => false, 'error' => 'already_posted'];
        }

        $amount = self::nextDepreciationAmount($asset);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'nothing_to_depreciate'];
        }

        self::ensureChartOfAccounts($asset->group_label_id);
        self::ensureAssetCategories($asset->group_label_id);
        $expenseAccountId = self::resolveAssetGlAccount($asset->category_id, 'depreciation_expense_account_id', self::depreciationExpenseAccountId($asset->group_label_id));
        $accumAccountId = self::resolveAssetGlAccount($asset->category_id, 'accum_depreciation_account_id', self::accumDepreciationAccountId($asset->group_label_id));
        $postDate = now()->toDateString();

        $journalId = self::postJournal($asset->cbe_node_id, $postDate, 'Depreciation — '.$asset->asset_name.' ('.$periodMonth.')', 'DEPRECIATION', $assetId, $postedBy, [
            [$expenseAccountId, $amount, 0, $asset->asset_name],
            [$accumAccountId, 0, $amount, $asset->asset_name],
        ]);

        DB::table('cbe_fixed_asset_depreciation_entries')->insert([
            'entry_id' => (string) Str::uuid(),
            'asset_id' => $assetId,
            'period_month' => $periodMonth,
            'amount' => $amount,
            'journal_id' => $journalId,
            'posted_by' => $postedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $totalPosted = self::accumulatedDepreciation($assetId);
        $depreciable = (float) $asset->acquisition_cost - (float) $asset->salvage_value;
        if ($totalPosted >= $depreciable) {
            DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->update(['status' => 'FULLY_DEPRECIATED', 'updated_at' => now()]);
        }

        return ['ok' => true, 'amount' => $amount];
    }

    // Disposes an asset: removes it and its accumulated depreciation
    // from the books, records any cash received, and plugs the
    // difference to Gain/Loss on Disposal — standard disposal entry.
    // Not available once already DISPOSED.
    public static function disposeFixedAsset(string $assetId, string $disposalDate, float $proceeds, ?string $reason, string $disposedBy, string $disposalType = 'OTHER'): array
    {
        $asset = DB::table('cbe_fixed_assets as f')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'f.cbe_node_id')
            ->where('f.asset_id', $assetId)
            ->select('f.*', 'n.group_label_id')
            ->first();

        if (! $asset || $asset->status === 'DISPOSED') {
            return ['ok' => false, 'error' => 'already_disposed'];
        }

        self::ensureChartOfAccounts($asset->group_label_id);
        self::ensureAssetCategories($asset->group_label_id);
        $fixedAssetAccountId = self::resolveAssetGlAccount($asset->category_id, 'fixed_asset_account_id', self::fixedAssetAccountId($asset->group_label_id));
        $accumAccountId = self::resolveAssetGlAccount($asset->category_id, 'accum_depreciation_account_id', self::accumDepreciationAccountId($asset->group_label_id));
        $cashAccountId = self::cashAccountId($asset->group_label_id);
        $gainLossAccountId = self::disposalGainLossAccountId($asset->group_label_id);

        $accumDepr = self::accumulatedDepreciation($assetId);
        $cost = self::revisedAssetCost($assetId);
        $plug = round($proceeds + $accumDepr - $cost, 2); // >0 gain, <0 loss

        $lines = [];
        if ($proceeds > 0) {
            $lines[] = [$cashAccountId, $proceeds, 0, 'Disposal proceeds — '.$asset->asset_name];
        }
        if ($accumDepr > 0) {
            $lines[] = [$accumAccountId, $accumDepr, 0, 'Disposal — '.$asset->asset_name];
        }
        $lines[] = [$fixedAssetAccountId, 0, $cost, 'Disposal — '.$asset->asset_name];
        if ($plug > 0) {
            $lines[] = [$gainLossAccountId, 0, $plug, 'Gain on disposal — '.$asset->asset_name];
        } elseif ($plug < 0) {
            $lines[] = [$gainLossAccountId, abs($plug), 0, 'Loss on disposal — '.$asset->asset_name];
        }

        $label = $disposalType === 'WRITE_OFF' ? 'Write-off' : 'Disposal';
        self::postJournal($asset->cbe_node_id, $disposalDate, $label.' — '.$asset->asset_name, 'FIXED_ASSET_DISPOSAL', $assetId, $disposedBy, $lines);

        DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->update([
            'status' => 'DISPOSED',
            'disposed_date' => $disposalDate,
            'disposal_proceeds' => $proceeds,
            'disposal_reason' => $reason,
            'disposal_type' => $disposalType,
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'gain_loss' => $plug];
    }

    // NEW 30 Aug 2026 (Task #320) — manual Journal Voucher entry.
    // $lines is [[account_id, debit, credit, memo], ...] — caller
    // (the controller) has already validated the lines balance before
    // calling this, but postJournal() doesn't enforce balance itself
    // (same as the rest of this service — business rules live in the
    // controller/service, not the database).
    public static function postManualJournal(string $cbeNodeId, string $entryDate, string $description, string $createdBy, array $lines, ?string $referenceNo = null, ?string $journalTypeId = null, ?string $attachmentPath = null, ?string $attachmentOriginalName = null): string
    {
        return self::postJournal($cbeNodeId, $entryDate, $description, 'MANUAL', null, $createdBy, $lines, $referenceNo, $journalTypeId, $attachmentPath, $attachmentOriginalName);
    }

    // NEW 2 Sep 2026 (Task #339) — Opening Balances. One-time setup entry
    // per node: brings each GL account's balance in line with the
    // temple's books as of the day they started using GeneralLink, as a
    // single balanced journal so every later report (Trial Balance,
    // Balance Sheet, GL) already reflects the correct starting figures.
    public static function postOpeningBalances(string $cbeNodeId, string $asOfDate, string $createdBy, array $lines): string
    {
        return self::postJournal($cbeNodeId, $asOfDate, __('cbe_accounting.ob_journal_description', ['date' => $asOfDate]), 'OPENING_BALANCE', null, $createdBy, $lines);
    }

    // Blocks re-entry once posted — same "one true starting point" rule
    // real accounting systems enforce. Chris re-opens it by voiding the
    // existing entry first (same generic void/reversal mechanism as
    // every other document type), which naturally clears this check.
    public static function openingBalancesPosted(string $cbeNodeId): bool
    {
        return DB::table('cbe_journal_entries')
            ->where('cbe_node_id', $cbeNodeId)
            ->where('source_type', 'OPENING_BALANCE')
            ->where('status', '!=', 'VOIDED')
            ->exists();
    }

    public static function openingBalancesJournalId(string $cbeNodeId): ?string
    {
        return DB::table('cbe_journal_entries')
            ->where('cbe_node_id', $cbeNodeId)
            ->where('source_type', 'OPENING_BALANCE')
            ->where('status', '!=', 'VOIDED')
            ->value('journal_id');
    }

    // NEW 30 Aug 2026 (Task #319) — this node's own Cash/Bank ledger
    // balance as of a given date, for Bank Reconciliation to compare
    // against the treasurer's bank statement ending balance.
    public static function cashBalanceAsOf(string $nodeId, string $asOfDate): float
    {
        $row = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->where('j.cbe_node_id', $nodeId)
            ->where('a.account_code', self::CASH_CODE)
            ->where('j.entry_date', '<=', $asOfDate)
            ->select(DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->first();

        return (float) ($row->d ?? 0) - (float) ($row->c ?? 0);
    }

    // NEW 2 Sep 2026 (Task #336) — same as cashBalanceAsOf() but scoped
    // to ONE bank account's own GL sub-account, via the same lookup
    // bankAccountGlAccountId() uses when posting. Falls back to the
    // node's default Cash account when $bankAccountId is null (a
    // reconciliation recorded before per-account scoping existed).
    // RENAMED 2 Sep 2026 — this was previously also called
    // bankAccountBalanceAsOf(), a duplicate of the simpler 2-arg version
    // above (a genuine `Cannot redeclare` fatal error that was breaking
    // this entire class on every request). Bank Reconciliation is the
    // only caller, so it gets its own name reflecting that.
    public static function reconciliationBankBalanceAsOf(?string $bankAccountId, string $nodeId, ?string $groupLabelId, string $asOfDate): float
    {
        $accountId = self::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);

        $row = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('j.cbe_node_id', $nodeId)
            ->where('l.account_id', $accountId)
            ->where('j.entry_date', '<=', $asOfDate)
            ->select(DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->first();

        return (float) ($row->d ?? 0) - (float) ($row->c ?? 0);
    }

    // ---------- Bank Reconciliation Matching (NEW 4 Sep 2026, Task
    // #396) — Bank Reconciliation Module upgrade, Phase 3, spec section
    // 3.4. "Bank side" = cbe_bank_transactions (raw statement lines,
    // Phase 2). "System side" = GL journal lines actually posted to the
    // bank's own chart-of-accounts entry — covers every posting module
    // in the app (transfers, AP payments, AR receipts, bill payments,
    // opening balances, fixed asset disposal proceeds, Bank
    // Adjustments), not just the simple treasurer-entry table. A line
    // counts as "already matched" purely by existing in
    // cbe_bank_reconciliation_matches — journal_lines itself carries no
    // reconciliation-specific column, since it's shared by every
    // module. ----------

    public static function unmatchedBankTransactions(string $bankAccountId, string $dateFrom, string $dateTo, int $perPage = 8, string $pageName = 'bankPage')
    {
        return DB::table('cbe_bank_transactions as t')
            ->leftJoin('cbe_bank_transaction_types as tt', 'tt.type_id', '=', 't.transaction_type_id')
            ->where('t.bank_account_id', $bankAccountId)
            ->where('t.status', 'UNRECONCILED')
            ->whereBetween('t.transaction_date', [$dateFrom, $dateTo])
            ->select('t.*', 'tt.type_name')
            ->orderBy('t.transaction_date')
            ->paginate($perPage, ['*'], $pageName);
    }

    public static function unmatchedSystemEntries(?string $bankAccountId, string $nodeId, ?string $groupLabelId, string $dateFrom, string $dateTo, int $perPage = 8, string $pageName = 'sysPage')
    {
        $accountId = self::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);
        $alreadyMatched = DB::table('cbe_bank_reconciliation_matches')->where('side', 'SYSTEM')->pluck('journal_line_id');

        return DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('l.account_id', $accountId)
            ->where('j.cbe_node_id', $nodeId)
            ->whereBetween('j.entry_date', [$dateFrom, $dateTo])
            ->when($alreadyMatched->isNotEmpty(), fn ($q) => $q->whereNotIn('l.line_id', $alreadyMatched))
            ->select('l.line_id', 'l.debit', 'l.credit', 'l.memo', 'j.entry_date', 'j.description', 'j.reference_no', 'j.source_type', 'j.source_id')
            ->orderBy('j.entry_date')
            ->paginate($perPage, ['*'], $pageName);
    }

    // NEW 9 Sep 2026 (Task #397 follow-up) — AI-Powered Accounting
    // Automation Management Module, Phase 15: Strengthened Duplicate
    // Detection. Reuses the exact same "system side" technique
    // unmatchedSystemEntries() above uses for Bank Reconciliation
    // matching: any GL journal line actually posted to this bank
    // account's own chart-of-accounts entry, from ANY source module
    // (manual Bill/Invoice payment, Journal Voucher, Bank Adjustment, a
    // prior AI commit) — not just the narrower cbe_bank_transactions
    // table the original duplicate check used, which manual AP/AR/JV
    // screens never populate at all. Matches on account + exact date +
    // exact amount + correct debit/credit side only — no reference
    // number involved, so this is inherently a "possible" signal, never
    // a certain one (two genuinely separate transactions can share a
    // date and amount) — the caller must never auto-skip on this alone.
    public static function findLikelyDuplicateJournal(?string $bankAccountId, string $nodeId, ?string $groupLabelId, string $entryDate, float $signedAmount): ?object
    {
        $accountId = self::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);
        $amount = round(abs($signedAmount), 2);
        $isCredit = $signedAmount >= 0; // money in = Dr the bank's own GL account

        return DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('l.account_id', $accountId)
            ->where('j.cbe_node_id', $nodeId)
            ->where('j.entry_date', $entryDate)
            ->where($isCredit ? 'l.debit' : 'l.credit', $amount)
            ->select('j.journal_id', 'j.journal_no', 'j.description', 'j.source_type')
            ->first();
    }

    // NEW 9 Sep 2026 (Task #397 follow-up) — AI-Powered Accounting
    // Automation Management Module, Phase 16: Returned/Bounced Cheque
    // Detection + Debit Note Drafting.
    //
    // Per Chris: "if you see a return cheque transaction... what happen
    // in AR AP GL" — the correct accounting answer is the app's EXISTING
    // Debit Note mechanism, never rewriting the original transaction.
    // Money coming BACK IN (a credit reversing an earlier debit) means
    // OUR OWN cheque to a supplier bounced — we still owe them, so this
    // raises an AP Debit Note (postApDebitNote() increases AP). Money
    // going back OUT (a debit reversing an earlier credit) means a
    // CUSTOMER's cheque we deposited bounced — they still owe us, so
    // this raises an AR Debit Note (postArDebitNote() increases AR).
    //
    // "Most likely original transaction" is found by amount (a bounced
    // cheque always bounces for its exact original amount) within a
    // 6-month lookback, optionally narrowed by whatever party name the
    // AI could read off the statement — deliberately NOT narrowed by
    // payment_method, since that field is free text with no reliable
    // "CHEQUE" value across every entry path in this app.
    //
    // Deliberately NEVER posts the drafted note to GL itself (no call to
    // postApDebitNote()/postArDebitNote() here) — misidentifying the
    // wrong original transaction is a real risk with only amount+name to
    // go on, so the note is created and left NOT_POSTED for Chris to
    // review and post himself from the AP/AR Debit Notes screen (a
    // "Post" action added there for exactly this — see
    // CbeAccountingController::postApDebitNoteAction()/postArDebitNoteAction()).
    //
    // Returns ['ok' => bool, 'type' => 'AP'|'AR'|null, 'doc_ref_no' =>
    // ?string, 'bill_id'/'invoice_id' => ?string, 'party_name' =>
    // ?string, 'note' => ?string (only set when ok=false, explaining why
    // nothing could be drafted)].
    public static function draftReturnedChequeNote(string $nodeId, ?string $groupLabelId, string $entryDate, float $signedAmount, ?string $partyNameHint, string $agentId): array
    {
        $amount = round(abs($signedAmount), 2);
        $tolerance = max(1.0, $amount * 0.01);
        $lookbackFrom = \Illuminate\Support\Carbon::parse($entryDate)->subMonths(6)->toDateString();
        $empty = ['ok' => false, 'type' => null, 'doc_ref_no' => null, 'bill_id' => null, 'invoice_id' => null, 'party_name' => null, 'note' => null];

        if ($signedAmount > 0) {
            // Money came back IN — our own cheque to a supplier bounced.
            $payment = DB::table('cbe_bill_payments as p')
                ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
                ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
                ->where('b.cbe_node_id', $nodeId)
                ->whereBetween('p.payment_date', [$lookbackFrom, $entryDate])
                ->whereRaw('ABS(p.amount - ?) <= ?', [$amount, $tolerance])
                ->when($partyNameHint, fn ($q) => $q->where('s.supplier_name', 'like', '%'.$partyNameHint.'%'))
                ->orderByDesc('p.payment_date')
                ->select('p.payment_id', 'p.payment_date', 'p.bill_id', 'b.doc_ref_no as bill_doc_ref_no', 's.supplier_id', 's.supplier_name')
                ->first();

            if (! $payment) {
                return array_merge($empty, ['note' => __('cbe_ai.returned_cheque_no_match')]);
            }

            $debitNoteId = (string) Str::uuid();
            $noteDate = \Illuminate\Support\Carbon::parse($entryDate);
            $docRefNo = self::nextDocumentNumber($nodeId, 'APDN', $noteDate->year, 'APDN', $noteDate->month);
            DB::table('cbe_ap_debit_notes')->insert([
                'debit_note_id' => $debitNoteId,
                'cbe_node_id' => $nodeId,
                'doc_ref_no' => $docRefNo,
                'supplier_id' => $payment->supplier_id,
                'bill_id' => $payment->bill_id,
                'category_id' => null,
                'note_date' => $entryDate,
                'amount' => $amount,
                'reason' => __('cbe_ai.returned_cheque_dn_reason', ['date' => \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y'), 'doc' => $payment->bill_doc_ref_no ?: '—']),
                'recorded_by' => $agentId,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['ok' => true, 'type' => 'AP', 'doc_ref_no' => $docRefNo, 'bill_id' => $payment->bill_id, 'invoice_id' => null, 'party_name' => $payment->supplier_name, 'note' => null];
        }

        // Money went back OUT — a customer's cheque we deposited bounced.
        $payment = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)
            ->whereBetween('p.payment_date', [$lookbackFrom, $entryDate])
            ->whereRaw('ABS(p.amount - ?) <= ?', [$amount, $tolerance])
            ->when($partyNameHint, fn ($q) => $q->where('c.customer_name', 'like', '%'.$partyNameHint.'%'))
            ->orderByDesc('p.payment_date')
            ->select('p.payment_id', 'p.payment_date', 'p.invoice_id', 'i.doc_ref_no as invoice_doc_ref_no', 'c.customer_id', 'c.customer_name')
            ->first();

        if (! $payment) {
            return array_merge($empty, ['note' => __('cbe_ai.returned_cheque_no_match')]);
        }

        $debitNoteId = (string) Str::uuid();
        $noteDate = \Illuminate\Support\Carbon::parse($entryDate);
        $docRefNo = self::nextDocumentNumber($nodeId, 'ARDN', $noteDate->year, 'ARDN', $noteDate->month);
        DB::table('cbe_ar_debit_notes')->insert([
            'debit_note_id' => $debitNoteId,
            'cbe_node_id' => $nodeId,
            'doc_ref_no' => $docRefNo,
            'customer_id' => $payment->customer_id,
            'invoice_id' => $payment->invoice_id,
            'category_id' => null,
            'note_date' => $entryDate,
            'amount' => $amount,
            'reason' => __('cbe_ai.returned_cheque_dn_reason', ['date' => \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y'), 'doc' => $payment->invoice_doc_ref_no ?: '—']),
            'recorded_by' => $agentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['ok' => true, 'type' => 'AR', 'doc_ref_no' => $docRefNo, 'bill_id' => null, 'invoice_id' => $payment->invoice_id, 'party_name' => $payment->customer_name, 'note' => null];
    }

    // Runs the node's configured Reconciliation Rules (Phase 1) to pair
    // unmatched bank transactions 1:1 with unmatched system entries —
    // amount within tolerance, date within tolerance, optionally same
    // reference/cheque no./description. Only ever creates clean 1:1
    // matches; anything left over needs a manual match (which supports
    // 1:many / many:1) via createManualMatch() below.
    public static function autoMatchReconciliation(string $reconciliationId, string $bankAccountId, string $nodeId, ?string $groupLabelId, string $dateFrom, string $dateTo, string $agentId): int
    {
        $rules = self::bankReconciliationRules($nodeId);
        $accountId = self::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);

        $bankTxns = DB::table('cbe_bank_transactions')
            ->where('bank_account_id', $bankAccountId)->where('status', 'UNRECONCILED')
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->orderBy('transaction_date')->get();

        $alreadyMatchedLineIds = DB::table('cbe_bank_reconciliation_matches')->where('side', 'SYSTEM')->pluck('journal_line_id')->all();

        $matched = 0;
        foreach ($bankTxns as $txn) {
            $signedAmount = round((float) $txn->amount, 2);
            $tolerance = round((float) $rules->amount_tolerance, 2);

            $query = DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->where('l.account_id', $accountId)
                ->where('j.cbe_node_id', $nodeId)
                ->whereBetween('j.entry_date', [
                    \Carbon\Carbon::parse($txn->transaction_date)->subDays($rules->date_tolerance_days)->toDateString(),
                    \Carbon\Carbon::parse($txn->transaction_date)->addDays($rules->date_tolerance_days)->toDateString(),
                ])
                ->when(!empty($alreadyMatchedLineIds), fn ($q) => $q->whereNotIn('l.line_id', $alreadyMatchedLineIds))
                ->whereRaw('ABS((l.debit - l.credit) - ?) <= ?', [$signedAmount, $tolerance])
                ->select('l.line_id', 'l.debit', 'l.credit', 'j.entry_date', 'j.reference_no', 'j.description');

            if ($rules->match_on_reference && $txn->reference_no) {
                $query->where('j.reference_no', $txn->reference_no);
            }
            if ($rules->match_on_description && $txn->description) {
                $query->where('j.description', $txn->description);
            }

            $candidate = $query->orderByRaw('ABS(DATEDIFF(j.entry_date, ?))', [$txn->transaction_date])->first();
            if (! $candidate) {
                continue;
            }

            $groupId = (string) Str::uuid();
            DB::table('cbe_bank_reconciliation_matches')->insert([
                ['match_id' => (string) Str::uuid(), 'reconciliation_id' => $reconciliationId, 'match_group_id' => $groupId, 'side' => 'BANK', 'bank_transaction_id' => $txn->transaction_id, 'journal_line_id' => null, 'amount' => $signedAmount, 'created_by' => $agentId, 'created_at' => now()],
                ['match_id' => (string) Str::uuid(), 'reconciliation_id' => $reconciliationId, 'match_group_id' => $groupId, 'side' => 'SYSTEM', 'bank_transaction_id' => null, 'journal_line_id' => $candidate->line_id, 'amount' => round((float) $candidate->debit - (float) $candidate->credit, 2), 'created_by' => $agentId, 'created_at' => now()],
            ]);
            DB::table('cbe_bank_transactions')->where('transaction_id', $txn->transaction_id)->update(['status' => 'MATCHED', 'updated_at' => now()]);
            $alreadyMatchedLineIds[] = $candidate->line_id;
            $matched++;
        }

        return $matched;
    }

    // Manual match: one or more bank transactions against one or more
    // system entries, covering 1:1, 1:many and many:1 (spec 3.4). Sums
    // must agree within the node's configured amount tolerance.
    // Returns null on success, or an error message string if the sums
    // don't agree (caller re-shows the form with both totals).
    public static function createManualMatch(string $reconciliationId, string $nodeId, array $bankTransactionIds, array $journalLineIds, string $agentId): ?string
    {
        if (empty($bankTransactionIds) && empty($journalLineIds)) {
            return 'no_selection';
        }

        $rules = self::bankReconciliationRules($nodeId);
        $bankTxns = DB::table('cbe_bank_transactions')->whereIn('transaction_id', $bankTransactionIds)->where('status', 'UNRECONCILED')->get();
        $journalLines = DB::table('cbe_journal_lines')->whereIn('line_id', $journalLineIds)->get();

        $bankTotal = round((float) $bankTxns->sum('amount'), 2);
        $systemTotal = round((float) $journalLines->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2);

        if (abs($bankTotal - $systemTotal) > round((float) $rules->amount_tolerance, 2)) {
            return 'mismatch';
        }

        $groupId = (string) Str::uuid();
        $rows = [];
        foreach ($bankTxns as $t) {
            $rows[] = ['match_id' => (string) Str::uuid(), 'reconciliation_id' => $reconciliationId, 'match_group_id' => $groupId, 'side' => 'BANK', 'bank_transaction_id' => $t->transaction_id, 'journal_line_id' => null, 'amount' => round((float) $t->amount, 2), 'created_by' => $agentId, 'created_at' => now()];
        }
        foreach ($journalLines as $l) {
            $rows[] = ['match_id' => (string) Str::uuid(), 'reconciliation_id' => $reconciliationId, 'match_group_id' => $groupId, 'side' => 'SYSTEM', 'bank_transaction_id' => null, 'journal_line_id' => $l->line_id, 'amount' => round((float) $l->debit - (float) $l->credit, 2), 'created_by' => $agentId, 'created_at' => now()];
        }
        DB::table('cbe_bank_reconciliation_matches')->insert($rows);
        DB::table('cbe_bank_transactions')->whereIn('transaction_id', $bankTransactionIds)->update(['status' => 'MATCHED', 'updated_at' => now()]);

        return null;
    }

    public static function unmatchGroup(string $matchGroupId): void
    {
        $bankTxnIds = DB::table('cbe_bank_reconciliation_matches')->where('match_group_id', $matchGroupId)->where('side', 'BANK')->pluck('bank_transaction_id');
        DB::table('cbe_bank_reconciliation_matches')->where('match_group_id', $matchGroupId)->delete();
        if ($bankTxnIds->isNotEmpty()) {
            DB::table('cbe_bank_transactions')->whereIn('transaction_id', $bankTxnIds)->update(['status' => 'UNRECONCILED', 'updated_at' => now()]);
        }
    }

    // Bank Adjustment Entry (spec 3.6) — a bank charge/interest/other
    // item the treasurer only discovers from the statement itself. This
    // records BOTH sides of the same real-world event in one step: a
    // cbe_bank_transactions row (source ADJUSTMENT) for the bank side,
    // and a GL journal for the system side — then immediately matches
    // them to each other, since by construction they're the same event.
    public static function createBankAdjustment(string $reconciliationId, string $nodeId, ?string $groupLabelId, string $bankAccountId, string $adjustmentType, string $adjustmentDate, string $description, float $amount, ?string $glAccountId, string $agentId): void
    {
        $bankGlAccountId = self::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);

        if ($adjustmentType === 'CHARGE') {
            $expenseAccountId = $glAccountId ?: self::bankChargesExpenseAccountId($groupLabelId);
            $lines = [[$expenseAccountId, $amount, 0, $description], [$bankGlAccountId, 0, $amount, $description]];
            $signedAmount = -$amount; // money out
        } elseif ($adjustmentType === 'INTEREST') {
            $incomeAccountId = $glAccountId ?: self::bankInterestIncomeAccountId($groupLabelId);
            $lines = [[$bankGlAccountId, $amount, 0, $description], [$incomeAccountId, 0, $amount, $description]];
            $signedAmount = $amount; // money in
        } else { // OTHER — treasurer picks the account; sign follows the amount's own direction
            $otherAccountId = $glAccountId ?: self::uncategorisedExpenseAccountId($groupLabelId);
            if ($amount >= 0) {
                $lines = [[$bankGlAccountId, $amount, 0, $description], [$otherAccountId, 0, $amount, $description]];
            } else {
                $lines = [[$otherAccountId, abs($amount), 0, $description], [$bankGlAccountId, 0, abs($amount), $description]];
            }
            $signedAmount = $amount;
        }

        $bankTransactionId = (string) Str::uuid();
        DB::table('cbe_bank_transactions')->insert([
            'transaction_id' => $bankTransactionId, 'cbe_node_id' => $nodeId, 'bank_account_id' => $bankAccountId,
            'transaction_date' => $adjustmentDate, 'value_date' => null, 'transaction_type_id' => null,
            'description' => $description, 'reference_no' => null, 'cheque_no' => null,
            'amount' => round($signedAmount, 2), 'bank_reference' => null, 'source' => 'ADJUSTMENT',
            'status' => 'MATCHED', 'created_by' => $agentId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $journalId = self::postJournal($nodeId, $adjustmentDate, $description, 'BANK_ADJUSTMENT', $bankTransactionId, $agentId, $lines);
        $bankSideLineId = DB::table('cbe_journal_lines')->where('journal_id', $journalId)->where('account_id', $bankGlAccountId)->value('line_id');

        $groupId = (string) Str::uuid();
        DB::table('cbe_bank_reconciliation_matches')->insert([
            ['match_id' => (string) Str::uuid(), 'reconciliation_id' => $reconciliationId, 'match_group_id' => $groupId, 'side' => 'BANK', 'bank_transaction_id' => $bankTransactionId, 'journal_line_id' => null, 'amount' => round($signedAmount, 2), 'created_by' => $agentId, 'created_at' => now()],
            ['match_id' => (string) Str::uuid(), 'reconciliation_id' => $reconciliationId, 'match_group_id' => $groupId, 'side' => 'SYSTEM', 'bank_transaction_id' => null, 'journal_line_id' => $bankSideLineId, 'amount' => round($signedAmount, 2), 'created_by' => $agentId, 'created_at' => now()],
        ]);
    }

    // NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
    // Management Module, Phase 4: GL Intelligence + Double-Entry Journal
    // Engine. Reuses the exact same postJournal() every other posting
    // path in this app goes through — no parallel pipeline — so an
    // AI-generated entry gets its own Journal Number, respects the
    // fiscal period lock, and shows up in Trial Balance/P&L/Balance
    // Sheet exactly like any manually entered one.
    //
    // Only auto-posts categories that map onto a single existing
    // default GL account with no subledger involved, the same accounts
    // createBankAdjustment() above already uses for the equivalent
    // manual case (Bank Charge -> its expense account; Donation/
    // Membership -> the shared uncategorised income account, since no
    // dedicated donation-income account exists yet; Adjustment/Other ->
    // income or expense depending on which way the money actually
    // moved). SUPPLIER and CUSTOMER are deliberately left unposted
    // here — those need a real AP/AR subledger entry tied to the
    // supplier/customer/draft record Phase 3 creates, which is Phase
    // 5's job; posting them to an "Uncategorised" account now would
    // double-post once Phase 5 does it properly. ASSET is Phase 6's job
    // (asset vs expense detection). TRANSFER and LOAN are never
    // auto-posted at all — correctly journaling either needs the OTHER
    // side of the transfer or the loan's liability account, neither of
    // which can be read off one bank statement line without guessing;
    // Chris records those manually via Bank Reconciliation ->
    // Adjustment or a Journal Voucher, same as before this module
    // existed.
    //
    // Confidence gate: only posts when the classification is
    // trustworthy — the AI's own score, UNLESS Chris typed/picked the
    // category himself, in which case it's treated as fully confident
    // (100) since his own explicit choice is never second-guessed.
    // Below the threshold, the bank transaction still commits exactly
    // as before, just left unjournaled for Chris to enter once he's
    // sure. Returns the new journal_id, or null when this
    // category/confidence combination wasn't auto-posted — a normal,
    // disclosed outcome, never an error.
    // NEW 8 Sep 2026 (Task #397) — AI Module Phase 8: admin-configurable
    // auto-post confidence threshold, single row per node (same pattern
    // as cbe_bank_reconciliation_rules), replacing the hardcoded "60"
    // used throughout Phases 4-6. Lazily defaults to 60 if the node
    // hasn't visited the settings screen yet — the module works
    // unchanged out of the box.
    public static function aiAutoPostThreshold(string $nodeId): int
    {
        return (int) (DB::table('cbe_ai_automation_settings')->where('cbe_node_id', $nodeId)->value('auto_post_confidence_threshold') ?? 60);
    }

    // UPDATED 19 Sep 2026 -- per Chris: "why only category, it suppose
    // to allocate to the right GL code" -- accepts an optional
    // $chartCategoryId (a cbe_transaction_categories row picked on the
    // AI review screen) that, when given, points this line's non-bank
    // leg at that category's own mapped GL account instead of the
    // generic Bank Charges/Uncategorised Income/Expense default below.
    // Same fallback pattern already used for invoice lines
    // (see postInvoiceToGl()): a chosen category with no chart_account_id
    // mapped, or none chosen at all, still falls back safely.
    public static function postAiClassifiedBankTransaction(string $bankTransactionId, string $nodeId, ?string $groupLabelId, string $bankAccountId, string $category, int $confidence, string $entryDate, string $description, float $signedAmount, string $agentId, ?string $chartCategoryId = null): ?string
    {
        $postableCategories = ['BANK_CHARGE', 'DONATION', 'MEMBERSHIP', 'ADJUSTMENT', 'OTHER'];
        if (! in_array($category, $postableCategories, true) || $confidence < self::aiAutoPostThreshold($nodeId)) {
            return null;
        }

        $bankGlAccountId = self::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);
        $amount = round(abs($signedAmount), 2);
        $chosenAccountId = $chartCategoryId
            ? DB::table('cbe_transaction_categories')->where('category_id', $chartCategoryId)->value('chart_account_id')
            : null;

        if ($category === 'BANK_CHARGE') {
            $otherAccountId = $chosenAccountId ?: self::bankChargesExpenseAccountId($groupLabelId);
            $lines = [[$otherAccountId, $amount, 0, $description], [$bankGlAccountId, 0, $amount, $description]];
        } elseif (in_array($category, ['DONATION', 'MEMBERSHIP'], true)) {
            $otherAccountId = $chosenAccountId ?: self::uncategorisedIncomeAccountId($groupLabelId);
            $lines = [[$bankGlAccountId, $amount, 0, $description], [$otherAccountId, 0, $amount, $description]];
        } elseif ($signedAmount >= 0) {
            $otherAccountId = $chosenAccountId ?: self::uncategorisedIncomeAccountId($groupLabelId);
            $lines = [[$bankGlAccountId, $amount, 0, $description], [$otherAccountId, 0, $amount, $description]];
        } else {
            $otherAccountId = $chosenAccountId ?: self::uncategorisedExpenseAccountId($groupLabelId);
            $lines = [[$otherAccountId, $amount, 0, $description], [$bankGlAccountId, 0, $amount, $description]];
        }

        try {
            return self::postJournal($nodeId, $entryDate, $description, 'AI_EXTRACT', $bankTransactionId, $agentId, $lines);
        } catch (\RuntimeException $e) {
            return null; // e.g. period_closed — left unjournaled, same as any other deferred case
        }
    }

    // NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
    // Management Module, Phase 5: Automatic Posting to AP/AR.
    //
    // REDESIGNED 9 Sep 2026 (Task #397 follow-up, Phase 13) — per Chris:
    // "when upload there is not matching require because there is no
    // transaction in AR AP GL FA Bank reconciliation... after submit you
    // have to insert directly as a original source where the transaction
    // started... sometime may not have debtor but you can create an
    // invoice as cash sales." Every CBE must go through accounting
    // audits, and an auditor needs a real Bill/Invoice in the respective
    // module, not just a bank-statement category tag.
    //
    // So this no longer bails out when there's no existing open bill/
    // invoice to match — it still checks first (a real document that
    // ALREADY exists, e.g. raised through the Purchasing module, must
    // never be duplicated), but when nothing existing matches it now
    // CREATES the real original-source Bill/Invoice itself — dated and
    // amounted exactly as the bank line, for the resolved party (or the
    // generic "Cash Purchase"/"Cash Sales" placeholder party when no
    // name could be identified — see PartyMatchingService::
    // resolveOrCreateGenericParty()) — and immediately records it paid
    // in full, since the bank statement already proves the cash moved.
    // Uses the exact same postBill()/postInvoice()/postBillPayment()/
    // postInvoicePayment() primitives the manual Bills/Invoices screens
    // use, so every downstream report, GL drill-down, and audit trail
    // treats an AI-created document exactly like a manually-typed one.
    //
    // Returns ['journal_id' => ?string, 'note' => ?string, 'bill_id' =>
    // ?string, 'invoice_id' => ?string]. journal_id is set only when the
    // payment itself posted; note explains why not otherwise
    // ('pending_approval'/'period_closed').
    public static function allocateAiPaymentToBillOrInvoice(string $category, ?string $partyId, string $nodeId, ?string $groupLabelId, string $bankAccountId, string $paymentDate, float $signedAmount, ?string $referenceNo, string $agentId, ?string $description = null): array
    {
        $empty = ['journal_id' => null, 'note' => null, 'bill_id' => null, 'invoice_id' => null, 'created_new' => false];
        if (! $partyId || ! in_array($category, ['SUPPLIER', 'CUSTOMER'], true)) {
            return $empty;
        }

        $amount = round(abs($signedAmount), 2);
        $tolerance = max(1.0, $amount * 0.01);
        $createdNew = false;

        if ($category === 'SUPPLIER') {
            $bill = DB::table('cbe_purchase_bills')
                ->where('cbe_node_id', $nodeId)
                ->where('supplier_id', $partyId)
                ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
                ->orderBy('bill_date')
                ->get()
                ->first(fn ($b) => abs(((float) $b->amount - (float) $b->paid_amount) - $amount) <= $tolerance);

            if (! $bill) {
                if (self::isPeriodClosed($nodeId, $paymentDate)) {
                    return array_merge($empty, ['note' => 'period_closed']);
                }
                $billId = self::createAiBill($nodeId, $groupLabelId, $partyId, $paymentDate, $description, $amount, $agentId);
                $bill = DB::table('cbe_purchase_bills')->where('bill_id', $billId)->first();
                $createdNew = true;
            }

            $needsApproval = self::requiresApproval($nodeId, $amount);
            $paymentId = (string) Str::uuid();
            DB::table('cbe_bill_payments')->insert([
                'payment_id' => $paymentId,
                'bill_id' => $bill->bill_id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_method' => 'BANK',
                'bank_account_id' => $bankAccountId,
                'reference_no' => $referenceNo,
                'recorded_by' => $agentId,
                'status' => $needsApproval ? 'PENDING' : 'APPROVED',
                'approved_by' => $needsApproval ? null : $agentId,
                'approved_at' => $needsApproval ? null : now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            if ($needsApproval) {
                return array_merge($empty, ['note' => 'pending_approval', 'bill_id' => $bill->bill_id, 'created_new' => $createdNew]);
            }

            try {
                self::postBillPayment($paymentId);
            } catch (\RuntimeException $e) {
                return array_merge($empty, ['note' => 'period_closed', 'bill_id' => $bill->bill_id, 'created_new' => $createdNew]);
            }

            $journalId = DB::table('cbe_bill_payments')->where('payment_id', $paymentId)->value('journal_id');

            return ['journal_id' => $journalId, 'note' => null, 'bill_id' => $bill->bill_id, 'invoice_id' => null, 'created_new' => $createdNew];
        }

        // CUSTOMER
        $invoice = DB::table('cbe_invoices')
            ->where('cbe_node_id', $nodeId)
            ->where('customer_id', $partyId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderBy('invoice_date')
            ->get()
            ->first(fn ($i) => abs(((float) $i->amount - (float) $i->paid_amount) - $amount) <= $tolerance);

        if (! $invoice) {
            if (self::isPeriodClosed($nodeId, $paymentDate)) {
                return array_merge($empty, ['note' => 'period_closed']);
            }
            $invoiceId = self::createAiInvoice($nodeId, $groupLabelId, $partyId, $paymentDate, $description, $amount, $agentId);
            $invoice = DB::table('cbe_invoices')->where('invoice_id', $invoiceId)->first();
            $createdNew = true;
        }

        $paymentId = (string) Str::uuid();
        DB::table('cbe_invoice_payments')->insert([
            'payment_id' => $paymentId,
            'invoice_id' => $invoice->invoice_id,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'payment_method' => 'BANK',
            'bank_account_id' => $bankAccountId,
            'reference_no' => $referenceNo,
            'recorded_by' => $agentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            self::postInvoicePayment($paymentId);
        } catch (\RuntimeException $e) {
            return array_merge($empty, ['note' => 'period_closed', 'invoice_id' => $invoice->invoice_id, 'created_new' => $createdNew]);
        }

        $journalId = DB::table('cbe_invoice_payments')->where('payment_id', $paymentId)->value('journal_id');

        return ['journal_id' => $journalId, 'note' => null, 'bill_id' => null, 'invoice_id' => $invoice->invoice_id, 'created_new' => $createdNew];
    }

    // NEW 9 Sep 2026 (Task #397 follow-up, Phase 13) — creates the real
    // original-source Bill a bank statement's supplier payment is proof
    // of, exactly as the manual "New Bill" screen would (single line,
    // Uncategorised Expense until Chris re-categorises it, gap-free
    // BILL doc number from the SAME shared sequence manual bills use),
    // then posts it (Dr Expense / Cr AP) via the existing postBill().
    private static function createAiBill(string $nodeId, ?string $groupLabelId, string $supplierId, string $billDate, ?string $description, float $amount, string $agentId): string
    {
        $description = $description ?: __('cbe_ai.default_description');
        $docRefNo = self::nextDocumentNumber($nodeId, 'BILL', (int) \Illuminate\Support\Carbon::parse($billDate)->year, 'BILL', (int) \Illuminate\Support\Carbon::parse($billDate)->month);

        $billId = (string) Str::uuid();
        DB::table('cbe_purchase_bills')->insert([
            'bill_id' => $billId,
            'doc_ref_no' => $docRefNo,
            'cbe_node_id' => $nodeId,
            'supplier_id' => $supplierId,
            'bill_no' => null,
            'bill_date' => $billDate,
            'due_date' => $billDate,
            'description' => $description,
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'category_id' => null,
            'recorded_by' => $agentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cbe_bill_lines')->insert([
            'line_id' => (string) Str::uuid(),
            'bill_id' => $billId,
            'category_id' => null,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'line_amount' => $amount,
            'tax_amount' => 0,
            'line_total' => $amount,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        self::postBill($billId);

        return $billId;
    }

    // NEW 9 Sep 2026 (Task #397 follow-up, Phase 13) — mirrors
    // createAiBill() exactly, for a bank statement's customer receipt
    // with no existing open invoice to allocate against (the "cash
    // sales" case Chris described).
    private static function createAiInvoice(string $nodeId, ?string $groupLabelId, string $customerId, string $invoiceDate, ?string $description, float $amount, string $agentId): string
    {
        $description = $description ?: __('cbe_ai.default_description');
        $docRefNo = self::nextDocumentNumber($nodeId, 'INVOICE', (int) \Illuminate\Support\Carbon::parse($invoiceDate)->year, 'INV', (int) \Illuminate\Support\Carbon::parse($invoiceDate)->month);

        $invoiceId = (string) Str::uuid();
        DB::table('cbe_invoices')->insert([
            'invoice_id' => $invoiceId,
            'doc_ref_no' => $docRefNo,
            'cbe_node_id' => $nodeId,
            'customer_id' => $customerId,
            'invoice_no' => null,
            'invoice_date' => $invoiceDate,
            'due_date' => $invoiceDate,
            'description' => $description,
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'category_id' => null,
            'recorded_by' => $agentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cbe_invoice_lines')->insert([
            'line_id' => (string) Str::uuid(),
            'invoice_id' => $invoiceId,
            'category_id' => null,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'line_amount' => $amount,
            'tax_amount' => 0,
            'line_total' => $amount,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        self::postInvoice($invoiceId);

        return $invoiceId;
    }

    // NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
    // Management Module, Phase 6: Fixed Asset Intelligence. Built-in,
    // never-hardcoded-as-master-data keyword map from a bank line's own
    // description text to one of THIS org's actual configured Asset
    // Category names (never a fabricated category) — deliberately
    // conservative fragments (e.g. "computer" only matches a category
    // whose own name contains "computer") so a false match is unlikely;
    // no match simply leaves the asset uncategorised, exactly like a
    // treasurer who adds an asset manually without picking one.
    private const ASSET_CATEGORY_KEYWORDS = [
        'Renovation' => ['renovation', 'refurbish', 'building improvement'],
        'Buildings' => ['building construction', 'hall construction', 'construction of building'],
        'Land' => ['purchase of land', 'land purchase', 'plot of land'],
        'Furniture & Fittings' => ['furniture', 'chair', 'table', 'cabinet', 'cupboard', 'shelving', 'sofa'],
        'Computer Equipment' => ['computer', 'laptop', 'desktop', 'printer', 'photocopier', 'notebook pc', 'pc set', 'monitor'],
        'Audio Visual Equipment' => ['projector', 'camera', 'speaker system', 'sound system', 'microphone', 'television', 'tv set', 'audio visual'],
        'Electrical Equipment' => ['air cond', 'aircond', 'air-cond', 'generator', 'electrical equipment', 'wiring', 'ceiling fan'],
        'Vehicles' => ['motor vehicle', 'purchase of vehicle', 'van purchase', 'lorry', 'motorcycle purchase', 'bus purchase'],
        'Religious Equipment' => ['altar', 'statue', 'shrine', 'religious equipment', 'incense burner', 'deity'],
        'Kitchen Equipment' => ['kitchen equipment', 'stove', 'refrigerator', 'fridge', 'cooking equipment'],
    ];

    private static function matchAssetCategoryId(string $text, ?string $groupLabelId): ?string
    {
        $haystack = mb_strtolower($text);
        $categories = DB::table('cbe_asset_categories')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })
            ->where('is_active', true)
            ->get(['category_id', 'category_name']);

        foreach (self::ASSET_CATEGORY_KEYWORDS as $fragment => $keywords) {
            foreach ($keywords as $keyword) {
                if (! str_contains($haystack, $keyword)) {
                    continue;
                }
                $match = $categories->first(fn ($c) => str_contains(mb_strtolower($c->category_name), mb_strtolower($fragment)));
                if ($match) {
                    return $match->category_id;
                }
            }
        }

        return null;
    }

    // Reuses createFixedAssetDirect() (the same factored-out insert the
    // manual "Add Asset" screen and the approval-queue replay both
    // already share) then links the register row to the journal this
    // method itself posts — Dr the category's mapped Fixed Asset
    // account (or the same generic fallback manual entry already uses
    // when no category is picked), Cr the bank account the money
    // actually left from — mirroring createFixedAssetFromBill()'s
    // "link to the existing journal, don't post a second one" pattern
    // rather than also calling postFixedAssetCapitalization() (which
    // would post its own Dr/Cr Cash journal and double-post).
    //
    // Returns ['journal_id' => ?string, 'asset_id' => ?string]; both
    // null only if the fiscal period is closed for this date.
    public static function postAiFixedAssetAcquisition(string $bankTransactionId, string $nodeId, ?string $groupLabelId, string $bankAccountId, string $assetName, ?string $supplierId, string $acquiredDate, float $signedAmount, string $agentId): array
    {
        $cost = round(abs($signedAmount), 2);

        self::ensureChartOfAccounts($groupLabelId);
        self::ensureAssetCategories($groupLabelId);

        $categoryId = self::matchAssetCategoryId($assetName, $groupLabelId);
        $fixedAssetAccountId = self::resolveAssetGlAccount($categoryId, 'fixed_asset_account_id', self::fixedAssetAccountId($groupLabelId));
        $bankGlAccountId = self::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);

        try {
            $journalId = self::postJournal($nodeId, $acquiredDate, $assetName, 'AI_EXTRACT', $bankTransactionId, $agentId, [
                [$fixedAssetAccountId, $cost, 0, $assetName],
                [$bankGlAccountId, 0, $cost, $assetName],
            ]);
        } catch (\RuntimeException $e) {
            return ['journal_id' => null, 'asset_id' => null];
        }

        $categoryDefaults = $categoryId ? DB::table('cbe_asset_categories')->where('category_id', $categoryId)->first() : null;

        $assetId = self::createFixedAssetDirect($nodeId, [
            'asset_name' => $assetName,
            'category_id' => $categoryId,
            'supplier_id' => $supplierId,
            'funding_source' => 'AI Accounting Automation',
            'acquisition_cost' => $cost,
            'useful_life_months' => $categoryDefaults->default_useful_life_months ?? 60,
            'depreciation_method' => $categoryDefaults->default_depreciation_method ?? 'STRAIGHT_LINE',
            'acquired_date' => $acquiredDate,
        ], $agentId);

        DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->update([
            'journal_id' => $journalId, 'gl_posting_status' => 'POSTED', 'updated_at' => now(),
        ]);

        return ['journal_id' => $journalId, 'asset_id' => $assetId];
    }

    // NEW 1 Sep 2026 (Task #328) — Fiscal Period Lock. Every posting
    // method funnels through postJournal() below, so this one check
    // blocks backdated entries into a closed month regardless of which
    // screen the entry came from (Transaction, Bill, Invoice, Fixed
    // Asset, or Manual Journal Voucher).
    public static function isPeriodClosed(string $cbeNodeId, string $entryDate): bool
    {
        $date = \Illuminate\Support\Carbon::parse($entryDate);

        return DB::table('cbe_fiscal_periods')
            ->where('cbe_node_id', $cbeNodeId)
            ->where('period_year', $date->year)
            ->where('period_month', $date->month)
            ->where('status', 'CLOSED')
            ->exists();
    }

    public static function periodStatus(string $cbeNodeId, int $year, int $month): ?object
    {
        return DB::table('cbe_fiscal_periods')
            ->where('cbe_node_id', $cbeNodeId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
    }

    public static function closePeriod(string $cbeNodeId, int $year, int $month, string $closedBy): void
    {
        $existing = self::periodStatus($cbeNodeId, $year, $month);
        if ($existing) {
            DB::table('cbe_fiscal_periods')->where('period_id', $existing->period_id)->update([
                'status' => 'CLOSED', 'closed_by' => $closedBy, 'closed_at' => now(), 'updated_at' => now(),
            ]);
            return;
        }

        DB::table('cbe_fiscal_periods')->insert([
            'period_id' => (string) Str::uuid(),
            'cbe_node_id' => $cbeNodeId,
            'period_year' => $year,
            'period_month' => $month,
            'status' => 'CLOSED',
            'closed_by' => $closedBy,
            'closed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Admin-only at the controller layer — reopening a closed period is
    // an internal-control exception, not a routine treasurer action.
    public static function reopenPeriod(string $cbeNodeId, int $year, int $month): void
    {
        DB::table('cbe_fiscal_periods')
            ->where('cbe_node_id', $cbeNodeId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->update(['status' => 'OPEN', 'closed_by' => null, 'closed_at' => null, 'updated_at' => now()]);
    }

    // NEW 2 Sep 2026 (Task #335) — Sequential document numbering, gap-free
    // per node per year. Shared by Bills, Invoices, Journal Vouchers, and
    // any future document type (official receipts, purchase requests).
    // lockForUpdate() inside a transaction prevents two treasurers saving
    // at the same instant from getting the same number.
    //
    // UPGRADED 4 Sep 2026 (Task #391) — per Chris: each calendar month now
    // gets its own fixed number band, the way a pre-printed physical
    // voucher/receipt book is numbered — January 01000-01999, February
    // 02000-02999, ... December 12000-12999 — rather than one counter
    // running continuously through the whole year. The counter itself is
    // now keyed by (node, doc_type, YEAR, MONTH), so each month restarts
    // at its own band automatically; it's still gap-free WITHIN that
    // month via the same lockForUpdate() pattern as before. 1,000 numbers
    // per month per doc type per node is generous headroom for a
    // temple's volume; if that's ever exceeded in one month the sequence
    // safely continues past 999 (e.g. 011000) rather than colliding with
    // the next month's band — it just stops looking as tidy, which is
    // disclosed as a known design limit rather than a length Chris asked
    // to plan around. Prospective only: documents already issued keep
    // their old number unchanged.
    public static function nextDocumentNumber(string $cbeNodeId, string $docType, int $year, string $prefix, int $month = 1): string
    {
        return DB::transaction(function () use ($cbeNodeId, $docType, $year, $prefix, $month) {
            $row = DB::table('cbe_document_sequences')
                ->where('cbe_node_id', $cbeNodeId)->where('doc_type', $docType)->where('year', $year)->where('month', $month)
                ->lockForUpdate()->first();

            if ($row) {
                $nextNumber = $row->last_number + 1;
                DB::table('cbe_document_sequences')->where('sequence_id', $row->sequence_id)
                    ->update(['last_number' => $nextNumber, 'updated_at' => now()]);
            } else {
                $nextNumber = 1;
                DB::table('cbe_document_sequences')->insert([
                    'sequence_id' => (string) Str::uuid(), 'cbe_node_id' => $cbeNodeId,
                    'doc_type' => $docType, 'year' => $year, 'month' => $month, 'last_number' => $nextNumber,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return sprintf('%s-%d-%s', $prefix, $year, self::formatMonthBand($month, $nextNumber));
        });
    }

    // seq is 1-indexed (the Nth document issued this month); the printed
    // band position is seq-1, so the first document of the month prints
    // as MM000 and the 1000th prints as MM999.
    private static function formatMonthBand(int $month, int $seq): string
    {
        $band = $seq - 1;
        return $band <= 999 ? sprintf('%02d%03d', $month, $band) : sprintf('%02d%d', $month, $band);
    }

    // NEW 4 Sep 2026 (Task #391) — Document Number Control master file.
    // Read-only lookup for the control screen: current last_number and
    // the printed band range for a given (node, doc_type, year, month),
    // without consuming a number the way nextDocumentNumber() would.
    public static function documentSequenceStatus(string $cbeNodeId, string $docType, int $year, int $month): array
    {
        $row = DB::table('cbe_document_sequences')
            ->where('cbe_node_id', $cbeNodeId)->where('doc_type', $docType)->where('year', $year)->where('month', $month)
            ->first();
        $lastNumber = $row->last_number ?? 0;
        return [
            'last_number' => $lastNumber,
            'next_number' => $lastNumber + 1,
            'band_start' => $month * 1000,
            'band_end' => $month * 1000 + 999,
        ];
    }

    // NEW 4 Sep 2026 (Task #391) — manual reset of the starting number
    // for a period, per Chris's request ("the user can reset based on a
    // periodic basis"). Every reset is logged with who/when/old/new/why
    // rather than silently overwritten — an unlogged reset is exactly
    // the kind of control gap an auditor flags, since it could otherwise
    // be used to quietly re-open a number range. Restricted to Admin in
    // the controller. $newNextNumber is the NEXT number to be issued
    // (1-indexed within the month), matching what the control screen
    // displays as "Next Number."
    public static function resetDocumentSequence(string $cbeNodeId, string $docType, int $year, int $month, int $newNextNumber, string $resetBy, string $reason): void
    {
        DB::transaction(function () use ($cbeNodeId, $docType, $year, $month, $newNextNumber, $resetBy, $reason) {
            $row = DB::table('cbe_document_sequences')
                ->where('cbe_node_id', $cbeNodeId)->where('doc_type', $docType)->where('year', $year)->where('month', $month)
                ->lockForUpdate()->first();
            $oldLastNumber = $row->last_number ?? 0;
            $newLastNumber = max(0, $newNextNumber - 1);

            if ($row) {
                DB::table('cbe_document_sequences')->where('sequence_id', $row->sequence_id)
                    ->update(['last_number' => $newLastNumber, 'updated_at' => now()]);
            } else {
                DB::table('cbe_document_sequences')->insert([
                    'sequence_id' => (string) Str::uuid(), 'cbe_node_id' => $cbeNodeId,
                    'doc_type' => $docType, 'year' => $year, 'month' => $month, 'last_number' => $newLastNumber,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('cbe_document_sequence_resets')->insert([
                'reset_id' => (string) Str::uuid(), 'cbe_node_id' => $cbeNodeId,
                'doc_type' => $docType, 'year' => $year, 'month' => $month,
                'old_last_number' => $oldLastNumber, 'new_last_number' => $newLastNumber,
                'reason' => $reason, 'reset_by' => $resetBy,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    // NEW 2 Sep 2026 (Task #334) — Maker-Checker approval. Applies to the
    // three ways money can leave/move: Bill Payments, Bank Transfers, and
    // Journal Vouchers. Off by default per node so existing single-officer
    // temples aren't disrupted — a Director/Admin switches it on and sets
    // a threshold once a second approver is appointed.
    public static function approvalSettings(string $cbeNodeId): object
    {
        $row = DB::table('cbe_approval_settings')->where('cbe_node_id', $cbeNodeId)->first();
        if ($row) {
            return $row;
        }
        return (object) ['maker_checker_enabled' => false, 'threshold_amount' => 0.0];
    }

    public static function requiresApproval(string $cbeNodeId, float $amount): bool
    {
        $settings = self::approvalSettings($cbeNodeId);
        return (bool) $settings->maker_checker_enabled && $amount >= (float) $settings->threshold_amount;
    }

    public static function saveApprovalSettings(string $cbeNodeId, bool $enabled, float $threshold, string $updatedBy): void
    {
        $existing = DB::table('cbe_approval_settings')->where('cbe_node_id', $cbeNodeId)->first();
        if ($existing) {
            DB::table('cbe_approval_settings')->where('setting_id', $existing->setting_id)->update([
                'maker_checker_enabled' => $enabled, 'threshold_amount' => $threshold,
                'updated_by' => $updatedBy, 'updated_at' => now(),
            ]);
            return;
        }
        DB::table('cbe_approval_settings')->insert([
            'setting_id' => (string) Str::uuid(), 'cbe_node_id' => $cbeNodeId,
            'maker_checker_enabled' => $enabled, 'threshold_amount' => $threshold,
            'updated_by' => $updatedBy, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Every approve*() below refuses to let the preparer approve their
    // own submission — that's the entire point of maker-checker. ADMIN
    // is exempt (same "internal-control exception" pattern as period
    // reopen) so a solo-officer temple isn't permanently stuck once it
    // switches the workflow on before a second officer is provisioned.
    private static function canApprove(string $preparedBy, string $approverId, bool $isAdmin): bool
    {
        return $isAdmin || $preparedBy !== $approverId;
    }

    public static function approveBillPayment(string $paymentId, string $approvedBy, bool $isAdmin = false): array
    {
        $payment = DB::table('cbe_bill_payments')->where('payment_id', $paymentId)->first();
        if (! $payment || $payment->status !== 'PENDING') {
            return ['ok' => false, 'error' => 'not_pending'];
        }
        if (! self::canApprove($payment->recorded_by, $approvedBy, $isAdmin)) {
            return ['ok' => false, 'error' => 'self_approval'];
        }

        DB::table('cbe_bill_payments')->where('payment_id', $paymentId)->update([
            'status' => 'APPROVED', 'approved_by' => $approvedBy, 'approved_at' => now(), 'updated_at' => now(),
        ]);
        self::postBillPayment($paymentId);

        return ['ok' => true];
    }

    public static function rejectBillPayment(string $paymentId, string $rejectedBy, string $reason): void
    {
        DB::table('cbe_bill_payments')->where('payment_id', $paymentId)->where('status', 'PENDING')->update([
            'status' => 'REJECTED', 'approved_by' => $rejectedBy, 'approved_at' => now(),
            'rejection_reason' => $reason, 'updated_at' => now(),
        ]);
    }

    public static function approveBankTransfer(string $transferId, string $approvedBy, bool $isAdmin = false): array
    {
        $transfer = DB::table('cbe_bank_transfers')->where('transfer_id', $transferId)->first();
        if (! $transfer || $transfer->status !== 'PENDING') {
            return ['ok' => false, 'error' => 'not_pending'];
        }
        if (! self::canApprove($transfer->prepared_by, $approvedBy, $isAdmin)) {
            return ['ok' => false, 'error' => 'self_approval'];
        }

        DB::table('cbe_bank_transfers')->where('transfer_id', $transferId)->update([
            'status' => 'APPROVED', 'approved_by' => $approvedBy, 'approved_at' => now(), 'updated_at' => now(),
        ]);
        self::postBankTransfer($transferId);

        return ['ok' => true];
    }

    public static function rejectBankTransfer(string $transferId, string $rejectedBy, string $reason): void
    {
        DB::table('cbe_bank_transfers')->where('transfer_id', $transferId)->where('status', 'PENDING')->update([
            'status' => 'REJECTED', 'approved_by' => $rejectedBy, 'approved_at' => now(),
            'rejection_reason' => $reason, 'updated_at' => now(),
        ]);
    }

    // Bank Reconciliation Approval (NEW 4 Sep 2026, Task #396) — Bank
    // Reconciliation Module upgrade, Phase 7, spec section 20. When the
    // node's Maker-Checker is enabled, completing a reconciliation
    // (completedBy) moves it to PENDING_APPROVAL instead of straight to
    // COMPLETED; a second officer (or an Admin) must approve it here —
    // same canApprove() self-approval block as Bill Payments/Bank
    // Transfers. Unlike those, there's no monetary "amount" to compare
    // against a threshold, so this checks only whether Maker-Checker is
    // switched on at all for the node.
    public static function approveBankReconciliation(string $reconciliationId, string $approvedBy, bool $isAdmin = false): array
    {
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->first();
        if (! $reconciliation || $reconciliation->status !== 'PENDING_APPROVAL') {
            return ['ok' => false, 'error' => 'not_pending'];
        }
        if (! self::canApprove($reconciliation->completed_by, $approvedBy, $isAdmin)) {
            return ['ok' => false, 'error' => 'self_approval'];
        }

        DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->update([
            'status' => 'COMPLETED', 'approved_by' => $approvedBy, 'approved_at' => now(), 'updated_at' => now(),
        ]);

        return ['ok' => true];
    }

    public static function rejectBankReconciliation(string $reconciliationId, string $rejectedBy, string $reason): void
    {
        DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('status', 'PENDING_APPROVAL')->update([
            'status' => 'DRAFT', 'approved_by' => $rejectedBy, 'approved_at' => now(),
            'rejection_reason' => $reason, 'updated_at' => now(),
        ]);
    }

    // $lines is the same [[account_id, debit, credit, memo], ...] shape
    // postManualJournal() expects — stored as JSON until approved, then
    // replayed through the exact same posting call a non-gated JV uses.
    public static function saveJournalVoucherDraft(string $cbeNodeId, string $entryDate, string $description, string $preparedBy, array $lines, float $totalAmount, ?string $journalTypeId = null, ?string $attachmentPath = null, ?string $attachmentOriginalName = null): string
    {
        $draftId = (string) Str::uuid();
        DB::table('cbe_journal_voucher_drafts')->insert([
            'draft_id' => $draftId, 'cbe_node_id' => $cbeNodeId, 'entry_date' => $entryDate,
            'description' => $description, 'attachment_path' => $attachmentPath, 'attachment_original_name' => $attachmentOriginalName,
            'journal_type_id' => $journalTypeId, 'lines' => json_encode($lines), 'total_amount' => $totalAmount,
            'prepared_by' => $preparedBy, 'status' => 'PENDING',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $draftId;
    }

    public static function approveJournalVoucherDraft(string $draftId, string $approvedBy, bool $isAdmin = false): array
    {
        $draft = DB::table('cbe_journal_voucher_drafts')->where('draft_id', $draftId)->first();
        if (! $draft || $draft->status !== 'PENDING') {
            return ['ok' => false, 'error' => 'not_pending'];
        }
        if (! self::canApprove($draft->prepared_by, $approvedBy, $isAdmin)) {
            return ['ok' => false, 'error' => 'self_approval'];
        }
        if (self::isPeriodClosed($draft->cbe_node_id, $draft->entry_date)) {
            return ['ok' => false, 'error' => 'period_closed'];
        }

        $lines = json_decode($draft->lines, true);
        $refNo = self::nextDocumentNumber($draft->cbe_node_id, 'JV', (int) \Illuminate\Support\Carbon::parse($draft->entry_date)->year, 'JV', (int) \Illuminate\Support\Carbon::parse($draft->entry_date)->month);
        $journalId = self::postManualJournal($draft->cbe_node_id, $draft->entry_date, $draft->description, $draft->prepared_by, $lines, $refNo, $draft->journal_type_id ?? null, $draft->attachment_path ?? null, $draft->attachment_original_name ?? null);

        DB::table('cbe_journal_voucher_drafts')->where('draft_id', $draftId)->update([
            'status' => 'APPROVED', 'approved_by' => $approvedBy, 'approved_at' => now(),
            'posted_journal_id' => $journalId, 'updated_at' => now(),
        ]);

        return ['ok' => true];
    }

    public static function rejectJournalVoucherDraft(string $draftId, string $rejectedBy, string $reason): void
    {
        DB::table('cbe_journal_voucher_drafts')->where('draft_id', $draftId)->where('status', 'PENDING')->update([
            'status' => 'REJECTED', 'approved_by' => $rejectedBy, 'approved_at' => now(),
            'rejection_reason' => $reason, 'updated_at' => now(),
        ]);
    }

    // Unified queue for the Pending Approvals screen — merges all 3
    // sources into one common shape and sorts oldest-first (FIFO), same
    // convention as every other worklist in this app.
    public static function pendingApprovals(string $cbeNodeId): \Illuminate\Support\Collection
    {
        $bills = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('agents as a', 'a.agent_id', '=', 'p.recorded_by')
            ->where('b.cbe_node_id', $cbeNodeId)->where('p.status', 'PENDING')
            ->select('p.payment_id as ref_id', DB::raw("'BILL_PAYMENT' as approval_type"), 'p.payment_date as entry_date',
                DB::raw("CONCAT('Bill payment — ', b.bill_no) as description"), 'p.amount', 'a.full_name as prepared_by_name', 'p.created_at')
            ->get();

        $transfers = DB::table('cbe_bank_transfers as t')
            ->join('agents as a', 'a.agent_id', '=', 't.prepared_by')
            ->where('t.cbe_node_id', $cbeNodeId)->where('t.status', 'PENDING')
            ->select('t.transfer_id as ref_id', DB::raw("'BANK_TRANSFER' as approval_type"), 't.transfer_date as entry_date',
                DB::raw("CONCAT('Bank transfer — ', COALESCE(t.purpose, t.reference_no, 'no reference')) as description"), 't.amount', 'a.full_name as prepared_by_name', 't.created_at')
            ->get();

        $journals = DB::table('cbe_journal_voucher_drafts as j')
            ->join('agents as a', 'a.agent_id', '=', 'j.prepared_by')
            ->where('j.cbe_node_id', $cbeNodeId)->where('j.status', 'PENDING')
            ->select('j.draft_id as ref_id', DB::raw("'JOURNAL_VOUCHER' as approval_type"), 'j.entry_date',
                'j.description', 'j.total_amount as amount', 'a.full_name as prepared_by_name', 'j.created_at')
            ->get();

        // NEW 4 Sep 2026 (Task #395) — Fixed Asset requests (Acquisition/
        // Improvement/Transfer/Disposal), same union pattern.
        $fixedAssets = DB::table('cbe_fixed_asset_requests as r')
            ->join('agents as a', 'a.agent_id', '=', 'r.prepared_by')
            ->where('r.cbe_node_id', $cbeNodeId)->where('r.status', 'PENDING')
            ->select('r.request_id as ref_id', DB::raw("CONCAT('FIXED_ASSET_', r.action_type) as approval_type"), 'r.entry_date',
                'r.description', 'r.amount', 'a.full_name as prepared_by_name', 'r.created_at')
            ->get();

        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Phase 7. No
        // monetary "amount" column fits naturally here, so the count of
        // matched bank transactions in the reconciliation's window is
        // shown instead (0 renders fine — it just means an unmatched
        // book-balance-only reconciliation).
        $bankReconciliations = DB::table('cbe_bank_reconciliations as r')
            ->join('agents as a', 'a.agent_id', '=', 'r.completed_by')
            ->where('r.cbe_node_id', $cbeNodeId)->where('r.status', 'PENDING_APPROVAL')
            ->select('r.reconciliation_id as ref_id', DB::raw("'BANK_RECONCILIATION' as approval_type"), 'r.statement_date as entry_date',
                DB::raw("CONCAT('Bank reconciliation — ', COALESCE(r.reconciliation_no, r.reconciliation_id)) as description"),
                'r.ending_balance as amount', 'a.full_name as prepared_by_name', 'r.updated_at as created_at')
            ->get();

        return $bills->concat($transfers)->concat($journals)->concat($fixedAssets)->concat($bankReconciliations)->sortBy('created_at')->values();
    }

    // NEW 3 Sep 2026 (Task #373) — per Chris's AP spec: Approval sub-menu
    // wants Pending / Approved / Rejected as separate views, not just the
    // pending queue built for Task #334. Same 3-source union as
    // pendingApprovals() above, filtered to history statuses instead, with
    // who approved/rejected it and when.
    public static function approvalHistory(string $cbeNodeId, string $status): \Illuminate\Support\Collection
    {
        $bills = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('agents as a', 'a.agent_id', '=', 'p.recorded_by')
            ->leftJoin('agents as ap', 'ap.agent_id', '=', 'p.approved_by')
            ->where('b.cbe_node_id', $cbeNodeId)->where('p.status', $status)
            ->select('p.payment_id as ref_id', DB::raw("'BILL_PAYMENT' as approval_type"), 'p.payment_date as entry_date',
                DB::raw("CONCAT('Bill payment — ', b.bill_no) as description"), 'p.amount', 'a.full_name as prepared_by_name',
                'ap.full_name as approved_by_name', 'p.approved_at', 'p.rejection_reason')
            ->get();

        $transfers = DB::table('cbe_bank_transfers as t')
            ->join('agents as a', 'a.agent_id', '=', 't.prepared_by')
            ->leftJoin('agents as ap', 'ap.agent_id', '=', 't.approved_by')
            ->where('t.cbe_node_id', $cbeNodeId)->where('t.status', $status)
            ->select('t.transfer_id as ref_id', DB::raw("'BANK_TRANSFER' as approval_type"), 't.transfer_date as entry_date',
                DB::raw("CONCAT('Bank transfer — ', COALESCE(t.purpose, t.reference_no, 'no reference')) as description"), 't.amount', 'a.full_name as prepared_by_name',
                'ap.full_name as approved_by_name', 't.approved_at', 't.rejection_reason')
            ->get();

        $journals = DB::table('cbe_journal_voucher_drafts as j')
            ->join('agents as a', 'a.agent_id', '=', 'j.prepared_by')
            ->leftJoin('agents as ap', 'ap.agent_id', '=', 'j.approved_by')
            ->where('j.cbe_node_id', $cbeNodeId)->where('j.status', $status)
            ->select('j.draft_id as ref_id', DB::raw("'JOURNAL_VOUCHER' as approval_type"), 'j.entry_date',
                'j.description', 'j.total_amount as amount', 'a.full_name as prepared_by_name',
                'ap.full_name as approved_by_name', 'j.approved_at', 'j.rejection_reason')
            ->get();

        // NEW 4 Sep 2026 (Task #395) — Fixed Asset requests history.
        $fixedAssets = DB::table('cbe_fixed_asset_requests as r')
            ->join('agents as a', 'a.agent_id', '=', 'r.prepared_by')
            ->leftJoin('agents as ap', 'ap.agent_id', '=', 'r.approved_by')
            ->where('r.cbe_node_id', $cbeNodeId)->where('r.status', $status)
            ->select('r.request_id as ref_id', DB::raw("CONCAT('FIXED_ASSET_', r.action_type) as approval_type"), 'r.entry_date',
                'r.description', 'r.amount', 'a.full_name as prepared_by_name',
                'ap.full_name as approved_by_name', 'r.approved_at', 'r.rejection_reason')
            ->get();

        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Phase 7
        // history. Unlike the other 4 sources, this table has no plain
        // APPROVED/REJECTED status value (it reuses COMPLETED/DRAFT so
        // every other status === 'COMPLETED' check elsewhere in the app
        // keeps working unchanged) — so "approved" / "rejected" here is
        // read off approved_by / rejection_reason instead of $status.
        $bankReconciliations = DB::table('cbe_bank_reconciliations as r')
            ->join('agents as a', 'a.agent_id', '=', 'r.completed_by')
            ->leftJoin('agents as ap', 'ap.agent_id', '=', 'r.approved_by')
            ->where('r.cbe_node_id', $cbeNodeId)->whereNotNull('r.approved_by')
            ->when($status === 'APPROVED', fn ($q) => $q->whereNull('r.rejection_reason'))
            ->when($status === 'REJECTED', fn ($q) => $q->whereNotNull('r.rejection_reason'))
            ->select('r.reconciliation_id as ref_id', DB::raw("'BANK_RECONCILIATION' as approval_type"), 'r.statement_date as entry_date',
                DB::raw("CONCAT('Bank reconciliation — ', COALESCE(r.reconciliation_no, r.reconciliation_id)) as description"),
                'r.ending_balance as amount', 'a.full_name as prepared_by_name',
                'ap.full_name as approved_by_name', 'r.approved_at', 'r.rejection_reason')
            ->get();

        return $bills->concat($transfers)->concat($journals)->concat($fixedAssets)->concat($bankReconciliations)->sortByDesc('approved_at')->values();
    }

    // ---------- Purchase Requests (NEW 2 Sep 2026, Task #335) ----------
    // Pre-bill approval step. Raised by one officer, approved by a
    // DIFFERENT officer (same self-approval block as Maker-Checker,
    // ADMIN exempt), then converted into a real Purchase Bill with one
    // click — no money moves and nothing posts to the ledger until that
    // conversion happens.

    public static function approvePurchaseRequest(string $requestId, string $approvedBy, bool $isAdmin = false): array
    {
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->first();
        if (! $pr || $pr->status !== 'PENDING') {
            return ['ok' => false, 'error' => 'not_pending'];
        }
        if (! self::canApprove($pr->requested_by, $approvedBy, $isAdmin)) {
            return ['ok' => false, 'error' => 'self_approval'];
        }

        DB::table('cbe_purchase_requests')->where('request_id', $requestId)->update([
            'status' => 'APPROVED', 'approved_by' => $approvedBy, 'approved_at' => now(), 'updated_at' => now(),
        ]);

        return ['ok' => true];
    }

    public static function rejectPurchaseRequest(string $requestId, string $rejectedBy, string $reason, bool $isAdmin = false): array
    {
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->first();
        if (! $pr || $pr->status !== 'PENDING') {
            return ['ok' => false, 'error' => 'not_pending'];
        }
        if (! self::canApprove($pr->requested_by, $rejectedBy, $isAdmin)) {
            return ['ok' => false, 'error' => 'self_approval'];
        }

        DB::table('cbe_purchase_requests')->where('request_id', $requestId)->update([
            'status' => 'REJECTED', 'approved_by' => $rejectedBy, 'approved_at' => now(),
            'rejection_reason' => $reason, 'updated_at' => now(),
        ]);

        return ['ok' => true];
    }

    // Converts an APPROVED request straight into a real Purchase Bill —
    // same cbe_purchase_bills + cbe_bill_lines shape and posting logic as
    // a bill entered directly, just seeded from the request's lines
    // instead of typed fresh. Requires a supplier on the request (one
    // may not have been picked yet when the request was first raised).
    public static function convertPurchaseRequestToBill(string $requestId, string $convertedBy, string $billDate): array
    {
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->first();
        if (! $pr || $pr->status !== 'APPROVED') {
            return ['ok' => false, 'error' => 'not_approved'];
        }
        if (! $pr->supplier_id) {
            return ['ok' => false, 'error' => 'no_supplier'];
        }

        $lines = DB::table('cbe_purchase_request_lines')->where('request_id', $requestId)->orderBy('display_order')->get();
        if ($lines->isEmpty()) {
            return ['ok' => false, 'error' => 'no_lines'];
        }

        $billId = (string) Str::uuid();
        $billDocRefNo = self::nextDocumentNumber($pr->cbe_node_id, 'BILL', (int) \Carbon\Carbon::parse($billDate)->year, 'BILL', (int) \Carbon\Carbon::parse($billDate)->month);

        DB::table('cbe_purchase_bills')->insert([
            'bill_id' => $billId,
            'doc_ref_no' => $billDocRefNo,
            'cbe_node_id' => $pr->cbe_node_id,
            'supplier_id' => $pr->supplier_id,
            'bill_no' => $pr->doc_ref_no,
            'bill_date' => $billDate,
            'due_date' => null,
            'description' => $pr->description,
            'amount' => $pr->amount,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'category_id' => null,
            'attachment_path' => null,
            'attachment_original_name' => null,
            'recorded_by' => $convertedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lines as $line) {
            DB::table('cbe_bill_lines')->insert([
                'line_id' => (string) Str::uuid(),
                'bill_id' => $billId,
                'category_id' => $line->category_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'tax_rate' => 0,
                'line_amount' => $line->line_amount,
                'tax_amount' => 0,
                'line_total' => $line->line_amount,
                'display_order' => $line->display_order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        self::postBill($billId);

        DB::table('cbe_purchase_requests')->where('request_id', $requestId)->update([
            'status' => 'CONVERTED', 'converted_bill_id' => $billId, 'updated_at' => now(),
        ]);

        return ['ok' => true, 'bill_id' => $billId];
    }

    // ---------- Purchase Orders (NEW 4 Sep 2026, Task #393) ----------
    // Converts an APPROVED Purchase Request into a Purchase Order — the
    // commitment actually sent to the supplier. No GL posting here (see
    // migration comment on cbe_purchase_orders): a PO is not yet a
    // liability under accrual accounting, only the eventual Bill is.
    public static function convertPurchaseRequestToPO(string $requestId, string $convertedBy, string $poDate, ?string $expectedDeliveryDate = null): array
    {
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->first();
        if (! $pr || $pr->status !== 'APPROVED') {
            return ['ok' => false, 'error' => 'not_approved'];
        }
        if (! $pr->supplier_id) {
            return ['ok' => false, 'error' => 'no_supplier'];
        }

        $lines = DB::table('cbe_purchase_request_lines')->where('request_id', $requestId)->orderBy('display_order')->get();
        if ($lines->isEmpty()) {
            return ['ok' => false, 'error' => 'no_lines'];
        }

        $poId = self::createPurchaseOrderFromLines($pr->cbe_node_id, $pr->supplier_id, $requestId, $poDate, $expectedDeliveryDate, $pr->description, $pr->amount, $convertedBy, $lines->map(fn ($l) => (array) $l)->all());

        DB::table('cbe_purchase_requests')->where('request_id', $requestId)->update([
            'status' => 'CONVERTED_TO_PO', 'converted_po_id' => $poId, 'updated_at' => now(),
        ]);

        return ['ok' => true, 'po_id' => $poId];
    }

    // Shared by both the PR->PO conversion above and a PO created
    // directly (no Purchase Request stage) — see storePurchaseOrder() in
    // the controller for the direct-entry path. $extra carries the
    // optional fields added in Task #394 (rfq_id/cost_centre_id/fund_id/
    // delivery_address/discount_amount/tax_amount) so the two original
    // call sites (which predate those fields) do not need updating.
    public static function createPurchaseOrderFromLines(string $cbeNodeId, string $supplierId, ?string $requestId, string $poDate, ?string $expectedDeliveryDate, ?string $description, float $amount, string $createdBy, array $lines, array $extra = []): string
    {
        $poId = (string) Str::uuid();
        $docRefNo = self::nextDocumentNumber($cbeNodeId, 'PO', (int) \Carbon\Carbon::parse($poDate)->year, 'PO', (int) \Carbon\Carbon::parse($poDate)->month);

        // NEW 4 Sep 2026 (Task #394) — reuse the same per-node
        // Maker-Checker threshold already used for Bill Payments/Bank
        // Transfers/Journal Vouchers (cbe_approval_settings) for Purchase
        // Order approval: a PO at or above that node's threshold needs a
        // second officer's approval before it can be sent to the supplier
        // or converted to a Bill. Below the threshold, or when
        // Maker-Checker is switched off for that node, the PO is
        // auto-approved exactly like today.
        $approvalStatus = 'APPROVED';
        $settings = DB::table('cbe_approval_settings')->where('cbe_node_id', $cbeNodeId)->first();
        if ($settings && $settings->maker_checker_enabled && round($amount, 2) >= (float) $settings->threshold_amount) {
            $approvalStatus = 'PENDING_APPROVAL';
        }

        DB::table('cbe_purchase_orders')->insert([
            'po_id' => $poId,
            'doc_ref_no' => $docRefNo,
            'cbe_node_id' => $cbeNodeId,
            'supplier_id' => $supplierId,
            'request_id' => $requestId,
            'rfq_id' => $extra['rfq_id'] ?? null,
            'cost_centre_id' => $extra['cost_centre_id'] ?? null,
            'fund_id' => $extra['fund_id'] ?? null,
            'po_date' => $poDate,
            'expected_delivery_date' => $expectedDeliveryDate,
            'delivery_address' => $extra['delivery_address'] ?? null,
            'description' => $description,
            'amount' => round($amount, 2),
            'discount_amount' => round($extra['discount_amount'] ?? 0, 2),
            'tax_amount' => round($extra['tax_amount'] ?? 0, 2),
            'status' => 'OPEN',
            'approval_status' => $approvalStatus,
            'created_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lines as $i => $line) {
            DB::table('cbe_purchase_order_lines')->insert([
                'line_id' => (string) Str::uuid(),
                'po_id' => $poId,
                'category_id' => $line['category_id'] ?? null,
                'description' => $line['description'] ?? null,
                'quantity' => $line['quantity'] ?? 1,
                'unit_price' => $line['unit_price'],
                'line_amount' => $line['line_amount'],
                'display_order' => $line['display_order'] ?? $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $poId;
    }

    // Converts an OPEN/PARTIALLY_RECEIVED/FULLY_RECEIVED PO into a real
    // Purchase Bill once the supplier's invoice actually arrives — same
    // shape and posting logic as a bill entered directly or converted
    // from a Purchase Request. The PO is marked CLOSED once its full
    // amount has been billed (a temple can also part-bill a PO across
    // more than one delivery, so this only closes when nothing remains).
    public static function convertPurchaseOrderToBill(string $poId, string $convertedBy, string $billDate): array
    {
        $po = DB::table('cbe_purchase_orders')->where('po_id', $poId)->first();
        if (! $po || in_array($po->status, ['CLOSED', 'CANCELLED'], true)) {
            return ['ok' => false, 'error' => 'not_open'];
        }
        // NEW 4 Sep 2026 (Task #394) — a PO still awaiting Maker-Checker
        // approval cannot be sent to the supplier or converted to a Bill.
        if ($po->approval_status === 'PENDING_APPROVAL') {
            return ['ok' => false, 'error' => 'pending_approval'];
        }

        $lines = DB::table('cbe_purchase_order_lines')->where('po_id', $poId)->orderBy('display_order')->get();
        if ($lines->isEmpty()) {
            return ['ok' => false, 'error' => 'no_lines'];
        }

        // At least one GRN posted against this PO — used only to set a
        // starting match_status; the full PO+GRN+Invoice comparison
        // (quantity/price variance) happens in the dedicated Supplier
        // Invoice entry screen (Task #394 section 10), not here.
        $hasGrn = DB::table('cbe_goods_receipts')->where('po_id', $poId)->where('status', '!=', 'CANCELLED')->exists();

        $billId = (string) Str::uuid();
        $billDocRefNo = self::nextDocumentNumber($po->cbe_node_id, 'BILL', (int) \Carbon\Carbon::parse($billDate)->year, 'BILL', (int) \Carbon\Carbon::parse($billDate)->month);

        DB::table('cbe_purchase_bills')->insert([
            'bill_id' => $billId,
            'doc_ref_no' => $billDocRefNo,
            'cbe_node_id' => $po->cbe_node_id,
            'supplier_id' => $po->supplier_id,
            'po_id' => $poId,
            'cost_centre_id' => $po->cost_centre_id,
            'fund_id' => $po->fund_id,
            'match_status' => $hasGrn ? 'MATCHED' : 'NO_GRN',
            'bill_no' => $po->doc_ref_no,
            'bill_date' => $billDate,
            'due_date' => null,
            'description' => $po->description,
            'amount' => $po->amount,
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'category_id' => null,
            'attachment_path' => null,
            'attachment_original_name' => null,
            'recorded_by' => $convertedBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lines as $line) {
            DB::table('cbe_bill_lines')->insert([
                'line_id' => (string) Str::uuid(),
                'bill_id' => $billId,
                'category_id' => $line->category_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'tax_rate' => 0,
                'line_amount' => $line->line_amount,
                'tax_amount' => 0,
                'line_total' => $line->line_amount,
                'display_order' => $line->display_order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        self::postBill($billId);

        DB::table('cbe_purchase_orders')->where('po_id', $poId)->update([
            'status' => 'CLOSED', 'updated_at' => now(),
        ]);

        return ['ok' => true, 'bill_id' => $billId];
    }

    // ---------- Goods / Service Receipt (NEW 4 Sep 2026, Task #394 Phase 2) ----------
    // Records what actually arrived against a PO. received_quantity here
    // means physically delivered (accepted + rejected together) — a
    // rejected quantity is a separate Purchase Return concern, not a
    // reason to treat the PO line as still outstanding once the supplier
    // has genuinely sent it. $lines = [['po_line_id', 'description',
    // 'ordered_quantity', 'received_quantity', 'rejected_quantity',
    // 'condition_note', 'remarks'], ...].
    public static function createGoodsReceipt(string $poId, string $grnDate, string $receiptType, ?string $remarks, ?string $attachmentPath, ?string $attachmentOriginalName, string $createdBy, array $lines): array
    {
        $po = DB::table('cbe_purchase_orders')->where('po_id', $poId)->first();
        if (! $po || $po->approval_status !== 'APPROVED') {
            return ['ok' => false, 'error' => 'not_approved'];
        }
        if (! in_array($po->status, ['OPEN', 'PARTIALLY_RECEIVED'], true)) {
            return ['ok' => false, 'error' => 'not_open'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'error' => 'no_lines'];
        }

        $grnId = (string) Str::uuid();
        $docRefNo = self::nextDocumentNumber($po->cbe_node_id, 'GRN', (int) \Carbon\Carbon::parse($grnDate)->year, 'GRN', (int) \Carbon\Carbon::parse($grnDate)->month);

        DB::table('cbe_goods_receipts')->insert([
            'grn_id' => $grnId,
            'doc_ref_no' => $docRefNo,
            'cbe_node_id' => $po->cbe_node_id,
            'po_id' => $poId,
            'supplier_id' => $po->supplier_id,
            'grn_date' => $grnDate,
            'receipt_type' => $receiptType,
            'status' => 'CONFIRMED',
            'remarks' => $remarks,
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentOriginalName,
            'created_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lines as $i => $line) {
            $receivedQty = (float) ($line['received_quantity'] ?? 0);
            if ($receivedQty <= 0) {
                continue;
            }
            DB::table('cbe_goods_receipt_lines')->insert([
                'grn_line_id' => (string) Str::uuid(),
                'grn_id' => $grnId,
                'po_line_id' => $line['po_line_id'] ?? null,
                'description' => $line['description'] ?? null,
                'ordered_quantity' => $line['ordered_quantity'] ?? 0,
                'received_quantity' => $receivedQty,
                'rejected_quantity' => (float) ($line['rejected_quantity'] ?? 0),
                'condition_note' => $line['condition_note'] ?? 'GOOD',
                'remarks' => $line['remarks'] ?? null,
                'display_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            if (! empty($line['po_line_id'])) {
                DB::table('cbe_purchase_order_lines')->where('line_id', $line['po_line_id'])
                    ->increment('received_quantity', $receivedQty);
            }
        }

        // Recompute PO header status from the running cumulative totals.
        $poLines = DB::table('cbe_purchase_order_lines')->where('po_id', $poId)->get();
        $anyReceived = $poLines->contains(fn ($l) => (float) $l->received_quantity > 0);
        $allReceived = $poLines->every(fn ($l) => (float) $l->received_quantity >= (float) $l->quantity);
        DB::table('cbe_purchase_orders')->where('po_id', $poId)->update([
            'status' => $allReceived ? 'FULLY_RECEIVED' : ($anyReceived ? 'PARTIALLY_RECEIVED' : 'OPEN'),
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'grn_id' => $grnId, 'doc_ref_no' => $docRefNo];
    }

    // Reverses a GRN's effect on its PO's cumulative received quantities
    // and recomputes the PO status — used when a GRN entered in error is
    // cancelled. Does not touch any Bill already raised against the PO.
    public static function cancelGoodsReceipt(string $grnId): array
    {
        $grn = DB::table('cbe_goods_receipts')->where('grn_id', $grnId)->first();
        if (! $grn || $grn->status === 'CANCELLED') {
            return ['ok' => false, 'error' => 'already_cancelled'];
        }

        $lines = DB::table('cbe_goods_receipt_lines')->where('grn_id', $grnId)->get();
        foreach ($lines as $line) {
            if ($line->po_line_id) {
                DB::table('cbe_purchase_order_lines')->where('line_id', $line->po_line_id)
                    ->decrement('received_quantity', $line->received_quantity);
            }
        }

        DB::table('cbe_goods_receipts')->where('grn_id', $grnId)->update(['status' => 'CANCELLED', 'updated_at' => now()]);

        $poLines = DB::table('cbe_purchase_order_lines')->where('po_id', $grn->po_id)->get();
        $anyReceived = $poLines->contains(fn ($l) => (float) $l->received_quantity > 0);
        $allReceived = $poLines->every(fn ($l) => (float) $l->received_quantity >= (float) $l->quantity);
        DB::table('cbe_purchase_orders')->where('po_id', $grn->po_id)->where('status', '!=', 'CANCELLED')->update([
            'status' => $allReceived ? 'FULLY_RECEIVED' : ($anyReceived ? 'PARTIALLY_RECEIVED' : 'OPEN'),
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'doc_ref_no' => $grn->doc_ref_no];
    }

    // ---------- Supplier Invoice 3-Way Matching (NEW 4 Sep 2026, Task #394 Phase 2) ----------
    // Creates a Supplier Invoice (Bill) from a confirmed Goods Receipt,
    // comparing what was ordered (PO), what arrived (GRN) and what the
    // supplier is now billing, per spec section 10. $lines = [['po_line_id',
    // 'grn_line_id', 'description', 'invoiced_quantity',
    // 'invoiced_unit_price', 'category_id'], ...]. A GRN can only be
    // billed once — it moves to POSTED status here — matching the
    // duplicate-invoice control the spec asks for at the source-document
    // level, not just the invoice-number level.
    public static function createBillFromGoodsReceipt(string $grnId, ?string $billNo, string $billDate, ?string $dueDate, string $createdBy, array $lines): array
    {
        $grn = DB::table('cbe_goods_receipts')->where('grn_id', $grnId)->first();
        if (! $grn || $grn->status !== 'CONFIRMED') {
            return ['ok' => false, 'error' => 'grn_not_available'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'error' => 'no_lines'];
        }

        $poLinesById = DB::table('cbe_purchase_order_lines')->where('po_id', $grn->po_id)->get()->keyBy('line_id');

        $matchStatus = 'MATCHED';
        $totalAmount = 0;
        $billLineRows = [];
        foreach ($lines as $i => $line) {
            $qty = (float) $line['invoiced_quantity'];
            $unitPrice = (float) $line['invoiced_unit_price'];
            if ($qty <= 0) {
                continue;
            }
            $lineAmount = round($qty * $unitPrice, 2);
            $totalAmount += $lineAmount;

            $poLine = ! empty($line['po_line_id']) ? ($poLinesById[$line['po_line_id']] ?? null) : null;
            if ($poLine) {
                if (abs((float) $poLine->unit_price - $unitPrice) > 0.01) {
                    $matchStatus = 'PRICE_VARIANCE';
                } elseif ($matchStatus === 'MATCHED' && abs((float) $poLine->quantity - $qty) > 0.01) {
                    $matchStatus = 'QUANTITY_VARIANCE';
                }
            }

            $billLineRows[] = [
                'category_id' => $line['category_id'] ?? null,
                'description' => $line['description'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => 0,
                'line_amount' => $lineAmount,
                'tax_amount' => 0,
                'line_total' => $lineAmount,
                'display_order' => $i,
            ];
        }

        if (empty($billLineRows)) {
            return ['ok' => false, 'error' => 'no_lines'];
        }

        if ($billNo && DB::table('cbe_purchase_bills')->where('supplier_id', $grn->supplier_id)->where('bill_no', $billNo)->where('status', '!=', 'CANCELLED')->exists()) {
            return ['ok' => false, 'error' => 'duplicate_invoice'];
        }

        $billId = (string) Str::uuid();
        $billDocRefNo = self::nextDocumentNumber($grn->cbe_node_id, 'BILL', (int) \Carbon\Carbon::parse($billDate)->year, 'BILL', (int) \Carbon\Carbon::parse($billDate)->month);

        DB::table('cbe_purchase_bills')->insert([
            'bill_id' => $billId,
            'doc_ref_no' => $billDocRefNo,
            'cbe_node_id' => $grn->cbe_node_id,
            'supplier_id' => $grn->supplier_id,
            'po_id' => $grn->po_id,
            'grn_id' => $grnId,
            'bill_no' => $billNo,
            'bill_date' => $billDate,
            'due_date' => $dueDate,
            'description' => null,
            'amount' => round($totalAmount, 2),
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'category_id' => null,
            'match_status' => $matchStatus,
            'attachment_path' => null,
            'attachment_original_name' => null,
            'recorded_by' => $createdBy,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($billLineRows as $row) {
            DB::table('cbe_bill_lines')->insert(array_merge($row, [
                'line_id' => (string) Str::uuid(),
                'bill_id' => $billId,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        self::postBill($billId);

        DB::table('cbe_goods_receipts')->where('grn_id', $grnId)->update(['status' => 'POSTED', 'updated_at' => now()]);

        return ['ok' => true, 'bill_id' => $billId, 'match_status' => $matchStatus, 'doc_ref_no' => $billDocRefNo];
    }

    // ---------- Purchase Quotations / RFQ (NEW 4 Sep 2026, Task #394) ----------
    // Converts an evaluated RFQ (one supplier already marked selected)
    // into a real Purchase Order for that supplier, carrying the quoted
    // amount across as a single line so it doesn't need re-entry. Mirrors
    // convertPurchaseRequestToPO() above — same "no GL posting until the
    // eventual Bill" rule applies here too.
    public static function convertPurchaseQuotationToPO(string $rfqId, string $convertedBy, string $poDate, ?string $expectedDeliveryDate = null): array
    {
        $rfq = DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfqId)->first();
        if (! $rfq || ! in_array($rfq->status, ['EVALUATED'], true)) {
            return ['ok' => false, 'error' => 'not_evaluated'];
        }
        if (! $rfq->selected_supplier_id) {
            return ['ok' => false, 'error' => 'no_selection'];
        }

        $selected = DB::table('cbe_purchase_rfq_suppliers')
            ->where('rfq_id', $rfqId)->where('supplier_id', $rfq->selected_supplier_id)->first();
        if (! $selected || ! $selected->quoted_amount) {
            return ['ok' => false, 'error' => 'no_quote_amount'];
        }

        $lines = [[
            'category_id' => null,
            'description' => $rfq->description,
            'quantity' => 1,
            'unit_price' => $selected->quoted_amount,
            'line_amount' => $selected->quoted_amount,
            'display_order' => 0,
        ]];

        $poId = self::createPurchaseOrderFromLines(
            $rfq->cbe_node_id, $rfq->selected_supplier_id, $rfq->request_id, $poDate, $expectedDeliveryDate,
            $rfq->description, (float) $selected->quoted_amount, $convertedBy, $lines, [
                'rfq_id' => $rfqId,
                'cost_centre_id' => $rfq->cost_centre_id,
                'fund_id' => $rfq->fund_id,
            ]
        );

        DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfqId)->update([
            'status' => 'AWARDED', 'converted_po_id' => $poId, 'updated_at' => now(),
        ]);

        return ['ok' => true, 'po_id' => $poId];
    }

    // Writes one journal entry with 2+ balanced lines. $lines is
    // [[account_id, debit, credit, memo, cost_centre_id, fund_id], ...]
    // — caller guarantees sum(debit) === sum(credit). cost_centre_id and
    // fund_id (NEW 3 Sep 2026, Task #389 — see cbe_journal_lines
    // migration comment) are both optional per line.
    private static function postJournal(string $cbeNodeId, string $entryDate, string $description, string $sourceType, ?string $sourceId, string $createdBy, array $lines, ?string $referenceNo = null, ?string $journalTypeId = null, ?string $attachmentPath = null, ?string $attachmentOriginalName = null): string
    {
        if (self::isPeriodClosed($cbeNodeId, $entryDate)) {
            throw new \RuntimeException('period_closed');
        }

        $journalId = (string) Str::uuid();

        // NEW 3 Sep 2026 (Task #382) — every journal entry, regardless of
        // source, now gets its own human-readable sequential Journal
        // Number (separate from reference_no, which for e.g. a BILL
        // journal carries the bill's own bill_no) and a Journal Type —
        // explicit if the caller passed one (Adjustment/Accrual JV
        // screens do), otherwise inferred from source_type so every
        // existing posting path keeps working unchanged.
        $journalNo = self::nextDocumentNumber($cbeNodeId, 'JE', (int) \Carbon\Carbon::parse($entryDate)->year, 'JE', (int) \Carbon\Carbon::parse($entryDate)->month);
        $journalTypeId = $journalTypeId ?? self::journalTypeIdForSource($sourceType);

        DB::table('cbe_journal_entries')->insert([
            'journal_id'      => $journalId,
            'cbe_node_id'     => $cbeNodeId,
            'entry_date'      => $entryDate,
            'reference_no'    => $referenceNo,
            'journal_no'      => $journalNo,
            'journal_type_id' => $journalTypeId,
            'description'     => $description,
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentOriginalName,
            'source_type'     => $sourceType,
            'source_id'       => $sourceId,
            'created_by'      => $createdBy,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        foreach (array_values($lines) as $i => $line) {
            DB::table('cbe_journal_lines')->insert([
                'line_id'        => (string) Str::uuid(),
                'journal_id'     => $journalId,
                'account_id'     => $line[0],
                'cost_centre_id' => $line[4] ?? null,
                'fund_id'        => $line[5] ?? null,
                'debit'          => $line[1],
                'credit'         => $line[2],
                'memo'           => $line[3],
                'display_order'  => $i,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return $journalId;
    }

    // NEW 3 Sep 2026 (Task #382) — maps a posting's source_type to one of
    // the 8 system Journal Types (General/Adjustment/Accrual/Reversal/
    // Opening/Closing/Recurring/Imported) so every journal gets a
    // sensible default without every caller having to know about this
    // new field. Static cache avoids re-querying the (tiny, rarely
    // changing) cbe_journal_types table on every single line posted.
    private static ?array $journalTypeCache = null;

    private static function journalTypeIdForSource(string $sourceType): ?string
    {
        if (self::$journalTypeCache === null) {
            self::$journalTypeCache = DB::table('cbe_journal_types')->pluck('type_id', 'type_code')->all();
        }
        $code = match (true) {
            $sourceType === 'REVERSAL' => 'REVERSAL',
            in_array($sourceType, ['OPENING_BALANCE', 'AR_OPENING_BALANCE', 'AP_OPENING_BALANCE'], true) => 'OPENING',
            default => 'GENERAL',
        };
        return self::$journalTypeCache[$code] ?? null;
    }

    // NEW 3 Sep 2026 (Task #383) — public helper so the Adjustment/
    // Accrual Journal entry screens can look up their fixed type_id
    // without duplicating the cache/lookup logic above.
    public static function journalTypeIdByCode(string $code): ?string
    {
        return DB::table('cbe_journal_types')->where('type_code', $code)->value('type_id');
    }

    // NEW 3 Sep 2026 (Task #383) — Recurring Journal templates. A
    // treasurer sets up the lines once; each period they open the
    // template and click Generate Now, which posts a journal exactly
    // like a manual JV (same postManualJournal/postJournal path, so it
    // gets its own Journal Number, respects the fiscal period lock, and
    // shows up in every report the same as any other entry) tagged with
    // the RECURRING journal type, then advances next_run_date so the
    // template's "due" state is always current.
    public static function generateFromRecurringTemplate(string $templateId, string $generatedBy): array
    {
        $template = DB::table('cbe_recurring_journal_templates')->where('template_id', $templateId)->first();
        if (! $template || ! $template->is_active) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $runDate = $template->next_run_date;
        if (self::isPeriodClosed($template->cbe_node_id, $runDate)) {
            return ['ok' => false, 'error' => 'period_closed'];
        }

        $templateLines = DB::table('cbe_recurring_journal_template_lines')
            ->where('template_id', $templateId)->orderBy('display_order')->get();
        if ($templateLines->count() < 2) {
            return ['ok' => false, 'error' => 'insufficient_lines'];
        }

        $lines = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($templateLines as $tl) {
            $lines[] = [$tl->account_id, (float) $tl->debit, (float) $tl->credit, $tl->memo, $tl->cost_centre_id];
            $totalDebit += (float) $tl->debit;
            $totalCredit += (float) $tl->credit;
        }
        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return ['ok' => false, 'error' => 'unbalanced'];
        }

        $rjRefNo = self::nextDocumentNumber($template->cbe_node_id, 'RJ', (int) \Carbon\Carbon::parse($runDate)->year, 'RJ', (int) \Carbon\Carbon::parse($runDate)->month);
        $journalId = self::postManualJournal($template->cbe_node_id, $runDate, $template->template_name.($template->description ? ' — '.$template->description : ''), $generatedBy, $lines, $rjRefNo, self::journalTypeIdByCode('RECURRING'));

        $nextRunDate = match ($template->frequency) {
            'MONTHLY' => \Carbon\Carbon::parse($runDate)->addMonthNoOverflow(),
            'QUARTERLY' => \Carbon\Carbon::parse($runDate)->addMonthsNoOverflow(3),
            'YEARLY' => \Carbon\Carbon::parse($runDate)->addYear(),
            default => \Carbon\Carbon::parse($runDate)->addMonthNoOverflow(),
        };

        DB::table('cbe_recurring_journal_templates')->where('template_id', $templateId)->update([
            'last_generated_date' => $runDate,
            'next_run_date' => $nextRunDate->toDateString(),
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'journal_id' => $journalId];
    }

    // NEW 2 Sep 2026 (Task #338) — Void/Reversal control. Posts a
    // brand-new reversing entry (every line's debit/credit swapped)
    // dated today, cross-links both entries, and marks the original
    // VOIDED. Never edits or deletes a posted entry — see the
    // migration's header comment for why this is the one generic
    // mechanism used for every document type.
    public static function voidJournal(string $journalId, string $voidedBy, string $reason): array
    {
        $journal = DB::table('cbe_journal_entries')->where('journal_id', $journalId)->first();
        if (! $journal) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($journal->status === 'VOIDED') {
            return ['ok' => false, 'error' => 'already_voided'];
        }
        if ($journal->reverses_journal_id) {
            return ['ok' => false, 'error' => 'is_reversal'];
        }

        $voidDate = now()->toDateString();
        if (self::isPeriodClosed($journal->cbe_node_id, $voidDate)) {
            return ['ok' => false, 'error' => 'period_closed'];
        }

        // NEW 2 Sep 2026 (Task #361) — Cancel/Reverse gap fix: block
        // voiding an INVOICE journal if payments have already been
        // recorded against it, since cancelling the invoice while money
        // sits against it would leave the AR sub-ledger in an impossible
        // state (a paid amount with no invoice to belong to). The admin
        // must reverse the payment(s) first, then the invoice.
        if ($journal->source_type === 'INVOICE') {
            $invoice = DB::table('cbe_invoices')->where('invoice_id', $journal->source_id)->first();
            if ($invoice && (float) $invoice->paid_amount > 0.004) {
                return ['ok' => false, 'error' => 'invoice_has_payments'];
            }
        }

        // NEW 3 Sep 2026 (Task #376) — AP mirror of the INVOICE guard
        // above: block voiding a BILL journal if payments have already
        // been recorded against it, for the same reason (a paid amount
        // with no bill left to belong to).
        if ($journal->source_type === 'BILL') {
            $bill = DB::table('cbe_purchase_bills')->where('bill_id', $journal->source_id)->first();
            if ($bill && (float) $bill->paid_amount > 0.004) {
                return ['ok' => false, 'error' => 'bill_has_payments'];
            }
        }

        $lines = DB::table('cbe_journal_lines')->where('journal_id', $journalId)->orderBy('display_order')->get();
        $reversalLines = $lines->map(fn ($l) => [$l->account_id, (float) $l->credit, (float) $l->debit, $l->memo])->all();

        $reversalId = self::postJournal(
            $journal->cbe_node_id,
            $voidDate,
            'Reversal — '.$journal->description,
            'REVERSAL',
            $journalId,
            $voidedBy,
            $reversalLines,
            $journal->reference_no
        );

        DB::table('cbe_journal_entries')->where('journal_id', $reversalId)->update([
            'reverses_journal_id' => $journalId, 'updated_at' => now(),
        ]);
        DB::table('cbe_journal_entries')->where('journal_id', $journalId)->update([
            'status' => 'VOIDED', 'voided_by' => $voidedBy, 'voided_at' => now(),
            'void_reason' => $reason, 'reversed_by_journal_id' => $reversalId, 'updated_at' => now(),
        ]);

        // NEW 2 Sep 2026 (Task #365) — flip the source AR document's GL
        // Posting Status to REVERSED so it stops showing as POSTED after
        // its journal is voided. Every AR table that stores journal_id
        // is checked (a journal only ever belongs to one, so at most one
        // update takes effect).
        // NEW 2 Sep 2026 (Task #361) — added the 3 AR tables that only
        // gained journal_id/gl_posting_status in migration 000018
        // (adjustments, refunds, opening balances); the original loop
        // predated that migration and was never extended to cover them.
        // NEW 3 Sep 2026 (Task #376) — added the 7 AP tables that gained
        // journal_id/gl_posting_status this session (Task #372/#377); the
        // loop previously only covered the AR side, so voiding an AP
        // journal left its source document's GL status stuck on POSTED.
        foreach (['cbe_invoices', 'cbe_invoice_payments', 'cbe_ar_debit_notes', 'cbe_ar_credit_notes', 'cbe_ar_adjustments', 'cbe_ar_refunds', 'cbe_ar_opening_balances', 'cbe_purchase_bills', 'cbe_bill_payments', 'cbe_debit_notes', 'cbe_ap_debit_notes', 'cbe_ap_adjustments', 'cbe_ap_refunds', 'cbe_ap_opening_balances'] as $table) {
            DB::table($table)->where('journal_id', $journalId)->update(['gl_posting_status' => 'REVERSED', 'updated_at' => now()]);
        }

        // NEW 2 Sep 2026 (Task #361) — Cancel/Reverse gap fix: until now,
        // voiding an AR journal only ever reversed the GL side — the AR
        // sub-ledger (cbe_invoices.status/paid_amount/amount) stayed
        // frozen at whatever it was, so a "voided" invoice still showed
        // as outstanding on the Debtor Ledger, AR Aging, and every
        // enquiry screen. This rolls the sub-ledger back too, keeping it
        // consistent with the GL in the same action — never a silent
        // manual edit, always driven by the same reversal event.
        self::rollbackArSubLedger($journal);

        // NEW 3 Sep 2026 (Task #376) — AP mirror of the AR sub-ledger
        // rollback above, for the same reason: keeps Supplier Enquiry,
        // Bill Enquiry, AP Aging and every AP report consistent with the
        // GL the instant a journal is voided, rather than needing a
        // separate manual correction.
        self::rollbackApSubLedger($journal);

        return ['ok' => true, 'reversal_journal_id' => $reversalId];
    }

    // Reverses the AR sub-ledger side-effect that was applied when the
    // now-voided journal's source document was first posted. Guarded by
    // source_type since each AR document type touches the sub-ledger
    // differently (or not at all — AR_REFUND/AR_OPENING_BALANCE have no
    // sub-ledger field to roll back beyond the GL status already flipped
    // above).
    private static function rollbackArSubLedger(object $journal): void
    {
        switch ($journal->source_type) {
            case 'INVOICE':
                // Guarded above: only reachable when paid_amount is 0.
                DB::table('cbe_invoices')->where('invoice_id', $journal->source_id)
                    ->update(['status' => 'CANCELLED', 'updated_at' => now()]);
                break;

            case 'INVOICE_PAYMENT':
                $payment = DB::table('cbe_invoice_payments')->where('payment_id', $journal->source_id)->first();
                if ($payment) {
                    $invoice = DB::table('cbe_invoices')->where('invoice_id', $payment->invoice_id)->first();
                    if ($invoice) {
                        $newPaid = max(0, (float) $invoice->paid_amount - (float) $payment->amount);
                        $status = $newPaid >= (float) $invoice->amount ? 'PAID' : ($newPaid > 0 ? 'PARTIALLY_PAID' : 'UNPAID');
                        DB::table('cbe_invoices')->where('invoice_id', $invoice->invoice_id)
                            ->update(['paid_amount' => $newPaid, 'status' => $status, 'updated_at' => now()]);
                    }
                }
                break;

            case 'AR_DEBIT_NOTE':
                $note = DB::table('cbe_ar_debit_notes')->where('debit_note_id', $journal->source_id)->first();
                if ($note && $note->invoice_id) {
                    DB::table('cbe_invoices')->where('invoice_id', $note->invoice_id)
                        ->update(['amount' => DB::raw('amount - '.(float) $note->amount), 'updated_at' => now()]);
                }
                break;

            case 'AR_CREDIT_NOTE':
                $note = DB::table('cbe_ar_credit_notes')->where('credit_note_id', $journal->source_id)->first();
                if ($note && $note->invoice_id) {
                    DB::table('cbe_invoices')->where('invoice_id', $note->invoice_id)
                        ->update(['amount' => DB::raw('amount + '.(float) $note->amount), 'updated_at' => now()]);
                }
                break;

            case 'AR_ADJUSTMENT':
                $adj = DB::table('cbe_ar_adjustments')->where('adjustment_id', $journal->source_id)->first();
                if ($adj && $adj->invoice_id) {
                    $op = $adj->direction === 'INCREASE' ? '-' : '+';
                    DB::table('cbe_invoices')->where('invoice_id', $adj->invoice_id)
                        ->update(['amount' => DB::raw('amount '.$op.' '.(float) $adj->amount), 'updated_at' => now()]);
                }
                break;

            // AR_REFUND, AR_OPENING_BALANCE: no invoice-amount side
            // effect was applied at post time, so there is nothing
            // further to roll back here beyond the GL status flip above.
        }
    }

    // NEW 3 Sep 2026 (Task #376) — AP mirror of rollbackArSubLedger()
    // above. Reverses the AP sub-ledger side-effect (cbe_purchase_bills
    // status/paid_amount/amount) that was applied when the now-voided
    // journal's source document was first posted.
    private static function rollbackApSubLedger(object $journal): void
    {
        switch ($journal->source_type) {
            case 'BILL':
                // Guarded above: only reachable when paid_amount is 0.
                DB::table('cbe_purchase_bills')->where('bill_id', $journal->source_id)
                    ->update(['status' => 'CANCELLED', 'updated_at' => now()]);
                break;

            case 'BILL_PAYMENT':
                $payment = DB::table('cbe_bill_payments')->where('payment_id', $journal->source_id)->first();
                if ($payment) {
                    $bill = DB::table('cbe_purchase_bills')->where('bill_id', $payment->bill_id)->first();
                    if ($bill) {
                        $newPaid = max(0, (float) $bill->paid_amount - (float) $payment->amount);
                        $status = $newPaid >= (float) $bill->amount ? 'PAID' : ($newPaid > 0 ? 'PARTIALLY_PAID' : 'UNPAID');
                        DB::table('cbe_purchase_bills')->where('bill_id', $bill->bill_id)
                            ->update(['paid_amount' => $newPaid, 'status' => $status, 'updated_at' => now()]);
                    }
                }
                break;

            // Displayed as "Credit Note" (see postDebitNote() comment) —
            // decreases the bill's amount at post time, so rolling back
            // adds it back.
            case 'DEBIT_NOTE':
                $note = DB::table('cbe_debit_notes')->where('debit_note_id', $journal->source_id)->first();
                if ($note && $note->bill_id) {
                    DB::table('cbe_purchase_bills')->where('bill_id', $note->bill_id)
                        ->update(['amount' => DB::raw('amount + '.(float) $note->amount), 'updated_at' => now()]);
                }
                break;

            case 'AP_DEBIT_NOTE':
                $note = DB::table('cbe_ap_debit_notes')->where('debit_note_id', $journal->source_id)->first();
                if ($note && $note->bill_id) {
                    DB::table('cbe_purchase_bills')->where('bill_id', $note->bill_id)
                        ->update(['amount' => DB::raw('amount - '.(float) $note->amount), 'updated_at' => now()]);
                }
                break;

            case 'AP_ADJUSTMENT':
                $adj = DB::table('cbe_ap_adjustments')->where('adjustment_id', $journal->source_id)->first();
                if ($adj && $adj->bill_id) {
                    $op = $adj->direction === 'INCREASE' ? '-' : '+';
                    DB::table('cbe_purchase_bills')->where('bill_id', $adj->bill_id)
                        ->update(['amount' => DB::raw('amount '.$op.' '.(float) $adj->amount), 'updated_at' => now()]);
                }
                break;

            // AP_REFUND, AP_OPENING_BALANCE: no bill-amount side effect
            // was applied at post time, so there is nothing further to
            // roll back here beyond the GL status flip above.
        }
    }

    // Call right after inserting a cbe_transactions row (both the
    // manual Finance > Transactions entry path and the Event::close()
    // summary-posting path — same table, same rule either way).
    public static function postTransaction(string $transactionId): void
    {
        $txn = DB::table('cbe_transactions as t')
            ->join('cbe_transaction_categories as c', 'c.category_id', '=', 't.category_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 't.cbe_node_id')
            ->where('t.transaction_id', $transactionId)
            ->select('t.*', 'c.type as category_type', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $txn) {
            return;
        }

        self::ensureChartOfAccounts($txn->group_label_id);
        $categoryAccountId = $txn->chart_account_id ?: self::uncategorisedExpenseAccountId($txn->group_label_id);
        $cashAccountId = self::bankAccountGlAccountId($txn->bank_account_id ?? null, $txn->cbe_node_id, $txn->group_label_id);

        if ($txn->category_type === 'INCOME') {
            $lines = [
                [$cashAccountId, $txn->amount, 0, $txn->description],
                [$categoryAccountId, 0, $txn->amount, $txn->description],
            ];
        } else {
            $lines = [
                [$categoryAccountId, $txn->amount, 0, $txn->description],
                [$cashAccountId, 0, $txn->amount, $txn->description],
            ];
        }

        self::postJournal($txn->cbe_node_id, $txn->transaction_date, $txn->description ?: '', 'TRANSACTION', $transactionId, $txn->entered_by, $lines);
    }

    // Call right after inserting a cbe_purchase_bills row — recognises
    // the expense and the liability immediately (accrual basis), before
    // any cash has actually been paid out.
    public static function postBill(string $billId): void
    {
        $bill = DB::table('cbe_purchase_bills as b')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'b.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'b.category_id')
            ->where('b.bill_id', $billId)
            ->select('b.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $bill) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($bill->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($bill->group_label_id);
        $apAccountId = self::apAccountId($bill->group_label_id);
        $memo = $bill->description ?: ('Bill '.$bill->bill_no);

        // NEW 2 Sep 2026 (Task #335) — multi-line bills. One Dr line per
        // distinct expense account (tax rolled into the same account —
        // see migration comment), one Cr to Accounts Payable for the
        // total. Falls back to the original single-line behaviour for
        // any bill saved before this feature existed.
        $lines = DB::table('cbe_bill_lines')->where('bill_id', $billId)->orderBy('display_order')->get();

        if ($lines->isEmpty()) {
            $expenseAccountId = $bill->chart_account_id ?: self::uncategorisedExpenseAccountId($bill->group_label_id);
            $journalId = self::postJournal($bill->cbe_node_id, $bill->bill_date, $memo, 'BILL', $billId, $bill->recorded_by, [
                [$expenseAccountId, $bill->amount, 0, $bill->description],
                [$apAccountId, 0, $bill->amount, $bill->description],
            ]);
            // NEW 3 Sep 2026 (Task #377) — AP/GL posting status linkage,
            // mirroring the AR side (Task #365).
            self::markGlPosted('cbe_purchase_bills', 'bill_id', $billId, $journalId);
            return;
        }

        $journalLines = [];
        foreach ($lines as $line) {
            $accountId = $line->category_id
                ? (DB::table('cbe_transaction_categories')->where('category_id', $line->category_id)->value('chart_account_id') ?: self::uncategorisedExpenseAccountId($bill->group_label_id))
                : self::uncategorisedExpenseAccountId($bill->group_label_id);
            $journalLines[] = [$accountId, $line->line_total, 0, $line->description ?: $memo];
        }
        $journalLines[] = [$apAccountId, 0, $bill->amount, $memo];

        $journalId = self::postJournal($bill->cbe_node_id, $bill->bill_date, $memo, 'BILL', $billId, $bill->recorded_by, $journalLines);
        self::markGlPosted('cbe_purchase_bills', 'bill_id', $billId, $journalId);
    }

    // Call right after inserting a cbe_bill_payments row — settles the
    // liability and moves cash. Also updates the parent bill's
    // paid_amount/status.
    public static function postBillPayment(string $paymentId): void
    {
        $payment = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'b.cbe_node_id')
            ->where('p.payment_id', $paymentId)
            ->select('p.*', 'b.bill_id', 'b.amount as bill_amount', 'b.paid_amount as bill_paid_amount', 'n.group_label_id')
            ->first();

        if (! $payment) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($payment->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($payment->group_label_id);
        $billNodeId = DB::table('cbe_purchase_bills')->where('bill_id', $payment->bill_id)->value('cbe_node_id');
        $cashAccountId = self::bankAccountGlAccountId($payment->bank_account_id ?? null, $billNodeId, $payment->group_label_id);
        $apAccountId = self::apAccountId($payment->group_label_id);

        $journalId = self::postJournal(
            $billNodeId,
            $payment->payment_date,
            'Bill payment',
            'BILL_PAYMENT',
            $paymentId,
            $payment->recorded_by,
            [
                [$apAccountId, $payment->amount, 0, 'Bill payment'],
                [$cashAccountId, 0, $payment->amount, 'Bill payment'],
            ]
        );
        // NEW 3 Sep 2026 (Task #377) — AP/GL posting status linkage.
        self::markGlPosted('cbe_bill_payments', 'payment_id', $paymentId, $journalId);

        $newPaid = (float) $payment->bill_paid_amount + (float) $payment->amount;
        $status = $newPaid >= (float) $payment->bill_amount ? 'PAID' : ($newPaid > 0 ? 'PARTIALLY_PAID' : 'UNPAID');
        DB::table('cbe_purchase_bills')->where('bill_id', $payment->bill_id)
            ->update(['paid_amount' => $newPaid, 'status' => $status, 'updated_at' => now()]);
    }

    // Call right after inserting a cbe_debit_notes row — partially
    // reverses a bill's expense/liability (e.g. returned goods).
    public static function postDebitNote(string $debitNoteId): void
    {
        $note = DB::table('cbe_debit_notes as dn')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'dn.cbe_node_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'b.category_id')
            ->where('dn.debit_note_id', $debitNoteId)
            ->select('dn.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $note) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($note->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($note->group_label_id);
        $expenseAccountId = $note->chart_account_id ?: self::uncategorisedExpenseAccountId($note->group_label_id);
        $apAccountId = self::apAccountId($note->group_label_id);

        $journalId = self::postJournal($note->cbe_node_id, $note->note_date, $note->reason ?: 'Credit note', 'DEBIT_NOTE', $debitNoteId, $note->recorded_by, [
            [$apAccountId, $note->amount, 0, $note->reason],
            [$expenseAccountId, 0, $note->amount, $note->reason],
        ]);
        // NEW 3 Sep 2026 (Task #377) — AP/GL posting status linkage. Note:
        // this table/method is displayed to users as "Credit Note" (see
        // lang file comment above tile_debit_notes) — Dr AP/Cr Expense is
        // the correct accounting direction for a credit note, which is
        // why the underlying names were left unchanged rather than risk a
        // disruptive rename.
        self::markGlPosted('cbe_debit_notes', 'debit_note_id', $debitNoteId, $journalId);

        if ($note->bill_id) {
            DB::table('cbe_purchase_bills')->where('bill_id', $note->bill_id)
                ->update(['amount' => DB::raw('amount - '.(float) $note->amount), 'updated_at' => now()]);
        }
    }

    // NEW 3 Sep 2026 (Task #372) — AP Debit Note: the genuine INCREASE
    // direction (supplier under-billed and later charges more, or any
    // correction that raises what we owe) — Dr Expense / Cr Accounts
    // Payable. Mirror image of postDebitNote() above (which is displayed
    // as "Credit Note" and decreases AP). See postArDebitNote() for the
    // AR-side equivalent this was modelled on.
    public static function postApDebitNote(string $debitNoteId): void
    {
        $note = DB::table('cbe_ap_debit_notes as dn')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'dn.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'dn.category_id')
            ->where('dn.debit_note_id', $debitNoteId)
            ->select('dn.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $note) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($note->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($note->group_label_id);
        $expenseAccountId = $note->chart_account_id ?: self::uncategorisedExpenseAccountId($note->group_label_id);
        $apAccountId = self::apAccountId($note->group_label_id);
        $memo = $note->reason ?: 'AP debit note';

        $journalId = self::postJournal($note->cbe_node_id, $note->note_date, $memo, 'AP_DEBIT_NOTE', $debitNoteId, $note->recorded_by, [
            [$expenseAccountId, $note->amount, 0, $memo],
            [$apAccountId, 0, $note->amount, $memo],
        ]);
        self::markGlPosted('cbe_ap_debit_notes', 'debit_note_id', $debitNoteId, $journalId);

        if ($note->bill_id) {
            DB::table('cbe_purchase_bills')->where('bill_id', $note->bill_id)
                ->update(['amount' => DB::raw('amount + '.(float) $note->amount), 'updated_at' => now()]);
        }
    }

    // NEW 3 Sep 2026 (Task #372) — AP Adjustment: generic AP write-off or
    // balance correction, mirroring postArAdjustment() exactly. INCREASE =
    // we owe more (Dr Expense / Cr AP); DECREASE = we owe less (Dr AP /
    // Cr Expense).
    public static function postApAdjustment(string $adjustmentId): void
    {
        $adj = DB::table('cbe_ap_adjustments as a')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'a.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'a.category_id')
            ->where('a.adjustment_id', $adjustmentId)
            ->select('a.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $adj) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($adj->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($adj->group_label_id);
        $expenseAccountId = $adj->chart_account_id ?: self::uncategorisedExpenseAccountId($adj->group_label_id);
        $apAccountId = self::apAccountId($adj->group_label_id);
        $memo = $adj->reason ?: 'AP adjustment';

        $lines = $adj->direction === 'INCREASE'
            ? [[$expenseAccountId, $adj->amount, 0, $memo], [$apAccountId, 0, $adj->amount, $memo]]
            : [[$apAccountId, $adj->amount, 0, $memo], [$expenseAccountId, 0, $adj->amount, $memo]];

        $journalId = self::postJournal($adj->cbe_node_id, $adj->adjustment_date, $memo, 'AP_ADJUSTMENT', $adjustmentId, $adj->recorded_by, $lines);
        self::markGlPosted('cbe_ap_adjustments', 'adjustment_id', $adjustmentId, $journalId);

        if ($adj->bill_id) {
            $op = $adj->direction === 'INCREASE' ? '+' : '-';
            DB::table('cbe_purchase_bills')->where('bill_id', $adj->bill_id)
                ->update(['amount' => DB::raw('amount '.$op.' '.(float) $adj->amount), 'updated_at' => now()]);
        }
    }

    // NEW 3 Sep 2026 (Task #372) — AP Refund: cash a supplier pays back to
    // us directly, mirroring postArRefund()'s mirror-image logic. Dr Bank
    // (cash comes in) / Cr Accounts Payable (reduces what we'd otherwise
    // still owe, or parks as a credit if the bill's already settled).
    public static function postApRefund(string $refundId): void
    {
        $refund = DB::table('cbe_ap_refunds as r')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'r.cbe_node_id')
            ->where('r.refund_id', $refundId)
            ->select('r.*', 'n.group_label_id')
            ->first();

        if (! $refund) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($refund->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($refund->group_label_id);
        $apAccountId = self::apAccountId($refund->group_label_id);
        $cashAccountId = self::bankAccountGlAccountId($refund->bank_account_id ?? null, $refund->cbe_node_id, $refund->group_label_id);
        $memo = $refund->reason ?: 'AP refund';

        $journalId = self::postJournal($refund->cbe_node_id, $refund->refund_date, $memo, 'AP_REFUND', $refundId, $refund->recorded_by, [
            [$cashAccountId, $refund->amount, 0, $memo],
            [$apAccountId, 0, $refund->amount, $memo],
        ]);
        self::markGlPosted('cbe_ap_refunds', 'refund_id', $refundId, $journalId);
    }

    // NEW 3 Sep 2026 (Task #372) — AP Opening Balance: what we already
    // owed a supplier on go-live day, mirroring postArOpeningBalance().
    // Dr Fund Balance / Cr Accounts Payable (opposite sides from the AR
    // version, since this increases a liability instead of an asset).
    public static function postApOpeningBalance(string $openingId): void
    {
        $ob = DB::table('cbe_ap_opening_balances as o')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'o.cbe_node_id')
            ->where('o.opening_id', $openingId)
            ->select('o.*', 'n.group_label_id')
            ->first();

        if (! $ob) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($ob->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($ob->group_label_id);
        $apAccountId = self::apAccountId($ob->group_label_id);
        $fundBalanceAccountId = self::fundBalanceAccountId($ob->group_label_id);
        $memo = $ob->notes ?: 'AP opening balance';

        $journalId = self::postJournal($ob->cbe_node_id, $ob->opening_date, $memo, 'AP_OPENING_BALANCE', $openingId, $ob->recorded_by, [
            [$fundBalanceAccountId, $ob->amount, 0, $memo],
            [$apAccountId, 0, $ob->amount, $memo],
        ]);
        self::markGlPosted('cbe_ap_opening_balances', 'opening_id', $openingId, $journalId);
    }

    // NEW 2 Sep 2026 (Task #354) — AR-side counterpart to postDebitNote()
    // above. A Debit Note INCREASES what the customer owes (an extra
    // charge after the original invoice) — Dr Accounts Receivable,
    // Cr the income account. If linked to an invoice, that invoice's
    // amount grows by the same figure so AR Aging/Debtor Ledger stay
    // in sync with the ledger.
    public static function postArDebitNote(string $debitNoteId): void
    {
        $note = DB::table('cbe_ar_debit_notes as dn')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'dn.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'dn.category_id')
            ->where('dn.debit_note_id', $debitNoteId)
            ->select('dn.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $note) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($note->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($note->group_label_id);
        $incomeAccountId = $note->chart_account_id ?: self::uncategorisedIncomeAccountId($note->group_label_id);
        $arAccountId = self::arAccountId($note->group_label_id);

        $journalId = self::postJournal($note->cbe_node_id, $note->note_date, $note->reason ?: 'AR debit note', 'AR_DEBIT_NOTE', $debitNoteId, $note->recorded_by, [
            [$arAccountId, $note->amount, 0, $note->reason],
            [$incomeAccountId, 0, $note->amount, $note->reason],
        ]);
        self::markGlPosted('cbe_ar_debit_notes', 'debit_note_id', $debitNoteId, $journalId);

        if ($note->invoice_id) {
            DB::table('cbe_invoices')->where('invoice_id', $note->invoice_id)
                ->update(['amount' => DB::raw('amount + '.(float) $note->amount), 'updated_at' => now()]);
        }
    }

    // NEW 2 Sep 2026 (Task #354) — a Credit Note REDUCES what the
    // customer owes (returned goods, discount, billing correction) —
    // Dr the income account, Cr Accounts Receivable, the mirror image of
    // the Debit Note above. If linked to an invoice, that invoice's
    // amount shrinks by the same figure, same pattern as postDebitNote().
    public static function postArCreditNote(string $creditNoteId): void
    {
        $note = DB::table('cbe_ar_credit_notes as cn')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'cn.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'cn.category_id')
            ->where('cn.credit_note_id', $creditNoteId)
            ->select('cn.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $note) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($note->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($note->group_label_id);
        $incomeAccountId = $note->chart_account_id ?: self::uncategorisedIncomeAccountId($note->group_label_id);
        $arAccountId = self::arAccountId($note->group_label_id);

        $journalId = self::postJournal($note->cbe_node_id, $note->note_date, $note->reason ?: 'AR credit note', 'AR_CREDIT_NOTE', $creditNoteId, $note->recorded_by, [
            [$incomeAccountId, $note->amount, 0, $note->reason],
            [$arAccountId, 0, $note->amount, $note->reason],
        ]);
        self::markGlPosted('cbe_ar_credit_notes', 'credit_note_id', $creditNoteId, $journalId);

        if ($note->invoice_id) {
            DB::table('cbe_invoices')->where('invoice_id', $note->invoice_id)
                ->update(['amount' => DB::raw('amount - '.(float) $note->amount), 'updated_at' => now()]);
        }
    }

    private static function fundBalanceAccountId(?string $groupLabelId): string
    {
        return self::getOrCreateAccount($groupLabelId, self::FUND_BALANCE_CODE, 'Fund Balance (Control)', '基金结余（统制账户）', 'EQUITY', true);
    }

    // NEW 2 Sep 2026 (Task #358) — AR Adjustment: a generic write-off or
    // balance correction, separate from Debit/Credit Note so it has its
    // own audit trail. INCREASE mirrors a debit note (Dr AR / Cr Income —
    // customer now owes more); DECREASE mirrors a credit note (Dr Income /
    // Cr AR — customer now owes less). If no category is chosen, the
    // adjustment posts against Uncategorised Income, same fallback as
    // every other AR document here.
    public static function postArAdjustment(string $adjustmentId): void
    {
        $adj = DB::table('cbe_ar_adjustments as a')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'a.cbe_node_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'a.category_id')
            ->where('a.adjustment_id', $adjustmentId)
            ->select('a.*', 'c.chart_account_id', 'n.group_label_id')
            ->first();

        if (! $adj) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($adj->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($adj->group_label_id);
        $incomeAccountId = $adj->chart_account_id ?: self::uncategorisedIncomeAccountId($adj->group_label_id);
        $arAccountId = self::arAccountId($adj->group_label_id);
        $memo = $adj->reason ?: 'AR adjustment';

        $lines = $adj->direction === 'INCREASE'
            ? [[$arAccountId, $adj->amount, 0, $memo], [$incomeAccountId, 0, $adj->amount, $memo]]
            : [[$incomeAccountId, $adj->amount, 0, $memo], [$arAccountId, 0, $adj->amount, $memo]];

        $journalId = self::postJournal($adj->cbe_node_id, $adj->adjustment_date, $memo, 'AR_ADJUSTMENT', $adjustmentId, $adj->recorded_by, $lines);
        self::markGlPosted('cbe_ar_adjustments', 'adjustment_id', $adjustmentId, $journalId);

        if ($adj->invoice_id) {
            $op = $adj->direction === 'INCREASE' ? '+' : '-';
            DB::table('cbe_invoices')->where('invoice_id', $adj->invoice_id)
                ->update(['amount' => DB::raw('amount '.$op.' '.(float) $adj->amount), 'updated_at' => now()]);
        }
    }

    // NEW 2 Sep 2026 (Task #358) — AR Refund: cash paid back to a
    // customer/donor, typically clearing an overpayment or credit-note
    // balance that left them with a negative (credit) AR position.
    // Dr Accounts Receivable (brings the customer's balance back toward
    // zero) / Cr Bank (cash leaves the temple). This is the mirror image
    // of postInvoicePayment(), which does the opposite (Dr Bank / Cr AR).
    public static function postArRefund(string $refundId): void
    {
        $refund = DB::table('cbe_ar_refunds as r')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'r.cbe_node_id')
            ->where('r.refund_id', $refundId)
            ->select('r.*', 'n.group_label_id')
            ->first();

        if (! $refund) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($refund->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($refund->group_label_id);
        $arAccountId = self::arAccountId($refund->group_label_id);
        $cashAccountId = self::bankAccountGlAccountId($refund->bank_account_id ?? null, $refund->cbe_node_id, $refund->group_label_id);
        $memo = $refund->reason ?: 'AR refund';

        $journalId = self::postJournal($refund->cbe_node_id, $refund->refund_date, $memo, 'AR_REFUND', $refundId, $refund->recorded_by, [
            [$arAccountId, $refund->amount, 0, $memo],
            [$cashAccountId, 0, $refund->amount, $memo],
        ]);
        self::markGlPosted('cbe_ar_refunds', 'refund_id', $refundId, $journalId);
    }

    // NEW 2 Sep 2026 (Task #358) — AR Opening Balance: what a customer
    // already owed on go-live day, entered once when the temple migrates
    // into the system. Dr Accounts Receivable / Cr Fund Balance — the
    // same "Fund Balance" equity account the GL-wide Opening Balances
    // screen (Task #339) already uses as its balancing entry, so opening
    // AR doesn't inflate current-year income the way an invoice would.
    public static function postArOpeningBalance(string $openingId): void
    {
        $ob = DB::table('cbe_ar_opening_balances as o')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'o.cbe_node_id')
            ->where('o.opening_id', $openingId)
            ->select('o.*', 'n.group_label_id')
            ->first();

        if (! $ob) {
            return;
        }

        // NEW 3 Sep 2026 (Task #386) — duplicate-posting guard: this
        // document already has a GL journal, so do nothing rather than
        // create a second journal entry for the same source document
        // (e.g. a double form submit).
        if ($ob->journal_id) {
            return;
        }

        self::ensureChartOfAccounts($ob->group_label_id);
        $arAccountId = self::arAccountId($ob->group_label_id);
        $fundBalanceAccountId = self::fundBalanceAccountId($ob->group_label_id);
        $memo = $ob->notes ?: 'AR opening balance';

        $journalId = self::postJournal($ob->cbe_node_id, $ob->opening_date, $memo, 'AR_OPENING_BALANCE', $openingId, $ob->recorded_by, [
            [$arAccountId, $ob->amount, 0, $memo],
            [$fundBalanceAccountId, 0, $ob->amount, $memo],
        ]);
        self::markGlPosted('cbe_ar_opening_balances', 'opening_id', $openingId, $journalId);
    }

    // NEW 25 Aug 2026 — per Chris: a State or HQ officer's Financial
    // Overview needs to see money rolled up across every Temple/Branch
    // underneath them, not just their own node's books (which for a
    // State/HQ node are usually empty — they don't record their own
    // day-to-day transactions). Same hierarchy_path prefix-match
    // pattern already used for descendant AGENTS (see
    // DashboardController::getCbeNodeDescendantAgentIds) — here applied
    // to journal entries instead.
    public static function descendantNodeIds(string $nodeId): array
    {
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        if (! $node || ! $node->hierarchy_path) {
            return [$nodeId];
        }

        return DB::table('cbe_hierarchy_nodes')
            ->where('hierarchy_path', 'like', $node->hierarchy_path.'%')
            ->pluck('node_id')->toArray();
    }

    // One consolidated set of headline numbers — Cash Balance, Total
    // Income, Total Expense, Net Surplus, and outstanding Accounts
    // Payable — rolled up across a node and everything beneath it, for
    // a given period. Powers the Finance dashboard's KPI tiles and the
    // Director dashboard's Financial Overview box; both read the exact
    // same numbers so they never disagree with each other.
    public static function financialSummary(string $nodeId, string $from, string $to): array
    {
        return self::financialSummaryForNodeIds(self::descendantNodeIds($nodeId), $from, $to);
    }

    // NEW 25 Aug 2026 — per Chris: GeneralLink's own platform Admin
    // (Director/Finance/Sales departments, NOT a CBE officer) needs a
    // separate CBE KPI screen that can look at ALL CBE communities
    // combined, or any ONE community picked from a dropdown (Tao,
    // Rotary Club, etc.) — not just "a node and its descendants" like
    // an officer's own dashboard. Extracted so both call paths share the
    // exact same aggregation logic and never disagree with each other.
    public static function financialSummaryForNodeIds(array $nodeIds, string $from, string $to): array
    {
        if (empty($nodeIds)) {
            return ['cash_balance' => 0, 'total_income' => 0, 'total_expense' => 0, 'net_surplus' => 0, 'ap_outstanding' => 0];
        }

        $cashBalanceRow = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->whereIn('j.cbe_node_id', $nodeIds)
            ->where('a.account_code', self::CASH_CODE)
            ->where('j.entry_date', '<=', $to)
            ->select(DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->first();
        $cashBalance = (float) ($cashBalanceRow->d ?? 0) - (float) ($cashBalanceRow->c ?? 0);

        $periodRow = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->whereIn('j.cbe_node_id', $nodeIds)
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('a.account_type', DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->groupBy('a.account_type')
            ->get()->keyBy('account_type');

        $totalIncome = isset($periodRow['INCOME']) ? (float) $periodRow['INCOME']->c - (float) $periodRow['INCOME']->d : 0;
        $totalExpense = isset($periodRow['EXPENSE']) ? (float) $periodRow['EXPENSE']->d - (float) $periodRow['EXPENSE']->c : 0;

        $apOutstanding = (float) DB::table('cbe_purchase_bills')
            ->whereIn('cbe_node_id', $nodeIds)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->selectRaw('SUM(amount - paid_amount) as v')->value('v') ?: 0;

        return [
            'cash_balance'  => $cashBalance,
            'total_income'  => $totalIncome,
            'total_expense' => $totalExpense,
            'net_surplus'   => $totalIncome - $totalExpense,
            'ap_outstanding'=> $apOutstanding,
        ];
    }

    // NEW 27 Aug 2026 — per Chris: Box 7 (Executive Analytics Dashboard)
    // needs Assets/Liabilities pulled from the same Balance Sheet logic
    // already built in CbeAccountingController::balanceSheet()/
    // accountBalances(), but that pair is private/single-node and built
    // for an Excel export, not a live multi-node dashboard figure. This
    // mirrors the same normal-balance math, aggregated across an array
    // of node ids (same convention as financialSummaryForNodeIds above),
    // evaluated "as of" a given date — a balance sheet is always a
    // point-in-time snapshot, never a period total.
    public static function balanceSheetSummaryForNodeIds(array $nodeIds, string $asOf): array
    {
        if (empty($nodeIds)) {
            return ['total_assets' => 0, 'total_liabilities' => 0];
        }

        $rows = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->whereIn('j.cbe_node_id', $nodeIds)
            ->where('j.entry_date', '<=', $asOf)
            ->whereIn('a.account_type', ['ASSET', 'LIABILITY'])
            ->select('a.account_type', DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->groupBy('a.account_type')
            ->get()->keyBy('account_type');

        $totalAssets = isset($rows['ASSET']) ? (float) $rows['ASSET']->d - (float) $rows['ASSET']->c : 0;
        $totalLiabilities = isset($rows['LIABILITY']) ? (float) $rows['LIABILITY']->c - (float) $rows['LIABILITY']->d : 0;

        return [
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
        ];
    }
}
