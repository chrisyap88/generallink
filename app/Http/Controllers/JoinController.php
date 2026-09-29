<?php

// NEW 28 Sep 2026 — per Chris (member file item 31): every entity has its own
// QR code. Scanning it opens /join/{code}:
//  - logged in  → tick Member / Follower / Believer / Volunteer → Join → the
//                 lines are ACTIVE at once in his Affiliation Register (How: QR).
//  - not logged in → Log in, or Register (the registration duplicate rule
//                 applies); after registering he joins the entity as Follower
//                 at once, and can add more types later.
// Staff print the QR from the entity's Members tab (Join QR).

namespace App\Http\Controllers;

use App\Services\MemberFileService as MF;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JoinController extends Controller
{
    public const JOIN_TYPES = ['MEMBER', 'FOLLOWER', 'BELIEVER', 'VOLUNTEER'];

    private function node(string $token): object
    {
        $n = DB::table('cbe_hierarchy_nodes as n')->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->where('n.join_token', $token)->first(['n.node_id', 'n.node_name', 'n.node_name_zh', 'n.join_token', 'n.city', 'g.group_name']);
        abort_if(! $n, 404);

        return $n;
    }

    public function show(Request $request, string $token)
    {
        $node = $this->node($token);
        $request->session()->put('pending_join_token', $token);
        $agent = Auth::guard('agent')->user();
        $have = $agent ? array_keys(MF::existingAt($agent->agent_id, $node->node_id)) : [];
        $labels = MF::typeLabels();

        return view('join.show', compact('node', 'agent', 'have', 'labels') + ['types' => self::JOIN_TYPES]);
    }

    public function join(Request $request, string $token)
    {
        $node = $this->node($token);
        $agent = Auth::guard('agent')->user();
        abort_if(! $agent, 403);
        $types = array_values(array_intersect((array) $request->get('types', []), self::JOIN_TYPES));
        $n = $types ? MF::addTypes($agent->agent_id, $node->node_id, $types, null, 'QR') : 0;
        $request->session()->forget('pending_join_token');

        return redirect()->route('join.show', $token)->with('joined', $n);
    }

    // after a registration (new or claimed) that started from a QR page
    public static function finishPendingJoin(Request $request, string $agentId): void
    {
        $tok = $request->session()->pull('pending_join_token');
        if (! $tok) {
            return;
        }
        $nodeId = DB::table('cbe_hierarchy_nodes')->where('join_token', $tok)->value('node_id');
        if ($nodeId) {
            MF::addTypes($agentId, $nodeId, ['FOLLOWER'], null, 'QR');
        }
    }

    // Staff: the printable QR of one entity (Admin, or the entity's own officer)
    public function qr(Request $request, string $nodeId)
    {
        $agent = Auth::guard('agent')->user();
        $node = DB::table('cbe_hierarchy_nodes as n')->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->where('n.node_id', $nodeId)->first(['n.node_id', 'n.node_name', 'n.node_name_zh', 'n.join_token', 'n.hierarchy_path', 'g.group_name']);
        abort_if(! $node, 404);
        if ($agent->role !== 'ADMIN') {
            $mine = DB::table('cbe_node_officers')->where('agent_id', $agent->agent_id)->where('is_active', true)->pluck('node_id')->all();
            $ok = in_array($node->node_id, $mine, true) || collect($mine)->contains(fn ($m) => str_contains((string) $node->hierarchy_path, '/'.$m.'/'));
            abort_if(! $ok, 403);
        }
        if (! $node->join_token) {
            $node->join_token = strtolower(\Illuminate\Support\Str::random(12));
            DB::table('cbe_hierarchy_nodes')->where('node_id', $node->node_id)->update(['join_token' => $node->join_token]);
        }
        $url = route('join.show', $node->join_token);
        $qr = (new Builder(writer: new \Endroid\QrCode\Writer\PngWriter(), data: $url, size: 320, margin: 10))->build()->getDataUri();

        return view('join.qr', compact('node', 'url', 'qr'));
    }
}
