-- ============================================================
--  HRMS Portal — Database Setup Script
--  DB : u587292075_portal
--  Run this once via phpMyAdmin or CLI on labxco.cloud
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Users (all roles in one table) ───────────────────────────
-- Only this table is created as requested for authentication.
CREATE TABLE IF NOT EXISTS `users` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120)     NOT NULL,
  `email`       VARCHAR(180)     NOT NULL UNIQUE,
  `password`    VARCHAR(255)     NOT NULL COMMENT 'bcrypt hash',
  `role`        ENUM('admin','manager','employee') NOT NULL DEFAULT 'employee',
  `status`      ENUM('active','inactive')          NOT NULL DEFAULT 'active',
  `manager_id`  INT UNSIGNED     NULL COMMENT 'FK → users.id for employee→manager link',
  `phone`       VARCHAR(20)      NULL,
  `department`  VARCHAR(80)      NULL,
  `designation` VARCHAR(80)      NULL,
  `joined_at`   DATE             NULL,
  `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_role`       (`role`),
  INDEX `idx_manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  SEED — Default admin user
--  Password: password  (bcrypt hash below)
--  Change this immediately after first login!
-- ============================================================
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `status`)
VALUES (
  'System Admin',
  'admin@hrms.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: "password" (bcrypt)
  'admin',
  'active'
);
