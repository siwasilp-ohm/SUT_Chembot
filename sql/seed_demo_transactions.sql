-- =====================================================
-- DEMO DATA: Comprehensive Transaction Lifecycle
-- Creates realistic borrow / return / use / transfer / dispose
-- records spread across ALL users for the past 3 months
-- =====================================================
-- Run: C:\xampp\mysql\bin\mysql.exe -u root chem_inventory_db < sql/seed_demo_transactions.sql
-- =====================================================

USE chem_inventory_db;

-- Clear existing demo transactions (keep any user-created ones by checking known demo prefixes)
DELETE FROM disposal_bin WHERE txn_id IS NOT NULL AND txn_id IN (SELECT id FROM chemical_transactions WHERE txn_number LIKE 'TXN-DEMO-%');
DELETE FROM chemical_transactions WHERE txn_number LIKE 'TXN-DEMO-%';

-- =====================================================
-- LEGEND: Users (id → role → lab)
-- 1  admin1   (admin)
-- 2  admin2   (admin)
-- 3  ceo1     (ceo)
-- 5  lab1     (lab_manager, lab1 - เคมีอินทรีย์)
-- 6  lab2     (lab_manager, lab2 - เคมีอนินทรีย์)
-- 7  lab3     (lab_manager, lab3 - ชีวเคมี)
-- 8  lab4     (lab_manager, lab4 - วิเคราะห์)
-- 9  user1    (user, lab1)
-- 10 user2    (user, lab1)
-- 11 user3    (user, lab2)
-- 12 user4    (user, lab2)
-- 13 user5    (user, lab3)
-- 14 user6    (user, lab3)
-- =====================================================
-- Containers (id → chemical → owner):
-- 1  HCl       → user1(9)      qr=CHEM-20240214-001A   850mL
-- 2  HCl       → user1(9)      qr=CHEM-20240214-001B   120mL
-- 3  HCl       → user2(10)     qr=CHEM-20240214-001C   2300mL
-- 4  H2SO4     → user1(9)      qr=CHEM-20240214-002A   920mL
-- 5  H2SO4     → user3(11)     qr=CHEM-20240214-002B   45mL
-- 6  Acetic    → user2(10)     qr=CHEM-20240214-003A   1800mL
-- 7  Acetic    → user2(10)     qr=CHEM-20240214-003B   15mL
-- 8  NaOH      → user3(11)     qr=CHEM-20240214-004A   750g
-- 9  NaOH      → user3(11)     qr=CHEM-20240214-004B   480g
-- 10 NH4OH     → user1(9)      qr=CHEM-20240214-005A   1200mL
-- 11 Acetone   → user1(9)      qr=CHEM-20240214-006A   15000mL
-- 12 Acetone   → user2(10)     qr=CHEM-20240214-006B   320mL
-- 13 EtOH      → user1(9)      qr=CHEM-20240214-007A   18500mL
-- 14 EtOH      → user5(13)     qr=CHEM-20240214-007B   2100mL
-- 15 IPA       → user3(11)     qr=CHEM-20240214-008A   3800mL
-- 16 DCM       → user1(9)      qr=CHEM-20240214-009A   680mL
-- 17 Formal    → user5(13)     qr=CHEM-20240214-010A   850mL
-- 18 Toluene   → user1(9)      qr=CHEM-20240214-011A   920mL
-- 19 KCl       → user3(11)     qr=CHEM-20240214-012A   450g
-- 20 NaCl      → user5(13)     qr=CHEM-20240214-013A   980g
-- 21 HEPES     → user5(13)     qr=CHEM-20240214-014A   85g
-- 24 CS2       → user1(9)      qr=CHEM-20240214-017A   420mL
-- =====================================================

-- =====================================================
-- PHASE 1: USE transactions (ทุก user ใช้สารของตัวเอง)
-- Spread across Nov 2025 - Feb 2026
-- =====================================================

INSERT INTO chemical_transactions
(txn_number, source_type, source_id, chemical_id, barcode, txn_type,
 from_user_id, to_user_id, initiated_by, quantity, unit, balance_after,
 purpose, project_name, status, created_at) VALUES

-- user1 (9) uses own chemicals
('TXN-DEMO-USE-001', 'container', 1, 1, 'CHEM-20240214-001A', 'use', 9, 9, 9, 25.00, 'mL', 975.00, 'เตรียม HCl 1M สำหรับ titration', 'Acid-Base Titration Lab', 'completed', '2025-11-20 09:30:00'),
('TXN-DEMO-USE-002', 'container', 1, 1, 'CHEM-20240214-001A', 'use', 9, 9, 9, 50.00, 'mL', 925.00, 'ล้างเครื่องแก้ว pH electrode', 'Equipment Maintenance', 'completed', '2025-12-02 14:15:00'),
('TXN-DEMO-USE-003', 'container', 4, 2, 'CHEM-20240214-002A', 'use', 9, 9, 9, 30.00, 'mL', 950.00, 'ย่อยตัวอย่าง digestion', 'Heavy Metal Analysis', 'completed', '2025-12-10 10:00:00'),
('TXN-DEMO-USE-004', 'container', 11, 6, 'CHEM-20240214-006A', 'use', 9, 9, 9, 500.00, 'mL', 19500.00, 'ล้างเครื่องแก้ว', 'Lab Routine', 'completed', '2025-12-18 08:45:00'),
('TXN-DEMO-USE-005', 'container', 13, 7, 'CHEM-20240214-007A', 'use', 9, 9, 9, 200.00, 'mL', 19800.00, 'เตรียม 70% EtOH สำหรับฆ่าเชื้อ', 'Sterilization', 'completed', '2026-01-08 09:00:00'),
('TXN-DEMO-USE-006', 'container', 16, 9, 'CHEM-20240214-009A', 'use', 9, 9, 9, 50.00, 'mL', 730.00, 'สกัดสารจากใบไม้ด้วย DCM', 'Natural Product Extraction', 'completed', '2026-01-15 11:30:00'),
('TXN-DEMO-USE-007', 'container', 18, 11, 'CHEM-20240214-011A', 'use', 9, 9, 9, 30.00, 'mL', 950.00, 'เตรียม toluene สำหรับ column chromatography', 'Purification', 'completed', '2026-01-22 14:00:00'),
('TXN-DEMO-USE-008', 'container', 24, 17, 'CHEM-20240214-017A', 'use', 9, 9, 9, 15.00, 'mL', 435.00, 'ทดสอบ viscosity CS2', 'Physical Chemistry Lab', 'completed', '2026-02-05 10:30:00'),
('TXN-DEMO-USE-009', 'container', 10, 5, 'CHEM-20240214-005A', 'use', 9, 9, 9, 100.00, 'mL', 1300.00, 'เตรียมสารละลาย NH4OH 10%', 'Buffer Preparation', 'completed', '2026-02-10 09:15:00'),

-- user2 (10) uses own chemicals
('TXN-DEMO-USE-010', 'container', 6, 3, 'CHEM-20240214-003A', 'use', 10, 10, 10, 100.00, 'mL', 2200.00, 'เตรียม acetic acid buffer pH 4.5', 'Buffer Lab', 'completed', '2025-11-25 10:00:00'),
('TXN-DEMO-USE-011', 'container', 6, 3, 'CHEM-20240214-003A', 'use', 10, 10, 10, 200.00, 'mL', 2000.00, 'ทำความสะอาด column', 'Chromatography Prep', 'completed', '2025-12-08 13:30:00'),
('TXN-DEMO-USE-012', 'container', 12, 6, 'CHEM-20240214-006B', 'use', 10, 10, 10, 80.00, 'mL', 400.00, 'ล้างเครื่องแก้ว', 'Lab Routine', 'completed', '2025-12-20 15:00:00'),
('TXN-DEMO-USE-013', 'container', 3, 1, 'CHEM-20240214-001C', 'use', 10, 10, 10, 50.00, 'mL', 2350.00, 'เตรียม HCl 6M stock', 'Stock Preparation', 'completed', '2026-01-12 09:45:00'),
('TXN-DEMO-USE-014', 'container', 12, 6, 'CHEM-20240214-006B', 'use', 10, 10, 10, 50.00, 'mL', 350.00, 'ล้าง TLC plates', 'TLC Analysis', 'completed', '2026-01-28 11:00:00'),

-- user3 (11) uses own chemicals
('TXN-DEMO-USE-015', 'container', 8, 4, 'CHEM-20240214-004A', 'use', 11, 11, 11, 100.00, 'g', 850.00, 'เตรียม NaOH 5M', 'Analytical Lab', 'completed', '2025-11-22 08:30:00'),
('TXN-DEMO-USE-016', 'container', 15, 8, 'CHEM-20240214-008A', 'use', 11, 11, 11, 100.00, 'mL', 3900.00, 'ทำความสะอาดพื้นผิว', 'Lab Cleaning', 'completed', '2025-12-05 16:00:00'),
('TXN-DEMO-USE-017', 'container', 5, 2, 'CHEM-20240214-002B', 'use', 11, 11, 11, 30.00, 'mL', 75.00, 'ย่อยตัวอย่างดิน', 'Soil Analysis', 'completed', '2025-12-15 10:30:00'),
('TXN-DEMO-USE-018', 'container', 19, 12, 'CHEM-20240214-012A', 'use', 11, 11, 11, 20.00, 'g', 470.00, 'เตรียม KCl standard', 'Calibration', 'completed', '2026-01-10 14:00:00'),
('TXN-DEMO-USE-019', 'container', 9, 4, 'CHEM-20240214-004B', 'use', 11, 11, 11, 10.00, 'g', 490.00, 'ปรับ pH ตัวอย่าง', 'Water Quality Test', 'completed', '2026-02-03 09:00:00'),

-- user4 (12) uses stock items
('TXN-DEMO-USE-020', 'stock', 15, NULL, 'F01121A5900001', 'use', 12, 12, 12, 50.00, 'mL', 2450.00, 'ใช้ EtOH ล้างอุปกรณ์', 'Equipment Cleaning', 'completed', '2025-12-01 10:00:00'),
('TXN-DEMO-USE-021', 'stock', 16, NULL, 'F01121A5900002', 'use', 12, 12, 12, 30.00, 'mL', 2470.00, 'ใช้ IPA ทำความสะอาด', 'Cleaning', 'completed', '2026-01-18 11:00:00'),

-- user5 (13) uses own chemicals
('TXN-DEMO-USE-022', 'container', 14, 7, 'CHEM-20240214-007B', 'use', 13, 13, 13, 100.00, 'mL', 2200.00, 'เตรียม 70% EtOH', 'Sterilization', 'completed', '2025-11-28 09:30:00'),
('TXN-DEMO-USE-023', 'container', 17, 10, 'CHEM-20240214-010A', 'use', 13, 13, 13, 30.00, 'mL', 970.00, 'ตรึงเนื้อเยื่อด้วย formalin', 'Histology', 'completed', '2025-12-12 14:45:00'),
('TXN-DEMO-USE-024', 'container', 20, 13, 'CHEM-20240214-013A', 'use', 13, 13, 13, 50.00, 'g', 950.00, 'เตรียม PBS buffer', 'Cell Culture', 'completed', '2026-01-05 10:00:00'),
('TXN-DEMO-USE-025', 'container', 21, 14, 'CHEM-20240214-014A', 'use', 13, 13, 13, 5.00, 'g', 90.00, 'เตรียม HEPES buffer 50mM', 'Protein Assay', 'completed', '2026-01-20 08:30:00'),
('TXN-DEMO-USE-026', 'container', 17, 10, 'CHEM-20240214-010A', 'use', 13, 13, 13, 20.00, 'mL', 950.00, 'fixation ตัวอย่างพืช', 'Botany Research', 'completed', '2026-02-08 13:00:00'),

-- user6 (14) uses stock items
('TXN-DEMO-USE-027', 'stock', 3, NULL, '320F6600000003', 'use', 14, 14, 14, 50.00, 'mL', 2450.00, 'ใช้ H2O2 ฟอกตัวอย่าง', 'Bleaching', 'completed', '2026-01-25 15:00:00'),

-- lab1 manager (5) uses stock
('TXN-DEMO-USE-028', 'stock', 1, NULL, '320F6600000001', 'use', 5, 5, 5, 5.00, 'kg', 20.00, 'ทดสอบ CO2 ในห้องปฏิบัติการ', 'Safety Test', 'completed', '2026-01-30 09:00:00'),

-- lab2 manager (6) uses container
('TXN-DEMO-USE-029', 'container', 8, 4, 'CHEM-20240214-004A', 'use', 6, 6, 6, 50.00, 'g', 800.00, 'สาธิต NaOH titration ให้นักศึกษา', 'Teaching Demo', 'completed', '2026-02-12 10:30:00');

-- =====================================================
-- PHASE 2: BORROW transactions (ยืมข้าม user/lab)
-- Mix of completed, pending, approved
-- =====================================================

INSERT INTO chemical_transactions
(txn_number, source_type, source_id, chemical_id, barcode, txn_type,
 from_user_id, to_user_id, initiated_by, quantity, unit, balance_after,
 purpose, project_name, requires_approval, status, approved_by, approved_at,
 expected_return_date, created_at) VALUES

-- user2(10) borrows HCl from user1(9) — completed, returned
('TXN-DEMO-BRW-001', 'container', 1, 1, 'CHEM-20240214-001A', 'borrow', 9, 10, 10, 50.00, 'mL', 900.00, 'ยืม HCl สำหรับ titration lab', 'Acid-Base Titration', 1, 'completed', 5, '2025-11-18 11:00:00', '2025-12-01', '2025-11-18 10:00:00'),

-- user4(12) borrows NaOH from user3(11) — completed, returned
('TXN-DEMO-BRW-002', 'container', 8, 4, 'CHEM-20240214-004A', 'borrow', 11, 12, 12, 30.00, 'g', 820.00, 'ยืม NaOH ทำ titration', 'QC Testing', 1, 'completed', 6, '2025-12-03 09:30:00', '2025-12-15', '2025-12-03 09:00:00'),

-- user6(14) borrows EtOH from user5(13) — completed, returned
('TXN-DEMO-BRW-003', 'container', 14, 7, 'CHEM-20240214-007B', 'borrow', 13, 14, 14, 200.00, 'mL', 2000.00, 'ยืม EtOH สำหรับ fixation', 'Tissue Fixation', 1, 'completed', 7, '2025-12-10 14:00:00', '2025-12-20', '2025-12-10 13:30:00'),

-- user1(9) borrows IPA from user3(11) — completed, returned
('TXN-DEMO-BRW-004', 'container', 15, 8, 'CHEM-20240214-008A', 'borrow', 11, 9, 9, 200.00, 'mL', 3700.00, 'ยืม IPA ล้างเครื่องมือ spectro', 'Spectroscopy Cleanup', 1, 'completed', 6, '2025-12-22 10:15:00', '2026-01-05', '2025-12-22 10:00:00'),

-- user3(11) borrows acetone from user1(9) — completed, returned
('TXN-DEMO-BRW-005', 'container', 11, 6, 'CHEM-20240214-006A', 'borrow', 9, 11, 11, 300.00, 'mL', 18700.00, 'ยืมอะซิโตนล้าง column', 'Column Washing', 1, 'completed', 5, '2026-01-05 09:00:00', '2026-01-15', '2026-01-05 08:30:00'),

-- user5(13) borrows DCM from user1(9) — active (not yet returned)
('TXN-DEMO-BRW-006', 'container', 16, 9, 'CHEM-20240214-009A', 'borrow', 9, 13, 13, 100.00, 'mL', 630.00, 'ยืม DCM สกัดโปรตีน', 'Protein Extraction', 1, 'completed', 5, '2026-01-28 11:00:00', '2026-02-15', '2026-01-28 10:30:00'),

-- user2(10) borrows toluene from user1(9) — active
('TXN-DEMO-BRW-007', 'container', 18, 11, 'CHEM-20240214-011A', 'borrow', 9, 10, 10, 50.00, 'mL', 900.00, 'ยืม toluene สำหรับ recrystallization', 'Recrystallization', 1, 'completed', 5, '2026-02-01 14:00:00', '2026-02-20', '2026-02-01 13:30:00'),

-- user4(12) borrows KCl from user3(11) — pending approval
('TXN-DEMO-BRW-008', 'container', 19, 12, 'CHEM-20240214-012A', 'borrow', 11, 12, 12, 15.00, 'g', NULL, 'ยืม KCl เตรียม electrode filling solution', 'Electrode Maintenance', 1, 'pending', NULL, NULL, '2026-02-25', '2026-02-14 09:00:00'),

-- user6(14) borrows HEPES from user5(13) — pending approval
('TXN-DEMO-BRW-009', 'container', 21, 14, 'CHEM-20240214-014A', 'borrow', 13, 14, 14, 3.00, 'g', NULL, 'ยืม HEPES สำหรับ cell culture medium', 'Cell Culture', 1, 'pending', NULL, NULL, '2026-02-28', '2026-02-15 10:00:00'),

-- lab1(5) borrows H2SO4 from user3(11) — approved, in progress
('TXN-DEMO-BRW-010', 'container', 5, 2, 'CHEM-20240214-002B', 'borrow', 11, 5, 5, 10.00, 'mL', 35.00, 'ยืม H2SO4 สาธิตนักศึกษา', 'Student Demo', 1, 'approved', 6, '2026-02-10 12:00:00', '2026-02-20', '2026-02-10 11:30:00'),

-- cross-lab: user1(9,lab1) borrows NaCl from user5(13,lab3) — completed
('TXN-DEMO-BRW-011', 'container', 20, 13, 'CHEM-20240214-013A', 'borrow', 13, 9, 9, 100.00, 'g', 850.00, 'ยืม NaCl เตรียม saline solution', 'Solution Preparation', 1, 'completed', 7, '2026-01-15 10:00:00', '2026-01-30', '2026-01-15 09:30:00'),

-- stock-based borrow: user2(10) borrows from stock owner admin(1)
('TXN-DEMO-BRW-012', 'stock', 4, NULL, '320F6600000004', 'borrow', 1, 10, 10, 500.00, 'mL', 2000.00, 'ยืม H2O2 สำหรับ oxidation experiment', 'Oxidation Study', 1, 'completed', 5, '2026-01-20 08:30:00', '2026-02-05', '2026-01-20 08:00:00');

-- =====================================================
-- PHASE 3: RETURN transactions (คืนสาร — linked to borrows)
-- We insert without parent_txn_id first, then UPDATE to link them
-- =====================================================

INSERT INTO chemical_transactions
(txn_number, source_type, source_id, chemical_id, barcode, txn_type,
 from_user_id, to_user_id, initiated_by, quantity, unit, balance_after,
 purpose, status, actual_return_date, return_condition, created_at) VALUES

('TXN-DEMO-RET-001', 'container', 1, 1, 'CHEM-20240214-001A', 'return', 10, 9, 10, 50.00, 'mL', 950.00, 'คืน HCl ครบจำนวน', 'completed', '2025-11-28', 'good', '2025-11-28 14:00:00'),
('TXN-DEMO-RET-002', 'container', 8, 4, 'CHEM-20240214-004A', 'return', 12, 11, 12, 30.00, 'g', 850.00, 'คืน NaOH สมบูรณ์', 'completed', '2025-12-12', 'good', '2025-12-12 16:00:00'),
('TXN-DEMO-RET-003', 'container', 14, 7, 'CHEM-20240214-007B', 'return', 14, 13, 14, 180.00, 'mL', 2180.00, 'คืน EtOH บางส่วน ใช้ไป 20mL', 'completed', '2025-12-18', 'partially_used', '2025-12-18 11:00:00'),
('TXN-DEMO-RET-004', 'container', 15, 8, 'CHEM-20240214-008A', 'return', 9, 11, 9, 200.00, 'mL', 3900.00, 'คืน IPA ครบจำนวน', 'completed', '2026-01-03', 'good', '2026-01-03 09:30:00'),
('TXN-DEMO-RET-005', 'container', 11, 6, 'CHEM-20240214-006A', 'return', 11, 9, 11, 280.00, 'mL', 18980.00, 'คืนอะซิโตน ใช้ไป 20mL', 'completed', '2026-01-13', 'partially_used', '2026-01-13 15:00:00'),
('TXN-DEMO-RET-006', 'container', 20, 13, 'CHEM-20240214-013A', 'return', 9, 13, 9, 90.00, 'g', 940.00, 'คืน NaCl — ใช้ไป 10g', 'completed', '2026-01-28', 'partially_used', '2026-01-28 10:00:00'),
('TXN-DEMO-RET-007', 'stock', 4, NULL, '320F6600000004', 'return', 10, 1, 10, 450.00, 'mL', 2450.00, 'คืน H2O2 ใช้ไป 50mL', 'completed', '2026-02-03', 'partially_used', '2026-02-03 14:00:00');

-- Link returns to their parent borrow transactions
UPDATE chemical_transactions SET parent_txn_id = (SELECT id FROM (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-BRW-001') t) WHERE txn_number = 'TXN-DEMO-RET-001';
UPDATE chemical_transactions SET parent_txn_id = (SELECT id FROM (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-BRW-002') t) WHERE txn_number = 'TXN-DEMO-RET-002';
UPDATE chemical_transactions SET parent_txn_id = (SELECT id FROM (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-BRW-003') t) WHERE txn_number = 'TXN-DEMO-RET-003';
UPDATE chemical_transactions SET parent_txn_id = (SELECT id FROM (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-BRW-004') t) WHERE txn_number = 'TXN-DEMO-RET-004';
UPDATE chemical_transactions SET parent_txn_id = (SELECT id FROM (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-BRW-005') t) WHERE txn_number = 'TXN-DEMO-RET-005';
UPDATE chemical_transactions SET parent_txn_id = (SELECT id FROM (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-BRW-011') t) WHERE txn_number = 'TXN-DEMO-RET-006';
UPDATE chemical_transactions SET parent_txn_id = (SELECT id FROM (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-BRW-012') t) WHERE txn_number = 'TXN-DEMO-RET-007';

-- =====================================================
-- PHASE 4: TRANSFER transactions (โอนสารระหว่างคน/แลป)
-- =====================================================

INSERT INTO chemical_transactions
(txn_number, source_type, source_id, chemical_id, barcode, txn_type,
 from_user_id, to_user_id, initiated_by, quantity, unit, balance_after,
 purpose, project_name, from_department, to_department, status, approved_by, approved_at, created_at) VALUES

-- lab1 manager(5) transfers HCl from user1(9) to user2(10)
('TXN-DEMO-TRF-001', 'container', 2, 1, 'CHEM-20240214-001B', 'transfer', 9, 10, 5, 50.00, 'mL', 170.00, 'โอน HCl ให้ทีมวิจัย B', 'Team B Research', 'เคมี', 'เคมี', 'completed', 5, '2025-12-05 09:00:00', '2025-12-05 08:30:00'),

-- lab2 manager(6) transfers NaOH from user3(11) to user4(12)
('TXN-DEMO-TRF-002', 'container', 9, 4, 'CHEM-20240214-004B', 'transfer', 11, 12, 6, 100.00, 'g', 390.00, 'โอน NaOH สำหรับ project ใหม่ของ user4', 'New QC Project', 'เคมี', 'เคมี', 'completed', 6, '2025-12-20 10:00:00', '2025-12-20 09:30:00'),

-- Cross-lab transfer: lab3 manager(7) transfers formalin from user5(13) to user1(9) in lab1
('TXN-DEMO-TRF-003', 'container', 17, 10, 'CHEM-20240214-010A', 'transfer', 13, 9, 7, 100.00, 'mL', 850.00, 'โอน formalin ข้ามแลป สำหรับงานวิจัยร่วม', 'Joint Research', 'ชีววิทยา', 'เคมี', 'completed', 7, '2026-01-08 14:30:00', '2026-01-08 14:00:00'),

-- admin(1) transfers stock to user3(11) — distributing supplies
('TXN-DEMO-TRF-004', 'stock', 7, NULL, '320F6600000007', 'transfer', 1, 11, 1, 500.00, 'mL', 2000.00, 'แจกจ่าย H2O2 ให้ lab2', 'Supply Distribution', 'ไอที', 'เคมี', 'completed', 1, '2026-01-12 09:00:00', '2026-01-12 08:30:00'),

-- lab1(5) transfers acetone from user2(10) to lab4 manager(8)
('TXN-DEMO-TRF-005', 'container', 12, 6, 'CHEM-20240214-006B', 'transfer', 10, 8, 5, 100.00, 'mL', 250.00, 'โอนอะซิโตนให้แลปวิเคราะห์', 'Cross-Lab Supply', 'เคมี', 'วิทยาศาสตร์', 'completed', 5, '2026-01-25 11:00:00', '2026-01-25 10:30:00'),

-- Pending transfer: user1(9) wants to transfer CS2 to user3(11)
('TXN-DEMO-TRF-006', 'container', 24, 17, 'CHEM-20240214-017A', 'transfer', 9, 11, 9, 50.00, 'mL', 370.00, 'โอน CS2 ให้ lab2 ทดลอง solvent test', 'Solvent Study', 'เคมี', 'เคมี', 'pending', NULL, NULL, '2026-02-16 10:00:00'),

-- Stock transfer across users
('TXN-DEMO-TRF-007', 'stock', 8, NULL, '320F6600000008', 'transfer', 1, 13, 1, 1000.00, 'mL', 1500.00, 'แจกจ่าย H2O2 ให้ lab3', 'Supply Distribution', 'ไอที', 'ชีววิทยา', 'completed', 1, '2026-02-05 09:00:00', '2026-02-05 08:30:00');

-- =====================================================
-- PHASE 5: DISPOSE transactions (จำหน่ายสาร)
-- =====================================================

INSERT INTO chemical_transactions
(txn_number, source_type, source_id, chemical_id, barcode, txn_type,
 from_user_id, to_user_id, initiated_by, quantity, unit, balance_after,
 purpose, status, disposal_reason, disposal_method, disposal_approved_by, created_at) VALUES

-- user2(10) disposes near-empty acetic acid
('TXN-DEMO-DSP-001', 'container', 7, 3, 'CHEM-20240214-003B', 'dispose', 10, NULL, 10, 15.00, 'mL', 0.00, 'จำหน่ายสารที่เหลือน้อย', 'completed', 'เหลือปริมาณน้อยเกินไป ไม่คุ้มเก็บ', 'neutralization', 5, '2026-01-10 16:00:00'),

-- user3(11) disposes expired H2SO4
('TXN-DEMO-DSP-002', 'container', 5, 2, 'CHEM-20240214-002B', 'dispose', 11, NULL, 11, 45.00, 'mL', 0.00, 'จำหน่ายสารหมดอายุ', 'completed', 'สารเหลือน้อยและใกล้หมดอายุ', 'neutralization', 6, '2026-02-01 14:30:00'),

-- Pending disposal: lab3(7) wants to dispose old formalin stock
('TXN-DEMO-DSP-003', 'stock', 3, NULL, '320F6600000003', 'dispose', 7, NULL, 7, 2450.00, 'mL', 0.00, 'จำหน่าย H2O2 เก่า', 'pending', 'สารเก่า ต้องการแทนที่ด้วย lot ใหม่', 'return_to_vendor', NULL, '2026-02-13 09:00:00');

-- =====================================================
-- PHASE 6: Disposal bin entries (for pending/completed disposals)
-- =====================================================

INSERT INTO disposal_bin
(source_type, source_id, chemical_id, barcode, chemical_name, remaining_qty, unit,
 disposed_by, disposal_reason, disposal_method, owner_name, department,
 status, approved_by, approved_at, completed_at, created_at) VALUES

('container', 7, 3, 'CHEM-20240214-003B', 'กรดแอซิติก', 15.00, 'mL',
 10, 'เหลือปริมาณน้อยเกินไป ไม่คุ้มเก็บ', 'neutralization', 'นักวิจัย สอง', 'เคมี',
 'completed', 5, '2026-01-10 16:30:00', '2026-01-11 10:00:00', '2026-01-10 16:00:00'),

('container', 5, 2, 'CHEM-20240214-002B', 'กรดซัลฟิวริก', 45.00, 'mL',
 11, 'สารเหลือน้อยและใกล้หมดอายุ', 'neutralization', 'นักวิจัย สาม', 'เคมี',
 'completed', 6, '2026-02-01 15:00:00', '2026-02-02 09:00:00', '2026-02-01 14:30:00'),

('stock', 3, NULL, '320F6600000003', 'Hydrogen peroxide', 2450.00, 'mL',
 7, 'สารเก่า ต้องการแทนที่ด้วย lot ใหม่', 'return_to_vendor', 'ผู้จัดการ สาม', 'ชีววิทยา',
 'pending', NULL, NULL, NULL, '2026-02-13 09:00:00');

-- Link disposal_bin to transactions
UPDATE disposal_bin SET txn_id = (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-DSP-001') WHERE barcode = 'CHEM-20240214-003B' AND disposed_by = 10;
UPDATE disposal_bin SET txn_id = (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-DSP-002') WHERE barcode = 'CHEM-20240214-002B' AND disposed_by = 11;
UPDATE disposal_bin SET txn_id = (SELECT id FROM chemical_transactions WHERE txn_number='TXN-DEMO-DSP-003') WHERE barcode = '320F6600000003' AND disposed_by = 7;

-- =====================================================
-- PHASE 7: RECEIVE transactions (นำเข้าสาร — recent receives)
-- =====================================================

INSERT INTO chemical_transactions
(txn_number, source_type, source_id, chemical_id, barcode, txn_type,
 from_user_id, to_user_id, initiated_by, quantity, unit, balance_after,
 purpose, project_name, status, created_at) VALUES

-- user1(9) receives new HCl shipment
('TXN-DEMO-RCV-001', 'container', 1, 1, 'CHEM-20240214-001A', 'receive', 9, 9, 9, 1000.00, 'mL', 1000.00, 'รับเข้า HCl lot ใหม่จาก Merck', 'Stock Replenishment', 'completed', '2025-11-15 09:00:00'),

-- user3(11) receives new NaOH
('TXN-DEMO-RCV-002', 'container', 8, 4, 'CHEM-20240214-004A', 'receive', 11, 11, 11, 1000.00, 'g', 1000.00, 'รับเข้า NaOH จาก Fisher Scientific', 'Stock Replenishment', 'completed', '2025-11-18 10:00:00'),

-- user5(13) receives HEPES
('TXN-DEMO-RCV-003', 'container', 21, 14, 'CHEM-20240214-014A', 'receive', 13, 13, 13, 100.00, 'g', 100.00, 'รับเข้า HEPES จาก Sigma-Aldrich', 'New Purchase', 'completed', '2025-11-20 08:30:00'),

-- lab2(6) receives IPA for the lab
('TXN-DEMO-RCV-004', 'container', 15, 8, 'CHEM-20240214-008A', 'receive', 6, 11, 6, 4000.00, 'mL', 4000.00, 'รับเข้า IPA สำหรับ lab2', 'Lab Supply', 'completed', '2025-12-01 09:00:00'),

-- admin(1) receives bulk EtOH
('TXN-DEMO-RCV-005', 'container', 13, 7, 'CHEM-20240214-007A', 'receive', 1, 9, 1, 20000.00, 'mL', 20000.00, 'รับเข้า EtOH bulk order', 'Central Procurement', 'completed', '2025-11-10 09:00:00'),

-- lab3(7) receives formalin
('TXN-DEMO-RCV-006', 'container', 17, 10, 'CHEM-20240214-010A', 'receive', 7, 13, 7, 1000.00, 'mL', 1000.00, 'รับเข้า formalin สำหรับ lab3', 'Lab Supply', 'completed', '2025-11-12 10:00:00');

-- =====================================================
-- PHASE 8: More ADJUST transactions (ปรับปรุงสต็อก)
-- =====================================================

INSERT INTO chemical_transactions
(txn_number, source_type, source_id, chemical_id, barcode, txn_type,
 from_user_id, to_user_id, initiated_by, quantity, unit, balance_after,
 purpose, status, created_at) VALUES

-- Admin(1) adjusts stock after audit
('TXN-DEMO-ADJ-001', 'container', 3, 1, 'CHEM-20240214-001C', 'adjust', 10, 10, 1, -100.00, 'mL', 2200.00, 'ปรับลดจากการตรวจนับประจำปี', 'completed', '2026-01-02 09:00:00'),

-- Lab manager(5) adjusts acetone after spill
('TXN-DEMO-ADJ-002', 'container', 11, 6, 'CHEM-20240214-006A', 'adjust', 9, 9, 5, -500.00, 'mL', 18500.00, 'ปรับลดจากการหก (spill report #SR-2026-003)', 'completed', '2026-01-18 14:30:00');

-- =====================================================
-- PHASE 9: Additional container history entries
-- =====================================================

INSERT INTO container_history
(container_id, action_type, user_id, quantity_change, quantity_after, notes, created_at) VALUES

-- Receives
(1, 'created', 9, NULL, 1000.00, 'รับเข้าคลัง — HCl จาก Merck', '2025-11-15 09:00:00'),
(8, 'created', 11, NULL, 1000.00, 'รับเข้าคลัง — NaOH จาก Fisher', '2025-11-18 10:00:00'),
(21, 'created', 13, NULL, 100.00, 'รับเข้าคลัง — HEPES จาก Sigma', '2025-11-20 08:30:00'),
(15, 'created', 6, NULL, 4000.00, 'รับเข้าคลัง — IPA สำหรับ lab2', '2025-12-01 09:00:00'),
(13, 'created', 1, NULL, 20000.00, 'รับเข้าคลัง — EtOH bulk', '2025-11-10 09:00:00'),

-- Uses
(1, 'used', 9, -25.00, 975.00, 'เตรียม HCl 1M', '2025-11-20 09:30:00'),
(1, 'used', 9, -50.00, 925.00, 'ล้าง pH electrode', '2025-12-02 14:15:00'),
(4, 'used', 9, -30.00, 950.00, 'digestion', '2025-12-10 10:00:00'),
(6, 'used', 10, -100.00, 2200.00, 'buffer pH 4.5', '2025-11-25 10:00:00'),
(6, 'used', 10, -200.00, 2000.00, 'column cleaning', '2025-12-08 13:30:00'),
(8, 'used', 11, -100.00, 850.00, 'NaOH 5M', '2025-11-22 08:30:00'),
(14, 'used', 13, -100.00, 2200.00, '70% EtOH', '2025-11-28 09:30:00'),
(17, 'used', 13, -30.00, 970.00, 'formalin fixation', '2025-12-12 14:45:00'),

-- Borrows
(1, 'borrowed', 10, -50.00, 900.00, 'ยืมโดย user2', '2025-11-18 10:00:00'),
(8, 'borrowed', 12, -30.00, 820.00, 'ยืมโดย user4', '2025-12-03 09:00:00'),
(14, 'borrowed', 14, -200.00, 2000.00, 'ยืมโดย user6', '2025-12-10 13:30:00'),
(15, 'borrowed', 9, -200.00, 3700.00, 'ยืมโดย user1', '2025-12-22 10:00:00'),

-- Returns
(1, 'returned', 10, 50.00, 950.00, 'คืนจาก user2', '2025-11-28 14:00:00'),
(8, 'returned', 12, 30.00, 850.00, 'คืนจาก user4', '2025-12-12 16:00:00'),
(14, 'returned', 14, 180.00, 2180.00, 'คืนบางส่วนจาก user6', '2025-12-18 11:00:00'),
(15, 'returned', 9, 200.00, 3900.00, 'คืนจาก user1', '2026-01-03 09:30:00'),

-- Transfers
(2, 'transferred', 5, -50.00, 170.00, 'โอนจาก user1 → user2', '2025-12-05 08:30:00'),
(9, 'transferred', 6, -100.00, 390.00, 'โอนจาก user3 → user4', '2025-12-20 09:30:00'),
(12, 'transferred', 5, -100.00, 250.00, 'โอนจาก user2 → lab4', '2026-01-25 10:30:00'),

-- Disposals
(7, 'disposed', 10, -15.00, 0.00, 'จำหน่ายเนื่องจากเหลือน้อย', '2026-01-10 16:00:00'),
(5, 'disposed', 11, -45.00, 0.00, 'จำหน่ายเนื่องจากใกล้หมดอายุ', '2026-02-01 14:30:00'),

-- Adjustments
(3, 'updated', 1, -100.00, 2200.00, 'ปรับสต็อกจากการตรวจนับ', '2026-01-02 09:00:00'),
(11, 'updated', 5, -500.00, 18500.00, 'ปรับลดจากการหก (spill)', '2026-01-18 14:30:00');

-- =====================================================
-- PHASE 10: Update container statuses and quantities
-- to reflect the demo transactions
-- =====================================================

-- Container 7: disposed → empty
UPDATE containers SET status = 'disposed', current_quantity = 0.00 WHERE id = 7;

-- Container 5: disposed → empty
UPDATE containers SET status = 'disposed', current_quantity = 0.00 WHERE id = 5;

-- =====================================================
-- PHASE 11: Additional alerts for richer dashboard
-- =====================================================

INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'คำขอยืมใหม่', 'นักวิจัย สี่ ขอยืม KCl 15 g จากคุณ', 11, 12, 19, 0, 1, '2026-02-14 09:00:00'),
('borrow_request', 'info', 'คำขอยืมใหม่', 'นักวิจัย หก ขอยืม HEPES 3 g จากคุณ', 13, 14, 21, 0, 1, '2026-02-15 10:00:00'),
('overdue_borrow', 'warning', 'ยืมเกินกำหนด', 'นักวิจัย ห้า ยืม DCM 100mL ครบกำหนดวันที่ 15 ก.พ. 2026', 9, 9, 16, 0, 1, '2026-02-16 08:00:00'),
('low_stock', 'warning', 'สต็อกใกล้หมด', 'Acetone (CHEM-20240214-006B) เหลือ 250 mL (25%)', 10, 6, 12, 0, 1, '2026-02-10 09:00:00'),
('expiry', 'critical', 'สารเคมีหมดอายุแล้ว', 'NH4OH (CHEM-20240214-EXP1) หมดอายุวันที่ 20 ก.พ. 2026', 9, 5, 28, 0, 1, '2026-02-18 06:00:00'),
('safety_violation', 'critical', 'แจ้งเตือนความปลอดภัย', 'พบสาร CS2 (ไวไฟสูง) ถูกเก็บนอกตู้ safety — กรุณาตรวจสอบ', 5, 17, 24, 0, 1, '2026-02-17 14:00:00'),
('compliance', 'info', 'รายงานประจำเดือน', 'สรุปการใช้สารเคมีเดือน ม.ค. 2026 พร้อมให้ตรวจสอบ', 3, NULL, NULL, 0, 0, '2026-02-01 08:00:00'),
('low_stock', 'critical', 'สต็อกวิกฤต', 'HCl (CHEM-20240214-001B) เหลือ 120 mL (24%)', 9, 1, 2, 0, 1, '2026-02-12 09:00:00');

-- =====================================================
-- DONE!
-- =====================================================
SELECT '✅ Demo transaction data seeded successfully!' AS status;
SELECT txn_type, COUNT(*) as count FROM chemical_transactions WHERE txn_number LIKE 'TXN-DEMO-%' GROUP BY txn_type ORDER BY count DESC;
SELECT 'Total demo transactions:' as label, COUNT(*) as total FROM chemical_transactions WHERE txn_number LIKE 'TXN-DEMO-%';
