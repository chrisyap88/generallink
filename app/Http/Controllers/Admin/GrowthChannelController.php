<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Growth & Outreach Center, Phase 1 (task #209). Per
// Chris: "you can incorporate first i subscribe later... just a tick
// box for me to choose." Ticking a channel on here does NOT send
// anything by itself — it just marks it as available for Broadcast
// Campaigns later. The provider/account/API key fields are optional and
// can be filled in whenever Chris actually signs up with a provider;
// until then the channel simply shows "Not Connected".
class GrowthChannelController extends Controller
{
    public function index()
    {
        $channels = DB::table('growth_channels')->orderBy('channel_name')->get();

        // API key is never shown back in plaintext once saved — only
        // whether one exists, so nobody can read a live secret off the
        // screen just by opening it.
        foreach ($channels as $c) {
            $c->has_api_key = !empty($c->credentials_encrypted);
        }

        return view('growth.channels', compact('channels'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'channels' => ['required', 'array'],
        ]);

        $agent = Auth::guard('agent')->user();
        $existing = DB::table('growth_channels')->get()->keyBy('channel_id');

        foreach ($request->input('channels') as $channelId => $row) {
            if (!$existing->has($channelId)) {
                continue;
            }
            $before = $existing[$channelId];

            $values = [
                'is_enabled'         => isset($row['is_enabled']) ? true : false,
                'provider_name'      => $row['provider_name'] !== '' ? $row['provider_name'] : null,
                'account_identifier' => $row['account_identifier'] !== '' ? $row['account_identifier'] : null,
                'updated_by'         => $agent->agent_id,
                'updated_at'         => now(),
            ];

            // Only overwrite the stored secret if a new one was actually
            // typed in this save — an empty box means "leave it as is",
            // never "clear it out".
            if (!empty($row['api_key'])) {
                $values['credentials_encrypted'] = Crypt::encryptString($row['api_key']);
            }

            DB::table('growth_channels')->where('channel_id', $channelId)->update($values);

            AuditService::logChange('growth_channels', $channelId, 'GROWTH_CHANNEL_UPDATED', $before, $values);
        }

        return redirect()->route('admin.growth.channels.index')->with('success', 'Channel settings saved.');
    }
}
