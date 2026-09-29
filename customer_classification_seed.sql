-- GeneralLink — Customer Classification lookup data
-- Safe to run any time: uses INSERT IGNORE keyed on the unique `code`
-- column, so re-running this never creates duplicates.

-- ============ CUSTOMER CATEGORIES ============
INSERT IGNORE INTO customer_categories (category_id, code, description, is_active, is_system, created_at, updated_at) VALUES
(UUID(), 'INDIVIDUAL', 'Individual', 1, 0, NOW(), NOW()),
(UUID(), 'FAMILY', 'Family', 1, 0, NOW(), NOW()),
(UUID(), 'SME', 'SME', 1, 0, NOW(), NOW()),
(UUID(), 'CORPORATE', 'Corporate', 1, 0, NOW(), NOW()),
(UUID(), 'GOVERNMENT', 'Government', 1, 0, NOW(), NOW()),
(UUID(), 'NGO_CHARITY', 'NGO / Charity', 1, 0, NOW(), NOW()),
(UUID(), 'ASSOCIATION', 'Association', 1, 0, NOW(), NOW()),
(UUID(), 'EDUCATIONAL_INSTITUTION', 'Educational Institution', 1, 0, NOW(), NOW());

-- ============ OCCUPATION GROUPS ============
INSERT IGNORE INTO occupation_groups (occupation_group_id, code, description, is_active, is_system, created_at, updated_at) VALUES
(UUID(), 'GOVERNMENT_OFFICER', 'Government Officer', 1, 0, NOW(), NOW()),
(UUID(), 'HEALTHCARE', 'Healthcare', 1, 0, NOW(), NOW()),
(UUID(), 'EDUCATION', 'Education', 1, 0, NOW(), NOW()),
(UUID(), 'BANKING_AND_FINANCE', 'Banking & Finance', 1, 0, NOW(), NOW()),
(UUID(), 'INSURANCE', 'Insurance', 1, 0, NOW(), NOW()),
(UUID(), 'MILITARY', 'Military', 1, 0, NOW(), NOW()),
(UUID(), 'POLICE', 'Police', 1, 0, NOW(), NOW()),
(UUID(), 'FIRE_AND_RESCUE', 'Fire & Rescue', 1, 0, NOW(), NOW()),
(UUID(), 'PRIVATE_EMPLOYEE', 'Private Employee', 1, 0, NOW(), NOW()),
(UUID(), 'SELF_EMPLOYED', 'Self-Employed', 1, 0, NOW(), NOW()),
(UUID(), 'BUSINESS_OWNER', 'Business Owner', 1, 0, NOW(), NOW()),
(UUID(), 'PROFESSIONAL', 'Professional', 1, 0, NOW(), NOW()),
(UUID(), 'RETIRED', 'Retired', 1, 0, NOW(), NOW()),
(UUID(), 'STUDENT', 'Student', 1, 0, NOW(), NOW()),
(UUID(), 'HOUSEWIFE', 'Housewife', 1, 0, NOW(), NOW()),
(UUID(), 'UNEMPLOYED', 'Unemployed', 1, 0, NOW(), NOW()),
(UUID(), 'OTHERS', 'Others', 1, 0, NOW(), NOW());

-- ============ CUSTOMER SOURCES ============
INSERT IGNORE INTO customer_sources (source_id, code, description, is_active, is_system, created_at, updated_at) VALUES
(UUID(), 'WALK_IN', 'Walk-in', 1, 0, NOW(), NOW()),
(UUID(), 'REFERRAL', 'Referral', 1, 0, NOW(), NOW()),
(UUID(), 'INTRODUCER', 'Introducer', 1, 0, NOW(), NOW()),
(UUID(), 'AGENT', 'Agent', 1, 0, NOW(), NOW()),
(UUID(), 'BROKER', 'Broker', 1, 0, NOW(), NOW()),
(UUID(), 'WEBSITE', 'Website', 1, 0, NOW(), NOW()),
(UUID(), 'FACEBOOK', 'Facebook', 1, 0, NOW(), NOW()),
(UUID(), 'GOOGLE', 'Google', 1, 0, NOW(), NOW()),
(UUID(), 'TIKTOK', 'TikTok', 1, 0, NOW(), NOW()),
(UUID(), 'WHATSAPP', 'WhatsApp', 1, 0, NOW(), NOW()),
(UUID(), 'EXISTING_CUSTOMER', 'Existing Customer', 1, 0, NOW(), NOW()),
(UUID(), 'CAMPAIGN', 'Campaign', 1, 0, NOW(), NOW());

-- ============ CUSTOMER STATUS (full list incl. Active) ============
-- ACTIVE/PROSPECT/SUSPENDED/WITHDRAWN are marked is_system=1 (business
-- logic depends on their exact codes); everything else is a normal,
-- freely editable/removable entry.
INSERT IGNORE INTO customer_statuses (status_id, code, description, is_active, is_system, created_at, updated_at) VALUES
(UUID(), 'ACTIVE', 'Active', 1, 1, NOW(), NOW()),
(UUID(), 'PENDING', 'Pending', 1, 0, NOW(), NOW()),
(UUID(), 'IN_PROGRESS', 'In Progress', 1, 0, NOW(), NOW()),
(UUID(), 'WAITING_DOCUMENTS', 'Waiting Documents', 1, 0, NOW(), NOW()),
(UUID(), 'UNDER_REVIEW', 'Under Review', 1, 0, NOW(), NOW()),
(UUID(), 'QUOTATION_ISSUED', 'Quotation Issued', 1, 0, NOW(), NOW()),
(UUID(), 'WAITING_CUSTOMER_RESPONSE', 'Waiting Customer Response', 1, 0, NOW(), NOW()),
(UUID(), 'APPROVED', 'Approved', 1, 0, NOW(), NOW()),
(UUID(), 'REJECTED', 'Rejected', 1, 0, NOW(), NOW()),
(UUID(), 'POLICY_ISSUED', 'Policy Issued', 1, 0, NOW(), NOW()),
(UUID(), 'POLICY_EXPIRED', 'Policy Expired', 1, 0, NOW(), NOW()),
(UUID(), 'RENEWAL_DUE', 'Renewal Due', 1, 0, NOW(), NOW()),
(UUID(), 'RENEWED', 'Renewed', 1, 0, NOW(), NOW()),
(UUID(), 'CLAIM_ACTIVE', 'Claim Active', 1, 0, NOW(), NOW()),
(UUID(), 'CLAIM_CLOSED', 'Claim Closed', 1, 0, NOW(), NOW()),
(UUID(), 'SUSPENDED', 'Suspended', 1, 1, NOW(), NOW()),
(UUID(), 'ARCHIVED', 'Archived', 1, 0, NOW(), NOW()),
(UUID(), 'WITHDRAWN', 'Withdrawn', 1, 1, NOW(), NOW()),
(UUID(), 'BLACKLISTED', 'Blacklisted', 1, 0, NOW(), NOW()),
(UUID(), 'DECEASED', 'Deceased', 1, 0, NOW(), NOW());
