<?php

// NEW 27 Aug 2026 — per Chris: "Master file maintenance ... this is
// where you set up consultant donor, members, temple, branch, state,
// HQ (all related CBE set up)." These 7 screens are not built yet —
// keys reserved here so the GLADE sidebar can show them as "soon"
// placeholders now, and become real page titles once each screen is
// built, without a second round of translation.
return [
    'consultant_maintenance' => 'Advisor Maintenance',
    'donor_maintenance' => 'Donor Maintenance',
    'member_maintenance' => 'Member Maintenance',
    // NEW 15 Sep 2026 — per Chris: appointment booking module. Who is a
    // practitioner (Sensei/Consultant/Legal Advisor) at this specific
    // entity — always an existing Agent/Member, never a separate
    // registration — plus their booking settings, weekly hours, and
    // leave dates.
    'practitioner_setup' => 'Practitioner / Appointment Setup',
    'err_practitioner_already_exists' => 'This person is already set up as this practitioner type at this entity.',
    'err_practitioner_has_upcoming_bookings' => 'Cannot remove this practitioner — one or more members still have an upcoming booked appointment with them. Cancel or complete those first.',
    // FIXED 28 Aug 2026 — per Chris: never hardcode "Temple" — a CBE
    // group can be any kind of organization (e.g. a Rotary Club), not
    // just a temple federation. One generic "Entity" term replaces the
    // old 4 separate Temple/Branch/State/HQ keys, since a community's
    // actual level names are entirely admin-defined (cbe_hierarchy_levels).
    'entity_maintenance' => 'Entity Maintenance',

    // NEW 27 Aug 2026 — Hierarchy Node (Entity) Maintenance create screen.
    'node_title' => 'Entity Maintenance',
    'select_group_prompt' => 'Select a CBE Group to set up its entity structure (levels are defined per group).',
    'btn_change_group' => 'Change Group',
    'form_level' => 'Level',
    'form_parent' => 'Reports To (Parent)',
    // CHANGED 12 Sep 2026 — per Chris: "why you dont design your own
    // group link id rather the user select, if select wrong, will be big
    // problem." The Parent field is no longer a dropdown to choose from —
    // it is worked out automatically from the Postcode below, matched
    // against the coverage ranges already set up on the level(s) above.
    // This note explains that in place of a picker.
    'form_parent_auto_note' => 'Worked out automatically from the Postcode below — nothing to pick. If no matching entity exists yet, this stays unlinked and connects itself automatically the moment a matching one is created.',
    // Shown when no parent could be worked out yet (this note used to sit
    // next to a "None" option in the old dropdown; now purely informational).
    'form_parent_none' => 'None — not linked to a parent yet (fine even if this isn\'t the top level; it links automatically once a matching entity above it is created).',
    'form_node_name' => 'Name (English)',
    'form_node_name_zh' => 'Name (Chinese)',
    'form_city' => 'City',
    'form_district' => 'District',
    'district_type_hint' => 'Type district',
    'form_postcode' => 'Postcode',
    'form_postcode_linking_hint' => 'Used to work out which entity above this one it reports to — see Reports To (Parent).',
    // NEW 12 Sep 2026 — per Chris: "sometime the Temple A management
    // decision does not want to link and stay by itself stand alone."
    'form_stay_standalone' => 'Stay standalone — do not link to a parent automatically',
    'form_address' => 'Address',
    'form_contact_person_1' => 'Contact Person 1',
    'form_contact_person_2' => 'Contact Person 2',
    'form_reference_no' => 'Reference No.',
    'form_phones' => 'Phone Numbers',
    'btn_add_phone' => '+ Add Phone',
    'phone_placeholder' => 'Phone number',
    'note_placeholder' => 'Note (optional)',
    'btn_save_node' => 'Save',
    'node_saved' => 'Saved.',
    'existing_nodes_title' => 'Existing Structure',
    'no_levels_yet' => 'This group has no levels defined yet. Set up levels first on the Group Name screen.',
    'err_level_required' => 'Please choose a valid level.',
    'err_name_required' => 'Name is required.',
    // NEW 11 Sep 2026 (Task #413 follow-up) — shown to a CBE node officer
    // only, if a level/parent combination outside their own node's
    // subtree somehow reaches the server (tampered link, stale form).
    'err_parent_scope' => 'You can only add entities under your own node. Please choose a valid parent.',
    // NEW 12 Sep 2026 — the rare case where two existing entities at the
    // same level both claim the same postcode in their coverage range (a
    // data entry mistake) — refuses to guess between them rather than
    // silently pick one.
    'err_parent_ambiguous' => 'More than one existing entity\'s postcode coverage overlaps this postcode — please fix the overlapping coverage ranges on the Insert/Restructure screen first, then save this again.',
    // NEW 11 Sep 2026 — per Chris: catches an accidental duplicate or a
    // mistyped/missing comma in the Hierarchy Level Names field before
    // it's saved.
    'err_level_name_duplicate' => 'Level name ":name" is listed more than once — each level name must be unique.',
    'err_level_name_too_long' => 'A level name is too long (":name..."). Check that levels are separated by commas.',

    // NEW 10 Sep 2026 (Task #409) — per Chris: "ask me to configure cbe
    // group for the state or branch or temple, why you show everything."
    // New Step 2 of the 3-step flow: pick which level to set up, before
    // ever seeing the create form. Level is then locked/read-only on the
    // form itself (Step 3) — no more open dropdown re-asking something
    // already chosen.
    'select_level_prompt' => 'Which level are you setting up?',
    'btn_change_level' => 'Change Level',
    'level_entity_count' => ':count set up so far',
    'level_entity_count_zero' => 'none set up yet',

    // NEW 10 Sep 2026 (Task #410) — per Chris: "where is rotary club,
    // where is other CBE? why straight to Tao?" and "how many records i
    // can create for HQ?"
    'only_one_group_note' => 'Only 1 CBE group exists right now. To set up another (e.g. a different community), add it first in Group Name Maintenance.',
    'link_group_name_maintenance' => 'Group Name Maintenance',
    'warn_hq_exists' => 'This group already has a top-level (:level) record: :names. Most CBE communities only need one — continue only if a second one is genuinely intended.',

    // NEW 11 Sep 2026 — per Chris: "branch can configure which city it
    // belong to range of post code" — optional coverage range so a
    // Branch (or any level) can declare which postcodes it covers,
    // shown next to its name when picking a Parent for a new child.
    'form_coverage_postcode' => 'Postcode Coverage (optional)',
    'form_coverage_postcode_hint' => 'If this entity covers a range of postcodes (e.g. a Branch), enter it here — helps when choosing which parent a new entity belongs under.',
    'form_coverage_postcode_from' => 'From',
    'form_coverage_postcode_to' => 'To',

    // NEW 11 Sep 2026 (Task #411) — Link to Another CBE Group screen. Per
    // Chris: standalone entities (e.g. a Rotary Club chapter that starts
    // on its own, or a Temple not yet part of any federation) need to be
    // movable later into a different CBE group's real structure.
    'link_to_group_title' => 'Link to Another CBE Group',
    'link_link_to_group' => 'Link to Another Group',
    'link_to_group_intro' => 'Move a standalone entity out of its current group and into a chosen spot inside a different CBE group — e.g. a Rotary Club chapter that started on its own now joining under a Branch, or a Temple joining Tao\'s structure.',
    'select_source_node_prompt' => 'Which entity do you want to move?',
    'source_node_search_placeholder' => 'Type an entity name to search',
    'select_target_group_prompt' => 'Move it into which CBE group?',
    'select_target_level_prompt' => 'At which level in that group?',
    'form_target_parent' => 'Reports To (Parent) in the new group',
    // NEW 12 Sep 2026 — replaces the old Target Parent dropdown; explains
    // that the new group's parent is worked out automatically the same
    // way as on the Entity Maintenance create screen.
    'form_target_parent_auto_note' => 'This entity\'s postcode (:postcode) will be matched automatically against whichever City/Branch/State/HQ already exists in the new group — whichever is the closest match, skipping any level that hasn\'t been set up yet. Nothing to pick; if nothing matches yet, it connects itself later once a matching entity is created there.',
    'btn_save_link' => 'Move Entity',
    'link_saved' => 'Moved.',
    'err_link_same_group' => 'Pick a different CBE group — this entity is already in that one.',
    'err_link_has_children' => 'This entity has :count entities under it — moving it isn\'t supported yet, since every one of them would also need a matching level in the new group. Move a standalone entity (no children) instead.',
    'no_other_groups_note' => 'No other CBE group exists yet to move this into. Add one first in Group Name Maintenance.',

    // NEW 10 Sep 2026 (Task #399) — Restructure screen: insert a node at
    // an existing or brand-new level, above/below/between any existing
    // nodes, and optionally move existing nodes to become its children.
    // Per Chris: different CBE communities reach HQ/State/Branch/Temple
    // in a different real-world order — this must never require a fixed
    // build sequence.
    'restructure_title' => 'Insert / Restructure Node',
    'restructure_intro' => 'Add a new node anywhere in this group\'s structure — at an existing level, or a brand-new one — and optionally move existing entities to sit underneath it. Use this when a Branch, State, or HQ is formalised after entities beneath it already exist.',
    'link_restructure' => 'Insert / Restructure',
    'link_back_to_create' => '< Back to Add Entity',
    'form_level_mode' => 'Level for the New Node',
    'form_level_mode_existing' => 'Use an existing level',
    'form_level_mode_new' => 'Create a new level',
    'form_new_level_name' => 'New Level Name',
    'form_new_level_name_placeholder' => 'e.g. Branch, Zone, Sub-section',
    'form_new_level_position' => 'Position',
    'new_level_position_above' => 'Directly above',
    'new_level_position_below' => 'Directly below',
    'form_new_level_anchor' => 'Relative to Level',
    'form_children_title' => 'Move Existing Entities Under This Node',
    'children_search_placeholder' => 'Type to search entities...',
    'children_no_matches' => 'No entities match your search.',
    'btn_save_restructure' => 'Save & Restructure',
    'err_new_level_name_required' => 'Enter a name for the new level.',
    'err_restructure_failed' => 'Could not save — nothing was changed. (:error)',
    'err_levels_in_use' => 'The group name and other details were saved. The level(s) ":levels" were kept as-is because entities already exist at that level — remove or move them first if you really want to delete that level, or use Insert/Restructure instead.',
    'err_cannot_change_group_type' => 'Cannot change this away from a CBE group — :count entities already exist in its structure. Move or remove them first.',

    // NEW 28 Aug 2026 — per Chris: "develop all the soon programs" —
    // shared group→node picker used by Donor/Consultant/Member
    // Maintenance when an Admin (no single node) opens them, same
    // two-step pattern as the Entity Maintenance screen above.
    'picker_select_group' => 'Select a CBE Group to continue.',
    'picker_select_node' => 'Select an entity below to continue.',
    'picker_change_group' => 'Change Group',
    'picker_no_groups' => 'No CBE groups found.',
    'picker_no_nodes' => 'This group has no entities set up yet.',
    'switch_entity' => '⇄ Switch Entity',

    // NEW 28 Aug 2026 — per Chris: "no scroll at meeting minutes... just
    // display the persatuan name and number of minutes at right end."
    // Step 2 of the picker is now type-to-search instead of a long
    // scrollable list, mirroring the Donors/Members/Customers pattern.
    'picker_search_node' => 'Type an entity name to search',
    'picker_type_to_search' => 'Start typing above to find an entity.',
    'picker_no_matches' => 'No entities match your search.',

    // NEW 10 Sep 2026 — per Chris: type-ahead search is compulsory on
    // every search field across the app. Step 1 of this picker (choosing
    // a CBE Group) was missed when Step 2 got its search box on 28 Aug
    // 2026 — added here for consistency.
    'picker_search_group' => 'Type a group name to search',
    'picker_no_group_matches' => 'No groups match your search.',
    // NEW 26 Sep 2026 — postcode coverage tick-box selection screen.
    'cov_title' => 'Select Postcodes Covered',
    'cov_hint' => 'All postcodes in the range are ticked. Untick any postcode this entity does not cover.',
    'cov_col_entity' => 'Entity Name',
    'cov_col_postcode' => 'Postcode',
    'cov_col_city' => 'City',
    'cov_col_select' => 'Select',
    'cov_page' => 'Page :page of :pages',
    'cov_selected' => ':count of :total ticked',
    'cov_loading' => 'Loading postcodes...',
    'cov_none' => 'No postcodes found in this range.',
    'cov_tick_all' => 'Tick all',
    'cov_untick_all' => 'Untick all',
    'cov_prev' => 'Prev',
    'cov_next' => 'Next',
    'cov_range_error' => 'Please key in both From and To postcodes (5 digits).',
    'cov_none_ticked' => 'Please tick at least one postcode.',
    // NEW 26 Sep 2026 — Entity Maintenance Add / Search-View-Edit redesign.
    'mode_add' => 'Add New Entity',
    'mode_edit' => 'View / Edit',
    'landing_prompt' => 'What would you like to do?',
    'btn_add_new_entity' => 'Add New Entity',
    'search_entity_placeholder' => 'Type to search by entity name, level, city or postcode',
    'col_coverage' => 'Postcode Coverage',
    'cov_form_hint' => 'Key in the From and To postcodes this entity covers, then press Next to tick or untick each postcode in the range. Leave blank if it does not cover a range.',
    // NEW 26 Sep 2026 — Link Entities (search + tick) screen.
    'link_entities_title' => 'Link Entities',
    'link_entities_hint' => 'Search, then tick the entities that belong under this one. [All] ticks every row; untick any that should not be linked.',
    'link_pc_from' => 'Postcode From',
    'link_pc_to' => 'Postcode To',
    'link_all' => 'All',
    'link_search_first' => 'Key in any search above and press Search to list the entities you can link.',
    'link_col_link' => 'Link',
    'link_col_current' => 'Currently Linked To',
    'link_not_linked' => 'Not linked',
    'link_entities_saved' => 'Saved — :linked linked, :unlinked removed.',
    'link_bottom_level' => 'This is the lowest level — there is nothing to link under it.',
    'action_link_entities' => 'Link Entities',
    // NEW 26 Sep 2026 — structured search boxes + Entity Hierarchy Link program.
    'search_entity_code' => 'Entity Code',
    'search_go' => 'GO',
    'search_first_go' => 'Key in any search box above and press GO.',
    'hier_link_title' => 'CBE Entity Affiliation',
    'hier_link_intro' => 'Set up which entities belong under each level of this CBE (HQ / State / Branch / City): choose the parent, search, untick any entity that should not be affiliated, then Save.',
    'hier_link_group' => 'CBE Group',
    'hier_link_parent' => 'Link To (Parent)',
    'hier_link_parent_placeholder' => 'Type a City / Branch / State / HQ name and pick from the list',
    'hier_link_pick_group' => 'Choose a CBE Group above.',
    'hier_link_pick_parent' => 'Choose the parent (Link To) above.',
    // NEW 27 Sep 2026 — City tick-box panel.
    'city_type_hint' => 'Type a town or area, e.g. Meru',
    'city_panel_title' => 'Towns / areas matching',
    'city_panel_ok' => 'OK',
    'city_panel_pcs' => ':count postcode(s) selected',
    // NEW 27 Sep 2026 — Affiliated To (Parent) + HQ-family affiliation.
    'form_affiliated_parent' => 'Affiliated To (Parent)',
    'form_affiliated_parent_hint' => 'Type the upline entity (any CBE) and pick from the list; leave blank if stand-alone / HQ',
    'form_affiliated_none' => 'No parent — stand-alone / HQ, pending affiliation',
    'err_parent_invalid' => 'The chosen parent is not allowed (it is this entity itself, one of its own downlines, or outside your area).',
    'btn_add_entity' => 'Add Entity',
    'group_entities_saved' => 'Links removed: :count',
    'group_entities_own_hint' => 'Entity of this CBE — to move it, open View / Edit',
    'ag_new_found' => ':count found and linked automatically (New)',
    'ag_none' => 'No affiliated entities yet.',
    'ag_new' => 'New',
    'ag_saved' => 'Saved',
    'ag_save_failed' => 'Not saved — please try again',
    'hier_link_standalone' => 'This CBE stands alone — it has no entities to affiliate.',
    'hier_link_already' => 'Already affiliated to :name',
    'aff_count' => ':count affiliated entities',
    'col_contact_person' => 'Contact Person',
    'col_contact_number' => 'Contact Number',
    'btn_view_edit' => 'View / Edit',
    'pick_group_first' => 'Choose the CBE Group first',
    'change_group' => 'Change CBE Group',
];
