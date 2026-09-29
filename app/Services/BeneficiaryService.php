<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Beneficiary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BeneficiaryService
{
    /**
     * Save or update a beneficiary record.
     */
    public function save(Agent $agent, array $data, ?string $beneficiaryId = null): Beneficiary
    {
        $before = null;

        if ($beneficiaryId) {
            $ben = Beneficiary::where('beneficiary_id', $beneficiaryId)
                              ->where('agent_id', $agent->agent_id)
                              ->firstOrFail();
            $before = $ben->toArray();

            $ben->update([
                'full_name'               => $data['full_name'],
                'nric_encrypted'          => encrypt($data['nric']),
                'relationship'            => $data['relationship'],
                'phone'                   => $data['phone'] ?? null,
                'email'                   => $data['email'] ?? null,
                'address'                 => $data['address'] ?? null,
                'bank_name'               => $data['bank_name'] ?? null,
                'bank_account_encrypted'  => isset($data['bank_account']) ? encrypt($data['bank_account']) : null,
                'priority_order'          => $data['priority_order'] ?? 1,
                'updated_by'              => auth('agent')->id(),
            ]);

            AuditService::logChange('beneficiaries', $ben->beneficiary_id, 'UPDATE', $before, $ben->fresh()->toArray());
        } else {
            $ben = Beneficiary::create([
                'beneficiary_id'          => Str::uuid()->toString(),
                'agent_id'                => $agent->agent_id,
                'full_name'               => $data['full_name'],
                'nric_encrypted'          => encrypt($data['nric']),
                'relationship'            => $data['relationship'],
                'phone'                   => $data['phone'] ?? null,
                'email'                   => $data['email'] ?? null,
                'address'                 => $data['address'] ?? null,
                'bank_name'               => $data['bank_name'] ?? null,
                'bank_account_encrypted'  => isset($data['bank_account']) ? encrypt($data['bank_account']) : null,
                'priority_order'          => $data['priority_order'] ?? 1,
                'is_active'               => true,
                'created_by'              => auth('agent')->id(),
            ]);

            AuditService::logChange('beneficiaries', $ben->beneficiary_id, 'CREATE', null, $ben->toArray());
        }

        return $ben;
    }

    /**
     * Trigger beneficiary takeover when agent status = RESIGNED or DECEASED.
     * Transfers: hierarchy position, commission balance, reward points.
     */
    public function triggerTakeover(Agent $originalAgent, string $adminId, string $notes = ''): void
    {
        // Find highest priority active beneficiary
        $beneficiary = Beneficiary::where('agent_id', $originalAgent->agent_id)
                                  ->where('is_active', true)
                                  ->where('takeover_triggered', false)
                                  ->orderBy('priority_order')
                                  ->first();

        if (! $beneficiary) {
            return; // No beneficiary configured — skip
        }

        DB::transaction(function () use ($originalAgent, $beneficiary, $adminId, $notes) {

            // 1. Create a new agent account for the beneficiary
            $newAgentId = Str::uuid()->toString();

            DB::table('agents')->insert([
                'agent_id'               => $newAgentId,
                'member_code'            => $originalAgent->member_code,  // Inherit same code
                'agent_code'             => $originalAgent->agent_code,
                'full_name'              => $beneficiary->full_name,
                'email'                  => $beneficiary->email ?? 'beneficiary_' . $newAgentId . '@pending.gl',
                'password_hash'          => bcrypt(Str::random(32)), // Temp password — reset via email
                'nric_encrypted'         => $beneficiary->nric_encrypted,
                'phone'                  => $beneficiary->phone ?? $originalAgent->phone,
                'role'                   => $originalAgent->role,
                'status'                 => 'ACTIVE',
                'parent_id'              => $originalAgent->parent_id,
                'hierarchy_path'         => $originalAgent->hierarchy_path,
                'group_id'               => $originalAgent->group_id,
                'recruitable_tier_depth' => $originalAgent->recruitable_tier_depth,
                'recruitment_blocked'    => $originalAgent->recruitment_blocked,
                'qr_code_token'          => Str::random(40),
                'bank_name'              => $beneficiary->bank_name,
                'bank_account_encrypted' => $beneficiary->bank_account_encrypted,
                'commission_balance'     => $originalAgent->commission_balance,
                'email_verified_at'      => null, // Must verify
                'security_phrase_set'    => false,
                'created_by'             => $adminId,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            // 2. Reassign all downlines to new agent
            DB::table('agents')
              ->where('parent_id', $originalAgent->agent_id)
              ->update(['parent_id' => $newAgentId, 'updated_at' => now()]);

            // 3. Transfer commission wallet — add ledger entry
            DB::table('reward_points_ledger')->insert([
                'ledger_id'   => Str::uuid()->toString(),
                'agent_id'    => $newAgentId,
                'txn_type'    => 'TRANSFERRED',
                'points_in'   => 0,
                'points_out'  => 0,
                'running_balance' => 0,
                'notes'       => 'Beneficiary takeover from ' . $originalAgent->full_name,
                'created_by'  => $adminId,
                'created_at'  => now(),
            ]);

            // 4. Transfer reward points balance — create TRANSFERRED ledger entries
            $pointsBalance = DB::table('reward_points_ledger')
                ->where('agent_id', $originalAgent->agent_id)
                ->selectRaw('COALESCE(SUM(points_in) - SUM(points_out), 0) as balance')
                ->value('balance') ?? 0;

            if ($pointsBalance > 0) {
                // Debit original
                DB::table('reward_points_ledger')->insert([
                    'ledger_id'   => Str::uuid()->toString(),
                    'agent_id'    => $originalAgent->agent_id,
                    'txn_type'    => 'TRANSFERRED',
                    'points_in'   => 0,
                    'points_out'  => $pointsBalance,
                    'running_balance' => 0,
                    'notes'       => 'Beneficiary takeover — transferred to ' . $beneficiary->full_name,
                    'created_by'  => $adminId,
                    'created_at'  => now(),
                ]);
                // Credit new agent
                DB::table('reward_points_ledger')->insert([
                    'ledger_id'   => Str::uuid()->toString(),
                    'agent_id'    => $newAgentId,
                    'txn_type'    => 'TRANSFERRED',
                    'points_in'   => $pointsBalance,
                    'points_out'  => 0,
                    'running_balance' => $pointsBalance,
                    'notes'       => 'Beneficiary takeover — received from ' . $originalAgent->full_name,
                    'created_by'  => $adminId,
                    'created_at'  => now(),
                ]);
            }

            // 5. Mark beneficiary as triggered
            $beneficiary->update([
                'takeover_triggered' => true,
                'takeover_at'        => now(),
                'takeover_by'        => $adminId,
                'takeover_notes'     => $notes,
            ]);

            // 6. Audit log
            AuditService::logChange('agents', $originalAgent->agent_id, 'TAKEOVER', null, [
                'original_agent_id' => $originalAgent->agent_id,
                'new_agent_id'      => $newAgentId,
                'beneficiary_id'    => $beneficiary->beneficiary_id,
                'points_transferred'=> $pointsBalance,
                'commission_transferred' => $originalAgent->commission_balance,
            ]);
        });
    }
}
