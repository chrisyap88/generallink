<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 3 Aug 2026 — AI Guided Navigation, part 3/4.
//
// PHASE 1 SCOPE (deliberate, matches the design doc's phased rollout,
// §6): this service only walks AUTHORED workflows step-by-step. It does
// NOT yet call Claude to improvise ad-hoc plans (design doc §2.3) — that
// is left for a later phase once this framework is proven on the one
// pilot workflow (affiliate_registration). If no authored workflow
// matches the requested goal, it says so plainly rather than guessing.
//
// Every single step returned here is still re-validated against
// NavigationRegistryService before being handed back — even though the
// steps come from a developer-authored manifest, not the AI — so there is
// exactly one gate in the whole system that can let a bad action through
// (design doc §5.2).
//
// The AI NEVER clicks anything (Chris's decision, 2 Aug 2026). Every
// action returned here is highlight/focus/explain only.
// -------------------------------------------------------
class AiGuidanceService
{
    public function __construct(private NavigationRegistryService $registry)
    {
    }

    /**
     * @return array{status:string, session_id?:int, action?:array, message?:string}
     */
    public function start(string $goal, string $role, ?string $currentRoute, ?int $agentId): array
    {
        $workflow = $this->registry->workflowFor($goal, $role);

        if (!$workflow) {
            Log::info('[AiNav] start() — no authored workflow for goal', ['goal' => $goal, 'role' => $role]);
            return [
                'status' => 'NO_WORKFLOW',
                'message' => "I don't have a step-by-step walkthrough for that yet — but I'm happy to explain what to do if you ask me directly.",
            ];
        }

        $steps = $workflow['steps'];
        $firstStep = $steps[0];

        if (!$this->registry->validateAction(['page' => $firstStep['page'], 'element' => $firstStep['element']], $role)) {
            Log::warning('[AiNav] start() — first step of workflow failed validation', ['goal' => $goal]);
            return ['status' => 'ERROR', 'message' => 'Something is misconfigured with this walkthrough — please try again later.'];
        }

        $sessionId = DB::table('ai_navigation_sessions')->insertGetId([
            'agent_id' => $agentId,
            'mode' => $agentId ? 'agent' : 'guest',
            'goal' => $goal,
            'workflow_key' => $goal,
            'current_step' => 0,
            'status' => 'open',
            'started_on_route' => $currentRoute,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('[AiNav] session started', ['session_id' => $sessionId, 'goal' => $goal, 'role' => $role]);

        return [
            'status' => 'OK',
            'session_id' => $sessionId,
            'action' => $this->toClientAction($firstStep),
        ];
    }

    /**
     * @return array{status:string, action?:array, message?:string}
     */
    public function next(int $sessionId, string $role, string $event): array
    {
        $session = DB::table('ai_navigation_sessions')->find($sessionId);
        if (!$session) {
            return ['status' => 'ERROR', 'message' => 'This walkthrough session was not found.'];
        }
        if ($session->status !== 'open') {
            return ['status' => 'ENDED', 'message' => 'This walkthrough has already ended.'];
        }

        $workflow = $this->registry->workflowFor($session->workflow_key, $role);
        if (!$workflow) {
            DB::table('ai_navigation_sessions')->where('id', $sessionId)->update(['status' => 'abandoned', 'updated_at' => now()]);
            return ['status' => 'ERROR', 'message' => 'This walkthrough is no longer available.'];
        }

        $steps = $workflow['steps'];
        $nextIndex = $session->current_step + 1;

        if ($event === 'user_stuck') {
            // Re-send the CURRENT step's message rather than advancing —
            // the user asked for help, not to skip ahead.
            $currentStep = $steps[$session->current_step];
            return ['status' => 'OK', 'action' => $this->toClientAction($currentStep)];
        }

        if ($event === 'user_navigated_away') {
            // The user deliberately ended the walkthrough (or left the
            // flow entirely) — mark it abandoned, never silently advance.
            DB::table('ai_navigation_sessions')->where('id', $sessionId)->update(['status' => 'abandoned', 'updated_at' => now()]);
            return ['status' => 'ENDED', 'message' => 'Walkthrough ended.'];
        }

        if (!isset($steps[$nextIndex])) {
            DB::table('ai_navigation_sessions')->where('id', $sessionId)->update(['status' => 'completed', 'updated_at' => now()]);
            return ['status' => 'ENDED', 'message' => "That's the end of this walkthrough."];
        }

        $step = $steps[$nextIndex];

        if (!$this->registry->validateAction(['page' => $step['page'], 'element' => $step['element']], $role)) {
            Log::warning('[AiNav] next() — step failed validation, ending session', ['session_id' => $sessionId, 'step_index' => $nextIndex]);
            DB::table('ai_navigation_sessions')->where('id', $sessionId)->update(['status' => 'abandoned', 'updated_at' => now()]);
            return ['status' => 'ERROR', 'message' => 'Something went wrong with this walkthrough.'];
        }

        $isEnd = $step['action'] === 'end_workflow';

        DB::table('ai_navigation_sessions')->where('id', $sessionId)->update([
            'current_step' => $nextIndex,
            'status' => $isEnd ? 'completed' : 'open',
            'updated_at' => now(),
        ]);

        return ['status' => 'OK', 'action' => $this->toClientAction($step)];
    }

    private function toClientAction(array $step): array
    {
        $url = null;
        if ($step['action'] === 'open_page' && $step['page']) {
            try {
                $url = route($step['page']); // server-resolved, never a raw AI-supplied URL
            } catch (\Throwable $e) {
                $url = null;
            }
        }

        return [
            'action' => $step['action'],
            'page' => $step['page'],
            'element' => $step['element'],
            'message' => $step['message'],
            'wait' => $step['wait'],
            'url' => $url,
        ];
    }
}
