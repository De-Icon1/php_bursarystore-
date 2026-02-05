<?php
require_once 'assets/inc/config.php';

echo "=== USERS TABLE STRUCTURE ===\n";
$res = $mysqli->query("DESCRIBE users");
while($r = $res->fetch_assoc()){
    echo "{$r['Field']} - {$r['Type']} - Null: {$r['Null']} - Default: {$r['Default']}\n";
}

echo "\n=== UPDATE ROLES DIRECTLY ===\n";
$mysqli->query("UPDATE users SET role = 'vc' WHERE user_id = 2");
echo "Updated user_id 2 (vc)\n";

$mysqli->query("UPDATE users SET role = 'storekeeper' WHERE user_id = 3");
echo "Updated user_id 3 (storekeeper)\n";

echo "\n=== CHECK RESULTS ===\n";
$res = $mysqli->query("SELECT user_id, username, role FROM users");
while($r = $res->fetch_assoc()){
    echo "ID: {$r['user_id']} | Username: {$r['username']} | Role: [{$r['role']}]\n";
}
?>
