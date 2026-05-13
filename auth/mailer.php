<?php
/**
 * auth/mailer.php
 * Simple mail helper using PHP's mail() function.
 * For production, swap sendWelcomeEmail() body to use PHPMailer/SMTP.
 */

function generatePassword(int $length = 10): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#$!';
    $pass  = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

function sendWelcomeEmail(string $toEmail, string $toName, string $plainPassword): bool {
    $subject  = 'Your HRMS Portal Account is Ready';
    $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'portal.labxco.cloud') . '/index.php';

    $body = "Dear $toName,\n\n"
          . "Your HRMS Portal account has been created. Here are your login credentials:\n\n"
          . "  Login URL : $loginUrl\n"
          . "  Email     : $toEmail\n"
          . "  Password  : $plainPassword\n\n"
          . "Please log in and change your password immediately.\n\n"
          . "Regards,\nHRMS Admin Team";

    $headers  = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'portal.labxco.cloud') . "\r\n";
    $headers .= "Reply-To: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'portal.labxco.cloud') . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($toEmail, $subject, $body, $headers);
}

function sendPromotionEmail(string $toEmail, string $toName, string $department): bool {
    $subject  = 'Congratulations! You have been promoted to Manager';
    $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'portal.labxco.cloud') . '/index.php';

    $body = "Dear $toName,\n\n"
          . "Congratulations! 🎉\n\n"
          . "We are pleased to inform you that you have been promoted to the role of Manager"
          . ($department ? " for the $department department" : "") . ".\n\n"
          . "Your login credentials remain the same. You can now access the Manager Dashboard "
          . "where you can view your team, manage leave requests, assign tasks, and track attendance.\n\n"
          . "  Login URL : $loginUrl\n"
          . "  Email     : $toEmail\n"
          . "  (Use your existing password)\n\n"
          . "Welcome to your new role. We look forward to your leadership!\n\n"
          . "Regards,\nHRMS Admin Team";

    $headers  = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'portal.labxco.cloud') . "\r\n";
    $headers .= "Reply-To: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'portal.labxco.cloud') . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($toEmail, $subject, $body, $headers);
}
