<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Services\NoticeRelevanceService;

/**
 * NEW 5 Aug 2026 — per Chris: Carolyn shouldn't just answer questions when
 * asked, she should proactively surface things an agent actually needs to
 * know about (overdue tickets, unread notifications, pending approvals,
 * renewal/quotation reminders) — politely, on login, not buried behind a
 * "how do I check my tickets" question the agent has to think to ask.
 *
 * This service is the single source of truth both the login popup/badge
 * (AiAssistantController::alerts) AND Carolyn's own chat system prompt
 * (AiAssistantService::buildSystemPrompt) read from, so the two are always
 * consistent with each other.
 *
 * Deliberately read-only and defensive: every query is wrapped so that if
 * one data source has a problem, the others still work — an agent should
 * never see a broken page because one of five queries failed.
 */
class ProactiveAlertService
{
    /**
     * Returns a flat list of outstanding items for this agent, each shaped
     * as ['type' => string, 'count' => int, 'label' => string, 'route' => string|null].
     * Empty array = nothing outstanding.
     */
    public function items(string $agentId, ?string $role): array
    {
        $items = [];

        $this->safe(function () use (&$items, $agentId) {
            $count = DB::table('notifications')
                ->where('recipient_agent_id', $agentId)
                ->whereNull('read_at')
                ->count();
            if ($count > 0) {
                $items[] = [
                    'type' => 'notification',
                    'count' => $count,
                    'label' => $count === 1 ? '1 unread notification' : "{$count} unread notifications",
                    'route' => 'notifications.index',
                ];
            }
        });

        $this->safe(function () use (&$items, $agentId) {
            $count = DB::table('customer_support_tickets')
                ->where('owned_by_agent_id', $agentId)
                ->where('is_deleted', false)
                ->whereNotIn('status', ['RESOLVED', 'CLOSED'])
                ->whereNotNull('due_at')
                ->where('due_at', '<=', now())
                ->count();
            if ($count > 0) {
                $items[] = [
                    'type' => 'overdue_ticket',
                    'count' => $count,
                    'label' => $count === 1 ? '1 support ticket is overdue' : "{$count} support tickets are overdue",
                    'route' => null,
                ];
            }
        });

        // Admin-only: other agents' requests waiting on an Admin decision
        // (mirrors ApprovalController::index's own filter exactly).
        if ($role === 'ADMIN') {
            $this->safe(function () use (&$items, $agentId) {
                $count = DB::table('pending_approvals')
                    ->where('status', 'PENDING')
                    ->where('requested_by', '!=', $agentId)
                    ->count();
                if ($count > 0) {
                    $items[] = [
                        'type' => 'approval_awaiting_you',
                        'count' => $count,
                        'label' => $count === 1 ? '1 approval is waiting on your decision' : "{$count} approvals are waiting on your decision",
                        'route' => 'approvals.index',
                    ];
                }
            });
        }

        // NEW 14 Aug 2026 — per Chris: "carolyn is informed also." Admin
        // gets a plain bell notification (see VendorAgreementController::
        // notifyAdminsOfAcceptance()) which already feeds the generic
        // unread-notification count above — this block additionally names
        // the specific vendor, same richer pattern as the PROMOTION block
        // below, so Carolyn can actually say who accepted rather than
        // just "you have 1 unread notification."
        if ($role === 'ADMIN') {
            $this->safe(function () use (&$items, $agentId) {
                $rows = DB::table('notifications')
                    ->where('recipient_agent_id', $agentId)
                    ->where('type', 'VENDOR_AGREEMENT_ACCEPTED')
                    ->whereNull('read_at')
                    ->orderByDesc('created_at')
                    ->get();
                $count = $rows->count();
                if ($count > 0) {
                    $latest = $rows->first();
                    $label = $count === 1
                        ? '1 vendor accepted their Registration Activation Agreement — ' . $latest->title
                        : "{$count} vendors accepted their Registration Activation Agreement, most recent: " . $latest->title;
                    $items[] = [
                        'type' => 'vendor_agreement_accepted',
                        'count' => $count,
                        'label' => $label,
                        'route' => 'notifications.index',
                    ];
                }
            });
        }

        // Any role: requests this agent raised that are still waiting on someone else.
        $this->safe(function () use (&$items, $agentId) {
            $count = DB::table('pending_approvals')
                ->where('requested_by', $agentId)
                ->where('status', 'PENDING')
                ->count();
            if ($count > 0) {
                $items[] = [
                    'type' => 'my_approval_pending',
                    'count' => $count,
                    'label' => $count === 1 ? '1 of your requests is still awaiting approval' : "{$count} of your requests are still awaiting approval",
                    'route' => null,
                ];
            }
        });

        $this->safe(function () use (&$items, $agentId) {
            $count = DB::table('insurance_renewal_schedules as irs')
                ->join('sales_transactions as st', 'irs.policy_id', '=', 'st.policy_id')
                ->where('st.agent_id', $agentId)
                ->where('st.is_deleted', false)
                ->whereIn('irs.status', ['UPCOMING', 'DUE', 'OVERDUE'])
                ->whereDate('irs.coverage_end', '<=', now()->addDays(30)->toDateString())
                ->count();
            if ($count > 0) {
                $items[] = [
                    'type' => 'renewal_due',
                    'count' => $count,
                    'label' => $count === 1 ? '1 policy renewal needs attention within 30 days' : "{$count} policy renewals need attention within 30 days",
                    'route' => null,
                ];
            }
        });

        // NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 1 (Task #84).
        // Unread PROMOTION notices (respects the agent's own category
        // preference if they've set one) — Carolyn can now mention "you
        // have new offers" alongside overdue tickets/renewals, not just
        // leave it to the Notice Board badge.
        $this->safe(function () use (&$items, $agentId) {
            $pref = DB::table('agent_notification_preferences')->where('agent_id', $agentId)->first();
            $categories = $pref && $pref->categories ? json_decode($pref->categories, true) : null;
            if ($categories && !in_array('PROMOTION', $categories, true)) {
                return; // agent explicitly opted out of Promotion notices
            }
            $today = now()->toDateString();
            $baseQuery = DB::table('notices as n')
                ->leftJoin('notice_reads as r', function ($join) use ($agentId) {
                    $join->on('n.notice_id', '=', 'r.notice_id')->where('r.agent_id', '=', $agentId);
                })
                ->where('n.category', 'PROMOTION')
                ->where('n.is_deleted', false)
                ->where(function ($q) use ($today) {
                    $q->whereNull('n.expires_at')->orWhere('n.expires_at', '>=', $today);
                })
                ->whereNull('r.read_at');

            $count = (clone $baseQuery)->count();

            if ($count > 0) {
                // NEW 8 Aug 2026 (Task #89) — GLADE Phase 2: name the SINGLE
                // most relevant unread promotion (category preference match
                // + recency), not just "there are N of them" — makes
                // Carolyn's proactive mention feel like it actually picked
                // the best one, not a generic count.
                [$relevanceSql, $relevanceBindings] = NoticeRelevanceService::sqlExpression($categories);
                $top = (clone $baseQuery)
                    ->selectRaw("n.title, {$relevanceSql} as relevance_score", $relevanceBindings)
                    ->orderByDesc('relevance_score')
                    ->first();

                $label = $count === 1
                    ? '1 new offer/promotion on the Notice Board — "' . $top->title . '"'
                    : "{$count} new offers/promotions on the Notice Board — most relevant: \"{$top->title}\"";

                $items[] = [
                    'type' => 'new_promotion',
                    'count' => $count,
                    'label' => $label,
                    'route' => 'notice-board.index',
                ];
            }
        });

        $this->safe(function () use (&$items, $agentId) {
            $count = DB::table('renewal_quotation_requests')
                ->where('agent_id', $agentId)
                ->where('status', 'REQUESTED')
                ->count();
            if ($count > 0) {
                $items[] = [
                    'type' => 'quotation_pending',
                    'count' => $count,
                    'label' => $count === 1 ? '1 customer is waiting on a renewal quotation' : "{$count} customers are waiting on a renewal quotation",
                    'route' => null,
                ];
            }
        });

        return $items;
    }

    public function totalCount(string $agentId, ?string $role): int
    {
        return array_sum(array_column($this->items($agentId, $role), 'count'));
    }

    /**
     * A warm, plain-language message Carolyn can say out loud / show in the
     * popup. Returns null if there's nothing outstanding.
     */
    public function politeMessage(string $agentId, ?string $role, string $fullName): ?string
    {
        $items = $this->items($agentId, $role);
        if (empty($items)) {
            return null;
        }

        $firstName = trim(explode(' ', $fullName)[0] ?? $fullName);
        $lines = array_map(fn ($i) => '- ' . $i['label'], $items);

        return "Welcome back, {$firstName}! Before we dive in, I noticed a few things worth your attention:\n"
            . implode("\n", $lines)
            . "\n\nNo rush — just wanted you to know. Let me know if you'd like help with any of these, or just ask me anything else.";
    }

    /**
     * Same data as politeMessage(), but as a compact block for Carolyn's
     * system prompt so she stays accurate if asked mid-conversation, without
     * needing to re-open the popup.
     */
    public function forSystemPrompt(string $agentId, ?string $role): string
    {
        $items = $this->items($agentId, $role);
        if (empty($items)) {
            return '';
        }
        $lines = array_map(fn ($i) => '- ' . $i['label'], $items);
        return implode("\n", $lines);
    }

    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ProactiveAlertService] a data source failed, skipping it', ['error' => $e->getMessage()]);
        }
    }
}
