<?php
// CLI helper: create an initial admin user.
// Usage: php tools/create_admin.php admin_username admin_password "Full Name" [role]

require_once __DIR__ . '/../assets/inc/config.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? null;
$full_name = $argv[3] ?? 'System Administrator';
$role = $argv[4] ?? 'admin';

if(!$password){
    fwrite(STDERR, "Password required. Usage: php tools/create_admin.php username password \"Full Name\" [role]\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
if(!$stmt){
    fwrite(STDERR, "Prepare failed: " . $mysqli->error . "\n");
    exit(1);
}
$stmt->bind_param('ssss', $username, $hash, $role, $full_name);
if($stmt->execute()){
    echo "Created user '{$username}' with id: " . $stmt->insert_id . "\n";
} else {
    fwrite(STDERR, "Insert failed: " . $stmt->error . "\n");
}
$stmt->close();
