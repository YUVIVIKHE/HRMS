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
        } catch (PDOException $e) {
            // Never expose credentials in production
            error_log('DB Connection failed: ' . $e->getMessage());
            header('Location: ../index.php?error=server');
            exit;
        }
    }
    return $pdo;
}
