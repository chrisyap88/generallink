<?php

// -------------------------------------------------------
// AI Guided Navigation — module manifest for the Affiliate module.
// This is plain data, not code — see docs/ai-guided-navigation-design.md.
//
// PILOT WORKFLOW (2 Aug 2026): affiliate_registration is the one and only
// authored workflow shipped in this first phase, per the design doc's
// phased-rollout plan (§6) — prove the framework out on one real workflow
// before authoring manifests for every other module.
//
// IMPORTANT: the AI NEVER clicks anything here (Chris's decision, 2 Aug
// 2026 — see §3.2/§7 of the design doc). Every step is highlight_element
// or focus_input with a message — the human performs every click/typing
// themselves. 'destructive' below is descriptive metadata only; it does
// not gate any executor behavior (nothing is ever auto-clicked regardless).
// -------------------------------------------------------

return [
    'module' => 'affiliate',

    'pages' => [
        'auth.register' => [
            'label' => 'Affiliate Registration',
            'roles' => ['guest'],
            'elements' => [
                'upline-type-dropdown'    => ['type' => 'dropdown', 'destructive' => false],
                'affiliate-search-input'  => ['type' => 'input',    'destructive' => false],
                'upline-agent-id-hidden'  => ['type' => 'hidden',   'destructive' => false],
                'admin-assign-notice'     => ['type' => 'notice',   'destructive' => false],
                'full-name-input'         => ['type' => 'input',    'destructive' => false],
                'phone-input'             => ['type' => 'input',    'destructive' => false],
                'email-input'             => ['type' => 'input',    'destructive' => false],
                'address-input'           => ['type' => 'input',    'destructive' => false],
                'postcode-input'          => ['type' => 'input',    'destructive' => false],
                'city-input'              => ['type' => 'input',    'destructive' => false],
                'state-dropdown'          => ['type' => 'dropdown', 'destructive' => false],
                'register-submit-btn'     => ['type' => 'button',   'destructive' => true],
            ],
        ],
        'auth.verify-pending' => [
            'label' => 'Verification Pending',
            'roles' => ['guest'],
            'elements' => [],
        ],
    ],

    'workflows' => [
        'affiliate_registration' => [
            'label' => 'Register as an affiliate',
            'roles' => ['guest'],
            // Every step: highlight/focus + explain, never click. The
            // 'wait' key names the built-in condition the executor already
            // knows how to check (see ai-guidance-overlay.blade.php) — not
            // arbitrary code, a small fixed enum.
            'steps' => [
                [
                    'page' => 'auth.register',
                    'element' => null,
                    'action' => 'open_page',
                    'message' => "Let's get you registered as an affiliate! I'll walk you through this form step by step — just follow along and click or type where I point.",
                    'wait' => null,
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'upline-type-dropdown',
                    'action' => 'highlight_element',
                    'message' => "First, choose who you're joining under — Group Leader, Team Leader, Introducer, or let our Admin team assign one for you.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'affiliate-search-input',
                    'action' => 'highlight_element',
                    'message' => "Now search your sponsor by name or code and pick them from the list. If you chose Admin Assign instead, you can skip this — just carry on to the next step.",
                    'wait' => 'sponsor_selected',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'full-name-input',
                    'action' => 'focus_input',
                    'message' => "Type your full legal name here, exactly as it appears on your NRIC or MyKad.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'phone-input',
                    'action' => 'focus_input',
                    'message' => "Enter your mobile number, e.g. 012-3456789.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'email-input',
                    'action' => 'focus_input',
                    'message' => "Enter your email address — your verification link will be sent here, so make sure it's correct.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'address-input',
                    'action' => 'focus_input',
                    'message' => "Enter your street address — unit or house number, street name, and area.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'postcode-input',
                    'action' => 'focus_input',
                    'message' => "Enter your postcode — city and state can auto-fill from this, so type it first.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'city-input',
                    'action' => 'highlight_element',
                    'message' => "Check that your city filled in correctly, or type it in yourself.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'state-dropdown',
                    'action' => 'highlight_element',
                    'message' => "Check that your state is correct.",
                    'wait' => 'field_filled',
                ],
                [
                    'page' => 'auth.register',
                    'element' => 'register-submit-btn',
                    'action' => 'highlight_element',
                    'message' => "You're all set! Have a quick look over your details, then click Submit Registration yourself when you're ready — I won't click it for you.",
                    'wait' => 'page_arrived:auth.verify-pending',
                ],
                [
                    'page' => 'auth.verify-pending',
                    'element' => null,
                    'action' => 'end_workflow',
                    'message' => "You're registered! Please check your email for the verification link to finish setting up your account.",
                    'wait' => null,
                ],
            ],
        ],
    ],
];
