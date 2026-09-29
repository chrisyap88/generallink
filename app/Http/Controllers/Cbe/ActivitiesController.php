<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 22 Aug 2026 — per Chris: activities (events, festivals, outreach)
// held by a CBE node, kept as attachments. Mirrors MeetingMinutesController
// exactly on purpose — the Annual Report's Secretary Activity Report
// combines both tables, sorted by date, into one Excel export.
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." See ResolvesCbeActiveNode.
class ActivitiesController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.activities.index', __('cbe_records.activities_page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_activities')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $activities = $nodeId
            ? DB::table('cbe_activities')->where('cbe_node_id', $nodeId)
                ->orderByDesc('activity_date')->paginate(8, ['*'], 'actPage')
            : collect();

        return view('cbe.activities.index', ['activities' => $activities, 'hasNode' => (bool) $nodeId]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        if (! $this->resolveCbeNodeId($agent)) {
            return redirect()->route('cbe.activities.index');
        }
        return view('cbe.activities.create');
    }

    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeId($agent);
        if (! $nodeId) {
            return redirect()->route('cbe.activities.index');
        }

        $request->validate([
            'activity_date' => ['required', 'date'],
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:3000'],
            'attachment'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:8192'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('cbe-activities', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        DB::table('cbe_activities')->insert([
            'activity_id'                => (string) Str::uuid(),
            'cbe_node_id'                 => $nodeId,
            'activity_date'               => $request->input('activity_date'),
            'title'                       => $request->input('title'),
            'description'                 => $request->input('description'),
            'attachment_path'             => $attachmentPath,
            'attachment_original_name'    => $attachmentName,
            'uploaded_by'                 => $agent->agent_id,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        return redirect()->route('cbe.activities.index')->with('success', __('cbe_records.activities_saved'));
    }

    public function download(string $activityId)
    {
        $agent = auth('agent')->user();
        $activity = DB::table('cbe_activities')
            ->where('activity_id', $activityId)
            ->where('cbe_node_id', $this->resolveCbeNodeId($agent))
            ->firstOrFail();

        if (! $activity->attachment_path || ! Storage::disk('local')->exists($activity->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($activity->attachment_path));
    }
}
