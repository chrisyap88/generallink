<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 22 Aug 2026 — per Chris: every CBE node (Temple, Branch, State, HQ)
// keeps its own meeting minutes as attachments. Scoped strictly to the
// logged-in agent's own cbe_node_id — a Temple secretary only ever sees
// their own Temple's minutes, never another Temple's or the whole tree's.
// Same file-upload shape as Admin\NoticeBoardController (local disk,
// mimes:jpg,jpeg,png,pdf, 8MB cap) so behaviour is consistent app-wide.
// Feeds into the Annual Report's Secretary Activity Report section
// (combined with cbe_activities) — see AnnualReportController.
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." Admin has no cbe_node_id of
// their own (that column only exists for officers), so this used to
// show as a locked "soon" placeholder for Admin. ResolvesCbeActiveNode
// lets Admin pick a CBE Group + entity via the shared picker and
// remembers that choice for the rest of the visit — see the trait for
// the full explanation.
class MeetingMinutesController extends Controller
{
    use ResolvesCbeActiveNode;

    // Same lookup CbeAccountingController uses — a node's group_label_id
    // (needed to scope Meeting Types the same way Finance Categories are
    // scoped: global defaults + this community's own additions).
    private function groupLabelFor(?string $nodeId): ?string
    {
        return $nodeId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id') : null;
    }

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        // UPDATED 17 Sep 2026 — per Chris (Meeting Notice/Agenda/Quorum
        // approved): an ordinary member needs to see SCHEDULED meetings
        // to RSVP, not just officers. Same member-safe fallback Notice
        // Board/Events already use.
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            // UPDATED 28 Aug 2026 — per Chris: "why display temple? no
            // need to display just display the persatuan name and
            // number of minutes at right end (2) means there is 2
            // minutes founds." leafOnly skips HQ/State/Branch (minutes
            // are only ever recorded at the entity itself); the count
            // resolver runs the EXACT same query the list below uses
            // (cbe_meeting_minutes.cbe_node_id), so the number shown
            // here always matches what you see after drilling in.
            return $this->renderCbeNodePicker('cbe.minutes.index', __('cbe_records.minutes_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_meeting_minutes')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $canManage = \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);

        $minutes = $nodeId
            ? DB::table('cbe_meeting_minutes')->where('cbe_node_id', $nodeId)
                ->orderByDesc('meeting_date')->paginate(8, ['*'], 'mmPage')
            : collect();

        return view('cbe.minutes.index', ['minutes' => $minutes, 'hasNode' => (bool) $nodeId, 'canManage' => $canManage]);
    }

    // UPDATED 28 Aug 2026 — per Chris: full 3-step wizard (Meeting
    // Details -> Attendees -> Numbered Content) replacing the old
    // 4-field form. Meeting types are admin-configurable (never a
    // hardcoded list, same rule as Finance Categories) — global defaults
    // plus this community's own additions. The attendee roster is pulled
    // from this entity's existing Member Maintenance list (cbe_group_
    // memberships) so the secretary ticks Attended/Not Attended instead
    // of retyping names; a "+ Add Guest" row on the same screen covers
    // non-member visitors.
    public function create()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.minutes.index');
        }

        $groupLabelId = $this->groupLabelFor($nodeId);
        $meetingTypes = DB::table('cbe_meeting_types')
            ->where('is_active', true)
            ->where(function ($q) use ($groupLabelId) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId);
            })
            ->orderBy('display_order')
            ->get();

        $members = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.cbe_node_id', $nodeId)
            ->where('a.is_deleted', false)
            ->orderBy('a.full_name')
            ->get(['a.agent_id', 'a.full_name']);

        return view('cbe.minutes.create', compact('meetingTypes', 'members'));
    }

    // UPDATED 28 Aug 2026 — per Chris: one POST at the very end (the
    // "Save" button on Step 3) carries all three steps' data together —
    // nothing is written to the DB until the secretary actually presses
    // Save, so there's never a stray half-finished minute. content_sections
    // arrives as a JSON string built client-side by the numbered-paragraph
    // editor; it's decoded and re-encoded here (never trusted verbatim)
    // so a malformed payload can't reach the database.
    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.minutes.index');
        }

        $request->validate([
            'meeting_date'       => ['required', 'date'],
            'meeting_time'       => ['nullable', 'date_format:H:i'],
            'meeting_end_time'   => ['nullable', 'date_format:H:i'],
            'venue'              => ['nullable', 'string', 'max:255'],
            'meeting_mode'       => ['nullable', 'in:ONLINE,PHYSICAL'],
            'meeting_link'       => ['nullable', 'string', 'max:500'],
            'meeting_type_id'    => ['nullable', 'uuid', 'exists:cbe_meeting_types,meeting_type_id'],
            'title'              => ['required', 'string', 'max:255'],
            'agenda'             => ['nullable', 'string', 'max:3000'],
            'summary'            => ['nullable', 'string', 'max:3000'],
            'quorum_required'    => ['nullable', 'integer', 'min:1', 'max:9999'],
            'attachment'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:8192'],
            'content_sections_json' => ['nullable', 'string'],
            'attended_agent_ids'    => ['nullable', 'array'],
            'attended_agent_ids.*'  => ['uuid'],
            'roster_agent_ids'      => ['nullable', 'array'],
            'roster_agent_ids.*'    => ['uuid'],
            'guest_names'           => ['nullable', 'array'],
            'guest_names.*'         => ['nullable', 'string', 'max:150'],
            'guest_attended'        => ['nullable', 'array'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-meeting-minutes', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        // Recompute the section/paragraph numbering server-side from the
        // submitted order — never trust numbers sent from the browser.
        $sections = [];
        $rawSections = json_decode((string) $request->input('content_sections_json', '[]'), true);
        if (is_array($rawSections)) {
            foreach (array_values($rawSections) as $si => $section) {
                $secNum = ($si + 1) . '.0';
                $paragraphs = [];
                foreach (array_values($section['paragraphs'] ?? []) as $pi => $para) {
                    $text = trim((string) ($para['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $paragraphs[] = ['number' => ($si + 1) . '.' . ($pi + 1), 'text' => $text];
                }
                $heading = trim((string) ($section['heading'] ?? ''));
                if ($heading === '' && empty($paragraphs)) {
                    continue;
                }
                $sections[] = ['number' => $secNum, 'heading' => $heading, 'paragraphs' => $paragraphs];
            }
        }

        $minuteId = (string) Str::uuid();

        DB::table('cbe_meeting_minutes')->insert([
            'minute_id'                 => $minuteId,
            'cbe_node_id'                => $nodeId,
            'meeting_type_id'            => $request->input('meeting_type_id') ?: null,
            'meeting_date'               => $request->input('meeting_date'),
            'meeting_time'               => $request->input('meeting_time') ?: null,
            'meeting_end_time'           => $request->input('meeting_end_time') ?: null,
            'venue'                      => $request->input('venue'),
            'meeting_mode'               => $request->input('meeting_mode') ?: 'PHYSICAL',
            'meeting_link'               => $request->input('meeting_link'),
            'title'                      => $request->input('title'),
            'agenda'                     => $request->input('agenda'),
            'summary'                    => $request->input('summary'),
            'quorum_required'            => $request->input('quorum_required') ?: null,
            'content_sections'           => ! empty($sections) ? json_encode($sections) : null,
            'attachment_path'            => $attachmentPath,
            'attachment_original_name'   => $attachmentName,
            'uploaded_by'                => $agent->agent_id,
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);

        // Attendees — every roster member gets a row (attended or not),
        // plus one row per named guest.
        $rosterIds = $request->input('roster_agent_ids', []);
        $attendedIds = $request->input('attended_agent_ids', []);
        $attendeeRows = [];
        foreach ($rosterIds as $memberAgentId) {
            $wasAttended = in_array($memberAgentId, $attendedIds, true);
            $attendeeRows[] = [
                'attendee_id'       => (string) Str::uuid(),
                'minute_id'         => $minuteId,
                'agent_id'          => $memberAgentId,
                'guest_name'        => null,
                'attended'          => $wasAttended,
                'attendance_status' => $wasAttended ? 'PRESENT' : 'ABSENT',
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }
        $guestNames = $request->input('guest_names', []);
        $guestAttended = $request->input('guest_attended', []);
        foreach ($guestNames as $gi => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $guestWasAttended = isset($guestAttended[$gi]);
            $attendeeRows[] = [
                'attendee_id'       => (string) Str::uuid(),
                'minute_id'         => $minuteId,
                'agent_id'          => null,
                'guest_name'        => $name,
                'attended'          => $guestWasAttended,
                'attendance_status' => $guestWasAttended ? 'PRESENT' : 'ABSENT',
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }
        if (! empty($attendeeRows)) {
            DB::table('cbe_meeting_minute_attendees')->insert($attendeeRows);
        }

        return redirect()->route('cbe.minutes.index')->with('success', __('cbe_records.minutes_saved'));
    }

    // NEW 28 Aug 2026 — per Chris: "what s the content of the minutes,
    // are you going to show the content if exist?" The typed summary was
    // being saved but never shown anywhere in the UI. This detail screen
    // shows date/title/summary plus the attachment link if one exists.
    public function show(string $minuteId)
    {
        $agent = auth('agent')->user();
        // UPDATED 17 Sep 2026 — same member-safe fallback as index()
        // above, so a member can open a SCHEDULED meeting to RSVP.
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $minute = DB::table('cbe_meeting_minutes')
            ->where('minute_id', $minuteId)
            ->where('cbe_node_id', $nodeId)
            ->firstOrFail();

        $meetingType = $minute->meeting_type_id
            ? DB::table('cbe_meeting_types')->where('meeting_type_id', $minute->meeting_type_id)->first()
            : null;

        $sections = $minute->content_sections ? json_decode($minute->content_sections, true) : [];

        $attendees = DB::table('cbe_meeting_minute_attendees as at')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'at.agent_id')
            ->where('at.minute_id', $minuteId)
            ->orderByDesc('at.attended')
            ->orderBy('a.full_name')
            ->get(['at.attendee_id', 'at.agent_id', 'at.guest_name', 'at.attended', 'at.duration_minutes', 'at.attendance_status', 'a.full_name']);

        $resolutions = DB::table('cbe_meeting_resolutions')
            ->where('minute_id', $minuteId)
            ->orderBy('created_at')
            ->get();

        $canManage = \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id);

        // NEW 17 Sep 2026 — Meeting Notice/Agenda/Quorum.
        $rsvpCounts = DB::table('cbe_meeting_rsvps')->where('minute_id', $minuteId)
            ->selectRaw('response, COUNT(*) as cnt')->groupBy('response')->pluck('cnt', 'response');
        $myRsvp = DB::table('cbe_meeting_rsvps')->where('minute_id', $minuteId)->where('agent_id', $agent->agent_id)->value('response');

        return view('cbe.minutes.show', ['minute' => $minute, 'meetingType' => $meetingType, 'sections' => $sections, 'attendees' => $attendees, 'resolutions' => $resolutions, 'canManage' => $canManage, 'rsvpCounts' => $rsvpCounts, 'myRsvp' => $myRsvp]);
    }

    // NEW 17 Sep 2026 — per Chris (Meeting Notice/Agenda/Quorum
    // approved): sends the meeting notice + agenda to every active
    // member of this entity, by Email + in-app (same NotificationService
    // the Announcement Blast uses). Officers + current Secretary only.
    // UPDATED 18 Sep 2026 — per Chris: this is now also the "7 days
    // before" availability notice (auto-triggered by the
    // cbe:send-meeting-availability-notices schedule — see
    // SendMeetingAvailabilityNotices), and now goes out by WhatsApp too,
    // not just Email/in-app — see CbeMeetingOtpNoticeService.
    public function sendNotice(string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->first();
        abort_if(! $minute, 404);

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        $count = app(\App\Services\CbeMeetingOtpNoticeService::class)->sendAvailabilityNotice($minute, $agent->agent_id);

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.notice_sent_note', ['count' => $count]));
    }

    // NEW 18 Sep 2026 — per Chris: "the secretary will initiate that
    // otp request" — a manual button, fired when the meeting (physical
    // or online) actually begins. Sends every roster attendee a
    // personal one-tap confirmation link by WhatsApp + Email + in-app.
    public function startAttendanceCheckin(string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->first();
        abort_if(! $minute, 404);

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        $count = app(\App\Services\CbeMeetingOtpNoticeService::class)->openAttendanceCheckin($minute, $agent->agent_id);

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.checkin_opened_note', ['count' => $count]));
    }

    // The member's side — tapping their personal link. Must be logged
    // in AS THEMSELVES (the attendee row this token belongs to), so one
    // member's link can never mark someone else present.
    public function checkinShow(string $token)
    {
        $agent = auth('agent')->user();
        $row = DB::table('cbe_meeting_minute_attendees')->where('checkin_token', $token)->first();
        if (! $row || $row->agent_id !== $agent->agent_id) {
            abort(404);
        }
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $row->minute_id)->first();
        $expired = ! $row->checkin_token_expires_at || \Carbon\Carbon::parse($row->checkin_token_expires_at)->isPast();

        return view('cbe.minutes.checkin', ['minute' => $minute, 'attendee' => $row, 'expired' => $expired, 'token' => $token]);
    }

    public function checkinConfirm(string $token)
    {
        $agent = auth('agent')->user();
        $row = DB::table('cbe_meeting_minute_attendees')->where('checkin_token', $token)->first();
        if (! $row || $row->agent_id !== $agent->agent_id) {
            abort(404);
        }
        if (! $row->checkin_token_expires_at || \Carbon\Carbon::parse($row->checkin_token_expires_at)->isPast()) {
            return redirect()->route('cbe.minutes.checkin.show', $token)->with('error', __('cbe_records.checkin_expired_note'));
        }

        DB::table('cbe_meeting_minute_attendees')->where('attendee_id', $row->attendee_id)->update([
            'attended' => true,
            'attendance_status' => 'PRESENT',
            'checkin_confirmed_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.minutes.checkin.show', $token)->with('success', __('cbe_records.checkin_confirmed_note'));
    }

    // NEW 17 Sep 2026 — any member can RSVP to a SCHEDULED meeting, same
    // one-row-per-(minute,agent) mechanic as Event RSVP.
    public function rsvp(Request $request, string $minuteId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate(['response' => ['required', 'in:GOING,NOT_GOING,MAYBE']]);

        $existing = DB::table('cbe_meeting_rsvps')->where('minute_id', $minuteId)->where('agent_id', $agent->agent_id)->first();
        if ($existing) {
            DB::table('cbe_meeting_rsvps')->where('rsvp_id', $existing->rsvp_id)->update([
                'response' => $request->input('response'),
                'responded_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('cbe_meeting_rsvps')->insert([
                'rsvp_id' => (string) Str::uuid(),
                'minute_id' => $minuteId,
                'agent_id' => $agent->agent_id,
                'response' => $request->input('response'),
                'responded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.rsvp_saved'));
    }

    // NEW 17 Sep 2026 — per Chris ("yes build all this for me" —
    // AGM/Resolution tracking was one of the 7 approved secretarial
    // gaps): records one resolution put to the floor and voted on at
    // this meeting. Any meeting type can carry a resolution (not only
    // AGM — an EGM or Committee Meeting can pass one too), so this is
    // not gated by meeting_type_id. Officers + current Secretary only.
    public function storeResolution(Request $request, string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->first();
        abort_if(! $minute, 404);

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        $request->validate([
            'resolution_text' => ['required', 'string', 'max:3000'],
            'votes_for' => ['required', 'integer', 'min:0'],
            'votes_against' => ['required', 'integer', 'min:0'],
            'votes_abstain' => ['required', 'integer', 'min:0'],
            'outcome' => ['required', 'in:PASSED,REJECTED,DEFERRED'],
        ]);

        DB::table('cbe_meeting_resolutions')->insert([
            'resolution_id' => (string) Str::uuid(),
            'minute_id' => $minuteId,
            'resolution_text' => $request->input('resolution_text'),
            'votes_for' => $request->input('votes_for'),
            'votes_against' => $request->input('votes_against'),
            'votes_abstain' => $request->input('votes_abstain'),
            'outcome' => $request->input('outcome'),
            'created_by' => $agent->agent_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.resolution_saved'));
    }

    public function download(string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')
            ->where('minute_id', $minuteId)
            ->where('cbe_node_id', $this->resolveCbeNodeId($agent))
            ->firstOrFail();

        if (! $minute->attachment_path || ! Storage::disk('local')->exists($minute->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($minute->attachment_path));
    }

    // NEW 28 Aug 2026 — per Chris: "you should have a meeting type...
    // you need to have add defined meeting type." Admin-configurable,
    // never a hardcoded list — same shape as Finance's Manage Categories
    // screen (cbe.finance.categories), seeded with sensible global
    // defaults by the migration but freely extendable per community.
    public function meetingTypes()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $this->groupLabelFor($nodeId);

        $types = DB::table('cbe_meeting_types')
            ->where(function ($q) use ($groupLabelId) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $groupLabelId);
            })
            ->orderBy('display_order')
            ->paginate(6, ['*'], 'mtPage');

        return view('cbe.minutes.meeting-types', compact('types'));
    }

    public function storeMeetingType(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        $groupLabelId = $this->groupLabelFor($nodeId);

        $request->validate([
            'type_name'    => ['required', 'string', 'max:150'],
            'type_name_zh' => ['nullable', 'string', 'max:150'],
        ]);

        DB::table('cbe_meeting_types')->insert([
            'meeting_type_id' => (string) Str::uuid(),
            'group_label_id'  => $groupLabelId,
            'type_name'       => $request->input('type_name'),
            'type_name_zh'    => $request->input('type_name_zh'),
            'is_active'       => true,
            'display_order'   => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return redirect()->route('cbe.minutes.meeting-types')->with('success', __('cbe_records.meeting_type_saved'));
    }

    public function deactivateMeetingType(string $meetingTypeId)
    {
        DB::table('cbe_meeting_types')->where('meeting_type_id', $meetingTypeId)->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', __('cbe_records.meeting_type_deactivated'));
    }

    // ===== NEW 18 Sep 2026 — per Chris's uploaded "Online Meeting
    // Attendance & Meeting Minutes System" requirement =====

    // Organizer enters actual Join/Leave time per attendee after the
    // meeting (real-time capture would need a public HTTPS webhook from
    // Zoom/Meet/Teams, which localhost XAMPP cannot receive — see chat).
    // Officers + current Secretary only.
    public function attendance(string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->firstOrFail();

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        $attendees = DB::table('cbe_meeting_minute_attendees as at')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'at.agent_id')
            ->where('at.minute_id', $minuteId)
            ->orderByDesc('at.attended')
            ->orderBy('a.full_name')
            ->get(['at.attendee_id', 'at.agent_id', 'at.guest_name', 'at.attended', 'at.duration_minutes', 'at.attendance_status', 'a.full_name']);

        return view('cbe.minutes.attendance', ['minute' => $minute, 'attendees' => $attendees]);
    }

    public function updateAttendance(Request $request, string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->firstOrFail();

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        // UPDATED 18 Sep 2026 — per Chris: "remove Join Time and Leave
        // Time completely... only record Participant Name, Attendance
        // Status, Attendance Duration." The organizer now types the
        // status and duration directly — no time-of-day entry at all.
        $request->validate([
            'attendee_id'   => ['nullable', 'array'],
            'attendee_id.*' => ['uuid'],
            'status'        => ['nullable', 'array'],
            'status.*'      => ['nullable', 'in:PRESENT,PARTIAL,ABSENT'],
            'duration'      => ['nullable', 'array'],
            'duration.*'    => ['nullable', 'integer', 'min:0', 'max:1440'],
        ]);

        $statuses = $request->input('status', []);
        $durations = $request->input('duration', []);

        foreach ($request->input('attendee_id', []) as $attendeeId) {
            $status = $statuses[$attendeeId] ?? 'ABSENT';
            $duration = $status === 'ABSENT' ? null : (($durations[$attendeeId] ?? '') !== '' ? (int) $durations[$attendeeId] : null);

            DB::table('cbe_meeting_minute_attendees')->where('attendee_id', $attendeeId)->where('minute_id', $minuteId)->update([
                'duration_minutes'  => $duration,
                'attendance_status' => $status,
                'attended'          => $status !== 'ABSENT',
                'updated_at'        => now(),
            ]);
        }

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.attendance_saved'));
    }

    // Upload the meeting summary/transcript/notes, then immediately ask
    // the AI to organize it into structured minutes (Discussion,
    // Decisions, Action Items, Other Matters, Next Meeting). The
    // organizer still has to Approve before it counts as final — see
    // approveMinutes() below.
    public function aiDraft(Request $request, string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->firstOrFail();

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        $request->validate([
            'transcript' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt', 'max:8192'],
            'pasted_notes' => ['nullable', 'string', 'max:20000'],
        ]);

        $transcriptPath = $minute->transcript_path;
        $transcriptName = $minute->transcript_original_name;
        if ($request->hasFile('transcript')) {
            $file = $request->file('transcript');
            $transcriptPath = $file->store('cbe-meeting-transcripts', 'local');
            $transcriptName = $file->getClientOriginalName();
            DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->update([
                'transcript_path' => $transcriptPath,
                'transcript_original_name' => $transcriptName,
                'updated_at' => now(),
            ]);
        }

        $transcriptForAi = null;
        if ($transcriptPath && Storage::disk('local')->exists($transcriptPath)) {
            $mime = Storage::disk('local')->mimeType($transcriptPath) ?: 'application/octet-stream';
            if (in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
                $transcriptForAi = ['path' => Storage::disk('local')->path($transcriptPath), 'mime' => $mime, 'name' => $transcriptName];
            }
        }

        $attendedNames = DB::table('cbe_meeting_minute_attendees as at')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'at.agent_id')
            ->where('at.minute_id', $minuteId)
            ->where('at.attended', true)
            ->get(['a.full_name', 'at.guest_name'])
            ->map(fn ($r) => $r->full_name ?? $r->guest_name)
            ->filter()
            ->implode(', ');

        $result = app(\App\Services\CbeMeetingMinutesAiDraftService::class)->draft(
            [
                'title' => $minute->title,
                'agenda' => $minute->agenda ?? '',
                'date' => \Carbon\Carbon::parse($minute->meeting_date)->format('d M Y'),
                'attendees' => $attendedNames ?: 'Not recorded',
            ],
            $transcriptForAi,
            $request->input('pasted_notes')
        );

        if ($result['status'] !== 'OK') {
            return redirect()->route('cbe.minutes.show', $minuteId)->with('error', $result['message']);
        }

        $sections = [];
        foreach ($result['sections'] as $i => $s) {
            $sections[] = [
                'number' => ($i + 1) . '.0',
                'heading' => $s['heading'],
                'paragraphs' => [['number' => ($i + 1) . '.1', 'text' => $s['text']]],
            ];
        }

        DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->update([
            'content_sections' => json_encode($sections),
            'ai_draft_status' => 'DRAFTED',
            'minutes_approved_at' => null,
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.ai_draft_ready'));
    }

    // Organizer's manual edit of the minutes body after an AI draft (or
    // typed from scratch) — same numbered-section JSON shape store()
    // already uses, never trusted verbatim, recomputed server-side.
    public function updateSections(Request $request, string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->firstOrFail();

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        $request->validate(['content_sections_json' => ['nullable', 'string']]);

        $sections = [];
        $rawSections = json_decode((string) $request->input('content_sections_json', '[]'), true);
        if (is_array($rawSections)) {
            foreach (array_values($rawSections) as $si => $section) {
                $secNum = ($si + 1) . '.0';
                $paragraphs = [];
                foreach (array_values($section['paragraphs'] ?? []) as $pi => $para) {
                    $text = trim((string) ($para['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $paragraphs[] = ['number' => ($si + 1) . '.' . ($pi + 1), 'text' => $text];
                }
                $heading = trim((string) ($section['heading'] ?? ''));
                if ($heading === '' && empty($paragraphs)) {
                    continue;
                }
                $sections[] = ['number' => $secNum, 'heading' => $heading, 'paragraphs' => $paragraphs];
            }
        }

        DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->update([
            'content_sections' => ! empty($sections) ? json_encode($sections) : null,
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.minutes_saved'));
    }

    // The organizer's final sign-off — after this, the minutes are
    // treated as the approved record (shown on the PDF and the Annual
    // Report as final, not "draft").
    public function approveMinutes(string $minuteId)
    {
        $agent = auth('agent')->user();
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->firstOrFail();

        if (! \App\Services\CbeCommitteeAuthService::isAuthorizedManager($agent, $minute->cbe_node_id)) {
            return redirect()->route('cbe.minutes.show', $minuteId);
        }

        DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->update([
            'ai_draft_status' => 'APPROVED',
            'minutes_approved_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.minutes.show', $minuteId)->with('success', __('cbe_records.minutes_approved'));
    }

    public function attendanceReportPdf(string $minuteId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $attendees = DB::table('cbe_meeting_minute_attendees as at')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'at.agent_id')
            ->where('at.minute_id', $minuteId)
            ->orderByDesc('at.attended')
            ->orderBy('a.full_name')
            ->get(['at.agent_id', 'at.guest_name', 'at.duration_minutes', 'at.attendance_status', 'a.full_name']);

        $nodeName = DB::table('cbe_hierarchy_nodes')->where('node_id', $minute->cbe_node_id)->value('node_name') ?? '';
        $organizer = DB::table('agents')->where('agent_id', $minute->uploaded_by)->value('full_name') ?? '';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cbe.minutes.pdf.attendance-report', [
            'minute' => $minute, 'attendees' => $attendees, 'nodeName' => $nodeName, 'organizer' => $organizer,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Attendance_Report_' . \Illuminate\Support\Str::slug($minute->title) . '_' . $minute->meeting_date . '.pdf');
    }

    public function minutesPdf(string $minuteId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $minute = DB::table('cbe_meeting_minutes')->where('minute_id', $minuteId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $sections = $minute->content_sections ? json_decode($minute->content_sections, true) : [];

        $attendees = DB::table('cbe_meeting_minute_attendees as at')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'at.agent_id')
            ->where('at.minute_id', $minuteId)
            ->orderByDesc('at.attended')
            ->orderBy('a.full_name')
            ->get(['at.agent_id', 'at.guest_name', 'at.attended', 'a.full_name']);

        $resolutions = DB::table('cbe_meeting_resolutions')->where('minute_id', $minuteId)->orderBy('created_at')->get();

        $nodeName = DB::table('cbe_hierarchy_nodes')->where('node_id', $minute->cbe_node_id)->value('node_name') ?? '';
        $organizer = DB::table('agents')->where('agent_id', $minute->uploaded_by)->value('full_name') ?? '';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cbe.minutes.pdf.minutes', [
            'minute' => $minute, 'sections' => $sections, 'attendees' => $attendees, 'resolutions' => $resolutions, 'nodeName' => $nodeName, 'organizer' => $organizer,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Meeting_Minutes_' . \Illuminate\Support\Str::slug($minute->title) . '_' . $minute->meeting_date . '.pdf');
    }
}
