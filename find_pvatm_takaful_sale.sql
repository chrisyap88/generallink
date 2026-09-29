-- Diagnostic only — finds the real Takaful / Motor Comprehensive sale(s)
-- for PVATM and shows exactly why they're not showing as the Top
-- Vendor / Top Product on the KPI Dashboard (which only counts THIS
-- MONTH's sales, for agents inside PVATM's own group).
-- Run this in phpMyAdmin (SQL tab) and send me back what it prints.

SET @pvatm_group_id = (
    SELECT group_label_id FROM group_labels WHERE group_name LIKE '%PVATM%' LIMIT 1
);

-- Step 1: does a Takaful vendor exist, and what's it called exactly?
SELECT vendor_id, vendor_name, industry
FROM vendors
WHERE vendor_name LIKE '%Takaful%';

-- Step 2: does "Motor Comprehensive" exist as a product, and for which vendor?
SELECT p.product_id, p.product_name, p.vendor_id, v.vendor_name
FROM products p
JOIN vendors v ON v.vendor_id = p.vendor_id
WHERE p.product_name = 'Motor Comprehensive';

-- Step 3: every PVATM sales_transaction for a Takaful vendor + Motor
-- Comprehensive product — regardless of month — so we can see the real
-- transaction(s) and why they're not counted as "this month".
SELECT
    st.policy_id, st.policy_number, st.premium_amount, st.status,
    st.created_at, st.agent_id, a.full_name AS agent_name, a.group_label_id,
    v.vendor_name, p.product_name
FROM sales_transactions st
JOIN vendors v  ON v.vendor_id  = st.vendor_id
JOIN products p ON p.product_id = st.product_id
JOIN agents a   ON a.agent_id   = st.agent_id
WHERE v.vendor_name LIKE '%Takaful%'
  AND p.product_name = 'Motor Comprehensive'
  AND a.group_label_id = @pvatm_group_id
ORDER BY st.created_at DESC;

-- Step 4: for comparison, what IS currently counted as this month's
-- (August 2026) top vendor/product for PVATM (the numbers the
-- dashboard is actually showing right now).
SELECT v.vendor_name, SUM(st.premium_amount) AS total_sales
FROM sales_transactions st
JOIN vendors v ON v.vendor_id = st.vendor_id
JOIN agents a  ON a.agent_id  = st.agent_id
WHERE a.group_label_id = @pvatm_group_id
  AND MONTH(st.created_at) = MONTH(CURDATE())
  AND YEAR(st.created_at)  = YEAR(CURDATE())
GROUP BY v.vendor_id, v.vendor_name
ORDER BY total_sales DESC;
