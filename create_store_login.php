<?php
require 'assets/inc/config.php';

$username = 'storekeeper';
$password = 'store123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$full_name = 'Store Keeper';
$role = 'storekeeper';

// force the storekeeper to set their own password on first login
$stmt = $mysqli->prepare('INSERT INTO users (username, password, role, full_name, must_change_password) VALUES (?, ?, ?, ?, 1)');
$stmt->bind_param('ssss', $username, $hash, $role, $full_name);
$stmt->execute();
$stmt->close();

echo "Store account created successfully.\n";
echo "Username: $username\n";
echo "Password: $password\n";
$mysqli->close();
