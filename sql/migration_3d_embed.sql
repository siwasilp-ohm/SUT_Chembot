-- =====================================================
-- 3D Model System — Embed / iFrame Support
-- Adds columns for external embed sources (Kiri Engine, Sketchfab, etc.)
-- + Iframe config settings in system_settings
-- =====================================================

-- ── Step 1: Add embed columns to packaging_3d_models ──
ALTER TABLE packaging_3d_models
    ADD COLUMN source_type ENUM('upload','embed') DEFAULT 'upload' COMMENT 'upload=file, embed=iframe/external' AFTER thumbnail_path,
    ADD COLUMN embed_url VARCHAR(2000) DEFAULT NULL COMMENT 'External embed URL (iframe src)' AFTER source_type,
    ADD COLUMN embed_code TEXT DEFAULT NULL COMMENT 'Full iframe HTML code' AFTER embed_url,
    ADD COLUMN embed_provider VARCHAR(100) DEFAULT NULL COMMENT 'Provider name: Kiri Engine, Sketchfab, etc.' AFTER embed_code;

-- ── Step 2: Index for embed queries ──
CREATE INDEX idx_p3d_source ON packaging_3d_models(source_type);
CREATE INDEX idx_p3d_provider ON packaging_3d_models(embed_provider(50));

-- ── Step 3: Update existing records to source_type=upload ──
UPDATE packaging_3d_models SET source_type = 'upload' WHERE source_type IS NULL;

-- ── Step 4: Insert iframe config settings ──
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, label, description) VALUES
    ('iframe_kiri_bg_theme', 'transparent', 'string', '3d_iframe', 'Kiri Engine Background Theme', 'ธีมพื้นหลังสำหรับ Kiri Engine embed: transparent, dark, light, gradient'),
    ('iframe_kiri_auto_spin', '1', 'string', '3d_iframe', 'Kiri Engine Auto Spin', 'เปิด/ปิดการหมุนอัตโนมัติของโมเดล Kiri Engine'),
    ('iframe_default_params', 'bg_theme=transparent&auto_spin_model=1', 'string', '3d_iframe', 'Default URL Parameters', 'พารามิเตอร์ที่จะต่อท้าย URL ของ iframe อัตโนมัติ'),
    ('iframe_default_attrs', 'frameborder="0" allowfullscreen mozallowfullscreen webkitallowfullscreen allow="autoplay; fullscreen;"', 'string', '3d_iframe', 'Default Iframe Attributes', 'คุณสมบัติเริ่มต้นสำหรับแท็ก iframe'),
    ('iframe_width', '640', 'string', '3d_iframe', 'Default Width (px)', 'ความกว้างเริ่มต้นของ iframe'),
    ('iframe_height', '480', 'string', '3d_iframe', 'Default Height (px)', 'ความสูงเริ่มต้นของ iframe')
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description);

-- ── Verify ──
-- DESCRIBE packaging_3d_models;
-- SELECT * FROM system_settings WHERE category = '3d_iframe';
