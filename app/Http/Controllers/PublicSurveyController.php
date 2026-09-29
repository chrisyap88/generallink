<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// REPLACED 25 Jul 2026 — Survey Management Module, Phase 2 (task #232).
// Public, no-login respondent form — reached via the public link/QR/
// embed built in Admin\SurveyController::distribute() or a targeted
// send logged by Shared\SurveySendController. Never requires an
// account: per the spec's Core Business Principle, only internal
// agents log in — customers/prospects just answer.
//
// Design notes:
//   - An IN_PROGRESS survey_responses row is created the first time a
//     respondent opens the link (not on submit), so abandonment is
//     measurable (spec's drop-off analysis, Phase 3). The row id is
//     kept in the session (keyed per survey) so refreshing the page
//     resumes the same row instead of creating a new one every time.
//   - Conditional/skip logic (survey_question_logic) is evaluated
//     twice: client-side in JS for live show/hide as the respondent
//     answers, and again here server-side before validating "required"
//     and before saving — a hidden question can never be wrongly
//     required, and a skipped question's answer (if somehow submitted)
//     is simply not saved.
//   - Best-effort Customer match is by phone number only (same
//     approach as Customer Refer-a-Friend) — never NRIC (a stranger
//     filling in a public form should never be asked for one).
class PublicSurveyController extends Controller
{
    public function show(Request $request, string $token)
    {
        $survey = DB::table('surveys')->where('public_token', $token)->where('is_deleted', false)->first();
        abort_unless($survey, 404);

        if (!$this->isOpenForResponses($survey)) {
            return view('public.survey-closed', compact('survey'));
        }

        $questions = DB::table('survey_questions')->where('survey_id', $survey->survey_id)->orderBy('sort_order')->get();
        $optionsByQuestion = DB::table('survey_question_options')
            ->whereIn('question_id', $questions->pluck('question_id'))
            ->orderBy('sort_order')->get()->groupBy('question_id');
        foreach ($questions as $q) {
            $q->question_options = $optionsByQuestion[$q->question_id] ?? collect();
            $q->matrix = $q->matrix_config ? json_decode($q->matrix_config, true) : null;
        }
        $logicRules = DB::table('survey_question_logic')->where('survey_id', $survey->survey_id)->get();

        $responseId = $this->resumeOrCreateResponse($request, $survey);

        return view('public.survey-respond', compact('survey', 'questions', 'logicRules', 'responseId'));
    }

    public function submit(Request $request, string $token)
    {
        $survey = DB::table('surveys')->where('public_token', $token)->where('is_deleted', false)->first();
        abort_unless($survey, 404);
        abort_unless($this->isOpenForResponses($survey), 403, 'This survey is no longer accepting responses.');

        $response = DB::table('survey_responses')
            ->where('response_id', (string) $request->input('response_id'))
            ->where('survey_id', $survey->survey_id)
            ->first();
        abort_unless($response, 404);

        $questions = DB::table('survey_questions')->where('survey_id', $survey->survey_id)->orderBy('sort_order')->get();
        $logicRules = DB::table('survey_question_logic')->where('survey_id', $survey->survey_id)->get();
        $answersInput = (array) $request->input('answers', []);

        $visible = $this->resolveVisibleQuestions($questions, $logicRules, $answersInput);

        $errors = [];
        foreach ($questions as $q) {
            if (!in_array($q->question_id, $visible, true) || !$q->is_required) {
                continue;
            }
            $val = $answersInput[$q->question_id] ?? null;
            $isEmpty = is_array($val)
                ? empty(array_filter($val, fn ($v) => $v !== null && $v !== ''))
                : ($val === null || trim((string) $val) === '');
            if ($isEmpty) {
                $errors['answers.' . $q->question_id] = 'This question is required.';
            }
        }

        if (!$survey->allow_anonymous) {
            if (trim((string) $request->input('respondent_name')) === '') {
                $errors['respondent_name'] = 'Please tell us your name.';
            }
            if (trim((string) $request->input('respondent_type')) === '') {
                $errors['respondent_type'] = 'Please select one.';
            }
        }
        if ($survey->require_respondent_contact && !$survey->allow_anonymous) {
            if (trim((string) $request->input('respondent_email')) === '' && trim((string) $request->input('respondent_phone')) === '') {
                $errors['respondent_contact'] = 'Please provide either an email or a phone number.';
            }
        }

        if (!empty($errors)) {
            return back()->withInput()->withErrors($errors);
        }

        $customerId = null;
        $phone = trim((string) $request->input('respondent_phone'));
        if ($phone !== '') {
            $customer = DB::table('customers')->where('phone', $phone)->where('is_deleted', false)->first();
            if ($customer) {
                $customerId = $customer->customer_id;
            }
        }

        DB::transaction(function () use ($request, $response, $questions, $visible, $answersInput, $customerId, $survey) {
            DB::table('survey_responses')->where('response_id', $response->response_id)->update([
                'is_anonymous'       => $survey->allow_anonymous && trim((string) $request->input('respondent_name')) === '',
                'respondent_name'    => $request->input('respondent_name') ?: null,
                'respondent_email'   => $request->input('respondent_email') ?: null,
                'respondent_phone'   => $request->input('respondent_phone') ?: null,
                'respondent_type'    => $request->input('respondent_type') ?: null,
                'customer_id'        => $customerId,
                'submitted_at'       => now(),
                'completion_status'  => 'COMPLETED',
                'completion_seconds' => $response->started_at ? Carbon::parse($response->started_at)->diffInSeconds(now()) : null,
                'updated_at'         => now(),
            ]);

            // Resubmission safety (e.g. respondent hit back + submitted
            // again) — replace rather than duplicate this response's answers.
            DB::table('survey_answers')->where('response_id', $response->response_id)->delete();

            foreach ($questions as $q) {
                if (!in_array($q->question_id, $visible, true)) {
                    continue;
                }
                $val = $answersInput[$q->question_id] ?? null;
                if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                    continue;
                }

                $row = [
                    'answer_id'        => (string) Str::uuid(),
                    'response_id'      => $response->response_id,
                    'question_id'      => $q->question_id,
                    'answer_text'      => null,
                    'answer_option_id' => null,
                    'answer_option_ids' => null,
                    'answer_number'    => null,
                    'answer_date'      => null,
                    'answer_time'      => null,
                    'answer_matrix'    => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];

                switch ($q->question_type) {
                    case 'SINGLE_CHOICE':
                    case 'DROPDOWN':
                        $row['answer_option_id'] = $val;
                        break;
                    case 'YES_NO':
                        $row['answer_text'] = $val;
                        break;
                    case 'MULTIPLE_CHOICE':
                        $row['answer_option_ids'] = json_encode(array_values((array) $val));
                        break;
                    case 'RATING_SCALE':
                    case 'STAR_RATING':
                    case 'NUMERIC_RATING':
                    case 'LIKERT_SCALE':
                    case 'NUMBER':
                        $row['answer_number'] = is_numeric($val) ? $val : null;
                        break;
                    case 'DATE':
                        $row['answer_date'] = $val;
                        break;
                    case 'TIME':
                        $row['answer_time'] = $val;
                        break;
                    case 'MATRIX':
                        $row['answer_matrix'] = json_encode($val);
                        break;
                    default: // SHORT_TEXT, LONG_TEXT, EMAIL, PHONE
                        $row['answer_text'] = $val;
                }

                DB::table('survey_answers')->insert($row);
            }

            if ($response->invitation_id) {
                DB::table('survey_invitations')->where('invitation_id', $response->invitation_id)->update([
                    'status'     => 'COMPLETED',
                    'updated_at' => now(),
                ]);
            }
        });

        $request->session()->forget('survey_response_' . $survey->survey_id);

        return redirect()->route('public.survey.thankyou', $survey->public_token);
    }

    public function thankYou(string $token)
    {
        $survey = DB::table('surveys')->where('public_token', $token)->where('is_deleted', false)->first();
        abort_unless($survey, 404);
        return view('public.survey-thankyou', compact('survey'));
    }

    private function isOpenForResponses(object $survey): bool
    {
        if ($survey->status !== 'ACTIVE') {
            return false;
        }
        if ($survey->start_date && Carbon::parse($survey->start_date)->isFuture()) {
            return false;
        }
        if ($survey->end_date && Carbon::parse($survey->end_date)->endOfDay()->isPast()) {
            return false;
        }
        return true;
    }

    private function resumeOrCreateResponse(Request $request, object $survey): string
    {
        $sessionKey = 'survey_response_' . $survey->survey_id;
        $responseId = $request->session()->get($sessionKey);

        if ($responseId) {
            $existing = DB::table('survey_responses')
                ->where('response_id', $responseId)
                ->where('completion_status', 'IN_PROGRESS')
                ->first();
            if ($existing) {
                return $responseId;
            }
        }

        $responseId = (string) Str::uuid();
        $invitationId = null;
        $invId = $request->get('inv');
        if ($invId) {
            $inv = DB::table('survey_invitations')->where('invitation_id', $invId)->where('survey_id', $survey->survey_id)->first();
            if ($inv) {
                $invitationId = $inv->invitation_id;
                DB::table('survey_invitations')->where('invitation_id', $invitationId)->update(['status' => 'OPENED', 'updated_at' => now()]);
            }
        }

        DB::table('survey_responses')->insert([
            'response_id'       => $responseId,
            'survey_id'         => $survey->survey_id,
            'invitation_id'     => $invitationId,
            'is_anonymous'      => false,
            'started_at'        => now(),
            'completion_status' => 'IN_PROGRESS',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        $request->session()->put($sessionKey, $responseId);

        return $responseId;
    }

    // Works out which questions are actually visible given the
    // submitted answers — mirrors the JS-side live evaluation on the
    // form itself. A question with no logic rules targeting it is
    // always visible. SHOW rules make it visible only if a match is
    // found (default hidden); SKIP rules hide it if a match is found
    // (default visible); if both target the same question, SKIP wins.
    private function resolveVisibleQuestions($questions, $logicRules, array $answers): array
    {
        $visible = [];
        $rulesByTarget = $logicRules->groupBy('target_question_id');

        foreach ($questions as $q) {
            $rules = $rulesByTarget->get($q->question_id, collect());
            if ($rules->isEmpty()) {
                $visible[] = $q->question_id;
                continue;
            }

            $showRules = $rules->where('action', 'SHOW');
            $skipRules = $rules->where('action', 'SKIP');

            $isVisible = $showRules->isEmpty() ? true : $showRules->contains(fn ($r) => $this->conditionMet($r, $answers));
            if ($skipRules->isNotEmpty() && $skipRules->contains(fn ($r) => $this->conditionMet($r, $answers))) {
                $isVisible = false;
            }
            if ($isVisible) {
                $visible[] = $q->question_id;
            }
        }

        return $visible;
    }

    private function conditionMet(object $rule, array $answers): bool
    {
        $sourceVal = $answers[$rule->source_question_id] ?? null;

        if ($rule->condition_option_id) {
            if (is_array($sourceVal)) {
                return in_array($rule->condition_option_id, $sourceVal, true);
            }
            return $sourceVal === $rule->condition_option_id;
        }

        if ($rule->condition_operator && $rule->condition_value !== null) {
            $num = is_numeric($sourceVal) ? (float) $sourceVal : null;
            $cmp = is_numeric($rule->condition_value) ? (float) $rule->condition_value : null;
            return match ($rule->condition_operator) {
                'EQUALS'       => (string) $sourceVal === (string) $rule->condition_value,
                'NOT_EQUALS'   => (string) $sourceVal !== (string) $rule->condition_value,
                'GREATER_THAN' => $num !== null && $cmp !== null && $num > $cmp,
                'LESS_THAN'    => $num !== null && $cmp !== null && $num < $cmp,
                default        => false,
            };
        }

        return false;
    }
}
