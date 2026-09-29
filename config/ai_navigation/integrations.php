<?php

use App\Services\Integrations\IntegrationConnectorResolver;

// -------------------------------------------------------
// AI Guided Navigation — module manifest for the Integration Hub.
// NEW 4 Aug 2026, corrected same day — first pass only wired up ONE
// category (AI Services -> OpenAI) as a proof of concept. Chris
// correctly called that out: "not just ElevenLabs" (meaning: not just
// the one path I'd demoed) — he wants Carolyn able to visually guide a
// user to ANY of the 16 categories, since that's what she now claims
// to know about in conversation. This file now generates one workflow
// PER CATEGORY straight from IntegrationConnectorResolver::categories()
// — the same source of truth the Hub screens themselves use — so a
// 17th category added there automatically gets a guided workflow here
// too, no manual authoring needed.
//
// Scope, stated plainly: each workflow highlights the category tile on
// the Hub's main screen, then hands off to a spoken instruction naming
// the actual providers in that category and telling the user to paste
// their key and click Save/Test Connection themselves. It does NOT
// highlight the individual provider's input box for every one of the
// ~85 providers — that would need the guided-nav system to accept a
// provider parameter per session, which today's fixed-step-per-goal
// design doesn't support. That's real future work, not done here.
//
// Same hard rule as every other manifest: highlight + explain only,
// never auto-click. See config/ai_navigation/affiliate.php.
// -------------------------------------------------------

// Explicit role list, NOT '*' — the Integration Hub route requires
// auth:agent, so a guest (login-page) session must never be offered
// these workflows; handing a guest off to a route that just bounces
// them back to login would be a broken, confusing experience.
$loggedInRoles = ['ADMIN', 'GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];

$categories = IntegrationConnectorResolver::categories();

$pages = [
    'integrations.index' => [
        'label' => 'Integration Hub',
        'roles' => $loggedInRoles,
        'elements' => [],
    ],
    'integrations.category' => [
        'label' => 'Integration Hub — Category',
        'roles' => $loggedInRoles,
        'elements' => [],
    ],
];

$workflows = [];

foreach ($categories as $catKey => $catMeta) {
    $tileElement = "category-tile-{$catKey}";
    $pages['integrations.index']['elements'][$tileElement] = ['type' => 'button', 'destructive' => false];

    $providerNames = collect($catMeta['providers'])->pluck('label')->implode(', ');
    $goal = "connect_" . $catKey;

    $workflows[$goal] = [
        'label' => "Connect a {$catMeta['label']} provider",
        'roles' => $loggedInRoles,
        'steps' => [
            [
                'page' => 'integrations.index',
                'element' => null,
                'action' => 'open_page',
                'message' => "Let's get your {$catMeta['label']} connected! I'll take you to your Integration Hub.",
                'wait' => null,
            ],
            [
                'page' => 'integrations.index',
                'element' => $tileElement,
                'action' => 'highlight_element',
                'message' => "Click this {$catMeta['label']} tile — that's where {$providerNames} live.",
                'wait' => 'page_arrived:integrations.category',
            ],
            [
                'page' => 'integrations.category',
                'element' => null,
                'action' => 'end_workflow',
                'message' => "Find the provider you want in the list, paste your own API key (or the fields it asks for) next to it, click Save, then Test Connection if that provider supports it yet. I'm here if you get stuck!",
                'wait' => null,
            ],
        ],
    ];
}

return [
    'module' => 'integrations',
    'pages' => $pages,
    'workflows' => $workflows,
];
