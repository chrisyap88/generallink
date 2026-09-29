<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovalService
{
    // -------------------------------------------------------
    // Any Admin can REQUEST a sensitive action — this just creates a
    // PENDING record and notifies every OTHER active Admin (flexible
    // pool, confirmed decision — not one fixed mandatory approver).
    // Nothing actually happens to real data until a DIFFERENT Admin
    // approves it.
    // -------------------------------------------------------
    public function requestApproval(
        string $actionType,
        ?string $targetAgentId,
        array $payload,
        string $requestedBy,
        ?string $reasonCodeId,
        ?string $notes
    ): string {
        $approvalId = (string) Str::uuid();

        DB::table('pending_approvals')->insert([
            'approval_id'            => $approvalId,
            'action_type'            => $actionType,
            'target_agent_id'        => $targetAgentId,
            'payload'                => json_encode($payload),
            'requested_by'           => $requestedBy,
            'request_reason_code_id' => $reasonCodeId,
            'request_notes'          => $notes,
            'status'                 => 'PENDING',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $requester = Agent::find($requestedBy);
        $eligibleApprovers = $this->getEligibleApprovers($requestedBy, $actionType);

        $reasonDesc = $reasonCodeId ? DB::table('reason_codes')->where('reason_code_id', $reasonCodeId)->value('description') : null;

        app(NotificationService::class)->notify(
            $eligibleApprovers,
            'APPROVAL_NEEDED',
            'Approval Needed',
            "{$requester->full_name} has requested: {$this->actionLabel($actionType)}. This needs a DIFFERENT Admin's approval before it takes effect." . ($reasonDesc ? " Reason: {$reasonDesc}." : '') . ($notes ? " Notes: {$notes}" : ''),
            $targetAgentId,
            $reasonCodeId,
            $notes
        );

        return $approvalId;
    }

    // Which department's admins are the natural approvers for this
    // action type. Admin Director can ALWAYS approve anything
    // (universal fallback), regardless of this mapping.
    private function departmentForAction(string $actionType): ?string
    {
        return match ($actionType) {
            'WITHDRAWAL_APPROVAL', 'COMMISSION_STRUCTURE_CHANGE', 'UNMATCHED_CLAIM',
            'RELEASE_HELD_COMMISSION', 'UNCLAIMED_COMMISSION',
            // NEW 2 Aug 2026 — Vendor Override Member claims are real
            // money owed to a vendor-side person, same category as
            // Withdrawal Approval.
            'OVERRIDE_CLAIM_APPROVAL' => 'FINANCE',

            'UNDO_ROLE_CHANGE', 'ADMIN_ASSIGN_PLACEMENT', 'MANUAL_HIERARCHY_CORRECTION',
            'GROUP_MERGE_SPLIT', 'STATUS_CHANGE' => 'SALES',

            default => null, // unmapped — any department admin can approve
        };
    }

    // Flexible pool, now routed by department — any DIFFERENT Admin
    // whose department matches the action's category, PLUS Admin
    // Director always (universal fallback, per confirmed decision).
    public function getEligibleApprovers(string $requestedBy, ?string $actionType = null): array
    {
        $requiredDept = $actionType ? $this->departmentForAction($actionType) : null;

        return Agent::where('role', 'ADMIN')
            ->where('is_deleted', false)
            ->where('agent_id', '!=', $requestedBy)
            ->where(function ($query) use ($requiredDept) {
                $query->where('department', 'DIRECTOR');
                if ($requiredDept) {
                    $query->orWhere('department', $requiredDept);
                } else {
                    $query->orWhereNotNull('department'); // unmapped action — any department admin
                }
            })
            ->get()
            ->all();
    }

    // -------------------------------------------------------
    // APPROVE — must be a DIFFERENT Admin than whoever requested it.
    // Only on approval does the actual sensitive action get executed.
    // -------------------------------------------------------
    // Action types requiring TRUE 2-stage approval (2 DIFFERENT admins,
    // not just any one) — per confirmed decision (06 Jul 2026), since
    // real money is involved in withdrawals.
    private function requiresTwoStage(string $actionType): bool
    {
        return in_array($actionType, ['WITHDRAWAL_APPROVAL']);
    }

    public function approve(string $approvalId, string $approvedBy, ?string $approvalNotes = null): array
    {
        $approval = DB::table('pending_approvals')->where('approval_id', $approvalId)->first();

        if (!$approval) {
            return ['success' => false, 'message' => 'Request not found.'];
        }
        if ($approval->status !== 'PENDING') {
            return ['success' => false, 'message' => 'This request has already been decided.'];
        }
        if ($approval->requested_by === $approvedBy) {
            return ['success' => false, 'message' => 'You cannot approve your own request — a different Admin must approve it (4-eye policy).'];
        }

        $approver = Agent::find($approvedBy);
        $requiredDept = $this->departmentForAction($approval->action_type);
        if ($requiredDept && $approver->department !== $requiredDept && $approver->department !== 'DIRECTOR') {
            return ['success' => false, 'message' => "Only Admin " . ucfirst(strtolower($requiredDept)) . " or Admin Director can approve this."];
        }

        $needsStage2 = $this->requiresTwoStage($approval->action_type) && $approval->stage == 1;

        DB::table('pending_approvals')->where('approval_id', $approvalId)->update([
            'status'         => $needsStage2 ? 'STAGE_1_APPROVED' : 'APPROVED',
            'approved_by'    => $approvedBy,
            'approval_notes' => $approvalNotes,
            'decided_at'     => now(),
            'updated_at'     => now(),
        ]);

        if ($needsStage2) {
            // Create Stage 2 — same request, but 'requested_by' is now
            // the Stage 1 approver, so the EXISTING self-approval check
            // above automatically blocks them from also doing Stage 2.
            // No new logic needed for that — just reusing what's
            // already correct.
            $stage2Id = (string) Str::uuid();
            DB::table('pending_approvals')->insert([
                'approval_id'            => $stage2Id,
                'action_type'            => $approval->action_type,
                'target_agent_id'        => $approval->target_agent_id,
                'payload'                => $approval->payload,
                'requested_by'           => $approvedBy, // Stage 1 approver
                'request_reason_code_id' => $approval->request_reason_code_id,
                'request_notes'          => "Stage 2 of 2 — following Stage 1 approval by {$approver->full_name}.",
                'status'                 => 'PENDING',
                'stage'                  => 2,
                'parent_approval_id'     => $approvalId,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            $stage2Approvers = $this->getEligibleApprovers($approvedBy, $approval->action_type);
            app(NotificationService::class)->notify(
                $stage2Approvers,
                'APPROVAL_NEEDED',
                'Second Approval Needed',
                "{$approver->full_name} gave Stage 1 approval for: {$this->actionLabel($approval->action_type)}. A DIFFERENT Admin must now give Stage 2 approval before this takes effect."
            );

            return ['success' => true, 'message' => 'Stage 1 approved — waiting for a second, different Admin to give Stage 2 approval.'];
        }

        // Stage 2 (or a single-stage action) — actually execute now.
        $this->executeAction($approval, $approvedBy);

        $requester = Agent::find($approval->requested_by);
        if ($requester) {
            app(NotificationService::class)->notify(
                [$requester],
                'APPROVAL_GRANTED',
                'Request Approved',
                "Your request ({$this->actionLabel($approval->action_type)}) was approved by {$approver->full_name} and has been carried out."
            );
        }

        return ['success' => true, 'message' => 'Approved and executed.'];
    }

    public function reject(string $approvalId, string $approvedBy, string $rejectionReason): array
    {
        $approval = DB::table('pending_approvals')->where('approval_id', $approvalId)->first();

        if (!$approval) {
            return ['success' => false, 'message' => 'Request not found.'];
        }
        if ($approval->status !== 'PENDING') {
            return ['success' => false, 'message' => 'This request has already been decided.'];
        }
        if ($approval->requested_by === $approvedBy) {
            return ['success' => false, 'message' => 'You cannot reject your own request.'];
        }

        $rejector = Agent::find($approvedBy);
        $requiredDeptForReject = $this->departmentForAction($approval->action_type);
        if ($requiredDeptForReject && $rejector->department !== $requiredDeptForReject && $rejector->department !== 'DIRECTOR') {
            return ['success' => false, 'message' => "Only Admin " . ucfirst(strtolower($requiredDeptForReject)) . " or Admin Director can act on this."];
        }

        DB::table('pending_approvals')->where('approval_id', $approvalId)->update([
            'status'         => 'REJECTED',
            'approved_by'    => $approvedBy,
            'approval_notes' => $rejectionReason,
            'decided_at'     => now(),
            'updated_at'     => now(),
        ]);

        // NEW 2 Aug 2026 — per-action-type side effects on rejection
        // (currently just Override Claim, which needs its own row put
        // back to REJECTED so it stops showing as "awaiting approval",
        // PLUS a reversing Credit ledger entry — the Debit was already
        // posted at submit time ("debit means submitted", per Chris),
        // so a rejection must reverse it or the ledger balance would
        // permanently overstate what's actually owed).
        if ($approval->action_type === 'OVERRIDE_CLAIM_APPROVAL') {
            $payload = json_decode($approval->payload, true);
            $claim = DB::table('override_commission_claims')->where('claim_id', $payload['claim_id'])->first();

            DB::table('override_commission_claims')->where('claim_id', $payload['claim_id'])->update([
                'status'            => 'REJECTED',
                'rejection_reason'  => $rejectionReason,
                'updated_at'        => now(),
            ]);

            if ($claim) {
                app(OverrideLedgerService::class)->post(
                    $claim->override_member_id, $claim->claim_id, 'CREDIT',
                    'Reversal — claim rejected: ' . $rejectionReason,
                    (float) $claim->calculated_amount, null, $approvedBy
                );
            }
        }

        $requester = Agent::find($approval->requested_by);
        $approver = Agent::find($approvedBy);
        app(NotificationService::class)->notify(
            [$requester],
            'APPROVAL_REJECTED',
            'Request Rejected',
            "Your request ({$this->actionLabel($approval->action_type)}) was rejected by {$approver->full_name}. Reason: {$rejectionReason}"
        );

        return ['success' => true, 'message' => 'Rejected.'];
    }

    private function actionLabel(string $actionType): string
    {
        return match ($actionType) {
            'UNDO_ROLE_CHANGE'         => 'Undo Promotion/Demotion',
            'WITHDRAWAL_APPROVAL'      => 'Withdrawal Approval',
            'OVERRIDE_CLAIM_APPROVAL'  => 'Vendor Override Claim Approval',
            default                    => $actionType,
        };
    }

    // -------------------------------------------------------
    // Executes the actual sensitive action — ONLY ever called from
    // approve() above, never directly. New action types get a new case
    // here later (Admin-Assign placement, Bank Account changes, Vendor
    // deactivation, etc.) — no schema changes needed to add more.
    // -------------------------------------------------------
    private function executeAction(object $approval, string $approvedBy): void
    {
        $payload = json_decode($approval->payload, true);

        match ($approval->action_type) {
            'UNDO_ROLE_CHANGE' => $this->executeUndoRoleChange($approval, $payload, $approvedBy),
            'STATUS_CHANGE' => $this->executeStatusChange($approval, $payload, $approvedBy),
            'WITHDRAWAL_APPROVAL' => $this->executeWithdrawalApproval($approval, $payload, $approvedBy),
            'OVERRIDE_CLAIM_APPROVAL' => $this->executeOverrideClaimApproval($approval, $payload, $approvedBy),
            default => null,
        };
    }

    private function executeUndoRoleChange(object $approval, array $payload, string $approvedBy): void
    {
        $historyEntry = DB::table('role_history')->where('history_id', $payload['role_history_id'])->first();
        if (!$historyEntry) {
            return;
        }

        $agent = Agent::find($approval->target_agent_id);
        if (!$agent) {
            return;
        }

        // Restore the exact "before" state that was saved at promotion
        // time. Note: group_id is intentionally NOT touched here — it's
        // a permanent ancestor-lineage identity that never changes
        // through any promotion or its reversal, per confirmed business
        // rule (old_group_id and new_group_id in role_history are always
        // identical for role changes; group_id only changes if someone
        // is deliberately reassigned to a different lineage entirely,
        // which is a separate action, not part of promotion/demotion).
        $agent->update([
            'role'      => $historyEntry->old_role,
            'parent_id' => $historyEntry->old_parent_id,
        ]);

        // Record the undo itself as its own permanent role_history entry
        // — never delete or overwrite the original record.
        DB::table('role_history')->insert([
            'history_id'     => (string) Str::uuid(),
            'agent_id'       => $agent->agent_id,
            'old_role'       => $historyEntry->new_role,
            'new_role'       => $historyEntry->old_role,
            'old_parent_id'  => $historyEntry->new_parent_id,
            'new_parent_id'  => $historyEntry->old_parent_id,
            'old_group_id'   => $agent->group_id,
            'new_group_id'   => $agent->group_id,
            'effective_date' => now(),
            'reason'         => 'ADMIN_UNDO',
            'performed_by'   => $approvedBy,
            'created_at'     => now(),
        ]);

        AuditService::logChange('agents', $agent->agent_id, 'ROLE_CHANGE', ['role' => $historyEntry->new_role], ['role' => $historyEntry->old_role]);

        $sponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $recipients = app(NotificationService::class)->recipientsForGroupBroadcast($agent, $sponsor);
        app(NotificationService::class)->notify(
            $recipients,
            'ROLE_CHANGE_UNDONE',
            'Role Change Reversed',
            "{$agent->full_name} ({$agent->agent_code})'s role was reverted from {$historyEntry->new_role} back to {$historyEntry->old_role}.",
            $agent->agent_id
        );
    }

    private function executeStatusChange(object $approval, array $payload, string $approvedBy): void
    {
        $agent = Agent::find($approval->target_agent_id);
        if (!$agent) {
            return;
        }

        $newStatus = $payload['new_status'];

        // Re-check the downline-block rule at execution time too — the
        // situation may have changed between request and approval.
        if ($newStatus === 'INACTIVE') {
            $activeDownlineCount = Agent::where('parent_id', $agent->agent_id)
                ->where('status', 'ACTIVE')
                ->where('is_deleted', false)
                ->count();
            if ($activeDownlineCount > 0) {
                DB::table('pending_approvals')->where('approval_id', $approval->approval_id)->update([
                    'status' => 'REJECTED',
                    'approval_notes' => "Auto-rejected on approval: this person now has {$activeDownlineCount} active downline record(s).",
                ]);
                return;
            }
        }

        $before = $agent->status;
        $agent->update(['status' => $newStatus]);

        AuditService::logChange('agents', $agent->agent_id, 'STATUS_CHANGE', ['status' => $before], ['status' => $newStatus]);

        if ($approval->request_reason_code_id || $approval->request_notes) {
            DB::table('audit_logs')->where('table_name', 'agents')
                ->where('record_id', $agent->agent_id)
                ->where('action', 'STATUS_CHANGE')
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'reason_code_id' => $approval->request_reason_code_id,
                    'reason_notes'   => $approval->request_notes,
                ]);
        }

        $sponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $recipients = app(NotificationService::class)->recipientsForGroupBroadcast($agent, $sponsor);
        app(NotificationService::class)->notify(
            $recipients,
            'STATUS_CHANGE',
            'Status Changed',
            "{$agent->full_name} ({$agent->agent_code})'s status was changed to {$newStatus} (approved).",
            $agent->agent_id,
            $approval->request_reason_code_id,
            $approval->request_notes
        );
    }

    // NEW 2 Aug 2026 — Vendor Override Member claim approval. Only
    // flips the claim from SUBMITTED to APPROVED and records who/when
    // — actually marking it PAID is a separate, non-approval step
    // (Admin does that once the vendor settlement is confirmed), same
    // separation as WITHDRAWAL_APPROVAL only approving, not paying.
    private function executeOverrideClaimApproval(object $approval, array $payload, string $approvedBy): void
    {
        $claim = DB::table('override_commission_claims')->where('claim_id', $payload['claim_id'])->first();
        if (!$claim) {
            return;
        }

        DB::table('override_commission_claims')->where('claim_id', $payload['claim_id'])->update([
            'status'      => 'APPROVED',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'updated_at'  => now(),
        ]);

        $member = DB::table('override_members')->where('override_member_id', $claim->override_member_id)->first();
        AuditService::logChange('override_commission_claims', $claim->claim_id, 'OVERRIDE_CLAIM_APPROVED', ['status' => $claim->status], ['status' => 'APPROVED']);

        $approver = Agent::find($approvedBy);
        $requester = Agent::find($approval->requested_by);
        if ($requester && $member) {
            app(NotificationService::class)->notify(
                [$requester],
                'APPROVAL_GRANTED',
                'Override Claim Approved',
                "The override claim for {$member->full_name} ({$member->override_member_code}) — RM " . number_format($claim->calculated_amount, 2) . " — was approved by {$approver->full_name}. It's now ready to be marked Paid."
            );
        }
    }

    private function executeWithdrawalApproval(object $approval, array $payload, string $approvedBy): void
    {
        $requestId = $payload['withdrawal_request_id'];
        $withdrawal = DB::table('withdrawal_requests')->where('request_id', $requestId)->first();
        if (!$withdrawal) {
            return;
        }

        DB::table('withdrawal_requests')->where('request_id', $requestId)->update([
            'status'        => 'APPROVED',
            'approved_by'   => $approvedBy,
            'approved_date' => now(),
            'updated_at'    => now(),
        ]);

        AuditService::logChange('withdrawal_requests', $requestId, 'WITHDRAWAL_APPROVED', ['status' => 'PENDING'], ['status' => 'APPROVED']);

        $agent = Agent::find($approval->target_agent_id);
        $approver = Agent::find($approvedBy);
        if ($agent) {
            $approvedAt = now()->format('d M Y, h:i A');
            app(NotificationService::class)->notify(
                [$agent],
                'WITHDRAWAL_APPROVED',
                'Withdrawal Approved',
                "Dear {$agent->full_name},\n\nWe are pleased to inform you that your withdrawal request has been approved.\n\nReference Number: {$withdrawal->reference_number}\nAmount: RM " . number_format($withdrawal->withdrawal_amount, 2) . "\nApproved: {$approvedAt}\n\nPayment will be processed shortly, and we will notify you once it has been completed.\n\nThank you for your patience.\n\nKind Regards,\nGeneralLink Admin",
                $agent->agent_id
            );
        }
    }
}
