-- ============================================================
--  Run this in phpMyAdmin or via CLI to create attendance tables
-- ============================================================

CREATE TABLE IF NOT EXISTS `attendance_locations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120)  NOT NULL,
  `address`     VARCHAR(255)  NULL,
  `latitude`    DECIMAL(10,7) NOT NULL DEFAULT 0.0000000,
  `longitude`   DECIMAL(10,7) NOT NULL DEFAULT 0.0000000,
  `radius_m`    INT UNSIGNED  NOT NULL DEFAULT 200,
  `is_remote`   TINYINT(1)    NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `attendance_locations` (`id`, `name`, `address`, `latitude`, `longitude`, `radius_m`, `is_remote`, `is_active`)
VALUES (1, 'Remote / Work from Home', 'Anywhere', 0.0000000, 0.0000000, 0, 1, 1);

CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `log_date`      DATE          NOT NULL,
  `clock_in`      DATETIME      NULL,
  `clock_out`     DATETIME      NULL,
  `work_seconds`  INT UNSIGNED  NULL,
  `status`        ENUM('present','absent','half_day','late','remote') NOT NULL DEFAULT 'present',
  `location_id`   INT UNSIGNED  NULL,
  `clock_in_lat`  DECIMAL(10,7) NULL,
  `clock_in_lng`  DECIMAL(10,7) NULL,
  `clock_out_lat` DECIMAL(10,7) NULL,
  `clock_out_lng` DECIMAL(10,7) NULL,
  `note`          VARCHAR(255)  NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_date` (`user_id`, `log_date`),
  INDEX `idx_log_date` (`log_date`),
  INDEX `idx_user_id`  (`user_id`),
  CONSTRAINT `fk_att_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_location` FOREIGN KEY (`location_id`) REFERENCES `attendance_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Fix collation mismatch (run if you get error 1267) ───────
ALTER TABLE `attendance_locations`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `attendance_logs`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- ── Per-user location assignments ────────────────────────────
CREATE TABLE IF NOT EXISTS `user_locations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED NOT NULL,
  `assigned_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_loc` (`user_id`, `location_id`),
  CONSTRAINT `fk_ul_user` FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ul_loc`  FOREIGN KEY (`location_id`) REFERENCES `attendance_locations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
--  LEAVE MANAGEMENT
-- ============================================================

-- Leave types defined by admin
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(100)  NOT NULL,
  `days_per_credit` DECIMAL(5,1)  NOT NULL DEFAULT 1.0 COMMENT 'Days credited per cycle',
  `credit_cycle`    ENUM('monthly','yearly','manual') NOT NULL DEFAULT 'monthly',
  `credit_day`      TINYINT       NOT NULL DEFAULT 1 COMMENT 'Day of month/year to auto-credit',
  `max_carry_fwd`   DECIMAL(5,1)  NOT NULL DEFAULT 0 COMMENT '0 = no carry forward',
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `color`           VARCHAR(7)    NOT NULL DEFAULT '#4f46e5',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Leave balance per user per leave type
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `leave_type_id`INT UNSIGNED  NOT NULL,
  `balance`      DECIMAL(5,1)  NOT NULL DEFAULT 0,
  `used`         DECIMAL(5,1)  NOT NULL DEFAULT 0,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_type` (`user_id`,`leave_type_id`),
  CONSTRAINT `fk_lb_user` FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lb_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Leave credit log (audit trail)
CREATE TABLE IF NOT EXISTS `leave_credit_log` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `leave_type_id`INT UNSIGNED  NOT NULL,
  `days`         DECIMAL(5,1)  NOT NULL,
  `reason`       VARCHAR(255)  NULL,
  `credited_by`  INT UNSIGNED  NULL COMMENT 'admin user_id, NULL = auto',
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_lcl_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Leave applications
CREATE TABLE IF NOT EXISTS `leave_applications` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `leave_type_id` INT UNSIGNED  NOT NULL,
  `from_date`     DATE          NOT NULL,
  `to_date`       DATE          NOT NULL,
  `days`          DECIMAL(5,1)  NOT NULL,
  `reason`        TEXT          NULL,
  `status`        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT UNSIGNED  NULL,
  `reviewed_at`   DATETIME      NULL,
  `review_note`   VARCHAR(255)  NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_la_user`   (`user_id`),
  INDEX `idx_la_status` (`status`),
  CONSTRAINT `fk_la_user` FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_la_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default leave types
INSERT IGNORE INTO `leave_types` (`id`,`name`,`days_per_credit`,`credit_cycle`,`credit_day`,`max_carry_fwd`,`color`) VALUES
(1,'Casual Leave',   1.0, 'monthly', 1, 0,  '#10b981'),
(2,'Privilege Leave',1.0, 'monthly', 1, 30, '#4f46e5'),
(3,'Sick Leave',     1.0, 'monthly', 1, 0,  '#ef4444');

-- Add escalation tracking to leave_applications
ALTER TABLE `leave_applications`
  ADD COLUMN IF NOT EXISTS `escalated_at` DATETIME NULL COMMENT 'Set when pending >3 days, escalated to admin',
  ADD COLUMN IF NOT EXISTS `escalated` TINYINT(1) NOT NULL DEFAULT 0;

-- ── Remove duplicate users created by the promote-to-manager bug ─────────
-- Keeps the manager-role row, deletes the employee-role duplicate for same email
DELETE u1 FROM users u1
INNER JOIN users u2 ON u1.email = u2.email
WHERE u1.role = 'employee'
  AND u2.role = 'manager'
  AND u1.id < u2.id;

-- ── IMMEDIATE FIX: Remove duplicate users (employee+manager same email) ──
-- Run this NOW in phpMyAdmin to fix existing duplicates
-- Keeps the manager row, deletes the employee row for same email

DELETE u1 FROM users u1
INNER JOIN users u2 ON u1.email = u2.email AND u1.id != u2.id
WHERE u1.role = 'employee' AND u2.role = 'manager';

-- Ensure UNIQUE constraint exists on email
ALTER TABLE `users` ADD UNIQUE KEY IF NOT EXISTS `uq_users_email` (`email`);
