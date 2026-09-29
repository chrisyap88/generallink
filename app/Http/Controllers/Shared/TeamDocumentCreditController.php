<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use App\Services\DocumentCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 21 Jul 2026 — Document Credit Wallet, read-only TEAM view for
// GL/TL/Introducer (per Chris: "GL choose TL and Introducer, TL choose
// introducer, introducer choose his downline" — the same network-tree
// authority policy DataScopeService already enforces everywhere else
// in this app, applied here). No approve/reject/settings actions live
// here — those stay Admin-only (see Admin\DocumentCreditController).
// This is purely "can my upline see how my team is doing on Document
// Credit", same spirit as GL Commissions / Network screens.
class TeamDocumentCreditController extends Controller
{
    public function index(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $tlId = $request->get('tl_id', '');
        $agentSearch = trim((string) $request->get('agent_search', ''));

        $query = DB::table('agents')->where('is_deleted', false)->where('role', '!=', 'ADMIN');

        $myTLs = collect();

        if ($scope->isGL()) {
            // GL: whole group by default, or narrow to one TL + their
            // Introducers if chosen. TL list is small enough to render
            // server-side — no AJAX needed.
            $myTLs = DB::table('agents')
                ->where('parent_id', $agent->agent_id)
                ->where('role', 'TEAM_LEADER')->where('is_deleted', false)
                ->orderBy('full_name')->get(['agent_id', 'full_name', 'agent_code']);

            if ($tlId !== '') {
                $query->where(function ($q) use ($tlId) {
                    $q->where('agent_id', $tlId)->orWhere('hierarchy_path', 'like', '%/' . $tlId . '/%');
                });
            } else {
                $query->where('group_id', $agent->group_id);
            }
        } elseif ($scope->isTL()) {
            // TL: no TL dropdown (they ARE the TL) — themselves + every
            // Introducer under them, however deep.
            $query->where(function ($q) use ($agent) {
                $q->where('agent_id', $agent->agent_id)->orWhere('hierarchy_path', 'like', '%/' . $agent->agent_id . '/%');
            });
        } else {
            // Introducer: themselves + any Introducer they personally
            // recruited (however deep) — often empty, that's fine.
            $query->where(function ($q) use ($agent) {
                $q->where('agent_id', $agent->agent_id)->orWhere('hierarchy_path', 'like', '%/' . $agent->agent_id . '/%');
            });
        }

        if ($agentSearch !== '') {
            $query->where(function ($q) use ($agentSearch) {
                $q->where('full_name', 'like', "%{$agentSearch}%")->orWhere('agent_code', 'like', "%{$agentSearch}%");
            });
        }

        $teamBalances = $query
            ->orderByDesc('document_credit_balance')
            ->select('agent_id', 'full_name', 'agent_code', 'role', 'document_credit_balance')
            ->paginate(8, ['*'], 'balPage')
            ->appends(['tl_id' => $tlId, 'agent_search' => $agentSearch]);

        $selectedTL = $tlId !== '' ? DB::table('agents')->where('agent_id', $tlId)->first() : null;
        $isGL = $scope->isGL();

        // NEW 21 Jul 2026 — top Prev button needs the right dashboard
        // route per role. FIXED 22 Jul 2026: GL's route is actually
        // 'gl.dashboard' (routes/web.php wraps the GL group in
        // Route::prefix('gl')->name('gl.') — there is no unprefixed
        // 'dashboard' route), matching TL's 'tl.dashboard' and
        // Introducer's 'introducer.dashboard'. The old unprefixed
        // 'dashboard' assumption threw RouteNotFoundException for every
        // GL who opened this screen.
        $dashboardRoute = $scope->isGL() ? 'gl.dashboard' : ($scope->isTL() ? 'tl.dashboard' : 'introducer.dashboard');

        return view('document-credit.team-index', compact('teamBalances', 'myTLs', 'tlId', 'selectedTL', 'agentSearch', 'isGL', 'dashboardRoute'));
    }

    // NEW — type-ahead suggestions for the agent_search field above,
    // reusing the exact same downline-scope rules as index() so an
    // agent can never see a suggestion outside what they're allowed
    // to see.
    public function typeahead(Request $request, DataScopeService $scope)
    {
        $agent = Auth::guard('agent')->user();
        $tlId = $request->query('tl_id', '');
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $query = DB::table('agents')->where('is_deleted', false)->where('role', '!=', 'ADMIN');

        if ($scope->isGL()) {
            if ($tlId !== '') {
                $query->where(function ($qr) use ($tlId) {
                    $qr->where('agent_id', $tlId)->orWhere('hierarchy_path', 'like', '%/' . $tlId . '/%');
                });
            } else {
                $query->where('group_id', $agent->group_id);
            }
        } else {
            $query->where(function ($qr) use ($agent) {
                $qr->where('agent_id', $agent->agent_id)->orWhere('hierarchy_path', 'like', '%/' . $agent->agent_id . '/%');
            });
        }

        $needle = '%'.$q.'%';
        $results = $query
            ->where(function ($qr) use ($needle) {
                $qr->where('full_name', 'like', $needle)->orWhere('agent_code', 'like', $needle);
            })
            ->orderBy('full_name')->limit(15)
            ->get(['agent_id', 'full_name', 'agent_code']);

        return response()->json($results);
    }

    public function agentDetail(string $agentId, DataScopeService $scope, DocumentCreditService $credit)
    {
        // Security: never let a GL/TL/Introducer drill into an agent
        // outside their own downline just by guessing/editing the URL.
        $scope->verifyAgentAccess($agentId);

        $agentRow = DB::table('agents')->where('agent_id', $agentId)->firstOrFail();

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

        // NEW 21 Jul 2026 — can the person VIEWING this screen actually
        // transfer credit to the agent they're looking at? Only true
        // when they're looking at someone else (not their own row) in
        // their own downline, and they themselves have a balance to
        // give. verifyAgentAccess() above already proved this agent is
        // in-scope; this just decides whether to show the transfer form.
        $me = Auth::guard('agent')->user();
        $canTransferHere = $agentId !== $me->agent_id;
        $myBalance = $credit->balance($me->agent_id);

        return view('document-credit.team-agent-detail', ['agent' => $agentRow, 'history' => $history, 'topupRequests' => $topupRequests, 'balance' => $balance, 'canTransferHere' => $canTransferHere, 'myBalance' => $myBalance]);
    }

    /**
     * NEW 21 Jul 2026 — GL/TL/Introducer transfers part of their OWN
     * Document Credit balance to one specific downline agent. Security:
     * verifyAgentAccess() guarantees the target is genuinely within the
     * caller's own downline, exactly the same guard used for viewing —
     * it is impossible to transfer to an agent outside your own team
     * just by editing the form's hidden agent_id.
     */
    public function transfer(Request $request, string $agentId, DataScopeService $scope, DocumentCreditService $credit)
    {
        $scope->verifyAgentAccess($agentId);
        $me = Auth::guard('agent')->user();

        if ($agentId === $me->agent_id) {
            return back()->withErrors(['transfer' => 'You cannot transfer credit to yourself.']);
        }

        $request->validate([
            'transfer_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = (float) $request->input('transfer_amount');

        if ($credit->balance($me->agent_id) < $amount) {
            return back()->withErrors(['transfer' => 'Your own balance (RM ' . number_format($credit->balance($me->agent_id), 2) . ') is not enough to transfer RM ' . number_format($amount, 2) . '.']);
        }

        $credit->transfer($me->agent_id, $agentId, $amount);

        $target = DB::table('agents')->where('agent_id', $agentId)->first();
        return back()->with('success', 'RM ' . number_format($amount, 2) . ' transferred to ' . ($target->full_name ?? 'agent') . '.');
    }
}
