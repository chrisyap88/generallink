<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\VendorFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 12 Aug 2026 — per Chris: "how admin communicate with new vendor
// before approval, means Q&A and the communication history log?" The
// vendor has no password/login before Admin approves them, so this pair
// of routes is deliberately public — access is controlled entirely by
// possessing the unguessable emailed token (vendors.qa_access_token),
// same trust model as the existing "verify email + set password" link
// (VendorAuthController::showSetPassword/setPassword). No session, no
// portal layout — a small standalone page, same visual family as the
// vendor login/registration screens.
class VendorPendingQaController extends Controller
{
    public function show(string $token)
    {
        $vendor = Vendor::where('qa_access_token', $token)->first();
        if (!$vendor) {
            abort(404, 'This link is no longer valid.');
        }

        $messages = DB::table('vendor_pending_messages')
            ->where('vendor_id', $vendor->vendor_id)
            ->orderBy('created_at')
            ->get();

        return view('vendor.pending-qa', compact('vendor', 'messages'));
    }

    public function reply(Request $request, string $token)
    {
        $vendor = Vendor::where('qa_access_token', $token)->first();
        if (!$vendor) {
            abort(404, 'This link is no longer valid.');
        }

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        // NEW 13 Aug 2026 — per Chris: "this communication tools allow
        // vendor to upload new file if require." A vendor can now attach
        // a document/photo to their reply — e.g. a corrected SSM
        // document or a screenshot of a fixed website URL — the same way
        // Admin can. The row-form amendment log itself (which specific
        // requested item this addresses) is still resolved by Admin
        // after reviewing the attachment, not auto-guessed here.
        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = VendorFileStorageService::store($request->file('attachment'), $vendor->vendor_id, $vendor->vendor_name, VendorFileStorageService::CATEGORY_QA_ATTACHMENT);
            $attachmentName = $request->file('attachment')->getClientOriginalName();
        }

        $hasOpenAmendment = DB::table('vendor_onboarding_amendments')->where('vendor_id', $vendor->vendor_id)->where('status', 'REQUESTED')->exists();

        DB::table('vendor_pending_messages')->insert([
            'message_id' => (string) \Illuminate\Support\Str::uuid(),
            'vendor_id' => $vendor->vendor_id,
            'sender_type' => 'VENDOR',
            'sender_admin_id' => null,
            'message_type' => ($attachmentPath && $hasOpenAmendment) ? 'AMENDMENT_SUBMITTED' : 'MESSAGE',
            'message' => $request->message,
            'attachment_path' => $attachmentPath,
            'attachment_file_name' => $attachmentName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('vendor.pending-qa.show', $token)->with('success', 'Your reply has been sent.');
    }

    /** Vendor-side download for a file attached to any thread message (e.g. a reference document Admin sent). */
    public function messageAttachment(string $token, string $messageId)
    {
        $vendor = Vendor::where('qa_access_token', $token)->first();
        if (!$vendor) {
            abort(404, 'This link is no longer valid.');
        }

        $message = DB::table('vendor_pending_messages')->where('message_id', $messageId)->where('vendor_id', $vendor->vendor_id)->first();
        if (!$message || !$message->attachment_path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($message->attachment_path)) {
            abort(404, 'Attachment not found.');
        }
        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($message->attachment_path));
    }
}
