<?php
/**
 * Database Configuration
 * Host  : portal.labxco.cloud (subdomain)
 * DB    : u587292075_portal
 */
define('DB_HOST', 'localhost');   // on cPanel shared hosts, DB is on localhost
define('DB_NAME', 'u587292075_portal');
define('DB_USER', 'u587292075_portal');
define('DB_PASS', 'Yuvraj@8600312640');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Set IST timezone for all PHP date functions
        date_default_timezone_set('Asia/Kolkata');

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Force consistent collation for the session to avoid collation mismatch errors
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("SET collation_connection = utf8mb4_general_ci");
            // Set MySQL session timezone to IST (+05:30)
            $pdo->exec("SET time_zone = '+05:30'");

            // ── Deduplicate users immediately on connect ──────────────
            // Remove stale employee row when a manager row exists for same email
            $pdo->exec("
                DELETE u1 FROM users u1
                INNER JOIN users u2 ON u1.email = u2.email AND u1.id != u2.id
                WHERE u1.role = 'employee' AND u2.role = 'manager'
            ");
            // Ensure UNIQUE on email
            try { $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_users_email` (`email`)"); } catch(Exception $e) {}            // Ensure UNIQUE on employees.email too
            try { $pdo->exec("ALTER TABLE `employees` ADD UNIQUE KEY `uq_emp_email` (`email`)"); } catch(Exception $e) {}
            // Ensure UNIQUE on employees.personal_email (nullable, so allow multiple NULLs)
            try { $pdo->exec("ALTER TABLE `employees` ADD UNIQUE KEY `uq_emp_personal_email` (`personal_email`)"); } catch(Exception $e) {}
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `attendance_locations` (
                  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                  `name`       VARCHAR(120)  NOT NULL,
                  `address`    VARCHAR(255)  NULL,
                  `latitude`   DECIMAL(10,7) NOT NULL DEFAULT 0.0000000,
                  `longitude`  DECIMAL(10,7) NOT NULL DEFAULT 0.0000000,
                  `radius_m`   INT UNSIGNED  NOT NULL DEFAULT 200,
                  `is_remote`  TINYINT(1)    NOT NULL DEFAULT 0,
                  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
                  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            $pdo->exec("
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
                  UNIQUE KEY `uq_user_date` (`user_id`,`log_date`),
                  INDEX `idx_log_date` (`log_date`),
                  INDEX `idx_user_id`  (`user_id`),
                  CONSTRAINT `fk_att_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
                  CONSTRAINT `fk_att_location` FOREIGN KEY (`location_id`) REFERENCES `attendance_locations`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `user_locations` (
                  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_id`     INT UNSIGNED NOT NULL,
                  `location_id` INT UNSIGNED NOT NULL,
                  `assigned_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_user_loc` (`user_id`,`location_id`),
                  CONSTRAINT `fk_ul_user` FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
                  CONSTRAINT `fk_ul_loc`  FOREIGN KEY (`location_id`) REFERENCES `attendance_locations`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            // Leave management tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS `leave_types` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`name` VARCHAR(100) NOT NULL,
              `days_per_credit` DECIMAL(5,1) NOT NULL DEFAULT 1.0,
              `credit_cycle` ENUM('monthly','yearly','manual') NOT NULL DEFAULT 'monthly',
              `credit_day` TINYINT NOT NULL DEFAULT 1,`max_carry_fwd` DECIMAL(5,1) NOT NULL DEFAULT 0,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,`color` VARCHAR(7) NOT NULL DEFAULT '#4f46e5',
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $pdo->exec("INSERT IGNORE INTO `leave_types` (`id`,`name`,`days_per_credit`,`credit_cycle`,`credit_day`,`max_carry_fwd`,`color`) VALUES
              (1,'Casual Leave',1.0,'monthly',1,0,'#10b981'),
              (2,'Privilege Leave',1.0,'monthly',1,30,'#4f46e5'),
              (3,'Sick Leave',1.0,'monthly',1,0,'#ef4444')");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `leave_balances` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`user_id` INT UNSIGNED NOT NULL,
              `leave_type_id` INT UNSIGNED NOT NULL,`balance` DECIMAL(5,1) NOT NULL DEFAULT 0,
              `used` DECIMAL(5,1) NOT NULL DEFAULT 0,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),UNIQUE KEY `uq_user_type` (`user_id`,`leave_type_id`),
              CONSTRAINT `fk_lb_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_lb_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `leave_credit_log` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`user_id` INT UNSIGNED NOT NULL,
              `leave_type_id` INT UNSIGNED NOT NULL,`days` DECIMAL(5,1) NOT NULL,
              `reason` VARCHAR(255) NULL,`credited_by` INT UNSIGNED NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),INDEX `idx_lcl_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `leave_applications` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`user_id` INT UNSIGNED NOT NULL,
              `leave_type_id` INT UNSIGNED NOT NULL,`from_date` DATE NOT NULL,`to_date` DATE NOT NULL,
              `days` DECIMAL(5,1) NOT NULL,`reason` TEXT NULL,
              `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
              `reviewed_by` INT UNSIGNED NULL,`reviewed_at` DATETIME NULL,`review_note` VARCHAR(255) NULL,
              `escalated` TINYINT(1) NOT NULL DEFAULT 0,`escalated_at` DATETIME NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),INDEX `idx_la_user` (`user_id`),INDEX `idx_la_status` (`status`),
              CONSTRAINT `fk_la_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_la_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            // Add escalation columns if missing (for existing installs)
            try { $pdo->exec("ALTER TABLE `leave_applications` ADD COLUMN `escalated` TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e) {}
            try { $pdo->exec("ALTER TABLE `leave_applications` ADD COLUMN `escalated_at` DATETIME NULL"); } catch(Exception $e) {}
            // Task assignments table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `task_assignments` (
              `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `project_id`   INT UNSIGNED NOT NULL,
              `assigned_to`  INT UNSIGNED NOT NULL COMMENT 'user_id of employee',
              `assigned_by`  INT UNSIGNED NOT NULL COMMENT 'user_id of manager',
              `subtask`      VARCHAR(100) NOT NULL,
              `from_date`    DATE         NOT NULL,
              `to_date`      DATE         NOT NULL,
              `hours`        DECIMAL(5,1) NOT NULL COMMENT 'Total hours for this task',
              `status`       ENUM('Pending','In Progress','Completed','On Hold') NOT NULL DEFAULT 'Pending',
              `notes`        TEXT         NULL,
              `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_ta_project`  (`project_id`),
              INDEX `idx_ta_assigned` (`assigned_to`),
              CONSTRAINT `fk_ta_project`  FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_ta_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_ta_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            // Projects table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `projects` (              `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
              `project_code`  VARCHAR(20)   NOT NULL UNIQUE,
              `project_name`  VARCHAR(150)  NOT NULL,
              `client_name`   VARCHAR(150)  NULL,
              `priority`      ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
              `manager_id`    INT UNSIGNED  NULL,
              `start_date`    DATE          NOT NULL,
              `deadline_date` DATE          NOT NULL,
              `total_hours`   DECIMAL(8,2)  NOT NULL DEFAULT 0 COMMENT 'Working hours excl. Sundays & holidays',
              `hr_rate`       DECIMAL(10,2) NOT NULL DEFAULT 0,
              `status`        ENUM('Planning','Active','On Hold','Completed','Cancelled') NOT NULL DEFAULT 'Planning',
              `description`   TEXT          NULL,
              `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_proj_manager` (`manager_id`),
              CONSTRAINT `fk_proj_manager` FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            // Holidays table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `holidays` (              `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
              `title`       VARCHAR(150)  NOT NULL,
              `holiday_date`DATE          NOT NULL,
              `description` VARCHAR(255)  NULL,
              `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_holiday_date` (`holiday_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            // Attendance regularization table
            try {                $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance_regularizations` (
                  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_id` INT UNSIGNED NOT NULL,
                  `log_date` DATE NOT NULL,
                  `req_clock_in` TIME NOT NULL,
                  `req_clock_out` TIME NOT NULL,
                  `reason` VARCHAR(255) NOT NULL,
                  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                  `reviewed_by` INT UNSIGNED NULL,
                  `reviewed_at` DATETIME NULL,
                  `review_note` VARCHAR(255) NULL,
                  `escalated` TINYINT(1) NOT NULL DEFAULT 0,
                  `escalated_at` DATETIME NULL,
                  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  INDEX `idx_reg_user` (`user_id`),
                  INDEX `idx_reg_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
                // Add FK separately so it doesn't fail if already exists
                try { $pdo->exec("ALTER TABLE `attendance_regularizations` ADD CONSTRAINT `fk_reg_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE"); } catch(Exception $e) {}
            } catch(Exception $e) { error_log('attendance_regularizations create: '.$e->getMessage()); }
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            header('Location: ../index.php?error=server');
            exit;
        }
    }
    return $pdo;
}
