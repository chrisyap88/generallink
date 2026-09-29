<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Growth & Outreach Center, Phase 1 (task #210). The
// referral link/QR mechanism itself (agents.qr_code_token,
// /register?ref=TOKEN, AffiliateController::lookupByToken) already
// existed from an earlier session, but had no screen to view/share it
// from (the old ProfileController::generateQr method pointed at a view
// that was never created) and no analytics at all. This is that screen,
// rebuilt under the new Growth & Outreach Center menu, plus the click/
// signup counts computed live from referral_clicks (see migration
// 2026_07_25_000004).
class ReferralLinkController extends Controller
{
    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        // Defensive backfill — covers any agent created before the
        // auto-generation was added to Agent::boot() (25 Jul 2026).
        if (empty($agent->qr_code_token)) {
            do {
                $token = Str::random(10);
            } while (Agent::where('qr_code_token', $token)->exists());
            DB::table('agents')->where('agent_id', $agent->agent_id)->update(['qr_code_token' => $token, 'updated_at' => now()]);
            $agent->qr_code_token = $token;
        }

        $referralUrl = url('/register?ref=' . $agent->qr_code_token);

        // Endroid QR Code v6 — direct instantiation (current library
        // API), same fix already applied in AuthController for the
        // identical Builder::create() removal.
        $qrDataUri = (new Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            data: $referralUrl,
            size: 220,
            margin: 8,
        ))->build()->getDataUri();

        $clickCount = DB::table('referral_clicks')->where('agent_id', $agent->agent_id)->count();
        $signupCount = DB::table('referral_clicks')->where('agent_id', $agent->agent_id)->whereNotNull('converted_agent_id')->count();

        $recentSignups = DB::table('referral_clicks as rc')
            ->join('agents as a', 'rc.converted_agent_id', '=', 'a.agent_id')
            ->where('rc.agent_id', $agent->agent_id)
            ->whereNotNull('rc.converted_agent_id')
            ->select('a.full_name', 'a.agent_code', 'a.role', 'a.status', 'rc.clicked_at')
            ->orderByDesc('rc.clicked_at')
            ->paginate(8, ['*'], 'refPage');

        return view('growth.referral-link', compact('agent', 'referralUrl', 'qrDataUri', 'clickCount', 'signupCount', 'recentSignups'));
    }

    // Downloads the QR as a standalone PNG file — for printing/sharing
    // outside the app (WhatsApp status, social media post, etc.).
    public function downloadQr()
    {
        $agent = Auth::guard('agent')->user();
        $referralUrl = url('/register?ref=' . $agent->qr_code_token);

        $result = (new Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            data: $referralUrl,
            size: 600,
            margin: 20,
        ))->build();

        return response($result->getString(), 200, [
            'Content-Type'        => $result->getMimeType(),
            'Content-Disposition' => 'attachment; filename="generallink-referral-qr.png"',
        ]);
    }
}
