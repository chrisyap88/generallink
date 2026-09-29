<?php

// NEW 27 Aug 2026 — dedicated login page for the /glade CBE-only
// portal (per Chris's "totally new url login" request). Separate
// small lang file so it never collides with the existing CBE exec
// dashboard keys in cbe_exec.php.
return [
    'page_title' => 'GLADE Sign In',
    'welcome_heading' => 'Welcome to GLADE',
    'sign_in_subtitle' => 'Sign in to your GLADE dashboard',
    'field_email_label' => 'Email Address',
    'field_password_label' => 'Password',
    'remember_me_label' => 'Remember me',
    'sign_in_button' => 'Sign In',
    // FIXED 28 Aug 2026 — per Chris: "why you write General link
    // Association & Donor Engagement? change back to General Link
    // Affiliate Digital Eco System" — GLADE is an acronym for this
    // phrase (General Link Affiliate Digital Eco (System) = G-L-A-D-E),
    // kept as the brand name in all 3 languages rather than translated,
    // same treatment as the word "GLADE" itself.
    'sidebar_brand_name' => 'Community Business Enterprise Group',
    'footer_tagline' => 'General Link Affiliate Digital Eco System',
    'pill_kpi' => 'Executive KPI',
    'pill_secure' => 'Secure & Scoped',
    'pill_realtime' => 'Real-Time Data',
];
