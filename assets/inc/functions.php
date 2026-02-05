<?php
function log_action($user_id, $action) {
    global $mysqli; // ✅ so it can use $mysqli from config.php

    $ipaddress = $_SERVER['REMOTE_ADDR']; // correct IP
    $log_stmt = $mysqli->prepare("INSERT INTO logs (user_id, action, mac) VALUES (?, ?, ?)");
    $log_stmt->bind_param('iss', $user_id, $action, $ipaddress);
    $log_stmt->execute();
    $log_stmt->close();
}
?>