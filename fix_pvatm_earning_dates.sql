-- Fixes: PVATM's Earning Income showing RM 0.00 for June (and possibly
-- other past months) even though the sales were real and commissions
-- had been recalculated.
--
-- Root cause (now also fixed in the code so it can't happen again):
-- CommissionEngine::calculate() always stamped commission_transactions.
-- created_at = NOW() every time it ran — including when RE-running a
-- recalculation for an OLD policy. So a policy sold in June, but
-- recalculated on 1 Aug (via commission:recalculate-pvatm), got its
-- commission row dated 1 Aug instead of June. Every dashboard that
-- buckets Earning Income by month (MTD/YTD/Top Vendor/Top Product/etc.)
-- then lost it from June's total.
--
-- This script repairs the ALREADY-recalculated PVATM rows by resetting
-- each commission_transactions.created_at back to match its policy's
-- real sale date (sales_transactions.created_at). Only touches PVATM's
-- own commission rows — nothing else.

SET @pvatm_group_id = (
    SELECT group_label_id FROM group_labels WHERE group_name LIKE '%PVATM%' LIMIT 1
);

-- Step 1: see how many rows are actually out of sync before changing anything
SELECT COUNT(*) AS rows_to_fix
FROM commission_transactions ct
JOIN sales_transactions st ON st.policy_id = ct.policy_id
JOIN agents a ON a.agent_id = st.agent_id
WHERE a.group_label_id = @pvatm_group_id
  AND DATE(ct.created_at) <> DATE(st.created_at);

-- Step 2: fix them — set each commission row's created_at to match its
-- own policy's real sale date/time.
UPDATE commission_transactions ct
JOIN sales_transactions st ON st.policy_id = ct.policy_id
JOIN agents a ON a.agent_id = st.agent_id
SET ct.created_at = st.created_at
WHERE a.group_label_id = @pvatm_group_id;

-- Step 3: confirm — should now be 0
SELECT COUNT(*) AS rows_still_mismatched
FROM commission_transactions ct
JOIN sales_transactions st ON st.policy_id = ct.policy_id
JOIN agents a ON a.agent_id = st.agent_id
WHERE a.group_label_id = @pvatm_group_id
  AND DATE(ct.created_at) <> DATE(st.created_at);

-- ============================================================
-- Same Takaful / Motor Comprehensive diagnostic as before, so we can
-- also answer "why isn't Takaful the top vendor" in the same run.
-- ============================================================

SELECT vendor_id, vendor_name, industry
FROM vendors
WHERE vendor_name LIKE '%Takaful%';

SELECT
    st.policy_id, st.policy_number, st.premium_amount, st.status,
    st.created_at, st.agent_id, a.full_name AS agent_name
FROM sales_transactions st
JOIN vendors v  ON v.vendor_id  = st.vendor_id
JOIN products p ON p.product_id = st.product_id
JOIN agents a   ON a.agent_id   = st.agent_id
WHERE v.vendor_name LIKE '%Takaful%'
  AND p.product_name = 'Motor Comprehensive'
  AND a.group_label_id = @pvatm_group_id
ORDER BY st.created_at DESC;
