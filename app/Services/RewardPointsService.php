<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RewardPointsService
{
    private ?string $systemAgentIdCache = null;

    /**
     * FIXED 30 Jul 2026 — reward_points_ledger.created_by is a real
     * foreign key to agents.agent_id (a UUID), but both insert sites in
     * this file were writing the literal string 'SYSTEM' into it. That
     * never failed before simply because no Reward Points Rate had ever
     * resolved to a positive point amount in practice, so the insert
     * was never actually reached — Chris's bulk commission:recalculate-
     * all run was the first time real rates existed and this bug got
     * exercised. Same convention already used in CommissionEngine's
     * SYSTEM_COMPANY_ACCOUNT breakage handling: resolve a real ADMIN
     * agent row to stand in for "the system" rather than fabricating an
     * ID. Cached per-request since it never changes mid-run.
     */
    private function resolveSystemAgentId(string $fallbackAgentId): string
    {
        if ($this->systemAgentIdCache) {
            return $this->systemAgentIdCache;
        }

        $adminId = DB::table('agents')->where('role', 'ADMIN')->where('is_deleted', false)->value('agent_id');
        return $this->systemAgentIdCache = $adminId ?? $fallbackAgentId;
    }

    /**
     * Award reward points to an agent after commission is confirmed.
     * Rate priority: Vendor+Product → Vendor only → Global.
     */
    public function awardPoints(
        string $agentId,
        float  $commissionAmount,
        string $sourceTxnId,
        string $vendorId,
        string $productId
    ): void {
        $rate = $this->resolveRate($vendorId, $productId);

        if (! $rate) {
            Log::warning("No reward points rate found for vendor={$vendorId} product={$productId}. Points = 0.");
            $pointsEarned = 0;
        } else {
            $pointsEarned = round($commissionAmount * $rate->points_per_rm, 4);
        }

        // Update commission_transactions with reward points fields
        DB::table('commission_transactions')
          ->where('txn_id', $sourceTxnId)
          ->update([
              'reward_points_rate_id' => $rate?->rate_id,
              'reward_points_earned'  => $pointsEarned,
              'updated_at'            => now(),
          ]);

        if ($pointsEarned <= 0) return;

        $newBalance = $this->getBalance($agentId) + $pointsEarned;

        DB::table('reward_points_ledger')->insert([
            'ledger_id'       => Str::uuid()->toString(),
            'agent_id'        => $agentId,
            'txn_type'        => 'EARNED',
            'points_in'       => $pointsEarned,
            'points_out'      => 0,
            'running_balance' => $newBalance,
            'source_txn_id'   => $sourceTxnId,
            'notes'           => "Commission RM {$commissionAmount} × {$rate->points_per_rm} pts/RM",
            'created_by'      => $this->resolveSystemAgentId($agentId),
            'created_at'      => now(),
        ]);
    }

    /**
     * Reverse reward points when a commission transaction is reversed (policy cancellation).
     */
    public function reversePoints(string $agentId, string $sourceTxnId): void
    {
        $earned = DB::table('reward_points_ledger')
            ->where('agent_id', $agentId)
            ->where('source_txn_id', $sourceTxnId)
            ->where('txn_type', 'EARNED')
            ->sum('points_in');

        if ($earned <= 0) return;

        $newBalance = max(0, $this->getBalance($agentId) - $earned);

        DB::table('reward_points_ledger')->insert([
            'ledger_id'       => Str::uuid()->toString(),
            'agent_id'        => $agentId,
            'txn_type'        => 'REVERSED',
            'points_in'       => 0,
            'points_out'      => $earned,
            'running_balance' => $newBalance,
            'source_txn_id'   => $sourceTxnId,
            'notes'           => 'Points reversed due to commission reversal',
            'created_by'      => $this->resolveSystemAgentId($agentId),
            'created_at'      => now(),
        ]);
    }

    /**
     * Rate resolution priority:
     * 1. vendor_id + product_id match
     * 2. vendor_id only (product_id IS NULL)
     * 3. Global (both NULL)
     */
    private function resolveRate(string $vendorId, string $productId): ?object
    {
        $today = now()->toDateString();

        // Priority 1: vendor + product
        $rate = DB::table('reward_points_rates')
            ->where('vendor_id', $vendorId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('valid_from', '<=', $today)
            ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->orderByDesc('valid_from')
            ->first();
        if ($rate) return $rate;

        // Priority 2: vendor only
        $rate = DB::table('reward_points_rates')
            ->where('vendor_id', $vendorId)
            ->whereNull('product_id')
            ->where('is_active', true)
            ->where('valid_from', '<=', $today)
            ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->orderByDesc('valid_from')
            ->first();
        if ($rate) return $rate;

        // Priority 3: global
        return DB::table('reward_points_rates')
            ->whereNull('vendor_id')
            ->whereNull('product_id')
            ->where('is_active', true)
            ->where('valid_from', '<=', $today)
            ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->orderByDesc('valid_from')
            ->first();
    }

    public function getBalance(string $agentId): float
    {
        return (float) DB::table('reward_points_ledger')
            ->where('agent_id', $agentId)
            ->selectRaw('COALESCE(SUM(points_in) - SUM(points_out), 0) as balance')
            ->value('balance');
    }
}
