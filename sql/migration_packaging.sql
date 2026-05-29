-- =====================================================
-- Chemical Packaging Templates
-- บรรจุภัณฑ์สำเร็จรูป ผูกกับ CAS Number / Chemical
-- =====================================================

CREATE TABLE IF NOT EXISTS chemical_packaging (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_id INT NOT NULL COMMENT 'FK -> chemicals.id',
    -- Container info
    container_type ENUM('bottle', 'vial', 'flask', 'canister', 'cylinder', 'ampoule', 'bag', 'gallon', 'drum', 'other') NOT NULL DEFAULT 'bottle',
    container_material ENUM('glass', 'plastic', 'metal', 'hdpe', 'amber_glass', 'other') DEFAULT 'glass',
    capacity DECIMAL(10, 2) NOT NULL COMMENT 'ปริมาตร/น้ำหนักบรรจุ',
    capacity_unit ENUM('mL', 'L', 'g', 'kg', 'mg', 'oz', 'lb', 'gal') DEFAULT 'mL',
    -- Display
    label VARCHAR(255) NOT NULL COMMENT 'ชื่อที่แสดง เช่น ขวด 2 L',
    description TEXT,
    image_url VARCHAR(500) COMMENT 'ลิงก์รูปภาพบรรจุภัณฑ์',
    -- Supplier / pricing
    supplier_name VARCHAR(255),
    catalogue_number VARCHAR(100),
    unit_price DECIMAL(12, 2),
    currency VARCHAR(10) DEFAULT 'THB',
    -- Metadata
    is_default BOOLEAN DEFAULT FALSE COMMENT 'บรรจุภัณฑ์เริ่มต้น',
    sort_order INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_packaging_chemical ON chemical_packaging(chemical_id);
CREATE INDEX idx_packaging_active ON chemical_packaging(chemical_id, is_active);
