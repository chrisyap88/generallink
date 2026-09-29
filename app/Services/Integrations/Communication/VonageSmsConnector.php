<?php

namespace App\Services\Integrations\Communication;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 8 Aug 2026 (Task #85) — real SMS sending via Vonage, using an
// agent's actual phone number. Unlike Twilio, Vonage lets you send from
// an alphanumeric sender name (no need to buy/look up a phone number in
// most countries) — defaults to "GeneralLink" as the sender ID shown to
// the recipient.
class VonageSmsConnector implements IntegrationConnectorInterface
{
    public function testConnection(array $credentials): array
    {
        $apiKey = trim($credentials['client_id'] ?? '');
        $apiSecret = trim($credentials['client_secret'] ?? '');
        if ($apiKey === '' || $apiSecret === '') {
            return ['success' => false, 'message' => 'Paste both your API Key and API Secret first, then click Test Connection.'];
        }

        try {
            $response = Http::timeout(10)->get('https://rest.nexmo.com/account/get-balance', [
                'api_key' => $apiKey, 'api_secret' => $apiSecret,
            ]);

            if ($response->successful() && $response->json('value') !== null) {
                $balance = $response->json('value');
                return ['success' => true, 'message' => 'Connected — Vonage account balance: ' . $balance . '.'];
            }
            return ['success' => false, 'message' => 'Vonage rejected this — double-check your API Key and API Secret from dashboard.nexmo.com (shown right on the dashboard homepage).'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Vonage — check your internet connection and try again.'];
        }
    }

    public function sendMessage(array $credentials, string $to, string $message): array
    {
        $apiKey = trim($credentials['client_id'] ?? '');
        $apiSecret = trim($credentials['client_secret'] ?? '');
        if ($apiKey === '' || $apiSecret === '') {
            return ['success' => false, 'message' => 'Vonage is not connected.'];
        }

        try {
            $response = Http::timeout(15)->asForm()->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $apiKey, 'api_secret' => $apiSecret,
                'to' => preg_replace('/[^0-9]/', '', $to),
                'from' => 'GeneralLink',
                'text' => $message,
            ]);

            $status = $response->json('messages.0.status');
            if ($response->successful() && $status === '0') {
                return ['success' => true, 'message' => 'Sent.'];
            }
            return ['success' => false, 'message' => $response->json('messages.0.error-text') ?? ('Vonage status ' . $status)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Vonage.'];
        }
    }
}
