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
            $pdo->exec("SET time_zone = '+05:30'"  );
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
                INSERT IGNORE INTO `attendance_locations`
                  (`id`,`name`,`address`,`latitude`,`longitude`,`radius_m`,`is_remote`,`is_active`)
                VALUES (1,'Remote / Work from Home','Anywhere',0,0,0,1,1)
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
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),INDEX `idx_la_user` (`user_id`),INDEX `idx_la_status` (`status`),
              CONSTRAINT `fk_la_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_la_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            header('Location: ../index.php?error=server');
            exit;
        }
    }
    return $pdo;
}
