<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NoticeDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// NEW 8 Aug 2026 (Task #95) — Admin's approval queue for vendor/agent-
// submitted offer requests. Approving simply inserts one row into the
// EXISTING notices table (same shape as Admin\NoticeBoardController::store())
// and calls the EXISTING NoticeDeliveryService — every downstream GLADE
// phase (relevance ranking, AI Insight, delivery, analytics) picks it up
// automatically, unchanged. This is deliberately NOT a parallel delivery
// pipeline.
class OfferRequestApprovalController extends Controller
{
    public function index()
    {
        $requests = DB::table('offer_requests as o')
            ->join('vendors as v', 'v.vendor_id', '=', 'o.vendor_id')
            ->where('o.status', 'PENDING')
            ->select('o.*', 'v.vendor_name', 'v.is_active as vendor_is_active', 'v.industry')
            ->orderBy('o.created_at')
            ->get();

        // Auto-check #2 (Chris's confirmed criteria): does this vendor
        // already have a live approved offer? Computed per row here
        // (small pending list — cheap) rather than in the main query.
        foreach ($requests as $r) {
            $r->has_duplicate_active = DB::table('offer_requests as o2')
                ->join('notices as n', 'n.notice_id', '=', 'o2.notice_id')
                ->where('o2.vendor_id', $r->vendor_id)
                ->where('o2.status', 'APPROVED')
                ->where('n.is_deleted', false)
                ->where(function ($q) { $q->whereNull('n.expires_at')->orWhere('n.expires_at', '>=', now()->toDateString()); })
                ->exists();

            $r->submitter_label = $r->submitted_by_type === 'VENDOR'
                ? 'Vendor (self-submitted)'
                : 'Agent: ' . (DB::table('agents')->where('agent_id', $r->submitted_by_agent_id)->value('full_name') ?? 'Unknown');
        }

        return view('admin.offer-requests.pending', compact('requests'));
    }

    public function approve(string $requestId, NoticeDeliveryService $delivery)
    {
        $admin = Auth::guard('agent')->user();
        $offer = DB::table('offer_requests')->where('request_id', $requestId)->where('status', 'PENDING')->firstOrFail();

        $noticeId = (string) Str::uuid();
        DB::table('notices')->insert([
            'notice_id'           => $noticeId,
            'title'                => $offer->title,
            'body'                 => $offer->body,
            'category'             => 'PROMOTION',
            'attachment_file_name' => $offer->attachment_file_name,
            'attachment_file_path' => $offer->attachment_file_path,
            'expires_at'           => $offer->expiry_date,
            'posted_by_agent_id'   => $admin->agent_id,
            'is_deleted'           => false,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        DB::table('offer_requests')->where('request_id', $requestId)->update([
            'status'              => 'APPROVED',
            'notice_id'           => $noticeId,
            'reviewed_by_agent_id'=> $admin->agent_id,
            'reviewed_at'         => now(),
            'updated_at'          => now(),
        ]);

        try {
            $delivery->deliver($noticeId);
        } catch (\Throwable $e) {
            Log::warning('OfferRequestApprovalController: NoticeDeliveryService failed', ['notice_id' => $noticeId, 'error' => $e->getMessage()]);
        }

        \App\Services\AuditService::logChange('offer_requests', $requestId, 'OFFER_APPROVED', ['status' => 'PENDING'], ['status' => 'APPROVED', 'notice_id' => $noticeId], $admin->agent_id);

        return redirect()->route('admin.offer-requests.pending')->with('success', '"' . $offer->title . '" approved and posted to the Notice Board.');
    }

    public function reject(Request $request, string $requestId)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $admin = Auth::guard('agent')->user();
        $offer = DB::table('offer_requests')->where('request_id', $requestId)->where('status', 'PENDING')->firstOrFail();

        DB::table('offer_requests')->where('request_id', $requestId)->update([
            'status'              => 'REJECTED',
            'rejection_reason'    => $request->input('reason'),
            'reviewed_by_agent_id'=> $admin->agent_id,
            'reviewed_at'         => now(),
            'updated_at'          => now(),
        ]);

        \App\Services\AuditService::logChange('offer_requests', $requestId, 'OFFER_REJECTED', ['status' => 'PENDING'], ['status' => 'REJECTED', 'reason' => $request->input('reason')], $admin->agent_id);

        // NEW 8 Aug 2026 (Task #96) — notify the submitter. An agent
        // submitter gets the same in-app notifications row every other
        // GeneralLink notification uses; a vendor submitter (no in-app
        // inbox) gets a plain email instead — both are non-blocking, the
        // rejection itself is already saved either way.
        if ($offer->submitted_by_type === 'AGENT' && $offer->submitted_by_agent_id) {
            try {
                DB::table('notifications')->insert([
                    'notification_id'    => (string) Str::uuid(),
                    'recipient_agent_id' => $offer->submitted_by_agent_id,
                    'type'                => 'OFFER_REJECTED',
                    'title'               => 'Offer Submission Rejected',
                    'message'             => "Your submitted offer \"{$offer->title}\" was not approved — {$request->input('reason')}",
                    'related_agent_id'    => $admin->agent_id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('OfferRequestApprovalController: failed to notify agent submitter', ['request_id' => $requestId]);
            }
        } elseif ($offer->submitted_by_type === 'VENDOR' && $offer->submitted_by_vendor_id) {
            $vendorEmail = DB::table('vendors')->where('vendor_id', $offer->submitted_by_vendor_id)->value('vendor_email');
            if ($vendorEmail) {
                try {
                    Mail::html('<p>Your submitted offer "' . e($offer->title) . '" was not approved.</p><p><strong>Reason:</strong> ' . e($request->input('reason')) . '</p><p>You can sign in to the Vendor Portal to review or submit a new offer.</p>', function ($m) use ($vendorEmail, $offer) {
                        $m->to($vendorEmail)->subject('[GeneralLink] Offer Submission Rejected — ' . $offer->title);
                    });
                } catch (\Throwable $e) {
                    Log::warning('OfferRequestApprovalController: failed to email vendor submitter', ['request_id' => $requestId]);
                }
            }
        }

        return redirect()->route('admin.offer-requests.pending')->with('success', 'Offer rejected.');
    }

    public function pendingCount()
    {
        return response()->json(['count' => DB::table('offer_requests')->where('status', 'PENDING')->count()]);
    }
}
