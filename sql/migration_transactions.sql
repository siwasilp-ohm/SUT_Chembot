-- =====================================================
-- Migration: Chemical Transaction Lifecycle System
-- Tracks borrow, return, transfer, dispose for all chemicals
-- Follows bottle_code / qr_code barcode tag throughout lifecycle
-- =====================================================

-- 1. Transaction ledger — every movement of a chemical item
CREATE TABLE IF NOT EXISTS chemical_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    txn_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'TXN-YYYYMMDD-XXXXXX',
    
    -- What
    source_type ENUM('container','stock') NOT NULL COMMENT 'containers or chemical_stock',
    source_id INT NOT NULL COMMENT 'ID in containers or chemical_stock table',
    chemical_id INT NOT NULL,
    barcode VARCHAR(100) COMMENT 'bottle_code or qr_code — the physical tag',
    
    -- Type of transaction
    txn_type ENUM('borrow','return','transfer','dispose','adjust','receive','use') NOT NULL,
    
    -- Who
    from_user_id INT NOT NULL COMMENT 'The person giving / owning',
    to_user_id INT COMMENT 'The person receiving (NULL for dispose)',
    initiated_by INT NOT NULL COMMENT 'Who created this txn',
    
    -- Quantity
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    balance_after DECIMAL(14,4) COMMENT 'Remaining in source after txn',
    
    -- Context
    purpose TEXT,
    project_name VARCHAR(255),
    
    -- Location
    from_building_id INT,
    from_department VARCHAR(255),
    to_building_id INT,
    to_department VARCHAR(255),
    
    -- Approval (for borrow/transfer)
    requires_approval TINYINT(1) DEFAULT 0,
    status ENUM('pending','approved','rejected','completed','cancelled') DEFAULT 'completed',
    approved_by INT,
    approved_at DATETIME,
    approval_notes TEXT,
    
    -- Return tracking (for borrow type)
    parent_txn_id INT COMMENT 'Links return txn to its borrow txn',
    expected_return_date DATE,
    actual_return_date DATE,
    return_condition ENUM('good','damaged','contaminated','partially_used','expired'),
    
    -- Disposal (for dispose type)
    disposal_reason TEXT,
    disposal_method VARCHAR(100),
    disposal_approved_by INT,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_txn_source (source_type, source_id),
    INDEX idx_txn_barcode (barcode),
    INDEX idx_txn_type (txn_type),
    INDEX idx_txn_status (status),
    INDEX idx_txn_from (from_user_id),
    INDEX idx_txn_to (to_user_id),
    INDEX idx_txn_chemical (chemical_id),
    INDEX idx_txn_parent (parent_txn_id),
    INDEX idx_txn_building_from (from_building_id),
    INDEX idx_txn_building_to (to_building_id),
    INDEX idx_txn_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Disposal bin — items pending decommission (admin only)
CREATE TABLE IF NOT EXISTS disposal_bin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('container','stock') NOT NULL,
    source_id INT NOT NULL,
    chemical_id INT NOT NULL,
    barcode VARCHAR(100),
    chemical_name VARCHAR(500),
    remaining_qty DECIMAL(14,4) DEFAULT 0,
    unit VARCHAR(50),
    
    -- Who & why
    disposed_by INT NOT NULL,
    disposal_reason TEXT,
    disposal_method VARCHAR(100) COMMENT 'incineration, neutralization, return_to_vendor, etc',
    
    -- Origin info (snapshot at time of disposal)
    owner_name VARCHAR(255),
    department VARCHAR(255),
    building_name VARCHAR(255),
    storage_location VARCHAR(255),
    
    -- Status
    status ENUM('pending','approved','completed','rejected') DEFAULT 'pending',
    approved_by INT,
    approved_at DATETIME,
    completed_at DATETIME,
    completion_notes TEXT,
    
    -- Link to transaction
    txn_id INT COMMENT 'FK to chemical_transactions',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_disp_source (source_type, source_id),
    INDEX idx_disp_status (status),
    INDEX idx_disp_chemical (chemical_id),
    INDEX idx_disp_barcode (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add 'disposed' status to chemical_stock if not present
-- ALTER TABLE chemical_stock MODIFY COLUMN status ENUM('active','low','empty','expired','disposed') DEFAULT 'active';
