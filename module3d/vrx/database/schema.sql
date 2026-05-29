-- ============================================================
-- VRX Studio — Database Schema v2.0
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `vrx_studio`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vrx_studio`;

-- ── Users ──
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `qr_codes`;
DROP TABLE IF EXISTS `share_links`;
DROP TABLE IF EXISTS `likes`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `file_tags`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `collection_files`;
DROP TABLE IF EXISTS `file_versions`;
DROP TABLE IF EXISTS `files`;
DROP TABLE IF EXISTS `collections`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `api_keys`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;
DROP VIEW IF EXISTS `v_file_gallery`;

CREATE TABLE `users` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uuid`          CHAR(36) NOT NULL UNIQUE,
  `username`      VARCHAR(60) NOT NULL UNIQUE,
  `email`         VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name`  VARCHAR(120) DEFAULT NULL,
  `avatar_url`    VARCHAR(500) DEFAULT NULL,
  `role`          ENUM('admin','user','viewer') NOT NULL DEFAULT 'viewer',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB;

-- ── Categories ──
CREATE TABLE `categories` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`       VARCHAR(60) NOT NULL UNIQUE,
  `name`       VARCHAR(120) NOT NULL,
  `icon`       VARCHAR(50) DEFAULT NULL,
  `color`      VARCHAR(7) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_system`  TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO `categories` (`slug`,`name`,`icon`,`color`,`is_system`,`sort_order`) VALUES
  ('model','3D Models','box','#6C5CE7',1,1),
  ('panorama','Panorama','globe','#00CEC9',1,2),
  ('image','Images','image','#74B9FF',1,3),
  ('embed','Embeds','monitor','#E17055',1,4),
  ('video','Videos','film','#FDCB6E',1,5),
  ('document','Documents','file-text','#DFE6E9',1,6);

-- ── Tags ──
CREATE TABLE `tags` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(80) NOT NULL UNIQUE,
  `slug`        VARCHAR(80) NOT NULL UNIQUE,
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Files ──
CREATE TABLE `files` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uuid`           CHAR(36) NOT NULL UNIQUE,
  `user_id`        INT UNSIGNED NOT NULL,
  `category_id`    INT UNSIGNED DEFAULT NULL,
  `name`           VARCHAR(255) NOT NULL,
  `original_name`  VARCHAR(255) NOT NULL,
  `description`    TEXT DEFAULT NULL,
  `file_path`      VARCHAR(500) DEFAULT NULL,
  `file_url`       VARCHAR(1000) DEFAULT NULL,
  `thumbnail_path` VARCHAR(500) DEFAULT NULL,
  `mime_type`      VARCHAR(100) DEFAULT NULL,
  `extension`      VARCHAR(20) DEFAULT NULL,
  `file_size`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `source_type`    ENUM('upload','url','embed') NOT NULL DEFAULT 'upload',
  `is_external`    TINYINT(1) NOT NULL DEFAULT 0,
  `embed_src`      VARCHAR(2000) DEFAULT NULL,
  `embed_code`     TEXT DEFAULT NULL,
  `embed_provider` VARCHAR(100) DEFAULT NULL,
  `ar_enabled`     TINYINT(1) NOT NULL DEFAULT 0,
  `ar_scale`       DECIMAL(5,3) DEFAULT 1.000,
  `view_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `like_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `visibility`     ENUM('public','unlisted','private') NOT NULL DEFAULT 'public',
  `status`         ENUM('active','deleted') NOT NULL DEFAULT 'active',
  `uploaded_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_cat`  (`category_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_uploaded` (`uploaded_at` DESC),
  FULLTEXT INDEX `ft_search` (`name`,`description`,`original_name`)
) ENGINE=InnoDB;

-- ── File ↔ Tag ──
CREATE TABLE `file_tags` (
  `file_id` INT UNSIGNED NOT NULL,
  `tag_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`file_id`,`tag_id`),
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── QR Codes ──
CREATE TABLE `qr_codes` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `file_id`         INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `qr_data_url`     TEXT NOT NULL,
  `scan_count`      INT UNSIGNED NOT NULL DEFAULT 0,
  `last_scanned_at` DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Activity Log ──
CREATE TABLE `activity_log` (
  `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `file_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(50) NOT NULL,
  `details`    JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_action` (`action`),
  INDEX `idx_date`   (`created_at` DESC)
) ENGINE=InnoDB;

-- ── Settings ──
CREATE TABLE `settings` (
  `key`         VARCHAR(100) PRIMARY KEY,
  `value`       TEXT DEFAULT NULL,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `settings` (`key`,`value`) VALUES
  ('site_name','VRX Studio'),
  ('max_upload_size','104857600'),
  ('qr_base_url',''),
  ('qr_pattern_ar','{origin}{base}/pages/ar.php?src={file_url}'),
  ('qr_pattern_3d','{origin}{base}/pages/viewer.php?src={file_url}'),
  ('qr_pattern_pano','{origin}{base}/pages/panorama.php?src={file_url}'),
  ('qr_pattern_embed','{origin}{base}/pages/viewer.php?mode=embed&embed={file_url}'),
  ('qr_size','250'),
  ('qr_color_dark','#000000'),
  ('qr_color_light','#ffffff'),
  ('qr_error_level','M'),
  ('iframe_kiri_bg_theme','transparent'),
  ('iframe_kiri_auto_spin','1'),
  ('iframe_default_params','bg_theme=transparent&auto_spin_model=1'),
  ('iframe_default_attrs','frameborder=\"0\" allowfullscreen mozallowfullscreen webkitallowfullscreen allow=\"autoplay; fullscreen;\" execution-while-out-of-viewport execution-while-not-rendered'),
  ('iframe_width','640'),
  ('iframe_height','480');

-- ── Gallery View ──
CREATE OR REPLACE VIEW `v_file_gallery` AS
SELECT f.*, c.slug AS category_slug, c.name AS category_name,
       c.icon AS category_icon, c.color AS category_color,
       u.username, u.display_name AS uploader_name
FROM files f
LEFT JOIN categories c ON c.id = f.category_id
LEFT JOIN users u ON u.id = f.user_id
WHERE f.status = 'active' AND f.deleted_at IS NULL;

-- ── Demo Users (password: 123) ──
INSERT INTO `users` (`uuid`,`username`,`email`,`password_hash`,`display_name`,`role`,`is_active`) VALUES
  (UUID(),'admin1','admin1@vrx.local','$2y$10$rsD/plyT1rtF2YIpDiAh1OQbhbUicrEuinZmg4zlMTKejK.lhm8pC','Admin','admin',1),
  (UUID(),'user1','user1@vrx.local','$2y$10$rsD/plyT1rtF2YIpDiAh1OQbhbUicrEuinZmg4zlMTKejK.lhm8pC','User','user',1),
  (UUID(),'view1','view1@vrx.local','$2y$10$rsD/plyT1rtF2YIpDiAh1OQbhbUicrEuinZmg4zlMTKejK.lhm8pC','Viewer','viewer',1);
