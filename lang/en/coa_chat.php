<?php

// NEW 19 Sep 2026 -- "COA Chat", Phase 1 of the AI Master Data
// Assistant (per Chris's uploaded spec
// AI_Master_Data_and_Transaction_Assistant_Specification.docx). See
// CoaChatAssistantService and CbeAccountingController::coaChatForm()/
// coaChatClassify().

return [
    'describe_label' => 'Describe what you want to add',
    'describe_placeholder' => 'e.g. hotel bills, printing expenses, staff uniforms...',
    'ask_button' => 'Ask',
    'idle_hint' => 'Type what you need above, in plain language — the AI will find or set up the right account for you.',
    'thinking' => 'Checking your Chart of Accounts...',
    'match_title' => 'This already exists — no need to create a new one:',
    'new_title' => 'Here is what I will create — please check before confirming:',
    'ask_again_button' => 'Ask Again',
    'view_account_button' => 'View / Edit This Account',
    'confirm_save_button' => 'Looks Good, Save',
    'none_placeholder' => '—',
    'generic_error' => 'Something went wrong — please try again.',
    'saving_label' => 'Saving...',
    'hint_prefill' => 'I want to add a new GL code for ',
];
