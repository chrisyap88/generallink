<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeCommitteeAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: "i like to know the temple calendar what
// events their planning." Deliberately its own table
// (cbe_temple_calendar_events), separate from the platform-wide
// `calendar_events` table Admin\CalendarController manages (company
// holidays, visible to every agent) — this is one temple's own planned
// events. Every member reads; only officers/Secretary can add, edit, or
// remove an event — see CbeCommitteeAuthService.
class CbeTempleCalendarController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        // FIXED 17 Sep 2026 — per Chris: this screen must be readable by
        // every member, not just officers/Admin. resolveCbeNodeId() alone
        // only ever resolves for an officer or Admin; the member fallback
        // (via cbe_group_memberships) lives in resolveCbeNodeIdForMember().
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.temple-calendar.index', __('cbe_records.temple_calendar_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_temple_calendar_events')
                    ->whereIn('cbe_node_id', $nodeIds)->where('is_active', true)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);

        $month = $request->get('month', now()->format('Y-m'));
        $monthStart = \Carbon\Carbon::parse($month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $events = collect();
        if ($nodeId) {
            $events = DB::table('cbe_temple_calendar_events')
                ->where('cbe_node_id', $nodeId)->where('is_active', true)
                ->whereBetween('event_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->orderBy('event_date')->orderBy('event_time')
                ->get();
        }

        return view('cbe.temple-calendar.index', [
            'events' => $events, 'hasNode' => (bool) $nodeId, 'canManage' => $canManage,
            'month' => $monthStart->format('Y-m'), 'monthLabel' => $monthStart->translatedFormat('F Y'),
            'prevMonth' => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $monthStart->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function create()
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        return view('cbe.temple-calendar.create');
    }

    public function store(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        $request->validate([
            'title'       => ['required', 'string', 'max:150'],
            'event_date'  => ['required', 'date'],
            'event_time'  => ['nullable', 'date_format:H:i'],
            'event_type'  => ['required', 'in:FESTIVAL,MEETING,CEREMONY,OTHER'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::table('cbe_temple_calendar_events')->insert([
            'event_id'          => (string) Str::uuid(),
            'cbe_node_id'       => $nodeId,
            'title'             => $request->input('title'),
            'event_date'        => $request->input('event_date'),
            'event_time'        => $request->input('event_time'),
            'event_type'        => $request->input('event_type'),
            'description'       => $request->input('description'),
            'created_by_agent_id' => $agent->agent_id,
            'is_active'         => true,
            'created_at'        => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.temple-calendar.index', ['month' => \Carbon\Carbon::parse($request->input('event_date'))->format('Y-m')])
            ->with('success', __('cbe_records.temple_calendar_added_success'));
    }

    public function edit(string $eventId)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        $event = DB::table('cbe_temple_calendar_events')->where('event_id', $eventId)->where('cbe_node_id', $nodeId)->firstOrFail();

        return view('cbe.temple-calendar.edit', compact('event'));
    }

    public function update(Request $request, string $eventId)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        DB::table('cbe_temple_calendar_events')->where('event_id', $eventId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate([
            'title'       => ['required', 'string', 'max:150'],
            'event_date'  => ['required', 'date'],
            'event_time'  => ['nullable', 'date_format:H:i'],
            'event_type'  => ['required', 'in:FESTIVAL,MEETING,CEREMONY,OTHER'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::table('cbe_temple_calendar_events')->where('event_id', $eventId)->update([
            'title'       => $request->input('title'),
            'event_date'  => $request->input('event_date'),
            'event_time'  => $request->input('event_time'),
            'event_type'  => $request->input('event_type'),
            'description' => $request->input('description'),
            'updated_at'  => now(),
        ]);

        return redirect()->route('cbe.temple-calendar.index', ['month' => \Carbon\Carbon::parse($request->input('event_date'))->format('Y-m')])
            ->with('success', __('cbe_records.temple_calendar_updated_success'));
    }

    public function destroy(Request $request, string $eventId)
    {
        $agent = Auth::guard('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        abort_unless(CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId), 403, __('cbe_records.not_authorized_note'));

        $updated = DB::table('cbe_temple_calendar_events')->where('event_id', $eventId)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => false, 'updated_at' => now()]);
        abort_if($updated === 0, 404);

        return back()->with('success', __('cbe_records.temple_calendar_deleted_success'));
    }
}
