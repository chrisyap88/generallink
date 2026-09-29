<?php

namespace App\Services\Integrations\Communication;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 8 Aug 2026 (Task #85) — real SMS sending via Twilio, using an
// agent's actual phone number (unlike Telegram/LINE/WeChat, SMS needs no
// separate "linked account" step). testConnection() verifies the Account
// SID/Auth Token; sendMessage() looks up the account's own Twilio number
// automatically (via IncomingPhoneNumbers) rather than asking Chris to
// enter a third field on the Connect form — keeps the same 2-field
// Account SID/Auth Token screen every other pair-shape provider uses.
class TwilioSmsConnector implements IntegrationConnectorInterface
{
    public function testConnection(array $credentials): array
    {
        $sid = trim($credentials['client_id'] ?? '');
        $token = trim($credentials['client_secret'] ?? '');
        if ($sid === '' || $token === '') {
            return ['success' => false, 'message' => 'Paste both your Account SID and Auth Token first, then click Test Connection.'];
        }

        try {
            $response = Http::withBasicAuth($sid, $token)->timeout(10)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}.json");

            if ($response->successful()) {
                $status = $response->json('status');
                return ['success' => true, 'message' => 'Connected — Twilio account is ' . ($status ?: 'active') . '.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Twilio rejected this — double-check your Account SID and Auth Token from twilio.com/console (both shown right on the dashboard homepage).'];
            }
            return ['success' => false, 'message' => 'Twilio returned an unexpected response (HTTP ' . $response->status() . '). Double-check both values were copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Twilio — check your internet connection and try again.'];
        }
    }

    public function sendMessage(array $credentials, string $to, string $message): array
    {
        $sid = trim($credentials['client_id'] ?? '');
        $token = trim($credentials['client_secret'] ?? '');
        if ($sid === '' || $token === '') {
            return ['success' => false, 'message' => 'Twilio is not connected.'];
        }

        try {
            $numbersResponse = Http::withBasicAuth($sid, $token)->timeout(10)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}/IncomingPhoneNumbers.json", ['PageSize' => 1]);
            $from = $numbersResponse->json('incoming_phone_numbers.0.phone_number');
            if (!$from) {
                return ['success' => false, 'message' => 'Your Twilio account has no phone number to send from yet — buy one at twilio.com/console/phone-numbers.'];
            }

            $response = Http::withBasicAuth($sid, $token)->timeout(15)->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to, 'From' => $from, 'Body' => $message,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Sent.'];
            }
            return ['success' => false, 'message' => $response->json('message') ?? ('Twilio HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Twilio.'];
        }
    }
}
