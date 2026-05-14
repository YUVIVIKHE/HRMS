-- ============================================================
--  Salary Structure — Migration
-- ============================================================

CREATE TABLE IF NOT EXISTS `salary_structures` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employee_id`           INT UNSIGNED  NOT NULL COMMENT 'FK to employees.id',
  `user_id`               INT UNSIGNED  NOT NULL COMMENT 'FK to users.id',
  `gross_salary`          DECIMAL(12,2) NOT NULL DEFAULT 0,
  `basic_salary`          DECIMAL(12,2) NOT NULL DEFAULT 0,
  `hra`                   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `special_allowance`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `conveyance`            DECIMAL(12,2) NOT NULL DEFAULT 0,
  `education_allowance`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `lta`                   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `mediclaim_insurance`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `medical_reimbursement` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `mobile_internet`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `personal_allowance`    DECIMAL(12,2) NOT NULL DEFAULT 0,
  `bonus`                 DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Yearly bonus, added in Dec payslip',
  `professional_tax`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `tax_regime`            ENUM('old','new') NOT NULL DEFAULT 'new',
  `esi_rate`              DECIMAL(5,2)  NOT NULL DEFAULT 0.75,
  `pf_rate`               DECIMAL(5,2)  NOT NULL DEFAULT 12.00,
  `custom_deductions`     JSON          NULL COMMENT 'Array of {name, amount}',
  `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salary_user` (`user_id`),
  INDEX `idx_salary_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
