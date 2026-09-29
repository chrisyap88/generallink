<?php

// NEW 15 Sep 2026 — per Chris: committee/management position titles
// must never be hardcoded — this is the Admin-editable catalog.
return [
    'page_title' => 'Committee / Management Positions',
    'catalog_helper' => 'Every committee or management position a CBE community can assign a member to — a temple or NGO\'s President, Deputy President, Secretary, Treasurer, or an SME/business\'s CEO, Finance Director, and so on. A community picks whichever positions apply to it on its Group Name screen. New positions can be added at any time — nothing here is hardcoded.',
    'col_position_label' => 'Position Name',
    'col_type' => 'Type',
    'col_system' => 'Built-in',
    'custom' => 'Custom',
    'btn_save' => 'Save',
    'saved' => '✓ Saved.',

    'add_title' => 'Add New Position',
    'edit_title' => 'Edit Position',
    'add_new_position' => 'Add New Position',
    'position_label_placeholder' => 'Position name (e.g. "Vice President")',
    'system_locked_note' => 'Built-in positions can be edited but not deactivated — existing CBE groups already use them.',

    'search_by_position_name' => 'Search by Position Name',
    'search_all_criteria_label' => 'Search by Position Name or Code',
    'search_placeholder' => 'Start typing a position name or code...',

    // NEW 24 Sep 2026 -- per-CBE-group scoping (same pattern as Role Ranks).
    'group_picker_label' => 'CBE Group',
    'system_default_group' => 'System Default (shared by every CBE group)',
    'unknown_group' => 'Unknown Group',
    'group_helper' => 'Positions shown/added here belong to the CBE group selected above.',
    'no_positions_found' => 'No matching positions found.',
    'no_positions_match' => 'No positions match your search.',
    'back_to_position_types' => '← Back to Committee / Management Positions',
    'search_type_label' => 'Search By',
    'search_all_option' => 'Search All',
    'field_position_label' => 'Position Name',
    'field_code' => 'Code',
    'show_inactive' => 'Show inactive (:count)',
    'hide_inactive' => 'Hide inactive',
];
