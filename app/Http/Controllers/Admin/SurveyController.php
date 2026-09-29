<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleFormsService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// REPLACED 25 Jul 2026 — Survey Management Module, Phase 1 (task #227,
// #231). Chris supplied a full enterprise-scale spec and chose to build
// toward it (not keep the earlier small MVP). This controller covers
// Phase 1 only: Dashboard (lite), Create Survey, Question Builder (all
// 16 question types + basic single-condition skip logic), Target
// Respondents, Preview, and the full status workflow. Distribution,
// response collection, analytics, AI, templates, and settings are later
// phases (#232-#235) — see PublicSurveyController/SurveySendController
// for what Phase 1 still stubs out.
//
// Question types supported: SINGLE_CHOICE, MULTIPLE_CHOICE, DROPDOWN,
// YES_NO, RATING_SCALE, STAR_RATING, NUMERIC_RATING, LIKERT_SCALE,
// SHORT_TEXT, LONG_TEXT, EMAIL, PHONE, DATE, TIME, NUMBER, MATRIX.
class SurveyController extends Controller
{
    private const STATUSES = ['DRAFT', 'PUBLISHED', 'ACTIVE', 'PAUSED', 'CLOSED', 'ARCHIVED'];

    private const CHOICE_TYPES = ['SINGLE_CHOICE', 'MULTIPLE_CHOICE', 'DROPDOWN', 'LIKERT_SCALE'];
    private const SCALE_TYPES = ['RATING_SCALE', 'STAR_RATING', 'NUMERIC_RATING', 'LIKERT_SCALE'];

    // ── Dashboard (lite) + List ──────────────────────────────────
    public function index(Request $request, GoogleFormsService $google)
    {
        $googleConnected = $google->isConnected();
        $googleConnection = $google->activeConnection();
        $categoryId = trim((string) $request->get('category_id', ''));
        $status = trim((string) $request->get('status', ''));

        $counts = DB::table('surveys')->where('is_deleted', false)
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $statCards = collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]);
        $totalSurveys = $statCards->sum();

        $totalInvitations = DB::table('survey_invitations')->count();
        $totalResponses = DB::table('survey_responses')->where('completion_status', 'COMPLETED')->count();
        $responseRate = $totalInvitations > 0 ? round(($totalResponses / $totalInvitations) * 100, 1) : 0;

        $surveys = DB::table('surveys as s')
            ->leftJoin('survey_categories as sc', 's.category_id', '=', 'sc.category_id')
            ->leftJoin('survey_responses as r', function ($j) { $j->on('s.survey_id', '=', 'r.survey_id')->where('r.completion_status', '=', 'COMPLETED'); })
            ->where('s.is_deleted', false)
            ->when($categoryId !== '', fn ($q) => $q->where('s.category_id', $categoryId))
            ->when($status !== '', fn ($q) => $q->where('s.status', $status))
            ->select('s.*', 'sc.name as category_name', DB::raw('COUNT(r.response_id) as response_count'))
            ->groupBy('s.survey_id', 's.name', 's.title', 's.description', 's.objective', 's.category_id', 's.owner_agent_id', 's.language', 's.status', 's.target_respondent_types', 's.start_date', 's.end_date', 's.allow_anonymous', 's.require_respondent_contact', 's.public_token', 's.google_form_id', 's.google_form_edit_url', 's.google_form_response_url', 's.google_synced_at', 's.created_by', 's.is_deleted', 's.created_at', 's.updated_at', 'sc.name')
            ->orderByDesc('s.created_at')
            ->paginate(8)->withQueryString();

        $categories = DB::table('survey_categories')->where('is_active', true)->orderBy('name')->get();

        return view('growth.surveys-admin', compact('surveys', 'categories', 'categoryId', 'status', 'statCards', 'totalSurveys', 'totalInvitations', 'totalResponses', 'responseRate', 'googleConnected', 'googleConnection'));
    }

    // ── Create / Edit Survey Info ────────────────────────────────
    public function create()
    {
        return $this->formView(null);
    }

    public function edit(string $id)
    {
        $survey = DB::table('surveys')->where('survey_id', $id)->where('is_deleted', false)->first();
        abort_unless($survey, 404);
        return $this->formView($survey);
    }

    private function formView(?object $survey)
    {
        $categories = DB::table('survey_categories')->where('is_active', true)->orderBy('name')->get();
        $owners = DB::table('agents')->where('is_deleted', false)->orderBy('full_name')->get(['agent_id', 'full_name', 'role']);
        $selectedTypes = $survey && $survey->target_respondent_types ? explode(',', $survey->target_respondent_types) : [];
        return view('growth.survey-form', compact('survey', 'categories', 'owners', 'selectedTypes'));
    }

    private function validateSurveyInfo(Request $request): array
    {
        $validated = $request->validate([
            'name'                     => ['required', 'string', 'max:200'],
            'title'                    => ['required', 'string', 'max:200'],
            'description'              => ['nullable', 'string'],
            'objective'                => ['required', 'string'],
            'category_id'              => ['nullable', 'exists:survey_categories,category_id'],
            'owner_agent_id'           => ['nullable', 'exists:agents,agent_id'],
            'language'                 => ['nullable', 'string', 'max:10'],
            'start_date'               => ['nullable', 'date'],
            'end_date'                 => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_respondent_types'  => ['nullable', 'array'],
            'target_respondent_types.*'=> ['in:EXISTING_CUSTOMER,NEW_CUSTOMER,PROSPECT'],
            'allow_anonymous'          => ['nullable'],
            'require_respondent_contact' => ['nullable'],
        ]);
        $validated['target_respondent_types'] = !empty($validated['target_respondent_types']) ? implode(',', $validated['target_respondent_types']) : null;
        $validated['allow_anonymous'] = !empty($validated['allow_anonymous']);
        $validated['require_respondent_contact'] = !empty($validated['require_respondent_contact']);
        $validated['language'] = $validated['language'] ?: 'en';
        return $validated;
    }

    public function store(Request $request)
    {
        $data = $this->validateSurveyInfo($request);
        $agent = Auth::guard('agent')->user();
        $surveyId = (string) Str::uuid();

        DB::table('surveys')->insert(array_merge($data, [
            'survey_id'    => $surveyId,
            'status'       => 'DRAFT',
            'public_token' => Str::random(24),
            'created_by'   => $agent->agent_id,
            'is_deleted'   => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]));

        return redirect()->route('admin.growth.surveys.builder', $surveyId)->with('success', 'Survey created as Draft. Now add your questions below.');
    }

    public function update(Request $request, string $id)
    {
        $survey = DB::table('surveys')->where('survey_id', $id)->where('is_deleted', false)->first();
        abort_unless($survey, 404);
        abort_if($survey->status === 'ARCHIVED', 403, 'An archived survey cannot be edited.');

        $data = $this->validateSurveyInfo($request);
        $data['updated_at'] = now();
        DB::table('surveys')->where('survey_id', $id)->update($data);

        return redirect()->route('admin.growth.surveys.builder', $id)->with('success', 'Survey info saved.');
    }

    // ── Question Builder ─────────────────────────────────────────
    public function builder(Request $request, string $id)
    {
        $survey = $this->findSurveyOr404($id);

        $questions = DB::table('survey_questions')->where('survey_id', $id)->orderBy('sort_order')->get();
        $optionsByQuestion = DB::table('survey_question_options')
            ->whereIn('question_id', $questions->pluck('question_id'))
            ->orderBy('sort_order')->get()->groupBy('question_id');
        foreach ($questions as $q) {
            $q->question_options = $optionsByQuestion[$q->question_id] ?? collect();
        }

        $logicRules = DB::table('survey_question_logic as l')
            ->join('survey_questions as sq', 'l.source_question_id', '=', 'sq.question_id')
            ->join('survey_questions as tq', 'l.target_question_id', '=', 'tq.question_id')
            ->leftJoin('survey_question_options as o', 'l.condition_option_id', '=', 'o.option_id')
            ->where('l.survey_id', $id)
            ->select('l.*', 'sq.question_text as source_text', 'tq.question_text as target_text', 'o.option_text')
            ->get();

        // The Add/Edit Question panel is a single form that switches
        // between "create new" and "edit this one" via ?edit=<id> —
        // simpler and more reliable than syncing state through JS.
        $editingQuestion = null;
        $editId = $request->get('edit');
        if ($editId) {
            $editingQuestion = $questions->firstWhere('question_id', $editId);
            if ($editingQuestion) {
                $editingQuestion->matrix = $editingQuestion->matrix_config ? json_decode($editingQuestion->matrix_config, true) : null;
            }
        }

        return view('growth.survey-builder', compact('survey', 'questions', 'logicRules', 'editingQuestion'));
    }

    private function questionValidationRules(): array
    {
        return [
            'question_text'        => ['required', 'string'],
            'question_description' => ['nullable', 'string'],
            'help_text'            => ['nullable', 'string'],
            'question_type'        => ['required', 'in:SINGLE_CHOICE,MULTIPLE_CHOICE,DROPDOWN,YES_NO,RATING_SCALE,STAR_RATING,NUMERIC_RATING,LIKERT_SCALE,SHORT_TEXT,LONG_TEXT,EMAIL,PHONE,DATE,TIME,NUMBER,MATRIX'],
            'is_required'          => ['nullable'],
            'options_text'         => ['nullable', 'string'],
            'min_length'           => ['nullable', 'integer', 'min:0'],
            'max_length'           => ['nullable', 'integer', 'min:0'],
            'min_value'            => ['nullable', 'numeric'],
            'max_value'            => ['nullable', 'numeric'],
            'scale_max'            => ['nullable', 'integer', 'min:2', 'max:10'],
            'matrix_rows_text'     => ['nullable', 'string'],
            'matrix_columns_text'  => ['nullable', 'string'],
        ];
    }

    private function linesToArray(?string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $text))));
    }

    public function storeQuestion(Request $request, string $id)
    {
        $survey = $this->findSurveyOr404($id);
        $data = $request->validate($this->questionValidationRules());

        $questionId = (string) Str::uuid();
        $nextOrder = (int) DB::table('survey_questions')->where('survey_id', $id)->max('sort_order') + 1;

        $matrixConfig = null;
        if ($data['question_type'] === 'MATRIX') {
            $matrixConfig = json_encode([
                'rows' => $this->linesToArray($data['matrix_rows_text'] ?? ''),
                'columns' => $this->linesToArray($data['matrix_columns_text'] ?? ''),
            ]);
        }

        DB::table('survey_questions')->insert([
            'question_id'           => $questionId,
            'survey_id'             => $id,
            'question_text'         => $data['question_text'],
            'question_description' => $data['question_description'] ?? null,
            'help_text'             => $data['help_text'] ?? null,
            'question_type'         => $data['question_type'],
            'is_required'           => !empty($data['is_required']),
            'sort_order'            => $nextOrder,
            'min_length'            => $data['min_length'] ?? null,
            'max_length'            => $data['max_length'] ?? null,
            'min_value'             => $data['min_value'] ?? null,
            'max_value'             => $data['max_value'] ?? null,
            'scale_max'             => in_array($data['question_type'], self::SCALE_TYPES, true) ? ($data['scale_max'] ?? 5) : null,
            'matrix_config'         => $matrixConfig,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        if (in_array($data['question_type'], self::CHOICE_TYPES, true)) {
            $this->saveOptions($questionId, $data['options_text'] ?? '');
        }

        return back()->with('success', 'Question added.');
    }

    public function updateQuestion(Request $request, string $id, string $questionId)
    {
        $survey = $this->findSurveyOr404($id);
        $question = DB::table('survey_questions')->where('question_id', $questionId)->where('survey_id', $id)->first();
        abort_unless($question, 404);

        $data = $request->validate($this->questionValidationRules());

        $matrixConfig = $question->matrix_config;
        if ($data['question_type'] === 'MATRIX') {
            $matrixConfig = json_encode([
                'rows' => $this->linesToArray($data['matrix_rows_text'] ?? ''),
                'columns' => $this->linesToArray($data['matrix_columns_text'] ?? ''),
            ]);
        } elseif ($question->question_type === 'MATRIX') {
            $matrixConfig = null;
        }

        DB::table('survey_questions')->where('question_id', $questionId)->update([
            'question_text'         => $data['question_text'],
            'question_description' => $data['question_description'] ?? null,
            'help_text'             => $data['help_text'] ?? null,
            'question_type'         => $data['question_type'],
            'is_required'           => !empty($data['is_required']),
            'min_length'            => $data['min_length'] ?? null,
            'max_length'            => $data['max_length'] ?? null,
            'min_value'             => $data['min_value'] ?? null,
            'max_value'             => $data['max_value'] ?? null,
            'scale_max'             => in_array($data['question_type'], self::SCALE_TYPES, true) ? ($data['scale_max'] ?? 5) : null,
            'matrix_config'         => $matrixConfig,
            'updated_at'            => now(),
        ]);

        if (in_array($data['question_type'], self::CHOICE_TYPES, true)) {
            $this->saveOptions($questionId, $data['options_text'] ?? '');
        } else {
            DB::table('survey_question_options')->where('question_id', $questionId)->delete();
        }

        return back()->with('success', 'Question updated.');
    }

    private function saveOptions(string $questionId, string $optionsText): void
    {
        DB::table('survey_question_options')->where('question_id', $questionId)->delete();
        foreach ($this->linesToArray($optionsText) as $i => $text) {
            DB::table('survey_question_options')->insert([
                'option_id'   => (string) Str::uuid(),
                'question_id' => $questionId,
                'option_text' => $text,
                'sort_order'  => $i,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function destroyQuestion(string $id, string $questionId)
    {
        $this->findSurveyOr404($id);
        DB::table('survey_questions')->where('question_id', $questionId)->where('survey_id', $id)->delete();
        return back()->with('success', 'Question deleted.');
    }

    public function duplicateQuestion(string $id, string $questionId)
    {
        $this->findSurveyOr404($id);
        $question = DB::table('survey_questions')->where('question_id', $questionId)->where('survey_id', $id)->first();
        abort_unless($question, 404);

        $newId = (string) Str::uuid();
        $nextOrder = (int) DB::table('survey_questions')->where('survey_id', $id)->max('sort_order') + 1;
        DB::table('survey_questions')->insert([
            'question_id'           => $newId,
            'survey_id'             => $id,
            'question_text'         => $question->question_text . ' (Copy)',
            'question_description' => $question->question_description,
            'help_text'             => $question->help_text,
            'question_type'         => $question->question_type,
            'is_required'           => $question->is_required,
            'sort_order'            => $nextOrder,
            'min_length'            => $question->min_length,
            'max_length'            => $question->max_length,
            'min_value'             => $question->min_value,
            'max_value'             => $question->max_value,
            'scale_max'             => $question->scale_max,
            'matrix_config'         => $question->matrix_config,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        foreach (DB::table('survey_question_options')->where('question_id', $questionId)->orderBy('sort_order')->get() as $opt) {
            DB::table('survey_question_options')->insert([
                'option_id'   => (string) Str::uuid(),
                'question_id' => $newId,
                'option_text' => $opt->option_text,
                'sort_order'  => $opt->sort_order,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return back()->with('success', 'Question duplicated.');
    }

    // Reorder — swap sort_order with the adjacent question, same
    // simple up/down technique used elsewhere in this app rather than
    // a full drag-and-drop library (this app has never used one, and
    // introducing a new JS dependency for one screen isn't justified).
    public function moveQuestion(Request $request, string $id, string $questionId)
    {
        $this->findSurveyOr404($id);
        $direction = $request->get('direction') === 'up' ? 'up' : 'down';

        $questions = DB::table('survey_questions')->where('survey_id', $id)->orderBy('sort_order')->get();
        $index = $questions->search(fn ($q) => $q->question_id === $questionId);
        if ($index === false) {
            return back();
        }
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $questions->count()) {
            return back();
        }

        $a = $questions[$index];
        $b = $questions[$swapWith];
        DB::table('survey_questions')->where('question_id', $a->question_id)->update(['sort_order' => $b->sort_order]);
        DB::table('survey_questions')->where('question_id', $b->question_id)->update(['sort_order' => $a->sort_order]);

        return back();
    }

    // ── Conditional / Skip Logic (Phase 1 — single condition) ────
    public function storeLogic(Request $request, string $id)
    {
        $this->findSurveyOr404($id);
        $data = $request->validate([
            'source_question_id'  => ['required', 'exists:survey_questions,question_id'],
            'condition_option_id' => ['nullable', 'exists:survey_question_options,option_id'],
            'condition_operator'  => ['nullable', 'in:EQUALS,NOT_EQUALS,GREATER_THAN,LESS_THAN'],
            'condition_value'     => ['nullable', 'string', 'max:300'],
            'target_question_id'  => ['required', 'exists:survey_questions,question_id', 'different:source_question_id'],
            'action'               => ['required', 'in:SHOW,SKIP'],
        ]);

        DB::table('survey_question_logic')->insert([
            'logic_id'            => (string) Str::uuid(),
            'survey_id'           => $id,
            'source_question_id'  => $data['source_question_id'],
            'condition_option_id' => $data['condition_option_id'] ?? null,
            'condition_operator'  => $data['condition_operator'] ?? null,
            'condition_value'     => $data['condition_value'] ?? null,
            'target_question_id'  => $data['target_question_id'],
            'action'              => $data['action'],
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return back()->with('success', 'Logic rule added.');
    }

    public function destroyLogic(string $id, string $logicId)
    {
        $this->findSurveyOr404($id);
        DB::table('survey_question_logic')->where('logic_id', $logicId)->where('survey_id', $id)->delete();
        return back()->with('success', 'Logic rule removed.');
    }

    // ── Preview ───────────────────────────────────────────────────
    public function preview(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        $questions = DB::table('survey_questions')->where('survey_id', $id)->orderBy('sort_order')->get();
        $optionsByQuestion = DB::table('survey_question_options')
            ->whereIn('question_id', $questions->pluck('question_id'))
            ->orderBy('sort_order')->get()->groupBy('question_id');
        foreach ($questions as $q) {
            $q->question_options = $optionsByQuestion[$q->question_id] ?? collect();
            $q->matrix = $q->matrix_config ? json_decode($q->matrix_config, true) : null;
        }
        return view('growth.survey-preview', compact('survey', 'questions'));
    }

    // ── Status Workflow: Draft -> Published -> Active -> Paused -> Closed -> Archived ──
    public function publish(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        abort_unless($survey->status === 'DRAFT', 403, 'Only a Draft survey can be published.');
        $hasQuestions = DB::table('survey_questions')->where('survey_id', $id)->exists();
        abort_unless($hasQuestions, 422, 'Add at least one question before publishing.');

        DB::table('surveys')->where('survey_id', $id)->update(['status' => 'PUBLISHED', 'updated_at' => now()]);
        return back()->with('success', 'Survey Published. Activate it when you\'re ready to start distributing.');
    }

    public function activate(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        abort_unless(in_array($survey->status, ['PUBLISHED', 'PAUSED'], true), 403, 'Only a Published or Paused survey can be activated.');
        DB::table('surveys')->where('survey_id', $id)->update(['status' => 'ACTIVE', 'updated_at' => now()]);
        return back()->with('success', 'Survey is now Active — it can be distributed and answered.');
    }

    public function pause(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        abort_unless($survey->status === 'ACTIVE', 403, 'Only an Active survey can be paused.');
        DB::table('surveys')->where('survey_id', $id)->update(['status' => 'PAUSED', 'updated_at' => now()]);
        return back()->with('success', 'Survey Paused — existing links stop accepting new responses until resumed.');
    }

    public function close(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        abort_unless(in_array($survey->status, ['ACTIVE', 'PAUSED'], true), 403, 'Only an Active or Paused survey can be closed.');
        DB::table('surveys')->where('survey_id', $id)->update(['status' => 'CLOSED', 'updated_at' => now()]);
        return back()->with('success', 'Survey Closed — no longer accepting responses.');
    }

    public function archive(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        abort_unless($survey->status === 'CLOSED', 403, 'Only a Closed survey can be archived.');
        DB::table('surveys')->where('survey_id', $id)->update(['status' => 'ARCHIVED', 'updated_at' => now()]);
        return back()->with('success', 'Survey Archived.');
    }

    public function duplicateSurvey(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        $agent = Auth::guard('agent')->user();
        $newSurveyId = (string) Str::uuid();

        DB::transaction(function () use ($survey, $agent, $newSurveyId) {
            DB::table('surveys')->insert([
                'survey_id'                  => $newSurveyId,
                'name'                        => $survey->name . ' (Copy)',
                'title'                       => $survey->title,
                'description'                 => $survey->description,
                'objective'                   => $survey->objective,
                'category_id'                 => $survey->category_id,
                'owner_agent_id'              => $survey->owner_agent_id,
                'language'                    => $survey->language,
                'status'                      => 'DRAFT',
                'target_respondent_types'    => $survey->target_respondent_types,
                'start_date'                  => null,
                'end_date'                    => null,
                'allow_anonymous'             => $survey->allow_anonymous,
                'require_respondent_contact' => $survey->require_respondent_contact,
                'public_token'                => Str::random(24),
                'created_by'                  => $agent->agent_id,
                'is_deleted'                  => false,
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ]);

            $idMap = [];
            foreach (DB::table('survey_questions')->where('survey_id', $survey->survey_id)->orderBy('sort_order')->get() as $q) {
                $newQId = (string) Str::uuid();
                $idMap[$q->question_id] = $newQId;
                DB::table('survey_questions')->insert([
                    'question_id'           => $newQId,
                    'survey_id'             => $newSurveyId,
                    'question_text'         => $q->question_text,
                    'question_description' => $q->question_description,
                    'help_text'             => $q->help_text,
                    'question_type'         => $q->question_type,
                    'is_required'           => $q->is_required,
                    'sort_order'            => $q->sort_order,
                    'min_length'            => $q->min_length,
                    'max_length'            => $q->max_length,
                    'min_value'             => $q->min_value,
                    'max_value'             => $q->max_value,
                    'scale_max'             => $q->scale_max,
                    'matrix_config'         => $q->matrix_config,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);

                $optIdMap = [];
                foreach (DB::table('survey_question_options')->where('question_id', $q->question_id)->orderBy('sort_order')->get() as $opt) {
                    $newOptId = (string) Str::uuid();
                    $optIdMap[$opt->option_id] = $newOptId;
                    DB::table('survey_question_options')->insert([
                        'option_id'   => $newOptId,
                        'question_id' => $newQId,
                        'option_text' => $opt->option_text,
                        'sort_order'  => $opt->sort_order,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
                $idMap['opt:' . $q->question_id] = $optIdMap;
            }

            foreach (DB::table('survey_question_logic')->where('survey_id', $survey->survey_id)->get() as $l) {
                $sourceOptMap = $idMap['opt:' . $l->source_question_id] ?? [];
                DB::table('survey_question_logic')->insert([
                    'logic_id'            => (string) Str::uuid(),
                    'survey_id'           => $newSurveyId,
                    'source_question_id'  => $idMap[$l->source_question_id] ?? $l->source_question_id,
                    'condition_option_id' => $l->condition_option_id ? ($sourceOptMap[$l->condition_option_id] ?? null) : null,
                    'condition_operator'  => $l->condition_operator,
                    'condition_value'     => $l->condition_value,
                    'target_question_id'  => $idMap[$l->target_question_id] ?? $l->target_question_id,
                    'action'              => $l->action,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        });

        return redirect()->route('admin.growth.surveys.builder', $newSurveyId)->with('success', 'Survey duplicated as a new Draft.');
    }

    // Only a never-published Draft can be deleted outright — anything
    // that reached Published/Active may already have real responses
    // attached, so it must be Archived instead (never destroyed).
    public function destroy(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        abort_unless($survey->status === 'DRAFT', 403, 'Only a Draft survey can be deleted. Archive it instead once it has been published.');
        DB::table('surveys')->where('survey_id', $id)->update(['is_deleted' => true, 'updated_at' => now()]);
        return redirect()->route('admin.growth.surveys.index')->with('success', 'Draft survey deleted.');
    }

    private function findSurveyOr404(string $id): object
    {
        $survey = DB::table('surveys')->where('survey_id', $id)->where('is_deleted', false)->first();
        abort_unless($survey, 404);
        return $survey;
    }

    // ══════════════════════════════════════════════════════════════
    // PHASE 2 (task #232) — Distribution + Response Collection.
    // Link/QR/Embed always work with zero external dependency, same
    // reasoning as ReferralLinkController. WhatsApp/Telegram/SMS reuse
    // the wa.me/t.me/sms: share-link pattern from My Referral Link —
    // it works today with no provider subscription, unlike Broadcast
    // Campaigns' gated Channel Connections. Real email sending reuses
    // Mail::raw, same as NotificationService. Per-customer targeted
    // sends (with invitation tracking) live in Shared\SurveySendController
    // — this controller only covers the generic/public link+QR+embed.
    // ══════════════════════════════════════════════════════════════
    public function distribute(string $id, GoogleFormsService $google)
    {
        $survey = $this->findSurveyOr404($id);

        // NEW 25 Jul 2026 — if this survey has been pushed to Google Forms,
        // distribution/response-viewing happens on Google's own link (task
        // #241) instead of GeneralLink's built-in public link/QR/embed.
        // The built-in path below still works for surveys not pushed to
        // Google (or if Chris ever disconnects Google again).
        $googleConnected = $google->isConnected();
        $googleResponseCount = null;
        $googleError = null;
        if ($survey->google_form_id) {
            try {
                $googleResponseCount = count($google->fetchResponses($survey->google_form_id));
            } catch (\Throwable $e) {
                $googleError = 'Could not reach Google to fetch the live response count: ' . $e->getMessage();
            }
        }

        $publicUrl = url('/survey/' . $survey->public_token);

        $qrDataUri = (new Builder(
            writer: new PngWriter(),
            data: $survey->google_form_id ? $survey->google_form_response_url : $publicUrl,
            size: 200,
            margin: 8,
        ))->build()->getDataUri();

        $embedCode = '<iframe src="' . $publicUrl . '" style="width:100%;height:640px;border:none;" title="' . e($survey->title) . '"></iframe>';

        $invitationStats = DB::table('survey_invitations')->where('survey_id', $id)
            ->select('channel', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status='COMPLETED' THEN 1 ELSE 0 END) as completed"))
            ->groupBy('channel')->get()->keyBy('channel');

        return view('growth.survey-distribute', compact('survey', 'publicUrl', 'qrDataUri', 'embedCode', 'invitationStats', 'googleConnected', 'googleResponseCount', 'googleError'));
    }

    // ── Google Forms push (task #241) ────────────────────────────
    public function pushToGoogle(string $id, GoogleFormsService $google)
    {
        $survey = $this->findSurveyOr404($id);

        if (!$google->isConnected()) {
            return back()->with('error', 'No Google account is connected yet. Click "Connect Google Account" first.');
        }

        $questions = DB::table('survey_questions')->where('survey_id', $id)->orderBy('sort_order')->get();
        if ($questions->isEmpty()) {
            return redirect()->route('admin.growth.surveys.builder', $id)
                ->with('error', 'Add at least one question first, then come back to Distribute and push to Google Forms.');
        }

        $optionsByQuestion = DB::table('survey_question_options')
            ->whereIn('question_id', $questions->pluck('question_id'))
            ->orderBy('sort_order')->get()->groupBy('question_id');
        foreach ($questions as $q) {
            $q->question_options = $optionsByQuestion[$q->question_id] ?? collect();
            $q->matrix = $q->matrix_config ? json_decode($q->matrix_config, true) : null;
        }

        try {
            $result = $google->createFormForSurvey($survey, $questions);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not push to Google Forms: ' . $e->getMessage());
        }

        DB::table('surveys')->where('survey_id', $id)->update([
            'google_form_id'            => $result['form_id'],
            'google_form_edit_url'      => $result['edit_url'],
            'google_form_response_url'  => $result['response_url'],
            'google_synced_at'          => now(),
            'updated_at'                => now(),
        ]);

        return redirect()->route('admin.growth.surveys.distribute', $id)
            ->with('success', 'Pushed to Google Forms. Open "Edit in Google Forms" to fine-tune styling/logic directly on Google, or share the Google link with respondents.');
    }

    public function downloadDistributeQr(string $id)
    {
        $survey = $this->findSurveyOr404($id);
        $publicUrl = url('/survey/' . $survey->public_token);

        $result = (new Builder(
            writer: new PngWriter(),
            data: $publicUrl,
            size: 600,
            margin: 20,
        ))->build();

        return response($result->getString(), 200, [
            'Content-Type'        => $result->getMimeType(),
            'Content-Disposition' => 'attachment; filename="survey-qr-' . Str::slug($survey->name) . '.png"',
        ]);
    }

    // ── Response Management — spec section 11 ────────────────────
    public function responses(Request $request, string $id)
    {
        $survey = $this->findSurveyOr404($id);
        $status = trim((string) $request->get('status', ''));

        $rows = DB::table('survey_responses as r')
            ->leftJoin('customers as c', 'r.customer_id', '=', 'c.customer_id')
            ->where('r.survey_id', $id)
            ->when($status !== '', fn ($q) => $q->where('r.completion_status', $status))
            ->select('r.*', 'c.full_name as matched_customer_name')
            ->orderByDesc('r.created_at')
            ->paginate(10)->withQueryString();

        $counts = DB::table('survey_responses')->where('survey_id', $id)
            ->select('completion_status', DB::raw('COUNT(*) as total'))->groupBy('completion_status')->pluck('total', 'completion_status');

        return view('growth.survey-responses', compact('survey', 'rows', 'status', 'counts'));
    }

    public function responseShow(string $id, string $responseId)
    {
        $survey = $this->findSurveyOr404($id);
        $response = DB::table('survey_responses')->where('response_id', $responseId)->where('survey_id', $id)->first();
        abort_unless($response, 404);

        $customer = $response->customer_id ? DB::table('customers')->where('customer_id', $response->customer_id)->first() : null;
        $invitation = $response->invitation_id ? DB::table('survey_invitations')->where('invitation_id', $response->invitation_id)->first() : null;

        $answers = DB::table('survey_answers as a')
            ->join('survey_questions as q', 'a.question_id', '=', 'q.question_id')
            ->leftJoin('survey_question_options as o', 'a.answer_option_id', '=', 'o.option_id')
            ->where('a.response_id', $responseId)
            ->orderBy('q.sort_order')
            ->select('a.*', 'q.question_text', 'q.question_type', 'o.option_text')
            ->get();

        foreach ($answers as $a) {
            if ($a->question_type === 'MULTIPLE_CHOICE' && $a->answer_option_ids) {
                $ids = json_decode($a->answer_option_ids, true) ?: [];
                $a->option_texts = DB::table('survey_question_options')->whereIn('option_id', $ids)->pluck('option_text');
            }
            if ($a->question_type === 'MATRIX' && $a->answer_matrix) {
                $a->matrix_answer = json_decode($a->answer_matrix, true);
            }
        }

        return view('growth.survey-response-detail', compact('survey', 'response', 'customer', 'invitation', 'answers'));
    }
}
