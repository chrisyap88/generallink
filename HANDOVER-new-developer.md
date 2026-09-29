# GeneralLink — Handover Notes for Next Developer/Session

Project: GeneralLink, a Laravel accounting + membership system built for
Chris's Malaysian NGO/temple federation client (Persekutuan Pertubuhan
Agama Tao Malaysia / Federation of Tao Malaysia), and designed as a
multi-tenant platform so other CBE (Community & Business Enterprise)
groups — e.g. a Rotary Club — can run on it too.
Location: C:\xampp\htdocs\generallink

## Read this first, every single time

Chris keeps a master spec Word doc in the project root:
`master-spec-vNNN-DDMMYY.docx` (highest N = latest). ALWAYS read the
latest one before starting any new work — it is the source of truth for
what's built, what's pending, and what Chris has already decided. Dated
snapshots of old versions live in the `version log` subfolder.

Check `PENDING-master-spec-changelog-110926.txt` in the project root —
if it still exists, it means the master spec docx has NOT yet been
updated with everything through 11 Sep 2026. Merge it into a new
version before trusting the spec as fully current.

## Chris's standing working style — non-negotiable

- He is a NON-CODER. Never ask him to open, find, or edit any file
  himself. Every code change is yours to write and apply directly.
- Any deployment/setup step (migrations, server commands, etc.) must be
  given as an exact, ready-to-run cmd command, ONE STEP AT A TIME. He
  runs the terminal, you never assume it ran successfully — ask/wait for
  confirmation.
- UI rule, applies to every screen: must fit with no scrolling in any
  direction, Prev/Next navigation only (no "jump" screens), no truncated
  labels or data, one standard font, one standard colour scheme across
  the whole app.
- When Chris says "continue later" / "take a break": update the master
  spec with what was done, bump the version number, before ending.
- When Chris says "continue" (resuming after a break): FIRST save a new
  dated snapshot copy of the current master spec into the "version log"
  folder, THEN proceed.
- Keep responses concise and direct. Minimal formatting/no fluff.
- Chris verifies your work against actual screenshots. If you claim
  something is fixed/working and it isn't, he will catch it immediately
  and is (understandably) very sharp about it. Never claim something
  works without having actually checked the code. If you didn't verify,
  say so.
- Backups: C:\xampp folder → Google Drive daily at 3am. MySQL backups
  must be a proper `mysqldump` export (never a raw file copy of the live
  DB) saved into C:\xampp\htdocs\generallink\db-backups. Google Drive
  desktop app syncs everything automatically — you just need to save
  files/backups into the right folders; no manual upload needed.

## Architecture essentials

**Two admin categories — do not conflate them:**
1. GeneralLink's own platform Admin — `agents.role = 'ADMIN'`. Exactly
   3 accounts (Director/Finance/Sales departments). Cross-org, sees
   every CBE group on the platform.
2. Each CBE hierarchy node's own officer — a row in `cbe_node_officers`
   (node_id, role, agent_id, is_active). Scoped to exactly one node
   (e.g. one Temple, one Branch, one standalone Rotary chapter).

Both log in at the same shared front door, `/glade` (branded "GLADE"),
same `agents` table/credentials — see
`app/Http/Controllers/Auth/GladePortalController.php`
(`hasGladeAccess()` / `landingFor()`). The sidebar
(`resources/views/layouts/glade.blade.php`) computes `$isAdmin` and
`$isOfficer`/`$officerNodeId`/`$officerGroupId` near the top and uses
them throughout to decide what each type sees.

**KNOWN UNRESOLVED BUG (see changelog file, item #4):** the Membership
Module's "Entity Maintenance" sidebar link already computes an
officer-scoped group param, implying officers should be able to use it
for their own node — but the underlying route
(`admin.cbe-kpi.hierarchy-nodes.create`) sits inside a
`role:ADMIN`-only middleware group in `routes/web.php`, so a real
officer hitting it gets a 403. Needs a decision from Chris on whether to
loosen the middleware (officer manages own node only) or hide the link
from officers entirely. Don't silently pick one — ask.

**CBE hierarchy data model:**
- `group_labels` (group_type='CBE') — one row per CBE group/community
  (e.g. Tao Malaysia, a Rotary Club).
- `cbe_hierarchy_levels` (level_id, group_label_id, level_order,
  level_name) — admin-defined, arbitrary depth per group. A group's
  level names (HQ/State/Branch/Temple, or whatever) are NEVER hardcoded
  anywhere in the app.
- `cbe_hierarchy_nodes` (node_id, group_label_id, level_id FK restrict,
  parent_node_id FK restrict, node_name, city, postcode,
  hierarchy_path materialized path, coverage_postcode_start/end).
  `hierarchy_path` is a materialized path — every "this node and
  everything below it" query is `hierarchy_path LIKE 'prefix%'`. Any
  reparent must recursively rewrite hierarchy_path for the node AND all
  its descendants.

**Sidebar conventions (`resources/views/layouts/glade.blade.php`):**
- Collapsible `nav-parent`/`nav-submenu` accordion, `SECTION_IDS` JS
  array drives open/close.
- Nested `nav-parent-sub`/`sb-item-sub` for the Financial Accounting
  Module's 2nd-level categories.
- Every `$xxxSubActive` PHP flag must be claimed by exactly ONE
  category, or two sections will auto-open at once on page load — this
  has broken before when links moved between categories without moving
  their route claim too. Check this carefully on any sidebar reshuffle.
- Single CSS variable `--sidebar-w` drives #sidebar/#topbar/#main widths
  together.

**3-step Prev/Next UX pattern** (used for Entity Maintenance, Link to
Another Group, etc.): Step 1 pick top-level thing (e.g. CBE Group) →
Step 2 pick sub-choice (e.g. Level) → Step 3 final form. Implemented via
GET query params, with "Change X" buttons to go back a step. Reuse this
pattern for any new multi-step admin flow — Chris has explicitly
rejected single-screen "show everything at once" designs multiple times.

## Environment gotcha

The bash/Linux sandbox used by the coding assistant can go down
independently of GeneralLink itself (a tooling issue, not a project
bug) — symptom: "VM service not running. Restart your computer to
restore it." When this happens: Read/Edit/Write file tools still work
fine for all code; what breaks is running shell commands (migrations,
composer, npm) and anything requiring binary file handling (opening/
editing the master-spec .docx, PDF generation, etc.). Don't fake
progress on those — tell Chris plainly and give him the exact commands
to run himself once you're blocked.

## Immediate open items as of 11 Sep 2026

1. Confirm Chris ran `php artisan migrate` for
   `2026_09_11_000001_add_coverage_postcode_range_to_cbe_hierarchy_nodes.php`.
2. Resolve the officer-access bug above (needs Chris's decision).
3. Merge `PENDING-master-spec-changelog-110926.txt` into a real
   `master-spec-v155-*.docx` once the docx-editing sandbox is back, with
   a version-log snapshot of v154 taken first.
4. Task #75 (AI Module Phase 17: Printable PDF Official Receipts) and
   Task #84 (final verification sweep for the hub reorg) were left
   in_progress — check current state before assuming either is done.
