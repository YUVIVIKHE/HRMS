-- ============================================================
--  ACL Requests — Migration
--  Employee requests go to manager, Manager requests go to admin
-- ============================================================

CREATE TABLE IF NOT EXISTS `acl_requests` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `work_date`     DATE          NOT NULL COMMENT 'The holiday/weekend date to work on',
  `reason`        VARCHAR(255)  NOT NULL,
  `hours`         DECIMAL(5,2)  NOT NULL DEFAULT 9 COMMENT 'Hours to be credited on approval',
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT UNSIGNED  NULL,
  `reviewed_at`   DATETIME      NULL,
  `review_note`   VARCHAR(255)  NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_acl_user` (`user_id`),
  INDEX `idx_acl_status` (`status`),
  INDEX `idx_acl_date` (`work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
