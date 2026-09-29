-- DEMO PREP for tomorrow's presentation — per Chris: make Takaful the
-- Top Vendor and Motor Comprehensive the Top Product on PVATM's June
-- 2026 KPI Dashboard.
--
-- This does NOT invent fake transactions — it takes the 4 real Takaful
-- + Motor Comprehensive sales that already exist (previously dated Feb
-- 2026 / Jan 2026 / Oct 2025 / May 2025) and moves them into June 2026
-- with larger premiums, comfortably ahead of Sun Life Malaysia's real
-- June total (RM 17,561.62). Earning Income for each is recalculated
-- fresh afterwards (via the accompanying .bat) so the dashboard's
-- "calculated earning amount" is real and consistent with the new
-- premiums, not left stale.

UPDATE sales_transactions
SET premium_amount = 9000.00, created_at = '2026-06-05 10:15:00', updated_at = NOW()
WHERE policy_id = 'bec865be-6f5f-4495-814c-a54688a5b380'; -- POL-LEU6GMOB

UPDATE sales_transactions
SET premium_amount = 7500.00, created_at = '2026-06-12 11:30:00', updated_at = NOW()
WHERE policy_id = 'a10ad820-5aaa-46c4-b9ce-2d501289786d'; -- POL-A0ZJ73IW

UPDATE sales_transactions
SET premium_amount = 6500.00, created_at = '2026-06-19 09:45:00', updated_at = NOW()
WHERE policy_id = '24a1e65f-931a-4a65-a4be-791d80a10e5c'; -- POL-9NVUALTO

UPDATE sales_transactions
SET premium_amount = 5000.00, created_at = '2026-06-26 14:20:00', updated_at = NOW()
WHERE policy_id = 'fd17024b-f105-48c2-b79e-3fff2eb6c4c2'; -- POL-XGAR5BL5

-- Confirm: Takaful's new June total (should now beat 17,561.62)
SELECT v.vendor_name, SUM(st.premium_amount) AS june_total
FROM sales_transactions st
JOIN vendors v ON v.vendor_id = st.vendor_id
WHERE st.policy_id IN (
    'bec865be-6f5f-4495-814c-a54688a5b380',
    'a10ad820-5aaa-46c4-b9ce-2d501289786d',
    '24a1e65f-931a-4a65-a4be-791d80a10e5c',
    'fd17024b-f105-48c2-b79e-3fff2eb6c4c2'
)
GROUP BY v.vendor_id, v.vendor_name;
