<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;

// NEW 8 Aug 2026 — "Carolyn help to write," available anywhere in
// GeneralLink a logged-in user (agent OR vendor) composes free text:
// Notice Board, Offer Requests, WhatsApp, Help Desk, Broadcast
// Campaigns, Contests, Public Profile bio, Surveys, and anywhere else
// this gets wired up going forward. One shared endpoint, registered
// under BOTH the auth:agent group and the auth:vendor group (see
// routes/web.php), since both kinds of user compose text somewhere.
//
// $content_type is validated against AiAssistantService::WRITE_ASSIST_TYPES
// — a fixed server-side whitelist — so the actual prompt wording is never
// built from raw client input.
class AiWriteAssistController extends Controller
{
    public function assist(Request $request, AiAssistantService $ai)
    {
        $request->validate([
            'title'        => ['nullable', 'string', 'max:200'],
            'body'         => ['required', 'string', 'max:4000'],
            'content_type' => ['required', 'string', 'in:' . implode(',', array_keys(AiAssistantService::WRITE_ASSIST_TYPES))],
        ]);

        // NEW 28 Aug 2026 — per Chris: "rephrase depend the language
        // preference." app()->getLocale() reflects whatever the user
        // currently has their interface set to (en/ms/zh) — never taken
        // from raw client input, same whitelist-only spirit as content_type.
        $result = $ai->improveWrittenText(
            (string) $request->input('content_type'),
            (string) $request->input('body'),
            $request->filled('title') ? (string) $request->input('title') : null,
            app()->getLocale()
        );

        if ($result['status'] !== 'OK') {
            return response()->json(['status' => 'ERROR', 'message' => $result['message'] ?? "Sorry, Carolyn couldn't rewrite that just now — please try again."], 200);
        }

        return response()->json(['status' => 'OK', 'title' => $result['title'], 'body' => $result['body']]);
    }
}
