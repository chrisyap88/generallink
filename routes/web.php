<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\AffiliateController;
use App\Http\Controllers\Auth\GladePortalController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ProfileController as AdminProfile;
use App\Http\Controllers\GL\DashboardController as GLDashboard;
use App\Http\Controllers\TL\DashboardController as TLDashboard;
use App\Http\Controllers\Introducer\DashboardController as IntroducerDashboard;
use App\Http\Controllers\Cbe\DashboardController as CbeDashboard;
use App\Http\Controllers\Cbe\MeetingMinutesController as CbeMeetingMinutes;
use App\Http\Controllers\Cbe\ActivitiesController as CbeActivities;
use App\Http\Controllers\Cbe\CbeNoticeBoardController;
use App\Http\Controllers\Cbe\CbeTempleCalendarController;
use App\Http\Controllers\Cbe\CbeMessagingController;
use App\Http\Controllers\Cbe\FinanceController as CbeFinance;
use App\Http\Controllers\Cbe\AnnualReportController as CbeAnnualReport;
use App\Http\Controllers\Cbe\EventController as CbeEvent;
use App\Http\Controllers\Cbe\DonorController as CbeDonor;
use App\Http\Controllers\Cbe\ContributionController as CbeContribution;
use App\Http\Controllers\Cbe\EventExpenseController as CbeEventExpense;
use App\Http\Controllers\Cbe\EventReportController as CbeEventReport;
use App\Http\Controllers\Cbe\AiAccountingController;
use App\Http\Controllers\Cbe\CbeAccountingController;
use App\Http\Controllers\Cbe\CbeExecDashboardController;
use App\Http\Controllers\Shared\ProfileController;
use App\Http\Controllers\Shared\NotificationController;
use App\Http\Controllers\Shared\WalletController;
use App\Http\Controllers\Shared\SalesTransactionController;
use App\Http\Controllers\Shared\DocumentCreditController;
use App\Http\Controllers\Shared\TeamDocumentCreditController;
use App\Http\Controllers\Admin\DocumentCreditController as AdminDocumentCreditController;
use App\Http\Controllers\Shared\HelpDeskController;
use App\Http\Controllers\Shared\LanguageController;
use App\Http\Controllers\Shared\NoticeBoardController;
use App\Http\Controllers\Admin\NoticeBoardController as AdminNoticeBoardController;
use App\Http\Controllers\Admin\MasterFileController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\GroupLabelController;
use App\Http\Controllers\Admin\FinanceWithdrawalController;
use App\Http\Controllers\Admin\ReasonCodeController;
use App\Http\Controllers\Admin\OrgCategoryController;
use App\Http\Controllers\Admin\RoleRankController;
use App\Http\Controllers\Admin\PromotionRuleController;
use App\Http\Controllers\Admin\BreakawayBonusRuleController;
use App\Http\Controllers\Shared\BreakawayBonusClaimController;
use App\Http\Controllers\Admin\GrowthChannelController;
use App\Http\Controllers\Shared\ReferralLinkController;
use App\Http\Controllers\Admin\RecruitmentContestController as AdminContestController;
use App\Http\Controllers\Shared\RecruitmentContestController as SharedContestController;
use App\Http\Controllers\Shared\BadgeController;
use App\Http\Controllers\Shared\CustomerReferralController;
use App\Http\Controllers\Shared\PublicProfilePageController;
use App\Http\Controllers\Admin\BroadcastCampaignController;
use App\Http\Controllers\Admin\SurveyController as AdminSurveyController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\SpecialGroupController;
use App\Http\Controllers\Admin\BatchRegistrationController;
use App\Http\Controllers\Admin\AgentManagementController;
use App\Http\Controllers\Admin\AgentProfileController;
use App\Http\Controllers\Admin\PendingAssignmentController;
use App\Http\Controllers\Admin\NetworkController;
use App\Http\Controllers\Admin\FraudReviewController;
use App\Http\Controllers\Admin\AdminCbeKpiController;
use App\Http\Controllers\Admin\AdminCbeMembersController;
use App\Http\Controllers\Admin\AdminCbeCustomersController;
use App\Http\Controllers\Admin\AdminCbeDonorsController;
use App\Http\Controllers\Admin\AdminCbeHierarchyNodeController;
use App\Http\Controllers\Admin\AdminGladeTierController;
use App\Http\Controllers\Admin\AdminCbeAppointmentsController;
use App\Http\Controllers\Admin\AdminCbeParticipationController;
use App\Http\Controllers\Admin\AdminCbeReceiptController;
use App\Http\Controllers\GL\TransactionController as GLTransaction;
use App\Http\Controllers\GL\CustomerController as GLCustomer;
use App\Http\Controllers\Shared\CustomerController as SharedCustomer;
use App\Http\Controllers\Shared\PersonalReminderController;
use App\Http\Controllers\Shared\RenewalQuotationController;
use App\Http\Controllers\RenewalResponseController;
use App\Http\Controllers\GL\CommissionController as GLCommission;
use App\Http\Controllers\GL\ProfileController as GLProfile;
use App\Http\Controllers\GL\NetworkController as GLNetwork;
use App\Http\Controllers\TL\ProfileController as TLProfile;
use App\Http\Controllers\Introducer\ProfileController as IntroducerProfile;
use App\Http\Controllers\Shared\EarningLedgerController;

// GUEST ONLY
Route::middleware('guest:agent')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('auth.login');
    // NEW 22 Jul 2026 — security hardening: the existing per-account
    // lockout (5 failed attempts -> 30 min lock, in AuthController)
    // only ever throttled a KNOWN account. It did nothing to stop
    // unlimited email-enumeration attempts against unknown accounts,
    // or an attacker credential-stuffing many different accounts in
    // parallel from one IP. Laravel's built-in `throttle` middleware
    // keys by IP (for guests), so this closes that gap with zero new
    // code/dependency — 6 attempts per minute per IP.
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login.post')->middleware('throttle:6,1');
    // NEW 3 Aug 2026 — AI Assistant, login-page (guest) mode. Same
    // throttle pattern as /login above — a chat endpoint is just as
    // worth rate-limiting per IP.
    Route::post('/ai-assistant/chat-guest', [\App\Http\Controllers\Shared\AiAssistantController::class, 'chat'])->name('ai-assistant.chat-guest')->middleware('throttle:20,1');
    // NEW 18 Aug 2026 — per Chris: CBE members (e.g. Tao group, Chinese-
    // speaking) need the LOGIN PAGE ITSELF in their language, before they
    // even have an account session. No agent exists yet at this point, so
    // this just stores the choice in the session; SetAgentLocale reads it
    // as a fallback when nobody is logged in. Kept separate from
    // LanguageController::quickSwitch (that one requires an authenticated
    // agent and writes to agents.preferred_language).
    Route::post('/language/guest-switch', [LanguageController::class, 'guestSwitch'])->name('language.guest-switch');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

// NEW 8 Aug 2026 (Task #92) — Vendor Portal login/registration. Own
// guard ('vendor'), own guest middleware — completely separate from the
// agent auth flow above.
Route::middleware('guest:vendor')->group(function () {
    Route::get('/vendor/login', [\App\Http\Controllers\Auth\VendorAuthController::class, 'showLogin'])->name('vendor.login');
    Route::post('/vendor/login', [\App\Http\Controllers\Auth\VendorAuthController::class, 'login'])->name('vendor.login.post')->middleware('throttle:6,1');
    Route::get('/vendor/register', [\App\Http\Controllers\Auth\VendorAuthController::class, 'showRegister'])->name('vendor.register');
    Route::post('/vendor/register', [\App\Http\Controllers\Auth\VendorAuthController::class, 'register'])->name('vendor.register.post')->middleware('throttle:10,1');
    // NEW 8 Aug 2026 — reached via the emailed link after Admin approves
    // a vendor's documents (or Admin creates a login directly). Visiting
    // this link IS the email verification step.
    Route::get('/vendor/set-password/{token}', [\App\Http\Controllers\Auth\VendorAuthController::class, 'showSetPassword'])->name('vendor.set-password.show');
    Route::post('/vendor/set-password/{token}', [\App\Http\Controllers\Auth\VendorAuthController::class, 'setPassword'])->name('vendor.set-password.post')->middleware('throttle:10,1');
});
Route::post('/vendor/logout', [\App\Http\Controllers\Auth\VendorAuthController::class, 'logout'])->name('vendor.logout');

// NEW 12 Aug 2026 — pre-approval vendor Q&A / communication log. No
// auth guard — the vendor has no login yet, so access is controlled
// entirely by possessing the unguessable emailed token (same trust
// model as vendor.set-password above).
Route::get('/vendor/pending-qa/{token}', [\App\Http\Controllers\Vendor\VendorPendingQaController::class, 'show'])->name('vendor.pending-qa.show');
Route::post('/vendor/pending-qa/{token}', [\App\Http\Controllers\Vendor\VendorPendingQaController::class, 'reply'])->name('vendor.pending-qa.reply')->middleware('throttle:20,1');
Route::get('/vendor/pending-qa/{token}/message/{messageId}/attachment', [\App\Http\Controllers\Vendor\VendorPendingQaController::class, 'messageAttachment'])->name('vendor.pending-qa.attachment');

// NEW 9 Aug 2026 — Video Library streaming. Deliberately public/no-auth
// (unauthenticated vendor prospects need to watch the intro video on the
// registration page) — only ACTIVE videos are servable to guests; the
// controller itself checks status and allows Admin to preview INACTIVE
// ones. Uses response()->file() for Range-request support (seeking).
Route::get('/video-library/{id}/stream', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'stream'])->name('video-library.stream');

// NEW 8 Aug 2026 (Task #93) — Vendor Portal (logged-in vendor only).
// CHANGED 13 Aug 2026 — added vendor.restrict: locks a RESTRICTED-
// status vendor (mandatory docs verified, password set, but not yet
// Admin-approved) down to the application-status screen only, see
// RestrictVendorPortalAccess.
Route::middleware(['auth:vendor', 'vendor.restrict'])->prefix('vendor')->name('vendor.')->group(function () {
    // NEW 13 Aug 2026 — the ONE screen a RESTRICTED vendor can reach:
    // status, communication thread (same backbone the admin-side
    // Vendor Onboarding Workflow and the emailed token-link page use),
    // and document upload. Declared first/plainly named so it reads
    // clearly as the restricted-access landing page.
    Route::get('application-status', [\App\Http\Controllers\Vendor\VendorApplicationStatusController::class, 'show'])->name('application-status');
    Route::post('application-status/message', [\App\Http\Controllers\Vendor\VendorApplicationStatusController::class, 'sendMessage'])->name('application-status.message');
    Route::get('application-status/message/{messageId}/attachment', [\App\Http\Controllers\Vendor\VendorApplicationStatusController::class, 'messageAttachment'])->name('application-status.attachment');

    Route::get('portal', [\App\Http\Controllers\Vendor\VendorPortalController::class, 'index'])->name('portal.index');
    // NEW 8 Aug 2026 — Vendor Management Phase 1 (spec Section 10, Vendor Profile).
    Route::get('profile', [\App\Http\Controllers\Vendor\VendorPortalController::class, 'profile'])->name('profile');
    Route::put('profile', [\App\Http\Controllers\Vendor\VendorPortalController::class, 'updateProfile'])->name('profile.update');
    // REMOVED 8 Aug 2026 per Chris: "remove everything ... i want to redo
    // and revamp" — the Offer Request feature (offers/create, offers
    // store, portal.my-offers, and the whole Admin approval side below)
    // is gone until he brings back a fully re-discussed requirement.
    // VendorPortalController@index was simplified to match (see that
    // file) — Vendor Portal itself, and vendor login/registration, are
    // untouched; only the offer-submission part of it is removed.

    // NEW 8 Aug 2026 — "Carolyn help to write" for vendors (Submit Offer
    // screen). Same shared endpoint agents use, just under the vendor
    // guard/prefix so it's reachable from the Vendor Portal too.
    Route::post('write-assist', [\App\Http\Controllers\Shared\AiWriteAssistController::class, 'assist'])->name('write-assist')->middleware('throttle:20,1');

    // NEW 10 Aug 2026 — per Chris: "all agents including vendor is
    // allow to send attachment...marketing documents...to admin." Same
    // ContentSubmissionController the agent side uses — it detects
    // auth('vendor') to know it's a vendor submitting.
    Route::get('submit-content', [\App\Http\Controllers\Shared\ContentSubmissionController::class, 'create'])->name('content-submission.create');
    Route::post('submit-content', [\App\Http\Controllers\Shared\ContentSubmissionController::class, 'store'])->name('content-submission.store');
    Route::get('my-submissions', [\App\Http\Controllers\Shared\ContentSubmissionController::class, 'index'])->name('content-submission.index');

    // NEW 12 Aug 2026 — per Chris: proper redo of vendor-submitted rebate
    // program applications, with an auto-generated submission number and
    // version tracking on resubmission (see VendorRebateApplicationController).
    Route::get('rebate-applications', [\App\Http\Controllers\Vendor\VendorRebateApplicationController::class, 'index'])->name('rebate-applications.index');
    Route::post('rebate-applications', [\App\Http\Controllers\Vendor\VendorRebateApplicationController::class, 'store'])->name('rebate-applications.store');
    Route::get('rebate-applications/{applicationNumber}/history', [\App\Http\Controllers\Vendor\VendorRebateApplicationController::class, 'history'])->name('rebate-applications.history');
    Route::post('rebate-applications/{applicationNumber}/revise', [\App\Http\Controllers\Vendor\VendorRebateApplicationController::class, 'revise'])->name('rebate-applications.revise');

    // NEW 14 Aug 2026 — per Chris: "build OTP-click flow and complete
    // the entire approval process because i will login
    // chrisyap@mybbs.com.my and click/type the OTP acceptance number."
    Route::get('agreement', [\App\Http\Controllers\Vendor\VendorAgreementController::class, 'show'])->name('agreement');
    Route::get('agreement/file', [\App\Http\Controllers\Vendor\VendorAgreementController::class, 'file'])->name('agreement.file');
    Route::post('agreement/send-otp', [\App\Http\Controllers\Vendor\VendorAgreementController::class, 'sendOtp'])->name('agreement.send-otp');
    Route::post('agreement/accept', [\App\Http\Controllers\Vendor\VendorAgreementController::class, 'accept'])->name('agreement.accept');
});

// NEW 3 Aug 2026 — Carolyn's real /speak endpoint lives HERE (not
// routes/api.php) specifically because it now needs to know which agent
// (if any) is logged in, to use THEIR own connected voice provider —
// requires a real session, which the 'api' group deliberately doesn't
// have. Reachable by guests too (Carolyn greets pre-login), but guests
// always resolve to the free browser voice — see AiVoiceController::speak().
Route::post('/ai-assistant/speak', [\App\Http\Controllers\Shared\AiVoiceController::class, 'speak'])->name('ai-assistant.speak')->middleware('throttle:20,1');

// NEW 3 Aug 2026 — AI Guided Navigation (design: docs/ai-guided-navigation-design.md).
// Session-bearing (CSRF applies) on purpose — unlike the public ElevenLabs
// proxy, a guidance session belongs to one browser tab's journey and is
// used by both guest pages (e.g. affiliate registration) and, later,
// logged-in pages — so it is NOT restricted to the guest:agent group.
Route::post('/ai-assistant/guide/start', [\App\Http\Controllers\Shared\AiGuidanceController::class, 'start'])->name('ai-guidance.start')->middleware('throttle:20,1');
Route::post('/ai-assistant/guide/next', [\App\Http\Controllers\Shared\AiGuidanceController::class, 'next'])->name('ai-guidance.next')->middleware('throttle:30,1');

// NEW 15 Jul 2026 — no middleware here on purpose. setPassword() logs the
// agent out BEFORE redirecting here (so they land on a login page, not
// the dashboard), so this page must be reachable while logged out. It
// only reads flash session data set by setPassword() — no auth needed.
Route::get('/verification-success', [AuthController::class, 'verificationSuccess'])->name('auth.verification-success');

// -------------------------------------------------------
// NEW 03 Jul 2026 — Registration + Email Verification flow
// Public routes — no login required (person isn't an agent yet)
// -------------------------------------------------------
Route::middleware('guest:agent')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('auth.register');
    // NEW 22 Jul 2026 — security hardening: no rate limit existed here
    // before — an open door for registration spam and, since this
    // sends verification email, a way to mail-bomb arbitrary
    // addresses. 10 attempts per minute per IP is generous for a
    // genuine person filling the form, but stops automated abuse.
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register.post')->middleware('throttle:10,1');

    // NEW — public branded self-registration for Special Privilege
    // Groups (Tekun, Felda, Police Cooperative, etc.) — a prospect
    // joins as Introducer directly under that group's one TL, no
    // sponsor choice needed.
    Route::get('/join/{slug}', [SpecialGroupController::class, 'joinPage'])->name('special-group.join');
    Route::post('/join/{slug}', [SpecialGroupController::class, 'joinSubmit'])->name('special-group.join.submit');

    // NEW — branded login page per Organization Rewards Group, showing
    // that group's own logo. Submits to the SAME existing login logic.
    Route::get('/login-as/{slug}', [SpecialGroupController::class, 'loginPage'])->name('special-group.login');
    Route::post('/login-as/{slug}', [SpecialGroupController::class, 'loginSubmit'])->name('special-group.login.post');

    // NEW 27 Aug 2026 — per Chris: a totally separate CBE-only front
    // door at /glade (branded "GLADE"), same agents table/credentials
    // as every other login page here. Lets in GeneralLink's 3 platform
    // Admin accounts AND any CBE hierarchy node's own officers (see
    // cbe_node_officers) — everyone else is rejected even with valid
    // credentials. Auto-lands Admin in the cross-org CBE KPI dashboard,
    // and a node officer straight into their own node's KPI dashboard —
    // see GladePortalController::landingFor().
});

// FIXED 27 Sep 2026 -- per Chris: "i login in glade it give me this" (General Link
// KPI Dashboard). /glade sat inside the 'guest:agent' group above, so when the
// browser still had a valid login, Laravel's guest middleware bounced /glade to the
// General Link dashboard BEFORE GladePortalController::loginPage() could send the
// user to the CBE main menu (glade.home). Moved out of that group: loginPage()
// already handles an existing login itself (-> glade.home).
Route::get('/glade', [GladePortalController::class, 'loginPage'])->name('glade.login');
Route::post('/glade', [GladePortalController::class, 'loginSubmit'])->name('glade.login.post');

// NEW 28 Aug 2026 — per Chris: "when i login the default is the main
// menu, not this" — Admin and a CBE officer were both landing straight
// inside a specific report (CBE KPI Dashboard / Exec Dashboard) right
// after logging in at /glade, with no home screen in between. This is
// that home screen — reachable by anyone already logged in (Admin or
// officer), not gated behind 'guest:agent' like the login routes above.
Route::middleware('auth:agent')->get('/glade/home', [GladePortalController::class, 'home'])->name('glade.home');
Route::middleware('auth:agent')->get('/glade/enter', [GladePortalController::class, 'enter'])->name('glade.enter');
Route::get('/verify-pending', [AuthController::class, 'verifyPending'])->name('auth.verify-pending');
Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('auth.verify-email');
Route::get('/verify-email-change/{token}', [AuthController::class, 'verifyEmailChange'])->name('auth.verify-email-change');
Route::get('/affiliate/lookup', [AffiliateController::class, 'lookup'])->name('affiliate.lookup');
Route::get('/affiliate/lookup-by-token', [AffiliateController::class, 'lookupByToken'])->name('affiliate.lookup-by-token');

// NEW 25 Jul 2026 — Growth & Outreach Center (task #214). Public agent
// "business card" page — no login required, reachable by anyone with
// the link. Only renders if the agent has opted in and published it
// (see AgentPublicPageController).
Route::get('/agent/{agentCode}', [\App\Http\Controllers\AgentPublicPageController::class, 'show'])->name('agent.public-profile');

// REPLACED 25 Jul 2026 — Survey Management Module Phase 2 (task #232).
// Public, no-login respondent form — reached via the link/QR/embed
// built in Admin\SurveyController::distribute() or a targeted send
// from Shared\SurveySendController. Per the spec's Core Business
// Principle, only internal agents ever log in; respondents never do.
Route::get('/survey/{token}', [\App\Http\Controllers\PublicSurveyController::class, 'show'])->name('public.survey.show');
Route::post('/survey/{token}', [\App\Http\Controllers\PublicSurveyController::class, 'submit'])->name('public.survey.submit');
Route::get('/survey/{token}/thank-you', [\App\Http\Controllers\PublicSurveyController::class, 'thankYou'])->name('public.survey.thankyou');

// NEW 25 Jul 2026 — Survey Management, real Google Forms integration
// (task #227 follow-up, #240). Not under /admin — the redirect URI
// registered in Google Cloud Console is the bare path
// /google/oauth/callback (see .env GOOGLE_REDIRECT_URI), so this route
// must live at that exact path. Still Admin-only via middleware, since
// Chris stays logged in as Admin in the same browser across the
// redirect to Google and back.
Route::middleware(['auth:agent', 'role:ADMIN'])->group(function () {
    Route::get('/google/connect', [\App\Http\Controllers\GoogleAuthController::class, 'connect'])->name('google.connect');
    Route::get('/google/oauth/callback', [\App\Http\Controllers\GoogleAuthController::class, 'callback'])->name('google.oauth.callback');
    Route::post('/google/disconnect', [\App\Http\Controllers\GoogleAuthController::class, 'disconnect'])->name('google.disconnect');
});

// -------------------------------------------------------
// NEW 18 Jul 2026 — customer-facing renewal reminder response. No
// login — reached via a signed link emailed by the SendRenewalReminders
// command, expires automatically (Laravel signed-URL TTL). See
// RenewalResponseController for why this is deliberately not a real
// login.
// -------------------------------------------------------
Route::get('/renewal/{policyId}', [RenewalResponseController::class, 'show'])->name('renewal.response.show');
Route::post('/renewal/{policyId}', [RenewalResponseController::class, 'respond'])->name('renewal.response.submit');

// -------------------------------------------------------
// NEW — Public postcode lookup for /register (Postcode -> City/State).
// Reads the SAME malaysia_postcodes table as the Admin postcode-lookup
// route (no duplicate data) — this route just makes it reachable
// without being logged in, same reasoning as affiliate.lookup above.
// -------------------------------------------------------
// NEW 28 Sep 2026 — member file item 31: entity QR join (public page; Join needs a login)
Route::get('/cbe-join/{token}', [\App\Http\Controllers\JoinController::class, 'show'])->name('join.show');
Route::middleware('auth:agent')->post('/cbe-join/{token}', [\App\Http\Controllers\JoinController::class, 'join'])->name('join.post');
Route::get('/register/postcode-lookup', function (\Illuminate\Http\Request $request) {
    $postcode = $request->get('postcode');
    $city     = $request->get('city');
    $partial  = $request->get('partial');

    if ($city) {
        $results = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
            ->where('city', 'like', '%'.$city.'%')
            ->orderBy('city')->limit(20)->get();
        return response()->json($results);
    }

    if ($partial) {
        $results = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
            ->where('postcode', 'like', $postcode.'%')
            ->orderBy('postcode')->limit(10)->get();
        return response()->json($results);
    }

    $result = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
        ->where('postcode', $postcode)->first();
    return response()->json($result ?: ['city' => '', 'state' => '']);
})->name('register.postcode-lookup');

// These require the agent to be logged in — verifyEmail() logs them in
// automatically right before redirecting here.
Route::middleware(['auth:agent', 'glade.sidebar'])->group(function () {
    Route::get('/set-password', [AuthController::class, 'showSetPassword'])->name('auth.set-password');
    Route::post('/set-password', [AuthController::class, 'setPassword'])->name('auth.set-password.post');

    // Kept for backward compatibility with the 5 existing accounts that
    // already use security phrase — not used by new registrations (Decision 3)
    Route::get('/security-phrase', [AuthController::class, 'showSecurityPhrase'])->name('auth.security-phrase');
    Route::post('/security-phrase', [AuthController::class, 'saveSecurityPhrase'])->name('auth.security-phrase.save');

    // NEW 22 Jul 2026 — quick-switch language control (globe icon),
    // TL/Introducer only (enforced in the controller, not here).
    Route::post('/language/quick-switch', [LanguageController::class, 'quickSwitch'])->name('language.quick-switch');

    // NEW 3 Aug 2026 — AI Assistant, logged-in mode. Reachable by all 4
    // roles (Admin/GL/TL/Introducer) — role-based scoping is enforced
    // inside AiAssistantController/AiAssistantService, not by route
    // middleware here.
    Route::post('/ai-assistant/chat', [\App\Http\Controllers\Shared\AiAssistantController::class, 'chat'])->name('ai-assistant.chat')->middleware('throttle:20,1');
    // NEW 19 Sep 2026 -- "AI Accountant", per Chris: a separate, focused
    // agent from Carolyn for Chart of Accounts / GL Code questions
    // (Phase 1 of the AI Master Data Assistant). See AiAccountantService.
    Route::post('/ai-accountant/chat', [\App\Http\Controllers\Shared\AiAccountantController::class, 'chat'])->name('ai-accountant.chat')->middleware('throttle:20,1');
    // NEW 5 Aug 2026 — proactive alerts (badge count + once-a-day popup summary). See ProactiveAlertService.
    Route::get('/ai-assistant/alerts', [\App\Http\Controllers\Shared\AiAssistantController::class, 'alerts'])->name('ai-assistant.alerts')->middleware('throttle:60,1');
    // NEW 8 Aug 2026 — "Carolyn help to write," reachable by any logged-in
    // agent role, wired into every "compose a message" screen (Notice
    // Board, Offer Requests, WhatsApp, Help Desk, Broadcast Campaigns,
    // Contests, Public Profile bio, Surveys). See Shared\AiWriteAssistController.
    Route::post('/ai-write-assist', [\App\Http\Controllers\Shared\AiWriteAssistController::class, 'assist'])->name('ai-write-assist')->middleware('throttle:20,1');

    // NEW — Notification bell, shared across every role.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // NEW — Earning Income Wallet, shared across every role. Bank
    // details collected fresh at withdrawal time only, never saved to
    // profile (per confirmed legal/privacy requirement).
    Route::get('/wallet', [WalletController::class, 'show'])->name('wallet.show');
    Route::post('/wallet/withdraw', [WalletController::class, 'submitRequest'])->name('wallet.withdraw');
    Route::get('/wallet/confirm/{requestId}', [WalletController::class, 'showConfirmCode'])->name('wallet.confirm-code');
    Route::post('/wallet/confirm/{requestId}', [WalletController::class, 'confirmCode'])->name('wallet.confirm-code.post');

    // NEW 15 Sep 2026 — per Chris: member-facing appointment booking,
    // shared across every role — any agent can be a member of a CBE
    // community and book a Sensei/Consultant/Legal Advisor appointment.
    // Always books an existing practitioner's real open slot; nothing
    // here is typed in manually.
    Route::get('/book-appointment', [\App\Http\Controllers\Shared\BookingController::class, 'index'])->name('book-appointment');
    Route::get('/book-appointment/type/{typeId}', [\App\Http\Controllers\Shared\BookingController::class, 'practitioners'])->name('book-appointment.practitioners');
    Route::get('/book-appointment/practitioner/{profileId}', [\App\Http\Controllers\Shared\BookingController::class, 'calendar'])->name('book-appointment.calendar');
    Route::get('/book-appointment/practitioner/{profileId}/slots', [\App\Http\Controllers\Shared\BookingController::class, 'slots'])->name('book-appointment.slots');
    Route::post('/book-appointment/practitioner/{profileId}/book', [\App\Http\Controllers\Shared\BookingController::class, 'book'])->name('book-appointment.book');
    Route::get('/my-appointments', [\App\Http\Controllers\Shared\BookingController::class, 'myAppointments'])->name('my-appointments');
    Route::post('/my-appointments/{bookingId}/cancel', [\App\Http\Controllers\Shared\BookingController::class, 'cancel'])->name('my-appointments.cancel');

    // NEW 21 Jul 2026 — Document Credit Wallet, shared across every
    // role (Admin does not use this — Admin never uploads a document
    // for extraction — but is not blocked from viewing it either).
    Route::get('/document-credit', [DocumentCreditController::class, 'show'])->name('document-credit.show');
    Route::post('/document-credit/topup', [DocumentCreditController::class, 'submitTopup'])->name('document-credit.topup');
    Route::post('/document-credit/reminder-threshold', [DocumentCreditController::class, 'updateReminderThreshold'])->name('document-credit.reminder-threshold');

    // NEW 21 Jul 2026 — Team Document Credit: read-only downline view
    // for GL/TL/Introducer (Admin uses the separate admin.document-credit.*
    // screens instead, which also have approve/settings actions).
    Route::get('/team-document-credit', [TeamDocumentCreditController::class, 'index'])->name('team-document-credit.index');
    Route::get('/team-document-credit/typeahead', [TeamDocumentCreditController::class, 'typeahead'])->name('team-document-credit.typeahead');
    Route::get('/team-document-credit/agent/{agentId}', [TeamDocumentCreditController::class, 'agentDetail'])->name('team-document-credit.agent-detail');
    Route::post('/team-document-credit/agent/{agentId}/transfer', [TeamDocumentCreditController::class, 'transfer'])->name('team-document-credit.transfer');

    // REDESIGNED 21 Jul 2026 — Help Desk (was agent-to-Admin-only
    // "Enquiries"). One controller, one route set, for EVERY role
    // including Admin: message your own upline/downline (never
    // sideways), reply, Cc, flag, close/reopen, attachments. Admin is
    // just another party here — no separate admin.help-desk.* routes.
    Route::get('/help-desk', [HelpDeskController::class, 'index'])->name('help-desk.index');
    Route::post('/help-desk', [HelpDeskController::class, 'store'])->name('help-desk.store');
    Route::get('/help-desk/recipient-typeahead', [HelpDeskController::class, 'recipientTypeahead'])->name('help-desk.recipient-typeahead');
    Route::get('/help-desk/cc-options', [HelpDeskController::class, 'ccOptions'])->name('help-desk.cc-options');
    Route::get('/help-desk/{threadId}', [HelpDeskController::class, 'show'])->name('help-desk.show');
    Route::post('/help-desk/{threadId}/reply', [HelpDeskController::class, 'reply'])->name('help-desk.reply');
    Route::post('/help-desk/{threadId}/flag', [HelpDeskController::class, 'toggleFlag'])->name('help-desk.flag');
    Route::post('/help-desk/{threadId}/close', [HelpDeskController::class, 'close'])->name('help-desk.close');
    Route::post('/help-desk/{threadId}/reopen', [HelpDeskController::class, 'reopen'])->name('help-desk.reopen');
    Route::get('/help-desk/attachment/{messageId}', [HelpDeskController::class, 'attachment'])->name('help-desk.attachment');
    // NEW 22 Jul 2026 — TL/Introducer-only message translation + "Fix
    // Wording" rephrase, both paid Claude API calls confirmed in the
    // UI first (see HelpDeskController::translateMessage/rephrase).
    Route::post('/help-desk/message/{messageId}/translate', [HelpDeskController::class, 'translateMessage'])->name('help-desk.message.translate');
    Route::post('/help-desk/rephrase', [HelpDeskController::class, 'rephrase'])->name('help-desk.rephrase');

    // NEW 21 Jul 2026 — Notice Board, shared across every role: one-way
    // broadcast announcements from Admin (important updates, promotions,
    // holiday/festive greetings, contact info, bank account number,
    // customer hotline). Read-only for agents — posting is Admin-only,
    // see the admin.notice-board.* routes below.
    Route::get('/notice-board', [NoticeBoardController::class, 'index'])->name('notice-board.index');
    Route::get('/notice-board/typeahead', [NoticeBoardController::class, 'typeahead'])->name('notice-board.typeahead');
    Route::get('/notice-board/attachment/{noticeId}', [NoticeBoardController::class, 'attachment'])->name('notice-board.attachment');

    // NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 1 (Task #84).
    Route::get('/notification-preferences', [\App\Http\Controllers\Shared\NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::post('/notification-preferences', [\App\Http\Controllers\Shared\NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    // REMOVED 8 Aug 2026 per Chris: "remove everything ... i want to redo
    // and revamp" — the agent-submits-on-behalf-of-vendor Offer Request
    // routes are gone along with the rest of the feature.

    // NEW 9 Aug 2026 — Vendor Rebate Offer Search, every logged-in role
    // (including Admin) per Chris: agents search active vendor rebate
    // offers to pitch customers. Deliberately shows ONLY vendor/product/
    // rebate details — never address or vendor contact info
    // (RebateOfferSearchController enforces this at the query level, not
    // just by hiding columns in the view).
    Route::get('/rebate-offers', [\App\Http\Controllers\Shared\RebateOfferSearchController::class, 'index'])->name('rebate-offers.search');
    Route::get('/rebate-offers/typeahead', [\App\Http\Controllers\Shared\RebateOfferSearchController::class, 'typeahead'])->name('rebate-offers.typeahead');

    // NEW 12 Aug 2026 — per Chris: GL/TL/Introducer write reviews (star
    // rating + comment) on a vendor's rebate program, viewable by
    // everyone (including Admin, read-only for Admin — see
    // VendorReviewController::store's role gate). Reached from Rebate
    // Offer Search and from the vendor's/product's own record on the
    // Admin side.
    Route::get('/vendor-reviews/{vendorId}', [\App\Http\Controllers\Shared\VendorReviewController::class, 'index'])->name('vendor-reviews.index');
    Route::post('/vendor-reviews/{vendorId}', [\App\Http\Controllers\Shared\VendorReviewController::class, 'store'])->name('vendor-reviews.store');

    // NEW — Calendar viewing, shared across every role (Admin manages
    // via separate Admin-only routes above; this is read-only viewing).
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // NEW 19 Jul 2026 — Yearly Projected Insurance Renewal Forecast, per
    // Chris: shared across every role, scoped internally via
    // DataScopeService (same as Calendar above).
    Route::get('/renewal-forecast', [\App\Http\Controllers\Shared\RenewalForecastController::class, 'index'])->name('renewal-forecast.index');

    // NEW 23 Jul 2026 — per Chris: with 10,000+ Group Leaders, 600,000+
    // Team Leaders and a million Introducers, a plain <select> listing
    // every candidate is unworkable. Typeahead search-as-you-type
    // instead, same debounced fetch()+limit(20) pattern already used by
    // Help Desk / Document Credit / Customer search elsewhere.
    Route::get('/renewal-forecast/agent-typeahead', [\App\Http\Controllers\Shared\RenewalForecastController::class, 'agentTypeahead'])->name('renewal-forecast.agent-typeahead');

    // NEW — Organization Rewards Group actions. Controller enforces exact
    // role checks internally (GL-only for appointTL, GL/TL for
    // addIntroducer), so these live in the shared group.
    Route::get('special-group/appoint-tl', [SpecialGroupController::class, 'appointTLForm'])->name('special-group.appoint-tl');
    Route::post('special-group/appoint-tl', [SpecialGroupController::class, 'appointTL'])->name('special-group.appoint-tl.store');
    Route::get('special-group/add-introducer', [SpecialGroupController::class, 'addIntroducerForm'])->name('special-group.add-introducer');
    Route::post('special-group/add-introducer', [SpecialGroupController::class, 'addIntroducer'])->name('special-group.add-introducer.store');

    // NEW 25 Jul 2026 (task #207) — Breakaway Bonus claims tracking.
    // Controller enforces ADMIN/GROUP_LEADER-only access internally
    // (only a Group Leader can ever be an original_gl_agent_id).
    Route::get('/breakaway-claims', [BreakawayBonusClaimController::class, 'index'])->name('breakaway-claims.index');
    Route::get('/breakaway-claims/gl-typeahead', [BreakawayBonusClaimController::class, 'glTypeahead'])->name('breakaway-claims.gl-typeahead');
    Route::post('/breakaway-claims/{claimId}/mark-claimed', [BreakawayBonusClaimController::class, 'markClaimed'])->name('breakaway-claims.mark-claimed');
    Route::get('/breakaway-claims/{claimId}/voucher', [BreakawayBonusClaimController::class, 'voucher'])->name('breakaway-claims.voucher');

    // NEW 25 Jul 2026 — Growth & Outreach Center (task #210). Every
    // role (including Admin) can view/share their own referral link.
    Route::get('/referral-link', [ReferralLinkController::class, 'index'])->name('referral-link.index');
    Route::get('/referral-link/download-qr', [ReferralLinkController::class, 'downloadQr'])->name('referral-link.download-qr');

    // NEW 10 Aug 2026 — per Chris: "all agents including vendor is
    // allow to send attachment...marketing documents...to admin."
    // Any agent role can submit marketing content for Admin review —
    // vendor's equivalent routes are in the vendor guard group below.
    Route::get('/growth/submit-content', [\App\Http\Controllers\Shared\ContentSubmissionController::class, 'create'])->name('content-submission.create');
    Route::post('/growth/submit-content', [\App\Http\Controllers\Shared\ContentSubmissionController::class, 'store'])->name('content-submission.store');
    Route::get('/growth/my-submissions', [\App\Http\Controllers\Shared\ContentSubmissionController::class, 'index'])->name('content-submission.index');

    // NEW 25 Jul 2026 — Growth & Outreach Center (task #211), agent-facing.
    Route::get('/growth/contests', [SharedContestController::class, 'index'])->name('growth-contests.index');

    // REPLACED 25 Jul 2026 — Survey Management Module Phase 2 (task
    // #232). Agent-facing "Send Survey" — any role can send an ACTIVE
    // survey to their own customers, tracked per person. Reuses the
    // {rolePrefix}.customers.typeahead endpoint already registered in
    // each role-prefixed group below for the customer picker.
    Route::get('/survey-send', [\App\Http\Controllers\Shared\SurveySendController::class, 'create'])->name('survey-send.create');
    Route::post('/survey-send', [\App\Http\Controllers\Shared\SurveySendController::class, 'store'])->name('survey-send.store');

    // NEW 25 Jul 2026 — Growth & Outreach Center (task #212). My Badges
    // — REPLACED 25 Jul 2026 by Customer KPI (task #225) per Chris:
    // "remove my badge confusing replace with customer kpi." Routes/
    // controller left in place (unlinked from the menu) rather than
    // deleted, in case Chris wants any part of it back.
    Route::get('/growth/badges', [BadgeController::class, 'index'])->name('growth-badges.index');
    Route::get('/growth/badges/{type}/detail', [BadgeController::class, 'detail'])->name('growth-badges.detail');

    // NEW 25 Jul 2026 — Customer KPI Dashboard (task #225). REPLACES My
    // Badges in the Network Tree menu. Top 3 Sales / Top 3 Earning
    // Income customers, filterable by Category/Type/Occupation/Source/
    // Status, drill-down cascade Company -> Group -> TL -> Introducer
    // (same mechanics as Renewal Forecast — reuses its typeahead
    // endpoint below), and a transaction-level detail per customer.
    Route::get('/customer-kpi', [\App\Http\Controllers\Shared\CustomerKpiController::class, 'index'])->name('customer-kpi.index');
    Route::get('/customer-kpi/list', [\App\Http\Controllers\Shared\CustomerKpiController::class, 'list'])->name('customer-kpi.list');
    // NEW 25 Jul 2026 — per Chris: Box 3's Top-3 states/statuses need a
    // full "View All" drill-down (state/status, customer count, total
    // amount, then a View into that one state/status's customer list —
    // see customer-kpi.list?state=/?status_id= above), sitting between
    // the Dashboard and that customer list in the back/Prev chain.
    Route::get('/customer-kpi/states', [\App\Http\Controllers\Shared\CustomerKpiController::class, 'states'])->name('customer-kpi.states');
    Route::get('/customer-kpi/statuses', [\App\Http\Controllers\Shared\CustomerKpiController::class, 'statuses'])->name('customer-kpi.statuses');
    Route::get('/customer-kpi/{customerId}/detail', [\App\Http\Controllers\Shared\CustomerKpiController::class, 'detail'])->name('customer-kpi.detail');

    // NEW 25 Jul 2026 — Growth & Outreach Center (task #213). Admin sees
    // every referral company-wide; every other role sees only their own
    // (enforced inside the controller).
    Route::get('/customer-referrals', [CustomerReferralController::class, 'index'])->name('customer-referrals.index');
    Route::get('/customer-referrals/create', [CustomerReferralController::class, 'create'])->name('customer-referrals.create');
    Route::post('/customer-referrals', [CustomerReferralController::class, 'store'])->name('customer-referrals.store');
    Route::post('/customer-referrals/{id}/status', [CustomerReferralController::class, 'updateStatus'])->name('customer-referrals.status');
    Route::post('/customer-referrals/{id}/set-reward', [CustomerReferralController::class, 'setReward'])->name('customer-referrals.set-reward');
    Route::post('/customer-referrals/{id}/mark-rewarded', [CustomerReferralController::class, 'markRewarded'])->name('customer-referrals.mark-rewarded');

    // NEW 25 Jul 2026 — Growth & Outreach Center (task #214).
    Route::get('/my-public-profile', [PublicProfilePageController::class, 'edit'])->name('public-profile.edit');
    Route::post('/my-public-profile', [PublicProfilePageController::class, 'update'])->name('public-profile.update');

    // NEW 29 Jul 2026 — Support Tickets (task #259). Per Chris: "make
    // full use of EspoCRM" — Help Desk / Case Management / Complaint
    // Management / Service Request module, one controller shared across
    // every role (scoped internally via DataScopeService::applyToCustomers,
    // same rule as the Customer record itself), synced one-way to
    // EspoCRM's free, core Case entity. See EspoCrmService::createCase.
    Route::get('/support-tickets', [\App\Http\Controllers\Shared\SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('/support-tickets/typeahead', [\App\Http\Controllers\Shared\SupportTicketController::class, 'typeahead'])->name('support-tickets.typeahead');
    Route::post('/customers/{id}/tickets', [\App\Http\Controllers\Shared\SupportTicketController::class, 'store'])->name('support-tickets.store');
    Route::post('/tickets/{id}/status', [\App\Http\Controllers\Shared\SupportTicketController::class, 'updateStatus'])->name('support-tickets.update-status');

    // REMOVED 12 Aug 2026 per Chris: "just maintain one, dont confuse" —
    // Help Center (EspoCRM Knowledge Base articles) folded into Help
    // Desk as its "FAQ / Help Articles" tab (see help-desk.index route).

    // NEW 4 Aug 2026 — Enterprise Integration Hub, Phase 1. Every agent
    // connects/tests/disconnects their OWN provider accounts here — never
    // falls back to Chris's credentials. See IntegrationHubController.
    Route::get('/integrations', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'index'])->name('integrations.index');

    // NEW 5 Aug 2026 — Hub vault setup/unlock/lock. MUST be registered
    // before the /integrations/{category} wildcard below, otherwise Laravel
    // would try to match "vault" itself as a category name.
    Route::get('/integrations/vault/setup', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'vaultSetupForm'])->name('integrations.vault.setup');
    Route::post('/integrations/vault/setup', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'vaultSetup'])->name('integrations.vault.setup.post');
    Route::get('/integrations/vault/unlock', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'vaultUnlockForm'])->name('integrations.vault.unlock');
    Route::post('/integrations/vault/unlock', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'vaultUnlock'])->name('integrations.vault.unlock.post');
    Route::post('/integrations/vault/lock', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'vaultLock'])->name('integrations.vault.lock');

    // NEW 5 Aug 2026 — Hub Security page, reached from My Profile: change
    // (know current) or self-reset (forgot it, confirm via login password
    // instead) the Hub password. See IntegrationHubController.
    Route::get('/integrations-security', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'hubSecurity'])->name('integrations.security');
    Route::post('/integrations-security/change', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'changeHubPassword'])->name('integrations.security.change');
    Route::post('/integrations-security/reset', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'resetHubPassword'])->name('integrations.security.reset');

    Route::get('/integrations/{category}', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'category'])->name('integrations.category');
    Route::post('/integrations/{category}/{provider}/connect', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'connect'])->name('integrations.connect');
    Route::post('/integrations/{category}/{provider}/test', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'test'])->name('integrations.test');
    Route::delete('/integrations/{category}/{provider}', [\App\Http\Controllers\Shared\IntegrationHubController::class, 'disconnect'])->name('integrations.disconnect');

    // NEW 6 Aug 2026 — Send WhatsApp Message tool (topbar button, next to
    // the bell). Uses the WhatsApp credentials saved in Integration Hub >
    // Communication > WhatsApp. See WhatsAppController.
    Route::get('/whatsapp/send', [\App\Http\Controllers\Shared\WhatsAppController::class, 'form'])->name('whatsapp.form');
    Route::post('/whatsapp/send', [\App\Http\Controllers\Shared\WhatsAppController::class, 'send'])->name('whatsapp.send')->middleware('throttle:20,1');
    Route::post('/whatsapp/register', [\App\Http\Controllers\Shared\WhatsAppController::class, 'register'])->name('whatsapp.register')->middleware('throttle:10,1');

    // NEW 5 Aug 2026 — "What Carolyn Remembers About You" — transparency
    // + self-service erase for Carolyn's long-term memory. Reached from
    // My Profile > Text Chat. See AiMemoryController/AiMemoryService.
    Route::get('/ai-memory', [\App\Http\Controllers\Shared\AiMemoryController::class, 'index'])->name('ai.memory.index');
    Route::delete('/ai-memory/{memoryId}', [\App\Http\Controllers\Shared\AiMemoryController::class, 'forgetOne'])->name('ai.memory.forget-one');
    Route::delete('/ai-memory', [\App\Http\Controllers\Shared\AiMemoryController::class, 'forgetAll'])->name('ai.memory.forget-all');
});

// GROUP LEADER (GL)
Route::prefix('gl')->name('gl.')->group(function () {
    Route::middleware('guest:agent')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login']);
    });
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware(['auth:agent', 'role:GROUP_LEADER'])->group(function () {
        Route::get('dashboard', [GLDashboard::class, 'index'])->name('dashboard');
        Route::get('dashboard/metrics', [GLDashboard::class, 'metrics'])->name('dashboard.metrics');
        Route::get('dashboard/drilldown', [GLDashboard::class, 'drilldown'])->name('dashboard.drilldown');
        Route::get('dashboard/chart-drilldown', [GLDashboard::class, 'chartDrilldown'])->name('dashboard.chart.drilldown');
        Route::get('network', [GLNetwork::class, 'index'])->name('network');
        Route::get('network/intros/all', [\App\Http\Controllers\GL\IntroducerController::class, 'index'])->name('network.intros');
        Route::get('network/export', [GLNetwork::class, 'exportGroup'])->name('network.export');
        Route::get('network/all/tls', [GLNetwork::class, 'allTLs'])->name('network.all-tls');
        Route::get('network/all/intros', [GLNetwork::class, 'allIntros'])->name('network.all-intros');
        Route::get('network/inactive/tls', [GLNetwork::class, 'inactiveTLs'])->name('network.inactive-tls');
        Route::get('network/inactive/intros', [GLNetwork::class, 'inactiveIntros'])->name('network.inactive-intros');
        Route::get('network/intros/{introId}/transactions', [GLNetwork::class, 'introTransactions'])->name('network.intro.transactions');
        Route::get('network/{tlId}', [GLNetwork::class, 'byTL'])->name('network.tl');
        Route::get('transactions', [GLTransaction::class, 'index'])->name('transactions');
        Route::get('transactions/{id}', [GLTransaction::class, 'show'])->name('transactions.show');
        // NEW 18 Jul 2026 — moved to the Shared controller (same one now
        // used by Admin/TL/Introducer too) so all 4 roles share one
        // implementation instead of GL having its own separate copy.
        Route::get('customers', [SharedCustomer::class, 'index'])->name('customers.index');
        Route::get('customers/typeahead', [SharedCustomer::class, 'typeahead'])->name('customers.typeahead');
        Route::get('customers/{id}', [SharedCustomer::class, 'show'])->name('customers.show');
        Route::get('customers/{id}/edit', [SharedCustomer::class, 'edit'])->name('customers.edit');
        Route::put('customers/{id}', [SharedCustomer::class, 'update'])->name('customers.update');
    Route::post('customers/{id}/deactivate', [SharedCustomer::class, 'deactivate'])->name('customers.deactivate');
    // NEW 19 Jul 2026 — Prospects (personal contacts, no policy yet) +
    // personal follow-up reminders, per Chris.
    Route::get('customers/prospects/create', [SharedCustomer::class, 'createProspect'])->name('customers.prospects.create');
    Route::post('customers/prospects', [SharedCustomer::class, 'storeProspect'])->name('customers.prospects.store');
    Route::post('customers/{id}/reminders', [PersonalReminderController::class, 'store'])->name('customers.reminders.store');
    Route::post('reminders/{id}/done', [PersonalReminderController::class, 'markDone'])->name('reminders.done');
    Route::delete('reminders/{id}', [PersonalReminderController::class, 'destroy'])->name('reminders.destroy');
        // NEW 18 Jul 2026 — Renewal Quotation Requests, GL scope (own group).
        Route::get('renewal-quotations', [RenewalQuotationController::class, 'index'])->name('renewal-quotations.index');
        Route::post('renewal-quotations/{id}/mark-sent', [RenewalQuotationController::class, 'markSent'])->name('renewal-quotations.mark-sent');
        Route::get('commissions', [GLCommission::class, 'index'])->name('commissions.index');
        // NEW 2 Aug 2026 — Earning Income Ledger (Debit/Credit history,
        // separate from the "Earning Income" summary dashboard above).
        // GL scope: himself + his Team Leaders + their Introducers.
        Route::get('earning-ledger', [EarningLedgerController::class, 'index'])->name('earning-ledger');
        Route::get('earning-ledger/statement', [EarningLedgerController::class, 'statement'])->name('earning-ledger.statement');
        Route::get('rewards', [\App\Http\Controllers\GL\RewardController::class, 'index'])->name('rewards');
        Route::get('profile', [GLProfile::class, 'show'])->name('profile.show');
        Route::get('profile/edit', [GLProfile::class, 'edit'])->name('profile.edit');
        Route::put('profile', [GLProfile::class, 'update'])->name('profile.update');
        Route::get('profile/change-password', [GLProfile::class, 'changePasswordPage'])->name('profile.change-password');
        Route::put('profile/password', [GLProfile::class, 'changePassword'])->name('profile.password');

        // NEW — Tier Structure Maintenance, scoped to GL's own downline.
        // Reuses the exact same MasterFileController methods as Admin.
        Route::get('masterfile/team-leaders', [MasterFileController::class, 'teamLeaders'])->name('masterfile.team-leaders');
        Route::get('masterfile/team-leaders/search', [MasterFileController::class, 'teamLeaderSearchForm'])->name('masterfile.team-leaders.search');
        Route::get('masterfile/team-leaders/field-lookup', [MasterFileController::class, 'teamLeaderFieldLookup'])->name('masterfile.team-leaders.field-lookup');
        Route::get('masterfile/team-leaders/{id}/edit', [MasterFileController::class, 'teamLeaderShow'])->name('masterfile.team-leaders.edit');
        Route::put('masterfile/team-leaders/{id}', [MasterFileController::class, 'teamLeaderUpdate'])->name('masterfile.team-leaders.update');
        Route::get('masterfile/introducers', [MasterFileController::class, 'introducers'])->name('masterfile.introducers');
        Route::get('masterfile/introducers/search', [MasterFileController::class, 'introducerSearchForm'])->name('masterfile.introducers.search');
        Route::get('masterfile/introducers/field-lookup', [MasterFileController::class, 'introducerFieldLookup'])->name('masterfile.introducers.field-lookup');
        Route::get('masterfile/introducers/{id}/edit', [MasterFileController::class, 'introducerShow'])->name('masterfile.introducers.edit');
        Route::put('masterfile/introducers/{id}', [MasterFileController::class, 'introducerUpdate'])->name('masterfile.introducers.update');
        // NEW 15 Jul 2026 — Resend Verification, GL scope (own TLs + those TLs' Introducers).
        Route::post('masterfile/agents/{id}/resend-verification', [MasterFileController::class, 'resendVerification'])->name('masterfile.resend-verification');
        Route::get('masterfile/pending-verifications', [MasterFileController::class, 'pendingVerifications'])->name('masterfile.pending-verifications');

        // NEW 16 Jul 2026 — Sales Transaction Maintenance, GL scope (own group).
        Route::get('sales-transactions', [SalesTransactionController::class, 'index'])->name('sales-transactions.index');
        Route::get('sales-transactions/create', [SalesTransactionController::class, 'create'])->name('sales-transactions.create');
        Route::post('sales-transactions', [SalesTransactionController::class, 'store'])->name('sales-transactions.store');
        Route::get('sales-transactions/customer-typeahead', [SalesTransactionController::class, 'customerTypeahead'])->name('sales-transactions.customer-typeahead');
        Route::get('sales-transactions/product-typeahead', [SalesTransactionController::class, 'productTypeahead'])->name('sales-transactions.product-typeahead');
    Route::post('sales-transactions/extract-document', [SalesTransactionController::class, 'extractDocument'])->name('sales-transactions.extract-document');
        Route::post('sales-transactions/extract-document', [SalesTransactionController::class, 'extractDocument'])->name('sales-transactions.extract-document');
        Route::get('sales-transactions/{id}', [SalesTransactionController::class, 'show'])->name('sales-transactions.show');
        Route::get('sales-transactions/document/{documentId}', [SalesTransactionController::class, 'document'])->name('sales-transactions.document');
    Route::get('submission-key', [\App\Http\Controllers\Shared\SubmissionKeyController::class, 'show'])->name('submission-key');
        Route::get('submission-key', [\App\Http\Controllers\Shared\SubmissionKeyController::class, 'show'])->name('submission-key');
    });
});

// TEAM LEADER (TL)
Route::middleware(['auth:agent', 'role:TEAM_LEADER'])->prefix('tl')->name('tl.')->group(function () {
    Route::get('dashboard', [TLDashboard::class, 'index'])->name('dashboard');
    Route::get('dashboard/metrics', [TLDashboard::class, 'metrics'])->name('dashboard.metrics');
    Route::get('dashboard/recent-transactions', [TLDashboard::class, 'recentTransactions'])->name('dashboard.recent-transactions');
    Route::get('dashboard/drilldown', [TLDashboard::class, 'drilldown'])->name('dashboard.drilldown');
    Route::get('dashboard/chart-drilldown', [TLDashboard::class, 'chartDrilldown'])->name('dashboard.chart-drilldown');
    Route::get('dashboard/export', [TLDashboard::class, 'exportTeam'])->name('dashboard.export');
    Route::get('network/all-intros', [\App\Http\Controllers\TL\IntroducerController::class, 'index'])->name('network.all-intros');
    Route::get('promoted-tls', [\App\Http\Controllers\TL\IntroducerController::class, 'promotedTLs'])->name('promoted-tls');
    Route::get('promoted-tls/{id}/transactions', [\App\Http\Controllers\TL\IntroducerController::class, 'promotedTLTransactions'])->name('promoted-tls.transactions');
    Route::get('promoted-tls/{ptlId}/member/{memberId}/transactions', [\App\Http\Controllers\TL\IntroducerController::class, 'promotedTLMemberTransactions'])->name('promoted-tls.member.transactions');
    Route::get('network/inactive-intros', [\App\Http\Controllers\TL\IntroducerController::class, 'index'])->name('network.inactive-intros');
    Route::get('introducers', [\App\Http\Controllers\TL\IntroducerController::class, 'index'])->name('introducers');
    Route::get('introducers/{id}/transactions', [\App\Http\Controllers\TL\IntroducerController::class, 'transactions'])->name('introducers.transactions');
    Route::get('transactions', [\App\Http\Controllers\TL\TransactionController::class, 'index'])->name('transactions');
    Route::get('transactions/{id}', [\App\Http\Controllers\TL\TransactionController::class, 'show'])->name('transactions.show');
    Route::get('rewards', [\App\Http\Controllers\TL\RewardController::class, 'index'])->name('rewards');
    // NEW 2 Aug 2026 — Earning Income Ledger. TL scope: himself + his Introducers.
    Route::get('earning-ledger', [EarningLedgerController::class, 'index'])->name('earning-ledger');
    Route::get('earning-ledger/statement', [EarningLedgerController::class, 'statement'])->name('earning-ledger.statement');
    Route::get('profile', [TLProfile::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [TLProfile::class, 'edit'])->name('profile.edit');
    Route::put('profile', [TLProfile::class, 'update'])->name('profile.update');
    Route::get('profile/change-password', [TLProfile::class, 'changePasswordPage'])->name('profile.change-password');
    Route::put('profile/password', [TLProfile::class, 'changePassword'])->name('profile.password');

    // NEW — Introducer Maintenance only, scoped to TL's own downline.
    Route::get('masterfile/introducers', [MasterFileController::class, 'introducers'])->name('masterfile.introducers');
    Route::get('masterfile/introducers/search', [MasterFileController::class, 'introducerSearchForm'])->name('masterfile.introducers.search');
    Route::get('masterfile/introducers/field-lookup', [MasterFileController::class, 'introducerFieldLookup'])->name('masterfile.introducers.field-lookup');
    Route::get('masterfile/introducers/{id}/edit', [MasterFileController::class, 'introducerShow'])->name('masterfile.introducers.edit');
    Route::put('masterfile/introducers/{id}', [MasterFileController::class, 'introducerUpdate'])->name('masterfile.introducers.update');
    // NEW 15 Jul 2026 — Resend Verification, TL scope (own direct Introducers only).
    Route::post('masterfile/agents/{id}/resend-verification', [MasterFileController::class, 'resendVerification'])->name('masterfile.resend-verification');
    Route::get('masterfile/pending-verifications', [MasterFileController::class, 'pendingVerifications'])->name('masterfile.pending-verifications');

    // NEW 16 Jul 2026 — Sales Transaction Maintenance, TL scope (self + own Introducers).
    Route::get('sales-transactions', [SalesTransactionController::class, 'index'])->name('sales-transactions.index');
    Route::get('sales-transactions/create', [SalesTransactionController::class, 'create'])->name('sales-transactions.create');
    Route::post('sales-transactions', [SalesTransactionController::class, 'store'])->name('sales-transactions.store');
    Route::get('sales-transactions/customer-typeahead', [SalesTransactionController::class, 'customerTypeahead'])->name('sales-transactions.customer-typeahead');
    Route::get('sales-transactions/product-typeahead', [SalesTransactionController::class, 'productTypeahead'])->name('sales-transactions.product-typeahead');
    Route::post('sales-transactions/extract-document', [SalesTransactionController::class, 'extractDocument'])->name('sales-transactions.extract-document');
    Route::get('sales-transactions/{id}', [SalesTransactionController::class, 'show'])->name('sales-transactions.show');
    Route::get('sales-transactions/document/{documentId}', [SalesTransactionController::class, 'document'])->name('sales-transactions.document');
    Route::get('submission-key', [\App\Http\Controllers\Shared\SubmissionKeyController::class, 'show'])->name('submission-key');

    // NEW 18 Jul 2026 — Customer Maintenance, TL scope (self + own Introducers).
    Route::get('customers', [SharedCustomer::class, 'index'])->name('customers.index');
    Route::get('customers/typeahead', [SharedCustomer::class, 'typeahead'])->name('customers.typeahead');
    Route::get('customers/{id}', [SharedCustomer::class, 'show'])->name('customers.show');
    Route::get('customers/{id}/edit', [SharedCustomer::class, 'edit'])->name('customers.edit');
    Route::put('customers/{id}', [SharedCustomer::class, 'update'])->name('customers.update');
    Route::post('customers/{id}/deactivate', [SharedCustomer::class, 'deactivate'])->name('customers.deactivate');
    // NEW 19 Jul 2026 — Prospects (personal contacts, no policy yet) +
    // personal follow-up reminders, per Chris.
    Route::get('customers/prospects/create', [SharedCustomer::class, 'createProspect'])->name('customers.prospects.create');
    Route::post('customers/prospects', [SharedCustomer::class, 'storeProspect'])->name('customers.prospects.store');
    Route::post('customers/{id}/reminders', [PersonalReminderController::class, 'store'])->name('customers.reminders.store');
    Route::post('reminders/{id}/done', [PersonalReminderController::class, 'markDone'])->name('reminders.done');
    Route::delete('reminders/{id}', [PersonalReminderController::class, 'destroy'])->name('reminders.destroy');
    // NEW 18 Jul 2026 — Renewal Quotation Requests, TL scope.
    Route::get('renewal-quotations', [RenewalQuotationController::class, 'index'])->name('renewal-quotations.index');
    Route::post('renewal-quotations/{id}/mark-sent', [RenewalQuotationController::class, 'markSent'])->name('renewal-quotations.mark-sent');
});

// INTRODUCER
Route::middleware(['auth:agent', 'role:INTRODUCER'])->prefix('introducer')->name('introducer.')->group(function () {
    Route::get('dashboard', [IntroducerDashboard::class, 'index'])->name('dashboard');
    Route::get('dashboard/metrics', [IntroducerDashboard::class, 'metrics'])->name('dashboard.metrics');
    Route::get('dashboard/drilldown', [IntroducerDashboard::class, 'drilldown'])->name('dashboard.drilldown');
    Route::get('dashboard/chart-drilldown', [IntroducerDashboard::class, 'chartDrilldown'])->name('dashboard.chart-drilldown');
    Route::get('dashboard/export', [IntroducerDashboard::class, 'exportTeam'])->name('dashboard.export');
    Route::get('recruits', [\App\Http\Controllers\Introducer\RecruitController::class, 'index'])->name('recruits');
    Route::get('recruits/{id}/transactions', [\App\Http\Controllers\Introducer\RecruitController::class, 'transactions'])->name('recruits.transactions');
    Route::get('rewards', [\App\Http\Controllers\Introducer\RewardController::class, 'index'])->name('rewards');
    // NEW 2 Aug 2026 — Earning Income Ledger. Introducer scope: himself + introducers he recruited (any depth).
    Route::get('earning-ledger', [EarningLedgerController::class, 'index'])->name('earning-ledger');
    Route::get('earning-ledger/statement', [EarningLedgerController::class, 'statement'])->name('earning-ledger.statement');
    Route::get('profile', [IntroducerProfile::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [IntroducerProfile::class, 'edit'])->name('profile.edit');
    Route::put('profile', [IntroducerProfile::class, 'update'])->name('profile.update');
    Route::get('profile/change-password', [IntroducerProfile::class, 'changePasswordPage'])->name('profile.change-password');
    Route::put('profile/password', [IntroducerProfile::class, 'changePassword'])->name('profile.password');

    // NEW — Introducer Maintenance, scoped to this Introducer's own
    // direct recruits only.
    Route::get('masterfile/introducers', [MasterFileController::class, 'introducers'])->name('masterfile.introducers');
    Route::get('masterfile/introducers/search', [MasterFileController::class, 'introducerSearchForm'])->name('masterfile.introducers.search');
    Route::get('masterfile/introducers/field-lookup', [MasterFileController::class, 'introducerFieldLookup'])->name('masterfile.introducers.field-lookup');
    Route::get('masterfile/introducers/{id}/edit', [MasterFileController::class, 'introducerShow'])->name('masterfile.introducers.edit');
    Route::put('masterfile/introducers/{id}', [MasterFileController::class, 'introducerUpdate'])->name('masterfile.introducers.update');

    // NEW 16 Jul 2026 — Sales Transaction Maintenance, Introducer scope (self only).
    Route::get('sales-transactions', [SalesTransactionController::class, 'index'])->name('sales-transactions.index');
    Route::get('sales-transactions/create', [SalesTransactionController::class, 'create'])->name('sales-transactions.create');
    Route::post('sales-transactions', [SalesTransactionController::class, 'store'])->name('sales-transactions.store');
    Route::get('sales-transactions/customer-typeahead', [SalesTransactionController::class, 'customerTypeahead'])->name('sales-transactions.customer-typeahead');
    Route::get('sales-transactions/product-typeahead', [SalesTransactionController::class, 'productTypeahead'])->name('sales-transactions.product-typeahead');
    Route::post('sales-transactions/extract-document', [SalesTransactionController::class, 'extractDocument'])->name('sales-transactions.extract-document');
    Route::get('sales-transactions/{id}', [SalesTransactionController::class, 'show'])->name('sales-transactions.show');
    Route::get('sales-transactions/document/{documentId}', [SalesTransactionController::class, 'document'])->name('sales-transactions.document');
    Route::get('submission-key', [\App\Http\Controllers\Shared\SubmissionKeyController::class, 'show'])->name('submission-key');

    // NEW 18 Jul 2026 — Customer Maintenance, Introducer scope (self only).
    Route::get('customers', [SharedCustomer::class, 'index'])->name('customers.index');
    Route::get('customers/typeahead', [SharedCustomer::class, 'typeahead'])->name('customers.typeahead');
    Route::get('customers/{id}', [SharedCustomer::class, 'show'])->name('customers.show');
    Route::get('customers/{id}/edit', [SharedCustomer::class, 'edit'])->name('customers.edit');
    Route::put('customers/{id}', [SharedCustomer::class, 'update'])->name('customers.update');
    Route::post('customers/{id}/deactivate', [SharedCustomer::class, 'deactivate'])->name('customers.deactivate');
    // NEW 19 Jul 2026 — Prospects (personal contacts, no policy yet) +
    // personal follow-up reminders, per Chris.
    Route::get('customers/prospects/create', [SharedCustomer::class, 'createProspect'])->name('customers.prospects.create');
    Route::post('customers/prospects', [SharedCustomer::class, 'storeProspect'])->name('customers.prospects.store');
    Route::post('customers/{id}/reminders', [PersonalReminderController::class, 'store'])->name('customers.reminders.store');
    Route::post('reminders/{id}/done', [PersonalReminderController::class, 'markDone'])->name('reminders.done');
    Route::delete('reminders/{id}', [PersonalReminderController::class, 'destroy'])->name('reminders.destroy');
    // NEW 18 Jul 2026 — Renewal Quotation Requests, Introducer scope (self only).
    Route::get('renewal-quotations', [RenewalQuotationController::class, 'index'])->name('renewal-quotations.index');
    Route::post('renewal-quotations/{id}/mark-sent', [RenewalQuotationController::class, 'markSent'])->name('renewal-quotations.mark-sent');
});

// CBE — Community & Business Enterprise Group
// NEW 17 Aug 2026 — per Chris: login pages are unchanged for every
// group type, including CBE. This route group only covers what a CBE
// member sees AFTER logging in — the new GLADE-style Ecosystem Home,
// reached via AuthController::redirectToDashboard() once an agent's
// group_labels.group_type = 'CBE'. Any authenticated agent can reach
// 'dashboard' (not role-gated, since CBE has no fixed GROUP_LEADER/
// TEAM_LEADER/INTRODUCER roles — see cbe_hierarchy_levels instead).
// 'preview' is Admin-only, for reviewing the screen before any real
// CBE member exists to log in and see it themselves.
Route::prefix('cbe')->name('cbe.')->group(function () {
    Route::middleware(['auth:agent', 'glade.sidebar'])->group(function () {
        Route::get('dashboard', [CbeDashboard::class, 'index'])->name('dashboard');

        // NEW 25 Aug 2026 — per Chris: the 3 role-based executive
        // dashboards (Director/Finance/Membership). One entry point —
        // it looks up which officer role (if any) the logged-in agent
        // holds via cbe_node_officers and renders the matching view.
        Route::get('exec-dashboard', [CbeExecDashboardController::class, 'index'])->name('exec-dashboard');
        Route::get('exec-dashboard/communication-kpi', [CbeExecDashboardController::class, 'communicationKpi'])->name('exec-dashboard.communication');
        Route::get('exec-dashboard/vendor-marketplace-kpi', [CbeExecDashboardController::class, 'vendorMarketplaceKpi'])->name('exec-dashboard.vendor-marketplace');
        Route::get('exec-dashboard/customer-kpi', [CbeExecDashboardController::class, 'customerKpi'])->name('exec-dashboard.customer');

        // NEW 22 Aug 2026 — per Chris: Meeting Minutes, Activities, Bank
        // Statement/Transactions, and Annual Report. Every action here is
        // scoped inside its controller to the logged-in agent's own
        // cbe_node_id (their Temple/Branch/State/HQ), so no extra
        // route-level scoping is needed.
        Route::get('minutes', [CbeMeetingMinutes::class, 'index'])->name('minutes.index');
        Route::get('minutes/create', [CbeMeetingMinutes::class, 'create'])->name('minutes.create');
        Route::post('minutes', [CbeMeetingMinutes::class, 'store'])->name('minutes.store');
        Route::get('minutes/{id}/download', [CbeMeetingMinutes::class, 'download'])->name('minutes.download');

        // NEW 28 Aug 2026 — per Chris: "you should have a meeting type...
        // you need to have add defined meeting type." Admin-configurable,
        // same Manage Categories pattern as Finance. Registered BEFORE
        // minutes/{id} below so the literal "meeting-types" segment isn't
        // swallowed by the {id} wildcard.
        Route::get('minutes/meeting-types', [CbeMeetingMinutes::class, 'meetingTypes'])->name('minutes.meeting-types');
        Route::post('minutes/meeting-types', [CbeMeetingMinutes::class, 'storeMeetingType'])->name('minutes.meeting-types.store');
        Route::post('minutes/meeting-types/{id}/deactivate', [CbeMeetingMinutes::class, 'deactivateMeetingType'])->name('minutes.meeting-types.deactivate');

        // Registered BEFORE minutes/{id} so "checkin" isn't swallowed by
        // the {id} wildcard — same reasoning as meeting-types above.
        Route::get('minutes/checkin/{token}', [CbeMeetingMinutes::class, 'checkinShow'])->name('minutes.checkin.show');
        Route::post('minutes/checkin/{token}', [CbeMeetingMinutes::class, 'checkinConfirm'])->name('minutes.checkin.confirm');

        Route::get('minutes/{id}', [CbeMeetingMinutes::class, 'show'])->name('minutes.show');

        // NEW 17 Sep 2026 — per Chris ("yes build all this for me"):
        // AGM/Resolution tracking — resolutions live under a meeting
        // minute (see the new Resolutions tab on cbe.minutes.show).
        Route::post('minutes/{id}/resolutions', [CbeMeetingMinutes::class, 'storeResolution'])->name('minutes.resolutions.store');

        // NEW 17 Sep 2026 — per Chris (Meeting Notice/Agenda/Quorum
        // approved): send the notice+agenda blast, and let any member
        // RSVP to a SCHEDULED meeting.
        Route::post('minutes/{id}/send-notice', [CbeMeetingMinutes::class, 'sendNotice'])->name('minutes.send-notice');
        Route::post('minutes/{id}/rsvp', [CbeMeetingMinutes::class, 'rsvp'])->name('minutes.rsvp');

        // NEW 18 Sep 2026 — per Chris's uploaded "Online Meeting
        // Attendance & Meeting Minutes System" requirement: attendance
        // (join/leave time entry), AI-drafted minutes from an uploaded
        // transcript, manual section editing, approval, and the two PDF
        // exports (Attendance Report + Meeting Minutes).
        Route::get('minutes/{id}/attendance', [CbeMeetingMinutes::class, 'attendance'])->name('minutes.attendance');
        Route::post('minutes/{id}/attendance', [CbeMeetingMinutes::class, 'updateAttendance'])->name('minutes.attendance.update');
        Route::post('minutes/{id}/ai-draft', [CbeMeetingMinutes::class, 'aiDraft'])->name('minutes.ai-draft');
        Route::post('minutes/{id}/sections', [CbeMeetingMinutes::class, 'updateSections'])->name('minutes.sections.update');
        Route::post('minutes/{id}/approve', [CbeMeetingMinutes::class, 'approveMinutes'])->name('minutes.approve');
        Route::get('minutes/{id}/attendance-report-pdf', [CbeMeetingMinutes::class, 'attendanceReportPdf'])->name('minutes.attendance-report-pdf');
        Route::get('minutes/{id}/minutes-pdf', [CbeMeetingMinutes::class, 'minutesPdf'])->name('minutes.minutes-pdf');

        // NEW 18 Sep 2026 — per Chris: WhatsApp/Email OTP-style
        // attendance confirmation — Secretary starts the round, each
        // attendee gets a personal one-tap link.
        Route::post('minutes/{id}/checkin/start', [CbeMeetingMinutes::class, 'startAttendanceCheckin'])->name('minutes.checkin.start');

        // NEW 17 Sep 2026 — per Chris: Document Repository. Registered
        // BEFORE documents/{id} equivalents aren't needed here since
        // there's no {id} show screen (download only), same shape as
        // minutes/activities download.
        Route::get('documents', [\App\Http\Controllers\Cbe\CbeDocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/create', [\App\Http\Controllers\Cbe\CbeDocumentController::class, 'create'])->name('documents.create');
        Route::post('documents', [\App\Http\Controllers\Cbe\CbeDocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{id}/download', [\App\Http\Controllers\Cbe\CbeDocumentController::class, 'download'])->name('documents.download');

        // NEW 17 Sep 2026 — per Chris: Announcement Blast (Email + in-app
        // to every active member of this entity). Real WhatsApp send is
        // out of scope until a system-wide WhatsApp Business connection
        // exists — see CbeSecretarialBlastController header.
        Route::get('blast', [\App\Http\Controllers\Cbe\CbeSecretarialBlastController::class, 'index'])->name('blast.index');
        Route::get('blast/create', [\App\Http\Controllers\Cbe\CbeSecretarialBlastController::class, 'create'])->name('blast.create');
        Route::post('blast', [\App\Http\Controllers\Cbe\CbeSecretarialBlastController::class, 'store'])->name('blast.store');

        // NEW 17 Sep 2026 — per Chris: Correspondence Register.
        Route::get('correspondences', [\App\Http\Controllers\Cbe\CbeCorrespondenceController::class, 'index'])->name('correspondences.index');
        Route::get('correspondences/create', [\App\Http\Controllers\Cbe\CbeCorrespondenceController::class, 'create'])->name('correspondences.create');
        Route::post('correspondences', [\App\Http\Controllers\Cbe\CbeCorrespondenceController::class, 'store'])->name('correspondences.store');
        Route::get('correspondences/{id}/download', [\App\Http\Controllers\Cbe\CbeCorrespondenceController::class, 'download'])->name('correspondences.download');

        // NEW 17 Sep 2026 — per Chris: Statutory Compliance Reminders.
        Route::get('compliance-items', [\App\Http\Controllers\Cbe\CbeComplianceItemController::class, 'index'])->name('compliance-items.index');
        Route::get('compliance-items/create', [\App\Http\Controllers\Cbe\CbeComplianceItemController::class, 'create'])->name('compliance-items.create');
        Route::post('compliance-items', [\App\Http\Controllers\Cbe\CbeComplianceItemController::class, 'store'])->name('compliance-items.store');
        Route::post('compliance-items/{id}/deactivate', [\App\Http\Controllers\Cbe\CbeComplianceItemController::class, 'deactivate'])->name('compliance-items.deactivate');

        // NEW 17 Sep 2026 — per Chris (his "Gemini Gem" idea): named AI
        // Assistants that answer only from attached Document Repository
        // documents.
        Route::get('ai-assistants', [\App\Http\Controllers\Cbe\CbeAiAssistantController::class, 'index'])->name('ai-assistants.index');
        Route::get('ai-assistants/create', [\App\Http\Controllers\Cbe\CbeAiAssistantController::class, 'create'])->name('ai-assistants.create');
        Route::post('ai-assistants', [\App\Http\Controllers\Cbe\CbeAiAssistantController::class, 'store'])->name('ai-assistants.store');
        Route::get('ai-assistants/{id}/chat', [\App\Http\Controllers\Cbe\CbeAiAssistantController::class, 'chat'])->name('ai-assistants.chat');
        Route::post('ai-assistants/{id}/ask', [\App\Http\Controllers\Cbe\CbeAiAssistantController::class, 'ask'])->name('ai-assistants.ask');

        Route::get('activities', [CbeActivities::class, 'index'])->name('activities.index');
        Route::get('activities/create', [CbeActivities::class, 'create'])->name('activities.create');
        Route::post('activities', [CbeActivities::class, 'store'])->name('activities.store');
        Route::get('activities/{id}/download', [CbeActivities::class, 'download'])->name('activities.download');

        // NEW 17 Sep 2026 — per Chris: temple Notice Board and Calendar,
        // manageable by the entity's own officers/Secretary (never the
        // platform-wide notices/calendar_events Admin manages — see
        // CbeNoticeBoardController/CbeTempleCalendarController's own
        // comments).
        Route::get('notice-board', [CbeNoticeBoardController::class, 'index'])->name('notice-board.index');
        Route::get('notice-board/create', [CbeNoticeBoardController::class, 'create'])->name('notice-board.create');
        Route::post('notice-board', [CbeNoticeBoardController::class, 'store'])->name('notice-board.store');
        Route::get('notice-board/{notice}/edit', [CbeNoticeBoardController::class, 'edit'])->name('notice-board.edit');
        Route::post('notice-board/{notice}', [CbeNoticeBoardController::class, 'update'])->name('notice-board.update');
        Route::post('notice-board/{notice}/delete', [CbeNoticeBoardController::class, 'destroy'])->name('notice-board.destroy');
        Route::get('notice-board/{notice}/attachment', [CbeNoticeBoardController::class, 'attachment'])->name('notice-board.attachment');
        Route::get('notice-board/{notice}/catalog', [CbeNoticeBoardController::class, 'catalogFile'])->name('notice-board.catalog');
        Route::get('notice-board/{notice}/video-file', [CbeNoticeBoardController::class, 'videoFile'])->name('notice-board.video-file');
        Route::post('notice-board/ai-assist', [CbeNoticeBoardController::class, 'aiAssist'])->name('notice-board.ai-assist');
        Route::post('notice-board/style-detect', [CbeNoticeBoardController::class, 'styleDetect'])->name('notice-board.style-detect');
        Route::post('notice-board/toggle-birthday-share', [CbeNoticeBoardController::class, 'toggleBirthdayShare'])->name('notice-board.toggle-birthday-share');
        Route::post('notice-board/{notice}/join', [CbeNoticeBoardController::class, 'joinListing'])->name('notice-board.join');

        Route::get('temple-calendar', [CbeTempleCalendarController::class, 'index'])->name('temple-calendar.index');
        Route::get('temple-calendar/create', [CbeTempleCalendarController::class, 'create'])->name('temple-calendar.create');
        Route::post('temple-calendar', [CbeTempleCalendarController::class, 'store'])->name('temple-calendar.store');
        Route::get('temple-calendar/{event}/edit', [CbeTempleCalendarController::class, 'edit'])->name('temple-calendar.edit');
        Route::post('temple-calendar/{event}', [CbeTempleCalendarController::class, 'update'])->name('temple-calendar.update');
        Route::post('temple-calendar/{event}/delete', [CbeTempleCalendarController::class, 'destroy'])->name('temple-calendar.destroy');

        // NEW 17 Sep 2026 — per Chris: internal messaging within one CBE
        // entity, anyone to anyone, no upline/downline rule (see
        // CbeMessagingController). Vendor messaging deliberately out of
        // scope for v1 — cbe_vendors has no login yet.
        Route::get('messaging', [CbeMessagingController::class, 'index'])->name('messaging.index');
        Route::get('messaging/create', [CbeMessagingController::class, 'create'])->name('messaging.create');
        Route::post('messaging', [CbeMessagingController::class, 'store'])->name('messaging.store');
        Route::get('messaging/recipient-typeahead', [CbeMessagingController::class, 'recipientTypeahead'])->name('messaging.recipient-typeahead');
        Route::get('messaging/{thread}', [CbeMessagingController::class, 'show'])->name('messaging.show');
        Route::post('messaging/{thread}/reply', [CbeMessagingController::class, 'reply'])->name('messaging.reply');
        Route::post('messaging/ai-assist', [CbeMessagingController::class, 'aiAssist'])->name('messaging.ai-assist');
        Route::get('messaging/attachment/{message}', [CbeMessagingController::class, 'attachment'])->name('messaging.attachment');
        Route::post('messaging/{message}/confirm-issue', [CbeMessagingController::class, 'confirmAndIssue'])->name('messaging.confirm-issue');

        // NEW 17 Sep 2026 — per Chris: CBE Support Tickets (member
        // complaint, event prep request, purchase request, etc) —
        // Secretary/officer triages every ticket first (see
        // CbeTicketController's own comment).
        Route::get('tickets', [\App\Http\Controllers\Cbe\CbeTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [\App\Http\Controllers\Cbe\CbeTicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [\App\Http\Controllers\Cbe\CbeTicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [\App\Http\Controllers\Cbe\CbeTicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [\App\Http\Controllers\Cbe\CbeTicketController::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/triage', [\App\Http\Controllers\Cbe\CbeTicketController::class, 'triage'])->name('tickets.triage');
        Route::get('tickets/{ticket}/assignee-typeahead', [\App\Http\Controllers\Cbe\CbeTicketController::class, 'assigneeTypeahead'])->name('tickets.assignee-typeahead');

        Route::get('finance', [CbeFinance::class, 'index'])->name('finance.index');
        Route::get('finance/statements/create', [CbeFinance::class, 'createStatement'])->name('finance.statements.create');
        Route::post('finance/statements', [CbeFinance::class, 'storeStatement'])->name('finance.statements.store');
        Route::get('finance/statements/{id}/download', [CbeFinance::class, 'downloadStatement'])->name('finance.statements.download');
        // NEW 27 Aug 2026 (Task #222) — bank account quick-add + AI
        // document-extraction for the closing balance, both reachable
        // from the Upload Statement screen.
        Route::post('finance/bank-accounts', [CbeFinance::class, 'storeBankAccount'])->name('finance.bank-accounts.store');
        Route::post('finance/statements/extract', [CbeFinance::class, 'extractStatement'])->name('finance.statements.extract');
        Route::get('finance/transactions/create', [CbeFinance::class, 'createTransaction'])->name('finance.transactions.create');
        Route::post('finance/transactions', [CbeFinance::class, 'storeTransaction'])->name('finance.transactions.store');
        Route::get('finance/categories', [CbeFinance::class, 'categories'])->name('finance.categories');
        Route::post('finance/categories', [CbeFinance::class, 'storeCategory'])->name('finance.categories.store');
        Route::post('finance/categories/{id}/deactivate', [CbeFinance::class, 'deactivateCategory'])->name('finance.categories.deactivate');
        // NEW 3 Sep 2026 (Task #367) — GL Account Mapping UI.
        Route::post('finance/categories/{id}/mapping', [CbeFinance::class, 'updateCategoryMapping'])->name('finance.categories.mapping');
        // NEW 1 Sep 2026 (Task #333) — Bank Accounts Master (full screen,
        // not just the quick-add modal), Bank Transfer, Cash & Bank
        // Position report.
        Route::get('finance/bank-accounts-list', [CbeFinance::class, 'bankAccounts'])->name('finance.bank-accounts');
        Route::get('finance/bank-accounts-typeahead', [CbeFinance::class, 'bankAccountTypeahead'])->name('finance.bank-accounts.typeahead');
        Route::get('finance/bank-accounts/create', [CbeFinance::class, 'createBankAccount'])->name('finance.bank-accounts.create');
        // NEW 3 Sep 2026 (Task #388) — Bank Reconciliation gap-fix: edit +
        // deactivate/reactivate for the Bank Accounts Master.
        Route::get('finance/bank-accounts/{account}/edit', [CbeFinance::class, 'editBankAccount'])->name('finance.bank-accounts.edit');
        Route::put('finance/bank-accounts/{account}', [CbeFinance::class, 'updateBankAccount'])->name('finance.bank-accounts.update');
        Route::post('finance/bank-accounts/{account}/deactivate', [CbeFinance::class, 'deactivateBankAccount'])->name('finance.bank-accounts.deactivate');
        Route::post('finance/bank-accounts/{account}/reactivate', [CbeFinance::class, 'reactivateBankAccount'])->name('finance.bank-accounts.reactivate');
        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
        // Phase 1: Bank Transaction Type master.
        Route::get('finance/bank-transaction-types', [CbeFinance::class, 'bankTransactionTypes'])->name('finance.bank-transaction-types');
        Route::get('finance/bank-transaction-types-typeahead', [CbeFinance::class, 'bankTransactionTypeTypeahead'])->name('finance.bank-transaction-types.typeahead');
        Route::post('finance/bank-transaction-types', [CbeFinance::class, 'storeBankTransactionType'])->name('finance.bank-transaction-types.store');
        Route::post('finance/bank-transaction-types/{type}/deactivate', [CbeFinance::class, 'deactivateBankTransactionType'])->name('finance.bank-transaction-types.deactivate');
        // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
        // Phase 2: Bank Transaction Entry (manual + CSV/Excel import),
        // scoped to one bank account.
        Route::get('finance/bank-accounts/{account}/transactions', [CbeFinance::class, 'bankTransactions'])->name('finance.bank-transactions');
        Route::get('finance/bank-accounts/{account}/transactions/create', [CbeFinance::class, 'createBankTransactionForm'])->name('finance.bank-transactions.create');
        Route::post('finance/bank-accounts/{account}/transactions', [CbeFinance::class, 'storeBankTransaction'])->name('finance.bank-transactions.store');
        Route::post('finance/bank-transactions/{transaction}/delete', [CbeFinance::class, 'deleteBankTransaction'])->name('finance.bank-transactions.delete');
        Route::get('finance/bank-transaction-import/template', [CbeFinance::class, 'downloadBankTransactionImportTemplate'])->name('finance.bank-transaction-import.template');
        Route::get('finance/bank-accounts/{account}/transactions/import', [CbeFinance::class, 'bankTransactionImportForm'])->name('finance.bank-transaction-import.create');
        Route::post('finance/bank-accounts/{account}/transactions/import', [CbeFinance::class, 'storeBankTransactionImport'])->name('finance.bank-transaction-import.store');
        Route::get('finance/bank-transaction-import/{batch}/preview', [CbeFinance::class, 'bankTransactionImportPreview'])->name('finance.bank-transaction-import.preview');
        Route::post('finance/bank-transaction-import/{batch}/commit', [CbeFinance::class, 'commitBankTransactionImport'])->name('finance.bank-transaction-import.commit');
        Route::post('finance/bank-transaction-import/{batch}/cancel', [CbeFinance::class, 'cancelBankTransactionImport'])->name('finance.bank-transaction-import.cancel');
        Route::get('finance/transfers', [CbeFinance::class, 'transfers'])->name('finance.transfers');
        Route::get('finance/transfers/create', [CbeFinance::class, 'createTransfer'])->name('finance.transfers.create');
        Route::post('finance/transfers', [CbeFinance::class, 'storeTransfer'])->name('finance.transfers.store');
        Route::get('finance/cash-position', [CbeFinance::class, 'cashPosition'])->name('finance.cash-position');

        Route::get('annual-report', [CbeAnnualReport::class, 'index'])->name('annual-report.index');
        Route::get('annual-report/secretary', [CbeAnnualReport::class, 'secretaryReport'])->name('annual-report.secretary');
        Route::get('annual-report/income-expenditure', [CbeAnnualReport::class, 'incomeExpenditureReport'])->name('annual-report.income-expenditure');

        // NEW 22 Aug 2026 — per Chris: Event, Donation, Sponsorship &
        // Financial Management Module. All scoped inside each controller
        // to the agent's own cbe_node_id, same pattern as every CBE
        // screen above.
        Route::get('events', [CbeEvent::class, 'index'])->name('events.index');
        Route::get('events/create', [CbeEvent::class, 'create'])->name('events.create');
        Route::post('events', [CbeEvent::class, 'store'])->name('events.store');
        Route::get('events/{id}', [CbeEvent::class, 'show'])->name('events.show');
        Route::post('events/{id}/start', [CbeEvent::class, 'start'])->name('events.start');
        Route::post('events/{id}/close', [CbeEvent::class, 'close'])->name('events.close');

        // NEW 17 Sep 2026 — per Chris ("yes build all this for me"):
        // Event RSVP/attendance — every member can respond, not just
        // officers (see EventController::index/show now using the
        // member-safe resolveCbeNodeIdForMember()).
        Route::post('events/{id}/rsvp', [CbeEvent::class, 'rsvp'])->name('events.rsvp');

        Route::get('donors', [CbeDonor::class, 'index'])->name('donors.index');
        Route::get('donors/create', [CbeDonor::class, 'create'])->name('donors.create');
        Route::post('donors', [CbeDonor::class, 'store'])->name('donors.store');

        Route::get('events/{event}/contributions', [CbeContribution::class, 'index'])->name('contributions.index');
        Route::get('events/{event}/contributions/create', [CbeContribution::class, 'create'])->name('contributions.create');
        Route::post('events/{event}/contributions', [CbeContribution::class, 'store'])->name('contributions.store');
        Route::get('contributions/{id}', [CbeContribution::class, 'show'])->name('contributions.show');
        Route::post('contributions/{id}/payments', [CbeContribution::class, 'addPayment'])->name('contributions.payments.store');
        Route::get('contributions/{id}/receipt', [CbeContribution::class, 'downloadReceipt'])->name('contributions.receipt');

        Route::get('events/{event}/expenses', [CbeEventExpense::class, 'index'])->name('expenses.index');
        Route::get('events/{event}/expenses/create', [CbeEventExpense::class, 'create'])->name('expenses.create');
        Route::post('events/{event}/expenses', [CbeEventExpense::class, 'store'])->name('expenses.store');
        Route::get('expenses/{id}/receipt', [CbeEventExpense::class, 'downloadReceipt'])->name('expenses.receipt');

        Route::get('events/{event}/reports', [CbeEventReport::class, 'index'])->name('event-reports.index');
        Route::get('events/{event}/reports/donation-register', [CbeEventReport::class, 'donationRegister'])->name('event-reports.donation-register');
        Route::get('events/{event}/reports/donation-summary', [CbeEventReport::class, 'donationSummary'])->name('event-reports.donation-summary');
        Route::get('events/{event}/reports/sponsorship', [CbeEventReport::class, 'sponsorshipReport'])->name('event-reports.sponsorship');
        Route::get('events/{event}/reports/auction', [CbeEventReport::class, 'auctionReport'])->name('event-reports.auction');
        Route::get('events/{event}/reports/income-expenditure', [CbeEventReport::class, 'incomeExpenditure'])->name('event-reports.income-expenditure');

        // NEW 25 Aug 2026 — native double-entry accounting (Chart of
        // Accounts, Suppliers, Purchase Bills / Accounts Payable,
        // General Ledger, Trial Balance, Balance Sheet, Profit & Loss).
        Route::prefix('accounting')->name('accounting.')->group(function () {
            Route::get('/', [CbeAccountingController::class, 'index'])->name('index');
            // NEW 4 Sep 2026 (Task #394) — Purchasing Management hub.
            Route::get('purchasing', [CbeAccountingController::class, 'purchasingHub'])->name('purchasing-hub');
            // NEW 10 Sep 2026 (Task #398) — per Chris: the main Accounting
            // Hub was a single flat screen of ~40 tiles. Split into one
            // sub-hub per module (AR/AP/GL/FA/Bank Reconciliation) plus a
            // new Master Files hub that centralises every master/reference
            // table (Entity, Debtor, Creditor, Chart of Accounts,
            // Transaction Type, Document Numbering, FA Code, Bank Account
            // Number, etc.) in one place instead of scattered across every
            // module. The main hub (index above) now only has 7 tiles:
            // AR, AP, GL, FA, Bank Reconciliation, AI Accounting
            // Automation, Master Files.
            Route::get('ar-hub', [CbeAccountingController::class, 'arHub'])->name('ar-hub');
            Route::get('ap-hub', [CbeAccountingController::class, 'apHub'])->name('ap-hub');
            Route::get('gl-hub', [CbeAccountingController::class, 'glHub'])->name('gl-hub');
            Route::get('fa-hub', [CbeAccountingController::class, 'faHub'])->name('fa-hub');
            Route::get('bank-recon-hub', [CbeAccountingController::class, 'bankReconHub'])->name('bank-recon-hub');
            Route::get('master-files', [CbeAccountingController::class, 'masterFilesHub'])->name('master-files-hub');
            // NEW 19 Sep 2026 -- per Chris: "Chart of Accounts Structure
            // Tree", a folder/sub-folder view for spotting GL code gaps
            // before adding a new account. Registered BEFORE chart-of-accounts
            // itself, matching where it sits in the sidebar.
            Route::get('chart-of-accounts-structure-tree', [CbeAccountingController::class, 'coaStructureTree'])->name('chart-of-accounts-structure-tree');
            Route::get('chart-of-accounts', [CbeAccountingController::class, 'chartOfAccounts'])->name('chart-of-accounts');
            Route::post('chart-of-accounts', [CbeAccountingController::class, 'storeAccount'])->name('chart-of-accounts.store');
            // NEW 19 Sep 2026 -- per Chris: Add / Search-Edit split, same
            // pattern as Reason Code. These literal routes MUST be
            // registered before the {account}/edit wildcard just below,
            // or Laravel would try to match "search"/"create"/"typeahead"
            // etc. as an {account} id instead.
            Route::get('chart-of-accounts/create', [CbeAccountingController::class, 'createAccount'])->name('chart-of-accounts.create');
            Route::get('chart-of-accounts/search', [CbeAccountingController::class, 'chartOfAccountsSearchForm'])->name('chart-of-accounts.search');
            Route::get('chart-of-accounts/typeahead', [CbeAccountingController::class, 'chartOfAccountsTypeahead'])->name('chart-of-accounts.typeahead');
            Route::get('chart-of-accounts/category-typeahead', [CbeAccountingController::class, 'accountCategoryTypeahead'])->name('chart-of-accounts.category-typeahead');
            Route::get('chart-of-accounts/parent-typeahead', [CbeAccountingController::class, 'parentAccountTypeahead'])->name('chart-of-accounts.parent-typeahead');
            // NEW 19 Sep 2026 -- advanced multi-criteria Search screen
            // (Type -> Category -> Group -> Code -> Name -> Name(zh) ->
            // Description), per Chris.
            Route::get('chart-of-accounts/group-typeahead', [CbeAccountingController::class, 'accountGroupTypeahead'])->name('chart-of-accounts.group-typeahead');
            Route::get('chart-of-accounts/total-count', [CbeAccountingController::class, 'chartOfAccountsTotalCount'])->name('chart-of-accounts.total-count');
            // NEW 19 Sep 2026 -- "COA Chat" (AI Master Data Assistant,
            // Phase 1: Chart of Accounts).
            Route::get('chart-of-accounts/chat', [CbeAccountingController::class, 'coaChatForm'])->name('chart-of-accounts.chat');
            Route::post('chart-of-accounts/chat/classify', [CbeAccountingController::class, 'coaChatClassify'])->name('chart-of-accounts.chat.classify');
            // NEW 3 Sep 2026 (Task #381) — CoA edit/deactivate + Account
            // Group / Account Category master files (GL spec gap-fix).
            Route::get('chart-of-accounts/{account}/edit', [CbeAccountingController::class, 'editAccount'])->name('chart-of-accounts.edit');
            Route::put('chart-of-accounts/{account}', [CbeAccountingController::class, 'updateAccount'])->name('chart-of-accounts.update');
            Route::post('chart-of-accounts/{account}/deactivate', [CbeAccountingController::class, 'deactivateAccount'])->name('chart-of-accounts.deactivate');
            Route::post('chart-of-accounts/{account}/reactivate', [CbeAccountingController::class, 'reactivateAccount'])->name('chart-of-accounts.reactivate');
            Route::get('account-groups', [CbeAccountingController::class, 'accountGroups'])->name('account-groups');
            Route::post('account-groups', [CbeAccountingController::class, 'storeAccountGroup'])->name('account-groups.store');
            // NEW 22 Sep 2026 -- per Chris: Add / Search-Edit split, same
            // pattern as Chart of Accounts.
            Route::get('account-groups/create', [CbeAccountingController::class, 'createAccountGroup'])->name('account-groups.create');
            Route::get('account-groups/search', [CbeAccountingController::class, 'accountGroupsSearch'])->name('account-groups.search');
            Route::post('account-groups/{group}/deactivate', [CbeAccountingController::class, 'deactivateAccountGroup'])->name('account-groups.deactivate');
            Route::get('account-categories', [CbeAccountingController::class, 'accountCategories'])->name('account-categories');
            Route::post('account-categories', [CbeAccountingController::class, 'storeAccountCategory'])->name('account-categories.store');
            // NEW 22 Sep 2026 -- per Chris: Add / Search-Edit split, same
            // pattern as Chart of Accounts. Registered before the
            // {category}/deactivate route is irrelevant here (that's a
            // POST with a literal segment after it, not a GET wildcard),
            // but kept together for readability.
            Route::get('account-categories/create', [CbeAccountingController::class, 'createAccountCategory'])->name('account-categories.create');
            Route::get('account-categories/search', [CbeAccountingController::class, 'accountCategoriesSearch'])->name('account-categories.search');
            Route::post('account-categories/{category}/deactivate', [CbeAccountingController::class, 'deactivateAccountCategory'])->name('account-categories.deactivate');
            // NEW 19 Sep 2026 -- per Chris: lets the treasurer create a
            // named spending/income category (e.g. "Meeting & Refreshment")
            // linked to a specific GL account, so it appears both as a
            // pickable line-item category on Bills/Invoices/JVs AND in the
            // AI Accounting Automation "GL Account" dropdown.
            Route::get('transaction-categories', [CbeAccountingController::class, 'transactionCategories'])->name('transaction-categories');
            Route::post('transaction-categories', [CbeAccountingController::class, 'storeTransactionCategory'])->name('transaction-categories.store');
            // NEW 22 Sep 2026 -- per Chris: Add / Search-Edit split, same
            // pattern as Chart of Accounts.
            Route::get('transaction-categories/create', [CbeAccountingController::class, 'createTransactionCategory'])->name('transaction-categories.create');
            Route::get('transaction-categories/search', [CbeAccountingController::class, 'transactionCategoriesSearch'])->name('transaction-categories.search');
            Route::get('transaction-categories/typeahead', [CbeAccountingController::class, 'transactionCategoryTypeahead'])->name('transaction-categories.typeahead');
            Route::post('transaction-categories/{category}/deactivate', [CbeAccountingController::class, 'deactivateTransactionCategory'])->name('transaction-categories.deactivate');
            Route::post('transaction-categories/{category}/reactivate', [CbeAccountingController::class, 'reactivateTransactionCategory'])->name('transaction-categories.reactivate');
            Route::get('suppliers', [CbeAccountingController::class, 'suppliers'])->name('suppliers');
            Route::get('suppliers/create', [CbeAccountingController::class, 'createSupplier'])->name('suppliers.create');
            Route::post('suppliers', [CbeAccountingController::class, 'storeSupplier'])->name('suppliers.store');
            // NEW 3 Sep 2026 (Task #371) — Supplier edit + Supplier Categories.
            Route::get('suppliers/{supplier}/edit', [CbeAccountingController::class, 'editSupplier'])->name('suppliers.edit');
            Route::put('suppliers/{supplier}', [CbeAccountingController::class, 'updateSupplier'])->name('suppliers.update');
            Route::get('supplier-categories', [CbeAccountingController::class, 'supplierCategories'])->name('supplier-categories');
            Route::post('supplier-categories', [CbeAccountingController::class, 'storeSupplierCategory'])->name('supplier-categories.store');
            Route::post('supplier-categories/{category}/deactivate', [CbeAccountingController::class, 'deactivateSupplierCategory'])->name('supplier-categories.deactivate');
            Route::get('bills', [CbeAccountingController::class, 'bills'])->name('bills');
            Route::get('bills/create', [CbeAccountingController::class, 'createBill'])->name('bills.create');
            Route::post('bills', [CbeAccountingController::class, 'storeBill'])->name('bills.store');
            Route::post('bills/{bill}/pay', [CbeAccountingController::class, 'payBill'])->name('bills.pay');
            Route::get('bills/{bill}/attachment', [CbeAccountingController::class, 'downloadBillAttachment'])->name('bills.attachment');
            // NEW 2 Sep 2026 (Task #335) — Debit Notes.
            Route::get('debit-notes', [CbeAccountingController::class, 'debitNotes'])->name('debit-notes');
            Route::get('debit-notes/create', [CbeAccountingController::class, 'createDebitNote'])->name('debit-notes.create');
            Route::post('debit-notes', [CbeAccountingController::class, 'storeDebitNote'])->name('debit-notes.store');
            // NEW 3 Sep 2026 (Task #372) — AP Debit Note (genuine increase
            // direction), AP Refunds, AP Adjustments, AP Opening Balances,
            // Payment Voucher (multi-bill allocation).
            Route::get('ap-debit-notes', [CbeAccountingController::class, 'apDebitNotes'])->name('ap-debit-notes');
            Route::get('ap-debit-notes/create', [CbeAccountingController::class, 'createApDebitNote'])->name('ap-debit-notes.create');
            Route::post('ap-debit-notes', [CbeAccountingController::class, 'storeApDebitNote'])->name('ap-debit-notes.store');
            Route::post('ap-debit-notes/{debitNote}/post', [CbeAccountingController::class, 'postApDebitNoteAction'])->name('ap-debit-notes.post');
            Route::get('ap-refunds', [CbeAccountingController::class, 'apRefunds'])->name('ap-refunds');
            Route::get('ap-refunds/create', [CbeAccountingController::class, 'createApRefund'])->name('ap-refunds.create');
            Route::post('ap-refunds', [CbeAccountingController::class, 'storeApRefund'])->name('ap-refunds.store');
            Route::get('ap-adjustments', [CbeAccountingController::class, 'apAdjustments'])->name('ap-adjustments');
            Route::get('ap-adjustments/create', [CbeAccountingController::class, 'createApAdjustment'])->name('ap-adjustments.create');
            Route::post('ap-adjustments', [CbeAccountingController::class, 'storeApAdjustment'])->name('ap-adjustments.store');
            Route::get('ap-opening-balances', [CbeAccountingController::class, 'apOpeningBalances'])->name('ap-opening-balances');
            Route::get('ap-opening-balances/create', [CbeAccountingController::class, 'createApOpeningBalance'])->name('ap-opening-balances.create');
            Route::post('ap-opening-balances', [CbeAccountingController::class, 'storeApOpeningBalance'])->name('ap-opening-balances.store');
            Route::get('payment-voucher', [CbeAccountingController::class, 'paymentVoucherPicker'])->name('payment-voucher');
            Route::get('payment-voucher/{supplier}', [CbeAccountingController::class, 'createPaymentVoucher'])->name('payment-voucher.create');
            Route::post('payment-voucher/{supplier}', [CbeAccountingController::class, 'storePaymentVoucher'])->name('payment-voucher.store');
            // NEW 3 Sep 2026 (Task #374) — AP Enquiry: Supplier Account,
            // Supplier Invoice (Bill), Payment, Outstanding Payables.
            Route::get('supplier-enquiry/{supplier}', [CbeAccountingController::class, 'supplierEnquiry'])->name('supplier-enquiry');
            Route::get('supplier-enquiry/{supplier}/procurement-history', [CbeAccountingController::class, 'supplierProcurementHistory'])->name('supplier-procurement-history');
            Route::get('bill-enquiry', [CbeAccountingController::class, 'billEnquiry'])->name('bill-enquiry');
            Route::get('bill-enquiry/{bill}', [CbeAccountingController::class, 'billEnquiryShow'])->name('bill-enquiry.show');
            Route::get('bill-enquiry/{bill}/create-fixed-asset', [CbeAccountingController::class, 'createFixedAssetFromBill'])->name('bill-enquiry.create-fixed-asset');
            Route::post('bill-enquiry/{bill}/create-fixed-asset', [CbeAccountingController::class, 'storeFixedAssetFromBill'])->name('bill-enquiry.store-fixed-asset');
            Route::get('ap-payment-enquiry', [CbeAccountingController::class, 'apPaymentEnquiry'])->name('ap-payment-enquiry');
            Route::get('ap-payment-enquiry/{payment}', [CbeAccountingController::class, 'apPaymentEnquiryShow'])->name('ap-payment-enquiry.show');
            Route::get('ap-outstanding-balance-enquiry', [CbeAccountingController::class, 'apOutstandingBalanceEnquiry'])->name('ap-outstanding-balance-enquiry');
            // NEW 2 Sep 2026 (Task #354) — AR Debit Notes.
            Route::get('ar-debit-notes', [CbeAccountingController::class, 'arDebitNotes'])->name('ar-debit-notes');
            Route::get('ar-debit-notes/create', [CbeAccountingController::class, 'createArDebitNote'])->name('ar-debit-notes.create');
            Route::post('ar-debit-notes', [CbeAccountingController::class, 'storeArDebitNote'])->name('ar-debit-notes.store');
            Route::post('ar-debit-notes/{debitNote}/post', [CbeAccountingController::class, 'postArDebitNoteAction'])->name('ar-debit-notes.post');
            // NEW 2 Sep 2026 (Task #354) — AR Credit Notes.
            Route::get('ar-credit-notes', [CbeAccountingController::class, 'arCreditNotes'])->name('ar-credit-notes');
            Route::get('ar-credit-notes/create', [CbeAccountingController::class, 'createArCreditNote'])->name('ar-credit-notes.create');
            Route::post('ar-credit-notes', [CbeAccountingController::class, 'storeArCreditNote'])->name('ar-credit-notes.store');
            // NEW 2 Sep 2026 (Task #358) — AR Adjustments.
            Route::get('ar-adjustments', [CbeAccountingController::class, 'arAdjustments'])->name('ar-adjustments');
            Route::get('ar-adjustments/create', [CbeAccountingController::class, 'createArAdjustment'])->name('ar-adjustments.create');
            Route::post('ar-adjustments', [CbeAccountingController::class, 'storeArAdjustment'])->name('ar-adjustments.store');
            // NEW 2 Sep 2026 (Task #358) — AR Refunds.
            Route::get('ar-refunds', [CbeAccountingController::class, 'arRefunds'])->name('ar-refunds');
            Route::get('ar-refunds/create', [CbeAccountingController::class, 'createArRefund'])->name('ar-refunds.create');
            Route::post('ar-refunds', [CbeAccountingController::class, 'storeArRefund'])->name('ar-refunds.store');
            // NEW 2 Sep 2026 (Task #358) — AR Opening Balances.
            Route::get('ar-opening-balances', [CbeAccountingController::class, 'arOpeningBalances'])->name('ar-opening-balances');
            Route::get('ar-opening-balances/create', [CbeAccountingController::class, 'createArOpeningBalance'])->name('ar-opening-balances.create');
            Route::post('ar-opening-balances', [CbeAccountingController::class, 'storeArOpeningBalance'])->name('ar-opening-balances.store');
            // NEW 2 Sep 2026 (Task #358) — AR Payment Allocation.
            Route::get('payment-allocation', [CbeAccountingController::class, 'paymentAllocationPicker'])->name('payment-allocation');
            Route::get('payment-allocation/{customer}', [CbeAccountingController::class, 'createPaymentAllocation'])->name('payment-allocation.create');
            Route::post('payment-allocation/{customer}', [CbeAccountingController::class, 'storePaymentAllocation'])->name('payment-allocation.store');
            // NEW 2 Sep 2026 (Task #355) — Debtor Ledger + Debtor Statement.
            Route::get('reports/debtor-ledger-picker', [CbeAccountingController::class, 'debtorLedgerPicker'])->name('reports.debtor-ledger-picker');
            // NEW 3 Sep 2026 (Task #368) — Donor Statement (mirrors Debtor Statement).
            Route::get('reports/donor-statement-picker', [CbeAccountingController::class, 'donorStatementPicker'])->name('reports.donor-statement-picker');
            Route::get('reports/donor-statement', [CbeAccountingController::class, 'donorStatement'])->name('reports.donor-statement');
            Route::get('reports/debtor-ledger', [CbeAccountingController::class, 'debtorLedgerReport'])->name('reports.debtor-ledger');
            Route::get('reports/debtor-statement', [CbeAccountingController::class, 'debtorStatement'])->name('reports.debtor-statement');
            // NEW 2 Sep 2026 (Task #360) — AR Reports hub + 9 downloadable
            // reports beyond Debtor Ledger/Statement and AR Aging.
            Route::get('reports/ar-reports', [CbeAccountingController::class, 'arReportsHub'])->name('reports.ar-reports');
            Route::get('reports/invoice-listing', [CbeAccountingController::class, 'invoiceListingReport'])->name('reports.invoice-listing');
            Route::get('reports/receipt-listing', [CbeAccountingController::class, 'receiptListingReport'])->name('reports.receipt-listing');
            Route::get('reports/ar-debit-note-listing', [CbeAccountingController::class, 'debitNoteListingReport'])->name('reports.ar-debit-note-listing');
            Route::get('reports/ar-credit-note-listing', [CbeAccountingController::class, 'creditNoteListingReport'])->name('reports.ar-credit-note-listing');
            Route::get('reports/outstanding-receivables', [CbeAccountingController::class, 'outstandingReceivablesReport'])->name('reports.outstanding-receivables');
            Route::get('reports/debtor-balance', [CbeAccountingController::class, 'debtorBalanceReport'])->name('reports.debtor-balance');
            Route::get('reports/ar-gl-reconciliation', [CbeAccountingController::class, 'arGlReconciliationReport'])->name('reports.ar-gl-reconciliation');
            Route::get('reports/monthly-ar-summary', [CbeAccountingController::class, 'monthlyArSummaryReport'])->name('reports.monthly-ar-summary');
            Route::get('reports/ar-transaction-report', [CbeAccountingController::class, 'arTransactionReport'])->name('reports.ar-transaction-report');
            Route::get('reports/payment-collection', [CbeAccountingController::class, 'paymentCollectionReport'])->name('reports.payment-collection');
            // NEW 2 Sep 2026 (Task #335) — Purchase Requests.
            Route::get('purchase-requests', [CbeAccountingController::class, 'purchaseRequests'])->name('purchase-requests');
            Route::get('purchase-requests/create', [CbeAccountingController::class, 'createPurchaseRequest'])->name('purchase-requests.create');
            Route::post('purchase-requests', [CbeAccountingController::class, 'storePurchaseRequest'])->name('purchase-requests.store');
            Route::get('purchase-requests/{request}', [CbeAccountingController::class, 'showPurchaseRequest'])->name('purchase-requests.show');
            Route::post('purchase-requests/{request}/approve', [CbeAccountingController::class, 'approvePurchaseRequestAction'])->name('purchase-requests.approve');
            Route::post('purchase-requests/{request}/reject', [CbeAccountingController::class, 'rejectPurchaseRequestAction'])->name('purchase-requests.reject');
            Route::post('purchase-requests/{request}/convert', [CbeAccountingController::class, 'convertPurchaseRequestAction'])->name('purchase-requests.convert');
            // NEW 4 Sep 2026 (Task #393) — Purchase Orders.
            Route::post('purchase-requests/{request}/convert-to-po', [CbeAccountingController::class, 'convertPurchaseRequestToPOAction'])->name('purchase-requests.convert-to-po');
            Route::get('purchase-orders', [CbeAccountingController::class, 'purchaseOrders'])->name('purchase-orders');
            Route::get('purchase-orders/create', [CbeAccountingController::class, 'createPurchaseOrder'])->name('purchase-orders.create');
            Route::post('purchase-orders', [CbeAccountingController::class, 'storePurchaseOrder'])->name('purchase-orders.store');
            Route::get('purchase-orders/{po}', [CbeAccountingController::class, 'showPurchaseOrder'])->name('purchase-orders.show');
            Route::post('purchase-orders/{po}/approve', [CbeAccountingController::class, 'approvePurchaseOrderAction'])->name('purchase-orders.approve');
            Route::post('purchase-orders/{po}/reject', [CbeAccountingController::class, 'rejectPurchaseOrderAction'])->name('purchase-orders.reject');
            Route::post('purchase-orders/{po}/convert-to-bill', [CbeAccountingController::class, 'convertPurchaseOrderToBillAction'])->name('purchase-orders.convert-to-bill');
            Route::post('purchase-orders/{po}/mark-received', [CbeAccountingController::class, 'markPurchaseOrderReceivedAction'])->name('purchase-orders.mark-received');
            Route::post('purchase-orders/{po}/cancel', [CbeAccountingController::class, 'cancelPurchaseOrderAction'])->name('purchase-orders.cancel');
            Route::get('reports/purchase-order-outstanding', [CbeAccountingController::class, 'purchaseOrderOutstandingReport'])->name('reports.purchase-order-outstanding');
            // NEW 4 Sep 2026 (Task #394) — Purchase Quotations / RFQ.
            Route::get('purchase-quotations', [CbeAccountingController::class, 'purchaseQuotations'])->name('purchase-quotations');
            Route::get('purchase-quotations/create', [CbeAccountingController::class, 'createPurchaseQuotation'])->name('purchase-quotations.create');
            Route::post('purchase-quotations', [CbeAccountingController::class, 'storePurchaseQuotation'])->name('purchase-quotations.store');
            Route::get('purchase-quotations/{rfq}', [CbeAccountingController::class, 'showPurchaseQuotation'])->name('purchase-quotations.show');
            Route::post('purchase-quotations/{rfq}/quotes', [CbeAccountingController::class, 'storeQuotationAmountsAction'])->name('purchase-quotations.quotes');
            Route::post('purchase-quotations/{rfq}/suppliers/{rfqSupplier}/select', [CbeAccountingController::class, 'selectQuotationSupplierAction'])->name('purchase-quotations.select');
            Route::post('purchase-quotations/{rfq}/convert-to-po', [CbeAccountingController::class, 'convertQuotationToPOAction'])->name('purchase-quotations.convert-to-po');
            Route::post('purchase-quotations/{rfq}/cancel', [CbeAccountingController::class, 'cancelQuotationAction'])->name('purchase-quotations.cancel');
            // NEW 4 Sep 2026 (Task #394 Phase 2) — Goods / Service Receipt.
            Route::get('goods-receipts', [CbeAccountingController::class, 'goodsReceipts'])->name('goods-receipts');
            Route::get('purchase-orders/{po}/receive', [CbeAccountingController::class, 'createGoodsReceipt'])->name('goods-receipts.create');
            Route::post('purchase-orders/{po}/receive', [CbeAccountingController::class, 'storeGoodsReceipt'])->name('goods-receipts.store');
            Route::get('goods-receipts/{grn}', [CbeAccountingController::class, 'showGoodsReceipt'])->name('goods-receipts.show');
            Route::post('goods-receipts/{grn}/cancel', [CbeAccountingController::class, 'cancelGoodsReceiptAction'])->name('goods-receipts.cancel');
            // NEW 4 Sep 2026 (Task #394 Phase 2) — Purchase Return.
            Route::get('purchase-returns', [CbeAccountingController::class, 'purchaseReturns'])->name('purchase-returns');
            Route::get('goods-receipts/{grn}/return', [CbeAccountingController::class, 'createPurchaseReturn'])->name('purchase-returns.create');
            Route::post('goods-receipts/{grn}/return', [CbeAccountingController::class, 'storePurchaseReturn'])->name('purchase-returns.store');
            Route::get('purchase-returns/{return}', [CbeAccountingController::class, 'showPurchaseReturn'])->name('purchase-returns.show');
            Route::post('purchase-returns/{return}/cancel', [CbeAccountingController::class, 'cancelPurchaseReturnAction'])->name('purchase-returns.cancel');
            // NEW 4 Sep 2026 (Task #394 Phase 2) — Supplier Invoice 3-way matching.
            Route::get('goods-receipts/{grn}/invoice', [CbeAccountingController::class, 'createSupplierInvoiceFromGrn'])->name('supplier-invoice.create');
            Route::post('goods-receipts/{grn}/invoice', [CbeAccountingController::class, 'storeSupplierInvoiceFromGrn'])->name('supplier-invoice.store');
            // NEW 4 Sep 2026 (Task #394 Phase 2) — Budget & Purchase Commitment.
            Route::get('budgets', [CbeAccountingController::class, 'budgets'])->name('budgets');
            Route::get('budgets/create', [CbeAccountingController::class, 'createBudget'])->name('budgets.create');
            Route::post('budgets', [CbeAccountingController::class, 'storeBudget'])->name('budgets.store');
            Route::get('budgets/{budget}/edit', [CbeAccountingController::class, 'editBudget'])->name('budgets.edit');
            Route::put('budgets/{budget}', [CbeAccountingController::class, 'updateBudget'])->name('budgets.update');
            Route::get('reports/budget-utilisation', [CbeAccountingController::class, 'budgetUtilisationReport'])->name('reports.budget-utilisation');
            // NEW 4 Sep 2026 (Task #394 Phase 2) — Purchasing Reports suite.
            Route::get('purchasing-reports', [CbeAccountingController::class, 'purchasingReportsHub'])->name('purchasing-reports-hub');
            // NEW 4 Sep 2026 (Task #394 gap-fix) — Purchasing Audit Trail.
            Route::get('purchasing-audit-log', [CbeAccountingController::class, 'purchasingAuditLog'])->name('purchasing-audit-log');
            Route::get('reports/purchase-requisition-listing', [CbeAccountingController::class, 'purchaseRequisitionListingReport'])->name('reports.purchase-requisition-listing');
            Route::get('reports/cancelled-purchase-orders', [CbeAccountingController::class, 'cancelledPurchaseOrdersReport'])->name('reports.cancelled-purchase-orders');
            Route::get('reports/purchase-return-listing', [CbeAccountingController::class, 'purchaseReturnListingReport'])->name('reports.purchase-return-listing');
            Route::get('reports/po-invoice-matching-variance', [CbeAccountingController::class, 'poInvoiceMatchingVarianceReport'])->name('reports.po-invoice-matching-variance');
            Route::get('reports/unbilled-goods-received', [CbeAccountingController::class, 'unbilledGoodsReceivedReport'])->name('reports.unbilled-goods-received');
            Route::get('reports/pending-approval-listing', [CbeAccountingController::class, 'pendingApprovalListingReport'])->name('reports.pending-approval-listing');
            Route::get('reports/purchase-by-dimension', [CbeAccountingController::class, 'purchaseByDimensionReport'])->name('reports.purchase-by-dimension');
            // NEW 2 Sep 2026 (Task #330) — Fund Accounting.
            Route::get('funds', [CbeAccountingController::class, 'funds'])->name('funds');
            Route::post('funds', [CbeAccountingController::class, 'storeFund'])->name('funds.store');
            Route::post('funds/{fund}/deactivate', [CbeAccountingController::class, 'deactivateFund'])->name('funds.deactivate');
            Route::get('donation-entry', [CbeAccountingController::class, 'createDonationEntry'])->name('donation-entry.create');
            Route::post('donation-entry', [CbeAccountingController::class, 'storeDonationEntry'])->name('donation-entry.store');
            // NEW 3 Sep 2026 (Task #366) — Donation Pledge (GL-integrated,
            // donor-scoped), separate from the event-scoped Donor Register.
            Route::get('donation-pledges', [CbeAccountingController::class, 'donationPledges'])->name('donation-pledges');
            Route::get('donation-pledges/create', [CbeAccountingController::class, 'createDonationPledge'])->name('donation-pledges.create');
            Route::post('donation-pledges', [CbeAccountingController::class, 'storeDonationPledge'])->name('donation-pledges.store');
            Route::get('donation-pledges/{pledge}', [CbeAccountingController::class, 'donationPledgeShow'])->name('donation-pledges.show');
            Route::post('donation-pledges/{pledge}/receipts', [CbeAccountingController::class, 'storePledgeReceipt'])->name('donation-pledges.receipts.store');
            Route::get('reports/fund-balance', [CbeAccountingController::class, 'fundBalanceReport'])->name('reports.fund-balance');
            // NEW 2 Sep 2026 (Task #339) — Cash & Bank Position.
            Route::get('reports/cash-bank-position', [CbeAccountingController::class, 'cashBankPositionReport'])->name('reports.cash-bank-position');
            Route::get('reports/fixed-asset-schedule', [CbeAccountingController::class, 'fixedAssetScheduleReport'])->name('reports.fixed-asset-schedule');
            Route::get('reports/monthly-financial-summary', [CbeAccountingController::class, 'monthlyFinancialSummaryReport'])->name('reports.monthly-financial-summary');
            Route::get('reports/prior-year-comparison', [CbeAccountingController::class, 'priorYearComparisonReport'])->name('reports.prior-year-comparison');
            Route::get('reports/office-bearer-list', [CbeAccountingController::class, 'officeBearerListReport'])->name('reports.office-bearer-list');
            Route::get('reports/trial-balance', [CbeAccountingController::class, 'trialBalance'])->name('reports.trial-balance');
            Route::get('reports/balance-sheet', [CbeAccountingController::class, 'balanceSheet'])->name('reports.balance-sheet');
            Route::get('reports/profit-loss', [CbeAccountingController::class, 'profitLoss'])->name('reports.profit-loss');
            Route::get('reports/general-ledger', [CbeAccountingController::class, 'generalLedger'])->name('reports.general-ledger');
            Route::get('reports/ap-aging', [CbeAccountingController::class, 'apAging'])->name('reports.ap-aging');
            // NEW 3 Sep 2026 (Task #375) — AP Reports hub + Supplier
            // Statement + every downloadable AP report beyond AP Aging.
            Route::get('reports/ap-reports', [CbeAccountingController::class, 'apReportsHub'])->name('reports.ap-reports');
            Route::get('reports/supplier-statement-picker', [CbeAccountingController::class, 'supplierStatementPicker'])->name('reports.supplier-statement-picker');
            Route::get('reports/supplier-statement', [CbeAccountingController::class, 'supplierStatement'])->name('reports.supplier-statement');
            Route::get('reports/ap-outstanding-payables', [CbeAccountingController::class, 'apOutstandingPayablesReport'])->name('reports.ap-outstanding-payables');
            Route::get('reports/bill-listing', [CbeAccountingController::class, 'billListingReport'])->name('reports.bill-listing');
            Route::get('reports/ap-payment-listing', [CbeAccountingController::class, 'apPaymentListingReport'])->name('reports.ap-payment-listing');
            Route::get('reports/ap-credit-note-listing', [CbeAccountingController::class, 'apCreditNoteListingReport'])->name('reports.ap-credit-note-listing');
            Route::get('reports/ap-debit-note-listing', [CbeAccountingController::class, 'apDebitNoteListingReport'])->name('reports.ap-debit-note-listing');
            Route::get('reports/supplier-balance', [CbeAccountingController::class, 'supplierBalanceReport'])->name('reports.supplier-balance');
            Route::get('reports/ap-gl-reconciliation', [CbeAccountingController::class, 'apGlReconciliationReport'])->name('reports.ap-gl-reconciliation');
            Route::get('reports/monthly-ap-summary', [CbeAccountingController::class, 'monthlyApSummaryReport'])->name('reports.monthly-ap-summary');
            Route::get('reports/ap-transaction-report', [CbeAccountingController::class, 'apTransactionReport'])->name('reports.ap-transaction-report');
            Route::get('reports/expense-summary-by-supplier', [CbeAccountingController::class, 'expenseSummaryBySupplierReport'])->name('reports.expense-summary-by-supplier');
            Route::get('reports/expense-summary-by-category', [CbeAccountingController::class, 'expenseSummaryByCategoryReport'])->name('reports.expense-summary-by-category');
            // NEW 29 Aug 2026 (Task #313), REBUILT 30 Aug 2026 (Task #317)
            // onto the native ledger — see CbeAccountingController header.
            Route::get('customers', [CbeAccountingController::class, 'customers'])->name('customers');
            Route::get('customers/create', [CbeAccountingController::class, 'createCustomer'])->name('customers.create');
            Route::post('customers', [CbeAccountingController::class, 'storeCustomer'])->name('customers.store');
            Route::get('customers/agent-typeahead', [CbeAccountingController::class, 'customerAgentTypeahead'])->name('customers.agent-typeahead');
            Route::get('customers/phone-check', [CbeAccountingController::class, 'customerPhoneCheck'])->name('customers.phone-check');
            // NEW 2 Sep 2026 (Task #359) — Debtor Account Enquiry.
            Route::get('customers/{customer}/enquiry', [CbeAccountingController::class, 'customerEnquiry'])->name('customers.enquiry');
            Route::get('invoices', [CbeAccountingController::class, 'invoices'])->name('invoices');
            Route::get('invoices/create', [CbeAccountingController::class, 'createInvoice'])->name('invoices.create');
            Route::post('invoices', [CbeAccountingController::class, 'storeInvoice'])->name('invoices.store');
            Route::post('invoices/{invoice}/pay', [CbeAccountingController::class, 'payInvoice'])->name('invoices.pay');
            // NEW 2 Sep 2026 (Task #359) — Invoice Enquiry, Receipt
            // Enquiry (= Payment History), Outstanding Balance Enquiry.
            Route::get('invoice-enquiry', [CbeAccountingController::class, 'invoiceEnquiry'])->name('invoice-enquiry');
            Route::get('invoice-enquiry/{invoice}', [CbeAccountingController::class, 'invoiceEnquiryShow'])->name('invoice-enquiry.show');
            Route::get('receipt-enquiry', [CbeAccountingController::class, 'receiptEnquiry'])->name('receipt-enquiry');
            Route::get('receipt-enquiry/{payment}', [CbeAccountingController::class, 'receiptEnquiryShow'])->name('receipt-enquiry.show');
            Route::get('outstanding-balance-enquiry', [CbeAccountingController::class, 'outstandingBalanceEnquiry'])->name('outstanding-balance-enquiry');
            Route::get('reports/ar-aging', [CbeAccountingController::class, 'arAging'])->name('reports.ar-aging');
            Route::get('fixed-assets', [CbeAccountingController::class, 'fixedAssets'])->name('fixed-assets');
            Route::get('fixed-assets/create', [CbeAccountingController::class, 'createFixedAsset'])->name('fixed-assets.create');
            Route::post('fixed-assets', [CbeAccountingController::class, 'storeFixedAsset'])->name('fixed-assets.store');
            // NEW 2 Sep 2026 (Task #329) — depreciation posting + disposal.
            Route::get('fixed-assets/{asset}', [CbeAccountingController::class, 'showFixedAsset'])->name('fixed-assets.show');
            Route::post('fixed-assets/{asset}/depreciate', [CbeAccountingController::class, 'postAssetDepreciation'])->name('fixed-assets.depreciate');
            Route::post('fixed-assets/{asset}/dispose', [CbeAccountingController::class, 'disposeAsset'])->name('fixed-assets.dispose');
            // NEW 4 Sep 2026 (Task #395) — Asset Improvement / Asset Transfer.
            Route::get('fixed-assets/{asset}/improve', [CbeAccountingController::class, 'createAssetImprovement'])->name('fixed-assets.improve');
            Route::post('fixed-assets/{asset}/improve', [CbeAccountingController::class, 'storeAssetImprovement'])->name('fixed-assets.improve.store');
            Route::get('fixed-assets/{asset}/transfer', [CbeAccountingController::class, 'createAssetTransfer'])->name('fixed-assets.transfer');
            Route::post('fixed-assets/{asset}/transfer', [CbeAccountingController::class, 'storeAssetTransfer'])->name('fixed-assets.transfer.store');
            // NEW 3 Sep 2026 (Task #388) — Fixed Asset gap-fix: edit +
            // Disposal Listing / Depreciation Listing reports.
            Route::get('fixed-assets/{asset}/edit', [CbeAccountingController::class, 'editFixedAsset'])->name('fixed-assets.edit');
            Route::put('fixed-assets/{asset}', [CbeAccountingController::class, 'updateFixedAsset'])->name('fixed-assets.update');
            Route::get('reports/fixed-asset-disposal-listing', [CbeAccountingController::class, 'assetDisposalListingReport'])->name('reports.fixed-asset-disposal-listing');
            Route::get('reports/depreciation-listing', [CbeAccountingController::class, 'depreciationListingReport'])->name('reports.depreciation-listing');
            Route::get('bank-reconciliations', [CbeAccountingController::class, 'bankReconciliations'])->name('bank-reconciliations');
            Route::get('bank-reconciliations/create', [CbeAccountingController::class, 'createBankReconciliation'])->name('bank-reconciliations.create');
            Route::post('bank-reconciliations', [CbeAccountingController::class, 'storeBankReconciliation'])->name('bank-reconciliations.store');
            // NEW 2 Sep 2026 (Task #336) — line-level matching.
            Route::get('bank-reconciliations/{reconciliation}', [CbeAccountingController::class, 'showBankReconciliation'])->name('bank-reconciliations.show');
            Route::post('bank-reconciliations/{reconciliation}/lines', [CbeAccountingController::class, 'storeReconciliationLines'])->name('bank-reconciliations.lines.store');
            Route::post('bank-reconciliations/{reconciliation}/complete', [CbeAccountingController::class, 'completeBankReconciliation'])->name('bank-reconciliations.complete');
            // NEW 2 Sep 2026 (Task #349) — Month-End Reconciliation Lock: Admin-only Reopen.
            Route::post('bank-reconciliations/{reconciliation}/reopen', [CbeAccountingController::class, 'reopenBankReconciliation'])->name('bank-reconciliations.reopen');
            Route::post('bank-reconciliation-lines/{line}/match', [CbeAccountingController::class, 'matchReconciliationLine'])->name('bank-reconciliation-lines.match');
            Route::post('bank-reconciliation-lines/{line}/unmatch', [CbeAccountingController::class, 'unmatchReconciliationLine'])->name('bank-reconciliation-lines.unmatch');
            Route::post('bank-reconciliation-lines/{line}/outstanding', [CbeAccountingController::class, 'markReconciliationLineOutstanding'])->name('bank-reconciliation-lines.outstanding');
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
            // upgrade, Phase 3: matching workspace, matched items list,
            // Bank Adjustment Entry.
            Route::get('bank-reconciliations/{reconciliation}/match', [CbeAccountingController::class, 'matchingWorkspace'])->name('bank-reconciliations.match');
            Route::post('bank-reconciliations/{reconciliation}/auto-match', [CbeAccountingController::class, 'autoMatchAction'])->name('bank-reconciliations.auto-match');
            Route::post('bank-reconciliations/{reconciliation}/manual-match', [CbeAccountingController::class, 'storeManualMatch'])->name('bank-reconciliations.manual-match');
            Route::get('bank-reconciliations/{reconciliation}/matched', [CbeAccountingController::class, 'matchedItems'])->name('bank-reconciliations.matched');
            Route::post('bank-reconciliation-matches/{group}/unmatch', [CbeAccountingController::class, 'unmatchGroupAction'])->name('bank-reconciliation-matches.unmatch');
            Route::get('bank-reconciliations/{reconciliation}/adjustment', [CbeAccountingController::class, 'createBankAdjustmentForm'])->name('bank-reconciliations.adjustment.create');
            Route::post('bank-reconciliations/{reconciliation}/adjustment', [CbeAccountingController::class, 'storeBankAdjustment'])->name('bank-reconciliations.adjustment.store');
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
            // upgrade, Phase 4: Enquiry screens.
            Route::get('bank-account-enquiry', [CbeAccountingController::class, 'bankAccountEnquiry'])->name('bank-account-enquiry');
            Route::get('bank-transaction-enquiry', [CbeAccountingController::class, 'bankTransactionEnquiry'])->name('bank-transaction-enquiry');
            Route::get('bank-reconciliation-enquiry', [CbeAccountingController::class, 'bankReconciliationEnquiry'])->name('bank-reconciliation-enquiry');
            Route::get('unmatched-transaction-enquiry', [CbeAccountingController::class, 'unmatchedTransactionEnquiry'])->name('unmatched-transaction-enquiry');
            // NEW 3 Sep 2026 (Task #388) — Bank Reconciliation Listing report.
            Route::get('reports/bank-reconciliation-listing', [CbeAccountingController::class, 'bankReconciliationListingReport'])->name('reports.bank-reconciliation-listing');
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
            // upgrade, Phase 5: Reports suite + hub.
            Route::get('bank-reconciliation-reports-hub', [CbeAccountingController::class, 'bankReconciliationReportsHub'])->name('bank-reconciliation-reports-hub');
            Route::get('reports/bank-account-listing', [CbeAccountingController::class, 'bankAccountListingReport'])->name('reports.bank-account-listing');
            Route::get('reports/bank-transaction-report', [CbeAccountingController::class, 'bankTransactionReport'])->name('reports.bank-transaction-report');
            Route::get('reports/bank-reconciliation-statement/{reconciliation}', [CbeAccountingController::class, 'bankReconciliationStatementReport'])->name('reports.bank-reconciliation-statement');
            Route::get('reports/outstanding-cheques', [CbeAccountingController::class, 'outstandingChequeReport'])->name('reports.outstanding-cheques');
            Route::get('reports/deposits-in-transit', [CbeAccountingController::class, 'depositsInTransitReport'])->name('reports.deposits-in-transit');
            Route::get('reports/unmatched-bank-transactions', [CbeAccountingController::class, 'unmatchedBankTransactionReport'])->name('reports.unmatched-bank-transactions');
            Route::get('reports/bank-charges', [CbeAccountingController::class, 'bankChargesReport'])->name('reports.bank-charges');
            Route::get('reports/bank-interest', [CbeAccountingController::class, 'bankInterestReport'])->name('reports.bank-interest');
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
            // upgrade, Phase 7: Approval workflow + Audit Trail.
            Route::get('bank-reconciliation-audit-log', [CbeAccountingController::class, 'bankReconciliationAuditLog'])->name('bank-reconciliation-audit-log');
            // NEW 2 Sep 2026 (Task #337) — Petty Cash (imprest system).
            Route::get('petty-cash-funds', [CbeAccountingController::class, 'pettyCashFunds'])->name('petty-cash-funds');
            Route::get('petty-cash-funds/create', [CbeAccountingController::class, 'createPettyCashFund'])->name('petty-cash-funds.create');
            Route::post('petty-cash-funds', [CbeAccountingController::class, 'storePettyCashFund'])->name('petty-cash-funds.store');
            Route::get('petty-cash-funds/{fund}', [CbeAccountingController::class, 'showPettyCashFund'])->name('petty-cash-funds.show');
            Route::post('petty-cash-funds/{fund}/vouchers', [CbeAccountingController::class, 'storePettyCashVoucher'])->name('petty-cash-funds.vouchers.store');
            Route::post('petty-cash-funds/{fund}/topup', [CbeAccountingController::class, 'storePettyCashTopup'])->name('petty-cash-funds.topup');
            // NEW 30 Aug 2026 (Task #320) — manual Journal Voucher entry.
            Route::get('journal-vouchers', [CbeAccountingController::class, 'journalVouchers'])->name('journal-vouchers');
            Route::get('journal-vouchers/create', [CbeAccountingController::class, 'createJournalVoucher'])->name('journal-vouchers.create');
            Route::post('journal-vouchers', [CbeAccountingController::class, 'storeJournalVoucher'])->name('journal-vouchers.store');
            // NEW 2 Sep 2026 (Task #339) — Opening Balances.
            Route::get('opening-balances', [CbeAccountingController::class, 'openingBalances'])->name('opening-balances');
            Route::post('opening-balances', [CbeAccountingController::class, 'storeOpeningBalances'])->name('opening-balances.store');
            Route::get('journal-vouchers/{journal}', [CbeAccountingController::class, 'showJournalVoucher'])->name('journal-vouchers.show');
            // NEW 3 Sep 2026 (Task #389) — Journal Voucher attachment download.
            Route::get('journal-vouchers/{journal}/attachment', [CbeAccountingController::class, 'downloadJournalAttachment'])->name('journal-vouchers.attachment');
            // NEW 3 Sep 2026 (Task #382) — Journal Type + Cost Centre master files.
            Route::get('journal-types', [CbeAccountingController::class, 'journalTypes'])->name('journal-types');
            Route::post('journal-types', [CbeAccountingController::class, 'storeJournalType'])->name('journal-types.store');
            Route::post('journal-types/{type}/deactivate', [CbeAccountingController::class, 'deactivateJournalType'])->name('journal-types.deactivate');
            Route::get('cost-centres', [CbeAccountingController::class, 'costCentres'])->name('cost-centres');
            Route::post('cost-centres', [CbeAccountingController::class, 'storeCostCentre'])->name('cost-centres.store');
            Route::post('cost-centres/{centre}/deactivate', [CbeAccountingController::class, 'deactivateCostCentre'])->name('cost-centres.deactivate');
            // NEW 4 Sep 2026 (Task #395) — Asset Category / Asset Location masters.
            Route::get('asset-categories', [CbeAccountingController::class, 'assetCategories'])->name('asset-categories');
            Route::post('asset-categories', [CbeAccountingController::class, 'storeAssetCategory'])->name('asset-categories.store');
            // NEW 22 Sep 2026 -- per Chris: Add / Search-Edit split, same
            // pattern as Chart of Accounts.
            Route::get('asset-categories/create', [CbeAccountingController::class, 'createAssetCategory'])->name('asset-categories.create');
            Route::get('asset-categories/search', [CbeAccountingController::class, 'assetCategoriesSearch'])->name('asset-categories.search');
            Route::get('asset-categories/typeahead', [CbeAccountingController::class, 'assetCategoryTypeahead'])->name('asset-categories.typeahead');
            Route::post('asset-categories/{category}/deactivate', [CbeAccountingController::class, 'deactivateAssetCategory'])->name('asset-categories.deactivate');
            Route::get('asset-locations', [CbeAccountingController::class, 'assetLocations'])->name('asset-locations');
            Route::post('asset-locations', [CbeAccountingController::class, 'storeAssetLocation'])->name('asset-locations.store');
            Route::post('asset-locations/{location}/deactivate', [CbeAccountingController::class, 'deactivateAssetLocation'])->name('asset-locations.deactivate');
            // NEW 4 Sep 2026 (Task #395 Phase 3/4) — Asset Enquiry search,
            // remaining FA Reports, Reports Hub, Fixed Asset Audit Trail.
            Route::get('asset-enquiry', [CbeAccountingController::class, 'assetEnquiry'])->name('asset-enquiry');
            Route::get('reports/asset-acquisition', [CbeAccountingController::class, 'assetAcquisitionReport'])->name('reports.asset-acquisition');
            Route::get('reports/asset-transfer', [CbeAccountingController::class, 'assetTransferReport'])->name('reports.asset-transfer');
            Route::get('reports/asset-write-off', [CbeAccountingController::class, 'assetWriteOffReport'])->name('reports.asset-write-off');
            Route::get('reports/asset-by-location', [CbeAccountingController::class, 'assetByLocationReport'])->name('reports.asset-by-location');
            Route::get('reports/asset-by-department', [CbeAccountingController::class, 'assetByDepartmentReport'])->name('reports.asset-by-department');
            Route::get('reports/asset-by-fund', [CbeAccountingController::class, 'assetByFundReport'])->name('reports.asset-by-fund');
            Route::get('fixed-asset-reports-hub', [CbeAccountingController::class, 'fixedAssetReportsHub'])->name('fixed-asset-reports-hub');
            Route::get('fixed-asset-audit-log', [CbeAccountingController::class, 'fixedAssetAuditLog'])->name('fixed-asset-audit-log');
            // NEW 3 Sep 2026 (Task #383) — dedicated Adjustment/Accrual
            // Journal screens + Recurring Journal templates.
            Route::get('adjustment-journals', [CbeAccountingController::class, 'adjustmentJournals'])->name('adjustment-journals');
            Route::get('adjustment-journals/create', [CbeAccountingController::class, 'createAdjustmentJournal'])->name('adjustment-journals.create');
            Route::post('adjustment-journals', [CbeAccountingController::class, 'storeAdjustmentJournal'])->name('adjustment-journals.store');
            Route::get('accrual-journals', [CbeAccountingController::class, 'accrualJournals'])->name('accrual-journals');
            Route::get('accrual-journals/create', [CbeAccountingController::class, 'createAccrualJournal'])->name('accrual-journals.create');
            Route::post('accrual-journals', [CbeAccountingController::class, 'storeAccrualJournal'])->name('accrual-journals.store');
            Route::get('recurring-journal-templates', [CbeAccountingController::class, 'recurringJournalTemplates'])->name('recurring-journal-templates');
            Route::get('recurring-journal-templates/create', [CbeAccountingController::class, 'createRecurringJournalTemplate'])->name('recurring-journal-templates.create');
            Route::post('recurring-journal-templates', [CbeAccountingController::class, 'storeRecurringJournalTemplate'])->name('recurring-journal-templates.store');
            Route::post('recurring-journal-templates/{template}/generate', [CbeAccountingController::class, 'generateRecurringJournal'])->name('recurring-journal-templates.generate');
            Route::post('recurring-journal-templates/{template}/deactivate', [CbeAccountingController::class, 'deactivateRecurringJournalTemplate'])->name('recurring-journal-templates.deactivate');
            // NEW 3 Sep 2026 (Task #384) — GL Enquiry screens (on-screen,
            // no download): Chart of Accounts, General Ledger, Journal,
            // Trial Balance.
            Route::get('chart-of-accounts-enquiry', [CbeAccountingController::class, 'chartOfAccountsEnquiry'])->name('chart-of-accounts-enquiry');
            Route::get('general-ledger-enquiry', [CbeAccountingController::class, 'generalLedgerEnquiry'])->name('general-ledger-enquiry');
            Route::get('journal-enquiry', [CbeAccountingController::class, 'journalEnquiry'])->name('journal-enquiry');
            Route::get('trial-balance-enquiry', [CbeAccountingController::class, 'trialBalanceEnquiry'])->name('trial-balance-enquiry');
            // NEW 3 Sep 2026 (Task #385) — GL Reports hub + six
            // downloadable reports beyond Trial Balance/Balance Sheet/P&L/
            // General Ledger (already built earlier).
            Route::get('reports/gl-reports', [CbeAccountingController::class, 'glReportsHub'])->name('reports.gl-reports');
            Route::get('reports/journal-listing', [CbeAccountingController::class, 'journalListingReport'])->name('reports.journal-listing');
            Route::get('reports/gl-account-balance', [CbeAccountingController::class, 'accountBalanceReport'])->name('reports.gl-account-balance');
            Route::get('reports/gl-monthly-summary', [CbeAccountingController::class, 'monthlyGlSummaryReport'])->name('reports.gl-monthly-summary');
            Route::get('reports/unposted-journals', [CbeAccountingController::class, 'unpostedJournalsReport'])->name('reports.unposted-journals');
            Route::get('reports/reversal-listing', [CbeAccountingController::class, 'reversalListingReport'])->name('reports.reversal-listing');
            Route::get('reports/adjustment-journal-listing', [CbeAccountingController::class, 'adjustmentJournalReport'])->name('reports.adjustment-journal-listing');
            // NEW 3 Sep 2026 (Task #386) — GL Control/Integration:
            // Integration Status board + Fixed Asset/GL and Bank
            // Reconciliation/GL reconciliation reports. Duplicate-posting
            // protection has no route — it's a guard inside
            // CbeAccountingService, not a screen.
            Route::get('integration-status', [CbeAccountingController::class, 'integrationStatus'])->name('integration-status');
            Route::get('reports/fa-gl-reconciliation', [CbeAccountingController::class, 'fixedAssetGlReconciliationReport'])->name('reports.fa-gl-reconciliation');
            Route::get('reports/bank-reconciliation-gl', [CbeAccountingController::class, 'bankReconciliationGlReport'])->name('reports.bank-reconciliation-gl');
            // NEW 2 Sep 2026 (Task #338) — Void/Reversal control + the
            // general Transaction History / audit trail screen.
            Route::post('journal-vouchers/{journal}/void', [CbeAccountingController::class, 'voidJournalAction'])->name('journal-vouchers.void');
            Route::get('transaction-history', [CbeAccountingController::class, 'transactionHistory'])->name('transaction-history');
            Route::get('reports/cash-flow-statement', [CbeAccountingController::class, 'cashFlowStatement'])->name('reports.cash-flow-statement');
            // NEW 28 Aug 2026 — per accounting-integration evaluation report:
            // AutoCount/SQL Account/Million have no live API to sync to, so
            // instead of a real-time connector we ship a flat, generic
            // journal CSV any external accountant/software can re-import.
            Route::get('reports/journal-export', [CbeAccountingController::class, 'journalExportCsv'])->name('reports.journal-export');
            // NEW 1 Sep 2026 (Task #328) — Fiscal Period Lock, the
            // treasurer's month-end close control.
            Route::get('periods', [CbeAccountingController::class, 'periods'])->name('periods');
            Route::post('periods/close', [CbeAccountingController::class, 'closePeriod'])->name('periods.close');
            Route::post('periods/reopen', [CbeAccountingController::class, 'reopenPeriod'])->name('periods.reopen');
            // NEW 4 Sep 2026 (Task #391) — Document Number Control.
            Route::get('document-number-control', [CbeAccountingController::class, 'documentNumberControl'])->name('document-number-control');
            Route::get('document-number-control/reset', [CbeAccountingController::class, 'documentNumberControlReset'])->name('document-number-control.reset');
            Route::post('document-number-control/reset', [CbeAccountingController::class, 'storeDocumentNumberControlReset'])->name('document-number-control.reset.store');
            // NEW 2 Sep 2026 (Task #332) — Year-End Closing.
            Route::get('year-end-closing', [CbeAccountingController::class, 'yearEndClosing'])->name('year-end-closing');
            Route::post('year-end-closing/checklist', [CbeAccountingController::class, 'saveYearEndChecklist'])->name('year-end-closing.checklist');
            Route::post('year-end-closing/close', [CbeAccountingController::class, 'closeYear'])->name('year-end-closing.close');
            Route::get('year-end-closing/pack', [CbeAccountingController::class, 'yearEndPack'])->name('year-end-closing.pack');
            // NEW 2 Sep 2026 (Task #339) — ROS Submission Checklist.
            Route::get('ros-submission-checklist', [CbeAccountingController::class, 'rosSubmissionChecklist'])->name('ros-submission-checklist');
            Route::post('ros-submission-checklist', [CbeAccountingController::class, 'saveRosSubmissionChecklist'])->name('ros-submission-checklist.store');
            // NEW 2 Sep 2026 (Task #334) — Maker-Checker approval workflow.
            Route::get('approvals', [CbeAccountingController::class, 'approvals'])->name('approvals');
            Route::post('approvals/{type}/{id}/approve', [CbeAccountingController::class, 'approveApprovalItem'])->name('approvals.approve');
            Route::post('approvals/{type}/{id}/reject', [CbeAccountingController::class, 'rejectApprovalItem'])->name('approvals.reject');
            Route::get('approval-settings', [CbeAccountingController::class, 'approvalSettingsForm'])->name('approval-settings');
            Route::post('approval-settings', [CbeAccountingController::class, 'saveApprovalSettingsForm'])->name('approval-settings.store');
            // NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module
            // upgrade, Phase 1: Reconciliation Rules.
            Route::get('bank-reconciliation-rules', [CbeAccountingController::class, 'bankReconciliationRulesForm'])->name('bank-reconciliation-rules');
            Route::post('bank-reconciliation-rules', [CbeAccountingController::class, 'saveBankReconciliationRulesForm'])->name('bank-reconciliation-rules.store');
            // NEW 2 Sep 2026 (Task #335) — configurable Tax Rates, per node.
            Route::get('tax-rates', [CbeAccountingController::class, 'taxRates'])->name('tax-rates');
            Route::post('tax-rates', [CbeAccountingController::class, 'storeTaxRate'])->name('tax-rates.store');
            Route::post('tax-rates/{rateId}/deactivate', [CbeAccountingController::class, 'deactivateTaxRate'])->name('tax-rates.deactivate');
            // NEW 2 Sep 2026 (Task #357) — AR Master File: Debtor Category.
            Route::get('customer-categories', [CbeAccountingController::class, 'customerCategories'])->name('customer-categories');
            Route::post('customer-categories', [CbeAccountingController::class, 'storeCustomerCategory'])->name('customer-categories.store');
            Route::post('customer-categories/{categoryId}/deactivate', [CbeAccountingController::class, 'deactivateCustomerCategory'])->name('customer-categories.deactivate');
            // NEW 2 Sep 2026 (Task #357) — AR Master File: Payment Method.
            Route::get('payment-methods', [CbeAccountingController::class, 'paymentMethods'])->name('payment-methods');
            Route::post('payment-methods', [CbeAccountingController::class, 'storePaymentMethod'])->name('payment-methods.store');
            Route::post('payment-methods/{methodId}/deactivate', [CbeAccountingController::class, 'deactivatePaymentMethod'])->name('payment-methods.deactivate');
            // NEW 2 Sep 2026 (Task #357) — AR Master File: Payment Terms.
            Route::get('payment-terms', [CbeAccountingController::class, 'paymentTerms'])->name('payment-terms');
            Route::post('payment-terms', [CbeAccountingController::class, 'storePaymentTerm'])->name('payment-terms.store');
            Route::post('payment-terms/{termId}/deactivate', [CbeAccountingController::class, 'deactivatePaymentTerm'])->name('payment-terms.deactivate');
        });

        // NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
        // Management Module, Phase 1: Document Upload + PDF Statement
        // Extraction. Sits alongside (not inside) the accounting prefix
        // group above since this module has its own controller.
        Route::prefix('ai-accounting')->name('ai-accounting.')->group(function () {
            Route::get('/', [AiAccountingController::class, 'index'])->name('index');
            Route::get('batches/create', [AiAccountingController::class, 'createBatchForm'])->name('batches.create');
            Route::post('batches', [AiAccountingController::class, 'storeBatch'])->name('batches.store');
            Route::get('batches/{batch}', [AiAccountingController::class, 'showBatch'])->name('batches.show');
            // NEW 16 Sep 2026 — per Chris: "you should have delete option"
            Route::post('batches/{batch}/delete', [AiAccountingController::class, 'destroyBatch'])->name('batches.destroy');
            Route::get('documents/{document}', [AiAccountingController::class, 'showDocument'])->name('documents.show');
            Route::get('documents/{document}/download', [AiAccountingController::class, 'downloadDocument'])->name('documents.download');
            Route::post('documents/{document}/commit', [AiAccountingController::class, 'commitDocument'])->name('documents.commit');
            Route::post('extracted-lines/{extraction}/reject', [AiAccountingController::class, 'rejectLine'])->name('extracted-lines.reject');
            Route::post('extracted-lines/{extraction}/confirm-not-duplicate', [AiAccountingController::class, 'confirmNotDuplicate'])->name('extracted-lines.confirm-not-duplicate');
            Route::get('rules', [AiAccountingController::class, 'rules'])->name('rules');
            Route::post('rules/threshold', [AiAccountingController::class, 'updateThreshold'])->name('rules.threshold');
            Route::post('rules/{rule}/toggle', [AiAccountingController::class, 'toggleRule'])->name('rules.toggle');
            Route::get('exceptions', [AiAccountingController::class, 'exceptions'])->name('exceptions');
            Route::get('audit-log', [AiAccountingController::class, 'auditLog'])->name('audit-log');
        });
    });
    Route::middleware(['auth:agent', 'role:ADMIN'])->group(function () {
        Route::get('preview', [CbeDashboard::class, 'preview'])->name('preview');
    });
});

// ADMIN
Route::middleware(['auth:agent', 'role:ADMIN', 'glade.sidebar'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
    Route::get('dashboard/metrics', [AdminDashboard::class, 'metrics'])->name('dashboard.metrics');
    Route::get('dashboard/drilldown', [AdminDashboard::class, 'drilldown'])->name('dashboard.drilldown');
    Route::get('dashboard/chart-drilldown', [AdminDashboard::class, 'chartDrilldown'])->name('dashboard.chart.drilldown');
    Route::get('dashboard/group-typeahead', [AdminDashboard::class, 'groupTypeahead'])->name('dashboard.group-typeahead');
    // NEW 22 Aug 2026 — per Chris: CBE scope picker on this dashboard is
    // cascading dropdowns (HQ -> State -> Temple -> ...), not a name
    // search — this endpoint returns one node's direct children at a time.
    Route::get('dashboard/cbe-children', [AdminDashboard::class, 'cbeChildren'])->name('dashboard.cbe-children');

    // NEW 25 Aug 2026 — per Chris: separate CBE KPI menu for GeneralLink's
    // 3 platform Admin accounts (Director/Finance/Sales departments), with
    // an ALL CBE / per-group (Tao, Rotary Club, etc.) dropdown. The
    // existing DSG/ORG Customer KPI dashboard is deliberately left
    // untouched — this is an additional, separate screen, not a change
    // to it.
    Route::get('cbe-kpi', [AdminCbeKpiController::class, 'index'])->name('cbe-kpi');
    Route::get('cbe-kpi/communication-kpi', [AdminCbeKpiController::class, 'communicationKpi'])->name('cbe-kpi.communication');
    Route::get('cbe-kpi/vendor-marketplace-kpi', [AdminCbeKpiController::class, 'vendorMarketplaceKpi'])->name('cbe-kpi.vendor-marketplace');
    Route::get('cbe-kpi/customer-kpi', [AdminCbeKpiController::class, 'customerKpi'])->name('cbe-kpi.customer');
    Route::get('cbe-kpi/nodes', [AdminCbeKpiController::class, 'nodes'])->name('cbe-kpi.nodes');
    Route::get('cbe-kpi/search-level', [AdminCbeKpiController::class, 'searchLevel'])->name('cbe-kpi.search-level');
    Route::post('cbe-kpi/profile', [AdminCbeKpiController::class, 'updateProfile'])->name('cbe-kpi.update-profile');

    // NEW 27 Aug 2026 — Box 6's merged Bills (Due Soon/Overdue) and
    // Approval Messages (Pending/History) rows now drill down into their
    // own 2-tab screens instead of just showing a plain count.
    Route::get('cbe-kpi/bills', [AdminCbeKpiController::class, 'bills'])->name('cbe-kpi.bills');
    Route::get('cbe-kpi/approvals', [AdminCbeKpiController::class, 'approvals'])->name('cbe-kpi.approvals');

    // NEW 27 Aug 2026 — Box 3 Financial Overview's Income/Expenses/Bank
    // Balance rows now drill down into a 3-tab detail screen.
    Route::get('cbe-kpi/financial-detail', [AdminCbeKpiController::class, 'financialDetail'])->name('cbe-kpi.financial-detail');

    // NEW 27 Aug 2026 — Box 2 Secretarial Overview's 3 rows now drill
    // down into a 3-tab detail screen (Committee/Meetings/Correspondence).
    Route::get('cbe-kpi/secretarial-detail', [AdminCbeKpiController::class, 'secretarialDetail'])->name('cbe-kpi.secretarial-detail');

    // NEW 27 Aug 2026 — Box 6's Approve/Reject action (OTP send/verify,
    // mirroring the vendor agreement acceptance flow).
    Route::get('cbe-kpi/approvals/review/{message}', [AdminCbeKpiController::class, 'approvalReview'])->name('cbe-kpi.approvals.review');
    Route::post('cbe-kpi/approvals/send-otp/{message}', [AdminCbeKpiController::class, 'approvalSendOtp'])->name('cbe-kpi.approvals.send-otp');
    Route::post('cbe-kpi/approvals/decide/{message}', [AdminCbeKpiController::class, 'approvalDecide'])->name('cbe-kpi.approvals.decide');

    // NEW 26 Aug 2026 — per Chris: "under this temple folder beside
    // profile, contact information, KPI, we also need to know who are
    // their member/follower... Customers... Sponsor/donor." 3 new
    // full-screen tabs (4th/5th/6th), each search-first (like Vendor
    // Profile search) with its own typeahead and full profile screen.
    Route::get('cbe-kpi/members', [AdminCbeMembersController::class, 'index'])->name('cbe-kpi.members');
    Route::get('cbe-kpi/members/typeahead', [AdminCbeMembersController::class, 'typeahead'])->name('cbe-kpi.members.typeahead');
    Route::get('cbe-kpi/members/show', [AdminCbeMembersController::class, 'show'])->name('cbe-kpi.members.show');
    Route::post('cbe-kpi/members/status', [AdminCbeMembersController::class, 'updateStatus'])->name('cbe-kpi.members.update-status');
    Route::post('cbe-kpi/members/participation', [AdminCbeMembersController::class, 'storeParticipation'])->name('cbe-kpi.members.store-participation');
    Route::post('cbe-kpi/members/appointment', [AdminCbeMembersController::class, 'storeAppointment'])->name('cbe-kpi.members.store-appointment');
    // NEW 27 Aug 2026 — Member Maintenance: link an existing agent, or
    // register a brand-new one, as a member of this node; plus the
    // multi-select Role Tags save (Volunteer/Follower/Consultant).
    Route::post('cbe-kpi/members/link', [AdminCbeMembersController::class, 'link'])->name('cbe-kpi.members.link');
    Route::get('cbe-kpi/members/customer-typeahead', [AdminCbeMembersController::class, 'customerTypeahead'])->name('cbe-kpi.members.customer-typeahead');
    Route::post('cbe-kpi/members/link-customer', [AdminCbeMembersController::class, 'linkCustomer'])->name('cbe-kpi.members.link-customer');
    Route::get('cbe-kpi/members/vendor-typeahead', [AdminCbeMembersController::class, 'vendorTypeahead'])->name('cbe-kpi.members.vendor-typeahead');
    Route::post('cbe-kpi/members/link-vendor', [AdminCbeMembersController::class, 'linkVendor'])->name('cbe-kpi.members.link-vendor');
    Route::post('cbe-kpi/members/create', [AdminCbeMembersController::class, 'store'])->name('cbe-kpi.members.store');
    Route::post('cbe-kpi/members/tags', [AdminCbeMembersController::class, 'updateTags'])->name('cbe-kpi.members.update-tags');

    Route::get('cbe-kpi/customers', [AdminCbeCustomersController::class, 'index'])->name('cbe-kpi.customers');
    Route::get('cbe-kpi/customers/typeahead', [AdminCbeCustomersController::class, 'typeahead'])->name('cbe-kpi.customers.typeahead');
    Route::get('cbe-kpi/customers/show', [AdminCbeCustomersController::class, 'show'])->name('cbe-kpi.customers.show');
    Route::post('cbe-kpi/customers/participation', [AdminCbeCustomersController::class, 'storeParticipation'])->name('cbe-kpi.customers.store-participation');
    Route::post('cbe-kpi/customers/appointment', [AdminCbeCustomersController::class, 'storeAppointment'])->name('cbe-kpi.customers.store-appointment');

    Route::get('cbe-kpi/donors', [AdminCbeDonorsController::class, 'index'])->name('cbe-kpi.donors');
    Route::get('cbe-kpi/donors/typeahead', [AdminCbeDonorsController::class, 'typeahead'])->name('cbe-kpi.donors.typeahead');
    Route::get('cbe-kpi/donors/show', [AdminCbeDonorsController::class, 'show'])->name('cbe-kpi.donors.show');
    Route::post('cbe-kpi/donors/link', [AdminCbeDonorsController::class, 'linkTemple'])->name('cbe-kpi.donors.link');
    Route::post('cbe-kpi/donors/create', [AdminCbeDonorsController::class, 'store'])->name('cbe-kpi.donors.store');
    // NEW 26 Aug 2026, 18th pass — per Chris: cash donations, in-kind
    // gifts (crystal, hampers, artwork), sponsorships, and auction wins
    // must all be recorded against a donor + occasion (event).
    Route::post('cbe-kpi/donors/contribution', [AdminCbeDonorsController::class, 'storeContribution'])->name('cbe-kpi.donors.store-contribution');
    // NEW 27 Aug 2026 — per Chris: donor profile also needs event
    // participation + appointment history, same as Members/Customers.
    Route::post('cbe-kpi/donors/participation', [AdminCbeDonorsController::class, 'storeParticipation'])->name('cbe-kpi.donors.store-participation');
    Route::post('cbe-kpi/donors/appointment', [AdminCbeDonorsController::class, 'storeAppointment'])->name('cbe-kpi.donors.store-appointment');
    Route::get('cbe-kpi/donors/contribution/receipt', [AdminCbeDonorsController::class, 'downloadReceipt'])->name('cbe-kpi.donors.contribution-receipt');

    // NEW 27 Aug 2026 — per Chris: "master file maintenance ... this is
    // where you set up ... temple, branch, state, HQ." Create-only
    // screen for cbe_hierarchy_nodes (edit already exists via the
    // Profile tab). One flexible screen covers all 4 levels since a
    // CBE community's level names are entirely admin-defined.
    // MOVED 11 Sep 2026 (Task #413 follow-up) — per Chris's decision: a
    // CBE node officer must be able to use Entity Maintenance for their
    // OWN node, so these 2 routes (create/store) no longer live inside
    // this role:ADMIN-only group. They're defined further below in their
    // own group under ['auth:agent', 'cbe.officer.or.admin']. Restructure
    // and Link-to-Group directly below stay platform-Admin-only — Chris's
    // decision only covers the plain create/store screen an officer's
    // sidebar link already pointed at.
    // NEW 10 Sep 2026 (Task #399) — insert a node (at an existing or
    // brand-new level, above/below/between any existing nodes) and
    // optionally move existing nodes to become its children. See the
    // controller for why this is a separate screen from plain create.
    Route::get('cbe-kpi/hierarchy-nodes/restructure', [AdminCbeHierarchyNodeController::class, 'restructure'])->name('cbe-kpi.hierarchy-nodes.restructure');
    Route::post('cbe-kpi/hierarchy-nodes/restructure', [AdminCbeHierarchyNodeController::class, 'storeRestructure'])->name('cbe-kpi.hierarchy-nodes.restructure.store');
    // NEW 11 Sep 2026 (Task #411) — per Chris: a standalone entity (e.g.
    // "Rotary Club Uptown Damansara", or a Temple not yet part of any
    // federation) needs to start as its own thing, then later be moved
    // to become part of a bigger CBE group's real structure (e.g. under
    // Tao's Klang Branch). Restructure (Task #399) only rewires nodes
    // WITHIN one group — this is the cross-group move.
    Route::get('cbe-kpi/hierarchy-nodes/link-to-group', [AdminCbeHierarchyNodeController::class, 'linkToGroup'])->name('cbe-kpi.hierarchy-nodes.link-to-group');
    Route::post('cbe-kpi/hierarchy-nodes/link-to-group', [AdminCbeHierarchyNodeController::class, 'storeLinkToGroup'])->name('cbe-kpi.hierarchy-nodes.link-to-group.store');

    // NEW 26 Aug 2026, 20th pass — per Chris: "any appointment with
    // temple for prayer and advise from sensei... have you
    // incorporate?" 7th tab — temple-wide appointment search.
    Route::get('cbe-kpi/appointments', [AdminCbeAppointmentsController::class, 'index'])->name('cbe-kpi.appointments');

    // NEW 27 Aug 2026 — per Chris: persistent tab bar (Members |
    // Participation | Appointments) must never disappear before/after
    // search. Temple-wide event participation history, sibling screen
    // to cbe-kpi/appointments above.
    Route::get('cbe-kpi/participation', [AdminCbeParticipationController::class, 'index'])->name('cbe-kpi.participation');

    // NEW 26 Aug 2026 — per Chris: "one of the function of accounting is
    // to issue receipt upon a collection... i cannot be double standard
    // one is upload one is key in." Printable receipt, opened from
    // Donor/Members/Customers profiles wherever a collection was auto-
    // receipted by CbeReceiptService.
    Route::get('cbe-kpi/receipts', [AdminCbeReceiptController::class, 'show'])->name('cbe-kpi.receipts.show');
    Route::get('cbe-kpi/receipts/pdf', [AdminCbeReceiptController::class, 'downloadPdf'])->name('cbe-kpi.receipts.pdf');

    // NEW 2 Aug 2026 — Earning Income Ledger (Debit/Credit history for
    // Admin/GL/TL/Introducer's own commission earnings, same idea as
    // the Override Member Ledger). Admin scope: filterable by Group
    // Label / GL / TL / Introducer, or unfiltered = every agent.
    Route::get('earning-ledger', [EarningLedgerController::class, 'index'])->name('earning-ledger');
    Route::get('earning-ledger/statement', [EarningLedgerController::class, 'statement'])->name('earning-ledger.statement');

    // REMOVED 12 Aug 2026 per Chris: "no need to split into 3" — AI
    // Assistant Tickets folded into Help Desk (see help-desk.* routes
    // above). Carolyn now logs straight into help_desk_threads.

    // NEW 25 Jul 2026 — Growth & Outreach Center, Phase 1 (task #209).
    Route::get('growth/channels', [GrowthChannelController::class, 'index'])->name('growth.channels.index');
    Route::post('growth/channels', [GrowthChannelController::class, 'update'])->name('growth.channels.update');

    // NEW 25 Jul 2026 — Growth & Outreach Center (task #211), Admin side.
    Route::get('growth/contests', [AdminContestController::class, 'index'])->name('growth.contests.index');
    Route::post('growth/contests', [AdminContestController::class, 'store'])->name('growth.contests.store');
    Route::post('growth/contests/{id}/toggle-active', [AdminContestController::class, 'toggleActive'])->name('growth.contests.toggle-active');
    Route::get('growth/contests/{id}/winners', [AdminContestController::class, 'winners'])->name('growth.contests.winners');
    Route::post('growth/contests/{id}/announce', [AdminContestController::class, 'announce'])->name('growth.contests.announce');
    Route::post('growth/contests/award/{awardId}/mark-awarded', [AdminContestController::class, 'markAwarded'])->name('growth.contests.mark-awarded');

    // NEW 25 Jul 2026 — Growth & Outreach Center (task #215), Admin only.
    Route::get('growth/broadcasts', [BroadcastCampaignController::class, 'index'])->name('growth.broadcasts.index');
    Route::post('growth/broadcasts', [BroadcastCampaignController::class, 'store'])->name('growth.broadcasts.store');
    Route::post('growth/broadcasts/{id}/send', [BroadcastCampaignController::class, 'send'])->name('growth.broadcasts.send');
    Route::delete('growth/broadcasts/{id}', [BroadcastCampaignController::class, 'destroy'])->name('growth.broadcasts.destroy');

    // REPLACED 25 Jul 2026 — Survey Management Module, Phase 1 (task
    // #227, #231). Full spec rebuild, Admin side. Distribution/response
    // collection (Phase 2), analytics/reports (Phase 3), AI/templates/
    // notifications (Phase 4), permissions/settings (Phase 5) all come
    // later — see task list #232-#235.
    Route::get('growth/surveys', [AdminSurveyController::class, 'index'])->name('growth.surveys.index');
    Route::get('growth/surveys/create', [AdminSurveyController::class, 'create'])->name('growth.surveys.create');
    Route::post('growth/surveys', [AdminSurveyController::class, 'store'])->name('growth.surveys.store');
    Route::get('growth/surveys/{id}/edit', [AdminSurveyController::class, 'edit'])->name('growth.surveys.edit');
    Route::post('growth/surveys/{id}', [AdminSurveyController::class, 'update'])->name('growth.surveys.update');
    Route::delete('growth/surveys/{id}', [AdminSurveyController::class, 'destroy'])->name('growth.surveys.destroy');
    Route::post('growth/surveys/{id}/duplicate', [AdminSurveyController::class, 'duplicateSurvey'])->name('growth.surveys.duplicate');

    Route::get('growth/surveys/{id}/builder', [AdminSurveyController::class, 'builder'])->name('growth.surveys.builder');
    Route::post('growth/surveys/{id}/questions', [AdminSurveyController::class, 'storeQuestion'])->name('growth.surveys.questions.store');
    Route::post('growth/surveys/{id}/questions/{questionId}', [AdminSurveyController::class, 'updateQuestion'])->name('growth.surveys.questions.update');
    Route::delete('growth/surveys/{id}/questions/{questionId}', [AdminSurveyController::class, 'destroyQuestion'])->name('growth.surveys.questions.destroy');
    Route::post('growth/surveys/{id}/questions/{questionId}/duplicate', [AdminSurveyController::class, 'duplicateQuestion'])->name('growth.surveys.questions.duplicate');
    Route::post('growth/surveys/{id}/questions/{questionId}/move', [AdminSurveyController::class, 'moveQuestion'])->name('growth.surveys.questions.move');

    Route::post('growth/surveys/{id}/logic', [AdminSurveyController::class, 'storeLogic'])->name('growth.surveys.logic.store');
    Route::delete('growth/surveys/{id}/logic/{logicId}', [AdminSurveyController::class, 'destroyLogic'])->name('growth.surveys.logic.destroy');

    Route::get('growth/surveys/{id}/preview', [AdminSurveyController::class, 'preview'])->name('growth.surveys.preview');
    Route::post('growth/surveys/{id}/publish', [AdminSurveyController::class, 'publish'])->name('growth.surveys.publish');
    Route::post('growth/surveys/{id}/activate', [AdminSurveyController::class, 'activate'])->name('growth.surveys.activate');
    Route::post('growth/surveys/{id}/pause', [AdminSurveyController::class, 'pause'])->name('growth.surveys.pause');
    Route::post('growth/surveys/{id}/close', [AdminSurveyController::class, 'close'])->name('growth.surveys.close');
    Route::post('growth/surveys/{id}/archive', [AdminSurveyController::class, 'archive'])->name('growth.surveys.archive');

    // NEW 25 Jul 2026 — Survey Management Module Phase 2 (task #232).
    // Generic public link/QR/embed distribution + Response Management
    // (spec sections 7 + 11). Targeted per-customer sends are the
    // agent-facing Shared\SurveySendController instead (shared route
    // block above).
    Route::get('growth/surveys/{id}/distribute', [AdminSurveyController::class, 'distribute'])->name('growth.surveys.distribute');
    Route::get('growth/surveys/{id}/distribute/qr', [AdminSurveyController::class, 'downloadDistributeQr'])->name('growth.surveys.distribute.qr');
    Route::get('growth/surveys/{id}/responses', [AdminSurveyController::class, 'responses'])->name('growth.surveys.responses');
    Route::get('growth/surveys/{id}/responses/{responseId}', [AdminSurveyController::class, 'responseShow'])->name('growth.surveys.responses.show');
    Route::post('growth/surveys/{id}/push-to-google', [AdminSurveyController::class, 'pushToGoogle'])->name('growth.surveys.push-to-google');

    // NEW 21 Jul 2026 — Document Credit Wallet, Admin side.
    Route::get('document-credit', [AdminDocumentCreditController::class, 'index'])->name('document-credit.index');
    Route::get('document-credit/agent-typeahead', [AdminDocumentCreditController::class, 'agentTypeahead'])->name('document-credit.agent-typeahead');
    Route::get('document-credit/agent/{agentId}', [AdminDocumentCreditController::class, 'agentDetail'])->name('document-credit.agent-detail');
    Route::post('document-credit/settings', [AdminDocumentCreditController::class, 'updateSettings'])->name('document-credit.settings');
    Route::get('document-credit/{requestId}/slip', [AdminDocumentCreditController::class, 'viewSlip'])->name('document-credit.slip');
    Route::post('document-credit/{requestId}/approve', [AdminDocumentCreditController::class, 'approve'])->name('document-credit.approve');
    Route::post('document-credit/{requestId}/reject', [AdminDocumentCreditController::class, 'reject'])->name('document-credit.reject');

    // SUPERSEDED 21 Jul 2026 — Admin's Enquiry inbox was replaced by
    // the shared Help Desk routes above (Admin is just another party
    // there now, not a separate global inbox). See Shared\HelpDeskController.

    // NEW 21 Jul 2026 — Notice Board, Admin side: post, edit, remove
    // broadcast announcements.
    Route::get('notice-board', [AdminNoticeBoardController::class, 'index'])->name('notice-board.index');
    Route::get('notice-board/create', [AdminNoticeBoardController::class, 'create'])->name('notice-board.create');
    Route::post('notice-board', [AdminNoticeBoardController::class, 'store'])->name('notice-board.store');
    Route::get('notice-board/{noticeId}/edit', [AdminNoticeBoardController::class, 'edit'])->name('notice-board.edit');
    Route::post('notice-board/{noticeId}', [AdminNoticeBoardController::class, 'update'])->name('notice-board.update');
    Route::post('notice-board/{noticeId}/delete', [AdminNoticeBoardController::class, 'destroy'])->name('notice-board.destroy');
    Route::get('notice-board/{noticeId}/attachment', [AdminNoticeBoardController::class, 'attachment'])->name('notice-board.attachment');

    // NEW 8 Aug 2026 — "Carolyn help to write" on the Post/Edit Notice
    // screens: Admin can ask Carolyn to polish the title/message she's
    // drafting (fix spelling, better wording, add a couple of relevant
    // emoji) before posting. AJAX, same shared Anthropic key Carolyn's
    // Text Chat already uses — no separate billing.
    Route::post('notice-board/ai-assist', [AdminNoticeBoardController::class, 'aiAssist'])->name('notice-board.ai-assist');

    // NEW 8 Aug 2026 (Task #83) — WhatsApp send audit log: every agent's
    // WhatsApp send attempt, allowed/blocked by recipient scoping and
    // sent/failed by Meta. Admin-only, read-only.
    Route::get('whatsapp-audit', [\App\Http\Controllers\Admin\WhatsAppAuditController::class, 'index'])->name('whatsapp-audit.index');

    // NEW 8 Aug 2026 (Task #91) — GLADE Ecosystem Engagement, Phase 4:
    // Admin-only analytics (deliveries, read rate, AI Insight, top notices).
    Route::get('glade-analytics', [\App\Http\Controllers\Admin\GladeAnalyticsController::class, 'index'])->name('glade-analytics.index');

    // NEW 22 Jul 2026 — Risk Review Queue (business-rule fraud checks:
    // duplicate files/reference numbers, math/date anomalies). Never
    // an automatic genuine/fraudulent verdict — Admin always decides.
    Route::get('fraud-review', [FraudReviewController::class, 'index'])->name('fraud-review.index');
    Route::get('fraud-review/{flagId}', [FraudReviewController::class, 'show'])->name('fraud-review.show');
    Route::post('fraud-review/{flagId}/under-review', [FraudReviewController::class, 'markUnderReview'])->name('fraud-review.under-review');
    Route::post('fraud-review/{flagId}/clear', [FraudReviewController::class, 'clear'])->name('fraud-review.clear');
    Route::post('fraud-review/{flagId}/confirm-fraud', [FraudReviewController::class, 'confirmFraud'])->name('fraud-review.confirm-fraud');
    Route::post('fraud-review/settings', [FraudReviewController::class, 'updateSettings'])->name('fraud-review.settings');

    // Agents
    Route::get('agents', [AgentManagementController::class, 'index'])->name('agents.index');
    Route::get('agents/pending', [PendingAssignmentController::class, 'index'])->name('agents.pending');
    Route::get('agents/{agentId}', [AgentProfileController::class, 'show'])->name('agents.show');
    Route::post('agents/{agentId}/clear-hub-vault', [AgentProfileController::class, 'clearHubVault'])->name('agents.clear-hub-vault');
    Route::post('agents/{agentId}/assign-gl', [PendingAssignmentController::class, 'assign'])->name('agents.assign-gl');

    // Network — AJAX endpoints (must be before parameterised routes)
    Route::get('network/ajax/gls',       [NetworkController::class, 'ajaxGLs'])->name('network.ajax.gls');
    Route::get('network/ajax/tls',       [NetworkController::class, 'ajaxTLs'])->name('network.ajax.tls');
    Route::get('network/ajax/intros',    [NetworkController::class, 'ajaxIntros'])->name('network.ajax.intros');
    Route::get('network/ajax/children',  [NetworkController::class, 'ajaxChildren'])->name('network.ajax.children');
    Route::get('network/ajax/typeahead', [NetworkController::class, 'ajaxTypeahead'])->name('network.ajax.typeahead');

    // Network — Agent edit
    Route::get('network/agent/{id}/edit', [NetworkController::class, 'editAgent'])->name('network.agent.edit');
    Route::put('network/agent/{id}',      [NetworkController::class, 'updateAgent'])->name('network.agent.update');

    // Network — Excel export (must be before parameterised routes)
    Route::get('network/{glId}/export', [NetworkController::class, 'exportGL'])->name('network.gl.export');

    // Network — Drill down
    Route::get('network', [NetworkController::class, 'index'])->name('network');
    Route::get('network/all-tls', [NetworkController::class, 'allTLs'])->name('network.all-tls');
    Route::get('network/all-intros', [NetworkController::class, 'allIntros'])->name('network.all-intros');
    Route::get('network/{glId}', [NetworkController::class, 'byGL'])->name('network.gl');
    Route::get('network/{glId}/{tlId}', [NetworkController::class, 'byTL'])->name('network.tl');
    Route::get('network/{glId}/{tlId}/{introducerId}', [NetworkController::class, 'byIntroducer'])->name('network.introducer');

    // Profile
    Route::get('profile', [AdminProfile::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [AdminProfile::class, 'edit'])->name('profile.edit');
    Route::put('profile', [AdminProfile::class, 'update'])->name('profile.update');
    Route::get('profile/change-password', [AdminProfile::class, 'changePasswordPage'])->name('profile.change-password');
    Route::put('profile/password', [AdminProfile::class, 'changePassword'])->name('profile.password');

    // Postcode lookup
    Route::get('postcode-lookup', function(\Illuminate\Http\Request $request) {
        $postcode = $request->get('postcode');
        $city     = $request->get('city');
        $partial  = $request->get('partial');
        if ($city) {
            $results = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
                ->where('city', 'like', '%'.$city.'%')
                ->orderBy('city')->limit(20)->get();
            return response()->json($results);
        }
        if ($partial) {
            $results = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
                ->where('postcode', 'like', $postcode.'%')
                ->orderBy('postcode')->limit(10)->get();
            return response()->json($results);
        }
        $result = \Illuminate\Support\Facades\DB::table('malaysia_postcodes')
            ->where('postcode', $postcode)->first();
        return response()->json($result ?: ['city' => '', 'state' => '']);
    })->name('postcode.lookup');

    // Vendors
    Route::get('vendors/search-edit', [\App\Http\Controllers\Admin\VendorController::class, 'searchEdit'])->name('vendors.search-edit');
    Route::get('vendors', [\App\Http\Controllers\Admin\VendorController::class, 'index'])->name('vendors.index');
    Route::post('vendors', [\App\Http\Controllers\Admin\VendorController::class, 'store'])->name('vendors.store');
    Route::put('vendors/{id}', [\App\Http\Controllers\Admin\VendorController::class, 'update'])->name('vendors.update');
    Route::post('vendors/{id}/branches', [\App\Http\Controllers\Admin\VendorController::class, 'storeBranch'])->name('vendors.branches.store');
    Route::put('vendors/branches/{id}', [\App\Http\Controllers\Admin\VendorController::class, 'updateBranch'])->name('vendors.branches.update');
    Route::post('vendors/{id}/products/quick-add', [\App\Http\Controllers\Admin\VendorController::class, 'quickAddProducts'])->name('vendors.products.quick-add');
    Route::post('vendors/{id}/create-login', [\App\Http\Controllers\Admin\VendorController::class, 'createLogin'])->name('vendors.create-login');

    // NEW 8 Aug 2026 (Task #92) — Pending Vendor Login Approval queue.
    Route::get('vendors/pending-logins', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'index'])->name('vendors.pending-logins');
    Route::get('vendors/pending-logins/count', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'pendingCount'])->name('vendors.pending-logins.count');
    Route::post('vendors/pending-logins/{vendorId}/approve', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'approve'])->name('vendors.pending-logins.approve');
    Route::post('vendors/pending-logins/{vendorId}/reject', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'reject'])->name('vendors.pending-logins.reject');
    // NEW 13 Aug 2026 — per Chris: real Gmail SMTP just got wired up, and
    // the AWAITING_PASSWORD verification email for a vendor already in
    // that state (sent back when MAIL_MAILER=log, so it never actually
    // delivered) needs a way to go out for real without rejecting and
    // re-approving the whole vendor. Only valid while still
    // AWAITING_PASSWORD — see resendVerification() in the controller.
    Route::post('vendors/pending-logins/{vendorId}/resend-verification', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'resendVerification'])->name('vendors.pending-logins.resend-verification');
    // NEW 8 Aug 2026 — Vendor Management Phase 1: how many Admins must approve a vendor (1 or 2).
    Route::post('vendors/approval-settings', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'approvalSettings'])->name('vendors.approval-settings');
    Route::get('vendors/pending-logins/{vendorId}/document/{type}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'document'])->name('vendors.pending-logins.document')->where('type', 'ssm|profile');
    // NEW 9 Aug 2026 — entity-type document checklist (Task #58/#59): view one uploaded file, and verify/reject it.
    Route::get('vendors/pending-logins/{vendorId}/document-file/{vendorDocumentId}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'documentFile'])->name('vendors.pending-logins.document-file');
    Route::post('vendors/pending-logins/{vendorId}/document-file/{vendorDocumentId}/verify', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'verifyDocument'])->name('vendors.pending-logins.document-verify');
    // NEW 9 Aug 2026 — Due Diligence follow-up: forward a vendor's registration + findings to the Director for review.
    Route::post('vendors/review-director-email', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'directorEmailSetting'])->name('vendors.review-director-email');
    Route::post('vendors/pending-logins/{vendorId}/forward-director', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'forwardToDirector'])->name('vendors.pending-logins.forward-director');
    Route::post('vendors/pending-logins/{vendorId}/qa-message', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'sendQaMessage'])->name('vendors.pending-logins.qa-message');
    Route::get('vendors/pending-logins/{vendorId}/media/{mediaId}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'marketingMediaFile'])->name('vendors.pending-logins.media-file');
    // NEW 12 Aug 2026 — Risk Assessment Results, now a real screen/route (see riskAssessment() controller method).
    Route::get('vendors/pending-logins/{vendorId}/risk-assessment', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'riskAssessment'])->name('vendors.pending-logins.risk-assessment');
    // NEW 12 Aug 2026 — Risk Assessment Results tab: admin-uploaded bankruptcy/insolvency reports (multiple, one per director/shareholder), AI-reviewed on upload.
    Route::post('vendors/pending-logins/{vendorId}/bankruptcy-docs', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'uploadBankruptcyDocuments'])->name('vendors.pending-logins.bankruptcy-upload');
    Route::get('vendors/pending-logins/{vendorId}/bankruptcy-docs/{documentId}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'bankruptcyDocumentFile'])->name('vendors.pending-logins.bankruptcy-file');
    // NEW 12 Aug 2026 — 9th Risk Assessment category: CTOS credit report, same admin-upload + AI-review pattern as bankruptcy.
    Route::post('vendors/pending-logins/{vendorId}/ctos-docs', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'uploadCtosDocuments'])->name('vendors.pending-logins.ctos-upload');
    Route::get('vendors/pending-logins/{vendorId}/ctos-docs/{documentId}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'ctosDocumentFile'])->name('vendors.pending-logins.ctos-file');
    // NEW 12 Aug 2026 (Task #106) — per Chris: "remove all popup content,
    // new screen with proper tab." "View Details," Q&A, and Forward to
    // Director all move from in-page modals to real pages. Declared
    // AFTER every more-specific /pending-logins/{vendorId}/... route
    // above so this single-segment {vendorId} route never intercepts
    // them (Laravel matches by route order + segment count).
    Route::get('vendors/pending-logins/{vendorId}/qa', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'qa'])->name('vendors.pending-logins.qa');
    Route::get('vendors/pending-logins/{vendorId}/forward-director-form', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'forwardDirectorForm'])->name('vendors.pending-logins.forward-director-form');
    Route::get('vendors/pending-logins/{vendorId}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'show'])->name('vendors.pending-logins.show');

    // NEW 13 Aug 2026 — per Chris: "create another workflow program
    // below pending approve in the menu dashboard... Vendor Onboarding
    // and Communication Workflow." Own prefix (vendors/onboarding-
    // workflow/...), separate from vendors/pending-logins/..., so there
    // is no route-ordering collision risk between the two feature's
    // single-segment {vendorId} routes.
    Route::get('vendors/onboarding-workflow', [\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::class, 'index'])->name('vendors.onboarding-workflow');
    Route::post('vendors/onboarding-workflow/{vendorId}/message', [\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::class, 'sendMessage'])->name('vendors.onboarding-workflow.message');
    Route::post('vendors/onboarding-workflow/{vendorId}/amendment', [\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::class, 'requestAmendment'])->name('vendors.onboarding-workflow.amendment');
    Route::post('vendors/onboarding-workflow/{vendorId}/amendment/{amendmentId}/resolve', [\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::class, 'resolveAmendment'])->name('vendors.onboarding-workflow.amendment-resolve');
    // NEW 14 Aug 2026 — per Chris: "Amendment auto-updating final registration copy Please build."
    Route::post('vendors/onboarding-workflow/{vendorId}/amendment/{amendmentId}/apply', [\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::class, 'applyAmendment'])->name('vendors.onboarding-workflow.amendment-apply');
    Route::get('vendors/onboarding-workflow/{vendorId}/message/{messageId}/attachment', [\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::class, 'messageAttachment'])->name('vendors.onboarding-workflow.attachment');
    Route::get('vendors/onboarding-workflow/{vendorId}', [\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::class, 'show'])->name('vendors.onboarding-workflow.show');

    // NEW 14 Aug 2026 — per Chris: "i dont want Approve reject header
    // [on Vendor Registration Detail]... separate program in menu
    // dashboard after workflow." Vendor Approvals: its own prefix
    // (vendors/approvals/...), sits in the menu right below Vendor
    // Onboarding Workflow. Registration Detail (vendors/pending-logins/
    // {vendorId}) stays as pure document viewing; Approve/Reject/Q&A/
    // Forward-to-Director now live only here.
    Route::get('vendors/approvals', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'approvalsIndex'])->name('vendors.approvals');
    // NEW 14 Aug 2026 — per Chris: "when click approved green it will
    // show like a payment voucher form with proper professional cover
    // letter." Declared BEFORE the single-segment {vendorId} route right
    // below, same reasoning as every other more-specific-route-first
    // pattern already used in this file — otherwise "agreement-file"
    // would be swallowed as if it were a vendorId.
    Route::get('vendors/approvals/agreement-file', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'agreementFile'])->name('vendors.approvals.agreement-file');
    Route::get('vendors/approvals/{vendorId}/letter', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'approvalLetter'])->name('vendors.approvals.letter');
    // NEW 14 Aug 2026 — the Sales/Finance/Director tick-box sign-off,
    // one per department, declared before the bare {vendorId} route
    // below per this file's established ordering rule.
    Route::post('vendors/approvals/{vendorId}/gate/{department}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'approveDepartment'])->name('vendors.approvals.gate');
    // NEW 14 Aug 2026 (3rd pass) — per Chris's SOP: only Director Admin can resend the Welcome Letter.
    Route::post('vendors/approvals/{vendorId}/resend-letter', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'resendWelcomeLetter'])->name('vendors.approvals.resend-letter');
    Route::get('vendors/approvals/{vendorId}', [\App\Http\Controllers\Admin\VendorLoginApprovalController::class, 'approvalsShow'])->name('vendors.approvals.show');

    // NEW 14 Aug 2026 — per Chris: "there must be a program to retrieve
    // all past email OTP records to comply the Malaysia's Electronic
    // Commerce Act 2006." Declared as its own top-level 'vendor-agreements'
    // prefix (not nested under vendors/approvals/{vendorId}) since it
    // browses across ALL vendors, not one vendor's own record.
    Route::get('vendor-agreements', [\App\Http\Controllers\Admin\VendorAgreementComplianceController::class, 'index'])->name('vendor-agreements.index');
    Route::get('vendor-agreements/{acceptanceId}', [\App\Http\Controllers\Admin\VendorAgreementComplianceController::class, 'show'])->name('vendor-agreements.show');

    // REMOVED 8 Aug 2026 per Chris: "remove everything ... i want to redo
    // and revamp" — Offer Request Approval queue routes removed.

    // REWRITTEN 18 Jul 2026 — Document Template Library is now a simple
    // per-vendor field checklist (no more calibration/preview-text/
    // test-extraction/calibrate-image endpoints — the live Sales
    // Transaction form reads documents directly via the Claude API).
    Route::get('document-templates', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'index'])->name('document-templates.index');
    Route::get('document-templates/create', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'create'])->name('document-templates.create');
    Route::post('document-templates', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'store'])->name('document-templates.store');
    Route::get('document-templates/{id}/edit', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
    Route::put('document-templates/{id}', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'update'])->name('document-templates.update');
    Route::get('document-templates/{id}/new-version', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'newVersionForm'])->name('document-templates.new-version');
    Route::post('document-templates/{id}/new-version', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'storeNewVersion'])->name('document-templates.new-version.store');
    Route::get('document-templates/group/{groupId}/history', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'history'])->name('document-templates.history');
    Route::post('document-templates/{id}/reactivate', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'reactivate'])->name('document-templates.reactivate');
    Route::patch('document-templates/{id}/toggle', [\App\Http\Controllers\Admin\DocumentTemplateController::class, 'toggle'])->name('document-templates.toggle');

    // Branches
    Route::get('branches/search-edit', [\App\Http\Controllers\Admin\BranchController::class, 'searchEdit'])->name('branches.search-edit');
    Route::get('branches', [\App\Http\Controllers\Admin\BranchController::class, 'index'])->name('branches.index');
    Route::post('branches', [\App\Http\Controllers\Admin\BranchController::class, 'store'])->name('branches.store');
    Route::put('branches/{id}', [\App\Http\Controllers\Admin\BranchController::class, 'update'])->name('branches.update');

    // NEW — Tier Structure Maintenance (Introducer / Team Leader / Group Leader)
    Route::get('masterfile/introducers', [MasterFileController::class, 'introducers'])->name('masterfile.introducers');
    Route::get('masterfile/team-leaders', [MasterFileController::class, 'teamLeaders'])->name('masterfile.team-leaders');

    // NEW — Team Leader Maintenance, exact same pattern as Introducer.
    Route::get('masterfile/team-leaders/search', [MasterFileController::class, 'teamLeaderSearchForm'])->name('masterfile.team-leaders.search');
    Route::get('masterfile/team-leaders/field-lookup', [MasterFileController::class, 'teamLeaderFieldLookup'])->name('masterfile.team-leaders.field-lookup');
    Route::get('masterfile/team-leaders/{id}/edit', [MasterFileController::class, 'teamLeaderShow'])->name('masterfile.team-leaders.edit');
    Route::put('masterfile/team-leaders/{id}', [MasterFileController::class, 'teamLeaderUpdate'])->name('masterfile.team-leaders.update');
    Route::get('masterfile/team-leaders/{id}/status', [MasterFileController::class, 'teamLeaderStatusForm'])->name('masterfile.team-leaders.status');
    Route::post('masterfile/team-leaders/{id}/status', [MasterFileController::class, 'teamLeaderStatusUpdate'])->name('masterfile.team-leaders.status.update');
    Route::get('masterfile/audit-logs', [MasterFileController::class, 'auditLogs'])->name('masterfile.audit-logs');

    // NEW — Group Maintenance (previously had controller methods but
    // zero routes — completely unreachable until now).
    Route::get('masterfile/groups', [MasterFileController::class, 'groups'])->name('masterfile.groups');
    Route::post('masterfile/groups', [MasterFileController::class, 'storeGroup'])->name('masterfile.groups.store');
    Route::put('masterfile/groups/{id}', [MasterFileController::class, 'updateGroup'])->name('masterfile.groups.update');

    // NEW — Group Name Maintenance. Completely separate feature from the
    // Group Maintenance above — a simple Admin-assigned identity label
    // (e.g. "prihatin2u"), unrelated to the promotion/hierarchy system.
    // NEW 17 Aug 2026 per Chris: Group Set Up drill-down (3-choice
    // landing -> DSG/ORG/CBE own program lists).
    Route::get('group-setup', [\App\Http\Controllers\Admin\GroupSetupController::class, 'index'])->name('group-setup.index');
    Route::get('group-setup/dsg', [\App\Http\Controllers\Admin\GroupSetupController::class, 'dsg'])->name('group-setup.dsg');
    Route::get('group-setup/org', [\App\Http\Controllers\Admin\GroupSetupController::class, 'org'])->name('group-setup.org');
    Route::get('group-setup/cbe', [\App\Http\Controllers\Admin\GroupSetupController::class, 'cbe'])->name('group-setup.cbe');

    Route::get('masterfile/group-names', [GroupLabelController::class, 'index'])->name('masterfile.group-names');
    Route::get('masterfile/group-names/search', [GroupLabelController::class, 'searchForm'])->name('masterfile.group-names.search');
    Route::get('masterfile/group-names/typeahead', [GroupLabelController::class, 'typeahead'])->name('masterfile.group-names.typeahead');
    Route::get('masterfile/group-names/create', [GroupLabelController::class, 'create'])->name('masterfile.group-names.create');
    Route::get('masterfile/group-names/{id}/edit', [GroupLabelController::class, 'edit'])->name('masterfile.group-names.edit');
    Route::post('masterfile/group-names', [GroupLabelController::class, 'store'])->name('masterfile.group-names.store');
    Route::put('masterfile/group-names/{id}', [GroupLabelController::class, 'update'])->name('masterfile.group-names.update');
    // NEW 15 Sep 2026 — per Chris: "setting up group name does not have
    // the full contacts details... address, contact 1 2, add contact
    // etc." and "committee team... master file to set up the position
    // according to the cbe group... not hardcoded contact hp, email
    // (information from agent/member files)." The group's own head-
    // office contact (separate save, own tab on the edit screen) and
    // its Committee/Management Team (positions filled by picking an
    // existing Agent — phone/email always read live from that agent's
    // own record, never retyped here).
    Route::put('masterfile/group-names/{id}/contact', [GroupLabelController::class, 'updateContact'])->name('masterfile.group-names.update-contact');
    Route::get('masterfile/group-names/{id}/committee-agent-typeahead', [GroupLabelController::class, 'committeeAgentTypeahead'])->name('masterfile.group-names.committee-agent-typeahead');
    Route::post('masterfile/group-names/{id}/committee-members', [GroupLabelController::class, 'addCommitteeMember'])->name('masterfile.group-names.committee-members.add');
    Route::delete('masterfile/group-names/{id}/committee-members/{memberId}', [GroupLabelController::class, 'removeCommitteeMember'])->name('masterfile.group-names.committee-members.remove');
    // NEW 27 Sep 2026 — Continue New Term (one row) / New Term (whole committee).
    Route::post('masterfile/group-names/{id}/committee-members/{memberId}/continue', [GroupLabelController::class, 'continueCommitteeMember'])->name('masterfile.group-names.committee-members.continue');
    Route::post('masterfile/group-names/{id}/committee-new-term', [GroupLabelController::class, 'newCommitteeTerm'])->name('masterfile.group-names.committee-new-term');
    // NEW 27 Sep 2026 — Undo an End Term clicked by mistake.
    Route::post('masterfile/group-names/{id}/committee-members/{memberId}/undo-end', [GroupLabelController::class, 'undoEndCommitteeMember'])->name('masterfile.group-names.committee-members.undo-end');

    // NEW 15 Sep 2026 — per Chris: appointment booking module. Practitioner
    // TYPES (Sensei/Consultant/Legal Advisor, never hardcoded) — same
    // landing > search (typeahead) > single-record edit pattern as every
    // other master file.
    Route::get('practitioner-types', [\App\Http\Controllers\Admin\AdminPractitionerTypeController::class, 'index'])->name('practitioner-types.index');
    Route::get('practitioner-types/search', [\App\Http\Controllers\Admin\AdminPractitionerTypeController::class, 'searchForm'])->name('practitioner-types.search');
    Route::get('practitioner-types/typeahead', [\App\Http\Controllers\Admin\AdminPractitionerTypeController::class, 'typeahead'])->name('practitioner-types.typeahead');
    Route::get('practitioner-types/create', [\App\Http\Controllers\Admin\AdminPractitionerTypeController::class, 'create'])->name('practitioner-types.create');
    Route::get('practitioner-types/{id}/edit', [\App\Http\Controllers\Admin\AdminPractitionerTypeController::class, 'edit'])->name('practitioner-types.edit');
    Route::post('practitioner-types', [\App\Http\Controllers\Admin\AdminPractitionerTypeController::class, 'store'])->name('practitioner-types.store');
    Route::put('practitioner-types/{id}', [\App\Http\Controllers\Admin\AdminPractitionerTypeController::class, 'update'])->name('practitioner-types.update');

    // NEW 17 Sep 2026 — per Chris: "all is automatic as by default from
    // the system unless the admin want to turn off or edit or add."
    // Admin-editable catalogs behind the CBE Notice Board's content-
    // aware + seasonal styling.
    Route::get('cbe-notice-styles', [\App\Http\Controllers\Admin\AdminCbeNoticeStyleController::class, 'index'])->name('cbe-notice-styles.index');
    Route::get('cbe-notice-styles/create', [\App\Http\Controllers\Admin\AdminCbeNoticeStyleController::class, 'create'])->name('cbe-notice-styles.create');
    Route::get('cbe-notice-styles/{id}/edit', [\App\Http\Controllers\Admin\AdminCbeNoticeStyleController::class, 'edit'])->name('cbe-notice-styles.edit');
    Route::post('cbe-notice-styles', [\App\Http\Controllers\Admin\AdminCbeNoticeStyleController::class, 'store'])->name('cbe-notice-styles.store');
    Route::put('cbe-notice-styles/{id}', [\App\Http\Controllers\Admin\AdminCbeNoticeStyleController::class, 'update'])->name('cbe-notice-styles.update');

    Route::get('cbe-season-themes', [\App\Http\Controllers\Admin\AdminCbeSeasonThemeController::class, 'index'])->name('cbe-season-themes.index');
    Route::get('cbe-season-themes/create', [\App\Http\Controllers\Admin\AdminCbeSeasonThemeController::class, 'create'])->name('cbe-season-themes.create');
    Route::get('cbe-season-themes/{id}/edit', [\App\Http\Controllers\Admin\AdminCbeSeasonThemeController::class, 'edit'])->name('cbe-season-themes.edit');
    Route::post('cbe-season-themes', [\App\Http\Controllers\Admin\AdminCbeSeasonThemeController::class, 'store'])->name('cbe-season-themes.store');
    Route::put('cbe-season-themes/{id}', [\App\Http\Controllers\Admin\AdminCbeSeasonThemeController::class, 'update'])->name('cbe-season-themes.update');

    Route::get('cbe-ticket-categories', [\App\Http\Controllers\Admin\AdminCbeTicketCategoryController::class, 'index'])->name('cbe-ticket-categories.index');
    Route::get('cbe-ticket-categories/create', [\App\Http\Controllers\Admin\AdminCbeTicketCategoryController::class, 'create'])->name('cbe-ticket-categories.create');
    Route::get('cbe-ticket-categories/{id}/edit', [\App\Http\Controllers\Admin\AdminCbeTicketCategoryController::class, 'edit'])->name('cbe-ticket-categories.edit');
    Route::post('cbe-ticket-categories', [\App\Http\Controllers\Admin\AdminCbeTicketCategoryController::class, 'store'])->name('cbe-ticket-categories.store');
    Route::put('cbe-ticket-categories/{id}', [\App\Http\Controllers\Admin\AdminCbeTicketCategoryController::class, 'update'])->name('cbe-ticket-categories.update');

    // NEW 17 Sep 2026 — per Chris ("yes build all this for me" —
    // Document Repository was one of the 7 approved secretarial gaps):
    // document categories, same admin-editable catalog pattern.
    Route::get('cbe-document-categories', [\App\Http\Controllers\Admin\AdminCbeDocumentCategoryController::class, 'index'])->name('cbe-document-categories.index');
    Route::get('cbe-document-categories/create', [\App\Http\Controllers\Admin\AdminCbeDocumentCategoryController::class, 'create'])->name('cbe-document-categories.create');
    Route::get('cbe-document-categories/{id}/edit', [\App\Http\Controllers\Admin\AdminCbeDocumentCategoryController::class, 'edit'])->name('cbe-document-categories.edit');
    Route::post('cbe-document-categories', [\App\Http\Controllers\Admin\AdminCbeDocumentCategoryController::class, 'store'])->name('cbe-document-categories.store');
    Route::put('cbe-document-categories/{id}', [\App\Http\Controllers\Admin\AdminCbeDocumentCategoryController::class, 'update'])->name('cbe-document-categories.update');

    // NEW 15 Sep 2026 — WHO is a practitioner at a specific temple/
    // branch/entity — always an existing Agent/Member picked by search,
    // never a separate registration — plus their booking settings,
    // weekly hours, and leave/blocked dates. Same node-scoped
    // group->node picker as Member/Consultant/Donor Maintenance when no
    // ?node= is given yet.
    Route::get('practitioners', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'index'])->name('practitioners.index');
    Route::get('practitioners/agent-typeahead', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'agentTypeahead'])->name('practitioners.agent-typeahead');
    Route::post('practitioners', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'store'])->name('practitioners.store');
    Route::get('practitioners/{id}/edit', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'edit'])->name('practitioners.edit');
    Route::put('practitioners/{id}', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'updateSettings'])->name('practitioners.update');
    Route::post('practitioners/{id}/hours', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'updateWeeklyHours'])->name('practitioners.hours.update');
    Route::post('practitioners/{id}/leave', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'addLeaveDate'])->name('practitioners.leave.add');
    Route::delete('practitioners/{id}/leave/{leaveId}', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'removeLeaveDate'])->name('practitioners.leave.remove');
    Route::delete('practitioners/{id}', [\App\Http\Controllers\Admin\AdminPractitionerController::class, 'destroy'])->name('practitioners.destroy');

    // NEW 12 Sep 2026 (Task #416) — Program Library: catalog of every
    // program/feature across the app, each flaggable Paid or Free (crown
    // icon), replacing the old whole-community FREE/PAID subscription_
    // tier idea. Plus a per-community manual unlock screen (no payment
    // gateway yet — Chris confirmed manual Admin unlock for now).
    Route::get('masterfile/program-library', [\App\Http\Controllers\Admin\ProgramLibraryController::class, 'index'])->name('masterfile.program-library');
    Route::post('masterfile/program-library/{id}/toggle', [\App\Http\Controllers\Admin\ProgramLibraryController::class, 'toggle'])->name('masterfile.program-library.toggle');
    Route::post('masterfile/program-library/bulk-update', [\App\Http\Controllers\Admin\ProgramLibraryController::class, 'bulkUpdate'])->name('masterfile.program-library.bulk-update');
    Route::get('masterfile/program-unlocks', [\App\Http\Controllers\Admin\ProgramLibraryController::class, 'unlocks'])->name('masterfile.program-unlocks');
    Route::post('masterfile/program-unlocks/{group}/{program}/unlock', [\App\Http\Controllers\Admin\ProgramLibraryController::class, 'unlock'])->name('masterfile.program-unlocks.unlock');
    Route::post('masterfile/program-unlocks/{group}/{program}/lock', [\App\Http\Controllers\Admin\ProgramLibraryController::class, 'lock'])->name('masterfile.program-unlocks.lock');

    // NEW 13 Sep 2026 (Task #418) — CBE Vendor Registration, Phase 2 of
    // the CBE Marketplace build. Registration itself is platform-Admin-
    // only for now (vendor self-registration is a separate, bigger
    // feature Chris hasn't asked for yet). Approval of a vendor's
    // requested entity is a SEPARATE route group below (cbe.officer.or.
    // admin), since an entity's own officer — not just platform Admin —
    // must be able to approve a vendor for their own temple/club.
    Route::get('masterfile/cbe-vendors', [\App\Http\Controllers\Admin\CbeVendorController::class, 'index'])->name('masterfile.cbe-vendors');
    Route::post('masterfile/cbe-vendors', [\App\Http\Controllers\Admin\CbeVendorController::class, 'store'])->name('masterfile.cbe-vendors.store');
    Route::get('masterfile/cbe-vendors/agent-typeahead', [\App\Http\Controllers\Admin\CbeVendorController::class, 'agentTypeahead'])->name('masterfile.cbe-vendors.agent-typeahead');
    Route::get('masterfile/cbe-vendors/phone-check', [\App\Http\Controllers\Admin\CbeVendorController::class, 'phoneCheck'])->name('masterfile.cbe-vendors.phone-check');
    Route::get('masterfile/cbe-vendors/{vendor}', [\App\Http\Controllers\Admin\CbeVendorController::class, 'show'])->name('masterfile.cbe-vendors.show');
    Route::get('masterfile/cbe-vendors/{vendor}/request-node', [\App\Http\Controllers\Admin\CbeVendorController::class, 'requestNode'])->name('masterfile.cbe-vendors.request-node');

    // NEW 27 Aug 2026 (Task #233) — GLADE Public Model tier catalog +
    // community tier approval flow.
    // NEW 11 Sep 2026 — 'store' lets an Admin add a brand-new catalog
    // tier (previously only the 6 seeded ones could be edited, never
    // added to). approve/reject now act on a group_label_glade_tiers
    // row id ({link}), not a group_label_id — a community can have
    // several tiers, pending/active independently of each other.
    Route::get('glade-tiers', [AdminGladeTierController::class, 'index'])->name('glade-tiers.index');
    Route::post('glade-tiers', [AdminGladeTierController::class, 'store'])->name('glade-tiers.store');
    Route::post('glade-tiers/save-all', [AdminGladeTierController::class, 'updateAllTiers'])->name('glade-tiers.update-all');

    // NEW 11 Sep 2026 — per Chris: "Faith Practice Type" was hardcoded to
    // 5 fixed choices — "why is only offer this few type? what if new
    // type introduce? you should not hard code." Admin-manageable
    // wording-set catalog, same pattern as GLADE tiers above.
    //
    // REBUILT 14 Sep 2026 — per Chris: "confusing... can you follow the
    // other programs like product or vendor master file... example
    // debtor, creditor, chart account transaction type." Same landing >
    // search (typeahead) > single-record edit pattern as Reason Code /
    // Group Name & Hierarchy Levels — one record, one edit screen, one
    // Save, instead of one screen listing every row with its own button.
    Route::get('faith-practice-types', [\App\Http\Controllers\Admin\AdminFaithPracticeTypeController::class, 'index'])->name('faith-practice-types.index');
    Route::get('faith-practice-types/search', [\App\Http\Controllers\Admin\AdminFaithPracticeTypeController::class, 'searchForm'])->name('faith-practice-types.search');
    Route::get('faith-practice-types/typeahead', [\App\Http\Controllers\Admin\AdminFaithPracticeTypeController::class, 'typeahead'])->name('faith-practice-types.typeahead');
    Route::get('faith-practice-types/create', [\App\Http\Controllers\Admin\AdminFaithPracticeTypeController::class, 'create'])->name('faith-practice-types.create');
    Route::get('faith-practice-types/{id}/edit', [\App\Http\Controllers\Admin\AdminFaithPracticeTypeController::class, 'edit'])->name('faith-practice-types.edit');
    Route::post('faith-practice-types', [\App\Http\Controllers\Admin\AdminFaithPracticeTypeController::class, 'store'])->name('faith-practice-types.store');
    Route::put('faith-practice-types/{id}', [\App\Http\Controllers\Admin\AdminFaithPracticeTypeController::class, 'update'])->name('faith-practice-types.update');

    // NEW 15 Sep 2026 — per Chris: "temple/NGO committee team or SME CBE
    // group management team... you should have a master file to set up
    // the position according to the cbe group." Same landing > search
    // (typeahead) > single-record edit pattern as Appointment Position
    // Types / Reason Code / Group Name & Hierarchy Levels.
    // NEW 28 Sep 2026 — Member Maintenance for ALL CBEs (one person, many affiliations).
    // NEW 28 Sep 2026 — Member Pick Lists + Membership Plans master files.
    Route::get('member-pick-lists', [\App\Http\Controllers\Admin\AdminMemberMastersController::class, 'lists'])->name('member-pick-lists.index');
    Route::post('member-pick-lists', [\App\Http\Controllers\Admin\AdminMemberMastersController::class, 'saveOption'])->name('member-pick-lists.save');
    Route::get('membership-plans', [\App\Http\Controllers\Admin\AdminMemberMastersController::class, 'plans'])->name('membership-plans.index');
    Route::post('membership-plans', [\App\Http\Controllers\Admin\AdminMemberMastersController::class, 'savePlan'])->name('membership-plans.save');
    Route::get('committee-position-types', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'index'])->name('committee-position-types.index');
    Route::get('committee-position-types/search', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'searchForm'])->name('committee-position-types.search');
    Route::get('committee-position-types/typeahead', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'typeahead'])->name('committee-position-types.typeahead');
    Route::get('committee-position-types/create', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'create'])->name('committee-position-types.create');
    Route::get('committee-position-types/{id}/edit', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'edit'])->name('committee-position-types.edit');
    Route::post('committee-position-types', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'store'])->name('committee-position-types.store');
    Route::put('committee-position-types/{id}', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'update'])->name('committee-position-types.update');
    Route::patch('committee-position-types/{id}/move-up', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'moveUp'])->name('committee-position-types.move-up');
    Route::patch('committee-position-types/{id}/move-down', [\App\Http\Controllers\Admin\AdminCommitteePositionTypeController::class, 'moveDown'])->name('committee-position-types.move-down');

    // NEW — 4-eye Approval workflow (generic, reusable across all
    // confirmed sensitive actions, starting with Undo Promotion/Demotion).
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/pending-count', [ApprovalController::class, 'pendingCount'])->name('approvals.pending-count');
    Route::post('approvals/availability', [ApprovalController::class, 'updateAvailability'])->name('approvals.availability');
    Route::post('approvals/settings', [ApprovalController::class, 'updateSettings'])->name('approvals.settings');

    // NEW — Finance withdrawal processing (masked account, decrypt-on-
    // demand, Mark as Paid). Finance/Director-only, enforced in code.
    Route::get('finance/withdrawal/{requestId}', [FinanceWithdrawalController::class, 'show'])->name('finance.withdrawal.show');
    Route::get('finance/withdrawal/{requestId}/decrypt', [FinanceWithdrawalController::class, 'decryptAccount'])->name('finance.withdrawal.decrypt');
    Route::post('finance/withdrawal/{requestId}/mark-paid', [FinanceWithdrawalController::class, 'markAsPaid'])->name('finance.withdrawal.mark-paid');

    // NEW 19 Sep 2026 -- AI Master Data Assistant hub, per Chris's
    // uploaded spec (AI_Master_Data_and_Transaction_Assistant_
    // Specification.docx): top item in Master File Maintenance. Phase 1
    // = Chart of Accounts ("COA Chat", see CbeAccountingController);
    // other master data types (Supplier, Customer, Bank, Fixed Asset,
    // Cost Centre, Tax) show as "Coming Soon" until built one at a time.
    Route::get('masterfile/ai-assistant', [\App\Http\Controllers\Admin\AiMasterDataAssistantController::class, 'index'])->name('masterfile.ai-assistant');

    // NEW — Reason Code Maintenance, following the same pattern as
    // Group Name Maintenance.
    Route::get('masterfile/reason-codes', [ReasonCodeController::class, 'index'])->name('masterfile.reason-codes');
    Route::get('masterfile/reason-codes/search', [ReasonCodeController::class, 'searchForm'])->name('masterfile.reason-codes.search');
    Route::get('masterfile/reason-codes/create', [ReasonCodeController::class, 'create'])->name('masterfile.reason-codes.create');
    Route::get('masterfile/reason-codes/typeahead', [ReasonCodeController::class, 'typeahead'])->name('masterfile.reason-codes.typeahead');
    Route::get('masterfile/reason-codes/{id}/edit', [ReasonCodeController::class, 'edit'])->name('masterfile.reason-codes.edit');
    Route::post('masterfile/reason-codes', [ReasonCodeController::class, 'store'])->name('masterfile.reason-codes.store');
    Route::put('masterfile/reason-codes/{id}', [ReasonCodeController::class, 'update'])->name('masterfile.reason-codes.update');

    // NEW 19 Jul 2026 — Customer Status + Customer Type Maintenance, per
    // Chris: both fully Admin-configurable lookup tables instead of
    // hardcoded lists.
    Route::get('masterfile/customer-statuses', [\App\Http\Controllers\Admin\CustomerStatusController::class, 'index'])->name('masterfile.customer-statuses');
    Route::get('masterfile/customer-statuses/create', [\App\Http\Controllers\Admin\CustomerStatusController::class, 'create'])->name('masterfile.customer-statuses.create');
    Route::get('masterfile/customer-statuses/{id}/edit', [\App\Http\Controllers\Admin\CustomerStatusController::class, 'edit'])->name('masterfile.customer-statuses.edit');
    Route::post('masterfile/customer-statuses', [\App\Http\Controllers\Admin\CustomerStatusController::class, 'store'])->name('masterfile.customer-statuses.store');
    Route::put('masterfile/customer-statuses/{id}', [\App\Http\Controllers\Admin\CustomerStatusController::class, 'update'])->name('masterfile.customer-statuses.update');
    Route::delete('masterfile/customer-statuses/{id}', [\App\Http\Controllers\Admin\CustomerStatusController::class, 'destroy'])->name('masterfile.customer-statuses.destroy');

    Route::get('masterfile/customer-types', [\App\Http\Controllers\Admin\CustomerTypeController::class, 'index'])->name('masterfile.customer-types');
    Route::get('masterfile/customer-types/create', [\App\Http\Controllers\Admin\CustomerTypeController::class, 'create'])->name('masterfile.customer-types.create');
    Route::get('masterfile/customer-types/{id}/edit', [\App\Http\Controllers\Admin\CustomerTypeController::class, 'edit'])->name('masterfile.customer-types.edit');
    Route::post('masterfile/customer-types', [\App\Http\Controllers\Admin\CustomerTypeController::class, 'store'])->name('masterfile.customer-types.store');
    Route::put('masterfile/customer-types/{id}', [\App\Http\Controllers\Admin\CustomerTypeController::class, 'update'])->name('masterfile.customer-types.update');
    Route::delete('masterfile/customer-types/{id}', [\App\Http\Controllers\Admin\CustomerTypeController::class, 'destroy'])->name('masterfile.customer-types.destroy');

    // NEW 19 Jul 2026 — Customer Category / Occupation Group / Customer Source:
    // Chris: fully Admin-configurable lookup tables, same code/description pattern.
    Route::get('masterfile/customer-categories', [\App\Http\Controllers\Admin\CustomerCategoryController::class, 'index'])->name('masterfile.customer-categories');
    Route::get('masterfile/customer-categories/create', [\App\Http\Controllers\Admin\CustomerCategoryController::class, 'create'])->name('masterfile.customer-categories.create');
    Route::get('masterfile/customer-categories/{id}/edit', [\App\Http\Controllers\Admin\CustomerCategoryController::class, 'edit'])->name('masterfile.customer-categories.edit');
    Route::post('masterfile/customer-categories', [\App\Http\Controllers\Admin\CustomerCategoryController::class, 'store'])->name('masterfile.customer-categories.store');
    Route::put('masterfile/customer-categories/{id}', [\App\Http\Controllers\Admin\CustomerCategoryController::class, 'update'])->name('masterfile.customer-categories.update');
    Route::delete('masterfile/customer-categories/{id}', [\App\Http\Controllers\Admin\CustomerCategoryController::class, 'destroy'])->name('masterfile.customer-categories.destroy');

    Route::get('masterfile/occupation-groups', [\App\Http\Controllers\Admin\OccupationGroupController::class, 'index'])->name('masterfile.occupation-groups');
    Route::get('masterfile/occupation-groups/create', [\App\Http\Controllers\Admin\OccupationGroupController::class, 'create'])->name('masterfile.occupation-groups.create');
    Route::get('masterfile/occupation-groups/{id}/edit', [\App\Http\Controllers\Admin\OccupationGroupController::class, 'edit'])->name('masterfile.occupation-groups.edit');
    Route::post('masterfile/occupation-groups', [\App\Http\Controllers\Admin\OccupationGroupController::class, 'store'])->name('masterfile.occupation-groups.store');
    Route::put('masterfile/occupation-groups/{id}', [\App\Http\Controllers\Admin\OccupationGroupController::class, 'update'])->name('masterfile.occupation-groups.update');
    Route::delete('masterfile/occupation-groups/{id}', [\App\Http\Controllers\Admin\OccupationGroupController::class, 'destroy'])->name('masterfile.occupation-groups.destroy');

    Route::get('masterfile/customer-sources', [\App\Http\Controllers\Admin\CustomerSourceController::class, 'index'])->name('masterfile.customer-sources');
    Route::get('masterfile/customer-sources/create', [\App\Http\Controllers\Admin\CustomerSourceController::class, 'create'])->name('masterfile.customer-sources.create');
    Route::get('masterfile/customer-sources/{id}/edit', [\App\Http\Controllers\Admin\CustomerSourceController::class, 'edit'])->name('masterfile.customer-sources.edit');
    Route::post('masterfile/customer-sources', [\App\Http\Controllers\Admin\CustomerSourceController::class, 'store'])->name('masterfile.customer-sources.store');
    Route::put('masterfile/customer-sources/{id}', [\App\Http\Controllers\Admin\CustomerSourceController::class, 'update'])->name('masterfile.customer-sources.update');
    Route::delete('masterfile/customer-sources/{id}', [\App\Http\Controllers\Admin\CustomerSourceController::class, 'destroy'])->name('masterfile.customer-sources.destroy');

    // NEW 19 Jul 2026 — Customer Housekeeping (purge). Admin-only, manual
    // trigger, per Chris: nobody can delete a customer directly, agents
    // can only set Inactive — this screen is where Admin permanently
    // cleans those up in their own time, in batches they choose.
    Route::get('housekeeping/customers', [\App\Http\Controllers\Admin\CustomerHousekeepingController::class, 'index'])->name('housekeeping.customers');
    Route::post('housekeeping/customers/purge', [\App\Http\Controllers\Admin\CustomerHousekeepingController::class, 'purge'])->name('housekeeping.customers.purge');

    // NEW 20 Jul 2026 — Notification Setup: real settings screen for
    // every reminder type that has tunable timing (renewal, quotation
    // escalation, unclaimed commission), replacing the dead "#"
    // sidebar placeholder link.
    Route::get('notification-setup', [\App\Http\Controllers\Admin\NotificationSetupController::class, 'index'])->name('notification-setup.index');
    Route::post('notification-setup', [\App\Http\Controllers\Admin\NotificationSetupController::class, 'update'])->name('notification-setup.update');

    // NEW — Calendar & Reminders. Admin-only management (create/edit/
    // delete) — same centralized pattern as Vendors/Products. The
    // read-only viewing route lives in the SHARED group below instead,
    // since GL/TL/Introducer also need to see the calendar.
    Route::get('calendar/create', [CalendarController::class, 'create'])->name('calendar.create');
    Route::get('calendar/{id}/edit', [CalendarController::class, 'edit'])->name('calendar.edit');
    Route::post('calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('calendar/{id}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('calendar/{id}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

    // NEW — Admin creates the one GL for an Organization Rewards Group
    // (the group itself must already exist via Group Name Maintenance
    // with the flag OFF).
    // NEW — landing page, search, and view for Special Privilege
    // Groups, matching the established pattern everywhere else.
    Route::get('special-group', [SpecialGroupController::class, 'index'])->name('special-group.index');
    Route::get('special-group/search', [SpecialGroupController::class, 'searchForm'])->name('special-group.search');
    Route::get('special-group/typeahead', [SpecialGroupController::class, 'typeahead'])->name('special-group.typeahead');
    Route::get('special-group/{groupLabelId}/view', [SpecialGroupController::class, 'view'])->name('special-group.view');
    Route::get('special-group/create-gl', [SpecialGroupController::class, 'createGLForm'])->name('special-group.create-gl');
    Route::post('special-group/create-gl', [SpecialGroupController::class, 'createGL'])->name('special-group.create-gl.store');
    Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('masterfile/introducers/{id}/request-undo-role', [ApprovalController::class, 'requestUndoRole'])->name('masterfile.introducers.request-undo-role');
    Route::post('masterfile/team-leaders/{id}/request-undo-role', [ApprovalController::class, 'requestUndoRole'])->name('masterfile.team-leaders.request-undo-role');
    Route::post('masterfile/group-leaders/{id}/request-undo-role', [ApprovalController::class, 'requestUndoRole'])->name('masterfile.group-leaders.request-undo-role');
    Route::get('masterfile/group-leaders', [MasterFileController::class, 'groupLeaders'])->name('masterfile.group-leaders');
    Route::get('masterfile/group-leaders/search', [MasterFileController::class, 'groupLeaderSearchForm'])->name('masterfile.group-leaders.search');
    Route::get('masterfile/group-leaders/field-lookup', [MasterFileController::class, 'groupLeaderFieldLookup'])->name('masterfile.group-leaders.field-lookup');
    Route::get('masterfile/group-leaders/{id}/edit', [MasterFileController::class, 'groupLeaderShow'])->name('masterfile.group-leaders.edit');
    Route::put('masterfile/group-leaders/{id}', [MasterFileController::class, 'groupLeaderUpdate'])->name('masterfile.group-leaders.update');

    // NEW — "Add New Introducer" from Introducer Maintenance. Reuses the
    // EXACT SAME showRegister()/register() controller methods as the public
    // /register page (spec Decision 2 — no separate/simpler direct-add
    // form). This route just makes that same form reachable while already
    // logged in as Admin, since /register itself is guest-only.
    Route::get('masterfile/introducers/create', [AuthController::class, 'showRegister'])->name('masterfile.introducers.create');
    Route::post('masterfile/introducers/create', [AuthController::class, 'register'])->name('masterfile.introducers.create.store');

    // NEW — Search To Edit: blank form (styled like register). Reuses the
    // EXISTING admin.network.ajax.typeahead endpoint for live autocomplete
    // (same one Network Drill Down already uses) — no duplicate logic.
    // Submitting the form feeds the SAME results table already built at
    // admin.masterfile.introducers via query string.
    Route::get('masterfile/introducers/search', [MasterFileController::class, 'introducerSearchForm'])->name('masterfile.introducers.search');
    Route::get('masterfile/introducers/field-lookup', [MasterFileController::class, 'introducerFieldLookup'])->name('masterfile.introducers.field-lookup');

    // NEW — View/Edit an existing Introducer's full record.
    Route::get('masterfile/introducers/{id}/edit', [MasterFileController::class, 'introducerShow'])->name('masterfile.introducers.edit');
    Route::put('masterfile/introducers/{id}', [MasterFileController::class, 'introducerUpdate'])->name('masterfile.introducers.update');

    // NEW 15 Jul 2026 — Resend Verification. Admin scope = any agent.
    // Shared across Introducer/Team Leader/Group Leader edit screens
    // since it only needs the agent's ID, not their tier.
    Route::post('masterfile/agents/{id}/resend-verification', [MasterFileController::class, 'resendVerification'])->name('masterfile.resend-verification');
    Route::get('masterfile/pending-verifications', [MasterFileController::class, 'pendingVerifications'])->name('masterfile.pending-verifications');

    // NEW — Status Update (Active/Inactive/Terminated), with reason code +
    // optional notes, and the downline-block rule for Terminate.
    Route::get('masterfile/introducers/{id}/status', [MasterFileController::class, 'introducerStatusForm'])->name('masterfile.introducers.status');
    Route::post('masterfile/introducers/{id}/status', [MasterFileController::class, 'introducerStatusUpdate'])->name('masterfile.introducers.status.update');

    // NEW — Reason Code Maintenance (Admin can edit/add reason codes used
    // by Status Update / Delete, rather than a fixed pre-set list).
    Route::get('masterfile/reason-codes', [MasterFileController::class, 'reasonCodes'])->name('masterfile.reason-codes');
    Route::post('masterfile/reason-codes', [MasterFileController::class, 'storeReasonCode'])->name('masterfile.reason-codes.store');
    Route::put('masterfile/reason-codes/{id}', [MasterFileController::class, 'updateReasonCode'])->name('masterfile.reason-codes.update');
    Route::patch('masterfile/reason-codes/{id}/toggle', [MasterFileController::class, 'toggleReasonCode'])->name('masterfile.reason-codes.toggle');

    // NEW 23 Jul 2026 — Organization Category Maintenance: lets Admin
    // rename the 3 fixed system role labels (Group Leader/Team Leader/
    // Introducer) for this deployment. Edit-only, exactly 3 rows —
    // see OrgCategoryController and RoleLabelService.
    Route::get('masterfile/org-category', [OrgCategoryController::class, 'index'])->name('masterfile.org-category');
    Route::put('masterfile/org-category', [OrgCategoryController::class, 'update'])->name('masterfile.org-category.update');
    Route::post('masterfile/org-category/reset', [OrgCategoryController::class, 'reset'])->name('masterfile.org-category.reset');

    // NEW 5 Aug 2026 — Outbound Partner API key management. Reverse
    // direction from the Integration Hub: GeneralLink issuing ITS OWN
    // keys to outside systems (insurance vendor, hotel PMS, customer
    // ERP/POS), not agents connecting out with their own keys. ADMIN
    // only — see PartnerApiKeyController and app/Http/Middleware/PartnerApiAuth.php.
    Route::get('partner-api', [\App\Http\Controllers\Admin\PartnerApiKeyController::class, 'index'])->name('partner-api.index');
    Route::get('partner-api/create', [\App\Http\Controllers\Admin\PartnerApiKeyController::class, 'create'])->name('partner-api.create');
    Route::post('partner-api', [\App\Http\Controllers\Admin\PartnerApiKeyController::class, 'store'])->name('partner-api.store');
    Route::post('partner-api/{keyId}/revoke', [\App\Http\Controllers\Admin\PartnerApiKeyController::class, 'revoke'])->name('partner-api.revoke');

    // NEW 24 Jul 2026 — Rank Definition Maintenance (Configurable Rank
    // System, Phase 1). Define Ranks under each of the 3 fixed system
    // roles — see RoleRankController.
    Route::get('masterfile/role-ranks', [RoleRankController::class, 'index'])->name('masterfile.role-ranks');
    Route::get('masterfile/role-ranks/rows', [RoleRankController::class, 'rows'])->name('masterfile.role-ranks.rows');
    Route::get('masterfile/role-ranks/create', [RoleRankController::class, 'create'])->name('masterfile.role-ranks.create');
    Route::get('masterfile/role-ranks/{id}/edit', [RoleRankController::class, 'edit'])->name('masterfile.role-ranks.edit');
    Route::post('masterfile/role-ranks', [RoleRankController::class, 'store'])->name('masterfile.role-ranks.store');
    Route::put('masterfile/role-ranks/{id}', [RoleRankController::class, 'update'])->name('masterfile.role-ranks.update');
    Route::delete('masterfile/role-ranks/{id}', [RoleRankController::class, 'destroy'])->name('masterfile.role-ranks.destroy');
    Route::patch('masterfile/role-ranks/{id}/move-up', [RoleRankController::class, 'moveUp'])->name('masterfile.role-ranks.move-up');
    Route::patch('masterfile/role-ranks/{id}/move-down', [RoleRankController::class, 'moveDown'])->name('masterfile.role-ranks.move-down');

    // NEW 31 Jul 2026 — Rank Assignment (Configurable Rank System,
    // Phase 2). Assign each real agent to one of the ranks defined
    // above — see RankAssignmentController.
    Route::get('masterfile/rank-assignment', [\App\Http\Controllers\Admin\RankAssignmentController::class, 'index'])->name('masterfile.rank-assignment');
    Route::put('masterfile/rank-assignment', [\App\Http\Controllers\Admin\RankAssignmentController::class, 'update'])->name('masterfile.rank-assignment.update');

    // NEW 31 Jul 2026 — Rank Promotion Rules (Configurable Rank System,
    // Phase 4). Same idea as Promotion & Demotion Rules below, but grants
    // REMOVED 31 Jul 2026 — per Chris: "REMOVE THIS RANK PROMOTION, IS
    // CONFUSING." It evaluated rank per individual agent (their own
    // recruit count / sales volume / tenure / top-N standing), which
    // directly conflicts with the bucket-level (Role + Special
    // Privilege Group) rank model Rank Assignment now uses — one shared
    // rank per bucket, no per-agent distinction. Routes removed, menu
    // link removed, and the automatic trigger calls in
    // CommissionEngine::confirmPolicy() and RecalculateAllCommissions
    // removed. RankPromotionRuleController/RankPromotionService/
    // role_ranks-linked rank_promotion_rules table are left in place as
    // inert, unreferenced code (file deletion isn't available in this
    // environment) — safe to ignore, nothing routes to them anymore.

    // NEW 31 Jul 2026 — Override Recipient Profile (personal profile).
    // Per-NAMED-AGENT override entitlements — % or fixed RM amount,
    // drawn from a chosen source Role/Rank, per company/product, with
    // effective/expiry dates and Active/Inactive status. See
    // AgentOverrideProfileController / CommissionEngine::resolveIndividualOverrides().
    Route::get('masterfile/override-recipient-profile', [\App\Http\Controllers\Admin\AgentOverrideProfileController::class, 'index'])->name('masterfile.override-recipient-profile');
    Route::get('masterfile/override-recipient-profile/{agentId}', [\App\Http\Controllers\Admin\AgentOverrideProfileController::class, 'profile'])->name('masterfile.override-recipient-profile.show');
    Route::post('masterfile/override-recipient-profile', [\App\Http\Controllers\Admin\AgentOverrideProfileController::class, 'store'])->name('masterfile.override-recipient-profile.store');
    Route::patch('masterfile/override-recipient-profile/{id}/toggle', [\App\Http\Controllers\Admin\AgentOverrideProfileController::class, 'toggleStatus'])->name('masterfile.override-recipient-profile.toggle');
    Route::delete('masterfile/override-recipient-profile/{id}', [\App\Http\Controllers\Admin\AgentOverrideProfileController::class, 'destroy'])->name('masterfile.override-recipient-profile.destroy');

    // NEW 31 Jul 2026 — Vendor Override Members. Distinct from Override
    // Recipient Profile above: these are people on the VENDOR's side
    // (Country Director, Regional Directors, etc.), not agents in our
    // hierarchy — see OverrideMemberController.
    Route::get('masterfile/override-members', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'index'])->name('masterfile.override-members');
    Route::get('masterfile/override-members/create', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'create'])->name('masterfile.override-members.create');
    Route::post('masterfile/override-members', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'store'])->name('masterfile.override-members.store');
    Route::get('masterfile/override-members/{id}', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'show'])->name('masterfile.override-members.show');
    Route::put('masterfile/override-members/{id}', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'update'])->name('masterfile.override-members.update');
    Route::post('masterfile/override-members/{id}/rules', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'storeRule'])->name('masterfile.override-members.rules.store');
    Route::patch('masterfile/override-members/{id}/rules/{ruleId}/toggle', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'toggleRule'])->name('masterfile.override-members.rules.toggle');
    Route::delete('masterfile/override-members/{id}/rules/{ruleId}', [\App\Http\Controllers\Admin\OverrideMemberController::class, 'destroyRule'])->name('masterfile.override-members.rules.destroy');

    // NEW 2 Aug 2026 — Override Claim Report/workflow. This is the
    // screen the override:calculate command's own output message
    // pointed at but never actually existed — closes that gap. Submit
    // routes a claim through the same 4-eye ApprovalService already
    // used for Withdrawal Approval; Mark Paid is a separate, simpler
    // step once approved. See OverrideClaimController.
    Route::get('masterfile/override-claims', [\App\Http\Controllers\Admin\OverrideClaimController::class, 'index'])->name('masterfile.override-claims');
    Route::get('masterfile/override-claims/export', [\App\Http\Controllers\Admin\OverrideClaimController::class, 'export'])->name('masterfile.override-claims.export');
    // NEW 2 Aug 2026 — full audit ledger (every claim's complete
    // lifecycle: who submitted/approved/rejected/paid it and when),
    // separate from the operational report above which focuses on
    // what needs action right now.
    Route::get('masterfile/override-ledger', [\App\Http\Controllers\Admin\OverrideClaimController::class, 'ledger'])->name('masterfile.override-ledger');
    // NEW 2 Aug 2026 — clean, no-filter boxed Debit/Credit statement for
    // ONE Override Member "as at" today. Reached via the View Statement
    // button on the ledger list above (?member_id=...), not its own
    // sidebar entry. Registered BEFORE any {id}-style route so "statement"
    // is never swallowed as a wildcard (moot here since it's query-string
    // based, but kept consistent with the rest of this file's convention).
    Route::get('masterfile/override-ledger/statement', [\App\Http\Controllers\Admin\OverrideClaimController::class, 'statement'])->name('masterfile.override-ledger.statement');
    Route::post('masterfile/override-claims/{claimId}/submit', [\App\Http\Controllers\Admin\OverrideClaimController::class, 'submit'])->name('masterfile.override-claims.submit');
    Route::post('masterfile/override-claims/{claimId}/mark-paid', [\App\Http\Controllers\Admin\OverrideClaimController::class, 'markPaid'])->name('masterfile.override-claims.mark-paid');

    // NEW 24 Jul 2026 — Promotion & Demotion Rules. Admin-editable,
    // per-group (Organization Rewards Group) criteria list — recruit count,
    // sales volume, tenure, unlimited rows — enforced by
    // HierarchyService::evaluateRankChange(). A group with none of its
    // own rules inherits the System Default set automatically.
    Route::get('masterfile/promotion-rules', [PromotionRuleController::class, 'index'])->name('masterfile.promotion-rules');
    Route::post('masterfile/promotion-rules', [PromotionRuleController::class, 'store'])->name('masterfile.promotion-rules.store');
    Route::delete('masterfile/promotion-rules/{id}', [PromotionRuleController::class, 'destroy'])->name('masterfile.promotion-rules.destroy');
    Route::put('masterfile/promotion-rules/logic', [PromotionRuleController::class, 'updateLogic'])->name('masterfile.promotion-rules.logic');

    // NEW 25 Jul 2026 — Breakaway Bonus rule config (target Y, bonus %,
    // metric, period), per group. See BreakawayBonusRuleController.
    Route::get('masterfile/breakaway-bonus-rules', [BreakawayBonusRuleController::class, 'index'])->name('masterfile.breakaway-bonus-rules');
    Route::put('masterfile/breakaway-bonus-rules', [BreakawayBonusRuleController::class, 'update'])->name('masterfile.breakaway-bonus-rules.update');

    // Master File — Products
    Route::get('masterfile/products', [MasterFileController::class, 'products'])->name('masterfile.products');
    Route::post('masterfile/products', [MasterFileController::class, 'storeProduct'])->name('masterfile.products.store');
    Route::put('masterfile/products/{id}', [MasterFileController::class, 'updateProduct'])->name('masterfile.products.update');
    Route::patch('masterfile/products/{id}/toggle', [MasterFileController::class, 'toggleProduct'])->name('masterfile.product.toggle');

    // Master File — Commission Structures
    Route::get('masterfile/commissions', [MasterFileController::class, 'commissionStructures'])->name('masterfile.commissions');
    Route::post('masterfile/commissions', [MasterFileController::class, 'storeCommissionStructure'])->name('masterfile.commissions.store');
    Route::put('masterfile/commissions/{id}', [MasterFileController::class, 'updateCommissionStructure'])->name('masterfile.commissions.update');
    Route::patch('masterfile/commissions/{id}/toggle', [MasterFileController::class, 'toggleCommissionStructure'])->name('masterfile.commission.toggle');
    Route::get('masterfile/commissions/vendor-typeahead', [MasterFileController::class, 'commissionVendorTypeahead'])->name('masterfile.commissions.vendor-typeahead');
    Route::get('masterfile/commissions/product-typeahead', [MasterFileController::class, 'commissionProductTypeahead'])->name('masterfile.commissions.product-typeahead');

    // Rank Allocation — moved out of the Earning Income Structure form
    // into its own screen, 31 Jul 2026 (see RankAllocationController).
    Route::get('masterfile/commissions/{structureId}/rank-allocation', [\App\Http\Controllers\Admin\RankAllocationController::class, 'edit'])->name('masterfile.commissions.rank-allocation');
    Route::put('masterfile/commissions/{structureId}/rank-allocation', [\App\Http\Controllers\Admin\RankAllocationController::class, 'update'])->name('masterfile.commissions.rank-allocation.update');

    // Cascade Overrides — Rank system Phase 2, 31 Jul 2026. Admin-only,
    // per-agent multi-tier % (see CommissionCascadeOverrideController).
    Route::get('masterfile/commissions/{structureId}/cascade-overrides', [\App\Http\Controllers\Admin\CommissionCascadeOverrideController::class, 'index'])->name('masterfile.commissions.cascade-overrides');
    Route::get('masterfile/commissions/{structureId}/cascade-overrides/agent-typeahead', [\App\Http\Controllers\Admin\CommissionCascadeOverrideController::class, 'agentTypeahead'])->name('masterfile.commissions.cascade-overrides.agent-typeahead');
    Route::get('masterfile/commissions/{structureId}/cascade-overrides/{agentId}/edit', [\App\Http\Controllers\Admin\CommissionCascadeOverrideController::class, 'edit'])->name('masterfile.commissions.cascade-overrides.edit');
    Route::put('masterfile/commissions/{structureId}/cascade-overrides/{agentId}', [\App\Http\Controllers\Admin\CommissionCascadeOverrideController::class, 'update'])->name('masterfile.commissions.cascade-overrides.update');
    Route::delete('masterfile/commissions/{structureId}/cascade-overrides/{agentId}', [\App\Http\Controllers\Admin\CommissionCascadeOverrideController::class, 'destroy'])->name('masterfile.commissions.cascade-overrides.destroy');

    // Master File — Reward Rates
    Route::get('masterfile/reward-rates', [MasterFileController::class, 'rewardRates'])->name('masterfile.reward-rates');
    Route::post('masterfile/reward-rates', [MasterFileController::class, 'storeRewardRate'])->name('masterfile.reward-rates.store');

    // NEW 9 Aug 2026 — Vendor KPI Dashboard, part of the new Overview KPI
    // menu group (Overview KPI / Vendor KPI / Customer KPI). Admin-only —
    // vendors are company-wide, not owned per-agent, so there is no
    // GL/TL/Introducer cascade to scope by (unlike Customer KPI).
    Route::get('vendor-kpi', [\App\Http\Controllers\Admin\VendorKpiController::class, 'index'])->name('vendor-kpi.index');

    // NEW 9 Aug 2026 — Master File — Vendor Rebate Offers. Admin-created
    // only (deliberately not vendor-submitted — that flow was the old
    // Offer Request feature Chris scrapped 8 Aug 2026). Reuses the same
    // vendor/product typeahead endpoints already registered for
    // Commission Structures above.
    Route::get('masterfile/rebate-offers', [\App\Http\Controllers\Admin\VendorRebateOfferController::class, 'index'])->name('masterfile.rebate-offers');
    Route::post('masterfile/rebate-offers', [\App\Http\Controllers\Admin\VendorRebateOfferController::class, 'store'])->name('masterfile.rebate-offers.store');
    Route::patch('masterfile/rebate-offers/{id}/toggle', [\App\Http\Controllers\Admin\VendorRebateOfferController::class, 'toggle'])->name('masterfile.rebate-offers.toggle');
    // NEW 12 Aug 2026 — Admin review queue for vendor-submitted rebate program applications.
    Route::get('masterfile/rebate-applications', [\App\Http\Controllers\Admin\VendorRebateApplicationController::class, 'index'])->name('masterfile.rebate-applications');
    Route::post('masterfile/rebate-applications/{applicationId}/approve', [\App\Http\Controllers\Admin\VendorRebateApplicationController::class, 'approve'])->name('masterfile.rebate-applications.approve');
    Route::post('masterfile/rebate-applications/{applicationId}/reject', [\App\Http\Controllers\Admin\VendorRebateApplicationController::class, 'reject'])->name('masterfile.rebate-applications.reject');

    // NEW 9 Aug 2026 — Video Library. Admin uploads/maintains video
    // metadata here; the actual files live on disk in a configurable
    // folder (never in the DB). First use: "Watch Intro Video" button on
    // the public Vendor Registration page (see stream route below, which
    // lives OUTSIDE this auth:agent group since a not-yet-logged-in
    // vendor prospect needs to watch it too).
    Route::get('video-library', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'index'])->name('video-library.index');
    // NEW 10 Aug 2026 — Add Video is its own screen now, not squeezed
    // beside the list (see VideoLibraryController::create()).
    Route::get('video-library/create', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'create'])->name('video-library.create');
    Route::post('video-library', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'store'])->name('video-library.store');
    Route::post('video-library/folder-path', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'updateFolderPath'])->name('video-library.folder-path');
    Route::patch('video-library/{id}/toggle', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'toggle'])->name('video-library.toggle');
    // NEW 10 Aug 2026 — per Chris: "admin received and upload to the
    // master file and activate" — Approve/Reject for agent/vendor
    // content submissions (status PENDING_REVIEW), separate from the
    // plain Active/Inactive toggle above.
    Route::patch('video-library/{id}/approve', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'approve'])->name('video-library.approve');
    Route::post('video-library/{id}/reject', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'reject'])->name('video-library.reject');
    Route::delete('video-library/{id}', [\App\Http\Controllers\Admin\VideoLibraryController::class, 'destroy'])->name('video-library.destroy');

    // NEW 16 Jul 2026 — Sales Transaction Maintenance, Admin scope (all agents).
    // Same shared controller/routes as GL/TL/Introducer, plus Admin-only
    // confirm (release commission) and clear-flag actions.
    Route::get('sales-transactions', [SalesTransactionController::class, 'index'])->name('sales-transactions.index');
    Route::get('sales-transactions/create', [SalesTransactionController::class, 'create'])->name('sales-transactions.create');
    Route::post('sales-transactions', [SalesTransactionController::class, 'store'])->name('sales-transactions.store');
    Route::get('sales-transactions/customer-typeahead', [SalesTransactionController::class, 'customerTypeahead'])->name('sales-transactions.customer-typeahead');
    Route::get('sales-transactions/product-typeahead', [SalesTransactionController::class, 'productTypeahead'])->name('sales-transactions.product-typeahead');
    Route::post('sales-transactions/extract-document', [SalesTransactionController::class, 'extractDocument'])->name('sales-transactions.extract-document');
    Route::get('sales-transactions/{id}', [SalesTransactionController::class, 'show'])->name('sales-transactions.show');
    Route::get('sales-transactions/document/{documentId}', [SalesTransactionController::class, 'document'])->name('sales-transactions.document');
    Route::get('submission-key', [\App\Http\Controllers\Shared\SubmissionKeyController::class, 'show'])->name('submission-key');
    Route::post('sales-transactions/{id}/confirm', [SalesTransactionController::class, 'confirm'])->name('sales-transactions.confirm');
    Route::post('sales-transactions/{id}/clear-flag', [SalesTransactionController::class, 'clearFlag'])->name('sales-transactions.clear-flag');

    // NEW 18 Jul 2026 — Customer Maintenance, Admin scope (all customers).
    Route::get('customers', [SharedCustomer::class, 'index'])->name('customers.index');
    Route::get('customers/typeahead', [SharedCustomer::class, 'typeahead'])->name('customers.typeahead');
    Route::get('customers/{id}', [SharedCustomer::class, 'show'])->name('customers.show');
    Route::get('customers/{id}/edit', [SharedCustomer::class, 'edit'])->name('customers.edit');
    Route::put('customers/{id}', [SharedCustomer::class, 'update'])->name('customers.update');
    Route::post('customers/{id}/deactivate', [SharedCustomer::class, 'deactivate'])->name('customers.deactivate');
    // NEW 19 Jul 2026 — Prospects (personal contacts, no policy yet) +
    // personal follow-up reminders, per Chris.
    Route::get('customers/prospects/create', [SharedCustomer::class, 'createProspect'])->name('customers.prospects.create');
    Route::post('customers/prospects', [SharedCustomer::class, 'storeProspect'])->name('customers.prospects.store');
    Route::post('customers/{id}/reminders', [PersonalReminderController::class, 'store'])->name('customers.reminders.store');
    Route::post('reminders/{id}/done', [PersonalReminderController::class, 'markDone'])->name('reminders.done');
    Route::delete('reminders/{id}', [PersonalReminderController::class, 'destroy'])->name('reminders.destroy');
    // NEW 18 Jul 2026 — Renewal Quotation Requests, Admin scope (all).
    Route::get('renewal-quotations', [RenewalQuotationController::class, 'index'])->name('renewal-quotations.index');
    Route::post('renewal-quotations/{id}/mark-sent', [RenewalQuotationController::class, 'markSent'])->name('renewal-quotations.mark-sent');

    // Batch
    Route::get('batch', [BatchRegistrationController::class, 'index'])->name('batch.index');
    Route::post('batch/upload', [BatchRegistrationController::class, 'upload'])->name('batch.upload');
    Route::get('batch/owner-lookup', [BatchRegistrationController::class, 'ownerLookup'])->name('batch.owner-lookup');
    Route::get('batch/template', [BatchRegistrationController::class, 'downloadTemplate'])->name('batch.template');
    Route::get('batch/{batchId}', [BatchRegistrationController::class, 'show'])->name('batch.show');
    Route::post('batch/{batchId}/validate', [BatchRegistrationController::class, 'validate'])->name('batch.validate');
    Route::post('batch/{batchId}/commit', [BatchRegistrationController::class, 'commit'])->name('batch.commit');
    Route::get('batch/{batchId}/rejection-report', [BatchRegistrationController::class, 'rejectionReport'])->name('batch.rejection-report');
    Route::delete('batch/{batchId}/purge', [BatchRegistrationController::class, 'purge'])->name('batch.purge');
    Route::patch('record/{recordId}', [BatchRegistrationController::class, 'updateRecord'])->name('record.update');

    // Placeholder routes
    Route::get('transactions', function() { return redirect()->route('admin.dashboard'); })->name('transactions');
    Route::get('points', function() { return redirect()->route('admin.dashboard'); })->name('points.index');
});

// NEW 11 Sep 2026 (Task #413 follow-up) — per Chris's decision: Entity
// Maintenance's create/store must be reachable by a CBE node officer for
// their OWN node, not platform Admin only. Same 'admin.' route names and
// 'admin' URL prefix as before (nothing about the URLs or the link in
// glade.blade.php changes) — only the middleware differs: 'role:ADMIN'
// swapped for 'cbe.officer.or.admin' (admits Admin OR an agent with an
// active cbe_node_officers row). AdminCbeHierarchyNodeController itself
// still enforces that an officer can only touch their own node's
// subtree — this middleware only decides who gets past the door.
// CHANGED 27 Sep 2026 -- per Chris: "your side bar is wrong again" -- this group
// lacked 'glade.sidebar', so Entity Maintenance / Entity Hierarchy Link could open
// with the generic General Link sidebar instead of the CBE (GLADE) one.
Route::middleware(['auth:agent', 'cbe.officer.or.admin', 'glade.sidebar'])->prefix('admin')->name('admin.')->group(function () {
    // MOVED 28 Sep 2026 — per Chris (item 36): Member Maintenance is also for a CBE officer (his own entity only;
    // AdminMemberFileController scopes him). Same names / URLs as before.
    Route::get('member-file', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'landing'])->name('member-file.landing');
    Route::get('member-file/add', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'create'])->name('member-file.create');
    Route::post('member-file', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'store'])->name('member-file.store');
    Route::get('member-file/saved/{id}', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'saved'])->name('member-file.saved');
    Route::get('member-file/search', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'index'])->name('member-file.index');
    Route::get('member-file/entity-lookup', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'entityLookup'])->name('member-file.entity-lookup');
    Route::get('member-file/person-lookup', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'personLookup'])->name('member-file.person-lookup');
    Route::get('member-file/company-lookup', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'companyLookup'])->name('member-file.company-lookup');
    Route::post('member-file/{id}/practitioner', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'practitionerAdd'])->name('member-file.practitioner.add');
    Route::post('member-file/line/{tagId}/family', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'familyAdd'])->name('member-file.family.add');
    Route::post('member-file/family/{familyId}/remove', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'familyRemove'])->name('member-file.family.remove');
    Route::post('member-file/line/{tagId}/company', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'companySet'])->name('member-file.company.set');
    Route::get('member-file/join-qr/{node}', [\App\Http\Controllers\JoinController::class, 'qr'])->name('member-file.join-qr');
    // NEW 28 Sep 2026 — item 35: Member KPI dashboard
    Route::get('member-kpi', [\App\Http\Controllers\Admin\MemberKpiController::class, 'index'])->name('member-kpi');
    // NEW 28 Sep 2026 — item 26: Donor & Sponsor Link / Merge
    Route::get('donor-link', [\App\Http\Controllers\Admin\AdminDonorLinkController::class, 'index'])->name('donor-link.index');
    Route::post('donor-link/link', [\App\Http\Controllers\Admin\AdminDonorLinkController::class, 'link'])->name('donor-link.link');
    Route::post('donor-link/merge', [\App\Http\Controllers\Admin\AdminDonorLinkController::class, 'merge'])->name('donor-link.merge');
    Route::get('member-file/{id}/edit', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'edit'])->name('member-file.edit');
    Route::put('member-file/{id}', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'update'])->name('member-file.update');
    Route::get('member-file/{id}/existing', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'existing'])->name('member-file.existing');
    Route::post('member-file/{id}/affiliation', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'addAffiliation'])->name('member-file.affiliation.add');
    Route::post('member-file/line/{tagId}/end', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'endLine'])->name('member-file.line.end');
    Route::post('member-file/line/{tagId}/payment', [\App\Http\Controllers\Admin\AdminMemberFileController::class, 'payment'])->name('member-file.line.payment');
    Route::get('cbe-kpi/hierarchy-nodes/create', [AdminCbeHierarchyNodeController::class, 'create'])->name('cbe-kpi.hierarchy-nodes.create');
    Route::post('cbe-kpi/hierarchy-nodes', [AdminCbeHierarchyNodeController::class, 'store'])->name('cbe-kpi.hierarchy-nodes.store');
    // NEW 26 Sep 2026 — postcode list inside a coverage From/To range, for the tick-box selection screen.
    Route::get('cbe-kpi/hierarchy-nodes/coverage-postcodes', [AdminCbeHierarchyNodeController::class, 'coveragePostcodes'])->name('cbe-kpi.hierarchy-nodes.coverage-postcodes');
    // NEW 26 Sep 2026 — Entity Maintenance Search / View / Edit (standard Add / Search-Edit method).
    Route::get('cbe-kpi/hierarchy-nodes/search', [AdminCbeHierarchyNodeController::class, 'index'])->name('cbe-kpi.hierarchy-nodes.index');
    Route::get('cbe-kpi/hierarchy-nodes/{node}/edit', [AdminCbeHierarchyNodeController::class, 'edit'])->name('cbe-kpi.hierarchy-nodes.edit');
    Route::put('cbe-kpi/hierarchy-nodes/{node}', [AdminCbeHierarchyNodeController::class, 'update'])->name('cbe-kpi.hierarchy-nodes.update');
    // NEW 26 Sep 2026 — separate program: Entity Hierarchy Link (pick Parent, search, tick, save).
    Route::get('cbe-kpi/hierarchy-link', [AdminCbeHierarchyNodeController::class, 'hierarchyLink'])->name('cbe-kpi.hierarchy-link');
    // NEW 27 Sep 2026 — Group Name › (CBE) › Entities / Branches › Search / View / Edit.
    Route::get('cbe-kpi/group-entities', [AdminCbeHierarchyNodeController::class, 'groupEntities'])->name('cbe-kpi.group-entities');
    // CHANGED 27 Sep 2026 — Affiliate Group: every tick / untick auto-saves.
    Route::post('cbe-kpi/group-entities/toggle', [AdminCbeHierarchyNodeController::class, 'toggleGroupEntity'])->name('cbe-kpi.group-entities.toggle');
    // NEW 27 Sep 2026 — nationwide town/area type-ahead for the City search box.
    Route::get('cbe-kpi/place-lookup', [AdminCbeHierarchyNodeController::class, 'placeLookup'])->name('cbe-kpi.place-lookup');
    // NEW 27 Sep 2026 — type-ahead for Affiliated To (Parent).
    Route::get('cbe-kpi/parent-lookup', [AdminCbeHierarchyNodeController::class, 'parentLookup'])->name('cbe-kpi.parent-lookup');
    Route::post('cbe-kpi/hierarchy-link', [AdminCbeHierarchyNodeController::class, 'storeHierarchyLink'])->name('cbe-kpi.hierarchy-link.store');

    // NEW 13 Sep 2026 (Task #418) — vendor entity-link approval must be
    // reachable by a CBE node officer approving a vendor for their OWN
    // temple/club, not platform Admin only — same reasoning as Entity
    // Maintenance above. CbeVendorController::approvals() itself scopes
    // an officer down to only their own node's pending requests, and
    // approve()/reject() both enforce the 4-eye rule (approver must
    // differ from whoever requested it) regardless of which door they
    // came through.
    Route::get('masterfile/cbe-vendor-approvals', [\App\Http\Controllers\Admin\CbeVendorController::class, 'approvals'])->name('masterfile.cbe-vendor-approvals');
    Route::post('masterfile/cbe-vendor-approvals/{link}/approve', [\App\Http\Controllers\Admin\CbeVendorController::class, 'approve'])->name('masterfile.cbe-vendor-approvals.approve');
    Route::post('masterfile/cbe-vendor-approvals/{link}/reject', [\App\Http\Controllers\Admin\CbeVendorController::class, 'reject'])->name('masterfile.cbe-vendor-approvals.reject');

    // NEW 13 Sep 2026 (Task #418) — CBE Marketplace, Phase 3: Listings +
    // Orders. Same middleware group as vendor approvals above — an
    // entity's own officer manages their own listings/orders, platform
    // Admin can pick any entity via the group->node picker.
    Route::get('masterfile/cbe-marketplace-listings', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'listings'])->name('masterfile.cbe-marketplace-listings');
    Route::post('masterfile/cbe-marketplace-listings/{node}', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'storeListing'])->name('masterfile.cbe-marketplace-listings.store');
    Route::post('masterfile/cbe-marketplace-listings/{listing}/toggle', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'toggleListing'])->name('masterfile.cbe-marketplace-listings.toggle');
    Route::get('masterfile/cbe-marketplace-orders', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'orders'])->name('masterfile.cbe-marketplace-orders');
    Route::post('masterfile/cbe-marketplace-orders/{node}', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'storeOrder'])->name('masterfile.cbe-marketplace-orders.store');
    Route::get('masterfile/cbe-marketplace-orders/{node}/customer-typeahead', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'customerTypeahead'])->name('masterfile.cbe-marketplace-orders.customer-typeahead');

    // NEW 13 Sep 2026 (Task #418) — CBE Marketplace, Phase 4: Campaigns,
    // including "Membership Recruitment Initiative" (see controller
    // notes). Same middleware group — Admin or the entity's own officer.
    Route::get('masterfile/cbe-marketplace-campaigns', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'campaigns'])->name('masterfile.cbe-marketplace-campaigns');
    Route::post('masterfile/cbe-marketplace-campaigns/{node}', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'storeCampaign'])->name('masterfile.cbe-marketplace-campaigns.store');
    Route::post('masterfile/cbe-marketplace-campaigns/{campaign}/advance', [\App\Http\Controllers\Admin\CbeMarketplaceController::class, 'advanceCampaign'])->name('masterfile.cbe-marketplace-campaigns.advance');

    // NEW 13 Sep 2026 (Task #418) — Master File Maintenance split into
    // sub-screens (25+ links no longer fit one no-scroll screen), per
    // Chris. Financial Master File groups the 16 accounting/finance
    // reference-data links; Vendor Master File groups the 5 vendor and
    // CBE Marketplace links. Static hub pages, no controller needed —
    // each view builds its own item list and links out.
    Route::view('masterfile/financial-master-file', 'masterfile.financial-master-file')->name('masterfile.financial-master-file');
    Route::view('masterfile/vendor-master-file', 'masterfile.vendor-master-file')->name('masterfile.vendor-master-file');
});