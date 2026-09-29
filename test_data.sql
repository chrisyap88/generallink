-- ============================================================
-- GeneralLink Test Data — 2 Groups with full hierarchy
-- Group 2: Amy Tan (GL-00002)
-- Group 3: David Lim (GL-00003)
-- Each GL → 4 TLs → each TL → 4 Introducers → each Introducer → 2 Introducers
-- ============================================================

SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHxL9h0ym'; -- password: "password"
SET @now = NOW();
SET @admin_id = '40dd977a-82a6-403c-9ad8-3df18f30c1a3';

-- ============================================================
-- GROUP 2: AMY TAN
-- ============================================================
SET @g2_id   = UUID();
SET @gl2_id  = UUID();

-- Group record
INSERT INTO `groups` (group_id, group_name, group_code, group_email, separator_char, root_member_suffix, is_active, created_by, created_at, updated_at)
VALUES (@g2_id, 'Amy Tan', 'A0002', 'amytan@generallink.my', '-', '0', 1, @admin_id, @now, @now);

-- GL: Amy Tan
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@gl2_id, 'Amy Tan', 'amytan@generallink.my', @pwd, 'A000002', '+60112000001', 'GROUP_LEADER', 'ACTIVE', 'GL-00002', 'A0002-0', @g2_id, NULL, CONCAT('/', @gl2_id, '/'), 3, 0, 0, @admin_id, @now, @now);

-- TL 2-1
SET @tl21_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl21_id, 'Amy TL Sarah Wong', 'sarah.wong@generallink.my', @pwd, 'T210001', '+60112000101', 'TEAM_LEADER', 'ACTIVE', 'TL-00021', 'A0002-1-01', @g2_id, @gl2_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- TL 2-2
SET @tl22_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl22_id, 'Amy TL Kevin Ng', 'kevin.ng@generallink.my', @pwd, 'T220001', '+60112000102', 'TEAM_LEADER', 'ACTIVE', 'TL-00022', 'A0002-1-02', @g2_id, @gl2_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- TL 2-3
SET @tl23_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl23_id, 'Amy TL Linda Chia', 'linda.chia@generallink.my', @pwd, 'T230001', '+60112000103', 'TEAM_LEADER', 'ACTIVE', 'TL-00023', 'A0002-1-03', @g2_id, @gl2_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- TL 2-4
SET @tl24_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl24_id, 'Amy TL Raymond Teh', 'raymond.teh@generallink.my', @pwd, 'T240001', '+60112000104', 'TEAM_LEADER', 'ACTIVE', 'TL-00024', 'A0002-1-04', @g2_id, @gl2_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- ---- Introducers under TL 2-1 (Sarah Wong) ----
SET @i2101_id = UUID(); SET @i2102_id = UUID(); SET @i2103_id = UUID(); SET @i2104_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i2101_id, 'Intro Sarah-1 Ali Hassan',    'ali.hassan@generallink.my',    @pwd, 'I210101', '+60113000101', 'INTRODUCER', 'ACTIVE', 'IN-02101', 'A0002-1-01-001', @g2_id, @tl21_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2101_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2102_id, 'Intro Sarah-2 Mei Ling',      'mei.ling@generallink.my',      @pwd, 'I210102', '+60113000102', 'INTRODUCER', 'ACTIVE', 'IN-02102', 'A0002-1-01-002', @g2_id, @tl21_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2102_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2103_id, 'Intro Sarah-3 Rajan Kumar',   'rajan.kumar@generallink.my',   @pwd, 'I210103', '+60113000103', 'INTRODUCER', 'ACTIVE', 'IN-02103', 'A0002-1-01-003', @g2_id, @tl21_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2103_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2104_id, 'Intro Sarah-4 Farah Aziz',    'farah.aziz@generallink.my',    @pwd, 'I210104', '+60113000104', 'INTRODUCER', 'ACTIVE', 'IN-02104', 'A0002-1-01-004', @g2_id, @tl21_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2104_id, '/'), 1, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2101
SET @s210101_id = UUID(); SET @s210102_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s210101_id, 'Sub Ali-1 Zack Musa',      'zack.musa@generallink.my',      @pwd, 'S21010101', '+60114000101', 'INTRODUCER', 'ACTIVE', 'IN-02111', 'A0002-1-01-001-01', @g2_id, @i2101_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2101_id, '/', @s210101_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s210102_id, 'Sub Ali-2 Nurul Ain',      'nurul.ain@generallink.my',      @pwd, 'S21010102', '+60114000102', 'INTRODUCER', 'ACTIVE', 'IN-02112', 'A0002-1-01-001-02', @g2_id, @i2101_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2101_id, '/', @s210102_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2102
SET @s210201_id = UUID(); SET @s210202_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s210201_id, 'Sub Mei-1 Jason Lim',      'jason.lim@generallink.my',      @pwd, 'S21020101', '+60114000201', 'INTRODUCER', 'ACTIVE', 'IN-02121', 'A0002-1-01-002-01', @g2_id, @i2102_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2102_id, '/', @s210201_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s210202_id, 'Sub Mei-2 Priya Nair',     'priya.nair@generallink.my',     @pwd, 'S21020102', '+60114000202', 'INTRODUCER', 'ACTIVE', 'IN-02122', 'A0002-1-01-002-02', @g2_id, @i2102_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2102_id, '/', @s210202_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2103
SET @s210301_id = UUID(); SET @s210302_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s210301_id, 'Sub Rajan-1 Hafiz Shah',   'hafiz.shah@generallink.my',     @pwd, 'S21030101', '+60114000301', 'INTRODUCER', 'ACTIVE', 'IN-02131', 'A0002-1-01-003-01', @g2_id, @i2103_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2103_id, '/', @s210301_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s210302_id, 'Sub Rajan-2 Cindy Tan',    'cindy.tan@generallink.my',      @pwd, 'S21030102', '+60114000302', 'INTRODUCER', 'ACTIVE', 'IN-02132', 'A0002-1-01-003-02', @g2_id, @i2103_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2103_id, '/', @s210302_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2104
SET @s210401_id = UUID(); SET @s210402_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s210401_id, 'Sub Farah-1 Bobby Chan',   'bobby.chan@generallink.my',     @pwd, 'S21040101', '+60114000401', 'INTRODUCER', 'ACTIVE', 'IN-02141', 'A0002-1-01-004-01', @g2_id, @i2104_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2104_id, '/', @s210401_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s210402_id, 'Sub Farah-2 Siti Rahimah', 'siti.rahimah@generallink.my',  @pwd, 'S21040102', '+60114000402', 'INTRODUCER', 'ACTIVE', 'IN-02142', 'A0002-1-01-004-02', @g2_id, @i2104_id, CONCAT('/', @gl2_id, '/', @tl21_id, '/', @i2104_id, '/', @s210402_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- ---- Introducers under TL 2-2 (Kevin Ng) ----
SET @i2201_id = UUID(); SET @i2202_id = UUID(); SET @i2203_id = UUID(); SET @i2204_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i2201_id, 'Intro Kevin-1 Ben Ooi',      'ben.ooi@generallink.my',        @pwd, 'I220101', '+60113000201', 'INTRODUCER', 'ACTIVE', 'IN-02201', 'A0002-1-02-001', @g2_id, @tl22_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2201_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2202_id, 'Intro Kevin-2 Grace Yeo',    'grace.yeo@generallink.my',      @pwd, 'I220102', '+60113000202', 'INTRODUCER', 'ACTIVE', 'IN-02202', 'A0002-1-02-002', @g2_id, @tl22_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2202_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2203_id, 'Intro Kevin-3 Amir Hamzah',  'amir.hamzah@generallink.my',    @pwd, 'I220103', '+60113000203', 'INTRODUCER', 'ACTIVE', 'IN-02203', 'A0002-1-02-003', @g2_id, @tl22_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2203_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2204_id, 'Intro Kevin-4 Lisa Koh',     'lisa.koh@generallink.my',       @pwd, 'I220104', '+60113000204', 'INTRODUCER', 'ACTIVE', 'IN-02204', 'A0002-1-02-004', @g2_id, @tl22_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2204_id, '/'), 1, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2201
SET @s220101_id = UUID(); SET @s220102_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s220101_id, 'Sub Ben-1 Tom Wee',        'tom.wee@generallink.my',        @pwd, 'S22010101', '+60114001101', 'INTRODUCER', 'ACTIVE', 'IN-02211', 'A0002-1-02-001-01', @g2_id, @i2201_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2201_id, '/', @s220101_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s220102_id, 'Sub Ben-2 Rose Lau',       'rose.lau@generallink.my',       @pwd, 'S22010102', '+60114001102', 'INTRODUCER', 'ACTIVE', 'IN-02212', 'A0002-1-02-001-02', @g2_id, @i2201_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2201_id, '/', @s220102_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2202
SET @s220201_id = UUID(); SET @s220202_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s220201_id, 'Sub Grace-1 Hadi Azman',   'hadi.azman@generallink.my',     @pwd, 'S22020101', '+60114001201', 'INTRODUCER', 'ACTIVE', 'IN-02221', 'A0002-1-02-002-01', @g2_id, @i2202_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2202_id, '/', @s220201_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s220202_id, 'Sub Grace-2 Wendy Foo',    'wendy.foo@generallink.my',      @pwd, 'S22020102', '+60114001202', 'INTRODUCER', 'ACTIVE', 'IN-02222', 'A0002-1-02-002-02', @g2_id, @i2202_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2202_id, '/', @s220202_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2203
SET @s220301_id = UUID(); SET @s220302_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s220301_id, 'Sub Amir-1 Kelvin Sim',    'kelvin.sim@generallink.my',     @pwd, 'S22030101', '+60114001301', 'INTRODUCER', 'ACTIVE', 'IN-02231', 'A0002-1-02-003-01', @g2_id, @i2203_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2203_id, '/', @s220301_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s220302_id, 'Sub Amir-2 Nadia Yusof',   'nadia.yusof@generallink.my',    @pwd, 'S22030102', '+60114001302', 'INTRODUCER', 'ACTIVE', 'IN-02232', 'A0002-1-02-003-02', @g2_id, @i2203_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2203_id, '/', @s220302_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2204
SET @s220401_id = UUID(); SET @s220402_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s220401_id, 'Sub Lisa-1 Darren Kok',    'darren.kok@generallink.my',     @pwd, 'S22040101', '+60114001401', 'INTRODUCER', 'ACTIVE', 'IN-02241', 'A0002-1-02-004-01', @g2_id, @i2204_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2204_id, '/', @s220401_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s220402_id, 'Sub Lisa-2 Suraya Malik',  'suraya.malik@generallink.my',   @pwd, 'S22040102', '+60114001402', 'INTRODUCER', 'ACTIVE', 'IN-02242', 'A0002-1-02-004-02', @g2_id, @i2204_id, CONCAT('/', @gl2_id, '/', @tl22_id, '/', @i2204_id, '/', @s220402_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- ---- Introducers under TL 2-3 (Linda Chia) ----
SET @i2301_id = UUID(); SET @i2302_id = UUID(); SET @i2303_id = UUID(); SET @i2304_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i2301_id, 'Intro Linda-1 Eric Pang',    'eric.pang@generallink.my',      @pwd, 'I230101', '+60113000301', 'INTRODUCER', 'ACTIVE', 'IN-02301', 'A0002-1-03-001', @g2_id, @tl23_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2301_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2302_id, 'Intro Linda-2 Nora Ismail',  'nora.ismail@generallink.my',    @pwd, 'I230102', '+60113000302', 'INTRODUCER', 'ACTIVE', 'IN-02302', 'A0002-1-03-002', @g2_id, @tl23_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2302_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2303_id, 'Intro Linda-3 William Hew',  'william.hew@generallink.my',    @pwd, 'I230103', '+60113000303', 'INTRODUCER', 'ACTIVE', 'IN-02303', 'A0002-1-03-003', @g2_id, @tl23_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2303_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2304_id, 'Intro Linda-4 Jasmine Low',  'jasmine.low@generallink.my',    @pwd, 'I230104', '+60113000304', 'INTRODUCER', 'ACTIVE', 'IN-02304', 'A0002-1-03-004', @g2_id, @tl23_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2304_id, '/'), 1, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2301
SET @s230101_id = UUID(); SET @s230102_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s230101_id, 'Sub Eric-1 Fong Wei',      'fong.wei@generallink.my',       @pwd, 'S23010101', '+60114002101', 'INTRODUCER', 'ACTIVE', 'IN-02311', 'A0002-1-03-001-01', @g2_id, @i2301_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2301_id, '/', @s230101_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s230102_id, 'Sub Eric-2 Haslinda Bt',   'haslinda.bt@generallink.my',    @pwd, 'S23010102', '+60114002102', 'INTRODUCER', 'ACTIVE', 'IN-02312', 'A0002-1-03-001-02', @g2_id, @i2301_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2301_id, '/', @s230102_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2302
SET @s230201_id = UUID(); SET @s230202_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s230201_id, 'Sub Nora-1 Chester Yap',   'chester.yap@generallink.my',    @pwd, 'S23020101', '+60114002201', 'INTRODUCER', 'ACTIVE', 'IN-02321', 'A0002-1-03-002-01', @g2_id, @i2302_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2302_id, '/', @s230201_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s230202_id, 'Sub Nora-2 Irene Soh',     'irene.soh@generallink.my',      @pwd, 'S23020102', '+60114002202', 'INTRODUCER', 'ACTIVE', 'IN-02322', 'A0002-1-03-002-02', @g2_id, @i2302_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2302_id, '/', @s230202_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2303
SET @s230301_id = UUID(); SET @s230302_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s230301_id, 'Sub William-1 Azrul Hadi',  'azrul.hadi@generallink.my',    @pwd, 'S23030101', '+60114002301', 'INTRODUCER', 'ACTIVE', 'IN-02331', 'A0002-1-03-003-01', @g2_id, @i2303_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2303_id, '/', @s230301_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s230302_id, 'Sub William-2 Penny Khor',  'penny.khor@generallink.my',    @pwd, 'S23030102', '+60114002302', 'INTRODUCER', 'ACTIVE', 'IN-02332', 'A0002-1-03-003-02', @g2_id, @i2303_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2303_id, '/', @s230302_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2304
SET @s230401_id = UUID(); SET @s230402_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s230401_id, 'Sub Jasmine-1 Rizal Ahmad', 'rizal.ahmad@generallink.my',   @pwd, 'S23040101', '+60114002401', 'INTRODUCER', 'ACTIVE', 'IN-02341', 'A0002-1-03-004-01', @g2_id, @i2304_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2304_id, '/', @s230401_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s230402_id, 'Sub Jasmine-2 Connie Lim',  'connie.lim@generallink.my',    @pwd, 'S23040102', '+60114002402', 'INTRODUCER', 'ACTIVE', 'IN-02342', 'A0002-1-03-004-02', @g2_id, @i2304_id, CONCAT('/', @gl2_id, '/', @tl23_id, '/', @i2304_id, '/', @s230402_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- ---- Introducers under TL 2-4 (Raymond Teh) ----
SET @i2401_id = UUID(); SET @i2402_id = UUID(); SET @i2403_id = UUID(); SET @i2404_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i2401_id, 'Intro Raymond-1 Sunny Tan',  'sunny.tan@generallink.my',      @pwd, 'I240101', '+60113000401', 'INTRODUCER', 'ACTIVE', 'IN-02401', 'A0002-1-04-001', @g2_id, @tl24_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2401_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2402_id, 'Intro Raymond-2 Halimah Bt', 'halimah.bt@generallink.my',     @pwd, 'I240102', '+60113000402', 'INTRODUCER', 'ACTIVE', 'IN-02402', 'A0002-1-04-002', @g2_id, @tl24_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2402_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2403_id, 'Intro Raymond-3 Derek Chong', 'derek.chong@generallink.my',   @pwd, 'I240103', '+60113000403', 'INTRODUCER', 'ACTIVE', 'IN-02403', 'A0002-1-04-003', @g2_id, @tl24_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2403_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i2404_id, 'Intro Raymond-4 Shila Hamid', 'shila.hamid@generallink.my',   @pwd, 'I240104', '+60113000404', 'INTRODUCER', 'ACTIVE', 'IN-02404', 'A0002-1-04-004', @g2_id, @tl24_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2404_id, '/'), 1, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2401
SET @s240101_id = UUID(); SET @s240102_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s240101_id, 'Sub Sunny-1 Benny Hoe',    'benny.hoe@generallink.my',      @pwd, 'S24010101', '+60114003101', 'INTRODUCER', 'ACTIVE', 'IN-02411', 'A0002-1-04-001-01', @g2_id, @i2401_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2401_id, '/', @s240101_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s240102_id, 'Sub Sunny-2 Fatin Najwa',  'fatin.najwa@generallink.my',    @pwd, 'S24010102', '+60114003102', 'INTRODUCER', 'ACTIVE', 'IN-02412', 'A0002-1-04-001-02', @g2_id, @i2401_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2401_id, '/', @s240102_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2402
SET @s240201_id = UUID(); SET @s240202_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s240201_id, 'Sub Halimah-1 Ivan Chew',  'ivan.chew@generallink.my',      @pwd, 'S24020101', '+60114003201', 'INTRODUCER', 'ACTIVE', 'IN-02421', 'A0002-1-04-002-01', @g2_id, @i2402_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2402_id, '/', @s240201_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s240202_id, 'Sub Halimah-2 Zura Bakar', 'zura.bakar@generallink.my',     @pwd, 'S24020102', '+60114003202', 'INTRODUCER', 'ACTIVE', 'IN-02422', 'A0002-1-04-002-02', @g2_id, @i2402_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2402_id, '/', @s240202_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2403
SET @s240301_id = UUID(); SET @s240302_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s240301_id, 'Sub Derek-1 Patrick Goh',  'patrick.goh@generallink.my',    @pwd, 'S24030101', '+60114003301', 'INTRODUCER', 'ACTIVE', 'IN-02431', 'A0002-1-04-003-01', @g2_id, @i2403_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2403_id, '/', @s240301_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s240302_id, 'Sub Derek-2 Mimi Leong',   'mimi.leong@generallink.my',     @pwd, 'S24030102', '+60114003302', 'INTRODUCER', 'ACTIVE', 'IN-02432', 'A0002-1-04-003-02', @g2_id, @i2403_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2403_id, '/', @s240302_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i2404
SET @s240401_id = UUID(); SET @s240402_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s240401_id, 'Sub Shila-1 Norman Idris',  'norman.idris@generallink.my',  @pwd, 'S24040101', '+60114003401', 'INTRODUCER', 'ACTIVE', 'IN-02441', 'A0002-1-04-004-01', @g2_id, @i2404_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2404_id, '/', @s240401_id, '/'), 0, 0, 0, @admin_id, @now, @now),
(@s240402_id, 'Sub Shila-2 Diana Putri',   'diana.putri@generallink.my',   @pwd, 'S24040102', '+60114003402', 'INTRODUCER', 'ACTIVE', 'IN-02442', 'A0002-1-04-004-02', @g2_id, @i2404_id, CONCAT('/', @gl2_id, '/', @tl24_id, '/', @i2404_id, '/', @s240402_id, '/'), 0, 0, 0, @admin_id, @now, @now);

-- ============================================================
-- GROUP 3: DAVID LIM
-- ============================================================
SET @g3_id  = UUID();
SET @gl3_id = UUID();

-- Group record
INSERT INTO `groups` (group_id, group_name, group_code, group_email, separator_char, root_member_suffix, is_active, created_by, created_at, updated_at)
VALUES (@g3_id, 'David Lim', 'D0003', 'davidlim@generallink.my', '-', '0', 1, @admin_id, @now, @now);

-- GL: David Lim
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@gl3_id, 'David Lim', 'davidlim@generallink.my', @pwd, 'A000003', '+60112000002', 'GROUP_LEADER', 'ACTIVE', 'GL-00003', 'D0003-0', @g3_id, NULL, CONCAT('/', @gl3_id, '/'), 3, 0, 0, @admin_id, @now, @now);

-- TL 3-1
SET @tl31_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl31_id, 'David TL Marcus Lee', 'marcus.lee@generallink.my', @pwd, 'T310001', '+60112000201', 'TEAM_LEADER', 'ACTIVE', 'TL-00031', 'D0003-1-01', @g3_id, @gl3_id, CONCAT('/', @gl3_id, '/', @tl31_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- TL 3-2
SET @tl32_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl32_id, 'David TL Aishah Rahim', 'aishah.rahim@generallink.my', @pwd, 'T320001', '+60112000202', 'TEAM_LEADER', 'ACTIVE', 'TL-00032', 'D0003-1-02', @g3_id, @gl3_id, CONCAT('/', @gl3_id, '/', @tl32_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- TL 3-3
SET @tl33_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl33_id, 'David TL Henry Chua', 'henry.chua@generallink.my', @pwd, 'T330001', '+60112000203', 'TEAM_LEADER', 'ACTIVE', 'TL-00033', 'D0003-1-03', @g3_id, @gl3_id, CONCAT('/', @gl3_id, '/', @tl33_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- TL 3-4
SET @tl34_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at)
VALUES (@tl34_id, 'David TL Norhaida Abd', 'norhaida.abd@generallink.my', @pwd, 'T340001', '+60112000204', 'TEAM_LEADER', 'ACTIVE', 'TL-00034', 'D0003-1-04', @g3_id, @gl3_id, CONCAT('/', @gl3_id, '/', @tl34_id, '/'), 2, 0, 0, @admin_id, @now, @now);

-- ---- Introducers under TL 3-1 (Marcus Lee) ----
SET @i3101_id = UUID(); SET @i3102_id = UUID(); SET @i3103_id = UUID(); SET @i3104_id = UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i3101_id, 'Intro Marcus-1 Sheila Goh',  'sheila.goh@generallink.my',     @pwd, 'I310101', '+60113001101', 'INTRODUCER', 'ACTIVE', 'IN-03101', 'D0003-1-01-001', @g3_id, @tl31_id, CONCAT('/', @gl3_id, '/', @tl31_id, '/', @i3101_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i3102_id, 'Intro Marcus-2 Kamal Idris', 'kamal.idris@generallink.my',    @pwd, 'I310102', '+60113001102', 'INTRODUCER', 'ACTIVE', 'IN-03102', 'D0003-1-01-002', @g3_id, @tl31_id, CONCAT('/', @gl3_id, '/', @tl31_id, '/', @i3102_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i3103_id, 'Intro Marcus-3 Jenny Tan',   'jenny.tan@generallink.my',      @pwd, 'I310103', '+60113001103', 'INTRODUCER', 'ACTIVE', 'IN-03103', 'D0003-1-01-003', @g3_id, @tl31_id, CONCAT('/', @gl3_id, '/', @tl31_id, '/', @i3103_id, '/'), 1, 0, 0, @admin_id, @now, @now),
(@i3104_id, 'Intro Marcus-4 Faris Zaki',  'faris.zaki@generallink.my',     @pwd, 'I310104', '+60113001104', 'INTRODUCER', 'ACTIVE', 'IN-03104', 'D0003-1-01-004', @g3_id, @tl31_id, CONCAT('/', @gl3_id, '/', @tl31_id, '/', @i3104_id, '/'), 1, 0, 0, @admin_id, @now, @now);

-- Sub-Introducers under i3101-i3104 (Marcus)
SET @s310101_id=UUID();SET @s310102_id=UUID();SET @s310201_id=UUID();SET @s310202_id=UUID();
SET @s310301_id=UUID();SET @s310302_id=UUID();SET @s310401_id=UUID();SET @s310402_id=UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s310101_id,'Sub Sheila-1 Andy Goh',    'andy.goh@generallink.my',       @pwd,'S31010101','+60114004101','INTRODUCER','ACTIVE','IN-03111','D0003-1-01-001-01',@g3_id,@i3101_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3101_id,'/',@s310101_id,'/'),0,0,0,@admin_id,@now,@now),
(@s310102_id,'Sub Sheila-2 Rina Mat',    'rina.mat@generallink.my',       @pwd,'S31010102','+60114004102','INTRODUCER','ACTIVE','IN-03112','D0003-1-01-001-02',@g3_id,@i3101_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3101_id,'/',@s310102_id,'/'),0,0,0,@admin_id,@now,@now),
(@s310201_id,'Sub Kamal-1 Steven Yap',   'steven.yap@generallink.my',     @pwd,'S31020101','+60114004201','INTRODUCER','ACTIVE','IN-03121','D0003-1-01-002-01',@g3_id,@i3102_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3102_id,'/',@s310201_id,'/'),0,0,0,@admin_id,@now,@now),
(@s310202_id,'Sub Kamal-2 Laila Hasan',  'laila.hasan@generallink.my',    @pwd,'S31020102','+60114004202','INTRODUCER','ACTIVE','IN-03122','D0003-1-01-002-02',@g3_id,@i3102_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3102_id,'/',@s310202_id,'/'),0,0,0,@admin_id,@now,@now),
(@s310301_id,'Sub Jenny-1 Michael Kee',  'michael.kee@generallink.my',    @pwd,'S31030101','+60114004301','INTRODUCER','ACTIVE','IN-03131','D0003-1-01-003-01',@g3_id,@i3103_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3103_id,'/',@s310301_id,'/'),0,0,0,@admin_id,@now,@now),
(@s310302_id,'Sub Jenny-2 Roslina Abd',  'roslina.abd@generallink.my',    @pwd,'S31030102','+60114004302','INTRODUCER','ACTIVE','IN-03132','D0003-1-01-003-02',@g3_id,@i3103_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3103_id,'/',@s310302_id,'/'),0,0,0,@admin_id,@now,@now),
(@s310401_id,'Sub Faris-1 Clement Ng',   'clement.ng@generallink.my',     @pwd,'S31040101','+60114004401','INTRODUCER','ACTIVE','IN-03141','D0003-1-01-004-01',@g3_id,@i3104_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3104_id,'/',@s310401_id,'/'),0,0,0,@admin_id,@now,@now),
(@s310402_id,'Sub Faris-2 Zainab Husin', 'zainab.husin@generallink.my',   @pwd,'S31040102','+60114004402','INTRODUCER','ACTIVE','IN-03142','D0003-1-01-004-02',@g3_id,@i3104_id,CONCAT('/',@gl3_id,'/',@tl31_id,'/',@i3104_id,'/',@s310402_id,'/'),0,0,0,@admin_id,@now,@now);

-- ---- Introducers under TL 3-2 (Aishah Rahim) ----
SET @i3201_id=UUID();SET @i3202_id=UUID();SET @i3203_id=UUID();SET @i3204_id=UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i3201_id,'Intro Aishah-1 Poh Ling',    'poh.ling@generallink.my',       @pwd,'I320101','+60113001201','INTRODUCER','ACTIVE','IN-03201','D0003-1-02-001',@g3_id,@tl32_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3201_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3202_id,'Intro Aishah-2 Harun Said',  'harun.said@generallink.my',     @pwd,'I320102','+60113001202','INTRODUCER','ACTIVE','IN-03202','D0003-1-02-002',@g3_id,@tl32_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3202_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3203_id,'Intro Aishah-3 Tracy Lim',   'tracy.lim@generallink.my',      @pwd,'I320103','+60113001203','INTRODUCER','ACTIVE','IN-03203','D0003-1-02-003',@g3_id,@tl32_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3203_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3204_id,'Intro Aishah-4 Zul Ariffin', 'zul.ariffin@generallink.my',    @pwd,'I320104','+60113001204','INTRODUCER','ACTIVE','IN-03204','D0003-1-02-004',@g3_id,@tl32_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3204_id,'/'),1,0,0,@admin_id,@now,@now);

SET @s320101_id=UUID();SET @s320102_id=UUID();SET @s320201_id=UUID();SET @s320202_id=UUID();
SET @s320301_id=UUID();SET @s320302_id=UUID();SET @s320401_id=UUID();SET @s320402_id=UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s320101_id,'Sub Poh-1 Albert Chin',    'albert.chin@generallink.my',    @pwd,'S32010101','+60114005101','INTRODUCER','ACTIVE','IN-03211','D0003-1-02-001-01',@g3_id,@i3201_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3201_id,'/',@s320101_id,'/'),0,0,0,@admin_id,@now,@now),
(@s320102_id,'Sub Poh-2 Rohani Daud',   'rohani.daud@generallink.my',    @pwd,'S32010102','+60114005102','INTRODUCER','ACTIVE','IN-03212','D0003-1-02-001-02',@g3_id,@i3201_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3201_id,'/',@s320102_id,'/'),0,0,0,@admin_id,@now,@now),
(@s320201_id,'Sub Harun-1 Cecilia Tan',  'cecilia.tan@generallink.my',    @pwd,'S32020101','+60114005201','INTRODUCER','ACTIVE','IN-03221','D0003-1-02-002-01',@g3_id,@i3202_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3202_id,'/',@s320201_id,'/'),0,0,0,@admin_id,@now,@now),
(@s320202_id,'Sub Harun-2 Fuad Mansor',  'fuad.mansor@generallink.my',    @pwd,'S32020102','+60114005202','INTRODUCER','ACTIVE','IN-03222','D0003-1-02-002-02',@g3_id,@i3202_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3202_id,'/',@s320202_id,'/'),0,0,0,@admin_id,@now,@now),
(@s320301_id,'Sub Tracy-1 Bryan Wong',   'bryan.wong@generallink.my',     @pwd,'S32030101','+60114005301','INTRODUCER','ACTIVE','IN-03231','D0003-1-02-003-01',@g3_id,@i3203_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3203_id,'/',@s320301_id,'/'),0,0,0,@admin_id,@now,@now),
(@s320302_id,'Sub Tracy-2 Salwa Aziz',   'salwa.aziz@generallink.my',     @pwd,'S32030102','+60114005302','INTRODUCER','ACTIVE','IN-03232','D0003-1-02-003-02',@g3_id,@i3203_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3203_id,'/',@s320302_id,'/'),0,0,0,@admin_id,@now,@now),
(@s320401_id,'Sub Zul-1 Doris Loh',      'doris.loh@generallink.my',      @pwd,'S32040101','+60114005401','INTRODUCER','ACTIVE','IN-03241','D0003-1-02-004-01',@g3_id,@i3204_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3204_id,'/',@s320401_id,'/'),0,0,0,@admin_id,@now,@now),
(@s320402_id,'Sub Zul-2 Razif Osman',    'razif.osman@generallink.my',    @pwd,'S32040102','+60114005402','INTRODUCER','ACTIVE','IN-03242','D0003-1-02-004-02',@g3_id,@i3204_id,CONCAT('/',@gl3_id,'/',@tl32_id,'/',@i3204_id,'/',@s320402_id,'/'),0,0,0,@admin_id,@now,@now);

-- ---- Introducers under TL 3-3 (Henry Chua) ----
SET @i3301_id=UUID();SET @i3302_id=UUID();SET @i3303_id=UUID();SET @i3304_id=UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i3301_id,'Intro Henry-1 Azlan Shah',   'azlan.shah@generallink.my',     @pwd,'I330101','+60113001301','INTRODUCER','ACTIVE','IN-03301','D0003-1-03-001',@g3_id,@tl33_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3301_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3302_id,'Intro Henry-2 Maggie Koh',   'maggie.koh@generallink.my',     @pwd,'I330102','+60113001302','INTRODUCER','ACTIVE','IN-03302','D0003-1-03-002',@g3_id,@tl33_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3302_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3303_id,'Intro Henry-3 Rashid Bakar', 'rashid.bakar@generallink.my',   @pwd,'I330103','+60113001303','INTRODUCER','ACTIVE','IN-03303','D0003-1-03-003',@g3_id,@tl33_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3303_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3304_id,'Intro Henry-4 Sandra Heng',  'sandra.heng@generallink.my',    @pwd,'I330104','+60113001304','INTRODUCER','ACTIVE','IN-03304','D0003-1-03-004',@g3_id,@tl33_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3304_id,'/'),1,0,0,@admin_id,@now,@now);

SET @s330101_id=UUID();SET @s330102_id=UUID();SET @s330201_id=UUID();SET @s330202_id=UUID();
SET @s330301_id=UUID();SET @s330302_id=UUID();SET @s330401_id=UUID();SET @s330402_id=UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s330101_id,'Sub Azlan-1 Felix Ong',    'felix.ong@generallink.my',      @pwd,'S33010101','+60114006101','INTRODUCER','ACTIVE','IN-03311','D0003-1-03-001-01',@g3_id,@i3301_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3301_id,'/',@s330101_id,'/'),0,0,0,@admin_id,@now,@now),
(@s330102_id,'Sub Azlan-2 Norziana Md',  'norziana.md@generallink.my',    @pwd,'S33010102','+60114006102','INTRODUCER','ACTIVE','IN-03312','D0003-1-03-001-02',@g3_id,@i3301_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3301_id,'/',@s330102_id,'/'),0,0,0,@admin_id,@now,@now),
(@s330201_id,'Sub Maggie-1 Alvin Tay',   'alvin.tay@generallink.my',      @pwd,'S33020101','+60114006201','INTRODUCER','ACTIVE','IN-03321','D0003-1-03-002-01',@g3_id,@i3302_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3302_id,'/',@s330201_id,'/'),0,0,0,@admin_id,@now,@now),
(@s330202_id,'Sub Maggie-2 Asmah Rahim', 'asmah.rahim@generallink.my',    @pwd,'S33020102','+60114006202','INTRODUCER','ACTIVE','IN-03322','D0003-1-03-002-02',@g3_id,@i3302_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3302_id,'/',@s330202_id,'/'),0,0,0,@admin_id,@now,@now),
(@s330301_id,'Sub Rashid-1 Denny Lim',   'denny.lim@generallink.my',      @pwd,'S33030101','+60114006301','INTRODUCER','ACTIVE','IN-03331','D0003-1-03-003-01',@g3_id,@i3303_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3303_id,'/',@s330301_id,'/'),0,0,0,@admin_id,@now,@now),
(@s330302_id,'Sub Rashid-2 Noraini Abd', 'noraini.abd@generallink.my',    @pwd,'S33030102','+60114006302','INTRODUCER','ACTIVE','IN-03332','D0003-1-03-003-02',@g3_id,@i3303_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3303_id,'/',@s330302_id,'/'),0,0,0,@admin_id,@now,@now),
(@s330401_id,'Sub Sandra-1 Edwin Chai',   'edwin.chai@generallink.my',     @pwd,'S33040101','+60114006401','INTRODUCER','ACTIVE','IN-03341','D0003-1-03-004-01',@g3_id,@i3304_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3304_id,'/',@s330401_id,'/'),0,0,0,@admin_id,@now,@now),
(@s330402_id,'Sub Sandra-2 Yusnida Yus', 'yusnida.yus@generallink.my',    @pwd,'S33040102','+60114006402','INTRODUCER','ACTIVE','IN-03342','D0003-1-03-004-02',@g3_id,@i3304_id,CONCAT('/',@gl3_id,'/',@tl33_id,'/',@i3304_id,'/',@s330402_id,'/'),0,0,0,@admin_id,@now,@now);

-- ---- Introducers under TL 3-4 (Norhaida Abd) ----
SET @i3401_id=UUID();SET @i3402_id=UUID();SET @i3403_id=UUID();SET @i3404_id=UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@i3401_id,'Intro Norhaida-1 Gary Tan',  'gary.tan@generallink.my',       @pwd,'I340101','+60113001401','INTRODUCER','ACTIVE','IN-03401','D0003-1-04-001',@g3_id,@tl34_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3401_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3402_id,'Intro Norhaida-2 Salmah Bt', 'salmah.bt@generallink.my',      @pwd,'I340102','+60113001402','INTRODUCER','ACTIVE','IN-03402','D0003-1-04-002',@g3_id,@tl34_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3402_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3403_id,'Intro Norhaida-3 Jimmy Hoo', 'jimmy.hoo@generallink.my',      @pwd,'I340103','+60113001403','INTRODUCER','ACTIVE','IN-03403','D0003-1-04-003',@g3_id,@tl34_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3403_id,'/'),1,0,0,@admin_id,@now,@now),
(@i3404_id,'Intro Norhaida-4 Fauziah Md','fauziah.md@generallink.my',     @pwd,'I340104','+60113001404','INTRODUCER','ACTIVE','IN-03404','D0003-1-04-004',@g3_id,@tl34_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3404_id,'/'),1,0,0,@admin_id,@now,@now);

SET @s340101_id=UUID();SET @s340102_id=UUID();SET @s340201_id=UUID();SET @s340202_id=UUID();
SET @s340301_id=UUID();SET @s340302_id=UUID();SET @s340401_id=UUID();SET @s340402_id=UUID();
INSERT INTO agents (agent_id, full_name, email, password_hash, nric_encrypted, phone, role, status, agent_code, member_code, group_id, parent_id, hierarchy_path, recruitable_tier_depth, commission_balance, is_deleted, created_by, created_at, updated_at) VALUES
(@s340101_id,'Sub Gary-1 Nicholas Yip',  'nicholas.yip@generallink.my',   @pwd,'S34010101','+60114007101','INTRODUCER','ACTIVE','IN-03411','D0003-1-04-001-01',@g3_id,@i3401_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3401_id,'/',@s340101_id,'/'),0,0,0,@admin_id,@now,@now),
(@s340102_id,'Sub Gary-2 Hamidah Saat',  'hamidah.saat@generallink.my',   @pwd,'S34010102','+60114007102','INTRODUCER','ACTIVE','IN-03412','D0003-1-04-001-02',@g3_id,@i3401_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3401_id,'/',@s340102_id,'/'),0,0,0,@admin_id,@now,@now),
(@s340201_id,'Sub Salmah-1 Vincent Ng',  'vincent.ng@generallink.my',     @pwd,'S34020101','+60114007201','INTRODUCER','ACTIVE','IN-03421','D0003-1-04-002-01',@g3_id,@i3402_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3402_id,'/',@s340201_id,'/'),0,0,0,@admin_id,@now,@now),
(@s340202_id,'Sub Salmah-2 Zuraini Mat', 'zuraini.mat@generallink.my',    @pwd,'S34020102','+60114007202','INTRODUCER','ACTIVE','IN-03422','D0003-1-04-002-02',@g3_id,@i3402_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3402_id,'/',@s340202_id,'/'),0,0,0,@admin_id,@now,@now),
(@s340301_id,'Sub Jimmy-1 Alice Teoh',   'alice.teoh@generallink.my',     @pwd,'S34030101','+60114007301','INTRODUCER','ACTIVE','IN-03431','D0003-1-04-003-01',@g3_id,@i3403_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3403_id,'/',@s340301_id,'/'),0,0,0,@admin_id,@now,@now),
(@s340302_id,'Sub Jimmy-2 Shahrul Niza', 'shahrul.niza@generallink.my',   @pwd,'S34030102','+60114007302','INTRODUCER','ACTIVE','IN-03432','D0003-1-04-003-02',@g3_id,@i3403_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3403_id,'/',@s340302_id,'/'),0,0,0,@admin_id,@now,@now),
(@s340401_id,'Sub Fauziah-1 Edmund Lee', 'edmund.lee@generallink.my',     @pwd,'S34040101','+60114007401','INTRODUCER','ACTIVE','IN-03441','D0003-1-04-004-01',@g3_id,@i3404_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3404_id,'/',@s340401_id,'/'),0,0,0,@admin_id,@now,@now),
(@s340402_id,'Sub Fauziah-2 Rosnah Hj',  'rosnah.hj@generallink.my',      @pwd,'S34040102','+60114007402','INTRODUCER','ACTIVE','IN-03442','D0003-1-04-004-02',@g3_id,@i3404_id,CONCAT('/',@gl3_id,'/',@tl34_id,'/',@i3404_id,'/',@s340402_id,'/'),0,0,0,@admin_id,@now,@now);

-- ============================================================
-- Done! Total: 2 GLs + 8 TLs + 32 Introducers + 64 Sub-Introducers = 106 agents
-- ============================================================
