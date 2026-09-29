<?php

use Illuminate\Database\Migrations\Migration;

// DEFERRED 31 Jul 2026 — role_label_overrides has the exact same
// group_id-vs-group_label_id scoping flaw as role_ranks (see the
// migration right before this one), but rescoping it touches many more
// call sites across the app (RoleLabelService::label()/plural()/
// shortLabel() is called from the dashboard sidebar, several
// controllers, and export templates — all currently passing a
// groups.group_id). Fixing rank/commission scoping first since that's
// what actually affects money paid out; role LABEL text (e.g. "Regional
// Director" vs "Group Leader") divergence between two GLs under the
// same Special Privilege Group is a real but lower-stakes version of
// the same bug, tracked as a separate follow-up. This migration is
// intentionally a no-op so it's safe to run alongside 000008 without
// breaking any existing RoleLabelService call site.
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — see note above.
    }

    public function down(): void
    {
        // Intentionally empty — see note above.
    }
};
