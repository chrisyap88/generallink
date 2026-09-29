<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CbeFaithTerminologyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 27 Aug 2026 — per Chris: "before search you should have the tap,
// after search you show the tap with (n) records not disappear from
// the screen when i drill in from temple tap. this apply to all tap."
// A temple-wide, search-first browse screen over every event
// participation record logged at this temple (Members and Customers
// alike) — same pattern as AdminCbeAppointmentsController's 7th tab.
// Reached as one of 3 always-visible tabs (Members / Participation /
// Appointments) that stay on screen together, before AND after a
// search, matching Chris's compulsory instruction that a tab bar must
// never disappear once you've drilled in from a temple.
class AdminCbeParticipationController extends Controller
{
    public function index(Request $request)
    {
        $nodeId = $request->get('node');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $node, 404);

        $mode = $request->get('mode', 'main');
        $doSearch = $mode === 'search' && $request->has('do_search');

        $results = null;
        if ($doSearch) {
            $q = DB::table('cbe_event_participants as p')
                ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
                ->leftJoin('agents as ag', 'ag.agent_id', '=', 'p.agent_id')
                ->leftJoin('customers as cu', 'cu.customer_id', '=', 'p.customer_id')
                // NEW 27 Aug 2026 — per Chris: donors also get event
                // participation/appointment history, same as Members/
                // Customers. This temple-wide screen now surfaces all
                // three owner types.
                ->leftJoin('cbe_donors as dn', 'dn.donor_id', '=', 'p.donor_id')
                ->leftJoin('cbe_group_memberships as gm', function ($j) use ($nodeId) {
                    $j->on('gm.agent_id', '=', 'p.agent_id')->where('gm.cbe_node_id', $nodeId);
                })
                ->leftJoin('cbe_receipts as r', function ($j) {
                    $j->on('r.source_id', '=', 'p.participant_record_id')->where('r.source_type', 'EVENT_SALE');
                })
                ->where('e.cbe_node_id', $nodeId);

            // NEW 27 Aug 2026 — per Chris: "why you didnt show what event,
            // event description when is the events." Added an explicit
            // Event picker (same idea as Appointments' appointment_type
            // dropdown) so results can be scoped to one specific event,
            // and pulled the event's date + description through so the
            // results list actually says which occasion each record
            // belongs to and when it happened.
            if ($v = trim((string) $request->get('event_id'))) {
                $q->where('p.event_id', $v);
            }
            if ($v = trim((string) $request->get('person_name'))) {
                $q->where(function ($w) use ($v) {
                    $w->where('ag.full_name', 'like', '%'.$v.'%')
                        ->orWhere('cu.full_name', 'like', '%'.$v.'%')
                        ->orWhere('dn.donor_name', 'like', '%'.$v.'%');
                });
            }
            if ($v = trim((string) $request->get('item_name'))) {
                $q->where('p.item_name', 'like', '%'.$v.'%');
            }
            if ($v = trim((string) $request->get('date_from'))) {
                $q->where('p.paid_at', '>=', $v);
            }
            if ($v = trim((string) $request->get('date_to'))) {
                $q->where('p.paid_at', '<=', $v);
            }

            $results = $q->orderByDesc('p.paid_at')
                ->select(
                    'p.item_name', 'p.quantity', 'p.amount_paid', 'p.paid_at',
                    'e.event_name', 'e.event_name_zh', 'e.event_start_date', 'e.description as event_description',
                    'ag.full_name as agent_name', 'gm.membership_id',
                    'cu.full_name as customer_name', 'cu.customer_id',
                    'dn.donor_name', 'dn.donor_id',
                    'r.receipt_id'
                )
                ->paginate(10)
                ->withQueryString();
        }

        $events = DB::table('cbe_events')->where('cbe_node_id', $nodeId)->orderByDesc('event_start_date')->get(['event_id', 'event_name', 'event_name_zh', 'event_start_date']);

        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);

        // Needed by the persistent tab bar partial for the Appointments
        // tab's dynamic label (never hardcoded "Sensei"/"Appointments").
        $faithPracticeType = DB::table('group_labels')->where('group_label_id', $node->group_label_id)->value('faith_practice_type');
        $faithTerms = CbeFaithTerminologyService::terms($faithPracticeType);

        return view('admin.cbe-kpi.participation.index', array_merge([
            'node' => $node,
            'nodePrimary' => $primary,
            'nodeSecondary' => $secondary,
            'mode' => $mode,
            'doSearch' => $doSearch,
            'results' => $results,
            'events' => $events,
            'faithTerms' => $faithTerms,
            'tabCounts' => $this->tabCounts($nodeId),
        ], self::fromTabContext($request)));
    }

    // NEW 27 Aug 2026 — per Chris: the persistent tab bar's first tab
    // must reflect whichever of Members/Customers/Donors you actually
    // came from, not always default to Members. Shared by both this
    // controller and AdminCbeAppointmentsController since both screens
    // are reachable from all 3 origins via the ?from= query param the
    // persistent-tabs partial appends to its own links.
    public static function fromTabContext(Request $request): array
    {
        $from = in_array($request->get('from'), ['members', 'customers', 'donors'], true) ? $request->get('from') : 'members';
        $labelKeys = ['members' => 'members_title', 'customers' => 'customers_title', 'donors' => 'donors_title'];

        return [
            'primaryTabKey' => $from,
            'primaryTabLabel' => __('admin_cbe_directory.'.$labelKeys[$from]),
            'primaryTabRoute' => 'admin.cbe-kpi.'.$from,
        ];
    }

    // Shared by Members/Customers/Appointments/Participation index() so
    // the same persistent tab bar with the same live counts renders
    // identically no matter which of the 4 you land on first.
    public static function tabCounts(string $nodeId): array
    {
        $participationCount = DB::table('cbe_event_participants as p')
            ->join('cbe_events as e', 'e.event_id', '=', 'p.event_id')
            ->where('e.cbe_node_id', $nodeId)
            ->count();

        $appointmentCount = DB::table('cbe_appointments')->where('cbe_node_id', $nodeId)->count();

        return [
            'participationCount' => $participationCount,
            'appointmentCount' => $appointmentCount,
        ];
    }

    private function localizedNames(?string $nameEn, ?string $nameZh): array
    {
        if (app()->getLocale() === 'zh' && $nameZh) {
            return [$nameZh, $nameEn];
        }

        return [$nameEn, $nameZh];
    }
}
