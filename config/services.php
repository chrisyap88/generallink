<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // NEW 18 Jul 2026 — Claude API key, used by ClaudeDocumentExtractionService
    // to read uploaded policy/receipt/invoice documents directly (replaces
    // the old tesseract/poppler local OCR pipeline).
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    // NEW 18 Jul 2026 — shared secret the inbound-email webhook URL must
    // include as ?token=... — see EmailIngestionController::inbound().
    // Bare-minimum protection so the public webhook URL can't be spammed.
    'email_ingestion' => [
        'webhook_secret' => env('EMAIL_INGESTION_WEBHOOK_SECRET'),
    ],

    // NEW 25 Jul 2026 — Survey Management, real Google Forms integration
    // (task #227 follow-up, per Chris's explicit choice to use Google
    // Forms rather than keep building the in-app question builder).
    // GoogleAuthController uses these to run the OAuth "Connect Google
    // Account" flow; GoogleFormsService uses the resulting token to
    // create/read real Forms via the Forms/Sheets/Drive APIs.
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_REDIRECT_URI'),
    ],

    // NEW 28 Jul 2026 — EspoCRM integration. GeneralLink stopped extending
    // its own follow-up scheduling/calendar/escalation and adopted EspoCRM
    // for that (task #251, per Chris's decision after the CRM evaluation
    // report). EspoCrmService authenticates every request with this single
    // shared API key (the "GeneralLink Integration" API User created in
    // EspoCRM Administration -> API Users) — agents never log into EspoCRM
    // directly.
    'espocrm' => [
        'base_url' => env('ESPOCRM_BASE_URL'),
        'api_key'  => env('ESPOCRM_API_KEY'),
    ],

    // NEW 3 Aug 2026 — ElevenLabs text-to-speech, used so Carolyn (the AI
    // Assistant) can speak with a real Malaysian-accent female voice
    // instead of the generic browser voice. See ElevenLabsService.
    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
    ],

    // NEW 16 Sep 2026 — qpdf (used by PdfPasswordRemovalService to
    // unlock password-protected bank statement PDFs before the AI
    // Accounting module reads them). Defaults to plain 'qpdf', which
    // only works if it's on the Windows PATH — set QPDF_BINARY in .env
    // to the exact qpdf.exe path instead so this never depends on PATH
    // being configured correctly (per Chris: PATH setup on Windows was
    // unreliable in practice).
    'qpdf' => [
        'binary' => env('QPDF_BINARY', 'qpdf'),
    ],

];
