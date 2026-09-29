<?php

namespace App\Services\Integrations\Communication;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 8 Aug 2026 (Task #85) — verifies a WeChat Official Account's
// AppID/AppSecret by exchanging them for an access_token (WeChat's own
// OAuth2 client_credentials flow). Actually sending to a specific agent
// needs their WeChat openid (NOT their phone number — same limitation as
// Telegram/LINE). Until agents have somewhere to link their WeChat
// account, sendMessage() exists for completeness/future use but
// NoticeDeliveryService does not call it yet — it marks WeChat as
// SKIPPED with an honest reason.
class WeChatConnector implements IntegrationConnectorInterface
{
    public function testConnection(array $credentials): array
    {
        $appId = trim($credentials['client_id'] ?? '');
        $appSecret = trim($credentials['client_secret'] ?? '');
        if ($appId === '' || $appSecret === '') {
            return ['success' => false, 'message' => 'Paste both your AppID and AppSecret first, then click Test Connection.'];
        }

        try {
            $response = Http::timeout(10)->get('https://api.weixin.qq.com/cgi-bin/token', [
                'grant_type' => 'client_credential',
                'appid' => $appId,
                'secret' => $appSecret,
            ]);

            if ($response->successful() && $response->json('access_token')) {
                return ['success' => true, 'message' => 'Connected — WeChat Official Account verified.'];
            }
            $errcode = $response->json('errcode');
            $errmsg = $response->json('errmsg');
            return ['success' => false, 'message' => 'WeChat rejected this — double-check your AppID and AppSecret from mp.weixin.qq.com (Development > Basic Configuration).' . ($errmsg ? " WeChat's message: {$errmsg} (code {$errcode})" : '')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach WeChat — check your internet connection and try again.'];
        }
    }

    public function sendMessage(array $credentials, string $openId, string $message): array
    {
        $appId = trim($credentials['client_id'] ?? '');
        $appSecret = trim($credentials['client_secret'] ?? '');
        if ($appId === '' || $appSecret === '' || $openId === '') {
            return ['success' => false, 'message' => 'WeChat credentials or recipient openid missing.'];
        }

        try {
            $tokenResponse = Http::timeout(10)->get('https://api.weixin.qq.com/cgi-bin/token', [
                'grant_type' => 'client_credential', 'appid' => $appId, 'secret' => $appSecret,
            ]);
            $accessToken = $tokenResponse->json('access_token');
            if (!$accessToken) {
                return ['success' => false, 'message' => $tokenResponse->json('errmsg') ?? 'Could not obtain WeChat access token.'];
            }

            $response = Http::timeout(10)->post('https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $accessToken, [
                'touser' => $openId,
                'msgtype' => 'text',
                'text' => ['content' => $message],
            ]);
            if ($response->successful() && $response->json('errcode') == 0) {
                return ['success' => true, 'message' => 'Sent.'];
            }
            return ['success' => false, 'message' => $response->json('errmsg') ?? ('WeChat HTTP ' . $response->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach WeChat.'];
        }
    }
}
