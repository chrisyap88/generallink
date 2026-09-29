-- Fill in / redistribute rank for PVATM Introducers ("Ali")
-- -----------------------------------------------------------
-- UPDATED: the first version of this script only used PVATM's TWO
-- lowest-numbered active Introducer ranks (per the original request).
-- If PVATM has MORE than 2 active Introducer ranks defined (e.g. a
-- "Wakil Pemasaran 3"), those extra ranks were deliberately skipped —
-- not a bug. This version instead randomly spreads every PVATM
-- Introducer across ALL of PVATM's active Introducer ranks, whatever
-- they are and however many there are.
--
-- This RE-RANDOMIZES every PVATM Introducer (not just ones with no
-- rank), since the earlier run already filled everyone in using only
-- 2 ranks — this replaces that with a fuller spread across all ranks.
-- If you have already manually corrected some agents' ranks by hand
-- and want THOSE left alone, do not run this version — ask instead and
-- I'll give you a version that only touches specific agents.
--
-- HOW TO RUN (phpMyAdmin):
--   1. Open phpMyAdmin (http://localhost/phpmyadmin) and select the
--      generallink database.
--   2. Click the "SQL" tab.
--   3. Paste this entire file in and click "Go".
--   4. The last SELECT at the bottom shows a count per rank, so you
--      can confirm every rank (including Wakil Pemasaran 3) now has
--      agents on it.

SET @pvatm_group_id = (
    SELECT group_label_id FROM group_labels WHERE group_name LIKE '%PVATM%' LIMIT 1
);

-- Sanity check — list every active Introducer rank PVATM has, so you
-- can see exactly what will be used before the UPDATE runs.
SELECT rank_id, rank_no, rank_name
FROM role_ranks
WHERE group_label_id = @pvatm_group_id AND role = 'INTRODUCER' AND is_active = 1
ORDER BY CAST(rank_no AS UNSIGNED);

UPDATE agents a
SET rank_id = (
        SELECT rr.rank_id
        FROM role_ranks rr
        WHERE rr.group_label_id = @pvatm_group_id
          AND rr.role = 'INTRODUCER'
          AND rr.is_active = 1
        ORDER BY RAND()
        LIMIT 1
    ),
    updated_at = NOW()
WHERE a.group_label_id = @pvatm_group_id
  AND a.role = 'INTRODUCER'
  AND a.is_deleted = 0;

-- Verify: how many Introducers ended up on each rank.
SELECT rr.rank_no, rr.rank_name, COUNT(*) AS agent_count
FROM agents a
JOIN role_ranks rr ON rr.rank_id = a.rank_id
WHERE a.group_label_id = @pvatm_group_id
  AND a.role = 'INTRODUCER'
  AND a.is_deleted = 0
GROUP BY rr.rank_id, rr.rank_no, rr.rank_name
ORDER BY CAST(rr.rank_no AS UNSIGNED);
