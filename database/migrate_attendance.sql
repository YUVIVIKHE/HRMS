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
