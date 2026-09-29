<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// NEW — checks pending 4-eye approvals and sends reminder/escalation
// notifications, per Admin Director's configured durations. Runs
// hourly, but each individual reminder/escalation only ever sends
// once (tracked via reminder_sent/escalation_sent flags), so running
// this frequently is safe and just keeps the check responsive.
Schedule::command('approvals:check-reminders')->hourly();

// NEW 18 Jul 2026 — Renewal reminder pipeline. Sends the initial
// customer-facing reminder once per policy (guarded by
// reminder_sent_at), then escalates unfulfilled quotation requests
// Introducer -> TL -> GL -> Admin at day 3/5/7/10 (guarded by their
// own per-level timestamps) — same "safe to run often, never
// double-sends" pattern as the approvals check above.
Schedule::command('renewals:send-reminders')->daily();
Schedule::command('renewals:check-quotation-reminders')->hourly();

// NEW 20 Jul 2026 — per Chris: personal Follow Up Reminders (Save &
// Send) previously only ever notified once at creation time, never
// again on the actual reminder_date. Same daily cadence and
// safe-to-run-often guard pattern (notification_sent_at) as the
// renewal reminder command above.
Schedule::command('reminders:send-followups')->daily();

// NEW 20 Jul 2026 — per Chris: "unclaimed commission" reminder, closes
// a gap that was discussed before but never built. Settings
// (enabled/threshold/min amount) configurable under Notification Setup.
Schedule::command('commissions:check-unclaimed')->daily();

// NEW 22 Jul 2026 — Fraud Review Queue reminder/escalation. Same
// safe-to-run-often pattern as the approvals check above
// (reminder_sent_at/escalated_at guard against double-sending).
Schedule::command('fraud-review:check-reminders')->hourly();

// NEW 25 Jul 2026 — Breakaway Bonus evaluation. Safe to run daily —
// each (link, period) only ever produces one claim (bbc_link_period_unique).
Schedule::command('breakaway:evaluate-bonuses')->daily();

// NEW 25 Jul 2026 — Growth & Outreach Center: Recruitment Contests and
// Milestone Badges. Same safe-to-run-often pattern — each award is
// unique per (contest, agent) or (agent, badge), so running daily never
// double-awards.
Schedule::command('contests:evaluate')->daily();
Schedule::command('badges:evaluate')->daily();

// NEW 25 Jul 2026 — per Chris: "give the automatic calendar schedule to
// send la rather than i need to do and remember every day." Fires any
// Broadcast Campaign the moment its scheduled_at time arrives, with no
// manual clicking. Hourly (not daily) since scheduled_at is a specific
// date+time, not just a date. Safe to run often — a campaign flips
// SCHEDULED -> SENT once and is never picked up again.
Schedule::command('broadcasts:dispatch')->hourly();

// NEW 29 Jul 2026 — Support Ticket SLA/escalation (task #260). Per
// Chris: "make full use of EspoCRM" — Help Desk module. Hourly so a
// High-priority (4-hour SLA) breach is caught promptly; the 24-hour
// sla_notified_at cooldown means it's still safe to run this often —
// each ticket only ever re-notifies once a day while still breached.
Schedule::command('tickets:check-sla')->hourly();

// NEW 18 Sep 2026 — CBE internal messaging escalation (payment
// notifications, receipt requests, CN/DN, cheque/TT slip). Same hourly
// + cooldown-guarded pattern as tickets:check-sla above.
Schedule::command('cbe-messaging:check-sla')->hourly();

// NEW 2 Aug 2026 — Vendor Override Member commission calculation. Was
// never scheduled before (had to be run by hand, and nobody knew to).
// Runs on the 1st of every month at 01:00 — writes each active
// member's calculated claim for the period into
// override_commission_claims, ready for Admin to review on the
// Override Claim Report screen (Master File Maintenance).
Schedule::command('override:calculate')->monthlyOn(1, '01:00');

// NEW 17 Sep 2026 — per Chris: festival greeting + opt-in birthday
// notices post themselves, no officer action needed. Runs daily just
// after midnight, before the 3am xampp backup; safe to run more than
// once a day — every insert is guarded by a unique system_period_key
// per node/type so nothing ever double-posts.
Schedule::command('cbe:generate-system-notices')->dailyAt('02:00');

// NEW 17 Sep 2026 — per Chris ("yes build all this for me"): Committee
// term-expiry alerts + Statutory compliance reminders. Both safe to
// run daily — term_expiry_notified_at / last_notified_year dedup guards
// mean re-running the same day never double-notifies.
Schedule::command('cbe:check-committee-term-expiry')->dailyAt('07:00');
Schedule::command('cbe:check-compliance-reminders')->dailyAt('07:00');
Schedule::command('cbe:send-meeting-availability-notices')->dailyAt('08:00');
