<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CbeFaithTerminologyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 26 Aug 2026, 20th pass — per Chris: "any appointment with temple
// for prayer and advise from sensei (temple resident advisor for
// counseling purpose etc), all this is important to the temple/club...
// have you incorporate?" 7th tab — a search-first browse screen over
// every appointment logged at this temple (Members and Customers
// alike), every field its own search criterion. The actual "Log
// Appointment" entry form lives on each person's own Member/Customer
// profile screen (per Chris's answer to also show it there) — this
// screen is the temple-wide view across everyone, linking each result
// back to that person's full profile.
class AdminCbeAppointmentsController extends Controller
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
            $q = DB::table('cbe_appointments as ap')
                ->join('agents as adv', 'adv.agent_id', '=', 'ap.advisor_id')
                ->leftJoin('agents as ag', 'ag.agent_id', '=', 'ap.agent_id')
                ->leftJoin('customers as cu', 'cu.customer_id', '=', 'ap.customer_id')
                // NEW 27 Aug 2026 — per Chris: donors also get appointment
                // history with the temple's advisor, same as Members/
                // Customers.
                ->leftJoin('cbe_donors as dn', 'dn.donor_id', '=', 'ap.donor_id')
                ->leftJoin('cbe_group_memberships as gm', function ($j) use ($nodeId) {
                    $j->on('gm.agent_id', '=', 'ap.agent_id')->where('gm.cbe_node_id', $nodeId);
                })
                ->where('ap.cbe_node_id', $nodeId);

            if ($v = trim((string) $request->get('person_name'))) {
                $q->where(function ($w) use ($v) {
                    $w->where('ag.full_name', 'like', '%'.$v.'%')
                        ->orWhere('cu.full_name', 'like', '%'.$v.'%')
                        ->orWhere('dn.donor_name', 'like', '%'.$v.'%');
                });
            }
            if ($v = trim((string) $request->get('advisor_name'))) {
                $q->where('adv.full_name', 'like', '%'.$v.'%');
            }
            if ($v = $request->get('appointment_type')) {
                $q->where('ap.appointment_type', $v);
            }
            if ($v = trim((string) $request->get('date_from'))) {
                $q->where('ap.appointment_date', '>=', $v);
            }
            if ($v = trim((string) $request->get('date_to'))) {
                $q->where('ap.appointment_date', '<=', $v);
            }
            if ($v = trim((string) $request->get('notes'))) {
                $q->where('ap.notes', 'like', '%'.$v.'%');
            }

            $results = $q->orderByDesc('ap.appointment_date')
                ->select('ap.appointment_id', 'ap.appointment_type', 'ap.appointment_date', 'ap.notes', 'adv.full_name as advisor_name', 'ag.full_name as agent_name', 'gm.membership_id', 'cu.full_name as customer_name', 'cu.customer_id', 'dn.donor_name', 'dn.donor_id')
                ->paginate(10)
                ->withQueryString();
        }

        [$primary, $secondary] = $this->localizedNames($node->node_name, $node->node_name_zh);

        // CHANGED 12 Sep 2026 — per Chris: a community can now enable
        // SEVERAL appointment positions at once (e.g. Sensei AND Legal
        // Advisor together), not just one — see CbeFaithTerminologyService.
        // This temple-wide browse screen uses the single position's own
        // wording when there's only one, and a generic label when there
        // are several (since no one wording set fits them all). The
        // "Reason" filter below is built from every enabled position's
        // own admin-editable reason list, never a hardcoded set.
        $positions = CbeFaithTerminologyService::positionsForGroup($node->group_label_id);
        $faithTerms = count($positions) === 1 ? $positions[0] : CbeFaithTerminologyService::terms(null);
        $allReasons = collect($positions)->flatMap(fn ($p) => $p['reasons'])->unique()->values()->all();

        return view('admin.cbe-kpi.appointments.index', array_merge([
            'node' => $node,
            'nodePrimary' => $primary,
            'nodeSecondary' => $secondary,
            'mode' => $mode,
            'doSearch' => $doSearch,
            'results' => $results,
            'faithTerms' => $faithTerms,
            'allReasons' => $allReasons,
            // NEW 27 Aug 2026 — persistent Members/Participation/Appointments
            // tab bar counts, must stay visible before AND after search.
            'tabCounts' => AdminCbeParticipationController::tabCounts($nodeId),
        ], AdminCbeParticipationController::fromTabContext($request)));
    }

    private function localizedNames(?string $nameEn, ?string $nameZh): array
    {
        if (app()->getLocale() === 'zh' && $nameZh) {
            return [$nameZh, $nameEn];
        }

        return [$nameEn, $nameZh];
    }
}
