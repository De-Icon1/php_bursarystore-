<?php
// CLI helper: reset a user's password.
// Usage:
// php reset_vc_password.php --username=vc_user --password=vc123

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/assets/inc/config.php';

$opts = getopt('', ['username::', 'password::', 'help::']);
if (isset($opts['help'])) {
    echo "Usage: php reset_vc_password.php --username=vc_user --password=vc123\n";
    exit(0);
}

$username = $opts['username'] ?? 'vc_user';
$password = $opts['password'] ?? 'vc123';

if (empty($username) || empty($password)) {
    echo "Username and password must not be empty.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare('UPDATE users SET password = ? WHERE username = ?');
if (!$stmt) {
    echo "DB prepare failed: " . $mysqli->error . "\n";
    exit(1);
}

$stmt->bind_param('ss', $hash, $username);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "Password for '{$username}' updated successfully.\n";
    } else {
        echo "No user updated. Check that username '{$username}' exists.\n";
    }
} else {
    echo "Execution failed: " . $stmt->error . "\n";
}

$stmt->close();

exit(0);
