<?php
function log_action()
{
    // Flexible signature to support different callers across the app:
    // - log_action($user_id, $action)
    // - log_action($mysqli, $user_id, $action, $meta)
    // - log_action($user_id, $action, $meta)
    global $mysqli;

    $args = func_get_args();
    if (isset($args[0]) && $args[0] instanceof mysqli) {
        // some callers pass $mysqli as the first arg; drop it
        array_shift($args);
    }

    $user_id = isset($args[0]) ? (int)$args[0] : 0;
    $action = isset($args[1]) ? $args[1] : '';
    // optional meta appended to action for storage in the single `action` column
    if (isset($args[2])) {
        $meta = is_string($args[2]) ? $args[2] : json_encode($args[2]);
        if ($meta !== '') {
            $action .= ' | ' . $meta;
        }
    }

    // When executed from CLI, REMOTE_ADDR may be undefined
    $ipaddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';

    if ($user_id > 0) {
        $log_stmt = $mysqli->prepare("INSERT INTO logs (user_id, action, mac) VALUES (?, ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param('iss', $user_id, $action, $ipaddress);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } else {
        // Insert with NULL user_id to satisfy foreign key constraints
        $log_stmt = $mysqli->prepare("INSERT INTO logs (user_id, action, mac) VALUES (NULL, ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param('ss', $action, $ipaddress);
            $log_stmt->execute();
            $log_stmt->close();
        }
    }
}

/**
 * Run a mysqli query safely, catching exceptions when mysqli is configured
 * to throw. Returns the mysqli_result on success or false on failure.
 */
function safe_query($mysqli, $sql)
{
    try {
        // Prefer the mysqli object method
        return $mysqli->query($sql);
    } catch (Exception $e) {
        // swallow and return false so callers can handle gracefully
        return false;
    }
}
?>