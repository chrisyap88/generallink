-- Checks whether commission_transactions rows exist at all for Chris Yap's
-- WHOLE downline — Team Leaders AND their Introducers (per Chris: Introducers
-- earn commission too, not just TLs) — specifically for June 2026 (the demo
-- month), so we know whether the 0.00 Earning Income MTD is a missing-data
-- problem (needs recalculation) or something else.

SET @chris_id = (SELECT agent_id FROM agents WHERE full_name = 'Chris Yap' AND is_deleted = 0 LIMIT 1);

-- 1. Every TL + Introducer under Chris Yap (2 levels down)
SELECT agent_id, full_name, agent_code, role, parent_id
FROM agents
WHERE is_deleted = 0
  AND (parent_id = @chris_id
       OR parent_id IN (SELECT agent_id FROM agents WHERE parent_id = @chris_id AND role = 'TEAM_LEADER' AND is_deleted = 0))
ORDER BY role, full_name;

-- 2. ANY commission_transactions ever recorded for this whole downline,
--    grouped by role + status (proves whether commission calc has EVER run
--    for TLs, for Introducers, or for neither)
SELECT a.role, ct.status, COUNT(*) AS cnt, SUM(ct.commission_amount) AS total_amt,
       MIN(ct.created_at) AS earliest, MAX(ct.created_at) AS latest
FROM commission_transactions ct
JOIN agents a ON a.agent_id = ct.agent_id
WHERE a.agent_id IN (
    SELECT agent_id FROM agents
    WHERE is_deleted = 0
      AND (parent_id = @chris_id
           OR parent_id IN (SELECT agent_id FROM agents WHERE parent_id = @chris_id AND role = 'TEAM_LEADER' AND is_deleted = 0))
)
GROUP BY a.role, ct.status;

-- 3. Specifically June 2026, per agent, TL + Introducer
SELECT a.agent_id, a.full_name, a.role, ct.status, COUNT(*) AS cnt, SUM(ct.commission_amount) AS total_amt
FROM commission_transactions ct
JOIN agents a ON a.agent_id = ct.agent_id
WHERE a.agent_id IN (
    SELECT agent_id FROM agents
    WHERE is_deleted = 0
      AND (parent_id = @chris_id
           OR parent_id IN (SELECT agent_id FROM agents WHERE parent_id = @chris_id AND role = 'TEAM_LEADER' AND is_deleted = 0))
)
  AND MONTH(ct.created_at) = 6 AND YEAR(ct.created_at) = 2026
GROUP BY a.agent_id, a.full_name, a.role, ct.status
ORDER BY a.role, a.full_name;

-- 4. Confirm June sales_transactions really exist (for comparison), TL + Introducer
SELECT a.agent_id, a.full_name, a.role, COUNT(*) AS cnt, SUM(st.premium_amount) AS total_premium
FROM sales_transactions st
JOIN agents a ON a.agent_id = st.agent_id
WHERE a.agent_id IN (
    SELECT agent_id FROM agents
    WHERE is_deleted = 0
      AND (parent_id = @chris_id
           OR parent_id IN (SELECT agent_id FROM agents WHERE parent_id = @chris_id AND role = 'TEAM_LEADER' AND is_deleted = 0))
)
  AND MONTH(st.created_at) = 6 AND YEAR(st.created_at) = 2026
  AND st.is_deleted = 0
GROUP BY a.agent_id, a.full_name, a.role
ORDER BY a.role, a.full_name;
