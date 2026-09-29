<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Breakaway Bonus claims tracking (task #207). One row
// per period a breakaway target was hit (created by
// `breakaway:evaluate-bonuses`, see EvaluateBreakawayBonuses command).
// This screen is for the ORIGINAL Group Leader (e.g. Chris Yap) to see
// their claim vouchers and mark them claimed once they've filed with the
// vendor — Admin can see every GL's claims. Nobody else (TL/Introducer)
// has any reason to see this, since only a Group Leader can ever be an
// original_gl_agent_id.
//
// No wallet is touched anywhere in this controller — "Mark as Claimed"
// is Chris Yap's own bookkeeping only, per his explicit decision that
// the real payment happens outside GeneralLink with the insurance
// vendor.
class BreakawayBonusClaimController extends Controller
{
    private function guardAccess($agent): void
    {
        abort_unless(in_array($agent->role, ['ADMIN', 'GROUP_LEADER'], true), 403);
    }

    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $this->guardAccess($agent);
        $isAdmin = $agent->role === 'ADMIN';

        $glId = $isAdmin ? trim((string) $request->get('gl_id', '')) : $agent->agent_id;
        $status = in_array($request->get('status'), ['PENDING', 'CLAIMED'], true) ? $request->get('status') : '';

        $query = DB::table('breakaway_bonus_claims as c')
            ->join('agents as pgl', 'c.promoted_gl_agent_id', '=', 'pgl.agent_id')
            ->join('agents as ogl', 'c.original_gl_agent_id', '=', 'ogl.agent_id');

        if (!$isAdmin) {
            $query->where('c.original_gl_agent_id', $agent->agent_id);
        } elseif ($glId !== '') {
            $query->where('c.original_gl_agent_id', $glId);
        }
        if ($status !== '') {
            $query->where('c.status', $status);
        }

        $selectedGL = $glId !== '' ? DB::table('agents')->where('agent_id', $glId)->first() : null;

        // Totals for the scoped set (before pagination), split by status
        // — small, cheap query, matches the summary-card pattern used
        // elsewhere (e.g. Renewal Forecast, Agent Balances).
        $totalsQuery = clone $query;
        $totals = $totalsQuery->select('c.status', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(c.bonus_amount) as amt'))
            ->groupBy('c.status')->get()->keyBy('status');

        $pendingCount = (int) ($totals['PENDING']->cnt ?? 0);
        $pendingAmount = (float) ($totals['PENDING']->amt ?? 0);
        $claimedCount = (int) ($totals['CLAIMED']->cnt ?? 0);
        $claimedAmount = (float) ($totals['CLAIMED']->amt ?? 0);

        $claims = $query->select(
            'c.claim_id', 'c.link_id', 'c.period_start', 'c.period_end', 'c.target_metric',
            'c.target_amount', 'c.achieved_amount', 'c.bonus_pct', 'c.bonus_amount', 'c.status', 'c.claimed_at',
            'pgl.full_name as promoted_gl_name', 'pgl.agent_code as promoted_gl_code',
            'ogl.full_name as original_gl_name', 'ogl.agent_code as original_gl_code'
        )->orderByDesc('c.created_at')->paginate(8)->appends($request->except('page'));

        return view('breakaway.claims', compact('claims', 'isAdmin', 'agent', 'glId', 'selectedGL', 'status', 'pendingCount', 'pendingAmount', 'claimedCount', 'claimedAmount'));
    }

    // Admin-only typeahead, same lightweight pattern used for the
    // Renewal Forecast GL search — never loads the whole company list.
    public function glTypeahead(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        if ($agent->role !== 'ADMIN') {
            return response()->json([]);
        }
        $q = trim((string) $request->get('q', ''));

        $query = DB::table('agents')->where('role', 'GROUP_LEADER')->where('is_deleted', false);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
            });
        }

        return response()->json($query->orderBy('full_name')->limit(20)->get(['agent_id', 'full_name', 'agent_code']));
    }

    public function markClaimed(Request $request, string $claimId)
    {
        $agent = Auth::guard('agent')->user();
        $this->guardAccess($agent);

        $claim = DB::table('breakaway_bonus_claims')->where('claim_id', $claimId)->first();
        abort_if(!$claim, 404);
        abort_unless($agent->role === 'ADMIN' || $claim->original_gl_agent_id === $agent->agent_id, 403);

        if ($claim->status === 'PENDING') {
            DB::table('breakaway_bonus_claims')->where('claim_id', $claimId)->update([
                'status'      => 'CLAIMED',
                'claimed_at'  => now(),
                'claimed_by'  => $agent->agent_id,
                'updated_at'  => now(),
            ]);
            AuditService::logChange('breakaway_bonus_claims', $claimId, 'BREAKAWAY_CLAIM_MARKED_CLAIMED', $claim, ['status' => 'CLAIMED']);
        }

        return redirect()->back()->with('success', 'Marked as claimed.');
    }

    // Printable/downloadable voucher — plain standalone HTML (no
    // dashboard layout), styled for A4 print. Chris downloads/saves it
    // as a PDF via the browser's own Print dialog — no new PHP
    // dependency needed, so nothing new that could break the earning
    // income engine.
    public function voucher(string $claimId)
    {
        $agent = Auth::guard('agent')->user();
        $this->guardAccess($agent);

        $claim = DB::table('breakaway_bonus_claims as c')
            ->join('agents as pgl', 'c.promoted_gl_agent_id', '=', 'pgl.agent_id')
            ->join('agents as ogl', 'c.original_gl_agent_id', '=', 'ogl.agent_id')
            ->where('c.claim_id', $claimId)
            ->select('c.*', 'pgl.full_name as promoted_gl_name', 'pgl.agent_code as promoted_gl_code', 'ogl.full_name as original_gl_name', 'ogl.agent_code as original_gl_code')
            ->first();
        abort_if(!$claim, 404);
        abort_unless($agent->role === 'ADMIN' || $claim->original_gl_agent_id === $agent->agent_id, 403);

        return view('breakaway.voucher', compact('claim'));
    }
}
