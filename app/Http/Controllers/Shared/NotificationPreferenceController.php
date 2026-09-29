<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 1 (Task #84). Lets
// an agent choose which Notice Board categories they want proactively
// pushed to them, and through which channels — separate from just
// browsing the Notice Board itself (Portal), which always works
// regardless of this. Multiple choice on both, per Chris. The channel
// list intentionally shows every intended channel now (Portal is
// implicit/always-on and not shown as a choice; Email and WhatsApp can
// actually deliver today; Telegram/LINE/WeChat/SMS are accepted as
// choices but marked "Coming soon" until Task #85 builds those
// connectors) — so an agent never has to redo this later.
class NotificationPreferenceController extends Controller
{
    public const CATEGORIES = ['IMPORTANT_UPDATE', 'PROMOTION', 'HOLIDAY_FESTIVE', 'CONTACT_INFO', 'GENERAL'];
    // UPDATED 8 Aug 2026 (Task #85) — SMS moved to "ready" now that
    // TwilioSmsConnector/VonageSmsConnector can actually send (real phone
    // numbers). Telegram/LINE/WeChat stay "soon" — those platforms need
    // each agent to link a platform-specific chat/user ID that nowhere in
    // GeneralLink collects yet, even though their connectors can verify
    // credentials today.
    public const CHANNELS_READY = ['EMAIL', 'WHATSAPP', 'SMS'];
    public const CHANNELS_SOON = ['TELEGRAM', 'LINE', 'WECHAT'];

    public function edit()
    {
        $agent = Auth::guard('agent')->user();
        $pref = DB::table('agent_notification_preferences')->where('agent_id', $agent->agent_id)->first();

        $selectedCategories = $pref && $pref->categories ? json_decode($pref->categories, true) : self::CATEGORIES;
        $selectedChannels = $pref ? json_decode($pref->channels, true) : ['PORTAL'];
        $frequencyCap = $pref->frequency_cap_per_week ?? null;

        $dashboardRoute = $agent->role === 'GROUP_LEADER' ? 'gl.dashboard' : ($agent->role === 'TEAM_LEADER' ? 'tl.dashboard' : ($agent->role === 'ADMIN' ? 'admin.dashboard' : 'introducer.dashboard'));

        return view('notification-preferences.edit', [
            'selectedCategories' => $selectedCategories,
            'selectedChannels' => $selectedChannels,
            'frequencyCap' => $frequencyCap,
            'dashboardRoute' => $dashboardRoute,
        ]);
    }

    public function update(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        $request->validate([
            'categories' => ['nullable', 'array'],
            'categories.*' => ['in:' . implode(',', self::CATEGORIES)],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['in:' . implode(',', array_merge(['PORTAL'], self::CHANNELS_READY, self::CHANNELS_SOON))],
            'frequency_cap_per_week' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $categories = $request->input('categories', []);
        $channels = $request->input('channels', ['PORTAL']);
        if (!in_array('PORTAL', $channels, true)) $channels[] = 'PORTAL'; // Portal (the Notice Board) can never be turned off

        DB::table('agent_notification_preferences')->updateOrInsert(
            ['agent_id' => $agent->agent_id],
            [
                // All categories ticked = same as no filter (null) — keeps future new categories included automatically.
                'categories' => count($categories) === count(self::CATEGORIES) ? null : json_encode($categories),
                'channels' => json_encode(array_values($channels)),
                'frequency_cap_per_week' => $request->input('frequency_cap_per_week') ?: null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Notification preferences saved.');
    }
}
