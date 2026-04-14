<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/functions.php');

// Show a success message after logout redirect
$success = $success ?? '';
if(isset($_GET['logged_out'])){
    $success = "Successfully logged out.";
}

if(isset($_POST['admin_login'])) {
    $username = trim($_POST['ad_id']);
    $username = str_replace(["\n","\r","\t"], "", $username);
    $username = trim(stripslashes($username));
    $entered_password = trim($_POST['ad_pwd']);

    // Fetch user
    $stmt = $mysqli->prepare(
        "SELECT user_id, username, password, role, full_name FROM users WHERE username = ?"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if($stmt->num_rows === 1){
        $stmt->bind_result($user_id, $db_username, $db_password, $user_role, $full_name);
        $stmt->fetch();

        // verify password using standard PHP password hashing
        if(password_verify($entered_password, $db_password)){

            // normalize role and store session
            $role_normalized = strtolower(trim($user_role));
            if($role_normalized === 'vice chancellor') $role_normalized = 'vc';

            $_SESSION['user_id']   = $user_id;
            $_SESSION['username']  = $db_username;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role']      = $role_normalized;

            // role-based redirects (use normalized role)
            switch($role_normalized){
                case 'admin':
                    header("Location: admin_dashboard.php");
                    break;
                case 'vc':
                    header("Location: vc_dashboard.php");
                    break;

                default:
                    header("Location: admin_dashboard.php");
            }
            exit;

        } else {
            $err = "Incorrect password.";
        }

    } else {
        $err = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>OOU Bursary Store | Inventory Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/oou.png">

    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

    <script src="assets/js/swal.js"></script>

    <!-- Neutral background for authentication pages (no image) -->
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
    <?php if(!empty($success)) { ?>
    <script>
        setTimeout(function () { swal("Success", <?php echo json_encode($success); ?>, "success"); }, 200);
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
                            <a href="index.php">
                                <img src="assets/images/OOU.png" alt="" height="46">
                            </a>
                            <h4 class="text-dark-50 text-center mt-3 font-weight-bold">OOU Bursary Store</h4>
                            <p class="text-muted mb-4 mt-2">Bursary Store Inventory — enter credentials to manage stationery and consumables</p>
                        </div>

                        <form method="post">

                            <div class="form-group mb-3">
                                <label>Staff Number / Username</label>
                                <input class="form-control" name="ad_id" type="text" required placeholder="Enter your staff number">
                            </div>

                            <div class="form-group mb-3">
                                <label>Password</label>
                                <input class="form-control" name="ad_pwd" type="password" required placeholder="Enter your password">
                            </div>

                            <div class="form-group mb-0 text-center">
                                <button name="admin_login" type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Access Bursary Store
                                </button>
                            </div>

                        </form>

                        <div class="text-center mt-3">
                            <small class="text-muted">Need help signing in? Contact IT Support: <a href="mailto:it-support@oou.edu.ng">it-support@oou.edu.ng</a> </small>
                        </div>

                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12 text-center">
                        <p class="text-white-50"><small>&copy; <?php echo date('Y'); ?> Olabisi Onabanjo University - Bursary Store</small></p>
                        <p><a href="#" class="text-white-50 ml-1">Need help? Contact IT Support</a></p>
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