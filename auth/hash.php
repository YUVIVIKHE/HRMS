<?php
/**
 * One-time utility: generate bcrypt hashes for seeding users.
 * DELETE THIS FILE after use — never leave it on production!
 *
 * Usage: http://portal.labxco.cloud/auth/hash.php?pw=YourPassword
 */
if (empty($_GET['pw'])) {
    echo '<form>Password: <input name="pw" type="text" size="30"> <button>Hash it</button></form>';
    exit;
}
$plain = $_GET['pw'];
$hash  = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
echo '<pre>';
echo "Plain  : " . htmlspecialchars($plain) . "\n";
echo "Hash   : " . $hash . "\n";
echo "Verify : " . (password_verify($plain, $hash) ? '✅ OK' : '❌ FAIL') . "\n";
echo '</pre>';
echo '<p style="color:red"><strong>⚠ Delete this file after use!</strong></p>';
