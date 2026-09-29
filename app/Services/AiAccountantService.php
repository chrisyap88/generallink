<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 19 Sep 2026 -- "AI Accountant", per Chris: "forget carolyn use
// another ai agents call AI Accountant... use a corporate account
// avatar man." A separate, deliberately narrow persona from Carolyn
// (the general GeneralLink Assistant) for exactly one job: master data
// for the CBE accounting module (Phase 1: Chart of Accounts, per
// Chris's uploaded AI_Master_Data_and_Transaction_Assistant_
// Specification.docx), starting with a non-accountant never having to
// pick Type/Group/Category/Normal Balance themselves.
//
// Kept intentionally small — no voice, no long-term memory, no
// relationship-building, no ticketing, no guided navigation, none of
// Carolyn's ~5000-word system prompt. That size and mixed purpose is
// exactly what caused Claude to stop reliably calling the Chart of
// Accounts tool once earlier failed attempts were sitting in the same
// conversation's history (she started inventing excuses instead of
// retrying). A small, single-purpose brain with one real capability is
// far more reliable for this — same reasoning as why COA Chat's own
// classification (CoaChatAssistantService) is its own tiny service
// rather than folded into a bigger one.
class AiAccountantService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * @param array<int, array{role:string, content:mixed}> $history
     * @param array{full_name:?string, role:?string} $context
     * @return array{status:string, reply?:string, history?:array, coa_result?:array, handoff_to_carolyn?:array, message?:string}
     */
    public function chat(array $history, string $userMessage, array $context): array
    {
        $requestId = substr(md5(uniqid('', true)), 0, 8);
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            Log::warning("[AI Accountant][$requestId] chat() aborted — ANTHROPIC_API_KEY missing.");
            return ['status' => 'ERROR', 'message' => 'The AI Accountant is not configured yet (missing ANTHROPIC_API_KEY in .env). Please contact Admin.'];
        }

        $system = $this->buildSystemPrompt($context);
        $tools = $this->tools();

        $messages = $history;
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $first = $this->callClaude($apiKey, $system, $messages, $tools, $requestId);
        if ($first['status'] !== 'OK') {
            return $first;
        }

        $blocks = $first['content'];
        $toolUse = collect($blocks)->firstWhere('type', 'tool_use');

        if (!$toolUse) {
            $text = collect($blocks)->firstWhere('type', 'text')['text'] ?? "Sorry, could you rephrase that?";
            $messages[] = ['role' => 'assistant', 'content' => $blocks];
            return ['status' => 'OK', 'reply' => $text, 'history' => $messages];
        }

        // NEW 19 Sep 2026 -- father/daughter persona handoff, per Chris:
        // if the request isn't Chart of Accounts / Accounting at all
        // (e.g. "i want to make a complaint"), the AI Accountant ("Chris")
        // narratively hands the user to Carolyn rather than trying to
        // help himself. The actual UI hand-off (opening Carolyn's panel
        // and having her greet the user by name) happens client-side in
        // ai-accountant-widget.blade.php, driven by the handoff_to_carolyn
        // field below -- this service only decides WHEN to hand off and
        // supplies the greeting text.
        if ($toolUse['name'] === 'handoff_to_carolyn') {
            $topic = trim((string) ($toolUse['input']['topic'] ?? 'something else'));
            $name = trim((string) ($context['full_name'] ?? ''));
            $firstName = $name !== '' ? explode(' ', $name)[0] : 'there';
            $carolynGreeting = "Sure! Hi {$firstName}, I'm happy to assist you — may I know your request?";

            $toolResultText = "This request is outside Chart of Accounts / Accounting, so hand it off narratively in ONE short sentence addressed to Carolyn, e.g. \"Carolyn, there is {$topic} here, please take over.\" Do not attempt to answer the request yourself.";

            $messages[] = ['role' => 'assistant', 'content' => $blocks];
            $messages[] = ['role' => 'user', 'content' => [
                ['type' => 'tool_result', 'tool_use_id' => $toolUse['id'], 'content' => $toolResultText],
            ]];

            $second = $this->callClaude($apiKey, $system, $messages, $tools, $requestId);
            $text = ($second['status'] === 'OK')
                ? (collect($second['content'])->firstWhere('type', 'text')['text'] ?? "Carolyn, there is {$topic} here, please take over.")
                : "Carolyn, there is {$topic} here, please take over.";
            if ($second['status'] === 'OK') {
                $messages[] = ['role' => 'assistant', 'content' => $second['content']];
            }

            return [
                'status' => 'OK',
                'reply' => $text,
                'history' => $messages,
                'handoff_to_carolyn' => ['greeting' => $carolynGreeting],
            ];
        }

        if ($toolUse['name'] !== 'coa_lookup_or_propose') {
            $text = collect($blocks)->firstWhere('type', 'text')['text'] ?? "Sorry, could you rephrase that?";
            $messages[] = ['role' => 'assistant', 'content' => $blocks];
            return ['status' => 'OK', 'reply' => $text, 'history' => $messages];
        }

        $description = trim((string) ($toolUse['input']['description'] ?? ''));
        $resolved = $this->resolveCbeGroupAndNode($context);

        Log::info("[AI Accountant][$requestId] coa_lookup_or_propose", ['description' => $description] + $resolved);

        $result = ($resolved['group_label_id'] && $resolved['node_id'])
            ? (new CoaChatAssistantService())->classify($description, $resolved['group_label_id'], $resolved['node_id'])
            : ['status' => 'ERROR', 'message' => 'This community\'s Chart of Accounts setup could not be found — if there is more than one community set up on this account, please say which one you mean.'];

        if ($result['status'] === 'MATCH') {
            $a = $result['account'];
            $toolResultText = "A matching account already exists: code {$a['account_code']}, name \"{$a['account_name']}\". Tell the user plainly that this already exists so they don't need to create a new one, and mention the code and name — the app is also showing them the full details as a card below, so you don't need to recite every field.";
        } elseif ($result['status'] === 'NEW') {
            $p = $result['proposal'];
            $toolResultText = "Drafted a new account proposal: type {$p['account_type']}, group " . ($p['account_group_name'] ?: 'none') . ", category " . ($p['account_category_name'] ?: 'none, optional') . ", code {$p['account_code']}, name \"{$p['account_name']}\". Tell the user plainly you've drafted this and ask them to check the details in the card below before confirming — do NOT recite every field yourself, the app already shows them visually. Make clear nothing is saved until they tap Confirm.";
        } else {
            $toolResultText = 'The lookup could not be completed: ' . ($result['message'] ?? 'unknown error') . '. Apologize briefly and suggest they try again shortly, or use the manual Add Account screen under Chart of Accounts.';
        }

        $messages[] = ['role' => 'assistant', 'content' => $blocks];
        $messages[] = ['role' => 'user', 'content' => [
            ['type' => 'tool_result', 'tool_use_id' => $toolUse['id'], 'content' => $toolResultText],
        ]];

        $second = $this->callClaude($apiKey, $system, $messages, $tools, $requestId);
        $text = ($second['status'] === 'OK')
            ? (collect($second['content'])->firstWhere('type', 'text')['text'] ?? 'Here you go.')
            : 'Here you go — please check the details below.';
        if ($second['status'] === 'OK') {
            $messages[] = ['role' => 'assistant', 'content' => $second['content']];
        }

        $response = ['status' => 'OK', 'reply' => $text, 'history' => $messages];
        if ($result['status'] === 'MATCH') {
            $response['coa_result'] = ['type' => 'MATCH', 'account' => $result['account']];
        } elseif ($result['status'] === 'NEW') {
            $response['coa_result'] = ['type' => 'NEW', 'proposal' => $result['proposal']];
        }
        return $response;
    }

    // Same auto-resolve rule as every other CBE screen (ResolvesCbeActiveNode),
    // plus a single-community shortcut so an Admin never needs to have
    // already picked an entity on a different screen just to talk to
    // this agent: if there's only one CBE community on this install,
    // there's nothing to actually pick, so go straight to its root node.
    private function resolveCbeGroupAndNode(array $context): array
    {
        $nodeId = $context['cbe_node_id'] ?? null;

        if ($nodeId) {
            $groupLabelId = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->value('group_label_id');
            return ['group_label_id' => $groupLabelId, 'node_id' => $nodeId];
        }

        $cbeGroupIds = DB::table('group_labels')->where('group_type', 'CBE')->pluck('group_label_id');
        if ($cbeGroupIds->count() === 1) {
            $groupLabelId = $cbeGroupIds->first();
            $rootNodeId = DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupLabelId)->whereNull('parent_node_id')->value('node_id');
            return ['group_label_id' => $groupLabelId, 'node_id' => $rootNodeId];
        }

        return ['group_label_id' => null, 'node_id' => null];
    }

    private function buildSystemPrompt(array $context): string
    {
        $name = $context['full_name'] ?? 'there';

        return <<<TXT
You are the AI Accountant — people know you as Chris. You are a focused GeneralLink assistant for Chart of Accounts / GL Code questions inside the CBE (temple/NGO/SME community accounting) module. You are speaking with {$name}, who is NOT a trained accountant — never ask them to pick Account Type, Account Group, Account Category, or Debit/Credit Normal Balance themselves; that is your job.

Your ONLY real capability right now is the coa_lookup_or_propose tool: call it whenever the user wants to FIND, CHECK, or ADD a Chart of Accounts / GL Code entry (e.g. "do we have an account for stationery", "add a GL code for hotel bills", "what's the code for donations", "set up an expense account for printing"). It searches their real existing accounts first (never invents a GL Code), and returns either a MATCH or a NEW proposal, shown to the user as a card with a Confirm button — you never save anything yourself, and you never recite every field back to them since the card already shows it. After calling it, just summarize the outcome in one short, plain sentence.

If the user asks about Suppliers, Customers, Bank Accounts, Fixed Assets, Cost Centres, or Tax Codes, say those are not wired up to you yet and are coming soon, one at a time — do NOT hand those off to Carolyn, since they are still your area (Accounting master data), just not built yet.

If the user asks about anything that is genuinely NOT Accounting at all (e.g. a complaint, a general GeneralLink question, anything from another module) — call the handoff_to_carolyn tool instead of trying to answer it yourself, so Carolyn (who handles everything else in GLADE) can take over.

If asked who Carolyn is, say warmly that Carolyn is your lovely daughter — she helps launch Service/Complaint Help Desk tickets, helps write messages professionally in English, Bahasa Malaysia and Chinese, and also teaches how to use the GLADE system, every module and program.

Keep replies short — 1-2 sentences by default. Plain, professional, direct tone; no markdown, no bullet points, no emoji.
TXT;
    }

    private function tools(): array
    {
        return [
            [
                'name' => 'coa_lookup_or_propose',
                'description' => 'Find, check, or set up a Chart of Accounts / GL Code entry for this community. Runs a real lookup against their existing accounts (never invents a GL Code) and returns either a MATCH or a NEW proposal for the user to confirm. Only call this for an actual lookup/setup request, not a general explanation question.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string', 'description' => 'A short, plain-language description of what the account is for, taken from what the user said (e.g. "hotel bills", "printing and stationery expenses").'],
                    ],
                    'required' => ['description'],
                ],
            ],
            [
                'name' => 'handoff_to_carolyn',
                'description' => 'Hand the conversation off to Carolyn when the user asks about something genuinely outside Chart of Accounts / Accounting (e.g. a complaint, a general GeneralLink question, another module). Do not use this for Supplier/Customer/Bank/Fixed Asset/Cost Centre/Tax Code questions -- those are your own area, just not built yet.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string', 'description' => 'A short plain description of what the user wants help with, e.g. "a complaint", "a question about their commission".'],
                    ],
                    'required' => ['topic'],
                ],
            ],
        ];
    }

    private function callClaude(string $apiKey, string $system, array $messages, array $tools, string $requestId): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])->timeout(30)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 1024,
                'system' => $system,
                'tools' => $tools,
                'messages' => $messages,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[AI Accountant][$requestId] request failed: " . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach the AI Accountant right now — please try again in a moment.'];
        }

        if ($response->failed()) {
            Log::warning("[AI Accountant][$requestId] failed (" . $response->status() . '): ' . $response->body());
            if (in_array($response->status(), [401, 403])) {
                return ['status' => 'ERROR', 'message' => 'The API key was rejected, or the account has run out of credit.'];
            }
            return ['status' => 'ERROR', 'message' => 'The AI Accountant could not respond right now — please try again.'];
        }

        $json = $response->json();
        if (empty($json['content'])) {
            return ['status' => 'ERROR', 'message' => 'The AI Accountant returned an unexpected response.'];
        }

        return ['status' => 'OK', 'content' => $json['content']];
    }
}
