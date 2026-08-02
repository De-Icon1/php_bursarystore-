<?php
require 'assets/inc/config.php';
$stmt = $mysqli->prepare("SELECT username, role, full_name, password FROM users ORDER BY role, username");
$stmt->execute();
$stmt->bind_result($username, $role, $full_name, $password);
while ($stmt->fetch()) {
    echo $username . '|' . $role . '|' . $full_name . '|' . substr($password, 0, 20) . PHP_EOL;
}
$stmt->close();
$mysqli->close();
