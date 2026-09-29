<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\VendorFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 13 Aug 2026 — the vendor-facing half of the restricted-access
// design: a RESTRICTED-status vendor (mandatory documents verified,
// password set, awaiting Admin's final approval) can now log in for
// real and see this ONE screen — status, the same communication thread
// as the emailed token-link page and the admin-side Vendor Onboarding
// Workflow, and file upload — instead of only ever reaching the app
// through an emailed link. The token-link page (VendorPendingQaController)
// still works too (e.g. if the vendor loses their password mid-review),
// both read/write the exact same vendor_pending_messages rows.
class VendorApplicationStatusController extends Controller
{
    public function show()
    {
        $vendor = Auth::guard('vendor')->user();

        $thread = DB::table('vendor_pending_messages')->where('vendor_id', $vendor->vendor_id)->orderBy('created_at')->get();
        $adminIds = $thread->pluck('sender_admin_id')->filter()->unique()->values();
        $adminNames = $adminIds->isEmpty() ? collect() : DB::table('agents')->whereIn('agent_id', $adminIds)->pluck('full_name', 'agent_id');

        $amendments = DB::table('vendor_onboarding_amendments')->where('vendor_id', $vendor->vendor_id)->where('status', '!=', 'RESOLVED')->orderByDesc('created_at')->get();

        return view('vendor.application-status', compact('vendor', 'thread', 'adminNames', 'amendments'));
    }

    public function sendMessage(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = VendorFileStorageService::store($request->file('attachment'), $vendor->vendor_id, $vendor->vendor_name, VendorFileStorageService::CATEGORY_QA_ATTACHMENT);
            $attachmentName = $request->file('attachment')->getClientOriginalName();
        }

        $hasOpenAmendment = DB::table('vendor_onboarding_amendments')->where('vendor_id', $vendor->vendor_id)->where('status', 'REQUESTED')->exists();

        DB::table('vendor_pending_messages')->insert([
            'message_id' => (string) Str::uuid(),
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

        return redirect()->route('vendor.application-status')->with('success', 'Your message has been sent.');
    }

    public function messageAttachment(string $messageId)
    {
        $vendor = Auth::guard('vendor')->user();

        $message = DB::table('vendor_pending_messages')->where('message_id', $messageId)->where('vendor_id', $vendor->vendor_id)->first();
        if (!$message || !$message->attachment_path || !Storage::disk('local')->exists($message->attachment_path)) {
            abort(404, 'Attachment not found.');
        }
        return response()->file(Storage::disk('local')->path($message->attachment_path));
    }
}
