-- ============================================================
-- Lab Stores (คลังสารเคมี) — Migration
-- Maps organization hierarchy: ศูนย์ → ฝ่าย → งาน → คลัง
-- ============================================================

CREATE TABLE IF NOT EXISTS lab_stores (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    center_name     VARCHAR(255) NOT NULL COMMENT 'ศูนย์/สำนักวิชา',
    division_name   VARCHAR(255) NOT NULL COMMENT 'ฝ่าย/สาขาวิชา',
    section_name    VARCHAR(255) NOT NULL COMMENT 'งาน',
    store_name      VARCHAR(255) NOT NULL COMMENT 'ชื่อคลังสารเคมี',
    department_id   INT          DEFAULT NULL COMMENT 'FK→departments (level 2 ฝ่าย)',
    section_dept_id INT          DEFAULT NULL COMMENT 'FK→departments (level 3 งาน)',
    building_id     INT          DEFAULT NULL COMMENT 'FK→buildings',
    room_id         INT          DEFAULT NULL COMMENT 'FK→rooms',
    manager_id      INT          DEFAULT NULL COMMENT 'FK→users ผู้รับผิดชอบ',
    bottle_count    INT          DEFAULT 0   COMMENT 'จำนวนขวดสารเคมี (from CSV)',
    chemical_count  INT          DEFAULT 0   COMMENT 'จำนวนสารเคมี (from CSV)',
    total_weight_kg DECIMAL(12,4) DEFAULT 0  COMMENT 'ปริมาณรวม kg (from CSV)',
    is_active       TINYINT(1)   DEFAULT 1,
    color           VARCHAR(7)   DEFAULT NULL COMMENT 'UI color hex',
    icon            VARCHAR(50)  DEFAULT NULL COMMENT 'FA icon name',
    notes           TEXT         DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_center    (center_name),
    INDEX idx_division  (division_name),
    INDEX idx_section   (section_name),
    INDEX idx_dept      (department_id),
    INDEX idx_active    (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
