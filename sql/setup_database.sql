-- =====================================================
-- AI-Driven Chemical Inventory Management System
-- Complete Database Setup (Schema + Sample Data)
-- For MySQL 5.7+ / MariaDB 10.3+
-- =====================================================

-- Drop and recreate database
DROP DATABASE IF EXISTS chem_inventory_db;
CREATE DATABASE chem_inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chem_inventory_db;

-- =====================================================
-- 1. USER MANAGEMENT & AUTHENTICATION
-- =====================================================

CREATE TABLE organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    logo_url VARCHAR(500),
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON NOT NULL,
    level INT NOT NULL COMMENT '1=Visitor, 2=User, 3=Lab Manager, 4=CEO, 5=Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT,
    role_id INT NOT NULL,
    lab_id INT,
    manager_id INT COMMENT 'Reports to which Lab Manager',
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(50),
    avatar_url VARCHAR(500),
    department VARCHAR(100),
    position VARCHAR(100),
    theme_preference ENUM('light', 'dark', 'auto') DEFAULT 'auto',
    primary_color VARCHAR(7) DEFAULT '#3B82F6',
    language VARCHAR(10) DEFAULT 'th',
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    api_token VARCHAR(255),
    token_expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_info JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 2. LABORATORY & LOCATION MANAGEMENT
-- =====================================================

CREATE TABLE labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    description TEXT,
    manager_id INT,
    floor_plan_url VARCHAR(500),
    floor_plan_svg TEXT,
    location_data JSON COMMENT 'Coordinates, dimensions for visualization',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Add lab_id FK to users after labs table exists
ALTER TABLE users ADD FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL;

CREATE TABLE buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    address TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    lab_id INT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    room_number VARCHAR(50),
    floor INT DEFAULT 1,
    floor_plan_url VARCHAR(500),
    floor_plan_svg TEXT,
    safety_level ENUM('general', 'chemical', 'biohazard', 'radiation') DEFAULT 'general',
    temperature_controlled BOOLEAN DEFAULT FALSE,
    humidity_controlled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL
);

CREATE TABLE cabinets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    type ENUM('storage', 'fume_hood', 'refrigerator', 'freezer', 'safety_cabinet', 'other') DEFAULT 'storage',
    capacity INT COMMENT 'Number of shelves',
    dimensions VARCHAR(100),
    temperature_min DECIMAL(5, 2),
    temperature_max DECIMAL(5, 2),
    ventilation BOOLEAN DEFAULT FALSE,
    fire_resistant BOOLEAN DEFAULT FALSE,
    position_x DECIMAL(6, 2),
    position_y DECIMAL(6, 2),
    width DECIMAL(6, 2),
    height DECIMAL(6, 2),
    svg_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

CREATE TABLE shelves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cabinet_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    level INT NOT NULL COMMENT 'Shelf level from bottom',
    capacity INT COMMENT 'Number of slots',
    dimensions VARCHAR(100),
    max_weight DECIMAL(8, 2),
    svg_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cabinet_id) REFERENCES cabinets(id) ON DELETE CASCADE
);

CREATE TABLE slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shelf_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    position INT NOT NULL,
    dimensions VARCHAR(100),
    position_x DECIMAL(6, 2),
    position_y DECIMAL(6, 2),
    width DECIMAL(6, 2),
    height DECIMAL(6, 2),
    svg_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shelf_id) REFERENCES shelves(id) ON DELETE CASCADE
);

-- =====================================================
-- 3. CHEMICAL & SUBSTANCE MANAGEMENT
-- =====================================================

CREATE TABLE chemical_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES chemical_categories(id) ON DELETE SET NULL
);

CREATE TABLE chemicals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cas_number VARCHAR(50) UNIQUE COMMENT 'CAS Registry Number',
    name VARCHAR(255) NOT NULL,
    iupac_name VARCHAR(500),
    synonyms JSON,
    molecular_formula VARCHAR(200),
    molecular_weight DECIMAL(12, 4),
    description TEXT,
    category_id INT,
    physical_state ENUM('solid', 'liquid', 'gas', 'plasma') DEFAULT 'solid',
    appearance VARCHAR(255),
    odor VARCHAR(255),
    melting_point DECIMAL(8, 2),
    boiling_point DECIMAL(8, 2),
    density DECIMAL(8, 4),
    solubility TEXT,
    vapor_pressure DECIMAL(10, 4),
    flash_point DECIMAL(8, 2),
    auto_ignition_temp DECIMAL(8, 2),
    -- GHS Classification
    ghs_classifications JSON,
    hazard_pictograms JSON,
    signal_word ENUM('Danger', 'Warning', 'No signal word'),
    hazard_statements JSON,
    precautionary_statements JSON,
    -- Safety Data
    sds_url VARCHAR(500),
    sds_pdf_path VARCHAR(500),
    sds_last_updated DATE,
    safety_info JSON,
    handling_procedures TEXT,
    storage_requirements TEXT,
    disposal_methods TEXT,
    first_aid_measures TEXT,
    fire_fighting_measures TEXT,
    accidental_release_measures TEXT,
    exposure_controls TEXT,
    -- Compatibility
    incompatible_chemicals JSON,
    storage_compatibility_group VARCHAR(50),
    -- Media
    image_url VARCHAR(500),
    model_3d_url VARCHAR(500),
    model_3d_glb VARCHAR(500),
    model_3d_usdz VARCHAR(500),
    -- Metadata
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    verified BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (category_id) REFERENCES chemical_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE chemical_suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_id INT NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    catalog_number VARCHAR(100),
    unit_price DECIMAL(12, 2),
    currency VARCHAR(10) DEFAULT 'THB',
    unit_size VARCHAR(50),
    lead_time_days INT,
    minimum_order INT DEFAULT 1,
    is_preferred BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE
);

-- =====================================================
-- 4. CONTAINER & INVENTORY MANAGEMENT
-- (Fixed: removed complex GENERATED column, added is_active)
-- =====================================================

CREATE TABLE containers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_code VARCHAR(255) UNIQUE,
    qr_code_image VARCHAR(500),
    chemical_id INT NOT NULL,
    owner_id INT NOT NULL,
    lab_id INT NOT NULL,
    -- Location (simplified - no GENERATED column to avoid MySQL issues)
    location_slot_id INT,
    location_path VARCHAR(500) DEFAULT NULL COMMENT 'Cached location path - updated via trigger/application',
    -- Container Details
    container_type ENUM('bottle', 'vial', 'flask', 'canister', 'cylinder', 'ampoule', 'bag', 'other') DEFAULT 'bottle',
    container_material ENUM('glass', 'plastic', 'metal', 'other') DEFAULT 'glass',
    container_size VARCHAR(50),
    container_capacity DECIMAL(10, 2),
    capacity_unit ENUM('mL', 'L', 'g', 'kg', 'mg', 'oz', 'lb') DEFAULT 'mL',
    -- Quantity Tracking
    initial_quantity DECIMAL(12, 4) NOT NULL,
    current_quantity DECIMAL(12, 4) NOT NULL,
    quantity_unit ENUM('mL', 'L', 'g', 'kg', 'mg', 'oz', 'lb', 'units') DEFAULT 'mL',
    remaining_percentage DECIMAL(5, 2) DEFAULT 100.00,
    -- Dates
    manufacture_date DATE,
    received_date DATE NOT NULL,
    opened_date DATE,
    expiry_date DATE,
    expiry_alert_days INT DEFAULT 30,
    -- Status
    status ENUM('active', 'empty', 'expired', 'quarantined', 'disposed', 'transferred') DEFAULT 'active',
    quality_status ENUM('good', 'questionable', 'contaminated', 'unknown') DEFAULT 'good',
    is_active BOOLEAN DEFAULT TRUE,
    -- Visual
    label_image VARCHAR(500),
    container_3d_model VARCHAR(500),
    -- Metadata
    batch_number VARCHAR(100),
    lot_number VARCHAR(100),
    po_number VARCHAR(100),
    supplier_id INT,
    cost DECIMAL(12, 2),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE RESTRICT,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE RESTRICT,
    FOREIGN KEY (location_slot_id) REFERENCES slots(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Trigger to auto-update remaining_percentage
DELIMITER //
CREATE TRIGGER update_remaining_percentage_insert
BEFORE INSERT ON containers
FOR EACH ROW
BEGIN
    IF NEW.initial_quantity > 0 THEN
        SET NEW.remaining_percentage = (NEW.current_quantity / NEW.initial_quantity) * 100;
    ELSE
        SET NEW.remaining_percentage = 0;
    END IF;
END//

CREATE TRIGGER update_remaining_percentage_update
BEFORE UPDATE ON containers
FOR EACH ROW
BEGIN
    IF NEW.initial_quantity > 0 THEN
        SET NEW.remaining_percentage = (NEW.current_quantity / NEW.initial_quantity) * 100;
    ELSE
        SET NEW.remaining_percentage = 0;
    END IF;
END//
DELIMITER ;

CREATE TABLE container_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    container_id INT NOT NULL,
    action_type ENUM('created', 'moved', 'used', 'transferred', 'borrowed', 'returned', 'disposed', 'updated', 'inspected') NOT NULL,
    user_id INT NOT NULL,
    from_location_id INT,
    to_location_id INT,
    from_user_id INT,
    to_user_id INT,
    quantity_change DECIMAL(12, 4),
    quantity_after DECIMAL(12, 4),
    notes TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- =====================================================
-- 5. BORROW/LOAN & TRANSFER SYSTEM
-- =====================================================

CREATE TABLE borrow_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE,
    requester_id INT NOT NULL,
    owner_id INT,
    container_id INT,
    chemical_id INT NOT NULL,
    request_type ENUM('borrow', 'transfer', 'request_new') NOT NULL,
    requested_quantity DECIMAL(12, 4) NOT NULL,
    quantity_unit ENUM('mL', 'L', 'g', 'kg', 'mg', 'oz', 'lb', 'units') DEFAULT 'mL',
    purpose TEXT NOT NULL,
    experiment_id VARCHAR(100),
    project_name VARCHAR(255),
    needed_by_date DATE,
    expected_return_date DATE,
    actual_return_date DATE,
    status ENUM('pending', 'approved', 'rejected', 'fulfilled', 'partially_returned', 'returned', 'overdue', 'cancelled') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    approval_notes TEXT,
    fulfilled_container_id INT,
    fulfilled_quantity DECIMAL(12, 4),
    fulfilled_by INT,
    fulfilled_at TIMESTAMP NULL,
    returned_quantity DECIMAL(12, 4),
    return_condition ENUM('good', 'damaged', 'contaminated', 'partially_used'),
    return_notes TEXT,
    reminder_sent BOOLEAN DEFAULT FALSE,
    overdue_notice_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE SET NULL,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (fulfilled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (fulfilled_container_id) REFERENCES containers(id) ON DELETE SET NULL
);

CREATE TABLE transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) UNIQUE,
    container_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    from_lab_id INT,
    to_lab_id INT,
    quantity_transferred DECIMAL(12, 4),
    quantity_unit ENUM('mL', 'L', 'g', 'kg', 'mg', 'oz', 'lb', 'units') DEFAULT 'mL',
    transfer_type ENUM('permanent', 'temporary') DEFAULT 'permanent',
    reason TEXT,
    status ENUM('pending', 'approved', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE RESTRICT,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 6. AI & SMART FEATURES
-- =====================================================

CREATE TABLE ai_chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    title VARCHAR(255),
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE ai_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    tokens_used INT,
    referenced_chemicals JSON,
    referenced_containers JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES ai_chat_sessions(session_id) ON DELETE CASCADE
);

CREATE TABLE visual_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    detected_text JSON,
    detected_chemicals JSON,
    search_results JSON,
    confidence_score DECIMAL(5, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE usage_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_id INT NOT NULL,
    lab_id INT NOT NULL,
    prediction_date DATE NOT NULL,
    predicted_usage DECIMAL(12, 4),
    confidence_level DECIMAL(5, 2),
    factors JSON,
    actual_usage DECIMAL(12, 4),
    accuracy DECIMAL(5, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
);

-- =====================================================
-- 7. ALERTS & NOTIFICATIONS
-- =====================================================

CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('expiry', 'low_stock', 'overdue_borrow', 'safety_violation', 'temperature_alert', 'compliance', 'custom', 'borrow_request') NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    user_id INT,
    chemical_id INT,
    container_id INT,
    lab_id INT,
    borrow_request_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    dismissed BOOLEAN DEFAULT FALSE,
    action_required BOOLEAN DEFAULT FALSE,
    action_taken TEXT,
    action_taken_by INT,
    action_taken_at TIMESTAMP NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    push_sent BOOLEAN DEFAULT FALSE,
    sms_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE,
    FOREIGN KEY (borrow_request_id) REFERENCES borrow_requests(id) ON DELETE CASCADE
);

CREATE TABLE notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    email_expiry_alert BOOLEAN DEFAULT TRUE,
    email_low_stock_alert BOOLEAN DEFAULT TRUE,
    email_borrow_request BOOLEAN DEFAULT TRUE,
    email_borrow_reminder BOOLEAN DEFAULT TRUE,
    email_safety_alert BOOLEAN DEFAULT TRUE,
    email_daily_digest BOOLEAN DEFAULT FALSE,
    email_weekly_report BOOLEAN DEFAULT TRUE,
    push_expiry_alert BOOLEAN DEFAULT TRUE,
    push_low_stock_alert BOOLEAN DEFAULT TRUE,
    push_borrow_request BOOLEAN DEFAULT TRUE,
    push_safety_alert BOOLEAN DEFAULT TRUE,
    expiry_alert_days INT DEFAULT 30,
    low_stock_threshold DECIMAL(5, 2) DEFAULT 20.00,
    borrow_reminder_days INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 8. AUDIT LOG & COMPLIANCE
-- =====================================================

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE compliance_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    check_type VARCHAR(100) NOT NULL,
    lab_id INT,
    chemical_id INT,
    container_id INT,
    check_result ENUM('pass', 'fail', 'warning') NOT NULL,
    details JSON,
    checked_by INT,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT,
    resolution_notes TEXT,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE
);

-- =====================================================
-- 9. 3D MODELS & AR ASSETS
-- =====================================================

CREATE TABLE container_3d_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    container_type ENUM('bottle', 'vial', 'flask', 'canister', 'cylinder', 'ampoule', 'bag', 'other') NOT NULL,
    material ENUM('glass', 'plastic', 'metal', 'other') DEFAULT 'glass',
    capacity_range_min DECIMAL(10, 2),
    capacity_range_max DECIMAL(10, 2),
    glb_file_path VARCHAR(500),
    usdz_file_path VARCHAR(500),
    thumbnail_url VARCHAR(500),
    is_default BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE ar_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    container_id INT NOT NULL,
    user_id INT NOT NULL,
    device_info JSON,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    interactions JSON,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX idx_containers_qr ON containers(qr_code);
CREATE INDEX idx_containers_chemical ON containers(chemical_id);
CREATE INDEX idx_containers_owner ON containers(owner_id);
CREATE INDEX idx_containers_location ON containers(location_slot_id);
CREATE INDEX idx_containers_status ON containers(status);
CREATE INDEX idx_containers_expiry ON containers(expiry_date);
CREATE INDEX idx_containers_active ON containers(is_active);
CREATE INDEX idx_chemicals_cas ON chemicals(cas_number);
CREATE INDEX idx_chemicals_name ON chemicals(name);
CREATE INDEX idx_borrow_requester ON borrow_requests(requester_id);
CREATE INDEX idx_borrow_status ON borrow_requests(status);
CREATE INDEX idx_borrow_dates ON borrow_requests(expected_return_date);
CREATE INDEX idx_alerts_user ON alerts(user_id, is_read);
CREATE INDEX idx_alerts_type ON alerts(alert_type, created_at);
CREATE INDEX idx_audit_table ON audit_logs(table_name, record_id);
CREATE INDEX idx_container_history ON container_history(container_id, created_at);

-- =====================================================
-- INITIAL DATA - Roles
-- =====================================================

INSERT INTO roles (name, display_name, description, permissions, level) VALUES
('admin', 'Administrator', 'Full system access', '{"all": true}', 5),
('ceo', 'CEO/Director', 'Organization-wide oversight', '{"dashboard": {"org_wide": true}, "reports": {"all": true}, "users": {"view": true}, "labs": {"view": true}}', 4),
('lab_manager', 'Lab Manager', 'Manage lab and team members', '{"dashboard": {"lab_wide": true}, "chemicals": {"manage": true}, "containers": {"manage": true}, "users": {"team_manage": true}, "borrow_requests": {"approve": true}}', 3),
('user', 'Lab User', 'Standard lab user', '{"dashboard": {"personal": true}, "chemicals": {"view": true, "request": true}, "containers": {"own": true}, "borrow_requests": {"create": true}}', 2),
('visitor', 'Visitor', 'Read-only public access', '{"chemicals": {"view_public": true}, "sds": {"view": true}}', 1);

-- =====================================================
-- SAMPLE DATA - Organizations
-- =====================================================

INSERT INTO organizations (id, name, description, address, phone, email) VALUES
(1, 'มหาวิทยาลัยเทคโนโลยีแห่งประเทศไทย', 'มหาวิทยาลัยชั้นนำด้านวิทยาศาสตร์และเทคโนโลยี', 'ถนนพระราม 1, กรุงเทพฯ', '02-123-4567', 'admin@tu.ac.th'),
(2, 'บริษัท ไทยเคมีภัณฑ์ จำกัด', 'บริษัทผลิตและจำหน่ายสารเคมีอุตสาหกรรม', 'นิคมอุตสาหกรรมระยอง', '038-123-456', 'contact@thaichem.co.th');

-- =====================================================
-- SAMPLE DATA - Users
-- Password for all: 123 (bcrypt hash)
-- =====================================================

INSERT INTO users (id, organization_id, role_id, lab_id, manager_id, username, email, password_hash, first_name, last_name, phone, department, position, theme_preference, language, email_verified, is_active) VALUES
(1, 1, 1, NULL, NULL, 'admin1', 'admin1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมชาย', 'แอดมิน', '081-111-1111', 'ไอที', 'ผู้ดูแลระบบ', 'light', 'th', 1, 1),
(2, 1, 1, NULL, NULL, 'admin2', 'admin2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมหญิง', 'แอดมิน', '081-111-1112', 'ไอที', 'ผู้ดูแลระบบ', 'dark', 'th', 1, 1),
(3, 1, 2, NULL, NULL, 'ceo1', 'ceo1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ประธาน', 'หนึ่ง', '082-222-2221', 'บริหาร', 'ประธานเจ้าหน้าที่บริหาร', 'light', 'th', 1, 1),
(4, 2, 2, NULL, NULL, 'ceo2', 'ceo2@thaichem.co.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ประธาน', 'สอง', '082-222-2222', 'บริหาร', 'ประธานเจ้าหน้าที่บริหาร', 'light', 'th', 1, 1);

-- =====================================================
-- SAMPLE DATA - Labs (must exist before lab managers reference them)
-- =====================================================

INSERT INTO labs (id, organization_id, name, code, description) VALUES
(1, 1, 'ห้องปฏิบัติการเคมีอินทรีย์', 'ORG-001', 'ห้องปฏิบัติการสำหรับการทดลองเคมีอินทรีย์'),
(2, 1, 'ห้องปฏิบัติการเคมีอนินทรีย์', 'INORG-001', 'ห้องปฏิบัติการสำหรับการทดลองเคมีอนินทรีย์'),
(3, 1, 'ห้องปฏิบัติการชีวเคมี', 'BIOCHEM-001', 'ห้องปฏิบัติการสำหรับการทดลองชีวเคมี'),
(4, 1, 'ห้องปฏิบัติการวิเคราะห์', 'ANAL-001', 'ห้องปฏิบัติการสำหรับการวิเคราะห์สาร'),
(5, 2, 'ห้องปฏิบัติการ QC', 'QC-001', 'ห้องปฏิบัติการควบคุมคุณภาพ');

-- Lab Managers
INSERT INTO users (id, organization_id, role_id, lab_id, manager_id, username, email, password_hash, first_name, last_name, phone, department, position, theme_preference, language, email_verified, is_active) VALUES
(5, 1, 3, 1, NULL, 'lab1', 'lab1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'หนึ่ง', '083-333-3331', 'เคมี', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(6, 1, 3, 2, NULL, 'lab2', 'lab2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สอง', '083-333-3332', 'เคมี', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(7, 1, 3, 3, NULL, 'lab3', 'lab3@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สาม', '083-333-3333', 'ชีววิทยา', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(8, 1, 3, 4, NULL, 'lab4', 'lab4@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สี่', '083-333-3334', 'วิทยาศาสตร์', 'ผู้จัดการห้องปฏิบัติการ', 'dark', 'th', 1, 1);

-- Update labs with manager_id
UPDATE labs SET manager_id = 5 WHERE id = 1;
UPDATE labs SET manager_id = 6 WHERE id = 2;
UPDATE labs SET manager_id = 7 WHERE id = 3;
UPDATE labs SET manager_id = 8 WHERE id = 4;
UPDATE labs SET manager_id = 5 WHERE id = 5;

-- Regular Users
INSERT INTO users (id, organization_id, role_id, lab_id, manager_id, username, email, password_hash, first_name, last_name, phone, department, position, theme_preference, language, email_verified, is_active) VALUES
(9, 1, 4, 1, 5, 'user1', 'user1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'หนึ่ง', '084-444-4441', 'เคมี', 'นักวิจัย', 'light', 'th', 1, 1),
(10, 1, 4, 1, 5, 'user2', 'user2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สอง', '084-444-4442', 'เคมี', 'นักวิจัย', 'light', 'en', 1, 1),
(11, 1, 4, 2, 6, 'user3', 'user3@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สาม', '084-444-4443', 'เคมี', 'นักวิจัย', 'dark', 'th', 1, 1),
(12, 1, 4, 2, 6, 'user4', 'user4@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สี่', '084-444-4444', 'เคมี', 'นักวิทยาศาสตร์', 'light', 'th', 1, 1),
(13, 1, 4, 3, 7, 'user5', 'user5@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'ห้า', '084-444-4445', 'ชีววิทยา', 'นักชีวเคมี', 'light', 'th', 1, 1),
(14, 1, 4, 3, 7, 'user6', 'user6@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'หก', '084-444-4446', 'ชีววิทยา', 'นักชีวเคมี', 'dark', 'en', 1, 1);

-- Notification settings for all users
INSERT INTO notification_settings (user_id) SELECT id FROM users;

-- =====================================================
-- SAMPLE DATA - Categories, Chemicals, Locations, Containers
-- (Same as sample_data.sql but without duplicate users/orgs)
-- =====================================================

INSERT INTO chemical_categories (id, name, description, parent_id) VALUES
(1, 'กรด', 'สารเคมีที่มีสภาพเป็นกรด', NULL),
(2, 'เบส', 'สารเคมีที่มีสภาพเป็นเบส', NULL),
(3, 'ตัวทำละลาย', 'สารที่ใช้ละลายสารอื่น', NULL),
(4, 'สารเคมีอินทรีย์', 'สารเคมีที่มีคาร์บอนเป็นส่วนประกอบหลัก', NULL),
(5, 'สารเคมีอนินทรีย์', 'สารเคมีที่ไม่มีคาร์บอน', NULL),
(6, 'สารละลายบัฟเฟอร์', 'สารละลายที่รักษาค่า pH', NULL),
(7, 'สารเคมีอันตราย', 'สารเคมีที่มีความอันตรายสูง', NULL);

INSERT INTO chemicals (id, cas_number, name, iupac_name, synonyms, molecular_formula, molecular_weight, description, category_id, physical_state, appearance, ghs_classifications, hazard_pictograms, signal_word, hazard_statements, precautionary_statements, handling_procedures, storage_requirements, created_by) VALUES
(1, '7647-01-0', 'กรดไฮโดรคลอริก', 'Hydrochloric acid', '["HCl", "Muriatic acid"]', 'HCl', 36.46, 'กรดแก่ที่ใช้กันอย่างแพร่หลาย', 1, 'liquid', 'ของเหลวใสไม่มีสี', '["Skin Corr. 1A", "STOT SE 3"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns and eye damage", "H335: May cause respiratory irritation"]', '["P280: Wear protective gloves", "P310: Immediately call a POISON CENTER"]', 'ใช้ในที่ระบายอากาศดี สวมอุปกรณ์ป้องกัน', 'เก็บในภาชนะที่ทนต่อกรด ปิดฝาให้สนิท', 1),
(2, '7664-93-9', 'กรดซัลฟิวริก', 'Sulfuric acid', '["H2SO4", "Oil of vitriol"]', 'H2SO4', 98.08, 'กรดแก่ที่ใช้ในอุตสาหกรรมมากที่สุด', 1, 'liquid', 'ของเหลวใสไม่มีสี', '["Skin Corr. 1A"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns and eye damage"]', '["P280: Wear protective gloves"]', 'เทกรดลงในน้ำอย่างช้าๆ', 'เก็บในภาชนะที่ทนต่อกรด', 1),
(3, '64-19-7', 'กรดแอซิติก', 'Acetic acid', '["CH3COOH", "Ethanoic acid"]', 'C2H4O2', 60.05, 'กรดอินทรีย์ที่ใช้กันอย่างแพร่หลาย', 1, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นฉุน', '["Flam. Liq. 3", "Skin Corr. 1A"]', '["flammable", "corrosive"]', 'Danger', '["H226: Flammable liquid and vapor", "H314: Causes severe skin burns"]', '["P210: Keep away from heat"]', 'หลีกเลี่ยงการสัมผัสกับผิวหนัง', 'เก็บในที่ระบายอากาศดี', 1),
(4, '1310-73-2', 'โซเดียมไฮดรอกไซด์', 'Sodium hydroxide', '["NaOH", "Caustic soda"]', 'NaOH', 40.00, 'เบสแก่ที่ใช้กันอย่างแพร่หลาย', 2, 'solid', 'ผลึกสีขาว', '["Skin Corr. 1A"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns and eye damage"]', '["P280: Wear protective gloves"]', 'สวมอุปกรณ์ป้องกันส่วนบุคคล', 'เก็บในภาชนะที่ปิดสนิท', 1),
(5, '1336-21-6', 'แอมโมเนียมไฮดรอกไซด์', 'Ammonium hydroxide', '["NH4OH", "Ammonia solution"]', 'NH5O', 35.05, 'สารละลายเบสอ่อน', 2, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นฉุน', '["Skin Corr. 1B", "STOT SE 3"]', '["corrosive"]', 'Danger', '["H314: Causes severe skin burns", "H335: May cause respiratory irritation"]', '["P280: Wear protective gloves"]', 'ใช้ในที่ระบายอากาศดี', 'เก็บในภาชนะที่ปิดสนิท ในที่เย็น', 1),
(6, '67-64-1', 'อะซิโตน', 'Acetone', '["Propanone", "Dimethyl ketone"]', 'C3H6O', 58.08, 'ตัวทำละลายอินทรีย์', 3, 'liquid', 'ของเหลวใสไม่มีสี', '["Flam. Liq. 2", "Eye Irrit. 2"]', '["flammable"]', 'Danger', '["H225: Highly flammable liquid and vapor"]', '["P210: Keep away from heat"]', 'ห่างจากแหล่งจุดระเบิด', 'เก็บในภาชนะที่ปิดสนิท', 1),
(7, '64-17-5', 'เอทานอล', 'Ethanol', '["Ethyl alcohol", "Alcohol"]', 'C2H6O', 46.07, 'แอลกอฮอล์ที่ใช้ทั่วไป', 3, 'liquid', 'ของเหลวใสไม่มีสี', '["Flam. Liq. 2"]', '["flammable"]', 'Danger', '["H225: Highly flammable liquid and vapor"]', '["P210: Keep away from heat"]', 'ห่างจากแหล่งจุดระเบิด', 'เก็บในภาชนะที่ปิดสนิท', 1),
(8, '67-63-0', 'ไอโซโพรพานอล', 'Isopropyl alcohol', '["IPA", "Isopropanol"]', 'C3H8O', 60.10, 'แอลกอฮอล์ทำความสะอาด', 3, 'liquid', 'ของเหลวใสไม่มีสี', '["Flam. Liq. 2", "Eye Irrit. 2"]', '["flammable"]', 'Danger', '["H225: Highly flammable liquid and vapor"]', '["P210: Keep away from heat"]', 'ใช้ในที่ระบายอากาศดี', 'เก็บในภาชนะที่ปิดสนิท', 1),
(9, '75-09-2', 'ไดคลอโรเมเทน', 'Dichloromethane', '["Methylene chloride", "DCM"]', 'CH2Cl2', 84.93, 'ตัวทำละลายคลอรินเนต', 3, 'liquid', 'ของเหลวใสไม่มีสี', '["Carc. 2", "STOT SE 3"]', '["health_hazard"]', 'Danger', '["H351: Suspected of causing cancer"]', '["P280: Wear protective gloves"]', 'ใช้ในตู้ดูดควันเท่านั้น', 'เก็บในตู้ดูดควัน ปิดฝาให้สนิท', 1),
(10, '50-00-0', 'ฟอร์มาลดีไฮด์', 'Formaldehyde', '["Formalin", "Methanal"]', 'CH2O', 30.03, 'สารถนอม', 4, 'liquid', 'ของเหลวใสไม่มีสี มีกลิ่นฉุน', '["Carc. 1B", "Acute Tox. 3"]', '["toxic", "corrosive", "health_hazard"]', 'Danger', '["H301: Toxic if swallowed", "H350: May cause cancer"]', '["P201: Obtain special instructions"]', 'ใช้ในตู้ดูดควันเท่านั้น', 'เก็บในตู้ดูดควัน ที่อุณหภูมิห้อง', 1),
(11, '108-88-3', 'โทลูอีน', 'Toluene', '["Methylbenzene"]', 'C7H8', 92.14, 'ตัวทำละลายอะรอมาติก', 4, 'liquid', 'ของเหลวใสไม่มีสี', '["Flam. Liq. 2", "Repr. 2"]', '["flammable", "health_hazard"]', 'Danger', '["H225: Highly flammable liquid"]', '["P210: Keep away from heat"]', 'ห่างจากแหล่งจุดระเบิด', 'เก็บในภาชนะที่ปิดสนิท', 1),
(12, '7447-40-7', 'โพแทสเซียมคลอไรด์', 'Potassium chloride', '["KCl", "Potash"]', 'KCl', 74.55, 'เกลือในห้องปฏิบัติการ', 5, 'solid', 'ผลึกสีขาว', '[]', '[]', 'No signal word', '[]', '["P264: Wash thoroughly after handling"]', 'หลีกเลี่ยงการสูดดมฝุ่น', 'เก็บในที่แห้ง', 1),
(13, '7647-14-5', 'โซเดียมคลอไรด์', 'Sodium chloride', '["NaCl", "Table salt"]', 'NaCl', 58.44, 'เกลือ', 5, 'solid', 'ผลึกสีขาว', '[]', '[]', 'No signal word', '[]', '["P264: Wash thoroughly after handling"]', 'หลีกเลี่ยงการสูดดมฝุ่น', 'เก็บในที่แห้ง', 1),
(14, '9005-49-6', 'HEPES', '4-(2-hydroxyethyl)-1-piperazineethanesulfonic acid', '["HEPES buffer"]', 'C8H18N2O4S', 238.30, 'บัฟเฟอร์ชีวภาพ', 6, 'solid', 'ผงสีขาว', '[]', '[]', 'No signal word', '[]', '["P264: Wash thoroughly after handling"]', 'หลีกเลี่ยงการสูดดมฝุ่น', 'เก็บในที่แห้งเย็น', 1),
(15, '7782-50-5', 'คลอรีน', 'Chlorine', '["Cl2"]', 'Cl2', 70.91, 'ก๊าซพิษฆ่าเชื้อ', 7, 'gas', 'ก๊าซสีเหลืองอ่อน', '["Ox. Gas 1", "Acute Tox. 2"]', '["oxidizing", "toxic", "corrosive"]', 'Danger', '["H270: May cause or intensify fire", "H330: Fatal if inhaled"]', '["P260: Do not breathe gas"]', 'ใช้ในตู้ดูดควันเท่านั้น', 'เก็บในภาชนะพิเศษสำหรับก๊าซ', 1),
(16, '7664-41-7', 'แอมโมเนีย', 'Ammonia', '["NH3"]', 'NH3', 17.03, 'ก๊าซในอุตสาหกรรม', 7, 'gas', 'ก๊าซไม่มีสี มีกลิ่นฉุน', '["Acute Tox. 3", "Skin Corr. 1B"]', '["toxic", "corrosive"]', 'Danger', '["H331: Toxic if inhaled"]', '["P260: Do not breathe gas"]', 'ใช้ในตู้ดูดควันเท่านั้น', 'เก็บในภาชนะพิเศษสำหรับก๊าซ', 1),
(17, '75-15-0', 'คาร์บอนไดซัลไฟด์', 'Carbon disulfide', '["CS2"]', 'CS2', 76.14, 'ตัวทำละลายอันตราย', 7, 'liquid', 'ของเหลวใสไม่มีสี', '["Flam. Liq. 1", "Acute Tox. 3"]', '["flammable", "toxic", "health_hazard"]', 'Danger', '["H224: Extremely flammable liquid"]', '["P210: Keep away from heat"]', 'ห่างจากแหล่งจุดระเบิดอย่างเคร่งครัด', 'เก็บในตู้ดูดควัน', 1);

-- Buildings
INSERT INTO buildings (id, organization_id, name, code, address) VALUES
(1, 1, 'อาคารวิทยาศาสตร์ 1', 'SCI-01', 'ถนนพระราม 1, กรุงเทพฯ'),
(2, 1, 'อาคารวิทยาศาสตร์ 2', 'SCI-02', 'ถนนพระราม 1, กรุงเทพฯ'),
(3, 2, 'อาคารโรงงานผลิต', 'FACT-01', 'นิคมอุตสาหกรรมระยอง');

-- Rooms
INSERT INTO rooms (id, building_id, lab_id, name, code, room_number, floor, safety_level, temperature_controlled, humidity_controlled) VALUES
(1, 1, 1, 'ห้องปฏิบัติการเคมีอินทรีย์ 101', 'ORG-101', '101', 1, 'chemical', 1, 0),
(2, 1, 1, 'ห้องเก็บสารเคมี', 'CHEM-STORE', '102', 1, 'chemical', 1, 0),
(3, 1, 2, 'ห้องปฏิบัติการเคมีอนินทรีย์ 201', 'INORG-201', '201', 2, 'chemical', 1, 0),
(4, 2, 3, 'ห้องปฏิบัติการชีวเคมี 301', 'BIOCHEM-301', '301', 3, 'biohazard', 1, 1),
(5, 2, 4, 'ห้องวิเคราะห์ 401', 'ANAL-401', '401', 4, 'chemical', 1, 0),
(6, 3, 5, 'ห้อง QC 101', 'QC-101', '101', 1, 'chemical', 1, 0);

-- Cabinets
INSERT INTO cabinets (id, room_id, name, code, type, capacity, ventilation, fire_resistant) VALUES
(1, 1, 'ตู้เก็บสารไวไฟ', 'CAB-FLAM-01', 'safety_cabinet', 4, 1, 1),
(2, 1, 'ตู้เก็บสารกัดกร่อน', 'CAB-CORR-01', 'storage', 5, 0, 0),
(3, 1, 'ตู้ดูดควัน A', 'FUME-A01', 'fume_hood', 3, 1, 0),
(4, 2, 'ตู้เย็นเก็บสาร', 'FRIDGE-01', 'refrigerator', 6, 0, 0),
(5, 2, 'ตู้เก็บสารทั่วไป A', 'CAB-GEN-01', 'storage', 8, 0, 0),
(6, 2, 'ตู้เก็บสารทั่วไป B', 'CAB-GEN-02', 'storage', 8, 0, 0),
(7, 3, 'ตู้ดูดควัน B', 'FUME-B01', 'fume_hood', 3, 1, 0),
(8, 3, 'ตู้เก็บสารอนินทรีย์', 'CAB-INORG-01', 'storage', 6, 0, 0),
(9, 4, 'ตู้เย็นชีวเคมี', 'FRIDGE-BIO', 'refrigerator', 4, 0, 0),
(10, 4, 'ตู้ดูดควัน C', 'FUME-C01', 'fume_hood', 3, 1, 0),
(11, 5, 'ตู้เก็บสารวิเคราะห์', 'CAB-ANAL-01', 'storage', 10, 0, 0),
(12, 6, 'ตู้ QC', 'CAB-QC-01', 'storage', 8, 0, 0);

-- Shelves
INSERT INTO shelves (id, cabinet_id, name, code, level, capacity) VALUES
(1, 1, 'ชั้นบน', 'SH-FLAM-01-A', 1, 10),
(2, 1, 'ชั้นกลาง', 'SH-FLAM-01-B', 2, 10),
(3, 1, 'ชั้นล่าง', 'SH-FLAM-01-C', 3, 10),
(4, 2, 'ชั้นบน', 'SH-CORR-01-A', 1, 12),
(5, 2, 'ชั้นกลาง', 'SH-CORR-01-B', 2, 12),
(6, 2, 'ชั้นล่าง', 'SH-CORR-01-C', 3, 12),
(7, 4, 'ชั้นบน', 'SH-FRIDGE-01-A', 1, 8),
(8, 4, 'ชั้นล่าง', 'SH-FRIDGE-01-B', 2, 8),
(9, 5, 'ชั้น 1', 'SH-GEN-01-A', 1, 15),
(10, 5, 'ชั้น 2', 'SH-GEN-01-B', 2, 15),
(11, 5, 'ชั้น 3', 'SH-GEN-01-C', 3, 15),
(12, 5, 'ชั้น 4', 'SH-GEN-01-D', 4, 15),
(13, 6, 'ชั้น 1', 'SH-GEN-02-A', 1, 15),
(14, 6, 'ชั้น 2', 'SH-GEN-02-B', 2, 15),
(15, 6, 'ชั้น 3', 'SH-GEN-02-C', 3, 15),
(16, 6, 'ชั้น 4', 'SH-GEN-02-D', 4, 15),
(17, 8, 'ชั้นบน', 'SH-INORG-01-A', 1, 12),
(18, 8, 'ชั้นกลาง', 'SH-INORG-01-B', 2, 12),
(19, 8, 'ชั้นล่าง', 'SH-INORG-01-C', 3, 12),
(20, 9, 'ชั้นบน', 'SH-BIO-01-A', 1, 10),
(21, 9, 'ชั้นล่าง', 'SH-BIO-01-B', 2, 10),
(22, 11, 'ชั้น 1', 'SH-ANAL-01-A', 1, 15),
(23, 11, 'ชั้น 2', 'SH-ANAL-01-B', 2, 15),
(24, 11, 'ชั้น 3', 'SH-ANAL-01-C', 3, 15),
(25, 12, 'ชั้น 1', 'SH-QC-01-A', 1, 12),
(26, 12, 'ชั้น 2', 'SH-QC-01-B', 2, 12);

-- Slots
INSERT INTO slots (id, shelf_id, name, code, position) VALUES
(1, 1, 'ช่อง A1', 'SL-FLAM-01-A1', 1),
(2, 1, 'ช่อง A2', 'SL-FLAM-01-A2', 2),
(3, 1, 'ช่อง A3', 'SL-FLAM-01-A3', 3),
(4, 1, 'ช่อง A4', 'SL-FLAM-01-A4', 4),
(5, 1, 'ช่อง A5', 'SL-FLAM-01-A5', 5),
(6, 2, 'ช่อง B1', 'SL-FLAM-01-B1', 1),
(7, 2, 'ช่อง B2', 'SL-FLAM-01-B2', 2),
(8, 2, 'ช่อง B3', 'SL-FLAM-01-B3', 3),
(9, 2, 'ช่อง B4', 'SL-FLAM-01-B4', 4),
(10, 2, 'ช่อง B5', 'SL-FLAM-01-B5', 5),
(11, 9, 'ช่อง 1A', 'SL-GEN-01-1A', 1),
(12, 9, 'ช่อง 1B', 'SL-GEN-01-1B', 2),
(13, 9, 'ช่อง 1C', 'SL-GEN-01-1C', 3),
(14, 9, 'ช่อง 1D', 'SL-GEN-01-1D', 4),
(15, 9, 'ช่อง 1E', 'SL-GEN-01-1E', 5),
(16, 10, 'ช่อง 2A', 'SL-GEN-01-2A', 1),
(17, 10, 'ช่อง 2B', 'SL-GEN-01-2B', 2),
(18, 10, 'ช่อง 2C', 'SL-GEN-01-2C', 3),
(19, 10, 'ช่อง 2D', 'SL-GEN-01-2D', 4),
(20, 10, 'ช่อง 2E', 'SL-GEN-01-2E', 5),
(21, 17, 'ช่อง A1', 'SL-INORG-01-A1', 1),
(22, 17, 'ช่อง A2', 'SL-INORG-01-A2', 2),
(23, 17, 'ช่อง A3', 'SL-INORG-01-A3', 3),
(24, 17, 'ช่อง A4', 'SL-INORG-01-A4', 4),
(25, 20, 'ช่อง A1', 'SL-BIO-01-A1', 1),
(26, 20, 'ช่อง A2', 'SL-BIO-01-A2', 2),
(27, 20, 'ช่อง A3', 'SL-BIO-01-A3', 3),
(28, 20, 'ช่อง A4', 'SL-BIO-01-A4', 4),
(29, 22, 'ช่อง 1A', 'SL-ANAL-01-1A', 1),
(30, 22, 'ช่อง 1B', 'SL-ANAL-01-1B', 2),
(31, 22, 'ช่อง 1C', 'SL-ANAL-01-1C', 3),
(32, 22, 'ช่อง 1D', 'SL-ANAL-01-1D', 4),
(33, 25, 'ช่อง 1A', 'SL-QC-01-1A', 1),
(34, 25, 'ช่อง 1B', 'SL-QC-01-1B', 2),
(35, 25, 'ช่อง 1C', 'SL-QC-01-1C', 3),
(36, 25, 'ช่อง 1D', 'SL-QC-01-1D', 4);

-- Containers
INSERT INTO containers (id, qr_code, chemical_id, owner_id, lab_id, location_slot_id, container_type, container_material, container_capacity, capacity_unit, initial_quantity, current_quantity, quantity_unit, received_date, opened_date, expiry_date, status, batch_number, cost, created_by) VALUES
(1, 'CHEM-20240214-001A', 1, 9, 1, 2, 'bottle', 'glass', 1000, 'mL', 1000.00, 850.00, 'mL', '2024-01-15', '2024-01-20', '2026-01-15', 'active', 'HCL-2024-001', 450.00, 5),
(2, 'CHEM-20240214-001B', 1, 9, 1, 2, 'bottle', 'glass', 500, 'mL', 500.00, 120.00, 'mL', '2024-02-01', '2024-02-05', '2026-02-01', 'active', 'HCL-2024-002', 280.00, 5),
(3, 'CHEM-20240214-001C', 1, 10, 1, 2, 'bottle', 'plastic', 2500, 'mL', 2500.00, 2300.00, 'mL', '2024-01-10', NULL, '2026-01-10', 'active', 'HCL-2024-003', 950.00, 5),
(4, 'CHEM-20240214-002A', 2, 9, 1, 4, 'bottle', 'glass', 1000, 'mL', 1000.00, 920.00, 'mL', '2024-01-20', '2024-01-25', '2027-01-20', 'active', 'H2SO4-2024-001', 520.00, 5),
(5, 'CHEM-20240214-002B', 2, 11, 2, 21, 'bottle', 'glass', 500, 'mL', 500.00, 45.00, 'mL', '2023-12-15', '2024-01-05', '2026-12-15', 'active', 'H2SO4-2023-012', 280.00, 6),
(6, 'CHEM-20240214-003A', 3, 10, 1, 1, 'bottle', 'glass', 2500, 'mL', 2500.00, 1800.00, 'mL', '2024-02-01', '2024-02-10', '2026-02-01', 'active', 'ACETIC-2024-001', 1200.00, 5),
(7, 'CHEM-20240214-003B', 3, 10, 1, 1, 'bottle', 'plastic', 500, 'mL', 500.00, 15.00, 'mL', '2023-11-20', '2023-12-01', '2027-11-20', 'active', 'ACETIC-2023-015', 280.00, 5),
(8, 'CHEM-20240214-004A', 4, 11, 2, 22, 'bottle', 'plastic', 1000, 'g', 1000.00, 750.00, 'g', '2024-01-05', '2024-01-10', '2026-01-05', 'active', 'NAOH-2024-001', 380.00, 6),
(9, 'CHEM-20240214-004B', 4, 11, 2, 22, 'bottle', 'plastic', 500, 'g', 500.00, 480.00, 'g', '2024-02-10', NULL, '2026-02-10', 'active', 'NAOH-2024-002', 200.00, 6),
(10, 'CHEM-20240214-005A', 5, 9, 1, 3, 'bottle', 'plastic', 2500, 'mL', 2500.00, 1200.00, 'mL', '2024-01-25', '2024-02-01', '2027-01-25', 'active', 'NH4OH-2024-001', 850.00, 5),
(11, 'CHEM-20240214-006A', 6, 9, 1, 1, 'canister', 'metal', 20000, 'mL', 20000.00, 15000.00, 'mL', '2024-01-15', '2024-01-20', '2027-01-15', 'active', 'ACETONE-2024-001', 3500.00, 5),
(12, 'CHEM-20240214-006B', 6, 10, 1, 6, 'bottle', 'glass', 1000, 'mL', 1000.00, 320.00, 'mL', '2024-02-05', '2024-02-10', '2027-02-05', 'active', 'ACETONE-2024-002', 450.00, 5),
(13, 'CHEM-20240214-007A', 7, 9, 1, 1, 'canister', 'metal', 20000, 'mL', 20000.00, 18500.00, 'mL', '2024-01-20', '2024-01-25', '2027-01-20', 'active', 'ETOH-2024-001', 4200.00, 5),
(14, 'CHEM-20240214-007B', 7, 13, 3, 25, 'bottle', 'glass', 2500, 'mL', 2500.00, 2100.00, 'mL', '2024-02-01', '2024-02-05', '2027-02-01', 'active', 'ETOH-2024-002', 1200.00, 7),
(15, 'CHEM-20240214-008A', 8, 11, 2, 23, 'bottle', 'plastic', 4000, 'mL', 4000.00, 3800.00, 'mL', '2024-01-30', '2024-02-05', '2027-01-30', 'active', 'IPA-2024-001', 950.00, 6),
(16, 'CHEM-20240214-009A', 9, 9, 1, 7, 'bottle', 'glass', 1000, 'mL', 1000.00, 680.00, 'mL', '2024-01-10', '2024-01-15', '2027-01-10', 'active', 'DCM-2024-001', 650.00, 5),
(17, 'CHEM-20240214-010A', 10, 13, 3, 26, 'bottle', 'plastic', 1000, 'mL', 1000.00, 850.00, 'mL', '2024-01-05', '2024-01-10', '2027-01-05', 'active', 'FORM-2024-001', 1200.00, 7),
(18, 'CHEM-20240214-011A', 11, 9, 1, 1, 'bottle', 'glass', 1000, 'mL', 1000.00, 920.00, 'mL', '2024-01-15', '2024-01-20', '2027-01-15', 'active', 'TOLUENE-2024-001', 580.00, 5),
(19, 'CHEM-20240214-012A', 12, 11, 2, 24, 'bottle', 'plastic', 500, 'g', 500.00, 450.00, 'g', '2024-02-01', '2024-02-05', '2027-02-01', 'active', 'KCL-2024-001', 280.00, 6),
(20, 'CHEM-20240214-013A', 13, 13, 3, 27, 'bottle', 'plastic', 1000, 'g', 1000.00, 980.00, 'g', '2024-01-20', '2024-01-25', '2028-01-20', 'active', 'NACL-2024-001', 150.00, 7),
(21, 'CHEM-20240214-014A', 14, 13, 3, 28, 'bottle', 'plastic', 100, 'g', 100.00, 85.00, 'g', '2024-01-10', '2024-01-15', '2026-01-10', 'active', 'HEPES-2024-001', 2800.00, 7),
(22, 'CHEM-20240214-015A', 15, 11, 2, NULL, 'cylinder', 'metal', 50000, 'g', 50000.00, 35000.00, 'g', '2024-01-05', '2024-01-10', '2027-01-05', 'active', 'CL2-2024-001', 8500.00, 6),
(23, 'CHEM-20240214-016A', 16, 11, 2, NULL, 'cylinder', 'metal', 25000, 'g', 25000.00, 22000.00, 'g', '2024-01-15', '2024-01-20', '2027-01-15', 'active', 'NH3-2024-001', 6500.00, 6),
(24, 'CHEM-20240214-017A', 17, 9, 1, 8, 'bottle', 'glass', 500, 'mL', 500.00, 420.00, 'mL', '2024-01-20', '2024-01-25', '2027-01-20', 'active', 'CS2-2024-001', 1200.00, 5),
(25, 'CHEM-20240214-LOW1', 3, 10, 1, 1, 'bottle', 'glass', 500, 'mL', 500.00, 25.00, 'mL', '2023-06-15', '2023-07-01', '2027-06-15', 'active', 'ACETIC-2023-010', 280.00, 5),
(26, 'CHEM-20240214-LOW2', 6, 10, 1, 6, 'bottle', 'glass', 500, 'mL', 500.00, 18.00, 'mL', '2023-08-20', '2023-09-01', '2027-08-20', 'active', 'ACETONE-2023-020', 450.00, 5),
(27, 'CHEM-20240214-LOW3', 8, 11, 2, 23, 'bottle', 'plastic', 1000, 'mL', 1000.00, 32.00, 'mL', '2023-07-10', '2023-07-15', '2027-07-10', 'active', 'IPA-2023-015', 650.00, 6),
(28, 'CHEM-20240214-EXP1', 5, 9, 1, 3, 'bottle', 'plastic', 1000, 'mL', 1000.00, 850.00, 'mL', '2023-02-20', '2023-03-01', '2026-02-20', 'active', 'NH4OH-2023-005', 450.00, 5),
(29, 'CHEM-20240214-EXP2', 6, 9, 1, 1, 'canister', 'metal', 20000, 'mL', 20000.00, 12000.00, 'mL', '2023-02-15', '2023-02-20', '2026-02-15', 'active', 'ACETONE-2023-003', 3500.00, 5),
(30, 'CHEM-20240214-EXP3', 10, 13, 3, 26, 'bottle', 'plastic', 500, 'mL', 500.00, 420.00, 'mL', '2023-03-10', '2023-03-15', '2026-03-10', 'active', 'FORM-2023-008', 850.00, 7);

-- Container History
INSERT INTO container_history (container_id, action_type, user_id, quantity_change, quantity_after, notes, created_at) VALUES
(1, 'created', 5, NULL, 1000.00, 'รับเข้าคลัง', '2024-01-15 09:00:00'),
(1, 'used', 9, -50.00, 850.00, 'ใช้ในการทดลอง', '2024-02-01 10:30:00'),
(2, 'created', 5, NULL, 500.00, 'รับเข้าคลัง', '2024-02-01 09:00:00'),
(2, 'used', 9, -200.00, 120.00, 'ใช้ล้างเครื่องมือ', '2024-02-10 09:15:00'),
(4, 'used', 9, -80.00, 920.00, 'ใช้ในการทดลอง', '2024-02-03 11:00:00'),
(6, 'used', 10, -300.00, 1800.00, 'ใช้เป็น catalyst', '2024-02-08 13:45:00');

-- Borrow Requests
INSERT INTO borrow_requests (request_number, requester_id, owner_id, container_id, chemical_id, request_type, requested_quantity, quantity_unit, purpose, needed_by_date, expected_return_date, status, approved_by, approved_at, created_at) VALUES
('BRW-20260210-001', 10, 9, 1, 1, 'borrow', 100.00, 'mL', 'ใช้ในการทดลองสังเคราะห์สารประกอบ', '2026-02-20', '2026-02-25', 'pending', NULL, NULL, NOW()),
('BRW-20260212-002', 12, 11, 8, 4, 'borrow', 50.00, 'g', 'ใช้ในการทดลอง titration', '2026-02-18', '2026-02-22', 'pending', NULL, NULL, NOW()),
('BRW-20260213-003', 14, 13, 20, 13, 'borrow', 20.00, 'g', 'ใช้ในการทดลองเตรียม buffer', '2026-02-20', '2026-02-28', 'pending', NULL, NULL, NOW());

-- Alerts
INSERT INTO alerts (alert_type, severity, title, message, user_id, chemical_id, container_id, is_read, action_required, created_at) VALUES
('expiry', 'warning', 'สารเคมีใกล้หมดอายุ', 'แอมโมเนียมไฮดรอกไซด์ จะหมดอายุในอีก 6 วัน', 9, 5, 28, 0, 1, NOW()),
('low_stock', 'critical', 'สต็อกต่ำมาก', 'กรดแอซิติก (ACETIC-2023-010) เหลือเพียง 5%', 10, 3, 25, 0, 1, NOW()),
('low_stock', 'critical', 'สต็อกต่ำมาก', 'อะซิโตน (ACETONE-2023-020) เหลือเพียง 3.6%', 10, 6, 26, 0, 1, NOW()),
('low_stock', 'critical', 'สต็อกต่ำมาก', 'ไอโซโพรพานอล (IPA-2023-015) เหลือเพียง 3.2%', 11, 8, 27, 0, 1, NOW()),
('borrow_request', 'info', 'คำขอยืมใหม่', 'นักวิจัย สอง ขอยืม HCl 100 mL', 9, 1, 1, 0, 1, NOW());

-- AI Chat Sessions
INSERT INTO ai_chat_sessions (user_id, session_id, title, context, created_at) VALUES
(9, 'chat_demo_001', 'ค้นหาสารเคมี', '{"topic": "chemical_search"}', NOW());

INSERT INTO ai_chat_messages (session_id, role, content, referenced_chemicals, created_at) VALUES
('chat_demo_001', 'user', 'HCl อยู่ที่ไหน?', '[1]', NOW()),
('chat_demo_001', 'assistant', 'พบ HCl จำนวน 3 ภาชนะ ในตู้เก็บสารกัดกร่อน', '[1]', NOW());

-- =====================================================
-- DONE! Database is ready.
-- =====================================================
SELECT 'Database chem_inventory_db created successfully!' AS status;
SELECT COUNT(*) AS total_users FROM users;
SELECT COUNT(*) AS total_chemicals FROM chemicals;
SELECT COUNT(*) AS total_containers FROM containers;
