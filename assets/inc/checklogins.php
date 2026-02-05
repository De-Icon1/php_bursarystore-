<?php
// assets/inc/checklogins.php
// Open-ended login + role checks — returns booleans, no hard redirects.

/**
 * Simple check if user is logged in.
 * Returns TRUE if logged in, FALSE otherwise.
 */
function check_login()
{
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    return true;
}

/**
 * Role-based authorization check.
 * Returns TRUE if role allowed, FALSE otherwise.
 *
 * $allowed_roles: array of roles (e.g. ['admin','storekeeper'])
 * If empty, any logged-in user is considered authorized.
 */
function authorize($allowed_roles = [])
{
    if (!isset($_SESSION['role']) || empty($_SESSION['role'])) {
        return false;
    }

    if (empty($allowed_roles)) {
        return true;
    }

    $user_role = strtolower($_SESSION['role']);
    $allowed_roles = array_map('strtolower', $allowed_roles);

    return in_array($user_role, $allowed_roles);
}
