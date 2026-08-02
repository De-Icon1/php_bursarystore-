<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/functions.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$forced = !empty($_SESSION['must_change_password']);
$err = $success = '';

function redirect_to_dashboard(){
    $role = strtolower($_SESSION['role'] ?? '');
    switch($role){
        case 'admin':
        case 'superadmin':
        case 'director':
            header('Location: admin_dashboard.php');
            break;
        case 'storekeeper':
        case 'supervisor':
            header('Location: store_dashboard.php');
            break;
        case 'vc':
            header('Location: vc_dashboard.php');
            break;
        default:
            header('Location: admin_dashboard.php');
    }
    exit;
}

if(isset($_POST['change_password'])){
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    $stmt = $mysqli->prepare('SELECT password FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($db_password);
    $stmt->fetch();
    $stmt->close();

    if(!password_verify($current_password, $db_password)){
        $err = 'Current password is incorrect.';
    } elseif(strlen($new_password) < 8){
        $err = 'New password must be at least 8 characters.';
    } elseif($new_password !== $confirm_password){
        $err = 'New password and confirmation do not match.';
    } elseif(password_verify($new_password, $db_password)){
        $err = 'New password must be different from the current password.';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $mysqli->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?');
        $upd->bind_param('si', $hash, $_SESSION['user_id']);
        if($upd->execute()){
            $upd->close();
            log_action($mysqli, $_SESSION['user_id'], 'Changed password');
            unset($_SESSION['must_change_password']);
            redirect_to_dashboard();
        } else {
            $err = 'Error updating password: ' . $mysqli->error;
            $upd->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Change Password | OOU Bursary Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/oou.png">

    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <script src="assets/js/swal.js"></script>

    <style>
        body.authentication-bg, body.authentication-bg-pattern {
            background-image: none !important;
            background-repeat: none !important;
            background-position: initial !important;
            background-size: initial !important;
            background-color: #f7f7f7 !important;
            background-attachment: scroll !important;
        }
        .bg-pattern, .card.bg-pattern { background-image: none !important; background-color: transparent !important; }
    </style>

    <?php if(!empty($err)) { ?>
    <script>
        setTimeout(function () { swal("Failed", <?php echo json_encode($err); ?>, "error"); }, 200);
    </script>
    <?php } ?>
</head>

<body class="authentication-bg authentication-bg-pattern">

<div class="account-pages mt-5 mb-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">

                <div class="card bg-pattern">
                    <div class="card-body p-4">

                        <div class="text-center w-75 m-auto">
                            <img src="assets/images/OOU.png" alt="" height="46">
                            <h4 class="text-dark-50 text-center mt-3 font-weight-bold">Change Password</h4>
                            <?php if($forced): ?>
                                <p class="text-muted mb-4 mt-2">For your security, you must set a new password before you can continue.</p>
                            <?php else: ?>
                                <p class="text-muted mb-4 mt-2">Update your account password.</p>
                            <?php endif; ?>
                        </div>

                        <form method="post">

                            <div class="form-group mb-3">
                                <label>Current Password</label>
                                <input class="form-control" name="current_password" type="password" required placeholder="Enter your current password">
                            </div>

                            <div class="form-group mb-3">
                                <label>New Password</label>
                                <input class="form-control" name="new_password" type="password" required minlength="8" placeholder="At least 8 characters">
                            </div>

                            <div class="form-group mb-3">
                                <label>Confirm New Password</label>
                                <input class="form-control" name="confirm_password" type="password" required minlength="8" placeholder="Re-enter new password">
                            </div>

                            <div class="form-group mb-0 text-center">
                                <button name="change_password" type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-key mr-1"></i> Update Password
                                </button>
                            </div>

                        </form>

                        <?php if(!$forced): ?>
                        <div class="text-center mt-3">
                            <a href="logout.php">Cancel and log out</a>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12 text-center">
                        <p class="text-white-50"><small>&copy; <?php echo date('Y'); ?> Olabisi Onabanjo University - Bursary Store</small></p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>

</body>
</html>
