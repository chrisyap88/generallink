-- What is PVATM's configured "Cawangan" (Team Leader) label?
SELECT group_label_id, group_name, promotion_demotion_enabled
FROM group_labels
WHERE group_name LIKE '%PVATM%' OR group_name LIKE '%pvatm%';

SELECT role, label, short_label, group_label_id
FROM role_label_overrides
WHERE group_label_id = (SELECT group_label_id FROM group_labels WHERE group_name LIKE '%PVATM%' LIMIT 1);
