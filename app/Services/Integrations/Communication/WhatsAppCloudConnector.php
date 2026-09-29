<?php

namespace App\Services\Integrations\Communication;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 6 Aug 2026 — per Chris: a real, working WhatsApp connection for a
// demo to his boss, not just a stored-but-unused credential. Uses Meta's
// official WhatsApp Business Cloud API (the current, free way to send
// WhatsApp messages programmatically — no separate paid gateway needed).
// testConnection() calls Meta's own "get phone number details" endpoint,
// which is free and read-only — it never sends an actual WhatsApp
// message, exactly like every other connector's Test Connection.
class WhatsAppCloudConnector implements IntegrationConnectorInterface
{
    private const API_VERSION = 'v21.0';

    /**
     * @param array $credentials ['client_id' => Phone Number ID, 'client_secret' => Access Token]
     */
    public function testConnection(array $credentials): array
    {
        $phoneNumberId = trim($credentials['client_id'] ?? '');
        $accessToken = trim($credentials['client_secret'] ?? '');

        if ($phoneNumberId === '' || $accessToken === '') {
            return ['success' => false, 'message' => 'Paste both your Phone Number ID and Access Token first, then click Test Connection.'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://graph.facebook.com/' . self::API_VERSION . '/' . $phoneNumberId, [
                    'fields' => 'display_phone_number,verified_name',
                ]);

            if ($response->successful()) {
                $displayNumber = $response->json('display_phone_number');
                return ['success' => true, 'message' => 'Connected — WhatsApp number ' . ($displayNumber ?: 'verified') . ' is ready to send.'];
            }

            $error = $response->json('error.message') ?? null;
            if ($response->status() === 401 || $response->status() === 400) {
                return ['success' => false, 'message' => 'Meta rejected this — likely the Access Token has expired (temporary tokens from the API Setup page only last 24 hours) or the Phone Number ID is wrong. Go back to developers.facebook.com, generate a fresh temporary token (or a permanent one via a System User), and paste both values again. ' . ($error ? "Meta's exact message: {$error}" : '')];
            }
            if ($response->status() === 404) {
                return ['success' => false, 'message' => 'Meta could not find a phone number with that ID. Double-check the Phone Number ID on your app\'s WhatsApp > API Setup page — it is a long number, not your actual phone number.'];
            }
            if ($response->status() >= 500) {
                return ['success' => false, 'message' => 'Meta\'s own service returned an error (HTTP ' . $response->status() . ') — this is temporary on their side, not your credentials. Try again in a few minutes.'];
            }
            return ['success' => false, 'message' => 'Meta returned an unexpected response (HTTP ' . $response->status() . '). ' . ($error ? "Their message: {$error}" : 'Double-check both values were copied in full, then try again.')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Meta\'s WhatsApp service — check your internet connection and try again. If this keeps happening, Meta\'s service may be temporarily down.'];
        }
    }

    // NEW 8 Aug 2026 — reusable send, extracted so GLADE's notice-push
    // delivery (NoticeDeliveryService) can reuse the exact same call
    // WhatsAppController::send() makes, instead of duplicating the HTTP
    // logic. Same error shapes as WhatsAppController::send() so callers
    // can branch on ['success' => bool, 'message' => string] uniformly.
    public function sendMessage(array $credentials, string $to, string $message): array
    {
        $phoneNumberId = trim($credentials['client_id'] ?? '');
        $accessToken = trim($credentials['client_secret'] ?? '');

        if ($phoneNumberId === '' || $accessToken === '') {
            return ['success' => false, 'message' => 'WhatsApp is not connected.'];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post('https://graph.facebook.com/' . self::API_VERSION . '/' . $phoneNumberId . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Sent.'];
            }

            $error = $response->json('error.message') ?? null;
            $errorCode = $response->json('error.code') ?? null;
            if ($errorCode == 131030) {
                return ['success' => false, 'message' => 'Recipient is not a verified test number.'];
            }
            if ($errorCode == 133010) {
                return ['success' => false, 'message' => 'Sending number is not registered yet.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Access Token expired or invalid.'];
            }
            return ['success' => false, 'message' => $error ?: ('Meta HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Meta.'];
        }
    }

    // NEW 19 Sep 2026 — per outstanding item flagged 8 Aug ("receipt to
    // the recipient is by email ya or whatapps if available"): sends a
    // PDF as a real WhatsApp document attachment, not just text. Meta's
    // Cloud API needs the file uploaded first (its own /media endpoint,
    // multipart) to get a media id, then a separate message referencing
    // that id — there's no way to send raw bytes in one call.
    public function sendDocument(array $credentials, string $to, string $pdfBytes, string $filename, ?string $caption = null): array
    {
        $phoneNumberId = trim($credentials['client_id'] ?? '');
        $accessToken = trim($credentials['client_secret'] ?? '');

        if ($phoneNumberId === '' || $accessToken === '') {
            return ['success' => false, 'message' => 'WhatsApp is not connected.'];
        }

        $to = preg_replace('/[^0-9]/', '', $to);

        try {
            $uploadResponse = Http::withToken($accessToken)
                ->timeout(20)
                ->attach('file', $pdfBytes, $filename, ['Content-Type' => 'application/pdf'])
                ->post('https://graph.facebook.com/' . self::API_VERSION . '/' . $phoneNumberId . '/media', [
                    'messaging_product' => 'whatsapp',
                    'type' => 'application/pdf',
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Meta to upload the file.'];
        }

        if (! $uploadResponse->successful()) {
            $error = $uploadResponse->json('error.message') ?? null;
            return ['success' => false, 'message' => 'Meta rejected the file upload' . ($error ? " — {$error}" : ' (HTTP ' . $uploadResponse->status() . ')') . '.'];
        }

        $mediaId = $uploadResponse->json('id');
        if (! $mediaId) {
            return ['success' => false, 'message' => 'Meta did not return a file reference after upload.'];
        }

        try {
            $sendResponse = Http::withToken($accessToken)
                ->timeout(15)
                ->post('https://graph.facebook.com/' . self::API_VERSION . '/' . $phoneNumberId . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'document',
                    'document' => array_filter([
                        'id' => $mediaId,
                        'filename' => $filename,
                        'caption' => $caption,
                    ]),
                ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Meta to send the document.'];
        }

        if ($sendResponse->successful()) {
            return ['success' => true, 'message' => 'Sent.'];
        }

        $error = $sendResponse->json('error.message') ?? null;
        $errorCode = $sendResponse->json('error.code') ?? null;
        if ($errorCode == 131030) {
            return ['success' => false, 'message' => 'Recipient is not a verified test number.'];
        }
        if ($errorCode == 133010) {
            return ['success' => false, 'message' => 'Sending number is not registered yet.'];
        }
        if ($sendResponse->status() === 401) {
            return ['success' => false, 'message' => 'Access Token expired or invalid.'];
        }
        return ['success' => false, 'message' => $error ?: ('Meta HTTP ' . $sendResponse->status())];
    }

    // NEW 6 Aug 2026 — one-time step Meta requires before a WhatsApp number
    // (even their own free test number) can actually SEND messages, even
    // though testConnection() above already succeeds. Without this,
    // sending fails with Meta error 133010 "Account not registered". This
    // sets up two-step verification with the PIN the agent chooses (any
    // 6 digits — GeneralLink never sees or stores it, it's sent straight
    // to Meta and forgotten).
    public function registerNumber(array $credentials, string $pin): array
    {
        $phoneNumberId = trim($credentials['client_id'] ?? '');
        $accessToken = trim($credentials['client_secret'] ?? '');

        if ($phoneNumberId === '' || $accessToken === '') {
            return ['success' => false, 'message' => 'WhatsApp isn\'t connected yet — connect it in Integration Hub first.'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post('https://graph.facebook.com/' . self::API_VERSION . '/' . $phoneNumberId . '/register', [
                    'messaging_product' => 'whatsapp',
                    'pin' => $pin,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Number registered — you can now send real WhatsApp messages.'];
            }

            $error = $response->json('error.message') ?? null;
            $errorCode = $response->json('error.code') ?? null;

            if ($errorCode == 133009 || ($error && stripos($error, 'already') !== false)) {
                return ['success' => true, 'message' => 'This number was already registered — you can send messages now.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Meta rejected the Access Token — it may have expired (temporary tokens only last 24 hours). Generate a fresh one at developers.facebook.com and reconnect in Integration Hub first.'];
            }
            return ['success' => false, 'message' => 'Meta could not register this number' . ($error ? " — {$error}" : '') . '. Double-check your PIN is exactly 6 digits, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Meta\'s WhatsApp service — check your internet connection and try again.'];
        }
    }
}
