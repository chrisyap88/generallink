<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 28 Jul 2026 — EspoCRM integration (task #251). Chris adopted EspoCRM
// (self-hosted, installed at C:\xampp\htdocs\espocrm\EspoCRM-9.2.7, see
// CRM_Evaluation_Recommendation.docx for why) to own follow-up scheduling,
// calendar, and customer-communication history going forward — GeneralLink
// stops extending its own versions of those. Escalation-to-upline logic and
// the insurance-specific Renewal Reminder trigger stay in GeneralLink itself
// (they're domain logic, not generic CRM features).
//
// This is a simple API-key integration (mirrors the shape of
// GoogleFormsService, but far simpler — EspoCRM's REST API authenticates
// every request with one static key, no OAuth/token-refresh dance needed).
// Agents never log into EspoCRM directly; every call here runs as the one
// shared "GeneralLink Integration" API User created in EspoCRM
// Administration -> API Users.
class EspoCrmService
{
    /** @var string|null cached for the lifetime of one request */
    private static ?string $cachedUserId = null;

    public function isConfigured(): bool
    {
        return filled(config('services.espocrm.base_url')) && filled(config('services.espocrm.api_key'));
    }

    /**
     * EspoCRM requires every Task/Meeting to have an assignedUser. Since
     * there's only ever one API user calling in (agents never log into
     * EspoCRM directly), everything gets self-assigned to that same
     * "GeneralLink Integration" user.
     */
    private function integrationUserId(): ?string
    {
        if (self::$cachedUserId !== null) {
            return self::$cachedUserId;
        }

        try {
            $response = $this->client()->get('/App/user');
            if ($response->successful()) {
                self::$cachedUserId = $response->json('user.id');
                return self::$cachedUserId;
            }
        } catch (\Throwable $e) {
            Log::warning('EspoCRM integrationUserId exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Base HTTP client, already carrying the API key and pointed at
     * EspoCRM's REST API root (…/api/v1).
     */
    protected function client()
    {
        $baseUrl = rtrim(config('services.espocrm.base_url'), '/');

        return Http::withHeaders([
            'X-Api-Key' => config('services.espocrm.api_key'),
            'Content-Type' => 'application/json',
        ])->baseUrl($baseUrl.'/api/v1')->timeout(15);
    }

    /**
     * Quick round-trip to confirm the base URL + API key actually work.
     * Returns ['ok' => bool, 'message' => string, 'detail' => mixed].
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'EspoCRM base URL / API key not set in .env yet.'];
        }

        try {
            // /App/user is EspoCRM's lightweight "who am I" endpoint —
            // cheap, and proves both the URL and the API key are valid.
            $response = $this->client()->get('/App/user');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'ok' => true,
                    'message' => 'Connected to EspoCRM successfully.',
                    'detail' => $data['user']['userName'] ?? $data,
                ];
            }

            return [
                'ok' => false,
                'message' => 'EspoCRM responded with an error (HTTP '.$response->status().').',
                'detail' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('EspoCRM connection test failed: '.$e->getMessage());
            return ['ok' => false, 'message' => 'Could not reach EspoCRM: '.$e->getMessage()];
        }
    }

    /**
     * Creates a Task in EspoCRM for a follow-up reminder.
     *
     * @param string      $name        short title, e.g. "Follow up: Ahmad bin Ali"
     * @param string|null $description longer note (customer context, why follow up)
     * @param \DateTimeInterface|string|null $dueAt when it's due
     * @return string|null the new EspoCRM Task id, or null on failure (never throws —
     *                      a down/misconfigured EspoCRM must not break GeneralLink's own flow)
     */
    public function createFollowUpTask(string $name, ?string $description = null, $dueAt = null): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $payload = ['name' => $name];
            if ($userId = $this->integrationUserId()) {
                $payload['assignedUserId'] = $userId;
            }
            if ($description) {
                $payload['description'] = $description;
            }
            if ($dueAt) {
                // EspoCRM's Task dateEnd is a full datetime field — a
                // bare "Y-m-d" (what GeneralLink's date-only reminder
                // form submits) fails EspoCRM's validation, so always
                // normalize through Carbon and default the time to 9am
                // when only a date was given.
                $carbonDue = $dueAt instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($dueAt)
                    : \Illuminate\Support\Carbon::parse($dueAt);
                if ($carbonDue->format('H:i:s') === '00:00:00') {
                    $carbonDue->setTime(9, 0, 0);
                }
                $payload['dateEnd'] = $carbonDue->format('Y-m-d H:i:s');
            }

            $response = $this->client()->post('/Task', $payload);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning('EspoCRM createFollowUpTask failed: HTTP '.$response->status().' — '.$response->body());
            return null;
        } catch (\Throwable $e) {
            Log::warning('EspoCRM createFollowUpTask exception: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Creates a Meeting (calendar event) in EspoCRM.
     *
     * @param string $name
     * @param \DateTimeInterface|string $start
     * @param \DateTimeInterface|string|null $end defaults to start + 30 minutes
     */
    public function createCalendarEvent(string $name, $start, $end = null, ?string $description = null): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            // Same normalization as createFollowUpTask() — a bare
            // "Y-m-d" fails EspoCRM's datetime validation, so always
            // go through Carbon and default a missing time to 9am.
            $startCarbon = $start instanceof \DateTimeInterface
                ? \Illuminate\Support\Carbon::instance($start)
                : \Illuminate\Support\Carbon::parse($start);
            if ($startCarbon->format('H:i:s') === '00:00:00') {
                $startCarbon->setTime(9, 0, 0);
            }
            $startFormatted = $startCarbon->format('Y-m-d H:i:s');

            if (!$end) {
                $endFormatted = $startCarbon->copy()->addMinutes(30)->format('Y-m-d H:i:s');
            } else {
                $endCarbon = $end instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($end)
                    : \Illuminate\Support\Carbon::parse($end);
                if ($endCarbon->format('H:i:s') === '00:00:00') {
                    $endCarbon->setTime(9, 30, 0);
                }
                $endFormatted = $endCarbon->format('Y-m-d H:i:s');
            }

            $payload = [
                'name' => $name,
                'dateStart' => $startFormatted,
                'dateEnd' => $endFormatted,
            ];
            if ($userId = $this->integrationUserId()) {
                $payload['assignedUserId'] = $userId;
            }
            if ($description) {
                $payload['description'] = $description;
            }

            $response = $this->client()->post('/Meeting', $payload);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning('EspoCRM createCalendarEvent failed: HTTP '.$response->status().' — '.$response->body());
            return null;
        } catch (\Throwable $e) {
            Log::warning('EspoCRM createCalendarEvent exception: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Updates an existing EspoCRM Meeting's name/time/description —
     * called when the matching GeneralLink calendar event is edited.
     */
    public function updateCalendarEvent(string $meetingId, string $name, $start, $end = null, ?string $description = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $startCarbon = $start instanceof \DateTimeInterface
                ? \Illuminate\Support\Carbon::instance($start)
                : \Illuminate\Support\Carbon::parse($start);
            if ($startCarbon->format('H:i:s') === '00:00:00') {
                $startCarbon->setTime(9, 0, 0);
            }

            $endCarbon = $end
                ? ($end instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($end) : \Illuminate\Support\Carbon::parse($end))
                : $startCarbon->copy()->addMinutes(30);
            if ($endCarbon->format('H:i:s') === '00:00:00') {
                $endCarbon->setTime(9, 30, 0);
            }

            $payload = [
                'name' => $name,
                'dateStart' => $startCarbon->format('Y-m-d H:i:s'),
                'dateEnd' => $endCarbon->format('Y-m-d H:i:s'),
            ];
            if ($description !== null) {
                $payload['description'] = $description;
            }

            $response = $this->client()->put('/Meeting/'.$meetingId, $payload);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM updateCalendarEvent exception: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an EspoCRM Meeting — called when the matching GeneralLink
     * calendar event is removed.
     */
    public function deleteCalendarEvent(string $meetingId): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client()->delete('/Meeting/'.$meetingId);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM deleteCalendarEvent exception: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Marks an EspoCRM Task as Completed — called when the matching
     * GeneralLink personal reminder is marked Done, so the two stay
     * in sync without the agent ever touching EspoCRM directly.
     */
    public function completeTask(string $taskId): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client()->put('/Task/'.$taskId, ['status' => 'Completed']);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM completeTask exception: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an EspoCRM Task — called when the matching GeneralLink
     * personal reminder is deleted.
     */
    public function deleteTask(string $taskId): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client()->delete('/Task/'.$taskId);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM deleteTask exception: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Fetches open (not-completed) Tasks from EspoCRM, most-recently-due
     * first — used to surface follow-up items back inside GeneralLink's
     * own screens without agents ever visiting EspoCRM's UI.
     */
    public function listOpenTasks(int $limit = 50): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->client()->get('/Task', [
                'select' => 'id,name,description,dateEnd,status',
                'where' => [
                    [
                        'type' => 'notIn',
                        'attribute' => 'status',
                        'value' => ['Completed', 'Canceled'],
                    ],
                ],
                'orderBy' => 'dateEnd',
                'order' => 'asc',
                'maxSize' => $limit,
            ]);

            if ($response->successful()) {
                return $response->json('list') ?? [];
            }

            Log::warning('EspoCRM listOpenTasks failed: HTTP '.$response->status());
            return [];
        } catch (\Throwable $e) {
            Log::warning('EspoCRM listOpenTasks exception: '.$e->getMessage());
            return [];
        }
    }

    // ── Contacts (Customers -> Contact, task #252) ─────────────────

    /**
     * Splits a single "full_name" field into EspoCRM's separate
     * firstName/lastName — a simple last-word-is-surname heuristic.
     * Good enough for keeping the two systems roughly in sync; not
     * meant to be perfectly correct for every naming convention.
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        if (count($parts) <= 1) {
            return ['firstName' => $fullName ?: '(no name)', 'lastName' => '-'];
        }
        $lastName = array_pop($parts);
        return ['firstName' => implode(' ', $parts), 'lastName' => $lastName];
    }

    /**
     * NEW 29 Jul 2026 — fixes a live 400 found via Chris's own testing:
     * EspoCRM's phoneNumber field rejected "016-6860771" with
     * {"field":"phoneNumber","type":"valid"}. Stripping the dashes alone
     * (first attempt) still failed — turned out EspoCRM's phone field
     * validates against a real country-aware format, and its widget is
     * set to Malaysia (+60), expecting full international numbers, not
     * the local "0..." format GeneralLink stores. Converts local
     * Malaysian mobile/landline numbers (leading 0) to E.164
     * international format (+60...) before sending — since GeneralLink
     * is a Malaysian insurance platform, +60 is always the right
     * assumption here (not made generic/configurable, since there's no
     * other country in play).
     */
    private function sanitizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }
        $digits = preg_replace('/[^\d+]/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '+')) {
            return $digits; // already international format, e.g. +60166860771
        }
        if (str_starts_with($digits, '60')) {
            return '+' . $digits; // has the country code but missing the leading +
        }
        if (str_starts_with($digits, '0')) {
            return '+60' . substr($digits, 1); // local format "016..." -> "+6016..."
        }
        return '+60' . $digits; // no leading 0 at all — assume it's still local
    }

    private function contactPayload(object $customer): array
    {
        $name = $this->splitName((string) $customer->full_name);
        $payload = [
            'firstName' => $name['firstName'],
            'lastName' => $name['lastName'],
        ];
        if (!empty($customer->email)) {
            $payload['emailAddress'] = $customer->email;
        }
        if ($cleanPhone = $this->sanitizePhone($customer->phone ?? null)) {
            $payload['phoneNumber'] = $cleanPhone;
        }
        if (!empty($customer->address)) {
            $payload['addressStreet'] = $customer->address;
        }
        if (!empty($customer->city)) {
            $payload['addressCity'] = $customer->city;
        }
        if (!empty($customer->state)) {
            $payload['addressState'] = $customer->state;
        }
        if (!empty($customer->postcode)) {
            $payload['addressPostalCode'] = $customer->postcode;
        }
        if ($userId = $this->integrationUserId()) {
            $payload['assignedUserId'] = $userId;
        }
        return $payload;
    }

    /**
     * Creates a new EspoCRM Contact for a GeneralLink customer/prospect.
     * Returns the new Contact id, or null on failure (non-fatal — the
     * GeneralLink customer record is always the source of truth and
     * must save successfully regardless of EspoCRM's availability).
     *
     * @param object $customer row from `customers` (full_name, email,
     *   phone, address, postcode, city, state)
     */
    public function createContact(object $customer): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client()->post('/Contact', $this->contactPayload($customer));

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning('EspoCRM createContact failed: HTTP '.$response->status().' — '.$response->body());
            return null;
        } catch (\Throwable $e) {
            Log::warning('EspoCRM createContact exception: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Patches an existing EspoCRM Contact with the customer's current
     * details — called whenever the GeneralLink customer record is
     * edited, so the two stay in sync.
     */
    public function updateContact(string $contactId, object $customer): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client()->put('/Contact/'.$contactId, $this->contactPayload($customer));
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM updateContact exception: '.$e->getMessage());
            return false;
        }
    }

    // ── Opportunities (Sales Transactions -> Opportunity, task #253) ──

    /**
     * Creates an Opportunity in EspoCRM for a policy/sale, linked to the
     * customer's Contact record (via EspoCRM's contactId link field,
     * which also auto-links the parent Account if the Contact has one).
     *
     * @param string      $name        e.g. "Private Car Policy Schedule — Ahmad bin Ali"
     * @param string|null $contactId   the customer's EspoCRM Contact id, if known
     * @param float|null  $amount      gross premium / sale amount
     * @param string|null $stage       EspoCRM Opportunity stage name, e.g. "Closed Won"
     * @param \DateTimeInterface|string|null $closeDate policy/sale date
     */
    public function createOpportunity(
        string $name,
        ?string $contactId = null,
        ?float $amount = null,
        ?string $stage = 'Closed Won',
        $closeDate = null,
        ?string $description = null
    ): ?string {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $payload = ['name' => $name, 'stage' => $stage];
            if ($contactId) {
                $payload['contactId'] = $contactId;
            }
            if ($amount !== null) {
                $payload['amount'] = $amount;
            }
            if ($closeDate) {
                $carbon = $closeDate instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($closeDate)
                    : \Illuminate\Support\Carbon::parse($closeDate);
                $payload['closeDate'] = $carbon->format('Y-m-d');
            }
            if ($description) {
                $payload['description'] = $description;
            }
            if ($userId = $this->integrationUserId()) {
                $payload['assignedUserId'] = $userId;
            }

            $response = $this->client()->post('/Opportunity', $payload);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning('EspoCRM createOpportunity failed: HTTP '.$response->status().' — '.$response->body());
            return null;
        } catch (\Throwable $e) {
            Log::warning('EspoCRM createOpportunity exception: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Patches an existing Opportunity — called when the matching Sales
     * Transaction is confirmed/edited (e.g. stage moving to Closed Won
     * on Admin confirmation).
     */
    public function updateOpportunity(string $opportunityId, array $fields): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client()->put('/Opportunity/'.$opportunityId, $fields);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM updateOpportunity exception: '.$e->getMessage());
            return false;
        }
    }

    // ── Campaigns (Broadcast Campaigns -> Campaign, task #254) ──────

    /**
     * Creates a Campaign in EspoCRM mirroring a GeneralLink Broadcast
     * Campaign (Growth & Outreach Center). EspoCRM's "type" field only
     * accepts its own fixed list (Email, Newsletter, ...) — since
     * GeneralLink's channels (WhatsApp, SMS, LINE, etc.) don't map
     * cleanly onto that list, this always uses the generic "Other" type
     * and puts the real channel name in the description instead.
     */
    public function createCampaign(string $name, ?string $description = null, $startDate = null, $endDate = null): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $payload = ['name' => $name, 'type' => 'Other', 'status' => 'Planning'];
            if ($description) {
                $payload['description'] = $description;
            }
            if ($startDate) {
                $carbon = $startDate instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($startDate)
                    : \Illuminate\Support\Carbon::parse($startDate);
                $payload['startDate'] = $carbon->format('Y-m-d');
            }
            if ($endDate) {
                $carbon = $endDate instanceof \DateTimeInterface
                    ? \Illuminate\Support\Carbon::instance($endDate)
                    : \Illuminate\Support\Carbon::parse($endDate);
                $payload['endDate'] = $carbon->format('Y-m-d');
            }
            if ($userId = $this->integrationUserId()) {
                $payload['assignedUserId'] = $userId;
            }

            $response = $this->client()->post('/Campaign', $payload);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning('EspoCRM createCampaign failed: HTTP '.$response->status().' — '.$response->body());
            return null;
        } catch (\Throwable $e) {
            Log::warning('EspoCRM createCampaign exception: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Patches an existing Campaign's status — called when the matching
     * GeneralLink Broadcast Campaign's own status changes (e.g.
     * SCHEDULED -> SENT).
     */
    public function updateCampaignStatus(string $campaignId, string $status): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client()->put('/Campaign/'.$campaignId, ['status' => $status]);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM updateCampaignStatus exception: '.$e->getMessage());
            return false;
        }
    }

    // ── Cases (Support Tickets -> Case, task #259) ──────────────────
    // Help Desk / Case Management / Complaint Management / Service
    // Requests are all the same EspoCRM entity ("Case") — free, core,
    // no paid add-on needed. GeneralLink's own ticket_type/priority
    // strings don't try to match EspoCRM's fixed enums exactly (avoids
    // repeating the same "invalid enum value" 400 risk hit earlier with
    // Campaign's type field) — only priority (a stable, standard EspoCRM
    // enum: Low/Normal/High) is mapped across; the ticket type is folded
    // into the Case name/description instead, which always succeeds.

    private function casePriority(string $priority): string
    {
        return match (strtoupper($priority)) {
            'HIGH' => 'High',
            'LOW' => 'Low',
            default => 'Normal',
        };
    }

    /**
     * Creates a Case in EspoCRM for a Support Ticket.
     *
     * @param string      $name       e.g. "[Complaint] Late claim processing — Ahmad bin Ali"
     * @param string|null $contactId  the customer's EspoCRM Contact id, if known
     */
    public function createCase(string $name, ?string $description = null, ?string $contactId = null, string $priority = 'MEDIUM'): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $payload = [
                'name' => $name,
                'status' => 'New',
                'priority' => $this->casePriority($priority),
            ];
            if ($contactId) {
                $payload['contactId'] = $contactId;
            }
            if ($description) {
                $payload['description'] = $description;
            }
            if ($userId = $this->integrationUserId()) {
                $payload['assignedUserId'] = $userId;
            }

            $response = $this->client()->post('/Case', $payload);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning('EspoCRM createCase failed: HTTP '.$response->status().' — '.$response->body());
            return null;
        } catch (\Throwable $e) {
            Log::warning('EspoCRM createCase exception: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Patches an existing Case's status — called whenever the matching
     * Support Ticket's own status changes. GeneralLink's OPEN/IN_PROGRESS/
     * RESOLVED/CLOSED map onto EspoCRM's own New/Assigned/Closed —
     * EspoCRM's Case status list also includes Pending/Rejected/Duplicate,
     * which GeneralLink never sets itself, so those are left alone.
     */
    public function updateCaseStatus(string $caseId, string $status): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $espoStatus = match (strtoupper($status)) {
            'IN_PROGRESS' => 'Assigned',
            'RESOLVED', 'CLOSED' => 'Closed',
            default => 'New',
        };

        try {
            $response = $this->client()->put('/Case/'.$caseId, ['status' => $espoStatus]);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('EspoCRM updateCaseStatus exception: '.$e->getMessage());
            return false;
        }
    }

    // ── Knowledge Base (Help Center read pane, task #261) ────────────

    /**
     * Fetches published Knowledge Base articles — free, core EspoCRM
     * feature (see EspoCRM_Feature_Reuse_Review.docx, Section 3: a
     * genuine reuse win). Chris authors articles directly in EspoCRM's
     * own editor (Admin-only, rarely touched); GeneralLink just reads
     * the published ones back in, read-only, no login required.
     */
    public function listKnowledgeBaseArticles(int $limit = 50): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->client()->get('/KnowledgeBaseArticle', [
                'select' => 'id,name,body',
                'where' => [
                    [
                        'type' => 'equals',
                        'attribute' => 'status',
                        'value' => 'Published',
                    ],
                ],
                'orderBy' => 'name',
                'order' => 'asc',
                'maxSize' => $limit,
            ]);

            if ($response->successful()) {
                return $response->json('list') ?? [];
            }

            Log::warning('EspoCRM listKnowledgeBaseArticles failed: HTTP '.$response->status().' — '.$response->body());
            return [];
        } catch (\Throwable $e) {
            Log::warning('EspoCRM listKnowledgeBaseArticles exception: '.$e->getMessage());
            return [];
        }
    }

    // REMOVED 12 Aug 2026 per Chris: "remove the article/faq folder" —
    // the FAQ tab + "+ Add Article" publish flow that used this method
    // was pulled back out. listKnowledgeBaseArticles() above is untouched.

    // ── Customer Timeline (Contact Stream, task #263) ────────────────

    /**
     * Fetches a Contact's Stream — EspoCRM's built-in activity feed
     * (field changes, notes, related-record creation) for that record.
     * The last item on the original Feature Reuse Review list that
     * hadn't actually been wired up yet: a genuine reuse win (core,
     * free, no extra setup) surfaced read-only inside the Customer
     * Detail Activity Log tab, alongside GeneralLink's own audit log —
     * never merged into one feed, since the two have different shapes
     * and this keeps it obvious which system each entry came from.
     */
    public function listContactStream(string $contactId, int $limit = 20): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->client()->get('/Contact/'.$contactId.'/stream', [
                'maxSize' => $limit,
            ]);

            if ($response->successful()) {
                return $response->json('list') ?? [];
            }

            Log::warning('EspoCRM listContactStream failed: HTTP '.$response->status().' — '.$response->body());
            return [];
        } catch (\Throwable $e) {
            Log::warning('EspoCRM listContactStream exception: '.$e->getMessage());
            return [];
        }
    }
}
