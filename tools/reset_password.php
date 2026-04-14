<?php
// tools/reset_password.php
// Usage (CLI): php tools/reset_password.php <user_id|username> <new_password>

if (php_sapi_name() !== 'cli'){
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../assets/inc/config.php';
require_once __DIR__ . '/../assets/inc/functions.php';

if ($argc < 3){
    echo "Usage: php tools/reset_password.php <user_id|username> <new_password>\n";
    exit(1);
}

$target = $argv[1];
$new_password = $argv[2];

// Validate password length
if(strlen($new_password) < 8){
    echo "Password must be at least 8 characters.\n";
    exit(1);
}

// Determine whether target is numeric id or username
if(ctype_digit($target)){
    $user_id = (int)$target;
    $stmt = $mysqli->prepare('SELECT user_id, username, role FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
} else {
    $username = $target;
    $stmt = $mysqli->prepare('SELECT user_id, username, role FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
}

$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if(!$user){
    echo "User not found: {$target}\n";
    exit(1);
}

$hash = password_hash($new_password, PASSWORD_DEFAULT);
$upd = $mysqli->prepare('UPDATE users SET password = ? WHERE user_id = ?');
$upd->bind_param('si', $hash, $user['user_id']);
if(!$upd->execute()){
    echo "Failed to update password: " . $upd->error . "\n";
    $upd->close();
    exit(1);
}
$upd->close();

// Log action (user_id 0 used for system-script). Adjust if you want to pass an acting admin id.
log_action(0, 'Password reset via CLI', json_encode(['target_user_id' => $user['user_id'], 'target_username' => $user['username']]));

echo "Password updated for user_id={$user['user_id']}, username={$user['username']}\n";
echo "IMPORTANT: share the new password securely and force a password change at first login if desired.\n";

?>