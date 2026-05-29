-- =====================================================
-- Migration V2: SUT Chemical Inventory Field Alignment
-- =====================================================
-- จุดประสงค์: ปรับปรุง schema ให้รองรับฟิลด์ข้อมูลจาก
-- ระบบเดิม (0.ฟิวข้อมูล.csv) ครบถ้วน พร้อม import ข้อมูลจริง
-- =====================================================

-- =====================================================
-- 1. ORGANIZATION HIERARCHY (โครงสร้างองค์กร)
-- รองรับ: คณะ/สถาบัน → ภาควิชา/ฝ่าย → สาขา/หน่วย
-- =====================================================

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    parent_id INT NULL COMMENT 'ลำดับชั้น: ศูนย์→ฝ่าย→งาน',
    name VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    code VARCHAR(50),
    level TINYINT NOT NULL DEFAULT 1 COMMENT '1=คณะ/ศูนย์, 2=ฝ่าย/ภาค, 3=สาขา/หน่วย',
    level_label VARCHAR(100) COMMENT 'คณะ|สถาบัน|ศูนย์วิจัย|กอง|ส่วน / ภาควิชา|ฝ่าย|สำนัก / สาขา|หน่วย',
    description TEXT,
    manager_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_dept_org ON departments(organization_id);
CREATE INDEX idx_dept_parent ON departments(parent_id);
CREATE INDEX idx_dept_level ON departments(level);

-- =====================================================
-- 2. MANUFACTURERS (ผู้ผลิต) — แยกจาก suppliers
-- =====================================================

CREATE TABLE IF NOT EXISTS manufacturers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    name_th VARCHAR(255),
    code VARCHAR(50),
    country VARCHAR(100),
    website VARCHAR(500),
    contact_info JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_mfr_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. SUPPLIERS (ผู้ขาย/ตัวแทนจำหน่าย) — standalone
-- =====================================================

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    name_th VARCHAR(255),
    code VARCHAR(50),
    tax_id VARCHAR(20),
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    contact_person VARCHAR(255),
    payment_terms VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_supplier_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. FUNDING SOURCES (แหล่งทุน)
-- รองรับ: ชื่อแหล่งทุน + แหล่งทุนย่อย
-- =====================================================

CREATE TABLE IF NOT EXISTS funding_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL COMMENT 'แหล่งทุนหลัก→แหล่งทุนย่อย',
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    description TEXT,
    fiscal_year VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES funding_sources(id) ON DELETE SET NULL,
    UNIQUE KEY uk_funding_name (name, parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. ALTER chemicals TABLE — เพิ่มฟิลด์ที่ขาด
-- =====================================================

-- เพิ่ม catalogue_number (CAS อาจเป็น Catalogue No. สำหรับ kit/reagent)
ALTER TABLE chemicals
    ADD COLUMN catalogue_number VARCHAR(100) AFTER cas_number,
    ADD COLUMN manufacturer_id INT AFTER category_id,
    ADD COLUMN substance_type VARCHAR(100) COMMENT 'ชนิดสาร: HomogeneousSubstance, HeterogenousSubstance, etc.' AFTER physical_state,
    ADD COLUMN substance_category VARCHAR(255) COMMENT 'ประเภทสาร: Vinyl Polysiloxane, Antimicrobial, etc.' AFTER substance_type,
    ADD COLUMN un_class VARCHAR(500) COMMENT 'ความเป็นอันตรายตามระบบ UN class' AFTER ghs_classifications,
    ADD COLUMN un_class_division VARCHAR(100) AFTER un_class,
    ADD COLUMN ghs_hazard_text VARCHAR(500) COMMENT 'GHS ภาษาไทย เช่น Gas under pressure (แก๊สภายใต้ความดัน)' AFTER un_class_division,
    ADD FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL;

ALTER TABLE chemicals
    ADD INDEX idx_chem_catalogue (catalogue_number),
    ADD INDEX idx_chem_manufacturer (manufacturer_id);

-- =====================================================
-- 6. ALTER containers TABLE — เพิ่มฟิลด์จากระบบเดิม
-- =====================================================

ALTER TABLE containers
    ADD COLUMN bottle_code VARCHAR(100) COMMENT 'รหัสขวด เช่น F10015A6800001' AFTER qr_code,
    ADD COLUMN grade VARCHAR(255) COMMENT 'เกรด เช่น 99.90%, Ultra Pure Grade' AFTER container_size,
    ADD COLUMN original_quantity_text VARCHAR(100) COMMENT 'ขนาดบรรจุ ข้อความดิบ เช่น "25.00 กิโลกรัม"' AFTER initial_quantity,
    ADD COLUMN remaining_quantity_text VARCHAR(100) COMMENT 'ปริมาณคงเหลือ ข้อความดิบ' AFTER current_quantity,
    ADD COLUMN invoice_number VARCHAR(100) COMMENT 'Invoice No.' AFTER po_number,
    ADD COLUMN tax_rate DECIMAL(5,2) COMMENT 'ภาษี %' AFTER cost,
    ADD COLUMN tax_amount DECIMAL(12,2) COMMENT 'จำนวนภาษี (คำนวณ)' AFTER tax_rate,
    ADD COLUMN total_cost DECIMAL(12,2) COMMENT 'ราคารวมภาษี' AFTER tax_amount,
    ADD COLUMN manufacturer_id INT AFTER supplier_id,
    ADD COLUMN supplier_id_new INT COMMENT 'FK ไปที่ suppliers table ใหม่' AFTER manufacturer_id,
    ADD COLUMN funding_source_id INT AFTER supplier_id_new,
    ADD COLUMN project_name VARCHAR(500) COMMENT 'โครงการ' AFTER funding_source_id,
    ADD COLUMN department_id INT COMMENT 'สังกัด สาขา/หน่วย' AFTER project_name,
    ADD COLUMN inventory_name VARCHAR(255) COMMENT 'ชื่อคลังสารเคมี' AFTER department_id,
    ADD COLUMN inventory_full_path TEXT COMMENT 'ชื่อคลังฯ และสังกัด (computed display)' AFTER inventory_name,
    ADD COLUMN added_by_name VARCHAR(255) COMMENT 'ชื่อผู้เพิ่มขวด (ข้อความจากระบบเดิม)' AFTER created_by,
    ADD COLUMN added_at_original DATETIME COMMENT 'เวลาเพิ่มขวด (จากระบบเดิม)' AFTER added_by_name,
    ADD COLUMN building_id INT COMMENT 'อาคาร (shortcut ไม่ต้อง join หลายชั้น)' AFTER location_slot_id,
    ADD COLUMN room_id INT COMMENT 'ห้อง (shortcut)' AFTER building_id,
    ADD COLUMN cabinet_id INT COMMENT 'ตู้ (shortcut)' AFTER room_id,
    ADD COLUMN shelf_id INT COMMENT 'ชั้น (shortcut)' AFTER cabinet_id,
    ADD COLUMN slot_id INT COMMENT 'ช่อง (shortcut)' AFTER shelf_id,
    ADD FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (supplier_id_new) REFERENCES suppliers(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (funding_source_id) REFERENCES funding_sources(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (shelf_id) REFERENCES shelves(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE SET NULL;

ALTER TABLE containers
    ADD UNIQUE INDEX uk_bottle_code (bottle_code),
    ADD INDEX idx_cont_manufacturer (manufacturer_id),
    ADD INDEX idx_cont_supplier_new (supplier_id_new),
    ADD INDEX idx_cont_funding (funding_source_id),
    ADD INDEX idx_cont_department (department_id),
    ADD INDEX idx_cont_building (building_id),
    ADD INDEX idx_cont_room (room_id),
    ADD INDEX idx_cont_invoice (invoice_number);

-- =====================================================
-- 7. ALTER buildings TABLE — เพิ่มฟิลด์จากข้อมูลจริง
-- =====================================================

ALTER TABLE buildings
    ADD COLUMN name_en VARCHAR(255) AFTER name,
    ADD COLUMN shortname VARCHAR(50) AFTER code;

-- =====================================================
-- 8. ALTER rooms TABLE — เพิ่มฟิลด์จากข้อมูลจริง
-- =====================================================

ALTER TABLE rooms
    ADD COLUMN name_en VARCHAR(255) AFTER name,
    ADD COLUMN room_type VARCHAR(100) COMMENT 'ประเภทห้อง Lab' AFTER room_number,
    ADD COLUMN area_sqm DECIMAL(10,2) COMMENT 'พื้นที่ (ตร.ม.)' AFTER room_type,
    ADD COLUMN capacity_persons INT COMMENT 'ความจุ (คน)' AFTER area_sqm,
    ADD COLUMN department_id INT AFTER lab_id,
    ADD COLUMN responsibility_person VARCHAR(255) AFTER department_id,
    ADD COLUMN status_text VARCHAR(50) DEFAULT 'พร้อมใช้งาน' AFTER humidity_controlled,
    ADD COLUMN bookable BOOLEAN DEFAULT FALSE AFTER status_text,
    ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- =====================================================
-- 9. ALTER users TABLE — รองรับสังกัด
-- =====================================================

ALTER TABLE users
    ADD COLUMN department_id INT AFTER lab_id,
    ADD COLUMN full_name_th VARCHAR(255) COMMENT 'ชื่อ-นามสกุลเต็ม (คำนำหน้า+ชื่อ+สกุล)' AFTER last_name,
    ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- =====================================================
-- 10. QUANTITY UNITS LOOKUP — หน่วยวัดจากระบบเดิม
-- =====================================================

CREATE TABLE IF NOT EXISTS quantity_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_th VARCHAR(100) NOT NULL COMMENT 'กิโลกรัม, มิลลิลิตร, ไมโครลิตร etc.',
    name_en VARCHAR(100),
    symbol VARCHAR(20) NOT NULL COMMENT 'kg, mL, µL, Units etc.',
    unit_type ENUM('mass','volume','count','other') NOT NULL,
    base_factor DECIMAL(20,10) COMMENT 'ตัวคูณไปหน่วยฐาน (g สำหรับ mass, mL สำหรับ volume)',
    sort_order INT DEFAULT 0,
    UNIQUE KEY uk_unit_symbol (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- หน่วยที่พบในข้อมูลจริง
INSERT INTO quantity_units (name_th, name_en, symbol, unit_type, base_factor, sort_order) VALUES
('กิโลกรัม', 'Kilogram', 'kg', 'mass', 1000.0, 1),
('กรัม', 'Gram', 'g', 'mass', 1.0, 2),
('มิลลิกรัม', 'Milligram', 'mg', 'mass', 0.001, 3),
('ไมโครกรัม', 'Microgram', 'µg', 'mass', 0.000001, 4),
('ลิตร', 'Liter', 'L', 'volume', 1000.0, 10),
('มิลลิลิตร', 'Milliliter', 'mL', 'volume', 1.0, 11),
('ไมโครลิตร', 'Microliter', 'µL', 'volume', 0.001, 12),
('Units', 'Units', 'Units', 'count', 1.0, 20),
('ชิ้น', 'Piece', 'pcs', 'count', 1.0, 21),
('กล่อง', 'Box', 'box', 'count', 1.0, 22),
('ชุด', 'Set/Kit', 'kit', 'count', 1.0, 23),
('ออนซ์', 'Ounce', 'oz', 'mass', 28.3495, 30),
('ปอนด์', 'Pound', 'lb', 'mass', 453.592, 31)
ON DUPLICATE KEY UPDATE name_th=VALUES(name_th);

-- =====================================================
-- 11. UPDATE containers quantity_unit ENUM
--     เปลี่ยนเป็น VARCHAR เพื่อรองรับหน่วยไทยได้ยืดหยุ่น
-- =====================================================

ALTER TABLE containers
    MODIFY COLUMN quantity_unit VARCHAR(50) DEFAULT 'mL'
    COMMENT 'อ้างอิง quantity_units.symbol';

-- =====================================================
-- 12. VIEW: v_container_full — สำหรับ Report ที่แสดงผลเหมือน CSV
-- =====================================================

CREATE OR REPLACE VIEW v_container_full AS
SELECT
    -- รหัสขวด
    cn.bottle_code                                   AS `รหัสขวด`,
    cn.qr_code                                       AS `QR Code`,
    -- ชื่อสารเคมี
    ch.name                                          AS `ชื่อสารเคมี`,
    -- CAS / Catalogue No.
    COALESCE(ch.cas_number, ch.catalogue_number, '') AS `CAS / Catalogue No.`,
    -- เกรด
    cn.grade                                         AS `เกรด`,
    -- ขนาดบรรจุ
    CONCAT(cn.initial_quantity, ' ', cn.quantity_unit) AS `ขนาดบรรจุ`,
    cn.initial_quantity                               AS `ขนาดบรรจุ_ตัวเลข`,
    -- ปริมาณคงเหลือ
    CONCAT(cn.current_quantity, ' ', cn.quantity_unit) AS `ปริมาณคงเหลือ`,
    cn.current_quantity                               AS `ปริมาณคงเหลือ_ตัวเลข`,
    cn.remaining_percentage                           AS `เปอร์เซ็นต์คงเหลือ`,
    cn.quantity_unit                                  AS `หน่วย`,
    -- ชื่อผู้เพิ่มขวด
    COALESCE(cn.added_by_name,
        CONCAT(u.first_name, ' ', u.last_name))      AS `ชื่อผู้เพิ่มขวด`,
    -- เวลาเพิ่มขวด
    COALESCE(cn.added_at_original, cn.created_at)    AS `เวลาเพิ่มขวด`,
    -- สถานที่
    b.name                                           AS `ชื่ออาคาร`,
    rm.name                                          AS `ชื่อห้อง`,
    rm.code                                          AS `เลขทะเบียนห้อง`,
    cab.name                                         AS `ชื่อตู้เก็บขวด`,
    sh.name                                          AS `ชื่อชั้นเก็บขวด`,
    sl.name                                          AS `ชื่อช่องเก็บขวด`,
    -- ผู้ผลิต / ผู้ขาย
    mfr.name                                         AS `ชื่อผู้ผลิต`,
    sup.name                                         AS `ชื่อผู้ขาย`,
    -- การเงิน
    cn.invoice_number                                AS `Invoice No.`,
    cn.cost                                          AS `ราคา`,
    cn.tax_rate                                      AS `ภาษี`,
    cn.total_cost                                    AS `ราคารวม`,
    cn.notes                                         AS `หมายเหตุ`,
    -- แหล่งทุน
    fs.name                                          AS `ชื่อแหล่งทุน`,
    fs_sub.name                                      AS `แหล่งทุนย่อย`,
    -- สังกัด
    d1.name                                          AS `คณะ/สถาบัน/ศูนย์`,
    d2.name                                          AS `ภาควิชา/ฝ่าย`,
    d3.name                                          AS `สาขา/หน่วย`,
    cn.project_name                                  AS `โครงการ`,
    -- คลัง
    cn.inventory_name                                AS `ชื่อคลังสารเคมี`,
    cn.inventory_full_path                           AS `ชื่อคลังฯ และสังกัด`,
    -- ความเป็นอันตราย
    ch.un_class                                      AS `ความเป็นอันตรายตามระบบ UN class`,
    ch.ghs_hazard_text                               AS `ความเป็นอันตรายตามระบบ GHS`,
    -- วันหมดอายุ
    cn.expiry_date                                   AS `วันหมดอายุ`,
    -- สถานะ
    cn.status                                        AS `สถานะ`,
    -- keys
    cn.id                                            AS container_id,
    ch.id                                            AS chemical_id,
    cn.lab_id,
    cn.owner_id
FROM containers cn
LEFT JOIN chemicals ch        ON cn.chemical_id = ch.id
LEFT JOIN users u             ON cn.created_by = u.id
LEFT JOIN buildings b         ON cn.building_id = b.id
LEFT JOIN rooms rm            ON cn.room_id = rm.id
LEFT JOIN cabinets cab        ON cn.cabinet_id = cab.id
LEFT JOIN shelves sh          ON cn.shelf_id = sh.id
LEFT JOIN slots sl            ON cn.slot_id = sl.id
LEFT JOIN manufacturers mfr   ON cn.manufacturer_id = mfr.id
LEFT JOIN suppliers sup        ON cn.supplier_id_new = sup.id
LEFT JOIN funding_sources fs   ON cn.funding_source_id = fs.id
LEFT JOIN funding_sources fs_sub ON fs_sub.parent_id = fs.id
LEFT JOIN departments d3       ON cn.department_id = d3.id
LEFT JOIN departments d2       ON d3.parent_id = d2.id
LEFT JOIN departments d1       ON d2.parent_id = d1.id;

-- =====================================================
-- 13. VIEW: v_inventory_summary — สรุปคลัง (จาก file 3)
-- =====================================================

CREATE OR REPLACE VIEW v_inventory_summary AS
SELECT
    d1.name AS center_name,
    d2.name AS division_name,
    d3.name AS unit_name,
    cn.inventory_name,
    COUNT(cn.id) AS bottle_count,
    COUNT(DISTINCT cn.chemical_id) AS chemical_count,
    SUM(CASE
        WHEN cn.quantity_unit IN ('kg') THEN cn.current_quantity
        WHEN cn.quantity_unit IN ('g') THEN cn.current_quantity / 1000
        WHEN cn.quantity_unit IN ('mg') THEN cn.current_quantity / 1000000
        WHEN cn.quantity_unit IN ('L') THEN cn.current_quantity  -- liters ≈ kg for water-based
        WHEN cn.quantity_unit IN ('mL') THEN cn.current_quantity / 1000
        ELSE 0
    END) AS total_quantity_kg
FROM containers cn
LEFT JOIN departments d3 ON cn.department_id = d3.id
LEFT JOIN departments d2 ON d3.parent_id = d2.id
LEFT JOIN departments d1 ON d2.parent_id = d1.id
WHERE cn.status = 'active'
GROUP BY d1.id, d2.id, d3.id, cn.inventory_name
ORDER BY total_quantity_kg DESC;

-- =====================================================
-- 14. IMPORT STAGING TABLE — สำหรับ import CSV batch
-- =====================================================

CREATE TABLE IF NOT EXISTS import_staging (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_batch VARCHAR(50) NOT NULL COMMENT 'รหัส batch การ import',
    row_number INT,
    status ENUM('pending','processed','error','skipped') DEFAULT 'pending',
    error_message TEXT,
    -- ข้อมูลดิบจาก CSV ทุกฟิลด์
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
    -- resolved FK (หลัง process)
    resolved_chemical_id INT,
    resolved_container_id INT,
    resolved_building_id INT,
    resolved_room_id INT,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_staging_batch ON import_staging(import_batch);
CREATE INDEX idx_staging_status ON import_staging(status);
CREATE INDEX idx_staging_bottle ON import_staging(bottle_code);

-- =====================================================
-- 15. SEED DATA: Buildings จากไฟล์ 4
-- =====================================================

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

-- =====================================================
-- 16. SEED DATA: Organization + Top-Level Department
-- =====================================================

UPDATE organizations SET
    name = 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี',
    description = 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี มหาวิทยาลัยเทคโนโลยีสุรนารี'
WHERE id = 1;

-- ศูนย์ (Level 1)
INSERT INTO departments (id, organization_id, parent_id, name, level, level_label) VALUES
(1, 1, NULL, 'ศูนย์เครื่องมือวิทยาศาสตร์และเทคโนโลยี', 1, 'ศูนย์')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ฝ่าย (Level 2)
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

-- งาน (Level 3) — sample จาก file 3
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

-- =====================================================
-- 17. SEED DATA: Funding Sources
-- =====================================================

INSERT INTO funding_sources (id, parent_id, name, code) VALUES
(1, NULL, 'งบประมาณแผ่นดิน', 'GOV'),
(2, NULL, 'งบรายได้', 'REV'),
(3, NULL, 'งบวิจัย', 'RES'),
(4, NULL, 'งบบริจาค', 'DON')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- =====================================================
-- 18. FIELD MAPPING REFERENCE (สำหรับ CSV import)
-- =====================================================

CREATE TABLE IF NOT EXISTS import_field_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    csv_column_name VARCHAR(255) NOT NULL COMMENT 'ชื่อ column ใน CSV',
    target_table VARCHAR(100) NOT NULL,
    target_column VARCHAR(100) NOT NULL,
    data_type VARCHAR(50) DEFAULT 'VARCHAR',
    transform_rule TEXT COMMENT 'วิธีแปลง เช่น SPLIT_UNIT, LOOKUP, PARSE_DATE',
    required BOOLEAN DEFAULT FALSE,
    notes TEXT,
    UNIQUE KEY uk_csv_col (csv_column_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO import_field_mapping (csv_column_name, target_table, target_column, data_type, transform_rule, required, notes) VALUES
('รหัสขวด', 'containers', 'bottle_code', 'VARCHAR(100)', NULL, TRUE, 'Primary key ของขวด'),
('ชื่อสารเคมี', 'chemicals', 'name', 'VARCHAR(255)', 'LOOKUP_OR_CREATE chemicals', TRUE, 'ค้นหาหรือสร้างสารใหม่'),
('CAS / Catalogue No.', 'chemicals', 'cas_number|catalogue_number', 'VARCHAR(100)', 'IF CAS PATTERN → cas_number ELSE catalogue_number', FALSE, 'ถ้าตรง pattern XX-XX-X = CAS, อื่นๆ = Catalogue'),
('เกรด', 'containers', 'grade', 'VARCHAR(255)', NULL, FALSE, NULL),
('ขนาดบรรจุ', 'containers', 'initial_quantity+quantity_unit', 'DECIMAL+VARCHAR', 'SPLIT: "25.00 กิโลกรัม" → 25.00 + kg', TRUE, 'แยกตัวเลขและหน่วย'),
('ปริมาณคงเหลือ', 'containers', 'current_quantity+quantity_unit', 'DECIMAL+VARCHAR', 'SPLIT: same as ขนาดบรรจุ', TRUE, NULL),
('ชื่อผู้เพิ่มขวด', 'containers', 'added_by_name', 'VARCHAR(255)', 'ALSO LOOKUP users.full_name_th', FALSE, NULL),
('เวลาเพิ่มขวด', 'containers', 'added_at_original', 'DATETIME', 'PARSE: d/m/Y H:i', FALSE, 'รูปแบบ 3/11/2025 00:37'),
('ชื่ออาคาร', 'containers', 'building_id', 'INT', 'LOOKUP buildings.name', FALSE, NULL),
('ชื่อห้อง', 'containers', 'room_id', 'INT', 'LOOKUP rooms.name', FALSE, NULL),
('เลขทะเบียนห้อง', 'rooms', 'code', 'VARCHAR(50)', 'MATCH rooms.code', FALSE, NULL),
('ชื่อตู้เก็บขวด', 'containers', 'cabinet_id', 'INT', 'LOOKUP_OR_CREATE cabinets.name in room', FALSE, NULL),
('ชื่อชั้นเก็บขวด', 'containers', 'shelf_id', 'INT', 'LOOKUP_OR_CREATE shelves.name in cabinet', FALSE, NULL),
('ชื่อช่องเก็บขวด', 'containers', 'slot_id', 'INT', 'LOOKUP_OR_CREATE slots.name in shelf', FALSE, NULL),
('ชื่อผู้ผลิต', 'containers', 'manufacturer_id', 'INT', 'LOOKUP_OR_CREATE manufacturers.name', FALSE, NULL),
('ชื่อผู้ขาย', 'containers', 'supplier_id_new', 'INT', 'LOOKUP_OR_CREATE suppliers.name', FALSE, NULL),
('Invoice No.', 'containers', 'invoice_number', 'VARCHAR(100)', NULL, FALSE, NULL),
('ราคา', 'containers', 'cost', 'DECIMAL(12,2)', 'REMOVE_COMMA: "1,705.60" → 1705.60', FALSE, NULL),
('ภาษี', 'containers', 'tax_rate', 'DECIMAL(5,2)', NULL, FALSE, 'ค่า % เช่น 7'),
('หมายเหตุ', 'containers', 'notes', 'TEXT', NULL, FALSE, NULL),
('ชื่อแหล่งทุน', 'containers', 'funding_source_id', 'INT', 'LOOKUP_OR_CREATE funding_sources.name', FALSE, NULL),
('แหล่งทุนย่อย', 'funding_sources', 'sub_funding', 'INT', 'CREATE as child of แหล่งทุน', FALSE, NULL),
('คณะ, สถาบัน, ศูนย์วิจัย, กอง, ส่วน', 'departments', 'level1', 'INT', 'LOOKUP departments WHERE level=1', FALSE, NULL),
('ภาควิชา, ศูนย์, ฝ่าย, สำนัก', 'departments', 'level2', 'INT', 'LOOKUP departments WHERE level=2', FALSE, NULL),
('สาขา, หน่วย', 'departments', 'level3', 'INT', 'LOOKUP departments WHERE level=3 → container.department_id', FALSE, NULL),
('โครงการ', 'containers', 'project_name', 'VARCHAR(500)', NULL, FALSE, NULL),
('ชื่อคลังสารเคมี', 'containers', 'inventory_name', 'VARCHAR(255)', NULL, FALSE, NULL),
('ชื่อคลังฯ และสังกัด', 'containers', 'inventory_full_path', 'TEXT', NULL, FALSE, 'ข้อมูล display (computed)'),
('ความเป็นอันตรายตามระบบ UN class', 'chemicals', 'un_class', 'VARCHAR(500)', NULL, FALSE, NULL),
('ความเป็นอันตรายตามระบบ GHS', 'chemicals', 'ghs_hazard_text', 'VARCHAR(500)', NULL, FALSE, NULL),
('วันหมดอายุ', 'containers', 'expiry_date', 'DATE', 'PARSE_DATE: d/m/Y or NULL', FALSE, NULL)
ON DUPLICATE KEY UPDATE target_table=VALUES(target_table), target_column=VALUES(target_column);

-- =====================================================
-- DONE — Migration V2
-- =====================================================
-- สรุปสิ่งที่เพิ่ม:
-- ✅ departments         — โครงสร้างองค์กร 3 ระดับ (ศูนย์→ฝ่าย→งาน)
-- ✅ manufacturers       — ผู้ผลิต (Air Liquid, Vivantis, Invitrogen, etc.)
-- ✅ suppliers            — ผู้ขาย/ตัวแทนจำหน่าย
-- ✅ funding_sources      — แหล่งทุน + แหล่งทุนย่อย
-- ✅ quantity_units       — หน่วยวัดทั้งไทยและอังกฤษ
-- ✅ import_staging       — staging table สำหรับ import CSV
-- ✅ import_field_mapping — แผนที่ฟิลด์ CSV → DB columns
-- ✅ chemicals ALTER      — เพิ่ม catalogue_number, manufacturer_id, un_class, ghs_hazard_text, substance_type
-- ✅ containers ALTER     — เพิ่ม bottle_code, grade, invoice, tax, funding, department, location shortcuts
-- ✅ buildings ALTER      — เพิ่ม name_en, shortname
-- ✅ rooms ALTER          — เพิ่ม name_en, room_type, area, capacity, department_id
-- ✅ users ALTER          — เพิ่ม department_id, full_name_th
-- ✅ v_container_full     — VIEW แสดงข้อมูลเหมือน CSV ต้นฉบับ
-- ✅ v_inventory_summary  — VIEW สรุปคลัง (เหมือน file 3)
-- ✅ Seed: 15 buildings, 1 center + 9 divisions + 34 units, 4 funding sources
-- =====================================================
