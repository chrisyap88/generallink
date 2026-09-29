<?php

// NEW 13 Sep 2026 (Task #418) — hub screens for Master File Maintenance
// after it was split into sub-screens (25+ links no longer fit one
// no-scroll screen). Each hub lists its own group's links with its own
// Prev/Next; the actual item labels reuse each item's existing lang
// keys (cbe_accounting.tile_*, cbe_vendors.*, cbe_marketplace.*), so
// only the hub page title/intro/back-link text lives here.
return [
    'financial_hub_title' => 'Financial Master File',
    'financial_hub_intro' => 'All accounting and finance reference data set up for this system.',
    'vendor_hub_title' => 'Vendor Master File',
    'vendor_hub_intro' => 'Vendor registration, entity approvals, and CBE Marketplace set up.',
    'go_link' => 'Open',
];
