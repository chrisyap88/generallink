-- Diagnostic only — no changes, just shows what's stored.
-- Run in phpMyAdmin (SQL tab) against the generallink database.
SELECT
    rlo.role,
    rlo.label,
    rlo.short_label,
    rlo.group_label_id,
    gl.group_name
FROM role_label_overrides rlo
LEFT JOIN group_labels gl ON gl.group_label_id = rlo.group_label_id
ORDER BY rlo.group_label_id IS NULL DESC, rlo.role;
