-- ============================================================
-- VRX Studio — Complete Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- Run on phpMyAdmin or MySQL CLI:
--   mysql -u root -p < vrx_schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `vrx_studio`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `vrx_studio`;

-- ────────────────────────────────────────────
-- 1. Users & Authentication
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `uuid`            CHAR(36)        NOT NULL UNIQUE,
  `username`        VARCHAR(60)     NOT NULL UNIQUE,
  `email`           VARCHAR(255)    NOT NULL UNIQUE,
  `password_hash`   VARCHAR(255)    NOT NULL,
  `display_name`    VARCHAR(120)    DEFAULT NULL,
  `avatar_url`      VARCHAR(500)    DEFAULT NULL,
  `role`            ENUM('admin','editor','user','viewer','guest') NOT NULL DEFAULT 'viewer',
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `email_verified`  TINYINT(1)      NOT NULL DEFAULT 0,
  `last_login_at`   DATETIME        DEFAULT NULL,
  `login_count`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `storage_used`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'Bytes used',
  `storage_limit`   BIGINT UNSIGNED NOT NULL DEFAULT 536870912 COMMENT 'Default 512MB',
  `preferences`     JSON            DEFAULT NULL COMMENT 'UI preferences, theme, language',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email`    (`email`),
  INDEX `idx_users_role`     (`role`),
  INDEX `idx_users_active`   (`is_active`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 2. API Keys (for external integrations)
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT UNSIGNED    NOT NULL,
  `key_hash`        VARCHAR(255)    NOT NULL,
  `label`           VARCHAR(100)    DEFAULT NULL,
  `permissions`     JSON            DEFAULT NULL COMMENT '["read","write","delete"]',
  `last_used_at`    DATETIME        DEFAULT NULL,
  `expires_at`      DATETIME        DEFAULT NULL,
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_apikeys_user` (`user_id`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 3. Categories / Collections
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `categories` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `slug`            VARCHAR(60)     NOT NULL UNIQUE,
  `name`            VARCHAR(120)    NOT NULL,
  `description`     TEXT            DEFAULT NULL,
  `icon`            VARCHAR(50)     DEFAULT NULL COMMENT 'Feather icon name (e.g. box, globe, image)',
  `color`           VARCHAR(7)      DEFAULT NULL COMMENT 'Hex color #6C5CE7',
  `sort_order`      INT             NOT NULL DEFAULT 0,
  `is_system`       TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Built-in categories',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default categories
INSERT INTO `categories` (`slug`, `name`, `icon`, `color`, `is_system`, `sort_order`) VALUES
  ('model',    '3D Models',    'box',         '#6C5CE7', 1, 1),
  ('panorama', 'Panorama',     'globe',       '#00CEC9', 1, 2),
  ('image',    'Images',       'image',       '#74B9FF', 1, 3),
  ('embed',    'Embeds',       'monitor',     '#E17055', 1, 4),
  ('video',    'Videos',       'film',        '#FDCB6E', 1, 5),
  ('document', 'Documents',    'file-text',   '#DFE6E9', 1, 6);

-- ────────────────────────────────────────────
-- 4. Collections / Albums (user-created groups)
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `collections` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT UNSIGNED    NOT NULL,
  `name`            VARCHAR(200)    NOT NULL,
  `description`     TEXT            DEFAULT NULL,
  `cover_file_id`   INT UNSIGNED    DEFAULT NULL COMMENT 'Cover image file',
  `is_public`       TINYINT(1)      NOT NULL DEFAULT 0,
  `sort_order`      INT             NOT NULL DEFAULT 0,
  `file_count`      INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Denormalized count',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_collections_user`   (`user_id`),
  INDEX `idx_collections_public` (`is_public`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 5. Files — Core Asset Table
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `files` (
  `id`                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `uuid`              CHAR(36)        NOT NULL UNIQUE,
  `user_id`           INT UNSIGNED    NOT NULL,
  `category_id`       INT UNSIGNED    DEFAULT NULL,

  -- Basic info
  `name`              VARCHAR(255)    NOT NULL,
  `original_name`     VARCHAR(255)    NOT NULL,
  `description`       TEXT            DEFAULT NULL,
  `slug`              VARCHAR(255)    DEFAULT NULL COMMENT 'URL-friendly slug',

  -- File details
  `file_path`         VARCHAR(500)    DEFAULT NULL COMMENT 'Server path relative to uploads/',
  `file_url`          VARCHAR(1000)   DEFAULT NULL COMMENT 'Full URL (external/embed)',
  `thumbnail_path`    VARCHAR(500)    DEFAULT NULL,
  `mime_type`         VARCHAR(100)    DEFAULT NULL,
  `extension`         VARCHAR(20)     DEFAULT NULL,
  `file_size`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `file_hash`         VARCHAR(64)     DEFAULT NULL COMMENT 'SHA-256 for dedup',

  -- Source type
  `source_type`       ENUM('upload','url','embed') NOT NULL DEFAULT 'upload',
  `is_external`       TINYINT(1)      NOT NULL DEFAULT 0,

  -- Embed specific
  `embed_src`         VARCHAR(2000)   DEFAULT NULL COMMENT 'iframe src URL',
  `embed_code`        TEXT            DEFAULT NULL COMMENT 'Full iframe HTML',
  `embed_provider`    VARCHAR(100)    DEFAULT NULL COMMENT 'kiriengine, sketchfab, etc.',

  -- 3D Model specific
  `model_format`      VARCHAR(20)     DEFAULT NULL COMMENT 'glb, gltf, obj, fbx, stl',
  `model_vertices`    INT UNSIGNED    DEFAULT NULL,
  `model_faces`       INT UNSIGNED    DEFAULT NULL,
  `model_animations`  INT UNSIGNED    DEFAULT 0,
  `model_materials`   INT UNSIGNED    DEFAULT NULL,
  `model_textures`    INT UNSIGNED    DEFAULT NULL,

  -- Image specific
  `image_width`       INT UNSIGNED    DEFAULT NULL,
  `image_height`      INT UNSIGNED    DEFAULT NULL,
  `image_dpi`         INT UNSIGNED    DEFAULT NULL,
  `color_space`       VARCHAR(20)     DEFAULT NULL,

  -- Panorama specific
  `is_panorama`       TINYINT(1)      NOT NULL DEFAULT 0,
  `panorama_type`     ENUM('equirectangular','cubemap','cylindrical') DEFAULT NULL,
  `panorama_fov`      DECIMAL(5,2)    DEFAULT NULL COMMENT 'Field of view degrees',

  -- Metadata & Engagement
  `view_count`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `download_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `like_count`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `share_count`       INT UNSIGNED    NOT NULL DEFAULT 0,
  `rating_avg`        DECIMAL(3,2)    DEFAULT NULL,
  `rating_count`      INT UNSIGNED    NOT NULL DEFAULT 0,

  -- Access control
  `visibility`        ENUM('public','unlisted','private') NOT NULL DEFAULT 'private',
  `password_hash`     VARCHAR(255)    DEFAULT NULL COMMENT 'Optional file password',
  `allow_download`    TINYINT(1)      NOT NULL DEFAULT 1,
  `allow_embed`       TINYINT(1)      NOT NULL DEFAULT 1,

  -- QR / AR
  `has_qr`            TINYINT(1)      NOT NULL DEFAULT 0,
  `qr_code_path`      VARCHAR(500)    DEFAULT NULL,
  `ar_enabled`        TINYINT(1)      NOT NULL DEFAULT 0,
  `ar_scale`          DECIMAL(5,3)    DEFAULT 1.000,
  `ar_position_json`  JSON            DEFAULT NULL,

  -- Custom metadata
  `custom_meta`       JSON            DEFAULT NULL COMMENT 'Flexible key-value pairs',
  `exif_data`         JSON            DEFAULT NULL COMMENT 'EXIF from images',

  -- Status
  `status`            ENUM('active','processing','archived','deleted') NOT NULL DEFAULT 'active',
  `processing_status` ENUM('pending','processing','done','error') DEFAULT 'done',
  `processing_error`  TEXT            DEFAULT NULL,

  -- Timestamps
  `uploaded_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME        DEFAULT NULL COMMENT 'Soft delete',

  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,

  INDEX `idx_files_user`        (`user_id`),
  INDEX `idx_files_category`    (`category_id`),
  INDEX `idx_files_status`      (`status`),
  INDEX `idx_files_visibility`  (`visibility`),
  INDEX `idx_files_source`      (`source_type`),
  INDEX `idx_files_uploaded`    (`uploaded_at` DESC),
  INDEX `idx_files_views`       (`view_count` DESC),
  INDEX `idx_files_hash`        (`file_hash`),
  FULLTEXT INDEX `ft_files_search` (`name`, `description`, `original_name`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 6. File ↔ Collection (many-to-many)
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `collection_files` (
  `collection_id`   INT UNSIGNED    NOT NULL,
  `file_id`         INT UNSIGNED    NOT NULL,
  `sort_order`      INT             NOT NULL DEFAULT 0,
  `added_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`collection_id`, `file_id`),
  FOREIGN KEY (`collection_id`) REFERENCES `collections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`file_id`)       REFERENCES `files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 7. Tags
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `tags` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(80)     NOT NULL UNIQUE,
  `slug`            VARCHAR(80)     NOT NULL UNIQUE,
  `usage_count`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tags_usage` (`usage_count` DESC)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `file_tags` (
  `file_id`         INT UNSIGNED    NOT NULL,
  `tag_id`          INT UNSIGNED    NOT NULL,
  PRIMARY KEY (`file_id`, `tag_id`),
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`)  REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 8. File Versions (revision history)
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `file_versions` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `file_id`         INT UNSIGNED    NOT NULL,
  `version_number`  INT UNSIGNED    NOT NULL DEFAULT 1,
  `file_path`       VARCHAR(500)    NOT NULL,
  `file_size`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `file_hash`       VARCHAR(64)     DEFAULT NULL,
  `change_note`     TEXT            DEFAULT NULL,
  `created_by`      INT UNSIGNED    DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`file_id`)    REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_versions_file`  (`file_id`, `version_number` DESC)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 9. Comments
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `comments` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `file_id`         INT UNSIGNED    NOT NULL,
  `user_id`         INT UNSIGNED    DEFAULT NULL,
  `parent_id`       INT UNSIGNED    DEFAULT NULL COMMENT 'For threaded replies',
  `author_name`     VARCHAR(100)    DEFAULT NULL COMMENT 'Guest comments',
  `body`            TEXT            NOT NULL,
  `is_approved`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`file_id`)   REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`parent_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE,
  INDEX `idx_comments_file` (`file_id`, `created_at` DESC)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 10. Likes / Favorites
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `likes` (
  `user_id`         INT UNSIGNED    NOT NULL,
  `file_id`         INT UNSIGNED    NOT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `file_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 11. Share Links
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `share_links` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `file_id`         INT UNSIGNED    NOT NULL,
  `user_id`         INT UNSIGNED    NOT NULL,
  `token`           VARCHAR(64)     NOT NULL UNIQUE,
  `password_hash`   VARCHAR(255)    DEFAULT NULL,
  `max_views`       INT UNSIGNED    DEFAULT NULL,
  `current_views`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `allow_download`  TINYINT(1)      NOT NULL DEFAULT 0,
  `expires_at`      DATETIME        DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_share_token` (`token`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 12. QR Code Records
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `qr_codes` (
  `id`              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  `file_id`         INT UNSIGNED    NOT NULL,
  `user_id`         INT UNSIGNED    NOT NULL,
  `qr_data_url`     TEXT            NOT NULL COMMENT 'Encoded QR target URL',
  `qr_image_path`   VARCHAR(500)    DEFAULT NULL,
  `scan_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `last_scanned_at` DATETIME        DEFAULT NULL,
  `label`           VARCHAR(200)    DEFAULT NULL,
  `style_config`    JSON            DEFAULT NULL COMMENT 'Colors, logo, size',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_qr_file` (`file_id`)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 13. Activity / Audit Log
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT UNSIGNED    DEFAULT NULL,
  `file_id`         INT UNSIGNED    DEFAULT NULL,
  `action`          VARCHAR(50)     NOT NULL COMMENT 'upload, view, download, delete, share, qr_scan, etc.',
  `details`         JSON            DEFAULT NULL,
  `ip_address`      VARCHAR(45)     DEFAULT NULL,
  `user_agent`      VARCHAR(500)    DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_activity_user`   (`user_id`, `created_at` DESC),
  INDEX `idx_activity_file`   (`file_id`, `created_at` DESC),
  INDEX `idx_activity_action` (`action`),
  INDEX `idx_activity_date`   (`created_at` DESC)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────
-- 14. Settings (key-value config)
-- ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `settings` (
  `key`             VARCHAR(100)    PRIMARY KEY,
  `value`           TEXT            DEFAULT NULL,
  `type`            ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
  `description`     TEXT            DEFAULT NULL,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default settings
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
  ('site_name',           'VRX Studio',           'string', 'Site display name'),
  ('max_upload_size',     '104857600',            'int',    'Max upload size in bytes (100MB)'),
  ('allowed_extensions',  '["glb","gltf","obj","fbx","stl","jpg","jpeg","png","webp","hdr","gif","svg","bmp"]', 'json', 'Allowed file extensions'),
  ('default_visibility',  'private',              'string', 'Default file visibility'),
  ('enable_registration', '1',                    'bool',   'Allow new user registration'),
  ('enable_guest_upload', '0',                    'bool',   'Allow guest uploads'),
  ('ar_default_scale',    '1.0',                  'string', 'Default AR model scale'),
  ('gallery_page_size',   '24',                   'int',    'Items per page in gallery');

-- ────────────────────────────────────────────
-- 15. Useful Views
-- ────────────────────────────────────────────

CREATE OR REPLACE VIEW `v_file_gallery` AS
SELECT
  f.id,
  f.uuid,
  f.name,
  f.original_name,
  f.description,
  f.slug,
  f.file_url,
  f.thumbnail_path,
  f.mime_type,
  f.extension,
  f.file_size,
  f.source_type,
  f.embed_src,
  f.embed_provider,
  f.model_format,
  f.is_panorama,
  f.view_count,
  f.like_count,
  f.share_count,
  f.download_count,
  f.rating_avg,
  f.visibility,
  f.has_qr,
  f.ar_enabled,
  f.status,
  f.uploaded_at,
  f.updated_at,
  c.slug       AS category_slug,
  c.name       AS category_name,
  c.icon       AS category_icon,
  c.color      AS category_color,
  u.username   AS uploader_username,
  u.display_name AS uploader_name,
  u.avatar_url AS uploader_avatar,
  (SELECT COUNT(*) FROM comments cm WHERE cm.file_id = f.id) AS comment_count,
  (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ')
    FROM file_tags ft
    JOIN tags t ON t.id = ft.tag_id
    WHERE ft.file_id = f.id) AS tag_list
FROM files f
LEFT JOIN categories c ON c.id = f.category_id
LEFT JOIN users u ON u.id = f.user_id
WHERE f.status = 'active'
  AND f.deleted_at IS NULL;

-- ────────────────────────────────────────────
-- 16. Demo Users (3 roles for testing)
--     All passwords: 123
--     Hash generated by: password_hash('123', PASSWORD_DEFAULT)
-- ────────────────────────────────────────────

-- admin1 / 123 — Full admin access
INSERT INTO `users` (`uuid`, `username`, `email`, `password_hash`, `display_name`, `role`, `is_active`, `email_verified`)
VALUES (
  UUID(), 'admin1', 'admin1@vrx.local',
  '$2y$10$rsD/plyT1rtF2YIpDiAh1OQbhbUicrEuinZmg4zlMTKejK.lhm8pC',
  'Admin User', 'admin', 1, 1
);

-- user1 / 123 — Upload & manage own files
INSERT INTO `users` (`uuid`, `username`, `email`, `password_hash`, `display_name`, `role`, `is_active`, `email_verified`)
VALUES (
  UUID(), 'user1', 'user1@vrx.local',
  '$2y$10$rsD/plyT1rtF2YIpDiAh1OQbhbUicrEuinZmg4zlMTKejK.lhm8pC',
  'Regular User', 'user', 1, 1
);

-- view1 / 123 — View only
INSERT INTO `users` (`uuid`, `username`, `email`, `password_hash`, `display_name`, `role`, `is_active`, `email_verified`)
VALUES (
  UUID(), 'view1', 'view1@vrx.local',
  '$2y$10$rsD/plyT1rtF2YIpDiAh1OQbhbUicrEuinZmg4zlMTKejK.lhm8pC',
  'Viewer', 'viewer', 1, 1
);
