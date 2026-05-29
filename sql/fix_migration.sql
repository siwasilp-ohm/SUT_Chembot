-- Fix: Run remaining parts of migration that failed
USE chem_inventory_db;

-- Seed Buildings
INSERT INTO buildings (id, organization_id, name, name_en, code, shortname) VALUES
(1, 1, 'อาคารเครื่องมือ 1', 'Facility Building 1', 'F01', 'F1'),
(2, 1, 'อาคารเครื่องมือ 2', 'Facility Building 2', 'F02', 'F2'),
(3, 1, 'อาคารเครื่องมือ 3', 'Facility Building 3', 'F03', 'F3'),
(4, 1, 'อาคารเครื่องมือ 4', 'Facility Building 4', 'F04', 'F4'),
(5, 1, 'อาคารเครื่องมือ 5', 'Facility Building 5', 'F05', 'F5'),
(6, 1, 'อาคารเครื่องมือ 6', 'Facility Building 6', 'F06', 'F6'),
(7, 1, 'อาคารเครื่องมือ 7', 'Facility Building 7', 'F07', 'F7'),
(9, 1, 'อาคารเฉลิมพระเกียรติ 72 พรรษา (อาคารเครื่องมือ9)', 'Facility Building 9', 'F09', 'F9'),
(10, 1, 'อาคารเครื่องมือ 10', 'Facility Building 10', 'F10', 'F10'),
(11, 1, 'อาคารคลังสารเคมี', 'Chemical Storage', 'CHEM-STORE', 'คลังสารเคมี'),
(12, 1, 'อาคารเครื่องมือ 6/1', 'Facility Building 6/1', 'F06-1', 'F6/1'),
(14, 1, 'อาคารสัตว์ทดลอง', '320F', '320F', 'สัตว์ทดลอง'),
(15, 1, 'โรงประลอง', '1/2101', 'WORKSHOP', 'โรงประลอง')
ON DUPLICATE KEY UPDATE name=VALUES(name), name_en=VALUES(name_en), shortname=VALUES(shortname);

-- Update Organization
UPDATE organizations SET
    name = 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี',
    description = 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี มหาวิทยาลัยเทคโนโลยีสุรนารี'
WHERE id = 1;

-- Departments
INSERT INTO departments (id, organization_id, parent_id, name, level, level_label) VALUES
(1, 1, NULL, 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี', 1, 'ศูนย์')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO departments (id, organization_id, parent_id, name, level, level_label) VALUES
(10, 1, 1, 'ฝ่ายบริการสัตว์ทดลองเพื่องานทางวิทยาศาสตร์', 2, 'ฝ่าย'),
(11, 1, 1, 'ฝ่ายบริหารงานทั่วไป', 2, 'ฝ่าย'),
(12, 1, 1, 'ฝ่ายพัฒนาและปรับปรุงห้องปฏิบัติการ', 2, 'ฝ่าย'),
(13, 1, 1, 'ฝ่ายวิเคราะห์ด้วยเครื่องมือ', 2, 'ฝ่าย'),
(14, 1, 1, 'ฝ่ายสนับสนุนโครงการวิจัยฯ', 2, 'ฝ่าย'),
(15, 1, 1, 'ฝ่ายห้องปฏิบัติการเทคโนโลยีการเกษตร', 2, 'ฝ่าย'),
(16, 1, 1, 'ฝ่ายห้องปฏิบัติการวิทยาศาสตร์และเทคโนโลยีสังคม', 2, 'ฝ่าย'),
(17, 1, 1, 'ฝ่ายห้องปฏิบัติการวิทยาศาสตร์สุขภาพ', 2, 'ฝ่าย'),
(18, 1, 1, 'ฝ่ายห้องปฏิบัติการวิศวกรรม', 2, 'ฝ่าย')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO departments (id, organization_id, parent_id, name, level, level_label) VALUES
(101, 1, 10, 'งานผลิตและเลี้ยงสัตว์เพื่องานทางวิทยาศาสตร์', 3, 'งาน'),
(102, 1, 10, 'งานควบคุมคุณภาพ สุขภาพสัตว์และตรวจวิเคราะห์', 3, 'งาน'),
(103, 1, 11, 'งานจัดซื้อวัสดุ อุปกรณ์ ครุภัณฑ์', 3, 'งาน'),
(104, 1, 12, 'งานพัฒนาเครื่องมือและสิ่งประดิษฐ์', 3, 'งาน'),
(105, 1, 12, 'งานซ่อมบำรุงเครื่องมือ', 3, 'งาน'),
(106, 1, 13, 'งานวิเคราะห์ทางเคมีและชีวเคมี', 3, 'งาน'),
(107, 1, 13, 'งานวิเคราะห์ด้วยกล้องจุลทรรศน์', 3, 'งาน'),
(108, 1, 13, 'งานวิเคราะห์ทางจุลชีววิทยา', 3, 'งาน'),
(109, 1, 13, 'งานทดสอบทางกายภาพ', 3, 'งาน'),
(110, 1, 13, 'งานวิเคราะห์น้ำ', 3, 'งาน'),
(111, 1, 14, 'งานความปลอดภัยและสิ่งแวดล้อมห้องปฏิบัติการ', 3, 'งาน'),
(112, 1, 15, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีอาหาร', 3, 'งาน'),
(113, 1, 15, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีการผลิตสัตว์', 3, 'งาน'),
(114, 1, 15, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีการผลิตพืช', 3, 'งาน'),
(115, 1, 15, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีชีวภาพ', 3, 'งาน'),
(116, 1, 16, 'งานกลุ่มห้องปฏิบัติการเคมี', 3, 'งาน'),
(117, 1, 16, 'งานกลุ่มห้องปฏิบัติการชีววิทยา', 3, 'งาน'),
(118, 1, 16, 'งานกลุ่มห้องปฏิบัติการชีวเคมี', 3, 'งาน'),
(119, 1, 17, 'งานห้องปฏิบัติการทันตกรรมและเวชนิทัศน์', 3, 'งาน'),
(120, 1, 17, 'งานกลุ่มห้องปฎิบัติการอนามัยสิ่งแวดล้อม', 3, 'งาน'),
(121, 1, 17, 'งานกลุ่มห้องปฏิบัติการทางการแพทย์ 1', 3, 'งาน'),
(122, 1, 17, 'งานกลุ่มห้องปฏิบัติการทางการแพทย์ 2', 3, 'งาน'),
(123, 1, 17, 'งานสรีรวิทยาทางการแพทย์', 3, 'งาน'),
(124, 1, 17, 'งานกลุ่มห้องปฏิบัติการชีวอนามัยและความปลอดภัย', 3, 'งาน'),
(125, 1, 18, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมเคมีฯ', 3, 'งาน'),
(126, 1, 18, 'งานกลุ่มวิศวกรรมโลหการและกระบวนการผลิต', 3, 'งาน'),
(127, 1, 18, 'งานกลุ่มห้องปฏิบัติการเครื่องจักรกลพื้นฐานฯ', 3, 'งาน'),
(128, 1, 18, 'งานกลุ่มห้องปฏิบัติการโยธาและขนส่ง', 3, 'งาน'),
(129, 1, 18, 'งานกลุ่มห้องปฏิบัติการวิศกรรมพอลิเมอร์', 3, 'งาน'),
(130, 1, 18, 'งานกลุ่มห้องปฏิบัติการเทคโนโลยีธรณีและเซรามิก', 3, 'งาน'),
(131, 1, 18, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมอุตสาหการ', 3, 'งาน'),
(132, 1, 18, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมเครื่องกลฯ', 3, 'งาน'),
(133, 1, 18, 'งานกลุ่มห้องปฏิบัติการวิศวกรรมไฟฟ้า', 3, 'งาน')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Funding Sources
INSERT INTO funding_sources (id, parent_id, name, code) VALUES
(1, NULL, 'งบประมาณแผ่นดิน', 'GOV'),
(2, NULL, 'งบรายได้', 'REV'),
(3, NULL, 'งบวิจัย', 'RES'),
(4, NULL, 'งบบริจาค', 'DON')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Views
CREATE OR REPLACE VIEW v_container_full AS
SELECT
    cn.bottle_code AS bottle_code,
    cn.qr_code AS qr_code,
    ch.name AS chemical_name,
    COALESCE(ch.cas_number, ch.catalogue_number, '') AS cas_catalogue,
    cn.grade AS grade,
    CONCAT(cn.initial_quantity, ' ', cn.quantity_unit) AS pack_size,
    cn.initial_quantity AS pack_size_num,
    CONCAT(cn.current_quantity, ' ', cn.quantity_unit) AS remaining,
    cn.current_quantity AS remaining_num,
    cn.remaining_percentage AS remaining_pct,
    cn.quantity_unit AS unit,
    COALESCE(cn.added_by_name, CONCAT(u.first_name, ' ', u.last_name)) AS added_by,
    COALESCE(cn.added_at_original, cn.created_at) AS added_at,
    b.name AS building_name,
    rm.name AS room_name,
    rm.code AS room_code,
    cab.name AS cabinet_name,
    sh.name AS shelf_name,
    sl.name AS slot_name,
    mfr.name AS manufacturer_name,
    sup.name AS supplier_name,
    cn.invoice_number,
    cn.cost AS price,
    cn.tax_rate,
    cn.total_cost,
    cn.notes AS remarks,
    fs.name AS funding_source,
    d1.name AS center,
    d2.name AS division,
    d3.name AS unit_name,
    cn.project_name,
    cn.inventory_name,
    cn.inventory_full_path,
    ch.un_class,
    ch.ghs_hazard_text,
    cn.expiry_date,
    cn.status,
    cn.id AS container_id,
    ch.id AS chemical_id,
    cn.lab_id,
    cn.owner_id
FROM containers cn
LEFT JOIN chemicals ch ON cn.chemical_id = ch.id
LEFT JOIN users u ON cn.created_by = u.id
LEFT JOIN buildings b ON cn.building_id = b.id
LEFT JOIN rooms rm ON cn.room_id = rm.id
LEFT JOIN cabinets cab ON cn.cabinet_id = cab.id
LEFT JOIN shelves sh ON cn.shelf_id = sh.id
LEFT JOIN slots sl ON cn.slot_id = sl.id
LEFT JOIN manufacturers mfr ON cn.manufacturer_id = mfr.id
LEFT JOIN suppliers sup ON cn.supplier_id_new = sup.id
LEFT JOIN funding_sources fs ON cn.funding_source_id = fs.id
LEFT JOIN departments d3 ON cn.department_id = d3.id
LEFT JOIN departments d2 ON d3.parent_id = d2.id
LEFT JOIN departments d1 ON d2.parent_id = d1.id;

-- Import staging and field mapping tables
CREATE TABLE IF NOT EXISTS import_staging (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_batch VARCHAR(50) NOT NULL,
    row_number INT,
    status ENUM('pending','processed','error','skipped') DEFAULT 'pending',
    error_message TEXT,
    bottle_code VARCHAR(100),
    chemical_name VARCHAR(500),
    cas_catalogue VARCHAR(100),
    grade VARCHAR(255),
    pack_size VARCHAR(100),
    remaining_qty VARCHAR(100),
    added_by_name VARCHAR(255),
    added_at VARCHAR(100),
    building_name VARCHAR(255),
    room_name VARCHAR(500),
    room_code VARCHAR(50),
    cabinet_name VARCHAR(255),
    shelf_name VARCHAR(255),
    slot_name VARCHAR(255),
    manufacturer_name VARCHAR(255),
    supplier_name VARCHAR(500),
    invoice_no VARCHAR(100),
    price VARCHAR(50),
    tax VARCHAR(50),
    remarks TEXT,
    funding_source VARCHAR(255),
    funding_sub VARCHAR(255),
    faculty_center VARCHAR(500),
    division_dept VARCHAR(500),
    section_unit VARCHAR(500),
    project VARCHAR(500),
    inventory_name VARCHAR(500),
    inventory_full_path TEXT,
    un_class VARCHAR(500),
    ghs_class VARCHAR(500),
    expiry_date VARCHAR(50),
    resolved_chemical_id INT,
    resolved_container_id INT,
    resolved_building_id INT,
    resolved_room_id INT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_field_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    csv_column_name VARCHAR(255) NOT NULL,
    target_table VARCHAR(100) NOT NULL,
    target_column VARCHAR(100) NOT NULL,
    data_type VARCHAR(50) DEFAULT 'VARCHAR',
    transform_rule TEXT,
    required BOOLEAN DEFAULT FALSE,
    notes TEXT,
    UNIQUE KEY uk_csv_col (csv_column_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add lab0 user - นายนพดล พริ้งเพราะ as Lab Manager
INSERT INTO users (id, organization_id, role_id, lab_id, manager_id, username, email, password_hash, first_name, last_name, full_name_th, phone, department, position, department_id, theme_preference, language, email_verified, is_active) VALUES
(15, 1, 3, 1, NULL, 'lab0', 'noppadon@sut.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นพดล', 'พริ้งเพราะ', 'นายนพดล พริ้งเพราะ', '089-999-9999', 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี', 'ผู้จัดการห้องปฏิบัติการ', 116, 'light', 'th', 1, 1)
ON DUPLICATE KEY UPDATE username='lab0', first_name='นพดล', last_name='พริ้งเพราะ', full_name_th='นายนพดล พริ้งเพราะ', role_id=3, department_id=116;

-- Create notification settings for lab0
INSERT INTO notification_settings (user_id) VALUES (15)
ON DUPLICATE KEY UPDATE user_id=15;

-- Add some realistic SUT chemical data for lab0's lab
-- Add chemicals from real data (file 2)
INSERT INTO chemicals (id, cas_number, name, physical_state, category_id, created_by) VALUES
(18, '124-38-9', 'Carbon dioxide', 'gas', 5, 15),
(19, NULL, 'Ribonuclease Inhibitor, Human Placenta', 'liquid', 6, 15),
(20, NULL, 'AccuPower PCR PreMix (20 µL) / Bioneer', 'solid', 6, 15),
(21, NULL, 'GF-1 Nucleic Acid Extraction Kit (Tissue)', 'solid', 6, 15),
(22, NULL, 'Taq DNA Polymerase', 'liquid', 6, 15),
(23, NULL, 'TAE Buffer 50X', 'liquid', 6, 15),
(24, NULL, 'ViSafe Red (GelRed alternative)', 'liquid', 7, 15),
(25, NULL, '100 bp DNA Ladder', 'liquid', 6, 15),
(26, '9075-67-6', 'HinfI restriction enzyme', 'liquid', 6, 15)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Add manufacturers from real data
INSERT INTO manufacturers (name) VALUES
('Air Liquid'),('Vivantis'),('Bioneer'),('Fermentas'),('Bio Basic'),('GeneDireX'),('NEB')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Add containers (bottles) from real data for lab0
INSERT INTO containers (id, qr_code, bottle_code, chemical_id, owner_id, lab_id, container_type, container_material, container_capacity, capacity_unit, initial_quantity, current_quantity, quantity_unit, grade, received_date, status, cost, created_by, added_by_name, building_id, department_id, inventory_name, manufacturer_id) VALUES
(31, 'SUT-BTL-001', 'BTL-0001', 18, 15, 1, 'cylinder', 'metal', 25000, 'g', 25000.00, 25000.00, 'g', 'Industrial Grade', '2025-03-11', 'active', 1705.60, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='Air Liquid' LIMIT 1)),
(32, 'SUT-BTL-002', 'BTL-0002', 19, 15, 1, 'vial', 'plastic', 2500, 'Units', 2500.00, 2500.00, 'Units', 'Molecular Biology Grade', '2025-03-11', 'active', 3738.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='Vivantis' LIMIT 1)),
(33, 'SUT-BTL-003', 'BTL-0003', 20, 15, 1, 'box', 'plastic', 96, 'Units', 96.00, 96.00, 'Units', NULL, '2025-03-11', 'active', 4000.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='Bioneer' LIMIT 1)),
(34, 'SUT-BTL-004', 'BTL-0004', 21, 15, 1, 'kit', 'plastic', 100, 'Units', 100.00, 100.00, 'Units', NULL, '2025-03-11', 'active', 5080.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='Vivantis' LIMIT 1)),
(35, 'SUT-BTL-005', 'BTL-0005', 22, 15, 1, 'vial', 'plastic', 500, 'Units', 500.00, 500.00, 'Units', 'Molecular Biology Grade', '2025-03-11', 'active', 1550.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='Vivantis' LIMIT 1)),
(36, 'SUT-BTL-006', 'BTL-0006', 23, 15, 1, 'bottle', 'plastic', 1000, 'mL', 1000.00, 800.00, 'mL', 'Molecular Biology Grade', '2025-03-11', 'active', 800.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='Bio Basic' LIMIT 1)),
(37, 'SUT-BTL-007', 'BTL-0007', 24, 15, 1, 'vial', 'plastic', 1000, 'µL', 1000.00, 600.00, 'µL', NULL, '2025-03-11', 'active', 1500.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='GeneDireX' LIMIT 1)),
(38, 'SUT-BTL-008', 'BTL-0008', 25, 15, 1, 'vial', 'plastic', 500, 'µL', 500.00, 350.00, 'µL', NULL, '2025-03-11', 'active', 1200.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='GeneDireX' LIMIT 1)),
(39, 'SUT-BTL-009', 'BTL-0009', 26, 15, 1, 'vial', 'plastic', 10000, 'Units', 10000.00, 8000.00, 'Units', 'Molecular Biology Grade', '2025-03-11', 'active', 2200.00, 15, 'นายนพดล พริ้งเพราะ', 7, 116, 'คลังสารเคมี งาน กลุ่มห้องปฏิบัติการเคมี', (SELECT id FROM manufacturers WHERE name='NEB' LIMIT 1))
ON DUPLICATE KEY UPDATE bottle_code=VALUES(bottle_code);

-- Add container history for lab0
INSERT INTO container_history (container_id, action_type, user_id, quantity_change, quantity_after, notes, created_at) VALUES
(31, 'created', 15, NULL, 25000.00, 'รับเข้าคลัง - Carbon dioxide จาก Air Liquid', '2025-03-11 00:37:00'),
(32, 'created', 15, NULL, 2500.00, 'รับเข้าคลัง - Ribonuclease Inhibitor', '2025-03-11 00:37:00'),
(33, 'created', 15, NULL, 96.00, 'รับเข้าคลัง - AccuPower PCR PreMix', '2025-03-11 00:37:00'),
(34, 'created', 15, NULL, 100.00, 'รับเข้าคลัง - GF-1 Nucleic Acid Extraction Kit', '2025-03-11 00:37:00'),
(35, 'created', 15, NULL, 500.00, 'รับเข้าคลัง - Taq DNA Polymerase', '2025-03-11 00:37:00'),
(36, 'created', 15, NULL, 1000.00, 'รับเข้าคลัง - TAE Buffer 50X', '2025-03-11 00:37:00'),
(36, 'used', 15, -200.00, 800.00, 'ใช้สำหรับ Gel Electrophoresis', '2025-04-01 10:00:00'),
(37, 'created', 15, NULL, 1000.00, 'รับเข้าคลัง - ViSafe Red', '2025-03-11 00:37:00'),
(37, 'used', 15, -400.00, 600.00, 'ใช้สำหรับย้อม DNA gel', '2025-04-15 14:00:00'),
(38, 'created', 15, NULL, 500.00, 'รับเข้าคลัง - 100 bp DNA Ladder', '2025-03-11 00:37:00'),
(38, 'used', 15, -150.00, 350.00, 'ใช้สำหรับ size reference', '2025-04-10 09:00:00'),
(39, 'created', 15, NULL, 10000.00, 'รับเข้าคลัง - HinfI restriction enzyme', '2025-03-11 00:37:00'),
(39, 'used', 15, -2000.00, 8000.00, 'ใช้สำหรับ RFLP analysis', '2025-05-01 11:00:00');

-- Add some alerts for lab0
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, is_read, action_required, created_at) VALUES
('low_stock', 'warning', 'สต็อกต่ำ', 'ViSafe Red (BTL-0007) เหลือเพียง 60%', 15, 24, 37, 0, 1, NOW()),
('low_stock', 'warning', 'สต็อกต่ำ', '100 bp DNA Ladder (BTL-0008) เหลือเพียง 70%', 15, 25, 38, 0, 1, NOW()),
('borrow_request', 'info', 'คำขอยืมใหม่', 'นักวิจัย สอง ขอยืม TAE Buffer 50X 200 mL', 15, 23, 36, 0, 1, NOW());

-- Update AUTO_INCREMENT
ALTER TABLE users AUTO_INCREMENT = 16;
ALTER TABLE chemicals AUTO_INCREMENT = 27;
ALTER TABLE containers AUTO_INCREMENT = 40;
