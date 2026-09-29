<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    // Shows PENDING approvals to any Admin EXCEPT the ones they
    // themselves requested (they can't approve their own anyway, so no
    // point cluttering their own queue with their own requests) — plus
    // a separate section showing their own requests and their status.
    public function index()
    {
        $me = Auth::guard('agent')->id();

        // PAGINATED 8 Aug 2026 per Chris: strict no-scroll rule — both
        // lists were unbounded (->get() / ->limit(20)->get()), and the
        // whole page was one long overflow-y:auto scroll region. Rebuilt
        // as tabs (Awaiting Your Decision / My Requests / Settings), each
        // with real bottom Prev/Next pagination — see the view.
        $pendingForMe = DB::table('pending_approvals')
            ->leftJoin('agents', 'agents.agent_id', '=', 'pending_approvals.requested_by')
            ->leftJoin('reason_codes', 'reason_codes.reason_code_id', '=', 'pending_approvals.request_reason_code_id')
            ->where('pending_approvals.status', 'PENDING')
            ->where('pending_approvals.requested_by', '!=', $me)
            ->select('pending_approvals.*', 'agents.full_name as requested_by_name', 'reason_codes.description as reason_description')
            ->orderByDesc('pending_approvals.created_at')
            ->paginate(4, ['*'], 'apPage');

        $myRequests = DB::table('pending_approvals')
            ->leftJoin('agents', 'agents.agent_id', '=', 'pending_approvals.approved_by')
            ->where('pending_approvals.requested_by', $me)
            ->select('pending_approvals.*', 'agents.full_name as approved_by_name')
            ->orderByDesc('pending_approvals.created_at')
            ->paginate(5, ['*'], 'myPage');

        $me = Auth::guard('agent')->user();
        $reminderHours = DB::table('system_settings')->where('setting_key', 'approval_reminder_hours')->value('setting_value') ?? 24;
        $escalationHours = DB::table('system_settings')->where('setting_key', 'approval_escalation_hours')->value('setting_value') ?? 48;

        return view('masterfile.approvals', compact('pendingForMe', 'myRequests', 'me', 'reminderHours', 'escalationHours'));
    }

    public function pendingCount()
    {
        $me = Auth::guard('agent')->id();

        $count = DB::table('pending_approvals')
            ->where('status', 'PENDING')
            ->where('requested_by', '!=', $me)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function updateAvailability(Request $request)
    {
        $request->validate([
            'availability_status'      => ['required', 'in:AVAILABLE,ON_LEAVE'],
            'availability_return_date' => ['required_if:availability_status,ON_LEAVE', 'nullable', 'date', 'after_or_equal:today'],
        ]);

        $agent = Agent::find(Auth::guard('agent')->id());
        $agent->update([
            'availability_status'      => $request->availability_status,
            'availability_return_date' => $request->availability_status === 'AVAILABLE' ? null : $request->availability_return_date,
        ]);

        return back()->with('success', 'Your availability has been updated.');
    }

    // Director-only — configurable reminder/escalation durations, per
    // confirmed decision (never hardcoded, since fixed durations cause
    // problems during long holidays like Chinese New Year).
    public function updateSettings(Request $request)
    {
        if (Auth::guard('agent')->user()->department !== 'DIRECTOR') {
            abort(403, 'Only Admin Director can change these settings.');
        }

        $request->validate([
            'reminder_hours'   => ['required', 'integer', 'min:1', 'max:720'],
            'escalation_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'approval_reminder_hours'],
            ['setting_value' => $request->reminder_hours, 'updated_by' => Auth::guard('agent')->id(), 'updated_at' => now()]
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'approval_escalation_hours'],
            ['setting_value' => $request->escalation_hours, 'updated_by' => Auth::guard('agent')->id(), 'updated_at' => now()]
        );

        return back()->with('success', 'Reminder/escalation settings updated.');
    }

    public function approve(Request $request, string $id)
    {
        $result = app(ApprovalService::class)->approve($id, Auth::guard('agent')->id(), $request->notes);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Request $request, string $id)
    {
        $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $result = app(ApprovalService::class)->reject($id, Auth::guard('agent')->id(), $request->notes);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // The FIRST sensitive action wired into the 4-eye engine — request
    // to undo an agent's most recent promotion/demotion.
    public function requestUndoRole(Request $request, string $id)
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $agent = Agent::where('agent_id', $id)->where('is_deleted', false)->firstOrFail();

        $latestHistory = DB::table('role_history')
            ->where('agent_id', $id)
            ->orderByDesc('effective_date')
            ->first();

        if (!$latestHistory) {
            return back()->withErrors(['undo' => 'No role change history found for this person.']);
        }

        // Prevent duplicate pending requests for the same history entry.
        $alreadyPending = DB::table('pending_approvals')
            ->where('action_type', 'UNDO_ROLE_CHANGE')
            ->where('target_agent_id', $id)
            ->where('status', 'PENDING')
            ->exists();
        if ($alreadyPending) {
            return back()->withErrors(['undo' => 'There is already a pending Undo request for this person.']);
        }

        app(ApprovalService::class)->requestApproval(
            'UNDO_ROLE_CHANGE',
            $id,
            ['role_history_id' => $latestHistory->history_id],
            Auth::guard('agent')->id(),
            null,
            $request->notes
        );

        return back()->with('success', 'Undo request submitted — a different Admin must approve it before it takes effect.');
    }
}
