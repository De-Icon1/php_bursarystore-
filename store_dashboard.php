<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/functions.php');
include('assets/inc/checklogins.php');

if (!check_login()) {
    header('Location: index.php');
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['storekeeper', 'supervisor'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Store User';
?>
<!DOCTYPE html>
<html lang="en">
<?php include('assets/inc/head.php'); ?>
<body>
<?php include('assets/inc/nav.php'); ?>
<div id="wrapper">
    <?php include('assets/inc/sidebar_admin.php'); ?>
    <div class="content-page">
        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">Store Dashboard</h4>
                            <p class="mb-0 text-muted">Welcome, <?php echo htmlspecialchars($full_name); ?> — store operations workspace</p>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card-box">
                            <h5 class="mb-2">Store Actions</h5>
                            <div class="btn-group" role="group">
                                <a href="stock_receive.php" class="btn btn-success">Receive Stock</a>
                                <a href="issue_items.php" class="btn btn-warning">Issue Items</a>
                                <a href="stationery_store.php" class="btn btn-primary">Stationery Store</a>
                                <a href="low_stock_alerts.php" class="btn btn-danger">Low Stock Alerts</a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                $has_items_table = false;
                $items_result = $mysqli->query("SHOW TABLES LIKE 'items'");
                if ($items_result && $items_result->num_rows > 0) {
                    $has_items_table = true;
                }

                $item_name_expr = 'it.name';
                if ($has_items_table) {
                    $name_result = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'");
                    $item_name_result = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
                    $has_name_col = $name_result && $name_result->num_rows > 0;
                    $has_item_name_col = $item_name_result && $item_name_result->num_rows > 0;

                    if ($has_name_col && $has_item_name_col) {
                        $item_name_expr = 'COALESCE(it.name, it.item_name)';
                    } elseif ($has_name_col) {
                        $item_name_expr = 'it.name';
                    } elseif ($has_item_name_col) {
                        $item_name_expr = 'it.item_name';
                    }
                }

                $total_items = 0;
                if ($has_items_table) {
                    $res = $mysqli->query('SELECT COUNT(*) AS cnt FROM items');
                    if ($res) {
                        $row = $res->fetch_assoc();
                        $total_items = (int)($row['cnt'] ?? 0);
                    }
                }

                $current_stock = 0;
                $has_stock_transactions = false;
                $stock_tx_result = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'");
                if ($stock_tx_result && $stock_tx_result->num_rows > 0) {
                    $has_stock_transactions = true;
                    $res = $mysqli->query('SELECT COALESCE(SUM(qty_change), 0) AS total_stock FROM stock_transactions');
                    if ($res) {
                        $row = $res->fetch_assoc();
                        $current_stock = (int)($row['total_stock'] ?? 0);
                    }
                } else {
                    $stock_balance_result = $mysqli->query("SHOW TABLES LIKE 'stock_balance'");
                    if ($stock_balance_result && $stock_balance_result->num_rows > 0) {
                        $res = $mysqli->query('SELECT COALESCE(SUM(quantity), 0) AS total_stock FROM stock_balance');
                        if ($res) {
                            $row = $res->fetch_assoc();
                            $current_stock = (int)($row['total_stock'] ?? 0);
                        }
                    }
                }

                $pending_requests = 0;
                $stock_requests_result = $mysqli->query("SHOW TABLES LIKE 'stock_requests'");
                if ($stock_requests_result && $stock_requests_result->num_rows > 0) {
                    $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM stock_requests WHERE status = 'pending'");
                    if ($res) {
                        $row = $res->fetch_assoc();
                        $pending_requests = (int)($row['cnt'] ?? 0);
                    }
                }

                $low_stock_items = [];
                $low_stock_threshold = 10;
                $thresholds_result = $mysqli->query("SHOW TABLES LIKE 'inventory_thresholds'");
                $has_thresholds = $thresholds_result && $thresholds_result->num_rows > 0;

                if ($has_items_table && $has_stock_transactions) {
                    if ($has_thresholds) {
                        $sql = "SELECT {$item_name_expr} AS item_name,
                                COALESCE((SELECT SUM(st2.qty_change) FROM stock_transactions st2 WHERE st2.item_id = it.item_id), 0) AS qty,
                                COALESCE((SELECT threshold_qty FROM inventory_thresholds t WHERE t.item_id = it.item_id LIMIT 1), {$low_stock_threshold}) AS threshold_qty
                                FROM items it
                                HAVING qty <= threshold_qty
                                ORDER BY qty ASC LIMIT 8";
                    } else {
                        $sql = "SELECT {$item_name_expr} AS item_name,
                                COALESCE((SELECT SUM(st2.qty_change) FROM stock_transactions st2 WHERE st2.item_id = it.item_id), 0) AS qty,
                                {$low_stock_threshold} AS threshold_qty
                                FROM items it
                                HAVING qty <= {$low_stock_threshold}
                                ORDER BY qty ASC LIMIT 8";
                    }
                    $res = $mysqli->query($sql);
                    while ($res && $row = $res->fetch_assoc()) {
                        $low_stock_items[] = $row;
                    }
                } elseif ($has_items_table) {
                    if ($has_thresholds) {
                        $sql = "SELECT {$item_name_expr} AS item_name,
                                COALESCE(sb.quantity, 0) AS qty,
                                COALESCE((SELECT threshold_qty FROM inventory_thresholds t WHERE t.item_id = it.item_id LIMIT 1), {$low_stock_threshold}) AS threshold_qty
                                FROM items it
                                LEFT JOIN stock_balance sb ON sb.item_id = it.item_id
                                HAVING qty <= threshold_qty
                                ORDER BY qty ASC LIMIT 8";
                    } else {
                        $sql = "SELECT {$item_name_expr} AS item_name,
                                COALESCE(sb.quantity, 0) AS qty,
                                {$low_stock_threshold} AS threshold_qty
                                FROM items it
                                LEFT JOIN stock_balance sb ON sb.item_id = it.item_id
                                HAVING qty <= {$low_stock_threshold}
                                ORDER BY qty ASC LIMIT 8";
                    }
                    $res = $mysqli->query($sql);
                    while ($res && $row = $res->fetch_assoc()) {
                        $low_stock_items[] = $row;
                    }
                }

                $recent_activity = [];
                if ($has_items_table && $has_stock_transactions) {
                    $res = $mysqli->query("SELECT {$item_name_expr} AS item_name, st.qty_change, st.tx_type, st.created_at
                        FROM stock_transactions st
                        LEFT JOIN items it ON it.item_id = st.item_id
                        ORDER BY st.created_at DESC
                        LIMIT 5");
                    while ($res && $row = $res->fetch_assoc()) {
                        $recent_activity[] = $row;
                    }
                }
                ?>

                <div class="row">
                    <div class="col-md-6 col-xl-3">
                        <div class="card-box">
                            <h4 class="header-title">Items in store</h4>
                            <h2 class="mt-0 mb-1"><?php echo (int)$total_items; ?></h2>
                            <p class="text-muted mb-0">Registered inventory items</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card-box">
                            <h4 class="header-title">Current stock</h4>
                            <h2 class="mt-0 mb-1"><?php echo (int)$current_stock; ?></h2>
                            <p class="text-muted mb-0">Units currently on hand</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card-box">
                            <h4 class="header-title">Pending requests</h4>
                            <h2 class="mt-0 mb-1"><?php echo (int)$pending_requests; ?></h2>
                            <p class="text-muted mb-0">Open stock requests</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card-box">
                            <h4 class="header-title">Quick access</h4>
                            <ul class="mb-0">
                                <li><a href="stock_receive.php">Receive new stock</a></li>
                                <li><a href="stock_management.php">Manage stock levels</a></li>
                                <li><a href="inventory_history.php">View issuance history</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-7">
                        <div class="card-box">
                            <h4 class="header-title">Low stock items</h4>
                            <?php if (!empty($low_stock_items)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Qty</th>
                                                <th>Threshold</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_items as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['item_name'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo (int)($item['qty'] ?? 0); ?></td>
                                                    <td><?php echo (int)($item['threshold_qty'] ?? 0); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No low-stock items were found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="card-box">
                            <h4 class="header-title">Recent activity</h4>
                            <?php if (!empty($recent_activity)): ?>
                                <ul class="mb-0">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($activity['item_name'] ?? 'Unknown'); ?></strong>
                                            — <?php echo htmlspecialchars((string)($activity['tx_type'] ?? 'activity')); ?>
                                            (<?php echo (int)($activity['qty_change'] ?? 0); ?>)
                                            <div class="text-muted small"><?php echo htmlspecialchars((string)($activity['created_at'] ?? '')); ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No stock activity recorded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include('assets/inc/footer.php'); ?>
</body>
</html>
