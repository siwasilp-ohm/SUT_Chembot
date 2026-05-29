-- =====================================================
-- AI-Driven Chemical Inventory Management System
-- Database Schema for MySQL 8.0+
-- =====================================================

CREATE DATABASE IF NOT EXISTS chem_inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
    expires_at TIMESTAMP NOT NULL,
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
-- =====================================================

CREATE TABLE containers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_code VARCHAR(255) UNIQUE,
    qr_code_image VARCHAR(500),
    chemical_id INT NOT NULL,
    owner_id INT NOT NULL,
    lab_id INT NOT NULL,
    -- Location
    location_slot_id INT,
    location_path VARCHAR(500) GENERATED ALWAYS AS (
        CONCAT_WS(' > ', 
            (SELECT b.name FROM buildings b JOIN rooms r ON r.building_id = b.id JOIN cabinets c ON c.room_id = r.id JOIN shelves s ON s.cabinet_id = c.id JOIN slots sl ON sl.shelf_id = s.id WHERE sl.id = location_slot_id),
            (SELECT r.name FROM rooms r JOIN cabinets c ON c.room_id = r.id JOIN shelves s ON s.cabinet_id = c.id JOIN slots sl ON sl.shelf_id = s.id WHERE sl.id = location_slot_id),
            (SELECT c.name FROM cabinets c JOIN shelves s ON s.cabinet_id = c.id JOIN slots sl ON sl.shelf_id = s.id WHERE sl.id = location_slot_id),
            (SELECT s.name FROM shelves s JOIN slots sl ON sl.shelf_id = s.id WHERE sl.id = location_slot_id),
            (SELECT sl.name FROM slots sl WHERE sl.id = location_slot_id)
        )
    ) STORED,
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
    remaining_percentage DECIMAL(5, 2) GENERATED ALWAYS AS (
        CASE 
            WHEN initial_quantity > 0 THEN (current_quantity / initial_quantity) * 100 
            ELSE 0 
        END
    ) STORED,
    -- Dates
    manufacture_date DATE,
    received_date DATE NOT NULL,
    opened_date DATE,
    expiry_date DATE,
    expiry_alert_days INT DEFAULT 30,
    -- Status
    status ENUM('active', 'empty', 'expired', 'quarantined', 'disposed', 'transferred') DEFAULT 'active',
    quality_status ENUM('good', 'questionable', 'contaminated', 'unknown') DEFAULT 'good',
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

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
    -- Quantity
    requested_quantity DECIMAL(12, 4) NOT NULL,
    quantity_unit ENUM('mL', 'L', 'g', 'kg', 'mg', 'oz', 'lb', 'units') DEFAULT 'mL',
    -- Purpose
    purpose TEXT NOT NULL,
    experiment_id VARCHAR(100),
    project_name VARCHAR(255),
    -- Timeline
    needed_by_date DATE,
    expected_return_date DATE,
    actual_return_date DATE,
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'fulfilled', 'partially_returned', 'returned', 'overdue', 'cancelled') DEFAULT 'pending',
    -- Approval
    approved_by INT,
    approved_at TIMESTAMP NULL,
    approval_notes TEXT,
    -- Fulfillment
    fulfilled_container_id INT,
    fulfilled_quantity DECIMAL(12, 4),
    fulfilled_by INT,
    fulfilled_at TIMESTAMP NULL,
    -- Return
    returned_quantity DECIMAL(12, 4),
    return_condition ENUM('good', 'damaged', 'contaminated', 'partially_used'),
    return_notes TEXT,
    -- Reminders
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
    -- Related entities
    user_id INT,
    chemical_id INT,
    container_id INT,
    lab_id INT,
    borrow_request_id INT,
    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    dismissed BOOLEAN DEFAULT FALSE,
    -- Actions
    action_required BOOLEAN DEFAULT FALSE,
    action_taken TEXT,
    action_taken_by INT,
    action_taken_at TIMESTAMP NULL,
    -- Notification channels
    email_sent BOOLEAN DEFAULT FALSE,
    push_sent BOOLEAN DEFAULT FALSE,
    sms_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
    -- borrow_request_id is a loose reference to chemical_transactions.id (no FK constraint)
);

CREATE TABLE notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    -- Email notifications
    email_expiry_alert BOOLEAN DEFAULT TRUE,
    email_low_stock_alert BOOLEAN DEFAULT TRUE,
    email_borrow_request BOOLEAN DEFAULT TRUE,
    email_borrow_reminder BOOLEAN DEFAULT TRUE,
    email_safety_alert BOOLEAN DEFAULT TRUE,
    email_daily_digest BOOLEAN DEFAULT FALSE,
    email_weekly_report BOOLEAN DEFAULT TRUE,
    -- Push notifications
    push_expiry_alert BOOLEAN DEFAULT TRUE,
    push_low_stock_alert BOOLEAN DEFAULT TRUE,
    push_borrow_request BOOLEAN DEFAULT TRUE,
    push_safety_alert BOOLEAN DEFAULT TRUE,
    -- Alert thresholds
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
-- INITIAL DATA
-- =====================================================

INSERT INTO roles (name, display_name, description, permissions, level) VALUES
('admin', 'Administrator', 'Full system access', '{"all": true}', 5),
('ceo', 'CEO/Director', 'Organization-wide oversight', '{"dashboard": {"org_wide": true}, "reports": {"all": true}, "users": {"view": true}, "labs": {"view": true}}', 4),
('lab_manager', 'Lab Manager', 'Manage lab and team members', '{"dashboard": {"lab_wide": true}, "chemicals": {"manage": true}, "containers": {"manage": true}, "users": {"team_manage": true}, "borrow_requests": {"approve": true}}', 3),
('user', 'Lab User', 'Standard lab user', '{"dashboard": {"personal": true}, "chemicals": {"view": true, "request": true}, "containers": {"own": true}, "borrow_requests": {"create": true}}', 2),
('visitor', 'Visitor', 'Read-only public access', '{"chemicals": {"view_public": true}, "sds": {"view": true}}', 1);

INSERT INTO organizations (id, name, description) VALUES
(1, 'มหาวิทยาลัยเทคโนโลยีแห่งประเทศไทย', 'มหาวิทยาลัยชั้นนำด้านวิทยาศาสตร์และเทคโนโลยี'),
(2, 'บริษัท ไทยเคมีภัณฑ์ จำกัด', 'บริษัทผลิตและจำหน่ายสารเคมีอุตสาหกรรม');

-- Sample Users (password: 123 for all)
-- Password hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (id, organization_id, role_id, lab_id, manager_id, username, email, password_hash, first_name, last_name, phone, department, position, theme_preference, language, email_verified, is_active) VALUES
-- Admin users
(1, 1, 1, NULL, NULL, 'admin1', 'admin1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมชาย', 'แอดมิน', '081-111-1111', 'ไอที', 'ผู้ดูแลระบบ', 'light', 'th', 1, 1),
(2, 1, 1, NULL, NULL, 'admin2', 'admin2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'สมหญิง', 'แอดมิน', '081-111-1112', 'ไอที', 'ผู้ดูแลระบบ', 'dark', 'th', 1, 1),

-- CEO users
(3, 1, 4, NULL, NULL, 'ceo1', 'ceo1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ประธาน', 'หนึ่ง', '082-222-2221', 'บริหาร', 'ประธานเจ้าหน้าที่บริหาร', 'light', 'th', 1, 1),
(4, 2, 4, NULL, NULL, 'ceo2', 'ceo2@thaichem.co.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ประธาน', 'สอง', '082-222-2222', 'บริหาร', 'ประธานเจ้าหน้าที่บริหาร', 'light', 'th', 1, 1),

-- Lab Managers
(5, 1, 3, 1, NULL, 'lab1', 'lab1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'หนึ่ง', '083-333-3331', 'เคมี', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(6, 1, 3, 2, NULL, 'lab2', 'lab2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สอง', '083-333-3332', 'เคมี', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(7, 1, 3, 3, NULL, 'lab3', 'lab3@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สาม', '083-333-3333', 'ชีววิทยา', 'ผู้จัดการห้องปฏิบัติการ', 'light', 'th', 1, 1),
(8, 1, 3, 4, NULL, 'lab4', 'lab4@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้จัดการ', 'สี่', '083-333-3334', 'วิทยาศาสตร์', 'ผู้จัดการห้องปฏิบัติการ', 'dark', 'th', 1, 1),

-- Regular Users (Lab 1 - Organic Chemistry)
(9, 1, 2, 1, 5, 'user1', 'user1@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'หนึ่ง', '084-444-4441', 'เคมี', 'นักวิจัย', 'light', 'th', 1, 1),
(10, 1, 2, 1, 5, 'user2', 'user2@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สอง', '084-444-4442', 'เคมี', 'นักวิจัย', 'light', 'en', 1, 1),

-- Regular Users (Lab 2 - Inorganic Chemistry)
(11, 1, 2, 2, 6, 'user3', 'user3@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สาม', '084-444-4443', 'เคมี', 'นักวิจัย', 'dark', 'th', 1, 1),
(12, 1, 2, 2, 6, 'user4', 'user4@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'สี่', '084-444-4444', 'เคมี', 'นักวิทยาศาสตร์', 'light', 'th', 1, 1),

-- Regular Users (Lab 3 - Biochemistry)
(13, 1, 2, 3, 7, 'user5', 'user5@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'ห้า', '084-444-4445', 'ชีววิทยา', 'นักชีวเคมี', 'light', 'th', 1, 1),
(14, 1, 2, 3, 7, 'user6', 'user6@tu.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นักวิจัย', 'หก', '084-444-4446', 'ชีววิทยา', 'นักชีวเคมี', 'dark', 'en', 1, 1);
