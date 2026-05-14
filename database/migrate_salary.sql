-- ============================================================
--  Salary Structure — Migration (Updated with EPF/EPS/EDLI/PT)
-- ============================================================

DROP TABLE IF EXISTS `salary_structures`;

CREATE TABLE IF NOT EXISTS `salary_structures` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employee_id`           INT UNSIGNED  NOT NULL COMMENT 'FK to employees.id',
  `user_id`               INT UNSIGNED  NOT NULL COMMENT 'FK to users.id',
  `gross_salary`          DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Annual CTC',
  `basic_salary`          DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Monthly basic = gross/12/2',
  `hra`                   DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Monthly HRA = basic/2',
  `special_allowance`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `conveyance`            DECIMAL(12,2) NOT NULL DEFAULT 0,
  `education_allowance`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `lta`                   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `mediclaim_insurance`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `medical_reimbursement` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `mobile_internet`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `personal_allowance`    DECIMAL(12,2) NOT NULL DEFAULT 0,
  `bonus`                 DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Yearly bonus, added in Dec payslip',
  `income_tax_annual`     DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Annual income tax (new regime)',
  `professional_tax`      DECIMAL(12,2) NOT NULL DEFAULT 2500 COMMENT 'Annual PT fixed ₹2500',
  `epf_employee_rate`     DECIMAL(5,2)  NOT NULL DEFAULT 3.67 COMMENT 'EPF Employee %',
  `eps_employer_rate`     DECIMAL(5,2)  NOT NULL DEFAULT 8.33 COMMENT 'EPS Employer %',
  `edli_employer_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 0.50 COMMENT 'EDLI Employer %',
  `epf_admin_rate`        DECIMAL(5,2)  NOT NULL DEFAULT 0.50 COMMENT 'EPF Admin Charges %',
  `esi_employee_rate`     DECIMAL(5,2)  NOT NULL DEFAULT 0.75 COMMENT 'ESI Employee %',
  `esi_employer_rate`     DECIMAL(5,2)  NOT NULL DEFAULT 3.25 COMMENT 'ESI Employer %',
  `tax_regime`            ENUM('old','new') NOT NULL DEFAULT 'new',
  `custom_deductions`     JSON          NULL COMMENT 'Array of {name, amount}',
  `custom_additions`      JSON          NULL COMMENT 'Array of {name, amount} - other additions',
  `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salary_user` (`user_id`),
  INDEX `idx_salary_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
