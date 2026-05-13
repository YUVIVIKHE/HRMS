<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../auth/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Invalid request']); exit;
}

$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');

if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'msg'=>'Invalid name or email']); exit;
}

$sender    = $_SESSION['user_name'] ?? 'Your Team';
$loginUrl  = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'portal.labxco.cloud');
$firstName = explode(' ', $name)[0];

$subject = "🎂 Happy Birthday, $firstName!";
$body    = "Dear $firstName,\n\n"
         . "Wishing you a very Happy Birthday! 🎉🎂\n\n"
         . "May this special day bring you joy, happiness, and all the success you deserve.\n\n"
         . "Warm wishes from $sender and the entire team at HRMS Portal.\n\n"
         . "Have a wonderful day!\n\n"
         . "— $sender\n"
         . "HRMS Portal | $loginUrl";

$headers  = "From: noreply@".($_SERVER['HTTP_HOST']??'portal.labxco.cloud')."\r\n";
$headers .= "Reply-To: noreply@".($_SERVER['HTTP_HOST']??'portal.labxco.cloud')."\r\n";
$headers .= "X-Mailer: PHP/".phpversion();

$sent = mail($email, $subject, $body, $headers);

echo json_encode([
    'ok'  => $sent,
    'msg' => $sent ? "Wish sent to $email" : "Mail delivery failed. Check server mail config.",
]);
