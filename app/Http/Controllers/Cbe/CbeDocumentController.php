<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeCommitteeAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris ("yes build all this for me"): Document
// Repository — one place for an entity's Registration Certificate,
// Constitution/By-Laws, Financial Statements, AGM documents, and any
// other official paper. Every member can view/download; only officers
// + the current Secretary can upload/manage — same authorization rule
// as Notice Board/Messaging/Tickets (CbeCommitteeAuthService).
// Re-uploading under the same title bumps the version number and links
// back to the file it replaces, so nothing is ever silently overwritten.
class CbeDocumentController extends Controller
{
    use ResolvesCbeActiveNode;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.documents.index', __('cbe_documents.page_title'), leafOnly: true, countResolver: function (array $nodeIds) {
                return DB::table('cbe_documents')
                    ->whereIn('cbe_node_id', $nodeIds)
                    ->selectRaw('cbe_node_id, COUNT(*) as cnt')
                    ->groupBy('cbe_node_id')
                    ->pluck('cnt', 'cbe_node_id')
                    ->all();
            });
        }

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);

        $categories = DB::table('cbe_document_categories')->where('is_active', true)->orderBy('sort_order')->get();

        $query = $nodeId
            ? DB::table('cbe_documents as d')
                ->leftJoin('cbe_document_categories as c', 'c.id', '=', 'd.category_id')
                ->where('d.cbe_node_id', $nodeId)
            : null;

        if ($query && $request->filled('category_id')) {
            $query->where('d.category_id', $request->input('category_id'));
        }

        $documents = $query
            ? $query->orderByDesc('d.created_at')
                ->paginate(8, ['d.*', 'c.label as category_label'], 'docPage')
            : collect();

        return view('cbe.documents.index', [
            'documents' => $documents,
            'categories' => $categories,
            'hasNode' => (bool) $nodeId,
            'canManage' => $canManage,
            'selectedCategoryId' => $request->input('category_id'),
        ]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.documents.index');
        }

        $categories = DB::table('cbe_document_categories')->where('is_active', true)->orderBy('sort_order')->get();

        return view('cbe.documents.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.documents.index');
        }

        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'uuid', 'exists:cbe_document_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:8192'],
        ]);

        // A previous document with the SAME title in this entity is
        // treated as an earlier version of the same document, per
        // Chris's own answer that re-uploads should keep history, not
        // silently overwrite. The new row's "version" is bumped and it
        // points superseded_document_id at the row it replaces.
        $previous = DB::table('cbe_documents')
            ->where('cbe_node_id', $nodeId)
            ->where('title', $request->input('title'))
            ->orderByDesc('version')
            ->first();

        $file = $request->file('file');
        $path = $file->store('cbe-documents', 'local');

        DB::table('cbe_documents')->insert([
            'document_id' => (string) Str::uuid(),
            'cbe_node_id' => $nodeId,
            'category_id' => $request->input('category_id') ?: null,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'version' => $previous ? $previous->version + 1 : 1,
            'superseded_document_id' => $previous ? $previous->document_id : null,
            'uploaded_by' => $agent->agent_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.documents.index')->with('success', __('cbe_documents.saved_note'));
    }

    public function download(string $documentId)
    {
        $agent = auth('agent')->user();
        $document = DB::table('cbe_documents')
            ->where('document_id', $documentId)
            ->where('cbe_node_id', $this->resolveCbeNodeIdForMember($agent))
            ->firstOrFail();

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Document file not found.');
        }

        return response()->file(Storage::disk('local')->path($document->file_path));
    }
}
