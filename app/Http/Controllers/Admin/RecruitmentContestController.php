<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #211). Admin creates
// time-boxed recruitment contests; RecruitmentContestController (Shared)
// is the agent-facing leaderboard/progress screen. Awards are never
// auto-credited — Admin marks them awarded by hand here, same pattern
// as Breakaway Bonus claims.
class RecruitmentContestController extends Controller
{
    public function index(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : null;
        $groupLabels = DB::table('group_labels')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get(['vendor_id', 'vendor_name']);

        $contests = DB::table('recruitment_contests as c')
            ->leftJoin('vendors as v', 'c.sponsor_vendor_id', '=', 'v.vendor_id')
            ->where('c.group_label_id', $groupLabelId)
            ->orderByDesc('c.start_date')
            ->select('c.*', 'v.vendor_name as sponsor_vendor_name')
            ->paginate(8)->appends($request->except('page'));

        foreach ($contests as $c) {
            $c->winner_count = DB::table('recruitment_contest_awards')->where('contest_id', $c->contest_id)->count();
        }

        // NEW 10 Aug 2026 — per Chris: "study and modify the method to
        // upload video for this entire programs" — same Video Library
        // reuse mechanism as Broadcast Campaigns, see that controller's
        // index() for the full reasoning.
        try {
            $libraryVideos = DB::table('video_library')
                ->where('video_type', 'MARKETING_PROMOTION')
                ->where('status', 'ACTIVE')
                ->orderByDesc('created_at')
                ->get(['video_id', 'video_name']);
        } catch (\Throwable $e) {
            $libraryVideos = collect();
        }

        return view('growth.contests-admin', compact('contests', 'groupLabelId', 'groupLabels', 'vendors', 'libraryVideos'));
    }

    public function store(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;

        $request->validate([
            'group_label_id'    => ['nullable', 'exists:group_labels,group_label_id'],
            'title'             => ['required', 'string', 'max:150'],
            'description'       => ['nullable', 'string'],
            'rules_text'        => ['nullable', 'string'],
            'metric'            => ['required', 'in:RECRUIT_COUNT,SALES_VOLUME,EARNING_INCOME'],
            'contest_mode'      => ['required', 'in:THRESHOLD,RANKED_TOP3'],
            'target_value'      => ['required', 'numeric', 'min:0.01'],
            'reward_type'       => ['required', 'in:POINTS,CASH,DOCUMENT_CREDIT'],
            'reward_value'      => ['required', 'numeric', 'min:0.01'],
            'reward_value_2nd'  => ['nullable', 'numeric', 'min:0.01'],
            'reward_value_3rd'  => ['nullable', 'numeric', 'min:0.01'],
            'start_date'        => ['required', 'date'],
            'end_date'          => ['required', 'date', 'after_or_equal:start_date'],
            'sponsor_vendor_id' => ['nullable', 'exists:vendors,vendor_id'],
            'sponsor_amount'    => ['nullable', 'numeric', 'min:0.01'],
            'sponsor_notes'     => ['nullable', 'string'],
            'banner_image'      => ['nullable', 'image', 'max:5120'],
            'video_source'      => ['nullable', 'in:LINK,UPLOAD,LIBRARY'],
            'video_url'         => ['nullable', 'url', 'max:255'],
            'video_file'        => ['nullable', 'mimes:mp4,mov,webm', 'max:51200'],
            'video_id'          => ['nullable', 'exists:video_library,video_id'],
        ]);

        $bannerPath = $request->hasFile('banner_image') ? $request->file('banner_image')->store('contest-banners', 'public') : null;
        $videoUrl = null;
        $videoFilePath = null;
        $videoId = null;
        if ($request->input('video_source') === 'UPLOAD' && $request->hasFile('video_file')) {
            $videoFilePath = $request->file('video_file')->store('contest-videos', 'public');
        } elseif ($request->input('video_source') === 'LINK' && $request->filled('video_url')) {
            $videoUrl = $request->input('video_url');
        } elseif ($request->input('video_source') === 'LIBRARY' && $request->filled('video_id')) {
            $videoId = $request->input('video_id');
        }

        $contestId = (string) Str::uuid();
        DB::table('recruitment_contests')->insert([
            'contest_id'        => $contestId,
            'group_label_id'    => $groupLabelId,
            'title'             => $request->input('title'),
            'description'       => $request->input('description'),
            'rules_text'        => $request->input('rules_text'),
            'banner_image_path' => $bannerPath,
            'video_url'         => $videoUrl,
            'video_file_path'   => $videoFilePath,
            'video_id'          => $videoId,
            'metric'            => $request->input('metric'),
            'contest_mode'      => $request->input('contest_mode'),
            'target_value'      => (float) $request->input('target_value'),
            'reward_type'       => $request->input('reward_type'),
            'reward_value'      => (float) $request->input('reward_value'),
            'reward_value_2nd'  => $request->filled('reward_value_2nd') ? (float) $request->input('reward_value_2nd') : null,
            'reward_value_3rd'  => $request->filled('reward_value_3rd') ? (float) $request->input('reward_value_3rd') : null,
            'start_date'        => $request->input('start_date'),
            'end_date'          => $request->input('end_date'),
            'sponsor_vendor_id' => $request->input('sponsor_vendor_id') ?: null,
            'sponsor_amount'    => $request->filled('sponsor_amount') ? (float) $request->input('sponsor_amount') : null,
            'sponsor_notes'     => $request->input('sponsor_notes'),
            'is_active'         => true,
            'created_by'        => Auth::guard('agent')->id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        AuditService::logChange('recruitment_contests', $contestId, 'CONTEST_CREATED', null, ['title' => $request->input('title')]);

        return redirect()->route('admin.growth.contests.index', $groupLabelId ? ['group_label_id' => $groupLabelId] : [])->with('success', 'Contest created.');
    }

    public function toggleActive(Request $request, string $id)
    {
        $contest = DB::table('recruitment_contests')->where('contest_id', $id)->first();
        abort_if(!$contest, 404);

        DB::table('recruitment_contests')->where('contest_id', $id)->update(['is_active' => !$contest->is_active, 'updated_at' => now()]);
        AuditService::logChange('recruitment_contests', $id, 'CONTEST_TOGGLED', ['is_active' => $contest->is_active], ['is_active' => !$contest->is_active]);

        return redirect()->back()->with('success', $contest->is_active ? 'Contest deactivated.' : 'Contest activated.');
    }

    // NEW 25 Jul 2026 — per Chris: "admin need to inform and remind."
    // Manual, not automatic (per Chris's choice) — Admin decides when to
    // tell eligible agents about a contest. First click = an
    // announcement ("a new contest just started"); every click after
    // that = a reminder ("still running, X days left"), same button,
    // wording adapts off last_announced_at/announce_count. In-app +
    // email, via the same NotificationService used everywhere else.
    public function announce(string $id)
    {
        $contest = DB::table('recruitment_contests')->where('contest_id', $id)->first();
        abort_if(!$contest, 404);

        $recipients = Agent::where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
            ->when($contest->group_label_id, fn ($q) => $q->where('group_label_id', $contest->group_label_id))
            ->get();

        if ($recipients->isEmpty()) {
            return redirect()->back()->withErrors(['announce' => 'No eligible agents to notify for this contest.']);
        }

        $rewardLabel = match ($contest->reward_type) {
            'POINTS'          => number_format($contest->reward_value, 0) . ' reward points',
            'DOCUMENT_CREDIT' => number_format($contest->reward_value, 2) . ' document credits',
            default            => 'RM ' . number_format($contest->reward_value, 2),
        };
        $metricLabel = match ($contest->metric) {
            'RECRUIT_COUNT'  => 'recruit ' . (int) $contest->target_value . ' new agent(s)',
            'SALES_VOLUME'   => 'reach RM ' . number_format($contest->target_value, 2) . ' in sales volume',
            'EARNING_INCOME' => 'reach RM ' . number_format($contest->target_value, 2) . ' in earning income',
            default          => 'hit the target',
        };
        $daysLeft = max(0, now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($contest->end_date)->endOfDay(), false));

        $isFirstTime = !$contest->last_announced_at;
        $title = $isFirstTime ? "New Contest: {$contest->title}" : "Reminder: {$contest->title} — {$daysLeft} day(s) left";
        $modeLine = $contest->contest_mode === 'RANKED_TOP3'
            ? "The top 3 highest achievers by " . \Carbon\Carbon::parse($contest->end_date)->format('d M Y') . " win — 1st: {$rewardLabel}."
            : "Everyone who manages to {$metricLabel} by " . \Carbon\Carbon::parse($contest->end_date)->format('d M Y') . " wins {$rewardLabel}.";
        $message = ($isFirstTime ? "A new recruitment contest just started: " : "Just a reminder — the contest \"{$contest->title}\" is still running: ")
            . $modeLine
            . ($contest->rules_text ? "\n\nRules: {$contest->rules_text}" : '')
            . "\n\nCheck Growth & Outreach Center > Recruitment Contests for your progress.";

        (new NotificationService())->notify($recipients->all(), 'CONTEST_ANNOUNCEMENT', $title, $message);

        DB::table('recruitment_contests')->where('contest_id', $id)->update([
            'last_announced_at' => now(),
            'announce_count'    => $contest->announce_count + 1,
            'updated_at'        => now(),
        ]);

        AuditService::logChange('recruitment_contests', $id, 'CONTEST_ANNOUNCED', null, ['recipient_count' => $recipients->count(), 'first_time' => $isFirstTime]);

        return redirect()->back()->with('success', ($isFirstTime ? 'Announced' : 'Reminder sent') . " to {$recipients->count()} agent(s).");
    }

    public function winners(string $id)
    {
        $contest = DB::table('recruitment_contests')->where('contest_id', $id)->first();
        abort_if(!$contest, 404);

        $winners = DB::table('recruitment_contest_awards as w')
            ->join('agents as a', 'w.agent_id', '=', 'a.agent_id')
            ->where('w.contest_id', $id)
            ->select('w.award_id', 'w.achieved_value', 'w.placement', 'w.reward_type', 'w.reward_value', 'w.status', 'w.awarded_at', 'a.full_name', 'a.agent_code', 'a.role')
            ->orderByRaw('w.placement IS NULL, w.placement ASC')
            ->orderByDesc('w.achieved_value')
            ->get();

        return view('growth.contest-winners', compact('contest', 'winners'));
    }

    public function markAwarded(Request $request, string $awardId)
    {
        $award = DB::table('recruitment_contest_awards')->where('award_id', $awardId)->first();
        abort_if(!$award, 404);

        if ($award->status === 'PENDING') {
            DB::table('recruitment_contest_awards')->where('award_id', $awardId)->update([
                'status'      => 'AWARDED',
                'awarded_at'  => now(),
                'awarded_by'  => Auth::guard('agent')->id(),
                'updated_at'  => now(),
            ]);
            AuditService::logChange('recruitment_contest_awards', $awardId, 'CONTEST_AWARD_MARKED_AWARDED', $award, ['status' => 'AWARDED']);
        }

        return redirect()->back()->with('success', 'Marked as awarded.');
    }
}
