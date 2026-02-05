<?php
// Protect the page
if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit;
}

// Get user role
$role = $_SESSION['role'];
?>

<div class="left-side-menu">
    <div class="slimscroll-menu">

        <!-- User Box -->
        <div class="user-box text-center">
            <img src="assets/images/users/default.png" alt="user-img" class="rounded-circle img-thumbnail avatar-md">
            <div class="dropdown">
                <a href="#" class="text-dark h5 mt-2 mb-1 d-block">
                    <?php echo $_SESSION['full_name']; ?>
                </a>
                <p class="text-muted">Electrical Unit</p>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <div id="sidebar-menu">

            <ul class="metismenu" id="side-menu">

                <!-- Dashboard -->
                <li>
                    <a href="electrical_dashboard.php">
                        <i class="mdi mdi-view-dashboard"></i>
                        <span> Dashboard </span>
                    </a>
                </li>

                <!-- Inventory -->
                <li>
                    <a href="javascript: void(0);">
                        <i class="mdi mdi-package-variant"></i>
                        <span> Inventory </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li><a href="electrical_dashboard.php">Receive Items</a></li>
                        <li><a href="inventory_report.php">Stock Report</a></li>
                        <li><a href="low_stock_alerts.php">Low Stock Alerts</a></li>
                    </ul>
                </li>

                <!-- Reports -->
                <li>
                    <a href="javascript: void(0);">
                        <i class="mdi mdi-chart-bar"></i>
                        <span> Reports </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li><a href="inventory_report.php">Inventory Report</a></li>
                        <li><a href="inventory_history.php">Inventory History</a></li>
                    </ul>
                </li>

            </ul>

        </div>
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
    <!-- Sidebar -left -->

</div>
