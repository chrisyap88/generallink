<?php

// NEW 28 Sep 2026 — per Chris (member file item 26): donors / sponsors in the
// Donor Register are linked to ONE person (member file) or ONE company record,
// and duplicate donor records are merged.
//  First display: choose the CBE Group, then three folders:
//   Not Linked          — each donor with the suggested person / company
//                         (same Mobile / Email, else same name): Link, Pick
//                         another, or Add as New Member.
//   Linked              — donors already linked (Unlink if wrong).
//   Possible Duplicates — donor records that look like the same donor
//                         (same Mobile, Email or name): Merge into the first.
// A CBE officer sees only his own entities; Admin sees every CBE.

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MemberFileService as MF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminDonorLinkController extends Controller
{
    private function scope(): ?array
    {
        if (Auth::guard('agent')->user()?->role === 'ADMIN') {
            return null;
        }
        $nodes = DB::table('cbe_node_officers')->where('agent_id', Auth::guard('agent')->id())->where('is_active', true)->pluck('node_id')->all();
        abort_if(! $nodes, 403);

        return DB::table('cbe_hierarchy_nodes')->where(function ($w) use ($nodes) {
            $w->whereIn('node_id', $nodes);
            foreach ($nodes as $n) {
                $w->orWhere('hierarchy_path', 'like', '%/'.$n.'/%');
            }
        })->pluck('node_id')->all();
    }

    private function nodeIds(?string $groupId): array
    {
        $s = $this->scope();
        $ids = $groupId ? DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupId)->pluck('node_id')->all() : ($s ?? []);

        return $s === null ? $ids : array_values(array_intersect($ids ?: $s, $s));
    }

    public function index(Request $request)
    {
        $s = $this->scope();
        $groups = DB::table('group_labels')->where('group_type', 'CBE')
            ->when($s !== null, fn ($q) => $q->whereIn('group_label_id', DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $s ?: ['#'])->pluck('group_label_id')))
            ->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $group = $groups->firstWhere('group_label_id', $request->get('group')) ?? ($groups->count() === 1 ? $groups->first() : null);
        $folder = in_array($request->get('f'), ['linked', 'dups'], true) ? $request->get('f') : 'open';
        $rows = collect();
        $dups = collect();
        $counts = ['open' => 0, 'linked' => 0, 'dups' => 0];
        if ($group) {
            $ids = $this->nodeIds($group->group_label_id);
            $base = fn () => DB::table('cbe_donors as d')->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'd.cbe_node_id')->whereIn('d.cbe_node_id', $ids ?: ['#']);
            $isLinked = fn ($q) => $q->where(fn ($w) => $w->whereNotNull('d.agent_id')->orWhereNotNull('d.company_id'));
            $notLinked = fn ($q) => $q->whereNull('d.agent_id')->whereNull('d.company_id');
            $counts['open'] = $notLinked($base())->count();
            $counts['linked'] = $isLinked($base())->count();
            $dups = MF::donorDuplicateGroups($ids);
            $counts['dups'] = $dups->count();
            if ($folder !== 'dups') {
                $q = ($folder === 'linked' ? $isLinked($base()) : $notLinked($base()))
                    ->leftJoin('agents as a', 'a.agent_id', '=', 'd.agent_id')->leftJoin('member_companies as c', 'c.company_id', '=', 'd.company_id')
                    ->orderBy('d.donor_name');
                $per = max(3, min(50, (int) $request->get('per_page', 10)));
                $rows = $q->paginate($per, ['d.*', 'n.node_name', 'a.full_name as person_name', 'a.agent_code', 'c.company_name'])->withQueryString();
                $rows->getCollection()->transform(function ($d) {
                    $d->suggest = ($d->agent_id || $d->company_id) ? null : MF::donorSuggestion($d);
                    return $d;
                });
            }
        }

        return view('admin.donor-link.index', compact('groups', 'group', 'folder', 'rows', 'dups', 'counts'));
    }

    private function donorInScope(string $donorId): object
    {
        $d = DB::table('cbe_donors')->where('donor_id', $donorId)->first();
        $s = $this->scope();
        abort_if(! $d || ($s !== null && ! in_array($d->cbe_node_id, $s, true)), 404);

        return $d;
    }

    public function link(Request $request)
    {
        $d = $this->donorInScope((string) $request->get('donor_id'));
        if ($request->get('action') === 'unlink') {
            MF::linkDonor($d->donor_id, '', '');
        } elseif ($request->filled('company_id') || $request->filled('company_name')) {
            $cid = $request->filled('company_id') && DB::table('member_companies')->where('company_id', $request->company_id)->exists()
                ? $request->company_id
                : MF::saveCompany(['company_name' => $request->get('company_name') ?: $d->donor_name, 'phone' => $d->phone, 'email' => $d->email, 'address' => $d->address], Auth::guard('agent')->id());
            MF::linkDonor($d->donor_id, null, $cid);
        } else {
            $who = DB::table('agents')->where('is_deleted', false)->where(fn ($w) => $w->where('agent_id', (string) $request->get('agent_id'))->orWhere('agent_code', trim((string) $request->get('member'))))->value('agent_id');
            if (! $who) {
                return back()->with('link_error', __('member_file.pick_from_list'));
            }
            MF::linkDonor($d->donor_id, $who, null);
        }

        return back()->with('member_saved', __('member_file.donor_linked'));
    }

    public function merge(Request $request)
    {
        $keep = $this->donorInScope((string) $request->get('keep'));
        foreach ((array) $request->get('drop', []) as $drop) {
            $this->donorInScope((string) $drop);
            MF::mergeDonors($keep->donor_id, (string) $drop);
        }

        return back()->with('member_saved', __('member_file.donor_merged'));
    }
}
