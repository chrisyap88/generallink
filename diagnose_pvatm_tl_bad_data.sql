-- Checking PVATM's 14 Cawangan (TEAM_LEADER) records for anything that
-- could break the page render (null dates, bad numbers, etc.)
SELECT agent_id, full_name, agent_code, status, created_at, commission_balance
FROM agents
WHERE role = 'TEAM_LEADER' AND is_deleted = 0
  AND parent_id = (SELECT agent_id FROM agents WHERE full_name LIKE '%PVATM%' AND role='GROUP_LEADER' AND is_deleted=0 LIMIT 1)
ORDER BY full_name;
