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
  `department_id` int(11) DEFAULT NULL,
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
