<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use App\Services\NoticeDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 21 Jul 2026 — Notice Board, Admin side: post one-way broadcast
// announcements every agent will see (important updates, promotions,
// holiday/festive greetings, admin contact info, company bank account
// number, customer hotline, etc). Two tabs — Active and Expired — both
// are Admin's own content to manage, so unlike Agent Balances there's
// no "don't load anything by default" concern here; it's a bounded
// list Admin needs to see in full to maintain it.
class NoticeBoardController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'active');
        $today = now()->toDateString();

        $query = DB::table('notices')->where('is_deleted', false);

        if ($tab === 'expired') {
            $query->whereNotNull('expires_at')->where('expires_at', '<', $today);
        } else {
            $query->where(function ($q) use ($today) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $today);
            });
        }

        $notices = $query
            ->orderByRaw('expires_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->paginate(8, ['*'], 'ntPage')
            ->appends(['tab' => $tab]);

        return view('admin.notice-board.index', compact('notices', 'tab'));
    }

    public function create()
    {
        return view('admin.notice-board.create');
    }

    public function store(Request $request, NoticeDeliveryService $delivery)
    {
        $admin = Auth::guard('agent')->user();

        $request->validate([
            'title'      => ['required', 'string', 'max:150'],
            'category'   => ['required', 'in:IMPORTANT_UPDATE,PROMOTION,HOLIDAY_FESTIVE,CONTACT_INFO,GENERAL'],
            'body'       => ['required', 'string', 'max:3000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('notice-board-attachments', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        $noticeId = (string) Str::uuid();

        DB::table('notices')->insert([
            'notice_id'             => $noticeId,
            'title'                  => $request->input('title'),
            'body'                   => $request->input('body'),
            'category'               => $request->input('category'),
            'attachment_file_name'   => $attachmentName,
            'attachment_file_path'   => $attachmentPath,
            'expires_at'             => $request->input('expires_at'),
            'posted_by_agent_id'     => $admin->agent_id,
            'is_deleted'             => false,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // NEW 8 Aug 2026 (Task #84) — beyond just landing on the Notice
        // Board for agents to browse, push it to whoever opted in to
        // this category on Email/WhatsApp, respecting each agent's own
        // frequency cap. Never blocks the post itself if a push fails.
        try {
            $delivery->deliver($noticeId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('NoticeDeliveryService failed', ['notice_id' => $noticeId, 'error' => $e->getMessage()]);
        }

        return redirect()->route('admin.notice-board.index')->with('success', 'Notice posted to the board.');
    }

    public function edit(string $noticeId)
    {
        $notice = DB::table('notices')->where('notice_id', $noticeId)->firstOrFail();
        return view('admin.notice-board.edit', compact('notice'));
    }

    public function update(Request $request, string $noticeId)
    {
        $request->validate([
            'title'      => ['required', 'string', 'max:150'],
            'category'   => ['required', 'in:IMPORTANT_UPDATE,PROMOTION,HOLIDAY_FESTIVE,CONTACT_INFO,GENERAL'],
            'body'       => ['required', 'string', 'max:3000'],
            'expires_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $notice = DB::table('notices')->where('notice_id', $noticeId)->firstOrFail();

        $attachmentPath = $notice->attachment_file_path;
        $attachmentName = $notice->attachment_file_name;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('notice-board-attachments', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        DB::table('notices')->where('notice_id', $noticeId)->update([
            'title'                  => $request->input('title'),
            'body'                   => $request->input('body'),
            'category'               => $request->input('category'),
            'attachment_file_name'   => $attachmentName,
            'attachment_file_path'   => $attachmentPath,
            'expires_at'             => $request->input('expires_at'),
            'updated_at'             => now(),
        ]);

        return redirect()->route('admin.notice-board.index')->with('success', 'Notice updated.');
    }

    public function destroy(string $noticeId)
    {
        DB::table('notices')->where('notice_id', $noticeId)->update(['is_deleted' => true, 'updated_at' => now()]);
        return back()->with('success', 'Notice removed from the board.');
    }

    // NEW 8 Aug 2026 — "Carolyn help to write": Admin sends whatever
    // title/message is currently typed, Carolyn returns a polished
    // rewrite (spelling fixed, clearer wording, a couple of emoji added).
    // Admin reviews the suggestion and clicks "Use this" to accept it —
    // nothing is auto-applied, and nothing is posted from here.
    public function aiAssist(Request $request, AiAssistantService $ai)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'body'  => ['required', 'string', 'max:3000'],
        ]);

        $result = $ai->improveNoticeText((string) $request->input('title', ''), (string) $request->input('body'));

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'ERROR', 'message' => $result['message'] ?? "Sorry, Carolyn couldn't rewrite that just now — please try again."], 200);
        }

        return response()->json(['status' => 'OK', 'title' => $result['title'], 'body' => $result['body']]);
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
