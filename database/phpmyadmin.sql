-- ============================================================
-- Visitor Management System — phpMyAdmin / cPanel import
-- ============================================================
-- HOW TO IMPORT:
-- 1. In cPanel create a MySQL database + user, assign ALL privileges
-- 2. Open phpMyAdmin → select THAT database (left sidebar)
-- 3. Import tab → choose this file → Go
--
-- Do NOT run CREATE DATABASE here (cPanel already created it).
-- Then set config/database.php to your cPanel DB name/user/password.
--
-- Demo logins after import:
--   admin      / Admin@123!
--   security   / Security@123!
--   resident1  / Resident@123!
-- Change these passwords before production use.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `username` VARCHAR(80) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_user_time` (`username`, `attempted_at`),
  KEY `idx_login_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_token` (`token_hash`),
  KEY `idx_pr_user` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `residents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `resident_code` VARCHAR(30) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `gender` ENUM('male','female','other') NOT NULL DEFAULT 'other',
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(150) NULL,
  `address` VARCHAR(255) NULL,
  `unit_number` VARCHAR(50) NOT NULL,
  `emergency_contact` VARCHAR(150) NULL,
  `emergency_phone` VARCHAR(30) NULL,
  `status` ENUM('pending','active','suspended','moved_out','inactive') NOT NULL DEFAULT 'pending',
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_residents_code` (`resident_code`),
  UNIQUE KEY `uq_residents_user` (`user_id`),
  KEY `idx_residents_status` (`status`),
  KEY `idx_residents_unit` (`unit_number`),
  KEY `idx_residents_name` (`full_name`),
  CONSTRAINT `fk_residents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_residents_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resident_qr_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resident_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `revoked_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rq_token` (`token`),
  KEY `idx_rq_resident` (`resident_id`),
  KEY `idx_rq_active` (`resident_id`, `is_active`),
  CONSTRAINT `fk_rq_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_invite_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resident_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `max_uses` INT UNSIGNED NULL,
  `use_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `revoked_at` DATETIME NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vil_token` (`token`),
  KEY `idx_vil_resident` (`resident_id`),
  KEY `idx_vil_active` (`is_active`, `expires_at`),
  CONSTRAINT `fk_vil_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vil_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resident_id` INT UNSIGNED NOT NULL,
  `invite_link_id` INT UNSIGNED NULL,
  `visitor_name` VARCHAR(150) NOT NULL,
  `car_plate` VARCHAR(30) NULL,
  `phone` VARCHAR(30) NULL,
  `purpose` VARCHAR(255) NULL,
  `visit_date` DATE NOT NULL,
  `valid_until` DATE NOT NULL,
  `qr_token` VARCHAR(64) NOT NULL,
  `status` ENUM('pending','approved','checked_in','checked_out','expired','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `checked_in_at` DATETIME NULL,
  `checked_in_by` INT UNSIGNED NULL,
  `checked_out_at` DATETIME NULL,
  `checked_out_by` INT UNSIGNED NULL,
  `rejection_reason` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_visitors_qr` (`qr_token`),
  KEY `idx_visitors_resident` (`resident_id`),
  KEY `idx_visitors_status` (`status`),
  KEY `idx_visitors_visit_date` (`visit_date`),
  KEY `idx_visitors_plate` (`car_plate`),
  KEY `idx_visitors_phone` (`phone`),
  KEY `idx_visitors_invite` (`invite_link_id`),
  CONSTRAINT `fk_visitors_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visitors_invite` FOREIGN KEY (`invite_link_id`) REFERENCES `visitor_invite_links` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visitors_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visitors_cin` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_visitors_cout` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_scans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_id` INT UNSIGNED NULL,
  `qr_token` VARCHAR(64) NOT NULL,
  `scanned_by` INT UNSIGNED NULL,
  `result` ENUM('success','rejected') NOT NULL,
  `reason` VARCHAR(255) NULL,
  `scanned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scans_visitor` (`visitor_id`),
  KEY `idx_scans_time` (`scanned_at`),
  KEY `idx_scans_token` (`qr_token`),
  CONSTRAINT `fk_scans_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scans_user` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitor_blacklist` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NULL,
  `car_plate` VARCHAR(30) NULL,
  `phone` VARCHAR(30) NULL,
  `reason` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bl_plate` (`car_plate`),
  KEY `idx_bl_phone` (`phone`),
  KEY `idx_bl_active` (`is_active`),
  CONSTRAINT `fk_bl_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL,
  `audience` ENUM('all','residents','security','admin') NOT NULL DEFAULT 'all',
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` DATETIME NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ann_pub` (`is_published`, `published_at`),
  CONSTRAINT `fk_ann_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'system',
  `related_type` VARCHAR(50) NULL,
  `related_id` INT UNSIGNED NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`),
  KEY `idx_notif_created` (`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `description` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_user` (`user_id`),
  KEY `idx_al_action` (`action`),
  KEY `idx_al_created` (`created_at`),
  KEY `idx_al_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Admin', 'admin', 'Full system administrator'),
(2, 'Security', 'security', 'Security / gate staff'),
(3, 'Resident', 'resident', 'Residence occupant')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES
(1,  'View Residents', 'residents.view', 'residents', 'View resident list and details'),
(2,  'Create Residents', 'residents.create', 'residents', 'Create residents'),
(3,  'Edit Residents', 'residents.edit', 'residents', 'Edit residents'),
(4,  'Delete Residents', 'residents.delete', 'residents', 'Deactivate/delete residents'),
(5,  'View Visitors', 'visitors.view', 'visitors', 'View visitors'),
(6,  'Create Visitors', 'visitors.create', 'visitors', 'Create visitors'),
(7,  'Edit Visitors', 'visitors.edit', 'visitors', 'Edit/approve visitors'),
(8,  'Check-in Visitors', 'visitors.checkin', 'visitors', 'Check in visitors'),
(9,  'Check-out Visitors', 'visitors.checkout', 'visitors', 'Check out visitors'),
(10, 'Scan Check-in', 'checkin.scan', 'checkin', 'Scan visitor QR'),
(11, 'View Staff', 'staff.view', 'staff', 'View staff users'),
(12, 'Create Staff', 'staff.create', 'staff', 'Create staff users'),
(13, 'Edit Staff', 'staff.edit', 'staff', 'Edit staff users'),
(14, 'View Reports', 'reports.view', 'reports', 'View reports'),
(15, 'Export Reports', 'reports.export', 'reports', 'Export reports'),
(16, 'View Announcements', 'announcements.view', 'announcements', 'View announcements'),
(17, 'Create Announcements', 'announcements.create', 'announcements', 'Create announcements'),
(18, 'Edit Announcements', 'announcements.edit', 'announcements', 'Edit announcements'),
(19, 'View Blacklist', 'blacklist.view', 'blacklist', 'View blacklist'),
(20, 'Manage Blacklist', 'blacklist.manage', 'blacklist', 'Manage blacklist'),
(21, 'View Logs', 'logs.view', 'logs', 'View activity logs'),
(22, 'Manage QR', 'residents.qr', 'residents', 'Manage resident QR codes')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 5), (2, 8), (2, 9), (2, 10), (2, 16), (2, 19)
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 5), (3, 6), (3, 16)
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;

INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `status`, `must_change_password`) VALUES
(1, 1, 'admin', 'admin@vms.local', '$2y$10$1fV5p779nAOHKOToLNocJOoqGH5ma48fagyWhUV8VstoVPTu2HVV.', 'System Administrator', '0100000001', 'active', 0),
(2, 2, 'security', 'security@vms.local', '$2y$10$js9S50OSjHGJIF5Q.zpKKO9QPPHxN2YqvQHU/P9s6d9DxdZ8qi9Sa', 'Security Officer', '0100000002', 'active', 0),
(3, 3, 'resident1', 'resident1@vms.local', '$2y$10$qHRKkV2U9RB3MF463Yc3TOTZQwjzNL2/ObgfOmhR9AHX79Wlc.hNu', 'Ali Bin Abu', '0100000003', 'active', 0)
ON DUPLICATE KEY UPDATE
  `password_hash` = VALUES(`password_hash`),
  `email` = VALUES(`email`);

INSERT INTO `residents` (`id`, `user_id`, `resident_code`, `full_name`, `gender`, `phone`, `email`, `address`, `unit_number`, `emergency_contact`, `emergency_phone`, `status`, `created_by`) VALUES
(1, 3, 'R-0001', 'Ali Bin Abu', 'male', '0100000003', 'resident1@vms.local', 'Block A', 'A-12-03', 'Siti', '0100000004', 'active', 1)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

INSERT INTO `resident_qr_codes` (`resident_id`, `token`, `is_active`, `created_at`)
SELECT 1, '4051d86bfd1305d252fc04a17f5f79c33487cd2710acb41b85a4e9520a3ad37d', 1, NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `resident_qr_codes` WHERE `resident_id` = 1 AND `is_active` = 1
);