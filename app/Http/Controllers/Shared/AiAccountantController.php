<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\AiAccountantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// NEW 19 Sep 2026 -- "AI Accountant", per Chris: "forget carolyn use
// another ai agents call AI Accountant." Separate endpoint from
// Carolyn's /ai-assistant/chat entirely -- own widget, own brain
// (AiAccountantService), own avatar. CBE/Admin only (this agent has
// nothing to say to a DSG/insurance-side agent).
class AiAccountantController extends Controller
{
    use ResolvesCbeActiveNode;

    public function chat(Request $request, AiAccountantService $service)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
        ]);

        $agent = Auth::guard('agent')->user();
        if (!$agent) {
            return response()->json(['status' => 'ERROR', 'message' => 'Please log in first.'], 200);
        }

        $context = [
            'full_name' => $agent->full_name,
            'role' => $agent->role,
            'cbe_node_id' => $this->resolveCbeNodeId($agent),
        ];

        $history = array_slice($request->input('history', []), -20);

        $result = $service->chat($history, $request->input('message'), $context);

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'ERROR', 'message' => $result['message']], 200);
        }

        return response()->json([
            'status' => 'OK',
            'reply' => $result['reply'],
            'history' => $result['history'],
            'coa_result' => $result['coa_result'] ?? null,
            'handoff_to_carolyn' => $result['handoff_to_carolyn'] ?? null,
        ]);
    }
}
