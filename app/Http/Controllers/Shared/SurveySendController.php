<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// REPLACED 25 Jul 2026 — Survey Management Module, Phase 2 (task #232).
// The agent-facing side of distribution: any Admin/GL/TL/Introducer can
// send an ACTIVE survey to their own customers. Admin\SurveyController
// covers the generic public link/QR/embed; this covers *targeted*,
// tracked sends — one survey_invitations row per customer picked, so
// Response Management (Phase 2) and Analytics (Phase 3) can tell which
// customer a response came from, or that an invited customer never
// responded at all.
//
// Real send vs share-link, per channel (same reasoning as My Referral
// Link and Broadcast Campaigns):
//   - EMAIL: sent for real right now via Mail::raw (same mechanism
//     NotificationService already uses) — no external provider needed.
//   - WHATSAPP / TELEGRAM / SMS: no paid provider API is subscribed
//     yet (see Channel Connections), so these open the agent's own
//     WhatsApp/Telegram/SMS app with the personalized link pre-filled,
//     exactly like My Referral Link's share buttons — it works today,
//     the agent just taps Send on their own device.
class SurveySendController extends Controller
{
    public function create(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);

        $surveys = DB::table('surveys')->where('status', 'ACTIVE')->where('is_deleted', false)->orderBy('name')->get();

        $selectedSurveyId = $request->get('survey_id') ?: ($surveys->first()->survey_id ?? null);

        $recentSends = collect();
        if ($selectedSurveyId) {
            $recentSends = DB::table('survey_invitations as i')
                ->where('i.survey_id', $selectedSurveyId)
                ->where('i.sent_by_agent_id', $agent->agent_id)
                ->orderByDesc('i.created_at')
                ->limit(15)
                ->get();
        }

        return view('growth.survey-send', compact('agent', 'rolePrefix', 'surveys', 'selectedSurveyId', 'recentSends'));
    }

    // Same role -> route-prefix mapping used by CustomerKpiController /
    // RenewalForecastController — needed here so the customer typeahead
    // JS can hit the correctly-scoped {rolePrefix}.customers.typeahead
    // endpoint for whichever role is logged in.
    private function rolePrefix($agent): string
    {
        return match ($agent->role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }

    // Logs one survey_invitations row per selected customer and returns
    // to the same screen with each customer's personalized link ready
    // to copy/share. EMAIL is additionally sent for real immediately.
    public function store(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        $data = $request->validate([
            'survey_id'      => ['required', 'exists:surveys,survey_id'],
            'customer_ids'   => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['exists:customers,customer_id'],
            'send_email_now' => ['nullable'],
        ]);

        $survey = DB::table('surveys')->where('survey_id', $data['survey_id'])->where('status', 'ACTIVE')->first();
        abort_unless($survey, 404, 'This survey is not Active — it cannot be distributed right now.');

        $customers = DB::table('customers')->whereIn('customer_id', $data['customer_ids'])->where('is_deleted', false)->get();
        $sendEmailNow = !empty($data['send_email_now']);

        $generated = [];
        $emailsSent = 0;

        foreach ($customers as $customer) {
            $invitationId = (string) Str::uuid();
            $channel = ($sendEmailNow && $customer->email) ? 'EMAIL' : 'LINK';

            DB::table('survey_invitations')->insert([
                'invitation_id'        => $invitationId,
                'survey_id'            => $survey->survey_id,
                'channel'              => $channel,
                'recipient_identifier' => $channel === 'EMAIL' ? $customer->email : ($customer->phone ?: $customer->email),
                'sent_by_agent_id'     => $agent->agent_id,
                'sent_at'              => $channel === 'EMAIL' ? now() : null,
                'status'               => $channel === 'EMAIL' ? 'SENT' : 'PENDING',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $personalUrl = url('/survey/' . $survey->public_token) . '?inv=' . $invitationId;

            if ($channel === 'EMAIL') {
                try {
                    Mail::raw(
                        "Hi {$customer->full_name},\n\n{$agent->full_name} would appreciate your feedback: \"{$survey->title}\"\n\nPlease take a moment to answer here:\n{$personalUrl}\n\nThank you!",
                        function ($mail) use ($customer, $survey) {
                            $mail->to($customer->email)->subject('We would love your feedback — ' . $survey->title);
                        }
                    );
                    $emailsSent++;
                } catch (\Throwable $e) {
                    // Swallow — the invitation row still exists as PENDING
                    // context for the agent, and the summary below simply
                    // won't count this one as delivered.
                }
            }

            $generated[] = [
                'customer_id'   => $customer->customer_id,
                'name'          => $customer->full_name,
                'phone'         => $customer->phone,
                'email'         => $customer->email,
                'channel'       => $channel,
                'url'           => $personalUrl,
                'survey_title'  => $survey->title,
            ];
        }

        return redirect()->route('survey-send.create', ['survey_id' => $survey->survey_id])
            ->with('generatedLinks', $generated)
            ->with('success', count($generated) . ' link(s) generated' . ($emailsSent ? ", {$emailsSent} email(s) sent." : '.'));
    }
}
