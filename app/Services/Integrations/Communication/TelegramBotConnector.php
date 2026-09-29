<?php

namespace App\Services\Integrations\Communication;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 8 Aug 2026 (Task #85) — verifies a Telegram bot via getMe. Actually
// sending to a specific agent needs their Telegram numeric chat_id (NOT
// their phone number — Telegram bots cannot message a phone number
// directly, only a chat_id obtained after that person starts a chat with
// the bot). Until agents have somewhere to link their chat_id, sendMessage()
// exists for completeness/future use but NoticeDeliveryService does not
// call it yet — it marks Telegram as SKIPPED with an honest reason.
class TelegramBotConnector implements IntegrationConnectorInterface
{
    public function testConnection(array $credentials): array
    {
        $token = trim($credentials['api_key'] ?? '');
        if ($token === '') {
            return ['success' => false, 'message' => 'Paste your Bot Token first, then click Test Connection.'];
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful() && $response->json('ok')) {
                $username = $response->json('result.username');
                return ['success' => true, 'message' => 'Connected — bot @' . ($username ?: 'unknown') . ' is ready.'];
            }

            if ($response->status() === 401 || $response->status() === 404) {
                return ['success' => false, 'message' => 'Telegram rejected this token — double-check you copied the full Bot Token from @BotFather (Telegram app > search @BotFather > /mybots > your bot > API Token).'];
            }
            return ['success' => false, 'message' => 'Telegram returned an unexpected response (HTTP ' . $response->status() . '). Double-check the token was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Telegram — check your internet connection and try again.'];
        }
    }

    public function sendMessage(array $credentials, string $chatId, string $message): array
    {
        $token = trim($credentials['api_key'] ?? '');
        if ($token === '' || $chatId === '') {
            return ['success' => false, 'message' => 'Bot Token or recipient chat_id missing.'];
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);
            if ($response->successful() && $response->json('ok')) {
                return ['success' => true, 'message' => 'Sent.'];
            }
            return ['success' => false, 'message' => $response->json('description') ?? ('Telegram HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Telegram.'];
        }
    }
}
