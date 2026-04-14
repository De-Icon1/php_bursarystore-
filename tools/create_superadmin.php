<?php
require_once __DIR__ . '/../assets/inc/config.php';
require_once __DIR__ . '/../assets/inc/functions.php';

// Configure superadmin credentials here
$username = 'superadmin';
$password_plain = 'SuperAdminChangeMe!'; // change this immediately after creation
$full_name = 'Super Administrator';
$role = 'superadmin';

// Check if superadmin exists
$stmt = $mysqli->prepare('SELECT user_id FROM users WHERE username = ? OR role = ? LIMIT 1');
$stmt->bind_param('ss', $username, $role);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows > 0){
    echo "A user with that username or role already exists.\n";
    $stmt->close();
    exit;
}
$stmt->close();

$hash = password_hash($password_plain, PASSWORD_DEFAULT);
$ins = $mysqli->prepare('INSERT INTO users (username, password, role, full_name, created_at) VALUES (?, ?, ?, ?, NOW())');
$ins->bind_param('ssss', $username, $hash, $role, $full_name);
if($ins->execute()){
    $new_id = $ins->insert_id;
    echo "Superadmin created: user_id={$new_id}, username={$username}\n";
    log_action(0, 'Created superadmin account', $username);
} else {
    echo "Insert failed: " . $mysqli->error . "\n";
}
$ins->close();

echo "IMPORTANT: Change the password immediately and remove or secure this script.\n";
?>