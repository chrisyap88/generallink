<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PendingAssignmentController extends Controller
{
    /**
     * List all unassigned public registrations
     */
    public function index()
    {
        $pendingAgents = Agent::whereNull('parent_id')
            ->where('role', 'INTRODUCER')
            ->where('status', '!=', 'ADMIN')
            ->where('is_deleted', false)
            ->select('agent_id', 'full_name', 'email', 'phone', 'agent_code', 'status', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        $gls = Agent::where('role', 'GROUP_LEADER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->select('agent_id', 'full_name', 'agent_code')
            ->orderBy('full_name')
            ->get();

        return view('admin.agents.pending', compact('pendingAgents', 'gls'));
    }

    /**
     * Assign agent to GL — ONE TIME ONLY, PERMANENT
     */
    public function assign(Request $request, string $agentId)
    {
        $request->validate([
            'gl_agent_id' => ['required', 'string', 'exists:agents,agent_id'],
        ]);

        $agent = Agent::where('agent_id', $agentId)
            ->whereNull('parent_id')
            ->where('is_deleted', false)
            ->firstOrFail();

        $gl = Agent::where('agent_id', $request->gl_agent_id)
            ->where('role', 'GROUP_LEADER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->firstOrFail();

        // PERMANENT assignment — cannot be changed ever
        $agent->update([
            'parent_id'  => $gl->agent_id,
            'updated_by' => auth('agent')->user()->agent_id,
        ]);

        // Notify agent via email
        try {
            Mail::to($agent->email)->send(new \App\Mail\AgentAssignedMail($agent, $gl));
        } catch (\Exception $e) {
            // Log but don't fail
        }

        return redirect()->route('admin.agents.pending')
            ->with('success', "{$agent->full_name} has been successfully assigned to {$gl->full_name}.");
    }
}
