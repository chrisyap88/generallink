<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeCommitteeAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris ("yes build all this for me" —
// Statutory Compliance Reminders was one of the 7 approved secretarial
// gaps): recurring per-entity obligations (annual return due date, AGM
// by X date, license renewal, etc). Recurs every year on the same
// month/day; CheckComplianceReminders (scheduled command) sends the
// actual reminder — this controller is just the CRUD screen, officers
// + current Secretary only (a compliance deadline is not something an
// ordinary member needs to see or manage).
class CbeComplianceItemController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.compliance-items.index', __('cbe_compliance.page_title'), leafOnly: true);
        }

        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.dashboard');
        }

        $items = DB::table('cbe_compliance_items')
            ->where('cbe_node_id', $nodeId)
            ->orderBy('due_month')->orderBy('due_day')
            ->get();

        return view('cbe.compliance.index', ['items' => $items, 'hasNode' => true]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.compliance-items.index');
        }

        return view('cbe.compliance.create');
    }

    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.compliance-items.index');
        }

        $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'due_month' => ['required', 'integer', 'min:1', 'max:12'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'reminder_lead_days' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        DB::table('cbe_compliance_items')->insert([
            'compliance_item_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'label' => $request->input('label'),
            'due_month' => $request->input('due_month'),
            'due_day' => $request->input('due_day'),
            'reminder_lead_days' => $request->input('reminder_lead_days'),
            'is_active' => true,
            'created_by' => $agent->agent_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.compliance-items.index')->with('success', __('cbe_compliance.saved_note'));
    }

    public function deactivate(string $itemId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.compliance-items.index');
        }

        DB::table('cbe_compliance_items')->where('compliance_item_id', $itemId)->where('cbe_node_id', $nodeId)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return redirect()->route('cbe.compliance-items.index')->with('success', __('cbe_compliance.deactivated_note'));
    }
}
