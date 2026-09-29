<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Support\Facades\DB;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #214). Public,
// no-login page per agent — a shareable "business card" with their
// referral link/QR embedded. Only reachable if the agent has actually
// opted in (agent_profiles.is_published = true) — nothing is public by
// default. 404s for anyone not found/not published/not active, same as
// any other not-found page (no distinction is made between "doesn't
// exist" and "not published", so nobody can enumerate valid agent
// codes that simply haven't opted in yet).
class AgentPublicPageController extends Controller
{
    public function show(string $agentCode)
    {
        $agent = Agent::where('agent_code', $agentCode)
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->first();
        abort_if(!$agent, 404);

        $profile = DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->first();
        abort_if(!$profile || !$profile->is_published, 404);

        $referralUrl = url('/register?ref=' . $agent->qr_code_token);
        // Endroid QR Code v6 — direct instantiation, same fix already
        // applied in AuthController/ReferralLinkController.
        $qrDataUri = (new Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            data: $referralUrl,
            size: 180,
            margin: 8,
        ))->build()->getDataUri();

        return view('growth.public-profile', compact('agent', 'profile', 'referralUrl', 'qrDataUri'));
    }
}
