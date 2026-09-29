<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeAiSourceFetchService;
use App\Services\CbeCommitteeAuthService;
use App\Services\CbeKnowledgeBaseAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris (his "Gemini Gem" idea, approved after
// being asked to advise): a Secretary/officer names an AI Assistant,
// writes it instructions, and attaches up to 3 of this entity's own
// Document Repository documents (bylaws, ROS garis panduan, etc). Any
// member can then ask it questions — see CbeKnowledgeBaseAssistantService
// for why answers come only from the attached files, sent fresh each
// question, rather than a pre-extracted/embedded knowledge base.
class CbeAiAssistantController extends Controller
{
    use ResolvesCbeActiveNode;

    private const MAX_DOCUMENTS = 3;
    private const MAX_WEB_SOURCES = 2;
    private const MAX_HISTORY_TURNS = 4;

    public function index(Request $request)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);

        if (! $nodeId && $agent->role === 'ADMIN') {
            return $this->renderCbeNodePicker('cbe.ai-assistants.index', __('cbe_ai_assistant.page_title'), leafOnly: true);
        }

        $canManage = CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId);

        $assistants = $nodeId
            ? DB::table('cbe_ai_assistants')->where('cbe_node_id', $nodeId)->orderBy('name')->get()
            : collect();

        return view('cbe.ai-assistants.index', [
            'assistants' => $assistants,
            'hasNode' => (bool) $nodeId,
            'canManage' => $canManage,
        ]);
    }

    public function create()
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.ai-assistants.index');
        }

        $documents = DB::table('cbe_documents')->where('cbe_node_id', $nodeId)->orderByDesc('created_at')->get();

        return view('cbe.ai-assistants.create', compact('documents'));
    }

    public function store(Request $request, CbeAiSourceFetchService $fetchService)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        if (! $nodeId || ! CbeCommitteeAuthService::isAuthorizedManager($agent, $nodeId)) {
            return redirect()->route('cbe.ai-assistants.index');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'document_ids' => ['nullable', 'array', 'max:' . self::MAX_DOCUMENTS],
            'document_ids.*' => ['uuid', 'exists:cbe_documents,document_id'],
            'web_sources' => ['nullable', 'array', 'max:' . self::MAX_WEB_SOURCES],
            'web_sources.*.type' => ['required_with:web_sources.*.url', 'in:URL,YOUTUBE'],
            'web_sources.*.title' => ['required_with:web_sources.*.url', 'string', 'max:150'],
            'web_sources.*.url' => ['required_with:web_sources.*.title', 'url', 'max:1000'],
        ]);

        $documentIds = array_values(array_filter($request->input('document_ids', [])));
        $webSourceInputs = array_values(array_filter($request->input('web_sources', []), fn ($w) => ! empty($w['url'])));

        if (empty($documentIds) && empty($webSourceInputs)) {
            return back()->with('error', __('cbe_ai_assistant.needs_one_source'))->withInput();
        }

        $assistantId = (string) Str::uuid();
        DB::table('cbe_ai_assistants')->insert([
            'assistant_id' => $assistantId,
            'cbe_node_id' => $nodeId,
            'name' => $request->input('name'),
            'instructions' => $request->input('instructions'),
            'created_by' => $agent->agent_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [];
        // Only documents that actually belong to this entity are ever
        // attached — a tampered document_id from another entity is
        // silently dropped here, never trusted from the form alone.
        $validDocIds = DB::table('cbe_documents')->where('cbe_node_id', $nodeId)
            ->whereIn('document_id', $documentIds)->pluck('document_id');
        foreach ($validDocIds as $docId) {
            $rows[] = ['id' => (string) Str::uuid(), 'assistant_id' => $assistantId, 'document_id' => $docId, 'created_at' => now(), 'updated_at' => now()];
        }
        if (! empty($rows)) {
            DB::table('cbe_ai_assistant_documents')->insert($rows);
        }

        // NEW 18 Sep 2026 — web link/YouTube knowledge sources: content
        // is fetched and cached ONCE here, right at creation, rather
        // than live on every question (slow + costly). A source that
        // fails to fetch is still saved (fetch_status=FAILED) so the
        // officer can see exactly which link didn't work and why,
        // instead of it silently vanishing.
        foreach ($webSourceInputs as $w) {
            $result = $fetchService->fetch($w['type'], $w['url']);
            DB::table('cbe_ai_assistant_sources')->insert([
                'source_id' => (string) Str::uuid(),
                'assistant_id' => $assistantId,
                'type' => $w['type'],
                'url' => $w['url'],
                'title' => $w['title'],
                'cached_content' => $result['content'],
                'fetch_status' => $result['status'],
                'fetch_error' => $result['error'],
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('cbe.ai-assistants.index')->with('success', __('cbe_ai_assistant.saved_note'));
    }

    public function chat(string $assistantId)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $assistant = DB::table('cbe_ai_assistants')->where('assistant_id', $assistantId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $documents = DB::table('cbe_ai_assistant_documents as ad')
            ->join('cbe_documents as d', 'd.document_id', '=', 'ad.document_id')
            ->where('ad.assistant_id', $assistantId)
            ->get(['d.document_id', 'd.title']);

        $webSources = DB::table('cbe_ai_assistant_sources')
            ->where('assistant_id', $assistantId)
            ->get(['source_id', 'type', 'title', 'url', 'fetch_status', 'fetch_error']);

        $history = DB::table('cbe_ai_assistant_chats')
            ->where('assistant_id', $assistantId)->where('agent_id', $agent->agent_id)
            ->orderBy('created_at')->get();

        return view('cbe.ai-assistants.chat', compact('assistant', 'documents', 'webSources', 'history'));
    }

    public function ask(Request $request, string $assistantId, CbeKnowledgeBaseAssistantService $service)
    {
        $agent = auth('agent')->user();
        $nodeId = $this->resolveCbeNodeIdForMember($agent);
        $assistant = DB::table('cbe_ai_assistants')->where('assistant_id', $assistantId)->where('cbe_node_id', $nodeId)->firstOrFail();

        $request->validate(['question' => ['required', 'string', 'max:1000']]);

        $docRows = DB::table('cbe_ai_assistant_documents as ad')
            ->join('cbe_documents as d', 'd.document_id', '=', 'ad.document_id')
            ->where('ad.assistant_id', $assistantId)
            ->get(['d.file_path', 'd.file_original_name', 'd.title']);

        $documents = [];
        foreach ($docRows as $d) {
            if (! Storage::disk('local')->exists($d->file_path)) {
                continue;
            }
            $documents[] = [
                'path' => Storage::disk('local')->path($d->file_path),
                'mime' => Storage::disk('local')->mimeType($d->file_path) ?: 'application/octet-stream',
                'name' => $d->title,
            ];
        }

        // Only sources that were fetched successfully at attach time
        // are usable — a FAILED one is shown to the officer on the
        // chat/manage screen but never silently sent to the AI as
        // empty/missing content.
        $webSources = DB::table('cbe_ai_assistant_sources')
            ->where('assistant_id', $assistantId)->where('fetch_status', 'OK')
            ->get(['title', 'cached_content'])
            ->map(fn ($s) => ['title' => $s->title, 'text' => $s->cached_content])->values()->all();

        $priorHistory = DB::table('cbe_ai_assistant_chats')
            ->where('assistant_id', $assistantId)->where('agent_id', $agent->agent_id)
            ->orderByDesc('created_at')->limit(self::MAX_HISTORY_TURNS)->get()
            ->reverse()->map(fn ($h) => ['question' => $h->question, 'answer' => $h->answer])->values()->all();

        $result = $service->ask($assistant->name, $assistant->instructions, $documents, $webSources, $priorHistory, $request->input('question'));

        if ($result['status'] !== 'OK') {
            return back()->with('error', $result['message'])->withInput();
        }

        DB::table('cbe_ai_assistant_chats')->insert([
            'chat_id' => (string) Str::uuid(),
            'assistant_id' => $assistantId,
            'agent_id' => $agent->agent_id,
            'question' => $request->input('question'),
            'answer' => $result['answer'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cbe.ai-assistants.chat', $assistantId);
    }
}
