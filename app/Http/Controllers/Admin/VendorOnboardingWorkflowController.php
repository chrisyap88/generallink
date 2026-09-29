<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\AuditService;
use App\Services\VendorFileStorageService;
use App\Services\VendorPendingQaMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 13 Aug 2026 — per Chris: "create another workflow program below
// pending approve in the menu dashboard AI Developer Instruction...
// Vendor Onboarding and Communication Workflow. This program manages
// the entire onboarding lifecycle, from registration through review to
// approval or rejection." This is that program's admin side: a list of
// every vendor currently moving through onboarding, and a per-vendor
// screen showing the full communication thread (now with file
// attachments both ways), a structured "row form" log of every
// amendment/document Admin has asked the vendor to fix or provide (per
// Chris: "this amendment must have keep track in row form... because in
// actual environment it may incur a series of communication"), and a
// place to raise a new amendment request.
//
// Deliberately reuses vendor_pending_messages (built 12 Aug 2026 for
// pre-approval Q&A) as the ONE communication backbone rather than a
// second, parallel ticket system — see the recommendation given to
// Chris. The Agreement (Vendor Registration Activation Agreement) /
// Email OTP acceptance section of this program is a later phase — not
// built yet.
class VendorOnboardingWorkflowController extends Controller
{
    // NEW 14 Aug 2026 — per Chris: "Amendment auto-updating final
    // registration copy Please build." Deliberately a short whitelist of
    // plain informational vendors columns only — never vendor_email
    // (that's the login identifier), never login_status/password/
    // approval columns, never anything security-sensitive. Keys are the
    // real column name; labels are what Admin sees in the dropdown.
    public const AMENDABLE_FIELDS = [
        'vendor_name' => 'Company Name',
        'second_name' => 'Company Name (Other Language)',
        'pic_name' => 'Contact 1 Name',
        'pic_designation' => 'Contact 1 Designation',
        'pic_phone' => 'Contact 1 Phone',
        'pic_email' => 'Contact 1 Email',
        'vendor_phone' => 'Business Phone',
        'vendor_state' => 'State',
        'nature_of_business' => 'Nature of Business',
        'fb_page_url' => 'Website / Facebook Page',
    ];

    public function index(Request $request)
    {
        // Every vendor whose onboarding is still "live" — awaiting
        // password setup, awaiting review, or recently decided — same
        // statuses the Pending Vendor Logins screen already works with,
        // just not filtered down to PENDING only, since this program's
        // job is the whole lifecycle, not just the approve/reject click.
        // CHANGED 13 Aug 2026 — Laravel's ->paginate() was returning a
        // plain Eloquent\Collection instead of a LengthAwarePaginator on
        // Chris's server (BadMethodCallException: onPreviousPage does not
        // exist), even after clearing every Laravel cache. Rather than
        // keep guessing at an environment-specific cause I can't
        // reproduce here, paging is now done by hand: fetch everything,
        // slice it in PHP, and hand the view plain values instead of a
        // paginator object it has to call methods on.
        // CHANGED AGAIN 13 Aug 2026 — per Chris: "incorporate prev and
        // next... all information in one screen no scroll." 10 rows of
        // this table (2-line Communication column) didn't reliably fit
        // without the inner box scrolling; dropped to 5/page, same
        // density already proven on Pending Vendor Logins.
        $perPage = 5;
        $page = max(1, (int) $request->query('page', 1));

        $allVendors = Vendor::where(function ($outer) {
            $outer->whereIn('login_status', ['PENDING', 'AWAITING_PASSWORD', 'RESTRICTED', 'REJECTED'])
                ->orWhere(function ($q) {
                    $q->where('login_status', 'ACTIVE')->where('approved_at_2', '>=', now()->subDays(14))
                        ->orWhere(function ($q2) { $q2->where('login_status', 'ACTIVE')->whereNull('approved_at_2')->where('approved_at_1', '>=', now()->subDays(14)); });
                });
        })
            ->orderByDesc('created_at')
            ->get();

        $lastPage = max(1, (int) ceil($allVendors->count() / $perPage));
        $page = min($page, $lastPage);
        $vendors = $allVendors->slice(($page - 1) * $perPage, $perPage)->values();

        $vendorsMeta = [
            'currentPage'    => $page,
            'lastPage'       => $lastPage,
            'hasMorePages'   => $page < $lastPage,
            'onPreviousPage' => $page > 1,
            'prevUrl'        => $page > 1 ? route('admin.vendors.onboarding-workflow', ['page' => $page - 1]) : null,
            'nextUrl'        => $page < $lastPage ? route('admin.vendors.onboarding-workflow', ['page' => $page + 1]) : null,
        ];

        $vendorIds = $vendors->pluck('vendor_id');

        $lastActivity = DB::table('vendor_pending_messages')
            ->whereIn('vendor_id', $vendorIds)
            ->selectRaw('vendor_id, MAX(created_at) as last_at, COUNT(*) as message_count')
            ->groupBy('vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $openAmendments = DB::table('vendor_onboarding_amendments')
            ->whereIn('vendor_id', $vendorIds)
            ->where('status', '!=', 'RESOLVED')
            ->selectRaw('vendor_id, COUNT(*) as open_count')
            ->groupBy('vendor_id')
            ->pluck('open_count', 'vendor_id');

        return view('admin.vendors.onboarding-workflow', compact('vendors', 'vendorsMeta', 'lastActivity', 'openAmendments'));
    }

    public function show(string $vendorId, Request $request)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();

        // CHANGED 13 Aug 2026 per Chris: "no scroll" — Communication
        // Thread used to load every message and let the panel itself
        // scroll internally. Same fix as Help Desk's own thread view:
        // paginated (3 messages/page, oldest-first within a page), same
        // bottom Prev/Next bar, defaults to the LAST page (most recent
        // messages) so opening this screen shows what just happened.
        $owMessagesPerPage = 3;
        $owTotalMessages = DB::table('vendor_pending_messages')->where('vendor_id', $vendorId)->count();
        $owLastPage = max(1, (int) ceil($owTotalMessages / $owMessagesPerPage));
        $owPage = (int) $request->query('page', $owLastPage);
        if ($owPage < 1) { $owPage = 1; }
        if ($owPage > $owLastPage) { $owPage = $owLastPage; }

        $thread = DB::table('vendor_pending_messages')->where('vendor_id', $vendorId)->orderBy('created_at')->forPage($owPage, $owMessagesPerPage)->get();
        $adminIds = $thread->pluck('sender_admin_id')->filter()->unique()->values();
        $adminNames = $adminIds->isEmpty() ? collect() : DB::table('agents')->whereIn('agent_id', $adminIds)->pluck('full_name', 'agent_id');

        $amendments = DB::table('vendor_onboarding_amendments')->where('vendor_id', $vendorId)->orderByDesc('created_at')->get();
        $amendAdminIds = $amendments->pluck('requested_by_admin_id')->merge($amendments->pluck('resolved_by_admin_id'))->filter()->unique()->values();
        $amendAdminNames = $amendAdminIds->isEmpty() ? collect() : DB::table('agents')->whereIn('agent_id', $amendAdminIds)->pluck('full_name', 'agent_id');

        return view('admin.vendors.onboarding-workflow-show', compact('vendor', 'thread', 'adminNames', 'amendments', 'amendAdminNames', 'owPage', 'owLastPage', 'owTotalMessages'));
    }

    /** Admin sends a plain message (optionally with a file attached) into the same thread the Pending Vendor Logins Q&A already uses. */
    public function sendMessage(Request $request, string $vendorId, VendorPendingQaMailService $mailer)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;
        $messageId = (string) Str::uuid();

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = VendorFileStorageService::store($request->file('attachment'), $vendor->vendor_id, $vendor->vendor_name, VendorFileStorageService::CATEGORY_QA_ATTACHMENT);
            $attachmentName = $request->file('attachment')->getClientOriginalName();
        }

        DB::table('vendor_pending_messages')->insert([
            'message_id' => $messageId,
            'vendor_id' => $vendor->vendor_id,
            'sender_type' => 'ADMIN',
            'sender_admin_id' => $adminId,
            'message_type' => 'MESSAGE',
            'message' => $request->message,
            'attachment_path' => $attachmentPath,
            'attachment_file_name' => $attachmentName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer->notify($vendor, $request->message);
        AuditService::logChange('vendor_pending_messages', $messageId, 'VENDOR_QA_MESSAGE_SENT', null, ['vendor_id' => $vendor->vendor_id, 'sender_type' => 'ADMIN'], $adminId);

        return redirect()->route('admin.vendors.onboarding-workflow.show', $vendorId)->with('success', 'Message sent to ' . $vendor->vendor_name . ' (and emailed to them).');
    }

    /**
     * NEW 13 Aug 2026 — the structured "row form" amendment log Chris
     * asked for: raising one specific, trackable request (e.g. "Business
     * Registration Certificate — please upload a clearer copy") both
     * posts a normal thread message (so the vendor sees it the same way
     * as any other message, and gets the same email) AND creates its own
     * row in vendor_onboarding_amendments with status REQUESTED, so the
     * admin's workflow screen can show a clean open-items table instead
     * of having to re-read the whole conversation.
     */
    public function requestAmendment(Request $request, string $vendorId, VendorPendingQaMailService $mailer)
    {
        $request->validate([
            'item_label' => ['required', 'string', 'max:150'],
            'request_note' => ['required', 'string', 'max:2000'],
            // NEW 14 Aug 2026 — optional: which real vendor field this
            // amendment corrects, from the whitelist only. Left blank for
            // document-type amendments (SSM re-upload etc.), which still
            // resolve the old way (Accept the file, then Mark Resolved).
            'target_field' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::AMENDABLE_FIELDS))],
        ]);

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;
        $messageId = (string) Str::uuid();
        $amendmentId = (string) Str::uuid();

        $messageText = 'Amendment requested — ' . $request->item_label . ': ' . $request->request_note;

        DB::table('vendor_pending_messages')->insert([
            'message_id' => $messageId,
            'vendor_id' => $vendor->vendor_id,
            'sender_type' => 'ADMIN',
            'sender_admin_id' => $adminId,
            'message_type' => 'AMENDMENT_REQUEST',
            'message' => $messageText,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendor_onboarding_amendments')->insert([
            'amendment_id' => $amendmentId,
            'vendor_id' => $vendor->vendor_id,
            'message_id' => $messageId,
            'requested_by_admin_id' => $adminId,
            'item_label' => $request->item_label,
            'request_note' => $request->request_note,
            'target_field' => $request->target_field ?: null,
            'status' => 'REQUESTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer->notify($vendor, $messageText);
        AuditService::logChange('vendor_onboarding_amendments', $amendmentId, 'VENDOR_AMENDMENT_REQUESTED', null, ['vendor_id' => $vendor->vendor_id, 'item_label' => $request->item_label, 'target_field' => $request->target_field], $adminId);

        return redirect()->route('admin.vendors.onboarding-workflow.show', $vendorId)->with('success', 'Amendment request sent to ' . $vendor->vendor_name . ' and added to the tracking log.');
    }

    /** Admin marks an amendment row resolved directly (e.g. they verified the fix some other way, not only via a vendor thread reply) — no field to apply, or they chose not to apply one. */
    public function resolveAmendment(string $vendorId, string $amendmentId)
    {
        $adminId = auth('agent')->user()->agent_id;
        $amendment = DB::table('vendor_onboarding_amendments')->where('amendment_id', $amendmentId)->where('vendor_id', $vendorId)->first();
        if (!$amendment) {
            abort(404, 'Amendment not found.');
        }

        DB::table('vendor_onboarding_amendments')->where('amendment_id', $amendmentId)->update([
            'status' => 'RESOLVED',
            'resolved_by_admin_id' => $adminId,
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        AuditService::logChange('vendor_onboarding_amendments', $amendmentId, 'VENDOR_AMENDMENT_RESOLVED', ['status' => $amendment->status], ['status' => 'RESOLVED'], $adminId);

        return redirect()->route('admin.vendors.onboarding-workflow.show', $vendorId)->with('success', 'Amendment marked resolved.');
    }

    /**
     * NEW 14 Aug 2026 — per Chris: "Amendment auto-updating final
     * registration copy Please build." One click applies Admin's typed
     * corrected value directly to the vendor's real record (the "final
     * copy") AND marks the amendment resolved — instead of Admin having
     * to separately go retype it in Master File > Vendors. Only ever
     * touches the single whitelisted column this amendment was raised
     * against; every other field on the vendor row is untouched. Records
     * a full before/after via AuditService, same as every other field
     * edit in this app, so the change is traceable back to this
     * amendment and this Admin.
     */
    public function applyAmendment(Request $request, string $vendorId, string $amendmentId)
    {
        $request->validate(['new_value' => ['required', 'string', 'max:255']]);

        $adminId = auth('agent')->user()->agent_id;
        $amendment = DB::table('vendor_onboarding_amendments')->where('amendment_id', $amendmentId)->where('vendor_id', $vendorId)->first();
        if (!$amendment) {
            abort(404, 'Amendment not found.');
        }
        if (!$amendment->target_field || !array_key_exists($amendment->target_field, self::AMENDABLE_FIELDS)) {
            return back()->with('error', 'This amendment was not raised against a specific field, so there is nothing to auto-apply — use Mark Resolved instead.');
        }

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $field = $amendment->target_field;
        $oldValue = $vendor->{$field};

        $vendor->update([$field => $request->new_value]);

        DB::table('vendor_onboarding_amendments')->where('amendment_id', $amendmentId)->update([
            'applied_value' => $request->new_value,
            'applied_at' => now(),
            'applied_by_admin_id' => $adminId,
            'status' => 'RESOLVED',
            'resolved_by_admin_id' => $adminId,
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_AMENDMENT_APPLIED', [$field => $oldValue], [$field => $request->new_value], $adminId);
        AuditService::logChange('vendor_onboarding_amendments', $amendmentId, 'VENDOR_AMENDMENT_RESOLVED', ['status' => $amendment->status], ['status' => 'RESOLVED', 'applied_value' => $request->new_value], $adminId);

        return redirect()->route('admin.vendors.onboarding-workflow.show', $vendorId)->with('success', self::AMENDABLE_FIELDS[$field] . ' updated on ' . $vendor->vendor_name . '\'s registration, and the amendment marked resolved.');
    }

    /** Admin-side download for a file attached to any thread message. */
    public function messageAttachment(string $vendorId, string $messageId)
    {
        $message = DB::table('vendor_pending_messages')->where('message_id', $messageId)->where('vendor_id', $vendorId)->first();
        if (!$message || !$message->attachment_path || !Storage::disk('local')->exists($message->attachment_path)) {
            abort(404, 'Attachment not found.');
        }
        return response()->file(Storage::disk('local')->path($message->attachment_path));
    }
}
