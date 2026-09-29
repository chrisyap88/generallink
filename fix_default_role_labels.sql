-- Fixes: Admin's "no group selected" screens (e.g. Network Tree) wrongly
-- showing "Ibu Pejabat" instead of the generic default "Group Leader".
--
-- Root cause: a leftover row from BEFORE the 31 Jul rescope migration.
-- Back then PVATM's "Ibu Pejabat" label was saved under the old group_id
-- system. That column was dropped on 31 Jul, and the old row silently
-- turned into a "System Default" (group_label_id = NULL) row instead of
-- being cleaned up. This script removes ONLY the NULL-scoped rows and
-- reinserts the true 3 system defaults. It does NOT touch PVATM's (or
-- any other group's) own real labels — those are safely scoped to their
-- own group_label_id and are untouched by this script.

-- Step 1: see what's there right now (safe, no changes yet)
SELECT role, label, short_label, group_label_id
FROM role_label_overrides
ORDER BY group_label_id IS NULL DESC, role;

-- Step 2: wipe out only the bad "System Default" rows
DELETE FROM role_label_overrides WHERE group_label_id IS NULL;

-- Step 3: put back the 3 clean system defaults
INSERT INTO role_label_overrides (override_id, role, group_label_id, label, short_label, created_at, updated_at)
VALUES
    (UUID(), 'GROUP_LEADER', NULL, 'Group Leader', NULL, NOW(), NOW()),
    (UUID(), 'TEAM_LEADER',  NULL, 'Team Leader',  NULL, NOW(), NOW()),
    (UUID(), 'INTRODUCER',   NULL, 'Introducer',   NULL, NOW(), NOW());

-- Step 4: confirm — should now show exactly ONE NULL-scoped row per role
-- (Group Leader / Team Leader / Introducer), plus PVATM's own rows
-- untouched below them.
SELECT role, label, short_label, group_label_id
FROM role_label_overrides
ORDER BY group_label_id IS NULL DESC, role;
