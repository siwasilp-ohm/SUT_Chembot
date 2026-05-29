-- =====================================================
-- 3D Model Integration — Packaging ↔ VRX Studio
-- Links packaging templates to 3D model files in VRX
-- + Model request system for missing models
-- =====================================================

-- ── Packaging ↔ 3D Model link table ──
-- Maps chemical_packaging records to VRX files
CREATE TABLE IF NOT EXISTS packaging_3d_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    packaging_id INT COMMENT 'FK -> chemical_packaging.id (NULL = generic for container_type)',
    container_type VARCHAR(50) NOT NULL COMMENT 'bottle, vial, flask, etc.',
    container_material VARCHAR(50) DEFAULT NULL COMMENT 'glass, plastic, metal, etc.',
    capacity_range_min DECIMAL(10,2) DEFAULT NULL COMMENT 'Min capacity for auto-match',
    capacity_range_max DECIMAL(10,2) DEFAULT NULL COMMENT 'Max capacity for auto-match',
    capacity_unit VARCHAR(10) DEFAULT NULL,
    -- VRX file reference
    vrx_file_id INT UNSIGNED NOT NULL COMMENT 'FK -> vrx_studio.files.id',
    vrx_file_uuid CHAR(36) NOT NULL COMMENT 'VRX file UUID for direct access',
    vrx_file_url VARCHAR(1000) COMMENT 'Cached VRX file URL for quick render',
    vrx_thumbnail_url VARCHAR(500) COMMENT 'Cached thumbnail',
    -- Display
    label VARCHAR(255) NOT NULL COMMENT 'Display name e.g. "ขวดแก้ว 2.5L"',
    description TEXT,
    ar_enabled TINYINT(1) DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0 COMMENT 'Default model for this container_type',
    sort_order INT DEFAULT 0,
    -- Meta
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (packaging_id) REFERENCES chemical_packaging(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_p3d_packaging ON packaging_3d_models(packaging_id);
CREATE INDEX idx_p3d_type ON packaging_3d_models(container_type, container_material);
CREATE INDEX idx_p3d_vrx ON packaging_3d_models(vrx_file_id);
CREATE INDEX idx_p3d_active ON packaging_3d_models(is_active, is_default);

-- ── Model Request System ──
-- Users can request 3D models for packaging types that don't have one
CREATE TABLE IF NOT EXISTS model_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- What they want modeled
    chemical_id INT DEFAULT NULL COMMENT 'FK -> chemicals.id',
    packaging_id INT DEFAULT NULL COMMENT 'FK -> chemical_packaging.id',
    container_type VARCHAR(50) NOT NULL,
    container_material VARCHAR(50) DEFAULT NULL,
    capacity DECIMAL(10,2) DEFAULT NULL,
    capacity_unit VARCHAR(10) DEFAULT NULL,
    -- Request info
    title VARCHAR(255) NOT NULL,
    description TEXT,
    reference_image_url VARCHAR(500) COMMENT 'Reference image for modeler',
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    -- Status tracking
    status ENUM('pending','approved','in_progress','completed','rejected') DEFAULT 'pending',
    assigned_to INT DEFAULT NULL COMMENT 'FK -> users.id (modeler)',
    -- Fulfillment
    fulfilled_model_id INT DEFAULT NULL COMMENT 'FK -> packaging_3d_models.id',
    admin_notes TEXT,
    -- Meta
    requested_by INT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE SET NULL,
    FOREIGN KEY (packaging_id) REFERENCES chemical_packaging(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (fulfilled_model_id) REFERENCES packaging_3d_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_mr_status ON model_requests(status);
CREATE INDEX idx_mr_requester ON model_requests(requested_by);
CREATE INDEX idx_mr_type ON model_requests(container_type);

-- ── Add model_3d_id column to chemical_packaging ──
ALTER TABLE chemical_packaging 
    ADD COLUMN model_3d_id INT DEFAULT NULL COMMENT 'FK -> packaging_3d_models.id'
    AFTER image_url;

