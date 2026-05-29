-- ============================================================
-- COMPREHENSIVE DEMO DATA
-- Covers all users, all transaction types, full workflow flows,
-- and notification/alert logic
-- ============================================================
SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS=0;

-- ============================================================
-- STEP 1: Clean previous demo data (safe — only demo records)
-- ============================================================
DELETE FROM alerts;
DELETE FROM container_history WHERE notes LIKE '%[DEMO]%' OR id > 112;
DELETE FROM disposal_bin WHERE id > 9 OR disposal_reason LIKE '%[DEMO]%';
DELETE FROM chemical_transactions WHERE txn_number LIKE 'TXN-DEMO%' OR txn_number LIKE 'TXN-FULL%';
DELETE FROM borrow_requests WHERE request_number LIKE 'BRW-2024%' OR request_number LIKE 'BRW-2025%' OR request_number LIKE 'BRW-2026%';
DELETE FROM transfers WHERE transfer_number LIKE 'TRF-DEMO%' OR transfer_number LIKE 'TRF-2025%' OR transfer_number LIKE 'TRF-2026%';

-- ============================================================
-- STEP 2: BORROW REQUESTS — Full lifecycle flows
-- All statuses: pending, approved, rejected, fulfilled,
-- partially_returned, returned, overdue, cancelled
-- ============================================================

-- --- Lab 1: ห้องปฏิบัติการเคมีอินทรีย์ ---
-- user1(9) ยืมจาก lab1(5)

-- BR#1: COMPLETED FULL FLOW — ยืม→อนุมัติ→จ่าย→คืน (สมบูรณ์)
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, returned_quantity, return_condition, actual_return_date, created_at) VALUES
(101, 'BRW-20250801-101', 9, 5, 1, 1, 'borrow', 100.0000, 'mL', 'ทดสอบความเป็นกรด-เบสของสารละลาย', 'โปรเจกต์วิจัยเคมีอินทรีย์ A', '2025-08-10', '2025-08-20', 'returned', 5, '2025-08-02 09:00:00', 1, 100.0000, 5, '2025-08-03 10:00:00', 95.0000, 'good', '2025-08-18', '2025-08-01 14:30:00');

-- BR#2: OVERDUE — ยืมแล้วยังไม่คืน เลยกำหนด
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, created_at) VALUES
(102, 'BRW-20250901-102', 9, 5, 4, 2, 'borrow', 200.0000, 'mL', 'วิเคราะห์สารตัวอย่าง กรดซัลฟิวริก', 'งานวิจัย Acid Test', '2025-09-10', '2025-09-25', 'overdue', 5, '2025-09-02 08:30:00', 4, 200.0000, 5, '2025-09-03 09:00:00', '2025-09-01 10:00:00');

-- BR#3: PENDING — รอการอนุมัติ
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, created_at) VALUES
(103, 'BRW-20260210-103', 9, 5, 6, 3, 'borrow', 50.0000, 'mL', 'เตรียมสารละลายบัฟเฟอร์ กรดแอซิติก', 'Lab Routine Q1-2026', '2026-02-20', '2026-03-01', 'pending', '2026-02-10 11:00:00');

-- BR#4: APPROVED (ยังไม่จ่าย) — อนุมัติแล้ว รอจ่ายสาร
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, created_at) VALUES
(104, 'BRW-20260212-104', 9, 5, 13, 7, 'borrow', 500.0000, 'mL', 'ล้างอุปกรณ์แก้ว ด้วยเอทานอล', 'งานทั่วไป', '2026-02-15', '2026-02-28', 'approved', 5, '2026-02-13 09:00:00', '2026-02-12 14:00:00');

-- user2(10) ยืมจาก lab1(5)

-- BR#5: REJECTED — ถูกปฏิเสธ
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, approval_notes, created_at) VALUES
(105, 'BRW-20260201-105', 10, 5, 11, 6, 'borrow', 3000.0000, 'mL', 'ล้างเครื่องมือทั้งหมดในห้องแล็บ', 'ทำความสะอาดประจำเดือน', '2026-02-05', '2026-02-10', 'rejected', 5, '2026-02-02 10:00:00', 'ปริมาณที่ขอมากเกินไป กรุณาส่งคำขอใหม่ไม่เกิน 500 mL', '2026-02-01 09:00:00');

-- BR#6: FULFILLED — จ่ายแล้ว รอใช้/คืน
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, created_at) VALUES
(106, 'BRW-20260205-106', 10, 5, 13, 7, 'borrow', 300.0000, 'mL', 'สกัดสารอินทรีย์จากตัวอย่าง', 'วิจัย Extraction', '2026-02-10', '2026-03-05', 'fulfilled', 5, '2026-02-06 08:45:00', 13, 300.0000, 5, '2026-02-07 10:30:00', '2026-02-05 16:00:00');

-- BR#7: CANCELLED — ยกเลิกโดยผู้ขอ
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, needed_by_date, expected_return_date, status, created_at) VALUES
(107, 'BRW-20260208-107', 10, 5, 18, 11, 'borrow', 100.0000, 'mL', 'ทดสอบการละลาย (ยกเลิกเอง)', '2026-02-15', '2026-02-25', 'cancelled', '2026-02-08 13:00:00');

-- --- Lab 2: ห้องปฏิบัติการเคมีอนินทรีย์ ---
-- user3(11) ยืมจาก lab2(6)

-- BR#8: RETURNED — คืนแล้ว สภาพบางส่วนใช้แล้ว
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, returned_quantity, return_condition, actual_return_date, created_at) VALUES
(108, 'BRW-20250715-108', 11, 6, 8, 4, 'borrow', 150.0000, 'g', 'เตรียม NaOH solution 1M', 'โปรเจกต์ Titration', '2025-07-20', '2025-08-01', 'returned', 6, '2025-07-16 09:30:00', 8, 150.0000, 6, '2025-07-17 11:00:00', 120.0000, 'partially_used', '2025-07-28', '2025-07-15 08:00:00');

-- BR#9: PARTIALLY_RETURNED — คืนบางส่วน
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, returned_quantity, return_condition, actual_return_date, created_at) VALUES
(109, 'BRW-20251001-109', 11, 6, 15, 8, 'borrow', 500.0000, 'mL', 'ล้างเครื่องแก้วด้วย IPA', 'QC Cleaning', '2025-10-05', '2025-10-20', 'partially_returned', 6, '2025-10-02 10:00:00', 15, 500.0000, 6, '2025-10-03 14:00:00', 200.0000, 'good', '2025-10-18', '2025-10-01 09:30:00');

-- user4(12) ยืมจาก lab2(6)

-- BR#10: PENDING
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, needed_by_date, expected_return_date, status, created_at) VALUES
(110, 'BRW-20260215-110', 12, 6, 9, 4, 'borrow', 80.0000, 'g', 'เตรียม buffer NaOH สำหรับ pH meter calibration', '2026-02-20', '2026-03-01', 'pending', '2026-02-15 10:30:00');

-- BR#11: OVERDUE
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, created_at) VALUES
(111, 'BRW-20251201-111', 12, 6, 19, 12, 'borrow', 100.0000, 'g', 'ทดสอบสมบัติ KCl ในสารละลาย', 'วิจัย Electrolyte', '2025-12-10', '2025-12-25', 'overdue', 6, '2025-12-02 08:00:00', 19, 100.0000, 6, '2025-12-03 09:00:00', '2025-12-01 07:30:00');

-- --- Lab 3: ห้องปฏิบัติการชีวเคมี ---
-- user5(13) ยืมจาก lab3(7)

-- BR#12: FULFILLED — จ่ายแล้ว
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, created_at) VALUES
(112, 'BRW-20260101-112', 13, 7, 20, 13, 'borrow', 200.0000, 'g', 'เตรียม saline solution 0.9%', 'โปรเจกต์ Cell Culture', '2026-01-10', '2026-01-25', 'fulfilled', 7, '2026-01-02 09:00:00', 20, 200.0000, 7, '2026-01-03 11:00:00', '2026-01-01 08:00:00');

-- BR#13: RETURNED
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, returned_quantity, return_condition, actual_return_date, created_at) VALUES
(113, 'BRW-20250601-113', 13, 7, 14, 7, 'borrow', 300.0000, 'mL', 'ล้างสไลด์ด้วยเอทานอล', 'งานเตรียมสไลด์', '2025-06-10', '2025-06-25', 'returned', 7, '2025-06-02 10:30:00', 14, 300.0000, 7, '2025-06-03 14:00:00', 280.0000, 'good', '2025-06-22', '2025-06-01 09:00:00');

-- user6(14) ยืมจาก lab3(7)

-- BR#14: PENDING
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, needed_by_date, expected_return_date, status, created_at) VALUES
(114, 'BRW-20260216-114', 14, 7, 17, 10, 'borrow', 100.0000, 'mL', 'ใช้ formaldehyde ในการตรึงเนื้อเยื่อ', '2026-02-25', '2026-03-10', 'pending', '2026-02-16 15:00:00');

-- BR#15: REJECTED
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, status, approved_by, approved_at, approval_notes, created_at) VALUES
(115, 'BRW-20260210-115', 14, 7, 21, 14, 'borrow', 50.0000, 'g', 'ทดสอบ HEPES buffer', 'rejected', 7, '2026-02-11 09:00:00', 'สารนี้ใกล้หมดอายุ กรุณาสั่งซื้อใหม่', '2026-02-10 14:00:00');

-- --- Lab 4: ห้องปฏิบัติการวิเคราะห์ ---
-- lab4(8) — cross-lab borrow
-- BR#16: user1(9) ยืมข้ามแล็บจาก lab4(8)
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, project_name, needed_by_date, expected_return_date, status, approved_by, approved_at, fulfilled_container_id, fulfilled_quantity, fulfilled_by, fulfilled_at, returned_quantity, return_condition, actual_return_date, created_at) VALUES
(116, 'BRW-20251101-116', 9, 8, 22, 15, 'borrow', 5000.0000, 'g', 'ใช้คลอรีนฆ่าเชื้อในห้องแล็บ', 'Safety Disinfection', '2025-11-05', '2025-11-20', 'returned', 8, '2025-11-02 08:00:00', 22, 5000.0000, 8, '2025-11-03 10:00:00', 4500.0000, 'good', '2025-11-18', '2025-11-01 09:00:00');

-- BR#17: PENDING from user3(11) to lab4(8) — cross-lab
INSERT INTO borrow_requests (id, request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, needed_by_date, expected_return_date, status, created_at) VALUES
(117, 'BRW-20260217-117', 11, 8, 23, 16, 'borrow', 3000.0000, 'g', 'วิเคราะห์สารตัวอย่างแอมโมเนีย', '2026-02-25', '2026-03-10', 'pending', '2026-02-17 11:00:00');

-- ============================================================
-- STEP 3: CHEMICAL TRANSACTIONS — ครบทุก txn_type
-- borrow, return, transfer, dispose, adjust, receive, use
-- ============================================================

-- ---- BORROW transactions (linked to borrow_requests) ----

-- user1(9) ยืม HCl จาก container#1 (BR#101 — completed flow)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, created_at) VALUES
(301, 'TXN-FULL-BRW-001', 'container', 1, 1, 'F00000A6900001', 'borrow', 5, 9, 9, 100.0000, 'mL', 750.0000, 'ทดสอบความเป็นกรด-เบส', 'โปรเจกต์วิจัยเคมีอินทรีย์ A', 1, 'completed', '2025-08-20', '2025-08-03 10:00:00');

-- user1(9) ยืม H2SO4 จาก container#4 (BR#102 — overdue)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, created_at) VALUES
(302, 'TXN-FULL-BRW-002', 'container', 4, 2, 'F00000A6900004', 'borrow', 5, 9, 9, 200.0000, 'mL', 720.0000, 'วิเคราะห์สารตัวอย่าง', 'งานวิจัย Acid Test', 1, 'completed', '2025-09-25', '2025-09-03 09:00:00');

-- user2(10) ยืม EtOH จาก container#13 (BR#106 — fulfilled)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, created_at) VALUES
(303, 'TXN-FULL-BRW-003', 'container', 13, 7, 'F00000A6900013', 'borrow', 5, 10, 10, 300.0000, 'mL', 18200.0000, 'สกัดสารอินทรีย์', 'วิจัย Extraction', 1, 'completed', '2026-03-05', '2026-02-07 10:30:00');

-- user3(11) ยืม NaOH จาก container#8 (BR#108 — returned)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, actual_return_date, return_condition, created_at) VALUES
(304, 'TXN-FULL-BRW-004', 'container', 8, 4, 'F00000A6900008', 'borrow', 6, 11, 11, 150.0000, 'g', 600.0000, 'เตรียม NaOH solution 1M', 'โปรเจกต์ Titration', 2, 'completed', '2025-08-01', '2025-07-28', 'partially_used', '2025-07-17 11:00:00');

-- user3(11) ยืม IPA จาก container#15 (BR#109 — partially_returned)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, created_at) VALUES
(305, 'TXN-FULL-BRW-005', 'container', 15, 8, 'F00000A6900015', 'borrow', 6, 11, 11, 500.0000, 'mL', 3300.0000, 'ล้างเครื่องแก้ว IPA', 'QC Cleaning', 2, 'completed', '2025-10-20', '2025-10-03 14:00:00');

-- user4(12) ยืม KCl จาก container#19 (BR#111 — overdue)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, created_at) VALUES
(306, 'TXN-FULL-BRW-006', 'container', 19, 12, 'F00000A6900019', 'borrow', 6, 12, 12, 100.0000, 'g', 350.0000, 'ทดสอบ KCl', 'วิจัย Electrolyte', 2, 'completed', '2025-12-25', '2025-12-03 09:00:00');

-- user5(13) ยืม NaCl จาก container#20 (BR#112 — fulfilled)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, created_at) VALUES
(307, 'TXN-FULL-BRW-007', 'container', 20, 13, 'F00000A6900020', 'borrow', 7, 13, 13, 200.0000, 'g', 780.0000, 'เตรียม saline solution', 'โปรเจกต์ Cell Culture', 3, 'completed', '2026-01-25', '2026-01-03 11:00:00');

-- user5(13) ยืม EtOH จาก container#14 (BR#113 — returned)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, actual_return_date, return_condition, created_at) VALUES
(308, 'TXN-FULL-BRW-008', 'container', 14, 7, 'F00000A6900014', 'borrow', 7, 13, 13, 300.0000, 'mL', 1800.0000, 'ล้างสไลด์', 'งานเตรียมสไลด์', 3, 'completed', '2025-06-25', '2025-06-22', 'good', '2025-06-03 14:00:00');

-- user1(9) ยืมข้ามแล็บ Cl2 จาก container#22 (BR#116 — returned)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, from_building_id, status, expected_return_date, actual_return_date, return_condition, created_at) VALUES
(309, 'TXN-FULL-BRW-009', 'container', 22, 15, 'F00000A6900022', 'borrow', 8, 9, 9, 5000.0000, 'g', 30000.0000, 'ฆ่าเชื้อในห้องแล็บ', 'Safety Disinfection', 4, 'completed', '2025-11-20', '2025-11-18', 'good', '2025-11-03 10:00:00');

-- ---- RETURN transactions ----

-- user1(9) คืน HCl (BR#101)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, return_condition, actual_return_date, parent_txn_id, created_at) VALUES
(310, 'TXN-FULL-RTN-001', 'container', 1, 1, 'F00000A6900001', 'return', 9, 5, 9, 95.0000, 'mL', 845.0000, 'คืนกรดไฮโดรคลอริก สภาพดี', 'completed', 'good', '2025-08-18', 301, '2025-08-18 14:00:00');

-- user3(11) คืน NaOH (BR#108)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, return_condition, actual_return_date, parent_txn_id, created_at) VALUES
(311, 'TXN-FULL-RTN-002', 'container', 8, 4, 'F00000A6900008', 'return', 11, 6, 11, 120.0000, 'g', 720.0000, 'คืน NaOH ใช้ไปบางส่วน', 'completed', 'partially_used', '2025-07-28', 304, '2025-07-28 16:00:00');

-- user3(11) คืนบางส่วน IPA (BR#109 — partially_returned)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, return_condition, actual_return_date, parent_txn_id, created_at) VALUES
(312, 'TXN-FULL-RTN-003', 'container', 15, 8, 'F00000A6900015', 'return', 11, 6, 11, 200.0000, 'mL', 3500.0000, 'คืน IPA บางส่วน ยังใช้อยู่', 'completed', 'good', '2025-10-18', 305, '2025-10-18 10:00:00');

-- user5(13) คืน EtOH (BR#113)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, return_condition, actual_return_date, parent_txn_id, created_at) VALUES
(313, 'TXN-FULL-RTN-004', 'container', 14, 7, 'F00000A6900014', 'return', 13, 7, 13, 280.0000, 'mL', 2080.0000, 'คืนเอทานอล สภาพดี', 'completed', 'good', '2025-06-22', 308, '2025-06-22 15:00:00');

-- user1(9) คืน Cl2 (BR#116)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, return_condition, actual_return_date, parent_txn_id, created_at) VALUES
(314, 'TXN-FULL-RTN-005', 'container', 22, 15, 'F00000A6900022', 'return', 9, 8, 9, 4500.0000, 'g', 34500.0000, 'คืนคลอรีน สภาพดี', 'completed', 'good', '2025-11-18', 309, '2025-11-18 11:00:00');

-- ---- TRANSFER transactions ----

-- lab1(5) โอน acetone container#11 จาก lab1→lab2
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, from_building_id, to_building_id, from_department, to_department, status, created_at) VALUES
(315, 'TXN-FULL-TRF-001', 'container', 11, 6, 'F00000A6900011', 'transfer', 5, 6, 5, 500.0000, 'mL', 2278.0000, 'โอนอะซิโตนไปห้องแล็บเคมีอนินทรีย์', 1, 2, 'Lab 1 - เคมีอินทรีย์', 'Lab 2 - เคมีอนินทรีย์', 'completed', '2025-10-15 09:00:00');

-- lab2(6) โอน IPA container#27 จาก lab2→lab3 (pending approval)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, from_building_id, to_building_id, from_department, to_department, requires_approval, status, created_at) VALUES
(316, 'TXN-FULL-TRF-002', 'container', 27, 8, 'F00000A6900027', 'transfer', 6, 7, 6, 32.0000, 'mL', 0.0000, 'โอน IPA ทั้งขวดไปห้องชีวเคมี', 2, 3, 'Lab 2 - เคมีอนินทรีย์', 'Lab 3 - ชีวเคมี', 1, 'pending', '2026-02-14 13:00:00');

-- lab3(7) โอน Formaldehyde container#17 จาก lab3→lab4
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, from_building_id, to_building_id, from_department, to_department, status, created_at) VALUES
(317, 'TXN-FULL-TRF-003', 'container', 17, 10, 'F00000A6900017', 'transfer', 7, 8, 7, 200.0000, 'mL', 650.0000, 'โอน Formaldehyde ไปห้องวิเคราะห์', 3, 4, 'Lab 3 - ชีวเคมี', 'Lab 4 - วิเคราะห์', 'completed', '2025-12-10 10:00:00');

-- admin(1) โอน H2O2 stock#3 จาก admin→lab1 (stock-based transfer)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, from_building_id, to_building_id, status, created_at) VALUES
(318, 'TXN-FULL-TRF-004', 'stock', 3, 2983, '320F6600000003', 'transfer', 1, 5, 1, 500.0000, 'mL', 2000.0000, 'โอน H2O2 จากคลังกลางไปห้องเคมีอินทรีย์', 11, 1, 'completed', '2026-01-20 14:00:00');

-- lab1(5) โอน container#25 Acetic acid → lab3(7) — rejected
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, from_building_id, to_building_id, requires_approval, status, approved_by, approved_at, approval_notes, created_at) VALUES
(319, 'TXN-FULL-TRF-005', 'container', 25, 3, 'F00000A6900025', 'transfer', 5, 7, 5, 25.0000, 'mL', 0.0000, 'โอนกรดแอซิติกไปห้องชีวเคมี', 1, 3, 1, 'rejected', 1, '2026-02-02 09:00:00', 'สารนี้ใกล้หมดอายุ ไม่ควรโอน', '2026-02-01 16:00:00');

-- ---- DISPOSE transactions ----

-- lab1(5) จำหน่าย container#10 (NH4OH — หมดอายุ)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, initiated_by, quantity, unit, balance_after, purpose, disposal_reason, disposal_method, from_building_id, status, created_at) VALUES
(320, 'TXN-FULL-DSP-001', 'container', 10, 5, 'F00000A6900010', 'dispose', 5, 5, 1200.0000, 'mL', 0.0000, 'จำหน่ายสาร NH4OH หมดอายุ', 'สารหมดอายุการใช้งาน', 'chemical_waste_treatment', 1, 'completed', '2025-09-15 10:00:00');

-- lab2(6) จำหน่าย container#23 (Ammonia — ปนเปื้อน) — pending
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, initiated_by, quantity, unit, balance_after, purpose, disposal_reason, disposal_method, from_building_id, requires_approval, status, created_at) VALUES
(321, 'TXN-FULL-DSP-002', 'container', 23, 16, 'F00000A6900023', 'dispose', 6, 6, 22000.0000, 'g', 0.0000, 'จำหน่ายแอมโมเนียปนเปื้อน', 'สารปนเปื้อน ไม่ปลอดภัย', 'incineration', 2, 1, 'pending', '2026-02-10 11:00:00');

-- lab3(7) จำหน่าย container#30 (Formaldehyde — expired)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, initiated_by, quantity, unit, balance_after, purpose, disposal_reason, disposal_method, from_building_id, status, created_at) VALUES
(322, 'TXN-FULL-DSP-003', 'container', 30, 10, 'F00000A6900030', 'dispose', 7, 7, 420.0000, 'mL', 0.0000, 'จำหน่าย Formaldehyde หมดอายุ', 'หมดอายุการใช้งาน มีนาคม 2025', 'chemical_waste_treatment', 3, 'completed', '2025-11-20 09:00:00');

-- admin(1) จำหน่าย stock#5 (H2O2 — bulk disposal)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, initiated_by, quantity, unit, balance_after, purpose, disposal_reason, disposal_method, from_building_id, status, created_at) VALUES
(323, 'TXN-FULL-DSP-004', 'stock', 5, 2983, '320F6600000005', 'dispose', 1, 1, 2500.0000, 'mL', 0.0000, 'จำหน่าย H2O2 คลังกลาง', 'สารเสื่อมสภาพ สีเปลี่ยน', 'neutralization', 11, 'completed', '2026-01-05 15:00:00');

-- ---- USE transactions ----

-- user1(9) ใช้ HCl container#1
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, status, created_at) VALUES
(324, 'TXN-FULL-USE-001', 'container', 1, 1, 'F00000A6900001', 'use', 9, 9, 9, 50.0000, 'mL', 800.0000, 'ทดสอบปฏิกิริยา acid-base', 'Lab Practice', 'completed', '2026-01-15 09:30:00');

-- user2(10) ใช้ acetone container#12
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, status, created_at) VALUES
(325, 'TXN-FULL-USE-002', 'container', 12, 6, 'F00000A6900012', 'use', 10, 10, 10, 50.0000, 'mL', 270.0000, 'ล้างเครื่องแก้วด้วยอะซิโตน', 'Cleaning', 'completed', '2026-01-20 10:00:00');

-- user3(11) ใช้ NaOH container#9
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, status, created_at) VALUES
(326, 'TXN-FULL-USE-003', 'container', 9, 4, 'F00000A6900009', 'use', 11, 11, 11, 30.0000, 'g', 450.0000, 'เตรียมสารละลาย NaOH 0.1M', 'Titration Lab', 'completed', '2026-02-01 14:00:00');

-- user4(12) ใช้ IPA container#15
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, created_at) VALUES
(327, 'TXN-FULL-USE-004', 'container', 15, 8, 'F00000A6900015', 'use', 12, 12, 12, 100.0000, 'mL', 3700.0000, 'ทำความสะอาดพื้นผิว', 'completed', '2026-02-05 08:30:00');

-- user5(13) ใช้ EtOH container#14
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, project_name, status, created_at) VALUES
(328, 'TXN-FULL-USE-005', 'container', 14, 7, 'F00000A6900014', 'use', 13, 13, 13, 150.0000, 'mL', 1930.0000, 'ฆ่าเชื้อเครื่องมือ', 'Sterilization', 'completed', '2026-02-10 11:00:00');

-- user6(14) ใช้ NaCl container#20
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, created_at) VALUES
(329, 'TXN-FULL-USE-006', 'container', 20, 13, 'F00000A6900020', 'use', 14, 14, 14, 50.0000, 'g', 730.0000, 'เตรียม PBS buffer', 'completed', '2026-02-12 13:00:00');

-- ---- ADJUST transactions ----

-- admin(1) ปรับ container#3 HCl (stock count correction)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, created_at) VALUES
(330, 'TXN-FULL-ADJ-001', 'container', 3, 1, 'F00000A6900003', 'adjust', 1, 1, 1, -200.0000, 'mL', 2100.0000, 'ปรับยอดคงเหลือตามตรวจนับจริง', 'completed', '2026-01-31 16:00:00');

-- lab2(6) ปรับ container#19 KCl
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, created_at) VALUES
(331, 'TXN-FULL-ADJ-002', 'container', 19, 12, 'F00000A6900019', 'adjust', 6, 6, 6, -50.0000, 'g', 300.0000, 'พบการรั่วไหลของภาชนะ ปรับยอด', 'completed', '2026-02-08 09:00:00');

-- ---- RECEIVE transactions ----

-- admin(1) รับ HCl container#2 (new shipment)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, created_at) VALUES
(332, 'TXN-FULL-RCV-001', 'container', 2, 1, 'F00000A6900002', 'receive', 1, 5, 1, 500.0000, 'mL', 620.0000, 'รับสินค้าจาก supplier — HCl lot ใหม่', 'completed', '2025-12-01 09:00:00');

-- lab2(6) รับ NaOH container#9 (restock)
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, created_at) VALUES
(333, 'TXN-FULL-RCV-002', 'container', 9, 4, 'F00000A6900009', 'receive', 6, 6, 6, 200.0000, 'g', 680.0000, 'รับ NaOH จาก supplier เพิ่มเติม', 'completed', '2025-11-15 14:00:00');

-- lab3(7) รับ HEPES container#21
INSERT INTO chemical_transactions (id, txn_number, source_type, source_id, chemical_id, barcode, txn_type, from_user_id, to_user_id, initiated_by, quantity, unit, balance_after, purpose, status, created_at) VALUES
(334, 'TXN-FULL-RCV-003', 'container', 21, 14, 'F00000A6900021', 'receive', 7, 7, 7, 100.0000, 'g', 185.0000, 'รับ HEPES สำหรับ buffer เตรียม cell culture', 'completed', '2026-01-10 10:00:00');

-- ============================================================
-- STEP 4: DISPOSAL BIN entries
-- ============================================================

INSERT INTO disposal_bin (id, source_type, source_id, chemical_id, barcode, chemical_name, remaining_qty, unit, disposed_by, disposal_reason, disposal_method, owner_name, department, building_name, storage_location, status, approved_by, approved_at, completed_at, txn_id, created_at) VALUES
(101, 'container', 10, 5, 'F00000A6900010', 'แอมโมเนียมไฮดรอกไซด์', 1200.0000, 'mL', 5, 'สารหมดอายุการใช้งาน', 'chemical_waste_treatment', 'นางประวิตรา หมายสุข', 'Lab 1', 'อาคารเครื่องมือ 1', 'ชั้น 2 ห้อง 201', 'completed', 1, '2025-09-16 08:00:00', '2025-09-20 10:00:00', 320, '2025-09-15 10:00:00'),
(102, 'container', 23, 16, 'F00000A6900023', 'แอมโมเนีย', 22000.0000, 'g', 6, 'สารปนเปื้อน ไม่ปลอดภัย', 'incineration', 'นางสุรีย์พร อ่อนเจริญ', 'Lab 2', 'อาคารเครื่องมือ 2', 'ห้อง 102', 'pending', NULL, NULL, NULL, 321, '2026-02-10 11:00:00'),
(103, 'container', 30, 10, 'F00000A6900030', 'ฟอร์มาลดีไฮด์', 420.0000, 'mL', 7, 'หมดอายุการใช้งาน มีนาคม 2025', 'chemical_waste_treatment', 'นายสุวิทย์ เพียสังกะ', 'Lab 3', 'อาคารเครื่องมือ 3', 'ชั้น 3 ห้อง 302', 'completed', 1, '2025-11-21 08:00:00', '2025-11-25 14:00:00', 322, '2025-11-20 09:00:00'),
(104, 'stock', 5, 2983, '320F6600000005', 'Hydrogen peroxide', 2500.0000, 'mL', 1, 'สารเสื่อมสภาพ สีเปลี่ยน', 'neutralization', 'นายนพดล พริ้งเพราะ', 'Admin', 'อาคารคลังสารเคมี', 'คลังกลาง', 'completed', 1, '2026-01-06 08:00:00', '2026-01-08 10:00:00', 323, '2026-01-05 15:00:00'),
(105, 'container', 16, 9, 'F00000A6900016', 'ไดคลอโรเมเทน', 680.0000, 'mL', 5, 'สารหมดอายุ มกราคม 2025', 'chemical_waste_treatment', 'นางประวิตรา หมายสุข', 'Lab 1', 'อาคารเครื่องมือ 1', 'ชั้น 1 ตู้ A', 'approved', 1, '2026-02-15 09:00:00', NULL, NULL, '2026-02-14 16:00:00');

-- ============================================================
-- STEP 5: TRANSFERS table entries
-- ============================================================

INSERT INTO transfers (id, transfer_number, container_id, from_user_id, to_user_id, from_lab_id, to_lab_id, quantity_transferred, quantity_unit, transfer_type, reason, status, approved_by, approved_at, completed_at, created_at) VALUES
(101, 'TRF-2025-1015-001', 11, 5, 6, 1, 2, 500.0000, 'mL', 'permanent', 'โอนอะซิโตนไปห้องแล็บเคมีอนินทรีย์', 'completed', 1, '2025-10-15 10:00:00', '2025-10-15 14:00:00', '2025-10-15 09:00:00'),
(102, 'TRF-2026-0214-002', 27, 6, 7, 2, 3, 32.0000, 'mL', 'permanent', 'โอน IPA ทั้งขวดไปห้องชีวเคมี', 'pending', NULL, NULL, NULL, '2026-02-14 13:00:00'),
(103, 'TRF-2025-1210-003', 17, 7, 8, 3, 4, 200.0000, 'mL', 'permanent', 'โอน Formaldehyde ไปห้องวิเคราะห์', 'completed', 1, '2025-12-10 11:00:00', '2025-12-10 15:00:00', '2025-12-10 10:00:00'),
(104, 'TRF-2026-0201-004', 25, 5, 7, 1, 3, 25.0000, 'mL', 'permanent', 'โอนกรดแอซิติกไปห้องชีวเคมี', 'rejected', 1, '2026-02-02 09:00:00', NULL, '2026-02-01 16:00:00'),
(105, 'TRF-2026-0216-005', 4, 5, 8, 1, 4, 100.0000, 'mL', 'temporary', 'ยืม H2SO4 ชั่วคราวไปห้องวิเคราะห์', 'pending', NULL, NULL, NULL, '2026-02-16 14:00:00');

-- ============================================================
-- STEP 6: CONTAINER HISTORY
-- ============================================================

INSERT INTO container_history (container_id, action_type, user_id, from_user_id, to_user_id, quantity_change, quantity_after, notes, created_at) VALUES
-- Container#1 HCl lifecycle
(1, 'borrowed', 9, 5, 9, -100.0000, 750.0000, '[DEMO] user1 ยืม HCl 100 mL', '2025-08-03 10:00:00'),
(1, 'returned', 9, 9, 5, 95.0000, 845.0000, '[DEMO] user1 คืน HCl 95 mL สภาพดี', '2025-08-18 14:00:00'),
(1, 'used', 9, NULL, NULL, -50.0000, 800.0000, '[DEMO] user1 ใช้ HCl 50 mL ทดสอบ acid-base', '2026-01-15 09:30:00'),

-- Container#4 H2SO4
(4, 'borrowed', 9, 5, 9, -200.0000, 720.0000, '[DEMO] user1 ยืม H2SO4 200 mL (overdue)', '2025-09-03 09:00:00'),

-- Container#8 NaOH lifecycle
(8, 'borrowed', 11, 6, 11, -150.0000, 600.0000, '[DEMO] user3 ยืม NaOH 150 g', '2025-07-17 11:00:00'),
(8, 'returned', 11, 11, 6, 120.0000, 720.0000, '[DEMO] user3 คืน NaOH 120 g ใช้บางส่วน', '2025-07-28 16:00:00'),

-- Container#9 NaOH
(9, 'used', 11, NULL, NULL, -30.0000, 450.0000, '[DEMO] user3 ใช้ NaOH 30 g เตรียมสารละลาย', '2026-02-01 14:00:00'),

-- Container#10 NH4OH — disposed
(10, 'disposed', 5, NULL, NULL, -1200.0000, 0.0000, '[DEMO] จำหน่าย NH4OH หมดอายุ', '2025-09-15 10:00:00'),

-- Container#11 Acetone — transferred
(11, 'transferred', 5, 5, 6, -500.0000, 2278.0000, '[DEMO] โอนอะซิโตน Lab1→Lab2 500 mL', '2025-10-15 09:00:00'),

-- Container#12 Acetone
(12, 'used', 10, NULL, NULL, -50.0000, 270.0000, '[DEMO] user2 ใช้ acetone 50 mL ล้างเครื่องแก้ว', '2026-01-20 10:00:00'),

-- Container#13 EtOH
(13, 'borrowed', 10, 5, 10, -300.0000, 18200.0000, '[DEMO] user2 ยืม EtOH 300 mL', '2026-02-07 10:30:00'),

-- Container#14 EtOH lifecycle
(14, 'borrowed', 13, 7, 13, -300.0000, 1800.0000, '[DEMO] user5 ยืม EtOH 300 mL', '2025-06-03 14:00:00'),
(14, 'returned', 13, 13, 7, 280.0000, 2080.0000, '[DEMO] user5 คืน EtOH 280 mL สภาพดี', '2025-06-22 15:00:00'),
(14, 'used', 13, NULL, NULL, -150.0000, 1930.0000, '[DEMO] user5 ใช้ EtOH 150 mL ฆ่าเชื้อ', '2026-02-10 11:00:00'),

-- Container#15 IPA
(15, 'borrowed', 11, 6, 11, -500.0000, 3300.0000, '[DEMO] user3 ยืม IPA 500 mL', '2025-10-03 14:00:00'),
(15, 'returned', 11, 11, 6, 200.0000, 3500.0000, '[DEMO] user3 คืน IPA 200 mL (บางส่วน)', '2025-10-18 10:00:00'),
(15, 'used', 12, NULL, NULL, -100.0000, 3700.0000, '[DEMO] user4 ใช้ IPA 100 mL ทำความสะอาด', '2026-02-05 08:30:00'),

-- Container#17 Formaldehyde — transferred
(17, 'transferred', 7, 7, 8, -200.0000, 650.0000, '[DEMO] โอน Formaldehyde Lab3→Lab4 200 mL', '2025-12-10 10:00:00'),

-- Container#19 KCl
(19, 'borrowed', 12, 6, 12, -100.0000, 350.0000, '[DEMO] user4 ยืม KCl 100 g (overdue)', '2025-12-03 09:00:00'),

-- Container#20 NaCl
(20, 'borrowed', 13, 7, 13, -200.0000, 780.0000, '[DEMO] user5 ยืม NaCl 200 g', '2026-01-03 11:00:00'),
(20, 'used', 14, NULL, NULL, -50.0000, 730.0000, '[DEMO] user6 ใช้ NaCl 50 g PBS buffer', '2026-02-12 13:00:00'),

-- Container#22 Cl2 — cross-lab borrow
(22, 'borrowed', 9, 8, 9, -5000.0000, 30000.0000, '[DEMO] user1 ยืมข้ามแล็บ Cl2 5000 g', '2025-11-03 10:00:00'),
(22, 'returned', 9, 9, 8, 4500.0000, 34500.0000, '[DEMO] user1 คืน Cl2 4500 g สภาพดี', '2025-11-18 11:00:00'),

-- Container#23 Ammonia — pending disposal
(23, 'inspected', 6, NULL, NULL, NULL, NULL, '[DEMO] ตรวจสอบพบสารปนเปื้อน สั่งจำหน่าย', '2026-02-10 10:00:00'),

-- Container#30 Formaldehyde — disposed
(30, 'disposed', 7, NULL, NULL, -420.0000, 0.0000, '[DEMO] จำหน่าย Formaldehyde หมดอายุ', '2025-11-20 09:00:00'),

-- Container#2 HCl — received
(2, 'created', 1, NULL, NULL, 500.0000, 620.0000, '[DEMO] รับ HCl lot ใหม่ 500 mL', '2025-12-01 09:00:00'),

-- Container#3 HCl — adjusted
(3, 'updated', 1, NULL, NULL, -200.0000, 2100.0000, '[DEMO] ปรับยอด HCl ตามตรวจนับ', '2026-01-31 16:00:00');

-- ============================================================
-- STEP 7: ALERTS — Comprehensive notifications for ALL users
-- Covers all alert_type and severity combinations
-- ============================================================

-- =============================================
-- ALERTS FOR admin1 (user_id=1) — System-wide
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, is_read, action_required, created_at) VALUES
('expiry', 'critical', 'สารเคมีหมดอายุ: ไดคลอโรเมเทน', 'ภาชนะ F00000A6900016 ไดคลอโรเมเทน หมดอายุเมื่อ 10 ม.ค. 2025 กรุณาจำหน่ายทิ้ง', 1, 9, 16, 1, 0, 1, '2026-02-15 08:00:00'),
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ: กรดไฮโดรคลอริก', 'ภาชนะ F00000A6900002 กรดไฮโดรคลอริก จะหมดอายุ 1 ก.พ. 2026 (เหลือไม่ถึง 30 วัน)', 1, 1, 2, 1, 0, 1, '2026-01-20 08:00:00'),
('low_stock', 'warning', 'สารเคมีเหลือน้อย: กรดแอซิติก', 'ภาชนะ F00000A6900025 กรดแอซิติก เหลือเพียง 25 mL (ต่ำกว่า 10%)', 1, 3, 25, 1, 0, 1, '2026-02-01 09:00:00'),
('low_stock', 'critical', 'สารเคมีเหลือน้อยมาก: อะซิโตน', 'ภาชนะ F00000A6900026 อะซิโตน เหลือเพียง 18 mL (ต่ำกว่า 5%)', 1, 6, 26, 1, 0, 1, '2026-02-10 07:00:00'),
('compliance', 'info', 'รายงานประจำเดือน มกราคม 2026', 'สรุปรายงานการใช้สารเคมีประจำเดือน มกราคม 2026 พร้อมแล้ว กรุณาตรวจสอบ', 1, NULL, NULL, NULL, 0, 0, '2026-02-01 08:00:00'),
('safety_violation', 'critical', 'พบสารปนเปื้อนในภาชนะ', 'ภาชนะ F00000A6900023 แอมโมเนีย พบการปนเปื้อน กรุณาอนุมัติการจำหน่าย', 1, 16, 23, 2, 0, 1, '2026-02-10 11:30:00'),
('custom', 'info', 'การสำรองข้อมูลสำเร็จ', 'ระบบทำการสำรองข้อมูลอัตโนมัติประจำสัปดาห์เรียบร้อยแล้ว', 1, NULL, NULL, NULL, 1, 0, '2026-02-14 03:00:00');

-- =============================================
-- ALERTS FOR admin2 (user_id=2)
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, is_read, action_required, created_at) VALUES
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ: HEPES', 'ภาชนะ F00000A6900021 HEPES จะหมดอายุ 10 ม.ค. 2026', 2, 14, 21, 3, 0, 1, '2026-01-05 08:00:00'),
('compliance', 'warning', 'ต้องตรวจสอบความปลอดภัยประจำไตรมาส', 'กำหนดตรวจสอบความปลอดภัยห้องปฏิบัติการ Q1/2026 ภายใน 28 ก.พ. 2026', 2, NULL, NULL, NULL, 0, 1, '2026-02-01 09:00:00');

-- =============================================
-- ALERTS FOR lab1 (user_id=5) — Lab Manager Lab 1
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'คำขอยืมสารเคมีใหม่: กรดแอซิติก', 'เอกวัฒน์ ธาราพฤกษพงศ์ ขอยืมกรดแอซิติก 50 mL สำหรับเตรียมบัฟเฟอร์', 5, 3, 6, 1, 103, 0, 1, '2026-02-10 11:05:00'),
('borrow_request', 'info', 'คำขอยืมสารเคมีใหม่: เอทานอล', 'เอกวัฒน์ ธาราพฤกษพงศ์ ขอยืมเอทานอล 500 mL สำหรับล้างอุปกรณ์', 5, 7, 13, 1, 104, 0, 1, '2026-02-12 14:05:00'),
('overdue_borrow', 'critical', 'ยืมเกินกำหนด: กรดซัลฟิวริก', 'เอกวัฒน์ ธาราพฤกษพงศ์ ยืม H2SO4 200 mL เกินกำหนดคืน 25 ก.ย. 2025 แล้ว 146 วัน', 5, 2, 4, 1, 102, 0, 1, '2026-02-17 08:00:00'),
('expiry', 'critical', 'สารเคมีหมดอายุ: ไดคลอโรเมเทน', 'ภาชนะ F00000A6900016 ไดคลอโรเมเทน ในห้องแล็บของคุณหมดอายุแล้ว', 5, 9, 16, 1, NULL, 0, 1, '2026-02-15 08:05:00'),
('low_stock', 'warning', 'สารเคมีเหลือน้อย: อะซิโตน', 'ภาชนะ F00000A6900026 อะซิโตน ในห้องแล็บของคุณเหลือเพียง 18 mL', 5, 6, 26, 1, NULL, 0, 1, '2026-02-10 07:05:00'),
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ: กรดแอซิติก', 'ภาชนะ F00000A6900006 กรดแอซิติก จะหมดอายุ 1 ก.พ. 2026', 5, 3, 6, 1, NULL, 0, 0, '2026-01-15 08:00:00'),
('safety_violation', 'warning', 'ต้องอัปเดต SDS: โทลูอีน', 'เอกสาร SDS ของโทลูอีน ในห้องแล็บของคุณหมดอายุ กรุณาอัปเดต', 5, 11, 18, 1, NULL, 0, 1, '2026-02-12 09:00:00');

-- =============================================
-- ALERTS FOR lab2 (user_id=6) — Lab Manager Lab 2
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'คำขอยืมสารเคมีใหม่: NaOH', 'วันชัย จอกกระโทก ขอยืม NaOH 80 g สำหรับ pH calibration', 6, 4, 9, 2, 110, 0, 1, '2026-02-15 10:35:00'),
('overdue_borrow', 'critical', 'ยืมเกินกำหนด: โพแทสเซียมคลอไรด์', 'วันชัย จอกกระโทก ยืม KCl 100 g เกินกำหนดคืน 25 ธ.ค. 2025 แล้ว 55 วัน', 6, 12, 19, 2, 111, 0, 1, '2026-02-17 08:00:00'),
('borrow_request', 'info', 'คำขอยืมข้ามแล็บ: แอมโมเนีย', 'ทรงสุดา ชาติศรินทร์ (Lab 2) ขอยืมแอมโมเนีย 3000 g จากคุณ', 6, 16, 23, 2, 117, 0, 1, '2026-02-17 11:05:00'),
('low_stock', 'warning', 'สารเคมีเหลือน้อย: IPA', 'ภาชนะ F00000A6900027 IPA เหลือ 32 mL เท่านั้น', 6, 8, 27, 2, NULL, 0, 0, '2026-02-14 08:00:00'),
('safety_violation', 'critical', 'พบสารปนเปื้อน: แอมโมเนีย', 'ภาชนะ F00000A6900023 แอมโมเนีย พบการปนเปื้อน รอการจำหน่าย', 6, 16, 23, 2, NULL, 0, 1, '2026-02-10 11:15:00');

-- =============================================
-- ALERTS FOR lab3 (user_id=7) — Lab Manager Lab 3
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'คำขอยืมสารเคมีใหม่: ฟอร์มาลดีไฮด์', 'ศรัณย์ ดอกไม้กุล ขอยืม formaldehyde 100 mL ใช้ตรึงเนื้อเยื่อ', 7, 10, 17, 3, 114, 0, 1, '2026-02-16 15:05:00'),
('expiry', 'critical', 'สารเคมีหมดอายุ: ฟอร์มาลดีไฮด์', 'ภาชนะ F00000A6900030 ฟอร์มาลดีไฮด์ หมดอายุ มี.ค. 2025 (จำหน่ายแล้ว)', 7, 10, 30, 3, NULL, 1, 0, '2025-11-15 08:00:00'),
('low_stock', 'warning', 'สารเคมีเหลือน้อย: HEPES', 'ภาชนะ F00000A6900021 HEPES เหลือเพียง 85 g', 7, 14, 21, 3, NULL, 0, 1, '2026-02-01 08:00:00'),
('compliance', 'info', 'การโอนสารเสร็จสิ้น', 'การโอน Formaldehyde 200 mL ไปห้องวิเคราะห์เสร็จสิ้นแล้ว', 7, 10, 17, 3, NULL, 1, 0, '2025-12-10 15:00:00');

-- =============================================
-- ALERTS FOR lab4 (user_id=8) — Lab Manager Lab 4
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'คำขอยืมข้ามแล็บ: แอมโมเนีย', 'ทรงสุดา ชาติศรินทร์ ขอยืมแอมโมเนีย 3000 g ข้ามจากห้องแล็บ 2', 8, 16, 23, 4, 117, 0, 1, '2026-02-17 11:10:00'),
('compliance', 'info', 'รับโอน Formaldehyde สำเร็จ', 'รับโอน Formaldehyde 200 mL จากห้องชีวเคมีเรียบร้อยแล้ว', 8, 10, 17, 4, NULL, 1, 0, '2025-12-10 15:05:00'),
('temperature_alert', 'warning', 'อุณหภูมิห้องเก็บสารผิดปกติ', 'ห้องเก็บสารเคมี Lab 4 อุณหภูมิสูงกว่าค่ากำหนด 28°C (กำหนด 25°C)', 8, NULL, NULL, 4, NULL, 0, 1, '2026-02-16 14:30:00');

-- =============================================
-- ALERTS FOR user1 (user_id=9) — User Lab 1
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'คำขอยืมได้รับอนุมัติ: เอทานอล', 'คำขอยืมเอทานอล 500 mL ได้รับการอนุมัติแล้ว รอการจ่ายสาร', 9, 7, 13, 1, 104, 0, 0, '2026-02-13 09:05:00'),
('overdue_borrow', 'warning', 'เตือน: คุณมีสารเคมีค้างคืน', 'คุณยืม H2SO4 200 mL เกินกำหนดคืน 25 ก.ย. 2025 กรุณาคืนโดยเร็ว', 9, 2, 4, 1, 102, 0, 1, '2026-02-17 08:05:00'),
('borrow_request', 'info', 'คำขอยืมส่งแล้ว: กรดแอซิติก', 'คำขอยืมกรดแอซิติก 50 mL ส่งถึงผู้จัดการห้องแล็บแล้ว', 9, 3, 6, 1, 103, 0, 0, '2026-02-10 11:10:00'),
('custom', 'info', 'ยืมคืนสำเร็จ: คลอรีน', 'คุณคืนคลอรีน 4500 g เรียบร้อยแล้ว ขอบคุณที่คืนตรงเวลา', 9, 15, 22, 4, 116, 1, 0, '2025-11-18 11:05:00');

-- =============================================
-- ALERTS FOR user2 (user_id=10) — User Lab 1
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'warning', 'คำขอยืมถูกปฏิเสธ: อะซิโตน', 'คำขอยืมอะซิโตน 3000 mL ถูกปฏิเสธ เหตุผล: ปริมาณมากเกินไป กรุณาส่งใหม่ไม่เกิน 500 mL', 10, 6, 11, 1, 105, 0, 1, '2026-02-02 10:05:00'),
('borrow_request', 'info', 'จ่ายสารเคมีแล้ว: เอทานอล', 'เอทานอล 300 mL ได้รับการจ่ายแล้ว กรุณารับที่ห้องแล็บ', 10, 7, 13, 1, 106, 0, 0, '2026-02-07 10:35:00'),
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ: อะซิโตน', 'ภาชนะ F00000A6900012 อะซิโตน จะหมดอายุ 5 ก.พ. 2025', 10, 6, 12, 1, NULL, 0, 0, '2025-01-10 08:00:00');

-- =============================================
-- ALERTS FOR user3 (user_id=11) — User Lab 2
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('overdue_borrow', 'warning', 'เตือน: คุณมี IPA ยังไม่คืนครบ', 'คุณคืน IPA เพียง 200 mL จาก 500 mL ที่ยืม กรุณาคืนส่วนที่เหลือ', 11, 8, 15, 2, 109, 0, 1, '2026-02-17 08:10:00'),
('borrow_request', 'info', 'คำขอยืมส่งแล้ว: แอมโมเนีย', 'คำขอยืมแอมโมเนีย 3000 g ส่งถึงผู้จัดการห้องแล็บ 4 แล้ว', 11, 16, 23, 2, 117, 0, 0, '2026-02-17 11:15:00'),
('custom', 'info', 'คืนสารสำเร็จ: NaOH', 'คุณคืน NaOH 120 g เรียบร้อยแล้ว', 11, 4, 8, 2, 108, 1, 0, '2025-07-28 16:05:00');

-- =============================================
-- ALERTS FOR user4 (user_id=12) — User Lab 2
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('overdue_borrow', 'critical', 'เตือนด่วน: ยืมเกินกำหนด KCl', 'คุณยืม KCl 100 g เกินกำหนดคืน 25 ธ.ค. 2025 แล้ว 55 วัน กรุณาคืนทันที', 12, 12, 19, 2, 111, 0, 1, '2026-02-17 08:00:00'),
('borrow_request', 'info', 'คำขอยืมส่งแล้ว: NaOH', 'คำขอยืม NaOH 80 g ส่งถึงผู้จัดการห้องแล็บ 2 แล้ว', 12, 4, 9, 2, 110, 0, 0, '2026-02-15 10:35:00');

-- =============================================
-- ALERTS FOR user5 (user_id=13) — User Lab 3
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'จ่ายสารเคมีแล้ว: NaCl', 'NaCl 200 g ได้รับการจ่ายแล้ว สำหรับโปรเจกต์ Cell Culture', 13, 13, 20, 3, 112, 0, 0, '2026-01-03 11:05:00'),
('custom', 'info', 'คืนสารสำเร็จ: เอทานอล', 'คุณคืนเอทานอล 280 mL เรียบร้อยแล้ว สภาพดี', 13, 7, 14, 3, 113, 1, 0, '2025-06-22 15:05:00'),
('low_stock', 'warning', 'สารเหลือน้อย: NaCl', 'ภาชนะ F00000A6900020 NaCl ที่คุณใช้เหลือ 730 g (74.5%)', 13, 13, 20, 3, NULL, 0, 0, '2026-02-12 13:30:00');

-- =============================================
-- ALERTS FOR user6 (user_id=14) — User Lab 3
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, borrow_request_id, is_read, action_required, created_at) VALUES
('borrow_request', 'info', 'คำขอยืมส่งแล้ว: ฟอร์มาลดีไฮด์', 'คำขอยืม formaldehyde 100 mL ส่งถึงผู้จัดการห้องแล็บ 3 แล้ว', 14, 10, 17, 3, 114, 0, 0, '2026-02-16 15:10:00'),
('borrow_request', 'warning', 'คำขอยืมถูกปฏิเสธ: HEPES', 'คำขอยืม HEPES 50 g ถูกปฏิเสธ เหตุผล: สารนี้ใกล้หมดอายุ กรุณาสั่งซื้อใหม่', 14, 14, 21, 3, 115, 0, 1, '2026-02-11 09:05:00');

-- =============================================
-- ALERTS FOR ceo1 (user_id=3) — Executive view
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, is_read, action_required, created_at) VALUES
('compliance', 'warning', 'สรุปรายงานเตือนระบบประจำสัปดาห์', 'สัปดาห์ที่ 7/2026: พบ 3 รายการค้างคืนเกินกำหนด, 2 สารหมดอายุ, 4 สารเหลือน้อย', 3, NULL, NULL, NULL, 0, 0, '2026-02-17 07:00:00'),
('safety_violation', 'critical', 'แจ้งเตือนด่วน: พบสารปนเปื้อน', 'พบแอมโมเนีย ปนเปื้อนใน Lab 2 รอการจำหน่าย กรุณาติดตามสถานะ', 3, 16, 23, 2, 0, 1, '2026-02-10 12:00:00'),
('custom', 'info', 'รายงานสรุป Q4/2025', 'รายงานสรุปการใช้สารเคมี Q4/2025 พร้อมดาวน์โหลดแล้ว', 3, NULL, NULL, NULL, 1, 0, '2026-01-15 08:00:00');

-- =============================================
-- ALERTS FOR lab0/lab5 (user_id=15) — Lab Manager Lab 1 (backup)
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, lab_id, is_read, action_required, created_at) VALUES
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ: โทลูอีน', 'ภาชนะ F00000A6900018 โทลูอีน จะหมดอายุ 15 ม.ค. 2025', 15, 11, 18, 1, 0, 1, '2025-01-01 08:00:00'),
('compliance', 'info', 'ตรวจสอบคลังสารเคมีรายเดือน', 'กำหนดตรวจสอบคลังสารเคมีห้องเคมีอินทรีย์ประจำเดือน ก.พ. 2026', 15, NULL, NULL, 1, 0, 1, '2026-02-01 07:00:00');

-- =============================================
-- ALERTS FOR ceo2/ceo3 (user_id=4,19)
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, is_read, action_required, created_at) VALUES
('compliance', 'info', 'อนุมัติงบประมาณสั่งซื้อสารเคมี Q1/2026', 'ฝ่ายจัดซื้อส่งคำขออนุมัติงบ 150,000 บาท สำหรับสั่งซื้อสารเคมี Q1/2026', 4, 0, 1, '2026-02-05 10:00:00'),
('custom', 'info', 'รายงานความปลอดภัยประจำปี 2025', 'รายงาน Annual Safety Report 2025 พร้อมรีวิวแล้ว', 19, 0, 0, '2026-01-31 09:00:00');

-- =============================================
-- ALERTS FOR misc users (user_id=16-18,20-21, 28-30) — some read, some unread
-- =============================================
INSERT INTO alerts (alert_type, severity, title, message, user_id, is_read, created_at) VALUES
('custom', 'info', 'ยินดีต้อนรับสู่ระบบ ChemTrack', 'คุณได้รับเชิญเข้าใช้ระบบจัดการสารเคมี ChemTrack กรุณาตั้งค่าโปรไฟล์', 16, 1, '2025-06-01 08:00:00'),
('custom', 'info', 'ยินดีต้อนรับสู่ระบบ ChemTrack', 'คุณได้รับเชิญเข้าใช้ระบบจัดการสารเคมี ChemTrack กรุณาตั้งค่าโปรไฟล์', 17, 1, '2025-06-01 08:00:00'),
('custom', 'info', 'ยินดีต้อนรับสู่ระบบ ChemTrack', 'คุณได้รับเชิญเข้าใช้ระบบจัดการสารเคมี ChemTrack กรุณาตั้งค่าโปรไฟล์', 18, 0, '2025-06-01 08:00:00'),
('custom', 'info', 'ยินดีต้อนรับสู่ระบบ ChemTrack', 'คุณได้รับเชิญเข้าใช้ระบบจัดการสารเคมี ChemTrack กรุณาตั้งค่าโปรไฟล์', 20, 0, '2025-06-01 08:00:00'),
('custom', 'info', 'ยินดีต้อนรับสู่ระบบ ChemTrack', 'คุณได้รับเชิญเข้าใช้ระบบจัดการสารเคมี ChemTrack กรุณาตั้งค่าโปรไฟล์', 21, 0, '2025-06-01 08:00:00'),
('compliance', 'info', 'อบรมความปลอดภัย 2026', 'กรุณาลงทะเบียนอบรมความปลอดภัยในการใช้สารเคมีประจำปี 2026 ภายใน 31 มี.ค.', 28, 0, '2026-02-01 09:00:00'),
('compliance', 'info', 'อบรมความปลอดภัย 2026', 'กรุณาลงทะเบียนอบรมความปลอดภัยในการใช้สารเคมีประจำปี 2026 ภายใน 31 มี.ค.', 29, 0, '2026-02-01 09:00:00'),
('compliance', 'info', 'อบรมความปลอดภัย 2026', 'กรุณาลงทะเบียนอบรมความปลอดภัยในการใช้สารเคมีประจำปี 2026 ภายใน 31 มี.ค.', 30, 0, '2026-02-01 09:00:00');

-- ============================================================
-- STEP 8: Re-enable foreign key checks
-- ============================================================
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

-- Summary:
-- Borrow Requests: 17 records (pending:4, approved:1, rejected:2, fulfilled:2, partially_returned:1, returned:4, overdue:2, cancelled:1)
-- Chemical Transactions: 34 records (borrow:9, return:5, transfer:5, dispose:4, use:6, adjust:2, receive:3)
-- Disposal Bin: 5 records (pending:1, approved:1, completed:3)
-- Transfers: 5 records (pending:2, completed:2, rejected:1)
-- Container History: 30 records
-- Alerts: ~55 records across all users covering: expiry, low_stock, overdue_borrow, borrow_request, safety_violation, compliance, temperature_alert, custom
