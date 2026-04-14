<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

// VC Dashboard - comprehensive view of all bursary store reports
$uid = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

// Summary statistics
// Total items
$total_items = 0;
$res = $mysqli->query("SELECT COUNT(*) AS cnt FROM items");
if($res){
    $r = $res->fetch_assoc();
    $total_items = $r['cnt'];
}

// Total stock 
$total_stock = 0;
$has_stock_balance = $mysqli->query("SHOW TABLES LIKE 'stock_balance'")->num_rows;
if($has_stock_balance){
    $res = $mysqli->query("SELECT COALESCE(SUM(quantity),0) AS total_stock FROM stock_balance");
    if($res){ $r = $res->fetch_assoc(); $total_stock = (int)$r['total_stock']; }
} else {
    $has_tx = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'")->num_rows;
    if($has_tx){
        $res = $mysqli->query("SELECT COALESCE(SUM(qty_change),0) AS total_stock FROM stock_transactions");
        if($res){ $r = $res->fetch_assoc(); $total_stock = (int)$r['total_stock']; }
    }
}

// Total issued
$total_issued = 0;
$has_stock_issues = $mysqli->query("SHOW TABLES LIKE 'stock_issues'")->num_rows;
if($has_stock_issues){
    $res = $mysqli->query("SELECT COALESCE(SUM(quantity),0) AS total_issued FROM stock_issues");
    if($res){ $r = $res->fetch_assoc(); $total_issued = (int)$r['total_issued']; }
}

// Low stock count
$low_stock_count = 0;
if($has_stock_balance){
    $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM items it LEFT JOIN stock_balance sb ON it.item_id = sb.item_id LEFT JOIN inventory_thresholds t ON it.item_id = t.item_id WHERE COALESCE(sb.quantity,0) <= COALESCE(t.threshold_qty, 10)");
    if($res){ $r = $res->fetch_assoc(); $low_stock_count = $r['cnt']; }
}

// Recent issuances (last 5)
$recent_issues = [];
if($has_stock_issues){
    $has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'")->num_rows;
    $has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'")->num_rows;
    if($has_item_name_col){
        $item_col = 'it.item_name';
    } elseif($has_name_col){
        $item_col = 'it.name';
    } else {
        $item_col = "''";
    }
    
    $sql = "SELECT si.issued_at, {$item_col} AS item_name, si.quantity, si.issued_by, si.purpose 
            FROM stock_issues si 
            JOIN items it ON si.item_id = it.item_id 
            ORDER BY si.issued_at DESC 
            LIMIT 5";
    $res = $mysqli->query($sql);
    if($res){
        $recent_issues = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_vc.php"); ?>

<div class="content-page">
    <div class="content">
        <!-- Start Content-->
        <div class="container-fluid">
            
            <!-- Page title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box">
                        <h4 class="page-title">Vice Chancellor - Bursary Store Reports</h4>
                        <p class="mb-0 text-muted">Welcome, <?php echo htmlspecialchars($full_name); ?></p>
                    </div>
                </div>
            </div>

            <!-- VC Login Details & Instructions -->
            <div class="row">
                <div class="col-12">
                    <div class="card-box">
                        <h5 class="mb-3"><i class="fas fa-user-lock mr-2"></i>Login Details & Instructions</h5>
                        <p class="mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                        <p class="mb-1"><strong>Full Name:</strong> <?php echo htmlspecialchars($full_name); ?></p>
                        <p class="mb-1"><strong>Role:</strong> <?php echo htmlspecialchars($role); ?></p>
                        <p class="mb-1"><strong>Login Page:</strong> <a href="index.php">index.php</a></p>
                        <p class="mb-1"><strong>Redirects to:</strong> VC Dashboard (reports) — use <a href="inventory_report.php">Stock Balance Report</a> for full details.</p>
                        <p class="mb-0 text-muted">Password is hidden for security. If you need a password reset or a new account, contact the system administrator.</p>
                    </div>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card-box">
                        <div class="media">
                            <div class="avatar-md bg-primary rounded mr-3">
                                <i class="fas fa-boxes font-22 avatar-title text-white"></i>
                            </div>
                            <div class="media-body">
                                <h5 class="mt-0 mb-1"><?php echo number_format($total_items); ?></h5>
                                <p class="mb-0 text-muted">Total Items</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card-box">
                        <div class="media">
                            <div class="avatar-md bg-success rounded mr-3">
                                <i class="fas fa-layer-group font-22 avatar-title text-white"></i>
                            </div>
                            <div class="media-body">
                                <h5 class="mt-0 mb-1"><?php echo number_format($total_stock); ?></h5>
                                <p class="mb-0 text-muted">Current Stock</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card-box">
                        <div class="media">
                            <div class="avatar-md bg-warning rounded mr-3">
                                <i class="fas fa-arrow-circle-down font-22 avatar-title text-white"></i>
                            </div>
                            <div class="media-body">
                                <h5 class="mt-0 mb-1"><?php echo number_format($total_issued); ?></h5>
                                <p class="mb-0 text-muted">Total Issued</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card-box">
                        <div class="media">
                            <div class="avatar-md bg-danger rounded mr-3">
                                <i class="fas fa-exclamation-triangle font-22 avatar-title text-white"></i>
                            </div>
                            <div class="media-body">
                                <h5 class="mt-0 mb-1"><?php echo number_format($low_stock_count); ?></h5>
                                <p class="mb-0 text-muted">Low Stock Items</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Summary Cards -->
            
            <!-- Quick Reports Access -->
            <div class="row">
                <div class="col-12">
                    <div class="card-box">
                        <h5 class="mb-3"><i class="fas fa-chart-bar mr-2"></i>Quick Reports Access</h5>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="inventory_report.php" class="btn btn-primary btn-block">
                                    <i class="fas fa-file-alt mr-2"></i>Stock Balance Report
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="inventory_charts.php" class="btn btn-info btn-block">
                                    <i class="fas fa-chart-line mr-2"></i>Usage Charts
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="inventory_history.php" class="btn btn-secondary btn-block">
                                    <i class="fas fa-history mr-2"></i>Issuance History
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="low_stock_alerts.php" class="btn btn-warning btn-block">
                                    <i class="fas fa-bell mr-2"></i>Low Stock Alerts
                                </a>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 mb-2">
                                <a href="stock_review.php" class="btn btn-success btn-block">
                                    <i class="fas fa-clipboard-check mr-2"></i>Stock Review
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Issuances -->
            <div class="row">
                <div class="col-12">
                    <div class="card-box">
                        <h5 class="mb-3"><i class="fas fa-clock mr-2"></i>Recent Issuances</h5>
                        <?php if(empty($recent_issues)): ?>
                            <div class="alert alert-info">No recent issuances found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-centered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Issued By</th>
                                            <th>Purpose</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_issues as $issue): ?>
                                            <tr>
                                                <td><?php echo htmlentities($issue['issued_at']); ?></td>
                                                <td><?php echo htmlentities($issue['item_name']); ?></td>
                                                <td><?php echo number_format($issue['quantity']); ?></td>
                                                <td><?php echo htmlentities($issue['issued_by']); ?></td>
                                                <td><?php echo htmlentities($issue['purpose']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2 text-center">
                                <a href="inventory_history.php" class="btn btn-sm btn-outline-secondary">View All History</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stock Overview (Top 10 Items) -->
            <div class="row">
                <div class="col-12">
                    <div class="card-box">
                        <h5 class="mb-3"><i class="fas fa-list mr-2"></i>Top 10 Items by Current Stock</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Current Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get top 10 items
                                    $has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'")->num_rows;
                                    $has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'")->num_rows;
                                    if($has_item_name_col){
                                        $item_col = 'it.item_name';
                                    } elseif($has_name_col){
                                        $item_col = 'it.name';
                                    } else {
                                        $item_col = "''";
                                    }
                                    
                                    $category_expr = "it.category";
                                    $joins = "";
                                    $has_categories = $mysqli->query("SHOW TABLES LIKE 'categories'")->num_rows;
                                    $has_category_id = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'")->num_rows;
                                    if($has_categories && $has_category_id){
                                        $category_expr = "COALESCE(c.name, it.category)";
                                        $joins = " LEFT JOIN categories c ON it.category_id = c.category_id ";
                                    }
                                    
                                    if($has_stock_balance){
                                        $sql = "SELECT {$item_col} AS item_name, 
                                                       {$category_expr} AS category, 
                                                       it.unit_measure,
                                                       COALESCE(sb.quantity, 0) AS stock
                                                FROM items it 
                                                LEFT JOIN stock_balance sb ON it.item_id = sb.item_id
                                                {$joins}
                                                ORDER BY stock DESC 
                                                LIMIT 10";
                                    } else {
                                        $sql = "SELECT {$item_col} AS item_name, 
                                                       {$category_expr} AS category, 
                                                       it.unit_measure,
                                                       0 AS stock
                                                FROM items it 
                                                {$joins}
                                                LIMIT 10";
                                    }
                                    
                                    $res = $mysqli->query($sql);
                                    while($r = $res->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo htmlentities($r['item_name']); ?></td>
                                            <td><?php echo htmlentities($r['category']); ?></td>
                                            <td><?php echo htmlentities($r['unit_measure']); ?></td>
                                            <td><?php echo number_format($r['stock']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2 text-center">
                            <a href="inventory_report.php" class="btn btn-sm btn-outline-primary">View Full Report</a>
                        </div>
                    </div>
                </div>
            </div>
            
        </div> <!-- container -->
    </div> <!-- content -->
</div>

<?php include("assets/inc/footer.php"); ?>
</body>
</html>