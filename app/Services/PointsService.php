<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PointsService
{
    // -------------------------------------------------------
    // Transfer reward points to another agent
    // -------------------------------------------------------
    public function transferPoints(Agent $from, string $toAgentId, float $points, string $notes = ''): void
    {
        $balance = $this->getPointsBalance($from->agent_id);

        if ($points <= 0)        throw new \Exception('Points must be greater than zero.');
        if ($points > $balance)  throw new \Exception('Insufficient points balance.');

        $to = Agent::where('agent_id', $toAgentId)->where('is_deleted', false)->firstOrFail();

        DB::transaction(function () use ($from, $to, $points, $notes) {

            $fromNewBalance = $this->getPointsBalance($from->agent_id) - $points;
            $toNewBalance   = $this->getPointsBalance($to->agent_id)   + $points;

            DB::table('reward_points_ledger')->insert([
                [
                    'ledger_id'             => Str::uuid()->toString(),
                    'agent_id'              => $from->agent_id,
                    'txn_type'              => 'TRANSFERRED',
                    'points_in'             => 0,
                    'points_out'            => $points,
                    'running_balance'       => $fromNewBalance,
                    'transfer_to_agent_id'  => $to->agent_id,
                    'notes'                 => $notes ?: "Transferred to {$to->full_name}",
                    'created_by'            => $from->agent_id,
                    'created_at'            => now(),
                ],
                [
                    'ledger_id'               => Str::uuid()->toString(),
                    'agent_id'                => $to->agent_id,
                    'txn_type'                => 'TRANSFERRED',
                    'points_in'               => $points,
                    'points_out'              => 0,
                    'running_balance'         => $toNewBalance,
                    'transfer_from_agent_id'  => $from->agent_id,
                    'notes'                   => $notes ?: "Received from {$from->full_name}",
                    'created_by'              => $from->agent_id,
                    'created_at'              => now(),
                ],
            ]);
        });
    }

    // -------------------------------------------------------
    // Transfer commission (wallet) to another agent
    // -------------------------------------------------------
    public function transferCommission(Agent $from, string $toAgentId, float $amount, string $notes = ''): void
    {
        if ($amount <= 0)                         throw new \Exception('Amount must be greater than zero.');
        if ($amount > $from->commission_balance)  throw new \Exception('Insufficient commission balance.');

        $to = Agent::where('agent_id', $toAgentId)->where('is_deleted', false)->firstOrFail();

        DB::transaction(function () use ($from, $to, $amount, $notes) {
            DB::table('agents')->where('agent_id', $from->agent_id)
              ->decrement('commission_balance', $amount);
            DB::table('agents')->where('agent_id', $to->agent_id)
              ->increment('commission_balance', $amount);

            AuditService::logChange('agents', $from->agent_id, 'COMMISSION_TRANSFER', null, [
                'from' => $from->agent_id,
                'to'   => $to->agent_id,
                'amount' => $amount,
                'notes' => $notes,
            ]);
        });
    }

    // -------------------------------------------------------
    // Member submits a point purchase request (uploads slip)
    // -------------------------------------------------------
    public function submitPurchaseRequest(Agent $agent, float $amountPaid, string $slipPath, string $slipRef, string $bankInDate): string
    {
        // Calculate points based on admin cashout rate (100 pts = RM 1 by default)
        $rate   = DB::table('reward_points_rates')
                    ->whereNull('vendor_id')->whereNull('product_id')
                    ->where('is_active', true)->value('points_per_rm') ?? 2.5;

        $points = $amountPaid * $rate;

        $purchaseId = Str::uuid()->toString();

        DB::table('point_purchases')->insert([
            'purchase_id'    => $purchaseId,
            'agent_id'       => $agent->agent_id,
            'amount_paid_rm' => $amountPaid,
            'points_to_credit'=> $points,
            'bank_slip_path' => $slipPath,
            'bank_slip_ref'  => $slipRef,
            'bank_in_date'   => $bankInDate,
            'status'         => 'PENDING',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return $purchaseId;
    }

    // -------------------------------------------------------
    // Admin approves a point purchase — credits points to member
    // -------------------------------------------------------
    public function approvePurchase(string $purchaseId, string $adminId): void
    {
        $purchase = DB::table('point_purchases')->where('purchase_id', $purchaseId)->first();

        if (! $purchase || $purchase->status !== 'PENDING') {
            throw new \Exception('Purchase not found or already processed.');
        }

        DB::transaction(function () use ($purchase, $purchaseId, $adminId) {

            $newBalance = $this->getPointsBalance($purchase->agent_id) + $purchase->points_to_credit;

            $ledgerId = Str::uuid()->toString();

            DB::table('reward_points_ledger')->insert([
                'ledger_id'       => $ledgerId,
                'agent_id'        => $purchase->agent_id,
                'txn_type'        => 'PURCHASED',
                'points_in'       => $purchase->points_to_credit,
                'points_out'      => 0,
                'running_balance' => $newBalance,
                'reference_no'    => $purchase->bank_slip_ref,
                'notes'           => "Point purchase approved. Bank-in: RM {$purchase->amount_paid_rm}. Ref: {$purchase->bank_slip_ref}",
                'created_by'      => $adminId,
                'created_at'      => now(),
            ]);

            DB::table('point_purchases')->where('purchase_id', $purchaseId)->update([
                'status'       => 'APPROVED',
                'reviewed_by'  => $adminId,
                'reviewed_at'  => now(),
                'ledger_id'    => $ledgerId,
                'updated_at'   => now(),
            ]);
        });
    }

    // -------------------------------------------------------
    // Admin rejects a point purchase
    // -------------------------------------------------------
    public function rejectPurchase(string $purchaseId, string $adminId, string $reason): void
    {
        DB::table('point_purchases')->where('purchase_id', $purchaseId)->update([
            'status'           => 'REJECTED',
            'reviewed_by'      => $adminId,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
            'updated_at'       => now(),
        ]);
    }

    // -------------------------------------------------------
    // Get live points balance from ledger
    // -------------------------------------------------------
    public function getPointsBalance(string $agentId): float
    {
        return (float) DB::table('reward_points_ledger')
            ->where('agent_id', $agentId)
            ->selectRaw('COALESCE(SUM(points_in) - SUM(points_out), 0) as balance')
            ->value('balance');
    }
}
