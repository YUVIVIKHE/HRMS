-- ============================================================
--  Project Expenses — Migration
-- ============================================================

CREATE TABLE IF NOT EXISTS `project_expenses` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `project_id`    INT UNSIGNED  NOT NULL,
  `category`      ENUM('Travel','Food','Hotel','Other') NOT NULL DEFAULT 'Other',
  `amount`        DECIMAL(12,2) NOT NULL DEFAULT 0,
  `expense_date`  DATE          NOT NULL,
  `description`   VARCHAR(500)  NULL,
  `added_by`      INT UNSIGNED  NOT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_exp_project` (`project_id`),
  INDEX `idx_exp_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
