<?php

// NEW 25 Aug 2026 — Admin-only CBE KPI dashboard (GeneralLink's own 3
// platform Admin accounts, not a CBE organization's own officers).
// Box/row wording is shared with lang/en/cbe_exec.php on purpose — same
// dashboard content, just with an ALL/per-group dropdown instead of a
// single fixed node.
return [
    'title_director' => 'CBE KPI Dashboard — Director',
    'title_finance' => 'CBE KPI Dashboard — Finance',
    'title_membership' => 'CBE KPI Dashboard — Membership',
    'select_group' => 'CBE Group',
    'all_cbe_groups' => 'All CBE Groups (Combined)',
    'go_button' => 'Go',
    'groups_count' => 'CBE Groups',
    'nodes_count' => 'Total Nodes in Scope',
    'group_tier_free' => 'This group is on FREE tier',
    'group_tier_paid' => 'This group is on PAID tier',
    'drill_down' => 'Drill Down',
    'reset_drill' => 'Reset to whole group',
    'back_to_dashboard' => 'Back to Dashboard',
    'view_kpi' => 'View KPI',
    // NEW 26 Aug 2026, 9th pass — per Chris: "this screen should not
    // display View KPI should have View Details." Used only on the
    // Temple/Branch/State browse list (nodes.blade.php).
    'view_details' => 'View Details',
    'of_total' => 'of :total records',
    'prev' => 'Prev',
    'next' => 'Next',
    // NEW 25 Aug 2026, 3rd pass — 4 always-visible filter boxes (per
    // Chris: "i want all search filter show in one row NOT hidden").
    // Generic level-position labels, since not every CBE community has
    // 4 levels (Rotary has only 1, "Club") — matched by level_order,
    // never a hardcoded name.
    // RENAMED 26 Aug 2026, 6th pass — per Chris: "the first thing they
    // need to select is which cbe group... Tao... Rotary... then which
    // state which branch which temple." Box 1 was always this picker
    // (level_order 1 = each community's own HQ/root node) but was
    // labelled "HQ / Community", which didn't read as "pick your CBE
    // Group" to Chris. Renamed to match his own words exactly, and to
    // match the existing "CBE Group" wording already used elsewhere on
    // this same screen (the "CBE Groups: 2" count in the header).
    'filter_level1' => 'CBE Group',
    'filter_level2' => 'State',
    'filter_level3' => 'Branch',
    'filter_level4' => 'Entity',
    'filter_all' => '► All',
    // NEW 26 Aug 2026, 7th pass — per Chris: "dont default to search
    // before the selection criteria completed" — shown instead of the
    // KPI grid until Group/State/Branch/Temple + Go has been used.
    'select_prompt' => 'Select your CBE Group and criteria above, then click Go to view KPI data.',
    // NEW 26 Aug 2026, 8th pass — per Chris: "from temple drill down you
    // should display view details which the profile meaning contact
    // person, address, contact number... 2 tap folder on top, one is
    // profile, one is kpi... every screen i drill down i should have
    // prev... if i dont have any search anymore i should have back to
    // dashboard on the left corner bottom screen fill with blue color."
    'tab_profile' => 'Profile',
    // NEW 26 Aug 2026, 12th pass — per Chris: "put a new folder tap.
    // the folder tap sequence is suppose to have profile, add contact
    // tel no and lastly KPI" — split Contact Person 1/2 + phone numbers
    // out of the Profile tab into their own tab, in that exact order.
    'tab_contact' => 'Contact & Tel No',
    'tab_kpi' => 'KPI',
    // UPDATED 26 Aug 2026, 10th pass — per Chris: "i have so many
    // contact number in my excel file, you didnt insert? contact 1,
    // contact 2 with name?" The 591-temple import already captured 2
    // named contacts + every phone number per temple — the Profile tab
    // now reads/writes those same columns instead of the unused
    // singular contact_person/contact_phone fields.
    'profile_contact_person' => 'Contact Person',
    'profile_contact_phone' => 'Contact Number',
    'profile_contact_person_1' => 'Contact Person 1',
    'profile_contact_person_2' => 'Contact Person 2',
    'profile_phone_numbers' => 'Phone Numbers',
    // NEW 26 Aug 2026, 14th pass — per Chris: "i dont know about this
    // temple whether it has 2nd contact number... show contact 1,
    // contact 2 name and contact number if any" — each contact's own
    // number now sits directly beside their name.
    'profile_phone_number' => 'Phone Number',
    'profile_additional_phones' => 'Additional Phone Numbers',
    'profile_phone_placeholder' => 'Phone Number',
    'profile_note_placeholder' => 'Name / Note (optional)',
    'profile_add_phone' => '+ Add Phone',
    'profile_address' => 'Address',
    // NEW 26 Aug 2026, 11th pass — per Chris: "have you change the
    // profile to show all the information from y excel file" — State
    // (the temple's parent node) and the Excel's Serial Number/
    // Postcode weren't surfaced yet.
    'profile_city' => 'City',
    'profile_postcode' => 'Postcode',
    // CHANGED 12 Sep 2026 — per Chris: never hardcode "State" as the
    // parent label; a parent can be any level (Branch, City, HQ, Club).
    // The view now shows the parent's own real level name and only falls
    // back to this generic wording if that lookup somehow comes back
    // empty.
    'profile_state' => 'Parent',
    // NEW 12 Sep 2026 — per Chris: "you have found a temple A created and
    // there is a possibility to link to parent id you have to a flag
    // control linked to parent (show the parent name) because sometime
    // the Temple A management decision does not want to link and stay by
    // itself stand alone."
    'profile_standalone_by_choice' => 'Standalone by choice — not linked to any parent',
    'profile_standalone_unlinked' => 'Not linked to a parent yet (no matching entity above it exists yet — links automatically once one is created)',
    'profile_stay_standalone' => 'Stay standalone — do not link to a parent automatically',
    'profile_ref_no' => 'Ref No',
    'profile_save' => 'Save',
    'profile_saved' => 'Profile saved.',
    'back_to_dashboard_btn' => '← Back to Dashboard',
];
