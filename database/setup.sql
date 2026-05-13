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

-- ── Departments ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `departments` (`id`, `name`) VALUES
  (1, 'Admin'),
  (2, 'Engineering Design'),
  (3, 'Finance'),
  (4, 'HR'),
  (5, 'Project Management'),
  (6, 'Sales'),
  (7, 'SCM & Stores'),
  (8, 'Site Supervision');

-- ── Detailed Employee Records ────────────────────────────────
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `department_id` int(11) UNSIGNED DEFAULT NULL,
  `employee_type` enum('FTE','External') NOT NULL DEFAULT 'FTE',
  `date_of_joining` date DEFAULT NULL,
  `date_of_exit` date DEFAULT NULL,
  `date_of_confirmation` date DEFAULT NULL,
  `direct_manager_name` varchar(150) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `base_location` varchar(150) DEFAULT NULL,
  `user_code` varchar(50) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `permanent_address_line1` varchar(255) DEFAULT NULL,
  `permanent_address_line2` varchar(255) DEFAULT NULL,
  `permanent_city` varchar(100) DEFAULT NULL,
  `permanent_state` varchar(100) DEFAULT NULL,
  `permanent_zip_code` varchar(20) DEFAULT NULL,
  `account_type` enum('Savings','Current') DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `pan` varchar(20) DEFAULT NULL,
  `aadhar_no` varchar(20) DEFAULT NULL,
  `uan_number` varchar(20) DEFAULT NULL,
  `pf_account_number` varchar(50) DEFAULT NULL,
  `employee_provident_fund` varchar(50) DEFAULT NULL,
  `professional_tax` varchar(50) DEFAULT NULL,
  `esi_number` varchar(50) DEFAULT NULL,
  `exempt_from_tax` tinyint(1) DEFAULT 0,
  `passport_no` varchar(30) DEFAULT NULL,
  `place_of_issue` varchar(100) DEFAULT NULL,
  `passport_date_of_issue` date DEFAULT NULL,
  `passport_date_of_expiry` date DEFAULT NULL,
  `place_of_birth` varchar(100) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `personal_email` varchar(255) DEFAULT NULL,
  `emergency_contact_no` varchar(20) DEFAULT NULL,
  `country_code_phone` varchar(10) DEFAULT NULL,
  `status` enum('Active','Inactive','Terminated') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gross_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'CTC gross salary for payroll auto-fill',
  PRIMARY KEY (`id`),
  INDEX `idx_department_id` (`department_id`),
  CONSTRAINT `fk_emp_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  MIGRATION — Run on existing databases to add departments
--  Safe to run multiple times (uses IF NOT EXISTS / IGNORE)
-- ============================================================

-- 1. Create departments table if it doesn't exist yet
CREATE TABLE IF NOT EXISTS `departments` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)  NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `departments` (`id`, `name`) VALUES
  (1, 'Admin'),
  (2, 'Engineering Design'),
  (3, 'Finance'),
  (4, 'HR'),
  (5, 'Project Management'),
  (6, 'Sales'),
  (7, 'SCM & Stores'),
  (8, 'Site Supervision');

-- 2. Change department_id to UNSIGNED to match departments.id
--    (skip if already done)
ALTER TABLE `employees`
  MODIFY COLUMN `department_id` INT UNSIGNED DEFAULT NULL;

-- 3. Add index + FK if not already present
ALTER TABLE `employees`
  ADD INDEX IF NOT EXISTS `idx_department_id` (`department_id`);

SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `employees`
  ADD CONSTRAINT IF NOT EXISTS `fk_emp_department`
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
  ON UPDATE CASCADE ON DELETE SET NULL;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  ATTENDANCE — Locations & Logs
-- ============================================================

-- Office locations defined by admin
CREATE TABLE IF NOT EXISTS `attendance_locations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120)  NOT NULL,
  `address`     VARCHAR(255)  NULL,
  `latitude`    DECIMAL(10,7) NOT NULL,
  `longitude`   DECIMAL(10,7) NOT NULL,
  `radius_m`    INT UNSIGNED  NOT NULL DEFAULT 200 COMMENT 'Allowed radius in metres',
  `is_remote`   TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = remote/free location, no GPS check',
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily attendance log
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `log_date`      DATE          NOT NULL,
  `clock_in`      DATETIME      NULL,
  `clock_out`     DATETIME      NULL,
  `work_seconds`  INT UNSIGNED  NULL COMMENT 'Total worked seconds for the day',
  `status`        ENUM('present','absent','half_day','late','remote') NOT NULL DEFAULT 'present',
  `location_id`   INT UNSIGNED  NULL COMMENT 'FK → attendance_locations',
  `clock_in_lat`  DECIMAL(10,7) NULL,
  `clock_in_lng`  DECIMAL(10,7) NULL,
  `clock_out_lat` DECIMAL(10,7) NULL,
  `clock_out_lng` DECIMAL(10,7) NULL,
  `note`          VARCHAR(255)  NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_date` (`user_id`, `log_date`),
  INDEX `idx_log_date`  (`log_date`),
  INDEX `idx_user_id`   (`user_id`),
  CONSTRAINT `fk_att_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_location` FOREIGN KEY (`location_id`) REFERENCES `attendance_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: one remote location so employees can always clock in remotely
INSERT IGNORE INTO `attendance_locations` (`id`, `name`, `address`, `latitude`, `longitude`, `radius_m`, `is_remote`, `is_active`)
VALUES (1, 'Remote / Work from Home', 'Anywhere', 0.0000000, 0.0000000, 0, 1, 1);
