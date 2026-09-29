<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 15 Sep 2026 — per Chris: "temple/NGO committee team or SME CBE
// group management team... sensei/consultant/legal advisor... you
// should have a master file to set up the position according to the
// cbe group" (practitioner TYPES live in AdminPractitionerTypeController
// / cbe_practitioner_types — see that controller). This one sets up WHO
// is a practitioner at a specific temple/branch/entity: always an
// existing Agent/Member, picked by search, never a separate registration
// — same node-scoped pattern as Member/Consultant/Donor Maintenance
// (cbe-kpi/members etc.), including the same group->node picker when no
// ?node= is given yet.
class AdminPractitionerController extends Controller
{
    public function index(Request $request)
    {
        $nodeId = $request->get('node');
        $node = $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first() : null;

        if (! $node) {
            $groupId = $request->get('group');
            $groups = DB::table('group_labels')
                ->where('group_type', 'CBE')
                ->orderBy('group_name')
                ->get(['group_label_id', 'group_name']);

            $group = null;
            $nodes = collect();

            if ($groupId) {
                $group = DB::table('group_labels')
                    ->where('group_label_id', $groupId)
                    ->where('group_type', 'CBE')
                    ->first();

                if ($group) {
                    $nodes = DB::table('cbe_hierarchy_nodes as n')
                        ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                        ->where('n.group_label_id', $groupId)
                        ->orderBy('l.level_order')
                        ->orderBy('n.node_name')
                        ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'l.level_name')
                        ->get();
                }
            }

            return view('admin.cbe-kpi.node-picker', [
                'groups' => $groups,
                'group' => $group,
                'nodes' => $nodes,
                'pickerRoute' => 'admin.practitioners.index',
                'pickerTitle' => __('cbe_masterfile.practitioner_setup'),
            ]);
        }

        $practitioners = DB::table('cbe_practitioner_profiles as p')
            ->join('agents as a', 'a.agent_id', '=', 'p.agent_id')
            ->join('cbe_practitioner_types as t', 't.id', '=', 'p.practitioner_type_id')
            ->where('p.cbe_node_id', $nodeId)
            ->orderBy('t.sort_order')
            ->orderBy('a.full_name')
            ->select([
                'p.id', 'p.slot_duration_minutes', 'p.max_slots_per_day', 'p.booking_window_days',
                'p.max_upcoming_per_member', 'p.is_active',
                't.type_label', 'a.agent_id', 'a.full_name', 'a.agent_code', 'a.phone', 'a.email',
            ])
            ->get();

        $practitionerTypeCatalog = DB::table('cbe_practitioner_types')->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.practitioners.index', compact('node', 'practitioners', 'practitionerTypeCatalog'));
    }

    // Agent search for the "add practitioner" picker — excludes agents
    // already set up as that exact type at this node (no point adding
    // the same person twice for the same type; they can still be added
    // again under a DIFFERENT type at the same node).
    public function agentTypeahead(Request $request)
    {
        $nodeId = $request->get('node');
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $agents = DB::table('agents')
            ->where('is_deleted', false)
            ->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")
                    ->orWhere('agent_code', 'like', "%{$q}%");
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get(['agent_id', 'full_name', 'agent_code', 'phone', 'email']);

        return response()->json($agents);
    }

    public function store(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $request->validate([
            'practitioner_type_id'     => ['required', 'uuid', 'exists:cbe_practitioner_types,id'],
            'agent_id'                 => ['required', 'uuid', 'exists:agents,agent_id'],
            'slot_duration_minutes'    => ['required', 'integer', 'min:5', 'max:480'],
            'max_slots_per_day'        => ['required', 'integer', 'min:1', 'max:100'],
            'booking_window_days'      => ['required', 'integer', 'min:1', 'max:730'],
            'max_upcoming_per_member'  => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $exists = DB::table('cbe_practitioner_profiles')
            ->where('cbe_node_id', $nodeId)
            ->where('agent_id', $request->agent_id)
            ->where('practitioner_type_id', $request->practitioner_type_id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['agent_id' => __('cbe_masterfile.err_practitioner_already_exists')])->withInput();
        }

        DB::table('cbe_practitioner_profiles')->insert([
            'id'                        => (string) Str::uuid(),
            'cbe_node_id'               => $nodeId,
            'agent_id'                  => $request->agent_id,
            'practitioner_type_id'      => $request->practitioner_type_id,
            'slot_duration_minutes'     => $request->slot_duration_minutes,
            'max_slots_per_day'         => $request->max_slots_per_day,
            'booking_window_days'       => $request->booking_window_days,
            'max_upcoming_per_member'   => $request->max_upcoming_per_member,
            'is_active'                 => true,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        return redirect()->route('admin.practitioners.index', ['node' => $nodeId])->with('practitioner_saved', true);
    }

    public function edit(string $id)
    {
        $profile = DB::table('cbe_practitioner_profiles as p')
            ->join('agents as a', 'a.agent_id', '=', 'p.agent_id')
            ->join('cbe_practitioner_types as t', 't.id', '=', 'p.practitioner_type_id')
            ->where('p.id', $id)
            ->select([
                'p.*', 't.type_label', 'a.full_name', 'a.agent_code', 'a.phone', 'a.email',
            ])
            ->first();
        abort_if(! $profile, 404);

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $profile->cbe_node_id)->first();

        $weeklyHours = DB::table('cbe_practitioner_weekly_hours')
            ->where('practitioner_profile_id', $id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $leaveDates = DB::table('cbe_practitioner_leave_dates')
            ->where('practitioner_profile_id', $id)
            ->where('leave_date', '>=', now()->toDateString())
            ->orderBy('leave_date')
            ->get();

        return view('admin.practitioners.edit', compact('profile', 'node', 'weeklyHours', 'leaveDates'));
    }

    public function updateSettings(Request $request, string $id)
    {
        $profile = DB::table('cbe_practitioner_profiles')->where('id', $id)->first();
        abort_if(! $profile, 404);

        $request->validate([
            'slot_duration_minutes'    => ['required', 'integer', 'min:5', 'max:480'],
            'max_slots_per_day'        => ['required', 'integer', 'min:1', 'max:100'],
            'booking_window_days'      => ['required', 'integer', 'min:1', 'max:730'],
            'max_upcoming_per_member'  => ['required', 'integer', 'min:1', 'max:20'],
            'is_active'                => ['nullable', 'in:0,1'],
        ]);

        DB::table('cbe_practitioner_profiles')->where('id', $id)->update([
            'slot_duration_minutes'    => $request->slot_duration_minutes,
            'max_slots_per_day'        => $request->max_slots_per_day,
            'booking_window_days'      => $request->booking_window_days,
            'max_upcoming_per_member'  => $request->max_upcoming_per_member,
            'is_active'                => $request->boolean('is_active'),
            'updated_at'               => now(),
        ]);

        return redirect()->route('admin.practitioners.edit', $id)->with('practitioner_saved', true);
    }

    // Replace-all sync for the weekly hours grid — a practitioner's own
    // recurring hours, day by day, with an "Add Time Block" per day so
    // a day can have more than one open block (e.g. morning + evening).
    public function updateWeeklyHours(Request $request, string $id)
    {
        $profile = DB::table('cbe_practitioner_profiles')->where('id', $id)->first();
        abort_if(! $profile, 404);

        $request->validate([
            'hours'                => ['nullable', 'array'],
            'hours.*.day_of_week'  => ['required', 'integer', 'min:0', 'max:6'],
            'hours.*.start_time'   => ['required'],
            'hours.*.end_time'     => ['required', 'after:hours.*.start_time'],
        ]);

        DB::table('cbe_practitioner_weekly_hours')->where('practitioner_profile_id', $id)->delete();

        foreach ($request->input('hours', []) as $row) {
            if (empty($row['start_time']) || empty($row['end_time'])) {
                continue;
            }
            DB::table('cbe_practitioner_weekly_hours')->insert([
                'id'                       => (string) Str::uuid(),
                'practitioner_profile_id'  => $id,
                'day_of_week'              => (int) $row['day_of_week'],
                'start_time'               => $row['start_time'],
                'end_time'                 => $row['end_time'],
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }

        return redirect()->route('admin.practitioners.edit', $id)->with('practitioner_hours_saved', true);
    }

    // Per Chris: "do you allow them to declare off day or on leave or
    // not available date." One blocked date at a time — closes that
    // whole day regardless of the weekly hours above.
    public function addLeaveDate(Request $request, string $id)
    {
        $profile = DB::table('cbe_practitioner_profiles')->where('id', $id)->first();
        abort_if(! $profile, 404);

        $request->validate([
            'leave_date'  => ['required', 'date', 'after_or_equal:today'],
            'reason'      => ['nullable', 'string', 'max:150'],
        ]);

        $exists = DB::table('cbe_practitioner_leave_dates')
            ->where('practitioner_profile_id', $id)
            ->where('leave_date', $request->leave_date)
            ->exists();
        if (! $exists) {
            DB::table('cbe_practitioner_leave_dates')->insert([
                'id'                       => (string) Str::uuid(),
                'practitioner_profile_id'  => $id,
                'leave_date'               => $request->leave_date,
                'reason'                   => $request->reason ?: null,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }

        return redirect()->route('admin.practitioners.edit', $id)->with('practitioner_hours_saved', true);
    }

    public function removeLeaveDate(string $id, string $leaveId)
    {
        DB::table('cbe_practitioner_leave_dates')
            ->where('id', $leaveId)
            ->where('practitioner_profile_id', $id)
            ->delete();

        return redirect()->route('admin.practitioners.edit', $id)->with('practitioner_hours_saved', true);
    }

    // Safety check mirrors saveCbeLevels()'s pattern elsewhere in the
    // app — never silently orphan a member's existing upcoming booking.
    public function destroy(Request $request, string $id)
    {
        $profile = DB::table('cbe_practitioner_profiles')->where('id', $id)->first();
        abort_if(! $profile, 404);

        $hasUpcoming = DB::table('cbe_appointment_bookings')
            ->where('practitioner_profile_id', $id)
            ->where('status', 'CONFIRMED')
            ->where('booking_date', '>=', now()->toDateString())
            ->exists();
        if ($hasUpcoming) {
            return back()->with('practitioner_delete_blocked', __('cbe_masterfile.err_practitioner_has_upcoming_bookings'));
        }

        DB::table('cbe_practitioner_profiles')->where('id', $id)->delete();

        return redirect()->route('admin.practitioners.index', ['node' => $profile->cbe_node_id])->with('practitioner_saved', true);
    }
}
