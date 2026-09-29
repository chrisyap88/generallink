<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use App\Services\HubVaultService;
use App\Services\LanguageService;
use App\Services\ProactiveAlertService;
use App\Services\Tts\TtsProviderResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// -------------------------------------------------------
// NEW 3 Aug 2026 — one endpoint handles BOTH the guest (login page)
// and the logged-in (every screen, all 4 roles) AI Assistant widget.
// Which mode applies is detected from whether an agent session exists
// — the widget itself doesn't need to know or declare it.
// -------------------------------------------------------
class AiAssistantController extends Controller
{
    public function chat(Request $request, AiAssistantService $service, LanguageService $languageService, TtsProviderResolver $ttsResolver, HubVaultService $vault)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
            'page_context' => 'nullable|string|max:150',
        ]);

        $agent = Auth::guard('agent')->user();

        if ($agent) {
            $context = [
                'mode' => 'agent',
                'agent_id' => $agent->agent_id,
                'role' => $agent->role,
                'department' => $agent->department ?? null,
                'full_name' => $agent->full_name,
                'language' => $languageService->effectiveLanguage($agent),
                'page_context' => $request->input('page_context'),
                // NEW 5 Aug 2026 — lets Carolyn recognize a join
                // anniversary (see AiAssistantService::anniversaryLine).
                // Uses the agent's existing created_at — no new data
                // collected for this.
                'joined_at' => $agent->created_at,
            ];
        } else {
            $context = [
                'mode' => 'guest',
                'agent_id' => null,
                'role' => null,
                'department' => null,
                'full_name' => 'Guest',
                'language' => 'EN',
                // CHANGED 9 Aug 2026 — was hardcoded to "Login Page" no
                // matter which guest screen the widget was actually on,
                // so Carolyn could never tell a vendor filling in the
                // registration form apart from someone on the plain
                // sign-in page. Now trusts the label the widget sent
                // (partials.ai-assistant-widget's $guestPageLabel), same
                // "Login Page" default if none was sent.
                'page_context' => $request->input('page_context') ?: 'Login Page',
            ];
        }

        // History comes back from the previous turn exactly as this
        // endpoint returned it — trust it's already in the Anthropic
        // messages shape, just cap its size defensively.
        $history = array_slice($request->input('history', []), -20);

        // REWIRED 6 Aug 2026 — per Chris: an agent should never paste the
        // same key twice. The Anthropic key for text chat now comes ONLY
        // from the Integration Hub vault (agents.text_chat_provider picks
        // WHICH connected provider; agent_integrations + HubVaultService
        // holds the actual key) — never from a separate Profile field.
        // No free fallback exists for text (unlike voice), so guests and
        // agents without their own connected key still use Chris's shared
        // .env key — that's an accepted, genuinely small cost, not a
        // silent leak. $setupNeeded collects anything Carolyn should
        // proactively mention (see AiAssistantService::buildSystemPrompt).
        $apiKeyOverride = null;
        $setupNeeded = [];

        if ($agent) {
            // Voice Assistant — read-only check here (speak() itself does
            // the real resolve at speak time); this just lets Carolyn
            // know to mention it.
            if (!empty($agent->voice_provider)) {
                $voiceResolved = $ttsResolver->resolve($agent);
                if ($voiceResolved['status'] !== 'READY') {
                    $setupNeeded[] = [
                        'area' => 'Voice Assistant',
                        'provider' => $voiceResolved['providerLabel'] ?? $agent->voice_provider,
                        'status' => $voiceResolved['status'],
                    ];
                }
            }

            // Text Chat
            if (!empty($agent->text_chat_provider)) {
                $providerKey = strtolower($agent->text_chat_provider); // 'anthropic'
                $row = DB::table('agent_integrations')
                    ->where('agent_id', $agent->agent_id)
                    ->where('category', 'ai_services')
                    ->where('provider', $providerKey)
                    ->first();

                if (!$row || $row->status !== 'CONNECTED') {
                    $setupNeeded[] = ['area' => 'Text Chat', 'provider' => 'Anthropic (Claude)', 'status' => 'NOT_CONNECTED'];
                } elseif (!$vault->isUnlocked($agent->agent_id)) {
                    $setupNeeded[] = ['area' => 'Text Chat', 'provider' => 'Anthropic (Claude)', 'status' => 'LOCKED'];
                } else {
                    try {
                        $apiKeyOverride = $vault->decryptSecret($agent->agent_id, $row->api_key_encrypted);
                    } catch (\Throwable $e) {
                        Log::warning('[Carolyn] Could not decrypt agent text-chat key from Hub vault — falling back to shared key.', ['agent_id' => $agent->agent_id]);
                        $setupNeeded[] = ['area' => 'Text Chat', 'provider' => 'Anthropic (Claude)', 'status' => 'UNREADABLE'];
                    }
                }
            }

            $context['setup_needed'] = $setupNeeded;
            $context['is_first_turn'] = empty($history);
        }

        $result = $service->chat($history, $request->input('message'), $context, $apiKeyOverride);

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'ERROR', 'message' => $result['message']], 200);
        }

        return response()->json([
            'status' => 'OK',
            'reply' => $result['reply'],
            'history' => $result['history'],
            'ticket_created' => $result['ticket_created'] ?? false,
            'ticket_code' => $result['ticket_code'] ?? null,
            // NEW 3 Aug 2026 — AI Guided Navigation hand-off signal. When
            // present, the widget starts a guided walkthrough via
            // /ai-assistant/guide/start instead of (or after) speaking
            // the reply — see ai-guidance-overlay.blade.php.
            'guidance_goal' => $result['guidance_goal'] ?? null,
        ]);
    }

    /**
     * NEW 5 Aug 2026 — powers the chat bubble's badge count AND the
     * once-a-day proactive popup (per Chris: Carolyn should alert an agent
     * to outstanding items on login, politely, not just answer questions
     * when asked). Guests never see this — badge stays hidden on the login
     * page.
     *
     * `should_popup` is deliberately consumed here (via a per-agent,
     * per-day cache flag) so the popup fires once per agent per calendar
     * day no matter how many screens they visit — the badge count itself
     * keeps refreshing every page load regardless.
     */
    public function alerts(ProactiveAlertService $alertService)
    {
        $agent = Auth::guard('agent')->user();
        if (!$agent) {
            return response()->json(['count' => 0, 'should_popup' => false, 'message' => null]);
        }

        $items = $alertService->items($agent->agent_id, $agent->role);
        $count = array_sum(array_column($items, 'count'));

        $shouldPopup = false;
        if ($count > 0) {
            $cacheKey = 'proactive_alert_shown:' . $agent->agent_id . ':' . now()->toDateString();
            if (!Cache::has($cacheKey)) {
                $shouldPopup = true;
                Cache::put($cacheKey, true, now()->endOfDay());
            }
        }

        return response()->json([
            'count' => $count,
            'should_popup' => $shouldPopup,
            'message' => $count > 0 ? $alertService->politeMessage($agent->agent_id, $agent->role, $agent->full_name) : null,
        ]);
    }
}
