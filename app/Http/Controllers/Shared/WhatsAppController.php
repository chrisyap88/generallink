<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use App\Services\HubVaultService;
use App\Services\Integrations\Communication\WhatsAppCloudConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// NEW 6 Aug 2026 — Send WhatsApp Message tool (topbar button, next to the
// bell). Sends REAL WhatsApp messages via Meta's WhatsApp Business Cloud
// API, using the Phone Number ID + Access Token each agent saved in
// Integration Hub > Communication > WhatsApp (see WhatsAppCloudConnector).
// Same per-agent vault discipline as everywhere else — one agent can never
// see or use another agent's WhatsApp connection.
class WhatsAppController extends Controller
{
    private const API_VERSION = 'v21.0';

    public function __construct(private HubVaultService $vault) {}

    /** Redirects to Hub setup/unlock if needed; returns null if the vault is ready to use. */
    private function requireUnlockedVault(string $agentId)
    {
        if (!$this->vault->hasVault($agentId)) {
            return redirect()->route('integrations.vault.setup');
        }
        if (!$this->vault->isUnlocked($agentId)) {
            return redirect()->route('integrations.vault.unlock');
        }
        return null;
    }

    private function connectionRow(string $agentId)
    {
        return DB::table('agent_integrations')
            ->where('agent_id', $agentId)
            ->where('category', 'communication')
            ->where('provider', 'whatsapp')
            ->first();
    }

    public function form()
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        $row = $this->connectionRow($agentId);
        $connected = $row && $row->status === 'CONNECTED';

        return view('whatsapp.send', [
            'connected' => $connected,
            'lastTestedAt' => $row->last_tested_at ?? null,
        ]);
    }

    public function send(Request $request, DataScopeService $scope)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        $request->validate([
            'to' => 'required|string|max:20',
            'message' => 'required|string|max:1000',
        ]);

        // Meta expects the recipient number without spaces, dashes, or a
        // leading '+' — country code included (e.g. 60123456789, not
        // +60 12-345 6789).
        $to = preg_replace('/[^0-9]/', '', $request->input('to'));
        $message = $request->input('message');

        // NEW 8 Aug 2026 (Task #83) — per Chris: an agent may only WhatsApp
        // a phone number belonging to their own customer, or their own
        // upline/downline agent. Checked BEFORE anything is sent, and every
        // attempt (allowed or blocked) is written to whatsapp_message_log
        // for Admin's audit view.
        $recipient = $scope->verifyWhatsAppRecipient($to);
        if (!$recipient['allowed']) {
            $this->logAttempt($agentId, $to, $message, $recipient, 'BLOCKED', $recipient['reason']);
            return redirect()->route('whatsapp.form')->with('error', $recipient['reason'] . ' GeneralLink only allows sending WhatsApp messages to your own customers or your own upline/downline agents. If this number should be reachable, ask Admin to check it\'s saved correctly under the right owner, then try again.');
        }

        $row = $this->connectionRow($agentId);
        if (!$row || $row->status !== 'CONNECTED') {
            $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'WhatsApp not connected.');
            return redirect()->route('whatsapp.form')->with('error', 'WhatsApp isn\'t connected yet. Go to Integration Hub > Communication > WhatsApp, paste your Phone Number ID and Access Token, and click Test Connection first.');
        }

        try {
            $phoneNumberId = $this->vault->decryptSecret($agentId, $row->client_id_encrypted);
            $accessToken = $this->vault->decryptSecret($agentId, $row->client_secret_encrypted);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: failed to decrypt credentials', ['agent_id' => $agentId]);
            $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'Saved credentials could not be decrypted.');
            return redirect()->route('whatsapp.form')->with('error', 'Your saved WhatsApp credentials couldn\'t be read (they may have been saved under an old Hub password). Go to Integration Hub > Communication > WhatsApp and reconnect.');
        }

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
                $this->logAttempt($agentId, $to, $message, $recipient, 'SENT', null);
                return redirect()->route('whatsapp.form')->with('success', 'Message sent to ' . $to . '.');
            }

            $error = $response->json('error.message') ?? null;
            $errorCode = $response->json('error.code') ?? null;

            if ($response->status() === 401) {
                $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'Meta rejected the Access Token (expired).');
                return redirect()->route('whatsapp.form')->with('error', 'Meta rejected the Access Token — temporary tokens from the API Setup page only last 24 hours. Go to developers.facebook.com, generate a fresh temporary token (or set up a permanent one via a System User), then reconnect in Integration Hub > Communication > WhatsApp.');
            }
            if ($errorCode == 131030) {
                $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'Recipient not a verified test number (Meta 131030).');
                return redirect()->route('whatsapp.form')->with('error', 'This number hasn\'t been added as a test recipient yet. In test mode, Meta only allows sending to numbers you\'ve pre-verified — go to your app\'s WhatsApp > API Setup page, add this number under "To", and verify it with the code sent to that phone, then try again.');
            }
            if ($errorCode == 133010) {
                $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'Sending number not registered (Meta 133010).');
                return redirect()->route('whatsapp.form')->with('error', 'Your WhatsApp number needs a one-time registration step before it can send messages — scroll down to "Register This Number", choose any 6-digit PIN, and click Register. Then try sending again.');
            }
            if ($response->status() === 400) {
                $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', $error ?? 'Meta HTTP 400.');
                return redirect()->route('whatsapp.form')->with('error', 'Meta rejected this message' . ($error ? " — {$error}" : '') . '. Double-check the phone number includes the country code with no spaces or symbols (e.g. 60123456789), then try again.');
            }
            if ($response->status() >= 500) {
                $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'Meta HTTP ' . $response->status() . ' (their side).');
                return redirect()->route('whatsapp.form')->with('error', 'Meta\'s own service returned an error (HTTP ' . $response->status() . ') — this is temporary on their side, not your message. Try again in a few minutes.');
            }
            $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'Meta HTTP ' . $response->status() . ($error ? " — {$error}" : ''));
            return redirect()->route('whatsapp.form')->with('error', 'Meta returned an unexpected response (HTTP ' . $response->status() . ')' . ($error ? " — {$error}" : '') . '. Try again, and if it keeps happening, reconnect WhatsApp in Integration Hub.');
        } catch (\Throwable $e) {
            $this->logAttempt($agentId, $to, $message, $recipient, 'FAILED', 'Could not reach Meta (network/timeout).');
            return redirect()->route('whatsapp.form')->with('error', 'Could not reach Meta\'s WhatsApp service — check your internet connection and try again. If this keeps happening, Meta\'s service may be temporarily down.');
        }
    }

    /** Writes one row to whatsapp_message_log — every send attempt, allowed or blocked, successful or not. */
    private function logAttempt(string $agentId, string $to, string $message, array $recipient, string $status, ?string $detail): void
    {
        try {
            DB::table('whatsapp_message_log')->insert([
                'log_id' => (string) Str::uuid(),
                'sender_agent_id' => $agentId,
                'recipient_phone' => $to,
                'recipient_type' => $recipient['type'],
                'recipient_id' => $recipient['id'],
                'recipient_name' => $recipient['name'],
                'message' => $message,
                'status' => $status,
                'detail' => $detail,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: failed to write audit log', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
        }
    }

    // NEW 6 Aug 2026 — one-time step Meta requires before ANY WhatsApp
    // number (even their free test number) can send messages. See
    // WhatsAppCloudConnector::registerNumber(). The PIN the agent types
    // here is sent straight to Meta and never stored by GeneralLink.
    public function register(Request $request, WhatsAppCloudConnector $connector)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        if ($redirect = $this->requireUnlockedVault($agentId)) return $redirect;

        $request->validate([
            'pin' => 'required|digits:6',
        ]);

        $row = $this->connectionRow($agentId);
        if (!$row || $row->status !== 'CONNECTED') {
            return redirect()->route('whatsapp.form')->with('error', 'WhatsApp isn\'t connected yet. Go to Integration Hub > Communication > WhatsApp, connect it, and click Test Connection first.');
        }

        try {
            $credentials = [
                'client_id' => $this->vault->decryptSecret($agentId, $row->client_id_encrypted),
                'client_secret' => $this->vault->decryptSecret($agentId, $row->client_secret_encrypted),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: failed to decrypt credentials for registration', ['agent_id' => $agentId]);
            return redirect()->route('whatsapp.form')->with('error', 'Your saved WhatsApp credentials couldn\'t be read. Go to Integration Hub > Communication > WhatsApp and reconnect.');
        }

        $result = $connector->registerNumber($credentials, $request->input('pin'));

        return redirect()->route('whatsapp.form')->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
