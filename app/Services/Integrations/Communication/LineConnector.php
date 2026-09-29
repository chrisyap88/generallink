<?php

namespace App\Services\Integrations\Communication;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 8 Aug 2026 (Task #85) — verifies a LINE Official Account's Channel
// Access Token. Actually sending to a specific agent needs their LINE
// userId (NOT their phone number — same limitation as Telegram). Until
// agents have somewhere to link their LINE account, sendMessage() exists
// for completeness/future use but NoticeDeliveryService does not call it
// yet — it marks LINE as SKIPPED with an honest reason.
class LineConnector implements IntegrationConnectorInterface
{
    public function testConnection(array $credentials): array
    {
        $token = trim($credentials['api_key'] ?? '');
        if ($token === '') {
            return ['success' => false, 'message' => 'Paste your Channel Access Token first, then click Test Connection.'];
        }

        try {
            $response = Http::withToken($token)->timeout(10)->get('https://api.line.me/v2/bot/info');

            if ($response->successful()) {
                $name = $response->json('displayName');
                return ['success' => true, 'message' => 'Connected — LINE account ' . ($name ?: 'verified') . ' is ready.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'LINE rejected this token — double-check you copied the full Channel Access Token from the LINE Developers Console (your channel > Messaging API tab > Channel access token > Issue).'];
            }
            return ['success' => false, 'message' => 'LINE returned an unexpected response (HTTP ' . $response->status() . '). Double-check the token was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach LINE — check your internet connection and try again.'];
        }
    }

    public function sendMessage(array $credentials, string $lineUserId, string $message): array
    {
        $token = trim($credentials['api_key'] ?? '');
        if ($token === '' || $lineUserId === '') {
            return ['success' => false, 'message' => 'Channel Access Token or recipient LINE user ID missing.'];
        }

        try {
            $response = Http::withToken($token)->timeout(10)->post('https://api.line.me/v2/bot/message/push', [
                'to' => $lineUserId,
                'messages' => [['type' => 'text', 'text' => $message]],
            ]);
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Sent.'];
            }
            return ['success' => false, 'message' => $response->json('message') ?? ('LINE HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach LINE.'];
        }
    }
}
