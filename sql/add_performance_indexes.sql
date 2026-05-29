-- =====================================================
-- Performance Indexes for SUT chemBot
-- Run this script to add performance indexes
-- =====================================================

USE chem_inventory_db;

-- =====================================================
-- 1. USER MANAGEMENT INDEXES
-- =====================================================

-- Users table indexes
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role_id ON users(role_id);
CREATE INDEX idx_users_lab_id ON users(lab_id);
CREATE INDEX idx_users_is_active ON users(is_active);
CREATE INDEX idx_users_last_login ON users(last_login);
CREATE INDEX idx_users_api_token ON users(api_token);

-- User sessions indexes
CREATE INDEX idx_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_sessions_token ON user_sessions(session_token);
CREATE INDEX idx_sessions_expires ON user_sessions(expires_at);

-- =====================================================
-- 2. CHEMICAL MANAGEMENT INDEXES
-- =====================================================

-- Chemicals table indexes
CREATE INDEX idx_chemicals_cas_number ON chemicals(cas_number);
CREATE INDEX idx_chemicals_name ON chemicals(name);
CREATE INDEX idx_chemicals_category_id ON chemicals(category_id);
CREATE INDEX idx_chemicals_manufacturer_id ON chemicals(manufacturer_id);
CREATE INDEX idx_chemicals_physical_state ON chemicals(physical_state);
CREATE INDEX idx_chemicals_is_active ON chemicals(is_active);
CREATE INDEX idx_chemicals_verified ON chemicals(verified);
CREATE INDEX idx_chemicals_created_at ON chemicals(created_at);
CREATE INDEX idx_chemicals_substance_type ON chemicals(substance_type);
CREATE INDEX idx_chemicals_substance_category ON chemicals(substance_category);
CREATE INDEX idx_chemicals_catalogue_number ON chemicals(catalogue_number);

-- Chemical categories
CREATE INDEX idx_categories_parent_id ON chemical_categories(parent_id);

-- Chemical suppliers
CREATE INDEX idx_suppliers_chemical_id ON chemical_suppliers(chemical_id);
CREATE INDEX idx_suppliers_supplier_name ON chemical_suppliers(supplier_name);

-- =====================================================
-- 3. CONTAINER MANAGEMENT INDEXES
-- =====================================================

-- Containers table indexes
CREATE INDEX idx_containers_qr_code ON containers(qr_code);
CREATE INDEX idx_containers_chemical_id ON containers(chemical_id);
CREATE INDEX idx_containers_owner_id ON containers(owner_id);
CREATE INDEX idx_containers_lab_id ON containers(lab_id);
CREATE INDEX idx_containers_location_slot_id ON containers(location_slot_id);
CREATE INDEX idx_containers_status ON containers(status);
CREATE INDEX idx_containers_expiry_date ON containers(expiry_date);
CREATE INDEX idx_containers_current_quantity ON containers(current_quantity);
CREATE INDEX idx_containers_batch_number ON containers(batch_number);
CREATE INDEX idx_containers_created_at ON containers(created_at);

-- Composite indexes for common queries
CREATE INDEX idx_containers_chemical_status ON containers(chemical_id, status);
CREATE INDEX idx_containers_lab_status ON containers(lab_id, status);
CREATE INDEX idx_containers_expiry_status ON containers(expiry_date, status);
CREATE INDEX idx_containers_owner_status ON containers(owner_id, status);

-- Container history indexes
CREATE INDEX idx_container_history_container_id ON container_history(container_id);
CREATE INDEX idx_container_history_user_id ON container_history(user_id);
CREATE INDEX idx_container_history_action_type ON container_history(action_type);
CREATE INDEX idx_container_history_created_at ON container_history(created_at);

-- =====================================================
-- 4. LOCATION MANAGEMENT INDEXES
-- =====================================================

-- Buildings
CREATE INDEX idx_buildings_org_id ON buildings(organization_id);

-- Rooms
CREATE INDEX idx_rooms_building_id ON rooms(building_id);
CREATE INDEX idx_rooms_lab_id ON rooms(lab_id);

-- Cabinets
CREATE INDEX idx_cabinets_room_id ON cabinets(room_id);
CREATE INDEX idx_cabinets_type ON cabinets(type);

-- Shelves
CREATE INDEX idx_shelves_cabinet_id ON shelves(cabinet_id);

-- Slots
CREATE INDEX idx_slots_shelf_id ON slots(shelf_id);

-- =====================================================
-- 5. BORROW/LOAN SYSTEM INDEXES
-- =====================================================

-- Borrow requests indexes
CREATE INDEX idx_borrow_requester_id ON borrow_requests(requester_id);
CREATE INDEX idx_borrow_owner_id ON borrow_requests(owner_id);
CREATE INDEX idx_borrow_container_id ON borrow_requests(container_id);
CREATE INDEX idx_borrow_chemical_id ON borrow_requests(chemical_id);
CREATE INDEX idx_borrow_status ON borrow_requests(status);
CREATE INDEX idx_borrow_needed_by_date ON borrow_requests(needed_by_date);
CREATE INDEX idx_borrow_expected_return ON borrow_requests(expected_return_date);
CREATE INDEX idx_borrow_created_at ON borrow_requests(created_at);
CREATE INDEX idx_borrow_request_number ON borrow_requests(request_number);

-- Composite indexes
CREATE INDEX idx_borrow_requester_status ON borrow_requests(requester_id, status);
CREATE INDEX idx_borrow_owner_status ON borrow_requests(owner_id, status);
CREATE INDEX idx_borrow_status_date ON borrow_requests(status, expected_return_date);

-- Transfers indexes
CREATE INDEX idx_transfers_container_id ON transfers(container_id);
CREATE INDEX idx_transfers_from_user ON transfers(from_user_id);
CREATE INDEX idx_transfers_to_user ON transfers(to_user_id);
CREATE INDEX idx_transfers_status ON transfers(status);
CREATE INDEX idx_transfers_transfer_number ON transfers(transfer_number);

-- =====================================================
-- 6. LAB MANAGEMENT INDEXES
-- =====================================================

-- Labs indexes
CREATE INDEX idx_labs_organization_id ON labs(organization_id);
CREATE INDEX idx_labs_manager_id ON labs(manager_id);
CREATE INDEX idx_labs_is_active ON labs(is_active);
CREATE INDEX idx_labs_code ON labs(code);

-- =====================================================
-- 7. AI & NOTIFICATION INDEXES
-- =====================================================

-- AI Chat sessions
CREATE INDEX idx_ai_sessions_user_id ON ai_chat_sessions(user_id);
CREATE INDEX idx_ai_sessions_session_id ON ai_chat_sessions(session_id);
CREATE INDEX idx_ai_sessions_updated ON ai_chat_sessions(updated_at);

-- AI Chat messages
CREATE INDEX idx_ai_messages_session_id ON ai_chat_messages(session_id);
CREATE INDEX idx_ai_messages_created_at ON ai_chat_messages(created_at);

-- Visual searches
CREATE INDEX idx_visual_searches_user_id ON visual_searches(user_id);
CREATE INDEX idx_visual_searches_created_at ON visual_searches(created_at);

-- Usage predictions
CREATE INDEX idx_predictions_chemical_id ON usage_predictions(chemical_id);
CREATE INDEX idx_predictions_lab_id ON usage_predictions(lab_id);
CREATE INDEX idx_predictions_date ON usage_predictions(prediction_date);

-- =====================================================
-- 8. ALERTS & AUDIT INDEXES
-- =====================================================

-- Alerts indexes
CREATE INDEX idx_alerts_user_id ON alerts(user_id);
CREATE INDEX idx_alerts_chemical_id ON alerts(chemical_id);
CREATE INDEX idx_alerts_container_id ON alerts(container_id);
CREATE INDEX idx_alerts_lab_id ON alerts(lab_id);
CREATE INDEX idx_alerts_type ON alerts(alert_type);
CREATE INDEX idx_alerts_severity ON alerts(severity);
CREATE INDEX idx_alerts_is_read ON alerts(is_read);
CREATE INDEX idx_alerts_created_at ON alerts(created_at);
CREATE INDEX idx_alerts_type_severity ON alerts(alert_type, severity);

-- Notification settings
CREATE INDEX idx_notif_settings_user_id ON notification_settings(user_id);

-- Audit logs indexes
CREATE INDEX idx_audit_table_record ON audit_logs(table_name, record_id);
CREATE INDEX idx_audit_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_action ON audit_logs(action);
CREATE INDEX idx_audit_created_at ON audit_logs(created_at);

-- =====================================================
-- 9. ADDITIONAL TABLES INDEXES
-- =====================================================

-- Organizations
CREATE INDEX idx_orgs_is_active ON organizations(is_active);

-- Roles
CREATE INDEX idx_roles_name ON roles(name);
CREATE INDEX idx_roles_level ON roles(level);

-- System settings
CREATE INDEX idx_system_settings_key ON system_settings(setting_key);

-- Chemical stock
CREATE INDEX idx_stock_chemical_id ON chemical_stock(chemical_id);
CREATE INDEX idx_stock_lab_id ON chemical_stock(lab_id);
CREATE INDEX idx_stock_location_id ON chemical_stock(location_id);

-- Chemical warehouses
CREATE INDEX idx_warehouses_chemical_id ON chemical_warehouses(chemical_id);
CREATE INDEX idx_warehouses_warehouse_id ON chemical_warehouses(warehouse_id);

-- Chemical transactions
CREATE INDEX idx_transactions_chemical_id ON chemical_transactions(chemical_id);
CREATE INDEX idx_transactions_type ON chemical_transactions(transaction_type);
CREATE INDEX idx_transactions_created_at ON chemical_transactions(created_at);

-- Lab stores
CREATE INDEX idx_lab_stores_lab_id ON lab_stores(lab_id);
CREATE INDEX idx_lab_stores_chemical_id ON lab_stores(chemical_id);

-- Disposal bin
CREATE INDEX idx_disposal_container_id ON disposal_bin(container_id);
CREATE INDEX idx_disposal_status ON disposal_bin(status);
CREATE INDEX idx_disposal_disposed_at ON disposal_bin(disposed_at);

-- Chemical SDS files
CREATE INDEX idx_sds_chemical_id ON chemical_sds_files(chemical_id);
CREATE INDEX idx_sds_uploaded_by ON chemical_sds_files(uploaded_by);

-- Chemical GHS data
CREATE INDEX idx_ghs_chemical_id ON chemical_ghs_data(chemical_id);

-- Chemical packaging
CREATE INDEX idx_packaging_chemical_id ON chemical_packaging(chemical_id);

-- =====================================================
-- 10. FULLTEXT INDEXES (MySQL 5.6+)
-- =====================================================

-- Fulltext search for chemicals
ALTER TABLE chemicals ADD FULLTEXT INDEX ft_chemicals_search (name, iupac_name, synonyms, description);

-- Fulltext search for containers
ALTER TABLE containers ADD FULLTEXT INDEX ft_containers_search (qr_code, batch_number, lot_number, notes);

-- Fulltext search for borrow requests
ALTER TABLE borrow_requests ADD FULLTEXT INDEX ft_borrow_purpose (purpose, project_name);

-- =====================================================
-- SHOW INDEXES REPORT
-- =====================================================

SELECT 
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'chem_inventory_db'
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;
