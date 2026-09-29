<?php

// NEW 15 Sep 2026 — per Chris: practitioner types (Sensei, Consultant,
// Legal Advisor, etc.) must never be hardcoded — this is the
// Admin-editable catalog for the appointment booking module.
return [
    'page_title' => 'Practitioner Types',
    'catalog_helper' => 'The kinds of practitioner a community can book appointments with — a temple\'s Sensei, an SME\'s in-house Advisor, a Legal Advisor, and so on. Any existing Agent/Member can be set up as a practitioner of one of these types on the Practitioner / Appointment Setup screen. New types can be added at any time — nothing here is hardcoded.',
    'col_type_label' => 'Practitioner Type',
    'col_type' => 'Type',
    'col_system' => 'Built-in',
    'custom' => 'Custom',
    'btn_save' => 'Save',
    'saved' => '✓ Saved.',

    'add_title' => 'Add New Practitioner Type',
    'edit_title' => 'Edit Practitioner Type',
    'add_new_type' => 'Add New Practitioner Type',
    'type_label_placeholder' => 'Practitioner type (e.g. "Financial Advisor")',
    'system_locked_note' => 'Built-in types can be edited but not deactivated — existing practitioners already use them.',

    'search_by_type_name' => 'Search by Practitioner Type',
    'search_all_criteria_label' => 'Search by Practitioner Type or Code',
    'search_placeholder' => 'Start typing a practitioner type or code...',
    'no_types_found' => 'No matching practitioner types found.',
    'no_types_match' => 'No practitioner types match your search.',
    'back_to_types' => '← Back to Practitioner Types',
    'search_type_label' => 'Search By',
    'search_all_option' => 'Search All',
    'field_type_label' => 'Practitioner Type',
    'field_code' => 'Code',
];
