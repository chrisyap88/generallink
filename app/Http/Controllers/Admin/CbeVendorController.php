<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 13 Sep 2026 (Task #418) — per Chris: CBE Vendor Registration +
// Marketplace, Phase 2 (Vendor Registration + 4-eye entity approval).
// A vendor registers ONCE here (platform-wide), then separately picks
// which CBE entities (temples/clubs) to sell into — each entity pick
// needs a second, DIFFERENT agent to approve before it goes live
// (same maker-checker rule already used for GLADE Tier approvals and
// Bill Payment approvals: requested_by must never equal approved_by).
class CbeVendorController extends Controller
{
    // Vendor list + register form, same fixed-grid/Prev-Next/no-scroll
    // pattern as Program Library. Platform-Admin-only for now — vendor
    // self-registration (like GeneralLink's MLM vendor portal) is a
    // separate, bigger feature Chris hasn't asked for yet.
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = DB::table('cbe_vendors');
        if ($search !== '') {
            $query->where('vendor_name', 'like', '%' . $search . '%');
        }

        $vendors = $query->orderBy('vendor_name')->get();

        return view('masterfile.cbe-vendors', compact('vendors', 'search'));
    }

    // NEW 25 Sep 2026 -- per Chris: "a member can participate in
    // marketplace therefore he or she can be a vendor" -- same
    // search-and-link pattern already used for Add Customer/Member
    // Profile > Customer tab. Platform-wide search (this vendor
    // register isn't tied to one CBE node), same as Practitioner's own
    // agentTypeahead.
    public function agentTypeahead(Request $request)
    {
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

    // NEW 25 Sep 2026 -- per Chris: "what if the user key in the phone
    // number is exist in vendor or customer or membership..." -- warns
    // only, never blocks (Chris's explicit decision). Shared logic
    // lives in PersonPhoneDuplicateService so every screen checks the
    // exact same four tables the exact same way.
    public function phoneCheck(Request $request)
    {
        $phone = trim((string) $request->get('phone', ''));
        $hit = \App\Services\PersonPhoneDuplicateService::checkPerson($phone, 'VENDOR');

        return response()->json(['match' => $hit]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'agent_id' => ['nullable', 'uuid', 'exists:agents,agent_id'],
            'vendor_name' => ['required_without:agent_id', 'nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        // NEW 25 Sep 2026 -- when an existing Member was picked, their
        // name/phone/email come from the Agent record, never from what
        // was typed (those boxes are read-only on screen in that case)
        // -- same rule Add Customer already follows.
        $linkedAgentId = $request->input('agent_id') ?: null;
        $vendorName = $request->input('vendor_name');
        $vendorPhone = $request->input('phone');
        $vendorEmail = $request->input('email');
        if ($linkedAgentId) {
            $linkedAgent = DB::table('agents')->where('agent_id', $linkedAgentId)->first();
            abort_if(! $linkedAgent, 404);
            $vendorName = $linkedAgent->full_name;
            $vendorPhone = $linkedAgent->phone;
            $vendorEmail = $linkedAgent->email;
        }

        DB::table('cbe_vendors')->insert([
            'vendor_id' => (string) Str::uuid(),
            'agent_id' => $linkedAgentId,
            'vendor_name' => $vendorName,
            'contact_person' => $request->contact_person,
            'phone' => $vendorPhone,
            'email' => $vendorEmail,
            'address' => $request->address,
            'status' => 'ACTIVE',
            'created_by' => Auth::guard('agent')->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.masterfile.cbe-vendors')->with('success', __('cbe_vendors.registered_flash'));
    }

    // Vendor detail — its existing entity links (any status) and a
    // "Request New Entity" action that starts the shared group→node
    // picker (same one Member/Donor/Entity Maintenance already use).
    public function show(Request $request, string $vendorId)
    {
        $vendor = DB::table('cbe_vendors')->where('vendor_id', $vendorId)->first();
        abort_if(! $vendor, 404);

        $links = DB::table('cbe_vendor_node_links as l')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'l.cbe_node_id')
            ->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->where('l.vendor_id', $vendorId)
            ->orderByDesc('l.created_at')
            ->select('l.id', 'l.status', 'l.rejection_reason', 'l.created_at', 'n.node_name', 'n.node_name_zh', 'g.group_name')
            ->get();

        return view('masterfile.cbe-vendor-detail', compact('vendor', 'links'));
    }

    // Group→node picker for requesting a new entity — mirrors
    // AdminCbeMembersController's own picker fallback exactly, reusing
    // the same admin.cbe-kpi.node-picker view.
    public function requestNode(Request $request, string $vendorId)
    {
        $vendor = DB::table('cbe_vendors')->where('vendor_id', $vendorId)->first();
        abort_if(! $vendor, 404);

        $nodeId = $request->get('node');
        if ($nodeId) {
            $exists = DB::table('cbe_vendor_node_links')
                ->where('vendor_id', $vendorId)
                ->where('cbe_node_id', $nodeId)
                ->first();

            if (! $exists) {
                DB::table('cbe_vendor_node_links')->insert([
                    'id' => (string) Str::uuid(),
                    'vendor_id' => $vendorId,
                    'cbe_node_id' => $nodeId,
                    'status' => 'PENDING_APPROVAL',
                    'requested_by' => Auth::guard('agent')->id(),
                    'requested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($exists->status === 'REJECTED') {
                // Re-requesting after a rejection re-opens the SAME row
                // rather than creating a duplicate — clears the old
                // rejection reason and puts it back in the approval
                // queue.
                DB::table('cbe_vendor_node_links')->where('id', $exists->id)->update([
                    'status' => 'PENDING_APPROVAL',
                    'requested_by' => Auth::guard('agent')->id(),
                    'requested_at' => now(),
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => null,
                    'updated_at' => now(),
                ]);
            }

            return redirect()->route('admin.masterfile.cbe-vendors.show', $vendorId)->with('success', __('cbe_vendors.request_sent_flash'));
        }

        $groupId = $request->get('group');
        $groups = DB::table('group_labels')
            ->where('group_type', 'CBE')
            ->orderBy('group_name')
            ->get(['group_label_id', 'group_name']);

        $group = null;
        $nodes = collect();

        if ($groupId) {
            $group = DB::table('group_labels')->where('group_label_id', $groupId)->where('group_type', 'CBE')->first();
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
            'pickerRoute' => 'admin.masterfile.cbe-vendors.request-node',
            'pickerRouteParams' => ['vendor' => $vendorId],
            'pickerTitle' => __('cbe_vendors.request_entity_title'),
        ]);
    }

    // Approval queue — Admin sees every pending request; a CBE node
    // officer sees only requests for their own node. Same 4-eye rule
    // enforced in approve()/reject() below: the approver can never be
    // the same agent who made the request.
    public function approvals(Request $request)
    {
        $agent = Auth::guard('agent')->user();
        $officerNodeId = $agent && $agent->role !== 'ADMIN'
            ? DB::table('cbe_node_officers')->where('agent_id', $agent->agent_id)->where('is_active', true)->value('node_id')
            : null;

        $pending = DB::table('cbe_vendor_node_links as l')
            ->join('cbe_vendors as v', 'v.vendor_id', '=', 'l.vendor_id')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'l.cbe_node_id')
            ->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->join('agents as r', 'r.agent_id', '=', 'l.requested_by')
            ->where('l.status', 'PENDING_APPROVAL')
            ->when($officerNodeId, fn ($q) => $q->where('l.cbe_node_id', $officerNodeId))
            ->orderBy('l.requested_at')
            ->select('l.id', 'v.vendor_name', 'n.node_name', 'g.group_name', 'r.full_name as requested_by_name', 'l.requested_at', 'l.requested_by')
            ->get();

        return view('masterfile.cbe-vendor-approvals', compact('pending'));
    }

    public function approve(Request $request, string $linkId)
    {
        $link = DB::table('cbe_vendor_node_links')->where('id', $linkId)->first();
        abort_if(! $link || $link->status !== 'PENDING_APPROVAL', 404);

        $approverId = Auth::guard('agent')->id();
        if ($approverId === $link->requested_by) {
            return back()->withErrors(['approval' => __('cbe_vendors.err_same_person')]);
        }

        DB::table('cbe_vendor_node_links')->where('id', $linkId)->update([
            'status' => 'ACTIVE',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', __('cbe_vendors.approved_flash'));
    }

    public function reject(Request $request, string $linkId)
    {
        $request->validate(['rejection_reason' => ['nullable', 'string', 'max:255']]);

        $link = DB::table('cbe_vendor_node_links')->where('id', $linkId)->first();
        abort_if(! $link || $link->status !== 'PENDING_APPROVAL', 404);

        $approverId = Auth::guard('agent')->id();
        if ($approverId === $link->requested_by) {
            return back()->withErrors(['approval' => __('cbe_vendors.err_same_person')]);
        }

        DB::table('cbe_vendor_node_links')->where('id', $linkId)->update([
            'status' => 'REJECTED',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
            'updated_at' => now(),
        ]);

        return back()->with('success', __('cbe_vendors.rejected_flash'));
    }
}
