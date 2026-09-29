<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AiGuidanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// -------------------------------------------------------
// NEW 3 Aug 2026 — AI Guided Navigation, part 4/4.
// Same guest/agent dual-mode pattern as AiAssistantController. Both
// endpoints are session-bearing (CSRF applies, unlike the public
// ElevenLabs proxy) since guidance sessions belong to one browser tab's
// journey through the app.
// -------------------------------------------------------
class AiGuidanceController extends Controller
{
    private function currentRole(): string
    {
        $agent = Auth::guard('agent')->user();
        return $agent ? strtolower($agent->role) : 'guest';
    }

    private function currentAgentId(): ?int
    {
        $agent = Auth::guard('agent')->user();
        return $agent ? $agent->agent_id : null;
    }

    public function start(Request $request, AiGuidanceService $service)
    {
        $request->validate([
            'goal' => 'required|string|max:100',
            'current_route' => 'nullable|string|max:150',
        ]);

        $result = $service->start(
            $request->input('goal'),
            $this->currentRole(),
            $request->input('current_route'),
            $this->currentAgentId()
        );

        return response()->json($result);
    }

    public function next(Request $request, AiGuidanceService $service)
    {
        $request->validate([
            'session_id' => 'required|integer',
            'event' => 'required|string|in:step_completed,user_stuck,user_navigated_away',
        ]);

        $result = $service->next(
            $request->input('session_id'),
            $this->currentRole(),
            $request->input('event')
        );

        return response()->json($result);
    }
}
