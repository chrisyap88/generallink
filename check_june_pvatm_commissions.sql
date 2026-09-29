-- Follow-up diagnostic: the earlier date-repair script found ZERO
-- mismatched rows (rows_to_fix = 0), which means the commission dates
-- were already correct — so the created_at bug wasn't actually the live
-- cause of June showing RM 0.00. This checks a different, more
-- concerning possibility: that some of June's PVATM policies ended up
-- with NO valid (CONFIRMED/PENDING) commission_transactions row at all
-- after the recalculation — only a REVERSED one, meaning they were
-- reversed but never successfully recalculated back.
-- Run this and send me back everything it prints.

SET @pvatm_group_id = (
    SELECT group_label_id FROM group_labels WHERE group_name LIKE '%PVATM%' LIMIT 1
);

-- Every June 2026 PVATM policy, with a per-status breakdown of its
-- commission rows (0 rows in a status = blank for that column).
SELECT
    st.policy_id,
    st.policy_number,
    st.premium_amount,
    st.status AS policy_status,
    st.created_at AS sold_on,
    SUM(CASE WHEN ct.status='PENDING'   THEN ct.commission_amount ELSE 0 END) AS pending_total,
    SUM(CASE WHEN ct.status='CONFIRMED' THEN ct.commission_amount ELSE 0 END) AS confirmed_total,
    SUM(CASE WHEN ct.status='REVERSED'  THEN ct.commission_amount ELSE 0 END) AS reversed_total,
    COUNT(ct.txn_id) AS total_commission_rows
FROM sales_transactions st
JOIN agents a ON a.agent_id = st.agent_id
LEFT JOIN commission_transactions ct ON ct.policy_id = st.policy_id
WHERE a.group_label_id = @pvatm_group_id
  AND MONTH(st.created_at) = 6
  AND YEAR(st.created_at) = 2026
GROUP BY st.policy_id, st.policy_number, st.premium_amount, st.status, st.created_at
ORDER BY st.created_at;

-- Summary count: how many June policies have ZERO commission rows at
-- all, vs only-REVERSED (no valid replacement), vs a real PENDING/
-- CONFIRMED amount.
SELECT
    SUM(CASE WHEN total_commission_rows = 0 THEN 1 ELSE 0 END) AS policies_with_no_commission_row,
    SUM(CASE WHEN total_commission_rows > 0 AND pending_total = 0 AND confirmed_total = 0 THEN 1 ELSE 0 END) AS policies_only_reversed,
    SUM(CASE WHEN pending_total > 0 OR confirmed_total > 0 THEN 1 ELSE 0 END) AS policies_with_valid_commission
FROM (
    SELECT
        st.policy_id,
        SUM(CASE WHEN ct.status='PENDING'   THEN ct.commission_amount ELSE 0 END) AS pending_total,
        SUM(CASE WHEN ct.status='CONFIRMED' THEN ct.commission_amount ELSE 0 END) AS confirmed_total,
        COUNT(ct.txn_id) AS total_commission_rows
    FROM sales_transactions st
    JOIN agents a ON a.agent_id = st.agent_id
    LEFT JOIN commission_transactions ct ON ct.policy_id = st.policy_id
    WHERE a.group_label_id = @pvatm_group_id
      AND MONTH(st.created_at) = 6
      AND YEAR(st.created_at) = 2026
    GROUP BY st.policy_id
) x;
