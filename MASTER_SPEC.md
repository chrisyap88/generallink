# GeneralLink — Master Spec & Continuity Document

**Last updated: 20 Jul 2026, end of session.** This file exists so that a new chat session (a different AI conversation, possibly a different AI entirely) can pick up this project with full context, without Chris having to re-explain everything from scratch. If you are a new session reading this: read this whole file before touching any code. It reflects the real, current state of the app — not a wishlist.

Chris is the business owner, non-technical, a beginner at coding. He runs the app locally via XAMPP (`C:\xampp\htdocs\generallink`, served at `http://localhost/generallink/public`). He cannot run terminal commands himself for anything beyond `php artisan migrate` — any deployment/migration step must be given as exact, numbered, copy-pasteable instructions. He does not want vague "let me know if you need help" — he wants literal step-by-step. He also does not have his own dev environment; all code changes happen through an AI assistant editing files directly on his machine (or on a mounted copy of it).

---

## 1. What GeneralLink Is

A Laravel 12 CRM for an insurance agency's multi-level agent network. Four agent roles: **Introducer** (bottom tier, sells directly), **Team Leader (TL)**, **Group Leader (GL)**, and **Admin** (staff, not part of the sales hierarchy). Agents recruit other agents downward (parent_id chain). Customers/Prospects belong to one agent (`owned_by_agent_id`). Agents sell insurance products from Vendors, earn tiered commission ("Earning Income" — the app never says "commission" in the UI, only internally), and there's a Finance workflow for withdrawing that money.

Admin itself is split into 3 **departments**: `DIRECTOR` (universal fallback, can approve/see anything), `FINANCE`, `SALES`. This matters for approval routing and (as of today) reminder recipient routing.

### Conventions used throughout the codebase — read this before writing any new code
- **Inline CSS only.** No Bootstrap, no Tailwind (Tailwind config files exist but are unused/dead). Every screen is built with `style="..."` attributes directly on HTML tags. Only exception: Tabler icon webfont (`<i class="ti ti-...">`) and Chart.js/xlsx via CDN, loaded in `resources/views/layouts/dashboard.blade.php`.
- **`DB::table()` raw query builder everywhere**, not Eloquent models, for almost all business tables. The `Agent` model is the one real exception (used for `Agent::find()` and type-hints).
- **UUID primary keys** (`char(36)` or `uuid()`) on every table, never auto-increment ints.
- **No hard deletes anywhere.** Every table has `is_deleted` (soft delete flag). Customers specifically: agents can never delete, only set status to `INACTIVE`; Admin permanently purges via a dedicated Housekeeping screen (soft or hard delete, per-batch, manual only — see §4).
- **`AuditService::logChange()`** — call this after any meaningful UPDATE/CREATE/DELETE on a business table, feeds the Activity Log History tabs.
- **`NotificationService`** (`app/Services/NotificationService.php`) — the ONE place that creates bell notifications + sends emails. Never write raw `DB::table('notifications')->insert()` or `Mail::raw()` outside of it. Has 3 recipient-resolution helpers (`recipientsForGroupBroadcast`, `recipientsForUplineChain`, `recipientsForDemotion`) plus the generic `notify(array $recipients, string $type, string $title, string $message, ...)`.
- **`DataScopeService`** — the ONE place that scopes queries by role. `applyToCustomers()` restricts every non-Admin role to their OWN records only (no downline visibility — deliberate, see §5). `applyToTransactions()` and `applyToCommissions()` give GL/TL their WHOLE downline (team-wide), unchanged. Never hand-roll scope filtering in a controller.
- **`system_settings`** table — generic key-value store (`setting_key` primary, `setting_value` text) for anything Admin needs to configure without a code change. Established pattern: `DB::table('system_settings')->updateOrInsert(['setting_key'=>...], ['setting_value'=>...])`.
- **Known JS footgun** (cost real debugging time on 19 Jul 2026): never put `display:grid` directly on an element whose `.style.display` gets toggled by tab-switching JS (the `.cuTabBtn`/`.cuTabPanel` and similar patterns use `p.style.display = 'block'/'none'`). This silently overwrites `display:grid` the instant the tab activates, collapsing multi-column layouts to stacked full-width blocks. Always nest the grid one level deeper on a child wrapper div instead.
- **Compact, no-scroll screens**: Chris consistently wants every screen to fit in one viewport with no scrolling, small fonts (9-11px range is the norm for dense data screens), tight padding. When in doubt, make it smaller/tighter, not bigger.
- **Left-justify all data values.** Do not use `justify-content:space-between` to right-align values against labels — Chris has explicitly rejected right-justified data multiple times. Use a fixed-width label + left-aligned value instead.
- **Windows Task Scheduler**, not cron — this is a Windows/XAMPP machine. Laravel's scheduler (`routes/console.php`, `Schedule::command(...)`) only actually fires if something runs `php artisan schedule:run` every minute in the background. Confirmed 20 Jul 2026: **Chris did NOT have this set up** before today — meaning none of the scheduled reminders (renewal, quotation escalation, approvals) were actually firing until he configured Windows Task Scheduler per the instructions given (Program: `C:\xampp\php\php.exe`, Arguments: `artisan schedule:run`, Start in: `C:\xampp\htdocs\generallink`, repeat every 1 minute, indefinitely). **Verify this is still configured on his machine before assuming any scheduled reminder is actually running** — if he ever reinstalls Windows/XAMPP or gets a new PC, this needs to be redone.
- **Production hosting is not decided yet.** Whatever hosting Chris eventually picks (shared cPanel hosting vs VPS vs Windows Server) will need its OWN cron/Task Scheduler equivalent set up separately — the local Windows Task Scheduler entry only helps his local dev machine.

---

## 2. Roles, Permissions & Visibility — critical business rules

- **Customer/Prospect visibility (changed 19 Jul 2026):** every non-Admin role (Introducer/TL/GL alike) can only see/edit customers and prospects **they personally own** — never their downline's. This was a deliberate change specifically to stop an upline (TL/GL) from seeing or interfering with a downline agent's own contacts ("prevent upline sabotage," Chris's words). Admin sees everyone. This does **NOT** apply to Sales Transactions or Commissions — those remain team-wide/downline-visible for GL/TL (they need to see their team's production and earnings).
- **Full Name and NRIC** on a customer record are locked for everyone except Admin (identity/duplicate-matching integrity, since Customer Resolution Service keys off NRIC).
- **Classification fields** (Status, Type, Category, Occupation Group, Source) on a customer — changed 19 Jul 2026 to be editable by whoever can see the record, not Admin-only. Every one of these is a dropdown from a live master-file table, never free text.
- **No one can ever hard-delete a customer.** The only "removal" action available to any agent is "Set Inactive" (their own scope, or Admin any). Admin alone can permanently purge Inactive records via Customer Housekeeping — manual, per-batch (Admin picks the records), soft-delete or hard-delete choice, no automatic time-based purge.
- **Commission/tier rule — "no same-role double-dip":** when calculating commission for a policy (`CommissionEngine::resolveDistributions()`), the hierarchy walk fills each of the 3 tier slots (INTRODUCER / TEAM_LEADER / GROUP_LEADER) with only the FIRST matching agent found walking up from the closer. If an agent's direct upline happens to be the SAME role (e.g. an Introducer recruited by another Introducer, not yet under a TL), that intermediate same-role upline gets **zero** commission — the walk skips past them (their role's slot is already taken) and keeps going up until it finds the real TL and GL. Reviewed 20 Jul 2026 and this appears to be implemented correctly, but **has not yet been empirically confirmed against real data** — Chris's next session priority is to verify this against customer Vasu's actual policy and agent Murali's actual upline chain (see §7).
- **Admin has 3 departments**: DIRECTOR (sees/approves everything, universal fallback), FINANCE, SALES. Used today for: 4-eye Approval routing (`ApprovalService::departmentForAction()` — Withdrawal/Commission-structure-change/Unmatched-claim/Release-held-commission/Unclaimed-commission → FINANCE; Undo-role-change/Admin-assign-placement/Manual-hierarchy-correction/Group-merge-split/Status-change → SALES; everything else → any department). As of today this same 3-department structure is being extended to reminder recipient routing too (see §4, task in progress).
- **Admin never has a personal Earning Income Wallet** and can never submit a withdrawal request for themselves or anyone else — that would be an illegal withdrawal and create real company liability (confirmed business rule, 06 Jul 2026). Admin's only involvement with wallets is approving/processing others' requests via the Finance dashboard.
- **Agent bank account numbers**: currently stored **encrypted** (`Crypt::encryptString`, AES) in `withdrawal_requests.encrypted_bank_account`, collected fresh per withdrawal request, never saved to the agent's permanent profile, only a masked version ever shown on screen, decrypt access restricted to Finance in code. Chris stated today the system "cannot store the agent bank account number" — **this needs his explicit confirmation**: does encrypted-but-stored satisfy his policy, or does he want literally zero storage (which would make it impossible to actually pay agents by bank transfer, so probably he means the former, but confirm before assuming).

---

## 3. Full Feature Inventory (as of 20 Jul 2026)

This list is derived from the running task tracker maintained across the whole project (140+ completed items). Grouped by area. If a new session needs deep detail on any ONE of these, search the codebase for it directly — this is an index, not a full spec of each.

**Agent lifecycle & hierarchy:** registration/activation flow per role with branded verify/set-password/success pages, resend-verification (universal, Admin/GL/TL), multi-TL support for Special Privilege Groups (e.g. PVATM — narrower notification rules than public groups), recruitment hierarchy (parent_id chain), Undo Promotion/Demotion via 4-eye Approval, Group/Team Leader placement tools, agent profile screens (My Profile) per role with photo, beneficiaries, QR referral code, profile history.

**Customer/Prospect Maintenance:** full CRUD across all 4 roles (self-scoped per §2), Prospect creation (no NRIC required, auto-converts to real Customer once a real Sales Transaction is submitted against them via Customer Resolution Service matching by NRIC), configurable lookup tables for Customer Category / Occupation Group / Customer Source / Customer Status / Customer Type (all Admin-configurable code+description tables, not hardcoded enums), tabbed Customer Detail screen (Profile / Sales History / Renewal Reminder / Follow Up Reminder / Activity Log — BI Dashboard tab was removed 20 Jul 2026 per Chris), customer list with typeahead search, Set Inactive action, Admin Housekeeping purge screen.

**Sales Transactions:** full Maintenance module shared across all 4 roles, create/index/show screens, wildcard+typeahead search, document upload + AI extraction (Claude API) for auto-filling policy fields from PDFs, vendor/product fuzzy-matching and auto-lock, insurance-specific fields (Coverage Type, NCD%, Excess, Add-ons, vehicle details), Admin confirm/verification step, document reference number generation, Renewal Reminder auto-creation and preview at submission time.

**Commission / Earning Income:** `CommissionEngine` (see §2 for the tier rule), `commission_structures` (config, never hardcoded), PENDING→CONFIRMED flow (money only moves on Admin confirming the policy against real vendor remittance — not at submission), reversal handling for cancelled policies, Reward Points module, Earning Income Wallet + Withdrawal Request flow (email-confirmation-code step, 24hr expiry, 4-eye Finance approval via ApprovalService).

**Document Templates:** extraction engine (Claude API-based), Admin calibration UI, version history, document_type matching key, 3-step wizard (in progress — not finished, see §7 for status).

**Vendor / Product Maintenance:** industry category, Products Offered per vendor, Earning Income/Reward Rates merged into Vendor+Product screens, tabbed Vendor Edit (was overflowing before fix).

**Approvals (4-eye):** generic reusable engine (`ApprovalService`) — any Admin requests, a DIFFERENT Admin (matching department, or Director always) approves. Configurable reminder/escalation hours (Director-only settings screen). Used for Withdrawal Approval, Commission Structure Change, Undo Role Change, Admin Assign Placement, Manual Hierarchy Correction, Group Merge/Split, Status Change. **Unclaimed Commission and Unmatched Claim were defined as routing labels in this service from early on but nothing ever triggered them** — see §4, this was finally addressed today for Unclaimed Commission.

**Reminders/Notifications — see §4, mostly built today.**

**Renewal workflow:** `insurance_renewal_schedules` table (auto-created per policy at submission), `SendRenewalReminders` command (customer-facing, cc's upline chain, configurable lead time as of today), `renewal_quotation_requests` + `CheckRenewalQuotationReminders` escalation (Introducer→TL→GL→Admin, configurable days as of today), Yearly Renewal Forecast dashboard (in progress, see §7).

**Calendar:** consolidates Admin company events + agent's own personal Follow Up Reminders + auto-computed policy Renewal Reminders into one view. Rebuilt 20 Jul 2026 into a real month-grid (Google Calendar style) — see §4.

**Master File Maintenance sidebar section:** Reason Codes, Customer Type/Category/Occupation Group/Source, Vendor/Product, Group Name, Special Privilege Groups, Audit Logs, Pending Verifications (manual resend), Customer Maintenance (moved here from per-role Business menus, shared across all 4 roles).

**Reminder Processes sidebar section:** Approvals, Customer Housekeeping, Calendar & Reminders, Renewal Forecast, Notification Setup (built today, was a dead link before).

---

## 4. Today's Session (20 Jul 2026) — Reminder & Notification System

This was the main focus of today. Full detail since this is the freshest, most-likely-to-need-follow-up work.

### Built and fully working
1. **Customer Detail screen fixes** — left-justified all field values (was wrongly right-justified via `justify-content:space-between`), removed duplicated postcode/city/state text that was baked into the raw `address` field (stripped for display only, underlying data untouched), moved "Created By" into its own box in Column 2 below Classification, widened Column 1 (1.4fr) vs Column 2 (1fr), Address field order is now Address → Postcode → City → State → Customer Since, removed the BI Dashboard tab entirely (Chris didn't want it), un-nested Renewal Reminder / Follow Up Reminder from a sub-tab drill-down into their own top-level tabs, shortened tab labels, tightened all spacing so the whole screen fits one viewport with minimal top gap, tightened line-spacing inside the Renewal Reminder message box (blank lines between paragraphs now render as a small 4px gap instead of a full text-line height, by splitting the message into individual `<div>` lines instead of one `white-space:pre-line` block).
2. **Edit Customer screen rebuilt** — now matches the Profile tab's box layout (Contact / Address / Classification / Identity), Classification fields (Status/Type/Category/Occupation/Source) are now editable by everyone who reaches the screen (previously Admin-only), compacted into a 2-column mini-grid so it fits one screen with no scroll. Only Full Name/NRIC stay Admin-locked.
3. **Follow Up Reminder — on-date auto-notification.** Previously "Save & Send" only ever notified the upline chain once, immediately, at creation — nothing reminded the agent again when the actual `reminder_date` arrived. New migration `2026_07_20_000001` adds `personal_reminders.notification_sent_at` (guard column). New command `app/Console/Commands/SendFollowUpReminders.php` (`reminders:send-followups`, daily) checks for PENDING reminders due today/overdue and notifies the agent who set it (not the upline — that's a separate one-time FYI, this is the "alarm going off" for the person who set it). Registered in `routes/console.php`.
4. **Unclaimed Commission Reminder — brand new, closes a gap that was discussed before but never built.** New migration `2026_07_20_000002` adds `earning_wallets.unclaimed_reminder_sent_at` (re-arm cooldown, not a one-time guard — this is an ongoing condition). New command `app/Console/Commands/CheckUnclaimedCommissions.php` (`commissions:check-unclaimed`, daily) reminds an agent whose wallet balance sits ≥ a configurable minimum, unclaimed for ≥ a configurable number of days, skipping anyone with an active withdrawal request already in flight. Settings: `unclaimed_commission_enabled`, `unclaimed_commission_threshold_days`, `unclaimed_commission_min_amount` (system_settings keys).
5. **Renewal Reminder lead time and Quotation Escalation days made configurable** (were hardcoded). `system_settings` keys: `renewal_reminder_days` (default 30), `quotation_escalation_day1..4` (default 3/5/7/10). `SendRenewalReminders` and `CheckRenewalQuotationReminders` both updated to read these, falling back to the old defaults if unset. Validation added: the 4 escalation days must strictly increase.
6. **Notification Setup screen built** (`app/Http/Controllers/Admin/NotificationSetupController.php`, `resources/views/admin/notification-setup.blade.php`, routes `admin.notification-setup.index`/`.update`) — the sidebar link under Reminder Processes was a dead `href="#"` before today. Rebuilt into 2 folder-style tabs (per Chris's explicit request for a "folder type" layout he understands): **Reminder Types** (read-only reference table — every reminder type, what it does, and an honest status badge: Configurable / No setting needed / Manual only / Not built) and **Settings** (the actual editable form, with a plain-English "how to use" note at the top). Both tabs compacted to fit one screen with no scroll.
7. **Calendar & Reminders rebuilt into a real month-grid view** (Google Calendar style) — `resources/views/calendar/index.blade.php`. Weekday header row, day boxes, today highlighted, events shown as small colored chips on their actual day (Company Event / Follow-up / Renewal, same 3 sources as before — this was purely a display change, `CalendarController` untouched), "+N more" expand for busy days. Replaces the old plain list-by-month table.

### Migrations Chris still needs to run (if not already done)
```
cd C:\xampp\htdocs\generallink
php artisan migrate
```
Should show DONE for: `2026_07_20_000001_add_notification_sent_at_to_personal_reminders`, `2026_07_20_000002_add_unclaimed_reminder_to_earning_wallets`. (Chris confirmed both ran successfully today.)

### Full inventory of every reminder type (as documented on the new Notification Setup screen)
| Reminder | What it does | Status |
|---|---|---|
| Renewal Reminder | Customer's policy nearing renewal, cc's agent's upline chain | Configurable (lead time days) |
| Renewal Quotation Escalation | Customer asked for renewal quote, not sent → escalates Introducer→TL→GL→Admin | Configurable (4 escalation day thresholds) |
| Follow Up Reminder | Agent's own personal reminder on a customer/prospect | Configurable via Save vs Save&Send at creation; on-date notify built today, no further setting needed |
| Unclaimed Commission Reminder | Wallet balance sitting unclaimed past threshold | Configurable (on/off, days, min amount) — built today |
| Approval Reminder/Escalation | Pending 4-eye approval sitting too long | Configurable (Director-only, existing Approvals screen) |
| Verification Email Reminder | Resend account verification email | Manual only (Pending Verifications screen, no auto-check) |
| Unmatched Claim Reminder | Claim couldn't be auto-matched to a vendor payment | **Not built** — the underlying Claims/Vendor Payment matching module is only empty database tables (`claims`, `claim_documents`, `claim_matching_log`, `vendor_payments`, `vendor_payment_allocations`), zero screens/controllers ever create data in them. Cannot build a reminder on a workflow that doesn't exist. |

### Agreed but NOT YET built (queued as tasks in the tracker, numbered #142-147 as of this writing)
- **Recipient checkboxes** (Director/Finance/Sales) for Admin-facing reminders, replacing hardcoded recipient logic — default = whatever each reminder already does today, so nothing changes until Chris actively toggles something. Chris explicitly confirmed he wants this.
- **Underperforming Product Reminder** (Chris's own suggestion) — if a vendor/product combo has zero sales in N configurable days, alert Admin (Sales dept) to review it. Fully buildable now with existing `sales_transactions` data, no new schema needed.
- **Escalation added to Follow Up Reminder** — if still not marked Done X days after due date, escalate to the agent's TL/GL (today it only ever reminds the same agent once).
- **Escalation added to Unclaimed Commission Reminder** — if unclaimed for a much longer period, loop in Finance dept on top of the agent's own repeat reminder.
- **Customer KYC expiry fields + reminder** — passport_expiry (foreign customers) and business_reg_expiry (Corporate/SME/Government/NGO/Association/Educational Institution categories). Neither field exists in `customers` table today — needs a migration + Edit Customer form update before the reminder itself is possible.
- **Customer Portal** — explicitly deferred by Chris to a separate future project, NOT part of the reminder work. Full brainstormed scope preserved in task #147, headline items: Complaints management and Claims submission/tracking are the two that matter for compliance/business-critical reasons (not just convenience); the rest (My Policies dashboard, renewal self-service, profile self-update via approval, feedback, service ratings, news/promotions feed, referral, FAQ, notification preferences, WhatsApp link) are engagement/nice-to-have. **No customer login/auth system exists at all today** — confirmed via `config/auth.php`, only `web` guard exists, no `customer` guard. This is a from-scratch build, not an extension of anything.

---

## 5. Known Gaps / Honest Limitations (don't pretend these are built)

- **Claims/Vendor Payment reconciliation module**: tables exist (`claims`, `claim_documents`, `claim_matching_log`, `vendor_payments`, `vendor_payment_allocations`, `commission_hold_log`), but ZERO controllers, views, or commands reference them. This is pure unused scaffolding from earlier planning. Chris's stated next-priority-after-Earning-Income is to build this out for real (claim submission/request form + remittance/money-transfer request process).
- **No Excel/report export anywhere** — every "report" today is an on-screen drill-down (e.g. the network tree). Chris wants downloadable Excel reports at every level (Admin/GL/TL/Introducer), scope/columns not yet specified.
- **No customer-facing login/portal** at all.
- **Document Template screen 3-step wizard rebuild** — was in progress, not confirmed finished as of last touch.
- **Yearly Renewal Forecast dashboard** — was in progress, not confirmed finished as of last touch.
- **Recruitment depth control fields** — flagged for investigation early in the project, status unknown/never revisited.
- **generallink-enterprise fresh deployment + PVATM group setup in new deployment** — mentioned early in the project as pending, status unknown/never revisited. Likely relates to setting up a second/production instance.
- **Submission Key (delegated ownership) system** and **email ingestion pipeline for admin@generallink.my** — flagged early as "not started," status unknown/never revisited.
- **customer_change_logs migration** — flagged early as pending, status unknown/never revisited.

---

## 6. Environment Notes

- App root: `C:\xampp\htdocs\generallink`, served via XAMPP at `http://localhost/generallink/public`.
- Database: MySQL/MariaDB via XAMPP. Chris hit a "MySQL shutdown unexpectedly" error once (20 Jul 2026) — resolved itself on a second Start attempt in XAMPP Control Panel; log showed a normal crash-recovery startup, not an actual fatal error. If it recurs: check XAMPP's MySQL log first (Logs button), check Task Manager for a stuck `mysqld.exe`, don't touch/delete anything in `mysql/data` without confirming the actual error first.
- Windows Task Scheduler entry for `php artisan schedule:run` (every 1 minute, indefinitely) — **confirmed set up by Chris on 20 Jul 2026.** This is what makes every scheduled reminder command actually fire. Without it, the code exists but nothing runs.
- Chris cannot run most terminal commands — only give him `php artisan migrate` (and equivalent simple commands) with exact numbered steps: open Command Prompt, `cd C:\xampp\htdocs\generallink`, run the command, describe what success looks like (which migration names should appear DONE).
- The project root has a lot of stray one-off PHP scripts and oddly-named files (`check_*.php`, `fix_*.php`, `seed_*.php`, and even some literal shell-fragment filenames like `email)` or `route('admin.dashboard')` — leftover artifacts, not real app code). Ignore these; the real app lives in `app/`, `resources/views/`, `routes/`, `database/migrations/`.
- Anthropic API key for the Document Extraction Engine is stored in `Claude-API-Key.txt` at the project root (per earlier session) — treat as a secret, don't print its contents in chat.

---

## 7. Chris's Stated Next Priorities (verbatim intent, organized) — as of 20 Jul 2026

In his own words/plan, in order:

1. **Verify Murali's upline + Admin were properly notified** about the Sales Transaction already submitted for him — log in as each of Murali's upline (TL, GL) and Admin to confirm the notification actually reached them. (Task #148)
2. **Verify the 3-tier Earning Income calculation is correct for customer Vasu's policy** — specifically confirm the "same role cannot double-dip" rule holds (an Introducer's upline, if also an Introducer, should NOT get a 2nd tier of earning — only the real TL and GL above them should). Pre-analysis done today via code review (§2) suggests this is already correct, but Chris wants to see it confirmed against real data. (Task #149)
3. **Walk through and build the Claims process** — including a claims/request form and the remittance (money transfer) request process. This is the big pending module (§5). (Task #150)
4. **Excel download reports for all levels** — Admin/GL/TL/Introducer. Today there's only on-screen drill-down (network tree), no exportable reports at all. (Task #151)
5. **Data privacy — confirm agent bank account numbers are handled per policy.** Current actual state: encrypted storage in `withdrawal_requests`, never on the agent's profile, masked on screen, decrypt-restricted to Finance (§2). Needs Chris's explicit sign-off that this meets his stated policy. (Task #152)

Chris explicitly said his schedule to deliver this project is "long overdue many days" and he's under real time pressure — prioritize getting him working, verified features over new scope creep. When in doubt, ask him to confirm priority order rather than assuming.

---

## 8. How to Resume

A new session should: read this file fully, then check the live task tracker (TaskList) for the current status of tasks #138-152 (today's work) and anything else marked `pending`/`in_progress`, then ask Chris directly which of §7's priorities he wants to start with — don't assume. Do not re-build anything listed as "Built and fully working" in §4 without first confirming with Chris that something is actually broken — several multi-hour debugging sessions this project have turned out to be the code being correct all along and the real issue being browser cache or a misunderstanding of what was asked. Verify against the actual running app (or ask Chris for a fresh screenshot after a hard refresh, Ctrl+F5) before concluding something doesn't work.
