<?php

use Illuminate\Database\Migrations\Migration;

// SUPERSEDED 21 Jul 2026 — this file originally added Cc/flag columns
// to a plain "enquiries" table. Per Chris, that feature was redesigned
// before it ever ran (hierarchy-wide Help Desk messaging instead of
// agent-to-Admin-only) — every column this would have added is now
// part of the initial create in 2026_07_21_000006_create_help_desk_threads_table.php.
// Left as a harmless no-op (rather than deleted — the sandbox this was
// edited in can't delete files) so there's never any doubt about
// whether this migration "ran" or not.
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — see comment above.
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
