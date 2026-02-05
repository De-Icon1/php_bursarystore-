<?php
session_start();
include('assets/inc/config.php'); // must define $mysqli
include('assets/inc/functions.php'); // optional, keep if you use log_action etc.

// Simple auth for the new users table
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$uid = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
    
    <!--Head Code-->

<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<!-- Sidebar is included inside the wrapper below; avoid duplicate include here -->

        <!-- Begin page -->
        <div id="wrapper">

        
            <!-- ========== Left Sidebar Start ========== -->
            <?php include('assets/inc/sidebar_admin.php');?>
            <!-- Left Sidebar End -->

            <!-- ============================================================== -->
            <!-- Start Page Content here -->
            <!-- ============================================================== -->

            <div class="content-page">
                <div class="content">

                    <!-- Start Content-->
                    <div class="container-fluid">
                        
                        <!-- start page title -->
                        <div class="row">
                            <div class="col-12">
                                <div class="page-title-box">
                                    <h4 class="page-title">OOU Bursary Store Dashboard</h4>
                                    <p class="mb-0 text-muted">Welcome, <?php echo htmlspecialchars($full_name); ?> — role: <?php echo htmlspecialchars(ucfirst($role)); ?></p>
                                </div>
                            </div>
                        </div>     
                        <!-- end page title --> 
                        <!-- Role-specific quick links -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card-box">
                                    <h5 class="mb-2">Quick Links</h5>
                                    <div class="btn-group" role="group">
                                        <?php if(strtolower($role) === 'storekeeper'): ?>
                                            <a href="stock_receive.php" class="btn btn-success">Receive Stock</a>
                                            <a href="issue_items.php" class="btn btn-warning">Issue Items</a>
                                            <a href="stationery_store.php" class="btn btn-primary">Stationery Store</a>
                                        <?php elseif(strtolower($role) === 'admin'): ?>
                                            <a href="manage_users.php" class="btn btn-secondary">Manage Users</a>
                                            <a href="stationery_store.php" class="btn btn-primary">Stationery Store</a>
                                            <a href="inventory_report.php" class="btn btn-info">Inventory Report</a>
                                        <?php elseif(strtolower($role) === 'vc' || strtolower($role) === 'director'): ?>
                                            <a href="inventory_report.php" class="btn btn-info">View Reports</a>
                                            <a href="inventory_charts.php" class="btn btn-dark">Usage Charts</a>
                                        <?php else: ?>
                                            <a href="stationery_store.php" class="btn btn-primary">Browse Stationery</a>
                                            <a href="create_request.php" class="btn btn-outline-primary">Request Items</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Detect which column holds the item display name to avoid SQL errors
                        $has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'")->num_rows;
                        $has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'")->num_rows;
                        if($has_name_col && $has_item_name_col){
                            $item_col_where = "COALESCE(name,item_name)"; // for WHERE clauses without table alias
                            $item_col_it = "COALESCE(it.name,it.item_name)"; // when using alias it
                            $item_col_select = "COALESCE(it.item_name,it.name)"; // prefer item_name for historical display
                        } elseif($has_name_col){
                            $item_col_where = "name";
                            $item_col_it = "it.name";
                            $item_col_select = "it.name";
                        } elseif($has_item_name_col){
                            $item_col_where = "item_name";
                            $item_col_it = "it.item_name";
                            $item_col_select = "it.item_name";
                        } else {
                            $item_col_where = "''";
                            $item_col_it = "''";
                            $item_col_select = "''";
                        }

                        // Basic counts for inventory cards using the advanced schema (items, stock_balance, stock_issues)
                        // Total distinct items
                        $total_items = 0;
                        $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM items");
                        if($res){
                            $r = $res->fetch_assoc();
                            $total_items = $r['cnt'];
                        }

                        // Total stock across all items (try legacy `stock_balance`, else fall back to `stock_transactions`)
                        $total_stock = 0;
                        $has_stock_balance = $mysqli->query("SHOW TABLES LIKE 'stock_balance'")->num_rows;
                        if($has_stock_balance){
                            $res = $mysqli->query("SELECT COALESCE(SUM(quantity),0) AS total_stock FROM stock_balance");
                            if($res){ $r = $res->fetch_assoc(); $total_stock = (int)$r['total_stock']; }
                        } else {
                            // fallback: sum qty_change from stock_transactions if available
                            $has_tx = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'")->num_rows;
                            if($has_tx){
                                $res = $mysqli->query("SELECT COALESCE(SUM(qty_change),0) AS total_stock FROM stock_transactions");
                                if($res){ $r = $res->fetch_assoc(); $total_stock = (int)$r['total_stock']; }
                            }
                        }

                        // Total issued quantity (try legacy `stock_issues`, else compute from `stock_transactions` dispatches)
                        $total_issued = 0;
                        $has_stock_issues = $mysqli->query("SHOW TABLES LIKE 'stock_issues'")->num_rows;
                        if($has_stock_issues){
                            $res = $mysqli->query("SELECT COALESCE(SUM(quantity),0) AS total_issued FROM stock_issues");
                            if($res){ $r = $res->fetch_assoc(); $total_issued = (int)$r['total_issued']; }
                        } else {
                            $has_tx = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'")->num_rows;
                            if($has_tx){
                                // dispatched items are stored as negative qty_change or tx_type='dispatch'
                                $res = $mysqli->query("SELECT COALESCE(SUM(-qty_change),0) AS total_issued FROM stock_transactions WHERE qty_change < 0");
                                if($res){ $r = $res->fetch_assoc(); $total_issued = (int)$r['total_issued']; }
                            }
                        }

                        // Tyres/diesel/service metrics archived — set safe defaults for stationery-only mode
                        $tyre_count = 0;
                        $assigned_tyres = 0;
                        $diesel_total = 0;
                        $service_count = 0;

                        // Stationery store metrics: total items and total stock (Stationery + Toner)
                        $stationery_total_items = 0;
                        $stationery_total_stock = 0;
                        $stationery_low_items = [];

                        $stationery_filter = "(it.category IN ('Stationery','Toner') OR " . $item_col_it . " LIKE '%pen%' OR " . $item_col_it . " LIKE '%paper%' OR " . $item_col_it . " LIKE '%toner%')";

                        $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM items it WHERE " . $stationery_filter);
                        if($res){ $r = $res->fetch_assoc(); $stationery_total_items = (int)$r['cnt']; }

                        $has_stock_balance = $mysqli->query("SHOW TABLES LIKE 'stock_balance'")->num_rows;
                        $has_thresholds = $mysqli->query("SHOW TABLES LIKE 'inventory_thresholds'")->num_rows;

                        if($has_stock_balance){
                            $sql = "SELECT COALESCE(SUM(sb.quantity),0) AS total FROM items it LEFT JOIN stock_balance sb ON it.item_id = sb.item_id WHERE " . $stationery_filter;
                            $res = $mysqli->query($sql);
                            if($res){ $r = $res->fetch_assoc(); $stationery_total_stock = (int)$r['total']; }

                            // low items using stock_balance / thresholds
                            if($has_thresholds){
                                $sql = "SELECT " . $item_col_select . " AS item_name, COALESCE(sb.quantity,0) AS qty, t.threshold_qty FROM items it LEFT JOIN stock_balance sb ON it.item_id = sb.item_id LEFT JOIN inventory_thresholds t ON it.item_id = t.item_id WHERE (".$stationery_filter.") AND COALESCE(sb.quantity,0) <= COALESCE(t.threshold_qty,20) ORDER BY COALESCE(sb.quantity,0) ASC LIMIT 10";
                            } else {
                                $sql = "SELECT " . $item_col_select . " AS item_name, COALESCE(sb.quantity,0) AS qty, 20 AS threshold_qty FROM items it LEFT JOIN stock_balance sb ON it.item_id = sb.item_id WHERE (".$stationery_filter.") AND COALESCE(sb.quantity,0) < 20 ORDER BY COALESCE(sb.quantity,0) ASC LIMIT 10";
                            }
                            $res = $mysqli->query($sql);
                            if($res){ while($r = $res->fetch_assoc()) $stationery_low_items[] = $r; }

                        } else {
                            // compute from stock_transactions
                            $sql = "SELECT COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS qty, " . $item_col_select . " AS item_name";
                            if($has_thresholds){
                                $sql = "SELECT COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS qty, " . $item_col_select . " AS item_name, t.threshold_qty FROM items it LEFT JOIN inventory_thresholds t ON it.item_id = t.item_id WHERE (".$stationery_filter.") AND COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) <= COALESCE(t.threshold_qty,20) ORDER BY qty ASC LIMIT 10";
                            } else {
                                $sql = "SELECT COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS qty, " . $item_col_select . " AS item_name FROM items it WHERE (".$stationery_filter.") AND COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) < 20 ORDER BY qty ASC LIMIT 10";
                            }
                            $res = $mysqli->query($sql);
                            if($res){ while($r = $res->fetch_assoc()) $stationery_low_items[] = $r; }

                            // total stock via transactions
                            $sql = "SELECT COALESCE(SUM((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id)),0) AS total FROM items it WHERE " . $stationery_filter;
                            $res = $mysqli->query($sql);
                            if($res){ $r = $res->fetch_assoc(); $stationery_total_stock = (int)$r['total']; }
                        }
                        ?>

                        <div class="row">
                            <!-- Inventory: Store Items -->
                            <div class="col-12 col-md-6 col-xl-4 mb-3">
                                <div class="widget-rounded-circle card-box">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="avatar-lg rounded-circle bg-soft-primary border-primary border">
                                                <i class="fas fa-warehouse font-22 avatar-title text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-right">
                                                <h3 class="text-dark mt-1"><span data-plugin="counterup"><?php echo (int)$total_items;?></span></h3>
                                                <p class="text-muted mb-1 text-truncate">Store Items</p>
                                                <small class="text-muted">Register store item types and parts</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Inventory: Total Stock -->
                            <div class="col-12 col-md-6 col-xl-4 mb-3">
                                <div class="widget-rounded-circle card-box">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="avatar-lg rounded-circle bg-soft-success border-success border">
                                                <i class="fas fa-boxes font-22 avatar-title text-success"></i>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-right">
                                                <h3 class="text-dark mt-1"><span data-plugin="counterup"><?php echo (int)$total_stock;?></span></h3>
                                                <p class="text-muted mb-1 text-truncate">Total Stock (Units)</p>
                                                <small class="text-muted">Sum of all stock quantities</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Issued widget removed for stationery-only mode -->

                            <!-- Tyres widget removed for stationery-only mode -->

                            <!-- Diesel widget removed for stationery-only mode -->

                            <!-- Inventory: Stationery Store -->
                            <div class="col-12 col-md-6 col-xl-4 mb-3">
                                <div class="widget-rounded-circle card-box">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="avatar-lg rounded-circle bg-soft-pink border-pink border">
                                                <i class="fas fa-pencil-alt font-22 avatar-title text-pink"></i>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-right">
                                                <h3 class="text-dark mt-1"><span data-plugin="counterup"><?php echo (int)$stationery_total_stock;?></span></h3>
                                                <p class="text-muted mb-1 text-truncate">Stationery & Toner</p>
                                                <small class="text-muted">Items: <?php echo (int)$stationery_total_items;?> — Low: <?php echo count($stationery_low_items);?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vehicle service widget removed for stationery-only mode -->

                        </div> <!-- end row of cards -->

                        <!-- Quick action buttons -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card-box">
                                    <div class="btn-group" role="group" aria-label="Quick actions">
                                        <a href="store_items.php" class="btn btn-primary">Store Items</a>
                                        <a href="stock_management.php" class="btn btn-success">Manage Stock</a>
                                        <a href="inventory_report.php" class="btn btn-info">Stock Report</a>
                                        <a href="inventory_history.php" class="btn btn-secondary">Issuance History</a>
                                        <a href="inventory_charts.php" class="btn btn-dark">Usage Charts</a>
                                        <!-- Tyre Assignment button removed (table not present) -->
                                        <a href="download_stock.php" class="btn btn-light">Download Stock (XLS)</a>
                                        <a href="upload_stock.php" class="btn btn-light">Upload Stock (CSV)</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Low stock alert: support both legacy `stock_balance` table and new `stock_transactions` schema.
                        $low_items = [];
                        $has_thresholds = $mysqli->query("SHOW TABLES LIKE 'inventory_thresholds'")->num_rows;
                        $has_stock_balance = $mysqli->query("SHOW TABLES LIKE 'stock_balance'")->num_rows;

                        if($has_stock_balance){
                            // Prefer using stock_balance if it exists (legacy schema)
                            if($has_thresholds){
                                $sql = "SELECT " . $item_col_select . " AS item_name, COALESCE(sb.quantity,0) AS qty, t.threshold_qty
                                    FROM items it
                                        LEFT JOIN stock_balance sb ON it.item_id = sb.item_id
                                        LEFT JOIN inventory_thresholds t ON it.item_id = t.item_id
                                        WHERE COALESCE(sb.quantity,0) <= COALESCE(t.threshold_qty, 20)
                                        ORDER BY COALESCE(sb.quantity,0) ASC
                                        LIMIT 10";
                            } else {
                                $sql = "SELECT " . $item_col_select . " AS item_name, COALESCE(sb.quantity,0) AS qty, 20 AS threshold_qty
                                    FROM items it
                                        LEFT JOIN stock_balance sb ON it.item_id = sb.item_id
                                        WHERE COALESCE(sb.quantity,0) < 20
                                        ORDER BY COALESCE(sb.quantity,0) ASC
                                        LIMIT 10";
                            }
                            $res = $mysqli->query($sql);
                            if($res){ while($r = $res->fetch_assoc()) $low_items[] = $r; }
                        } else {
                            // Fallback: compute current stock from stock_transactions (sum qty_change)
                            if($has_thresholds){
                                    $sql = "SELECT " . $item_col_select . " AS item_name,
                                               COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS qty,
                                               t.threshold_qty
                                        FROM items it
                                        LEFT JOIN inventory_thresholds t ON it.item_id = t.item_id
                                        WHERE COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) <= COALESCE(t.threshold_qty, 20)
                                        ORDER BY qty ASC
                                        LIMIT 10";
                            } else {
                                    $sql = "SELECT " . $item_col_select . " AS item_name,
                                               COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS qty,
                                               20 AS threshold_qty
                                        FROM items it
                                        WHERE COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) < 20
                                        ORDER BY qty ASC
                                        LIMIT 10";
                            }
                            $res = $mysqli->query($sql);
                            if($res){ while($r = $res->fetch_assoc()) $low_items[] = $r; }
                        }
                        ?>

                        <?php if(count($low_items) > 0): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-danger">
                                    <strong>Low Stock Warning:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach($low_items as $li): ?>
                                            <li><?php echo htmlentities($li['item_name']); ?> — <?php echo $li['qty']; ?> (Threshold: <?php echo $li['threshold_qty']; ?>)</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recently Added Items (replaces hospital staff) -->
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card-box">
                                    <h4 class="header-title mb-3">Recently Added Items</h4>

                                    <div class="table-responsive">
                                        <table class="table table-borderless table-hover table-centered m-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Added</th>
                                                    <th>Item</th>
                                                    <th>Category</th>
                                                    <th>Unit</th>
                                                    <th>Current Qty</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql = "SELECT it.item_id, " . $item_col_select . " AS item_name, c.name AS category, it.unit, COALESCE(sb.quantity,0) AS qty, it.created_at
                                                        FROM items it
                                                        LEFT JOIN categories c ON it.category_id = c.category_id
                                                        LEFT JOIN stock_balance sb ON it.item_id = sb.item_id
                                                        ORDER BY it.created_at DESC
                                                        LIMIT 10";
                                                $res = $mysqli->query($sql);
                                                while($row = $res->fetch_assoc()){
                                                    echo "<tr>";
                                                    echo "<td>".htmlentities($row['created_at'])."</td>";
                                                    echo "<td>".htmlentities($row['item_name'])."</td>";
                                                    echo "<td>".htmlentities($row['category'])."</td>";
                                                    echo "<td>".htmlentities($row['unit'])."</td>";
                                                    echo "<td>".htmlentities($row['qty'])."</td>";
                                                    echo "<td><a href='store_items.php?edit_id={$row['item_id']}' class='btn btn-xs btn-primary'>Edit</a> <a href='stock_management.php?item_id={$row['item_id']}' class='btn btn-xs btn-success'>Manage</a></td>";
                                                    echo "</tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div> <!-- end col -->                                                                                                                                                                                                                                         

                        </div>
                        <!-- end row -->
                        
                    </div> <!-- container -->

                </div> <!-- content -->

                <!-- Footer Start -->
                <?php include('assets/inc/footer.php');?>
                <!-- end Footer -->

            </div>

            <!-- ============================================================== -->
            <!-- End Page content -->
            <!-- ============================================================== -->


        </div>
        <!-- END wrapper -->

        <!-- Right Sidebar -->
        <div class="right-bar">
            <div class="rightbar-title">
                <a href="javascript:void(0);" class="right-bar-toggle float-right">
                    <i class="dripicons-cross noti-icon"></i>
                </a>
                <h5 class="m-0 text-white">Settings</h5>
            </div>
            <div class="slimscroll-menu">
                <!-- User box -->
                <div class="user-box">
                    <div class="user-img">
                        <img src="assets/images/users/user-1.jpg" alt="user-img" title="Mat Helme" class="rounded-circle img-fluid">
                        <a href="javascript:void(0);" class="user-edit"><i class="mdi mdi-pencil"></i></a>
                    </div>
            
                    <h5><a href="javascript: void(0);"><?php echo htmlspecialchars($full_name); ?></a> </h5>
                    <p class="text-muted mb-0"><small><?php echo htmlspecialchars(ucfirst($role)); ?></small></p>
                </div>

                <!-- Settings -->
                <hr class="mt-0" />
                <h5 class="pl-3">Basic Settings</h5>
                <hr class="mb-0" />

                <div class="p-3">
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox1" type="checkbox" checked>
                        <label for="Rcheckbox1">
                            Notifications
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox2" type="checkbox" checked>
                        <label for="Rcheckbox2">
                            API Access
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox3" type="checkbox">
                        <label for="Rcheckbox3">
                            Auto Updates
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-2">
                        <input id="Rcheckbox4" type="checkbox" checked>
                        <label for="Rcheckbox4">
                            Online Status
                        </label>
                    </div>
                    <div class="checkbox checkbox-primary mb-0">
                        <input id="Rcheckbox5" type="checkbox" checked>
                        <label for="Rcheckbox5">
                            Auto Payout
                        </label>
                    </div>
                </div>

                <!-- Timeline -->
                <hr class="mt-0" />
                <h5 class="px-3">Messages <span class="float-right badge badge-pill badge-danger">25</span></h5>
                <hr class="mb-0" />
                <div class="p-3">
                    <div class="inbox-widget">
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-2.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Tomaslau</a></p>
                            <p class="inbox-item-text">I've finished it! See you so...</p>
                        </div>
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-3.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Stillnotdavid</a></p>
                            <p class="inbox-item-text">This theme is awesome!</p>
                        </div>
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-4.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Kurafire</a></p>
                            <p class="inbox-item-text">Nice to meet you</p>
                        </div>

                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-5.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Shahedk</a></p>
                            <p class="inbox-item-text">Hey! there I'm available...</p>
                        </div>
                        <div class="inbox-item">
                            <div class="inbox-item-img"><img src="assets/images/users/user-6.jpg" class="rounded-circle" alt=""></div>
                            <p class="inbox-item-author"><a href="javascript: void(0);" class="text-dark">Adhamdannaway</a></p>
                            <p class="inbox-item-text">This theme is awesome!</p>
                        </div>
                    </div> <!-- end inbox-widget -->
                </div> <!-- end .p-3-->

            </div> <!-- end slimscroll-menu-->
        </div>
        <!-- /Right-bar -->

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- Vendor js -->
        <script src="assets/js/vendor.min.js"></script>

        <!-- Plugins js-->
        <script src="assets/libs/flatpickr/flatpickr.min.js"></script>
        <script src="assets/libs/jquery-knob/jquery.knob.min.js"></script>
        <script src="assets/libs/jquery-sparkline/jquery.sparkline.min.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.time.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.tooltip.min.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.selection.js"></script>
        <script src="assets/libs/flot-charts/jquery.flot.crosshair.js"></script>

        <!-- Dashboar 1 init js-->
        <script src="assets/js/pages/dashboard-1.init.js"></script>

        <!-- App js-->
        <script src="assets/js/app.min.js"></script>
        
    </body>

</html>
