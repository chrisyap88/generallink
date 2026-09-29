<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 22 Aug 2026 — per Chris: the donor/sponsor master register, kept
// separate from any one Event so the same donor is reused across every
// event a Temple runs instead of being retyped each time.
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." See ResolvesCbeActiveNode.
// NOTE: this is the officer-facing "cbe.donors.*" register — a different,
// already-Admin-capable screen exists at admin.cbe-kpi.donors (see
// AdminCbeDonorsController), which is the wider Donor Maintenance/browse
// screen already fixed for Admin on 28 Aug 2026.
class DonorController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.donors.index', __('cbe_events.donors_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_donors')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $query = $nodeId ? DB::table('cbe_donors')->where('cbe_node_id', $nodeId) : null;
        if ($query && $request->filled('search')) {
            $query->where('donor_name', 'like', '%' . $request->get('search') . '%');
        }

        $donors = $query
            ? $query->orderBy('donor_name')->paginate(8, ['*'], 'donPage')->appends($request->only('search'))
            : collect();

        return view('cbe.donors.index', ['donors' => $donors, 'hasNode' => (bool) $nodeId, 'search' => $request->get('search', '')]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        if (! $this->resolveCbeNodeId($agent)) {
            return redirect()->route('cbe.donors.index');
        }
        return view('cbe.donors.create', ['returnToEvent' => request()->get('event')]);
    }

    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.donors.index');
        }

        $request->validate([
            'donor_name'      => ['required', 'string', 'max:255'],
            'donor_type'       => ['required', 'in:INDIVIDUAL,COMPANY,ORGANIZATION'],
            'contact_person'   => ['nullable', 'string', 'max:150'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:150'],
            'address'          => ['nullable', 'string', 'max:500'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $donorId = (string) Str::uuid();
        DB::table('cbe_donors')->insert([
            'donor_id'        => $donorId,
            'cbe_node_id'      => $nodeId,
            'donor_name'       => $request->input('donor_name'),
            'donor_type'       => $request->input('donor_type'),
            'contact_person'   => $request->input('contact_person'),
            'phone'            => $request->input('phone'),
            'email'            => $request->input('email'),
            'address'          => $request->input('address'),
            'notes'            => $request->input('notes'),
            'created_by'       => $agent->agent_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        // NEW 28 Sep 2026 — member file item 26: link to the person at once when Mobile / Email match exactly one member
        \App\Services\MemberFileService::autoLinkDonor($donorId);

        // Came here from "Add New Donor" mid-way through recording a
        // contribution — send them straight back to that form so they
        // don't lose their place.
        if ($request->filled('return_to_event')) {
            return redirect()->route('cbe.contributions.create', $request->input('return_to_event'))->with('success', __('cbe_events.donor_saved'));
        }

        return redirect()->route('cbe.donors.index')->with('success', __('cbe_events.donor_saved'));
    }
}
