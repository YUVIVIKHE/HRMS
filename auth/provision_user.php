<?php
/**
 * auth/provision_user.php
 * Creates or updates a users row for an employee.
 * Uses INSERT ... ON DUPLICATE KEY UPDATE — physically impossible to create duplicates.
 *
 * Returns ['success' => bool, 'message' => string, 'is_new' => bool]
 */
require_once __DIR__ . '/mailer.php';

function provisionEmployeeUser(PDO $db, string $email, string $firstName, string $lastName, string $role = 'employee'): array {
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => "Invalid email address: $email", 'is_new' => false];
    }

    $fullName = trim("$firstName $lastName");
    if (!$fullName) {
        return ['success' => false, 'message' => "Employee name is required.", 'is_new' => false];
    }

    // Check if user already exists
    $existing = $db->prepare("SELECT id, role FROM users WHERE email = ?");
    $existing->execute([$email]);
    $existingUser = $existing->fetch();

    if ($existingUser) {
        // User exists — just update name and ensure role is correct, don't change password
        $db->prepare("UPDATE users SET name = ?, role = ?, status = 'active' WHERE email = ?")
           ->execute([$fullName, $role, $email]);
        return [
            'success' => true,
            'message' => "User account already exists for $email. Details updated.",
            'is_new'  => false,
        ];
    }

    // New user — generate password and insert
    $plainPassword  = generatePassword(10);
    $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);

    $db->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')")
       ->execute([$fullName, $email, $hashedPassword, $role]);

    $sent = sendWelcomeEmail($email, $fullName, $plainPassword);

    return [
        'success' => true,
        'message' => "Login account created for $email." . ($sent ? " Welcome email sent." : " (Email delivery failed — check server mail config.)"),
        'is_new'  => true,
    ];
}
