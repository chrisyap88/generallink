<?php

namespace App\Http\Controllers;

use App\Services\GoogleFormsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// NEW 25 Jul 2026 — Survey Management, real Google Forms integration
// (task #227 follow-up, #240). Handles the one-time "Connect Google
// Account" OAuth flow. Only Admin can connect/disconnect (this is a
// single, business-wide connection — see google_connections table).
class GoogleAuthController extends Controller
{
    public function connect(GoogleFormsService $google)
    {
        return redirect()->away($google->authUrl());
    }

    public function callback(Request $request, GoogleFormsService $google)
    {
        if ($request->filled('error')) {
            return redirect()->route('admin.growth.surveys.index')
                ->with('error', 'Google sign-in was cancelled or denied ('.$request->get('error').'). Nothing was connected.');
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('admin.growth.surveys.index')->with('error', 'Google did not return an authorization code. Please try connecting again.');
        }

        $agent = Auth::guard('agent')->user();

        try {
            $google->handleCallback($code, $agent->agent_id ?? null);
        } catch (\Throwable $e) {
            return redirect()->route('admin.growth.surveys.index')->with('error', 'Could not connect Google account: '.$e->getMessage());
        }

        return redirect()->route('admin.growth.surveys.index')->with('success', 'Google account connected. You can now push surveys to Google Forms.');
    }

    public function disconnect(GoogleFormsService $google)
    {
        $google->disconnect();
        return redirect()->route('admin.growth.surveys.index')->with('success', 'Google account disconnected.');
    }
}
