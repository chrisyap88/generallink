<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\BankTransactionImportService;
use App\Services\CbeAccountingService;
use App\Services\ClaudeDocumentExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." See ResolvesCbeActiveNode.
// NEW 22 Aug 2026 — per Chris: bank statement handling for a CBE node,
// scoped strictly to the agent's own cbe_node_id, same as
// MeetingMinutesController/ActivitiesController.
//
// Two separate things live here on purpose (see migration comments,
// 22 Aug 2026):
// 1. Bank statements — the scanned/uploaded proof document itself, filed
//    by month/year. Storage only, not machine-readable.
// 2. Transactions — the actual income/expense ledger lines a treasurer
//    types in (date, category, amount), optionally linked back to the
//    statement that backs them. THIS is what the Income & Expenditure
//    report totals up — see AnnualReportController.
// Categories (Donations, Utilities, Festival Costs, etc.) are
// admin-configurable per Chris's "fees/settings MUST NOT hardcode" rule
// — managed on the categories() screen, never a fixed PHP list.
class FinanceController extends Controller
{
    use ResolvesCbeActiveNode;

    // Tabbed hub: ?tab=statements (default) or ?tab=transactions
    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.finance.index', __('cbe_records.finance_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_transactions')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $tab = $request->get('tab', 'statements');

        $statements = collect();
        $transactions = collect();

        if ($nodeId && $tab === 'transactions') {
            $transactions = DB::table('cbe_transactions as t')
                ->join('cbe_transaction_categories as c', 'c.category_id', '=', 't.category_id')
                ->where('t.cbe_node_id', $nodeId)
                ->select('t.*', 'c.category_name', 'c.category_name_zh', 'c.type as category_type')
                ->orderByDesc('t.transaction_date')
                ->paginate(8, ['*'], 'txPage')
                ->appends(['tab' => 'transactions']);
        } elseif ($nodeId) {
            $statements = DB::table('cbe_bank_statements')
                ->where('cbe_node_id', $nodeId)
                ->orderByDesc('statement_year')->orderByDesc('statement_month')
                ->paginate(8, ['*'], 'stPage')
                ->appends(['tab' => 'statements']);
        }

        return view('cbe.finance.index', [
            'statements'   => $statements,
            'transactions' => $transactions,
            'tab'          => $tab,
            'hasNode'      => (bool) $nodeId,
        ]);
    }

    public function createStatement()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        // NEW 27 Aug 2026 (Task #222) — bank_account_id was added to
        // cbe_bank_statements (27 Aug 2026 migration) but nothing ever
        // let an officer create the account itself. Listed here so the
        // upload form can offer a real choice instead of an always-empty
        // dropdown; "+ Add Bank Account" on the same screen closes that
        // gap without a whole separate management screen for something
        // most communities set up once.
        $bankAccounts = DB::table('cbe_bank_accounts')
            ->where('cbe_node_id', $nodeId)
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get();

        return view('cbe.finance.create-statement', compact('bankAccounts'));
    }

    // NEW 27 Aug 2026 (Task #222) — quick inline add, reachable from the
    // Upload Statement screen. UPGRADED 1 Sep 2026 (Task #333) — this is
    // now also the store handler for the full Bank Accounts Master
    // screen, so it always creates a real GL link, not just a filing
    // reference. account_type/opening_balance/signatories are optional
    // so the quick-add modal (which only ever sent bank_name/
    // account_name/account_number) keeps working unchanged.
    public function storeBankAccount(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $request->validate([
            'bank_name'            => ['required', 'string', 'max:150'],
            'branch'               => ['nullable', 'string', 'max:150'],
            'account_name'         => ['nullable', 'string', 'max:150'],
            'account_number'       => ['required', 'string', 'max:60'],
            'account_type'         => ['nullable', 'in:CASH,BANK_CURRENT,BANK_SAVINGS,FIXED_DEPOSIT,PETTY_CASH'],
            'opening_balance'      => ['nullable', 'numeric'],
            'opening_balance_date' => ['nullable', 'date'],
            'signatories'          => ['nullable', 'string', 'max:255'],
            'description'          => ['nullable', 'string', 'max:255'],
            // NEW 16 Sep 2026 — per Chris: "Honestly all bank statement is
            // password protected in Malaysia." Saved here (encrypted,
            // never shown back in plain text) so the AI Accounting upload
            // screen can unlock this account's own statements
            // automatically — see AiAccountingController::storeBatch().
            'statement_password'   => ['nullable', 'string', 'max:100'],
        ]);

        $groupLabelId = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id');
        CbeAccountingService::ensureChartOfAccounts($groupLabelId);
        $displayName = $request->input('account_name') ?: $request->input('bank_name');
        $glAccountId = CbeAccountingService::createBankAccountGlLink($groupLabelId, $displayName, $nodeId);
        $accountCode = DB::table('cbe_chart_of_accounts')->where('account_id', $glAccountId)->value('account_code');

        $bankAccountId = (string) Str::uuid();
        DB::table('cbe_bank_accounts')->insert([
            'bank_account_id'      => $bankAccountId,
            'cbe_node_id'          => $nodeId,
            'account_code'         => $accountCode,
            'bank_name'            => $request->input('bank_name'),
            'branch'               => $request->input('branch'),
            'account_name'         => $request->input('account_name'),
            'account_number'       => $request->input('account_number'),
            'account_type'         => $request->input('account_type') ?: 'BANK_CURRENT',
            'gl_account_id'        => $glAccountId,
            'opening_balance'      => round((float) $request->input('opening_balance', 0), 2),
            'opening_balance_date' => $request->input('opening_balance_date') ?: null,
            'signatories'          => $request->input('signatories'),
            'description'          => $request->input('description'),
            'statement_password_encrypted' => $request->filled('statement_password') ? encrypt($request->input('statement_password')) : null,
            'is_active'            => true,
            'created_by'           => $agent->agent_id,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // Opening balance becomes a journal entry dated the opening
        // date (or today, if none given) so it shows up on the Trial
        // Balance/Balance Sheet immediately — Dr the new account, Cr
        // Fund Balance, exactly like any other equity-side opening entry.
        $openingBalanceWarning = null;
        $openingBalance = round((float) $request->input('opening_balance', 0), 2);
        if ($openingBalance != 0) {
            $fundBalanceAccountId = DB::table('cbe_chart_of_accounts')
                ->where('group_label_id', $groupLabelId)->where('account_code', CbeAccountingService::FUND_BALANCE_CODE)
                ->value('account_id');
            try {
                CbeAccountingService::postManualJournal(
                    $nodeId,
                    $request->input('opening_balance_date') ?: now()->toDateString(),
                    'Opening balance: '.$displayName,
                    $agent->agent_id,
                    [
                        [$glAccountId, $openingBalance, 0, 'Opening balance'],
                        [$fundBalanceAccountId, 0, $openingBalance, 'Opening balance'],
                    ]
                );
            } catch (\RuntimeException $e) {
                // Opening date falls in an already-closed period — the
                // account itself is still created; the treasurer can
                // post the opening balance via a Journal Voucher dated
                // in an open period instead.
                $openingBalanceWarning = __('cbe_records.opening_balance_period_closed');
            }
        }

        // Called via fetch() from the Upload Statement screen (so the
        // new account can drop straight into that page's dropdown
        // without a full reload) — JSON for that case; a plain redirect
        // fallback if this route is ever posted to normally.
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'OK',
                'bank_account_id' => $bankAccountId,
                'label' => $request->input('bank_name').($request->input('account_name') ? ' — '.$request->input('account_name') : '').' ('.$request->input('account_number').')',
            ]);
        }

        return redirect()->route('cbe.finance.bank-accounts')
            ->with('success', __('cbe_records.bank_account_saved'))
            ->with('warning', $openingBalanceWarning);
    }

    // NEW 27 Aug 2026 (Task #222) — per Chris's original "wire bank
    // statement upload to existing document-extraction AI service"
    // ask. AJAX-called from the Upload Statement screen right after a
    // file is chosen: reads the statement, returns a suggested closing
    // balance + period for the officer to REVIEW before saving — never
    // auto-posted blind, same review-then-confirm pattern already used
    // for insurance document reads (SalesTransactionController).
    public function extractStatement(Request $request, ClaudeDocumentExtractionService $extractor)
    {
        $agent = auth('agent')->user();
        if (! $this->resolveCbeNodeId($agent)) {
            return response()->json(['status' => 'ERROR', 'message' => 'No CBE node on this account.'], 403);
        }

        $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $file = $request->file('attachment');
        $mimeType = $file->getClientMimeType();

        $fields = [
            'closing_balance' => 'The closing balance / ending balance shown on this bank statement, as a plain number with no currency symbol or commas.',
            'statement_month' => 'The month this statement covers, as a number 1-12 (e.g. 8 for August). If a date range is shown, use the LATEST month covered.',
            'statement_year'  => 'The year this statement covers, as a 4-digit number (e.g. 2026). If a date range is shown, use the year of the latest date covered.',
            'bank_name'       => 'The name of the bank that issued this statement, exactly as printed.',
            'account_number'  => 'The bank account number shown on this statement, exactly as printed.',
        ];

        $result = $extractor->extract($file->getRealPath(), $mimeType, $fields);

        return response()->json($result);
    }

    public function storeStatement(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $request->validate([
            'statement_month' => ['required', 'integer', 'between:1,12'],
            'statement_year'  => ['required', 'integer', 'between:2000,2100'],
            'attachment'      => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'bank_account_id' => ['nullable', 'uuid'],
            'closing_balance' => ['nullable', 'numeric'],
        ]);

        $file = $request->file('attachment');

        DB::table('cbe_bank_statements')->insert([
            'statement_id'               => (string) Str::uuid(),
            'cbe_node_id'                 => $nodeId,
            'bank_account_id'             => $request->input('bank_account_id') ?: null,
            'statement_month'             => $request->input('statement_month'),
            'statement_year'              => $request->input('statement_year'),
            'closing_balance'             => $request->input('closing_balance') !== null && $request->input('closing_balance') !== '' ? (float) $request->input('closing_balance') : null,
            // true whenever a balance figure is actually being saved —
            // the officer either accepted/edited the AI suggestion or
            // typed the figure in by hand; false only means "no balance
            // captured on this upload at all" (still allowed, per the
            // original "storage only" design this extends, not replaces).
            'extraction_reviewed'         => $request->input('closing_balance') !== null && $request->input('closing_balance') !== '',
            'attachment_path'             => $file->store('cbe-bank-statements', 'local'),
            'attachment_original_name'    => $file->getClientOriginalName(),
            'uploaded_by'                 => $agent->agent_id,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        return redirect()->route('cbe.finance.index', ['tab' => 'statements'])->with('success', __('cbe_records.statement_saved'));
    }

    public function downloadStatement(string $statementId)
    {
        $agent = auth('agent')->user();
        $statement = DB::table('cbe_bank_statements')
            ->where('statement_id', $statementId)
            ->where('cbe_node_id', $this->resolveCbeNodeId($agent))
            ->firstOrFail();

        if (! Storage::disk('local')->exists($statement->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($statement->attachment_path));
    }

    public function createTransaction()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)
            ->orderBy('type')->orderBy('display_order')
            ->get();

        $statements = DB::table('cbe_bank_statements')
            ->where('cbe_node_id', $nodeId)
            ->orderByDesc('statement_year')->orderByDesc('statement_month')
            ->get();

        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.finance.create-transaction', compact('categories', 'statements', 'bankAccounts'));
    }

    public function storeTransaction(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $request->validate([
            'transaction_date'  => ['required', 'date'],
            'category_id'       => ['required', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'description'       => ['nullable', 'string', 'max:255'],
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'bank_statement_id' => ['nullable', 'uuid', 'exists:cbe_bank_statements,statement_id'],
            'bank_account_id'   => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
        ]);

        // NEW 1 Sep 2026 (Task #328) — Fiscal Period Lock: block a
        // backdated entry into a month the treasurer already closed.
        if (CbeAccountingService::isPeriodClosed($nodeId, $request->input('transaction_date'))) {
            return back()->withInput()->with('error', __('cbe_accounting.error_period_closed'));
        }

        $transactionId = (string) Str::uuid();
        DB::table('cbe_transactions')->insert([
            'transaction_id'     => $transactionId,
            'cbe_node_id'         => $nodeId,
            'bank_account_id'     => $request->input('bank_account_id') ?: null,
            'bank_statement_id'   => $request->input('bank_statement_id') ?: null,
            'category_id'         => $request->input('category_id'),
            'transaction_date'    => $request->input('transaction_date'),
            'description'         => $request->input('description'),
            'amount'              => $request->input('amount'),
            'entered_by'          => $agent->agent_id,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // NEW 25 Aug 2026 — silently mirrors this simple cash entry into
        // the real double-entry ledger (Cash vs the category's mapped
        // GL account) so General Ledger/Trial Balance/Balance Sheet stay
        // in sync automatically. The treasurer's own workflow above is
        // completely unchanged.
        CbeAccountingService::postTransaction($transactionId);

        return redirect()->route('cbe.finance.index', ['tab' => 'transactions'])->with('success', __('cbe_records.transaction_saved'));
    }

    // Admin-configurable categories — per Chris, never a hardcoded list.
    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Nothing is queried or shown until a search
    // term is entered and submitted (Add stays on this same page,
    // unchanged, since a form isn't a record dump).
    public function categories(Request $request)
    {
        $agent = auth('agent')->user();
        // NEW 4 Sep 2026 (Task #390) — resolved so the account dropdowns
        // below can include this node's own local add-on accounts, not
        // just the shared master list.
        $nodeId = $this->resolveCbeNodeId($agent);

        // NEW 3 Sep 2026 (Task #367) — GL Account Mapping. Every category
        // already gets an auto-assigned GL account the first time it's
        // used (see CbeAccountingService::ensureChartOfAccounts()) so
        // nothing was ever unposted — this just gives Chris a screen to
        // SEE and, if needed, CHANGE which account a category maps to
        // (e.g. merging two categories onto one account), instead of
        // that mapping being an invisible behind-the-scenes detail.
        CbeAccountingService::ensureChartOfAccounts($agent->group_label_id);

        $q = trim((string) $request->query('q', ''));
        $categories = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $categories = DB::table('cbe_transaction_categories as c')
                ->leftJoin('cbe_chart_of_accounts as a', 'a.account_id', '=', 'c.chart_account_id')
                ->where(function ($qr) use ($agent) {
                    $qr->whereNull('c.group_label_id')->orWhere('c.group_label_id', $agent->group_label_id);
                })
                ->where(function ($qr) use ($needle) {
                    $qr->where('c.category_name', 'like', $needle)->orWhere('c.category_name_zh', 'like', $needle);
                })
                ->orderBy('c.type')->orderBy('c.display_order')
                ->select('c.*', 'a.account_code', 'a.account_name')
                ->paginate(6, ['*'], 'catPage')->withQueryString();
        }

        $accountVisibility = fn ($q) => $q->where('group_label_id', $agent->group_label_id)
            ->when($nodeId, fn ($q2) => $q2->where(fn ($q3) => $q3->whereNull('cbe_node_id')->orWhere('cbe_node_id', $nodeId)), fn ($q2) => $q2->whereNull('cbe_node_id'));

        $incomeAccounts = $accountVisibility(DB::table('cbe_chart_of_accounts'))->where('account_type', 'INCOME')->where('is_active', true)
            ->orderBy('account_code')->get();
        $expenseAccounts = $accountVisibility(DB::table('cbe_chart_of_accounts'))->where('account_type', 'EXPENSE')->where('is_active', true)
            ->orderBy('account_code')->get();

        return view('cbe.finance.categories', compact('categories', 'incomeAccounts', 'expenseAccounts'));
    }

    // NEW 3 Sep 2026 (Task #367) — re-point a category at a different GL
    // account. Validated to be the same account_type as the category
    // (INCOME category can only map to an INCOME account, and so on) so
    // this can never silently break double-entry balance-sheet logic
    // elsewhere (trialBalance()/balanceSheet() both branch on
    // account_type).
    public function updateCategoryMapping(Request $request, string $categoryId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $category = DB::table('cbe_transaction_categories')->where('category_id', $categoryId)->firstOrFail();

        $request->validate(['chart_account_id' => ['required', 'uuid', 'exists:cbe_chart_of_accounts,account_id']]);

        $account = DB::table('cbe_chart_of_accounts')->where('account_id', $request->input('chart_account_id'))->first();
        // NEW 4 Sep 2026 (Task #390) — a category may now map to either a
        // shared master account or this node's own local add-on account.
        $accountVisible = $account && $account->group_label_id === $agent->group_label_id
            && ($account->cbe_node_id === null || $account->cbe_node_id === $nodeId);
        if (! $account || $account->account_type !== $category->type || ! $accountVisible) {
            return back()->with('error', __('cbe_records.category_mapping_invalid'));
        }

        DB::table('cbe_transaction_categories')->where('category_id', $categoryId)
            ->update(['chart_account_id' => $account->account_id, 'updated_at' => now()]);

        return back()->with('success', __('cbe_records.category_mapping_saved'));
    }

    public function storeCategory(Request $request)
    {
        $agent = auth('agent')->user();

        $request->validate([
            'category_name'    => ['required', 'string', 'max:150'],
            'category_name_zh' => ['nullable', 'string', 'max:150'],
            'type'              => ['required', 'in:INCOME,EXPENSE'],
        ]);

        DB::table('cbe_transaction_categories')->insert([
            'category_id'       => (string) Str::uuid(),
            'group_label_id'     => $agent->group_label_id,
            'category_name'      => $request->input('category_name'),
            'category_name_zh'   => $request->input('category_name_zh'),
            'type'               => $request->input('type'),
            'is_active'          => true,
            'display_order'      => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return redirect()->route('cbe.finance.categories')->with('success', __('cbe_records.category_saved'));
    }

    public function deactivateCategory(string $categoryId)
    {
        DB::table('cbe_transaction_categories')->where('category_id', $categoryId)->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_records.category_deactivated'));
    }

    // ---------- Bank Accounts Master (NEW 1 Sep 2026, Task #333) ----------

    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default, even a short one. Nothing is queried or
    // shown until a search term is entered and submitted.
    public function bankAccounts(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $q = trim((string) $request->query('q', ''));
        $accounts = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $accounts = DB::table('cbe_bank_accounts')
                ->where('cbe_node_id', $nodeId)
                ->where(function ($qr) use ($needle) {
                    $qr->where('bank_name', 'like', $needle)
                       ->orWhere('account_name', 'like', $needle)
                       ->orWhere('account_number', 'like', $needle)
                       ->orWhere('account_code', 'like', $needle);
                })
                ->orderBy('account_code')
                ->get()
                ->map(function ($a) {
                    $a->current_balance = CbeAccountingService::bankAccountBalanceAsOf($a->bank_account_id, now()->toDateString());
                    return $a;
                });
        }

        return view('cbe.finance.bank-accounts', compact('accounts', 'q'));
    }

    // ADDED 23 Sep 2026 -- per Chris ("ALL search must have type
    // ahead").
    public function bankAccountTypeahead(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $q = trim((string) $request->query('q', ''));
        if (! $nodeId || mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';
        $results = DB::table('cbe_bank_accounts')
            ->where('cbe_node_id', $nodeId)
            ->where(function ($qr) use ($needle) {
                $qr->where('bank_name', 'like', $needle)
                   ->orWhere('account_name', 'like', $needle)
                   ->orWhere('account_number', 'like', $needle)
                   ->orWhere('account_code', 'like', $needle);
            })
            ->orderBy('account_code')
            ->limit(15)
            ->get(['bank_account_id', 'bank_name', 'account_name', 'account_number']);

        return response()->json($results);
    }

    public function createBankAccount()
    {
        return view('cbe.finance.create-bank-account');
    }

    // NEW 3 Sep 2026 (Task #388) — Bank Reconciliation module gap-fix:
    // there was no way to fix a typo'd bank name/account name/number or
    // to retire an account without one. Only the descriptive fields are
    // editable — account_code and gl_account_id are locked because every
    // journal entry against this account (opening balance, transfers,
    // reconciliations) already points at that GL account; opening_
    // balance/opening_balance_date are locked for the same reason as the
    // Fixed Asset edit screen — they already drove a posted journal.
    public function editBankAccount(string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $account = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)->firstOrFail();

        // NEW 16 Sep 2026 — the saved password itself is NEVER decrypted
        // back into the edit form (same rule as never showing a saved
        // password anywhere) — the view only needs to know whether one
        // is already on file, to show "a password is saved" instead of
        // an empty field.
        $hasStatementPassword = ! empty($account->statement_password_encrypted);

        return view('cbe.finance.edit-bank-account', compact('account', 'hasStatementPassword'));
    }

    public function updateBankAccount(Request $request, string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'bank_name'      => ['required', 'string', 'max:150'],
            'branch'         => ['nullable', 'string', 'max:150'],
            'account_name'   => ['nullable', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:60'],
            'account_type'   => ['required', 'in:CASH,BANK_CURRENT,BANK_SAVINGS,FIXED_DEPOSIT,PETTY_CASH'],
            'signatories'    => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:255'],
            // NEW 16 Sep 2026 — see storeBankAccount(). Left blank on
            // this edit form, the existing saved password (if any) is
            // kept unchanged; the separate "Remove saved password"
            // checkbox is the only way to clear it, so a blank field
            // can never silently wipe it out by accident.
            'statement_password'        => ['nullable', 'string', 'max:100'],
            'clear_statement_password'  => ['nullable', 'boolean'],
        ]);

        $update = [
            'bank_name'      => $request->input('bank_name'),
            'branch'         => $request->input('branch'),
            'account_name'   => $request->input('account_name'),
            'account_number' => $request->input('account_number'),
            'account_type'   => $request->input('account_type'),
            'signatories'    => $request->input('signatories'),
            'description'    => $request->input('description'),
            'updated_at'     => now(),
        ];

        if ($request->filled('statement_password')) {
            $update['statement_password_encrypted'] = encrypt($request->input('statement_password'));
        } elseif ($request->boolean('clear_statement_password')) {
            $update['statement_password_encrypted'] = null;
        }

        DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->update($update);

        return redirect()->route('cbe.finance.bank-accounts')->with('success', __('cbe_records.bank_account_updated'));
    }

    public function deactivateBankAccount(string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_records.bank_account_deactivated'));
    }

    public function reactivateBankAccount(string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => true, 'updated_at' => now()]);

        return back()->with('success', __('cbe_records.bank_account_reactivated'));
    }

    // ---------- Bank Transaction Type master (NEW 4 Sep 2026, Task
    // #396) — Bank Reconciliation Module upgrade, spec section 1.3.
    // Admin-configurable, group_label_id-scoped, same pattern as
    // Journal Types / Asset Categories — never hardcoded business
    // data. ----------

    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Nothing is queried or shown until a search
    // term is entered and submitted (Add stays on this same page,
    // unchanged, since a form isn't a record dump).
    public function bankTransactionTypes(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id') : null;
        CbeAccountingService::ensureBankTransactionTypes($groupLabelId);

        $q = trim((string) $request->query('q', ''));
        $types = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $types = DB::table('cbe_bank_transaction_types as t')
                ->leftJoin('cbe_chart_of_accounts as a', 'a.account_id', '=', 't.default_gl_account_id')
                ->where(function ($qr) use ($groupLabelId) {
                    $qr->where('t.group_label_id', $groupLabelId);
                    if ($groupLabelId === null) {
                        $qr->orWhereNull('t.group_label_id');
                    }
                })
                ->where('t.type_name', 'like', $needle)
                ->select('t.*', 'a.account_name as default_gl_account_name')
                ->orderByDesc('t.is_active')->orderBy('t.display_order')->orderBy('t.type_name')
                ->paginate(10, ['*'], 'bttPage')->withQueryString();
        }

        $accounts = DB::table('cbe_chart_of_accounts')
            ->where(function ($q2) use ($groupLabelId) { $q2->whereNull('cbe_node_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.finance.bank-transaction-types', compact('types', 'accounts'));
    }

    // ADDED 23 Sep 2026 -- per Chris ("ALL search must have type
    // ahead").
    public function bankTransactionTypeTypeahead(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id') : null;
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';
        $results = DB::table('cbe_bank_transaction_types')
            ->where(function ($qr) use ($groupLabelId) {
                $qr->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $qr->orWhereNull('group_label_id');
                }
            })
            ->where('is_active', true)
            ->where('type_name', 'like', $needle)
            ->orderBy('type_name')
            ->limit(15)
            ->get(['type_id', 'type_name']);

        return response()->json($results);
    }

    public function storeBankTransactionType(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id') : null;

        $request->validate([
            'type_name' => ['required', 'string', 'max:60'],
            'type_name_zh' => ['nullable', 'string', 'max:60'],
            'default_gl_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
        ]);

        DB::table('cbe_bank_transaction_types')->insert([
            'type_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'type_name' => $request->input('type_name'),
            'type_name_zh' => $request->input('type_name_zh'),
            'default_gl_account_id' => $request->input('default_gl_account_id') ?: null,
            'is_system' => false,
            'is_active' => true,
            'display_order' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.finance.bank-transaction-types')->with('success', __('cbe_records.bank_transaction_type_saved'));
    }

    public function deactivateBankTransactionType(string $typeId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id') : null;

        DB::table('cbe_bank_transaction_types')->where('type_id', $typeId)
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_records.bank_transaction_type_deactivated'));
    }

    // ---------- Bank Transaction Entry (NEW 4 Sep 2026, Task #396) —
    // Bank Reconciliation Module upgrade, spec section 2. Raw bank-side
    // records for one bank account, decoupled from any reconciliation
    // session — manual entry + CSV/Excel import. See the migration
    // comment on cbe_bank_transactions for why this is a NEW table
    // rather than reusing cbe_transactions (that table belongs to the
    // separate, older secretary/treasurer bookkeeping module). ----------

    public function bankTransactions(Request $request, string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $account = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)->firstOrFail();

        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
        // upgrade, Phase 6: GL drill-down — bring back the journal this
        // transaction was matched to, if any, so the list can link
        // straight to it (see CbeAccountingService::journalIdForBankTransaction
        // for the same lookup used elsewhere).
        $query = DB::table('cbe_bank_transactions as t')
            ->leftJoin('cbe_bank_transaction_types as tt', 'tt.type_id', '=', 't.transaction_type_id')
            ->leftJoin('cbe_bank_reconciliation_matches as bm', function ($j) {
                $j->on('bm.bank_transaction_id', '=', 't.transaction_id')->where('bm.side', 'BANK');
            })
            ->leftJoin('cbe_bank_reconciliation_matches as sm', function ($j) {
                $j->on('sm.match_group_id', '=', 'bm.match_group_id')->where('sm.side', 'SYSTEM');
            })
            ->leftJoin('cbe_journal_lines as jl', 'jl.line_id', '=', 'sm.journal_line_id')
            ->where('t.bank_account_id', $bankAccountId)
            ->select('t.*', 'tt.type_name', 'jl.journal_id');

        if ($request->filled('date_from')) {
            $query->where('t.transaction_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('t.transaction_date', '<=', $request->input('date_to'));
        }

        $transactions = $query->orderByDesc('t.transaction_date')->orderByDesc('t.created_at')->paginate(10, ['*'], 'btPage');

        return view('cbe.finance.bank-transactions', compact('account', 'transactions'));
    }

    public function createBankTransactionForm(string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $account = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)->firstOrFail();
        $groupLabelId = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id');
        CbeAccountingService::ensureBankTransactionTypes($groupLabelId);
        $types = DB::table('cbe_bank_transaction_types')
            ->where(function ($q) use ($groupLabelId) { $q->where('group_label_id', $groupLabelId); if ($groupLabelId === null) { $q->orWhereNull('group_label_id'); } })
            ->where('is_active', true)->orderBy('display_order')->orderBy('type_name')->get();

        return view('cbe.finance.create-bank-transaction', compact('account', 'types'));
    }

    public function storeBankTransaction(Request $request, string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $account = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'transaction_date'    => ['required', 'date'],
            'description'         => ['required', 'string', 'max:255'],
            'transaction_type_id' => ['nullable', 'uuid'],
            'reference_no'        => ['nullable', 'string', 'max:100'],
            'cheque_no'           => ['nullable', 'string', 'max:50'],
            'direction'           => ['required', 'in:IN,OUT'],
            'amount'              => ['required', 'numeric', 'gt:0'],
        ]);

        $signedAmount = $request->input('direction') === 'IN' ? (float) $request->input('amount') : -(float) $request->input('amount');

        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
        // upgrade, Phase 6: Duplicate Posting Control. Only checked
        // when a Reference No. is given — date+amount alone is too weak
        // a key (two genuine donations of the same amount on the same
        // day are common and shouldn't be blocked), but the same
        // reference number on the same account/date/amount is a strong
        // signal this was already keyed in once. CSV/Excel import has
        // its own, separate duplicate check (also keyed on bank
        // reference) from Phase 2.
        if ($request->filled('reference_no')) {
            $duplicate = DB::table('cbe_bank_transactions')
                ->where('bank_account_id', $bankAccountId)
                ->where('transaction_date', $request->input('transaction_date'))
                ->where('amount', round($signedAmount, 2))
                ->where('reference_no', $request->input('reference_no'))
                ->exists();
            if ($duplicate) {
                return back()->withInput()->with('error', __('cbe_records.bank_transaction_duplicate_note'));
            }
        }

        DB::table('cbe_bank_transactions')->insert([
            'transaction_id'      => (string) Str::uuid(),
            'cbe_node_id'         => $nodeId,
            'bank_account_id'     => $bankAccountId,
            'transaction_date'    => $request->input('transaction_date'),
            'value_date'          => null,
            'transaction_type_id' => $request->input('transaction_type_id') ?: null,
            'description'         => $request->input('description'),
            'reference_no'        => $request->input('reference_no') ?: null,
            'cheque_no'           => $request->input('cheque_no') ?: null,
            'amount'              => round($signedAmount, 2),
            'bank_reference'      => null,
            'source'              => 'MANUAL',
            'status'              => 'UNRECONCILED',
            'created_by'          => $agent->agent_id,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return redirect()->route('cbe.finance.bank-transactions', $bankAccountId)->with('success', __('cbe_records.bank_transaction_entry_saved'));
    }

    // Only UNRECONCILED entries can be removed — once a line has taken
    // part in a reconciliation (Phase 3), deleting it would silently
    // break that reconciliation's history, same rule as everywhere else
    // in this app that guards against retroactively unbalancing a
    // posted/matched record.
    public function deleteBankTransaction(string $transactionId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $txn = DB::table('cbe_bank_transactions')->where('transaction_id', $transactionId)->where('cbe_node_id', $nodeId)->first();
        if (! $txn) {
            return back();
        }
        if ($txn->status !== 'UNRECONCILED') {
            return back()->with('warning', __('cbe_records.bank_transaction_locked_note'));
        }

        DB::table('cbe_bank_transactions')->where('transaction_id', $transactionId)->delete();

        return redirect()->route('cbe.finance.bank-transactions', $txn->bank_account_id)->with('success', __('cbe_records.bank_transaction_deleted'));
    }

    public function bankTransactionImportForm(string $bankAccountId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $account = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)->firstOrFail();

        return view('cbe.finance.bank-transaction-import', compact('account'));
    }

    public function downloadBankTransactionImportTemplate()
    {
        $csv = "date,description,reference,cheque_no,debit,credit,bank_reference\n";
        $csv .= "01/09/2026,Sample deposit,,,,500.00,\n";
        $csv .= "02/09/2026,Sample bank charge,,,12.00,,\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bank_transaction_import_template.csv"',
        ]);
    }

    public function storeBankTransactionImport(Request $request, string $bankAccountId, BankTransactionImportService $importer)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $file = $request->file('import_file');
        try {
            $batchId = $importer->parseAndStage($file->getRealPath(), $file->getClientOriginalName(), $nodeId, $bankAccountId, $agent->agent_id);
        } catch (\Throwable $e) {
            return back()->withErrors(['import_file' => $e->getMessage()]);
        }

        return redirect()->route('cbe.finance.bank-transaction-import.preview', $batchId);
    }

    public function bankTransactionImportPreview(Request $request, string $batchId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $batch = DB::table('cbe_bank_transaction_import_batches')->where('batch_id', $batchId)->where('cbe_node_id', $nodeId)->firstOrFail();
        $account = DB::table('cbe_bank_accounts')->where('bank_account_id', $batch->bank_account_id)->first();

        $groupLabelId = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id');
        $types = DB::table('cbe_bank_transaction_types')
            ->where(function ($q) use ($groupLabelId) { $q->where('group_label_id', $groupLabelId); if ($groupLabelId === null) { $q->orWhereNull('group_label_id'); } })
            ->where('is_active', true)->orderBy('display_order')->orderBy('type_name')->get();

        $filter = $request->input('filter', 'ALL'); // ALL / VALID / INVALID / DUPLICATE
        $rowsQuery = DB::table('cbe_bank_transaction_import_rows')->where('batch_id', $batchId);
        if (in_array($filter, ['VALID', 'INVALID', 'DUPLICATE'], true)) {
            $rowsQuery->where('status', $filter);
        }
        $rows = $rowsQuery->orderBy('row_number')->paginate(10, ['*'], 'birPage');

        return view('cbe.finance.bank-transaction-import-preview', compact('batch', 'account', 'types', 'rows', 'filter'));
    }

    public function commitBankTransactionImport(Request $request, string $batchId, BankTransactionImportService $importer)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $batch = DB::table('cbe_bank_transaction_import_batches')->where('batch_id', $batchId)->where('cbe_node_id', $nodeId)->firstOrFail();

        if ($batch->status !== 'PREVIEW') {
            return redirect()->route('cbe.finance.bank-transactions', $batch->bank_account_id);
        }

        $request->validate(['transaction_type_id' => ['nullable', 'uuid']]);

        $committed = $importer->commit($batchId, $batch->bank_account_id, $nodeId, $request->input('transaction_type_id') ?: null, $agent->agent_id);

        return redirect()->route('cbe.finance.bank-transactions', $batch->bank_account_id)
            ->with('success', __('cbe_records.bank_transaction_import_committed', ['count' => $committed]));
    }

    public function cancelBankTransactionImport(string $batchId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $batch = DB::table('cbe_bank_transaction_import_batches')->where('batch_id', $batchId)->where('cbe_node_id', $nodeId)->firstOrFail();

        if ($batch->status === 'PREVIEW') {
            DB::table('cbe_bank_transaction_import_rows')->where('batch_id', $batchId)->delete();
            DB::table('cbe_bank_transaction_import_batches')->where('batch_id', $batchId)->update(['status' => 'CANCELLED', 'updated_at' => now()]);
        }

        return redirect()->route('cbe.finance.bank-transactions', $batch->bank_account_id);
    }

    // ---------- Bank Transfer (NEW 1 Sep 2026, Task #333) ----------

    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Nothing is queried or shown until a search
    // criterion is entered and submitted.
    public function transfers(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $fromDate = $request->query('from_date', '');
        $toDate = $request->query('to_date', '');
        $status = $request->query('status', '');
        // ADDED 23 Sep 2026 -- per Chris ("ALL search must have type
        // ahead"): a free-text Account filter (matches either side of
        // the transfer) with a live-suggestion box, reusing the same
        // bank-account lookup the Bank Accounts screen uses.
        $accountQ = trim((string) $request->query('account_q', ''));
        $transfers = null;
        if ($fromDate !== '' || $toDate !== '' || $status !== '' || $accountQ !== '') {
            $accountNeedle = '%'.$accountQ.'%';
            $transfers = DB::table('cbe_bank_transfers as tr')
                ->join('cbe_bank_accounts as fa', 'fa.bank_account_id', '=', 'tr.from_account_id')
                ->join('cbe_bank_accounts as ta', 'ta.bank_account_id', '=', 'tr.to_account_id')
                ->where('tr.cbe_node_id', $nodeId)
                ->when($fromDate !== '', fn ($q) => $q->whereDate('tr.transfer_date', '>=', $fromDate))
                ->when($toDate !== '', fn ($q) => $q->whereDate('tr.transfer_date', '<=', $toDate))
                ->when($status !== '', fn ($q) => $q->where('tr.status', $status))
                ->when($accountQ !== '', fn ($q) => $q->where(function ($qr) use ($accountNeedle) {
                    $qr->where('fa.bank_name', 'like', $accountNeedle)->orWhere('fa.account_name', 'like', $accountNeedle)
                       ->orWhere('ta.bank_name', 'like', $accountNeedle)->orWhere('ta.account_name', 'like', $accountNeedle);
                }))
                ->select('tr.*', 'fa.account_name as from_name', 'fa.bank_name as from_bank', 'ta.account_name as to_name', 'ta.bank_name as to_bank')
                ->orderByDesc('tr.transfer_date')
                ->paginate(8, ['*'], 'trPage')->withQueryString();
        }

        return view('cbe.finance.transfers', compact('transfers', 'fromDate', 'toDate', 'status', 'accountQ'));
    }

    public function createTransfer()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $accounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.finance.create-transfer', compact('accounts'));
    }

    public function storeTransfer(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $request->validate([
            'from_account_id' => ['required', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'to_account_id'   => ['required', 'uuid', 'different:from_account_id', 'exists:cbe_bank_accounts,bank_account_id'],
            'transfer_date'   => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'reference_no'    => ['nullable', 'string', 'max:60'],
            'purpose'         => ['nullable', 'string', 'max:255'],
        ]);

        if (CbeAccountingService::isPeriodClosed($nodeId, $request->input('transfer_date'))) {
            return back()->withInput()->with('error', __('cbe_accounting.error_period_closed'));
        }

        // NEW 2 Sep 2026 (Task #334) — Maker-Checker. Same gate as Bill
        // Payments/Journal Vouchers — above threshold, this waits in
        // Pending Approvals instead of moving cash immediately.
        $amount = round((float) $request->input('amount'), 2);
        $needsApproval = CbeAccountingService::requiresApproval($nodeId, $amount);

        $transferId = (string) Str::uuid();
        DB::table('cbe_bank_transfers')->insert([
            'transfer_id'     => $transferId,
            'cbe_node_id'     => $nodeId,
            'from_account_id' => $request->input('from_account_id'),
            'to_account_id'   => $request->input('to_account_id'),
            'transfer_date'   => $request->input('transfer_date'),
            'amount'          => $amount,
            'reference_no'    => $request->input('reference_no'),
            'purpose'         => $request->input('purpose'),
            'prepared_by'     => $agent->agent_id,
            'status'          => $needsApproval ? 'PENDING' : 'APPROVED',
            'approved_by'     => $needsApproval ? null : $agent->agent_id,
            'approved_at'     => $needsApproval ? null : now(),
            'created_at'      => now(), 'updated_at' => now(),
        ]);

        if ($needsApproval) {
            return redirect()->route('cbe.finance.transfers')->with('success', __('cbe_records.transfer_submitted_for_approval'));
        }

        CbeAccountingService::postBankTransfer($transferId);

        return redirect()->route('cbe.finance.transfers')->with('success', __('cbe_records.transfer_saved'));
    }

    // ---------- Cash & Bank Position (NEW 1 Sep 2026, Task #333) ----------

    public function cashPosition()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.finance.index');
        }

        $asOf = now()->toDateString();
        $accounts = DB::table('cbe_bank_accounts')
            ->where('cbe_node_id', $nodeId)->where('is_active', true)
            ->orderBy('account_code')
            ->get()
            ->map(function ($a) use ($asOf) {
                $a->current_balance = CbeAccountingService::bankAccountBalanceAsOf($a->bank_account_id, $asOf);
                return $a;
            });
        $total = $accounts->sum('current_balance');

        return view('cbe.finance.cash-position', compact('accounts', 'total', 'asOf'));
    }
}
