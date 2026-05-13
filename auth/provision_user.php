<?php
/**
 * auth/provision_user.php
 * Creates a users row for a newly added employee and sends welcome email.
 *
 * Returns ['success' => bool, 'message' => string]
 */
require_once __DIR__ . '/mailer.php';

function provisionEmployeeUser(PDO $db, string $email, string $firstName, string $lastName): array {
    // Check if a user with this email already exists
    $existing = $db->prepare("SELECT id FROM users WHERE email = ?");
    $existing->execute([$email]);
    if ($existing->fetch()) {
        return ['success' => false, 'message' => "User account for $email already exists."];
    }

    $plainPassword = generatePassword(10);
    $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
    $fullName = trim("$firstName $lastName");

    $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'employee', 'active')")
       ->execute([$fullName, $email, $hashedPassword]);

    $sent = sendWelcomeEmail($email, $fullName, $plainPassword);

    return [
        'success' => true,
        'message' => "Login account created for $email." . ($sent ? " Welcome email sent." : " (Email delivery failed — check server mail config.)"),
    ];
}
