<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AiMemoryService;
use Illuminate\Support\Facades\Auth;

// NEW 5 Aug 2026 — self-service transparency + control screen for
// Carolyn's long-term memory (see AiMemoryService). Every agent can see
// exactly what she remembers about them and erase all of it — or one
// note at a time — with no admin involvement needed. Reached from
// My Profile > Text Chat.
class AiMemoryController extends Controller
{
    public function index(AiMemoryService $memory)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        $notes = $memory->all($agentId);

        return view('ai.memory', compact('notes'));
    }

    public function forgetOne(AiMemoryService $memory, string $memoryId)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        $memory->forget($agentId, $memoryId);

        return back()->with('success', 'Forgotten.');
    }

    public function forgetAll(AiMemoryService $memory)
    {
        $agentId = Auth::guard('agent')->user()->agent_id;
        $memory->forgetAll($agentId);

        return back()->with('success', 'Carolyn has forgotten everything she knew about you. Future conversations start fresh.');
    }
}
