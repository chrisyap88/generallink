<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeCommitteeAuthService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris ("yes build all this for me" —
// "WhatsApp/Email Blast" was one of the 7 approved secretarial gaps):
// a Secretary/officer tool to announce something to every active
// member of the entity at once.
//
// IMPORTANT — honest scope limit, told to Chris when this shipped:
// real WhatsApp sending needs a connected WhatsApp Business number
// (App\Services\Integrations\Communication\WhatsAppCloudConnector),
// and that credential is stored per-AGENT (each poster's own account
// via the Integration Hub) — there is no system-wide "send WhatsApp to
// this entity's members" capability today. Faking that would be
// dishonest, so this sends a real Email (via NotificationService,
// same Mail::raw the whole app already uses) + an in-app bell
// notification to every active member instead. If Chris later
// connects a WhatsApp Business number for the CBE side specifically,
// this is the one place to wire it in.
class CbeSecretarialBlastController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.blast.index', __('cbe_blast.page_title'), leafOnly: true);
        }

        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.dashboard');
        }

        $blasts = DB::table('cbe_secretarial_blasts')
            ->where('cbe_node_id', $nodeId)
            ->orderByDesc('created_at')
            ->paginate(8, ['*'], 'blastPage');

        return view('cbe.blast.index', ['blasts' => $blasts, 'hasNode' => true]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.blast.index');
        }

        $recipientCount = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.cbe_node_id', $nodeId)
            ->where('m.status', 'ACTIVE')
            ->where('a.is_deleted', false)
            ->count();

        return view('cbe.blast.create', compact('recipientCount'));
    }

    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.blast.index');
        }

        $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $recipients = DB::table('cbe_group_memberships as m')
            ->join('agents as a', 'a.agent_id', '=', 'm.agent_id')
            ->where('m.cbe_node_id', $nodeId)
            ->where('m.status', 'ACTIVE')
            ->where('a.is_deleted', false)
            ->get(['a.agent_id', 'a.full_name', 'a.email']);

        app(NotificationService::class)->notify(
            $recipients->all(),
            'CBE_SECRETARIAL_BLAST',
            $request->input('subject'),
            $request->input('message')
        );

        DB::table('cbe_secretarial_blasts')->insert([
            'blast_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'recipient_count' => $recipients->count(),
            'sent_by' => $agent->agent_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.blast.index')->with('success', __('cbe_blast.sent_note', ['count' => $recipients->count()]));
    }
}
