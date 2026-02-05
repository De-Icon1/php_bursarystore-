<?php
    $uid = $_SESSION['user_id'] ?? null;
    $ret = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $mysqli->prepare($ret);
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while($row = $res->fetch_object()) {
?>
    <div class="navbar-custom">
        <ul class="list-unstyled topnav-menu float-right mb-0">

            <li class="d-none d-sm-block">
                <form class="app-search">
                    <div class="app-search-box">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search inventory...">
                            <div class="input-group-append">
                                <button class="btn" type="submit">
                                    <i class="fe-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </li>

            <!-- Notifications -->
            <li class="dropdown notification-list">
                <a class="nav-link dropdown-toggle nav-user mr-0 waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <span class="pro-user-name ml-1">
                        <?php echo htmlspecialchars($row->username ?? ''); ?> <i class="mdi mdi-chevron-down"></i> 
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-right profile-dropdown">
                    <a href="index.php" class="dropdown-item notify-item">
                        <i class="fe-home"></i>
                        <span>Home</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="his_admin_logout.php" class="dropdown-item notify-item">
                        <i class="fe-log-out"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </li>

        </ul>

        <!-- LOGO -->
        <div class="logo-box">
            <a href="admin_dashboard.php" class="logo text-center">
                <span class="logo-lg">
                    <img src="assets/images/OOU.png" alt="" height="45">
                </span>
                <span class="logo-sm">
                    <img src="assets/images/logo-sm-white.png" alt="" height="24">
                </span>
            </a>
        </div>

        <ul class="list-unstyled topnav-menu topnav-menu-left m-0">
            <li>
                <button class="button-menu-mobile waves-effect waves-light">
                    <i class="fe-menu"></i>
                </button>
            </li>

            <!-- Inventory Operations -->
            <li class="dropdown d-none d-lg-block">
                <a class="nav-link dropdown-toggle waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="fe-plus-circle mr-1"></i> Stock Operations
                    <i class="mdi mdi-chevron-down"></i> 
                </a>
                <div class="dropdown-menu">
                    <a href="stock_receive.php" class="dropdown-item">
                        <i class="fas fa-arrow-down mr-1"></i>
                        <span>Receive Stock</span>
                    </a>

                    <a href="store_items.php" class="dropdown-item">
                        <i class="fas fa-inbox mr-1"></i>
                        <span>Add New Item</span>
                    </a>

                    <div class="dropdown-divider"></div>

                    <a href="stock_management.php" class="dropdown-item">
                        <i class="fas fa-sliders-h mr-1"></i>
                        <span>Manage Stock Levels</span>
                    </a>

                    <a href="stock_review.php" class="dropdown-item">
                        <i class="fas fa-eye mr-1"></i>
                        <span>Stock Review</span>
                    </a>

                    <a href="low_stock_alerts.php" class="dropdown-item">
                        <i class="fas fa-bell mr-1"></i>
                        <span>Low Stock Alerts</span>
                    </a>
                </div>
            </li>

            <!-- Reporting -->
            <li class="dropdown d-none d-lg-block">
                <a class="nav-link dropdown-toggle waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="fe-bar-chart-2 mr-1"></i> Reports
                    <i class="mdi mdi-chevron-down"></i> 
                </a>
                <div class="dropdown-menu">
                    <a href="inventory_report.php" class="dropdown-item">
                        <i class="fas fa-file-alt mr-1"></i>
                        <span>Inventory Report</span>
                    </a>

                    <a href="inventory_history.php" class="dropdown-item">
                        <i class="fas fa-history mr-1"></i>
                        <span>Stock History</span>
                    </a>

                    <a href="inventory_charts.php" class="dropdown-item">
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>Stock Charts</span>
                    </a>

                    <div class="dropdown-divider"></div>

                    <!-- Diesel reports removed (not used in stationery-only mode) -->
                </div>
            </li>

            <!-- Vehicle & Assets -->
            <li class="dropdown d-none d-lg-block">
                <a class="nav-link dropdown-toggle waves-effect waves-light" data-toggle="dropdown" href="#" role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="fe-truck mr-1"></i> Fleet Management
                    <i class="mdi mdi-chevron-down"></i> 
                </a>
                <div class="dropdown-menu">
                    <!-- Vehicle links removed (not used in stationery-only mode) -->

                    <!-- Tyre Assignment removed (table not present) -->
                </div>
            </li>

        </ul>
    </div>
<?php } ?>
