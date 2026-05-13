<?php
/**
 * auth/guard.php
 * Include at the top of every protected page.
 * Usage: require_once __DIR__ . '/../auth/guard.php';
 *        guardRole('admin');   // or 'manager' / 'employee'
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function guardRole(string $required): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }
    if ($_SESSION['role'] !== $required) {
        // Redirect to the correct dashboard if role mismatch
        $map = [
            'admin'    => '../admin/dashboard.php',
            'manager'  => '../manager/dashboard.php',
            'employee' => '../employee/dashboard.php',
        ];
        header('Location: ' . ($map[$_SESSION['role']] ?? '../index.php'));
        exit;
    }
}
