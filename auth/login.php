<?php
/**
 * Login Handler — auth/login.php
 * Validates credentials against `users` table and redirects by role.
 *
 * Expected `users` table columns:
 *   id, name, email, password (bcrypt hash), role (admin|manager|employee), status (active|inactive)
 */
session_start();
require_once __DIR__ . '/db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$email    = trim($_POST['email']   ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: ../index.php?error=invalid');
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        header('Location: ../index.php?error=invalid');
        exit;
    }

    if ($user['status'] !== 'active') {
        header('Location: ../index.php?error=inactive');
        exit;
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    $redirects = [
        'admin'    => '../admin/dashboard.php',
        'manager'  => '../manager/dashboard.php',
        'employee' => '../employee/dashboard.php',
    ];

    $target = $redirects[$user['role']] ?? '../index.php';
    header('Location: ' . $target);
    exit;

} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    header('Location: ../index.php?error=server');
    exit;
}
