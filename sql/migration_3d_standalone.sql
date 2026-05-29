-- =====================================================
-- 3D Model System — STANDALONE (no VRX dependency)
-- Converts packaging_3d_models to self-contained storage
-- Run this AFTER migration_3d_models.sql
-- =====================================================

-- ── Step 1: Add self-contained file columns ──
ALTER TABLE packaging_3d_models
    ADD COLUMN file_path VARCHAR(500) DEFAULT NULL COMMENT 'Relative path in uploads/3d/' AFTER capacity_unit,
    ADD COLUMN file_url VARCHAR(1000) DEFAULT NULL COMMENT 'Full URL for browser access' AFTER file_path,
    ADD COLUMN original_name VARCHAR(255) DEFAULT NULL COMMENT 'Original uploaded filename' AFTER file_url,
    ADD COLUMN mime_type VARCHAR(100) DEFAULT NULL AFTER original_name,
    ADD COLUMN extension VARCHAR(20) DEFAULT NULL AFTER mime_type,
    ADD COLUMN file_size BIGINT UNSIGNED DEFAULT 0 AFTER extension,
    ADD COLUMN thumbnail_path VARCHAR(500) DEFAULT NULL COMMENT 'Thumbnail image path' AFTER file_size;

-- ── Step 2: Migrate VRX URL data to new columns (preserve existing data) ──
UPDATE packaging_3d_models 
SET file_url = vrx_file_url, 
    thumbnail_path = vrx_thumbnail_url
WHERE vrx_file_url IS NOT NULL 
  AND file_url IS NULL;

-- ── Step 3: Drop VRX-specific columns ──
ALTER TABLE packaging_3d_models
    DROP COLUMN IF EXISTS vrx_file_id,
    DROP COLUMN IF EXISTS vrx_file_uuid,
    DROP COLUMN IF EXISTS vrx_file_url,
    DROP COLUMN IF EXISTS vrx_thumbnail_url;

-- ── Step 4: Drop old VRX index if exists ──
-- (may need to run manually if index name differs)
-- ALTER TABLE packaging_3d_models DROP INDEX idx_p3d_vrx;

-- ── Step 5: Add new indexes ──
CREATE INDEX IF NOT EXISTS idx_p3d_file ON packaging_3d_models(file_path(191));

-- ── Verify final structure ──
-- DESCRIBE packaging_3d_models;
