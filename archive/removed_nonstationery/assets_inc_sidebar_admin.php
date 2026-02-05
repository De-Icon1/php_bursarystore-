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
                <p class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></p>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <div id="sidebar-menu">

            <ul class="metismenu" id="side-menu">

                <!-- Dashboard -->
                <li>
                    <a href="admin_dashboard.php">
                        <i class="fe-monitor"></i>
                        <span> Dashboard </span>
                    </a>
                </li>

                <!-- Only Admin + Director -->
                <?php if ($role == 'admin' || $role == 'director') : ?>
                <li class="menu-title">Administration</li>

                <li>
                    <a href="manage_users.php">
                        <i class="fe-users"></i>
                        <span> Manage Users </span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Inventory Section -->
                <li class="menu-title">Inventory</li>

                <li class="has-submenu">
                    <a href="javascript:void(0);" class="waves-effect">
                        <i class="fe-box"></i>
                        <span> Store Management </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">

                        <li><a href="store_items.php">Register Item Types</a></li>
                        <li><a href="stationery_store.php">Stationery Store</a></li>
                        <li><a href="stock_receive.php">Receive New Stock</a></li>
                        <li><a href="stock_management.php">Manage Stock Levels</a></li>
                        <li><a href="low_stock_alerts.php">Low Stock Alerts</a></li>

                    </ul>
                </li>

                <!-- Reports -->
                <li class="menu-title">Reports</li>

                <li class="has-submenu">
                    <a href="javascript:void(0);" class="waves-effect">
                        <i class="fe-file-text"></i>
                        <span> Inventory Reports </span>
                        <span class="menu-arrow"></span>
                    </a>

                    <ul class="nav-second-level" aria-expanded="false">

                        <li><a href="inventory_report.php">Stock Balance Report</a></li>
                        <li><a href="inventory_history.php">Issuance History</a></li>
                        <li><a href="inventory_charts.php">Usage Charts</a></li>
                        <li><a href="download_stock.php">Export Stock (Excel)</a></li>
                        <li><a href="upload_stock.php">Import Stock (CSV)</a></li>

                    </ul>
                </li>

                <!-- Logout -->
                <li>
                    <a href="logout.php">
                        <i class="fe-log-out"></i>
                        <span> Logout </span>
                    </a>
                </li>

            </ul>

        </div>

        <div class="clearfix"></div>

    </div>
</div>
