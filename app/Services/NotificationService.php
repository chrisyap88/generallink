<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    // -------------------------------------------------------
    // Broad rule — used for new registration and promotion.
    //
    // DIRECT SELLING GROUP: direct sponsor + every Introducer in the group +
    // every Team Leader in the group + the Group Leader + Admin — the
    // whole group, per the business reasoning: visibility into group
    // activity keeps everyone motivated (a new person might be a friend
    // of an existing one).
    //
    // SPECIAL PRIVILEGE GROUP (e.g. PVATM): narrower — only the direct
    // sponsor (their TL) + the Group Leader + Admin. Special groups are
    // organizations where members are individual customers with no
    // relationship to each other, unlike a Direct Selling Group's tighter-knit
    // team. Broadcasting every new Introducer to the whole group would
    // spam unrelated members and risk being reported as spam. Confirmed
    // decision (15 Jul 2026).
    // -------------------------------------------------------
    public function recipientsForGroupBroadcast(Agent $subject, ?Agent $directSponsor): array
    {
        $recipients = collect();

        if ($directSponsor) {
            $recipients->push($directSponsor);
        }

        $isSpecialGroup = false;
        if ($subject->group_label_id) {
            $isSpecialGroup = DB::table('group_labels')
                ->where('group_label_id', $subject->group_label_id)
                ->where('promotion_demotion_enabled', false)
                ->exists();
        }

        if ($isSpecialGroup) {
            // Narrow: direct sponsor (already added above) + the Group
            // Leader only — never the wider group.
            if ($subject->group_id) {
                $gl = Agent::where('group_id', $subject->group_id)
                    ->where('role', 'GROUP_LEADER')
                    ->where('is_deleted', false)
                    ->first();
                if ($gl) {
                    $recipients->push($gl);
                }
            }
        } elseif ($subject->group_id) {
            // Broad: whole group, as before.
            $groupMembers = Agent::where('group_id', $subject->group_id)
                ->whereIn('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
                ->where('is_deleted', false)
                ->get();
            $recipients = $recipients->merge($groupMembers);
        }

        $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
        $recipients = $recipients->merge($admins);

        // De-duplicate by agent_id (e.g. sponsor might already be in the
        // group list) and never notify the subject about themselves.
        return $recipients
            ->unique('agent_id')
            ->reject(fn ($a) => $a->agent_id === $subject->agent_id)
            ->values()
            ->all();
    }

    // -------------------------------------------------------
    // Upline-chain rule — used for Status changes (e.g. set to
    // Inactive): notify every person in the direct reporting chain
    // above the subject, up to and including their Group Leader, plus
    // Admin. NOT the whole lateral group — just the chain of command.
    // -------------------------------------------------------
    public function recipientsForUplineChain(Agent $subject): array
    {
        $recipients = collect();
        $current = $subject;

        while ($current->parent_id) {
            $parent = Agent::find($current->parent_id);
            if (!$parent) {
                break;
            }
            $recipients->push($parent);
            if ($parent->role === 'GROUP_LEADER') {
                break; // chain stops once it reaches the Group Leader
            }
            $current = $parent;
        }

        $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();

        return $recipients
            ->merge($admins)
            ->unique('agent_id')
            ->reject(fn ($a) => $a->agent_id === $subject->agent_id)
            ->values()
            ->all();
    }

    // -------------------------------------------------------
    // Narrow rule — used for demotion: only the previous leader + Admin,
    // NOT a group-wide broadcast (per explicit decision).
    // -------------------------------------------------------
    public function recipientsForDemotion(Agent $previousLeader): array
    {
        $recipients = collect([$previousLeader]);
        $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
        return $recipients->merge($admins)->unique('agent_id')->values()->all();
    }

    // -------------------------------------------------------
    // Creates a bell notification for each recipient + sends an email
    // to each. Reason code/notes are optional (used for demotion).
    // -------------------------------------------------------
    public function notify(array $recipients, string $type, string $title, string $message, ?string $relatedAgentId = null, ?string $reasonCodeId = null, ?string $reasonNotes = null): void
    {
        $reasonLine = '';
        if ($reasonCodeId) {
            $reason = DB::table('reason_codes')->where('reason_code_id', $reasonCodeId)->value('description');
            $reasonLine = "\n\nReason: " . ($reason ?? '—');
            if ($reasonNotes) {
                $reasonLine .= "\nAdditional Notes: {$reasonNotes}";
            }
        }

        foreach ($recipients as $agent) {
            DB::table('notifications')->insert([
                'notification_id'    => (string) Str::uuid(),
                'recipient_agent_id' => $agent->agent_id,
                'type'                => $type,
                'title'               => $title,
                'message'             => $message . $reasonLine,
                'related_agent_id'    => $relatedAgentId,
                'reason_code_id'      => $reasonCodeId,
                'reason_notes'        => $reasonNotes,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // NEW 2 Aug 2026 — per Chris: submitting an Override Claim
            // crashed the whole page with a 500 because the local mail
            // server (127.0.0.1:1025) wasn't running. The bell
            // notification above already got saved to the database
            // before this point — email is a "nice to have" on top of
            // that, so a broken/offline mail server must never block
            // the real action (claim submitted, approval requested,
            // etc). Failures are now caught and just written to the
            // log instead of blowing up the request.
            if ($agent->email) {
                try {
                    Mail::raw(
                        "Hi {$agent->full_name},\n\n{$title}\n\n{$message}{$reasonLine}",
                        function ($mail) use ($agent, $title) {
                            $mail->to($agent->email)->subject("GeneralLink — {$title}");
                        }
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Email notification failed (bell notification still saved): ' . $e->getMessage());
                }
            }
        }
    }
}