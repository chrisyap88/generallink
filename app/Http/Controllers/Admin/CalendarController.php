<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DataScopeService;
use App\Services\EspoCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalendarController extends Controller
{
    private function guardAdmin()
    {
        if (Auth::guard('agent')->user()->role !== 'ADMIN') {
            abort(403, 'Only Admin can create, edit, or delete calendar events.');
        }
    }

    private function rolePrefix($agent): string
    {
        return match ($agent->role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }

    // Shared viewing — every role sees this. Company-wide events stay
    // Admin-managed (read-only for everyone else); the agent's own
    // personal follow-up reminders and each policy's auto-computed
    // renewal reminder are now CONSOLIDATED onto this same view too, per
    // Chris — "all posted sales or prospect customer if has the renewal
    // reminder set up it should consolidate in the calendar and reminder
    // program". Those two are private/scoped to the logged-in agent,
    // never shared like the company events are.
    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $month = $request->get('month', now()->format('Y-m'));
        $year = substr($month, 0, 4);
        $mon = substr($month, 5, 2);

        $companyEvents = DB::table('calendar_events')
            ->where('is_active', true)
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $mon)
            ->get();

        $items = collect();
        foreach ($companyEvents as $e) {
            $items->push((object) [
                'source'      => 'ADMIN',
                'event_date'  => $e->event_date,
                'event_time'  => $e->event_time,
                'title'       => $e->title,
                'type_label'  => ucwords(strtolower($e->event_type)),
                'type_color'  => ['#E0F7FA', '#1565C0'],
                'description' => $e->description,
                'link'        => null,
                'event_id'    => $e->event_id,
            ]);
        }

        // Personal follow-up reminders — private to this agent.
        $personalReminders = DB::table('personal_reminders as pr')
            ->join('customers as c', 'pr.customer_id', '=', 'c.customer_id')
            ->leftJoin('customer_statuses as cs', 'c.status_id', '=', 'cs.status_id')
            ->where('pr.agent_id', $agent->agent_id)
            ->where('pr.is_deleted', false)
            ->where('pr.status', 'PENDING')
            ->whereYear('pr.reminder_date', $year)
            ->whereMonth('pr.reminder_date', $mon)
            ->select('pr.reminder_date', 'pr.reminder_type', 'pr.note', 'pr.customer_id', 'c.full_name as customer_name', 'cs.code as status_code')
            ->get();

        $reminderTypeLabels = ['CALL_FOLLOW_UP' => 'Call Follow-up', 'RENEWAL' => 'Renewal', 'OTHER' => 'Other'];
        $rolePrefix = $this->rolePrefix($agent);

        foreach ($personalReminders as $r) {
            $items->push((object) [
                'source'      => 'PERSONAL',
                'event_date'  => $r->reminder_date,
                'event_time'  => null,
                'title'       => 'Follow up: ' . $r->customer_name . ($r->status_code === 'PROSPECT' ? ' (Prospect)' : ''),
                'type_label'  => $reminderTypeLabels[$r->reminder_type] ?? $r->reminder_type,
                'type_color'  => ['#DBEAFE', '#1e40af'],
                'description' => $r->note,
                'link'        => route($rolePrefix . '.customers.show', $r->customer_id),
                'event_id'    => null,
            ]);
        }

        // Auto-computed policy renewal reminders — scoped to whatever
        // sales transactions this agent can see (DataScopeService).
        $scope = new DataScopeService();
        $renewalQuery = DB::table('insurance_renewal_schedules as r')
            ->join('sales_transactions as st', 'r.policy_id', '=', 'st.policy_id')
            ->join('customers as c', 'st.customer_id', '=', 'c.customer_id')
            ->where('st.is_deleted', false)
            ->whereNotNull('r.reminder_scheduled_date')
            ->whereYear('r.reminder_scheduled_date', $year)
            ->whereMonth('r.reminder_scheduled_date', $mon);
        $scope->applyToTransactions($renewalQuery, 'st');
        $renewalReminders = $renewalQuery
            ->select('r.reminder_scheduled_date', 'st.policy_id', 'st.document_reference_number', 'c.full_name as customer_name')
            ->get();

        foreach ($renewalReminders as $r) {
            $items->push((object) [
                'source'      => 'RENEWAL',
                'event_date'  => $r->reminder_scheduled_date,
                'event_time'  => null,
                'title'       => 'Renewal reminder: ' . $r->customer_name . ' (' . $r->document_reference_number . ')',
                'type_label'  => 'Policy Renewal',
                'type_color'  => ['#EDE9FE', '#5b21b6'],
                'description' => 'Automatic renewal reminder sent to the customer for this policy.',
                'link'        => route($rolePrefix . '.sales-transactions.show', $r->policy_id),
                'event_id'    => null,
            ]);
        }

        // NEW 25 Jul 2026 — per Chris: "does it update Calendar and
        // reminder under Action Center." Broadcast Campaigns (Growth &
        // Outreach Center) are Admin-only, so only surface them here for
        // Admin — shows when each scheduled/recurring campaign is next
        // due to fire, read-only (managed from the Broadcast Campaigns
        // screen itself, not editable from Calendar).
        if ($agent->role === 'ADMIN') {
            $broadcasts = DB::table('broadcast_campaigns as bc')
                ->join('growth_channels as gc', 'bc.channel_code', '=', 'gc.channel_code')
                ->where('bc.status', 'SCHEDULED')
                ->whereYear('bc.scheduled_at', $year)
                ->whereMonth('bc.scheduled_at', $mon)
                ->select('bc.campaign_id', 'bc.title', 'bc.scheduled_at', 'bc.recurrence_type', 'gc.channel_name')
                ->get();

            foreach ($broadcasts as $b) {
                $recurLabel = match ($b->recurrence_type) {
                    'DAILY'  => ' (repeats daily)',
                    'WEEKLY' => ' (repeats weekly)',
                    default  => '',
                };
                $items->push((object) [
                    'source'      => 'BROADCAST',
                    'event_date'  => \Illuminate\Support\Carbon::parse($b->scheduled_at)->toDateString(),
                    'event_time'  => \Illuminate\Support\Carbon::parse($b->scheduled_at)->format('H:i'),
                    'title'       => 'Broadcast: ' . $b->title . ' via ' . $b->channel_name . $recurLabel,
                    'type_label'  => 'Broadcast Campaign',
                    'type_color'  => ['#FEF3C7', '#92400e'],
                    'description' => 'Scheduled outreach message — manage it from Growth & Outreach Center > Broadcast Campaigns.',
                    'link'        => route('admin.growth.broadcasts.index'),
                    'event_id'    => null,
                ]);
            }
        }

        $events = $items->sortBy('event_date')->values();

        $isAdmin = $agent->role === 'ADMIN';

        return view('calendar.index', compact('events', 'month', 'isAdmin'));
    }

    public function create()
    {
        $this->guardAdmin();
        return view('calendar.edit', ['event' => null]);
    }

    public function edit(string $id)
    {
        $this->guardAdmin();
        $event = DB::table('calendar_events')->where('event_id', $id)->firstOrFail();
        return view('calendar.edit', compact('event'));
    }

    public function store(Request $request, EspoCrmService $espoCrm)
    {
        $this->guardAdmin();

        $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date'  => ['required', 'date'],
            'event_time'  => ['nullable', 'date_format:H:i'],
            'event_type'  => ['required', 'in:BRIEFING,TRAINING,NEWS,OTHER'],
        ]);

        $eventId = (string) Str::uuid();

        // NEW 29 Jul 2026 — EspoCRM integration (task #251), part 2.
        // Mirror this company-wide event as a Meeting in EspoCRM, same
        // background-sync pattern as personal reminders -> Task. Never
        // blocks saving the GeneralLink event if EspoCRM is unreachable.
        $startAt = $request->event_date.' '.($request->event_time ?? '09:00');
        $espoMeetingId = $espoCrm->createCalendarEvent($request->title, $startAt, null, $request->description);

        DB::table('calendar_events')->insert([
            'event_id'    => $eventId,
            'title'       => $request->title,
            'description' => $request->description,
            'espocrm_meeting_id' => $espoMeetingId,
            'event_date'  => $request->event_date,
            'event_time'  => $request->event_time,
            'event_type'  => $request->event_type,
            'created_by'  => Auth::guard('agent')->id(),
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('calendar.index')->with('success', 'Event created successfully.');
    }

    public function update(Request $request, string $id, EspoCrmService $espoCrm)
    {
        $this->guardAdmin();

        $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date'  => ['required', 'date'],
            'event_time'  => ['nullable', 'date_format:H:i'],
            'event_type'  => ['required', 'in:BRIEFING,TRAINING,NEWS,OTHER'],
        ]);

        $existing = DB::table('calendar_events')->where('event_id', $id)->first();

        // NEW 29 Jul 2026 — EspoCRM integration (task #251), part 2.
        $startAt = $request->event_date.' '.($request->event_time ?? '09:00');
        if ($existing && !empty($existing->espocrm_meeting_id)) {
            $espoCrm->updateCalendarEvent($existing->espocrm_meeting_id, $request->title, $startAt, null, $request->description);
        }

        DB::table('calendar_events')->where('event_id', $id)->update([
            'title'       => $request->title,
            'description' => $request->description,
            'event_date'  => $request->event_date,
            'event_time'  => $request->event_time,
            'event_type'  => $request->event_type,
            'updated_at'  => now(),
        ]);

        return redirect()->route('calendar.index')->with('success', 'Event updated successfully.');
    }

    public function destroy(string $id, EspoCrmService $espoCrm)
    {
        $this->guardAdmin();

        // NEW 29 Jul 2026 — EspoCRM integration (task #251), part 2.
        $existing = DB::table('calendar_events')->where('event_id', $id)->first();
        if ($existing && !empty($existing->espocrm_meeting_id)) {
            $espoCrm->deleteCalendarEvent($existing->espocrm_meeting_id);
        }

        DB::table('calendar_events')->where('event_id', $id)->update(['is_active' => false, 'updated_at' => now()]);
        return redirect()->route('calendar.index')->with('success', 'Event removed successfully.');
    }
}
