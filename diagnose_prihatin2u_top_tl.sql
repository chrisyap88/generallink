-- Diagnostic: why does "Tan Ah Kow" show as #1 TL when Chris Yap (prihatin2u)
-- group filter is selected, instead of "Amy Tan"?
-- This reproduces the exact same logic the KPI Dashboard uses.

-- 1. Find Chris Yap's own GL record (this is what "prihatin2u — Chris Yap" points to)
SELECT agent_id, full_name, agent_code, group_id, group_label_id, status
FROM agents
WHERE full_name LIKE '%Chris Yap%' AND role='GROUP_LEADER' AND is_deleted=0;

-- 2. Any OTHER GLs "promoted" out of Chris Yap's downline (agent_code
--    prefixed with his own code + '-') -- these get pulled into his
--    "dynasty" scope too, per the dashboard's own logic.
SELECT p.agent_id, p.full_name, p.agent_code, p.group_id
FROM agents p
JOIN agents gl ON gl.full_name LIKE '%Chris Yap%' AND gl.role='GROUP_LEADER' AND gl.is_deleted=0
WHERE p.role='GROUP_LEADER' AND p.is_deleted=0
  AND p.agent_id != gl.agent_id
  AND p.agent_code LIKE CONCAT(gl.agent_code, '-%');

-- 3. Where do Tan Ah Kow and Amy Tan actually sit? (group_id, parent GL, status)
SELECT tl.agent_id, tl.full_name, tl.agent_code, tl.group_id, tl.parent_id,
       gl.full_name AS gl_name, gl.agent_code AS gl_code, gl.group_id AS gl_group_id
FROM agents tl
LEFT JOIN agents gl ON gl.agent_id = tl.parent_id
WHERE tl.role='TEAM_LEADER' AND tl.is_deleted=0
  AND (tl.full_name LIKE '%Tan Ah Kow%' OR tl.full_name LIKE '%Amy Tan%');

-- 4. Each TL's June 2026 total (own sales + their introducers' sales) --
--    this is the exact number the dashboard ranks by. Restricted to TLs
--    who share Chris Yap's group_id (his direct dynasty only, matching
--    getDynastyAgentIds logic -- ignoring any "promoted GL" expansion for
--    simplicity here).
SELECT tl.full_name, tl.agent_code, tl.group_id,
       COALESCE((
           SELECT SUM(st.premium_amount)
           FROM sales_transactions st
           JOIN agents a ON a.agent_id = st.agent_id
           WHERE (a.parent_id = tl.agent_id OR st.agent_id = tl.agent_id)
             AND st.is_deleted = 0
             AND MONTH(st.created_at) = 6 AND YEAR(st.created_at) = 2026
       ), 0) AS june_total
FROM agents tl
WHERE tl.role='TEAM_LEADER' AND tl.is_deleted=0
  AND tl.group_id = (SELECT group_id FROM agents WHERE full_name LIKE '%Chris Yap%' AND role='GROUP_LEADER' AND is_deleted=0 LIMIT 1)
ORDER BY june_total DESC;
