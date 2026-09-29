<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AiMatchService;
use App\Services\NoticeRelevanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 21 Jul 2026 — Notice Board, agent-facing side: read-only view of
// every active broadcast from Admin (important updates, promotions,
// holiday/festive greetings, admin contact info, bank account number,
// customer hotline, etc). Whichever notices land on the page the agent
// is viewing get marked read for them immediately, which is what
// clears both the sidebar's red unread-count badge and each notice's
// own "NEW" flag.
class NoticeBoardController extends Controller
{
    public function index(Request $request, AiMatchService $aiMatch)
    {
        $agent = Auth::guard('agent')->user();
        $today = now()->toDateString();

        // NEW 8 Aug 2026 (Task #87) — search box + category filter, per
        // Chris: agents need a way to actually find an offer, not just
        // page through everything in date order.
        $search = trim((string) $request->get('q', ''));
        $category = $request->get('category');

        // NEW 8 Aug 2026 (Task #89) — GLADE Phase 2: when the agent is
        // just browsing (no search text, no category filter typed in),
        // rank by relevance (their own category preference + recency)
        // instead of pure recency, so the offers most likely to matter to
        // THIS agent surface first. Once they search or pick a category
        // explicitly, that intent takes over and plain recency is clearer.
        $useRelevance = $search === '' && !$category;
        $relevanceBindings = [];
        if ($useRelevance) {
            $pref = DB::table('agent_notification_preferences')->where('agent_id', $agent->agent_id)->first();
            $prefCategories = $pref && $pref->categories ? json_decode($pref->categories, true) : null;
            [$relevanceSql, $relevanceBindings] = NoticeRelevanceService::sqlExpression($prefCategories);
        }

        $notices = DB::table('notices as n')
            ->leftJoin('notice_reads as r', function ($join) use ($agent) {
                $join->on('n.notice_id', '=', 'r.notice_id')->where('r.agent_id', '=', $agent->agent_id);
            })
            ->where('n.is_deleted', false)
            ->where(function ($q) use ($today) {
                $q->whereNull('n.expires_at')->orWhere('n.expires_at', '>=', $today);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('n.title', 'like', "%{$search}%")->orWhere('n.body', 'like', "%{$search}%");
                });
            })
            ->when($category, fn ($q) => $q->where('n.category', $category))
            ->when($useRelevance, function ($q) use ($relevanceSql, $relevanceBindings) {
                $q->orderByRaw('n.expires_at IS NULL DESC')
                  ->orderByRaw("{$relevanceSql} DESC", $relevanceBindings);
            }, function ($q) {
                $q->orderByRaw('n.expires_at IS NULL DESC')
                  ->orderByDesc('n.created_at');
            })
            ->select('n.*', 'r.read_at')
            // REDUCED 8 Aug 2026 per Chris: strict no-scroll rule — 8
            // full notice cards (title + meta + free-text body, possibly
            // an AI Insight blurb and an attachment link each) never
            // reliably fit one screen. 3/page matches the density other
            // variable-height card lists in this app already use.
            ->paginate(3, ['*'], 'ntPage')
            ->appends(['q' => $search, 'category' => $category]);

        // Mark every notice on this page as read for this agent — a
        // simple insertOrIgnore per row (unique constraint on
        // notice_id+agent_id prevents duplicates if they revisit).
        // NEW 8 Aug 2026 (Task #90) — GLADE Phase 3: for PROMOTION notices
        // only, attach an AI "why this matters to you" blurb (generated
        // once and cached — see AiMatchService), bounded to just this
        // page's up-to-8 notices so browsing never triggers more than a
        // handful of AI calls, and only ever once per notice per agent.
        foreach ($notices as $n) {
            if (!$n->read_at) {
                DB::table('notice_reads')->insertOrIgnore([
                    'read_id'   => (string) Str::uuid(),
                    'notice_id' => $n->notice_id,
                    'agent_id'  => $agent->agent_id,
                    'read_at'   => now(),
                ]);
            }
            $n->ai_blurb = $n->category === 'PROMOTION' ? $aiMatch->matchFor($n, $agent) : null;
        }

        // Top Prev button needs the right dashboard route per role —
        // same convention as Enquiries / Team Document Credit.
        $dashboardRoute = $agent->role === 'GROUP_LEADER' ? 'gl.dashboard' : ($agent->role === 'TEAM_LEADER' ? 'tl.dashboard' : 'introducer.dashboard');

        return view('notice-board.index', compact('notices', 'dashboardRoute', 'search', 'category'));
    }

    // NEW — type-ahead suggestions for the title/content search box
    // above, matching only titles (body text is too long to show as a
    // suggestion) among still-active notices.
    public function typeahead(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $today = now()->toDateString();
        $needle = '%'.$q.'%';

        $results = DB::table('notices')
            ->where('is_deleted', false)
            ->where(function ($qr) use ($today) {
                $qr->whereNull('expires_at')->orWhere('expires_at', '>=', $today);
            })
            ->where(function ($qr) use ($needle) {
                $qr->where('title', 'like', $needle)->orWhere('body', 'like', $needle);
            })
            ->orderByDesc('created_at')->limit(15)
            ->get(['notice_id', 'title', 'category']);

        return response()->json($results);
    }

    public function attachment(string $noticeId)
    {
        $notice = DB::table('notices')->where('notice_id', $noticeId)->firstOrFail();

        if (!$notice->attachment_file_path || !Storage::disk('local')->exists($notice->attachment_file_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($notice->attachment_file_path));
    }
}
