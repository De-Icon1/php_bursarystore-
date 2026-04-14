<?php
require_once 'assets/inc/config.php';

echo "========================================\n";
echo "  LOGIN CREDENTIALS FOR INDEX.PHP\n";
echo "========================================\n\n";

$res = $mysqli->query("SELECT user_id, username, full_name, role FROM users WHERE role IN ('admin','vc','storekeeper') ORDER BY role");

while($r = $res->fetch_assoc()){
    // Map known roles to their dashboard pages. Unknown roles fallback to admin.
    $role = strtolower($r['role']);
    switch($role){
        case 'superadmin':
            $redirect = 'admin_dashboard.php';
            break;
        case 'vc':
            $redirect = 'vc_dashboard.php';
            break;
        case 'storekeeper':
            $redirect = 'store_dashboard.php';
            break;
        case 'admin':
        default:
            $redirect = 'admin_dashboard.php';
    }

    echo strtoupper($r['role']) . " ACCOUNT:\n";
    echo "  Login Page: index.php\n";
    echo "  Username: " . $r['username'] . "\n";
    echo "  (Password hidden for security)\n";
    echo "  Full Name: " . $r['full_name'] . "\n";
    echo "  Role: " . $r['role'] . "\n";
    echo "  Redirects to: " . $redirect . "\n";
    echo "\n";
}

echo "========================================\n";
echo "HOW IT WORKS:\n";
echo "========================================\n";
echo "1. Go to: http://localhost/bursary/index.php\n";
echo "2. Enter username and password\n";
echo "3. System automatically redirects based on role:\n";
echo "   - Role 'admin' → admin_dashboard.php\n";
echo "   - Role 'vc' → vc_dashboard.php\n";
echo "   - Role 'storekeeper' → store_dashboard.php\n";
echo "\n";
?>
