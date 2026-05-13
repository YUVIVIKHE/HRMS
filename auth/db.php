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
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Auto-create attendance tables if they don't exist
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            header('Location: ../index.php?error=server');
            exit;
        }
    }
    return $pdo;
}
