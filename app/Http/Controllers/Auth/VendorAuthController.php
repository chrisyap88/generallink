<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PhoneNumberService;
use App\Services\VendorClassificationService;
use App\Services\VendorDocumentChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// NEW 8 Aug 2026 (Task #92, REDESIGNED same day per Chris) — Vendor
// login, no hierarchy/sponsor (unlike the agent flow — vendors never get
// assigned an upline). Deliberately NO password is collected at
// registration anymore — per Chris: this is an identity-fraud control.
// Registration instead collects an SSM certificate (required) plus a
// company profile document OR a Facebook page URL (at least one
// required), for Admin to manually verify are genuine BEFORE the vendor
// is ever given a way to log in at all. Only once Admin approves those
// documents does the system email a "verify your email + set your
// password" link (see showSetPassword()/setPassword() below) — so a
// scam registration never even reaches the password stage.
class VendorAuthController extends Controller
{
    // NEW 8 Aug 2026 — Vendor Management Phase 1 (spec Section 6, Vendor
    // Declaration). Kept as a controller constant, not free text typed
    // into the view, so the exact wording a vendor agreed to is always
    // traceable back to one place and can be versioned.
    public const DECLARATION_VERSION = 'v1';
    public const DECLARATION_TEXT = 'I confirm the information and documents I have submitted are accurate and genuine, that I have the authority to register this company, and I agree to GeneralLink\'s vendor terms and policies.';

    public function showRegister()
    {
        // States pulled live from the shared malaysia_postcodes table —
        // same source the Admin Vendor form's State dropdown uses. No
        // separate/hardcoded state list, per normalization rule.
        $states = DB::table('malaysia_postcodes')->select('state')->distinct()->orderBy('state')->pluck('state');

        // NEW 9 Aug 2026 — "Watch Intro Video" button on the left panel,
        // per Chris's Video Library request, now standardized across
        // every guest login/register page via one shared helper.
        $introVideo = \App\Http\Controllers\Admin\VideoLibraryController::latestIntroVideo();

        return view('auth.vendor-register', [
            'industries' => VendorClassificationService::INDUSTRIES,
            'businessActivities' => VendorClassificationService::BUSINESS_ACTIVITIES,
            'entityTypes' => VendorDocumentChecklistService::ENTITY_TYPES,
            'documentChecklists' => VendorDocumentChecklistService::allChecklists(),
            'declarationText' => self::DECLARATION_TEXT,
            'states' => $states,
            'introVideo' => $introVideo,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'vendor_name'  => ['required', 'string', 'max:200'],
            'second_name'  => ['nullable', 'string', 'max:200'],
            // CHANGED 9 Aug 2026 per Chris: Industry and Nature of Business
            // are now MULTI-select, structured data — "store as multiple
            // values, not as a single text string" — for future AI vendor
            // classification/matching/due diligence. Each ships as an array
            // of predefined keys; "Other" requires its own free-text note.
            'industries'    => ['required', 'array', 'min:1'],
            'industries.*'  => ['string', 'in:' . implode(',', array_keys(VendorClassificationService::INDUSTRIES))],
            'industry_other_text' => ['required_if:industries.*,OTHER', 'nullable', 'string', 'max:200'],
            'business_activities'   => ['required', 'array', 'min:1'],
            'business_activities.*' => ['string', 'in:' . implode(',', array_keys(VendorClassificationService::BUSINESS_ACTIVITIES))],
            'nature_of_business_other_text' => ['required_if:business_activities.*,OTHER', 'nullable', 'string', 'max:200'],
            // NEW 9 Aug 2026 — per Chris: vendor's office address is
            // compulsory, with postcode/city typeahead + state pick list
            // (same shared malaysia_postcodes lookup used on the main
            // agent Register screen and Admin's own Vendor form).
            'vendor_address'  => ['required', 'string', 'max:255'],
            'vendor_postcode' => ['required', 'string', 'max:10'],
            'vendor_city'     => ['required', 'string', 'max:100'],
            'vendor_state'    => ['required', 'string', 'max:50'],
            // CHANGED 9 Aug 2026 per Chris: "remove business category" from
            // the registration form — it was confusing next to Nature of
            // Business. vendor_type is no longer collected at
            // self-registration; Admin can still classify it later via
            // Master File Maintenance if the Phase 2 catalogue needs it.
            'entity_type'  => ['required', 'in:' . implode(',', array_keys(VendorDocumentChecklistService::ENTITY_TYPES))],
            // NEW 9 Aug 2026 — per Chris: up to 3 contact persons, Contact 1
            // compulsory. Contact 1's email/phone double as the vendor's
            // login email/phone (vendor_email/vendor_phone) — no separate
            // "Business Email" field anymore, since Contact 1 IS the
            // primary business contact who will receive the login.
            'contacts.1.name'        => ['required', 'string', 'max:200'],
            'contacts.1.designation' => ['required', 'string', 'max:100'],
            'contacts.1.phone'       => ['required', 'string', PhoneNumberService::rule()],
            'contacts.1.email'       => ['required', 'email', 'max:200'],
            'contacts.2.name'        => ['nullable', 'string', 'max:200'],
            'contacts.2.designation' => ['nullable', 'string', 'max:100'],
            'contacts.2.phone'       => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'contacts.2.email'       => ['nullable', 'email', 'max:200'],
            'contacts.3.name'        => ['nullable', 'string', 'max:200'],
            'contacts.3.designation' => ['nullable', 'string', 'max:100'],
            'contacts.3.phone'       => ['nullable', 'string', PhoneNumberService::nullableRule()],
            'contacts.3.email'       => ['nullable', 'email', 'max:200'],
            // NEW 13 Aug 2026 — per Chris: "which vendor email address?
            // contact 1 or 2 or 3?... add another contact as authorized
            // Director." Defaults to Contact 1 (unchecked box below);
            // these four are only required if the vendor ticks "someone
            // else will sign" — see Vendor::authorizedSignatory().
            'authorized_signatory_different' => ['nullable', 'boolean'],
            'authorized_signatory_name'        => ['required_if:authorized_signatory_different,1', 'nullable', 'string', 'max:150'],
            'authorized_signatory_designation' => ['required_if:authorized_signatory_different,1', 'nullable', 'string', 'max:100'],
            'authorized_signatory_email'       => ['required_if:authorized_signatory_different,1', 'nullable', 'email', 'max:150'],
            'authorized_signatory_phone'       => ['required_if:authorized_signatory_different,1', 'nullable', 'string', PhoneNumberService::nullableRule()],
            'documents'    => ['array'],
            'documents.*'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'fb_page_url'  => ['nullable', 'url', 'max:255'],
            // NEW 12 Aug 2026 — per Chris: "for new vendor registration
            // can he upload his video or slide show or you tube like or
            // ppt, product flyer." All optional — never blocks
            // registration, unlike the mandatory SSM documents above.
            'marketing_video'     => ['nullable', 'file', 'mimes:mp4,mov,avi,webm', 'max:51200'],
            // FIXED 12 Aug 2026 — per Chris's error: "The marketing_slideshow
            // field must be a file of type: pdf, ppt, pptx" on a genuinely
            // valid .pptx upload. Root cause: Laravel's mimes rule sniffs
            // the file's real content type via PHP fileinfo, and .pptx
            // (a ZIP-based Office Open XML format) very commonly gets
            // detected as generic application/zip rather than the
            // PowerPoint MIME type — a well-known false rejection, not a
            // problem with the file itself. extensions checks the
            // filename's extension instead (Laravel's own documented fix
            // for this exact issue) so a real .pptx/.ppt/.pdf is accepted.
            'marketing_slideshow' => ['nullable', 'file', 'extensions:pdf,ppt,pptx', 'max:20480'],
            'marketing_flyer'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'marketing_link'      => ['nullable', 'url', 'max:500'],
            'declaration'  => ['accepted'],
        ], [
            'entity_type.required' => 'Please select your business entity type — this determines which SSM documents you need to upload.',
            'industries.required' => 'Please select at least one Industry.',
            'business_activities.required' => 'Please select at least one Nature of Business activity.',
            'vendor_address.required' => 'Please provide your office address.',
            'vendor_postcode.required' => 'Please provide your postcode.',
            'vendor_city.required' => 'Please provide your city.',
            'vendor_state.required' => 'Please select your state.',
            'contacts.1.name.required' => 'Please provide the name of your main contact person (Contact 1) — this is compulsory.',
            'contacts.1.designation.required' => 'Please provide Contact 1\'s designation (job title) — this is compulsory.',
            'contacts.1.phone.required' => 'Please provide Contact 1\'s phone number — this is compulsory.',
            'contacts.1.email.required' => 'Please provide Contact 1\'s email — this will also be your login ID.',
            'declaration.accepted' => 'Please confirm the declaration checkbox before submitting.',
        ]);

        // NEW 9 Aug 2026 — required documents vary by entity_type (spec:
        // "the system should automatically switch required documents based
        // on this selection"). The client-side form only shows the right
        // upload boxes; THIS check is the real gate — never trust the
        // browser alone. "Block submission until mandatory documents are
        // uploaded" per Chris.
        // CHANGED 9 Aug 2026 per Chris's full SSM Document Registration &
        // Verification Module spec: every document is its own checklist
        // line again (name + description + one file). Only MANDATORY-tier
        // items block submission — CONDITIONAL/OPTIONAL items are shown
        // but never forced ("the Vendor should NOT be forced to upload a
        // conditional document when it does not apply to their company").
        $checklist = VendorDocumentChecklistService::checklist($request->entity_type);
        $missing = [];
        foreach ($checklist as $doc) {
            if ($doc['tier'] === \App\Services\VendorDocumentChecklistService::TIER_MANDATORY && !$request->hasFile("documents.{$doc['key']}")) {
                $missing[] = $doc['label'];
            }
        }
        if ($missing) {
            return back()->withErrors(['documents' => 'Please upload all required documents for your entity type: ' . implode('; ', $missing) . '.'])->withInput();
        }

        // NEW 9 Aug 2026 — Contact 1's email/phone are now the vendor's
        // login email/phone (see comment on the validation rules above).
        $contact1Email = strtolower($request->input('contacts.1.email'));
        $contact1Phone = PhoneNumberService::normalize($request->input('contacts.1.phone'));

        // A vendor_email already used by ANOTHER vendor login (pending,
        // awaiting password, active, or rejected) can't register again —
        // but a vendor_email that only exists on an Admin-created
        // reference-only record (login_status = NONE, most existing
        // vendor rows) is fine to "claim" for a login.
        $existingLogin = DB::table('vendors')
            ->where('vendor_email', $contact1Email)
            ->where('login_status', '!=', 'NONE')
            ->first();
        if ($existingLogin) {
            return back()->withErrors(['contacts.1.email' => 'An account with this email already exists. Please sign in, or contact Admin if you\'ve forgotten your password.'])->withInput();
        }

        $base = strtoupper(Str::slug($request->vendor_name, ''));
        $base = substr($base, 0, 16);
        $code = $base;
        $suffix = 1;
        while (DB::table('vendors')->where('vendor_code', $code)->exists()) {
            $suffix++;
            $code = substr($base, 0, 16 - strlen((string) $suffix)) . $suffix;
        }

        // NEW 9 Aug 2026 — the real, structured data lives in
        // vendor_industries / vendor_business_activities (inserted below).
        // vendors.industry / vendors.nature_of_business are kept as a
        // human-readable SUMMARY only, so the many existing screens that
        // already display $vendor->industry / $vendor->nature_of_business
        // (Admin approval cards, Vendor Profile, etc.) keep working without
        // needing every single one rewritten today.
        $selectedIndustries = array_values(array_intersect($request->input('industries', []), array_keys(VendorClassificationService::INDUSTRIES)));
        $selectedActivities = array_values(array_intersect($request->input('business_activities', []), array_keys(VendorClassificationService::BUSINESS_ACTIVITIES)));
        $industrySummary = implode(', ', array_map(fn ($k) => VendorClassificationService::INDUSTRIES[$k], $selectedIndustries));
        $activitySummary = implode(', ', array_map(fn ($k) => VendorClassificationService::BUSINESS_ACTIVITIES[$k], $selectedActivities));

        $vendor = Vendor::create([
            'vendor_name'  => $request->vendor_name,
            'second_name'  => $request->second_name ?: null,
            'vendor_code'  => $code,
            'industry'     => $industrySummary,
            'industry_other_text' => in_array('OTHER', $selectedIndustries, true) ? $request->industry_other_text : null,
            'entity_type'  => $request->entity_type,
            'nature_of_business' => $activitySummary,
            'nature_of_business_other_text' => in_array('OTHER', $selectedActivities, true) ? $request->nature_of_business_other_text : null,
            'vendor_address'  => $request->vendor_address,
            'vendor_postcode' => $request->vendor_postcode,
            'vendor_city'     => $request->vendor_city,
            'vendor_state'    => $request->vendor_state,
            'vendor_email' => $contact1Email,
            'vendor_phone' => $contact1Phone,
            'pic_name'        => $request->input('contacts.1.name'),
            'pic_designation' => $request->input('contacts.1.designation'),
            'pic_phone'       => $contact1Phone,
            'pic_email'       => $contact1Email,
            'contact2_name'        => $request->input('contacts.2.name'),
            'contact2_designation' => $request->input('contacts.2.designation'),
            'contact2_phone'       => $request->filled('contacts.2.phone') ? PhoneNumberService::normalize($request->input('contacts.2.phone')) : null,
            'contact2_email'       => $request->input('contacts.2.email') ? strtolower($request->input('contacts.2.email')) : null,
            'contact3_name'        => $request->input('contacts.3.name'),
            'contact3_designation' => $request->input('contacts.3.designation'),
            'contact3_phone'       => $request->filled('contacts.3.phone') ? PhoneNumberService::normalize($request->input('contacts.3.phone')) : null,
            'contact3_email'       => $request->input('contacts.3.email') ? strtolower($request->input('contacts.3.email')) : null,
            // NEW 13 Aug 2026 — left null unless the vendor explicitly
            // ticked "someone else will sign", in which case
            // Vendor::authorizedSignatory() uses these instead of
            // Contact 1.
            'authorized_signatory_name'        => $request->boolean('authorized_signatory_different') ? $request->input('authorized_signatory_name') : null,
            'authorized_signatory_designation' => $request->boolean('authorized_signatory_different') ? $request->input('authorized_signatory_designation') : null,
            'authorized_signatory_email'       => $request->boolean('authorized_signatory_different') ? strtolower($request->input('authorized_signatory_email')) : null,
            'authorized_signatory_phone'       => $request->boolean('authorized_signatory_different') && $request->filled('authorized_signatory_phone') ? PhoneNumberService::normalize($request->input('authorized_signatory_phone')) : null,
            'is_active'    => true,
            'login_status' => 'PENDING',
            'fb_page_url'  => $request->input('fb_page_url'),
            'declaration_accepted_at' => now(),
            'declaration_version'    => self::DECLARATION_VERSION,
        ]);

        // NEW 9 Aug 2026 — one row per selected Industry / Business
        // Activity (spec: "store as multiple values, not as a single text
        // string"), real structured data for future search/matching/
        // due diligence, same "one row per item" pattern as vendor_documents.
        foreach ($selectedIndustries as $key) {
            DB::table('vendor_industries')->insert([
                'vendor_industry_id' => (string) Str::uuid(),
                'vendor_id' => $vendor->vendor_id,
                'industry_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($selectedActivities as $key) {
            DB::table('vendor_business_activities')->insert([
                'vendor_activity_id' => (string) Str::uuid(),
                'vendor_id' => $vendor->vendor_id,
                'activity_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // NEW 9 Aug 2026 — one vendor_documents row per uploaded file,
        // instead of the old fixed ssm_document_path/company_profile_
        // document_path pair. Lets the checklist vary by entity_type and
        // lets Admin verify/reject each document individually (spec:
        // "Show verification status for each file").
        // CHANGED 12 Aug 2026 — per Chris: standard per-vendor folder
        // naming across every upload type (see VendorFileStorageService).
        // SSM/entity checklist documents go in this vendor's own
        // "ssm-documents" subfolder, real filename, auto-versioned.
        foreach ($checklist as $doc) {
            if ($request->hasFile("documents.{$doc['key']}")) {
                $file = $request->file("documents.{$doc['key']}");
                DB::table('vendor_documents')->insert([
                    'vendor_document_id' => (string) Str::uuid(),
                    'vendor_id'          => $vendor->vendor_id,
                    'document_key'       => $doc['key'],
                    'document_label'     => $doc['label'],
                    'file_path'          => \App\Services\VendorFileStorageService::store($file, $vendor->vendor_id, $vendor->vendor_name, \App\Services\VendorFileStorageService::CATEGORY_SSM),
                    'file_name'          => $file->getClientOriginalName(),
                    'verification_status' => 'PENDING',
                    'ai_check_status'    => 'PENDING',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }

        // NEW 12 Aug 2026 — per Chris: optional marketing attachments
        // (video / slideshow / flyer / external link) captured at
        // registration time, feeding the "Video/URL/Slideshow" folder
        // tab on Admin's Pending Vendor Logins detail panel. Separate
        // table from vendor_documents (SSM/company docs) since these are
        // marketing material, not registration proof.
        //
        // CHANGED 12 Aug 2026 — per Chris: "standard naming/folder for
        // each vendor. main folder vendor name sub folder individual
        // category." Routed through VendorFileStorageService — the same
        // service the SSM checklist upload above and the Admin bankruptcy
        // report upload both use, and the one any future rebate/
        // promotion marketing upload must also use, so every category
        // for a given vendor lands under that one vendor's folder.
        $marketingUploads = [
            'marketing_video'     => ['VIDEO', \App\Services\VendorFileStorageService::CATEGORY_VIDEO],
            'marketing_slideshow' => ['SLIDESHOW', \App\Services\VendorFileStorageService::CATEGORY_SLIDESHOW],
            'marketing_flyer'     => ['FLYER', \App\Services\VendorFileStorageService::CATEGORY_FLYERS],
        ];
        foreach ($marketingUploads as $field => [$mediaType, $category]) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                DB::table('vendor_registration_media')->insert([
                    'media_id'   => (string) Str::uuid(),
                    'vendor_id'  => $vendor->vendor_id,
                    'media_type' => $mediaType,
                    'file_path'  => \App\Services\VendorFileStorageService::store($file, $vendor->vendor_id, $vendor->vendor_name, $category),
                    'file_name'  => $file->getClientOriginalName(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        if ($request->filled('marketing_link')) {
            DB::table('vendor_registration_media')->insert([
                'media_id'     => (string) Str::uuid(),
                'vendor_id'    => $vendor->vendor_id,
                'media_type'   => 'LINK',
                'external_url' => $request->input('marketing_link'),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_SELF_REGISTERED', null, [
            'vendor_name' => $vendor->vendor_name,
            'vendor_email' => $vendor->vendor_email,
            'entity_type' => $vendor->entity_type,
        ]);

        // NEW 9 Aug 2026 — per Chris: "do you alert admin there is a new
        // vendor just register." Every active Admin gets both an in-app
        // bell notification AND an automatic email (NotificationService
        // sends both from one call, same pattern used everywhere else in
        // the app — e.g. Approval Needed). Never allowed to block
        // registration if it fails.
        try {
            $admins = \App\Models\Agent::where('role', 'ADMIN')->where('is_deleted', false)->get()->all();
            app(\App\Services\NotificationService::class)->notify(
                $admins,
                'NEW_VENDOR_REGISTRATION',
                'New Vendor Registration',
                "A new vendor has registered and is waiting for review: {$vendor->vendor_name} ({$vendor->vendor_code}), entity type: " . (VendorDocumentChecklistService::ENTITY_TYPES[$vendor->entity_type] ?? $vendor->entity_type) . ". Go to Master File Maintenance > Pending Vendor Logins to review their documents and approve or reject."
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to notify Admin of new vendor registration ' . $vendor->vendor_id . ': ' . $e->getMessage());
        }

        // NEW 9 Aug 2026 — per Chris: "do you inform the vendor when click
        // submission and feedback to vendor such as within 3 working days
        // after verification then vendor will be notify through the
        // contact email list." The on-screen flash message below already
        // said something similar, but a flash message vanishes the moment
        // the vendor closes the tab — this sends the same commitment as a
        // real email to Contact 1, so they have it in writing regardless.
        // Never allowed to block registration if the mail server is down.
        try {
            Mail::raw(
                "Hi {$vendor->pic_name},\n\nThanks for registering {$vendor->vendor_name} as a Partner Vendor with GeneralLink.\n\nYour login ID will be this email address ({$vendor->vendor_email}).\n\nWhat happens next: our Admin team will review the documents you uploaded. Once verification is complete — usually within 3 working days — we will email this address a link to verify it and set your password, so you can sign in.\n\nIf we need anything else from you, we'll reach out using the contact details you provided.\n\nThank you for your patience.\n\nKind Regards,\nGeneralLink Admin",
                function ($mail) use ($vendor) {
                    $mail->to($vendor->vendor_email)->subject('GeneralLink — We Received Your Vendor Registration');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send registration confirmation email to vendor ' . $vendor->vendor_id . ': ' . $e->getMessage());
        }

        // NEW 9 Aug 2026 — per Chris's spec: "after the vendor uploads all
        // required documents and they pass initial validation... should
        // automatically trigger an AI-driven Due Diligence Assessment."
        // Runs right here, right after registration passes validation.
        // Never allowed to block or fail the registration itself — if
        // anything about the assessment goes wrong, the vendor's
        // registration has already succeeded; Admin just sees "Manual
        // Review Required" on that screen instead of a completed report.
        try {
            app(\App\Services\VendorDueDiligenceService::class)->runAssessment($vendor);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Due diligence assessment failed for vendor ' . $vendor->vendor_id . ': ' . $e->getMessage());
        }

        // NEW 9 Aug 2026 — per Chris's SSM Document Registration &
        // Verification Module spec: an automatic first-pass AI read of
        // EVERY uploaded document, not just the one used for the identity
        // cross-check above. See VendorDocumentVerificationService for the
        // honest scope of what this does and doesn't check.
        try {
            app(\App\Services\VendorDocumentVerificationService::class)->checkAll($vendor);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Document AI verification failed for vendor ' . $vendor->vendor_id . ': ' . $e->getMessage());
        }

        // CHANGED 12 Aug 2026 — per Chris: on-screen thank-you message plus
        // a spoken appreciation from Carolyn (see auth/vendor-login.blade.php,
        // right after @include('partials.ai-assistant-widget')). Two
        // separate flash values so the visible banner and Carolyn's voice
        // say two complementary things rather than reading the same
        // sentence twice — both rephrased in GeneralLink's own courteous
        // voice rather than a literal copy of Chris's draft wording.
        return redirect()->route('vendor.login')
            ->with('success', 'Thank you for your interest in joining the GLADE platform! A confirmation email has been sent to your first contact address (' . $vendor->vendor_email . ') acknowledging that we\'ve received your registration.')
            ->with('vendor_just_registered', 'Thank you so much for registering with us! Our team will now review your submission, and we\'ll keep you updated within the next three working days. Have a wonderful day ahead!');
    }

    // NEW 8 Aug 2026 — reached only via the emailed link, after Admin has
    // approved the vendor's documents (see
    // Admin\VendorLoginApprovalController::approve()) or Admin has
    // created a login directly (see Admin\VendorController::createLogin()).
    // Visiting this link at all proves the vendor controls the email
    // address on file — that IS the email verification step — so no
    // separate "click to verify" page exists before this.
    public function showSetPassword(string $token)
    {
        $vendor = Vendor::where('email_verification_token', $token)->where('login_status', 'AWAITING_PASSWORD')->first();
        if (!$vendor) {
            return redirect()->route('vendor.login')->with('error', 'This link is invalid or has already been used. If you need a new one, contact Admin.');
        }
        return view('auth.vendor-set-password', ['token' => $token, 'vendorName' => $vendor->vendor_name, 'vendorEmail' => $vendor->vendor_email]);
    }

    public function setPassword(Request $request, string $token)
    {
        $vendor = Vendor::where('email_verification_token', $token)->where('login_status', 'AWAITING_PASSWORD')->first();
        if (!$vendor) {
            return redirect()->route('vendor.login')->with('error', 'This link is invalid or has already been used. If you need a new one, contact Admin.');
        }

        $request->validate(['password' => ['required', 'string', 'min:6', 'confirmed']]);

        // CHANGED 13 Aug 2026 — per Chris's restricted-access design: a
        // self-registered vendor with an entity_type checklist reaches
        // this screen because ALL their mandatory documents just passed
        // verification (see VendorLoginApprovalController::verifyDocument()),
        // NOT because Admin gave final approval — so setting their
        // password unlocks RESTRICTED access, not full ACTIVE access.
        // Every other path here (legacy no-entity_type self-registration
        // approved the old way, or an Admin-created login via
        // VendorController::createLogin(), neither of which has a
        // restricted stage) still goes straight to ACTIVE, unchanged.
        $targetStatus = ($vendor->declaration_accepted_at && $vendor->entity_type) ? 'RESTRICTED' : 'ACTIVE';

        $vendor->update([
            'password_hash' => Hash::make($request->password),
            'login_status'  => $targetStatus,
            'email_verification_token' => null,
        ]);

        \App\Services\AuditService::logChange('vendors', $vendor->vendor_id, 'VENDOR_EMAIL_VERIFIED_PASSWORD_SET', ['login_status' => 'AWAITING_PASSWORD'], ['login_status' => $targetStatus]);

        $message = $targetStatus === 'RESTRICTED'
            ? 'Your email is verified and your password is set — you can now sign in to check your application status and message us while final approval is in progress.'
            : 'Your email is verified and your password is set — you can now sign in.';

        return redirect()->route('vendor.login')->with('success', $message);
    }

    // CHANGED 14 Aug 2026 — per Chris: "why dont you have one commen
    // login for all includes vendor." /vendor/login is no longer its own
    // page — it just bounces to the one common /login page, which now
    // handles both Admin and Vendor accounts (see AuthController::login()).
    // Kept as a redirect (not deleted) so any old bookmark or internal
    // redirect()->route('vendor.login') call still lands somewhere
    // correct instead of erroring.
    public function showLogin()
    {
        if (Auth::guard('vendor')->check()) {
            return redirect()->route('vendor.portal.index');
        }
        return redirect()->route('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'vendor_email' => ['required', 'email'],
            'password'     => ['required', 'string'],
        ]);

        $vendor = Vendor::where('vendor_email', strtolower($request->vendor_email))->first();

        if (!$vendor || $vendor->login_status === 'NONE') {
            return back()->withErrors(['vendor_email' => 'No vendor account found with this email address.'])->withInput();
        }

        if ($vendor->login_status === 'PENDING') {
            return back()->withErrors(['vendor_email' => 'Your documents are still being reviewed by Admin. You\'ll receive an email as soon as they\'re verified, with a link to set your password and check your application status.'])->withInput();
        }

        if ($vendor->login_status === 'AWAITING_PASSWORD') {
            return back()->withErrors(['vendor_email' => 'Check your email for a link to verify your address and set your password before signing in.'])->withInput();
        }

        if ($vendor->login_status === 'REJECTED') {
            return back()->withErrors(['vendor_email' => 'Your registration was not approved' . ($vendor->rejection_reason ? " — {$vendor->rejection_reason}" : '.') . ' Contact Admin for more information.'])->withInput();
        }

        if ($vendor->isLocked()) {
            return back()->withErrors(['vendor_email' => 'Your account is temporarily locked due to too many failed login attempts. Please try again later.'])->withInput();
        }

        if (!Hash::check($request->password, $vendor->password_hash)) {
            $vendor->increment('failed_login_attempts');
            if ($vendor->failed_login_attempts >= 5) {
                $vendor->update(['locked_until' => now()->addMinutes(30)]);
                return back()->withErrors(['vendor_email' => 'Too many failed attempts. Your account is locked for 30 minutes.'])->withInput();
            }
            return back()->withErrors(['password' => 'Incorrect password. Please try again.'])->withInput();
        }

        $vendor->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        Auth::guard('vendor')->login($vendor, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('vendor.portal.index');
    }

    public function logout(Request $request)
    {
        Auth::guard('vendor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('vendor.login')->with('success', 'You have been logged out successfully.');
    }
}
