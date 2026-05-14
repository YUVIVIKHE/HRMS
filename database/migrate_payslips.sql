-- ============================================================
--  Payslips — Migration
-- ============================================================

CREATE TABLE IF NOT EXISTS `payslips` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED  NOT NULL,
  `month`             TINYINT       NOT NULL COMMENT '1-12',
  `year`              SMALLINT      NOT NULL,
  `gross_salary`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `basic_salary`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `hra`               DECIMAL(12,2) NOT NULL DEFAULT 0,
  `special_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `conveyance`        DECIMAL(12,2) NOT NULL DEFAULT 0,
  `education_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `lta`               DECIMAL(12,2) NOT NULL DEFAULT 0,
  `mediclaim_insurance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `medical_reimbursement` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `mobile_internet`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `personal_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `bonus`             DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total_earnings`    DECIMAL(12,2) NOT NULL DEFAULT 0,
  `income_tax`        DECIMAL(12,2) NOT NULL DEFAULT 0,
  `esi`               DECIMAL(12,2) NOT NULL DEFAULT 0,
  `pf`                DECIMAL(12,2) NOT NULL DEFAULT 0,
  `custom_deductions` JSON          NULL,
  `total_deductions`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `net_payable`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `generated_by`      INT UNSIGNED  NULL,
  `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payslip_user_month` (`user_id`, `month`, `year`),
  INDEX `idx_payslip_month` (`month`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
