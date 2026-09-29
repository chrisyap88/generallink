<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeAccountingService;
use App\Services\CoaChatAssistantService;
use App\Services\TransactionClassificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// NEW 25 Aug 2026 — per Chris: native Chart of Accounts / Accounts
// Payable / General Ledger / Trial Balance / Balance Sheet / Profit &
// Loss, built inside GeneralLink instead of integrating a 3rd-party
// accounting system (see CbeAccountingService header comment for why).
// Every screen here is scoped to the logged-in agent's own cbe_node_id,
// same as the rest of the Finance module — the underlying Chart of
// Accounts is shared per CBE community (group_label_id), but each
// temple/branch's journal entries are its own books.
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." Every method here already
// funnels through nodeAndGroup() below, so ResolvesCbeActiveNode only
// needed to be wired into that ONE place for the whole controller to
// become Admin-capable.
class CbeAccountingController extends Controller
{
    use ResolvesCbeActiveNode;

    private function nodeAndGroup(): array
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id') : null;
        return [$agent, $nodeId, $groupLabelId];
    }

    // NEW 4 Sep 2026 (Task #390) — whether the active node is the
    // community's own HQ/root (parent_node_id IS NULL). Only the root
    // node may create or edit accounts on the SHARED master Chart of
    // Accounts; every other node (State/Branch/Temple) may only create
    // or edit its own local "add-on" accounts (cbe_node_id = itself).
    // Kept separate from nodeAndGroup() rather than folded into it,
    // since most of the ~80 other call sites in this controller never
    // need it and shouldn't pay for the extra query.
    private function isRootNode(?string $nodeId): bool
    {
        if (! $nodeId) return false;
        return DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->whereNull('parent_node_id')->exists();
    }

    // Accounts visible to $nodeId within $groupLabelId: the shared
    // master (cbe_node_id IS NULL) plus this node's own local add-ons.
    private function visibleAccountsQuery(string $groupLabelId, string $nodeId)
    {
        // FIXED 23 Sep 2026 -- per Chris: "click search nothing happen" /
        // 500 error. Root cause: these two columns used to be unqualified,
        // which worked fine everywhere this was called ALONE, but
        // chartOfAccountsSearchForm() joins in cbe_account_groups and
        // cbe_account_categories -- both of which ALSO have their own
        // group_label_id column -- so MySQL could no longer tell which
        // table's group_label_id was meant ("Column 'group_label_id' in
        // where clause is ambiguous"). Qualifying both columns to
        // cbe_chart_of_accounts explicitly fixes it for every caller of
        // this shared method, not just the one that exposed it.
        return DB::table('cbe_chart_of_accounts')
            ->where('cbe_chart_of_accounts.group_label_id', $groupLabelId)
            ->where(function ($q) use ($nodeId) {
                $q->whereNull('cbe_chart_of_accounts.cbe_node_id')->orWhere('cbe_chart_of_accounts.cbe_node_id', $nodeId);
            });
    }

    // Accounts $nodeId is allowed to EDIT/deactivate/reactivate: the root
    // node may only touch the shared master (cbe_node_id IS NULL); every
    // other node may only touch its own local add-ons (cbe_node_id =
    // itself). Neither can reach into someone else's list — a Temple
    // can't edit the shared master, and HQ can't edit one Temple's local
    // account on its behalf.
    private function editableAccountsQuery(string $groupLabelId, string $nodeId)
    {
        $isRoot = $this->isRootNode($nodeId);
        return DB::table('cbe_chart_of_accounts')
            ->where('group_label_id', $groupLabelId)
            ->when($isRoot, fn ($q) => $q->whereNull('cbe_node_id'), fn ($q) => $q->where('cbe_node_id', $nodeId));
    }

    // NEW 1 Sep 2026 (Task #328) — Fiscal Period Lock. Call at the top
    // of every store*() method that eventually posts a journal entry,
    // BEFORE inserting the bill/invoice/asset/JV row, so a closed period
    // never leaves a half-saved record behind. Returns null if the
    // period is open (caller proceeds normally), or a redirect if closed.
    private function guardPeriodOpen(string $nodeId, string $date)
    {
        if (CbeAccountingService::isPeriodClosed($nodeId, $date)) {
            return back()->withInput()->with('error', __('cbe_accounting.error_period_closed'));
        }
        return null;
    }

    // REMOVED 30 Aug 2026 (Task #322) — ensureNodeBankAccount() used to
    // create per-temple sub-accounts inside the centrex package's
    // separate Chart of Accounts. No longer needed: AR/Fixed Assets/
    // Bank Reconciliation now all post into the SAME native ledger as
    // Accounts Payable, which was already scoped per cbe_node_id from
    // day one.

    public function index()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();

        // UPDATED 10 Sep 2026 (Task #397 follow-up) — per Chris: "HQ,
        // State, Branch, Temple... all have its own bank account
        // number." Accounting used to force Admin all the way down to a
        // leaf Temple (leafOnly: true) before it would show the module
        // menu, which made it impossible to open books kept at the HQ,
        // State, or Branch level. leafOnly: false lists every level in
        // the same type-to-search picker, each row tagged with its
        // level (HQ/State/Branch/Temple) so Admin can stop at whichever
        // entity actually holds this set of books.
        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.accounting.index', __('cbe.sb_accounting'), leafOnly: false);
        }

        if ($nodeId) {
            CbeAccountingService::ensureChartOfAccounts($groupLabelId);
        }
        return view('cbe.accounting.index', ['hasNode' => (bool) $nodeId]);
    }

    // NEW 4 Sep 2026 (Task #394) — dedicated Purchasing Management hub,
    // sitting between AR and AP on the main Accounting Hub (one tile
    // there links here) rather than piling every purchasing tile onto
    // the already-dense main hub. Covers the procurement chain: Supplier
    // Master (shared with AP) -> Requisition -> Quotation -> Order.
    public function purchasingHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.purchasing-hub', ['hasNode' => (bool) $nodeId]);
    }

    // NEW 10 Sep 2026 (Task #398) — 6 new sub-hubs (one per module) plus a
    // Master Files hub, splitting the old single ~40-tile Accounting Hub.
    // Each mirrors purchasingHub() exactly: no new data, just a menu.
    public function arHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.ar-hub', ['hasNode' => (bool) $nodeId]);
    }

    public function apHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.ap-hub', ['hasNode' => (bool) $nodeId]);
    }

    public function glHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.gl-hub', ['hasNode' => (bool) $nodeId]);
    }

    public function faHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.fa-hub', ['hasNode' => (bool) $nodeId]);
    }

    public function bankReconHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.bank-recon-hub', ['hasNode' => (bool) $nodeId]);
    }

    public function masterFilesHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.master-files-hub', ['hasNode' => (bool) $nodeId]);
    }

    // NEW 4 Sep 2026 (Task #394 Phase 2) — Purchasing Reports Hub: groups
    // the downloadable Excel reports plus the on-screen Budget Utilisation
    // dashboard, mirroring the AP/AR/GL Reports Hub pattern so Purchasing
    // follows the same navigation shape as every other module.
    public function purchasingReportsHub()
    {
        return view('cbe.accounting.purchasing-reports-hub');
    }

    // NEW 4 Sep 2026 (Task #394 gap-fix) — Purchasing Audit Trail (spec
    // section 26). Read-only browse screen over the append-only
    // cbe_purchasing_audit_log table, optionally filtered by document
    // type, each row linking back to its source document's own show
    // screen (same $docRoutes mapping used by Supplier Procurement
    // History, Task #394 gap-fix Task #30).
    public function purchasingAuditLog(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $docType = $request->input('doc_type');

        $query = DB::table('cbe_purchasing_audit_log as l')
            ->join('agents as a', 'a.agent_id', '=', 'l.actor_id')
            ->where('l.cbe_node_id', $nodeId);

        if ($docType) {
            $query->where('l.doc_type', $docType);
        }

        $logs = $query->select('l.*', 'a.full_name as actor_name')
            ->orderByDesc('l.created_at')
            ->paginate(10, ['*'], 'auditPage')
            ->withQueryString();

        return view('cbe.accounting.purchasing-audit-log', compact('logs', 'docType'));
    }

    // ---------- Chart of Accounts ----------

    public function chartOfAccounts()
    {
        // REBUILT 19 Sep 2026 -- per Chris: "add and search separate
        // action... first screen is to show add / search edit". This
        // route is now just the hub (2 buttons), same pattern as Reason
        // Code / Faith Practice Type. The old combined add-form + search
        // + full-tree-listing logic moved to createAccount() (the Add
        // screen) and chartOfAccountsSearchForm()/chartOfAccountsTypeahead()
        // (the Search/Edit screen, type-ahead only, no listed table --
        // picking a match goes straight to editAccount()).
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.chart-of-accounts', __('cbe_accounting.tile_chart_of_accounts'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        return view('cbe.accounting.chart-of-accounts');
    }

    // NEW 19 Sep 2026 -- the Add screen (was the top of the old combined
    // chart-of-accounts.blade.php page). Category / Parent Account are
    // now type-ahead search boxes instead of long <select> dropdowns --
    // per Chris: "all the field like category and gl code is a type
    // ahead search that i can select".
    public function createAccount()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.chart-of-accounts.create', __('cbe_accounting.create_account_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        CbeAccountingService::ensureChartOfAccounts($groupLabelId);

        $groups = DB::table('cbe_account_groups')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('account_type')->orderBy('display_order')->get();
        $isRootNode = $this->isRootNode($nodeId);

        return view('cbe.accounting.create-account', compact('groups', 'isRootNode'));
    }

    // NEW 19 Sep 2026 -- the Search/Edit screen: a single type-ahead
    // search box (code or name), same UX as Reason Code's search-form.
    // No results table here -- picking a match navigates straight to
    // editAccount(), which is where Edit/Deactivate actually happen.
    public function chartOfAccountsSearchForm(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.chart-of-accounts.search', __('cbe_accounting.coa_search_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        // REBUILT 23 Sep 2026 per Chris: "search results is open a new
        // screen display on top" -- this used to be a single AJAX call
        // rendered by client-side JS (the only screen in the app built
        // that way). Rebuilt to match every other list screen: a real
        // GET submit that reloads this page with the results server-
        // rendered at the top, Prev/Next paginated. The Description box
        // was dropped per Chris ("just remove the description as search
        // field, it is redundant") -- Type/Category/Group/Code/Name/
        // Name (Chinese) remain.
        $type = trim((string) $request->query('type', ''));
        $categoryId = trim((string) $request->query('category_id', ''));
        $categoryText = trim((string) $request->query('category_text', ''));
        $groupId = trim((string) $request->query('group_id', ''));
        $groupText = trim((string) $request->query('group_text', ''));
        $code = trim((string) $request->query('code', ''));
        $name = trim((string) $request->query('name', ''));
        $nameZh = trim((string) $request->query('name_zh', ''));

        $hasQuery = $type !== '' || $categoryId !== '' || $categoryText !== '' || $groupId !== '' || $groupText !== ''
            || $code !== '' || $name !== '' || $nameZh !== '';

        $results = null;
        if ($hasQuery) {
            $query = $this->visibleAccountsQuery($groupLabelId, $nodeId)
                ->leftJoin('cbe_account_groups', 'cbe_chart_of_accounts.account_group_id', '=', 'cbe_account_groups.group_id')
                ->leftJoin('cbe_account_categories', 'cbe_chart_of_accounts.account_category_id', '=', 'cbe_account_categories.category_id')
                ->when($type !== '', fn ($q) => $q->where('cbe_chart_of_accounts.account_type', $type))
                ->when($categoryId !== '', fn ($q) => $q->where('cbe_chart_of_accounts.account_category_id', $categoryId))
                ->when($categoryId === '' && $categoryText !== '', fn ($q) => $q->where(function ($qr) use ($categoryText) {
                    $qr->where('cbe_account_categories.category_name', 'like', "%{$categoryText}%")
                       ->orWhere('cbe_account_categories.category_name_zh', 'like', "%{$categoryText}%");
                }))
                ->when($groupId !== '', fn ($q) => $q->where('cbe_chart_of_accounts.account_group_id', $groupId))
                ->when($groupId === '' && $groupText !== '', fn ($q) => $q->where(function ($qr) use ($groupText) {
                    $qr->where('cbe_account_groups.group_name', 'like', "%{$groupText}%")
                       ->orWhere('cbe_account_groups.group_name_zh', 'like', "%{$groupText}%");
                }))
                ->when($code !== '', fn ($q) => $q->where('cbe_chart_of_accounts.account_code', 'like', "%{$code}%"))
                ->when($name !== '', fn ($q) => $q->where('cbe_chart_of_accounts.account_name', 'like', "%{$name}%"))
                ->when($nameZh !== '', fn ($q) => $q->where('cbe_chart_of_accounts.account_name_zh', 'like', "%{$nameZh}%"));

            $results = $query->orderBy('cbe_chart_of_accounts.account_code')
                ->select([
                    'cbe_chart_of_accounts.account_id', 'cbe_chart_of_accounts.account_code',
                    'cbe_chart_of_accounts.account_name', 'cbe_chart_of_accounts.account_name_zh',
                    'cbe_chart_of_accounts.account_type', 'cbe_chart_of_accounts.description',
                    'cbe_account_groups.group_name', 'cbe_account_categories.category_name',
                ])
                ->paginate(8)->withQueryString();
        }

        $coaTypeLabels = [
            'ASSET' => __('cbe_accounting.type_asset'),
            'LIABILITY' => __('cbe_accounting.type_liability'),
            'EQUITY' => __('cbe_accounting.type_equity'),
            'INCOME' => __('cbe_accounting.type_income'),
            'EXPENSE' => __('cbe_accounting.type_expense'),
        ];

        return view('cbe.accounting.chart-of-accounts-search', compact(
            'results', 'hasQuery', 'coaTypeLabels',
            'type', 'categoryId', 'categoryText', 'groupId', 'groupText', 'code', 'name', 'nameZh'
        ));
        // (categoryId/groupId ARE included above -- kept in the compact()
        // call so the hidden type-ahead id fields survive the page
        // reload after Search, same as categoryText/groupText do.)
    }

    // AJAX endpoint behind the Search/Edit screen's type-ahead box.
    public function chartOfAccountsTypeahead(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $q = trim((string) $request->query('q', ''));
        if (! $nodeId || mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';
        // WIDENED 23 Sep 2026 -- per Chris ("i say ALL search must have
        // type ahead"): also matches the free-text description column,
        // not just code/name/name_zh, so every field on the
        // Search/Edit form can share this one suggestion endpoint.
        $results = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where(function ($qr) use ($needle) {
                $qr->where('account_code', 'like', $needle)
                   ->orWhere('account_name', 'like', $needle)
                   ->orWhere('account_name_zh', 'like', $needle)
                   ->orWhere('description', 'like', $needle);
            })
            ->orderBy('account_code')
            ->limit(15)
            ->get(['account_id', 'account_code', 'account_name', 'account_name_zh', 'description']);

        return response()->json($results);
    }

    // AJAX endpoint behind the Add/Edit form's Account Category
    // type-ahead box.
    public function accountCategoryTypeahead(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $q = trim((string) $request->query('q', ''));
        if (! $groupLabelId || mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';
        $results = DB::table('cbe_account_categories')
            ->where(function ($qr) use ($groupLabelId) { $qr->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)
            ->where(function ($qr) use ($needle) {
                $qr->where('category_name', 'like', $needle)->orWhere('category_name_zh', 'like', $needle);
            })
            ->orderBy('display_order')
            ->limit(15)
            ->get(['category_id', 'category_name', 'category_name_zh']);

        return response()->json($results);
    }

    // AJAX endpoint behind the Add/Edit form's Parent Account type-ahead
    // box. $exclude (edit only) keeps an account from being offered as
    // its own parent. Same header-account-only rule, with the same
    // fallback-to-top-level-only behaviour as the old $parentChoices
    // logic, as a second query only if the first comes back empty.
    public function parentAccountTypeahead(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $q = trim((string) $request->query('q', ''));
        if (! $nodeId || mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $exclude = $request->query('exclude');
        $needle = '%'.$q.'%';

        $results = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_posting_account', false)
            ->when($exclude, fn ($qr) => $qr->where('account_id', '!=', $exclude))
            ->where(function ($qr) use ($needle) {
                $qr->where('account_code', 'like', $needle)->orWhere('account_name', 'like', $needle);
            })
            ->orderBy('account_code')->limit(15)->get(['account_id', 'account_code', 'account_name']);

        if ($results->isEmpty()) {
            $results = $this->visibleAccountsQuery($groupLabelId, $nodeId)
                ->whereNull('parent_account_id')
                ->when($exclude, fn ($qr) => $qr->where('account_id', '!=', $exclude))
                ->where(function ($qr) use ($needle) {
                    $qr->where('account_code', 'like', $needle)->orWhere('account_name', 'like', $needle);
                })
                ->orderBy('account_code')->limit(15)->get(['account_id', 'account_code', 'account_name']);
        }

        return response()->json($results);
    }

    // NEW 19 Sep 2026 -- AJAX endpoint behind the advanced Search
    // screen's Account Group type-ahead box. Optional ?type= narrows
    // suggestions to groups under that Account Type once the user has
    // picked one, same relationship as cbe_account_groups.account_type.
    public function accountGroupTypeahead(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $q = trim((string) $request->query('q', ''));
        if (! $groupLabelId || mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $type = $request->query('type');
        $needle = '%'.$q.'%';
        $results = DB::table('cbe_account_groups')
            ->where(function ($qr) use ($groupLabelId) { $qr->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)
            ->when($type, fn ($qr) => $qr->where('account_type', $type))
            ->where(function ($qr) use ($needle) {
                $qr->where('group_name', 'like', $needle)->orWhere('group_name_zh', 'like', $needle);
            })
            ->orderBy('display_order')
            ->limit(15)
            ->get(['group_id', 'group_name', 'group_name_zh']);

        return response()->json($results);
    }


    // NEW 19 Sep 2026 -- per Chris: "in total how many coa?" -- a small,
    // always-on total so he never has to ask again. Deliberately its own
    // lightweight endpoint (not folded into chartOfAccountsSearchForm,
    // which is paginated and requires a filled search field) so the
    // Search screen can show a real, un-capped total the moment it loads.
    public function chartOfAccountsTotalCount(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return response()->json(['total' => 0, 'by_type' => []]);
        }

        $rows = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_active', true)
            ->selectRaw('cbe_chart_of_accounts.account_type, COUNT(*) as c')
            ->groupBy('cbe_chart_of_accounts.account_type')
            ->pluck('c', 'account_type');

        return response()->json(['total' => (int) $rows->sum(), 'by_type' => $rows]);
    }

    // NEW 19 Sep 2026 -- "COA Chat", Phase 1 of the new AI Master Data
    // Assistant (per Chris's uploaded spec): describe an account in
    // plain language, the AI finds an existing match or classifies a
    // new one, Chris confirms before anything is saved. See
    // CoaChatAssistantService for the anti-fabrication rules.
    public function coaChatForm()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.chart-of-accounts.chat', __('masterfile.ai_assistant_tile_coa'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        return view('cbe.accounting.coa-chat');
    }

    public function coaChatClassify(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return response()->json(['status' => 'ERROR', 'message' => 'Session expired — please reload the page.']);
        }
        $description = trim((string) $request->input('description', ''));
        $result = (new CoaChatAssistantService())->classify($description, $groupLabelId, $nodeId);

        return response()->json($result);
    }

    private function buildAccountTree($flat): array
    {
        $byParent = [];
        foreach ($flat as $a) {
            $byParent[$a->parent_account_id ?? ''][] = $a;
        }

        $ordered = [];
        $walk = function ($parentKey, $depth) use (&$walk, &$byParent, &$ordered) {
            foreach ($byParent[$parentKey] ?? [] as $a) {
                $a->depth = $depth;
                $ordered[] = $a;
                $walk($a->account_id, $depth + 1);
            }
        };
        $walk('', 0);

        return $ordered;
    }

    // NEW 19 Sep 2026 -- "Chart of Accounts Structure Tree", per Chris:
    // a Windows-Explorer-style folder/sub-folder view of the Chart of
    // Accounts, searchable by All / one Account Type / a GL code range,
    // so the treasurer can SEE the last code used in a branch before
    // adding a new one and avoid leaving a gap (his example: 1004 exists,
    // user adds 1007 not realising 1005/1006 are now stuck unused).
    // Sits in the sidebar right before Chart of Accounts itself.
    // Same "don't dump everything by default" rule as the other CBE
    // list screens (37.0.5) -- stays empty until a mode is picked.
    public function coaStructureTree(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        // FIXED 23 Sep 2026 -- per Chris: picking an entity here used to
        // redirect to the generic Accounting hub instead of landing back
        // on this Structure Tree screen ("it should display the
        // structure tree why display all the module again"). This now
        // shows its OWN node-picker (same shared picker index() above
        // uses), so choosing an entity returns straight here with
        // ?node=<id> instead of losing the destination.
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.chart-of-accounts-structure-tree', __('cbe_accounting.tile_coa_structure_tree'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $mode = $request->query('mode', '');
        $accountType = $request->query('account_type', '');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $keyword = trim((string) $request->query('keyword', ''));
        $searched = in_array($mode, ['all', 'category', 'range', 'description'], true);

        $accountTypes = ['INCOME', 'EXPENSE', 'ASSET', 'EQUITY', 'LIABILITY']; // REORDERED 23 Sep 2026 -- per Chris
        $tree = [];
        $flatAccounts = null;
        $flatGap = null;

        // FIXED 23 Sep 2026 -- per Chris ("where is the bloody
        // entertain expenses gl code?"): the 19 Sep 2026 comprehensive
        // COA expansion added its ~121 new accounts with NO parent
        // (they sit directly under the Type, not folded into a header
        // account), so a type like EXPENSE can have 100+ TOP-LEVEL
        // siblings. The tree view's per-level cap (needed so the page
        // never scrolls) was silently hiding most of them, and its old
        // "see more" note pointed at Category search -- which ran the
        // exact same capped tree and hid them too. Category (a single
        // type) and Range now return a flat, Prev/Next-paginated list
        // instead, so every single account is always reachable. Only
        // "All" (a quick multi-type overview) still uses the capped
        // tree.
        if ($searched) {
            if ($mode === 'all') {
                $flat = $this->visibleAccountsQuery($groupLabelId, $nodeId)->orderBy('account_code')->get();
                $tree = $this->buildCoaStructureTree($flat, $accountTypes, $request);
            } elseif ($mode === 'category') {
                $query = $this->visibleAccountsQuery($groupLabelId, $nodeId);
                if ($accountType !== '') {
                    $query->where('account_type', $accountType);
                    $allOfType = (clone $query)->orderBy('account_code')->get();
                    $flatGap = $this->siblingGapInfo($allOfType->all(), null, null);
                }
                $flatAccounts = $query->orderBy('account_code')->paginate(15)->withQueryString();
            } elseif ($mode === 'range') {
                $fromNum = ctype_digit($from) ? (int) $from : null;
                $toNum = ctype_digit($to) ? (int) $to : null;
                $all = $this->visibleAccountsQuery($groupLabelId, $nodeId)->orderBy('account_code')->get();
                $filtered = $all->filter(function ($a) use ($fromNum, $toNum) {
                    if (! ctype_digit($a->account_code)) { return false; }
                    $n = (int) $a->account_code;
                    if ($fromNum !== null && $n < $fromNum) { return false; }
                    if ($toNum !== null && $n > $toNum) { return false; }
                    return true;
                })->sortBy('account_code')->values();
                $flatGap = $this->siblingGapInfo($filtered->all(), $fromNum, $toNum);

                $page = (int) $request->query('page', 1);
                $perPage = 15;
                $flatAccounts = new \Illuminate\Pagination\LengthAwarePaginator(
                    $filtered->forPage($page, $perPage)->values(),
                    $filtered->count(),
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'pageName' => 'page']
                );
                $flatAccounts->appends($request->query());
            } else { // description -- per Chris: "create another
                // selection search by description both chinese and
                // english with type ahead features". Matches the
                // keyword against account_name (English) OR
                // account_name_zh (Chinese); the type-ahead box on the
                // form (same AJAX endpoint the Search/Edit screen uses)
                // suggests matches live as Chris types, before he even
                // submits.
                $query = $this->visibleAccountsQuery($groupLabelId, $nodeId);
                if ($keyword !== '') {
                    $needle = '%'.$keyword.'%';
                    $query->where(function ($qr) use ($needle) {
                        $qr->where('account_name', 'like', $needle)
                           ->orWhere('account_name_zh', 'like', $needle);
                    });
                    $allMatches = (clone $query)->orderBy('account_code')->get();
                    $flatGap = $this->siblingGapInfo($allMatches->all(), null, null);
                }
                $flatAccounts = $query->orderBy('account_code')->paginate(15)->withQueryString();
            }
        }

        return view('cbe.accounting.chart-of-accounts-structure-tree', compact(
            'searched', 'mode', 'accountType', 'from', 'to', 'keyword', 'tree', 'flatAccounts', 'flatGap', 'accountTypes'
        ));
    }

    // Groups $flat by account_type, then recursively by parent_account_id
    // (the same 2-level header/posting-account hierarchy the rest of
    // Chart of Accounts already uses) into a nested array the Blade
    // partial _coa-tree-node.blade.php walks. Each level also carries its
    // own gap/next-code info (siblingGapInfo()) so a gap shows right at
    // the branch where it actually is, not just once at the bottom.
    // REPLACED 23 Sep 2026 -- per Chris ("ALL Means ALL, ALL Type
    // Account, glcode range. IT Means ALL"): the previous version
    // CAPPED each level and silently hid whatever didn't fit, with only
    // a "too many, use Category/Range search instead" note. Chris
    // rejected that outright -- "All" must actually show every single
    // account, full stop, never force him to switch search modes to
    // reach one. Every level (the top-level list under a Type, and
    // every folder's own children) is now PAGINATED instead of capped:
    // nothing is ever hidden without a Prev/Next to reach it, and the
    // page still never scrolls because only one page of rows renders at
    // a time. Page position for each level is carried in the URL
    // (?tp[TYPE]=n for a Type's top level, ?fp[account_id]=n for any
    // folder's children), so Prev/Next are plain links, same pattern as
    // the rest of the app.
    private const COA_TREE_TOP_PAGE_SIZE = 10;
    private const COA_TREE_ROW_PAGE_SIZE = 20;

    private function buildCoaStructureTree($flat, array $accountTypes, Request $request): array
    {
        $byType = [];
        foreach ($flat as $a) {
            $byType[$a->account_type][] = $a;
        }

        $topPages = $request->query('tp', []);
        $folderPages = $request->query('fp', []);

        $result = [];
        foreach ($accountTypes as $type) {
            $accountsOfType = $byType[$type] ?? [];
            $byParent = [];
            foreach ($accountsOfType as $a) {
                $byParent[$a->parent_account_id ?? ''][] = $a;
            }
            // Per Chris ("all the drill down glcode MUST same count tally
            // with category") -- every folder row now carries its own
            // 'count' = itself + every descendant underneath it, computed
            // from the FULL sibling set (never just the current page), so
            // a folder's number always matches the true total, and a
            // parent's count always equals the sum of its children's.
            $countSubtree = function ($parentKey) use (&$countSubtree, &$byParent) {
                $siblings = $byParent[$parentKey] ?? [];
                $total = count($siblings);
                foreach ($siblings as $a) {
                    $total += $countSubtree($a->account_id);
                }
                return $total;
            };
            // FIXED 23 Sep 2026 -- per Chris ("i dont understand when
            // click prev it come back to this screen"): clicking a
            // folder's own Next/Prev reloads the whole page, and every
            // <ul> starts collapsed again client-side -- so even though
            // the URL was still on the right Type/mode, EVERY expanded
            // folder snapped shut, which looked exactly like landing
            // back on the empty start screen. $openChain carries the
            // exact list of rows (Type, then each ancestor account_id)
            // that must be re-expanded to make THIS level visible again,
            // and gets baked into every Prev/Next link as ?open=...  The
            // page's own JS (below) reads that on load and re-opens
            // precisely that chain, so paging through a long folder like
            // Expense keeps your place instead of resetting the screen.
            $walk = function ($parentKey, $depth, $pageKind, $pageId, $openChain) use (&$walk, &$byParent, &$countSubtree, $request, $topPages, $folderPages) {
                $pageSize = $depth === 0 ? self::COA_TREE_TOP_PAGE_SIZE : self::COA_TREE_ROW_PAGE_SIZE;
                $siblings = $byParent[$parentKey] ?? [];
                usort($siblings, fn ($x, $y) => strcmp($x->account_code, $y->account_code));
                $total = count($siblings);
                $lastPage = max(1, (int) ceil($total / $pageSize));
                $rawPage = $pageKind === 'tp' ? ($topPages[$pageId] ?? 1) : ($folderPages[$pageId] ?? 1);
                $currentPage = max(1, min((int) $rawPage, $lastPage));
                $shown = array_slice($siblings, ($currentPage - 1) * $pageSize, $pageSize);

                $nodes = [];
                foreach ($shown as $a) {
                    $nodes[] = [
                        'account' => $a,
                        'children' => $walk($a->account_id, $depth + 1, 'fp', $a->account_id, array_merge($openChain, [(string) $a->account_id])),
                        'count' => 1 + $countSubtree($a->account_id),
                    ];
                }

                $prevUrl = null;
                $nextUrl = null;
                $baseArr = $pageKind === 'tp' ? $topPages : $folderPages;
                $paramName = $pageKind === 'tp' ? 'tp' : 'fp';
                $openParam = implode(',', $openChain);
                if ($currentPage > 1) {
                    $arr = $baseArr;
                    $arr[$pageId] = $currentPage - 1;
                    $prevUrl = $request->fullUrlWithQuery([$paramName => $arr, 'open' => $openParam]);
                }
                if ($currentPage < $lastPage) {
                    $arr = $baseArr;
                    $arr[$pageId] = $currentPage + 1;
                    $nextUrl = $request->fullUrlWithQuery([$paramName => $arr, 'open' => $openParam]);
                }

                return [
                    'nodes' => $nodes,
                    'gap' => $this->siblingGapInfo($siblings, null, null),
                    'total' => $total,
                    'page' => $currentPage,
                    'lastPage' => $lastPage,
                    'hasPrev' => $currentPage > 1,
                    'hasNext' => $currentPage < $lastPage,
                    'prevUrl' => $prevUrl,
                    'nextUrl' => $nextUrl,
                ];
            };
            $result[$type] = $walk('', 0, 'tp', $type, [$type]);
            // Per Chris: the count badge must be the TRUE total number of
            // GL accounts of this type (every level), not just what's on
            // the current page -- count($accountsOfType) here is the full
            // flat list for this type, before any pagination.
            $result[$type]['total'] = count($accountsOfType);
        }
        return $result;
    }

    // Finds missing numbers between the lowest and highest NUMERIC
    // account_code in $accounts (non-numeric codes are simply skipped --
    // this never blocks or errors on them), and works out the next code
    // to use, padded to match the same digit-width as the existing codes
    // (so "1004" -> next "1005", not "5"). $fromNum/$toNum (range mode
    // only) widen the checked window past what's actually present, so a
    // range search from 1000-1010 with only 1001/1002/1004 existing still
    // reports 1000, 1003, 1005-1010 as unused, not just the gap between
    // 1002 and 1004.
    private function siblingGapInfo(array $accounts, ?int $fromNum, ?int $toNum): ?array
    {
        $numeric = [];
        foreach ($accounts as $a) {
            if (ctype_digit($a->account_code)) {
                $numeric[(int) $a->account_code] = $a->account_code;
            }
        }
        if (empty($numeric)) {
            return null;
        }
        ksort($numeric);
        $nums = array_keys($numeric);

        // GUARD (added 22 Sep 2026, Ground Zero rebuild) -- the GL code
        // scheme is now 6 digits (type + 2-digit group + 3-digit item),
        // so a GROUP's own header sits 1000 apart from the next group's
        // header by design (e.g. 401000, 402000, ...). Those are never
        // meant to be "filled in" -- only a set of ITEM siblings inside
        // the SAME group (e.g. 401001, 401002, ...) should ever get a
        // missing-number / next-available suggestion. Outside of an
        // explicit range search, skip the whole gap block when the
        // siblings span more than one group -- otherwise this used to
        // walk every integer between two group headers and dump
        // thousands of meaningless "missing" numbers.
        if ($fromNum === null && $toNum === null) {
            $groupPrefixes = array_unique(array_map(fn ($n) => intdiv($n, 1000), $nums));
            if (count($groupPrefixes) > 1) {
                return null;
            }
        }

        $min = $fromNum !== null ? min($fromNum, $nums[0]) : $nums[0];
        $max = $toNum !== null ? max($toNum, end($nums)) : end($nums);
        $padLen = strlen($numeric[end($nums)]);

        // Safety cap regardless of scheme -- never hand the view a wall
        // of thousands of numbers to render.
        $missing = [];
        $cap = 200;
        for ($n = $min; $n <= $max && count($missing) < $cap; $n++) {
            if (! isset($numeric[$n])) {
                $missing[] = str_pad((string) $n, $padLen, '0', STR_PAD_LEFT);
            }
        }
        $truncated = ($max - $min + 1) - count($numeric) > count($missing);
        $nextNum = end($nums) + 1;
        $next = $toNum !== null && $nextNum > $toNum ? null : str_pad((string) $nextNum, $padLen, '0', STR_PAD_LEFT);

        if (empty($missing) && $next === null) {
            return null;
        }
        return ['missing' => $missing, 'next' => $next, 'truncated' => $truncated];
    }

    // NEW 3 Sep 2026 (Task #381) — shared validation for both add and
    // edit, now that the form carries Account Group/Category/Normal
    // Balance/Posting/Control/Description on top of the original 5 fields.
    private function accountValidationRules(): array
    {
        return [
            'account_code' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_name_zh' => ['nullable', 'string', 'max:150'],
            'account_type' => ['required', 'in:ASSET,LIABILITY,EQUITY,INCOME,EXPENSE'],
            'account_group_id' => ['nullable', 'uuid', 'exists:cbe_account_groups,group_id'],
            'account_category_id' => ['nullable', 'uuid', 'exists:cbe_account_categories,category_id'],
            'parent_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'normal_balance' => ['nullable', 'in:DEBIT,CREDIT'],
            'is_posting_account' => ['nullable'],
            'is_control_account' => ['nullable'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private static function defaultNormalBalance(string $accountType): string
    {
        return in_array($accountType, ['ASSET', 'EXPENSE'], true) ? 'DEBIT' : 'CREDIT';
    }

    public function storeAccount(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();

        $request->validate($this->accountValidationRules());
        $accountType = $request->input('account_type');

        // NEW 4 Sep 2026 (Task #390) — the community's root/HQ node adds
        // to the SHARED master (cbe_node_id null, visible federation-wide);
        // every other node (State/Branch/Temple) gets its own private
        // "add-on" account instead, per Chris's instruction that a temple
        // needing an account nobody else uses shouldn't have to go
        // through HQ, and shouldn't clutter every other temple's list.
        $isRootNode = $this->isRootNode($nodeId);
        $accountNodeId = $isRootNode ? null : $nodeId;

        // Account code must be unique among what THIS node can actually
        // see (the shared master plus its own local accounts) — not
        // globally, since two different temples are allowed to each pick
        // the same local code without ever colliding for either of them.
        $codeTaken = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('account_code', $request->input('account_code'))
            ->exists();
        if ($codeTaken) {
            return back()->withErrors([__('cbe_accounting.account_code_taken')])->withInput();
        }

        DB::table('cbe_chart_of_accounts')->insert([
            'account_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'cbe_node_id' => $accountNodeId,
            'account_code' => $request->input('account_code'),
            'account_name' => $request->input('account_name'),
            'account_name_zh' => $request->input('account_name_zh'),
            'account_type' => $accountType,
            'account_group_id' => $request->input('account_group_id') ?: null,
            'account_category_id' => $request->input('account_category_id') ?: null,
            'parent_account_id' => $request->input('parent_account_id') ?: null,
            'normal_balance' => $request->input('normal_balance') ?: self::defaultNormalBalance($accountType),
            'is_posting_account' => (bool) $request->input('is_posting_account', true),
            'is_control_account' => (bool) $request->input('is_control_account'),
            'description' => $request->input('description'),
            'created_by' => $agent->agent_id,
            'is_system' => false,
            'is_active' => true,
            'display_order' => (int) $request->input('account_code'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.chart-of-accounts')->with('success', __('cbe_accounting.account_saved'));
    }

    public function editAccount(string $accountId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $isRootNode = $this->isRootNode($nodeId);
        // NEW 4 Sep 2026 (Task #390) — a Temple/Branch/State cannot open
        // the shared master account for editing, and HQ cannot open one
        // Temple's local account; editableAccountsQuery() enforces which
        // side of that line this node is on before the row is even
        // fetched, so this 404s rather than silently showing it.
        $account = $this->editableAccountsQuery($groupLabelId, $nodeId)
            ->where('account_id', $accountId)
            ->first();
        if (! $account) {
            abort(404);
        }
        $account->created_by_name = $account->created_by ? DB::table('agents')->where('agent_id', $account->created_by)->value('full_name') : null;

        $groups = DB::table('cbe_account_groups')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('account_type')->orderBy('display_order')->get();

        // NEW 19 Sep 2026 -- per Chris: Category / Parent Account
        // switched from long <select> dropdowns to type-ahead search
        // boxes (parentAccountTypeahead()/accountCategoryTypeahead()
        // above), same pattern as Reason Code / Group Name. This screen
        // now only needs THIS record's own current label to pre-fill
        // the box, not the whole list.
        $selectedCategoryLabel = null;
        if ($account->account_category_id) {
            $cat = DB::table('cbe_account_categories')->where('category_id', $account->account_category_id)->first();
            if ($cat) { $selectedCategoryLabel = $cat->category_name_zh ? $cat->category_name.' ('.$cat->category_name_zh.')' : $cat->category_name; }
        }
        $selectedParentLabel = null;
        if ($account->parent_account_id) {
            $par = DB::table('cbe_chart_of_accounts')->where('account_id', $account->parent_account_id)->first();
            if ($par) { $selectedParentLabel = $par->account_code.' — '.$par->account_name; }
        }

        return view('cbe.accounting.edit-account', compact('account', 'groups', 'selectedCategoryLabel', 'selectedParentLabel'));
    }

    public function updateAccount(Request $request, string $accountId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        // NEW 4 Sep 2026 (Task #390) — same edit-scope restriction as
        // editAccount(): can't save a change to an account this node
        // isn't allowed to touch.
        $account = $this->editableAccountsQuery($groupLabelId, $nodeId)->where('account_id', $accountId)->first();
        if (! $account) {
            abort(404);
        }

        $request->validate($this->accountValidationRules());

        if ($request->input('parent_account_id') === $accountId) {
            return back()->withErrors([__('cbe_accounting.account_parent_self_error')])->withInput();
        }

        $codeTaken = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('account_id', '!=', $accountId)
            ->where('account_code', $request->input('account_code'))
            ->exists();
        if ($codeTaken) {
            return back()->withErrors([__('cbe_accounting.account_code_taken')])->withInput();
        }

        DB::table('cbe_chart_of_accounts')->where('account_id', $accountId)->update([
            'account_code' => $request->input('account_code'),
            'account_name' => $request->input('account_name'),
            'account_name_zh' => $request->input('account_name_zh'),
            // A system account's Type/Parent are structural (other code
            // relies on them, e.g. CbeAccountingService::AP_CODE) — the
            // edit form still shows them, but locks changes to those two.
            'account_type' => $account->is_system ? $account->account_type : $request->input('account_type'),
            'account_group_id' => $request->input('account_group_id') ?: null,
            'account_category_id' => $request->input('account_category_id') ?: null,
            'parent_account_id' => $account->is_system ? $account->parent_account_id : ($request->input('parent_account_id') ?: null),
            'normal_balance' => $request->input('normal_balance') ?: self::defaultNormalBalance($account->account_type),
            'is_posting_account' => (bool) $request->input('is_posting_account', true),
            'is_control_account' => (bool) $request->input('is_control_account'),
            'description' => $request->input('description'),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.chart-of-accounts')->with('success', __('cbe_accounting.account_saved'));
    }

    public function deactivateAccount(string $accountId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        // NEW 4 Sep 2026 (Task #390) — same edit-scope restriction: a
        // node can only deactivate an account it's allowed to edit.
        $account = $this->editableAccountsQuery($groupLabelId, $nodeId)->where('account_id', $accountId)->first();
        if ($account && ! $account->is_system) {
            DB::table('cbe_chart_of_accounts')->where('account_id', $accountId)->update(['is_active' => false, 'updated_at' => now()]);
        }
        return back()->with('success', __('cbe_accounting.account_deactivated'));
    }

    public function reactivateAccount(string $accountId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $this->editableAccountsQuery($groupLabelId, $nodeId)->where('account_id', $accountId)
            ->update(['is_active' => true, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.account_reactivated'));
    }

    // ---------- Account Groups (NEW 3 Sep 2026, Task #381) ----------

    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Now just the hub (2 buttons), same pattern as
    // Chart of Accounts -- see createAccountGroup() (Add) and
    // accountGroupsSearch() (Search/Edit) below.
    public function accountGroups()
    {
        return view('cbe.accounting.account-groups');
    }

    // NEW 22 Sep 2026 -- the Add screen.
    public function createAccountGroup()
    {
        return view('cbe.accounting.create-account-group');
    }

    // NEW 22 Sep 2026 -- the Search/Edit screen. Nothing is queried or
    // shown until Chris actually enters a criterion and submits.
    public function accountGroupsSearch(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $type = trim((string) $request->query('type', ''));
        $q = trim((string) $request->query('q', ''));
        $groups = null;
        if ($type !== '' || $q !== '') {
            $needle = '%'.$q.'%';
            $groups = DB::table('cbe_account_groups')
                ->where(function ($qr) use ($groupLabelId) { $qr->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
                ->when($type !== '', fn ($qr) => $qr->where('account_type', $type))
                ->when($q !== '', fn ($qr) => $qr->where(function ($qr2) use ($needle) {
                    $qr2->where('group_name', 'like', $needle)->orWhere('group_name_zh', 'like', $needle);
                }))
                ->orderByDesc('is_active')->orderBy('account_type')->orderBy('display_order')->orderBy('group_name')
                ->paginate(10, ['*'], 'agPage')->withQueryString();
        }
        return view('cbe.accounting.account-groups-search', compact('groups'));
    }

    public function storeAccountGroup(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $request->validate([
            'account_type' => ['required', 'in:ASSET,LIABILITY,EQUITY,INCOME,EXPENSE'],
            'group_name' => ['required', 'string', 'max:100'],
            'group_name_zh' => ['nullable', 'string', 'max:100'],
        ]);
        DB::table('cbe_account_groups')->insert([
            'group_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'account_type' => $request->input('account_type'),
            'group_name' => $request->input('group_name'),
            'group_name_zh' => $request->input('group_name_zh'),
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('cbe.accounting.account-groups')->with('success', __('cbe_accounting.account_group_saved'));
    }

    public function deactivateAccountGroup(string $groupId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        DB::table('cbe_account_groups')->where('group_id', $groupId)->where('group_label_id', $groupLabelId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.account_group_deactivated'));
    }

    // ---------- Account Categories (NEW 3 Sep 2026, Task #381) ----------

    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. This is now just the hub (2 buttons), same
    // pattern as Chart of Accounts -- see createAccountCategory() (Add)
    // and accountCategoriesSearch() (Search/Edit) below.
    public function accountCategories()
    {
        return view('cbe.accounting.account-categories');
    }

    // NEW 22 Sep 2026 -- the Add screen (was the top of the old combined
    // account-categories.blade.php page).
    public function createAccountCategory()
    {
        return view('cbe.accounting.create-account-category');
    }

    // NEW 22 Sep 2026 -- the Search/Edit screen. Nothing is queried or
    // shown until Chris actually types something into the search box
    // and submits -- an all-blank load shows the hint text only.
    public function accountCategoriesSearch(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $q = trim((string) $request->query('q', ''));
        $categories = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $categories = DB::table('cbe_account_categories')
                ->where(function ($qr) use ($groupLabelId) { $qr->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
                ->where(function ($qr) use ($needle) {
                    $qr->where('category_name', 'like', $needle)->orWhere('category_name_zh', 'like', $needle);
                })
                ->orderByDesc('is_active')->orderBy('display_order')->orderBy('category_name')
                ->paginate(10, ['*'], 'acPage')->withQueryString();
        }
        return view('cbe.accounting.account-categories-search', compact('categories'));
    }

    public function storeAccountCategory(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $request->validate([
            'category_name' => ['required', 'string', 'max:100'],
            'category_name_zh' => ['nullable', 'string', 'max:100'],
        ]);
        DB::table('cbe_account_categories')->insert([
            'category_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'category_name' => $request->input('category_name'),
            'category_name_zh' => $request->input('category_name_zh'),
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('cbe.accounting.account-categories')->with('success', __('cbe_accounting.account_category_saved'));
    }

    public function deactivateAccountCategory(string $categoryId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        DB::table('cbe_account_categories')->where('category_id', $categoryId)->where('group_label_id', $groupLabelId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.account_category_deactivated'));
    }

    // ---------- Transaction Categories (NEW 19 Sep 2026) ----------
    // A named income/expense category (e.g. "Meeting & Refreshment")
    // linked to one specific cbe_chart_of_accounts row -- used as the
    // line-item category dropdown on Bills/Invoices/JVs (already wired
    // up across this controller, see cbe_transaction_categories usages)
    // AND, as of today, the AI Accounting Automation review screen's own
    // GL Account dropdown. There was previously no screen to CREATE one
    // of these -- only to pick from ones already in the database -- so
    // "Entertainment Expenses"/"Meeting & Refreshment" as a Chart of
    // Accounts entry alone never showed up there. This closes that gap.

    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Now just the hub (2 buttons), same pattern as
    // Chart of Accounts -- see createTransactionCategory() (Add) and
    // transactionCategoriesSearch() (Search/Edit) below.
    public function transactionCategories()
    {
        return view('cbe.accounting.transaction-categories');
    }

    // NEW 22 Sep 2026 -- the Add screen.
    public function createTransactionCategory()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        // Only posting accounts can be picked here -- a header/control
        // account (like "Entertainment Expenses" itself, if set up as a
        // header) isn't something you post an actual transaction to
        // directly; its POSTING sub-accounts (like "Meeting & Refreshment")
        // are the ones that belong in this dropdown. Same visibility rule
        // as everywhere else: the shared master plus this node's own
        // local add-ons only.
        $glAccounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_posting_account', true)->where('is_active', true)
            ->orderBy('account_type')->orderBy('account_code')->get();

        return view('cbe.accounting.create-transaction-category', compact('glAccounts'));
    }

    // NEW 22 Sep 2026 -- the Search/Edit screen. Nothing is queried or
    // shown until Chris actually enters a criterion and submits.
    public function transactionCategoriesSearch(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $type = trim((string) $request->query('type', ''));
        $q = trim((string) $request->query('q', ''));
        $categories = null;
        if ($type !== '' || $q !== '') {
            $needle = '%'.$q.'%';
            $categories = DB::table('cbe_transaction_categories as tc')
                ->leftJoin('cbe_chart_of_accounts as coa', 'coa.account_id', '=', 'tc.chart_account_id')
                ->where(function ($qr) use ($groupLabelId) { $qr->whereNull('tc.group_label_id')->orWhere('tc.group_label_id', $groupLabelId); })
                ->when($type !== '', fn ($qr) => $qr->where('tc.type', $type))
                ->when($q !== '', fn ($qr) => $qr->where(function ($qr2) use ($needle) {
                    $qr2->where('tc.category_name', 'like', $needle)->orWhere('tc.category_name_zh', 'like', $needle);
                }))
                ->select('tc.*', 'coa.account_code', 'coa.account_name', 'coa.account_name_zh')
                ->orderByDesc('tc.is_active')->orderBy('tc.type')->orderBy('tc.display_order')->orderBy('tc.category_name')
                ->paginate(10, ['*'], 'tcPage')->withQueryString();
        }

        return view('cbe.accounting.transaction-categories-search', compact('categories'));
    }

    // ADDED 23 Sep 2026 -- per Chris ("ALL search must have type
    // ahead").
    public function transactionCategoryTypeahead(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $type = trim((string) $request->query('type', ''));
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';
        $results = DB::table('cbe_transaction_categories')
            ->where(function ($qr) use ($groupLabelId) { $qr->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)
            ->when($type !== '', fn ($qr) => $qr->where('type', $type))
            ->where(function ($qr) use ($needle) {
                $qr->where('category_name', 'like', $needle)->orWhere('category_name_zh', 'like', $needle);
            })
            ->orderBy('category_name')
            ->limit(15)
            ->get(['category_id', 'category_name', 'category_name_zh']);

        return response()->json($results);
    }

    public function storeTransactionCategory(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $request->validate([
            'category_name' => ['required', 'string', 'max:150'],
            'category_name_zh' => ['nullable', 'string', 'max:150'],
            'type' => ['required', 'in:INCOME,EXPENSE'],
            'chart_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
        ]);
        DB::table('cbe_transaction_categories')->insert([
            'category_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'category_name' => $request->input('category_name'),
            'category_name_zh' => $request->input('category_name_zh'),
            'type' => $request->input('type'),
            'chart_account_id' => $request->input('chart_account_id') ?: null,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('cbe.accounting.transaction-categories')->with('success', __('cbe_accounting.transaction_category_saved'));
    }

    public function deactivateTransactionCategory(string $categoryId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        DB::table('cbe_transaction_categories')->where('category_id', $categoryId)->where('group_label_id', $groupLabelId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.transaction_category_deactivated'));
    }

    public function reactivateTransactionCategory(string $categoryId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        DB::table('cbe_transaction_categories')->where('category_id', $categoryId)->where('group_label_id', $groupLabelId)
            ->update(['is_active' => true, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.transaction_category_reactivated'));
    }

    // ---------- Suppliers ----------

    public function suppliers()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $suppliers = DB::table('cbe_suppliers as s')
            ->leftJoin('cbe_supplier_categories as sc', 'sc.category_id', '=', 's.category_id')
            ->where('s.cbe_node_id', $nodeId)
            ->select('s.*', 'sc.category_name')
            ->orderByDesc('s.is_active')->orderBy('s.supplier_name')->paginate(8, ['*'], 'supPage');
        return view('cbe.accounting.suppliers', compact('suppliers'));
    }

    public function createSupplier()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $categories = DB::table('cbe_supplier_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('display_order')->orderBy('category_name')->get();
        $paymentTerms = DB::table('cbe_payment_terms')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('net_days')->get();
        $paymentMethods = DB::table('cbe_payment_methods')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('display_order')->orderBy('method_name')->get();
        // NEW 4 Sep 2026 (Task #394) — Default AP/Expense/Tax account
        // pickers, so a Supplier Invoice can pre-fill its GL coding.
        $glAccounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.accounting.create-supplier', compact('categories', 'paymentTerms', 'paymentMethods', 'glAccounts'));
    }

    // NEW 3 Sep 2026 (Task #371) — per Chris's AP spec: Supplier Master File
    // now captures category/payment terms/payment method/bank info/active
    // status, none of which existed before (25 Aug 2026 first build only
    // had name/contact/phone/email/address/notes).
    private function nextSupplierCode(string $nodeId): string
    {
        $count = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->count();
        return sprintf('SUP-%04d', $count + 1);
    }

    public function storeSupplier(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_name' => ['required', 'string', 'max:150'],
            'business_reg_no' => ['nullable', 'string', 'max:60'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_supplier_categories,category_id'],
            'payment_term_id' => ['nullable', 'uuid', 'exists:cbe_payment_terms,term_id'],
            'payment_method_id' => ['nullable', 'uuid', 'exists:cbe_payment_methods,method_id'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:60'],
            'bank_account_holder' => ['nullable', 'string', 'max:150'],
            'default_ap_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'default_expense_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'default_tax_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::table('cbe_suppliers')->insert([
            'supplier_id' => (string) Str::uuid(),
            'supplier_code' => $this->nextSupplierCode($nodeId),
            'cbe_node_id' => $nodeId,
            'supplier_name' => $request->input('supplier_name'),
            'business_reg_no' => $request->input('business_reg_no'),
            'category_id' => $request->input('category_id') ?: null,
            'payment_term_id' => $request->input('payment_term_id') ?: null,
            'payment_method_id' => $request->input('payment_method_id') ?: null,
            'contact_person' => $request->input('contact_person'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'bank_name' => $request->input('bank_name'),
            'bank_account_no' => $request->input('bank_account_no'),
            'bank_account_holder' => $request->input('bank_account_holder'),
            'default_ap_account_id' => $request->input('default_ap_account_id') ?: null,
            'default_expense_account_id' => $request->input('default_expense_account_id') ?: null,
            'default_tax_account_id' => $request->input('default_tax_account_id') ?: null,
            'is_active' => true,
            'notes' => $request->input('notes'),
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.suppliers')->with('success', __('cbe_accounting.supplier_saved'));
    }

    public function editSupplier(string $supplierId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $supplier = DB::table('cbe_suppliers')->where('supplier_id', $supplierId)->where('cbe_node_id', $nodeId)->firstOrFail();
        $categories = DB::table('cbe_supplier_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('display_order')->orderBy('category_name')->get();
        $paymentTerms = DB::table('cbe_payment_terms')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('net_days')->get();
        $paymentMethods = DB::table('cbe_payment_methods')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('display_order')->orderBy('method_name')->get();
        $glAccounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.accounting.edit-supplier', compact('supplier', 'categories', 'paymentTerms', 'paymentMethods', 'glAccounts'));
    }

    public function updateSupplier(Request $request, string $supplierId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_suppliers')->where('supplier_id', $supplierId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'supplier_name' => ['required', 'string', 'max:150'],
            'business_reg_no' => ['nullable', 'string', 'max:60'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_supplier_categories,category_id'],
            'payment_term_id' => ['nullable', 'uuid', 'exists:cbe_payment_terms,term_id'],
            'payment_method_id' => ['nullable', 'uuid', 'exists:cbe_payment_methods,method_id'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:60'],
            'bank_account_holder' => ['nullable', 'string', 'max:150'],
            'default_ap_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'default_expense_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'default_tax_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable'],
        ]);

        DB::table('cbe_suppliers')->where('supplier_id', $supplierId)->update([
            'supplier_name' => $request->input('supplier_name'),
            'business_reg_no' => $request->input('business_reg_no'),
            'category_id' => $request->input('category_id') ?: null,
            'payment_term_id' => $request->input('payment_term_id') ?: null,
            'payment_method_id' => $request->input('payment_method_id') ?: null,
            'contact_person' => $request->input('contact_person'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'bank_name' => $request->input('bank_name'),
            'bank_account_no' => $request->input('bank_account_no'),
            'bank_account_holder' => $request->input('bank_account_holder'),
            'default_ap_account_id' => $request->input('default_ap_account_id') ?: null,
            'default_expense_account_id' => $request->input('default_expense_account_id') ?: null,
            'default_tax_account_id' => $request->input('default_tax_account_id') ?: null,
            'is_active' => (bool) $request->input('is_active'),
            'notes' => $request->input('notes'),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.suppliers')->with('success', __('cbe_accounting.supplier_saved'));
    }

    // ---------- Supplier Categories (NEW 3 Sep 2026, Task #371) ----------

    public function supplierCategories()
    {
        [$agent] = $this->nodeAndGroup();
        $categories = DB::table('cbe_supplier_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->orderByDesc('is_active')->orderBy('display_order')->orderBy('category_name')
            ->paginate(8, ['*'], 'scPage');

        return view('cbe.accounting.supplier-categories', compact('categories'));
    }

    public function storeSupplierCategory(Request $request)
    {
        [$agent] = $this->nodeAndGroup();
        $request->validate([
            'category_name' => ['required', 'string', 'max:100'],
            'category_name_zh' => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('cbe_supplier_categories')->insert([
            'category_id' => (string) Str::uuid(),
            'group_label_id' => $agent->group_label_id,
            'category_name' => $request->input('category_name'),
            'category_name_zh' => $request->input('category_name_zh'),
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.supplier-categories')->with('success', __('cbe_accounting.supplier_category_saved'));
    }

    public function deactivateSupplierCategory(string $categoryId)
    {
        [$agent] = $this->nodeAndGroup();
        DB::table('cbe_supplier_categories')->where('category_id', $categoryId)->where('group_label_id', $agent->group_label_id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_accounting.supplier_category_deactivated'));
    }

    // ---------- Purchase Bills (Accounts Payable) ----------

    public function bills()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bills = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)
            ->select('b.*', 's.supplier_name')
            ->orderByDesc('b.bill_date')
            ->paginate(8, ['*'], 'billPage');
        return view('cbe.accounting.bills', compact('bills'));
    }

    public function createBill()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.bills.create', __('cbe_accounting.add_bill_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->orderBy('supplier_name')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('display_order')->get();
        $taxRates = DB::table('cbe_tax_rates')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('rate_percent')->get();
        // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest,
        // same list already used on the AI Accounting screen, now also
        // matched client-side (JS) here since these lines are typed
        // live rather than pre-extracted.
        $glHints = TransactionClassificationService::glCategoryHints();

        return view('cbe.accounting.create-bill', compact('suppliers', 'categories', 'taxRates', 'glHints'));
    }

    public function storeBill(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'bill_no' => ['nullable', 'string', 'max:60'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'line_category.*' => ['nullable', 'uuid'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_qty.*' => ['nullable', 'numeric', 'min:0'],
            'line_unit_price.*' => ['nullable', 'numeric', 'min:0'],
            'line_tax_rate.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // NEW 2 Sep 2026 (Task #335) — multi-line bills with tax, per
        // Chris's "kindergarten standard" correction. Build the line
        // items first, in memory, so we can validate there's at least
        // one before touching the database at all.
        $categories = $request->input('line_category', []);
        $descriptions = $request->input('line_description', []);
        $qtys = $request->input('line_qty', []);
        $unitPrices = $request->input('line_unit_price', []);
        $taxRates = $request->input('line_tax_rate', []);

        $lineRows = [];
        $totalAmount = 0;
        foreach ($unitPrices as $i => $unitPrice) {
            $unitPrice = (float) $unitPrice;
            if ($unitPrice <= 0) {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1) ?: 1;
            $taxRate = (float) ($taxRates[$i] ?? 0);
            $lineAmount = round($qty * $unitPrice, 2);
            $taxAmount = round($lineAmount * $taxRate / 100, 2);
            $lineTotal = round($lineAmount + $taxAmount, 2);

            $lineRows[] = [
                'category_id' => $categories[$i] ?: null,
                'description' => $descriptions[$i] ?: null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'line_amount' => $lineAmount,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'display_order' => $i,
            ];
            $totalAmount += $lineTotal;
        }

        if (empty($lineRows)) {
            return back()->withInput()->with('error', __('cbe_accounting.bill_error_min_lines'));
        }

        // NEW 4 Sep 2026 (Task #394 Phase 2) — duplicate Supplier Invoice
        // check (spec section 10): the same supplier cannot bill the same
        // invoice number twice. A DB-level unique index is the last line
        // of defence; this is the friendly message shown before that.
        if ($request->filled('bill_no') && DB::table('cbe_purchase_bills')
            ->where('supplier_id', $request->input('supplier_id'))
            ->where('bill_no', $request->input('bill_no'))
            ->where('status', '!=', 'CANCELLED')
            ->exists()) {
            return back()->withInput()->with('error', __('cbe_accounting.error_duplicate_invoice_no'));
        }

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('bill_date'))) {
            return $guard;
        }

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-purchase-bills', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        // GeneralLink's own gap-free document number, separate from
        // bill_no (the supplier's own reference, typed in above).
        $billDocRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'BILL', (int) \Carbon\Carbon::parse($request->input('bill_date'))->year, 'BILL', (int) \Carbon\Carbon::parse($request->input('bill_date'))->month);

        $billId = (string) Str::uuid();
        DB::table('cbe_purchase_bills')->insert([
            'bill_id' => $billId,
            'doc_ref_no' => $billDocRefNo,
            'cbe_node_id' => $nodeId,
            'supplier_id' => $request->input('supplier_id'),
            'bill_no' => $request->input('bill_no'),
            'bill_date' => $request->input('bill_date'),
            'due_date' => $request->input('due_date'),
            'description' => $request->input('description'),
            'amount' => round($totalAmount, 2),
            'paid_amount' => 0,
            'status' => 'UNPAID',
            'category_id' => null,
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentName,
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lineRows as $row) {
            DB::table('cbe_bill_lines')->insert(array_merge($row, [
                'line_id' => (string) Str::uuid(),
                'bill_id' => $billId,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        CbeAccountingService::postBill($billId);

        return redirect()->route('cbe.accounting.bills')->with('success', __('cbe_accounting.bill_saved'));
    }

    public function payBill(Request $request, string $billId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bill = DB::table('cbe_purchase_bills')->where('bill_id', $billId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('payment_date'))) {
            return $guard;
        }

        // NEW 2 Sep 2026 (Task #334) — Maker-Checker. Block a second
        // payment on this bill while one is still waiting for approval —
        // otherwise both could be approved later and overpay it, since
        // paid_amount doesn't move until a PENDING payment posts.
        $hasPendingPayment = DB::table('cbe_bill_payments')->where('bill_id', $billId)->where('status', 'PENDING')->exists();
        if ($hasPendingPayment) {
            return back()->with('error', __('cbe_accounting.error_payment_already_pending'));
        }

        $outstanding = (float) $bill->amount - (float) $bill->paid_amount;
        $amount = min((float) $request->input('amount'), $outstanding > 0 ? $outstanding : (float) $request->input('amount'));

        // NEW 2 Sep 2026 (Task #334) — Maker-Checker. If this node has the
        // approval workflow switched on and this amount meets the
        // threshold, the payment row is saved but NOT posted yet — it
        // waits in Pending Approvals for a second officer.
        $needsApproval = CbeAccountingService::requiresApproval($nodeId, $amount);

        $paymentId = (string) Str::uuid();
        DB::table('cbe_bill_payments')->insert([
            'payment_id' => $paymentId,
            'bill_id' => $billId,
            'payment_date' => $request->input('payment_date'),
            'amount' => $amount,
            'payment_method' => $request->input('payment_method'),
            'reference_no' => $request->input('reference_no'),
            'recorded_by' => $agent->agent_id,
            'status' => $needsApproval ? 'PENDING' : 'APPROVED',
            'approved_by' => $needsApproval ? null : $agent->agent_id,
            'approved_at' => $needsApproval ? null : now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($needsApproval) {
            return redirect()->route('cbe.accounting.bills')->with('success', __('cbe_accounting.payment_submitted_for_approval'));
        }

        CbeAccountingService::postBillPayment($paymentId);

        return redirect()->route('cbe.accounting.bills')->with('success', __('cbe_accounting.payment_saved'));
    }

    public function downloadBillAttachment(string $billId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bill = DB::table('cbe_purchase_bills')->where('bill_id', $billId)->where('cbe_node_id', $nodeId)->firstOrFail();

        if (! $bill->attachment_path || ! Storage::disk('local')->exists($bill->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($bill->attachment_path));
    }

    // ---------- Tax Rates (NEW 2 Sep 2026, Task #335) ----------
    // Scoped per node, not per group_label_id — see migration comment:
    // one branch/temple/state/HQ might be SST registered and another
    // not, so each configures its own rates independently. A node with
    // none configured only ever offers "No Tax" on Bill/Invoice lines.

    public function taxRates()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.tax-rates', __('cbe_accounting.manage_tax_rates_link'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $rates = DB::table('cbe_tax_rates')->where('cbe_node_id', $nodeId)
            ->orderByDesc('is_active')->orderBy('rate_percent')->paginate(6, ['*'], 'taxPage');

        return view('cbe.accounting.tax-rates', compact('rates'));
    }

    public function storeTaxRate(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'rate_name' => ['required', 'string', 'max:60'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::table('cbe_tax_rates')->insert([
            'rate_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'rate_name' => $request->input('rate_name'),
            'rate_percent' => round((float) $request->input('rate_percent'), 2),
            'is_active' => true,
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.tax-rates')->with('success', __('cbe_accounting.tax_rate_saved'));
    }

    public function deactivateTaxRate(string $rateId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_tax_rates')->where('rate_id', $rateId)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_accounting.tax_rate_deactivated'));
    }

    // ---------- AR Master File: Debtor Category (NEW 2 Sep 2026, Task
    // #357) — per Chris's Temple/NGO AR spec. Scoped by group_label_id
    // (same as cbe_transaction_categories) rather than cbe_node_id like
    // Tax Rates, since a customer category (Member, Donor, Event
    // Customer, Hall Rental Customer...) is normally standardised across
    // a whole temple organisation, not configured branch by branch. ----

    public function customerCategories()
    {
        [$agent] = $this->nodeAndGroup();
        $categories = DB::table('cbe_customer_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->orderByDesc('is_active')->orderBy('display_order')->orderBy('category_name')
            ->paginate(8, ['*'], 'ccPage');

        return view('cbe.accounting.customer-categories', compact('categories'));
    }

    public function storeCustomerCategory(Request $request)
    {
        [$agent] = $this->nodeAndGroup();
        $request->validate([
            'category_name' => ['required', 'string', 'max:100'],
            'category_name_zh' => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('cbe_customer_categories')->insert([
            'category_id' => (string) Str::uuid(),
            'group_label_id' => $agent->group_label_id,
            'category_name' => $request->input('category_name'),
            'category_name_zh' => $request->input('category_name_zh'),
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.customer-categories')->with('success', __('cbe_accounting.customer_category_saved'));
    }

    public function deactivateCustomerCategory(string $categoryId)
    {
        [$agent] = $this->nodeAndGroup();
        DB::table('cbe_customer_categories')->where('category_id', $categoryId)->where('group_label_id', $agent->group_label_id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_accounting.customer_category_deactivated'));
    }

    // ---------- AR Master File: Payment Method (NEW 2 Sep 2026, Task
    // #357) ----------

    public function paymentMethods()
    {
        [$agent] = $this->nodeAndGroup();
        $methods = DB::table('cbe_payment_methods')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->orderByDesc('is_active')->orderBy('display_order')->orderBy('method_name')
            ->paginate(8, ['*'], 'pmPage');

        return view('cbe.accounting.payment-methods', compact('methods'));
    }

    public function storePaymentMethod(Request $request)
    {
        [$agent] = $this->nodeAndGroup();
        $request->validate([
            'method_name' => ['required', 'string', 'max:100'],
        ]);

        DB::table('cbe_payment_methods')->insert([
            'method_id' => (string) Str::uuid(),
            'group_label_id' => $agent->group_label_id,
            'method_name' => $request->input('method_name'),
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.payment-methods')->with('success', __('cbe_accounting.payment_method_saved'));
    }

    public function deactivatePaymentMethod(string $methodId)
    {
        [$agent] = $this->nodeAndGroup();
        DB::table('cbe_payment_methods')->where('method_id', $methodId)->where('group_label_id', $agent->group_label_id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_accounting.payment_method_deactivated'));
    }

    // ---------- AR Master File: Payment Terms (NEW 2 Sep 2026, Task
    // #357) ----------

    public function paymentTerms()
    {
        [$agent] = $this->nodeAndGroup();
        $terms = DB::table('cbe_payment_terms')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->orderByDesc('is_active')->orderBy('net_days')
            ->paginate(8, ['*'], 'ptPage');

        return view('cbe.accounting.payment-terms', compact('terms'));
    }

    public function storePaymentTerm(Request $request)
    {
        [$agent] = $this->nodeAndGroup();
        $request->validate([
            'term_name' => ['required', 'string', 'max:100'],
            'net_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        DB::table('cbe_payment_terms')->insert([
            'term_id' => (string) Str::uuid(),
            'group_label_id' => $agent->group_label_id,
            'term_name' => $request->input('term_name'),
            'net_days' => (int) $request->input('net_days'),
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.payment-terms')->with('success', __('cbe_accounting.payment_term_saved'));
    }

    public function deactivatePaymentTerm(string $termId)
    {
        [$agent] = $this->nodeAndGroup();
        DB::table('cbe_payment_terms')->where('term_id', $termId)->where('group_label_id', $agent->group_label_id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_accounting.payment_term_deactivated'));
    }

    // ---------- Debit Notes (NEW 2 Sep 2026, Task #335) ----------
    // Reduces what's owed to a supplier (returned goods, a billing
    // correction) — cbe_debit_notes table and
    // CbeAccountingService::postDebitNote() already existed (25 Aug
    // 2026), this is the first screen to actually reach them.

    public function debitNotes()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_debit_notes as dn')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'dn.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.cbe_node_id', $nodeId)
            ->select('dn.*', 's.supplier_name', 'b.doc_ref_no as bill_doc_ref_no')
            ->orderByDesc('dn.note_date')
            ->paginate(8, ['*'], 'dnPage');

        return view('cbe.accounting.debit-notes', compact('notes'));
    }

    public function createDebitNote(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.debit-notes.create', __('cbe_accounting.add_debit_note_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->orderBy('supplier_name')->get();
        $bills = DB::table('cbe_purchase_bills')->where('cbe_node_id', $nodeId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderByDesc('bill_date')->get();

        // NEW 4 Sep 2026 (Task #394 Phase 2) — pre-fill supplier/amount/
        // reason and carry the return_id through when this screen is
        // reached via "Raise Debit Note" from a Purchase Return.
        $fromReturn = null;
        $returnAmount = null;
        if ($request->filled('return_id')) {
            $fromReturn = DB::table('cbe_purchase_returns')->where('return_id', $request->input('return_id'))->where('cbe_node_id', $nodeId)->first();
            if ($fromReturn) {
                $returnAmount = DB::table('cbe_purchase_return_lines')->where('return_id', $fromReturn->return_id)->sum('line_amount');
            }
        }

        return view('cbe.accounting.create-debit-note', compact('suppliers', 'bills', 'fromReturn', 'returnAmount'));
    }

    public function storeDebitNote(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'bill_id' => ['nullable', 'uuid', 'exists:cbe_purchase_bills,bill_id'],
            'return_id' => ['nullable', 'uuid', 'exists:cbe_purchase_returns,return_id'],
            'note_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('note_date'))) {
            return $guard;
        }

        $debitNoteId = (string) Str::uuid();
        // NEW 4 Sep 2026 (Task #392) — gap-free document number, same
        // scheme as every other document type.
        $noteDate = \Carbon\Carbon::parse($request->input('note_date'));
        $docRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'DN', $noteDate->year, 'DN', $noteDate->month);
        $returnId = $request->input('return_id') ?: null;
        DB::table('cbe_debit_notes')->insert([
            'debit_note_id' => $debitNoteId,
            'cbe_node_id' => $nodeId,
            'doc_ref_no' => $docRefNo,
            'supplier_id' => $request->input('supplier_id'),
            'bill_id' => $request->input('bill_id') ?: null,
            'return_id' => $returnId,
            'note_date' => $request->input('note_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postDebitNote($debitNoteId);

        if ($returnId) {
            DB::table('cbe_purchase_returns')->where('return_id', $returnId)->update([
                'status' => 'CREDIT_RAISED', 'ap_credit_note_id' => $debitNoteId, 'updated_at' => now(),
            ]);
        }

        return redirect()->route('cbe.accounting.debit-notes')->with('success', __('cbe_accounting.debit_note_saved'));
    }

    // ---------- AP Debit Notes (NEW 3 Sep 2026, Task #372) ----------
    // The genuine INCREASE-direction note (supplier under-billed, now
    // charges more). The existing debitNotes()/createDebitNote()/
    // storeDebitNote() methods above are displayed as "Credit Note" —
    // see lang file comment — and keep doing the DECREASE direction.

    public function apDebitNotes()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_ap_debit_notes as dn')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'dn.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.cbe_node_id', $nodeId)
            ->select('dn.*', 's.supplier_name', 'b.doc_ref_no as bill_doc_ref_no')
            ->orderByDesc('dn.note_date')
            ->paginate(8, ['*'], 'apDnPage');

        return view('cbe.accounting.ap-debit-notes', compact('notes'));
    }

    public function createApDebitNote()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ap-debit-notes.create', __('cbe_accounting.add_ap_debit_note_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();
        $bills = DB::table('cbe_purchase_bills')->where('cbe_node_id', $nodeId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderByDesc('bill_date')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('display_order')->orderBy('category_name')->get();

        return view('cbe.accounting.create-ap-debit-note', compact('suppliers', 'bills', 'categories'));
    }

    public function storeApDebitNote(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'bill_id' => ['nullable', 'uuid', 'exists:cbe_purchase_bills,bill_id'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'note_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('note_date'))) {
            return $guard;
        }

        $debitNoteId = (string) Str::uuid();
        $noteDate = \Carbon\Carbon::parse($request->input('note_date'));
        $docRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'APDN', $noteDate->year, 'APDN', $noteDate->month);
        DB::table('cbe_ap_debit_notes')->insert([
            'debit_note_id' => $debitNoteId,
            'cbe_node_id' => $nodeId,
            'doc_ref_no' => $docRefNo,
            'supplier_id' => $request->input('supplier_id'),
            'bill_id' => $request->input('bill_id') ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'note_date' => $request->input('note_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postApDebitNote($debitNoteId);

        return redirect()->route('cbe.accounting.ap-debit-notes')->with('success', __('cbe_accounting.ap_debit_note_saved'));
    }

    // NEW 9 Sep 2026 (Task #397 follow-up, Phase 16) — posts a note that
    // was drafted but deliberately left unposted (currently only the
    // AI's returned-cheque drafting does this — see
    // CbeAccountingService::draftReturnedChequeNote()). Manual notes
    // already post immediately at creation via storeApDebitNote() above,
    // so this action only ever has something to do for an AI draft, but
    // it's a normal, safe no-op (guarded by the same journal_id check)
    // if called on an already-posted one.
    public function postApDebitNoteAction(string $debitNote)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $note = DB::table('cbe_ap_debit_notes')->where('debit_note_id', $debitNote)->where('cbe_node_id', $nodeId)->firstOrFail();

        if ($guard = $this->guardPeriodOpen($nodeId, $note->note_date)) {
            return $guard;
        }

        CbeAccountingService::postApDebitNote($debitNote);

        return redirect()->route('cbe.accounting.ap-debit-notes')->with('success', __('cbe_accounting.debit_note_posted_success'));
    }

    // ---------- AP Refund (NEW 3 Sep 2026, Task #372) ----------

    public function apRefunds()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $refunds = DB::table('cbe_ap_refunds as r')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'r.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'r.bill_id')
            ->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 's.supplier_name', 'b.doc_ref_no as bill_doc_ref_no')
            ->orderByDesc('r.refund_date')
            ->paginate(8, ['*'], 'apRfPage');

        return view('cbe.accounting.ap-refunds', compact('refunds'));
    }

    public function createApRefund()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ap-refunds.create', __('cbe_accounting.add_ap_refund_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();
        $bills = DB::table('cbe_purchase_bills')->where('cbe_node_id', $nodeId)->orderByDesc('bill_date')->get();
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('bank_name')->get();

        return view('cbe.accounting.create-ap-refund', compact('suppliers', 'bills', 'bankAccounts'));
    }

    public function storeApRefund(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'bill_id' => ['nullable', 'uuid', 'exists:cbe_purchase_bills,bill_id'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'refund_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('refund_date'))) {
            return $guard;
        }

        $refundId = (string) Str::uuid();
        DB::table('cbe_ap_refunds')->insert([
            'refund_id' => $refundId,
            'cbe_node_id' => $nodeId,
            'supplier_id' => $request->input('supplier_id'),
            'bill_id' => $request->input('bill_id') ?: null,
            'bank_account_id' => $request->input('bank_account_id') ?: null,
            'refund_date' => $request->input('refund_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postApRefund($refundId);

        return redirect()->route('cbe.accounting.ap-refunds')->with('success', __('cbe_accounting.ap_refund_saved'));
    }

    // ---------- AP Adjustment (NEW 3 Sep 2026, Task #372) ----------

    public function apAdjustments()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $adjustments = DB::table('cbe_ap_adjustments as a')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'a.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'a.bill_id')
            ->where('a.cbe_node_id', $nodeId)
            ->select('a.*', 's.supplier_name', 'b.doc_ref_no as bill_doc_ref_no')
            ->orderByDesc('a.adjustment_date')
            ->paginate(8, ['*'], 'apAdjPage');

        return view('cbe.accounting.ap-adjustments', compact('adjustments'));
    }

    public function createApAdjustment()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ap-adjustments.create', __('cbe_accounting.add_ap_adjustment_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();
        $bills = DB::table('cbe_purchase_bills')->where('cbe_node_id', $nodeId)->orderByDesc('bill_date')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('display_order')->orderBy('category_name')->get();

        return view('cbe.accounting.create-ap-adjustment', compact('suppliers', 'bills', 'categories'));
    }

    public function storeApAdjustment(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'bill_id' => ['nullable', 'uuid', 'exists:cbe_purchase_bills,bill_id'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'adjustment_date' => ['required', 'date'],
            'direction' => ['required', 'in:INCREASE,DECREASE'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('adjustment_date'))) {
            return $guard;
        }

        $adjustmentId = (string) Str::uuid();
        DB::table('cbe_ap_adjustments')->insert([
            'adjustment_id' => $adjustmentId,
            'cbe_node_id' => $nodeId,
            'supplier_id' => $request->input('supplier_id'),
            'bill_id' => $request->input('bill_id') ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'adjustment_date' => $request->input('adjustment_date'),
            'direction' => $request->input('direction'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postApAdjustment($adjustmentId);

        return redirect()->route('cbe.accounting.ap-adjustments')->with('success', __('cbe_accounting.ap_adjustment_saved'));
    }

    // ---------- AP Opening Balance (NEW 3 Sep 2026, Task #372) ----------

    public function apOpeningBalances()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $openings = DB::table('cbe_ap_opening_balances as o')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'o.supplier_id')
            ->where('o.cbe_node_id', $nodeId)
            ->select('o.*', 's.supplier_name')
            ->orderByDesc('o.opening_date')
            ->paginate(8, ['*'], 'apObPage');

        return view('cbe.accounting.ap-opening-balances', compact('openings'));
    }

    public function createApOpeningBalance()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ap-opening-balances.create', __('cbe_accounting.add_ap_opening_balance_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();

        return view('cbe.accounting.create-ap-opening-balance', compact('suppliers'));
    }

    public function storeApOpeningBalance(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'opening_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Same period-closing guard every other AP entry method has —
        // Task #361 fixed the equivalent gap on the AR side.
        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('opening_date'))) {
            return $guard;
        }

        $openingId = (string) Str::uuid();
        DB::table('cbe_ap_opening_balances')->insert([
            'opening_id' => $openingId,
            'cbe_node_id' => $nodeId,
            'supplier_id' => $request->input('supplier_id'),
            'opening_date' => $request->input('opening_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'notes' => $request->input('notes'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postApOpeningBalance($openingId);

        return redirect()->route('cbe.accounting.ap-opening-balances')->with('success', __('cbe_accounting.ap_opening_balance_saved'));
    }

    // ---------- Payment Voucher (NEW 3 Sep 2026, Task #372) ----------
    // One payment, split across several outstanding bills for the same
    // supplier — the AP mirror of Payment Allocation (see
    // paymentAllocationPicker()/createPaymentAllocation()/
    // storePaymentAllocation() further down for the AR original this was
    // modelled on). Each allocation line becomes its own cbe_bill_payments
    // row (so postBillPayment()'s per-bill status/paid_amount rollforward
    // keeps working unchanged) — all rows sharing one Payment Voucher
    // number (pv_no) so they print/list together.

    public function paymentVoucherPicker()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();

        return view('cbe.accounting.payment-voucher-picker', compact('suppliers'));
    }

    public function createPaymentVoucher(string $supplierId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $supplier = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('supplier_id', $supplierId)->first();
        if (! $supplier) {
            return redirect()->route('cbe.accounting.payment-voucher');
        }

        $bills = DB::table('cbe_purchase_bills')->where('supplier_id', $supplierId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderBy('bill_date')->get()
            ->map(function ($b) {
                $b->outstanding = round((float) $b->amount - (float) $b->paid_amount, 2);
                return $b;
            });

        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('bank_name')->get();

        return view('cbe.accounting.create-payment-voucher', compact('supplier', 'bills', 'bankAccounts'));
    }

    public function storePaymentVoucher(Request $request, string $supplierId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $supplier = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('supplier_id', $supplierId)->first();
        if (! $supplier) {
            return redirect()->route('cbe.accounting.payment-voucher');
        }

        $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'allocation' => ['required', 'array'],
            'allocation.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('payment_date'))) {
            return $guard;
        }

        $allocations = array_filter($request->input('allocation', []), fn ($amt) => (float) $amt > 0);
        if (empty($allocations)) {
            return back()->withInput()->with('error', __('cbe_accounting.payment_voucher_error_none'));
        }

        $pvNo = CbeAccountingService::nextDocumentNumber($nodeId, 'PV', (int) \Carbon\Carbon::parse($request->input('payment_date'))->year, 'PV', (int) \Carbon\Carbon::parse($request->input('payment_date'))->month);
        $anyPending = false;

        foreach ($allocations as $billId => $amount) {
            $bill = DB::table('cbe_purchase_bills')->where('bill_id', $billId)->where('supplier_id', $supplierId)->first();
            if (! $bill) {
                continue;
            }
            // Same Maker-Checker threshold check as a single-bill payment
            // (payBill()) — a Payment Voucher isn't a way around approval.
            $needsApproval = CbeAccountingService::requiresApproval($nodeId, (float) $amount);
            $anyPending = $anyPending || $needsApproval;

            $paymentId = (string) Str::uuid();
            DB::table('cbe_bill_payments')->insert([
                'payment_id' => $paymentId,
                'bill_id' => $billId,
                'pv_no' => $pvNo,
                'bank_account_id' => $request->input('bank_account_id') ?: null,
                'payment_date' => $request->input('payment_date'),
                'amount' => round((float) $amount, 2),
                'payment_method' => $request->input('payment_method'),
                'reference_no' => $request->input('reference_no'),
                'recorded_by' => $agent->agent_id,
                'status' => $needsApproval ? 'PENDING' : 'APPROVED',
                'approved_by' => $needsApproval ? null : $agent->agent_id,
                'approved_at' => $needsApproval ? null : now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            if (! $needsApproval) {
                CbeAccountingService::postBillPayment($paymentId);
            }
        }

        $message = $anyPending ? __('cbe_accounting.payment_submitted_for_approval') : __('cbe_accounting.payment_voucher_saved');
        return redirect()->route('cbe.accounting.suppliers')->with('success', $message);
    }

    // ---------- AR Debit Notes (NEW 2 Sep 2026, Task #354) ----------
    // Customer-facing counterpart to the Debit Notes above. Increases
    // what a customer owes (an extra charge after the original invoice).
    // See CbeAccountingService::postArDebitNote().

    public function arDebitNotes()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_ar_debit_notes as dn')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'dn.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'dn.invoice_id')
            ->where('dn.cbe_node_id', $nodeId)
            ->select('dn.*', 'c.customer_name', 'i.invoice_no')
            ->orderByDesc('dn.note_date')
            ->paginate(8, ['*'], 'arDnPage');

        return view('cbe.accounting.ar-debit-notes', compact('notes'));
    }

    public function createArDebitNote()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ar-debit-notes.create', __('cbe_accounting.add_ar_debit_note_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();
        $invoices = DB::table('cbe_invoices')->where('cbe_node_id', $nodeId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderByDesc('invoice_date')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'INCOME')->where('is_active', true)
            ->orderBy('display_order')->orderBy('category_name')->get();

        return view('cbe.accounting.create-ar-debit-note', compact('customers', 'invoices', 'categories'));
    }

    public function storeArDebitNote(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:cbe_invoices,invoice_id'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'note_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('note_date'))) {
            return $guard;
        }

        $debitNoteId = (string) Str::uuid();
        $noteDate = \Carbon\Carbon::parse($request->input('note_date'));
        $docRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'ARDN', $noteDate->year, 'ARDN', $noteDate->month);
        DB::table('cbe_ar_debit_notes')->insert([
            'debit_note_id' => $debitNoteId,
            'cbe_node_id' => $nodeId,
            'doc_ref_no' => $docRefNo,
            'customer_id' => $request->input('customer_id'),
            'invoice_id' => $request->input('invoice_id') ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'note_date' => $request->input('note_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postArDebitNote($debitNoteId);

        return redirect()->route('cbe.accounting.ar-debit-notes')->with('success', __('cbe_accounting.ar_debit_note_saved'));
    }

    // NEW 9 Sep 2026 (Task #397 follow-up, Phase 16) — see
    // postApDebitNoteAction()'s comment; exact AR mirror.
    public function postArDebitNoteAction(string $debitNote)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $note = DB::table('cbe_ar_debit_notes')->where('debit_note_id', $debitNote)->where('cbe_node_id', $nodeId)->firstOrFail();

        if ($guard = $this->guardPeriodOpen($nodeId, $note->note_date)) {
            return $guard;
        }

        CbeAccountingService::postArDebitNote($debitNote);

        return redirect()->route('cbe.accounting.ar-debit-notes')->with('success', __('cbe_accounting.debit_note_posted_success'));
    }

    // ---------- AR Credit Notes (NEW 2 Sep 2026, Task #354) ----------
    // Reduces what a customer owes (returned goods, discount, billing
    // correction). See CbeAccountingService::postArCreditNote().

    public function arCreditNotes()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_ar_credit_notes as cn')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'cn.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'cn.invoice_id')
            ->where('cn.cbe_node_id', $nodeId)
            ->select('cn.*', 'c.customer_name', 'i.invoice_no')
            ->orderByDesc('cn.note_date')
            ->paginate(8, ['*'], 'arCnPage');

        return view('cbe.accounting.ar-credit-notes', compact('notes'));
    }

    public function createArCreditNote()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ar-credit-notes.create', __('cbe_accounting.add_ar_credit_note_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();
        $invoices = DB::table('cbe_invoices')->where('cbe_node_id', $nodeId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderByDesc('invoice_date')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'INCOME')->where('is_active', true)
            ->orderBy('display_order')->orderBy('category_name')->get();

        return view('cbe.accounting.create-ar-credit-note', compact('customers', 'invoices', 'categories'));
    }

    public function storeArCreditNote(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:cbe_invoices,invoice_id'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'note_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('note_date'))) {
            return $guard;
        }

        $creditNoteId = (string) Str::uuid();
        $noteDate = \Carbon\Carbon::parse($request->input('note_date'));
        $docRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'ARCN', $noteDate->year, 'ARCN', $noteDate->month);
        DB::table('cbe_ar_credit_notes')->insert([
            'credit_note_id' => $creditNoteId,
            'cbe_node_id' => $nodeId,
            'doc_ref_no' => $docRefNo,
            'customer_id' => $request->input('customer_id'),
            'invoice_id' => $request->input('invoice_id') ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'note_date' => $request->input('note_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postArCreditNote($creditNoteId);

        return redirect()->route('cbe.accounting.ar-credit-notes')->with('success', __('cbe_accounting.ar_credit_note_saved'));
    }

    // ---------- AR Adjustments (NEW 2 Sep 2026, Task #358) ----------
    // A generic write-off / balance correction, kept separate from
    // Debit/Credit Note so it has its own menu item + audit trail, per
    // Chris's Temple/NGO AR spec. See CbeAccountingService::postArAdjustment().

    public function arAdjustments()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $adjustments = DB::table('cbe_ar_adjustments as a')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'a.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'a.invoice_id')
            ->where('a.cbe_node_id', $nodeId)
            ->select('a.*', 'c.customer_name', 'i.invoice_no')
            ->orderByDesc('a.adjustment_date')
            ->paginate(8, ['*'], 'arAdjPage');

        return view('cbe.accounting.ar-adjustments', compact('adjustments'));
    }

    public function createArAdjustment()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ar-adjustments.create', __('cbe_accounting.add_ar_adjustment_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();
        $invoices = DB::table('cbe_invoices')->where('cbe_node_id', $nodeId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderByDesc('invoice_date')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'INCOME')->where('is_active', true)
            ->orderBy('display_order')->orderBy('category_name')->get();

        return view('cbe.accounting.create-ar-adjustment', compact('customers', 'invoices', 'categories'));
    }

    public function storeArAdjustment(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:cbe_invoices,invoice_id'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'adjustment_date' => ['required', 'date'],
            'direction' => ['required', 'in:INCREASE,DECREASE'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('adjustment_date'))) {
            return $guard;
        }

        $adjustmentId = (string) Str::uuid();
        DB::table('cbe_ar_adjustments')->insert([
            'adjustment_id' => $adjustmentId,
            'cbe_node_id' => $nodeId,
            'customer_id' => $request->input('customer_id'),
            'invoice_id' => $request->input('invoice_id') ?: null,
            'category_id' => $request->input('category_id') ?: null,
            'adjustment_date' => $request->input('adjustment_date'),
            'direction' => $request->input('direction'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postArAdjustment($adjustmentId);

        return redirect()->route('cbe.accounting.ar-adjustments')->with('success', __('cbe_accounting.ar_adjustment_saved'));
    }

    // ---------- AR Refunds (NEW 2 Sep 2026, Task #358) ----------
    // Cash paid back to a customer/donor (e.g. clearing an overpayment).
    // See CbeAccountingService::postArRefund().

    public function arRefunds()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $refunds = DB::table('cbe_ar_refunds as r')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'r.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'r.invoice_id')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 'c.customer_name', 'i.invoice_no', 'b.bank_name')
            ->orderByDesc('r.refund_date')
            ->paginate(8, ['*'], 'arRefPage');

        return view('cbe.accounting.ar-refunds', compact('refunds'));
    }

    public function createArRefund()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ar-refunds.create', __('cbe_accounting.add_ar_refund_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();
        $invoices = DB::table('cbe_invoices')->where('cbe_node_id', $nodeId)->orderByDesc('invoice_date')->get();
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('bank_name')->get();

        return view('cbe.accounting.create-ar-refund', compact('customers', 'invoices', 'bankAccounts'));
    }

    public function storeArRefund(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:cbe_invoices,invoice_id'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'refund_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('refund_date'))) {
            return $guard;
        }

        $refundId = (string) Str::uuid();
        DB::table('cbe_ar_refunds')->insert([
            'refund_id' => $refundId,
            'cbe_node_id' => $nodeId,
            'customer_id' => $request->input('customer_id'),
            'invoice_id' => $request->input('invoice_id') ?: null,
            'bank_account_id' => $request->input('bank_account_id') ?: null,
            'refund_date' => $request->input('refund_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'reason' => $request->input('reason'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postArRefund($refundId);

        return redirect()->route('cbe.accounting.ar-refunds')->with('success', __('cbe_accounting.ar_refund_saved'));
    }

    // ---------- AR Opening Balances (NEW 2 Sep 2026, Task #358) ----------
    // What a customer already owed on go-live day. One-off entry per
    // customer. See CbeAccountingService::postArOpeningBalance().

    public function arOpeningBalances()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $openingBalances = DB::table('cbe_ar_opening_balances as o')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'o.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'o.invoice_id')
            ->where('o.cbe_node_id', $nodeId)
            ->select('o.*', 'c.customer_name', 'i.invoice_no')
            ->orderByDesc('o.opening_date')
            ->paginate(8, ['*'], 'arObPage');

        return view('cbe.accounting.ar-opening-balances', compact('openingBalances'));
    }

    public function createArOpeningBalance()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ar-opening-balances.create', __('cbe_accounting.add_ar_opening_balance_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();
        $invoices = DB::table('cbe_invoices')->where('cbe_node_id', $nodeId)->orderByDesc('invoice_date')->get();

        return view('cbe.accounting.create-ar-opening-balance', compact('customers', 'invoices'));
    }

    public function storeArOpeningBalance(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:cbe_invoices,invoice_id'],
            'opening_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // NEW 2 Sep 2026 (Task #361) — period-closing coverage gap fix:
        // this entry point was missing the same guard every other AR
        // entry method already has, allowing an opening balance to be
        // backdated into a closed fiscal period.
        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('opening_date'))) {
            return $guard;
        }

        $openingId = (string) Str::uuid();
        DB::table('cbe_ar_opening_balances')->insert([
            'opening_id' => $openingId,
            'cbe_node_id' => $nodeId,
            'customer_id' => $request->input('customer_id'),
            'invoice_id' => $request->input('invoice_id') ?: null,
            'opening_date' => $request->input('opening_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'notes' => $request->input('notes'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postArOpeningBalance($openingId);

        return redirect()->route('cbe.accounting.ar-opening-balances')->with('success', __('cbe_accounting.ar_opening_balance_saved'));
    }

    // ---------- AR Payment Allocation (NEW 2 Sep 2026, Task #358) ----------
    // Per Chris's Temple/NGO AR spec worked example: one lump-sum payment
    // split across several of a customer's outstanding invoices in one
    // action. Pick a customer, tick which invoices the payment covers and
    // how much goes to each, and one cbe_invoice_payments row (posted via
    // the existing CbeAccountingService::postInvoicePayment()) is created
    // per invoice allocated to — same GL treatment as a single-invoice
    // payment, just applied several times in one screen.

    public function paymentAllocationPicker()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();

        return view('cbe.accounting.payment-allocation-picker', compact('customers'));
    }

    public function createPaymentAllocation(string $customerId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customer = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->where('customer_id', $customerId)->first();
        if (! $customer) {
            return redirect()->route('cbe.accounting.payment-allocation');
        }

        $invoices = DB::table('cbe_invoices')->where('customer_id', $customerId)
            ->whereIn('status', ['UNPAID', 'PARTIALLY_PAID'])
            ->orderBy('invoice_date')->get()
            ->map(function ($inv) {
                $inv->outstanding = round((float) $inv->amount - (float) $inv->paid_amount, 2);
                return $inv;
            });

        $paymentMethods = DB::table('cbe_payment_methods')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('display_order')->orderBy('method_name')->get();
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('bank_name')->get();

        return view('cbe.accounting.create-payment-allocation', compact('customer', 'invoices', 'paymentMethods', 'bankAccounts'));
    }

    public function storePaymentAllocation(Request $request, string $customerId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customer = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->where('customer_id', $customerId)->first();
        if (! $customer) {
            return redirect()->route('cbe.accounting.payment-allocation');
        }

        $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method_id' => ['nullable', 'uuid', 'exists:cbe_payment_methods,method_id'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'receipt_no' => ['nullable', 'string', 'max:60'],
            'allocation' => ['required', 'array'],
            'allocation.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('payment_date'))) {
            return $guard;
        }

        $allocations = array_filter($request->input('allocation', []), fn ($amt) => (float) $amt > 0);
        if (empty($allocations)) {
            return back()->withInput()->with('error', __('cbe_accounting.payment_allocation_error_none'));
        }

        $paymentMethodName = $request->input('payment_method_id')
            ? DB::table('cbe_payment_methods')->where('method_id', $request->input('payment_method_id'))->value('method_name')
            : null;

        foreach ($allocations as $invoiceId => $amount) {
            $invoice = DB::table('cbe_invoices')->where('invoice_id', $invoiceId)->where('customer_id', $customerId)->first();
            if (! $invoice) {
                continue;
            }
            $paymentId = (string) Str::uuid();
            DB::table('cbe_invoice_payments')->insert([
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'payment_date' => $request->input('payment_date'),
                'amount' => round((float) $amount, 2),
                'payment_method' => $paymentMethodName,
                'payment_method_id' => $request->input('payment_method_id') ?: null,
                'bank_account_id' => $request->input('bank_account_id') ?: null,
                'reference_no' => $request->input('reference_no'),
                'receipt_no' => $request->input('receipt_no'),
                'recorded_by' => $agent->agent_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            CbeAccountingService::postInvoicePayment($paymentId);
        }

        return redirect()->route('cbe.accounting.customers')->with('success', __('cbe_accounting.payment_allocation_saved'));
    }

    // ---------- Debtor Ledger + Debtor Statement (NEW 2 Sep 2026, Task #355) ----------
    // Debtor Ledger: every customer's AR activity (invoices, payments,
    // debit/credit notes) with running balance, one Excel export.
    // Debtor Statement: pick one customer, get their own statement
    // (opening balance, transactions, closing balance) as an Excel export.

    public function debtorLedgerPicker()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();

        return view('cbe.accounting.debtor-ledger-picker', compact('customers'));
    }

    // ---------- AR Enquiry: Debtor Account Enquiry (NEW 2 Sep 2026, Task
    // #359) — per Chris's Temple/NGO AR spec: an on-screen lookup (not a
    // downloadable report) showing one customer's category/terms,
    // outstanding balance, and full transaction history (Invoice/
    // Payment/Debit Note/Credit Note) with a running balance — covers
    // Debtor Account Enquiry, Outstanding Balance Enquiry, Transaction
    // History and Payment History for a single customer in one screen. --

    public function customerEnquiry(string $customerId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customer = DB::table('cbe_customers as cu')
            ->leftJoin('cbe_customer_categories as cc', 'cc.category_id', '=', 'cu.category_id')
            ->leftJoin('cbe_payment_terms as pt', 'pt.term_id', '=', 'cu.payment_terms_id')
            ->where('cu.customer_id', $customerId)->where('cu.cbe_node_id', $nodeId)
            ->select('cu.*', 'cc.category_name', 'pt.term_name', 'pt.net_days')
            ->first();

        if (! $customer) {
            return redirect()->route('cbe.accounting.customers');
        }

        $outstanding = (float) DB::table('cbe_invoices')->where('customer_id', $customerId)
            ->where('status', '!=', 'CANCELLED')
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total');

        $invoices = DB::table('cbe_invoices')->where('customer_id', $customerId)
            ->select('invoice_no', 'invoice_date as txn_date', DB::raw("'Invoice' as txn_type"), 'amount as debit', DB::raw('0 as credit'), 'journal_id', 'gl_posting_status');
        $payments = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->where('i.customer_id', $customerId)
            ->select('i.invoice_no', 'p.payment_date as txn_date', DB::raw("'Payment' as txn_type"), DB::raw('0 as debit'), 'p.amount as credit', 'p.journal_id', 'p.gl_posting_status');
        $debitNotes = DB::table('cbe_ar_debit_notes as dn')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'dn.invoice_id')
            ->where('dn.customer_id', $customerId)
            ->select('i.invoice_no', 'dn.note_date as txn_date', DB::raw("'Debit Note' as txn_type"), 'dn.amount as debit', DB::raw('0 as credit'), 'dn.journal_id', 'dn.gl_posting_status');
        $creditNotes = DB::table('cbe_ar_credit_notes as cn')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'cn.invoice_id')
            ->where('cn.customer_id', $customerId)
            ->select('i.invoice_no', 'cn.note_date as txn_date', DB::raw("'Credit Note' as txn_type"), DB::raw('0 as debit'), 'cn.amount as credit', 'cn.journal_id', 'cn.gl_posting_status');

        // Fetched whole (a temple customer's history is small) and
        // paginated manually with LengthAwarePaginator, rather than
        // wrapping the 4-way UNION ALL as a paginated subquery — keeps
        // the running-balance math simple and reliable across pages.
        $allTxns = $invoices->get()->concat($payments->get())->concat($debitNotes->get())->concat($creditNotes->get())
            ->sortBy('txn_date')->values();
        $balance = 0;
        $allTxns = $allTxns->map(function ($line) use (&$balance) {
            $balance += (float) $line->debit - (float) $line->credit;
            $line->running_balance = round($balance, 2);
            return $line;
        });

        $page = (int) request('txnPage', 1);
        $perPage = 8;
        $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $allTxns->forPage($page, $perPage)->values(),
            $allTxns->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'txnPage']
        );

        return view('cbe.accounting.customer-enquiry', compact('customer', 'outstanding', 'transactions'));
    }

    public function debtorLedgerReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $invoices = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)
            ->select('c.customer_name', 'i.invoice_no', 'i.invoice_date as txn_date',
                DB::raw("'Invoice' as txn_type"), 'i.amount as debit', DB::raw('0 as credit'))
            ->unionAll(
                DB::table('cbe_invoice_payments as p')
                    ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
                    ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
                    ->where('i.cbe_node_id', $nodeId)
                    ->select('c.customer_name', 'i.invoice_no', 'p.payment_date as txn_date',
                        DB::raw("'Payment' as txn_type"), DB::raw('0 as debit'), 'p.amount as credit')
            )
            ->unionAll(
                DB::table('cbe_ar_debit_notes as dn')
                    ->join('cbe_customers as c', 'c.customer_id', '=', 'dn.customer_id')
                    ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'dn.invoice_id')
                    ->where('dn.cbe_node_id', $nodeId)
                    ->select('c.customer_name', 'i.invoice_no', 'dn.note_date as txn_date',
                        DB::raw("'Debit Note' as txn_type"), 'dn.amount as debit', DB::raw('0 as credit'))
            )
            ->unionAll(
                DB::table('cbe_ar_credit_notes as cn')
                    ->join('cbe_customers as c', 'c.customer_id', '=', 'cn.customer_id')
                    ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'cn.invoice_id')
                    ->where('cn.cbe_node_id', $nodeId)
                    ->select('c.customer_name', 'i.invoice_no', 'cn.note_date as txn_date',
                        DB::raw("'Credit Note' as txn_type"), DB::raw('0 as debit'), 'cn.amount as credit')
            )
            ->orderBy('customer_name')->orderBy('txn_date')
            ->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Debtor Ledger Report'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Customer', 'Invoice No', 'Date', 'Type', 'Debit (RM)', 'Credit (RM)', 'Balance (RM)'];

        $runningBalance = 0;
        $lastCustomer = null;
        foreach ($invoices as $line) {
            if ($lastCustomer !== $line->customer_name) {
                $runningBalance = 0;
                $lastCustomer = $line->customer_name;
            }
            $runningBalance += (float) $line->debit - (float) $line->credit;
            $sheet[] = [
                $line->customer_name, $line->invoice_no ?: '—', \Carbon\Carbon::parse($line->txn_date)->format('d M Y'), $line->txn_type,
                round((float) $line->debit, 2), round((float) $line->credit, 2), round($runningBalance, 2),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Debtor Ledger', $sheet);
        return $this->download($spreadsheet, 'Debtor_Ledger');
    }

    public function debtorStatement(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
        ]);
        $customerId = $request->input('customer_id');
        $customer = DB::table('cbe_customers')->where('customer_id', $customerId)->first();
        if (! $customer) {
            return redirect()->route('cbe.accounting.debtor-ledger-picker');
        }

        $invoices = DB::table('cbe_invoices')->where('customer_id', $customerId)
            ->select('invoice_no', 'invoice_date as txn_date', DB::raw("'Invoice' as txn_type"), 'amount as debit', DB::raw('0 as credit'));
        $payments = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->where('i.customer_id', $customerId)
            ->select('i.invoice_no', 'p.payment_date as txn_date', DB::raw("'Payment' as txn_type"), DB::raw('0 as debit'), 'p.amount as credit');
        $debitNotes = DB::table('cbe_ar_debit_notes as dn')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'dn.invoice_id')
            ->where('dn.customer_id', $customerId)
            ->select('i.invoice_no', 'dn.note_date as txn_date', DB::raw("'Debit Note' as txn_type"), 'dn.amount as debit', DB::raw('0 as credit'));
        $creditNotes = DB::table('cbe_ar_credit_notes as cn')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'cn.invoice_id')
            ->where('cn.customer_id', $customerId)
            ->select('i.invoice_no', 'cn.note_date as txn_date', DB::raw("'Credit Note' as txn_type"), DB::raw('0 as debit'), 'cn.amount as credit');

        $lines = $invoices->unionAll($payments)->unionAll($debitNotes)->unionAll($creditNotes)
            ->orderBy('txn_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Statement of Account'];
        $sheet[] = [$customer->customer_name];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Invoice No', 'Date', 'Type', 'Debit (RM)', 'Credit (RM)', 'Balance (RM)'];

        $balance = 0;
        foreach ($lines as $line) {
            $balance += (float) $line->debit - (float) $line->credit;
            $sheet[] = [
                $line->invoice_no ?: '—', \Carbon\Carbon::parse($line->txn_date)->format('d M Y'), $line->txn_type,
                round((float) $line->debit, 2), round((float) $line->credit, 2), round($balance, 2),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Debtor Statement', $sheet);
        return $this->download($spreadsheet, 'Debtor_Statement_' . preg_replace('/[^A-Za-z0-9]+/', '_', $customer->customer_name));
    }

    // ---------- Donor Statement (NEW 3 Sep 2026, Task #368) ----------
    // Mirrors the Debtor Statement exactly, but sourced from Donation
    // Pledges/Pledge Receipts (Task #366) instead of Invoices/Invoice
    // Payments — the GL-integrated running balance a donor owes the
    // temple. cbe_contributions (event-scoped, non-GL) intentionally
    // stays out of this statement since it was never a receivable in
    // the first place — nothing to run a Dr/Cr balance against.

    public function donorStatementPicker()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $donors = DB::table('cbe_donors')->where('cbe_node_id', $nodeId)->orderBy('donor_name')->get();

        return view('cbe.accounting.donor-statement-picker', compact('donors'));
    }

    public function donorStatement(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $request->validate([
            'donor_id' => ['required', 'uuid', 'exists:cbe_donors,donor_id'],
        ]);
        $donorId = $request->input('donor_id');
        $donor = DB::table('cbe_donors')->where('donor_id', $donorId)->first();
        if (! $donor) {
            return redirect()->route('cbe.accounting.donor-statement-picker');
        }

        $pledges = DB::table('cbe_donation_pledges')->where('donor_id', $donorId)
            ->select('pledge_no', 'pledge_date as txn_date', DB::raw("'Pledge' as txn_type"), 'amount as debit', DB::raw('0 as credit'));
        $receipts = DB::table('cbe_pledge_receipts as r')
            ->join('cbe_donation_pledges as p', 'p.pledge_id', '=', 'r.pledge_id')
            ->where('p.donor_id', $donorId)
            ->select('p.pledge_no', 'r.receipt_date as txn_date', DB::raw("'Receipt' as txn_type"), DB::raw('0 as debit'), 'r.amount as credit');

        $lines = $pledges->unionAll($receipts)->orderBy('txn_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Donor Statement of Account'];
        $sheet[] = [$donor->donor_name];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Pledge No', 'Date', 'Type', 'Debit (RM)', 'Credit (RM)', 'Balance (RM)'];

        $balance = 0;
        foreach ($lines as $line) {
            $balance += (float) $line->debit - (float) $line->credit;
            $sheet[] = [
                $line->pledge_no ?: '—', \Carbon\Carbon::parse($line->txn_date)->format('d M Y'), $line->txn_type,
                round((float) $line->debit, 2), round((float) $line->credit, 2), round($balance, 2),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Donor Statement', $sheet);
        return $this->download($spreadsheet, 'Donor_Statement_' . preg_replace('/[^A-Za-z0-9]+/', '_', $donor->donor_name));
    }

    // ---------- AR Reports hub (NEW 2 Sep 2026, Task #360) ----------
    // 9 downloadable Excel reports per Chris's Temple/NGO AR spec,
    // beyond the Debtor Ledger/Statement and AR Aging built earlier.
    // All follow the same writeSheet()/download() pattern as every
    // other export in this controller.

    public function arReportsHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.ar-reports-hub');
    }

    public function invoiceListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $invoices = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)
            ->select('i.*', 'c.customer_name')
            ->orderBy('i.invoice_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Invoice Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Invoice No', 'Customer', 'Invoice Date', 'Due Date', 'Amount (RM)', 'Paid (RM)', 'Outstanding (RM)', 'Status'];
        foreach ($invoices as $inv) {
            $sheet[] = [
                $inv->invoice_no, $inv->customer_name, \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y'),
                $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d M Y') : '—',
                round((float) $inv->amount, 2), round((float) $inv->paid_amount, 2),
                round((float) $inv->amount - (float) $inv->paid_amount, 2), $inv->status,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Invoice Listing', $sheet);
        return $this->download($spreadsheet, 'Invoice_Listing');
    }

    public function receiptListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $payments = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'p.bank_account_id')
            ->where('i.cbe_node_id', $nodeId)
            ->select('p.*', 'i.invoice_no', 'c.customer_name', 'b.bank_name')
            ->orderBy('p.payment_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Receipt Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Receipt No', 'Date', 'Customer', 'Invoice No', 'Amount (RM)', 'Method', 'Bank Account', 'Reference No'];
        foreach ($payments as $p) {
            $sheet[] = [
                $p->receipt_no ?: '—', \Carbon\Carbon::parse($p->payment_date)->format('d M Y'), $p->customer_name, $p->invoice_no,
                round((float) $p->amount, 2), $p->payment_method ?: '—', $p->bank_name ?: '—', $p->reference_no ?: '—',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Receipt Listing', $sheet);
        return $this->download($spreadsheet, 'Receipt_Listing');
    }

    public function debitNoteListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_ar_debit_notes as dn')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'dn.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'dn.invoice_id')
            ->where('dn.cbe_node_id', $nodeId)
            ->select('dn.*', 'c.customer_name', 'i.invoice_no')
            ->orderBy('dn.note_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Debit Note Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Customer', 'Invoice No', 'Reason', 'Amount (RM)'];
        foreach ($notes as $n) {
            $sheet[] = [\Carbon\Carbon::parse($n->note_date)->format('d M Y'), $n->customer_name, $n->invoice_no ?: '—', $n->reason ?: '—', round((float) $n->amount, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Debit Note Listing', $sheet);
        return $this->download($spreadsheet, 'AR_Debit_Note_Listing');
    }

    public function creditNoteListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_ar_credit_notes as cn')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'cn.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'cn.invoice_id')
            ->where('cn.cbe_node_id', $nodeId)
            ->select('cn.*', 'c.customer_name', 'i.invoice_no')
            ->orderBy('cn.note_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Credit Note Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Customer', 'Invoice No', 'Reason', 'Amount (RM)'];
        foreach ($notes as $n) {
            $sheet[] = [\Carbon\Carbon::parse($n->note_date)->format('d M Y'), $n->customer_name, $n->invoice_no ?: '—', $n->reason ?: '—', round((float) $n->amount, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Credit Note Listing', $sheet);
        return $this->download($spreadsheet, 'AR_Credit_Note_Listing');
    }

    public function outstandingReceivablesReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $balances = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)
            ->where('i.status', '!=', 'CANCELLED')
            ->groupBy('c.customer_id', 'c.customer_name')
            ->select('c.customer_name', DB::raw('SUM(i.amount - i.paid_amount) as outstanding'))
            ->havingRaw('SUM(i.amount - i.paid_amount) > 0.004')
            ->orderByDesc('outstanding')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Outstanding Receivables'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Customer', 'Outstanding (RM)'];
        $total = 0;
        foreach ($balances as $b) {
            $sheet[] = [$b->customer_name, round((float) $b->outstanding, 2)];
            $total += (float) $b->outstanding;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', round($total, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Outstanding Receivables', $sheet);
        return $this->download($spreadsheet, 'Outstanding_Receivables');
    }

    public function debtorBalanceReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customers = DB::table('cbe_customers as c')
            ->leftJoin('cbe_customer_categories as cc', 'cc.category_id', '=', 'c.category_id')
            ->where('c.cbe_node_id', $nodeId)
            ->select('c.customer_id', 'c.customer_name', 'cc.category_name')
            ->orderBy('c.customer_name')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Debtor Balance Report'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Customer', 'Category', 'Total Invoiced (RM)', 'Total Paid (RM)', 'Total Debit Notes (RM)', 'Total Credit Notes (RM)', 'Outstanding (RM)'];

        foreach ($customers as $c) {
            $invoiced = (float) DB::table('cbe_invoices')->where('customer_id', $c->customer_id)->where('status', '!=', 'CANCELLED')->sum('amount');
            $paid = (float) DB::table('cbe_invoice_payments as p')->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')->where('i.customer_id', $c->customer_id)->sum('p.amount');
            $debitNotes = (float) DB::table('cbe_ar_debit_notes')->where('customer_id', $c->customer_id)->sum('amount');
            $creditNotes = (float) DB::table('cbe_ar_credit_notes')->where('customer_id', $c->customer_id)->sum('amount');
            $outstanding = $invoiced + $debitNotes - $creditNotes - $paid;
            if (abs($outstanding) < 0.004 && $invoiced < 0.004) {
                continue;
            }
            $sheet[] = [$c->customer_name, $c->category_name ?: '—', round($invoiced, 2), round($paid, 2), round($debitNotes, 2), round($creditNotes, 2), round($outstanding, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Debtor Balance', $sheet);
        return $this->download($spreadsheet, 'Debtor_Balance_Report');
    }

    // NEW 2 Sep 2026 (Task #361) — AR/GL Reconciliation report. Confirms
    // the AR sub-ledger (sum of outstanding invoice balances) agrees
    // with the AR Control Account balance in the General Ledger
    // (account code CbeAccountingService::AR_CODE). Any non-zero
    // Difference means a posting gap between the two sides.
    public function arGlReconciliationReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();

        $subLedgerTotal = (float) DB::table('cbe_invoices')
            ->where('cbe_node_id', $nodeId)
            ->where('status', '!=', 'CANCELLED')
            ->where('invoice_date', '<=', $asOf)
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
            ->value('total');

        $balances = $this->accountBalances($nodeId, $asOf);
        $arAccount = $balances->firstWhere('account_code', \App\Services\CbeAccountingService::AR_CODE);
        $controlBalance = $arAccount
            ? self::normalBalance('ASSET', (float) $arAccount->total_debit, (float) $arAccount->total_credit)
            : 0.0;

        $difference = round($subLedgerTotal - $controlBalance, 2);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — AR / GL Reconciliation'];
        $sheet[] = ['As of ' . \Carbon\Carbon::parse($asOf)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Description', 'Amount (RM)'];
        $sheet[] = ['AR Sub-Ledger Total (sum of outstanding invoices)', round($subLedgerTotal, 2)];
        $sheet[] = ['AR Control Account Balance (GL Account ' . \App\Services\CbeAccountingService::AR_CODE . ')', round($controlBalance, 2)];
        $sheet[] = ['Difference (expected RM0.00)', $difference];
        $sheet[] = [];
        $sheet[] = [$difference == 0.0 ? 'RECONCILED — no difference found.' : 'NOT RECONCILED — investigate unposted or misposted AR entries.'];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'AR-GL Reconciliation', $sheet);
        return $this->download($spreadsheet, 'AR_GL_Reconciliation');
    }

    public function monthlyArSummaryReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) $request->input('year', now()->year);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Monthly AR Summary ' . $year];
        $sheet[] = ['Generated ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Month', 'Invoiced (RM)', 'Received (RM)', 'Debit Notes (RM)', 'Credit Notes (RM)', 'Net Movement (RM)'];

        $totals = ['inv' => 0, 'rec' => 0, 'dn' => 0, 'cn' => 0];
        for ($m = 1; $m <= 12; $m++) {
            $invoiced = (float) DB::table('cbe_invoices')->where('cbe_node_id', $nodeId)
                ->whereYear('invoice_date', $year)->whereMonth('invoice_date', $m)->where('status', '!=', 'CANCELLED')->sum('amount');
            $received = (float) DB::table('cbe_invoice_payments as p')->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
                ->where('i.cbe_node_id', $nodeId)->whereYear('p.payment_date', $year)->whereMonth('p.payment_date', $m)->sum('p.amount');
            $debitNotes = (float) DB::table('cbe_ar_debit_notes')->where('cbe_node_id', $nodeId)
                ->whereYear('note_date', $year)->whereMonth('note_date', $m)->sum('amount');
            $creditNotes = (float) DB::table('cbe_ar_credit_notes')->where('cbe_node_id', $nodeId)
                ->whereYear('note_date', $year)->whereMonth('note_date', $m)->sum('amount');

            $sheet[] = [
                \Carbon\Carbon::create($year, $m, 1)->format('F'),
                round($invoiced, 2), round($received, 2), round($debitNotes, 2), round($creditNotes, 2),
                round($invoiced + $debitNotes - $creditNotes - $received, 2),
            ];
            $totals['inv'] += $invoiced; $totals['rec'] += $received; $totals['dn'] += $debitNotes; $totals['cn'] += $creditNotes;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', round($totals['inv'], 2), round($totals['rec'], 2), round($totals['dn'], 2), round($totals['cn'], 2), round($totals['inv'] + $totals['dn'] - $totals['cn'] - $totals['rec'], 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Monthly AR Summary', $sheet);
        return $this->download($spreadsheet, 'Monthly_AR_Summary_' . $year);
    }

    public function arTransactionReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfMonth()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $invoices = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)->whereBetween('i.invoice_date', [$from, $to])
            ->select('c.customer_name', 'i.invoice_no', 'i.invoice_date as txn_date', DB::raw("'Invoice' as txn_type"), 'i.amount as debit', DB::raw('0 as credit'));
        $payments = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)->whereBetween('p.payment_date', [$from, $to])
            ->select('c.customer_name', 'i.invoice_no', 'p.payment_date as txn_date', DB::raw("'Payment' as txn_type"), DB::raw('0 as debit'), 'p.amount as credit');
        $debitNotes = DB::table('cbe_ar_debit_notes as dn')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'dn.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'dn.invoice_id')
            ->where('dn.cbe_node_id', $nodeId)->whereBetween('dn.note_date', [$from, $to])
            ->select('c.customer_name', 'i.invoice_no', 'dn.note_date as txn_date', DB::raw("'Debit Note' as txn_type"), 'dn.amount as debit', DB::raw('0 as credit'));
        $creditNotes = DB::table('cbe_ar_credit_notes as cn')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'cn.customer_id')
            ->leftJoin('cbe_invoices as i', 'i.invoice_id', '=', 'cn.invoice_id')
            ->where('cn.cbe_node_id', $nodeId)->whereBetween('cn.note_date', [$from, $to])
            ->select('c.customer_name', 'i.invoice_no', 'cn.note_date as txn_date', DB::raw("'Credit Note' as txn_type"), DB::raw('0 as debit'), 'cn.amount as credit');

        $lines = $invoices->unionAll($payments)->unionAll($debitNotes)->unionAll($creditNotes)
            ->orderBy('txn_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — AR Transaction Report'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Customer', 'Invoice No', 'Type', 'Debit (RM)', 'Credit (RM)'];
        foreach ($lines as $l) {
            $sheet[] = [\Carbon\Carbon::parse($l->txn_date)->format('d M Y'), $l->customer_name, $l->invoice_no ?: '—', $l->txn_type, round((float) $l->debit, 2), round((float) $l->credit, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'AR Transactions', $sheet);
        return $this->download($spreadsheet, 'AR_Transaction_Report');
    }

    public function paymentCollectionReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfMonth()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $payments = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)->whereBetween('p.payment_date', [$from, $to])
            ->select('p.*', 'i.invoice_no', 'c.customer_name')
            ->orderBy('p.payment_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Payment Collection Report'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Customer', 'Invoice No', 'Method', 'Amount (RM)'];
        $byMethod = [];
        $total = 0;
        foreach ($payments as $p) {
            $method = $p->payment_method ?: 'Unspecified';
            $sheet[] = [\Carbon\Carbon::parse($p->payment_date)->format('d M Y'), $p->customer_name, $p->invoice_no, $method, round((float) $p->amount, 2)];
            $byMethod[$method] = ($byMethod[$method] ?? 0) + (float) $p->amount;
            $total += (float) $p->amount;
        }
        $sheet[] = [];
        $sheet[] = ['Summary by Method'];
        foreach ($byMethod as $method => $amt) {
            $sheet[] = [$method, round($amt, 2)];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL COLLECTED', round($total, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Payment Collection', $sheet);
        return $this->download($spreadsheet, 'Payment_Collection_Report');
    }

    // ---------- Purchase Requests (NEW 2 Sep 2026, Task #335) ----------
    // Pre-bill approval workflow: an officer raises a request (supplier +
    // line items, nothing posted yet), a DIFFERENT officer approves or
    // rejects it, then one click on an APPROVED request converts it
    // straight into a real Purchase Bill (own doc_ref_no, posts the
    // journal) — same self-approval block as Maker-Checker (Task #334),
    // ADMIN exempt.

    public function purchaseRequests()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $requests = DB::table('cbe_purchase_requests as r')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'r.supplier_id')
            ->join('agents as a', 'a.agent_id', '=', 'r.requested_by')
            ->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 's.supplier_name', 'a.full_name as requested_by_name')
            ->orderByDesc('r.created_at')
            ->paginate(8, ['*'], 'prPage');

        return view('cbe.accounting.purchase-requests', compact('requests'));
    }

    public function createPurchaseRequest()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.purchase-requests.create', __('cbe_accounting.add_purchase_request_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->orderBy('supplier_name')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('display_order')->get();
        // NEW 4 Sep 2026 (Task #394) — Department/Project/Cost Centre and
        // Fund pickers, per the Purchasing Management module spec.
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        return view('cbe.accounting.create-purchase-request', compact('suppliers', 'categories', 'costCentres', 'funds'));
    }

    public function storePurchaseRequest(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['nullable', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'line_category.*' => ['nullable', 'uuid'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_qty.*' => ['nullable', 'numeric', 'min:0'],
            'line_unit_price.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $categories = $request->input('line_category', []);
        $descriptions = $request->input('line_description', []);
        $qtys = $request->input('line_qty', []);
        $unitPrices = $request->input('line_unit_price', []);

        $lineRows = [];
        $totalAmount = 0;
        foreach ($unitPrices as $i => $unitPrice) {
            $unitPrice = (float) $unitPrice;
            if ($unitPrice <= 0) {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1) ?: 1;
            $lineAmount = round($qty * $unitPrice, 2);

            $lineRows[] = [
                'category_id' => $categories[$i] ?: null,
                'description' => $descriptions[$i] ?: null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_amount' => $lineAmount,
                'display_order' => $i,
            ];
            $totalAmount += $lineAmount;
        }

        if (empty($lineRows)) {
            return back()->withInput()->with('error', __('cbe_accounting.bill_error_min_lines'));
        }

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-purchase-requests', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        $requestId = (string) Str::uuid();
        $prDocRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'PR', (int) \Carbon\Carbon::parse($request->input('request_date'))->year, 'PR', (int) \Carbon\Carbon::parse($request->input('request_date'))->month);

        DB::table('cbe_purchase_requests')->insert([
            'request_id' => $requestId,
            'doc_ref_no' => $prDocRefNo,
            'cbe_node_id' => $nodeId,
            'supplier_id' => $request->input('supplier_id') ?: null,
            'cost_centre_id' => $request->input('cost_centre_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'request_date' => $request->input('request_date'),
            'required_date' => $request->input('required_date') ?: null,
            'description' => $request->input('description'),
            'remarks' => $request->input('remarks'),
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentName,
            'amount' => round($totalAmount, 2),
            'status' => 'PENDING',
            'requested_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lineRows as $row) {
            DB::table('cbe_purchase_request_lines')->insert(array_merge($row, [
                'line_id' => (string) Str::uuid(),
                'request_id' => $requestId,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'PR', $requestId, $prDocRefNo, 'CREATED', $agent->agent_id);

        return redirect()->route('cbe.accounting.purchase-requests')->with('success', __('cbe_accounting.purchase_request_saved'));
    }

    // NEW 4 Sep 2026 (Task #394 gap-fix) — a Purchase Requisition detail
    // screen never existed (only the list screen with inline actions),
    // so a Purchase Order could show "raised from PR :ref" as plain text
    // but had nowhere to link to. This closes that drill-down gap: PR ->
    // converted PO/Bill forward links, and is itself the backward target
    // for the PO show screen's "raised from Requisition" link.
    public function showPurchaseRequest(string $requestId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $pr = DB::table('cbe_purchase_requests as r')
            ->join('agents as a', 'a.agent_id', '=', 'r.requested_by')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'r.supplier_id')
            ->leftJoin('cbe_cost_centres as cc', 'cc.centre_id', '=', 'r.cost_centre_id')
            ->leftJoin('cbe_funds as f', 'f.fund_id', '=', 'r.fund_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'r.converted_bill_id')
            ->leftJoin('cbe_purchase_orders as po', 'po.po_id', '=', 'r.converted_po_id')
            ->where('r.request_id', $requestId)->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 'a.full_name as requested_by_name', 's.supplier_name', 'cc.centre_name', 'f.fund_name',
                'b.doc_ref_no as bill_doc_ref_no', 'po.doc_ref_no as po_doc_ref_no')
            ->firstOrFail();

        $lines = DB::table('cbe_purchase_request_lines as l')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'l.category_id')
            ->where('l.request_id', $requestId)
            ->select('l.*', 'c.category_name')
            ->orderBy('l.display_order')->get();

        return view('cbe.accounting.show-purchase-request', compact('pr', 'lines'));
    }

    public function approvePurchaseRequestAction(string $requestId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $result = CbeAccountingService::approvePurchaseRequest($requestId, $agent->agent_id, $agent->role === 'ADMIN');

        if (! $result['ok']) {
            $errorKey = $result['error'] === 'self_approval' ? 'error_self_approval' : 'error_approval_failed';
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'PR', $requestId, $pr->doc_ref_no, 'APPROVED', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.purchase_request_approved'));
    }

    public function rejectPurchaseRequestAction(Request $request, string $requestId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $result = CbeAccountingService::rejectPurchaseRequest($requestId, $agent->agent_id, $request->input('reason'), $agent->role === 'ADMIN');

        if (! $result['ok']) {
            $errorKey = $result['error'] === 'self_approval' ? 'error_self_approval' : 'error_approval_failed';
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'PR', $requestId, $pr->doc_ref_no, 'REJECTED', $agent->agent_id, $request->input('reason'));

        return back()->with('success', __('cbe_accounting.purchase_request_rejected'));
    }

    public function convertPurchaseRequestAction(Request $request, string $requestId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate(['bill_date' => ['required', 'date']]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('bill_date'))) {
            return $guard;
        }

        $result = CbeAccountingService::convertPurchaseRequestToBill($requestId, $agent->agent_id, $request->input('bill_date'));

        if (! $result['ok']) {
            $errorKey = match ($result['error']) {
                'no_supplier' => 'error_pr_no_supplier',
                'no_lines' => 'error_pr_no_lines',
                default => 'error_approval_failed',
            };
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'PR', $requestId, $pr->doc_ref_no, 'CONVERTED', $agent->agent_id, 'Converted to Bill');

        return redirect()->route('cbe.accounting.bills')->with('success', __('cbe_accounting.purchase_request_converted'));
    }

    public function convertPurchaseRequestToPOAction(Request $request, string $requestId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $pr = DB::table('cbe_purchase_requests')->where('request_id', $requestId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'po_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
        ]);

        $result = CbeAccountingService::convertPurchaseRequestToPO($requestId, $agent->agent_id, $request->input('po_date'), $request->input('expected_delivery_date') ?: null);

        if (! $result['ok']) {
            $errorKey = match ($result['error']) {
                'no_supplier' => 'error_pr_no_supplier',
                'no_lines' => 'error_pr_no_lines',
                default => 'error_approval_failed',
            };
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'PR', $requestId, $pr->doc_ref_no, 'CONVERTED', $agent->agent_id, 'Converted to Purchase Order');

        return redirect()->route('cbe.accounting.purchase-orders.show', $result['po_id'])->with('success', __('cbe_accounting.purchase_request_converted_to_po'));
    }

    // ---------- Purchase Orders (NEW 4 Sep 2026, Task #393) ----------
    // Master File/Entry/Enquiry pattern, same as every other document
    // type. No Control/Reports split needed beyond the one report below
    // — a PO's "control" is simply its status lifecycle (OPEN ->
    // received -> billed/closed, or cancelled), same weight as Bills/
    // Invoices, not the heavier maker-checker flow reserved for JVs.

    public function purchaseOrders()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $orders = DB::table('cbe_purchase_orders as po')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'po.supplier_id')
            ->where('po.cbe_node_id', $nodeId)
            ->select('po.*', 's.supplier_name')
            ->orderByDesc('po.po_date')
            ->paginate(8, ['*'], 'poPage');

        return view('cbe.accounting.purchase-orders', compact('orders'));
    }

    // Direct entry only — a temple's own Purchase Request that's already
    // APPROVED converts straight to a PO with one click (see
    // convertPurchaseRequestToPOAction() and the "Convert to PO" button
    // on the Purchase Requests screen), the same one-click pattern
    // already used for Request -> Bill. This form is for placing an
    // order that never went through a separate internal request first —
    // Chris's own example of a quick festival-season purchase.
    public function createPurchaseOrder()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.purchase-orders.create', __('cbe_accounting.add_purchase_order_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('display_order')->get();
        // NEW 4 Sep 2026 (Task #394) — Department/Project/Cost Centre and
        // Fund pickers, per the Purchasing Management module spec.
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        return view('cbe.accounting.create-purchase-order', compact('suppliers', 'categories', 'costCentres', 'funds'));
    }

    public function storePurchaseOrder(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'po_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'line_category.*' => ['nullable', 'uuid'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_qty.*' => ['nullable', 'numeric', 'min:0'],
            'line_unit_price.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $categories = $request->input('line_category', []);
        $descriptions = $request->input('line_description', []);
        $qtys = $request->input('line_qty', []);
        $unitPrices = $request->input('line_unit_price', []);

        $lineRows = [];
        $totalAmount = 0;
        foreach ($unitPrices as $i => $unitPrice) {
            $unitPrice = (float) $unitPrice;
            if ($unitPrice <= 0) {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1) ?: 1;
            $lineAmount = round($qty * $unitPrice, 2);

            $lineRows[] = [
                'category_id' => $categories[$i] ?: null,
                'description' => $descriptions[$i] ?: null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_amount' => $lineAmount,
                'display_order' => $i,
            ];
            $totalAmount += $lineAmount;
        }

        if (empty($lineRows)) {
            return back()->withInput()->with('error', __('cbe_accounting.bill_error_min_lines'));
        }

        $discountAmount = (float) $request->input('discount_amount', 0);
        $taxAmount = (float) $request->input('tax_amount', 0);
        $netAmount = round($totalAmount - $discountAmount + $taxAmount, 2);

        $poId = CbeAccountingService::createPurchaseOrderFromLines(
            $nodeId, $request->input('supplier_id'), null, $request->input('po_date'),
            $request->input('expected_delivery_date') ?: null, $request->input('description'),
            $netAmount, $agent->agent_id, $lineRows, [
                'cost_centre_id' => $request->input('cost_centre_id') ?: null,
                'fund_id' => $request->input('fund_id') ?: null,
                'delivery_address' => $request->input('delivery_address') ?: null,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
            ]
        );

        $poDocRefNo = DB::table('cbe_purchase_orders')->where('po_id', $poId)->value('doc_ref_no');
        CbeAccountingService::logPurchasingAudit($nodeId, 'PO', $poId, $poDocRefNo, 'CREATED', $agent->agent_id);

        return redirect()->route('cbe.accounting.purchase-orders.show', $poId)->with('success', __('cbe_accounting.purchase_order_saved'));
    }

    public function showPurchaseOrder(string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $order = DB::table('cbe_purchase_orders as po')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'po.supplier_id')
            ->leftJoin('cbe_purchase_requests as r', 'r.request_id', '=', 'po.request_id')
            ->where('po.po_id', $po)->where('po.cbe_node_id', $nodeId)
            ->select('po.*', 's.supplier_name', 's.contact_person', 's.phone as supplier_phone', 'r.doc_ref_no as request_doc_ref_no')
            ->firstOrFail();

        $lines = DB::table('cbe_purchase_order_lines as l')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'l.category_id')
            ->where('l.po_id', $po)
            ->select('l.*', 'c.category_name')
            ->orderBy('l.display_order')->get();

        $bills = DB::table('cbe_purchase_bills')->where('po_id', $po)->orderByDesc('bill_date')->get();
        $grns = DB::table('cbe_goods_receipts')->where('po_id', $po)->where('status', '!=', 'CANCELLED')->orderByDesc('grn_date')->get();

        // NEW 4 Sep 2026 (Task #394 gap-fix) — GL Integration / Posting
        // Control status trail (spec section 21). This system posts a
        // Bill's journal synchronously the instant it is created (see
        // CbeAccountingService::postBill()), so there is no real queued
        // "Transferred to AP" / "Ready for GL" / "Transferred to GL" wait
        // state to show separately — those spec stages are collapsed into
        // "AP Invoice Created" here, disclosed rather than fabricated.
        $activeBills = $bills->where('status', '!=', 'CANCELLED');
        if ($order->approval_status === 'PENDING_APPROVAL') {
            $glStage = 'PENDING_APPROVAL';
        } elseif ($order->approval_status === 'REJECTED') {
            $glStage = 'REJECTED';
        } elseif ($order->status === 'CANCELLED') {
            $glStage = 'CANCELLED';
        } elseif ($activeBills->isEmpty()) {
            $glStage = 'READY_FOR_AP';
        } elseif ($activeBills->contains(fn ($b) => $b->gl_posting_status === 'ERROR')) {
            $glStage = 'ERROR';
        } elseif ($activeBills->contains(fn ($b) => $b->gl_posting_status === 'REVERSED')) {
            $glStage = 'REVERSED';
        } elseif ($activeBills->every(fn ($b) => $b->gl_posting_status === 'POSTED') && $order->status === 'CLOSED') {
            $glStage = 'GL_POSTED';
        } else {
            $glStage = 'AP_INVOICE_CREATED';
        }

        return view('cbe.accounting.show-purchase-order', compact('order', 'lines', 'bills', 'grns', 'glStage'));
    }

    public function convertPurchaseOrderToBillAction(Request $request, string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $order = DB::table('cbe_purchase_orders')->where('po_id', $po)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate(['bill_date' => ['required', 'date']]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('bill_date'))) {
            return $guard;
        }

        $result = CbeAccountingService::convertPurchaseOrderToBill($po, $agent->agent_id, $request->input('bill_date'));

        if (! $result['ok']) {
            $errorKey = $result['error'] === 'pending_approval' ? 'error_po_pending_approval' : 'error_po_not_open';
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'PO', $po, $order->doc_ref_no, 'CONVERTED', $agent->agent_id, 'Converted to Bill');

        return redirect()->route('cbe.accounting.bills')->with('success', __('cbe_accounting.purchase_order_converted'));
    }

    // NEW 4 Sep 2026 (Task #394) — PO approval, using the same per-node
    // Maker-Checker self-approval block already used for Purchase Request
    // approval (a different officer must approve; ADMIN is exempt).
    public function approvePurchaseOrderAction(string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $order = DB::table('cbe_purchase_orders')->where('po_id', $po)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($order->approval_status !== 'PENDING_APPROVAL') {
            return back()->with('error', __('cbe_accounting.error_approval_failed'));
        }
        if ($order->created_by === $agent->agent_id && $agent->role !== 'ADMIN') {
            return back()->with('error', __('cbe_accounting.error_self_approval'));
        }
        DB::table('cbe_purchase_orders')->where('po_id', $po)->update([
            'approval_status' => 'APPROVED', 'approved_by' => $agent->agent_id, 'approved_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::logPurchasingAudit($nodeId, 'PO', $po, $order->doc_ref_no, 'APPROVED', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.purchase_order_approved'));
    }

    public function rejectPurchaseOrderAction(Request $request, string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $order = DB::table('cbe_purchase_orders')->where('po_id', $po)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($order->approval_status !== 'PENDING_APPROVAL') {
            return back()->with('error', __('cbe_accounting.error_approval_failed'));
        }
        if ($order->created_by === $agent->agent_id && $agent->role !== 'ADMIN') {
            return back()->with('error', __('cbe_accounting.error_self_approval'));
        }
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        DB::table('cbe_purchase_orders')->where('po_id', $po)->update([
            'approval_status' => 'REJECTED', 'approved_by' => $agent->agent_id, 'approved_at' => now(),
            'approval_remarks' => $request->input('reason'), 'status' => 'CANCELLED', 'updated_at' => now(),
        ]);

        CbeAccountingService::logPurchasingAudit($nodeId, 'PO', $po, $order->doc_ref_no, 'REJECTED', $agent->agent_id, $request->input('reason'));

        return back()->with('success', __('cbe_accounting.purchase_order_rejected'));
    }

    public function markPurchaseOrderReceivedAction(string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $order = DB::table('cbe_purchase_orders')->where('po_id', $po)->where('cbe_node_id', $nodeId)->firstOrFail();
        if (! in_array($order->status, ['OPEN', 'PARTIALLY_RECEIVED'], true)) {
            return back()->with('error', __('cbe_accounting.error_po_not_open'));
        }
        if ($order->approval_status === 'PENDING_APPROVAL') {
            return back()->with('error', __('cbe_accounting.error_po_pending_approval'));
        }
        DB::table('cbe_purchase_orders')->where('po_id', $po)->update(['status' => 'FULLY_RECEIVED', 'updated_at' => now()]);

        CbeAccountingService::logPurchasingAudit($nodeId, 'PO', $po, $order->doc_ref_no, 'RECEIVED', $agent->agent_id, 'Marked fully received');

        return back()->with('success', __('cbe_accounting.purchase_order_marked_received'));
    }

    public function cancelPurchaseOrderAction(string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $order = DB::table('cbe_purchase_orders')->where('po_id', $po)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($order->status === 'CLOSED') {
            return back()->with('error', __('cbe_accounting.error_po_already_closed'));
        }
        DB::table('cbe_purchase_orders')->where('po_id', $po)->update(['status' => 'CANCELLED', 'updated_at' => now()]);

        CbeAccountingService::logPurchasingAudit($nodeId, 'PO', $po, $order->doc_ref_no, 'CANCELLED', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.purchase_order_cancelled'));
    }

    // NEW 4 Sep 2026 (Task #393) — PO Outstanding report: every PO not
    // yet fully billed, ordered amount vs billed-so-far vs what's still
    // outstanding, so a treasurer can see festival-season commitments
    // that haven't turned into an actual bill yet.
    public function purchaseOrderOutstandingReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $orders = DB::table('cbe_purchase_orders as po')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'po.supplier_id')
            ->where('po.cbe_node_id', $nodeId)
            ->whereNotIn('po.status', ['CANCELLED'])
            ->select('po.*', 's.supplier_name')
            ->orderBy('po.po_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Purchase Order Outstanding'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['PO No.', 'PO Date', 'Supplier', 'Status', 'Ordered (RM)', 'Billed So Far (RM)', 'Outstanding (RM)'];

        foreach ($orders as $po) {
            $billed = (float) DB::table('cbe_purchase_bills')->where('po_id', $po->po_id)->sum('amount');
            $outstanding = round((float) $po->amount - $billed, 2);
            $sheet[] = [
                $po->doc_ref_no ?: '—', \Carbon\Carbon::parse($po->po_date)->format('d M Y'), $po->supplier_name,
                __('cbe_accounting.po_status_'.strtolower($po->status)),
                round((float) $po->amount, 2), round($billed, 2), $outstanding,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'PO Outstanding', $sheet);
        return $this->download($spreadsheet, 'Purchase_Order_Outstanding');
    }

    // ---------- Purchase Quotations / RFQ (NEW 4 Sep 2026, Task #394) ----------
    // Per the Purchasing Management module spec, section 5: sits between
    // Purchase Requisition and Purchase Order. One RFQ can invite several
    // suppliers (always from the common cbe_suppliers master) for
    // comparison; one is marked selected after evaluation, then the RFQ
    // converts straight into a PO for that supplier.
    public function purchaseQuotations()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $rfqs = DB::table('cbe_purchase_rfqs as q')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'q.selected_supplier_id')
            ->where('q.cbe_node_id', $nodeId)
            ->select('q.*', 's.supplier_name as selected_supplier_name')
            ->orderByDesc('q.created_at')
            ->paginate(8, ['*'], 'rfqPage');

        return view('cbe.accounting.purchase-quotations', compact('rfqs'));
    }

    public function createPurchaseQuotation()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.purchase-quotations.create', __('cbe_accounting.add_purchase_quotation_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        return view('cbe.accounting.create-purchase-quotation', compact('suppliers', 'costCentres', 'funds'));
    }

    public function storePurchaseQuotation(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'rfq_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'supplier_id.*' => ['nullable', 'uuid', 'exists:cbe_suppliers,supplier_id'],
        ]);

        $supplierIds = array_values(array_filter($request->input('supplier_id', [])));
        $supplierIds = array_unique($supplierIds);
        if (count($supplierIds) < 2) {
            return back()->withInput()->with('error', __('cbe_accounting.rfq_error_min_suppliers'));
        }

        $rfqId = (string) Str::uuid();
        $docRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'RFQ', (int) \Carbon\Carbon::parse($request->input('rfq_date'))->year, 'RFQ', (int) \Carbon\Carbon::parse($request->input('rfq_date'))->month);

        DB::table('cbe_purchase_rfqs')->insert([
            'rfq_id' => $rfqId,
            'doc_ref_no' => $docRefNo,
            'cbe_node_id' => $nodeId,
            'cost_centre_id' => $request->input('cost_centre_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'rfq_date' => $request->input('rfq_date'),
            'description' => $request->input('description'),
            'status' => 'SENT',
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($supplierIds as $supplierId) {
            DB::table('cbe_purchase_rfq_suppliers')->insert([
                'rfq_supplier_id' => (string) Str::uuid(),
                'rfq_id' => $rfqId,
                'supplier_id' => $supplierId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'RFQ', $rfqId, $docRefNo, 'CREATED', $agent->agent_id);

        return redirect()->route('cbe.accounting.purchase-quotations.show', $rfqId)->with('success', __('cbe_accounting.purchase_quotation_saved'));
    }

    public function showPurchaseQuotation(string $rfq)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $quotation = DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->where('cbe_node_id', $nodeId)->firstOrFail();

        $invited = DB::table('cbe_purchase_rfq_suppliers as qs')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'qs.supplier_id')
            ->where('qs.rfq_id', $rfq)
            ->select('qs.*', 's.supplier_name')
            ->orderBy('s.supplier_name')->get();

        return view('cbe.accounting.show-purchase-quotation', compact('quotation', 'invited'));
    }

    // Records every invited supplier's quote in one save — a single
    // comparison-table form (quoted_amount/ref/date keyed by
    // rfq_supplier_id) rather than a separate submit per supplier row,
    // matching this app's established "one form, array-keyed line inputs"
    // pattern already used for PR/PO/Bill line items.
    public function storeQuotationAmountsAction(Request $request, string $rfq)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $quotationRow = DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'quoted_amount' => ['nullable', 'array'],
            'quoted_amount.*' => ['nullable', 'numeric', 'min:0'],
            'quotation_ref' => ['nullable', 'array'],
            'quotation_date' => ['nullable', 'array'],
        ]);

        $amounts = $request->input('quoted_amount', []);
        $refs = $request->input('quotation_ref', []);
        $dates = $request->input('quotation_date', []);
        $anySaved = false;

        foreach ($amounts as $rfqSupplierId => $amount) {
            if ($amount === null || $amount === '') {
                continue;
            }
            $row = DB::table('cbe_purchase_rfq_suppliers')->where('rfq_supplier_id', $rfqSupplierId)->where('rfq_id', $rfq)->first();
            if (! $row) {
                continue;
            }
            DB::table('cbe_purchase_rfq_suppliers')->where('rfq_supplier_id', $rfqSupplierId)->update([
                'quoted_amount' => (float) $amount,
                'quotation_ref' => $refs[$rfqSupplierId] ?? null,
                'quotation_date' => ($dates[$rfqSupplierId] ?? null) ?: null,
                'updated_at' => now(),
            ]);
            $anySaved = true;
        }

        if ($anySaved) {
            DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->where('status', 'SENT')->update(['status' => 'QUOTED', 'updated_at' => now()]);
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'RFQ', $rfq, $quotationRow->doc_ref_no, 'QUOTE_SAVED', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.rfq_quote_saved'));
    }

    // Evaluation/selection step (spec section 5) — marking one supplier
    // selected moves the RFQ to EVALUATED, ready to award to a PO.
    public function selectQuotationSupplierAction(string $rfq, string $rfqSupplier)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $quotationRow = DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->where('cbe_node_id', $nodeId)->firstOrFail();
        $chosen = DB::table('cbe_purchase_rfq_suppliers')->where('rfq_supplier_id', $rfqSupplier)->where('rfq_id', $rfq)->firstOrFail();

        if (! $chosen->quoted_amount) {
            return back()->with('error', __('cbe_accounting.rfq_error_no_quote_yet'));
        }

        DB::table('cbe_purchase_rfq_suppliers')->where('rfq_id', $rfq)->update(['is_selected' => false]);
        DB::table('cbe_purchase_rfq_suppliers')->where('rfq_supplier_id', $rfqSupplier)->update(['is_selected' => true]);
        DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->update([
            'status' => 'EVALUATED', 'selected_supplier_id' => $chosen->supplier_id,
            'approved_by' => $agent->agent_id, 'approved_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::logPurchasingAudit($nodeId, 'RFQ', $rfq, $quotationRow->doc_ref_no, 'SELECTED_SUPPLIER', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.rfq_supplier_selected'));
    }

    public function convertQuotationToPOAction(Request $request, string $rfq)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $quotationRow = DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate(['po_date' => ['required', 'date'], 'expected_delivery_date' => ['nullable', 'date']]);

        $result = CbeAccountingService::convertPurchaseQuotationToPO($rfq, $agent->agent_id, $request->input('po_date'), $request->input('expected_delivery_date') ?: null);

        if (! $result['ok']) {
            return back()->with('error', __('cbe_accounting.rfq_error_convert_failed'));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'RFQ', $rfq, $quotationRow->doc_ref_no, 'CONVERTED', $agent->agent_id, 'Converted to Purchase Order');

        return redirect()->route('cbe.accounting.purchase-orders.show', $result['po_id'])->with('success', __('cbe_accounting.rfq_converted_to_po'));
    }

    public function cancelQuotationAction(string $rfq)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $quotation = DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($quotation->status === 'AWARDED') {
            return back()->with('error', __('cbe_accounting.rfq_error_already_awarded'));
        }
        DB::table('cbe_purchase_rfqs')->where('rfq_id', $rfq)->update(['status' => 'CANCELLED', 'updated_at' => now()]);

        CbeAccountingService::logPurchasingAudit($nodeId, 'RFQ', $rfq, $quotation->doc_ref_no, 'CANCELLED', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.rfq_cancelled'));
    }

    // ---------- Goods / Service Receipt (NEW 4 Sep 2026, Task #394 Phase 2) ----------
    public function goodsReceipts()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $grns = DB::table('cbe_goods_receipts as g')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'g.supplier_id')
            ->join('cbe_purchase_orders as po', 'po.po_id', '=', 'g.po_id')
            ->where('g.cbe_node_id', $nodeId)
            ->select('g.*', 's.supplier_name', 'po.doc_ref_no as po_doc_ref_no')
            ->orderByDesc('g.grn_date')
            ->paginate(8, ['*'], 'grnPage');

        return view('cbe.accounting.goods-receipts', compact('grns'));
    }

    // Entry point is always "Receive Goods" from an open, approved PO's
    // show screen — a GRN never exists without a PO to receive against.
    public function createGoodsReceipt(string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $order = DB::table('cbe_purchase_orders')->where('po_id', $po)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($order->approval_status !== 'APPROVED' || ! in_array($order->status, ['OPEN', 'PARTIALLY_RECEIVED'], true)) {
            return redirect()->route('cbe.accounting.purchase-orders.show', $po)->with('error', __('cbe_accounting.error_po_not_open'));
        }

        $lines = DB::table('cbe_purchase_order_lines')->where('po_id', $po)->orderBy('display_order')->get()
            ->map(function ($l) {
                $l->outstanding_quantity = round((float) $l->quantity - (float) $l->received_quantity, 2);
                return $l;
            })
            ->filter(fn ($l) => $l->outstanding_quantity > 0)->values();

        if ($lines->isEmpty()) {
            return redirect()->route('cbe.accounting.purchase-orders.show', $po)->with('error', __('cbe_accounting.grn_error_nothing_outstanding'));
        }

        return view('cbe.accounting.create-goods-receipt', compact('order', 'lines'));
    }

    public function storeGoodsReceipt(Request $request, string $po)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_purchase_orders')->where('po_id', $po)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'grn_date' => ['required', 'date'],
            'receipt_type' => ['required', 'in:GOODS,SERVICE'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'po_line_id.*' => ['nullable', 'uuid'],
            'received_qty.*' => ['nullable', 'numeric', 'min:0'],
            'rejected_qty.*' => ['nullable', 'numeric', 'min:0'],
            'condition_note.*' => ['nullable', 'string', 'max:20'],
        ]);

        $poLineIds = $request->input('po_line_id', []);
        $receivedQtys = $request->input('received_qty', []);
        $rejectedQtys = $request->input('rejected_qty', []);
        $conditions = $request->input('condition_note', []);
        $descriptions = $request->input('line_description', []);
        $orderedQtys = $request->input('ordered_qty', []);

        $lineRows = [];
        foreach ($poLineIds as $i => $poLineId) {
            $receivedQty = (float) ($receivedQtys[$i] ?? 0);
            if ($receivedQty <= 0) {
                continue;
            }
            $lineRows[] = [
                'po_line_id' => $poLineId,
                'description' => $descriptions[$i] ?? null,
                'ordered_quantity' => (float) ($orderedQtys[$i] ?? 0),
                'received_quantity' => $receivedQty,
                'rejected_quantity' => (float) ($rejectedQtys[$i] ?? 0),
                'condition_note' => $conditions[$i] ?: 'GOOD',
            ];
        }

        if (empty($lineRows)) {
            return back()->withInput()->with('error', __('cbe_accounting.grn_error_min_lines'));
        }

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-goods-receipts', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        $result = CbeAccountingService::createGoodsReceipt(
            $po, $request->input('grn_date'), $request->input('receipt_type'),
            $request->input('remarks'), $attachmentPath, $attachmentName, $agent->agent_id, $lineRows
        );

        if (! $result['ok']) {
            return back()->withInput()->with('error', __('cbe_accounting.error_po_not_open'));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'GRN', $result['grn_id'], $result['doc_ref_no'], 'RECEIVED', $agent->agent_id);

        return redirect()->route('cbe.accounting.goods-receipts.show', $result['grn_id'])->with('success', __('cbe_accounting.goods_receipt_saved'));
    }

    public function showGoodsReceipt(string $grn)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $receipt = DB::table('cbe_goods_receipts as g')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'g.supplier_id')
            ->join('cbe_purchase_orders as po', 'po.po_id', '=', 'g.po_id')
            ->where('g.grn_id', $grn)->where('g.cbe_node_id', $nodeId)
            ->select('g.*', 's.supplier_name', 'po.doc_ref_no as po_doc_ref_no')
            ->firstOrFail();

        $lines = DB::table('cbe_goods_receipt_lines')->where('grn_id', $grn)->orderBy('display_order')->get();

        return view('cbe.accounting.show-goods-receipt', compact('receipt', 'lines'));
    }

    public function cancelGoodsReceiptAction(string $grn)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_goods_receipts')->where('grn_id', $grn)->where('cbe_node_id', $nodeId)->firstOrFail();

        $result = CbeAccountingService::cancelGoodsReceipt($grn);
        if (! $result['ok']) {
            return back()->with('error', __('cbe_accounting.error_approval_failed'));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'GRN', $grn, $result['doc_ref_no'], 'CANCELLED', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.goods_receipt_cancelled'));
    }

    // ---------- Purchase Return (NEW 4 Sep 2026, Task #394 Phase 2) ----------
    // Entry point is always a confirmed Goods Receipt — a return needs to
    // reference what was actually received, not just what was ordered.
    public function purchaseReturns()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $returns = DB::table('cbe_purchase_returns as r')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'r.supplier_id')
            ->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 's.supplier_name')
            ->orderByDesc('r.return_date')
            ->paginate(8, ['*'], 'returnPage');

        return view('cbe.accounting.purchase-returns', compact('returns'));
    }

    public function createPurchaseReturn(string $grn)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $receipt = DB::table('cbe_goods_receipts')->where('grn_id', $grn)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($receipt->status === 'CANCELLED') {
            return redirect()->route('cbe.accounting.goods-receipts.show', $grn)->with('error', __('cbe_accounting.error_approval_failed'));
        }

        $lines = DB::table('cbe_goods_receipt_lines')->where('grn_id', $grn)->orderBy('display_order')->get();

        return view('cbe.accounting.create-purchase-return', compact('receipt', 'lines'));
    }

    public function storePurchaseReturn(Request $request, string $grn)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $receipt = DB::table('cbe_goods_receipts')->where('grn_id', $grn)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'return_qty.*' => ['nullable', 'numeric', 'min:0'],
            'unit_price.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $descriptions = $request->input('line_description', []);
        $qtys = $request->input('return_qty', []);
        $unitPrices = $request->input('unit_price', []);

        $lineRows = [];
        foreach ($qtys as $i => $qty) {
            $qty = (float) $qty;
            if ($qty <= 0) {
                continue;
            }
            $unitPrice = (float) ($unitPrices[$i] ?? 0);
            $lineRows[] = [
                'description' => $descriptions[$i] ?? null,
                'return_quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_amount' => round($qty * $unitPrice, 2),
                'display_order' => $i,
            ];
        }

        if (empty($lineRows)) {
            return back()->withInput()->with('error', __('cbe_accounting.return_error_min_lines'));
        }

        $returnId = (string) Str::uuid();
        $docRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'PRN', (int) \Carbon\Carbon::parse($request->input('return_date'))->year, 'PRN', (int) \Carbon\Carbon::parse($request->input('return_date'))->month);

        DB::table('cbe_purchase_returns')->insert([
            'return_id' => $returnId,
            'doc_ref_no' => $docRefNo,
            'cbe_node_id' => $nodeId,
            'po_id' => $receipt->po_id,
            'grn_id' => $grn,
            'supplier_id' => $receipt->supplier_id,
            'return_date' => $request->input('return_date'),
            'reason' => $request->input('reason'),
            'status' => 'CONFIRMED',
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lineRows as $row) {
            DB::table('cbe_purchase_return_lines')->insert(array_merge($row, [
                'return_line_id' => (string) Str::uuid(),
                'return_id' => $returnId,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'PRN', $returnId, $docRefNo, 'CREATED', $agent->agent_id);

        return redirect()->route('cbe.accounting.purchase-returns.show', $returnId)->with('success', __('cbe_accounting.purchase_return_saved'));
    }

    public function showPurchaseReturn(string $return)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $purchaseReturn = DB::table('cbe_purchase_returns as r')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'r.supplier_id')
            ->leftJoin('cbe_goods_receipts as g', 'g.grn_id', '=', 'r.grn_id')
            ->leftJoin('cbe_purchase_orders as po', 'po.po_id', '=', 'r.po_id')
            ->leftJoin('cbe_debit_notes as dn', 'dn.debit_note_id', '=', 'r.ap_credit_note_id')
            ->where('r.return_id', $return)->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 's.supplier_name', 'g.doc_ref_no as grn_doc_ref_no', 'po.doc_ref_no as po_doc_ref_no', 'dn.doc_ref_no as dn_doc_ref_no')
            ->firstOrFail();

        $lines = DB::table('cbe_purchase_return_lines')->where('return_id', $return)->orderBy('display_order')->get();

        return view('cbe.accounting.show-purchase-return', compact('purchaseReturn', 'lines'));
    }

    public function cancelPurchaseReturnAction(string $return)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $purchaseReturn = DB::table('cbe_purchase_returns')->where('return_id', $return)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($purchaseReturn->status === 'CREDIT_RAISED') {
            return back()->with('error', __('cbe_accounting.return_error_credit_already_raised'));
        }
        DB::table('cbe_purchase_returns')->where('return_id', $return)->update(['status' => 'CANCELLED', 'updated_at' => now()]);

        CbeAccountingService::logPurchasingAudit($nodeId, 'PRN', $return, $purchaseReturn->doc_ref_no, 'CANCELLED', $agent->agent_id);

        return back()->with('success', __('cbe_accounting.purchase_return_cancelled'));
    }

    // ---------- Supplier Invoice 3-Way Matching (NEW 4 Sep 2026, Task #394 Phase 2) ----------
    // Entry point is a confirmed Goods Receipt — this is the proper
    // 3-way-matched path into AP (PO + GRN + Invoice), distinct from the
    // quicker direct PO->Bill conversion (Task #393) used when a temple
    // doesn't need line-level matching for a small/simple purchase.
    public function createSupplierInvoiceFromGrn(string $grn)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $receipt = DB::table('cbe_goods_receipts')->where('grn_id', $grn)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($receipt->status !== 'CONFIRMED') {
            return redirect()->route('cbe.accounting.goods-receipts.show', $grn)->with('error', __('cbe_accounting.error_approval_failed'));
        }

        $lines = DB::table('cbe_goods_receipt_lines as gl')
            ->leftJoin('cbe_purchase_order_lines as pl', 'pl.line_id', '=', 'gl.po_line_id')
            ->where('gl.grn_id', $grn)
            ->select('gl.*', 'pl.unit_price as po_unit_price', 'pl.category_id')
            ->orderBy('gl.display_order')->get();

        return view('cbe.accounting.create-supplier-invoice', compact('receipt', 'lines'));
    }

    public function storeSupplierInvoiceFromGrn(Request $request, string $grn)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_goods_receipts')->where('grn_id', $grn)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'bill_no' => ['nullable', 'string', 'max:60'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'grn_line_id.*' => ['nullable', 'uuid'],
            'po_line_id.*' => ['nullable', 'uuid'],
            'invoiced_qty.*' => ['nullable', 'numeric', 'min:0'],
            'invoiced_unit_price.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('bill_date'))) {
            return $guard;
        }

        $grnLineIds = $request->input('grn_line_id', []);
        $poLineIds = $request->input('po_line_id', []);
        $qtys = $request->input('invoiced_qty', []);
        $unitPrices = $request->input('invoiced_unit_price', []);
        $descriptions = $request->input('line_description', []);
        $categoryIds = $request->input('line_category_id', []);

        $lineRows = [];
        foreach ($grnLineIds as $i => $grnLineId) {
            $qty = (float) ($qtys[$i] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $lineRows[] = [
                'grn_line_id' => $grnLineId,
                'po_line_id' => $poLineIds[$i] ?? null,
                'description' => $descriptions[$i] ?? null,
                'category_id' => $categoryIds[$i] ?: null,
                'invoiced_quantity' => $qty,
                'invoiced_unit_price' => (float) ($unitPrices[$i] ?? 0),
            ];
        }

        if (empty($lineRows)) {
            return back()->withInput()->with('error', __('cbe_accounting.bill_error_min_lines'));
        }

        $result = CbeAccountingService::createBillFromGoodsReceipt(
            $grn, $request->input('bill_no') ?: null, $request->input('bill_date'),
            $request->input('due_date') ?: null, $agent->agent_id, $lineRows
        );

        if (! $result['ok']) {
            $errorKey = $result['error'] === 'duplicate_invoice' ? 'error_duplicate_invoice_no' : 'bill_error_min_lines';
            return back()->withInput()->with('error', __('cbe_accounting.'.$errorKey));
        }

        CbeAccountingService::logPurchasingAudit($nodeId, 'BILL', $result['bill_id'], $result['doc_ref_no'], 'MATCHED', $agent->agent_id, 'Matched: '.$result['match_status']);

        $successKey = $result['match_status'] === 'MATCHED' ? 'supplier_invoice_saved_matched' : 'supplier_invoice_saved_variance';
        return redirect()->route('cbe.accounting.bill-enquiry.show', $result['bill_id'])->with('success', __('cbe_accounting.'.$successKey));
    }

    // ---------- Budget & Purchase Commitment (NEW 4 Sep 2026, Task #394 Phase 2) ----------
    // "Light" budgeting, same pattern as Fund Accounting (Task #330) — one
    // budget figure per node/year/scope entered once, with commitment and
    // actual figures computed live at report time rather than a full
    // budget-versioning subsystem. Scoped by Cost Centre/Department/
    // Project and/or Fund (both optional, either narrows the budget);
    // account_id is descriptive only (which expense account this budget
    // line is for) since Purchase Orders/Bills are tagged by Cost Centre
    // and Fund directly, not by GL account.
    public function budgets()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $budgets = DB::table('cbe_purchase_budgets as b')
            ->leftJoin('cbe_chart_of_accounts as a', 'a.account_id', '=', 'b.account_id')
            ->leftJoin('cbe_cost_centres as cc', 'cc.centre_id', '=', 'b.cost_centre_id')
            ->leftJoin('cbe_funds as f', 'f.fund_id', '=', 'b.fund_id')
            ->where('b.cbe_node_id', $nodeId)
            ->select('b.*', 'a.account_name', 'cc.centre_name', 'f.fund_name')
            ->orderByDesc('b.fiscal_year')->orderBy('cc.centre_name')
            ->paginate(8, ['*'], 'budgetPage');

        return view('cbe.accounting.budgets', compact('budgets'));
    }

    public function createBudget()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.budgets.create', __('cbe_accounting.add_budget_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)->where('is_active', true)->orderBy('account_code')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        return view('cbe.accounting.create-budget', compact('accounts', 'costCentres', 'funds'));
    }

    public function storeBudget(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'budget_name' => ['nullable', 'string', 'max:150'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
        ]);

        DB::table('cbe_purchase_budgets')->insert([
            'budget_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'fiscal_year' => $request->input('fiscal_year'),
            'account_id' => $request->input('account_id') ?: null,
            'cost_centre_id' => $request->input('cost_centre_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'budget_name' => $request->input('budget_name'),
            'budget_amount' => round((float) $request->input('budget_amount'), 2),
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.budgets')->with('success', __('cbe_accounting.budget_saved'));
    }

    public function editBudget(string $budget)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $row = DB::table('cbe_purchase_budgets')->where('budget_id', $budget)->where('cbe_node_id', $nodeId)->firstOrFail();
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)->where('is_active', true)->orderBy('account_code')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        return view('cbe.accounting.edit-budget', compact('row', 'accounts', 'costCentres', 'funds'));
    }

    public function updateBudget(Request $request, string $budget)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_purchase_budgets')->where('budget_id', $budget)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'budget_name' => ['nullable', 'string', 'max:150'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
        ]);

        DB::table('cbe_purchase_budgets')->where('budget_id', $budget)->update([
            'fiscal_year' => $request->input('fiscal_year'),
            'account_id' => $request->input('account_id') ?: null,
            'cost_centre_id' => $request->input('cost_centre_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'budget_name' => $request->input('budget_name'),
            'budget_amount' => round((float) $request->input('budget_amount'), 2),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.budgets')->with('success', __('cbe_accounting.budget_saved'));
    }

    // Budget Utilisation / Purchase Commitment report (spec sections
    // 17-18): for each budget line, Approved-but-unbilled PO amount is
    // "PO Commitment"; once a PO converts to a Bill it leaves Commitment
    // and its amount appears in Actual Invoice instead — so the two never
    // double-count the same spend.
    public function budgetUtilisationReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) $request->input('year', now()->year);

        $budgets = DB::table('cbe_purchase_budgets as b')
            ->leftJoin('cbe_cost_centres as cc', 'cc.centre_id', '=', 'b.cost_centre_id')
            ->leftJoin('cbe_funds as f', 'f.fund_id', '=', 'b.fund_id')
            ->where('b.cbe_node_id', $nodeId)->where('b.fiscal_year', $year)
            ->select('b.*', 'cc.centre_name', 'f.fund_name')
            ->orderBy('cc.centre_name')->get();

        $rows = $budgets->map(function ($b) use ($nodeId, $year) {
            $poQuery = DB::table('cbe_purchase_orders')
                ->where('cbe_node_id', $nodeId)
                ->whereYear('po_date', $year)
                ->whereNotIn('status', ['CLOSED', 'CANCELLED'])
                ->where('approval_status', 'APPROVED');
            $billQuery = DB::table('cbe_purchase_bills')
                ->where('cbe_node_id', $nodeId)
                ->whereYear('bill_date', $year)
                ->where('status', '!=', 'CANCELLED');
            if ($b->cost_centre_id) {
                $poQuery->where('cost_centre_id', $b->cost_centre_id);
                $billQuery->where('cost_centre_id', $b->cost_centre_id);
            }
            if ($b->fund_id) {
                $poQuery->where('fund_id', $b->fund_id);
                $billQuery->where('fund_id', $b->fund_id);
            }

            $commitment = (float) $poQuery->sum('amount');
            $actualInvoice = (float) $billQuery->sum('amount');
            $actualPayment = (float) (clone $billQuery)->sum('paid_amount');
            $remaining = round((float) $b->budget_amount - $commitment - $actualInvoice, 2);
            $utilisation = (float) $b->budget_amount > 0 ? round(($commitment + $actualInvoice) / (float) $b->budget_amount * 100, 1) : 0;

            return (object) [
                'budget_name' => $b->budget_name, 'centre_name' => $b->centre_name, 'fund_name' => $b->fund_name,
                'budget_amount' => (float) $b->budget_amount, 'commitment' => $commitment,
                'actual_invoice' => $actualInvoice, 'actual_payment' => $actualPayment,
                'remaining' => $remaining, 'utilisation' => $utilisation,
            ];
        });

        return view('cbe.accounting.budget-utilisation-report', compact('rows', 'year'));
    }

    // ---------- Purchasing Reports suite (spec section 17-18), NEW 4 Sep
    // 2026 (Task #394) — downloadable Excel reports, same family and
    // writeSheet()/download() pattern as the AR/AP/GL report methods
    // above. Supplier Purchase History and Purchase by Category are
    // already covered by expenseSummaryBySupplierReport() /
    // expenseSummaryByCategoryReport() in the AP Reports Hub — not
    // duplicated here.
    public function purchaseRequisitionListingReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $rows = DB::table('cbe_purchase_requests as r')
            ->join('agents as a', 'a.agent_id', '=', 'r.requested_by')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'r.supplier_id')
            ->where('r.cbe_node_id', $nodeId)
            ->whereBetween('r.request_date', [$from, $to])
            ->select('r.*', 'a.full_name as requested_by_name', 's.supplier_name')
            ->orderBy('r.request_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Purchase Requisition Listing'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Requisition No.', 'Date', 'Requester', 'Supplier', 'Purpose', 'Amount (RM)', 'Status'];
        $prStatusLabels = [
            'PENDING' => __('cbe_records.status_pending'), 'APPROVED' => __('cbe_records.status_approved'),
            'REJECTED' => __('cbe_records.status_rejected'), 'CONVERTED' => __('cbe_accounting.status_converted'),
            'CONVERTED_TO_PO' => __('cbe_accounting.status_converted_to_po'),
        ];
        $total = 0;
        foreach ($rows as $r) {
            $sheet[] = [
                $r->doc_ref_no ?: '—', \Carbon\Carbon::parse($r->request_date)->format('d M Y'),
                $r->requested_by_name, $r->supplier_name ?: '—', $r->description ?: '—',
                round((float) $r->amount, 2), $prStatusLabels[$r->status] ?? $r->status,
            ];
            $total += (float) $r->amount;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', '', '', round($total, 2), ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Requisition Listing', $sheet);
        return $this->download($spreadsheet, 'Purchase_Requisition_Listing');
    }

    public function cancelledPurchaseOrdersReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $rows = DB::table('cbe_purchase_orders as po')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'po.supplier_id')
            ->where('po.cbe_node_id', $nodeId)
            ->where('po.status', 'CANCELLED')
            ->select('po.*', 's.supplier_name')
            ->orderByDesc('po.updated_at')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Cancelled Purchase Orders'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['PO No.', 'PO Date', 'Supplier', 'Amount (RM)', 'Purpose', 'Cancelled On'];
        foreach ($rows as $po) {
            $sheet[] = [
                $po->doc_ref_no ?: '—', \Carbon\Carbon::parse($po->po_date)->format('d M Y'), $po->supplier_name,
                round((float) $po->amount, 2), $po->description ?: '—',
                \Carbon\Carbon::parse($po->updated_at)->format('d M Y'),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Cancelled POs', $sheet);
        return $this->download($spreadsheet, 'Cancelled_Purchase_Orders');
    }

    public function purchaseReturnListingReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $rows = DB::table('cbe_purchase_returns as ret')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'ret.supplier_id')
            ->leftJoin('cbe_purchase_orders as po', 'po.po_id', '=', 'ret.po_id')
            ->leftJoin('cbe_debit_notes as dn', 'dn.debit_note_id', '=', 'ret.ap_credit_note_id')
            ->where('ret.cbe_node_id', $nodeId)
            ->whereBetween('ret.return_date', [$from, $to])
            ->select('ret.*', 's.supplier_name', 'po.doc_ref_no as po_ref', 'dn.doc_ref_no as dn_ref')
            ->orderBy('ret.return_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Purchase Return Listing'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Return No.', 'Date', 'Supplier', 'Against PO', 'Reason', 'Amount (RM)', 'Status', 'Debit Note'];
        $total = 0;
        foreach ($rows as $r) {
            $amount = (float) DB::table('cbe_purchase_return_lines')->where('return_id', $r->return_id)->sum('line_amount');
            $sheet[] = [
                $r->doc_ref_no ?: '—', \Carbon\Carbon::parse($r->return_date)->format('d M Y'), $r->supplier_name,
                $r->po_ref ?: '—', $r->reason ?: '—', round($amount, 2),
                __('cbe_accounting.return_status_'.strtolower($r->status)), $r->dn_ref ?: '—',
            ];
            $total += $amount;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', '', '', round($total, 2), '', ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Return Listing', $sheet);
        return $this->download($spreadsheet, 'Purchase_Return_Listing');
    }

    public function poInvoiceMatchingVarianceReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $rows = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->leftJoin('cbe_purchase_orders as po', 'po.po_id', '=', 'b.po_id')
            ->leftJoin('cbe_goods_receipts as g', 'g.grn_id', '=', 'b.grn_id')
            ->where('b.cbe_node_id', $nodeId)
            ->where('b.status', '!=', 'CANCELLED')
            ->whereNotIn('b.match_status', ['MATCHED', 'NOT_APPLICABLE'])
            ->whereBetween('b.bill_date', [$from, $to])
            ->select('b.*', 's.supplier_name', 'po.doc_ref_no as po_ref', 'g.doc_ref_no as grn_ref')
            ->orderBy('b.bill_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — PO / GRN / Invoice Matching Variance'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Bill No.', 'Bill Date', 'Supplier', 'PO No.', 'GRN No.', 'Amount (RM)', 'Match Status'];
        foreach ($rows as $r) {
            $sheet[] = [
                $r->bill_no ?: '—', \Carbon\Carbon::parse($r->bill_date)->format('d M Y'), $r->supplier_name,
                $r->po_ref ?: '—', $r->grn_ref ?: '—', round((float) $r->amount, 2),
                __('cbe_accounting.match_status_'.strtolower($r->match_status)),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Matching Variance', $sheet);
        return $this->download($spreadsheet, 'PO_Invoice_Matching_Variance');
    }

    public function unbilledGoodsReceivedReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $rows = DB::table('cbe_goods_receipts as g')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'g.supplier_id')
            ->join('cbe_purchase_orders as po', 'po.po_id', '=', 'g.po_id')
            ->where('g.cbe_node_id', $nodeId)
            ->where('g.status', 'CONFIRMED')
            ->select('g.*', 's.supplier_name', 'po.doc_ref_no as po_ref')
            ->orderBy('g.grn_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Unbilled Goods / Service Received'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['GRN No.', 'Receipt Date', 'Type', 'Supplier', 'Against PO', 'Remarks'];
        foreach ($rows as $r) {
            $sheet[] = [
                $r->doc_ref_no ?: '—', \Carbon\Carbon::parse($r->grn_date)->format('d M Y'),
                __('cbe_accounting.grn_type_'.strtolower($r->receipt_type)), $r->supplier_name,
                $r->po_ref ?: '—', $r->remarks ?: '—',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Unbilled GRN', $sheet);
        return $this->download($spreadsheet, 'Unbilled_Goods_Received');
    }

    public function pendingApprovalListingReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $prs = DB::table('cbe_purchase_requests as r')
            ->join('agents as a', 'a.agent_id', '=', 'r.requested_by')
            ->where('r.cbe_node_id', $nodeId)->where('r.status', 'PENDING')
            ->select('r.*', 'a.full_name as requested_by_name')
            ->orderBy('r.request_date')->get();

        $pos = DB::table('cbe_purchase_orders as po')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'po.supplier_id')
            ->where('po.cbe_node_id', $nodeId)->where('po.approval_status', 'PENDING_APPROVAL')
            ->select('po.*', 's.supplier_name')
            ->orderBy('po.po_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Pending Approval Listing'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Type', 'Document No.', 'Date', 'Requester / Supplier', 'Purpose', 'Amount (RM)'];
        foreach ($prs as $r) {
            $sheet[] = ['Purchase Requisition', $r->doc_ref_no ?: '—', \Carbon\Carbon::parse($r->request_date)->format('d M Y'), $r->requested_by_name, $r->description ?: '—', round((float) $r->amount, 2)];
        }
        foreach ($pos as $po) {
            $sheet[] = ['Purchase Order', $po->doc_ref_no ?: '—', \Carbon\Carbon::parse($po->po_date)->format('d M Y'), $po->supplier_name, $po->description ?: '—', round((float) $po->amount, 2)];
        }
        if ($prs->isEmpty() && $pos->isEmpty()) {
            $sheet[] = ['No documents currently pending approval.'];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Pending Approval', $sheet);
        return $this->download($spreadsheet, 'Pending_Approval_Listing');
    }

    public function purchaseByDimensionReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();
        $dimension = $request->input('dimension') === 'fund' ? 'fund' : 'cost_centre';

        if ($dimension === 'fund') {
            $rows = DB::table('cbe_purchase_bills as b')
                ->leftJoin('cbe_funds as d', 'd.fund_id', '=', 'b.fund_id')
                ->where('b.cbe_node_id', $nodeId)->where('b.status', '!=', 'CANCELLED')
                ->whereBetween('b.bill_date', [$from, $to])
                ->groupBy('d.fund_id', 'd.fund_name')
                ->select(DB::raw("COALESCE(d.fund_name, 'Unassigned') as dim_name"), DB::raw('SUM(b.amount) as total'), DB::raw('COUNT(*) as bill_count'))
                ->orderByDesc('total')->get();
            $title = 'Purchase by Fund';
            $colLabel = 'Fund';
        } else {
            $rows = DB::table('cbe_purchase_bills as b')
                ->leftJoin('cbe_cost_centres as d', 'd.centre_id', '=', 'b.cost_centre_id')
                ->where('b.cbe_node_id', $nodeId)->where('b.status', '!=', 'CANCELLED')
                ->whereBetween('b.bill_date', [$from, $to])
                ->groupBy('d.centre_id', 'd.centre_name')
                ->select(DB::raw("COALESCE(d.centre_name, 'Unassigned') as dim_name"), DB::raw('SUM(b.amount) as total'), DB::raw('COUNT(*) as bill_count'))
                ->orderByDesc('total')->get();
            $title = 'Purchase by Department / Project';
            $colLabel = 'Department / Project';
        }

        $sheet = [];
        $sheet[] = ["GeneralLink Digital Ecosystem — {$title}"];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = [$colLabel, 'No. of Bills', 'Total Purchase (RM)'];
        $total = 0;
        foreach ($rows as $r) {
            $sheet[] = [$r->dim_name, (int) $r->bill_count, round((float) $r->total, 2)];
            $total += (float) $r->total;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', round($total, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, $title, $sheet);
        return $this->download($spreadsheet, str_replace(' / ', '_', str_replace(' ', '_', $title)));
    }

    // ---------- Funds (Fund Accounting, NEW 2 Sep 2026, Task #330) ----------
    // "Light" fund accounting — see the migration comment on cbe_funds
    // for why this tags cbe_transactions rather than splitting the
    // formal Fund Balance GL account per fund. Funds are scoped per
    // node, same reasoning as Tax Rates.

    public function funds()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)
            ->orderByDesc('is_active')->orderBy('fund_name')->paginate(8, ['*'], 'fundPage');

        return view('cbe.accounting.funds', compact('funds'));
    }

    public function storeFund(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'fund_name' => ['required', 'string', 'max:150'],
            'fund_type' => ['required', 'in:RESTRICTED,UNRESTRICTED'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('cbe_funds')->insert([
            'fund_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'fund_name' => $request->input('fund_name'),
            'fund_type' => $request->input('fund_type'),
            'description' => $request->input('description'),
            'is_active' => true,
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.funds')->with('success', __('cbe_accounting.fund_saved'));
    }

    public function deactivateFund(string $fundId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_funds')->where('fund_id', $fundId)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', __('cbe_accounting.fund_deactivated'));
    }

    // Ledger-posting donation entry, distinct from the Donor Register's
    // pledge/contribution tracking (cbe_contributions), which is
    // deliberately NOT posted to the journal — see the header comment
    // on CbeAccountingService for why. This is for a donation that's
    // simply cash/bank money in today, optionally tagged to a Fund.
    public function createDonationEntry()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.donation-entry.create', __('cbe_accounting.add_donation_entry_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'INCOME')->where('is_active', true)
            ->orderBy('display_order')->get();
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.accounting.create-donation-entry', compact('funds', 'categories', 'bankAccounts'));
    }

    public function storeDonationEntry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'donor_name' => ['nullable', 'string', 'max:150'],
            'category_id' => ['required', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('transaction_date'))) {
            return $guard;
        }

        $donorName = $request->input('donor_name');
        $note = $request->input('description');
        $description = $donorName
            ? ('Donation — '.$donorName.($note ? ' — '.$note : ''))
            : ($note ?: 'Donation');

        $transactionId = (string) Str::uuid();
        DB::table('cbe_transactions')->insert([
            'transaction_id' => $transactionId,
            'cbe_node_id' => $nodeId,
            'bank_account_id' => $request->input('bank_account_id') ?: null,
            'bank_statement_id' => null,
            'category_id' => $request->input('category_id'),
            'fund_id' => $request->input('fund_id') ?: null,
            'transaction_date' => $request->input('transaction_date'),
            'description' => $description,
            'amount' => round((float) $request->input('amount'), 2),
            'entered_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postTransaction($transactionId);

        return redirect()->route('cbe.accounting.index')->with('success', __('cbe_accounting.donation_entry_saved'));
    }

    // ---------- Donation Pledge (NEW 3 Sep 2026, Task #366) ----------
    // A donor's promise to give a specific amount — GL-integrated and
    // donor-scoped, deliberately separate from the event-scoped
    // cbe_contributions register (Donor Register's own Log Contribution
    // screen), which is NOT individually posted to GL. Behaves like an
    // AR Invoice but for a donor: pledging posts a receivable straight
    // away, each amount received against it posts Dr Bank / Cr Pledges
    // Receivable, tracked through its own GL control account so it
    // never mixes with the AR Trade Debtors balance.

    public function donationPledges(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $query = DB::table('cbe_donation_pledges as p')
            ->join('cbe_donors as d', 'd.donor_id', '=', 'p.donor_id')
            ->where('p.cbe_node_id', $nodeId);
        if ($search = $request->input('search')) {
            $query->where('d.donor_name', 'like', '%'.$search.'%');
        }
        $pledges = $query->select('p.*', 'd.donor_name')->orderByDesc('p.pledge_date')
            ->paginate(8, ['*'], 'pledgePage')->withQueryString();

        return view('cbe.accounting.donation-pledges', compact('pledges'));
    }

    public function createDonationPledge()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $donors = DB::table('cbe_donors')->where('cbe_node_id', $nodeId)->orderBy('donor_name')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'INCOME')->where('is_active', true)
            ->orderBy('display_order')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        return view('cbe.accounting.create-donation-pledge', compact('donors', 'categories', 'funds'));
    }

    public function storeDonationPledge(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'donor_id' => ['required', 'uuid', 'exists:cbe_donors,donor_id'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'pledge_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('pledge_date'))) {
            return $guard;
        }

        $pledgeId = (string) Str::uuid();
        $pledgeNo = CbeAccountingService::nextDocumentNumber($nodeId, 'PLEDGE', (int) \Carbon\Carbon::parse($request->input('pledge_date'))->year, 'PLG', (int) \Carbon\Carbon::parse($request->input('pledge_date'))->month);
        DB::table('cbe_donation_pledges')->insert([
            'pledge_id' => $pledgeId,
            'cbe_node_id' => $nodeId,
            'donor_id' => $request->input('donor_id'),
            'category_id' => $request->input('category_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'pledge_no' => $pledgeNo,
            'pledge_date' => $request->input('pledge_date'),
            'due_date' => $request->input('due_date') ?: null,
            'amount' => round((float) $request->input('amount'), 2),
            'notes' => $request->input('notes'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postDonationPledge($pledgeId);

        return redirect()->route('cbe.accounting.donation-pledges')->with('success', __('cbe_accounting.donation_pledge_saved'));
    }

    public function donationPledgeShow(string $pledge)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $row = DB::table('cbe_donation_pledges as p')
            ->join('cbe_donors as d', 'd.donor_id', '=', 'p.donor_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'p.category_id')
            ->leftJoin('cbe_funds as f', 'f.fund_id', '=', 'p.fund_id')
            ->where('p.cbe_node_id', $nodeId)->where('p.pledge_id', $pledge)
            ->select('p.*', 'd.donor_name', 'd.phone', 'd.email', 'c.category_name', 'f.fund_name')
            ->firstOrFail();

        $receipts = DB::table('cbe_pledge_receipts as r')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.pledge_id', $pledge)
            ->select('r.*', 'b.bank_name')
            ->orderByDesc('r.receipt_date')->get();

        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.accounting.donation-pledge-show', compact('row', 'receipts', 'bankAccounts'));
    }

    public function storePledgeReceipt(Request $request, string $pledge)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $row = DB::table('cbe_donation_pledges')->where('cbe_node_id', $nodeId)->where('pledge_id', $pledge)->firstOrFail();

        $request->validate([
            'receipt_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('receipt_date'))) {
            return $guard;
        }

        $receiptId = (string) Str::uuid();
        $receiptNo = CbeAccountingService::nextDocumentNumber($nodeId, 'RCPT', (int) \Carbon\Carbon::parse($request->input('receipt_date'))->year, 'OR', (int) \Carbon\Carbon::parse($request->input('receipt_date'))->month);
        DB::table('cbe_pledge_receipts')->insert([
            'receipt_id' => $receiptId,
            'pledge_id' => $row->pledge_id,
            'receipt_no' => $receiptNo,
            'receipt_date' => $request->input('receipt_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'bank_account_id' => $request->input('bank_account_id') ?: null,
            'payment_method' => $request->input('payment_method'),
            'reference_no' => $request->input('reference_no'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postPledgeReceipt($receiptId);

        return redirect()->route('cbe.accounting.donation-pledges.show', $row->pledge_id)->with('success', __('cbe_accounting.pledge_receipt_saved'));
    }

    // REBUILT 3 Sep 2026 (Task #389) — per Chris's GL spec (AR Module,
    // Fund Balance Report section): "Per fund: Opening Balance, Income,
    // Expenditure, Transfers In/Out, Closing Balance — this is the
    // Statement of Changes in Fund Balance, not a single 'fund total'
    // number." The old version was a single all-time cumulative
    // snapshot sourced only from cbe_transactions (the cashbook), so it
    // silently missed anything posted through a manual Journal Voucher
    // (now that Task #389 lets a JV line tag a Fund, this report has to
    // read cbe_journal_lines too, or a fund correction/adjustment JV
    // would never show up here). Now: one fiscal year at a time
    // (?year=, defaults to the current year), Opening Balance is every
    // prior year's net movement rolled forward, and Income/Expenditure
    // are this year's movement only, from BOTH the cashbook and any
    // fund-tagged journal line. Transfers In/Out is included as its own
    // column per the spec's format, currently always zero — there is no
    // dedicated fund-to-fund transfer feature yet (flagged separately;
    // a fund correction today is entered as an income/expense pair via
    // Journal Voucher, which nets to the same closing balance either
    // way, just not split out as a distinct "transfer" line).
    public function fundBalanceReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) ($request->input('year') ?: now()->year);
        $yearStart = "{$year}-01-01";
        $yearEnd = "{$year}-12-31";

        $untaggedLabel = __('cbe_accounting.fund_untagged');

        // Every fund this node has, plus a synthetic "Untagged/General"
        // bucket, so a fund with zero movement THIS year (but a real
        // opening balance from prior years) still appears.
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->orderBy('fund_name')->get();
        $buckets = [];
        foreach ($funds as $f) {
            $buckets[$f->fund_id] = ['name' => $f->fund_name, 'type' => $f->fund_type, 'opening' => 0.0, 'income' => 0.0, 'expense' => 0.0];
        }
        $buckets['__untagged__'] = ['name' => $untaggedLabel, 'type' => '—', 'opening' => 0.0, 'income' => 0.0, 'expense' => 0.0];

        $applyTxnRows = function ($rows) use (&$buckets) {
            foreach ($rows as $r) {
                $key = $r->fund_id ?: '__untagged__';
                if (! isset($buckets[$key])) {
                    continue;
                }
                $field = $r->category_type === 'INCOME' ? 'income' : 'expense';
                $buckets[$key][$field] += (float) $r->total;
            }
        };
        $applyOpeningTxnRows = function ($rows) use (&$buckets) {
            foreach ($rows as $r) {
                $key = $r->fund_id ?: '__untagged__';
                if (! isset($buckets[$key])) {
                    continue;
                }
                $sign = $r->category_type === 'INCOME' ? 1 : -1;
                $buckets[$key]['opening'] += $sign * (float) $r->total;
            }
        };

        // Cashbook (cbe_transactions) — opening (before this year) and
        // this year's movement.
        $applyOpeningTxnRows(DB::table('cbe_transactions as t')
            ->join('cbe_transaction_categories as c', 'c.category_id', '=', 't.category_id')
            ->where('t.cbe_node_id', $nodeId)->where('t.transaction_date', '<', $yearStart)
            ->select('t.fund_id', 'c.type as category_type', DB::raw('SUM(t.amount) as total'))
            ->groupBy('t.fund_id', 'c.type')->get());
        $applyTxnRows(DB::table('cbe_transactions as t')
            ->join('cbe_transaction_categories as c', 'c.category_id', '=', 't.category_id')
            ->where('t.cbe_node_id', $nodeId)->whereBetween('t.transaction_date', [$yearStart, $yearEnd])
            ->select('t.fund_id', 'c.type as category_type', DB::raw('SUM(t.amount) as total'))
            ->groupBy('t.fund_id', 'c.type')->get());

        // Fund-tagged journal lines (manual JVs, Adjustment/Accrual
        // entries, or any future posting that tags a fund) — same
        // opening/this-year split. Untagged journal lines never
        // contribute here by design (only cbe_transactions feeds the
        // Untagged/General bucket).
        $applyOpeningJournalRows = function ($rows) use (&$buckets) {
            foreach ($rows as $r) {
                if (! isset($buckets[$r->fund_id])) {
                    continue;
                }
                $isIncome = $r->account_type === 'INCOME';
                $net = $isIncome ? ((float) $r->total_credit - (float) $r->total_debit) : -((float) $r->total_debit - (float) $r->total_credit);
                $buckets[$r->fund_id]['opening'] += $net;
            }
        };
        $applyJournalRows = function ($rows) use (&$buckets) {
            foreach ($rows as $r) {
                if (! isset($buckets[$r->fund_id])) {
                    continue;
                }
                if ($r->account_type === 'INCOME') {
                    $buckets[$r->fund_id]['income'] += (float) $r->total_credit - (float) $r->total_debit;
                } elseif ($r->account_type === 'EXPENSE') {
                    $buckets[$r->fund_id]['expense'] += (float) $r->total_debit - (float) $r->total_credit;
                }
            }
        };
        $journalBase = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->where('j.cbe_node_id', $nodeId)->whereNotNull('l.fund_id')
            ->whereIn('a.account_type', ['INCOME', 'EXPENSE']);
        $applyOpeningJournalRows((clone $journalBase)->where('j.entry_date', '<', $yearStart)
            ->select('l.fund_id', 'a.account_type', DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('l.fund_id', 'a.account_type')->get());
        $applyJournalRows((clone $journalBase)->whereBetween('j.entry_date', [$yearStart, $yearEnd])
            ->select('l.fund_id', 'a.account_type', DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('l.fund_id', 'a.account_type')->get());

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Statement of Changes in Fund Balance'];
        $sheet[] = ['Fiscal Year '.$year];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Fund', 'Type', 'Opening Balance (RM)', 'Income (RM)', 'Expenditure (RM)', 'Transfers In/(Out) (RM)', 'Closing Balance (RM)'];

        $grandOpening = 0; $grandIncome = 0; $grandExpense = 0; $grandClosing = 0;
        foreach ($buckets as $b) {
            $closing = $b['opening'] + $b['income'] - $b['expense'];
            $grandOpening += $b['opening']; $grandIncome += $b['income']; $grandExpense += $b['expense']; $grandClosing += $closing;
            $sheet[] = [$b['name'], $b['type'] === '—' ? '—' : __('cbe_accounting.fund_type_'.strtolower($b['type'])), round($b['opening'], 2), round($b['income'], 2), round($b['expense'], 2), 0.00, round($closing, 2)];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', round($grandOpening, 2), round($grandIncome, 2), round($grandExpense, 2), 0.00, round($grandClosing, 2)];
        $sheet[] = [];
        $sheet[] = [__('cbe_accounting.fund_balance_transfers_note')];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Fund Balance', $sheet);
        return $this->download($spreadsheet, 'Fund_Balance_Report');
    }

    // NEW 2 Sep 2026 (Task #339) — Cash & Bank Position report. Every
    // bank/cash account (from the Bank Accounts Master, Task #333) plus
    // every Petty Cash fund (Task #337), each with its live ledger
    // balance as of today, so Chris can see the temple's whole cash
    // position on one page instead of opening each account separately.
    public function cashBankPositionReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $today = now()->toDateString();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Cash & Bank Position'];
        $sheet[] = ['As of: '.now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Account', 'Type', 'Bank', 'Balance (RM)'];

        $grandTotal = 0;
        $accounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('account_code')->get();
        foreach ($accounts as $a) {
            $balance = CbeAccountingService::bankAccountBalanceAsOf($a->bank_account_id, $today);
            $grandTotal += $balance;
            $sheet[] = [$a->account_name, __('cbe_records.acct_type_'.strtolower($a->account_type)), $a->bank_name ?: '—', round($balance, 2)];
        }

        $funds = DB::table('cbe_petty_cash_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();
        foreach ($funds as $f) {
            $balance = CbeAccountingService::pettyCashBalanceAsOf($f->fund_id, $today);
            $grandTotal += $balance;
            $sheet[] = [$f->fund_name, __('cbe_accounting.tile_petty_cash'), '—', round($balance, 2)];
        }

        $sheet[] = [];
        $sheet[] = ['TOTAL CASH & BANK', '', '', round($grandTotal, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Cash & Bank Position', $sheet);
        return $this->download($spreadsheet, 'Cash_Bank_Position');
    }

    // NEW 2 Sep 2026 (Task #339) — Fixed Asset Schedule. The Fixed Assets
    // screen (Task #313/#329) already lists every asset with its net
    // book value on-screen; this is the same figures as a formal
    // downloadable schedule — cost, accumulated depreciation, and net
    // book value per asset with totals — the format an external
    // accountant or auditor expects to see.
    public function fixedAssetScheduleReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        // UPGRADED 4 Sep 2026 (Task #395 Phase 3) — this is now the full
        // Asset Register per spec (Category/Location/Department/Fund/
        // Supplier plus the Original/Additional/Revised Cost breakdown),
        // not just the original 7-column Schedule. Kept on the same
        // route/tile ("Fixed Asset Schedule" and "Asset Register" are the
        // same document per spec) so nothing that already links here
        // breaks.
        $assets = DB::table('cbe_fixed_assets as f')
            ->leftJoin('cbe_asset_categories as cat', 'cat.category_id', '=', 'f.category_id')
            ->leftJoin('cbe_asset_locations as loc', 'loc.location_id', '=', 'f.location_id')
            ->leftJoin('cbe_cost_centres as cc', 'cc.centre_id', '=', 'f.cost_centre_id')
            ->leftJoin('cbe_funds as fd', 'fd.fund_id', '=', 'f.fund_id')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'f.supplier_id')
            ->where('f.cbe_node_id', $nodeId)
            ->select('f.*', 'cat.category_name', 'loc.location_name', 'cc.centre_name', 'fd.fund_name', 's.supplier_name')
            ->orderBy('f.acquired_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Fixed Asset Register'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Asset', 'Tag', 'Category', 'Location', 'Department', 'Fund', 'Supplier', 'Acquired', 'Original Cost (RM)', 'Additional Cost (RM)', 'Revised Cost (RM)', 'Accum. Depreciation (RM)', 'Net Book Value (RM)', 'Status'];

        $totalOriginal = 0; $totalAdditional = 0; $totalRevised = 0; $totalDepr = 0; $totalNbv = 0;
        foreach ($assets as $a) {
            $revisedCost = CbeAccountingService::revisedAssetCost($a->asset_id);
            $additionalCost = round($revisedCost - (float) $a->acquisition_cost, 2);
            $accumDepr = CbeAccountingService::accumulatedDepreciation($a->asset_id);
            $nbv = $a->status === 'DISPOSED' ? 0 : round($revisedCost - $accumDepr, 2);
            $totalOriginal += (float) $a->acquisition_cost;
            $totalAdditional += $additionalCost;
            $totalRevised += $revisedCost;
            $totalDepr += $accumDepr;
            $totalNbv += $nbv;
            $sheet[] = [
                $a->asset_name,
                $a->asset_tag ?: '—',
                $a->category_name ?: ($a->asset_class ?: '—'),
                $a->location_name ?: ($a->location ?: '—'),
                $a->centre_name ?: '—',
                $a->fund_name ?: '—',
                $a->supplier_name ?: '—',
                \Carbon\Carbon::parse($a->acquired_date)->format('d M Y'),
                round((float) $a->acquisition_cost, 2),
                $additionalCost,
                round($revisedCost, 2),
                round($accumDepr, 2),
                $nbv,
                $a->status,
            ];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', '', '', '', '', '', round($totalOriginal, 2), round($totalAdditional, 2), round($totalRevised, 2), round($totalDepr, 2), round($totalNbv, 2), ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Fixed Asset Register', $sheet);
        return $this->download($spreadsheet, 'Fixed_Asset_Register');
    }

    // ---------- Accounts Receivable (Customers/Invoices) ----------
    // NEW 29 Aug 2026 (Task #313) — per Chris: "MUST incorporate" a real
    // AR module. REBUILT 30 Aug 2026 (Task #317) — originally built on
    // the embedded centrex/laravel-accounting package (Task #312), but
    // that package kept its own separate Chart of Accounts/ledger, so
    // invoices posted there never showed up in the Trial Balance/
    // Balance Sheet/P&L/GL Chris already had. Rebuilt on the SAME
    // native cbe_ tables/ledger as Accounts Payable above — exact same
    // pattern as Suppliers/Bills, just for revenue instead of expense.

    public function customers()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customers = DB::table('cbe_customers as cu')
            ->leftJoin('cbe_customer_categories as cc', 'cc.category_id', '=', 'cu.category_id')
            ->where('cu.cbe_node_id', $nodeId)
            ->select('cu.*', 'cc.category_name')
            ->orderBy('cu.customer_name')->paginate(8, ['*'], 'custPage');
        return view('cbe.accounting.customers', compact('customers'));
    }

    public function createCustomer()
    {
        [$agent] = $this->nodeAndGroup();
        // NEW 2 Sep 2026 (Task #357) — Debtor Category + Payment Terms
        // now selectable on the customer form, per Chris's Temple/NGO AR
        // spec Master File section.
        $categories = DB::table('cbe_customer_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('display_order')->orderBy('category_name')->get();
        $paymentTerms = DB::table('cbe_payment_terms')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('net_days')->get();

        return view('cbe.accounting.create-customer', compact('categories', 'paymentTerms'));
    }

    // NEW 25 Sep 2026 — per Chris: "the records must be standardized in
    // one table" — a Customer who is already an existing Member/agent
    // must be picked from that one record, never re-typed with a
    // possibly-different name/phone/email. Same search-and-link pattern
    // already used for Committee (GroupLabelController::
    // committeeAgentTypeahead) and Practitioners
    // (AdminPractitionerController::agentTypeahead). This does not
    // change how any existing screen reads cbe_customers (customer_name/
    // phone/email are still filled in, just copied once from the linked
    // Member at the moment of creation instead of typed by hand) — see
    // migration 2026_09_18_000002_add_agent_link_to_cbe_customers_and_donors.php
    // for the agent_id column this uses.
    // NEW 25 Sep 2026 -- per Chris's decision: warning only, never
    // blocks saving. Shared logic lives in PersonPhoneDuplicateService.
    public function customerPhoneCheck(Request $request)
    {
        $phone = trim((string) $request->get('phone', ''));
        $hit = \App\Services\PersonPhoneDuplicateService::checkPerson($phone, 'CUSTOMER');

        return response()->json(['match' => $hit]);
    }

    public function customerAgentTypeahead(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $agents = DB::table('agents')
            ->where('is_deleted', false)
            ->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")
                    ->orWhere('agent_code', 'like', "%{$q}%");
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get(['agent_id', 'full_name', 'agent_code', 'phone', 'email']);

        return response()->json($agents);
    }

    public function storeCustomer(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'agent_id' => ['nullable', 'uuid', 'exists:agents,agent_id'],
            'customer_name' => ['required_without:agent_id', 'nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_customer_categories,category_id'],
            'payment_terms_id' => ['nullable', 'uuid', 'exists:cbe_payment_terms,term_id'],
        ]);

        // NEW 25 Sep 2026 — per Chris: when an existing Member was
        // picked, their name/phone/email come from the Agent record,
        // never from what was typed in those boxes (the boxes are
        // read-only on screen in that case).
        $linkedAgentId = $request->input('agent_id') ?: null;
        $customerName = $request->input('customer_name');
        $contactPhone = $request->input('phone');
        $contactEmail = $request->input('email');
        if ($linkedAgentId) {
            $linkedAgent = DB::table('agents')->where('agent_id', $linkedAgentId)->first();
            abort_if(! $linkedAgent, 404);
            $customerName = $linkedAgent->full_name;
            $contactPhone = $linkedAgent->phone;
            $contactEmail = $linkedAgent->email;
        }

        DB::table('cbe_customers')->insert([
            'customer_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'agent_id' => $linkedAgentId,
            'customer_name' => $customerName,
            'category_id' => $request->input('category_id') ?: null,
            'payment_terms_id' => $request->input('payment_terms_id') ?: null,
            'contact_person' => $request->input('contact_person'),
            'phone' => $contactPhone,
            'email' => $contactEmail,
            'address' => $request->input('address'),
            'notes' => $request->input('notes'),
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.customers')->with('success', __('cbe_accounting.customer_saved'));
    }

    public function invoices()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $invoices = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)
            ->select('i.*', 'c.customer_name')
            ->orderByDesc('i.invoice_date')
            ->paginate(8, ['*'], 'invPage');
        return view('cbe.accounting.invoices', compact('invoices'));
    }

    public function createInvoice()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.invoices.create', __('cbe_accounting.add_invoice_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();
        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'INCOME')->where('is_active', true)
            ->orderBy('display_order')->get();
        $taxRates = DB::table('cbe_tax_rates')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('rate_percent')->get();
        // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest,
        // same list already used on the AI Accounting screen, now also
        // matched client-side (JS) here since these lines are typed
        // live rather than pre-extracted.
        $glHints = TransactionClassificationService::glCategoryHints();

        return view('cbe.accounting.create-invoice', compact('customers', 'categories', 'taxRates', 'glHints'));
    }

    public function storeInvoice(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:cbe_customers,customer_id'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'line_category.*' => ['nullable', 'uuid'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_qty.*' => ['nullable', 'numeric', 'min:0'],
            'line_unit_price.*' => ['nullable', 'numeric', 'min:0'],
            'line_tax_rate.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // NEW 2 Sep 2026 (Task #335) — multi-line invoices with tax,
        // mirrors storeBill() exactly.
        $categories = $request->input('line_category', []);
        $descriptions = $request->input('line_description', []);
        $qtys = $request->input('line_qty', []);
        $unitPrices = $request->input('line_unit_price', []);
        $taxRates = $request->input('line_tax_rate', []);

        $lineRows = [];
        $totalAmount = 0;
        foreach ($unitPrices as $i => $unitPrice) {
            $unitPrice = (float) $unitPrice;
            if ($unitPrice <= 0) {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1) ?: 1;
            $taxRate = (float) ($taxRates[$i] ?? 0);
            $lineAmount = round($qty * $unitPrice, 2);
            $taxAmount = round($lineAmount * $taxRate / 100, 2);
            $lineTotal = round($lineAmount + $taxAmount, 2);

            $lineRows[] = [
                'category_id' => $categories[$i] ?: null,
                'description' => $descriptions[$i] ?: null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'line_amount' => $lineAmount,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'display_order' => $i,
            ];
            $totalAmount += $lineTotal;
        }

        if (empty($lineRows)) {
            return back()->withInput()->with('error', __('cbe_accounting.bill_error_min_lines'));
        }

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('invoice_date'))) {
            return $guard;
        }

        $invoiceDocRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'INVOICE', (int) \Carbon\Carbon::parse($request->input('invoice_date'))->year, 'INV', (int) \Carbon\Carbon::parse($request->input('invoice_date'))->month);

        $invoiceId = (string) Str::uuid();
        DB::table('cbe_invoices')->insert([
            'invoice_id' => $invoiceId,
            'doc_ref_no' => $invoiceDocRefNo,
            'cbe_node_id' => $nodeId,
            'customer_id' => $request->input('customer_id'),
            'invoice_no' => $request->input('invoice_no'),
            'invoice_date' => $request->input('invoice_date'),
            'due_date' => $request->input('due_date') ?: null,
            'description' => $request->input('description'),
            'amount' => round($totalAmount, 2),
            'category_id' => null,
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($lineRows as $row) {
            DB::table('cbe_invoice_lines')->insert(array_merge($row, [
                'line_id' => (string) Str::uuid(),
                'invoice_id' => $invoiceId,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        CbeAccountingService::postInvoice($invoiceId);

        return redirect()->route('cbe.accounting.invoices')->with('success', __('cbe_accounting.invoice_saved'));
    }

    public function payInvoice(Request $request, string $invoice)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $inv = DB::table('cbe_invoices')->where('cbe_node_id', $nodeId)->where('invoice_id', $invoice)->firstOrFail();

        $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'reference_no' => ['nullable', 'string', 'max:60'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('payment_date'))) {
            return $guard;
        }

        $paymentId = (string) Str::uuid();
        DB::table('cbe_invoice_payments')->insert([
            'payment_id' => $paymentId,
            'invoice_id' => $inv->invoice_id,
            'payment_date' => $request->input('payment_date'),
            'amount' => round((float) $request->input('amount'), 2),
            'payment_method' => $request->input('payment_method'),
            'reference_no' => $request->input('reference_no'),
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::postInvoicePayment($paymentId);

        return redirect()->route('cbe.accounting.invoices')->with('success', __('cbe_accounting.payment_saved'));
    }

    // ---------- Invoice Enquiry (NEW 2 Sep 2026, Task #359) ----------
    // On-screen search + read-only detail for one invoice: line items,
    // payments received, linked debit/credit notes, and GL posting
    // status — separate from the Invoices entry/list screen, which is
    // for creating and paying invoices rather than looking one up.

    public function invoiceEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();

        $query = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId);

        if ($search = $request->input('search')) {
            $query->where('i.invoice_no', 'like', '%'.$search.'%');
        }
        if ($customerId = $request->input('customer_id')) {
            $query->where('i.customer_id', $customerId);
        }

        $invoices = $query->select('i.*', 'c.customer_name')
            ->orderByDesc('i.invoice_date')
            ->paginate(8, ['*'], 'invEnqPage')
            ->withQueryString();

        return view('cbe.accounting.invoice-enquiry', compact('invoices', 'customers'));
    }

    public function invoiceEnquiryShow(string $invoice)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $inv = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)->where('i.invoice_id', $invoice)
            ->select('i.*', 'c.customer_name')
            ->first();

        if (! $inv) {
            return redirect()->route('cbe.accounting.invoice-enquiry');
        }

        $lines = DB::table('cbe_invoice_lines as l')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'l.category_id')
            ->where('l.invoice_id', $invoice)
            ->select('l.*', 'c.category_name')
            ->orderBy('l.display_order')->get();

        $payments = DB::table('cbe_invoice_payments as p')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'p.bank_account_id')
            ->where('p.invoice_id', $invoice)
            ->select('p.*', 'b.bank_name')
            ->orderBy('p.payment_date')->get();

        $debitNotes = DB::table('cbe_ar_debit_notes')->where('invoice_id', $invoice)->orderBy('note_date')->get();
        $creditNotes = DB::table('cbe_ar_credit_notes')->where('invoice_id', $invoice)->orderBy('note_date')->get();

        return view('cbe.accounting.invoice-enquiry-show', compact('inv', 'lines', 'payments', 'debitNotes', 'creditNotes'));
    }

    // ---------- Receipt Enquiry / Payment History (NEW 2 Sep 2026, Task
    // #359) — on-screen search across every payment received (this list
    // IS the Payment History the spec asks for), plus a read-only detail
    // per receipt with its GL posting status.

    public function receiptEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $customers = DB::table('cbe_customers')->where('cbe_node_id', $nodeId)->orderBy('customer_name')->get();

        $query = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('p.receipt_no', 'like', '%'.$search.'%')
                    ->orWhere('p.reference_no', 'like', '%'.$search.'%');
            });
        }
        if ($customerId = $request->input('customer_id')) {
            $query->where('i.customer_id', $customerId);
        }

        $payments = $query->select('p.*', 'i.invoice_no', 'c.customer_name')
            ->orderByDesc('p.payment_date')
            ->paginate(8, ['*'], 'recEnqPage')
            ->withQueryString();

        return view('cbe.accounting.receipt-enquiry', compact('payments', 'customers'));
    }

    public function receiptEnquiryShow(string $payment)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $p = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'p.bank_account_id')
            ->leftJoin('cbe_payment_methods as m', 'm.method_id', '=', 'p.payment_method_id')
            ->where('i.cbe_node_id', $nodeId)->where('p.payment_id', $payment)
            ->select('p.*', 'i.invoice_no', 'i.invoice_id', 'c.customer_name', 'b.bank_name', 'm.method_name')
            ->first();

        if (! $p) {
            return redirect()->route('cbe.accounting.receipt-enquiry');
        }

        return view('cbe.accounting.receipt-enquiry-show', compact('p'));
    }

    // ---------- Outstanding Balance Enquiry (NEW 2 Sep 2026, Task #359)
    // — on-screen (not downloadable) list of every customer with a
    // non-zero AR balance, per Chris's Temple/NGO AR spec. The
    // downloadable equivalent is the AR Aging report; this is the quick
    // on-screen lookup, one click away from the Debtor Account Enquiry.

    public function outstandingBalanceEnquiry()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $balances = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)
            ->where('i.status', '!=', 'CANCELLED')
            ->groupBy('c.customer_id', 'c.customer_name')
            ->select('c.customer_id', 'c.customer_name', DB::raw('SUM(i.amount - i.paid_amount) as outstanding'))
            ->havingRaw('SUM(i.amount - i.paid_amount) > 0.004')
            ->orderByDesc('outstanding')
            ->paginate(10, ['*'], 'obEnqPage');

        return view('cbe.accounting.outstanding-balance-enquiry', compact('balances'));
    }

    public function arAging(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $invoices = DB::table('cbe_invoices as i')
            ->join('cbe_customers as c', 'c.customer_id', '=', 'i.customer_id')
            ->where('i.cbe_node_id', $nodeId)
            ->whereIn('i.status', ['UNPAID', 'PARTIALLY_PAID'])
            ->select('i.*', 'c.customer_name')
            ->get();

        $today = now();
        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Accounts Receivable Aging Report'];
        $sheet[] = ['As of ' . $today->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Customer', 'Invoice No', 'Invoice Date', 'Due Date', 'Outstanding (RM)', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'];

        $buckets = ['current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90plus' => 0];
        foreach ($invoices as $inv) {
            $outstanding = (float) $inv->amount - (float) $inv->paid_amount;
            if ($outstanding <= 0) {
                continue;
            }
            $dueDate = $inv->due_date ? \Carbon\Carbon::parse($inv->due_date) : \Carbon\Carbon::parse($inv->invoice_date);
            $daysOverdue = $today->diffInDays($dueDate, false) * -1;

            $row = [$inv->customer_name ?? '—', $inv->invoice_no, \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y'), $dueDate->format('d M Y'), round($outstanding, 2), 0, 0, 0, 0, 0];
            if ($daysOverdue <= 0) { $row[5] = round($outstanding, 2); $buckets['current'] += $outstanding; }
            elseif ($daysOverdue <= 30) { $row[6] = round($outstanding, 2); $buckets['d30'] += $outstanding; }
            elseif ($daysOverdue <= 60) { $row[7] = round($outstanding, 2); $buckets['d60'] += $outstanding; }
            elseif ($daysOverdue <= 90) { $row[8] = round($outstanding, 2); $buckets['d90'] += $outstanding; }
            else { $row[9] = round($outstanding, 2); $buckets['d90plus'] += $outstanding; }

            $sheet[] = $row;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', '', round(array_sum($buckets), 2), round($buckets['current'], 2), round($buckets['d30'], 2), round($buckets['d60'], 2), round($buckets['d90'], 2), round($buckets['d90plus'], 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'AR Aging', $sheet);
        return $this->download($spreadsheet, 'AR_Aging_Report');
    }

    // ---------- Fixed Assets ----------
    // REBUILT 30 Aug 2026 (Task #318) — same reason as AR above: moved
    // off the centrex package onto the native cbe_ ledger. Depreciation
    // is calculated on the fly (straight-line) for display only — see
    // the migration comment on cbe_fixed_assets for why it isn't posted
    // to the GL automatically yet.

    public function fixedAssets()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $assets = DB::table('cbe_fixed_assets')->where('cbe_node_id', $nodeId)
            ->orderByDesc('acquired_date')->paginate(8, ['*'], 'faPage');

        // CHANGED 2 Sep 2026 (Task #329) — net book value now reads the
        // REAL posted depreciation total (cbe_fixed_asset_depreciation_
        // entries) instead of the old time-based on-the-fly estimate,
        // now that depreciation actually posts to the ledger.
        $assets->getCollection()->transform(function ($a) {
            $accumDepr = CbeAccountingService::accumulatedDepreciation($a->asset_id);
            $a->net_book_value = $a->status === 'DISPOSED' ? 0 : round(CbeAccountingService::revisedAssetCost($a->asset_id) - $accumDepr, 2);
            return $a;
        });

        return view('cbe.accounting.fixed-assets', compact('assets'));
    }

    // NEW 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade: Asset
    // Category and Asset Location are lazily seeded here (same pattern
    // as Chart of Accounts) so the very first time a temple opens
    // "Add Fixed Asset" it already has Chris's example categories/
    // locations to pick from, plus Department (Cost Centre master,
    // same convention already used by Purchasing) and Fund.
    public function createFixedAsset()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        CbeAccountingService::ensureAssetCategories($groupLabelId);
        CbeAccountingService::ensureAssetLocations($nodeId);

        $categories = DB::table('cbe_asset_categories')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })->where('is_active', true)->orderBy('display_order')->get();
        $locations = DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('display_order')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();
        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();
        // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest
        // for Asset Category, matched client-side (JS) against the
        // Asset Name box using his own list (land/building/machinery/
        // computer/motor/van/aircond/furniture/bike, etc.).
        $assetHints = TransactionClassificationService::assetCategoryHints();

        return view('cbe.accounting.create-fixed-asset', compact('categories', 'locations', 'costCentres', 'funds', 'suppliers', 'assetHints'));
    }

    public function storeFixedAsset(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'asset_name' => ['required', 'string', 'max:150'],
            'asset_class' => ['nullable', 'string', 'max:100'],
            'asset_tag' => ['nullable', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_asset_categories,category_id'],
            'location_id' => ['nullable', 'uuid', 'exists:cbe_asset_locations,location_id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'supplier_id' => ['nullable', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'funding_source' => ['nullable', 'string', 'max:150'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'depreciation_method' => ['required', 'in:STRAIGHT_LINE,REDUCING_BALANCE'],
            'acquired_date' => ['required', 'date'],
            'capitalisation_date' => ['nullable', 'date'],
            'depreciation_start_date' => ['nullable', 'date'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('acquired_date'))) {
            return $guard;
        }

        $fields = [
            'asset_name' => $request->input('asset_name'),
            'asset_class' => $request->input('asset_class'),
            'asset_tag' => $request->input('asset_tag'),
            'location' => $request->input('location'),
            'category_id' => $request->input('category_id') ?: null,
            'location_id' => $request->input('location_id') ?: null,
            'cost_centre_id' => $request->input('cost_centre_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'supplier_id' => $request->input('supplier_id') ?: null,
            'invoice_no' => $request->input('invoice_no'),
            'funding_source' => $request->input('funding_source'),
            'acquisition_cost' => round((float) $request->input('acquisition_cost'), 2),
            'salvage_value' => round((float) $request->input('salvage_value', 0), 2),
            'useful_life_months' => (int) $request->input('useful_life_months'),
            'depreciation_method' => $request->input('depreciation_method'),
            'acquired_date' => $request->input('acquired_date'),
            'capitalisation_date' => $request->input('capitalisation_date') ?: $request->input('acquired_date'),
            'depreciation_start_date' => $request->input('depreciation_start_date') ?: $request->input('acquired_date'),
        ];

        // NEW 4 Sep 2026 (Task #395) — spec section 27 (Approval
        // Control): above the node's Maker-Checker threshold, an
        // acquisition is queued for a second officer instead of being
        // capitalised immediately.
        if (CbeAccountingService::requiresApproval($nodeId, $fields['acquisition_cost'])) {
            CbeAccountingService::queueFixedAssetRequest($nodeId, 'ACQUISITION', null, __('cbe_accounting.fa_request_desc_acquisition', ['name' => $fields['asset_name']]), $fields['acquired_date'], $fields['acquisition_cost'], $fields, $agent->agent_id);
            CbeAccountingService::logFixedAssetAudit($nodeId, null, $fields['asset_name'], 'SUBMITTED_FOR_APPROVAL', $agent->agent_id, 'Acquisition');
            return redirect()->route('cbe.accounting.fixed-assets')->with('success', __('cbe_accounting.fixed_asset_submitted_for_approval'));
        }

        $assetId = CbeAccountingService::createFixedAssetDirect($nodeId, $fields, $agent->agent_id);
        CbeAccountingService::postFixedAssetCapitalization($assetId);
        CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $fields['asset_name'], 'CREATED', $agent->agent_id);

        return redirect()->route('cbe.accounting.fixed-assets')->with('success', __('cbe_accounting.fixed_asset_saved'));
    }

    // NEW 2 Sep 2026 (Task #329) — asset detail: register fields,
    // depreciation history, Post Depreciation + Dispose actions.

    public function showFixedAsset(string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets as f')
            ->leftJoin('cbe_asset_categories as cat', 'cat.category_id', '=', 'f.category_id')
            ->leftJoin('cbe_asset_locations as loc', 'loc.location_id', '=', 'f.location_id')
            ->leftJoin('cbe_cost_centres as cc', 'cc.centre_id', '=', 'f.cost_centre_id')
            ->leftJoin('cbe_funds as fd', 'fd.fund_id', '=', 'f.fund_id')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'f.supplier_id')
            ->where('f.asset_id', $assetId)->where('f.cbe_node_id', $nodeId)
            ->select('f.*', 'cat.category_name', 'loc.location_name', 'cc.centre_name', 'fd.fund_name', 's.supplier_name')
            ->firstOrFail();

        $entries = DB::table('cbe_fixed_asset_depreciation_entries')
            ->where('asset_id', $assetId)->orderByDesc('period_month')->get();

        // NEW 4 Sep 2026 (Task #395) — Original/Additional/Revised Cost
        // (spec section 9) and Net Book Value now read the revised cost,
        // not just the original acquisition_cost.
        $improvements = DB::table('cbe_fixed_asset_improvements')->where('asset_id', $assetId)->orderByDesc('transaction_date')->get();
        $revisedCost = CbeAccountingService::revisedAssetCost($assetId);
        $accumDepr = (float) $entries->sum('amount');
        $netBookValue = $asset->status === 'DISPOSED' ? 0 : round($revisedCost - $accumDepr, 2);
        $nextAmount = $asset->status === 'ACTIVE' ? CbeAccountingService::nextDepreciationAmount($asset) : 0;
        $currentPeriod = now()->format('Y-m');
        $alreadyPostedThisMonth = $entries->firstWhere('period_month', $currentPeriod) !== null;
        $page = (int) request('page', 1);

        // NEW 4 Sep 2026 (Task #395) — spec section 16 (Asset History):
        // Improvements + Transfers combined chronologically, shown on
        // page 3 of the asset detail flow.
        $transfers = DB::table('cbe_fixed_asset_transfers as t')
            ->leftJoin('cbe_asset_locations as fl', 'fl.location_id', '=', 't.from_location_id')
            ->leftJoin('cbe_asset_locations as tl', 'tl.location_id', '=', 't.to_location_id')
            ->leftJoin('cbe_cost_centres as fc', 'fc.centre_id', '=', 't.from_cost_centre_id')
            ->leftJoin('cbe_cost_centres as tc', 'tc.centre_id', '=', 't.to_cost_centre_id')
            ->leftJoin('cbe_funds as ff', 'ff.fund_id', '=', 't.from_fund_id')
            ->leftJoin('cbe_funds as tf', 'tf.fund_id', '=', 't.to_fund_id')
            ->where('t.asset_id', $assetId)
            ->select('t.*', 'fl.location_name as from_location_name', 'tl.location_name as to_location_name',
                'fc.centre_name as from_centre_name', 'tc.centre_name as to_centre_name',
                'ff.fund_name as from_fund_name', 'tf.fund_name as to_fund_name')
            ->orderByDesc('t.transfer_date')->get();

        // NEW 4 Sep 2026 (Task #395 Phase 4) — GL drill-down (spec section
        // 10): disposeFixedAsset() posts its own journal but, unlike
        // capitalisation, never writes it back onto cbe_fixed_assets.
        // journal_id (that column already means "the capitalisation
        // journal" everywhere else it's read) — so look it up the same
        // way the journal itself was tagged (source_type/source_id) on
        // cbe_journal_entries instead.
        $disposalJournalId = $asset->status === 'DISPOSED'
            ? DB::table('cbe_journal_entries')->where('source_type', 'FIXED_ASSET_DISPOSAL')->where('source_id', $assetId)->value('journal_id')
            : null;

        return view('cbe.accounting.show-fixed-asset', compact('asset', 'entries', 'accumDepr', 'netBookValue', 'nextAmount', 'currentPeriod', 'alreadyPostedThisMonth', 'page', 'improvements', 'revisedCost', 'transfers', 'disposalJournalId'));
    }

    public function postAssetDepreciation(string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();

        if ($guard = $this->guardPeriodOpen($nodeId, now()->toDateString())) {
            return $guard;
        }

        $result = CbeAccountingService::postDepreciation($assetId, now()->format('Y-m'), $agent->agent_id);

        if (! $result['ok']) {
            $errorKey = match ($result['error']) {
                'already_posted' => 'error_depreciation_already_posted',
                'nothing_to_depreciate' => 'error_depreciation_nothing_left',
                default => 'error_depreciation_not_active',
            };
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $asset->asset_name, 'DEPRECIATED', $agent->agent_id, 'RM '.number_format($result['amount'], 2).' — '.now()->format('Y-m'));

        return back()->with('success', __('cbe_accounting.depreciation_posted', ['amount' => number_format($result['amount'], 2)]));
    }

    public function disposeAsset(Request $request, string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'disposed_date' => ['required', 'date'],
            'disposal_proceeds' => ['required', 'numeric', 'min:0'],
            'disposal_reason' => ['nullable', 'string', 'max:255'],
            'disposal_type' => ['required', 'in:SALE,DONATION,SCRAP,WRITE_OFF,LOSS,OTHER'],
            'asset_condition' => ['nullable', 'string', 'max:30'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('disposed_date'))) {
            return $guard;
        }

        $proceeds = round((float) $request->input('disposal_proceeds'), 2);
        $netBookValue = CbeAccountingService::revisedAssetCost($assetId) - CbeAccountingService::accumulatedDepreciation($assetId);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-fixed-asset-disposals', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        // NEW 4 Sep 2026 (Task #395) — spec section 27: disposal/write-off
        // is gated the same way as acquisition, compared against whichever
        // is larger of cash proceeds or Net Book Value being removed (a
        // write-off with zero proceeds still needs approval if the asset
        // being removed from the books is material).
        if (CbeAccountingService::requiresApproval($nodeId, max($proceeds, $netBookValue))) {
            $payload = [
                'disposed_date' => $request->input('disposed_date'),
                'disposal_proceeds' => $proceeds,
                'disposal_reason' => $request->input('disposal_reason'),
                'disposal_type' => $request->input('disposal_type'),
                'asset_condition' => $request->input('asset_condition'),
                'attachment_path' => $attachmentPath,
                'attachment_original_name' => $attachmentName,
            ];
            CbeAccountingService::queueFixedAssetRequest($nodeId, 'DISPOSAL', $assetId, __('cbe_accounting.fa_request_desc_disposal', ['name' => $asset->asset_name]), $request->input('disposed_date'), max($proceeds, $netBookValue), $payload, $agent->agent_id);
            CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $asset->asset_name, 'SUBMITTED_FOR_APPROVAL', $agent->agent_id, 'Disposal');
            return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.fixed_asset_submitted_for_approval'));
        }

        $result = CbeAccountingService::disposeFixedAsset(
            $assetId,
            $request->input('disposed_date'),
            $proceeds,
            $request->input('disposal_reason'),
            $agent->agent_id,
            $request->input('disposal_type')
        );

        if (! $result['ok']) {
            return back()->with('error', __('cbe_accounting.error_asset_already_disposed'));
        }

        if ($request->input('asset_condition') || $attachmentPath) {
            DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->update([
                'asset_condition' => $request->input('asset_condition'),
                'disposal_attachment_path' => $attachmentPath,
                'disposal_attachment_original_name' => $attachmentName,
                'updated_at' => now(),
            ]);
        }

        CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $asset->asset_name, 'DISPOSED', $agent->agent_id, $request->input('disposal_type').' — RM '.number_format($proceeds, 2));

        return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.asset_disposed_success'));
    }

    // ---------- Asset Improvement / Additional Cost (NEW 4 Sep 2026,
    // Task #395, spec section 9) ----------

    public function createAssetImprovement(string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($asset->status === 'DISPOSED') {
            return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('error', __('cbe_accounting.error_asset_already_disposed'));
        }

        return view('cbe.accounting.create-asset-improvement', compact('asset'));
    }

    public function storeAssetImprovement(Request $request, string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'additional_cost' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'source_reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('transaction_date'))) {
            return $guard;
        }

        $cost = round((float) $request->input('additional_cost'), 2);

        // NEW 4 Sep 2026 (Task #395) — spec section 27: an Additional
        // Cost above threshold is queued for approval exactly like an
        // acquisition, since it is its own capital spend.
        if (CbeAccountingService::requiresApproval($nodeId, $cost)) {
            $payload = [
                'description' => $request->input('description'),
                'additional_cost' => $cost,
                'transaction_date' => $request->input('transaction_date'),
                'source_reference' => $request->input('source_reference'),
            ];
            CbeAccountingService::queueFixedAssetRequest($nodeId, 'IMPROVEMENT', $assetId, __('cbe_accounting.fa_request_desc_improvement', ['name' => $asset->asset_name]), $request->input('transaction_date'), $cost, $payload, $agent->agent_id);
            CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $asset->asset_name, 'SUBMITTED_FOR_APPROVAL', $agent->agent_id, 'Improvement');
            return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.fixed_asset_submitted_for_approval'));
        }

        $result = CbeAccountingService::postAssetImprovement($assetId, $request->input('description'), $cost, $request->input('transaction_date'), $request->input('source_reference'), $agent->agent_id);

        if (! $result['ok']) {
            return back()->withInput()->with('error', __('cbe_accounting.error_asset_already_disposed'));
        }

        CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $asset->asset_name, 'IMPROVED', $agent->agent_id, 'RM '.number_format($cost, 2).' — '.$request->input('description'));

        return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.asset_improvement_saved'));
    }

    // ---------- Asset Transfer (NEW 4 Sep 2026, Task #395, spec
    // section 12) ----------

    public function createAssetTransfer(string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($asset->status === 'DISPOSED') {
            return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('error', __('cbe_accounting.error_asset_already_disposed'));
        }

        CbeAccountingService::ensureAssetLocations($nodeId);
        $locations = DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('display_order')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        $fromLocationName = $asset->location_id ? DB::table('cbe_asset_locations')->where('location_id', $asset->location_id)->value('location_name') : null;
        $fromCentreName = $asset->cost_centre_id ? DB::table('cbe_cost_centres')->where('centre_id', $asset->cost_centre_id)->value('centre_name') : null;
        $fromFundName = $asset->fund_id ? DB::table('cbe_funds')->where('fund_id', $asset->fund_id)->value('fund_name') : null;

        return view('cbe.accounting.create-asset-transfer', compact('asset', 'locations', 'costCentres', 'funds', 'fromLocationName', 'fromCentreName', 'fromFundName'));
    }

    public function storeAssetTransfer(Request $request, string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'transfer_date' => ['required', 'date'],
            'to_location_id' => ['nullable', 'uuid', 'exists:cbe_asset_locations,location_id'],
            'to_cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'to_fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // NEW 4 Sep 2026 (Task #395) — spec section 27: a Transfer has no
        // natural monetary amount to compare against a threshold, so —
        // disclosed design choice — it always requires a second officer's
        // approval once Maker-Checker is switched on for this node,
        // regardless of amount.
        if (CbeAccountingService::approvalSettings($nodeId)->maker_checker_enabled) {
            $payload = [
                'transfer_date' => $request->input('transfer_date'),
                'to_location_id' => $request->input('to_location_id') ?: null,
                'to_cost_centre_id' => $request->input('to_cost_centre_id') ?: null,
                'to_fund_id' => $request->input('to_fund_id') ?: null,
                'reason' => $request->input('reason'),
            ];
            CbeAccountingService::queueFixedAssetRequest($nodeId, 'TRANSFER', $assetId, __('cbe_accounting.fa_request_desc_transfer', ['name' => $asset->asset_name]), $request->input('transfer_date'), 0, $payload, $agent->agent_id);
            CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $asset->asset_name, 'SUBMITTED_FOR_APPROVAL', $agent->agent_id, 'Transfer');
            return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.fixed_asset_submitted_for_approval'));
        }

        CbeAccountingService::transferFixedAsset(
            $assetId, $request->input('transfer_date'),
            $request->input('to_location_id') ?: null, $request->input('to_cost_centre_id') ?: null, $request->input('to_fund_id') ?: null,
            $request->input('reason'), $agent->agent_id
        );

        CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $asset->asset_name, 'TRANSFERRED', $agent->agent_id);

        return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.asset_transfer_saved'));
    }

    // NEW 3 Sep 2026 (Task #388) — Fixed Asset Register gap-fix: there
    // was no way to correct a typo'd asset name/class/tag/location/
    // funding source after creation (only Post Depreciation and
    // Dispose existed). Only the descriptive/master-file-style fields
    // are editable here — acquisition_cost, salvage_value, useful_life_
    // months, and acquired_date are locked once created, because they
    // already drove the capitalization journal (postFixedAssetCapital-
    // ization) and every depreciation amount calculated since; changing
    // them after the fact would desync the register from the ledger.
    public function editFixedAsset(string $assetId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();

        CbeAccountingService::ensureAssetCategories($groupLabelId);
        CbeAccountingService::ensureAssetLocations($nodeId);

        $categories = DB::table('cbe_asset_categories')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })->where('is_active', true)->orderBy('display_order')->get();
        $locations = DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('display_order')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();
        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('supplier_name')->get();

        return view('cbe.accounting.edit-fixed-asset', compact('asset', 'categories', 'locations', 'costCentres', 'funds', 'suppliers'));
    }

    public function updateFixedAsset(Request $request, string $assetId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asset = DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'asset_name' => ['required', 'string', 'max:150'],
            'asset_class' => ['nullable', 'string', 'max:100'],
            'asset_tag' => ['nullable', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_asset_categories,category_id'],
            'location_id' => ['nullable', 'uuid', 'exists:cbe_asset_locations,location_id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'supplier_id' => ['nullable', 'uuid', 'exists:cbe_suppliers,supplier_id'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'funding_source' => ['nullable', 'string', 'max:150'],
        ]);

        DB::table('cbe_fixed_assets')->where('asset_id', $assetId)->update([
            'asset_name' => $request->input('asset_name'),
            'asset_class' => $request->input('asset_class'),
            'asset_tag' => $request->input('asset_tag'),
            'location' => $request->input('location'),
            'category_id' => $request->input('category_id') ?: null,
            'location_id' => $request->input('location_id') ?: null,
            'cost_centre_id' => $request->input('cost_centre_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'supplier_id' => $request->input('supplier_id') ?: null,
            'invoice_no' => $request->input('invoice_no'),
            'funding_source' => $request->input('funding_source'),
            'updated_at' => now(),
        ]);

        CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $request->input('asset_name'), 'EDITED', $agent->agent_id);

        return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.fixed_asset_updated'));
    }

    // NEW 3 Sep 2026 (Task #388) — Disposal Listing: every DISPOSED
    // asset, with the cost/accumulated-depreciation/proceeds/gain-loss
    // figures the disposal journal actually posted, so a treasurer can
    // see the whole year's disposals in one downloadable list instead of
    // opening each asset's detail page individually.
    public function assetDisposalListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $assets = DB::table('cbe_fixed_assets')->where('cbe_node_id', $nodeId)
            ->where('status', 'DISPOSED')->orderByDesc('disposed_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Fixed Asset Disposal Listing'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Asset', 'Class', 'Disposed', 'Cost (RM)', 'Accum. Depreciation (RM)', 'Proceeds (RM)', 'Gain/(Loss) (RM)', 'Reason'];

        $totalCost = 0; $totalProceeds = 0; $totalGainLoss = 0;
        foreach ($assets as $a) {
            // FIXED 4 Sep 2026 (Task #395 Phase 4) — was comparing proceeds
            // against the raw acquisition_cost column, which understates
            // cost (and so overstates gain / understates loss) for any
            // asset that later received a posted Improvement. Now reads
            // the same Revised Cost the disposal journal itself used.
            $revisedCost = CbeAccountingService::revisedAssetCost($a->asset_id);
            $accumDepr = CbeAccountingService::accumulatedDepreciation($a->asset_id);
            $gainLoss = round((float) $a->disposal_proceeds + $accumDepr - $revisedCost, 2);
            $totalCost += $revisedCost;
            $totalProceeds += (float) $a->disposal_proceeds;
            $totalGainLoss += $gainLoss;
            $sheet[] = [
                $a->asset_name,
                $a->asset_class ?: '—',
                $a->disposed_date ? \Carbon\Carbon::parse($a->disposed_date)->format('d M Y') : '—',
                round($revisedCost, 2),
                round($accumDepr, 2),
                round((float) $a->disposal_proceeds, 2),
                $gainLoss,
                $a->disposal_reason ?: '—',
            ];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', round($totalCost, 2), '', round($totalProceeds, 2), round($totalGainLoss, 2), ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Fixed Asset Disposal Listing', $sheet);
        return $this->download($spreadsheet, 'Fixed_Asset_Disposal_Listing');
    }

    // NEW 3 Sep 2026 (Task #388) — Depreciation Listing: every posted
    // depreciation entry across every asset (entry-level detail, unlike
    // the Fixed Asset Schedule which only shows the current cumulative
    // total per asset) — same "Listing" report pattern already used for
    // GL Journal Listing / Reversal Listing.
    public function depreciationListingReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from');
        $to = $request->input('to');

        $entries = DB::table('cbe_fixed_asset_depreciation_entries as e')
            ->join('cbe_fixed_assets as a', 'a.asset_id', '=', 'e.asset_id')
            ->where('a.cbe_node_id', $nodeId)
            ->when($from, fn ($q) => $q->where('e.period_month', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.period_month', '<=', $to))
            ->select('a.asset_name', 'a.asset_class', 'e.period_month', 'e.amount', 'e.created_at')
            ->orderBy('e.period_month')->orderBy('a.asset_name')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Depreciation Listing'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Asset', 'Class', 'Period', 'Amount (RM)', 'Posted On'];

        $total = 0;
        foreach ($entries as $e) {
            $total += (float) $e->amount;
            $sheet[] = [
                $e->asset_name,
                $e->asset_class ?: '—',
                $e->period_month,
                round((float) $e->amount, 2),
                \Carbon\Carbon::parse($e->created_at)->format('d M Y'),
            ];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', round($total, 2), ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Depreciation Listing', $sheet);
        return $this->download($spreadsheet, 'Depreciation_Listing');
    }

    // ---------- Fixed Asset Module Phase 3 (Task #395) — Asset Enquiry
    // + remaining Reports suite (spec sections 3 and 4). ----------

    // NEW 4 Sep 2026 (Task #395 Phase 3) — on-screen search across every
    // fixed asset, filterable by keyword (name/tag)/category/location/
    // department/fund/status/supplier — mirrors the Supplier Procurement
    // History / AP Payment Enquiry "search then list" pattern already
    // used elsewhere in this app rather than a downloadable report.
    public function assetEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        CbeAccountingService::ensureAssetCategories($agent->group_label_id);
        CbeAccountingService::ensureAssetLocations($nodeId);
        $categories = DB::table('cbe_asset_categories')
            ->where(function ($q) use ($agent) {
                $q->where('group_label_id', $agent->group_label_id)->orWhereNull('group_label_id');
            })->orderBy('display_order')->get();
        $locations = DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)->orderBy('display_order')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->orderBy('fund_name')->get();
        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->orderBy('supplier_name')->get();

        $keyword = $request->input('keyword');
        $categoryId = $request->input('category_id');
        $locationId = $request->input('location_id');
        $costCentreId = $request->input('cost_centre_id');
        $fundId = $request->input('fund_id');
        $status = $request->input('status');
        $supplierId = $request->input('supplier_id');
        $searched = $request->filled('keyword') || $categoryId || $locationId || $costCentreId || $fundId || $status || $supplierId || $request->has('search');

        $query = DB::table('cbe_fixed_assets as f')
            ->leftJoin('cbe_asset_categories as cat', 'cat.category_id', '=', 'f.category_id')
            ->leftJoin('cbe_asset_locations as loc', 'loc.location_id', '=', 'f.location_id')
            ->leftJoin('cbe_cost_centres as cc', 'cc.centre_id', '=', 'f.cost_centre_id')
            ->leftJoin('cbe_funds as fd', 'fd.fund_id', '=', 'f.fund_id')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'f.supplier_id')
            ->where('f.cbe_node_id', $nodeId);

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('f.asset_name', 'like', "%{$keyword}%")->orWhere('f.asset_tag', 'like', "%{$keyword}%");
            });
        }
        if ($categoryId) { $query->where('f.category_id', $categoryId); }
        if ($locationId) { $query->where('f.location_id', $locationId); }
        if ($costCentreId) { $query->where('f.cost_centre_id', $costCentreId); }
        if ($fundId) { $query->where('f.fund_id', $fundId); }
        if ($status) { $query->where('f.status', $status); }
        if ($supplierId) { $query->where('f.supplier_id', $supplierId); }

        $assets = $searched
            ? $query->select('f.*', 'cat.category_name', 'loc.location_name', 'cc.centre_name', 'fd.fund_name', 's.supplier_name')
                ->orderByDesc('f.acquired_date')->paginate(8, ['*'], 'enqPage')->withQueryString()
            : null;

        if ($assets) {
            $assets->getCollection()->transform(function ($a) {
                $accumDepr = CbeAccountingService::accumulatedDepreciation($a->asset_id);
                $a->net_book_value = $a->status === 'DISPOSED' ? 0 : round(CbeAccountingService::revisedAssetCost($a->asset_id) - $accumDepr, 2);
                return $a;
            });
        }

        return view('cbe.accounting.asset-enquiry', compact('assets', 'searched', 'categories', 'locations', 'costCentres', 'funds', 'suppliers', 'keyword', 'categoryId', 'locationId', 'costCentreId', 'fundId', 'status', 'supplierId'));
    }

    // NEW 4 Sep 2026 (Task #395 Phase 3) — Asset Acquisition Report: every
    // asset acquired within a date range, same "Listing" pattern as
    // Disposal Listing / Depreciation Listing above.
    public function assetAcquisitionReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from');
        $to = $request->input('to');

        $assets = DB::table('cbe_fixed_assets as f')
            ->leftJoin('cbe_asset_categories as cat', 'cat.category_id', '=', 'f.category_id')
            ->leftJoin('cbe_suppliers as s', 's.supplier_id', '=', 'f.supplier_id')
            ->where('f.cbe_node_id', $nodeId)
            ->when($from, fn ($q) => $q->where('f.acquired_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('f.acquired_date', '<=', $to))
            ->select('f.*', 'cat.category_name', 's.supplier_name')
            ->orderBy('f.acquired_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Asset Acquisition Report'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Asset', 'Category', 'Supplier', 'Invoice No.', 'Acquired', 'Capitalisation Date', 'Acquisition Cost (RM)', 'Funding Source'];

        $total = 0;
        foreach ($assets as $a) {
            $total += (float) $a->acquisition_cost;
            $sheet[] = [
                $a->asset_name,
                $a->category_name ?: ($a->asset_class ?: '—'),
                $a->supplier_name ?: '—',
                $a->invoice_no ?: '—',
                \Carbon\Carbon::parse($a->acquired_date)->format('d M Y'),
                $a->capitalisation_date ? \Carbon\Carbon::parse($a->capitalisation_date)->format('d M Y') : '—',
                round((float) $a->acquisition_cost, 2),
                $a->funding_source ?: '—',
            ];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', '', '', '', round($total, 2), ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Asset Acquisition Report', $sheet);
        return $this->download($spreadsheet, 'Asset_Acquisition_Report');
    }

    // NEW 4 Sep 2026 (Task #395 Phase 3) — Asset Transfer Report: every
    // logged transfer (spec section 12), from the cbe_fixed_asset_
    // transfers history table built in Phase 2.
    public function assetTransferReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from');
        $to = $request->input('to');

        $transfers = DB::table('cbe_fixed_asset_transfers as t')
            ->join('cbe_fixed_assets as a', 'a.asset_id', '=', 't.asset_id')
            ->leftJoin('cbe_asset_locations as fl', 'fl.location_id', '=', 't.from_location_id')
            ->leftJoin('cbe_asset_locations as tl', 'tl.location_id', '=', 't.to_location_id')
            ->leftJoin('cbe_cost_centres as fc', 'fc.centre_id', '=', 't.from_cost_centre_id')
            ->leftJoin('cbe_cost_centres as tc', 'tc.centre_id', '=', 't.to_cost_centre_id')
            ->leftJoin('cbe_funds as ff', 'ff.fund_id', '=', 't.from_fund_id')
            ->leftJoin('cbe_funds as tf', 'tf.fund_id', '=', 't.to_fund_id')
            ->leftJoin('agents as ag', 'ag.agent_id', '=', 't.authorized_by')
            ->where('a.cbe_node_id', $nodeId)
            ->when($from, fn ($q) => $q->where('t.transfer_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('t.transfer_date', '<=', $to))
            ->select('a.asset_name', 't.transfer_date', 'fl.location_name as from_location', 'tl.location_name as to_location',
                'fc.centre_name as from_centre', 'tc.centre_name as to_centre', 'ff.fund_name as from_fund', 'tf.fund_name as to_fund',
                't.reason', 'ag.full_name as authorized_by_name')
            ->orderBy('t.transfer_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Asset Transfer Report'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Asset', 'Date', 'From Location', 'To Location', 'From Department', 'To Department', 'From Fund', 'To Fund', 'Reason', 'Authorized By'];

        foreach ($transfers as $t) {
            $sheet[] = [
                $t->asset_name,
                \Carbon\Carbon::parse($t->transfer_date)->format('d M Y'),
                $t->from_location ?: '—', $t->to_location ?: '—',
                $t->from_centre ?: '—', $t->to_centre ?: '—',
                $t->from_fund ?: '—', $t->to_fund ?: '—',
                $t->reason ?: '—',
                $t->authorized_by_name ?: '—',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Asset Transfer Report', $sheet);
        return $this->download($spreadsheet, 'Asset_Transfer_Report');
    }

    // NEW 4 Sep 2026 (Task #395 Phase 3) — Asset Write-Off Report: the
    // subset of disposals that were write-offs rather than a sale (spec
    // section 14), with the Asset Condition and Supporting Document
    // captured on the write-off form (Phase 2) that a plain Disposal
    // Listing doesn't show.
    public function assetWriteOffReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $assets = DB::table('cbe_fixed_assets')->where('cbe_node_id', $nodeId)
            ->where('status', 'DISPOSED')->where('disposal_type', 'WRITE_OFF')
            ->orderByDesc('disposed_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Asset Write-Off Report'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Asset', 'Class', 'Written Off', 'Revised Cost (RM)', 'Accum. Depreciation (RM)', 'Loss Written Off (RM)', 'Condition', 'Supporting Document', 'Reason'];

        $totalLoss = 0;
        foreach ($assets as $a) {
            $revisedCost = CbeAccountingService::revisedAssetCost($a->asset_id);
            $accumDepr = CbeAccountingService::accumulatedDepreciation($a->asset_id);
            $loss = round($revisedCost - $accumDepr - (float) $a->disposal_proceeds, 2);
            $totalLoss += $loss;
            $sheet[] = [
                $a->asset_name,
                $a->asset_class ?: '—',
                $a->disposed_date ? \Carbon\Carbon::parse($a->disposed_date)->format('d M Y') : '—',
                round($revisedCost, 2),
                round($accumDepr, 2),
                $loss,
                $a->asset_condition ?: '—',
                $a->disposal_attachment_original_name ?: 'None',
                $a->disposal_reason ?: '—',
            ];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', '', '', round($totalLoss, 2), '', '', ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Asset Write-Off Report', $sheet);
        return $this->download($spreadsheet, 'Asset_Write_Off_Report');
    }

    // NEW 4 Sep 2026 (Task #395 Phase 3) — By Location / By Department /
    // By Fund reports (spec section 4): the same Register-style columns,
    // grouped with a subtotal per group and a grand total, so a treasurer
    // can see book value split by physical location, project/department,
    // or restricted fund without opening every asset individually.
    private function fixedAssetGroupedReport(string $groupBy, string $title, string $filename)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $joinAlias = match ($groupBy) {
            'location' => ['cbe_asset_locations as g', 'g.location_id', 'f.location_id', 'g.location_name'],
            'department' => ['cbe_cost_centres as g', 'g.centre_id', 'f.cost_centre_id', 'g.centre_name'],
            'fund' => ['cbe_funds as g', 'g.fund_id', 'f.fund_id', 'g.fund_name'],
        };
        [$joinTable, $joinOn1, $joinOn2, $groupNameCol] = $joinAlias;

        $assets = DB::table('cbe_fixed_assets as f')
            ->leftJoin($joinTable, $joinOn1, '=', $joinOn2)
            ->where('f.cbe_node_id', $nodeId)
            ->select('f.*', DB::raw($groupNameCol.' as group_name'))
            ->orderBy('group_name')->orderBy('f.asset_name')->get();

        $groups = $assets->groupBy(fn ($a) => $a->group_name ?: '(Unassigned)');

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — '.$title];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];

        $grandCost = 0; $grandDepr = 0; $grandNbv = 0;
        foreach ($groups as $groupName => $groupAssets) {
            $sheet[] = [$groupName];
            $sheet[] = ['Asset', 'Revised Cost (RM)', 'Accum. Depreciation (RM)', 'Net Book Value (RM)', 'Status'];
            $subCost = 0; $subDepr = 0; $subNbv = 0;
            foreach ($groupAssets as $a) {
                $revisedCost = CbeAccountingService::revisedAssetCost($a->asset_id);
                $accumDepr = CbeAccountingService::accumulatedDepreciation($a->asset_id);
                $nbv = $a->status === 'DISPOSED' ? 0 : round($revisedCost - $accumDepr, 2);
                $subCost += $revisedCost; $subDepr += $accumDepr; $subNbv += $nbv;
                $sheet[] = [$a->asset_name, round($revisedCost, 2), round($accumDepr, 2), $nbv, $a->status];
            }
            $sheet[] = ['Subtotal', round($subCost, 2), round($subDepr, 2), round($subNbv, 2), ''];
            $sheet[] = [];
            $grandCost += $subCost; $grandDepr += $subDepr; $grandNbv += $subNbv;
        }
        $sheet[] = ['GRAND TOTAL', round($grandCost, 2), round($grandDepr, 2), round($grandNbv, 2), ''];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, $title, $sheet);
        return $this->download($spreadsheet, $filename);
    }

    public function assetByLocationReport()
    {
        return $this->fixedAssetGroupedReport('location', 'Asset by Location Report', 'Asset_By_Location_Report');
    }

    public function assetByDepartmentReport()
    {
        return $this->fixedAssetGroupedReport('department', 'Asset by Department Report', 'Asset_By_Department_Report');
    }

    public function assetByFundReport()
    {
        return $this->fixedAssetGroupedReport('fund', 'Asset by Fund Report', 'Asset_By_Fund_Report');
    }

    // NEW 4 Sep 2026 (Task #395 Phase 3) — Fixed Asset Reports Hub:
    // mirrors gl-reports-hub.blade.php / purchasing-reports-hub.blade.php
    // — a grid of tiles for every downloadable FA report, so the sidebar
    // doesn't need one link per report.
    public function fixedAssetReportsHub()
    {
        return view('cbe.accounting.fixed-asset-reports-hub');
    }

    // NEW 4 Sep 2026 (Task #395 Phase 4) — Fixed Asset Audit Trail (spec
    // section 24/29): read-only browse over the append-only
    // cbe_fixed_asset_audit_log table, exact same pattern as
    // purchasingAuditLog() above.
    public function fixedAssetAuditLog(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $logs = DB::table('cbe_fixed_asset_audit_log as l')
            ->join('agents as a', 'a.agent_id', '=', 'l.actor_id')
            ->where('l.cbe_node_id', $nodeId)
            ->select('l.*', 'a.full_name as actor_name')
            ->orderByDesc('l.created_at')
            ->paginate(10, ['*'], 'faAuditPage')
            ->withQueryString();

        return view('cbe.accounting.fixed-asset-audit-log', compact('logs'));
    }

    // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Audit Trail (spec
    // section 24). Read-only browse over the append-only
    // cbe_bank_reconciliation_audit_log table. Mirrors
    // fixedAssetAuditLog()/fixed-asset-audit-log.blade.php exactly.
    public function bankReconciliationAuditLog(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $logs = DB::table('cbe_bank_reconciliation_audit_log as l')
            ->join('agents as a', 'a.agent_id', '=', 'l.actor_id')
            ->where('l.cbe_node_id', $nodeId)
            ->select('l.*', 'a.full_name as actor_name')
            ->orderByDesc('l.created_at')
            ->paginate(10, ['*'], 'brAuditPage')
            ->withQueryString();

        return view('cbe.accounting.bank-reconciliation-audit-log', compact('logs'));
    }

    // ---------- Bank Reconciliation ----------
    // REBUILT 30 Aug 2026 (Task #319) — same reason as AR/Fixed Assets
    // above. No per-temple sub-account trick needed here (see the
    // migration comment on cbe_bank_reconciliations) — the temple's own
    // Cash account balance is already isolated by cbe_node_id on every
    // journal entry, so this just compares that computed balance
    // against what the treasurer's bank statement says.

    public function bankReconciliations()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliations = DB::table('cbe_bank_reconciliations as r')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 'b.bank_name', 'b.account_name')
            ->orderByDesc('r.statement_date')->paginate(8, ['*'], 'brPage');

        $ledgerCashBalance = $nodeId ? CbeAccountingService::cashBalanceAsOf($nodeId, now()->toDateString()) : 0;

        return view('cbe.accounting.bank-reconciliations', compact('reconciliations', 'ledgerCashBalance'));
    }

    public function createBankReconciliation()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bankAccounts = $nodeId ? DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('bank_name')->get() : collect();
        return view('cbe.accounting.create-bank-reconciliation', compact('bankAccounts'));
    }

    public function storeBankReconciliation(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'statement_date' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric'],
            'ending_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $statementDate = \Carbon\Carbon::parse($request->input('statement_date'));
        $reconciliationNo = CbeAccountingService::nextDocumentNumber($nodeId, 'BANK_RECON', $statementDate->year, 'BR', $statementDate->month);

        $reconciliationId = (string) Str::uuid();
        DB::table('cbe_bank_reconciliations')->insert([
            'reconciliation_id' => $reconciliationId,
            'reconciliation_no' => $reconciliationNo,
            'cbe_node_id' => $nodeId,
            'bank_account_id' => $request->input('bank_account_id') ?: null,
            'statement_date' => $request->input('statement_date'),
            'opening_balance' => round((float) $request->input('opening_balance'), 2),
            'ending_balance' => round((float) $request->input('ending_balance'), 2),
            'notes' => $request->input('notes'),
            'status' => 'DRAFT',
            'recorded_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliationNo, 'CREATED', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations.show', $reconciliationId)->with('success', __('cbe_accounting.bank_reconciliation_saved'));
    }

    // NEW 2 Sep 2026 (Task #336) — line-level matching. See migration
    // comment on cbe_bank_reconciliation_lines for the overall design.

    public function showBankReconciliation(string $reconciliationId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations as r')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.reconciliation_id', $reconciliationId)->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 'b.bank_name', 'b.account_name')
            ->firstOrFail();

        $lines = DB::table('cbe_bank_reconciliation_lines as l')
            ->leftJoin('cbe_transactions as t', 't.transaction_id', '=', 'l.matched_transaction_id')
            ->where('l.reconciliation_id', $reconciliationId)
            ->select('l.*', 't.description as matched_description')
            ->orderBy('l.line_date')->get();

        $unmatchedCount = $lines->where('status', 'UNMATCHED')->count();
        $matchedTotal = (float) $lines->where('status', 'MATCHED')->sum('amount');
        $outstandingTotal = (float) $lines->where('status', 'OUTSTANDING')->sum('amount');

        $ledgerBalance = CbeAccountingService::reconciliationBankBalanceAsOf($reconciliation->bank_account_id, $nodeId, $groupLabelId, $reconciliation->statement_date);
        // If every outstanding item WERE recorded in the ledger, the
        // ledger balance would move by that amount — compare that
        // projected figure against the statement's own ending balance.
        $projectedLedgerBalance = round($ledgerBalance + $outstandingTotal, 2);
        $isBalanced = $unmatchedCount === 0 && abs($projectedLedgerBalance - (float) $reconciliation->ending_balance) < 0.01;

        // Candidates for the manual-match dropdown on any UNMATCHED
        // line: this node's transactions in a ±10 day window around the
        // statement date that aren't already matched to another line
        // anywhere (this or any other reconciliation).
        $alreadyMatchedIds = DB::table('cbe_bank_reconciliation_lines')->whereNotNull('matched_transaction_id')->pluck('matched_transaction_id');
        $candidateTxns = DB::table('cbe_transactions')
            ->where('cbe_node_id', $nodeId)
            ->whereBetween('transaction_date', [
                \Carbon\Carbon::parse($reconciliation->statement_date)->subDays(45)->toDateString(),
                \Carbon\Carbon::parse($reconciliation->statement_date)->addDays(10)->toDateString(),
            ])
            ->whereNotIn('transaction_id', $alreadyMatchedIds->isEmpty() ? ['00000000-0000-0000-0000-000000000000'] : $alreadyMatchedIds)
            ->orderByDesc('transaction_date')->get();

        // NEW 2 Sep 2026 (Task #349) — Month-End Reconciliation Lock: the
        // view uses $isLocked to disable every match/unmatch/mark-
        // outstanding control and show a Reopen button (Admin only)
        // instead.
        $isLocked = $this->reconciliationIsLocked($reconciliationId);
        $isAdmin = $agent->role === 'ADMIN';

        return view('cbe.accounting.show-bank-reconciliation', compact(
            'reconciliation', 'lines', 'unmatchedCount', 'matchedTotal', 'outstandingTotal',
            'ledgerBalance', 'projectedLedgerBalance', 'isBalanced', 'candidateTxns',
            'isLocked', 'isAdmin'
        ));
    }

    public function storeReconciliationLines(Request $request, string $reconciliationId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();

        // NEW 2 Sep 2026 (Task #349) — Month-End Reconciliation Lock: once
        // a reconciliation is marked COMPLETED, its lines can no longer be
        // added to or changed. This is separate from the general fiscal
        // period lock — it locks THIS reconciliation record specifically,
        // so an Admin can still Reopen just this one without reopening the
        // whole month for every other posting.
        if ($this->reconciliationIsLocked($reconciliationId)) {
            return back()->with('error', __('cbe_accounting.error_br_locked'));
        }

        $request->validate([
            'line_date.*' => ['nullable', 'date'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_amount.*' => ['nullable', 'numeric'],
        ]);

        $dates = $request->input('line_date', []);
        $descriptions = $request->input('line_description', []);
        $amounts = $request->input('line_amount', []);

        $alreadyMatchedIds = DB::table('cbe_bank_reconciliation_lines')->whereNotNull('matched_transaction_id')->pluck('matched_transaction_id')->all();
        $addedCount = 0;

        foreach ($amounts as $i => $amount) {
            $amount = (float) $amount;
            if ($amount == 0 || empty($dates[$i])) {
                continue;
            }

            $lineId = (string) Str::uuid();
            $status = 'UNMATCHED';
            $matchedTransactionId = null;

            // Auto-match: same node, amount within 1 cent, INCOME
            // category for a positive line / EXPENSE for a negative
            // one, transaction date within 5 days either side, not
            // already matched to another line.
            $categoryType = $amount > 0 ? 'INCOME' : 'EXPENSE';
            $candidate = DB::table('cbe_transactions as t')
                ->join('cbe_transaction_categories as c', 'c.category_id', '=', 't.category_id')
                ->where('t.cbe_node_id', $nodeId)
                ->where('c.type', $categoryType)
                ->whereBetween('t.amount', [round(abs($amount) - 0.01, 2), round(abs($amount) + 0.01, 2)])
                ->whereBetween('t.transaction_date', [
                    \Carbon\Carbon::parse($dates[$i])->subDays(5)->toDateString(),
                    \Carbon\Carbon::parse($dates[$i])->addDays(5)->toDateString(),
                ])
                ->when(!empty($alreadyMatchedIds), fn ($q) => $q->whereNotIn('t.transaction_id', $alreadyMatchedIds))
                ->orderByRaw('ABS(DATEDIFF(t.transaction_date, ?))', [$dates[$i]])
                ->first();

            if ($candidate) {
                $status = 'MATCHED';
                $matchedTransactionId = $candidate->transaction_id;
                $alreadyMatchedIds[] = $candidate->transaction_id;
            }

            DB::table('cbe_bank_reconciliation_lines')->insert([
                'line_id' => $lineId,
                'reconciliation_id' => $reconciliationId,
                'cbe_node_id' => $nodeId,
                'line_date' => $dates[$i],
                'description' => $descriptions[$i] ?? null,
                'amount' => round($amount, 2),
                'status' => $status,
                'matched_transaction_id' => $matchedTransactionId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $addedCount++;
        }

        if ($addedCount > 0) {
            CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliation->reconciliation_no, 'TRANSACTION_ADDED', $agent->agent_id, $addedCount.' line(s)');
        }

        return redirect()->route('cbe.accounting.bank-reconciliations.show', $reconciliationId)->with('success', __('cbe_accounting.br_lines_added'));
    }

    public function matchReconciliationLine(Request $request, string $lineId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $line = DB::table('cbe_bank_reconciliation_lines')->where('line_id', $lineId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($this->reconciliationIsLocked($line->reconciliation_id)) {
            return back()->with('error', __('cbe_accounting.error_br_locked'));
        }

        $request->validate(['transaction_id' => ['required', 'uuid', 'exists:cbe_transactions,transaction_id']]);

        DB::table('cbe_bank_reconciliation_lines')->where('line_id', $lineId)->update([
            'status' => 'MATCHED', 'matched_transaction_id' => $request->input('transaction_id'), 'updated_at' => now(),
        ]);
        $reconciliationNo = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $line->reconciliation_id)->value('reconciliation_no');
        CbeAccountingService::logBankReconciliationAudit($nodeId, $line->reconciliation_id, $reconciliationNo, 'MANUALLY_MATCHED', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations.show', $line->reconciliation_id)->with('success', __('cbe_accounting.br_line_matched'));
    }

    public function unmatchReconciliationLine(string $lineId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $line = DB::table('cbe_bank_reconciliation_lines')->where('line_id', $lineId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($this->reconciliationIsLocked($line->reconciliation_id)) {
            return back()->with('error', __('cbe_accounting.error_br_locked'));
        }

        DB::table('cbe_bank_reconciliation_lines')->where('line_id', $lineId)->update([
            'status' => 'UNMATCHED', 'matched_transaction_id' => null, 'updated_at' => now(),
        ]);
        $reconciliationNo = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $line->reconciliation_id)->value('reconciliation_no');
        CbeAccountingService::logBankReconciliationAudit($nodeId, $line->reconciliation_id, $reconciliationNo, 'UNMATCHED', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations.show', $line->reconciliation_id)->with('success', __('cbe_accounting.br_line_unmatched'));
    }

    public function markReconciliationLineOutstanding(string $lineId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $line = DB::table('cbe_bank_reconciliation_lines')->where('line_id', $lineId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($this->reconciliationIsLocked($line->reconciliation_id)) {
            return back()->with('error', __('cbe_accounting.error_br_locked'));
        }

        DB::table('cbe_bank_reconciliation_lines')->where('line_id', $lineId)->update([
            'status' => 'OUTSTANDING', 'matched_transaction_id' => null, 'updated_at' => now(),
        ]);
        $reconciliationNo = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $line->reconciliation_id)->value('reconciliation_no');
        CbeAccountingService::logBankReconciliationAudit($nodeId, $line->reconciliation_id, $reconciliationNo, 'MARKED_OUTSTANDING', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations.show', $line->reconciliation_id)->with('success', __('cbe_accounting.br_line_outstanding'));
    }

    public function completeBankReconciliation(string $reconciliationId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $unmatchedCount = DB::table('cbe_bank_reconciliation_lines')->where('reconciliation_id', $reconciliationId)->where('status', 'UNMATCHED')->count();
        if ($unmatchedCount > 0) {
            return back()->with('error', __('cbe_accounting.error_br_unmatched_lines'));
        }

        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
        // upgrade, Phase 3: a reconciliation scoped to a real bank
        // account also can't complete while it still has unmatched Bank
        // Transactions sitting in the matching workspace's date window.
        if ($reconciliation->bank_account_id) {
            [$dateFrom, $dateTo] = $this->matchingDateWindow($reconciliation);
            $unmatchedTxnCount = DB::table('cbe_bank_transactions')
                ->where('bank_account_id', $reconciliation->bank_account_id)->where('status', 'UNRECONCILED')
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])->count();
            if ($unmatchedTxnCount > 0) {
                return back()->with('error', __('cbe_accounting.error_br_unmatched_transactions', ['count' => $unmatchedTxnCount]));
            }
        }

        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Phase 7, spec
        // section 20 (Approval Control). Same Maker-Checker gate as Bill
        // Payments/Bank Transfers: when the node has it switched on, this
        // now stops at PENDING_APPROVAL instead of going straight to
        // COMPLETED, and a different officer (or an Admin) must approve
        // it via the shared Approvals hub. When Maker-Checker is off,
        // behaviour is unchanged from before — straight to COMPLETED.
        $makerCheckerOn = CbeAccountingService::approvalSettings($nodeId)->maker_checker_enabled;
        $reconciliationNo = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->value('reconciliation_no');

        if ($makerCheckerOn) {
            DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)
                ->update(['status' => 'PENDING_APPROVAL', 'completed_by' => $agent->agent_id, 'updated_at' => now()]);
            CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliationNo, 'SUBMITTED_FOR_APPROVAL', $agent->agent_id);

            return redirect()->route('cbe.accounting.bank-reconciliations')->with('success', __('cbe_accounting.br_submitted_for_approval_success'));
        }

        // NEW 2 Sep 2026 (Task #349) — Month-End Reconciliation Lock takes
        // effect from this point: status COMPLETED now blocks every line
        // mutation above until an Admin explicitly reopens it below.
        DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)
            ->update(['status' => 'COMPLETED', 'completed_by' => $agent->agent_id, 'updated_at' => now()]);
        CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliationNo, 'COMPLETED', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations')->with('success', __('cbe_accounting.br_completed_success'));
    }

    // NEW 2 Sep 2026 (Task #349) — Month-End Reconciliation Lock. Small
    // private helper shared by every line-mutating action above. NEW 4
    // Sep 2026 (Task #396): PENDING_APPROVAL also locks — an approval
    // pending on a reconciliation must not be quietly invalidated by
    // more matching/adjustment activity while it waits for sign-off.
    private function reconciliationIsLocked(string $reconciliationId): bool
    {
        $status = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->value('status');

        return in_array($status, ['COMPLETED', 'PENDING_APPROVAL'], true);
    }

    public function reopenBankReconciliation(string $reconciliationId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if ($agent->role !== 'ADMIN') {
            abort(403);
        }
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();

        DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->update(['status' => 'DRAFT', 'updated_at' => now()]);
        CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliation->reconciliation_no, 'REOPENED', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations.show', $reconciliationId)->with('success', __('cbe_accounting.br_reopened_success'));
    }

    // NEW 3 Sep 2026 (Task #388) — Bank Reconciliation Listing: every
    // reconciliation record across every bank account, with its status
    // and balances, so a treasurer can see which months/accounts are
    // still open (DRAFT) at a glance instead of opening each one.
    public function bankReconciliationListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliations = DB::table('cbe_bank_reconciliations as r')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 'b.bank_name', 'b.account_name')
            ->orderByDesc('r.statement_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Bank Reconciliation Listing'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Bank Account', 'Statement Date', 'Opening Balance (RM)', 'Ending Balance (RM)', 'Status'];

        foreach ($reconciliations as $r) {
            $sheet[] = [
                trim(($r->bank_name ?: '—').($r->account_name ? ' — '.$r->account_name : '')),
                \Carbon\Carbon::parse($r->statement_date)->format('d M Y'),
                round((float) $r->opening_balance, 2),
                round((float) $r->ending_balance, 2),
                $r->status,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Bank Reconciliation Listing', $sheet);
        return $this->download($spreadsheet, 'Bank_Reconciliation_Listing');
    }

    // ---------- Bank Reconciliation Matching (NEW 4 Sep 2026, Task
    // #396) — Bank Reconciliation Module upgrade, Phase 3. Only
    // available once a reconciliation is scoped to a real bank account
    // (bank_account_id set) — that's what Phase 2's Bank Transactions
    // are recorded against. Reconciliations with no bank account
    // (recorded before per-account scoping existed) keep using the
    // original freehand-line workflow above only. ----------

    private function matchingDateWindow(object $reconciliation): array
    {
        $statementDate = \Carbon\Carbon::parse($reconciliation->statement_date);
        return [$statementDate->copy()->subDays(45)->toDateString(), $statementDate->copy()->addDays(10)->toDateString()];
    }

    public function matchingWorkspace(string $reconciliationId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if (! $reconciliation->bank_account_id) {
            return redirect()->route('cbe.accounting.bank-reconciliations.show', $reconciliationId);
        }
        if ($this->reconciliationIsLocked($reconciliationId)) {
            return redirect()->route('cbe.accounting.bank-reconciliations.show', $reconciliationId);
        }

        [$dateFrom, $dateTo] = $this->matchingDateWindow($reconciliation);
        $bankTxns = CbeAccountingService::unmatchedBankTransactions($reconciliation->bank_account_id, $dateFrom, $dateTo);
        $systemEntries = CbeAccountingService::unmatchedSystemEntries($reconciliation->bank_account_id, $nodeId, $groupLabelId, $dateFrom, $dateTo);

        return view('cbe.accounting.match-bank-reconciliation', compact('reconciliation', 'bankTxns', 'systemEntries'));
    }

    public function autoMatchAction(string $reconciliationId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($this->reconciliationIsLocked($reconciliationId) || ! $reconciliation->bank_account_id) {
            return back();
        }

        [$dateFrom, $dateTo] = $this->matchingDateWindow($reconciliation);
        $count = CbeAccountingService::autoMatchReconciliation($reconciliationId, $reconciliation->bank_account_id, $nodeId, $groupLabelId, $dateFrom, $dateTo, $agent->agent_id);
        if ($count > 0) {
            CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliation->reconciliation_no, 'AUTO_MATCHED', $agent->agent_id, (string) $count.' group(s)');
        }

        return redirect()->route('cbe.accounting.bank-reconciliations.match', $reconciliationId)->with('success', __('cbe_accounting.auto_match_result', ['count' => $count]));
    }

    public function storeManualMatch(Request $request, string $reconciliationId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($this->reconciliationIsLocked($reconciliationId)) {
            return back()->with('error', __('cbe_accounting.error_br_locked'));
        }

        $bankIds = array_values(array_filter((array) $request->input('bank_transaction_ids', [])));
        $lineIds = array_values(array_filter((array) $request->input('journal_line_ids', [])));

        $error = CbeAccountingService::createManualMatch($reconciliationId, $nodeId, $bankIds, $lineIds, $agent->agent_id);

        if ($error === 'no_selection') {
            return redirect()->route('cbe.accounting.bank-reconciliations.match', $reconciliationId)->with('error', __('cbe_accounting.match_no_selection_error'));
        }
        if ($error === 'mismatch') {
            return redirect()->route('cbe.accounting.bank-reconciliations.match', $reconciliationId)->with('error', __('cbe_accounting.match_sum_mismatch_error'));
        }

        CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliation->reconciliation_no, 'MANUALLY_MATCHED', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations.match', $reconciliationId)->with('success', __('cbe_accounting.match_created_success'));
    }

    public function matchedItems(string $reconciliationId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();

        // Paginate at the match-GROUP level (one match may have several
        // rows on either side), then pull full detail only for the
        // groups on this page.
        $groupPage = DB::table('cbe_bank_reconciliation_matches')
            ->where('reconciliation_id', $reconciliationId)
            ->select('match_group_id', DB::raw('MAX(created_at) as latest'))
            ->groupBy('match_group_id')
            ->orderByDesc('latest')
            ->paginate(6, ['*'], 'groupPage');

        $groupIds = $groupPage->pluck('match_group_id')->all();

        $rows = empty($groupIds) ? collect() : DB::table('cbe_bank_reconciliation_matches as m')
            ->leftJoin('cbe_bank_transactions as t', 't.transaction_id', '=', 'm.bank_transaction_id')
            ->leftJoin('cbe_journal_lines as l', 'l.line_id', '=', 'm.journal_line_id')
            ->leftJoin('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->whereIn('m.match_group_id', $groupIds)
            ->select('m.*', 't.description as bank_description', 't.transaction_date as bank_date', 'j.description as system_description', 'j.entry_date as system_date', 'j.source_type')
            ->orderByDesc('m.created_at')
            ->get()
            ->groupBy('match_group_id');

        return view('cbe.accounting.bank-reconciliation-matched', compact('reconciliation', 'rows', 'groupPage'));
    }

    public function unmatchGroupAction(string $matchGroupId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliationId = DB::table('cbe_bank_reconciliation_matches')->where('match_group_id', $matchGroupId)->value('reconciliation_id');
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->first();
        if (! $reconciliation || $this->reconciliationIsLocked($reconciliationId)) {
            return back();
        }

        CbeAccountingService::unmatchGroup($matchGroupId);
        CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliation->reconciliation_no, 'UNMATCHED', $agent->agent_id);

        return redirect()->route('cbe.accounting.bank-reconciliations.matched', $reconciliationId)->with('success', __('cbe_accounting.match_unmatched_success'));
    }

    public function createBankAdjustmentForm(string $reconciliationId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if (! $reconciliation->bank_account_id || $this->reconciliationIsLocked($reconciliationId)) {
            return redirect()->route('cbe.accounting.bank-reconciliations.show', $reconciliationId);
        }

        $accounts = DB::table('cbe_chart_of_accounts')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('cbe_node_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('account_code')->get();
        $glHints = TransactionClassificationService::glCategoryHints();

        return view('cbe.accounting.create-bank-adjustment', compact('reconciliation', 'accounts', 'glHints'));
    }

    public function storeBankAdjustment(Request $request, string $reconciliationId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $reconciliationId)->where('cbe_node_id', $nodeId)->firstOrFail();
        if ($this->reconciliationIsLocked($reconciliationId)) {
            return back()->with('error', __('cbe_accounting.error_br_locked'));
        }

        $request->validate([
            'adjustment_type'   => ['required', 'in:CHARGE,INTEREST,OTHER'],
            'adjustment_date'   => ['required', 'date'],
            'description'       => ['required', 'string', 'max:255'],
            'amount'            => ['required', 'numeric', 'gt:0'],
            'gl_account_id'     => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
        ]);

        if (CbeAccountingService::isPeriodClosed($nodeId, $request->input('adjustment_date'))) {
            return back()->withInput()->with('error', __('cbe_accounting.error_period_closed'));
        }

        CbeAccountingService::createBankAdjustment(
            $reconciliationId, $nodeId, $groupLabelId, $reconciliation->bank_account_id,
            $request->input('adjustment_type'), $request->input('adjustment_date'),
            $request->input('description'), (float) $request->input('amount'),
            $request->input('gl_account_id') ?: null, $agent->agent_id
        );
        CbeAccountingService::logBankReconciliationAudit($nodeId, $reconciliationId, $reconciliation->reconciliation_no, 'ADJUSTMENT_ADDED', $agent->agent_id, $request->input('adjustment_type').' — '.$request->input('description'));

        return redirect()->route('cbe.accounting.bank-reconciliations.show', $reconciliationId)->with('success', __('cbe_accounting.bank_adjustment_saved'));
    }

    // ---------- Bank Reconciliation Enquiry screens (NEW 4 Sep 2026,
    // Task #396) — Bank Reconciliation Module upgrade, Phase 4, spec
    // section 4. Same "search then list" pattern as Asset Enquiry /
    // Bill Enquiry: read-only, results only render after the first
    // search, fixed-height/no-scroll either way. ----------

    public function bankAccountEnquiry(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $keyword = $request->input('keyword');
        $accountType = $request->input('account_type');
        $status = $request->input('status');
        $searched = $request->filled('keyword') || $accountType || $status || $request->has('search');

        $query = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId);
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('bank_name', 'like', "%{$keyword}%")->orWhere('account_name', 'like', "%{$keyword}%")->orWhere('account_number', 'like', "%{$keyword}%");
            });
        }
        if ($accountType) { $query->where('account_type', $accountType); }
        if ($status === 'ACTIVE') { $query->where('is_active', true); }
        if ($status === 'INACTIVE') { $query->where('is_active', false); }

        $accounts = $searched ? $query->orderBy('account_code')->paginate(8, ['*'], 'enqPage')->withQueryString() : null;

        if ($accounts) {
            $accounts->getCollection()->transform(function ($a) {
                $a->current_balance = CbeAccountingService::bankAccountBalanceAsOf($a->bank_account_id, now()->toDateString());
                $lastRecon = DB::table('cbe_bank_reconciliations')->where('bank_account_id', $a->bank_account_id)->orderByDesc('statement_date')->first();
                $a->last_reconciliation_date = $lastRecon->statement_date ?? null;
                $a->last_reconciliation_status = $lastRecon->status ?? null;
                return $a;
            });
        }

        return view('cbe.accounting.bank-account-enquiry', compact('accounts', 'searched', 'keyword', 'accountType', 'status'));
    }

    public function bankTransactionEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->orderBy('bank_name')->get();

        $keyword = $request->input('keyword');
        $bankAccountId = $request->input('bank_account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $status = $request->input('status');
        $searched = $request->filled('keyword') || $bankAccountId || $dateFrom || $dateTo || $status || $request->has('search');

        $query = DB::table('cbe_bank_transactions as t')
            ->join('cbe_bank_accounts as b', 'b.bank_account_id', '=', 't.bank_account_id')
            ->leftJoin('cbe_bank_transaction_types as tt', 'tt.type_id', '=', 't.transaction_type_id')
            // NEW 4 Sep 2026 (Task #396) — Phase 6: GL drill-down.
            ->leftJoin('cbe_bank_reconciliation_matches as bm', function ($j) {
                $j->on('bm.bank_transaction_id', '=', 't.transaction_id')->where('bm.side', 'BANK');
            })
            ->leftJoin('cbe_bank_reconciliation_matches as sm', function ($j) {
                $j->on('sm.match_group_id', '=', 'bm.match_group_id')->where('sm.side', 'SYSTEM');
            })
            ->leftJoin('cbe_journal_lines as jl', 'jl.line_id', '=', 'sm.journal_line_id')
            ->where('t.cbe_node_id', $nodeId);

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('t.description', 'like', "%{$keyword}%")->orWhere('t.reference_no', 'like', "%{$keyword}%")->orWhere('t.cheque_no', 'like', "%{$keyword}%");
            });
        }
        if ($bankAccountId) { $query->where('t.bank_account_id', $bankAccountId); }
        if ($dateFrom) { $query->where('t.transaction_date', '>=', $dateFrom); }
        if ($dateTo) { $query->where('t.transaction_date', '<=', $dateTo); }
        if ($status) { $query->where('t.status', $status); }

        $transactions = $searched
            ? $query->select('t.*', 'b.bank_name', 'b.account_name', 'tt.type_name', 'jl.journal_id')
                ->orderByDesc('t.transaction_date')->paginate(10, ['*'], 'enqPage')->withQueryString()
            : null;

        return view('cbe.accounting.bank-transaction-enquiry', compact('transactions', 'searched', 'bankAccounts', 'keyword', 'bankAccountId', 'dateFrom', 'dateTo', 'status'));
    }

    public function bankReconciliationEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->orderBy('bank_name')->get();

        $bankAccountId = $request->input('bank_account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $status = $request->input('status');
        $searched = $bankAccountId || $dateFrom || $dateTo || $status || $request->has('search');

        $query = DB::table('cbe_bank_reconciliations as r')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.cbe_node_id', $nodeId);

        if ($bankAccountId) { $query->where('r.bank_account_id', $bankAccountId); }
        if ($dateFrom) { $query->where('r.statement_date', '>=', $dateFrom); }
        if ($dateTo) { $query->where('r.statement_date', '<=', $dateTo); }
        if ($status) { $query->where('r.status', $status); }

        $reconciliations = $searched
            ? $query->select('r.*', 'b.bank_name', 'b.account_name')
                ->orderByDesc('r.statement_date')->paginate(8, ['*'], 'enqPage')->withQueryString()
            : null;

        return view('cbe.accounting.bank-reconciliation-enquiry', compact('reconciliations', 'searched', 'bankAccounts', 'bankAccountId', 'dateFrom', 'dateTo', 'status'));
    }

    // Cross-account "what still needs attention" view — every
    // UNRECONCILED bank transaction for the node, regardless of which
    // reconciliation (if any) is currently open for its account.
    public function unmatchedTransactionEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->orderBy('bank_name')->get();

        $bankAccountId = $request->input('bank_account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = DB::table('cbe_bank_transactions as t')
            ->join('cbe_bank_accounts as b', 'b.bank_account_id', '=', 't.bank_account_id')
            ->where('t.cbe_node_id', $nodeId)
            ->where('t.status', 'UNRECONCILED');

        if ($bankAccountId) { $query->where('t.bank_account_id', $bankAccountId); }
        if ($dateFrom) { $query->where('t.transaction_date', '>=', $dateFrom); }
        if ($dateTo) { $query->where('t.transaction_date', '<=', $dateTo); }

        $transactions = $query->select('t.*', 'b.bank_name', 'b.account_name')
            ->orderBy('t.transaction_date')->paginate(10, ['*'], 'enqPage')->withQueryString();

        return view('cbe.accounting.unmatched-transaction-enquiry', compact('transactions', 'bankAccounts', 'bankAccountId', 'dateFrom', 'dateTo'));
    }

    // ---------- Petty Cash (NEW 2 Sep 2026, Task #337) ----------
    // Imprest system for small day-to-day spending — see the migration's
    // header comment for the full design. Establishing a fund creates
    // its own GL sub-account but posts nothing; a Top-Up is what actually
    // moves cash in (used for both the very first funding and every
    // later replenishment). A Voucher is the custodian recording one
    // small spend out of the tin.

    public function pettyCashFunds()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $funds = DB::table('cbe_petty_cash_funds')->where('cbe_node_id', $nodeId)->orderBy('fund_name')->paginate(8, ['*'], 'pcfPage');

        foreach ($funds as $f) {
            $f->balance = CbeAccountingService::pettyCashBalanceAsOf($f->fund_id, now()->toDateString());
        }

        return view('cbe.accounting.petty-cash-funds', compact('funds'));
    }

    public function createPettyCashFund()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.petty-cash-funds.create', __('cbe_accounting.add_petty_cash_fund_button'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        return view('cbe.accounting.create-petty-cash-fund');
    }

    public function storePettyCashFund(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'fund_name' => ['required', 'string', 'max:150'],
            'custodian_name' => ['required', 'string', 'max:150'],
            'float_amount' => ['required', 'numeric', 'min:0'],
        ]);

        CbeAccountingService::ensureChartOfAccounts($groupLabelId);
        $glAccountId = CbeAccountingService::createPettyCashGlLink($groupLabelId, $request->input('fund_name'), $nodeId);

        $fundId = (string) Str::uuid();
        DB::table('cbe_petty_cash_funds')->insert([
            'fund_id' => $fundId,
            'cbe_node_id' => $nodeId,
            'fund_name' => $request->input('fund_name'),
            'custodian_name' => $request->input('custodian_name'),
            'gl_account_id' => $glAccountId,
            'float_amount' => $request->input('float_amount'),
            'is_active' => true,
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.accounting.petty-cash-funds.show', $fundId)->with('success', __('cbe_accounting.petty_cash_fund_saved'));
    }

    public function showPettyCashFund(string $fundId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $fund = DB::table('cbe_petty_cash_funds')->where('fund_id', $fundId)->where('cbe_node_id', $nodeId)->firstOrFail();
        $balance = CbeAccountingService::pettyCashBalanceAsOf($fundId, now()->toDateString());

        $vouchers = DB::table('cbe_petty_cash_vouchers as v')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'v.category_id')
            ->where('v.fund_id', $fundId)
            ->select('v.*', 'c.category_name')
            ->orderByDesc('v.voucher_date')->orderByDesc('v.created_at')
            ->paginate(6, ['*'], 'pcvPage');

        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('display_order')->get();

        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('bank_name')->get();

        return view('cbe.accounting.show-petty-cash-fund', compact('fund', 'balance', 'vouchers', 'categories', 'bankAccounts'));
    }

    public function storePettyCashVoucher(Request $request, string $fundId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $fund = DB::table('cbe_petty_cash_funds')->where('fund_id', $fundId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'voucher_date' => ['required', 'date'],
            'line_payee.*' => ['nullable', 'string', 'max:150'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_category.*' => ['nullable', 'uuid'],
            'line_amount.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('voucher_date'))) {
            return $guard;
        }

        $payees = $request->input('line_payee', []);
        $descriptions = $request->input('line_description', []);
        $categories = $request->input('line_category', []);
        $amounts = $request->input('line_amount', []);
        $year = (int) \Carbon\Carbon::parse($request->input('voucher_date'))->year;
        $month = (int) \Carbon\Carbon::parse($request->input('voucher_date'))->month;

        $saved = 0;
        foreach ($amounts as $i => $amount) {
            $amount = (float) $amount;
            if ($amount <= 0) {
                continue;
            }

            $voucherId = (string) Str::uuid();
            DB::table('cbe_petty_cash_vouchers')->insert([
                'voucher_id' => $voucherId,
                'fund_id' => $fundId,
                'cbe_node_id' => $nodeId,
                'doc_ref_no' => CbeAccountingService::nextDocumentNumber($nodeId, 'PCV', $year, 'PCV', $month),
                'voucher_date' => $request->input('voucher_date'),
                'payee' => $payees[$i] ?: '-',
                'description' => $descriptions[$i] ?: '-',
                'category_id' => $categories[$i] ?: null,
                'amount' => $amount,
                'created_by' => $agent->agent_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            CbeAccountingService::postPettyCashVoucher($voucherId);
            $saved++;
        }

        if ($saved === 0) {
            return back()->withInput()->with('error', __('cbe_accounting.error_petty_cash_min_lines'));
        }

        return redirect()->route('cbe.accounting.petty-cash-funds.show', $fundId)->with('success', __('cbe_accounting.petty_cash_vouchers_saved'));
    }

    public function storePettyCashTopup(Request $request, string $fundId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $fund = DB::table('cbe_petty_cash_funds')->where('fund_id', $fundId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'topup_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:cbe_bank_accounts,bank_account_id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('topup_date'))) {
            return $guard;
        }

        $topupId = (string) Str::uuid();
        $year = (int) \Carbon\Carbon::parse($request->input('topup_date'))->year;
        $month = (int) \Carbon\Carbon::parse($request->input('topup_date'))->month;
        DB::table('cbe_petty_cash_topups')->insert([
            'topup_id' => $topupId,
            'fund_id' => $fundId,
            'cbe_node_id' => $nodeId,
            'doc_ref_no' => CbeAccountingService::nextDocumentNumber($nodeId, 'PCT', $year, 'PCT', $month),
            'topup_date' => $request->input('topup_date'),
            'amount' => $request->input('amount'),
            'bank_account_id' => $request->input('bank_account_id') ?: null,
            'notes' => $request->input('notes'),
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CbeAccountingService::postPettyCashTopup($topupId);

        return redirect()->route('cbe.accounting.petty-cash-funds.show', $fundId)->with('success', __('cbe_accounting.petty_cash_topup_saved'));
    }

    // ---------- Journal Voucher (manual entry) ----------
    // NEW 30 Aug 2026 (Task #320) — per Chris: General Ledger had no way
    // to key in a manual journal entry. Fixed-row form (6 lines, blank
    // ones ignored) rather than a dynamic "add row" — keeps the screen
    // simple and within the no-scroll/no-JS-framework standard used
    // everywhere else. Must balance (total debit = total credit) before
    // it's allowed to post.

    public function journalVouchers()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $vouchers = DB::table('cbe_journal_entries as j')
            ->leftJoin('cbe_journal_types as t', 't.type_id', '=', 'j.journal_type_id')
            ->where('j.cbe_node_id', $nodeId)->where('j.source_type', 'MANUAL')
            ->select('j.*', 't.type_name', 't.type_name_zh')
            ->orderByDesc('j.entry_date')->paginate(8, ['*'], 'jvPage');

        return view('cbe.accounting.journal-vouchers', compact('vouchers'));
    }

    public function showJournalVoucher(string $journal)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $voucher = DB::table('cbe_journal_entries as j')
            ->leftJoin('cbe_journal_types as t', 't.type_id', '=', 'j.journal_type_id')
            ->where('j.cbe_node_id', $nodeId)->where('j.journal_id', $journal)
            ->select('j.*', 't.type_name', 't.type_name_zh')
            ->firstOrFail();
        $lines = DB::table('cbe_journal_lines as l')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->leftJoin('cbe_cost_centres as c', 'c.centre_id', '=', 'l.cost_centre_id')
            ->leftJoin('cbe_funds as f', 'f.fund_id', '=', 'l.fund_id')
            ->where('l.journal_id', $journal)
            ->select('l.*', 'a.account_code', 'a.account_name', 'c.centre_name', 'c.centre_name_zh', 'f.fund_name')
            ->orderBy('l.display_order')->get();

        // NEW 2 Sep 2026 (Task #338) — Void/Reversal control.
        $reversedByRefNo = $voucher->reversed_by_journal_id
            ? DB::table('cbe_journal_entries')->where('journal_id', $voucher->reversed_by_journal_id)->value('reference_no')
            : null;
        $reversesRefNo = $voucher->reverses_journal_id
            ? DB::table('cbe_journal_entries')->where('journal_id', $voucher->reverses_journal_id)->value('reference_no')
            : null;

        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
        // upgrade, Phase 6: GL drill-down in the reverse direction — if
        // any line of this journal has been matched during a Bank
        // Reconciliation, link straight back to that reconciliation.
        $lineIds = $lines->pluck('line_id')->all();
        $bankReconciliationId = empty($lineIds) ? null : DB::table('cbe_bank_reconciliation_matches')
            ->where('side', 'SYSTEM')->whereIn('journal_line_id', $lineIds)->value('reconciliation_id');

        return view('cbe.accounting.show-journal-voucher', compact('voucher', 'lines', 'reversedByRefNo', 'reversesRefNo', 'bankReconciliationId'));
    }

    public function voidJournalAction(Request $request, string $journal)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $voucher = DB::table('cbe_journal_entries')->where('cbe_node_id', $nodeId)->where('journal_id', $journal)->firstOrFail();

        $request->validate(['void_reason' => ['required', 'string', 'max:255']]);

        $result = CbeAccountingService::voidJournal($journal, $agent->agent_id, $request->input('void_reason'));

        if (! $result['ok']) {
            $errorKey = match ($result['error']) {
                'already_voided' => 'error_already_voided',
                'is_reversal' => 'error_is_reversal',
                'period_closed' => 'error_period_closed',
                'invoice_has_payments' => 'error_invoice_has_payments',
                default => 'error_approval_failed',
            };
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        return back()->with('success', __('cbe_accounting.journal_voided_success'));
    }

    // NEW 3 Sep 2026 (Task #389) — Journal Voucher attachment download,
    // same pattern as downloadBillAttachment() above.
    public function downloadJournalAttachment(string $journal)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $voucher = DB::table('cbe_journal_entries')->where('journal_id', $journal)->where('cbe_node_id', $nodeId)->firstOrFail();

        if (! $voucher->attachment_path || ! Storage::disk('local')->exists($voucher->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($voucher->attachment_path));
    }

    // NEW 2 Sep 2026 (Task #338) — Transaction History: every posted
    // journal entry regardless of source_type (TRANSACTION, BILL,
    // BILL_PAYMENT, PETTY_CASH_VOUCHER, REVERSAL, MANUAL...), unlike
    // journalVouchers() above which only lists manually-keyed JVs. This
    // is the general audit trail / edit log — reuses the same detail +
    // Void screen as Journal Vouchers since every entry lives in the
    // same table.
    public function transactionHistory(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->get('from');
        $to = $request->get('to');

        $entries = DB::table('cbe_journal_entries')
            ->where('cbe_node_id', $nodeId)
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to))
            ->orderByDesc('entry_date')->orderByDesc('created_at')
            ->paginate(10, ['*'], 'thPage')->withQueryString();

        foreach ($entries as $e) {
            $totals = DB::table('cbe_journal_lines')->where('journal_id', $e->journal_id)
                ->select(DB::raw('SUM(debit) as d'))->first();
            $e->amount = (float) ($totals->d ?? 0);
        }

        return view('cbe.accounting.transaction-history', compact('entries', 'from', 'to'));
    }

    public function createJournalVoucher()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        CbeAccountingService::ensureChartOfAccounts($groupLabelId);
        // NEW 4 Sep 2026 (Task #390) — shared master + this node's own
        // local add-on accounts, so a temple's own custom accounts show
        // up in its own dropdowns without leaking to any other node.
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_active', true)->orderBy('account_type')->orderBy('account_code')->get();
        // NEW 3 Sep 2026 (Task #382) — Journal Type + Cost Centre selectors.
        $journalTypes = DB::table('cbe_journal_types')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('display_order')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('display_order')->get();
        // NEW 3 Sep 2026 (Task #389) — Fund selector, scoped per node
        // (cbe_funds is per-entity, unlike Cost Centre/Journal Type).
        $funds = $nodeId ? DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get() : collect();
        // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest
        // for the GL Account picker, matched client-side (JS) against
        // account name/code since this is typed live.
        $glHints = TransactionClassificationService::glCategoryHints();

        return view('cbe.accounting.create-journal-voucher', compact('accounts', 'journalTypes', 'costCentres', 'funds', 'glHints'));
    }

    public function storeJournalVoucher(Request $request)
    {
        return $this->storeJournalEntryCommon($request, null, 'cbe.accounting.journal-vouchers', 'journal_voucher_saved');
    }

    // NEW 3 Sep 2026 (Task #383) — shared by the generic Journal Voucher
    // screen and the dedicated Adjustment/Accrual Journal screens below.
    // $fixedTypeCode is null for the generic JV (the form's own Journal
    // Type dropdown decides), or 'ADJUSTMENT'/'ACCRUAL' to lock the type
    // and hide that dropdown on those dedicated screens.
    private function storeJournalEntryCommon(Request $request, ?string $fixedTypeCode, string $redirectRoute, string $successKey)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'journal_type_id' => ['nullable', 'uuid', 'exists:cbe_journal_types,type_id'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'line_account.*' => ['nullable', 'uuid'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_debit.*' => ['nullable', 'numeric', 'min:0'],
            'line_credit.*' => ['nullable', 'numeric', 'min:0'],
            'line_cost_centre.*' => ['nullable', 'uuid'],
            'line_fund.*' => ['nullable', 'uuid'],
        ]);

        $accounts = $request->input('line_account', []);
        $lineDescriptions = $request->input('line_description', []);
        $debits = $request->input('line_debit', []);
        $credits = $request->input('line_credit', []);
        $costCentres = $request->input('line_cost_centre', []);
        $funds = $request->input('line_fund', []);

        $lines = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($accounts as $i => $accountId) {
            $debit = round((float) ($debits[$i] ?? 0), 2);
            $credit = round((float) ($credits[$i] ?? 0), 2);
            if (! $accountId || ($debit <= 0 && $credit <= 0)) {
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                return back()->withInput()->with('error', __('cbe_accounting.jv_error_both_sides'));
            }
            // NEW 3 Sep 2026 (Task #389) — per-line description, falling
            // back to the header description only if a line's own box was
            // left blank, so existing short-form habits still work.
            $lineDescription = trim((string) ($lineDescriptions[$i] ?? '')) ?: $request->input('description');
            $lines[] = [$accountId, $debit, $credit, $lineDescription, $costCentres[$i] ?? null ?: null, $funds[$i] ?? null ?: null];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($lines) < 2) {
            return back()->withInput()->with('error', __('cbe_accounting.jv_error_min_lines'));
        }
        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->withInput()->with('error', __('cbe_accounting.jv_error_unbalanced'));
        }
        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('entry_date'))) {
            return $guard;
        }

        $journalTypeId = $fixedTypeCode
            ? CbeAccountingService::journalTypeIdByCode($fixedTypeCode)
            : ($request->input('journal_type_id') ?: null);

        // NEW 3 Sep 2026 (Task #389) — Attachment, same store-then-record
        // pattern as Purchase Bills (attachment_path + attachment_
        // original_name on local disk).
        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-journal-vouchers', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        // NEW 2 Sep 2026 (Task #334) — Maker-Checker. A JV can move cash
        // just like a bill payment or transfer, so it's gated the same
        // way: above threshold, it waits in Pending Approvals instead of
        // posting straight to the ledger.
        if (CbeAccountingService::requiresApproval($nodeId, $totalDebit)) {
            CbeAccountingService::saveJournalVoucherDraft($nodeId, $request->input('entry_date'), $request->input('description'), $agent->agent_id, $lines, $totalDebit, $journalTypeId, $attachmentPath, $attachmentName);
            return redirect()->route($redirectRoute)->with('success', __('cbe_accounting.jv_submitted_for_approval'));
        }

        $jvRefNo = CbeAccountingService::nextDocumentNumber($nodeId, 'JV', (int) \Carbon\Carbon::parse($request->input('entry_date'))->year, 'JV', (int) \Carbon\Carbon::parse($request->input('entry_date'))->month);
        CbeAccountingService::postManualJournal($nodeId, $request->input('entry_date'), $request->input('description'), $agent->agent_id, $lines, $jvRefNo, $journalTypeId, $attachmentPath, $attachmentName);

        return redirect()->route($redirectRoute)->with('success', __('cbe_accounting.'.$successKey));
    }

    // ---------- Adjustment Journal (NEW 3 Sep 2026, Task #383) ----------
    // Same posting mechanics as the generic Journal Voucher, but its own
    // menu entry / list / form with the Journal Type locked to
    // ADJUSTMENT, matching the spec's request for a dedicated screen
    // rather than treasurers having to remember to pick a type each time.

    public function adjustmentJournals()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $vouchers = DB::table('cbe_journal_entries as j')
            ->join('cbe_journal_types as t', 't.type_id', '=', 'j.journal_type_id')
            ->where('j.cbe_node_id', $nodeId)->where('t.type_code', 'ADJUSTMENT')
            ->select('j.*', 't.type_name', 't.type_name_zh')
            ->orderByDesc('j.entry_date')->paginate(8, ['*'], 'ajPage');
        return view('cbe.accounting.adjustment-journals', compact('vouchers'));
    }

    public function createAdjustmentJournal()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        CbeAccountingService::ensureChartOfAccounts($groupLabelId);
        // NEW 4 Sep 2026 (Task #390) — shared master + this node's own
        // local add-on accounts, so a temple's own custom accounts show
        // up in its own dropdowns without leaking to any other node.
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_active', true)->orderBy('account_type')->orderBy('account_code')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('display_order')->get();
        $funds = $nodeId ? DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get() : collect();
        $glHints = TransactionClassificationService::glCategoryHints();

        return view('cbe.accounting.create-adjustment-journal', compact('accounts', 'costCentres', 'funds', 'glHints'));
    }

    public function storeAdjustmentJournal(Request $request)
    {
        return $this->storeJournalEntryCommon($request, 'ADJUSTMENT', 'cbe.accounting.adjustment-journals', 'adjustment_journal_saved');
    }

    // ---------- Accrual Journal (NEW 3 Sep 2026, Task #383) ----------

    public function accrualJournals()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $vouchers = DB::table('cbe_journal_entries as j')
            ->join('cbe_journal_types as t', 't.type_id', '=', 'j.journal_type_id')
            ->where('j.cbe_node_id', $nodeId)->where('t.type_code', 'ACCRUAL')
            ->select('j.*', 't.type_name', 't.type_name_zh')
            ->orderByDesc('j.entry_date')->paginate(8, ['*'], 'acjPage');
        return view('cbe.accounting.accrual-journals', compact('vouchers'));
    }

    public function createAccrualJournal()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        CbeAccountingService::ensureChartOfAccounts($groupLabelId);
        // NEW 4 Sep 2026 (Task #390) — shared master + this node's own
        // local add-on accounts, so a temple's own custom accounts show
        // up in its own dropdowns without leaking to any other node.
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_active', true)->orderBy('account_type')->orderBy('account_code')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('display_order')->get();
        $funds = $nodeId ? DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get() : collect();
        $glHints = TransactionClassificationService::glCategoryHints();

        return view('cbe.accounting.create-accrual-journal', compact('accounts', 'costCentres', 'funds', 'glHints'));
    }

    public function storeAccrualJournal(Request $request)
    {
        return $this->storeJournalEntryCommon($request, 'ACCRUAL', 'cbe.accounting.accrual-journals', 'accrual_journal_saved');
    }

    // ---------- Recurring Journal Templates (NEW 3 Sep 2026, Task #383) ----------

    public function recurringJournalTemplates()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $templates = DB::table('cbe_recurring_journal_templates')
            ->where('cbe_node_id', $nodeId)
            ->orderByDesc('is_active')->orderBy('next_run_date')
            ->paginate(8, ['*'], 'rjPage');
        return view('cbe.accounting.recurring-journal-templates', compact('templates'));
    }

    public function createRecurringJournalTemplate()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        CbeAccountingService::ensureChartOfAccounts($groupLabelId);
        // NEW 4 Sep 2026 (Task #390) — shared master + this node's own
        // local add-on accounts, so a temple's own custom accounts show
        // up in its own dropdowns without leaking to any other node.
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_active', true)->orderBy('account_type')->orderBy('account_code')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('display_order')->get();

        return view('cbe.accounting.create-recurring-journal-template', compact('accounts', 'costCentres'));
    }

    public function storeRecurringJournalTemplate(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'template_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:MONTHLY,QUARTERLY,YEARLY'],
            'next_run_date' => ['required', 'date'],
            'line_account.*' => ['nullable', 'uuid'],
            'line_debit.*' => ['nullable', 'numeric', 'min:0'],
            'line_credit.*' => ['nullable', 'numeric', 'min:0'],
            'line_cost_centre.*' => ['nullable', 'uuid'],
        ]);

        $accounts = $request->input('line_account', []);
        $debits = $request->input('line_debit', []);
        $credits = $request->input('line_credit', []);
        $costCentres = $request->input('line_cost_centre', []);

        $lines = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($accounts as $i => $accountId) {
            $debit = round((float) ($debits[$i] ?? 0), 2);
            $credit = round((float) ($credits[$i] ?? 0), 2);
            if (! $accountId || ($debit <= 0 && $credit <= 0)) {
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                return back()->withInput()->with('error', __('cbe_accounting.jv_error_both_sides'));
            }
            $lines[] = ['account_id' => $accountId, 'debit' => $debit, 'credit' => $credit, 'cost_centre_id' => $costCentres[$i] ?? null ?: null];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($lines) < 2) {
            return back()->withInput()->with('error', __('cbe_accounting.jv_error_min_lines'));
        }
        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->withInput()->with('error', __('cbe_accounting.jv_error_unbalanced'));
        }

        $templateId = (string) Str::uuid();
        DB::table('cbe_recurring_journal_templates')->insert([
            'template_id' => $templateId,
            'cbe_node_id' => $nodeId,
            'template_name' => $request->input('template_name'),
            'description' => $request->input('description'),
            'frequency' => $request->input('frequency'),
            'next_run_date' => $request->input('next_run_date'),
            'is_active' => true,
            'created_by' => $agent->agent_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($lines as $i => $l) {
            DB::table('cbe_recurring_journal_template_lines')->insert([
                'line_id' => (string) Str::uuid(),
                'template_id' => $templateId,
                'account_id' => $l['account_id'],
                'cost_centre_id' => $l['cost_centre_id'],
                'debit' => $l['debit'],
                'credit' => $l['credit'],
                'memo' => $request->input('description'),
                'display_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return redirect()->route('cbe.accounting.recurring-journal-templates')->with('success', __('cbe_accounting.recurring_template_saved'));
    }

    public function generateRecurringJournal(string $template)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $tpl = DB::table('cbe_recurring_journal_templates')->where('template_id', $template)->where('cbe_node_id', $nodeId)->firstOrFail();

        $result = CbeAccountingService::generateFromRecurringTemplate($template, $agent->agent_id);
        if (! $result['ok']) {
            $errorKey = match ($result['error']) {
                'period_closed' => 'error_period_closed',
                'unbalanced' => 'jv_error_unbalanced',
                'insufficient_lines' => 'jv_error_min_lines',
                default => 'error_approval_failed',
            };
            return back()->with('error', __('cbe_accounting.'.$errorKey));
        }

        return redirect()->route('cbe.accounting.journal-vouchers.show', $result['journal_id'])->with('success', __('cbe_accounting.recurring_template_generated'));
    }

    public function deactivateRecurringJournalTemplate(string $template)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_recurring_journal_templates')->where('template_id', $template)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.recurring_template_deactivated'));
    }

    // ---------- Journal Types (NEW 3 Sep 2026, Task #382) ----------

    public function journalTypes()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $types = DB::table('cbe_journal_types')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->orderByDesc('is_active')->orderBy('display_order')->orderBy('type_name')
            ->paginate(10, ['*'], 'jtPage');
        return view('cbe.accounting.journal-types', compact('types'));
    }

    public function storeJournalType(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $request->validate([
            'type_name' => ['required', 'string', 'max:60'],
            'type_name_zh' => ['nullable', 'string', 'max:60'],
        ]);
        DB::table('cbe_journal_types')->insert([
            'type_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'type_code' => Str::upper(Str::slug($request->input('type_name'), '_')),
            'type_name' => $request->input('type_name'),
            'type_name_zh' => $request->input('type_name_zh'),
            'is_system' => false,
            'is_active' => true,
            'display_order' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('cbe.accounting.journal-types')->with('success', __('cbe_accounting.journal_type_saved'));
    }

    public function deactivateJournalType(string $typeId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $type = DB::table('cbe_journal_types')->where('type_id', $typeId)->where('group_label_id', $groupLabelId)->first();
        if ($type && ! $type->is_system) {
            DB::table('cbe_journal_types')->where('type_id', $typeId)->update(['is_active' => false, 'updated_at' => now()]);
        }
        return back()->with('success', __('cbe_accounting.journal_type_deactivated'));
    }

    // ---------- Cost Centres (NEW 3 Sep 2026, Task #382) ----------

    public function costCentres()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $centres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->orderByDesc('is_active')->orderBy('centre_type')->orderBy('display_order')->orderBy('centre_name')
            ->paginate(10, ['*'], 'ccPage');
        return view('cbe.accounting.cost-centres', compact('centres'));
    }

    public function storeCostCentre(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $request->validate([
            'centre_type' => ['required', 'in:COST_CENTRE,DEPARTMENT,PROJECT,EVENT'],
            'centre_code' => ['nullable', 'string', 'max:20'],
            'centre_name' => ['required', 'string', 'max:100'],
            'centre_name_zh' => ['nullable', 'string', 'max:100'],
        ]);
        DB::table('cbe_cost_centres')->insert([
            'centre_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'centre_type' => $request->input('centre_type'),
            'centre_code' => $request->input('centre_code'),
            'centre_name' => $request->input('centre_name'),
            'centre_name_zh' => $request->input('centre_name_zh'),
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('cbe.accounting.cost-centres')->with('success', __('cbe_accounting.cost_centre_saved'));
    }

    public function deactivateCostCentre(string $centreId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        DB::table('cbe_cost_centres')->where('centre_id', $centreId)->where('group_label_id', $groupLabelId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.cost_centre_deactivated'));
    }

    // ---------- Asset Categories / Asset Locations (NEW 4 Sep 2026,
    // Task #395) — Fixed Asset Module upgrade spec sections 2 + 4 + 6.
    // Same "list + inline add form + deactivate" master-file pattern as
    // Cost Centres above. Asset Category additionally lets an admin map
    // (or re-map) each of the 3 GL accounts a category drives, straight
    // from the existing Chart of Accounts — left blank, the category
    // falls back to the one global set of Fixed Asset/Accum.
    // Depreciation/Depreciation Expense accounts every asset used before
    // this upgrade (see resolveAssetGlAccount() in the service).

    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Now just the hub (2 buttons), same pattern as
    // Chart of Accounts -- see createAssetCategory() (Add) and
    // assetCategoriesSearch() (Search/Edit) below.
    public function assetCategories()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.asset-categories', __('cbe_accounting.asset_categories_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        return view('cbe.accounting.asset-categories');
    }

    // NEW 22 Sep 2026 -- the Add screen (was the top of the old combined
    // asset-categories.blade.php page).
    public function createAssetCategory()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.asset-categories.create', __('cbe_accounting.asset_categories_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        CbeAccountingService::ensureAssetCategories($groupLabelId);
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)->where('is_active', true)->orderBy('account_code')->get();

        return view('cbe.accounting.create-asset-category', compact('accounts'));
    }

    // NEW 22 Sep 2026 -- the Search/Edit screen. Nothing is queried or
    // shown until Chris actually types something into the search box
    // and submits -- an all-blank load shows the hint text only.
    public function assetCategoriesSearch(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.asset-categories.search', __('cbe_accounting.asset_categories_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        // Same auto-seed as the Add screen -- a node that goes straight
        // to Search without ever opening Add must still see the default
        // categories.
        CbeAccountingService::ensureAssetCategories($groupLabelId);
        $q = trim((string) $request->query('q', ''));
        $categories = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $categories = DB::table('cbe_asset_categories as c')
                ->leftJoin('cbe_chart_of_accounts as fa', 'fa.account_id', '=', 'c.fixed_asset_account_id')
                ->leftJoin('cbe_chart_of_accounts as ad', 'ad.account_id', '=', 'c.accum_depreciation_account_id')
                ->leftJoin('cbe_chart_of_accounts as de', 'de.account_id', '=', 'c.depreciation_expense_account_id')
                ->where(function ($qr) use ($groupLabelId) {
                    $qr->where('c.group_label_id', $groupLabelId);
                    if ($groupLabelId === null) {
                        $qr->orWhereNull('c.group_label_id');
                    }
                })
                ->where(function ($qr) use ($needle) {
                    $qr->where('c.category_name', 'like', $needle)->orWhere('c.category_name_zh', 'like', $needle);
                })
                ->select('c.*', 'fa.account_name as fixed_asset_account_name', 'ad.account_name as accum_depreciation_account_name', 'de.account_name as depreciation_expense_account_name')
                ->orderByDesc('c.is_active')->orderBy('c.display_order')->orderBy('c.category_name')
                ->paginate(10, ['*'], 'catPage')->withQueryString();
        }

        return view('cbe.accounting.asset-categories-search', compact('categories'));
    }

    // ADDED 23 Sep 2026 -- per Chris ("ALL search must have type
    // ahead"). Same shape as accountCategoryTypeahead().
    public function assetCategoryTypeahead(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $q = trim((string) $request->query('q', ''));
        if (! $nodeId || mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $needle = '%'.$q.'%';
        $results = DB::table('cbe_asset_categories')
            ->where(function ($qr) use ($groupLabelId) {
                $qr->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $qr->orWhereNull('group_label_id');
                }
            })
            ->where('is_active', true)
            ->where(function ($qr) use ($needle) {
                $qr->where('category_name', 'like', $needle)->orWhere('category_name_zh', 'like', $needle);
            })
            ->orderBy('category_name')
            ->limit(15)
            ->get(['category_id', 'category_name', 'category_name_zh']);

        return response()->json($results);
    }

    public function storeAssetCategory(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $request->validate([
            'category_name' => ['required', 'string', 'max:150'],
            'category_name_zh' => ['nullable', 'string', 'max:150'],
            'fixed_asset_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'accum_depreciation_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'depreciation_expense_account_id' => ['nullable', 'uuid', 'exists:cbe_chart_of_accounts,account_id'],
            'default_useful_life_months' => ['nullable', 'integer', 'min:1'],
            'default_depreciation_method' => ['required', 'in:STRAIGHT_LINE,REDUCING_BALANCE'],
        ]);
        DB::table('cbe_asset_categories')->insert([
            'category_id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'category_name' => $request->input('category_name'),
            'category_name_zh' => $request->input('category_name_zh'),
            'fixed_asset_account_id' => $request->input('fixed_asset_account_id') ?: null,
            'accum_depreciation_account_id' => $request->input('accum_depreciation_account_id') ?: null,
            'depreciation_expense_account_id' => $request->input('depreciation_expense_account_id') ?: null,
            'default_useful_life_months' => $request->input('default_useful_life_months') ?: null,
            'default_depreciation_method' => $request->input('default_depreciation_method'),
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('cbe.accounting.asset-categories')->with('success', __('cbe_accounting.asset_category_saved'));
    }

    public function deactivateAssetCategory(string $categoryId)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        DB::table('cbe_asset_categories')->where('category_id', $categoryId)
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })
            ->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.asset_category_deactivated'));
    }

    public function assetLocations()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        // FIX 13 Sep 2026 (Task #418 follow-up) — same null-node crash:
        // ensureAssetLocations() requires a non-null string $nodeId.
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.asset-locations', __('cbe_accounting.asset_locations_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        CbeAccountingService::ensureAssetLocations($nodeId);

        $locations = DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)
            ->orderByDesc('is_active')->orderBy('display_order')->orderBy('location_name')
            ->paginate(10, ['*'], 'locPage');

        return view('cbe.accounting.asset-locations', compact('locations'));
    }

    public function storeAssetLocation(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $request->validate([
            'location_name' => ['required', 'string', 'max:150'],
            'location_name_zh' => ['nullable', 'string', 'max:150'],
        ]);
        DB::table('cbe_asset_locations')->insert([
            'location_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'location_name' => $request->input('location_name'),
            'location_name_zh' => $request->input('location_name_zh'),
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('cbe.accounting.asset-locations')->with('success', __('cbe_accounting.asset_location_saved'));
    }

    public function deactivateAssetLocation(string $locationId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        DB::table('cbe_asset_locations')->where('location_id', $locationId)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_accounting.asset_location_deactivated'));
    }

    // ---------- Opening Balances (NEW 2 Sep 2026, Task #339) ----------

    public function openingBalances()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.opening-balances', __('cbe_accounting.tile_opening_balances'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }
        CbeAccountingService::ensureChartOfAccounts($groupLabelId);

        // Already posted once — send Chris to the entry itself instead
        // of a blank form (voiding it there clears this and re-opens
        // this screen for a fresh entry).
        if ($journalId = CbeAccountingService::openingBalancesJournalId($nodeId)) {
            return redirect()->route('cbe.accounting.journal-vouchers.show', $journalId)->with('success', __('cbe_accounting.ob_already_posted'));
        }

        // NEW 4 Sep 2026 (Task #390) — shared master + this node's own
        // local add-on accounts, so a temple's own custom accounts show
        // up in its own dropdowns without leaking to any other node.
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('is_active', true)->orderBy('account_type')->orderBy('account_code')->get();

        return view('cbe.accounting.opening-balances', compact('accounts'));
    }

    public function storeOpeningBalances(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        if (CbeAccountingService::openingBalancesPosted($nodeId)) {
            return redirect()->route('cbe.accounting.opening-balances');
        }

        $request->validate([
            'as_of_date' => ['required', 'date'],
            'line_account.*' => ['nullable', 'uuid'],
            'line_debit.*' => ['nullable', 'numeric', 'min:0'],
            'line_credit.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $accountIds = $request->input('line_account', []);
        $debits = $request->input('line_debit', []);
        $credits = $request->input('line_credit', []);

        $lines = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($accountIds as $i => $accountId) {
            $debit = round((float) ($debits[$i] ?? 0), 2);
            $credit = round((float) ($credits[$i] ?? 0), 2);
            if (! $accountId || ($debit <= 0 && $credit <= 0)) {
                continue;
            }
            if ($debit > 0 && $credit > 0) {
                return back()->withInput()->with('error', __('cbe_accounting.jv_error_both_sides'));
            }
            $lines[] = [$accountId, $debit, $credit, __('cbe_accounting.ob_line_memo')];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($lines) < 1) {
            return back()->withInput()->with('error', __('cbe_accounting.ob_error_no_lines'));
        }
        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->withInput()->with('error', __('cbe_accounting.jv_error_unbalanced'));
        }
        if ($guard = $this->guardPeriodOpen($nodeId, $request->input('as_of_date'))) {
            return $guard;
        }

        $journalId = CbeAccountingService::postOpeningBalances($nodeId, $request->input('as_of_date'), $agent->agent_id, $lines);

        return redirect()->route('cbe.accounting.journal-vouchers.show', $journalId)->with('success', __('cbe_accounting.ob_saved'));
    }

    // ---------- Maker-Checker Approvals (NEW 2 Sep 2026, Task #334) ----------

    public function approvals(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.approvals', __('cbe_accounting.approvals_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        // NEW 3 Sep 2026 (Task #373) — per Chris's AP spec: Pending
        // Approval / Approved Transactions / Rejected Transactions as 3
        // separate tabs on the one screen, defaulting to Pending.
        $tab = in_array($request->input('tab'), ['approved', 'rejected'], true) ? $request->input('tab') : 'pending';
        $items = $tab === 'pending'
            ? CbeAccountingService::pendingApprovals($nodeId)
            : CbeAccountingService::approvalHistory($nodeId, strtoupper($tab));

        return view('cbe.accounting.approvals', compact('items', 'tab'));
    }

    public function approveApprovalItem(Request $request, string $type, string $id)
    {
        [$agent] = $this->nodeAndGroup();
        $isAdmin = $agent->role === 'ADMIN';

        // NEW 4 Sep 2026 (Task #395) — Fixed Asset requests share one
        // handler regardless of action_type (ACQUISITION/DISPOSAL/
        // TRANSFER/IMPROVEMENT all replay through the same queue table).
        if (str_starts_with($type, 'FIXED_ASSET_')) {
            $req = DB::table('cbe_fixed_asset_requests')->where('request_id', $id)->first();
            $result = CbeAccountingService::approveFixedAssetRequest($id, $agent->agent_id, $isAdmin);
            if ($result['ok'] && $req) {
                $assetName = DB::table('cbe_fixed_assets')->where('asset_id', $result['asset_id'])->value('asset_name');
                CbeAccountingService::logFixedAssetAudit($req->cbe_node_id, $result['asset_id'], $assetName, 'APPROVED', $agent->agent_id, $req->action_type);
            }
        } else {
            $result = match ($type) {
                'BILL_PAYMENT' => CbeAccountingService::approveBillPayment($id, $agent->agent_id, $isAdmin),
                'BANK_TRANSFER' => CbeAccountingService::approveBankTransfer($id, $agent->agent_id, $isAdmin),
                'JOURNAL_VOUCHER' => CbeAccountingService::approveJournalVoucherDraft($id, $agent->agent_id, $isAdmin),
                'BANK_RECONCILIATION' => CbeAccountingService::approveBankReconciliation($id, $agent->agent_id, $isAdmin),
                default => ['ok' => false, 'error' => 'unknown_type'],
            };
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation audit trail.
            if ($type === 'BANK_RECONCILIATION' && $result['ok']) {
                $recon = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $id)->first();
                if ($recon) {
                    CbeAccountingService::logBankReconciliationAudit($recon->cbe_node_id, $id, $recon->reconciliation_no, 'APPROVED', $agent->agent_id);
                }
            }
        }

        if (! $result['ok']) {
            $message = $result['error'] === 'self_approval'
                ? __('cbe_accounting.error_self_approval')
                : __('cbe_accounting.error_approval_failed');
            return back()->with('error', $message);
        }

        return back()->with('success', __('cbe_accounting.approval_approved_success'));
    }

    public function rejectApprovalItem(Request $request, string $type, string $id)
    {
        [$agent] = $this->nodeAndGroup();
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if (str_starts_with($type, 'FIXED_ASSET_')) {
            $req = DB::table('cbe_fixed_asset_requests')->where('request_id', $id)->first();
            CbeAccountingService::rejectFixedAssetRequest($id, $agent->agent_id, $request->input('reason'));
            if ($req) {
                $assetName = $req->asset_id ? DB::table('cbe_fixed_assets')->where('asset_id', $req->asset_id)->value('asset_name') : null;
                CbeAccountingService::logFixedAssetAudit($req->cbe_node_id, $req->asset_id, $assetName, 'REJECTED', $agent->agent_id, $req->action_type.' — '.$request->input('reason'));
            }
        } else {
            match ($type) {
                'BILL_PAYMENT' => CbeAccountingService::rejectBillPayment($id, $agent->agent_id, $request->input('reason')),
                'BANK_TRANSFER' => CbeAccountingService::rejectBankTransfer($id, $agent->agent_id, $request->input('reason')),
                'JOURNAL_VOUCHER' => CbeAccountingService::rejectJournalVoucherDraft($id, $agent->agent_id, $request->input('reason')),
                'BANK_RECONCILIATION' => CbeAccountingService::rejectBankReconciliation($id, $agent->agent_id, $request->input('reason')),
                default => null,
            };
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation audit trail.
            if ($type === 'BANK_RECONCILIATION') {
                $recon = DB::table('cbe_bank_reconciliations')->where('reconciliation_id', $id)->first();
                if ($recon) {
                    CbeAccountingService::logBankReconciliationAudit($recon->cbe_node_id, $id, $recon->reconciliation_no, 'REJECTED', $agent->agent_id, $request->input('reason'));
                }
            }
        }

        return back()->with('success', __('cbe_accounting.approval_rejected_success'));
    }

    public function approvalSettingsForm()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.approval-settings', __('cbe_accounting.approval_settings_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $settings = CbeAccountingService::approvalSettings($nodeId);

        return view('cbe.accounting.approval-settings', compact('settings'));
    }

    public function saveApprovalSettingsForm(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'maker_checker_enabled' => ['nullable'],
            'threshold_amount' => ['required', 'numeric', 'min:0'],
        ]);

        CbeAccountingService::saveApprovalSettings(
            $nodeId,
            (bool) $request->input('maker_checker_enabled'),
            round((float) $request->input('threshold_amount'), 2),
            $agent->agent_id
        );

        return redirect()->route('cbe.accounting.approval-settings')->with('success', __('cbe_accounting.approval_settings_saved'));
    }

    // ---------- Bank Reconciliation Module Phase 1 (Task #396) —
    // Reconciliation Rules (spec section 1.4). Same single-row-per-node
    // get/save pattern as Approval Settings above. ----------

    public function bankReconciliationRulesForm()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.bank-reconciliation-rules', __('cbe_accounting.bank_reconciliation_rules_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $rules = CbeAccountingService::bankReconciliationRules($nodeId);

        return view('cbe.accounting.bank-reconciliation-rules', compact('rules'));
    }

    public function saveBankReconciliationRulesForm(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'amount_tolerance' => ['required', 'numeric', 'min:0'],
            'date_tolerance_days' => ['required', 'integer', 'min:0', 'max:90'],
            'match_on_reference' => ['nullable'],
            'match_on_cheque_no' => ['nullable'],
            'match_on_description' => ['nullable'],
        ]);

        CbeAccountingService::saveBankReconciliationRules(
            $nodeId,
            round((float) $request->input('amount_tolerance'), 2),
            (int) $request->input('date_tolerance_days'),
            (bool) $request->input('match_on_reference'),
            (bool) $request->input('match_on_cheque_no'),
            (bool) $request->input('match_on_description'),
            $agent->agent_id
        );

        return redirect()->route('cbe.accounting.bank-reconciliation-rules')->with('success', __('cbe_accounting.bank_reconciliation_rules_saved'));
    }

    // ---------- Reports ----------

    private function download(Spreadsheet $spreadsheet, string $filenamePrefix): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);
        $filename = $filenamePrefix . '_' . now()->format('dMY') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function writeSheet(Spreadsheet $spreadsheet, string $title, array $rows): void
    {
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle($title);
        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $val) {
                $coord = Coordinate::stringFromColumnIndex($colIdx + 1) . ($rowIdx + 1);
                if (is_float($val) || is_int($val)) {
                    $ws->getCell($coord)->setValueExplicit($val, DataType::TYPE_NUMERIC);
                } else {
                    $ws->getCell($coord)->setValue($val);
                }
            }
        }
        $lastCol = 'A';
        foreach ($rows as $row) {
            $c = Coordinate::stringFromColumnIndex(max(count($row), 1));
            if (strlen($c) > strlen($lastCol) || $c > $lastCol) {
                $lastCol = $c;
            }
        }
        foreach (range('A', $lastCol) as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // Net balance for one account, normal-balance aware: ASSET/EXPENSE
    // are debit-normal (balance = debit - credit); LIABILITY/EQUITY/
    // INCOME are credit-normal (balance = credit - debit).
    private static function normalBalance(string $accountType, float $debit, float $credit): float
    {
        return in_array($accountType, ['ASSET', 'EXPENSE'], true) ? ($debit - $credit) : ($credit - $debit);
    }

    private function accountBalances(string $nodeId, ?string $asOf = null): \Illuminate\Support\Collection
    {
        return DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->where('j.cbe_node_id', $nodeId)
            ->when($asOf, fn ($q) => $q->where('j.entry_date', '<=', $asOf))
            ->groupBy('a.account_id', 'a.account_code', 'a.account_name', 'a.account_type')
            ->select('a.account_id', 'a.account_code', 'a.account_name', 'a.account_type',
                DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->orderBy('a.account_type')->orderBy('a.account_code')
            ->get();
    }

    // Aligns a current-period balances collection with a prior-period one
    // by account_id, for the comparative-period toggle (Task #389, per
    // Module 1 spec — Trial Balance/Balance Sheet/P&L must support a
    // side-by-side "this period vs same period last year" view, the
    // format an AGM committee reviewing the Treasurer's Report expects).
    private function comparativeMap(\Illuminate\Support\Collection $current, \Illuminate\Support\Collection $prior): \Illuminate\Support\Collection
    {
        $map = [];
        foreach ($current as $r) {
            $map[$r->account_id] = [
                'account_code' => $r->account_code, 'account_name' => $r->account_name, 'account_type' => $r->account_type,
                'cur_debit' => (float) $r->total_debit, 'cur_credit' => (float) $r->total_credit,
                'py_debit' => 0.0, 'py_credit' => 0.0,
            ];
        }
        foreach ($prior as $r) {
            if (!isset($map[$r->account_id])) {
                $map[$r->account_id] = [
                    'account_code' => $r->account_code, 'account_name' => $r->account_name, 'account_type' => $r->account_type,
                    'cur_debit' => 0.0, 'cur_credit' => 0.0, 'py_debit' => 0.0, 'py_credit' => 0.0,
                ];
            }
            $map[$r->account_id]['py_debit'] = (float) $r->total_debit;
            $map[$r->account_id]['py_credit'] = (float) $r->total_credit;
        }

        return collect($map)->values()->sortBy(fn ($x) => $x['account_type'] . '_' . $x['account_code'])->values();
    }

    public function trialBalance(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();
        $compare = $request->boolean('compare');
        $balances = $this->accountBalances($nodeId, $asOf);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Trial Balance'];
        $sheet[] = ['As of ' . \Carbon\Carbon::parse($asOf)->format('d M Y')];
        if ($compare) {
            $priorAsOf = \Carbon\Carbon::parse($asOf)->subYear()->toDateString();
            $sheet[] = ['Comparative: As of ' . \Carbon\Carbon::parse($priorAsOf)->format('d M Y')];
        }
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];

        if (!$compare) {
            $sheet[] = ['Account Code', 'Account Name', 'Type', 'Debit (RM)', 'Credit (RM)'];
            $totalDebit = 0; $totalCredit = 0;
            foreach ($balances as $b) {
                $bal = self::normalBalance($b->account_type, (float) $b->total_debit, (float) $b->total_credit);
                $isDebitNormal = in_array($b->account_type, ['ASSET', 'EXPENSE'], true);
                $debitCol = $isDebitNormal ? max($bal, 0) : max(-$bal, 0);
                $creditCol = $isDebitNormal ? max(-$bal, 0) : max($bal, 0);
                $totalDebit += $debitCol; $totalCredit += $creditCol;
                $sheet[] = [$b->account_code, $b->account_name, $b->account_type, round($debitCol, 2), round($creditCol, 2)];
            }
            $sheet[] = [];
            $sheet[] = ['', '', 'TOTAL', round($totalDebit, 2), round($totalCredit, 2)];
        } else {
            $priorBalances = $this->accountBalances($nodeId, $priorAsOf);
            $merged = $this->comparativeMap($balances, $priorBalances);
            $sheet[] = ['Account Code', 'Account Name', 'Type', 'Debit (RM)', 'Credit (RM)', 'Debit PY (RM)', 'Credit PY (RM)'];
            $totalDebit = 0; $totalCredit = 0; $totalDebitPy = 0; $totalCreditPy = 0;
            foreach ($merged as $m) {
                $isDebitNormal = in_array($m['account_type'], ['ASSET', 'EXPENSE'], true);
                $bal = self::normalBalance($m['account_type'], $m['cur_debit'], $m['cur_credit']);
                $balPy = self::normalBalance($m['account_type'], $m['py_debit'], $m['py_credit']);
                $debitCol = $isDebitNormal ? max($bal, 0) : max(-$bal, 0);
                $creditCol = $isDebitNormal ? max(-$bal, 0) : max($bal, 0);
                $debitColPy = $isDebitNormal ? max($balPy, 0) : max(-$balPy, 0);
                $creditColPy = $isDebitNormal ? max(-$balPy, 0) : max($balPy, 0);
                $totalDebit += $debitCol; $totalCredit += $creditCol; $totalDebitPy += $debitColPy; $totalCreditPy += $creditColPy;
                $sheet[] = [$m['account_code'], $m['account_name'], $m['account_type'], round($debitCol, 2), round($creditCol, 2), round($debitColPy, 2), round($creditColPy, 2)];
            }
            $sheet[] = [];
            $sheet[] = ['', '', 'TOTAL', round($totalDebit, 2), round($totalCredit, 2), round($totalDebitPy, 2), round($totalCreditPy, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Trial Balance', $sheet);
        return $this->download($spreadsheet, 'Trial_Balance');
    }

    public function balanceSheet(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();
        $compare = $request->boolean('compare');
        $balances = $this->accountBalances($nodeId, $asOf);

        $assets = $balances->where('account_type', 'ASSET');
        $liabilities = $balances->where('account_type', 'LIABILITY');
        $equity = $balances->where('account_type', 'EQUITY');
        $income = $balances->where('account_type', 'INCOME');
        $expense = $balances->where('account_type', 'EXPENSE');

        $sumBal = fn ($coll) => $coll->sum(fn ($b) => self::normalBalance($b->account_type, (float) $b->total_debit, (float) $b->total_credit));
        $netSurplus = $sumBal($income) - $sumBal($expense);

        $priorAsOf = null; $priorMap = null; $priorAssets = null; $priorLiabilities = null; $priorEquity = null; $priorNetSurplus = 0.0;
        if ($compare) {
            $priorAsOf = \Carbon\Carbon::parse($asOf)->subYear()->toDateString();
            $priorBalances = $this->accountBalances($nodeId, $priorAsOf);
            $priorMap = $priorBalances->keyBy('account_id');
            $priorAssets = $priorBalances->where('account_type', 'ASSET');
            $priorLiabilities = $priorBalances->where('account_type', 'LIABILITY');
            $priorEquity = $priorBalances->where('account_type', 'EQUITY');
            $priorNetSurplus = $sumBal($priorBalances->where('account_type', 'INCOME')) - $sumBal($priorBalances->where('account_type', 'EXPENSE'));
        }
        $priorFor = function ($accountId) use ($priorMap) {
            if (!$priorMap || !isset($priorMap[$accountId])) return 0.0;
            $b = $priorMap[$accountId];
            return self::normalBalance($b->account_type, (float) $b->total_debit, (float) $b->total_credit);
        };
        $row = fn ($code, $name, $cur, $accountId = null) => $compare
            ? [$code, $name, round($cur, 2), round($priorFor($accountId), 2)]
            : [$code, $name, round($cur, 2)];

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Balance Sheet'];
        $sheet[] = ['As of ' . \Carbon\Carbon::parse($asOf)->format('d M Y')];
        if ($compare) {
            $sheet[] = ['Comparative: As of ' . \Carbon\Carbon::parse($priorAsOf)->format('d M Y')];
        }
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['ASSETS'];
        if ($compare) $sheet[] = ['Account Code', 'Account Name', 'Amount (RM)', 'Amount PY (RM)'];
        foreach ($assets as $a) {
            $sheet[] = $row($a->account_code, $a->account_name, self::normalBalance('ASSET', (float) $a->total_debit, (float) $a->total_credit), $a->account_id);
        }
        $sheet[] = $compare ? ['', 'Total Assets', round($sumBal($assets), 2), round($sumBal($priorAssets), 2)] : ['', 'Total Assets', round($sumBal($assets), 2)];
        $sheet[] = [];
        $sheet[] = ['LIABILITIES'];
        if ($compare) $sheet[] = ['Account Code', 'Account Name', 'Amount (RM)', 'Amount PY (RM)'];
        foreach ($liabilities as $l) {
            $sheet[] = $row($l->account_code, $l->account_name, self::normalBalance('LIABILITY', (float) $l->total_debit, (float) $l->total_credit), $l->account_id);
        }
        $sheet[] = $compare ? ['', 'Total Liabilities', round($sumBal($liabilities), 2), round($sumBal($priorLiabilities), 2)] : ['', 'Total Liabilities', round($sumBal($liabilities), 2)];
        $sheet[] = [];
        $sheet[] = ['EQUITY'];
        if ($compare) $sheet[] = ['Account Code', 'Account Name', 'Amount (RM)', 'Amount PY (RM)'];
        foreach ($equity as $e) {
            $sheet[] = $row($e->account_code, $e->account_name, self::normalBalance('EQUITY', (float) $e->total_debit, (float) $e->total_credit), $e->account_id);
        }
        $sheet[] = $compare
            ? ['', 'Net Surplus (accumulated Income − Expense to date)', round($netSurplus, 2), round($priorNetSurplus, 2)]
            : ['', 'Net Surplus (accumulated Income − Expense to date)', round($netSurplus, 2)];
        $sheet[] = $compare
            ? ['', 'Total Equity', round($sumBal($equity) + $netSurplus, 2), round($sumBal($priorEquity) + $priorNetSurplus, 2)]
            : ['', 'Total Equity', round($sumBal($equity) + $netSurplus, 2)];
        $sheet[] = [];
        $sheet[] = $compare
            ? ['', 'Total Liabilities + Equity', round($sumBal($liabilities) + $sumBal($equity) + $netSurplus, 2), round($sumBal($priorLiabilities) + $sumBal($priorEquity) + $priorNetSurplus, 2)]
            : ['', 'Total Liabilities + Equity', round($sumBal($liabilities) + $sumBal($equity) + $netSurplus, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Balance Sheet', $sheet);
        return $this->download($spreadsheet, 'Balance_Sheet');
    }

    // NEW 2 Sep 2026 (Task #339) — Prior-Year Comparison. This fiscal
    // year's P&L and Balance Sheet totals side by side with last fiscal
    // year's, the way a committee reviewing the AGM Treasurer's Report
    // expects to see growth/decline at a glance rather than pulling two
    // separate reports and comparing by hand.
    public function priorYearComparisonReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) ($request->get('year') ?: now()->year);
        $priorYear = $year - 1;

        $plTotals = function (int $y) use ($nodeId) {
            $from = \Carbon\Carbon::create($y, 1, 1)->toDateString();
            $to = \Carbon\Carbon::create($y, 12, 31)->toDateString();
            $rows = DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
                ->where('j.cbe_node_id', $nodeId)
                ->whereIn('a.account_type', ['INCOME', 'EXPENSE'])
                ->whereBetween('j.entry_date', [$from, $to])
                ->select('a.account_type', DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
                ->groupBy('a.account_type')->get();
            $income = (float) self::normalBalance('INCOME', (float) ($rows->firstWhere('account_type', 'INCOME')->total_debit ?? 0), (float) ($rows->firstWhere('account_type', 'INCOME')->total_credit ?? 0));
            $expense = (float) self::normalBalance('EXPENSE', (float) ($rows->firstWhere('account_type', 'EXPENSE')->total_debit ?? 0), (float) ($rows->firstWhere('account_type', 'EXPENSE')->total_credit ?? 0));
            return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
        };

        $bsTotals = function (int $y) use ($nodeId) {
            $asOf = \Carbon\Carbon::create($y, 12, 31)->toDateString();
            $balances = $this->accountBalances($nodeId, $asOf);
            $sumBal = fn ($coll) => $coll->sum(fn ($b) => self::normalBalance($b->account_type, (float) $b->total_debit, (float) $b->total_credit));
            return [
                'assets' => $sumBal($balances->where('account_type', 'ASSET')),
                'liabilities' => $sumBal($balances->where('account_type', 'LIABILITY')),
            ];
        };

        $thisPl = $plTotals($year);
        $priorPl = $plTotals($priorYear);
        $thisBs = $bsTotals($year);
        $priorBs = $bsTotals($priorYear);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Prior-Year Comparison'];
        $sheet[] = [$priorYear.' vs '.$year];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['', $priorYear.' (RM)', $year.' (RM)', 'Change (RM)'];
        $sheet[] = ['Total Income', round($priorPl['income'], 2), round($thisPl['income'], 2), round($thisPl['income'] - $priorPl['income'], 2)];
        $sheet[] = ['Total Expense', round($priorPl['expense'], 2), round($thisPl['expense'], 2), round($thisPl['expense'] - $priorPl['expense'], 2)];
        $sheet[] = ['Net Surplus / (Deficit)', round($priorPl['net'], 2), round($thisPl['net'], 2), round($thisPl['net'] - $priorPl['net'], 2)];
        $sheet[] = [];
        $sheet[] = ['Total Assets (year-end)', round($priorBs['assets'], 2), round($thisBs['assets'], 2), round($thisBs['assets'] - $priorBs['assets'], 2)];
        $sheet[] = ['Total Liabilities (year-end)', round($priorBs['liabilities'], 2), round($thisBs['liabilities'], 2), round($thisBs['liabilities'] - $priorBs['liabilities'], 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Prior-Year Comparison', $sheet);
        return $this->download($spreadsheet, 'Prior_Year_Comparison_'.$year);
    }

    // NEW 2 Sep 2026 (Task #339) — Office-Bearer / Committee List, part
    // of the ROS/Regulatory Reporting item set alongside the Annual
    // Report Pack (Task #332). Reads the Committee Structure already
    // recorded on the Secretarial Overview (Task #214/#228) — this is
    // not a separate register, just that same data formatted as the flat
    // list ROS submissions expect. Defaults to whoever is in office
    // today; pass ?as_of=YYYY-MM-DD for a past term.
    public function officeBearerListReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();

        $committee = DB::table('cbe_committee_positions as p')
            ->leftJoin('cbe_group_memberships as m', 'm.membership_id', '=', 'p.membership_id')
            ->leftJoin('agents as ag', 'ag.agent_id', '=', 'm.agent_id')
            ->where('p.cbe_node_id', $nodeId)
            ->where('p.term_start_date', '<=', $asOf)->where('p.term_end_date', '>=', $asOf)
            ->select('p.*', 'ag.full_name as member_name', 'ag.phone as member_phone', 'ag.email as member_email')
            ->orderBy('p.sort_order')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Office-Bearer / Committee List'];
        $sheet[] = ['As of: '.\Carbon\Carbon::parse($asOf)->format('d M Y')];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Position', 'Name', 'Phone', 'Email', 'Term Start', 'Term End'];
        foreach ($committee as $c) {
            $sheet[] = [
                $c->position_title,
                $c->member_name ?: '—',
                $c->member_phone ?: '—',
                $c->member_email ?: '—',
                \Carbon\Carbon::parse($c->term_start_date)->format('d M Y'),
                \Carbon\Carbon::parse($c->term_end_date)->format('d M Y'),
            ];
        }
        if ($committee->isEmpty()) {
            $sheet[] = ['No committee positions recorded for this date.'];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Office-Bearer List', $sheet);
        return $this->download($spreadsheet, 'Office_Bearer_List');
    }

    private function plRows(string $nodeId, string $from, string $to): \Illuminate\Support\Collection
    {
        return DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->where('j.cbe_node_id', $nodeId)
            ->whereIn('a.account_type', ['INCOME', 'EXPENSE'])
            ->whereBetween('j.entry_date', [$from, $to])
            ->groupBy('a.account_id', 'a.account_code', 'a.account_name', 'a.account_type')
            ->select('a.account_id', 'a.account_code', 'a.account_name', 'a.account_type',
                DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->orderBy('a.account_type')->orderBy('a.account_code')
            ->get();
    }

    public function profitLoss(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->get('from') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();
        $compare = $request->boolean('compare');

        $rows = $this->plRows($nodeId, $from, $to);

        $income = $rows->where('account_type', 'INCOME');
        $expense = $rows->where('account_type', 'EXPENSE');
        $totalIncome = $income->sum(fn ($r) => self::normalBalance('INCOME', (float) $r->total_debit, (float) $r->total_credit));
        $totalExpense = $expense->sum(fn ($r) => self::normalBalance('EXPENSE', (float) $r->total_debit, (float) $r->total_credit));

        $priorFrom = null; $priorTo = null; $priorMap = null; $priorTotalIncome = 0.0; $priorTotalExpense = 0.0;
        if ($compare) {
            $priorFrom = \Carbon\Carbon::parse($from)->subYear()->toDateString();
            $priorTo = \Carbon\Carbon::parse($to)->subYear()->toDateString();
            $priorRows = $this->plRows($nodeId, $priorFrom, $priorTo);
            $priorMap = $priorRows->keyBy('account_id');
            $priorTotalIncome = $priorRows->where('account_type', 'INCOME')->sum(fn ($r) => self::normalBalance('INCOME', (float) $r->total_debit, (float) $r->total_credit));
            $priorTotalExpense = $priorRows->where('account_type', 'EXPENSE')->sum(fn ($r) => self::normalBalance('EXPENSE', (float) $r->total_debit, (float) $r->total_credit));
        }
        $priorFor = function ($accountId) use ($priorMap) {
            if (!$priorMap || !isset($priorMap[$accountId])) return 0.0;
            $r = $priorMap[$accountId];
            return self::normalBalance($r->account_type, (float) $r->total_debit, (float) $r->total_credit);
        };
        $row = fn ($code, $name, $cur, $accountId) => $compare
            ? [$code, $name, round($cur, 2), round($priorFor($accountId), 2)]
            : [$code, $name, round($cur, 2)];

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Profit & Loss Statement'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        if ($compare) {
            $sheet[] = ['Comparative: ' . \Carbon\Carbon::parse($priorFrom)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($priorTo)->format('d M Y')];
        }
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['INCOME'];
        if ($compare) $sheet[] = ['Account Code', 'Account Name', 'Amount (RM)', 'Amount PY (RM)'];
        foreach ($income as $r) {
            $sheet[] = $row($r->account_code, $r->account_name, self::normalBalance('INCOME', (float) $r->total_debit, (float) $r->total_credit), $r->account_id);
        }
        $sheet[] = $compare ? ['', 'Total Income', round($totalIncome, 2), round($priorTotalIncome, 2)] : ['', 'Total Income', round($totalIncome, 2)];
        $sheet[] = [];
        $sheet[] = ['EXPENSE'];
        if ($compare) $sheet[] = ['Account Code', 'Account Name', 'Amount (RM)', 'Amount PY (RM)'];
        foreach ($expense as $r) {
            $sheet[] = $row($r->account_code, $r->account_name, self::normalBalance('EXPENSE', (float) $r->total_debit, (float) $r->total_credit), $r->account_id);
        }
        $sheet[] = $compare ? ['', 'Total Expense', round($totalExpense, 2), round($priorTotalExpense, 2)] : ['', 'Total Expense', round($totalExpense, 2)];
        $sheet[] = [];
        $sheet[] = $compare
            ? ['', 'NET SURPLUS / (DEFICIT)', round($totalIncome - $totalExpense, 2), round($priorTotalIncome - $priorTotalExpense, 2)]
            : ['', 'NET SURPLUS / (DEFICIT)', round($totalIncome - $totalExpense, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Profit and Loss', $sheet);
        return $this->download($spreadsheet, 'Profit_and_Loss');
    }

    // NEW 2 Sep 2026 (Task #339) — Monthly Financial Summary. A one-page
    // executive snapshot for a single month — income, expense, net
    // surplus/deficit, and the cash & bank position as of month-end —
    // rather than the full account-by-account detail Trial Balance/P&L
    // already give. Defaults to the current month.
    public function monthlyFinancialSummaryReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) ($request->get('year') ?: now()->year);
        $month = (int) ($request->get('month') ?: now()->month);
        $from = \Carbon\Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $to = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $rows = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->where('j.cbe_node_id', $nodeId)
            ->whereIn('a.account_type', ['INCOME', 'EXPENSE'])
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('a.account_type', DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->groupBy('a.account_type')->get();

        $totalIncome = (float) self::normalBalance('INCOME', (float) ($rows->firstWhere('account_type', 'INCOME')->total_debit ?? 0), (float) ($rows->firstWhere('account_type', 'INCOME')->total_credit ?? 0));
        $totalExpense = (float) self::normalBalance('EXPENSE', (float) ($rows->firstWhere('account_type', 'EXPENSE')->total_debit ?? 0), (float) ($rows->firstWhere('account_type', 'EXPENSE')->total_credit ?? 0));

        $cashPosition = 0;
        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->get();
        foreach ($bankAccounts as $a) {
            $cashPosition += CbeAccountingService::bankAccountBalanceAsOf($a->bank_account_id, $to);
        }
        $pettyCashFunds = DB::table('cbe_petty_cash_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->get();
        foreach ($pettyCashFunds as $f) {
            $cashPosition += CbeAccountingService::pettyCashBalanceAsOf($f->fund_id, $to);
        }

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Monthly Financial Summary'];
        $sheet[] = [\Carbon\Carbon::create($year, $month, 1)->format('F Y')];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Total Income (RM)', round($totalIncome, 2)];
        $sheet[] = ['Total Expense (RM)', round($totalExpense, 2)];
        $sheet[] = ['Net Surplus / (Deficit) (RM)', round($totalIncome - $totalExpense, 2)];
        $sheet[] = [];
        $sheet[] = ['Cash & Bank Position as of month-end (RM)', round($cashPosition, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Monthly Summary', $sheet);
        return $this->download($spreadsheet, 'Monthly_Financial_Summary_'.$year.'_'.str_pad((string) $month, 2, '0', STR_PAD_LEFT));
    }

    // NEW 2 Sep 2026 (Task #338) — Cash Flow Statement (direct method).
    // Classification is structural, not tag-based: every GL account with
    // a code in 1000-1199 is a "cash-family" account (Cash, every Bank
    // Account, every Petty Cash fund — see the code-space reservations
    // in CbeAccountingService). For each journal entry touching a
    // cash-family account within the period:
    //   - if EVERY line in that journal is also cash-family, it's money
    //     moving between the temple's OWN cash accounts (a Bank Transfer
    //     or a Petty Cash Top-Up) — not a real cash flow, skipped.
    //   - otherwise the non-cash side tells us what kind of flow it is:
    //     Fixed Assets/Accumulated Depreciation/Disposal Gain-Loss →
    //     Investing; everything else (income/expense, AP settled, AR
    //     collected) → Operating. No Financing activities exist yet
    //     (no loan/borrowing feature), so that section always nets zero
    //     — kept in the statement anyway since auditors expect the
    //     3-section format.
    public function cashFlowStatement(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $from = $request->get('from') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        // NEW 4 Sep 2026 (Task #390) — bank/petty-cash GL accounts are now
        // scoped to their owning node (cbe_node_id), not shared, so this
        // must include this node's own local accounts or its own bank
        // accounts would silently disappear from its own Cash Flow report.
        $cashAccountIds = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->where('account_code', '>=', '1000')->where('account_code', '<', '1200')
            ->pluck('account_id')->all();

        $investingCodes = [
            CbeAccountingService::FIXED_ASSET_CODE,
            CbeAccountingService::ACCUM_DEPRECIATION_CODE,
            CbeAccountingService::DISPOSAL_GAIN_LOSS_CODE,
        ];

        $cashAsOf = function (string $asOf) use ($nodeId, $cashAccountIds) {
            $row = DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->where('j.cbe_node_id', $nodeId)
                ->whereIn('l.account_id', $cashAccountIds)
                ->where('j.entry_date', '<=', $asOf)
                ->select(DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
                ->first();
            return (float) ($row->d ?? 0) - (float) ($row->c ?? 0);
        };
        $openingCash = $cashAsOf(\Carbon\Carbon::parse($from)->subDay()->toDateString());
        $closingCash = $cashAsOf($to);

        $journalIds = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('j.cbe_node_id', $nodeId)
            ->whereIn('l.account_id', $cashAccountIds)
            ->whereBetween('j.entry_date', [$from, $to])
            ->distinct()->pluck('l.journal_id');

        $operating = collect();
        $investing = collect();

        if ($journalIds->isNotEmpty()) {
            $lines = DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
                ->whereIn('l.journal_id', $journalIds)
                ->select('l.journal_id', 'l.account_id', 'l.debit', 'l.credit', 'j.entry_date', 'j.description as journal_description', 'a.account_code')
                ->get()
                ->groupBy('journal_id');

            foreach ($lines as $group) {
                $cashLines = $group->filter(fn ($l) => in_array($l->account_id, $cashAccountIds, true));
                $otherLines = $group->filter(fn ($l) => ! in_array($l->account_id, $cashAccountIds, true));
                if ($cashLines->isEmpty() || $otherLines->isEmpty()) {
                    continue; // no cash effect, or internal transfer between our own cash accounts
                }

                $netCash = round($cashLines->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2);
                if (abs($netCash) < 0.01) {
                    continue;
                }

                $isInvesting = $otherLines->contains(fn ($l) => in_array($l->account_code, $investingCodes, true));
                $row = (object) [
                    'entry_date' => $group->first()->entry_date,
                    'description' => $group->first()->journal_description,
                    'amount' => $netCash,
                ];
                $isInvesting ? $investing->push($row) : $operating->push($row);
            }
        }

        $operating = $operating->sortBy('entry_date')->values();
        $investing = $investing->sortBy('entry_date')->values();
        $operatingTotal = round($operating->sum('amount'), 2);
        $investingTotal = round($investing->sum('amount'), 2);
        $netChange = round($operatingTotal + $investingTotal, 2);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Cash Flow Statement'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['CASH FLOWS FROM OPERATING ACTIVITIES'];
        $sheet[] = ['Date', 'Description', 'Amount (RM)'];
        foreach ($operating as $r) {
            $sheet[] = [\Carbon\Carbon::parse($r->entry_date)->format('d M Y'), $r->description, $r->amount];
        }
        $sheet[] = ['', 'Net Cash from Operating Activities', $operatingTotal];
        $sheet[] = [];
        $sheet[] = ['CASH FLOWS FROM INVESTING ACTIVITIES'];
        $sheet[] = ['Date', 'Description', 'Amount (RM)'];
        foreach ($investing as $r) {
            $sheet[] = [\Carbon\Carbon::parse($r->entry_date)->format('d M Y'), $r->description, $r->amount];
        }
        $sheet[] = ['', 'Net Cash from Investing Activities', $investingTotal];
        $sheet[] = [];
        $sheet[] = ['CASH FLOWS FROM FINANCING ACTIVITIES'];
        $sheet[] = ['', '(none recorded — no loan/borrowing feature yet)', 0];
        $sheet[] = ['', 'Net Cash from Financing Activities', 0];
        $sheet[] = [];
        $sheet[] = ['', 'Net Increase / (Decrease) in Cash', $netChange];
        $sheet[] = ['', 'Cash at Beginning of Period', round($openingCash, 2)];
        $sheet[] = ['', 'Cash at End of Period', round($openingCash + $netChange, 2)];
        $sheet[] = ['', 'Cash at End of Period (per ledger, cross-check)', round($closingCash, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Cash Flow Statement', $sheet);
        return $this->download($spreadsheet, 'Cash_Flow_Statement');
    }

    public function generalLedger(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->get('from') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        $accounts = DB::table('cbe_chart_of_accounts as a')
            ->join('cbe_journal_lines as l', 'l.account_id', '=', 'a.account_id')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('j.cbe_node_id', $nodeId)
            ->select('a.account_id', 'a.account_code', 'a.account_name')
            ->distinct()->orderBy('a.account_code')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — General Ledger'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];

        foreach ($accounts as $acc) {
            $sheet[] = [];
            $sheet[] = [$acc->account_code . ' — ' . $acc->account_name];
            $sheet[] = ['Date', 'Description', 'Debit (RM)', 'Credit (RM)'];

            $lines = DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->where('j.cbe_node_id', $nodeId)->where('l.account_id', $acc->account_id)
                ->whereBetween('j.entry_date', [$from, $to])
                ->select('j.entry_date', 'j.description', 'l.debit', 'l.credit')
                ->orderBy('j.entry_date')->get();

            foreach ($lines as $ln) {
                $sheet[] = [\Carbon\Carbon::parse($ln->entry_date)->format('d M Y'), $ln->description, (float) $ln->debit, (float) $ln->credit];
            }
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'General Ledger', $sheet);
        return $this->download($spreadsheet, 'General_Ledger');
    }

    // ---------- GL Enquiry screens (NEW 3 Sep 2026, Task #384) — four
    // on-screen (no-download) lookups mirroring the AR/AP Enquiry
    // pattern: Chart of Accounts Enquiry (balance-annotated CoA browse),
    // General Ledger Enquiry (single account + date range, running
    // balance), Journal Enquiry (multi-criteria search across all
    // journals), Trial Balance Enquiry (on-screen paginated version of
    // trialBalance()). The pre-existing transactionHistory() (Task #338)
    // already covers all-account audit-trail drill-down at the node
    // level, so a separate 5th "Account Transaction History" screen is
    // not duplicated here.

    public function chartOfAccountsEnquiry(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();
        $search = trim((string) $request->get('search'));

        $balances = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('j.cbe_node_id', $nodeId)
            ->where('j.entry_date', '<=', $asOf)
            ->groupBy('l.account_id')
            ->select('l.account_id', DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'))
            ->get()->keyBy('account_id');

        // NEW 4 Sep 2026 (Task #390) — visibleAccountsQuery(), not a raw
        // group_label_id filter: otherwise every node's local add-on
        // accounts would leak into every other node's Enquiry screen.
        $accountsQuery = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->orderBy('account_type')->orderBy('account_code');
        if ($search !== '') {
            $accountsQuery->where(function ($q) use ($search) {
                $q->where('account_code', 'like', '%'.$search.'%')->orWhere('account_name', 'like', '%'.$search.'%');
            });
        }

        $all = $accountsQuery->get()->map(function ($a) use ($balances) {
            $b = $balances->get($a->account_id);
            $debit = $b->total_debit ?? 0;
            $credit = $b->total_credit ?? 0;
            $a->closing_balance = round(self::normalBalance($a->account_type, (float) $debit, (float) $credit), 2);
            return $a;
        });

        $perPage = 12;
        $page = max(1, (int) $request->query('coaeqPage', 1));
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();
        $accounts = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice, $all->count(), $perPage, $page,
            ['path' => $request->url(), 'pageName' => 'coaeqPage']
        );
        $accounts->appends($request->except('coaeqPage'));

        return view('cbe.accounting.chart-of-accounts-enquiry', compact('accounts', 'asOf', 'search'));
    }

    public function generalLedgerEnquiry(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        // NEW 4 Sep 2026 (Task #390) — same leak fix as chartOfAccountsEnquiry().
        $accountsList = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->orderBy('account_type')->orderBy('account_code')->get();

        $accountId = $request->get('account_id');
        $from = $request->get('from') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        // NEW 23 Sep 2026 -- per Chris: when this screen is opened via
        // "View" from Chart of Accounts search results, the Prev button
        // must reliably return to that exact search (same filters/page),
        // not just the generic Accounting hub. The search screen passes
        // its own full URL in ?back=..., we carry it through every link
        // and form on this screen, and only ever treat it as a relative
        // path (never an absolute URL) so it can never be turned into an
        // open redirect.
        $back = (string) $request->get('back', '');
        if ($back !== '' && ! str_starts_with($back, '/')) {
            $back = '';
        }

        $account = null;
        $transactions = null;
        $openingBalance = 0;

        if ($accountId) {
            $account = $this->visibleAccountsQuery($groupLabelId, $nodeId)->where('account_id', $accountId)->first();
        }

        if ($account) {
            $isDebitNormal = in_array($account->account_type, ['ASSET', 'EXPENSE'], true);

            $priorDebit = (float) DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->where('j.cbe_node_id', $nodeId)->where('l.account_id', $accountId)
                ->where('j.entry_date', '<', $from)->sum('l.debit');
            $priorCredit = (float) DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->where('j.cbe_node_id', $nodeId)->where('l.account_id', $accountId)
                ->where('j.entry_date', '<', $from)->sum('l.credit');
            $openingBalance = round(self::normalBalance($account->account_type, $priorDebit, $priorCredit), 2);

            // ADDED 23 Sep 2026 -- per Chris: the GL drill-down needs a
            // "Transaction Type" column. journal_type_id is set when the
            // entry was posted through a Journal Voucher with a type
            // picked; auto-posted entries (a Sales Transaction, a Bill,
            // a Bill Payment) never had one, so those fall back to a
            // readable label built from source_type instead of showing
            // blank.
            $lines = DB::table('cbe_journal_lines as l')
                ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
                ->leftJoin('cbe_journal_types as jt', 'jt.type_id', '=', 'j.journal_type_id')
                ->where('j.cbe_node_id', $nodeId)->where('l.account_id', $accountId)
                ->whereBetween('j.entry_date', [$from, $to])
                ->select('j.journal_id', 'j.journal_no', 'j.entry_date as txn_date', 'j.description', 'j.source_type', 'jt.type_name', 'l.debit', 'l.credit')
                ->orderBy('j.entry_date')->orderBy('j.journal_no')->get();

            $sourceTypeLabels = [
                'TRANSACTION' => __('cbe_accounting.gl_txn_type_sales_transaction'),
                'BILL' => __('cbe_accounting.gl_txn_type_bill'),
                'BILL_PAYMENT' => __('cbe_accounting.gl_txn_type_bill_payment'),
                'MANUAL' => __('cbe_accounting.gl_txn_type_manual_jv'),
            ];
            $lines = $lines->map(function ($ln) use ($sourceTypeLabels) {
                $ln->transaction_type = $ln->type_name ?: ($sourceTypeLabels[$ln->source_type] ?? $ln->source_type);
                return $ln;
            });

            $balance = $openingBalance;
            $lines = $lines->map(function ($ln) use (&$balance, $isDebitNormal) {
                $balance += $isDebitNormal ? ((float) $ln->debit - (float) $ln->credit) : ((float) $ln->credit - (float) $ln->debit);
                $ln->running_balance = round($balance, 2);
                return $ln;
            });

            $page = (int) $request->get('glPage', 1);
            $perPage = 8;
            $items = $lines->slice(($page - 1) * $perPage, $perPage)->values();
            $transactions = new \Illuminate\Pagination\LengthAwarePaginator($items, $lines->count(), $perPage, $page, ['pageName' => 'glPage']);
            $transactions->appends($request->except('glPage'));
        }

        return view('cbe.accounting.general-ledger-enquiry', compact('accountsList', 'account', 'accountId', 'from', 'to', 'openingBalance', 'transactions', 'back'));
    }

    public function journalEnquiry(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $types = DB::table('cbe_journal_types')
            ->where(function ($q) use ($groupLabelId) { $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId); })
            ->orderByDesc('is_active')->orderBy('display_order')->orderBy('type_name')->get();

        $from = $request->get('from') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();
        $typeId = $request->get('journal_type_id');
        $keyword = trim((string) $request->get('keyword'));

        $query = DB::table('cbe_journal_entries as j')
            ->leftJoin('cbe_journal_types as t', 't.type_id', '=', 'j.journal_type_id')
            ->where('j.cbe_node_id', $nodeId)
            ->whereBetween('j.entry_date', [$from, $to]);

        if ($typeId) {
            $query->where('j.journal_type_id', $typeId);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('j.description', 'like', '%'.$keyword.'%')
                  ->orWhere('j.journal_no', 'like', '%'.$keyword.'%')
                  ->orWhere('j.reference_no', 'like', '%'.$keyword.'%');
            });
        }

        $journals = $query->select('j.*', 't.type_name', 't.type_name_zh')
            ->orderByDesc('j.entry_date')->orderByDesc('j.journal_no')
            ->paginate(10, ['*'], 'jePage');
        $journals->appends($request->except('jePage'));

        return view('cbe.accounting.journal-enquiry', compact('journals', 'types', 'from', 'to', 'typeId', 'keyword'));
    }

    public function trialBalanceEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();
        $balances = $this->accountBalances($nodeId, $asOf);

        $rows = [];
        $totalDebit = 0; $totalCredit = 0;
        foreach ($balances as $b) {
            $bal = self::normalBalance($b->account_type, (float) $b->total_debit, (float) $b->total_credit);
            $isDebitNormal = in_array($b->account_type, ['ASSET', 'EXPENSE'], true);
            $debitCol = $isDebitNormal ? max($bal, 0) : max(-$bal, 0);
            $creditCol = $isDebitNormal ? max(-$bal, 0) : max($bal, 0);
            $totalDebit += $debitCol; $totalCredit += $creditCol;
            $rows[] = (object) [
                'account_code' => $b->account_code, 'account_name' => $b->account_name,
                'account_type' => $b->account_type, 'debit' => round($debitCol, 2), 'credit' => round($creditCol, 2),
            ];
        }

        $perPage = 12;
        $page = max(1, (int) $request->query('tbeqPage', 1));
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);
        $balancesPage = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice, count($rows), $perPage, $page,
            ['path' => $request->url(), 'pageName' => 'tbeqPage']
        );
        $balancesPage->appends($request->except('tbeqPage'));

        return view('cbe.accounting.trial-balance-enquiry', compact('balancesPage', 'asOf', 'totalDebit', 'totalCredit'));
    }

    // ---------- GL Reports (NEW 3 Sep 2026, Task #385) — six downloadable
    // Excel reports beyond Trial Balance / Balance Sheet / P&L / General
    // Ledger (already built earlier): Journal Listing, Account Balance,
    // Monthly Summary, Unposted, Reversal, Adjustment Journal.

    public function glReportsHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.gl-reports-hub');
    }

    public function journalListingReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->get('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to_date') ?: now()->toDateString();

        $journals = DB::table('cbe_journal_entries as j')
            ->leftJoin('cbe_journal_types as t', 't.type_id', '=', 'j.journal_type_id')
            ->where('j.cbe_node_id', $nodeId)
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('j.journal_no', 'j.reference_no', 'j.entry_date', 'j.description', 'j.source_type', 'j.status', 't.type_name')
            ->orderBy('j.entry_date')->orderBy('j.journal_no')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Journal Listing'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y').' to '.\Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Journal No', 'Reference No', 'Date', 'Description', 'Type', 'Source', 'Status'];
        foreach ($journals as $j) {
            $sheet[] = [$j->journal_no ?: '', $j->reference_no ?: '', \Carbon\Carbon::parse($j->entry_date)->format('d M Y'), $j->description, $j->type_name ?: '', $j->source_type, $j->status];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Journal Listing', $sheet);
        return $this->download($spreadsheet, 'Journal_Listing');
    }

    public function accountBalanceReport(Request $request)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $from = $request->get('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to_date') ?: now()->toDateString();

        $priorBalances = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('j.cbe_node_id', $nodeId)->where('j.entry_date', '<', $from)
            ->groupBy('l.account_id')
            ->select('l.account_id', DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->get()->keyBy('account_id');

        $periodBalances = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('j.cbe_node_id', $nodeId)->whereBetween('j.entry_date', [$from, $to])
            ->groupBy('l.account_id')
            ->select('l.account_id', DB::raw('SUM(l.debit) as d'), DB::raw('SUM(l.credit) as c'))
            ->get()->keyBy('account_id');

        // NEW 4 Sep 2026 (Task #390) — same leak fix as chartOfAccountsEnquiry().
        $accounts = $this->visibleAccountsQuery($groupLabelId, $nodeId)
            ->orderBy('account_type')->orderBy('account_code')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Account Balance Summary'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y').' to '.\Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Account Code', 'Account Name', 'Type', 'Opening Balance', 'Period Debit', 'Period Credit', 'Closing Balance'];

        foreach ($accounts as $a) {
            $prior = $priorBalances->get($a->account_id);
            $period = $periodBalances->get($a->account_id);
            if (! $prior && ! $period) {
                continue;
            }
            $isDebitNormal = in_array($a->account_type, ['ASSET', 'EXPENSE'], true);
            $opening = self::normalBalance($a->account_type, (float) ($prior->d ?? 0), (float) ($prior->c ?? 0));
            $pd = (float) ($period->d ?? 0);
            $pc = (float) ($period->c ?? 0);
            $closing = $opening + ($isDebitNormal ? ($pd - $pc) : ($pc - $pd));
            $sheet[] = [$a->account_code, $a->account_name, $a->account_type, round($opening, 2), round($pd, 2), round($pc, 2), round($closing, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Account Balance', $sheet);
        return $this->download($spreadsheet, 'GL_Account_Balance');
    }

    public function monthlyGlSummaryReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) ($request->get('year') ?: now()->year);

        $rows = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('j.cbe_node_id', $nodeId)
            ->whereYear('j.entry_date', $year)
            ->groupBy(DB::raw('MONTH(j.entry_date)'))
            ->select(DB::raw('MONTH(j.entry_date) as m'), DB::raw('SUM(l.debit) as total_debit'), DB::raw('SUM(l.credit) as total_credit'), DB::raw('COUNT(DISTINCT j.journal_id) as journal_count'))
            ->get()->keyBy('m');

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — GL Monthly Summary'];
        $sheet[] = ['Year '.$year];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Month', 'No. of Journals', 'Total Debit (RM)', 'Total Credit (RM)'];
        $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $totalJournals = 0; $totalDebit = 0; $totalCredit = 0;
        foreach ($months as $i => $name) {
            $r = $rows->get($i + 1);
            $jc = $r->journal_count ?? 0;
            $d = (float) ($r->total_debit ?? 0);
            $c = (float) ($r->total_credit ?? 0);
            $totalJournals += $jc; $totalDebit += $d; $totalCredit += $c;
            $sheet[] = [$name, $jc, round($d, 2), round($c, 2)];
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', $totalJournals, round($totalDebit, 2), round($totalCredit, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'GL Monthly Summary', $sheet);
        return $this->download($spreadsheet, 'GL_Monthly_Summary');
    }

    public function unpostedJournalsReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $drafts = DB::table('cbe_journal_voucher_drafts as j')
            ->join('agents as a', 'a.agent_id', '=', 'j.prepared_by')
            ->where('j.cbe_node_id', $nodeId)->where('j.status', 'PENDING')
            ->select('j.entry_date', 'j.description', 'j.total_amount', 'a.full_name as prepared_by_name', 'j.created_at')
            ->orderBy('j.entry_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Unposted Journals (Pending Approval)'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Description', 'Amount (RM)', 'Prepared By', 'Days Pending'];
        foreach ($drafts as $d) {
            $daysPending = \Carbon\Carbon::parse($d->created_at)->diffInDays(now());
            $sheet[] = [\Carbon\Carbon::parse($d->entry_date)->format('d M Y'), $d->description, (float) $d->total_amount, $d->prepared_by_name ?: '—', $daysPending];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Unposted Journals', $sheet);
        return $this->download($spreadsheet, 'Unposted_Journals');
    }

    public function reversalListingReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->get('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to_date') ?: now()->toDateString();

        $rows = DB::table('cbe_journal_entries as j')
            ->leftJoin('cbe_journal_entries as r', 'r.journal_id', '=', 'j.reversed_by_journal_id')
            ->leftJoin('agents as v', 'v.agent_id', '=', 'j.voided_by')
            ->where('j.cbe_node_id', $nodeId)->where('j.status', 'VOIDED')
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('j.journal_no as original_journal_no', 'j.entry_date', 'j.description', 'j.void_reason', 'j.voided_at', 'v.full_name as voided_by_name', 'r.journal_no as reversal_journal_no')
            ->orderBy('j.voided_at')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Reversal Listing'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y').' to '.\Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Original Journal No', 'Date', 'Description', 'Reversal Journal No', 'Void Reason', 'Voided By', 'Voided At'];
        foreach ($rows as $r) {
            $sheet[] = [$r->original_journal_no ?: '', \Carbon\Carbon::parse($r->entry_date)->format('d M Y'), $r->description, $r->reversal_journal_no ?: '—', $r->void_reason ?: '', $r->voided_by_name ?: '', $r->voided_at ? \Carbon\Carbon::parse($r->voided_at)->format('d M Y H:i') : ''];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Reversal Listing', $sheet);
        return $this->download($spreadsheet, 'Reversal_Listing');
    }

    public function adjustmentJournalReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->get('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to_date') ?: now()->toDateString();

        $rows = DB::table('cbe_journal_entries as j')
            ->join('cbe_journal_types as t', 't.type_id', '=', 'j.journal_type_id')
            ->where('j.cbe_node_id', $nodeId)->where('t.type_code', 'ADJUSTMENT')
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('j.journal_no', 'j.reference_no', 'j.entry_date', 'j.description', 'j.status')
            ->orderBy('j.entry_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Adjustment Journal Listing'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y').' to '.\Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Journal No', 'Reference No', 'Date', 'Description', 'Status'];
        foreach ($rows as $r) {
            $sheet[] = [$r->journal_no ?: '', $r->reference_no ?: '', \Carbon\Carbon::parse($r->entry_date)->format('d M Y'), $r->description, $r->status];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Adjustment Journal Listing', $sheet);
        return $this->download($spreadsheet, 'Adjustment_Journal_Listing');
    }

    // ---------- GL Control / Integration (NEW 3 Sep 2026, Task #386).
    // Duplicate-posting protection itself lives in CbeAccountingService
    // (an idempotency guard added to every one-shot document posting
    // function — see "Task #386" comments there) rather than here; this
    // section is the on-screen Integration Status board plus the two
    // remaining GL reconciliation reports (Fixed Asset, Bank
    // Reconciliation) mirroring the AR/AP ones built earlier.

    public function integrationStatus()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $sources = [
            ['table' => 'cbe_invoices', 'label' => 'integration_src_invoices', 'route' => 'cbe.accounting.invoice-enquiry'],
            ['table' => 'cbe_ar_debit_notes', 'label' => 'integration_src_ar_debit_notes', 'route' => 'cbe.accounting.ar-debit-notes'],
            ['table' => 'cbe_ar_credit_notes', 'label' => 'integration_src_ar_credit_notes', 'route' => 'cbe.accounting.ar-credit-notes'],
            ['table' => 'cbe_ar_adjustments', 'label' => 'integration_src_ar_adjustments', 'route' => 'cbe.accounting.ar-adjustments'],
            ['table' => 'cbe_ar_refunds', 'label' => 'integration_src_ar_refunds', 'route' => 'cbe.accounting.ar-refunds'],
            ['table' => 'cbe_ar_opening_balances', 'label' => 'integration_src_ar_opening_balances', 'route' => 'cbe.accounting.ar-opening-balances'],
            ['table' => 'cbe_purchase_bills', 'label' => 'integration_src_purchase_bills', 'route' => 'cbe.accounting.bill-enquiry'],
            ['table' => 'cbe_debit_notes', 'label' => 'integration_src_ap_credit_notes', 'route' => 'cbe.accounting.suppliers'],
            ['table' => 'cbe_ap_debit_notes', 'label' => 'integration_src_ap_debit_notes', 'route' => 'cbe.accounting.ap-debit-notes'],
            ['table' => 'cbe_ap_refunds', 'label' => 'integration_src_ap_refunds', 'route' => 'cbe.accounting.ap-refunds'],
            ['table' => 'cbe_ap_adjustments', 'label' => 'integration_src_ap_adjustments', 'route' => 'cbe.accounting.ap-adjustments'],
            ['table' => 'cbe_ap_opening_balances', 'label' => 'integration_src_ap_opening_balances', 'route' => 'cbe.accounting.ap-opening-balances'],
            ['table' => 'cbe_donation_pledges', 'label' => 'integration_src_donation_pledges', 'route' => 'cbe.accounting.donation-pledges'],
        ];

        $rows = collect();
        foreach ($sources as $src) {
            $counts = DB::table($src['table'])->where('cbe_node_id', $nodeId)
                ->select('gl_posting_status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('gl_posting_status')->pluck('cnt', 'gl_posting_status');
            $rows->push($this->integrationRow($src['label'], $src['route'], $counts));
        }

        $invoicePaymentCounts = DB::table('cbe_invoice_payments as p')
            ->join('cbe_invoices as i', 'i.invoice_id', '=', 'p.invoice_id')
            ->where('i.cbe_node_id', $nodeId)
            ->select('p.gl_posting_status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('p.gl_posting_status')->pluck('cnt', 'gl_posting_status');
        $rows->push($this->integrationRow('integration_src_invoice_payments', 'cbe.accounting.receipt-enquiry', $invoicePaymentCounts));

        $billPaymentCounts = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->where('b.cbe_node_id', $nodeId)
            ->select('p.gl_posting_status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('p.gl_posting_status')->pluck('cnt', 'gl_posting_status');
        $rows->push($this->integrationRow('integration_src_bill_payments', 'cbe.accounting.ap-payment-enquiry', $billPaymentCounts));

        $pledgeReceiptCounts = DB::table('cbe_pledge_receipts as r')
            ->join('cbe_donation_pledges as pl', 'pl.pledge_id', '=', 'r.pledge_id')
            ->where('pl.cbe_node_id', $nodeId)
            ->select('r.gl_posting_status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('r.gl_posting_status')->pluck('cnt', 'gl_posting_status');
        $rows->push($this->integrationRow('integration_src_pledge_receipts', 'cbe.accounting.donation-pledges', $pledgeReceiptCounts));

        return view('cbe.accounting.integration-status', compact('rows'));
    }

    private function integrationRow(string $labelKey, string $route, \Illuminate\Support\Collection $counts): object
    {
        return (object) [
            'label' => __('cbe_accounting.'.$labelKey),
            'route' => $route,
            'posted' => (int) $counts->get('POSTED', 0),
            'not_posted' => (int) $counts->get('NOT_POSTED', 0),
            'reversed' => (int) $counts->get('REVERSED', 0),
            'error' => (int) $counts->get('ERROR', 0),
        ];
    }

    public function fixedAssetGlReconciliationReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();

        $subLedgerCost = (float) DB::table('cbe_fixed_assets')
            ->where('cbe_node_id', $nodeId)->where('status', '!=', 'DISPOSED')
            ->where('acquired_date', '<=', $asOf)
            ->sum('acquisition_cost');

        // FIXED 4 Sep 2026 (Task #395 Phase 4) — a posted Asset Improvement
        // (spec section 9) debits the SAME Fixed Asset GL account as the
        // original acquisition (see postAssetImprovement()), so leaving it
        // out here understated the sub-ledger side and made this report
        // show a false difference for any asset with an improvement on it.
        $improvementsCost = (float) DB::table('cbe_fixed_asset_improvements as imp')
            ->join('cbe_fixed_assets as a', 'a.asset_id', '=', 'imp.asset_id')
            ->where('a.cbe_node_id', $nodeId)->where('a.status', '!=', 'DISPOSED')
            ->where('imp.transaction_date', '<=', $asOf)
            ->sum('imp.additional_cost');
        $subLedgerCost += $improvementsCost;

        $depreciationPosted = (float) DB::table('cbe_fixed_asset_depreciation_entries as e')
            ->join('cbe_fixed_assets as a', 'a.asset_id', '=', 'e.asset_id')
            ->where('a.cbe_node_id', $nodeId)->where('a.status', '!=', 'DISPOSED')
            ->where('e.period_month', '<=', substr($asOf, 0, 7))
            ->sum('e.amount');

        $subLedgerNbv = $subLedgerCost - $depreciationPosted;

        $balances = $this->accountBalances($nodeId, $asOf);
        $faAccount = $balances->firstWhere('account_code', \App\Services\CbeAccountingService::FIXED_ASSET_CODE);
        $accumAccount = $balances->firstWhere('account_code', \App\Services\CbeAccountingService::ACCUM_DEPRECIATION_CODE);
        $faBalance = $faAccount ? self::normalBalance('ASSET', (float) $faAccount->total_debit, (float) $faAccount->total_credit) : 0.0;
        $accumBalance = $accumAccount ? self::normalBalance('ASSET', (float) $accumAccount->total_debit, (float) $accumAccount->total_credit) : 0.0;
        $controlBalance = $faBalance + $accumBalance;

        $difference = round($subLedgerNbv - $controlBalance, 2);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Fixed Asset / GL Reconciliation'];
        $sheet[] = ['As of '.\Carbon\Carbon::parse($asOf)->format('d M Y')];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Description', 'Amount (RM)'];
        $sheet[] = ['Fixed Asset Sub-Ledger Net Book Value (cost less posted depreciation)', round($subLedgerNbv, 2)];
        $sheet[] = ['GL Control Balance (Fixed Assets '.\App\Services\CbeAccountingService::FIXED_ASSET_CODE.' less Accum. Depreciation '.\App\Services\CbeAccountingService::ACCUM_DEPRECIATION_CODE.')', round($controlBalance, 2)];
        $sheet[] = ['Difference (expected RM0.00)', $difference];
        $sheet[] = [];
        $sheet[] = [$difference == 0.0 ? 'RECONCILED — no difference found.' : 'NOT RECONCILED — investigate unposted or misposted fixed asset entries.'];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'FA-GL Reconciliation', $sheet);
        return $this->download($spreadsheet, 'FA_GL_Reconciliation');
    }

    public function bankReconciliationGlReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();

        $bankAccounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Bank Reconciliation / GL Report'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Bank Account', 'Last Reconciled Date', 'Reconciliation Ending Balance (RM)', 'GL Balance as of that Date (RM)', 'Difference (RM)', 'Status'];

        foreach ($bankAccounts as $ba) {
            $latest = DB::table('cbe_bank_reconciliations')
                ->where('cbe_node_id', $nodeId)->where('bank_account_id', $ba->bank_account_id)
                ->where('status', 'COMPLETED')
                ->orderByDesc('statement_date')->first();

            if (! $latest) {
                $sheet[] = [$ba->account_name ?: $ba->bank_name, '—', '—', '—', '—', 'No completed reconciliation yet'];
                continue;
            }
            if (! $ba->gl_account_id) {
                $sheet[] = [$ba->account_name ?: $ba->bank_name, \Carbon\Carbon::parse($latest->statement_date)->format('d M Y'), round((float) $latest->ending_balance, 2), '—', '—', 'No GL sub-account linked'];
                continue;
            }

            $balances = $this->accountBalances($nodeId, $latest->statement_date);
            $glAcc = $balances->firstWhere('account_id', $ba->gl_account_id);
            $glBalance = $glAcc ? self::normalBalance('ASSET', (float) $glAcc->total_debit, (float) $glAcc->total_credit) : 0.0;
            $diff = round((float) $latest->ending_balance - $glBalance, 2);

            $sheet[] = [
                $ba->account_name ?: $ba->bank_name,
                \Carbon\Carbon::parse($latest->statement_date)->format('d M Y'),
                round((float) $latest->ending_balance, 2),
                round($glBalance, 2),
                $diff,
                $diff == 0.0 ? 'RECONCILED' : 'NOT RECONCILED',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Bank Reconciliation - GL', $sheet);
        return $this->download($spreadsheet, 'Bank_Reconciliation_GL');
    }

    // ---------- Bank Reconciliation Reports (NEW 4 Sep 2026, Task
    // #396) — Bank Reconciliation Module upgrade, Phase 5, spec
    // section 8. Same Excel-download pattern as every other report in
    // this app (writeSheet()/download() helpers). ----------

    public function bankReconciliationReportsHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliations = DB::table('cbe_bank_reconciliations as r')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 'b.bank_name', 'b.account_name')
            ->orderByDesc('r.statement_date')->limit(50)->get();

        return view('cbe.accounting.bank-reconciliation-reports-hub', compact('reconciliations'));
    }

    public function bankAccountListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $accounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->orderBy('account_code')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Bank Account Listing'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Code', 'Bank / Account', 'Account No.', 'Type', 'Opening Balance (RM)', 'Current Balance (RM)', 'Status'];

        foreach ($accounts as $a) {
            $sheet[] = [
                $a->account_code ?: '—',
                trim($a->bank_name.($a->account_name ? ' — '.$a->account_name : '')),
                $a->account_number,
                $a->account_type,
                round((float) $a->opening_balance, 2),
                round(CbeAccountingService::bankAccountBalanceAsOf($a->bank_account_id, now()->toDateString()), 2),
                $a->is_active ? 'ACTIVE' : 'INACTIVE',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Bank Account Listing', $sheet);
        return $this->download($spreadsheet, 'Bank_Account_Listing');
    }

    public function bankTransactionReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $dateFrom = $request->input('from') ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->input('to') ?: now()->toDateString();

        $rows = DB::table('cbe_bank_transactions as t')
            ->join('cbe_bank_accounts as b', 'b.bank_account_id', '=', 't.bank_account_id')
            ->leftJoin('cbe_bank_transaction_types as tt', 'tt.type_id', '=', 't.transaction_type_id')
            ->where('t.cbe_node_id', $nodeId)
            ->whereBetween('t.transaction_date', [$dateFrom, $dateTo])
            ->select('t.*', 'b.bank_name', 'b.account_name', 'tt.type_name')
            ->orderBy('b.bank_name')->orderBy('t.transaction_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Bank Transaction Report'];
        $sheet[] = ['Period: '.\Carbon\Carbon::parse($dateFrom)->format('d M Y').' to '.\Carbon\Carbon::parse($dateTo)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Bank Account', 'Description', 'Type', 'Reference', 'Amount (RM)', 'Source', 'Status'];

        foreach ($rows as $r) {
            $sheet[] = [
                \Carbon\Carbon::parse($r->transaction_date)->format('d M Y'),
                trim($r->bank_name.($r->account_name ? ' — '.$r->account_name : '')),
                $r->description,
                $r->type_name ?: '—',
                $r->reference_no ?: ($r->cheque_no ?: '—'),
                round((float) $r->amount, 2),
                $r->source,
                $r->status,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Bank Transaction Report', $sheet);
        return $this->download($spreadsheet, 'Bank_Transaction_Report');
    }

    // Printable/exportable version of ONE reconciliation — opening
    // balance, every matched pair (bank side vs. system side), still-
    // unmatched items, and the ending balance, mirroring a classic
    // paper bank reconciliation statement.
    public function bankReconciliationStatementReport(string $reconciliationId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $reconciliation = DB::table('cbe_bank_reconciliations as r')
            ->leftJoin('cbe_bank_accounts as b', 'b.bank_account_id', '=', 'r.bank_account_id')
            ->where('r.reconciliation_id', $reconciliationId)->where('r.cbe_node_id', $nodeId)
            ->select('r.*', 'b.bank_name', 'b.account_name')
            ->firstOrFail();

        $matches = DB::table('cbe_bank_reconciliation_matches as m')
            ->leftJoin('cbe_bank_transactions as t', 't.transaction_id', '=', 'm.bank_transaction_id')
            ->leftJoin('cbe_journal_lines as l', 'l.line_id', '=', 'm.journal_line_id')
            ->leftJoin('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->where('m.reconciliation_id', $reconciliationId)
            ->select('m.*', 't.description as bank_description', 't.transaction_date as bank_date', 'j.description as system_description', 'j.entry_date as system_date')
            ->orderBy('m.match_group_id')->get()
            ->groupBy('match_group_id');

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Bank Reconciliation Statement'];
        $sheet[] = [trim(($reconciliation->bank_name ?: 'Default').($reconciliation->account_name ? ' — '.$reconciliation->account_name : '')).' — '.\Carbon\Carbon::parse($reconciliation->statement_date)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Opening Balance (RM)', round((float) $reconciliation->opening_balance, 2)];
        $sheet[] = ['Ending Balance per Statement (RM)', round((float) $reconciliation->ending_balance, 2)];
        $sheet[] = ['Status', $reconciliation->status];
        $sheet[] = [];
        $sheet[] = ['Matched Items'];
        $sheet[] = ['Bank Side (Date / Description)', 'System Side (Date / Description)', 'Amount (RM)'];

        foreach ($matches as $group) {
            $bankSide = $group->where('side', 'BANK');
            $sysSide = $group->where('side', 'SYSTEM');
            $total = round((float) $bankSide->sum('amount'), 2);
            $bankText = $bankSide->map(fn ($b) => ($b->bank_date ? \Carbon\Carbon::parse($b->bank_date)->format('d/m/Y') : '—').' — '.$b->bank_description)->implode('; ');
            $sysText = $sysSide->map(fn ($s) => ($s->system_date ? \Carbon\Carbon::parse($s->system_date)->format('d/m/Y') : '—').' — '.$s->system_description)->implode('; ');
            $sheet[] = [$bankText, $sysText, $total];
        }

        $sheet[] = [];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Bank Reconciliation Statement', $sheet);
        return $this->download($spreadsheet, 'Bank_Reconciliation_Statement');
    }

    // Cheques recorded as paid in the books but not yet cleared by the
    // bank — an unmatched SYSTEM-side reduction to the bank's GL
    // account. Identified by the journal's own reference number field
    // (this app has no separate dedicated cheque-number column on the
    // journal line itself, so the reference number is what carries it
    // when a payment was made by cheque).
    public function outstandingChequeReport()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $accounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Outstanding Cheque Report'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Bank Account', 'Date', 'Reference / Cheque No.', 'Description', 'Amount (RM)'];

        foreach ($accounts as $a) {
            $entries = CbeAccountingService::unmatchedSystemEntries($a->bank_account_id, $nodeId, $groupLabelId, '1900-01-01', now()->toDateString(), 10000, 'p');
            foreach ($entries as $e) {
                $signed = round((float) $e->debit - (float) $e->credit, 2);
                if ($signed >= 0) { continue; }
                $sheet[] = [
                    trim($a->bank_name.($a->account_name ? ' — '.$a->account_name : '')),
                    \Carbon\Carbon::parse($e->entry_date)->format('d M Y'),
                    $e->reference_no ?: '—',
                    $e->description,
                    $signed,
                ];
            }
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Outstanding Cheques', $sheet);
        return $this->download($spreadsheet, 'Outstanding_Cheque_Report');
    }

    // Mirror of the above — deposits recorded in the books that haven't
    // shown up on the bank statement yet (unmatched SYSTEM-side
    // increase to the bank's GL account).
    public function depositsInTransitReport()
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $accounts = DB::table('cbe_bank_accounts')->where('cbe_node_id', $nodeId)->where('is_active', true)->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Deposits in Transit Report'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Bank Account', 'Date', 'Reference', 'Description', 'Amount (RM)'];

        foreach ($accounts as $a) {
            $entries = CbeAccountingService::unmatchedSystemEntries($a->bank_account_id, $nodeId, $groupLabelId, '1900-01-01', now()->toDateString(), 10000, 'p');
            foreach ($entries as $e) {
                $signed = round((float) $e->debit - (float) $e->credit, 2);
                if ($signed <= 0) { continue; }
                $sheet[] = [
                    trim($a->bank_name.($a->account_name ? ' — '.$a->account_name : '')),
                    \Carbon\Carbon::parse($e->entry_date)->format('d M Y'),
                    $e->reference_no ?: '—',
                    $e->description,
                    $signed,
                ];
            }
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Deposits in Transit', $sheet);
        return $this->download($spreadsheet, 'Deposits_In_Transit_Report');
    }

    public function unmatchedBankTransactionReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $rows = DB::table('cbe_bank_transactions as t')
            ->join('cbe_bank_accounts as b', 'b.bank_account_id', '=', 't.bank_account_id')
            ->where('t.cbe_node_id', $nodeId)->where('t.status', 'UNRECONCILED')
            ->select('t.*', 'b.bank_name', 'b.account_name')
            ->orderBy('b.bank_name')->orderBy('t.transaction_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Unmatched Bank Transaction Report'];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Bank Account', 'Date', 'Description', 'Reference', 'Amount (RM)', 'Source'];

        foreach ($rows as $r) {
            $sheet[] = [
                trim($r->bank_name.($r->account_name ? ' — '.$r->account_name : '')),
                \Carbon\Carbon::parse($r->transaction_date)->format('d M Y'),
                $r->description,
                $r->reference_no ?: ($r->cheque_no ?: '—'),
                round((float) $r->amount, 2),
                $r->source,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Unmatched Bank Transactions', $sheet);
        return $this->download($spreadsheet, 'Unmatched_Bank_Transaction_Report');
    }

    // Bank Charges / Bank Interest reports both read cbe_bank_transactions,
    // since every Bank Adjustment (the module's dedicated screen for
    // this) is tagged source=ADJUSTMENT with a signed amount — negative
    // for a charge, positive for interest. Also picks up any manually
    // entered/imported line classified with the system-seeded "Bank
    // Charge"/"Bank Interest" transaction type, in case it wasn't
    // entered via the Adjustment screen.
    private function bankAdjustmentTypeReport(string $nodeId, string $typeName, bool $negative, string $title, string $filenamePrefix)
    {
        $rows = DB::table('cbe_bank_transactions as t')
            ->join('cbe_bank_accounts as b', 'b.bank_account_id', '=', 't.bank_account_id')
            ->leftJoin('cbe_bank_transaction_types as tt', 'tt.type_id', '=', 't.transaction_type_id')
            ->where('t.cbe_node_id', $nodeId)
            ->where(function ($q) use ($typeName) {
                $q->where('t.source', 'ADJUSTMENT')->orWhere('tt.type_name', $typeName);
            })
            ->when($negative, fn ($q) => $q->where('t.amount', '<', 0), fn ($q) => $q->where('t.amount', '>', 0))
            ->select('t.*', 'b.bank_name', 'b.account_name')
            ->orderBy('b.bank_name')->orderBy('t.transaction_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — '.$title];
        $sheet[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Bank Account', 'Date', 'Description', 'Amount (RM)'];
        $total = 0.0;
        foreach ($rows as $r) {
            $sheet[] = [trim($r->bank_name.($r->account_name ? ' — '.$r->account_name : '')), \Carbon\Carbon::parse($r->transaction_date)->format('d M Y'), $r->description, round((float) $r->amount, 2)];
            $total += (float) $r->amount;
        }
        $sheet[] = [];
        $sheet[] = ['Total (RM)', '', '', round($total, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, $title, $sheet);
        return $this->download($spreadsheet, $filenamePrefix);
    }

    public function bankChargesReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return $this->bankAdjustmentTypeReport($nodeId, 'Bank Charge', true, 'Bank Charges Report', 'Bank_Charges_Report');
    }

    public function bankInterestReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return $this->bankAdjustmentTypeReport($nodeId, 'Bank Interest', false, 'Bank Interest Report', 'Bank_Interest_Report');
    }

    // ---------- AP Enquiry: Supplier Account Enquiry (NEW 3 Sep 2026,
    // Task #374) — per Chris's AP spec: an on-screen lookup (not a
    // downloadable report) showing one supplier's category/terms,
    // outstanding balance, and full transaction history (Bill/Payment/
    // Debit Note/Credit Note/Adjustment/Refund) with a running balance —
    // mirrors customerEnquiry() on the AR side.

    public function supplierEnquiry(string $supplierId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $supplier = DB::table('cbe_suppliers as s')
            ->leftJoin('cbe_supplier_categories as sc', 'sc.category_id', '=', 's.category_id')
            ->leftJoin('cbe_payment_terms as pt', 'pt.term_id', '=', 's.payment_term_id')
            ->where('s.supplier_id', $supplierId)->where('s.cbe_node_id', $nodeId)
            ->select('s.*', 'sc.category_name', 'pt.term_name', 'pt.net_days')
            ->first();

        if (! $supplier) {
            return redirect()->route('cbe.accounting.suppliers');
        }

        $outstanding = (float) DB::table('cbe_purchase_bills')->where('supplier_id', $supplierId)
            ->where('status', '!=', 'CANCELLED')
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')->value('total');

        $bills = DB::table('cbe_purchase_bills')->where('supplier_id', $supplierId)
            ->select('bill_no as ref_no', 'bill_date as txn_date', DB::raw("'Bill' as txn_type"), DB::raw('0 as debit'), 'amount as credit', 'journal_id', 'gl_posting_status');
        $payments = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->where('b.supplier_id', $supplierId)
            ->select('b.bill_no as ref_no', 'p.payment_date as txn_date', DB::raw("'Payment' as txn_type"), 'p.amount as debit', DB::raw('0 as credit'), 'p.journal_id', 'p.gl_posting_status');
        $creditNotes = DB::table('cbe_debit_notes as dn')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.supplier_id', $supplierId)
            ->select('b.bill_no as ref_no', 'dn.note_date as txn_date', DB::raw("'Credit Note' as txn_type"), 'dn.amount as debit', DB::raw('0 as credit'), 'dn.journal_id', 'dn.gl_posting_status');
        $debitNotes = DB::table('cbe_ap_debit_notes as dn')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.supplier_id', $supplierId)
            ->select('b.bill_no as ref_no', 'dn.note_date as txn_date', DB::raw("'Debit Note' as txn_type"), DB::raw('0 as debit'), 'dn.amount as credit', 'dn.journal_id', 'dn.gl_posting_status');

        $allTxns = $bills->get()->concat($payments->get())->concat($creditNotes->get())->concat($debitNotes->get())
            ->sortBy('txn_date')->values();
        $balance = 0;
        $allTxns = $allTxns->map(function ($line) use (&$balance) {
            $balance += (float) $line->credit - (float) $line->debit;
            $line->running_balance = round($balance, 2);
            return $line;
        });

        $page = (int) request('txnPage', 1);
        $perPage = 8;
        $items = $allTxns->slice(($page - 1) * $perPage, $perPage)->values();
        $transactions = new \Illuminate\Pagination\LengthAwarePaginator($items, $allTxns->count(), $perPage, $page, ['pageName' => 'txnPage']);

        return view('cbe.accounting.supplier-enquiry', compact('supplier', 'outstanding', 'transactions'));
    }

    // NEW 4 Sep 2026 (Task #394 gap-fix) — Supplier Enquiry only ever
    // showed the AP side of a supplier's history (bills/payments/notes).
    // The Purchasing spec's drill-down also wants that supplier's
    // Requisitions, Quotations, Purchase Orders, Goods Receipts and
    // Returns visible from one place — this combines all 5 into one
    // paginated activity list, each row linking to its own detail screen.
    public function supplierProcurementHistory(string $supplierId)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $supplier = DB::table('cbe_suppliers')->where('supplier_id', $supplierId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $requisitions = DB::table('cbe_purchase_requests')->where('supplier_id', $supplierId)
            ->select('request_id as doc_id', 'doc_ref_no', 'request_date as doc_date', 'status', 'amount', DB::raw("'PR' as doc_type"));
        $quotations = DB::table('cbe_purchase_rfq_suppliers as qs')
            ->join('cbe_purchase_rfqs as q', 'q.rfq_id', '=', 'qs.rfq_id')
            ->where('qs.supplier_id', $supplierId)
            ->select('q.rfq_id as doc_id', 'q.doc_ref_no', 'q.rfq_date as doc_date', 'q.status', DB::raw('COALESCE(qs.quoted_amount, 0) as amount'), DB::raw("'RFQ' as doc_type"));
        $orders = DB::table('cbe_purchase_orders')->where('supplier_id', $supplierId)
            ->select('po_id as doc_id', 'doc_ref_no', 'po_date as doc_date', 'status', 'amount', DB::raw("'PO' as doc_type"));
        $receipts = DB::table('cbe_goods_receipts')->where('supplier_id', $supplierId)
            ->select('grn_id as doc_id', 'doc_ref_no', 'grn_date as doc_date', 'status', DB::raw('0 as amount'), DB::raw("'GRN' as doc_type"));
        $returns = DB::table('cbe_purchase_returns')->where('supplier_id', $supplierId)
            ->select('return_id as doc_id', 'doc_ref_no', 'return_date as doc_date', 'status', DB::raw('0 as amount'), DB::raw("'PRN' as doc_type"));

        $all = $requisitions->get()->concat($quotations->get())->concat($orders->get())
            ->concat($receipts->get())->concat($returns->get())
            ->sortByDesc('doc_date')->values();

        $page = (int) request('procPage', 1);
        $perPage = 8;
        $items = $all->slice(($page - 1) * $perPage, $perPage)->values();
        $documents = new \Illuminate\Pagination\LengthAwarePaginator($items, $all->count(), $perPage, $page, ['pageName' => 'procPage']);

        return view('cbe.accounting.supplier-procurement-history', compact('supplier', 'documents'));
    }

    // ---------- AP Enquiry: Supplier Invoice Enquiry (NEW 3 Sep 2026,
    // Task #374) — mirrors invoiceEnquiry()/invoiceEnquiryShow() on the
    // AR side.

    public function billEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->orderBy('supplier_name')->get();

        $query = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('b.bill_no', 'like', '%'.$search.'%')->orWhere('b.doc_ref_no', 'like', '%'.$search.'%');
            });
        }
        if ($supplierId = $request->input('supplier_id')) {
            $query->where('b.supplier_id', $supplierId);
        }

        $bills = $query->select('b.*', 's.supplier_name')
            ->orderByDesc('b.bill_date')
            ->paginate(8, ['*'], 'billEnqPage')
            ->withQueryString();

        return view('cbe.accounting.bill-enquiry', compact('bills', 'suppliers'));
    }

    public function billEnquiryShow(string $bill)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $b = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->leftJoin('cbe_purchase_orders as po', 'po.po_id', '=', 'b.po_id')
            ->leftJoin('cbe_goods_receipts as g', 'g.grn_id', '=', 'b.grn_id')
            ->where('b.cbe_node_id', $nodeId)->where('b.bill_id', $bill)
            ->select('b.*', 's.supplier_name', 'po.doc_ref_no as po_doc_ref_no', 'g.doc_ref_no as grn_doc_ref_no')
            ->first();

        if (! $b) {
            return redirect()->route('cbe.accounting.bill-enquiry');
        }

        $lines = DB::table('cbe_bill_lines as l')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'l.category_id')
            ->where('l.bill_id', $bill)
            ->select('l.*', 'c.category_name')
            ->orderBy('l.display_order')->get();

        $payments = DB::table('cbe_bill_payments as p')
            ->leftJoin('cbe_bank_accounts as bk', 'bk.bank_account_id', '=', 'p.bank_account_id')
            ->where('p.bill_id', $bill)
            ->select('p.*', 'bk.bank_name')
            ->orderBy('p.payment_date')->get();

        $creditNotes = DB::table('cbe_debit_notes')->where('bill_id', $bill)->orderBy('note_date')->get();
        $debitNotes = DB::table('cbe_ap_debit_notes')->where('bill_id', $bill)->orderBy('note_date')->get();

        // NEW 4 Sep 2026 (Task #394 gap-fix) — Capital Asset Purchase flow:
        // if this bill already created a Fixed Asset register entry, link
        // to it; otherwise the view offers a "Create Fixed Asset" action.
        $linkedAsset = DB::table('cbe_fixed_assets')->where('bill_id', $bill)->first();

        return view('cbe.accounting.bill-enquiry-show', compact('b', 'lines', 'payments', 'debitNotes', 'creditNotes', 'linkedAsset'));
    }

    // NEW 4 Sep 2026 (Task #394 gap-fix) — Capital Asset Purchase flow
    // (Purchasing -> Invoice -> AP -> Fixed Asset -> GL). See the
    // CbeAccountingService::createFixedAssetFromBill() comment for why
    // this does not post a second GL journal.
    public function createFixedAssetFromBill(string $bill)
    {
        [$agent, $nodeId, $groupLabelId] = $this->nodeAndGroup();
        $b = DB::table('cbe_purchase_bills as bl')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'bl.supplier_id')
            ->where('bl.bill_id', $bill)->where('bl.cbe_node_id', $nodeId)
            ->select('bl.*', 's.supplier_name')->firstOrFail();

        if (DB::table('cbe_fixed_assets')->where('bill_id', $bill)->exists()) {
            return redirect()->route('cbe.accounting.bill-enquiry.show', $bill);
        }

        CbeAccountingService::ensureAssetCategories($groupLabelId);
        CbeAccountingService::ensureAssetLocations($nodeId);
        $categories = DB::table('cbe_asset_categories')
            ->where(function ($q) use ($groupLabelId) {
                $q->where('group_label_id', $groupLabelId);
                if ($groupLabelId === null) {
                    $q->orWhereNull('group_label_id');
                }
            })->where('is_active', true)->orderBy('display_order')->get();
        $locations = DB::table('cbe_asset_locations')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('display_order')->get();
        $costCentres = DB::table('cbe_cost_centres')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('is_active', true)->orderBy('centre_type')->orderBy('centre_name')->get();
        $funds = DB::table('cbe_funds')->where('cbe_node_id', $nodeId)->where('is_active', true)->orderBy('fund_name')->get();

        return view('cbe.accounting.create-fixed-asset-from-bill', compact('b', 'categories', 'locations', 'costCentres', 'funds'));
    }

    public function storeFixedAssetFromBill(Request $request, string $bill)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $b = DB::table('cbe_purchase_bills')->where('bill_id', $bill)->where('cbe_node_id', $nodeId)->firstOrFail();

        if (DB::table('cbe_fixed_assets')->where('bill_id', $bill)->exists()) {
            return redirect()->route('cbe.accounting.bill-enquiry.show', $bill);
        }

        $request->validate([
            'asset_name' => ['required', 'string', 'max:150'],
            'asset_class' => ['nullable', 'string', 'max:100'],
            'asset_tag' => ['nullable', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_asset_categories,category_id'],
            'location_id' => ['nullable', 'uuid', 'exists:cbe_asset_locations,location_id'],
            'cost_centre_id' => ['nullable', 'uuid', 'exists:cbe_cost_centres,centre_id'],
            'fund_id' => ['nullable', 'uuid', 'exists:cbe_funds,fund_id'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'depreciation_method' => ['required', 'in:STRAIGHT_LINE,REDUCING_BALANCE'],
        ]);

        $assetId = CbeAccountingService::createFixedAssetFromBill($bill, [
            'asset_name' => $request->input('asset_name'),
            'asset_class' => $request->input('asset_class'),
            'asset_tag' => $request->input('asset_tag'),
            'location' => $request->input('location'),
            'category_id' => $request->input('category_id') ?: null,
            'location_id' => $request->input('location_id') ?: null,
            'cost_centre_id' => $request->input('cost_centre_id') ?: null,
            'fund_id' => $request->input('fund_id') ?: null,
            'funding_source' => 'Purchasing — '.($b->bill_no ?: $b->doc_ref_no),
            'acquisition_cost' => round((float) $request->input('acquisition_cost'), 2),
            'salvage_value' => round((float) $request->input('salvage_value', 0), 2),
            'useful_life_months' => (int) $request->input('useful_life_months'),
            'depreciation_method' => $request->input('depreciation_method'),
        ], $agent->agent_id);

        CbeAccountingService::logFixedAssetAudit($nodeId, $assetId, $request->input('asset_name'), 'CREATED', $agent->agent_id, 'From Bill '.($b->bill_no ?: $b->doc_ref_no));

        return redirect()->route('cbe.accounting.fixed-assets.show', $assetId)->with('success', __('cbe_accounting.fixed_asset_saved'));
    }

    // ---------- AP Enquiry: Payment Enquiry (NEW 3 Sep 2026, Task #374)
    // — on-screen search across every payment made, mirrors
    // receiptEnquiry()/receiptEnquiryShow().

    public function apPaymentEnquiry(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->orderBy('supplier_name')->get();

        $query = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('p.pv_no', 'like', '%'.$search.'%')->orWhere('p.reference_no', 'like', '%'.$search.'%');
            });
        }
        if ($supplierId = $request->input('supplier_id')) {
            $query->where('b.supplier_id', $supplierId);
        }

        $payments = $query->select('p.*', 'b.bill_no', 'b.doc_ref_no', 's.supplier_name')
            ->orderByDesc('p.payment_date')
            ->paginate(8, ['*'], 'apPayEnqPage')
            ->withQueryString();

        return view('cbe.accounting.ap-payment-enquiry', compact('payments', 'suppliers'));
    }

    public function apPaymentEnquiryShow(string $payment)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $p = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->leftJoin('cbe_bank_accounts as bk', 'bk.bank_account_id', '=', 'p.bank_account_id')
            ->where('b.cbe_node_id', $nodeId)->where('p.payment_id', $payment)
            ->select('p.*', 'b.bill_no', 'b.doc_ref_no', 'b.bill_id', 's.supplier_name', 'bk.bank_name')
            ->first();

        if (! $p) {
            return redirect()->route('cbe.accounting.ap-payment-enquiry');
        }

        return view('cbe.accounting.ap-payment-enquiry-show', compact('p'));
    }

    // ---------- AP Enquiry: Outstanding Payable Enquiry (NEW 3 Sep 2026,
    // Task #374) — on-screen list of every supplier with a non-zero AP
    // balance, mirrors outstandingBalanceEnquiry(). The downloadable
    // equivalent is AP Aging.

    public function apOutstandingBalanceEnquiry()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $balances = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)
            ->where('b.status', '!=', 'CANCELLED')
            ->groupBy('s.supplier_id', 's.supplier_name')
            ->select('s.supplier_id', 's.supplier_name', DB::raw('SUM(b.amount - b.paid_amount) as outstanding'))
            ->havingRaw('SUM(b.amount - b.paid_amount) > 0.004')
            ->orderByDesc('outstanding')
            ->paginate(10, ['*'], 'apObEnqPage');

        return view('cbe.accounting.ap-outstanding-balance-enquiry', compact('balances'));
    }

    public function apAging(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bills = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)
            ->whereIn('b.status', ['UNPAID', 'PARTIALLY_PAID'])
            ->select('b.*', 's.supplier_name')
            ->get();

        $today = now();
        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Accounts Payable Aging Report'];
        $sheet[] = ['As of ' . $today->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Supplier', 'Bill No', 'Bill Date', 'Due Date', 'Outstanding (RM)', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', '90+ Days'];

        $buckets = ['current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90plus' => 0];
        foreach ($bills as $b) {
            $outstanding = (float) $b->amount - (float) $b->paid_amount;
            $dueDate = $b->due_date ? \Carbon\Carbon::parse($b->due_date) : \Carbon\Carbon::parse($b->bill_date);
            $daysOverdue = $today->diffInDays($dueDate, false) * -1;

            $row = [$b->supplier_name, $b->bill_no, \Carbon\Carbon::parse($b->bill_date)->format('d M Y'), $dueDate->format('d M Y'), round($outstanding, 2), 0, 0, 0, 0, 0];
            if ($daysOverdue <= 0) { $row[5] = round($outstanding, 2); $buckets['current'] += $outstanding; }
            elseif ($daysOverdue <= 30) { $row[6] = round($outstanding, 2); $buckets['d30'] += $outstanding; }
            elseif ($daysOverdue <= 60) { $row[7] = round($outstanding, 2); $buckets['d60'] += $outstanding; }
            elseif ($daysOverdue <= 90) { $row[8] = round($outstanding, 2); $buckets['d90'] += $outstanding; }
            else { $row[9] = round($outstanding, 2); $buckets['d90plus'] += $outstanding; }

            $sheet[] = $row;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', '', '', round(array_sum($buckets), 2), round($buckets['current'], 2), round($buckets['d30'], 2), round($buckets['d60'], 2), round($buckets['d90'], 2), round($buckets['d90plus'], 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'AP Aging', $sheet);
        return $this->download($spreadsheet, 'AP_Aging_Report');
    }

    // ---------- AP Reports hub + Supplier Statement (NEW 3 Sep 2026,
    // Task #375) — mirrors the AR Reports hub (Task #360) and Debtor
    // Statement (Task #355) exactly, substituting suppliers/bills for
    // customers/invoices throughout.

    public function apReportsHub()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        return view('cbe.accounting.ap-reports-hub');
    }

    public function supplierStatementPicker()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $suppliers = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->orderBy('supplier_name')->get();

        return view('cbe.accounting.supplier-statement-picker', compact('suppliers'));
    }

    public function supplierStatement(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:cbe_suppliers,supplier_id'],
        ]);
        $supplierId = $request->input('supplier_id');
        $supplier = DB::table('cbe_suppliers')->where('supplier_id', $supplierId)->first();
        if (! $supplier) {
            return redirect()->route('cbe.accounting.reports.supplier-statement-picker');
        }

        $bills = DB::table('cbe_purchase_bills')->where('supplier_id', $supplierId)
            ->select('bill_no', 'bill_date as txn_date', DB::raw("'Bill' as txn_type"), DB::raw('0 as debit'), 'amount as credit');
        $payments = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->where('b.supplier_id', $supplierId)
            ->select('b.bill_no', 'p.payment_date as txn_date', DB::raw("'Payment' as txn_type"), 'p.amount as debit', DB::raw('0 as credit'));
        $creditNotes = DB::table('cbe_debit_notes as dn')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.supplier_id', $supplierId)
            ->select('b.bill_no', 'dn.note_date as txn_date', DB::raw("'Credit Note' as txn_type"), 'dn.amount as debit', DB::raw('0 as credit'));
        $debitNotes = DB::table('cbe_ap_debit_notes as dn')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.supplier_id', $supplierId)
            ->select('b.bill_no', 'dn.note_date as txn_date', DB::raw("'Debit Note' as txn_type"), DB::raw('0 as debit'), 'dn.amount as credit');

        $lines = $bills->unionAll($payments)->unionAll($creditNotes)->unionAll($debitNotes)
            ->orderBy('txn_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Supplier Statement'];
        $sheet[] = [$supplier->supplier_name];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Bill No', 'Date', 'Type', 'Debit (RM)', 'Credit (RM)', 'Balance (RM)'];

        $balance = 0;
        foreach ($lines as $line) {
            $balance += (float) $line->credit - (float) $line->debit;
            $sheet[] = [
                $line->bill_no ?: '—', \Carbon\Carbon::parse($line->txn_date)->format('d M Y'), $line->txn_type,
                round((float) $line->debit, 2), round((float) $line->credit, 2), round($balance, 2),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Supplier Statement', $sheet);
        return $this->download($spreadsheet, 'Supplier_Statement_' . preg_replace('/[^A-Za-z0-9]+/', '_', $supplier->supplier_name));
    }

    public function billListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $bills = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)
            ->select('b.*', 's.supplier_name')
            ->orderBy('b.bill_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Supplier Invoice Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Bill No', 'Supplier', 'Bill Date', 'Due Date', 'Amount (RM)', 'Paid (RM)', 'Outstanding (RM)', 'Status'];
        foreach ($bills as $b) {
            $sheet[] = [
                $b->bill_no ?: $b->doc_ref_no, $b->supplier_name, \Carbon\Carbon::parse($b->bill_date)->format('d M Y'),
                $b->due_date ? \Carbon\Carbon::parse($b->due_date)->format('d M Y') : '—',
                round((float) $b->amount, 2), round((float) $b->paid_amount, 2),
                round((float) $b->amount - (float) $b->paid_amount, 2), $b->status,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Supplier Invoice Listing', $sheet);
        return $this->download($spreadsheet, 'Supplier_Invoice_Listing');
    }

    public function apOutstandingPayablesReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $balances = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)
            ->where('b.status', '!=', 'CANCELLED')
            ->groupBy('s.supplier_id', 's.supplier_name')
            ->select('s.supplier_name', DB::raw('SUM(b.amount - b.paid_amount) as outstanding'))
            ->havingRaw('SUM(b.amount - b.paid_amount) > 0.004')
            ->orderByDesc('outstanding')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Outstanding Payables'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Supplier', 'Outstanding (RM)'];
        $total = 0;
        foreach ($balances as $b) {
            $sheet[] = [$b->supplier_name, round((float) $b->outstanding, 2)];
            $total += (float) $b->outstanding;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', round($total, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Outstanding Payables', $sheet);
        return $this->download($spreadsheet, 'Outstanding_Payables');
    }

    // Covers Payment Listing, Payment Voucher Listing and Payment Report
    // from Chris's spec in one export — every AP payment already carries
    // its Payment Voucher No (pv_no) as its own column.
    public function apPaymentListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $payments = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->leftJoin('cbe_bank_accounts as bk', 'bk.bank_account_id', '=', 'p.bank_account_id')
            ->where('b.cbe_node_id', $nodeId)
            ->select('p.*', 'b.bill_no', 'b.doc_ref_no', 's.supplier_name', 'bk.bank_name')
            ->orderBy('p.payment_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Payment Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['PV No', 'Date', 'Supplier', 'Bill No', 'Amount (RM)', 'Method', 'Bank Account', 'Reference No'];
        foreach ($payments as $p) {
            $sheet[] = [
                $p->pv_no ?: '—', \Carbon\Carbon::parse($p->payment_date)->format('d M Y'), $p->supplier_name, $p->bill_no ?: $p->doc_ref_no,
                round((float) $p->amount, 2), $p->payment_method ?: '—', $p->bank_name ?: '—', $p->reference_no ?: '—',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Payment Listing', $sheet);
        return $this->download($spreadsheet, 'AP_Payment_Listing');
    }

    public function apCreditNoteListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_debit_notes as dn')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'dn.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.cbe_node_id', $nodeId)
            ->select('dn.*', 's.supplier_name', 'b.bill_no')
            ->orderBy('dn.note_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Credit Note Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Supplier', 'Bill No', 'Reason', 'Amount (RM)'];
        foreach ($notes as $n) {
            $sheet[] = [\Carbon\Carbon::parse($n->note_date)->format('d M Y'), $n->supplier_name, $n->bill_no ?: '—', $n->reason ?: '—', round((float) $n->amount, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Credit Note Listing', $sheet);
        return $this->download($spreadsheet, 'AP_Credit_Note_Listing');
    }

    public function apDebitNoteListingReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $notes = DB::table('cbe_ap_debit_notes as dn')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'dn.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.cbe_node_id', $nodeId)
            ->select('dn.*', 's.supplier_name', 'b.bill_no')
            ->orderBy('dn.note_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Debit Note Listing'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Supplier', 'Bill No', 'Reason', 'Amount (RM)'];
        foreach ($notes as $n) {
            $sheet[] = [\Carbon\Carbon::parse($n->note_date)->format('d M Y'), $n->supplier_name, $n->bill_no ?: '—', $n->reason ?: '—', round((float) $n->amount, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Debit Note Listing', $sheet);
        return $this->download($spreadsheet, 'AP_Debit_Note_Listing');
    }

    public function supplierBalanceReport()
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $suppliers = DB::table('cbe_suppliers as s')
            ->leftJoin('cbe_supplier_categories as sc', 'sc.category_id', '=', 's.category_id')
            ->where('s.cbe_node_id', $nodeId)
            ->select('s.supplier_id', 's.supplier_name', 'sc.category_name')
            ->orderBy('s.supplier_name')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Supplier Balance Report'];
        $sheet[] = ['As of ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Supplier', 'Category', 'Total Billed (RM)', 'Total Paid (RM)', 'Total Debit Notes (RM)', 'Total Credit Notes (RM)', 'Outstanding (RM)'];

        foreach ($suppliers as $s) {
            $billed = (float) DB::table('cbe_purchase_bills')->where('supplier_id', $s->supplier_id)->where('status', '!=', 'CANCELLED')->sum('amount');
            $paid = (float) DB::table('cbe_bill_payments as p')->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')->where('b.supplier_id', $s->supplier_id)->sum('p.amount');
            $debitNotes = (float) DB::table('cbe_ap_debit_notes')->where('supplier_id', $s->supplier_id)->sum('amount');
            $creditNotes = (float) DB::table('cbe_debit_notes')->where('supplier_id', $s->supplier_id)->sum('amount');
            $outstanding = $billed + $debitNotes - $creditNotes - $paid;
            if (abs($outstanding) < 0.004 && $billed < 0.004) {
                continue;
            }
            $sheet[] = [$s->supplier_name, $s->category_name ?: '—', round($billed, 2), round($paid, 2), round($debitNotes, 2), round($creditNotes, 2), round($outstanding, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Supplier Balance', $sheet);
        return $this->download($spreadsheet, 'Supplier_Balance_Report');
    }

    // NEW 3 Sep 2026 (Task #376) — AP/GL Reconciliation report. Confirms
    // the AP sub-ledger (sum of outstanding bill balances) agrees with
    // the AP Control Account balance in the General Ledger (account code
    // CbeAccountingService::AP_CODE). Any non-zero Difference means a
    // posting gap between the two sides. Mirrors arGlReconciliationReport().
    public function apGlReconciliationReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $asOf = $request->get('as_of') ?: now()->toDateString();

        $subLedgerTotal = (float) DB::table('cbe_purchase_bills')
            ->where('cbe_node_id', $nodeId)
            ->where('status', '!=', 'CANCELLED')
            ->where('bill_date', '<=', $asOf)
            ->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as total')
            ->value('total');

        $balances = $this->accountBalances($nodeId, $asOf);
        $apAccount = $balances->firstWhere('account_code', \App\Services\CbeAccountingService::AP_CODE);
        $controlBalance = $apAccount
            ? self::normalBalance('LIABILITY', (float) $apAccount->total_debit, (float) $apAccount->total_credit)
            : 0.0;

        $difference = round($subLedgerTotal - $controlBalance, 2);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — AP / GL Reconciliation'];
        $sheet[] = ['As of ' . \Carbon\Carbon::parse($asOf)->format('d M Y')];
        $sheet[] = ['Downloaded: ' . now()->format('d M Y H:i')];
        $sheet[] = [];
        $sheet[] = ['Description', 'Amount (RM)'];
        $sheet[] = ['AP Sub-Ledger Total (sum of outstanding bills)', round($subLedgerTotal, 2)];
        $sheet[] = ['AP Control Account Balance (GL Account ' . \App\Services\CbeAccountingService::AP_CODE . ')', round($controlBalance, 2)];
        $sheet[] = ['Difference (expected RM0.00)', $difference];
        $sheet[] = [];
        $sheet[] = [$difference == 0.0 ? 'RECONCILED — no difference found.' : 'NOT RECONCILED — investigate unposted or misposted AP entries.'];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'AP-GL Reconciliation', $sheet);
        return $this->download($spreadsheet, 'AP_GL_Reconciliation');
    }

    public function monthlyApSummaryReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) $request->input('year', now()->year);

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Monthly AP Summary ' . $year];
        $sheet[] = ['Generated ' . now()->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Month', 'Billed (RM)', 'Paid (RM)', 'Debit Notes (RM)', 'Credit Notes (RM)', 'Net Movement (RM)'];

        $totals = ['bill' => 0, 'paid' => 0, 'dn' => 0, 'cn' => 0];
        for ($m = 1; $m <= 12; $m++) {
            $billed = (float) DB::table('cbe_purchase_bills')->where('cbe_node_id', $nodeId)
                ->whereYear('bill_date', $year)->whereMonth('bill_date', $m)->where('status', '!=', 'CANCELLED')->sum('amount');
            $paid = (float) DB::table('cbe_bill_payments as p')->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
                ->where('b.cbe_node_id', $nodeId)->whereYear('p.payment_date', $year)->whereMonth('p.payment_date', $m)->sum('p.amount');
            $debitNotes = (float) DB::table('cbe_ap_debit_notes')->where('cbe_node_id', $nodeId)
                ->whereYear('note_date', $year)->whereMonth('note_date', $m)->sum('amount');
            $creditNotes = (float) DB::table('cbe_debit_notes')->where('cbe_node_id', $nodeId)
                ->whereYear('note_date', $year)->whereMonth('note_date', $m)->sum('amount');

            $sheet[] = [
                \Carbon\Carbon::create($year, $m, 1)->format('F'),
                round($billed, 2), round($paid, 2), round($debitNotes, 2), round($creditNotes, 2),
                round($billed + $debitNotes - $creditNotes - $paid, 2),
            ];
            $totals['bill'] += $billed; $totals['paid'] += $paid; $totals['dn'] += $debitNotes; $totals['cn'] += $creditNotes;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', round($totals['bill'], 2), round($totals['paid'], 2), round($totals['dn'], 2), round($totals['cn'], 2), round($totals['bill'] + $totals['dn'] - $totals['cn'] - $totals['paid'], 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Monthly AP Summary', $sheet);
        return $this->download($spreadsheet, 'Monthly_AP_Summary_' . $year);
    }

    public function apTransactionReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfMonth()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $bills = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)->whereBetween('b.bill_date', [$from, $to])
            ->select('s.supplier_name', 'b.bill_no', 'b.bill_date as txn_date', DB::raw("'Bill' as txn_type"), DB::raw('0 as debit'), 'b.amount as credit');
        $payments = DB::table('cbe_bill_payments as p')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'p.bill_id')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)->whereBetween('p.payment_date', [$from, $to])
            ->select('s.supplier_name', 'b.bill_no', 'p.payment_date as txn_date', DB::raw("'Payment' as txn_type"), 'p.amount as debit', DB::raw('0 as credit'));
        $creditNotes = DB::table('cbe_debit_notes as dn')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'dn.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.cbe_node_id', $nodeId)->whereBetween('dn.note_date', [$from, $to])
            ->select('s.supplier_name', 'b.bill_no', 'dn.note_date as txn_date', DB::raw("'Credit Note' as txn_type"), 'dn.amount as debit', DB::raw('0 as credit'));
        $debitNotes = DB::table('cbe_ap_debit_notes as dn')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'dn.supplier_id')
            ->leftJoin('cbe_purchase_bills as b', 'b.bill_id', '=', 'dn.bill_id')
            ->where('dn.cbe_node_id', $nodeId)->whereBetween('dn.note_date', [$from, $to])
            ->select('s.supplier_name', 'b.bill_no', 'dn.note_date as txn_date', DB::raw("'Debit Note' as txn_type"), DB::raw('0 as debit'), 'dn.amount as credit');

        $lines = $bills->unionAll($payments)->unionAll($creditNotes)->unionAll($debitNotes)
            ->orderBy('txn_date')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — AP Transaction Report'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Date', 'Supplier', 'Bill No', 'Type', 'Debit (RM)', 'Credit (RM)'];
        foreach ($lines as $l) {
            $sheet[] = [\Carbon\Carbon::parse($l->txn_date)->format('d M Y'), $l->supplier_name, $l->bill_no ?: '—', $l->txn_type, round((float) $l->debit, 2), round((float) $l->credit, 2)];
        }

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'AP Transactions', $sheet);
        return $this->download($spreadsheet, 'AP_Transaction_Report');
    }

    public function expenseSummaryBySupplierReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $rows = DB::table('cbe_purchase_bills as b')
            ->join('cbe_suppliers as s', 's.supplier_id', '=', 'b.supplier_id')
            ->where('b.cbe_node_id', $nodeId)
            ->where('b.status', '!=', 'CANCELLED')
            ->whereBetween('b.bill_date', [$from, $to])
            ->groupBy('s.supplier_id', 's.supplier_name')
            ->select('s.supplier_name', DB::raw('SUM(b.amount) as total'), DB::raw('COUNT(*) as bill_count'))
            ->orderByDesc('total')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Expense Summary by Supplier'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Supplier', 'No. of Bills', 'Total Expense (RM)'];
        $total = 0;
        foreach ($rows as $r) {
            $sheet[] = [$r->supplier_name, (int) $r->bill_count, round((float) $r->total, 2)];
            $total += (float) $r->total;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', '', round($total, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Expense by Supplier', $sheet);
        return $this->download($spreadsheet, 'Expense_Summary_By_Supplier');
    }

    public function expenseSummaryByCategoryReport(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->input('from_date') ?: now()->startOfYear()->toDateString();
        $to = $request->input('to_date') ?: now()->toDateString();

        $rows = DB::table('cbe_bill_lines as l')
            ->join('cbe_purchase_bills as b', 'b.bill_id', '=', 'l.bill_id')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'l.category_id')
            ->where('b.cbe_node_id', $nodeId)
            ->where('b.status', '!=', 'CANCELLED')
            ->whereBetween('b.bill_date', [$from, $to])
            ->groupBy('c.category_id', 'c.category_name')
            ->select(DB::raw("COALESCE(c.category_name, 'Uncategorised') as category_name"), DB::raw('SUM(l.line_total) as total'))
            ->orderByDesc('total')->get();

        $sheet = [];
        $sheet[] = ['GeneralLink Digital Ecosystem — Expense Summary by Category'];
        $sheet[] = [\Carbon\Carbon::parse($from)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($to)->format('d M Y')];
        $sheet[] = [];
        $sheet[] = ['Expense Category', 'Total Expense (RM)'];
        $total = 0;
        foreach ($rows as $r) {
            $sheet[] = [$r->category_name, round((float) $r->total, 2)];
            $total += (float) $r->total;
        }
        $sheet[] = [];
        $sheet[] = ['TOTAL', round($total, 2)];

        $spreadsheet = new Spreadsheet();
        $this->writeSheet($spreadsheet, 'Expense by Category', $sheet);
        return $this->download($spreadsheet, 'Expense_Summary_By_Category');
    }

    // NEW 28 Aug 2026 — per the accounting-integration evaluation report
    // (see GeneralLink_CBE_Accounting_Integration_Evaluation.docx): none of
    // AutoCount / SQL Account / Million Accounting expose a live API we
    // could sync to, and QuickBooks now meters API reads. So rather than
    // building a real-time connector to any single system, this gives every
    // CBE community a flat, one-row-per-journal-line CSV — the same shape
    // every general ledger / journal import screen expects, whichever
    // accounting software their external accountant actually uses. This is
    // a generic bridge, not a certified per-vendor template; a treasurer
    // may still need to re-map a column or two to their own software's
    // exact import wizard.
    public function journalExportCsv(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $from = $request->get('from') ?: now()->startOfYear()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        $lines = DB::table('cbe_journal_lines as l')
            ->join('cbe_journal_entries as j', 'j.journal_id', '=', 'l.journal_id')
            ->join('cbe_chart_of_accounts as a', 'a.account_id', '=', 'l.account_id')
            ->where('j.cbe_node_id', $nodeId)
            ->whereBetween('j.entry_date', [$from, $to])
            ->select('j.entry_date', 'j.reference_no', 'j.description as journal_description',
                'a.account_code', 'a.account_name', 'l.memo as line_memo',
                'l.debit', 'l.credit')
            ->orderBy('j.entry_date')->orderBy('j.reference_no')
            ->get();

        $filename = 'Journal_Export_' . now()->format('dMY') . '.csv';

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel/AutoCount/SQL Account importers on Windows
            // read the Chinese/Malay account or description text correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Reference No', 'Account Code', 'Account Name', 'Description', 'Debit', 'Credit']);
            foreach ($lines as $ln) {
                fputcsv($out, [
                    \Carbon\Carbon::parse($ln->entry_date)->format('Y-m-d'),
                    $ln->reference_no,
                    $ln->account_code,
                    $ln->account_name,
                    $ln->line_memo ?: $ln->journal_description,
                    $ln->debit > 0 ? number_format((float) $ln->debit, 2, '.', '') : '',
                    $ln->credit > 0 ? number_format((float) $ln->credit, 2, '.', '') : '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ---------- Fiscal Period Lock (NEW 1 Sep 2026, Task #328) ----------

    public function periods(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.periods', __('cbe_accounting.periods_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $year = (int) $request->input('year', now()->year);

        $rows = DB::table('cbe_fiscal_periods')
            ->where('cbe_node_id', $nodeId)
            ->where('period_year', $year)
            ->get()->keyBy('period_month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $rows->get($m);
            $months[] = [
                'month'     => $m,
                'label'     => \Carbon\Carbon::create($year, $m, 1)->translatedFormat('M'),
                'status'    => $row->status ?? 'OPEN',
                'closed_at' => $row->closed_at ?? null,
            ];
        }

        return view('cbe.accounting.periods', [
            'year'     => $year,
            'months'   => $months,
            'isAdmin'  => $agent->role === 'ADMIN',
        ]);
    }

    public function closePeriod(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $request->validate(['year' => ['required', 'integer'], 'month' => ['required', 'integer', 'min:1', 'max:12']]);

        CbeAccountingService::closePeriod($nodeId, (int) $request->input('year'), (int) $request->input('month'), $agent->agent_id);

        return redirect()->route('cbe.accounting.periods', ['year' => $request->input('year')])
            ->with('success', __('cbe_accounting.period_closed_success'));
    }

    public function reopenPeriod(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if ($agent->role !== 'ADMIN') {
            abort(403);
        }
        $request->validate(['year' => ['required', 'integer'], 'month' => ['required', 'integer', 'min:1', 'max:12']]);

        CbeAccountingService::reopenPeriod($nodeId, (int) $request->input('year'), (int) $request->input('month'));

        return redirect()->route('cbe.accounting.periods', ['year' => $request->input('year')])
            ->with('success', __('cbe_accounting.period_reopened_success'));
    }

    // ---------- Document Number Control (NEW 4 Sep 2026, Task #391) ----------
    // Master file showing, per document type, the current monthly number
    // band, how many have been issued, and the next number due — plus an
    // Admin-only Reset action per Chris's request to be able to set the
    // starting number for a period. New document types (Debit/Credit
    // Note, Purchase Order) just need adding to this one catalog.
    private function documentTypeCatalog(): array
    {
        return [
            ['doc_type' => 'JV', 'prefix' => 'JV', 'label_key' => 'doctype_jv'],
            ['doc_type' => 'JE', 'prefix' => 'JE', 'label_key' => 'doctype_je'],
            ['doc_type' => 'BILL', 'prefix' => 'BILL', 'label_key' => 'doctype_bill'],
            ['doc_type' => 'PV', 'prefix' => 'PV', 'label_key' => 'doctype_pv'],
            ['doc_type' => 'PR', 'prefix' => 'PR', 'label_key' => 'doctype_pr'],
            ['doc_type' => 'PLEDGE', 'prefix' => 'PLG', 'label_key' => 'doctype_pledge'],
            ['doc_type' => 'RCPT', 'prefix' => 'OR', 'label_key' => 'doctype_rcpt'],
            ['doc_type' => 'INVOICE', 'prefix' => 'INV', 'label_key' => 'doctype_invoice'],
            ['doc_type' => 'PCV', 'prefix' => 'PCV', 'label_key' => 'doctype_pcv'],
            ['doc_type' => 'PCT', 'prefix' => 'PCT', 'label_key' => 'doctype_pct'],
            ['doc_type' => 'RJ', 'prefix' => 'RJ', 'label_key' => 'doctype_rj'],
            ['doc_type' => 'DN', 'prefix' => 'DN', 'label_key' => 'doctype_dn'],
            ['doc_type' => 'APDN', 'prefix' => 'APDN', 'label_key' => 'doctype_apdn'],
            ['doc_type' => 'ARDN', 'prefix' => 'ARDN', 'label_key' => 'doctype_ardn'],
            ['doc_type' => 'ARCN', 'prefix' => 'ARCN', 'label_key' => 'doctype_arcn'],
            ['doc_type' => 'PO', 'prefix' => 'PO', 'label_key' => 'doctype_po'],
            // NEW 4 Sep 2026 (Task #394) — Purchasing Management module.
            ['doc_type' => 'RFQ', 'prefix' => 'RFQ', 'label_key' => 'doctype_rfq'],
            ['doc_type' => 'GRN', 'prefix' => 'GRN', 'label_key' => 'doctype_grn'],
            ['doc_type' => 'PRN', 'prefix' => 'PRN', 'label_key' => 'doctype_prn'],
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
            // upgrade, Phase 3.
            ['doc_type' => 'BANK_RECON', 'prefix' => 'BR', 'label_key' => 'doctype_bank_recon'],
        ];
    }

    // REBUILT 22 Sep 2026 -- per Chris: no screen may display its
    // records before a selection is made, and Prev/Next must sit at the
    // bottom, left and right -- never at the top. This used to jump
    // straight to the current month's table on load with a Prev/Next
    // month-nav bar above it. Now it shows a Year/Month picker first;
    // the table (with Prev/Next at the bottom) only appears once a
    // month has actually been chosen and submitted.
    public function documentNumberControl(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.document-number-control', __('cbe_accounting.doc_number_control_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        if (! $request->filled('year') || ! $request->filled('month')) {
            return view('cbe.accounting.document-number-control', [
                'hasSelection' => false,
                'defaultYear' => now()->year, 'defaultMonth' => now()->month,
            ]);
        }

        $year = (int) $request->input('year');
        $month = (int) $request->input('month');

        $rows = collect($this->documentTypeCatalog())->map(function ($t) use ($nodeId, $year, $month) {
            $status = CbeAccountingService::documentSequenceStatus($nodeId, $t['doc_type'], $year, $month);
            return array_merge($t, $status, [
                'sample_next' => sprintf('%s-%d-%s', $t['prefix'], $year, $status['next_number'] - 1 <= 999
                    ? sprintf('%02d%03d', $month, $status['next_number'] - 1)
                    : sprintf('%02d%d', $month, $status['next_number'] - 1)),
            ]);
        });

        $prev = \Carbon\Carbon::create($year, $month, 1)->subMonthNoOverflow();
        $next = \Carbon\Carbon::create($year, $month, 1)->addMonthNoOverflow();

        return view('cbe.accounting.document-number-control', [
            'hasSelection' => true,
            'rows' => $rows, 'year' => $year, 'month' => $month,
            'monthLabel' => \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'prevYear' => $prev->year, 'prevMonth' => $prev->month,
            'nextYear' => $next->year, 'nextMonth' => $next->month,
            'isAdmin' => $agent->role === 'ADMIN',
        ]);
    }

    public function documentNumberControlReset(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if ($agent->role !== 'ADMIN') {
            abort(403);
        }
        $request->validate([
            'doc_type' => ['required', 'string', 'max:20'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $docType = $request->input('doc_type');
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        $catalog = collect($this->documentTypeCatalog())->firstWhere('doc_type', $docType);
        if (! $catalog) {
            abort(404);
        }
        $status = CbeAccountingService::documentSequenceStatus($nodeId, $docType, $year, $month);

        return view('cbe.accounting.document-number-control-reset', [
            'docType' => $docType, 'label' => __('cbe_accounting.'.$catalog['label_key']),
            'year' => $year, 'month' => $month, 'status' => $status,
        ]);
    }

    public function storeDocumentNumberControlReset(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if ($agent->role !== 'ADMIN') {
            abort(403);
        }
        $request->validate([
            'doc_type' => ['required', 'string', 'max:20'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'new_next_number' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'confirm_lower' => ['nullable'],
        ]);
        $docType = $request->input('doc_type');
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        $newNextNumber = (int) $request->input('new_next_number');

        // Moving the counter BACKWARD into already-issued territory risks
        // handing out a number that's already on a real document — allow
        // it (Chris may need to correct a mistake), but only with the
        // explicit checkbox acknowledged, never silently.
        $status = CbeAccountingService::documentSequenceStatus($nodeId, $docType, $year, $month);
        if ($newNextNumber <= $status['last_number'] && ! $request->boolean('confirm_lower')) {
            return back()->withInput()->withErrors([__('cbe_accounting.doc_seq_reset_lower_confirm_required')]);
        }

        CbeAccountingService::resetDocumentSequence($nodeId, $docType, $year, $month, $newNextNumber, $agent->agent_id, $request->input('reason'));

        return redirect()->route('cbe.accounting.document-number-control', ['year' => $year, 'month' => $month])
            ->with('success', __('cbe_accounting.doc_seq_reset_success'));
    }

    // ---------- Year-End Closing (NEW 2 Sep 2026, Task #332) ----------
    // See migration comment on cbe_year_end_closings for why this is
    // tracking-only (checklist + a "closed" marker), not a physical
    // closing journal entry.

    public function yearEndClosing(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.year-end-closing', __('cbe_accounting.year_end_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $year = (int) $request->input('year', now()->year);

        $closedMonths = DB::table('cbe_fiscal_periods')
            ->where('cbe_node_id', $nodeId)->where('period_year', $year)->where('status', 'CLOSED')->count();

        $checklist = DB::table('cbe_year_end_closings')->where('cbe_node_id', $nodeId)->where('fiscal_year', $year)->first();

        return view('cbe.accounting.year-end-closing', [
            'year' => $year,
            'closedMonths' => $closedMonths,
            'allMonthsClosed' => $closedMonths >= 12,
            'checklist' => $checklist,
            'isAdmin' => $agent->role === 'ADMIN',
        ]);
    }

    public function saveYearEndChecklist(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'year' => ['required', 'integer'],
            'agm_held' => ['nullable', 'boolean'],
            'agm_date' => ['nullable', 'date'],
            'ros_submitted' => ['nullable', 'boolean'],
            'ros_submission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $year = (int) $request->input('year');
        $existing = DB::table('cbe_year_end_closings')->where('cbe_node_id', $nodeId)->where('fiscal_year', $year)->first();

        $data = [
            'agm_held' => (bool) $request->boolean('agm_held'),
            'agm_date' => $request->input('agm_date') ?: null,
            'ros_submitted' => (bool) $request->boolean('ros_submitted'),
            'ros_submission_date' => $request->input('ros_submission_date') ?: null,
            'notes' => $request->input('notes'),
            'updated_by' => $agent->agent_id,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('cbe_year_end_closings')->where('closing_id', $existing->closing_id)->update($data);
        } else {
            DB::table('cbe_year_end_closings')->insert(array_merge($data, [
                'closing_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'fiscal_year' => $year,
                'created_at' => now(),
            ]));
        }

        return redirect()->route('cbe.accounting.year-end-closing', ['year' => $year])->with('success', __('cbe_accounting.year_end_checklist_saved'));
    }

    // Marks the year formally CLOSED — requires all 12 months locked
    // first. Tracking only (see migration comment); does not touch the
    // ledger.
    public function closeYear(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $request->validate(['year' => ['required', 'integer']]);
        $year = (int) $request->input('year');

        $closedMonths = DB::table('cbe_fiscal_periods')
            ->where('cbe_node_id', $nodeId)->where('period_year', $year)->where('status', 'CLOSED')->count();
        if ($closedMonths < 12) {
            return back()->with('error', __('cbe_accounting.error_year_not_all_months_closed'));
        }

        $existing = DB::table('cbe_year_end_closings')->where('cbe_node_id', $nodeId)->where('fiscal_year', $year)->first();
        $data = ['year_closed' => true, 'closed_by' => $agent->agent_id, 'closed_at' => now(), 'updated_by' => $agent->agent_id, 'updated_at' => now()];

        if ($existing) {
            DB::table('cbe_year_end_closings')->where('closing_id', $existing->closing_id)->update($data);
        } else {
            DB::table('cbe_year_end_closings')->insert(array_merge($data, [
                'closing_id' => (string) Str::uuid(), 'cbe_node_id' => $nodeId, 'fiscal_year' => $year, 'created_at' => now(),
            ]));
        }

        return redirect()->route('cbe.accounting.year-end-closing', ['year' => $year])->with('success', __('cbe_accounting.year_closed_success'));
    }

    // AGM/ROS Reporting Pack — one workbook, 4 tabs, everything a
    // temple/branch typically needs for its AGM and ROS annual return:
    // Income & Expenditure, Secretary Activity Report, Balance Sheet,
    // Trial Balance — all for the chosen year / as-of 31 Dec that year.
    public function yearEndPack(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        $year = (int) $request->input('year', now()->year);
        $yearEnd = $year.'-12-31';
        $nodeName = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('node_name') ?? '';

        $spreadsheet = new Spreadsheet();

        // Tab 1 — Income & Expenditure. NEW 8 Sep 2026 (Task #397 Phase
        // 10): this used to calculate straight from the legacy
        // cbe_transactions table, which meant AI-posted entries — which
        // post to cbe_journal_entries/cbe_journal_lines, never to
        // cbe_transactions — were silently missing from this one tab
        // even though they already appeared correctly everywhere else
        // (Trial Balance, Balance Sheet, the standalone Profit & Loss
        // report). Switched to plRows(), the exact same GL-based helper
        // profitLoss() itself uses, so there is one shared reporting
        // engine and this tab reflects manual and AI-sourced postings
        // identically, with no separate calculation to fall out of sync.
        $plRows = $this->plRows($nodeId, $year.'-01-01', $yearEnd);
        $income = $plRows->where('account_type', 'INCOME');
        $expense = $plRows->where('account_type', 'EXPENSE');
        $incomeTotal = $income->sum(fn ($r) => self::normalBalance('INCOME', (float) $r->total_debit, (float) $r->total_credit));
        $expenseTotal = $expense->sum(fn ($r) => self::normalBalance('EXPENSE', (float) $r->total_debit, (float) $r->total_credit));

        $ie = [];
        $ie[] = ['GeneralLink Digital Ecosystem — Income & Expenditure Report'];
        $ie[] = [$nodeName.' — Year '.$year];
        $ie[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $ie[] = [];
        $ie[] = ['INCOME'];
        foreach ($income as $r) { $ie[] = [$r->account_name, round(self::normalBalance('INCOME', (float) $r->total_debit, (float) $r->total_credit), 2)]; }
        $ie[] = ['Total Income', round($incomeTotal, 2)];
        $ie[] = [];
        $ie[] = ['EXPENDITURE'];
        foreach ($expense as $r) { $ie[] = [$r->account_name, round(self::normalBalance('EXPENSE', (float) $r->total_debit, (float) $r->total_credit), 2)]; }
        $ie[] = ['Total Expenditure', round($expenseTotal, 2)];
        $ie[] = [];
        $ie[] = ['NET SURPLUS / (DEFICIT)', round($incomeTotal - $expenseTotal, 2)];
        $this->writeSheet($spreadsheet, 'Income & Expenditure', $ie);

        // Tab 2 — Secretary Activity Report
        $minutes = DB::table('cbe_meeting_minutes')->where('cbe_node_id', $nodeId)->whereYear('meeting_date', $year)->get()
            ->map(fn ($m) => (object) ['date' => $m->meeting_date, 'type' => 'Meeting Minutes', 'title' => $m->title, 'notes' => $m->summary]);
        $activities = DB::table('cbe_activities')->where('cbe_node_id', $nodeId)->whereYear('activity_date', $year)->get()
            ->map(fn ($a) => (object) ['date' => $a->activity_date, 'type' => 'Activity', 'title' => $a->title, 'notes' => $a->description]);
        $secRows = $minutes->concat($activities)->sortBy('date')->values();

        $sec = [];
        $sec[] = ['GeneralLink Digital Ecosystem — Secretary Activity Report'];
        $sec[] = [$nodeName.' — Year '.$year];
        $sec[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $sec[] = [];
        $sec[] = ['Date', 'Type', 'Title', 'Notes / Summary'];
        foreach ($secRows as $r) { $sec[] = [\Carbon\Carbon::parse($r->date)->format('d M Y'), $r->type, $r->title, $r->notes ?? '']; }
        if ($secRows->isEmpty()) { $sec[] = ['No meeting minutes or activities recorded for '.$year.'.']; }
        $spreadsheet->createSheet(); $spreadsheet->setActiveSheetIndex(1);
        $this->writeSheet($spreadsheet, 'Secretary Report', $sec);

        // Tab 3 — Balance Sheet (as of 31 Dec)
        $balances = $this->accountBalances($nodeId, $yearEnd);
        $assets = $balances->where('account_type', 'ASSET');
        $liabilities = $balances->where('account_type', 'LIABILITY');
        $equity = $balances->where('account_type', 'EQUITY');
        $incomeBal = $balances->where('account_type', 'INCOME');
        $expenseBal = $balances->where('account_type', 'EXPENSE');
        $sumBal = fn ($coll) => $coll->sum(fn ($b) => self::normalBalance($b->account_type, (float) $b->total_debit, (float) $b->total_credit));
        $netSurplus = $sumBal($incomeBal) - $sumBal($expenseBal);

        $bs = [];
        $bs[] = ['GeneralLink Digital Ecosystem — Balance Sheet'];
        $bs[] = [$nodeName.' — As of 31 Dec '.$year];
        $bs[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $bs[] = [];
        $bs[] = ['ASSETS'];
        foreach ($assets as $a) { $bs[] = [$a->account_code, $a->account_name, round(self::normalBalance('ASSET', (float) $a->total_debit, (float) $a->total_credit), 2)]; }
        $bs[] = ['', 'Total Assets', round($sumBal($assets), 2)];
        $bs[] = [];
        $bs[] = ['LIABILITIES'];
        foreach ($liabilities as $l) { $bs[] = [$l->account_code, $l->account_name, round(self::normalBalance('LIABILITY', (float) $l->total_debit, (float) $l->total_credit), 2)]; }
        $bs[] = ['', 'Total Liabilities', round($sumBal($liabilities), 2)];
        $bs[] = [];
        $bs[] = ['EQUITY'];
        foreach ($equity as $e) { $bs[] = [$e->account_code, $e->account_name, round(self::normalBalance('EQUITY', (float) $e->total_debit, (float) $e->total_credit), 2)]; }
        $bs[] = ['', 'Net Surplus (accumulated Income − Expense to date)', round($netSurplus, 2)];
        $bs[] = ['', 'Total Equity', round($sumBal($equity) + $netSurplus, 2)];
        $spreadsheet->createSheet(); $spreadsheet->setActiveSheetIndex(2);
        $this->writeSheet($spreadsheet, 'Balance Sheet', $bs);

        // Tab 4 — Trial Balance (as of 31 Dec)
        $tb = [];
        $tb[] = ['GeneralLink Digital Ecosystem — Trial Balance'];
        $tb[] = [$nodeName.' — As of 31 Dec '.$year];
        $tb[] = ['Downloaded: '.now()->format('d M Y H:i')];
        $tb[] = [];
        $tb[] = ['Account Code', 'Account Name', 'Type', 'Debit (RM)', 'Credit (RM)'];
        $totalDebit = 0; $totalCredit = 0;
        foreach ($balances as $b) {
            $bal = self::normalBalance($b->account_type, (float) $b->total_debit, (float) $b->total_credit);
            $isDebitNormal = in_array($b->account_type, ['ASSET', 'EXPENSE'], true);
            $debitCol = $isDebitNormal ? max($bal, 0) : max(-$bal, 0);
            $creditCol = $isDebitNormal ? max(-$bal, 0) : max($bal, 0);
            $totalDebit += $debitCol; $totalCredit += $creditCol;
            $tb[] = [$b->account_code, $b->account_name, $b->account_type, round($debitCol, 2), round($creditCol, 2)];
        }
        $tb[] = [];
        $tb[] = ['', '', 'TOTAL', round($totalDebit, 2), round($totalCredit, 2)];
        $spreadsheet->createSheet(); $spreadsheet->setActiveSheetIndex(3);
        $this->writeSheet($spreadsheet, 'Trial Balance', $tb);

        $spreadsheet->setActiveSheetIndex(0);
        return $this->download($spreadsheet, 'AGM_ROS_Reporting_Pack_'.$year);
    }

    // ---------- ROS Submission Checklist (NEW 2 Sep 2026, Task #339) ----------
    // Distinct from Year-End Closing's own agm_held/ros_submitted pair
    // above — this is the fuller list of what actually needs to be
    // gathered before a Malaysian ROS annual return goes in: the report
    // pack itself, the current office-bearer list, verified financial
    // statements, AGM minutes, an up-to-date membership register, and
    // finally the submission reference. Same Prev/Next-by-year pattern
    // as Year-End Closing.

    public function rosSubmissionChecklist(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            if ($agent->role === 'ADMIN') {
                return $this->renderCbeNodePicker('cbe.accounting.ros-submission-checklist', __('cbe_accounting.ros_checklist_page_title'), leafOnly: false);
            }
            return redirect()->route('cbe.accounting.index');
        }

        $year = (int) $request->input('year', now()->year);
        $checklist = DB::table('cbe_ros_submission_checklists')->where('cbe_node_id', $nodeId)->where('fiscal_year', $year)->first();

        return view('cbe.accounting.ros-submission-checklist', [
            'year' => $year,
            'checklist' => $checklist,
            'activityRows' => $this->buildSecretaryActivityRows($nodeId, $year),
        ]);
    }

    // NEW 18 Sep 2026 — per Chris: the Secretary Activity Report (his
    // "association diary" / ROS annual activity report) must live
    // inside the ROS Submission screen, combining actual records
    // (Meeting Minutes + Activities — site visits, dinners, festival
    // prayers, seminars) with the entity's planned/upcoming Temple
    // Calendar events for the year, each summarised to one line of at
    // most 100 characters (Date, Time, Venue, Description). Shared with
    // AnnualReportController::secretaryReport()'s Excel export, which
    // keeps the full untruncated text in its own columns — this is only
    // for the compact on-screen list here.
    private function buildSecretaryActivityRows(string $nodeId, int $year): array
    {
        $minutes = DB::table('cbe_meeting_minutes')
            ->where('cbe_node_id', $nodeId)
            ->whereYear('meeting_date', $year)
            ->get()
            ->map(fn ($m) => (object) [
                'date' => $m->meeting_date, 'time' => $m->meeting_time, 'venue' => $m->venue,
                'type' => __('cbe_accounting.ros_activity_type_minutes'), 'description' => $m->title,
                'planned' => false,
            ]);

        $activities = DB::table('cbe_activities')
            ->where('cbe_node_id', $nodeId)
            ->whereYear('activity_date', $year)
            ->get()
            ->map(fn ($a) => (object) [
                'date' => $a->activity_date, 'time' => $a->activity_time ?? null, 'venue' => $a->venue ?? null,
                'type' => __('cbe_accounting.ros_activity_type_activity'), 'description' => $a->title,
                'planned' => false,
            ]);

        $planned = DB::table('cbe_temple_calendar_events')
            ->where('cbe_node_id', $nodeId)
            ->where('is_active', true)
            ->whereYear('event_date', $year)
            ->get()
            ->map(fn ($e) => (object) [
                'date' => $e->event_date, 'time' => $e->event_time ?? null, 'venue' => $e->venue ?? null,
                'type' => __('cbe_accounting.ros_activity_type_plan'), 'description' => $e->title,
                'planned' => true,
            ]);

        return $minutes->concat($activities)->concat($planned)->sortBy('date')->values()
            ->map(function ($row) {
                $dateStr = \Carbon\Carbon::parse($row->date)->format('d M Y');
                $timeStr = $row->time ? \Carbon\Carbon::parse($row->time)->format('g:i A') : '';
                $line = trim($dateStr . ($timeStr ? ' ' . $timeStr : '') . ($row->venue ? ' @ ' . $row->venue : '') . ' — ' . $row->description);
                $row->summary_line = \Illuminate\Support\Str::limit($line, 100);
                return $row;
            })->all();
    }

    public function saveRosSubmissionChecklist(Request $request)
    {
        [$agent, $nodeId] = $this->nodeAndGroup();
        if (! $nodeId) {
            return redirect()->route('cbe.accounting.index');
        }

        $request->validate([
            'year' => ['required', 'integer'],
            'ros_reference_no' => ['nullable', 'string', 'max:100'],
            'submission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $year = (int) $request->input('year');
        $existing = DB::table('cbe_ros_submission_checklists')->where('cbe_node_id', $nodeId)->where('fiscal_year', $year)->first();

        $data = [
            'item_annual_report_pack' => (bool) $request->boolean('item_annual_report_pack'),
            'item_office_bearer_list' => (bool) $request->boolean('item_office_bearer_list'),
            'item_financial_statements' => (bool) $request->boolean('item_financial_statements'),
            'item_agm_minutes' => (bool) $request->boolean('item_agm_minutes'),
            'item_membership_register' => (bool) $request->boolean('item_membership_register'),
            'item_form_submitted' => (bool) $request->boolean('item_form_submitted'),
            'ros_reference_no' => $request->input('ros_reference_no'),
            'submission_date' => $request->input('submission_date') ?: null,
            'notes' => $request->input('notes'),
            'updated_by' => $agent->agent_id,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('cbe_ros_submission_checklists')->where('checklist_id', $existing->checklist_id)->update($data);
        } else {
            DB::table('cbe_ros_submission_checklists')->insert(array_merge($data, [
                'checklist_id' => (string) Str::uuid(),
                'cbe_node_id' => $nodeId,
                'fiscal_year' => $year,
                'created_at' => now(),
            ]));
        }

        return redirect()->route('cbe.accounting.ros-submission-checklist', ['year' => $year])->with('success', __('cbe_accounting.ros_checklist_saved'));
    }
}
