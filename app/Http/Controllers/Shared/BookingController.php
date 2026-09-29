<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 15 Sep 2026 — per Chris: member-facing appointment booking.
// A member picks a practitioner type (Sensei/Consultant/Legal Advisor),
// then a practitioner, then an open slot, computed live from that
// practitioner's own weekly hours minus leave dates minus already-
// booked slots minus their max-slots-per-day cap. Reachable by any
// logged-in agent role — scoping to the member's own CBE community is
// done here via cbe_group_memberships, same table Member/Consultant
// Maintenance already uses.
class BookingController extends Controller
{
    // Step 0: work out which CBE community/entity this member belongs
    // to. Most members belong to exactly one; if more than one, they
    // pick which one to book through (rare, but never assume the first).
    private function resolveMemberNode(Request $request): ?object
    {
        $agentId = auth('agent')->id();

        if ($nodeId = $request->get('node')) {
            $belongs = DB::table('cbe_group_memberships')->where('agent_id', $agentId)->where('cbe_node_id', $nodeId)->exists();
            if ($belongs) {
                return DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
            }
        }

        $nodeIds = DB::table('cbe_group_memberships')->where('agent_id', $agentId)->pluck('cbe_node_id');
        if ($nodeIds->count() === 1) {
            return DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeIds->first())->first();
        }

        return null;
    }

    public function index(Request $request)
    {
        $agentId = auth('agent')->id();
        $node = $this->resolveMemberNode($request);

        if (! $node) {
            $nodeIds = DB::table('cbe_group_memberships')->where('agent_id', $agentId)->pluck('cbe_node_id');
            if ($nodeIds->isEmpty()) {
                return view('booking.no-membership');
            }
            $nodes = DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $nodeIds)->orderBy('node_name')->get();
            return view('booking.node-picker', compact('nodes'));
        }

        $types = DB::table('cbe_practitioner_profiles as p')
            ->join('cbe_practitioner_types as t', 't.id', '=', 'p.practitioner_type_id')
            ->where('p.cbe_node_id', $node->node_id)
            ->where('p.is_active', true)
            ->groupBy('t.id', 't.type_label', 't.sort_order')
            ->orderBy('t.sort_order')
            ->select('t.id', 't.type_label', DB::raw('COUNT(*) as practitioner_count'))
            ->get();

        return view('booking.index', compact('node', 'types'));
    }

    public function practitioners(Request $request, string $typeId)
    {
        $node = $this->resolveMemberNode($request);
        abort_if(! $node, 404);

        $type = DB::table('cbe_practitioner_types')->where('id', $typeId)->first();
        abort_if(! $type, 404);

        $practitioners = DB::table('cbe_practitioner_profiles as p')
            ->join('agents as a', 'a.agent_id', '=', 'p.agent_id')
            ->where('p.cbe_node_id', $node->node_id)
            ->where('p.practitioner_type_id', $typeId)
            ->where('p.is_active', true)
            ->orderBy('a.full_name')
            ->select('p.id', 'a.full_name', 'a.agent_code')
            ->get();

        return view('booking.practitioners', compact('node', 'type', 'practitioners'));
    }

    public function calendar(Request $request, string $profileId)
    {
        $profile = $this->activeProfileOrFail($profileId);
        $agentId = auth('agent')->id();

        $upcomingCount = DB::table('cbe_appointment_bookings')
            ->where('practitioner_profile_id', $profileId)
            ->where('member_agent_id', $agentId)
            ->where('status', 'CONFIRMED')
            ->where('booking_date', '>=', now()->toDateString())
            ->count();

        $minDate = now()->toDateString();
        $maxDate = now()->addDays($profile->booking_window_days)->toDateString();

        return view('booking.calendar', compact('profile', 'minDate', 'maxDate', 'upcomingCount'));
    }

    // AJAX: open slots for one date, computed live — never stored.
    public function slots(Request $request, string $profileId)
    {
        $profile = $this->activeProfileOrFail($profileId);

        $date = $request->get('date');
        if (! $date || ! $this->dateWithinWindow($date, $profile)) {
            return response()->json(['slots' => [], 'reason' => 'out_of_window']);
        }

        $isLeave = DB::table('cbe_practitioner_leave_dates')
            ->where('practitioner_profile_id', $profileId)
            ->where('leave_date', $date)
            ->exists();
        if ($isLeave) {
            return response()->json(['slots' => [], 'reason' => 'leave']);
        }

        $slots = $this->computeSlotsForDate($profile, $date);

        return response()->json(['slots' => $slots]);
    }

    public function book(Request $request, string $profileId)
    {
        $profile = $this->activeProfileOrFail($profileId);
        $agentId = auth('agent')->id();

        $request->validate([
            'date'       => ['required', 'date'],
            'start_time' => ['required'],
            'end_time'   => ['required'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->dateWithinWindow($request->date, $profile)) {
            return back()->withErrors(['date' => __('booking.err_out_of_window')]);
        }

        $isLeave = DB::table('cbe_practitioner_leave_dates')
            ->where('practitioner_profile_id', $profileId)
            ->where('leave_date', $request->date)
            ->exists();
        if ($isLeave) {
            return back()->withErrors(['date' => __('booking.err_not_available')]);
        }

        // Re-derive the real slot list server-side rather than trusting
        // whatever the browser posted — this is what actually prevents
        // double-booking and off-hours booking, not the AJAX call above.
        $available = $this->computeSlotsForDate($profile, $request->date);
        $matched = collect($available)->first(fn ($s) => $s['start_time'] === $request->start_time && $s['end_time'] === $request->end_time);
        if (! $matched) {
            return back()->withErrors(['start_time' => __('booking.err_slot_taken')]);
        }

        $upcomingCount = DB::table('cbe_appointment_bookings')
            ->where('practitioner_profile_id', $profileId)
            ->where('member_agent_id', $agentId)
            ->where('status', 'CONFIRMED')
            ->where('booking_date', '>=', now()->toDateString())
            ->count();
        if ($upcomingCount >= $profile->max_upcoming_per_member) {
            return back()->withErrors(['date' => __('booking.err_max_upcoming', ['count' => $profile->max_upcoming_per_member])]);
        }

        DB::table('cbe_appointment_bookings')->insert([
            'id'                       => (string) Str::uuid(),
            'practitioner_profile_id'  => $profileId,
            'member_agent_id'          => $agentId,
            'booking_date'             => $request->date,
            'start_time'               => $request->start_time,
            'end_time'                 => $request->end_time,
            'status'                   => 'CONFIRMED',
            'notes'                    => $request->notes ?: null,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        return redirect()->route('my-appointments')->with('booking_confirmed', true);
    }

    public function myAppointments(Request $request)
    {
        $agentId = auth('agent')->id();

        $bookings = DB::table('cbe_appointment_bookings as b')
            ->join('cbe_practitioner_profiles as p', 'p.id', '=', 'b.practitioner_profile_id')
            ->join('agents as a', 'a.agent_id', '=', 'p.agent_id')
            ->join('cbe_practitioner_types as t', 't.id', '=', 'p.practitioner_type_id')
            ->where('b.member_agent_id', $agentId)
            ->orderByDesc('b.booking_date')
            ->orderByDesc('b.start_time')
            ->select([
                'b.id', 'b.booking_date', 'b.start_time', 'b.end_time', 'b.status', 'b.notes',
                't.type_label', 'a.full_name as practitioner_name',
            ])
            ->get();

        return view('booking.my-appointments', compact('bookings'));
    }

    public function cancel(Request $request, string $bookingId)
    {
        $agentId = auth('agent')->id();

        $booking = DB::table('cbe_appointment_bookings')->where('id', $bookingId)->where('member_agent_id', $agentId)->first();
        abort_if(! $booking, 404);

        if ($booking->status === 'CONFIRMED' && $booking->booking_date >= now()->toDateString()) {
            DB::table('cbe_appointment_bookings')->where('id', $bookingId)->update([
                'status' => 'CANCELLED',
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('my-appointments')->with('booking_cancelled', true);
    }

    private function activeProfileOrFail(string $profileId): object
    {
        $profile = DB::table('cbe_practitioner_profiles as p')
            ->join('agents as a', 'a.agent_id', '=', 'p.agent_id')
            ->join('cbe_practitioner_types as t', 't.id', '=', 'p.practitioner_type_id')
            ->where('p.id', $profileId)
            ->where('p.is_active', true)
            ->select('p.*', 'a.full_name', 't.type_label')
            ->first();
        abort_if(! $profile, 404);

        return $profile;
    }

    private function dateWithinWindow(string $date, object $profile): bool
    {
        $min = now()->startOfDay();
        $max = now()->addDays($profile->booking_window_days)->endOfDay();
        $d = Carbon::parse($date);

        return $d->greaterThanOrEqualTo($min) && $d->lessThanOrEqualTo($max);
    }

    // The actual availability calculation: weekly hours for that
    // weekday, sliced into slot_duration_minutes chunks, minus any
    // slot already CONFIRMED-booked, capped at max_slots_per_day.
    private function computeSlotsForDate(object $profile, string $date): array
    {
        $dayOfWeek = (int) Carbon::parse($date)->format('w');

        $hours = DB::table('cbe_practitioner_weekly_hours')
            ->where('practitioner_profile_id', $profile->id)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($hours->isEmpty()) {
            return [];
        }

        $booked = DB::table('cbe_appointment_bookings')
            ->where('practitioner_profile_id', $profile->id)
            ->where('booking_date', $date)
            ->where('status', 'CONFIRMED')
            ->pluck('start_time')
            ->map(fn ($t) => substr($t, 0, 5))
            ->all();

        $slots = [];
        $isToday = Carbon::parse($date)->isToday();
        $nowTime = now()->format('H:i');

        foreach ($hours as $block) {
            $cursor = Carbon::parse($date.' '.$block->start_time);
            $blockEnd = Carbon::parse($date.' '.$block->end_time);

            while ($cursor->copy()->addMinutes($profile->slot_duration_minutes)->lessThanOrEqualTo($blockEnd)) {
                $slotStart = $cursor->format('H:i');
                $slotEndObj = $cursor->copy()->addMinutes($profile->slot_duration_minutes);
                $slotEnd = $slotEndObj->format('H:i');

                $isPast = $isToday && $slotStart <= $nowTime;
                if (! in_array($slotStart, $booked, true) && ! $isPast) {
                    $slots[] = ['start_time' => $slotStart, 'end_time' => $slotEnd];
                }

                $cursor = $slotEndObj;

                if (count($slots) >= $profile->max_slots_per_day) {
                    break 2;
                }
            }
        }

        return $slots;
    }
}
