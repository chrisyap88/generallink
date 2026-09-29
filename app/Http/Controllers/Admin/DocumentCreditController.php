<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DocumentCreditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// NEW 21 Jul 2026 — Document Credit Wallet, Admin-facing side. Admin
// sets the flat RM amount deducted per document read (system_settings
// key 'document_credit_deduction_amount'), reviews agents' top-up
// requests against their attached bank-in slip, and approves/rejects
// them — approving credits the agent's balance immediately via
// DocumentCreditService::creditTopup(). Also gives Admin a full list
// of every agent's current balance for a quick overview.
class DocumentCreditController extends Controller
{
    public function index(Request $request, DocumentCreditService $credit)
    {
        // PAGINATED 8 Aug 2026 per Chris: strict no-scroll rule — was
        // unbounded (->get()), rows beyond the visible area were just
        // silently clipped by the panel's overflow:hidden. Real
        // pagination now, bottom Prev/Next same as every other list.
        $pendingRequests = DB::table('document_credit_topup_requests as r')
            ->join('agents as a', 'r.agent_id', '=', 'a.agent_id')
            ->where('r.status', 'PENDING')
            ->orderBy('r.requested_at')
            ->select('r.*', 'a.full_name', 'a.agent_code', 'a.role')
            ->paginate(5, ['*'], 'dcPage');

        // REDUCED 8 Aug 2026 per Chris: strict no-scroll rule — this is a
        // secondary "recently reviewed" reference list, not the primary
        // action queue, so it's capped to the latest 3 (no scroll, no
        // pagination) rather than paginated like the list above.
        $reviewedRequests = DB::table('document_credit_topup_requests as r')
            ->join('agents as a', 'r.agent_id', '=', 'a.agent_id')
            ->whereIn('r.status', ['APPROVED', 'REJECTED'])
            ->orderByDesc('r.reviewed_at')
            ->limit(3)
            ->select('r.*', 'a.full_name', 'a.agent_code')
            ->get();

        // REBUILT 21 Jul 2026 — per Chris: a real 3-level network-tree
        // cascade — GL -> TL -> Introducer — exactly like Admin All
        // Intros (35.1/35.2 in the master spec), not a generic search
        // box. Each level is a dropdown scoped to the level above it:
        // choosing a GL narrows the TL dropdown to that GL's own TLs;
        // choosing a TL narrows the Introducer dropdown to that TL's
        // own Introducers. The free-text search box still exists but
        // now ALSO scopes its own suggestions/results to whatever
        // GL/TL/Introducer is currently selected, instead of searching
        // blindly across everyone. Unlike Admin All Intros this list
        // includes GL/TL rows themselves too, since GL/TL can also
        // hold a Document Credit balance.
        $glId = $request->get('gl_id', '');
        $tlId = $request->get('tl_id', '');
        $introId = $request->get('introducer_id', '');
        $agentSearch = trim((string) $request->get('agent_search', ''));

        // FIXED 21 Jul 2026 — per Chris: NEVER default-load every agent.
        // With hundreds (or one day, thousands) of agents, querying and
        // paginating the whole table on first load is exactly the "show
        // all by default" pattern he has flagged twice now — and it
        // does not scale. Nothing is queried until Admin picks at least
        // one filter level (GL/TL/Introducer) or types a search. Until
        // then the view shows a prompt, not a table.
        $filterApplied = ($glId !== '' || $tlId !== '' || $introId !== '' || $agentSearch !== '');

        if ($filterApplied) {
            $balancesQuery = DB::table('agents')
                ->where('is_deleted', false)
                ->where('role', '!=', 'ADMIN');

            if ($introId !== '') {
                // Introducer chosen — that one Introducer, plus any
                // sub-Introducer they personally recruited (however deep).
                $balancesQuery->where(function ($q) use ($introId) {
                    $q->where('agent_id', $introId)
                      ->orWhere('hierarchy_path', 'like', '%/' . $introId . '/%');
                });
            } elseif ($tlId !== '') {
                // TL chosen — that TL plus every Introducer under them, no
                // matter how deep (nested introducer recruiting), same
                // hierarchy_path technique DataScopeService::getAgentIds()
                // already uses elsewhere in this app.
                $balancesQuery->where(function ($q) use ($tlId) {
                    $q->where('agent_id', $tlId)
                      ->orWhere('hierarchy_path', 'like', '%/' . $tlId . '/%');
                });
            } elseif ($glId !== '') {
                $gl = DB::table('agents')->where('agent_id', $glId)->first();
                if ($gl) {
                    $balancesQuery->where('group_id', $gl->group_id);
                }
            }

            if ($agentSearch !== '') {
                // Free-text search with NO level chosen yet is the one
                // case that still touches the whole table — but it's an
                // indexed LIKE on name/code with a real WHERE clause, not
                // a "list everything" query, and still paginates.
                $balancesQuery->where(function ($q) use ($agentSearch) {
                    $q->where('full_name', 'like', "%{$agentSearch}%")
                      ->orWhere('agent_code', 'like', "%{$agentSearch}%");
                });
            }

            $agentBalances = $balancesQuery
                ->orderByDesc('document_credit_balance')
                ->select('agent_id', 'full_name', 'agent_code', 'role', 'document_credit_balance')
                ->paginate(8, ['*'], 'balPage')
                ->appends(['gl_id' => $glId, 'tl_id' => $tlId, 'introducer_id' => $introId, 'agent_search' => $agentSearch]);
        } else {
            // Nothing selected — empty paginator, no query executed.
            $agentBalances = new \Illuminate\Pagination\LengthAwarePaginator(
                [], 0, 8, 1, ['path' => $request->url()]
            );
        }

        // For re-populating the dropdowns with the currently selected
        // GL/TL/Introducer (so the filter row doesn't reset to blank
        // on pagination/search), plus the TL/Introducer option lists
        // for whichever levels are already chosen (server-rendered —
        // simpler and just as fast as AJAX for these small lists,
        // matches the GL viewer's own Team Document Credit screen).
        $selectedGL = $glId !== '' ? DB::table('agents')->where('agent_id', $glId)->first() : null;
        $selectedTL = $tlId !== '' ? DB::table('agents')->where('agent_id', $tlId)->first() : null;
        $selectedIntro = $introId !== '' ? DB::table('agents')->where('agent_id', $introId)->first() : null;

        // Server-rendered option lists for whichever levels are
        // relevant — simpler and just as fast as AJAX for lists this
        // size, and matches the GL viewer's own Team Document Credit
        // screen (see Shared\TeamDocumentCreditController). GL list is
        // always loaded (there are never many GLs); TL/Introducer lists
        // are only loaded once their parent is chosen — the SELECT
        // elements themselves are always shown in the view (disabled
        // with a placeholder until unlocked), per Chris: the 3 levels
        // must be visible from the start, not appear out of nowhere.
        $glOptions = DB::table('agents')->where('role', 'GROUP_LEADER')->where('is_deleted', false)->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code']);
        $tlOptions = $glId !== ''
            ? DB::table('agents')->where('role', 'TEAM_LEADER')->where('parent_id', $glId)->where('is_deleted', false)->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code'])
            : collect();
        $introOptions = $tlId !== ''
            ? DB::table('agents')->where('role', 'INTRODUCER')->where('parent_id', $tlId)->where('is_deleted', false)->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code'])
            : collect();

        $deductionAmount = $credit->deductionAmount();
        $topupFee = $credit->topupProcessingFee();
        $translationFee = $credit->translationFee();
        $rephraseFee = $credit->rephraseFee();

        return view('admin.document-credit.index', compact('pendingRequests', 'reviewedRequests', 'agentBalances', 'agentSearch', 'glId', 'tlId', 'introId', 'selectedGL', 'selectedTL', 'selectedIntro', 'glOptions', 'tlOptions', 'introOptions', 'deductionAmount', 'filterApplied', 'topupFee', 'translationFee', 'rephraseFee'));
    }

    /**
     * NEW 21 Jul 2026 — typeahead suggestions for the Agent Balances
     * free-text search box. FIXED same day: now scoped to whatever
     * GL/TL/Introducer is currently selected in the cascade (per
     * Chris — search must respect the chosen level, not search
     * blindly across every agent regardless of the dropdowns above).
     */
    public function agentTypeahead(Request $request)
    {
        $q = $request->get('q', '');
        $glId = $request->get('gl_id', '');
        $tlId = $request->get('tl_id', '');
        $introId = $request->get('introducer_id', '');

        $query = DB::table('agents')
            ->where('is_deleted', false)
            ->where('role', '!=', 'ADMIN')
            ->where(function ($q2) use ($q) {
                $q2->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
            });

        if ($introId !== '') {
            $query->where(function ($q2) use ($introId) {
                $q2->where('agent_id', $introId)->orWhere('hierarchy_path', 'like', '%/' . $introId . '/%');
            });
        } elseif ($tlId !== '') {
            $query->where(function ($q2) use ($tlId) {
                $q2->where('agent_id', $tlId)->orWhere('hierarchy_path', 'like', '%/' . $tlId . '/%');
            });
        } elseif ($glId !== '') {
            $gl = DB::table('agents')->where('agent_id', $glId)->first();
            if ($gl) $query->where('group_id', $gl->group_id);
        }

        $results = $query->orderBy('full_name')->limit(15)->get(['agent_id as id', 'full_name as name', 'agent_code as code']);

        return response()->json($results);
    }

    /**
     * NEW 21 Jul 2026 — drill-down: full top-up + usage history for one
     * agent, reached by clicking their row on the Agent Balances tab.
     */
    public function agentDetail(Request $request, string $agentId, DocumentCreditService $credit)
    {
        $agent = DB::table('agents')->where('agent_id', $agentId)->firstOrFail();

        $history = DB::table('document_credit_transactions')
            ->where('agent_id', $agentId)
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'histPage');

        $topupRequests = DB::table('document_credit_topup_requests')
            ->where('agent_id', $agentId)
            ->orderByDesc('requested_at')
            ->limit(20)
            ->get();

        $balance = $credit->balance($agentId);

        return view('admin.document-credit.agent-detail', compact('agent', 'history', 'topupRequests', 'balance'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'deduction_amount' => ['required', 'numeric', 'min:0.01'],
            'topup_fee'        => ['required', 'numeric', 'min:0'],
            // NEW 22 Jul 2026 — per Chris: Help Desk translation and
            // "Fix Wording" rephrase both draw from this same wallet.
            'translation_fee'  => ['required', 'numeric', 'min:0'],
            'rephrase_fee'     => ['required', 'numeric', 'min:0'],
        ]);
        $admin = Auth::guard('agent')->user();

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'document_credit_deduction_amount'],
            ['setting_value' => $request->input('deduction_amount'), 'updated_by' => $admin->agent_id, 'updated_at' => now()]
        );

        // NEW 21 Jul 2026 — per Chris: flat processing fee deducted from
        // every approved top-up (e.g. RM150 requested, RM1 fee, RM149
        // credited). Same system_settings convention as the deduction
        // amount above.
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'document_credit_topup_processing_fee'],
            ['setting_value' => $request->input('topup_fee'), 'updated_by' => $admin->agent_id, 'updated_at' => now()]
        );

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'document_credit_translation_fee'],
            ['setting_value' => $request->input('translation_fee'), 'updated_by' => $admin->agent_id, 'updated_at' => now()]
        );

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'document_credit_rephrase_fee'],
            ['setting_value' => $request->input('rephrase_fee'), 'updated_by' => $admin->agent_id, 'updated_at' => now()]
        );

        return back()->with('success', 'Settings updated: RM ' . number_format($request->input('deduction_amount'), 2) . ' per document read, RM ' . number_format($request->input('topup_fee'), 2) . ' processing fee per top-up, RM ' . number_format($request->input('translation_fee'), 2) . ' per message translation, RM ' . number_format($request->input('rephrase_fee'), 2) . ' per Fix Wording suggestion.');
    }

    public function viewSlip(string $requestId)
    {
        $topup = DB::table('document_credit_topup_requests')->where('request_id', $requestId)->firstOrFail();
        if (!Storage::disk('local')->exists($topup->bank_slip_file_path)) {
            abort(404, 'Bank-in slip file not found.');
        }
        return response()->file(Storage::disk('local')->path($topup->bank_slip_file_path));
    }

    public function approve(Request $request, string $requestId, DocumentCreditService $credit)
    {
        $admin = Auth::guard('agent')->user();
        $topup = DB::table('document_credit_topup_requests')->where('request_id', $requestId)->where('status', 'PENDING')->first();
        if (!$topup) {
            return back()->withErrors(['request' => 'This top-up request is no longer pending.']);
        }

        // NEW 22 Jul 2026 — per Chris: the same hold that applies to
        // Sales Transaction confirm() — an unresolved Risk Review flag
        // must be cleared before this top-up can be credited.
        if (app(\App\Services\FraudDetectionService::class)->hasUnresolvedFlag('DOCUMENT_CREDIT_TOPUP', $requestId)) {
            return back()->withErrors(['request' => 'This top-up has an unresolved Risk Review flag. Please review and clear it under Risk Review Queue before approving.']);
        }

        $credit->creditTopup($topup->agent_id, (float) $topup->amount_requested, $topup->request_id, $admin->agent_id);

        DB::table('document_credit_topup_requests')->where('request_id', $requestId)->update([
            'status'      => 'APPROVED',
            'reviewed_by' => $admin->agent_id,
            'reviewed_at' => now(),
            'updated_at'  => now(),
        ]);

        $agent = DB::table('agents')->where('agent_id', $topup->agent_id)->first();
        if ($agent) {
            app(NotificationService::class)->notify(
                [$agent],
                'DOCUMENT_CREDIT_TOPUP_APPROVED',
                'Document Credit Top-Up Approved',
                "Dear {$agent->full_name}, your Document Credit top-up of RM " . number_format($topup->amount_requested, 2) . " has been approved and credited to your balance. Thank you."
            );
        }

        return back()->with('success', 'Top-up approved and credited.');
    }

    public function reject(Request $request, string $requestId)
    {
        $request->validate(['admin_note' => ['required', 'string', 'max:500']]);
        $admin = Auth::guard('agent')->user();

        $topup = DB::table('document_credit_topup_requests')->where('request_id', $requestId)->where('status', 'PENDING')->first();
        if (!$topup) {
            return back()->withErrors(['request' => 'This top-up request is no longer pending.']);
        }

        DB::table('document_credit_topup_requests')->where('request_id', $requestId)->update([
            'status'      => 'REJECTED',
            'admin_note'  => $request->input('admin_note'),
            'reviewed_by' => $admin->agent_id,
            'reviewed_at' => now(),
            'updated_at'  => now(),
        ]);

        $agent = DB::table('agents')->where('agent_id', $topup->agent_id)->first();
        if ($agent) {
            app(NotificationService::class)->notify(
                [$agent],
                'DOCUMENT_CREDIT_TOPUP_REJECTED',
                'Document Credit Top-Up Not Approved',
                "Dear {$agent->full_name}, your Document Credit top-up request of RM " . number_format($topup->amount_requested, 2) . " could not be approved.\n\nReason: {$request->input('admin_note')}\n\nPlease resubmit with the correct bank-in slip, or contact Admin if you believe this is a mistake."
            );
        }

        return back()->with('success', 'Top-up request rejected.');
    }
}
