<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Forms as GoogleFormsApi;
use Google\Service\Forms\BatchUpdateFormRequest;
use Google\Service\Forms\ChoiceQuestion;
use Google\Service\Forms\CreateItemRequest;
use Google\Service\Forms\DateQuestion;
use Google\Service\Forms\Form as GoogleForm;
use Google\Service\Forms\Grid;
use Google\Service\Forms\Info;
use Google\Service\Forms\Item;
use Google\Service\Forms\Location;
use Google\Service\Forms\Option;
use Google\Service\Forms\PublishSettings;
use Google\Service\Forms\PublishState;
use Google\Service\Forms\Question;
use Google\Service\Forms\QuestionGroupItem;
use Google\Service\Forms\QuestionItem;
use Google\Service\Forms\RatingQuestion;
use Google\Service\Forms\Request as GoogleFormsRequest;
use Google\Service\Forms\RowQuestion;
use Google\Service\Forms\ScaleQuestion;
use Google\Service\Forms\SetPublishSettingsRequest;
use Google\Service\Forms\TextQuestion;
use Google\Service\Forms\TimeQuestion;
use Google\Service\Forms\UpdateFormInfoRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 25 Jul 2026 — Survey Management, real Google Forms integration
// (task #227 follow-up, #238-#241). Chris chose to switch from the
// in-app Question Builder/public response form to real Google Forms:
// GeneralLink creates the survey "record" (objective, category,
// targeting, distribution message) and pushes the actual questionnaire
// to Google Forms via this service. Responses are read back live via
// the Forms API — no Sheets bridge needed since forms.responses.readonly
// already gives direct read access.
//
// The scopes requested here (forms.body, forms.responses.readonly,
// drive.file) must exactly match what Chris configured on the OAuth
// consent screen in Google Cloud Console — requesting a scope that
// wasn't added there will make Google reject the consent, or silently
// not grant it.
class GoogleFormsService
{
    public const SCOPES = [
        'https://www.googleapis.com/auth/forms.body',
        'https://www.googleapis.com/auth/forms.responses.readonly',
        'https://www.googleapis.com/auth/drive.file',
    ];

    // ── OAuth: connect / token storage ───────────────────────────

    public function client(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');   // needed to receive a refresh_token
        $client->setPrompt('consent');       // force refresh_token on every (re)connect
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    public function authUrl(): string
    {
        return $this->client()->createAuthUrl();
    }

    /**
     * Exchanges the ?code=... Google sends back to our callback route for
     * an access/refresh token pair, then stores it (encrypted) as the one
     * active connection.
     */
    public function handleCallback(string $code, ?string $agentId): void
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google rejected the connection: '.($token['error_description'] ?? $token['error']));
        }

        $email = null;
        try {
            $client->setAccessToken($token);
            $oauth2 = new \Google\Service\Oauth2($client);
            $email = $oauth2->userinfo->get()->email ?? null;
        } catch (\Throwable $e) {
            // Non-fatal — the connection still works without a stored email label.
        }

        DB::table('google_connections')->where('is_active', true)->update(['is_active' => false]);

        DB::table('google_connections')->insert([
            'connection_id'         => (string) Str::uuid(),
            'connected_by_agent_id' => $agentId,
            'google_email'          => $email,
            'access_token'          => Crypt::encryptString($token['access_token']),
            'refresh_token'         => isset($token['refresh_token']) ? Crypt::encryptString($token['refresh_token']) : null,
            'token_expires_at'      => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'scopes'                => implode(' ', self::SCOPES),
            'is_active'             => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    public function activeConnection(): ?object
    {
        return DB::table('google_connections')->where('is_active', true)->orderByDesc('created_at')->first();
    }

    public function isConnected(): bool
    {
        return $this->activeConnection() !== null;
    }

    public function disconnect(): void
    {
        DB::table('google_connections')->where('is_active', true)->update(['is_active' => false]);
    }

    /**
     * A Google\Client already carrying a valid (refreshed if necessary)
     * access token for the one connected account. Returns null if no
     * account is connected.
     */
    public function authorizedClient(): ?GoogleClient
    {
        $conn = $this->activeConnection();
        if (!$conn) {
            return null;
        }

        $client = $this->client();
        $accessToken = Crypt::decryptString($conn->access_token);
        $client->setAccessToken(['access_token' => $accessToken]);

        $expired = !$conn->token_expires_at || Carbon::parse($conn->token_expires_at)->isPast();
        if ($expired && $conn->refresh_token) {
            $refreshToken = Crypt::decryptString($conn->refresh_token);
            $new = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (isset($new['access_token'])) {
                DB::table('google_connections')->where('connection_id', $conn->connection_id)->update([
                    'access_token'     => Crypt::encryptString($new['access_token']),
                    'token_expires_at' => now()->addSeconds((int) ($new['expires_in'] ?? 3600)),
                    'updated_at'       => now(),
                ]);
                $client->setAccessToken($new);
            }
        }

        return $client;
    }

    // ── Form creation ─────────────────────────────────────────────

    /**
     * Creates a real Google Form for a GeneralLink survey record and
     * pushes all its questions to it. Returns
     * ['form_id' => ..., 'edit_url' => ..., 'response_url' => ...].
     *
     * @param object     $survey    row from `surveys`
     * @param \Illuminate\Support\Collection $questions rows from
     *        `survey_questions`, each with ->options (Collection) and
     *        ->matrix (array|null) attached by the caller.
     */
    public function createFormForSurvey(object $survey, $questions): array
    {
        $client = $this->authorizedClient();
        if (!$client) {
            throw new \RuntimeException('No Google account is connected yet. Connect one from Survey Management first.');
        }

        $service = new GoogleFormsApi($client);

        // Step 1 — create the form shell (create only accepts info.title).
        $info = new Info();
        $info->setTitle(Str::limit($survey->title, 300, ''));
        $form = new GoogleForm();
        $form->setInfo($info);
        $created = $service->forms->create($form);
        $formId = $created->getFormId();

        // Step 2 — one batchUpdate: set the description + add every question,
        // each at the next index so order matches the Question Builder order.
        $requests = [];

        if (!empty($survey->description) || !empty($survey->objective)) {
            $descParts = array_filter([$survey->description, $survey->objective ? 'Why we\'re asking: '.$survey->objective : null]);
            $updateInfo = new UpdateFormInfoRequest();
            $newInfo = new Info();
            $newInfo->setDescription(implode("\n\n", $descParts));
            $updateInfo->setInfo($newInfo);
            $updateInfo->setUpdateMask('description');
            $req = new GoogleFormsRequest();
            $req->setUpdateFormInfo($updateInfo);
            $requests[] = $req;
        }

        $index = 0;
        foreach ($questions as $q) {
            $item = $this->buildItem($q);
            if (!$item) {
                continue; // unsupported/empty question — skip rather than fail the whole push
            }
            $createItem = new CreateItemRequest();
            $createItem->setItem($item);
            $location = new Location();
            $location->setIndex($index);
            $createItem->setLocation($location);
            $req = new GoogleFormsRequest();
            $req->setCreateItem($createItem);
            $requests[] = $req;
            $index++;
        }

        if (!empty($requests)) {
            $batch = new BatchUpdateFormRequest();
            $batch->setRequests($requests);
            $service->forms->batchUpdate($formId, $batch);
        }

        // Step 3 — make sure the form is actually publicly reachable.
        // Accounts created after Google's 2022 draft/publish change need
        // this explicit call; older accounts don't support the method at
        // all, so a failure here is silently ignored (the form is already
        // live in that case).
        try {
            $publishState = new PublishState();
            $publishState->setIsPublished(true);
            $publishState->setIsAcceptingResponses(true);
            $settings = new PublishSettings();
            $settings->setPublishState($publishState);
            $setReq = new SetPublishSettingsRequest();
            $setReq->setPublishSettings($settings);
            $service->forms->setPublishSettings($formId, $setReq);
        } catch (\Throwable $e) {
            // fine — see comment above
        }

        $final = $service->forms->get($formId);

        return [
            'form_id'      => $formId,
            'edit_url'     => 'https://docs.google.com/forms/d/'.$formId.'/edit',
            'response_url' => $final->getResponderUri(),
        ];
    }

    /**
     * Live list of responses for a pushed survey, straight from Google
     * (no local caching/table — Chris's Response Management screen calls
     * this on demand).
     *
     * @return \Google\Service\Forms\FormResponse[]
     */
    public function fetchResponses(string $formId): array
    {
        $client = $this->authorizedClient();
        if (!$client) {
            throw new \RuntimeException('No Google account is connected.');
        }
        $service = new GoogleFormsApi($client);

        return $service->forms_responses->listFormsResponses($formId)->getResponses() ?? [];
    }

    // ── Question-type mapping ────────────────────────────────────

    private function buildItem(object $q): ?Item
    {
        $item = new Item();
        $item->setTitle($q->question_text);
        if (!empty($q->question_description)) {
            $item->setDescription($q->question_description);
        }

        $type = $q->question_type;
        $options = $q->question_options ?? collect();
        $matrix = $q->matrix ?? null;

        // LIKERT_SCALE / MATRIX use a question *group* (grid), not a
        // single question item.
        if ($type === 'MATRIX' || $type === 'LIKERT_SCALE') {
            $rows = $type === 'MATRIX' ? ($matrix['rows'] ?? []) : $options->pluck('option_text')->all();
            $cols = $type === 'MATRIX' ? ($matrix['columns'] ?? []) : range(1, $q->scale_max ?? 5);
            if (empty($rows) || empty($cols)) {
                return null;
            }
            $group = new QuestionGroupItem();
            $grid = new Grid();
            $colChoice = new ChoiceQuestion();
            $colChoice->setType('RADIO');
            $colChoice->setOptions(array_map(function ($c) {
                $o = new Option();
                $o->setValue((string) $c);
                return $o;
            }, $cols));
            $grid->setColumns($colChoice);
            $group->setGrid($grid);
            $group->setQuestions(array_map(function ($r) use ($q) {
                $row = new RowQuestion();
                $row->setTitle((string) $r);
                $question = new Question();
                $question->setRequired((bool) $q->is_required);
                $question->setRowQuestion($row);
                return $question;
            }, $rows));
            $item->setQuestionGroupItem($group);
            return $item;
        }

        $question = new Question();
        $question->setRequired((bool) $q->is_required);

        switch ($type) {
            case 'SINGLE_CHOICE':
            case 'YES_NO':
                $choice = new ChoiceQuestion();
                $choice->setType('RADIO');
                $opts = $type === 'YES_NO' ? ['Yes', 'No'] : $options->pluck('option_text')->all();
                if (empty($opts)) return null;
                $choice->setOptions(array_map(fn ($t) => tap(new Option(), fn ($o) => $o->setValue((string) $t)), $opts));
                $question->setChoiceQuestion($choice);
                break;

            case 'MULTIPLE_CHOICE':
                $choice = new ChoiceQuestion();
                $choice->setType('CHECKBOX');
                $opts = $options->pluck('option_text')->all();
                if (empty($opts)) return null;
                $choice->setOptions(array_map(fn ($t) => tap(new Option(), fn ($o) => $o->setValue((string) $t)), $opts));
                $question->setChoiceQuestion($choice);
                break;

            case 'DROPDOWN':
                $choice = new ChoiceQuestion();
                $choice->setType('DROP_DOWN');
                $opts = $options->pluck('option_text')->all();
                if (empty($opts)) return null;
                $choice->setOptions(array_map(fn ($t) => tap(new Option(), fn ($o) => $o->setValue((string) $t)), $opts));
                $question->setChoiceQuestion($choice);
                break;

            case 'RATING_SCALE':
            case 'NUMERIC_RATING':
                $scale = new ScaleQuestion();
                $scale->setLow(1);
                $scale->setHigh($q->scale_max ?? 5);
                $question->setScaleQuestion($scale);
                break;

            case 'STAR_RATING':
                $rating = new RatingQuestion();
                $rating->setIconType('STAR');
                $rating->setRatingScaleLevel($q->scale_max ?? 5);
                $question->setRatingQuestion($rating);
                break;

            case 'LONG_TEXT':
                $text = new TextQuestion();
                $text->setParagraph(true);
                $question->setTextQuestion($text);
                break;

            case 'SHORT_TEXT':
            case 'EMAIL':
            case 'PHONE':
            case 'NUMBER':
                $text = new TextQuestion();
                $text->setParagraph(false);
                $question->setTextQuestion($text);
                break;

            case 'DATE':
                $date = new DateQuestion();
                $date->setIncludeYear(true);
                $question->setDateQuestion($date);
                break;

            case 'TIME':
                $question->setTimeQuestion(new TimeQuestion());
                break;

            default:
                return null;
        }

        $questionItem = new QuestionItem();
        $questionItem->setQuestion($question);
        $item->setQuestionItem($questionItem);
        return $item;
    }
}
