<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 20 Jul 2026 — per Chris: "unclaimed commission" was discussed
// before but never actually built — ApprovalService had an
// UNCLAIMED_COMMISSION routing label that nothing ever triggered.
// This closes that gap: reminds an agent whose Earning Income Wallet
// balance has been sitting unclaimed (no withdrawal request submitted)
// past a configurable threshold, so money doesn't just sit there
// forgotten. Settings are configurable by Admin under Notification
// Setup (system_settings keys below), not hardcoded.
//
// This is a genuinely ongoing condition (unlike a one-time event like
// a renewal date), so unclaimed_reminder_sent_at works as a re-arm
// cooldown rather than a permanent guard — the agent gets reminded
// again every {threshold_days} as long as the balance stays unclaimed.
// -------------------------------------------------------
class CheckUnclaimedCommissions extends Command
{
    protected $signature = 'commissions:check-unclaimed';
    protected $description = 'Reminds agents whose Earning Income Wallet balance has sat unclaimed past the configured threshold';

    public function handle()
    {
        $enabled = DB::table('system_settings')->where('setting_key', 'unclaimed_commission_enabled')->value('setting_value');
        if ($enabled !== null && $enabled === '0') {
            $this->info('Unclaimed commission reminder is disabled in Notification Setup — skipping.');
            return;
        }

        $thresholdDays = (int) (DB::table('system_settings')->where('setting_key', 'unclaimed_commission_threshold_days')->value('setting_value') ?? 60);
        $minAmount = (float) (DB::table('system_settings')->where('setting_key', 'unclaimed_commission_min_amount')->value('setting_value') ?? 50);
        $cutoff = now()->subDays($thresholdDays);

        DB::table('earning_wallets as w')
            ->where('w.wallet_balance', '>=', $minAmount)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('w.unclaimed_reminder_sent_at')
                  ->orWhere('w.unclaimed_reminder_sent_at', '<=', $cutoff);
            })
            ->select('w.wallet_id', 'w.agent_id', 'w.wallet_balance')
            ->orderBy('w.wallet_id')
            ->chunkById(500, function ($wallets) {
                foreach ($wallets as $wallet) {
                    $this->processOne($wallet);
                }
            }, 'w.wallet_id');

        $this->info('Unclaimed commission sweep complete.');
    }

    private function processOne(object $wallet): void
    {
        // Skip if this agent already has a withdrawal request in
        // flight — no need to remind them to withdraw what they've
        // already asked to withdraw.
        $hasActiveRequest = DB::table('withdrawal_requests')
            ->where('agent_id', $wallet->agent_id)
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->exists();
        if ($hasActiveRequest) {
            return;
        }

        $agent = Agent::find($wallet->agent_id);
        if (!$agent || $agent->is_deleted || $agent->role === 'ADMIN') {
            // Admin never has a personal wallet/withdrawal — see
            // WalletController::blockAdmin(). Skip defensively in case
            // a stray row ever exists.
            return;
        }

        $title = 'You have unclaimed commission';
        $message = 'You have RM ' . number_format($wallet->wallet_balance, 2) .
            " sitting in your Earning Income Wallet that hasn't been withdrawn. " .
            'Visit your Wallet to submit a withdrawal request whenever you\'re ready.';

        (new NotificationService())->notify([$agent], 'UNCLAIMED_COMMISSION_REMINDER', $title, $message, $agent->agent_id);

        DB::table('earning_wallets')
            ->where('wallet_id', $wallet->wallet_id)
            ->update(['unclaimed_reminder_sent_at' => now(), 'updated_at' => now()]);
    }
}
