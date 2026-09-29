<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeCommitteeAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris ("yes build all this for me" —
// Correspondence Register was one of the 7 approved secretarial gaps):
// a log of incoming/outgoing official letters and communications, per
// entity. Every member can read; only officers + the current Secretary
// can log a new entry.
class CbeCorrespondenceController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.correspondences.index', __('cbe_correspondence.page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_correspondences')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);

        $correspondences = $nodeId
            ? DB::table('cbe_correspondences')->where('cbe_node_id', $nodeId)
                ->orderByDesc('correspondence_date')->paginate(8, ['*'], 'corrPage')
            : collect();

        return view('cbe.correspondences.index', [
            'correspondences' => $correspondences,
            'hasNode' => (bool) $nodeId,
            'canManage' => $canManage,
        ]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.correspondences.index');
        }

        return view('cbe.correspondences.create');
    }

    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.correspondences.index');
        }

        $request->validate([
            'direction' => ['required', 'in:IN,OUT'],
            'correspondent_name' => ['required', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'correspondence_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:8192'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-correspondences', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        DB::table('cbe_correspondences')->insert([
            'correspondence_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'direction' => $request->input('direction'),
            'correspondent_name' => $request->input('correspondent_name'),
            'subject' => $request->input('subject'),
            'summary' => $request->input('summary'),
            'correspondence_date' => $request->input('correspondence_date'),
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentName,
            'created_by' => $agent->agent_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.correspondences.index')->with('success', __('cbe_correspondence.saved_note'));
    }

    public function download(string $correspondenceId)
    {
        $agent = auth('agent')->user();
        $correspondence = DB::table('cbe_correspondences')
            ->where('correspondence_id', $correspondenceId)
            ->where('cbe_node_id', $this->resolveCbeNodeIdForMember($agent))
            ->firstOrFail();

        if (! $correspondence->attachment_path || ! Storage::disk('local')->exists($correspondence->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($correspondence->attachment_path));
    }
}
