<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\VendorDocumentChecklistService;
use App\Services\VendorDueDiligenceService;
use App\Services\VendorFileStorageService;
use App\Services\VendorPendingQaMailService;
use App\Services\VendorVerificationMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// NEW 8 Aug 2026 (Task #92, REDESIGNED same day per Chris) — Admin
// approval queue for vendor self-registrations. login_status PENDING
// means "documents submitted, awaiting Admin's identity review" — Admin
// must open/check the SSM certificate and company profile document (or
// Facebook page link) before approving. Approve does NOT activate the
// login directly anymore — it sends a "verify email + set password"
// link (see VendorVerificationMailService) and moves the vendor to
// AWAITING_PASSWORD. Same shape as Admin\PendingAssignmentController
// (agent pending assignment) and ApprovalController::reject()
// (reason-required rejection).
class VendorLoginApprovalController extends Controller
{
    // NEW 8 Aug 2026 — Vendor Management Phase 1. How many Admins must
    // approve a vendor before it's genuinely approved (spec Section 2:
    // "two-person approval must be supported"). Read from system_settings
    // (same table/pattern as the Risk Review Queue's threshold settings)
    // instead of hard-coded, so raising it from 1 to 2 once a second Admin
    // exists needs no code change.
    public static function requiredApprovals(): int
    {
        $val = (int) (DB::table('system_settings')->where('setting_key', 'vendor_approval_required_count')->value('setting_value') ?? 1);
        return in_array($val, [1, 2], true) ? $val : 1;
    }

    // NEW 14 Aug 2026 — per Chris: "explain about the fees." Kept in ONE
    // place so the Approval Letter can never drift from the real
    // commercial terms. These MUST match GLADE-Vendor-Registration-
    // Activation-Agreement-DRAFT.pdf (Clause 2) exactly — that document,
    // not this constant, is the one Chris said he already gave and
    // should be treated as the source of truth; update both together if
    // the fees ever change.
    public const ACTIVATION_FEE = 300.00;
    public const ANNUAL_FEE = 1200.00;

    // Path to the vendor-facing agreement Chris already prepared and
    // confirmed exists in the project root — reused as-is rather than
    // drafting a new one, per his "if i not mistaken i given you before
    // please check attached" instruction.
    public const AGREEMENT_FILE = 'GLADE-Vendor-Registration-Activation-Agreement-DRAFT.pdf';

    public function index()
    {
        // FIXED 8 Aug 2026 — Vendor Management Phase 1 audit: this used to
        // load every pending vendor into one internally-scrolling box
        // (overflow-y:auto), the exact "unbounded list + inner scroll"
        // pattern removed from every Communication & Action Center screen
        // earlier today. Paginated (5/page) with the same bottom blue
        // Prev/Next bar used everywhere else instead.
        //
        // TRIMMED 12 Aug 2026 (Task #106) — per Chris: "remove all popup
        // content, new screen with proper tab." "View Details" no longer
        // opens an in-page modal built from data preloaded here — it
        // navigates to show() below, which loads everything for ONE
        // vendor on demand. This list screen only needs enough to render
        // its own rows: the vendor list itself, and the latest Due
        // Diligence result per vendor for the small risk-band dot.
        // CHANGED 13 Aug 2026 twice per Chris. First: a vendor moves
        // PENDING -> RESTRICTED once documents are verified but is still
        // awaiting Admin's final Approve click, so RESTRICTED must stay
        // visible here. Then Chris pushed back further: "why you dont
        // show the pending approval records again? you have to show as
        // long as long not approved" — so AWAITING_PASSWORD is included
        // too now. Approve genuinely can't act on AWAITING_PASSWORD yet
        // (vendor hasn't set a password), so that row shows an info note
        // instead of an Approve button on the detail screen — but Chris
        // wants it visible in this list regardless, not hidden until it
        // becomes actionable.
        $pendingVendors = Vendor::whereIn('login_status', ['PENDING', 'AWAITING_PASSWORD', 'RESTRICTED'])->orderBy('created_at')->paginate(5);

        $vendorIds = collect($pendingVendors->items())->pluck('vendor_id');
        $assessments = $vendorIds->isEmpty() ? collect() : DB::table('vendor_due_diligence_assessments')
            ->whereIn('vendor_id', $vendorIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('vendor_id')
            ->map(fn ($rows) => $rows->first());

        return view('admin.vendors.pending-logins', compact('pendingVendors', 'assessments'));
    }

    /**
     * NEW 12 Aug 2026 (Task #106) — per Chris: "you still have this
     * stupid pop up window... remove all popup content, new screen with
     * proper tab." Everything that used to live inside the "View
     * Details" modal (identity/approval-progress header, Approve/Reject
     * actions, and the Profile/SSM Documents/Video-URL-Slideshow tabs)
     * now renders on its own real page. The three tabs stay as an
     * in-page tab bar (that part was never the problem — the popup
     * overlay was); Q&A and Forward to Director move to their own pages
     * too (see qa()/forwardDirectorForm() below); Risk Assessment
     * Results already got this treatment first (see riskAssessment()).
     */
    public function show(string $vendorId)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $dd = DB::table('vendor_due_diligence_assessments')->where('vendor_id', $vendorId)->orderByDesc('created_at')->first();
        $vDocs = DB::table('vendor_documents')->where('vendor_id', $vendorId)->orderBy('created_at')->get();
        $vMedia = DB::table('vendor_registration_media')->where('vendor_id', $vendorId)->orderBy('created_at')->get();
        $vThreadCount = DB::table('vendor_pending_messages')->where('vendor_id', $vendorId)->count();
        // NEW 14 Aug 2026 — per Chris's OTP-acceptance build: lets Admin
        // see, without leaving this screen, whether the vendor has
        // actually digitally accepted the Activation Agreement yet.
        $agreementAcceptance = DB::table('vendor_agreement_acceptances')->where('vendor_id', $vendorId)->first();

        $vRequired = $vendor->entity_type ? VendorDocumentChecklistService::requiredKeys($vendor->entity_type) : [];
        // NEW 13 Aug 2026 — per Chris: "should have 3 ssm why show 2 all
        // must show." The SSM Documents tab used to only render a row for
        // documents actually uploaded, so a required checklist item the
        // vendor never uploaded silently vanished instead of showing as
        // outstanding. This is the FULL checklist for the vendor's entity
        // type (mandatory/conditional/optional, in the defined order) so
        // the view can render one row per required item — uploaded or not.
        $vChecklist = $vendor->entity_type ? VendorDocumentChecklistService::checklist($vendor->entity_type) : [];
        $vVerifiedKeys = $vDocs->where('verification_status', 'VERIFIED')->pluck('document_key')->all();
        $vVerifiedCount = collect($vRequired)->filter(function ($groupKey) use ($vVerifiedKeys) {
            foreach ($vVerifiedKeys as $vk) {
                if (VendorDocumentChecklistService::keySatisfiesGroup($vk, $groupKey)) {
                    return true;
                }
            }
            return false;
        })->count();
        // CHANGED 13 Aug 2026 — was ->first() on $vDocs (ordered oldest
        // first), which picked the OLDEST profile document ever uploaded
        // instead of the current one whenever it had been re-uploaded.
        // Sort newest-first before picking so Profile always shows the
        // latest version.
        $profileDoc = $vDocs->sortByDesc('created_at')->first(fn ($d) => str_contains($d->document_key, 'profile'));

        $requiredApprovals = self::requiredApprovals();
        $currentAdminId = auth('agent')->user()->agent_id;
        $approverName = $vendor->approved_by_1 ? DB::table('agents')->where('agent_id', $vendor->approved_by_1)->value('full_name') : null;

        $priorApplications = $this->findPriorApplications($vendor);

        // NEW 14 Aug 2026 (3rd pass) — per Chris's SOP: "only again Admin
        // director can resend." Needed here so the Resend button can show
        // only once the letter has genuinely been sent before, and only
        // to a Director Admin.
        $welcomeGate = $this->welcomeLetterGate($vendorId);
        $myDepartment = auth('agent')->user()->department;

        return view('admin.vendors.pending-login-detail', compact('vendor', 'dd', 'vDocs', 'vMedia', 'vThreadCount', 'vRequired', 'vChecklist', 'vVerifiedCount', 'profileDoc', 'requiredApprovals', 'currentAdminId', 'approverName', 'priorApplications', 'agreementAcceptance', 'welcomeGate', 'myDepartment'));
    }

    /**
     * NEW 14 Aug 2026 — per Chris: "All new registration submission with
     * attachment and registration must keep a copy even if is not
     * approved. this will form a reference record for future reference
     * if there is similar application submit to admin again." Rejecting
     * a vendor (reject() below) already only ever flips login_status —
     * it has never deleted the vendor row or any of their uploaded
     * documents, so nothing needed to change there for retention itself.
     * This is the other half: actually surfacing that history. Matches
     * any OTHER vendor row (any status, including REJECTED) sharing the
     * same email, phone, or a near-identical company name — deliberately
     * simple exact/near matching, not fuzzy AI matching, so it never
     * misses a real match by being too clever, and never falsely accuses
     * an unrelated company of being a repeat applicant.
     */
    private function findPriorApplications(Vendor $vendor)
    {
        return Vendor::where('vendor_id', '!=', $vendor->vendor_id)
            ->where(function ($q) use ($vendor) {
                $q->where('vendor_email', $vendor->vendor_email)
                    ->orWhere('vendor_phone', $vendor->vendor_phone)
                    ->orWhereRaw('LOWER(vendor_name) = ?', [mb_strtolower(trim($vendor->vendor_name))]);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['vendor_id', 'vendor_name', 'login_status', 'rejection_reason', 'created_at']);
    }

    /**
     * NEW 14 Aug 2026 — per Chris: "i dont want Approve reject header
     * [on Vendor Registration Detail]... separate program in menu
     * dashboard after workflow." Vendor Approvals is that separate
     * program: same underlying queue as Pending Vendor Logins (index()
     * above), but this list exists purely to get to the action screen
     * (approvalsShow() below) — Registration Detail (show() above) is
     * now document-viewing only, no actions.
     */
    public function approvalsIndex()
    {
        $pendingVendors = Vendor::whereIn('login_status', ['PENDING', 'AWAITING_PASSWORD', 'RESTRICTED'])->orderBy('created_at')->paginate(5);

        $vendorIds = collect($pendingVendors->items())->pluck('vendor_id');
        $assessments = $vendorIds->isEmpty() ? collect() : DB::table('vendor_due_diligence_assessments')
            ->whereIn('vendor_id', $vendorIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('vendor_id')
            ->map(fn ($rows) => $rows->first());

        return view('admin.vendors.approvals', compact('pendingVendors', 'assessments'));
    }

    /** NEW 14 Aug 2026 — the action screen itself: identity summary + risk score + Approve/Reject/Q&A/Forward-to-Director, exactly what used to sit on top of pending-login-detail.blade.php. */
    public function approvalsShow(string $vendorId)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $dd = DB::table('vendor_due_diligence_assessments')->where('vendor_id', $vendorId)->orderByDesc('created_at')->first();
        $vThreadCount = DB::table('vendor_pending_messages')->where('vendor_id', $vendorId)->count();
        $requiredApprovals = self::requiredApprovals();
        $currentAdminId = auth('agent')->user()->agent_id;
        $approverName = $vendor->approved_by_1 ? DB::table('agents')->where('agent_id', $vendor->approved_by_1)->value('full_name') : null;

        // Same "prior applications on file" reference check surfaced here
        // too — see priorApplications() below — so Admin sees it at the
        // moment they're about to decide, not only buried in the profile.
        $priorApplications = $this->findPriorApplications($vendor);

        return view('admin.vendors.approvals-show', compact('vendor', 'dd', 'vThreadCount', 'requiredApprovals', 'currentAdminId', 'approverName', 'priorApplications'));
    }

    /**
     * NEW 14 Aug 2026 — per Chris: "when click approved green it will
     * show like a payment voucher form with the proper professional
     * cover letter." Read-only preview of the welcome/approval letter
     * that will actually be emailed once Admin confirms — nothing is
     * approved yet just by opening this screen. Reuses the existing
     * approve() route/logic for the real action (the letter's "Confirm &
     * Send Approval" button posts straight to it), so there is exactly
     * one place that decides whether a vendor may be approved.
     */
    public function approvalLetter(string $vendorId)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->whereIn('login_status', ['PENDING', 'RESTRICTED'])->firstOrFail();
        $requiredApprovals = self::requiredApprovals();
        $currentAdminId = auth('agent')->user()->agent_id;
        $isSecondApproval = $requiredApprovals === 2 && $vendor->approved_at_1 && $vendor->approved_by_1 !== $currentAdminId;

        // NEW 14 Aug 2026 — per Chris: "show the approval button for
        // sales admin finance admin and director admin, tick box." The
        // sign-off gate for THIS vendor's Welcome Letter — separate from
        // (and in addition to) the requiredApprovals() counter above,
        // which is untouched.
        //
        // CHANGED 14 Aug 2026 (2nd pass) — per Chris: "the sales admin or
        // finance admin either one approve then the admin director
        // approve, not the director admin approve first." Not a flat
        // 3-of-3 requirement — it's two stages: (Sales OR Finance) first,
        // then Director. See gateStage1Done()/gateComplete() below.
        $gate = $this->welcomeLetterGate($vendorId);
        $gateApproverIds = collect([$gate->sales_approved_by, $gate->finance_approved_by, $gate->director_approved_by])->filter();
        $gateNames = $gateApproverIds->isEmpty() ? collect() : DB::table('agents')->whereIn('agent_id', $gateApproverIds)->pluck('full_name', 'agent_id');
        $gateComplete = $this->gateComplete($gate);
        $myDepartment = auth('agent')->user()->department;

        return view('admin.vendors.approval-letter', compact('vendor', 'requiredApprovals', 'currentAdminId', 'isSecondApproval', 'gate', 'gateNames', 'gateComplete', 'myDepartment'));
    }

    /**
     * NEW 14 Aug 2026 — returns the vendor_welcome_letter_approvals row
     * for this vendor, creating it (all three slots empty) the first
     * time this vendor's Approval Letter screen is opened.
     */
    private function welcomeLetterGate(string $vendorId): object
    {
        $row = DB::table('vendor_welcome_letter_approvals')->where('vendor_id', $vendorId)->first();
        if ($row) {
            return $row;
        }
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('vendor_welcome_letter_approvals')->insert([
            'id' => $id, 'vendor_id' => $vendorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('vendor_welcome_letter_approvals')->where('id', $id)->first();
    }

    /**
     * CHANGED 14 Aug 2026 (2nd pass) — per Chris: "the sales admin or
     * finance admin either one approve then the admin director approve,
     * not the director admin approve first." Stage 1 is satisfied by
     * EITHER Sales or Finance (only one of the two is required, not
     * both) — never Director.
     */
    private function gateStage1Done(object $gate): bool
    {
        return (bool) ($gate->sales_approved_at || $gate->finance_approved_at);
    }

    /** NEW 14 Aug 2026 (2nd pass) — the full gate: Stage 1 (Sales or Finance) AND then Director. */
    private function gateComplete(object $gate): bool
    {
        return $this->gateStage1Done($gate) && (bool) $gate->director_approved_at;
    }

    /**
     * CHANGED 14 Aug 2026 (2nd pass) — one of the required sign-offs for
     * this vendor's Welcome Letter. Each department can ONLY sign its
     * own slot now (the earlier "Director can approve any slot as
     * universal fallback" rule is removed — it would have let Director
     * short-circuit Stage 1, which is exactly what Chris said not to
     * allow). Director's slot is locked until Stage 1 is done; once
     * either Sales or Finance has signed, the OTHER of the two is no
     * longer needed and is blocked from also signing, to keep "what's
     * still outstanding" unambiguous. Cannot be un-ticked from here once
     * given — same "corrections happen via a new request, not by
     * editing history" pattern as the rest of the app.
     */
    public function approveDepartment(string $vendorId, string $department)
    {
        if (!in_array($department, ['SALES', 'FINANCE', 'DIRECTOR'], true)) {
            abort(404);
        }

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $admin = auth('agent')->user();
        $gate = $this->welcomeLetterGate($vendorId);

        if ($department === 'DIRECTOR' && !$this->gateStage1Done($gate)) {
            return back()->with('error', 'Sales Admin or Finance Admin must approve first — Director Admin cannot sign off before that.');
        }

        if (in_array($department, ['SALES', 'FINANCE'], true) && $this->gateStage1Done($gate)) {
            return back()->with('error', 'Sign-off already satisfied by ' . ($gate->sales_approved_at ? 'Sales' : 'Finance') . ' Admin — no further action needed here; Director Admin can proceed.');
        }

        if ($admin->department !== $department) {
            return back()->with('error', 'Only ' . ucfirst(strtolower($department)) . ' Admin can give this sign-off.');
        }

        $col = strtolower($department) . '_approved_by';
        $atCol = strtolower($department) . '_approved_at';

        if ($gate->$atCol) {
            return back()->with('error', ucfirst(strtolower($department)) . ' Admin sign-off was already given for ' . $vendor->vendor_name . '.');
        }

        DB::table('vendor_welcome_letter_approvals')->where('vendor_id', $vendorId)->update([
            $col => $admin->agent_id,
            $atCol => now(),
            'updated_at' => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_welcome_letter_approvals', $gate->id, 'WELCOME_LETTER_' . $department . '_SIGNOFF', null, ['vendor_id' => $vendorId, 'by' => $admin->agent_id], $admin->agent_id);

        // CHANGED 14 Aug 2026 (4th pass) — the "Director's tick auto-sends"
        // version (3rd pass) had a real bug: once the tick was recorded,
        // the button disappeared, but if the actual send/finalize step
        // failed to fire in that same request for any reason, there was
        // no way to retry — the screen just showed "done" with nothing
        // left to click, while the vendor stayed stuck un-finalized. Back
        // to two distinct steps: this just records the sign-off (for
        // ALL three departments, including Director, the same way).
        // Sending is its own explicit action below (see the letter
        // screen's footer button), restricted to Director only — matches
        // Chris's SOP just as well ("only admin director can send") but
        // is retry-safe: the button stays available for Director to
        // click for as long as the vendor isn't finalized yet, no matter
        // how many times the page is reloaded.
        return redirect()->route('admin.vendors.approvals.letter', $vendorId)->with('success', ucfirst(strtolower($department)) . ' Admin sign-off recorded for ' . $vendor->vendor_name . '.');
    }

    /**
     * NEW 14 Aug 2026 (3rd pass) — per Chris's SOP: "if chrisyap@mybbs.com
     * say didnt receive it is only again Admin director can resend." Only
     * usable once the letter has genuinely been sent before (gate's
     * director_approved_at is set) — re-sends the exact same letter
     * content, doesn't touch any approval state. Director-only, same
     * department check as the original send.
     */
    public function resendWelcomeLetter(string $vendorId)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $admin = auth('agent')->user();

        if ($admin->department !== 'DIRECTOR') {
            return back()->with('error', 'Only Director Admin can resend the Welcome Letter.');
        }

        $gate = $this->welcomeLetterGate($vendorId);
        if (!$gate->director_approved_at) {
            return back()->with('error', 'The Welcome Letter has not been sent yet for ' . $vendor->vendor_name . ' — nothing to resend.');
        }

        $this->sendApprovalWelcomeLetter($vendor);

        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'WELCOME_LETTER_RESENT', null, ['vendor_id' => $vendor->vendor_id, 'resent_by' => $admin->agent_id], $admin->agent_id);

        return back()->with('success', 'Welcome Letter resent to ' . $vendor->vendor_name . '.');
    }

    /** NEW 14 Aug 2026 — serves the actual agreement PDF Chris already prepared, both for the letter preview's "View Agreement" link and as the email attachment in sendApprovalWelcomeLetter() below. */
    public function agreementFile()
    {
        $path = base_path(self::AGREEMENT_FILE);
        if (!file_exists($path)) {
            abort(404, 'Agreement file not found on server.');
        }
        return response()->file($path);
    }

    /**
     * NEW 14 Aug 2026 — per Chris's approval-letter brief: the actual
     * email, sent once approve() (below) has genuinely finalized the
     * vendor to ACTIVE — never before, so a partial (1-of-2) approval
     * never sends a premature "welcome" email. To: Contact 1 (or the
     * vendor's main email if no Contact 1 email on file); CC: Contact 2
     * and Contact 3 where present. Follows the same raw Mail::html()
     * pattern already used by forwardToDirector() — no separate Mailable
     * class needed for one screen's worth of email.
     *
     * Login credentials note: per Chris's brief this should show "login
     * email address and temporary login password" — but by the time a
     * vendor reaches this final Approve step they have ALREADY set their
     * own password (VendorAuthController::setPassword(), during the
     * AWAITING_PASSWORD -> RESTRICTED step earlier in the flow). There is
     * no temporary password left to disclose at this point without
     * undermining that self-set-password security design — so this shows
     * the login email plus a plain instruction to use the password they
     * already chose (or Forgot Password), instead of fabricating or
     * resetting one. Flagged to Chris in chat; happy to change the
     * earlier flow to issue a real temporary password instead, if that's
     * actually what he wants.
     */
    private function sendApprovalWelcomeLetter(Vendor $vendor): void
    {
        $toEmail = $vendor->pic_email ?: $vendor->vendor_email;
        $toName = $vendor->pic_name ?: $vendor->vendor_name;
        $cc = array_values(array_filter([$vendor->contact2_email ?? null, $vendor->contact3_email ?? null]));

        $subject = 'Approval of Your GLADE Vendor Registration — Welcome to the GLADE Family';
        $html = view('admin.vendors.approval-letter-email', ['vendor' => $vendor, 'toName' => $toName])->render();

        try {
            \Illuminate\Support\Facades\Mail::html($html, function ($mail) use ($vendor, $toEmail, $toName, $cc, $subject) {
                $mail->to($toEmail, $toName)->subject($subject);
                if (!empty($cc)) {
                    $mail->cc($cc);
                }
                $agreementPath = base_path(self::AGREEMENT_FILE);
                if (file_exists($agreementPath)) {
                    $mail->attach($agreementPath, ['as' => 'GLADE Vendor Registration Activation Agreement.pdf']);
                }
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('VendorLoginApprovalController::sendApprovalWelcomeLetter mail failed: ' . $e->getMessage());
        }
    }

    /** NEW 12 Aug 2026 (Task #106) — Q&A thread, its own screen now instead of a modal launched from show(). Posting (sendQaMessage() below) is unchanged. */
    public function qa(string $vendorId)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $thread = DB::table('vendor_pending_messages')->where('vendor_id', $vendorId)->orderBy('created_at')->get();
        $adminIds = $thread->pluck('sender_admin_id')->filter()->unique()->values();
        $adminNames = $adminIds->isEmpty() ? collect() : DB::table('agents')->whereIn('agent_id', $adminIds)->pluck('full_name', 'agent_id');

        return view('admin.vendors.pending-login-qa', compact('vendor', 'thread', 'adminNames'));
    }

    /** NEW 12 Aug 2026 (Task #106) — Forward to Director form, its own screen now instead of a modal launched from show(). Sending (forwardToDirector() below) is unchanged. */
    public function forwardDirectorForm(string $vendorId)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $dd = DB::table('vendor_due_diligence_assessments')->where('vendor_id', $vendorId)->orderByDesc('created_at')->first();
        $directorEmail = DB::table('system_settings')->where('setting_key', 'vendor_review_director_email')->value('setting_value');

        return view('admin.vendors.pending-login-forward', compact('vendor', 'dd', 'directorEmail'));
    }

    /** NEW 12 Aug 2026 — Admin sends a message to a still-pending vendor (who has no login yet). Saved to the log AND emailed, since the vendor has no other way to see it. */
    public function sendQaMessage(Request $request, string $vendorId, VendorPendingQaMailService $mailer)
    {
        $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;
        $messageId = (string) \Illuminate\Support\Str::uuid();

        DB::table('vendor_pending_messages')->insert([
            'message_id' => $messageId,
            'vendor_id' => $vendor->vendor_id,
            'sender_type' => 'ADMIN',
            'sender_admin_id' => $adminId,
            'message' => $request->message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mailer->notify($vendor, $request->message);

        \App\Services\AuditService::logChange('vendor_pending_messages', $messageId, 'VENDOR_QA_MESSAGE_SENT', null, ['vendor_id' => $vendor->vendor_id, 'sender_type' => 'ADMIN'], $adminId);

        return back()->with('success', 'Message sent to ' . $vendor->vendor_name . ' (and emailed to them).');
    }

    /** NEW 9 Aug 2026 — lets Admin save the Director's email once, reused by every "Forward to Director" action below. */
    public function directorEmailSetting(Request $request)
    {
        $request->validate(['director_email' => ['required', 'email', 'max:200']]);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'vendor_review_director_email'],
            ['setting_value' => $request->director_email, 'updated_by' => auth('agent')->user()->agent_id, 'updated_at' => now()]
        );

        return redirect()->route('admin.vendors.pending-logins')->with('success', 'Director\'s review email saved.');
    }

    /** NEW 9 Aug 2026 — per Chris's spec: Admin can open an internal email, write a message, and forward a vendor's registration + Due Diligence findings to the Director for further review and decision. */
    public function forwardToDirector(Request $request, string $vendorId)
    {
        $request->validate([
            'director_email' => ['required', 'email', 'max:200'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;
        $referralId = (string) \Illuminate\Support\Str::uuid();

        $status = 'SENT';
        try {
            \Illuminate\Support\Facades\Mail::html(nl2br(e($request->message)), function ($mail) use ($request, $vendor) {
                $mail->to($request->director_email)->subject($request->subject . ' — ' . $vendor->vendor_name);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('VendorLoginApprovalController::forwardToDirector mail failed: ' . $e->getMessage());
            $status = 'FAILED';
        }

        DB::table('vendor_review_referrals')->insert([
            'referral_id' => $referralId,
            'vendor_id' => $vendor->vendor_id,
            'referred_by' => $adminId,
            'director_email' => $request->director_email,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_review_referrals', $referralId, 'VENDOR_FORWARDED_TO_DIRECTOR', null, ['vendor_id' => $vendor->vendor_id, 'director_email' => $request->director_email, 'status' => $status], $adminId);

        return $status === 'SENT'
            ? back()->with('success', 'Forwarded ' . $vendor->vendor_name . '\'s registration to ' . $request->director_email . '.')
            : back()->with('error', 'Could not send the email (saved as a record, but delivery failed) — check mail settings.');
    }

    public function approvalSettings(Request $request)
    {
        $request->validate(['required_count' => ['required', 'in:1,2']]);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'vendor_approval_required_count'],
            ['setting_value' => $request->required_count, 'updated_by' => auth('agent')->user()->agent_id, 'updated_at' => now()]
        );

        \App\Services\AuditService::logChange('system_settings', 'vendor_approval_required_count', 'SETTING_CHANGED', null, ['vendor_approval_required_count' => $request->required_count], auth('agent')->user()->agent_id);

        return redirect()->route('admin.vendors.pending-logins')->with('success', 'Vendor approval requirement updated: ' . $request->required_count . ' Admin approval(s) now required.');
    }

    // FIXED/EXTENDED 8 Aug 2026 — this used to always finalize on one
    // click. Now supports 1 or 2 required approvers (requiredApprovals()
    // above). With 1 required (Chris's current setup, sole Admin), this
    // behaves exactly as before — one click finalizes. With 2 required,
    // the first click just records approval 1 and the vendor stays
    // visible in the queue as "Awaiting 2nd approval"; the SAME Admin
    // cannot give the second approval — a different Admin account must.
    public function approve(string $vendorId, VendorVerificationMailService $mailer)
    {
        // CHANGED 13 Aug 2026 — per Chris's restricted-access design: a
        // vendor with entity_type is now RESTRICTED (not PENDING) by the
        // time every mandatory document is verified — see
        // verifyDocument() below, which does that transition
        // automatically. This final Approve click now accepts either
        // status so it still works for legacy no-entity_type vendors
        // (which skip the restricted stage entirely, same as before).
        $vendor = Vendor::where('vendor_id', $vendorId)->whereIn('login_status', ['PENDING', 'RESTRICTED'])->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;
        $required = self::requiredApprovals();

        // NEW 9 Aug 2026 — per Chris's spec: "Block submission until
        // mandatory documents are uploaded and verified." Applies only to
        // vendors registered under the new entity-type checklist
        // (entity_type set); older pending registrations pre-date
        // per-document verification and keep the old behavior. CHANGED
        // 13 Aug 2026 — now delegates to
        // VendorDocumentChecklistService::allMandatoryVerified(), the
        // same check verifyDocument() uses to grant restricted access,
        // so there is exactly one definition of "fully verified."
        if ($vendor->entity_type && !\App\Services\VendorDocumentChecklistService::allMandatoryVerified($vendor)) {
            return back()->with('error', 'Cannot approve ' . $vendor->vendor_name . ' yet — please verify (or reject) every required document first.');
        }

        // NEW 14 Aug 2026 — per Chris's tick-box brief: the Welcome
        // Letter cannot be sent until Sales Admin, Finance Admin, AND
        // Director Admin have each signed off on THIS vendor (see
        // welcomeLetterGate()/approveDepartment() above). This is
        // separate from the requiredApprovals() 1-or-2-Admin counter
        // just below, which still runs afterwards unchanged.
        $gateForApproval = $this->welcomeLetterGate($vendorId);
        if (!$this->gateStage1Done($gateForApproval)) {
            return back()->with('error', 'Cannot send the Welcome Letter yet — Sales Admin or Finance Admin must sign off first.');
        }
        if (!$gateForApproval->director_approved_at) {
            return back()->with('error', 'Cannot send the Welcome Letter yet — still waiting on Director Admin sign-off.');
        }

        // NEW 13 Aug 2026 — a RESTRICTED vendor already has a password
        // (set the moment their documents were verified) — final
        // approval just unlocks the full dashboard, no second
        // password-setup email needed. Only a legacy PENDING (no
        // entity_type) vendor still needs the mailer here.
        $alreadyHasPassword = $vendor->login_status === 'RESTRICTED';

        if (!$vendor->approved_at_1) {
            $vendor->update(['approved_by_1' => $adminId, 'approved_at_1' => now(), 'rejection_reason' => null]);
            \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_APPROVAL_1_GIVEN', null, ['approved_by_1' => $adminId], $adminId);

            if ($required <= 1) {
                if ($alreadyHasPassword) {
                    $vendor->update(['login_status' => 'ACTIVE']);
                    \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_LOGIN_APPROVED', ['login_status' => 'RESTRICTED'], ['login_status' => 'ACTIVE'], $adminId);
                    $this->sendApprovalWelcomeLetter($vendor);
                    return redirect()->route('admin.vendors.approvals')->with('success', $vendor->vendor_name . ' is fully approved — their restricted account now has full Vendor Dashboard access, and the welcome letter has been emailed.');
                }
                $mailer->send($vendor); // sets login_status = AWAITING_PASSWORD internally
                \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_LOGIN_APPROVED', ['login_status' => 'PENDING'], ['login_status' => 'AWAITING_PASSWORD'], $adminId);
                return redirect()->route('admin.vendors.approvals')->with('success', $vendor->vendor_name . ' has been emailed a link to verify their address and set their password.');
            }

            return redirect()->route('admin.vendors.approvals')->with('success', 'First approval recorded for ' . $vendor->vendor_name . '. A second, different Admin must approve before the vendor is notified.');
        }

        // A first approval already exists — this click is attempting the
        // second one.
        if ($vendor->approved_by_1 === $adminId) {
            return back()->with('error', 'You already gave the first approval for ' . $vendor->vendor_name . ' — a different Admin must give the second approval.');
        }

        $vendor->update(['approved_by_2' => $adminId, 'approved_at_2' => now(), 'rejection_reason' => null]);
        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_APPROVAL_2_GIVEN', null, ['approved_by_2' => $adminId], $adminId);

        if ($alreadyHasPassword) {
            $vendor->update(['login_status' => 'ACTIVE']);
            \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_LOGIN_APPROVED', ['login_status' => 'RESTRICTED'], ['login_status' => 'ACTIVE'], $adminId);
            $this->sendApprovalWelcomeLetter($vendor);
            return redirect()->route('admin.vendors.approvals')->with('success', $vendor->vendor_name . ' has received its second approval — their restricted account now has full Vendor Dashboard access, and the welcome letter has been emailed.');
        }

        $mailer->send($vendor); // sets login_status = AWAITING_PASSWORD internally
        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_LOGIN_APPROVED', ['login_status' => 'PENDING'], ['login_status' => 'AWAITING_PASSWORD'], $adminId);

        return redirect()->route('admin.vendors.approvals')->with('success', $vendor->vendor_name . ' has received its second approval and has been emailed a link to verify their address and set their password.');
    }

    /** Admin-only download for the uploaded SSM certificate / company profile document (legacy vendors, entity_type null), so they can be reviewed before approving. */
    public function document(string $vendorId, string $type)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $path = $type === 'ssm' ? $vendor->ssm_document_path : $vendor->company_profile_document_path;

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'Document not found.');
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    /** NEW 9 Aug 2026 — Admin-only download for one entry in the new per-document vendor_documents table (entity-type checklist). */
    public function documentFile(string $vendorId, string $vendorDocumentId)
    {
        $doc = DB::table('vendor_documents')->where('vendor_document_id', $vendorDocumentId)->where('vendor_id', $vendorId)->first();
        if (!$doc || !Storage::disk('local')->exists($doc->file_path)) {
            abort(404, 'Document not found.');
        }
        return response()->file(Storage::disk('local')->path($doc->file_path));
    }

    /** NEW 12 Aug 2026 — Admin-only download for one uploaded marketing attachment (video/slideshow/flyer) from vendor_registration_media. LINK-type rows have no file — they're rendered as a plain external link in the view instead. */
    public function marketingMediaFile(string $vendorId, string $mediaId)
    {
        $media = DB::table('vendor_registration_media')->where('media_id', $mediaId)->where('vendor_id', $vendorId)->first();
        if (!$media || !$media->file_path || !Storage::disk('local')->exists($media->file_path)) {
            abort(404, 'File not found.');
        }
        return response()->file(Storage::disk('local')->path($media->file_path));
    }

    /** NEW 9 Aug 2026 — per Chris's spec: "Show verification status for each file." Admin marks one uploaded document Verified or Rejected, with an optional note. */
    public function verifyDocument(Request $request, string $vendorId, string $vendorDocumentId, VendorVerificationMailService $mailer)
    {
        $request->validate([
            'decision' => ['required', 'in:VERIFIED,REJECTED'],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);

        $doc = DB::table('vendor_documents')->where('vendor_document_id', $vendorDocumentId)->where('vendor_id', $vendorId)->first();
        if (!$doc) {
            abort(404, 'Document not found.');
        }

        $adminId = auth('agent')->user()->agent_id;
        DB::table('vendor_documents')->where('vendor_document_id', $vendorDocumentId)->update([
            'verification_status' => $request->decision,
            'verification_note'   => $request->note,
            'verified_by'         => $adminId,
            'verified_at'         => now(),
            'updated_at'          => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_documents', $vendorDocumentId, 'VENDOR_DOCUMENT_' . $request->decision, ['verification_status' => $doc->verification_status], ['verification_status' => $request->decision, 'note' => $request->note], $adminId);

        // NEW 13 Aug 2026 — per Chris's restricted-access design,
        // confirmed: unlock restricted login the moment every MANDATORY
        // document is verified (not at raw registration — that would
        // undo the anti-fraud rule that no login exists until documents
        // are checked — and not only at final approval either). Only
        // fires once, from PENDING (raw, no password yet); re-verifying
        // an already-RESTRICTED vendor's document again does nothing here.
        $vendor = Vendor::where('vendor_id', $vendorId)->first();
        $successMsg = 'Document "' . $doc->document_label . '" marked ' . strtolower($request->decision) . '.';
        if ($vendor && $vendor->login_status === 'PENDING' && \App\Services\VendorDocumentChecklistService::allMandatoryVerified($vendor)) {
            $mailer->send($vendor); // sets login_status = AWAITING_PASSWORD internally
            \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_DOCUMENTS_FULLY_VERIFIED', ['login_status' => 'PENDING'], ['login_status' => 'AWAITING_PASSWORD'], $adminId);
            $successMsg .= ' All required documents are now verified — ' . $vendor->vendor_name . ' has been emailed a link to set their password and access their restricted account while final approval is pending.';
        }

        // FIXED 12 Aug 2026 per Chris: "why i click in ssm document accept
        // it automatically go back previous screen, i still have few more
        // not accept." This used to be back(), which reloads the same
        // page but with NO memory of which tab was open — since every
        // tab defaults to Profile on a fresh page load, accepting a
        // document made it look like the screen "jumped back" to Profile
        // even though it never left this vendor's detail page. Redirect
        // explicitly to the SSM Documents tab (?tab=ssm) instead, and
        // pending-login-detail.blade.php reads that on load to reopen
        // the same tab automatically.
        return redirect(route('admin.vendors.pending-logins.show', $vendorId) . '?tab=ssm')->with('success', $successMsg);
    }

    public function reject(Request $request, string $vendorId)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        // CHANGED 13 Aug 2026 — a vendor can now be rejected even after
        // reaching RESTRICTED (documents looked verified but something
        // else raised a concern before final approval), not only while
        // still PENDING. Also allowed during AWAITING_PASSWORD (verified,
        // emailed to set a password, but Admin changes their mind before
        // the vendor even logs in) — Approve can't act on that status
        // (nothing to finalize yet), but Reject/cancel still should.
        $vendor = Vendor::where('vendor_id', $vendorId)->whereIn('login_status', ['PENDING', 'RESTRICTED', 'AWAITING_PASSWORD'])->firstOrFail();
        $previousStatus = $vendor->login_status;
        $vendor->update(['login_status' => 'REJECTED', 'rejection_reason' => $request->reason]);

        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_LOGIN_REJECTED', ['login_status' => $previousStatus], ['login_status' => 'REJECTED', 'reason' => $request->reason], auth('agent')->user()->agent_id);

        return redirect()->route('admin.vendors.approvals')->with('success', $vendor->vendor_name . '\'s registration was rejected.');
    }

    /**
     * NEW 13 Aug 2026 — per Chris: real Gmail SMTP just got wired up
     * (MAIL_MAILER was "log" before, so every email up to now only wrote
     * itself into storage/logs/laravel.log and never actually reached a
     * vendor's inbox). A vendor already sitting in AWAITING_PASSWORD has
     * a verification email that was generated but never truly delivered
     * — this re-runs VendorVerificationMailService::send(), which issues
     * a fresh token/link and emails it again for real, without touching
     * anything else about the vendor's record. Only valid while still
     * AWAITING_PASSWORD; once they've set a password (RESTRICTED/ACTIVE)
     * there is nothing left to verify.
     */
    public function resendVerification(string $vendorId, VendorVerificationMailService $mailer)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->where('login_status', 'AWAITING_PASSWORD')->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;

        $mailer->send($vendor);

        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_VERIFICATION_EMAIL_RESENT', null, ['vendor_email' => $vendor->vendor_email], $adminId);

        return back()->with('success', 'Verification email resent to ' . $vendor->vendor_name . ' (' . $vendor->vendor_email . ').');
    }

    /**
     * NEW 12 Aug 2026 — per Chris: "if cannot check then you allow the
     * admin upload the report separately, and can upload multiple file
     * because many director and many shareholder... you have to prepare
     * your own review and comments your assessment risk score." Each
     * uploaded file is one director/shareholder's bankruptcy/insolvency
     * search report; Claude reads each one for real (same capability as
     * the SSM document identity check) and the combined finding rolls
     * into the vendor's overall risk score.
     */
    public function uploadBankruptcyDocuments(Request $request, string $vendorId, VendorDueDiligenceService $service)
    {
        $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;

        // CHANGED 12 Aug 2026 — per Chris: "standard naming/folder for
        // each vendor... all these upload is also store in the same
        // manner." Routed through VendorFileStorageService, same shared
        // service the SSM checklist and marketing media uploads use, so
        // bankruptcy reports land in this vendor's own folder too.
        foreach ($request->file('documents') as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $path = VendorFileStorageService::store($file, $vendor->vendor_id, $vendor->vendor_name, VendorFileStorageService::CATEGORY_BANKRUPTCY);
            $mime = match ($ext) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => null,
            };

            $documentId = (string) \Illuminate\Support\Str::uuid();
            $row = [
                'document_id' => $documentId,
                'vendor_id' => $vendor->vendor_id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'uploaded_by_admin_id' => $adminId,
                'ai_person_name' => null,
                'ai_conclusion' => null,
                'ai_finding' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($mime) {
                try {
                    $analysis = $service->analyzeBankruptcyDocument(Storage::disk('local')->path($path), $mime);
                    if ($analysis['status'] === 'OK') {
                        $row['ai_person_name'] = $analysis['person_name'];
                        $row['ai_conclusion'] = $analysis['conclusion'];
                        $row['ai_finding'] = $analysis['finding'];
                    } else {
                        $row['ai_conclusion'] = 'UNCLEAR';
                        $row['ai_finding'] = $analysis['message'] ?? 'Could not be read automatically — review manually.';
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('uploadBankruptcyDocuments: AI review failed for ' . $documentId . ': ' . $e->getMessage());
                    $row['ai_conclusion'] = 'UNCLEAR';
                    $row['ai_finding'] = 'Could not be reviewed automatically — review manually.';
                }
            } else {
                $row['ai_conclusion'] = 'UNCLEAR';
                $row['ai_finding'] = 'Unsupported file format — review manually.';
            }

            DB::table('vendor_bankruptcy_documents')->insert($row);
        }

        $service->refreshBankruptcyAssessment($vendor);

        \App\Services\AuditService::logChange('vendor_bankruptcy_documents', $vendor->vendor_id, 'VENDOR_BANKRUPTCY_DOCS_UPLOADED', null, ['vendor_id' => $vendor->vendor_id, 'count' => count($request->file('documents'))], $adminId);

        return back()->with('success', count($request->file('documents')) . ' bankruptcy report(s) uploaded and reviewed for ' . $vendor->vendor_name . '.');
    }

    /** Admin-only download for an uploaded bankruptcy/insolvency search report. */
    public function bankruptcyDocumentFile(string $vendorId, string $documentId)
    {
        $doc = DB::table('vendor_bankruptcy_documents')->where('document_id', $documentId)->where('vendor_id', $vendorId)->first();
        if (!$doc || !Storage::disk('local')->exists($doc->file_path)) {
            abort(404, 'Document not found.');
        }
        return response()->file(Storage::disk('local')->path($doc->file_path));
    }

    /**
     * NEW 12 Aug 2026 — per Chris: "add the 9th folder call Ctos report,
     * same as bankruptcy, choose file method." Mirrors
     * uploadBankruptcyDocuments() exactly — CTOS has no automated
     * GeneralLink connection either, so Admin uploads whatever CTOS
     * report(s) they obtained and Claude reads each one for real.
     */
    public function uploadCtosDocuments(Request $request, string $vendorId, VendorDueDiligenceService $service)
    {
        $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $adminId = auth('agent')->user()->agent_id;

        foreach ($request->file('documents') as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $path = VendorFileStorageService::store($file, $vendor->vendor_id, $vendor->vendor_name, VendorFileStorageService::CATEGORY_CTOS);
            $mime = match ($ext) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => null,
            };

            $documentId = (string) \Illuminate\Support\Str::uuid();
            $row = [
                'document_id' => $documentId,
                'vendor_id' => $vendor->vendor_id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'uploaded_by_admin_id' => $adminId,
                'ai_entity_name' => null,
                'ai_conclusion' => null,
                'ai_finding' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($mime) {
                try {
                    $analysis = $service->analyzeCtosDocument(Storage::disk('local')->path($path), $mime);
                    if ($analysis['status'] === 'OK') {
                        $row['ai_entity_name'] = $analysis['entity_name'];
                        $row['ai_conclusion'] = $analysis['conclusion'];
                        $row['ai_finding'] = $analysis['finding'];
                    } else {
                        $row['ai_conclusion'] = 'UNCLEAR';
                        $row['ai_finding'] = $analysis['message'] ?? 'Could not be read automatically — review manually.';
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('uploadCtosDocuments: AI review failed for ' . $documentId . ': ' . $e->getMessage());
                    $row['ai_conclusion'] = 'UNCLEAR';
                    $row['ai_finding'] = 'Could not be reviewed automatically — review manually.';
                }
            } else {
                $row['ai_conclusion'] = 'UNCLEAR';
                $row['ai_finding'] = 'Unsupported file format — review manually.';
            }

            DB::table('vendor_ctos_documents')->insert($row);
        }

        $service->refreshCtosAssessment($vendor);

        \App\Services\AuditService::logChange('vendor_ctos_documents', $vendor->vendor_id, 'VENDOR_CTOS_DOCS_UPLOADED', null, ['vendor_id' => $vendor->vendor_id, 'count' => count($request->file('documents'))], $adminId);

        return back()->with('success', count($request->file('documents')) . ' CTOS report(s) uploaded and reviewed for ' . $vendor->vendor_name . '.');
    }

    /** Admin-only download for an uploaded CTOS report. */
    public function ctosDocumentFile(string $vendorId, string $documentId)
    {
        $doc = DB::table('vendor_ctos_documents')->where('document_id', $documentId)->where('vendor_id', $vendorId)->first();
        if (!$doc || !Storage::disk('local')->exists($doc->file_path)) {
            abort(404, 'Document not found.');
        }
        return response()->file(Storage::disk('local')->path($doc->file_path));
    }

    /**
     * NEW 12 Aug 2026 (Task #105) — per Chris: "there is no drill down on
     * each due diligence, display all tap on top not a pop up window, it
     * is a new screen when click from pending approval screen." Risk
     * Assessment Results moves from a JS-toggled tab inside the Pending
     * Vendor Login popup to its own real route/screen. No ?cat = the
     * 9-category index (drill-down entry point); ?cat=<key> = that one
     * category's full finding/status, with Prev/Next to move through the
     * other 8 sequentially.
     */
    public function riskAssessment(Request $request, string $vendorId, VendorDueDiligenceService $service)
    {
        $vendor = Vendor::where('vendor_id', $vendorId)->firstOrFail();
        $dd = DB::table('vendor_due_diligence_assessments')->where('vendor_id', $vendorId)->orderByDesc('created_at')->first();
        $riskCategories = VendorDueDiligenceService::riskCategoryRows($dd);
        $bankruptcyDocs = DB::table('vendor_bankruptcy_documents')->where('vendor_id', $vendorId)->orderBy('created_at')->get();
        $ctosDocs = DB::table('vendor_ctos_documents')->where('vendor_id', $vendorId)->orderBy('created_at')->get();
        $cat = $request->query('cat');

        return view('admin.vendors.risk-assessment', compact('vendor', 'dd', 'riskCategories', 'bankruptcyDocs', 'ctosDocs', 'cat'));
    }

    /**
     * Sidebar badge count — same JSON {count} shape as ApprovalController::pendingCount().
     * CHANGED 13 Aug 2026 — includes RESTRICTED alongside PENDING: a
     * RESTRICTED vendor already has all mandatory documents verified and
     * is sitting in this same queue waiting on Admin's final Approve
     * click, so it must still count toward the badge or Admin would
     * think there's nothing left to do.
     */
    public function pendingCount()
    {
        return response()->json(['count' => Vendor::whereIn('login_status', ['PENDING', 'RESTRICTED'])->count()]);
    }
}
