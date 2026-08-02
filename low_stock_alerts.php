<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$rows = [];

$has_items = false;
$items_result = $mysqli->query("SHOW TABLES LIKE 'items'");
if ($items_result && $items_result->num_rows > 0) {
    $has_items = true;
}

$has_stock_transactions = false;
$tx_result = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'");
if ($tx_result && $tx_result->num_rows > 0) {
    $has_stock_transactions = true;
}

$has_thresholds = false;
$thresholds_result = $mysqli->query("SHOW TABLES LIKE 'inventory_thresholds'");
if ($thresholds_result && $thresholds_result->num_rows > 0) {
    $has_thresholds = true;
}

$has_stock_balance = false;
$balance_result = $mysqli->query("SHOW TABLES LIKE 'stock_balance'");
if ($balance_result && $balance_result->num_rows > 0) {
    $has_stock_balance = true;
}

if ($has_items) {
    $item_name_expr = 'it.name';
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

    if ($has_stock_transactions) {
        $sql = "SELECT {$item_name_expr} AS item_name,
                COALESCE((SELECT SUM(st2.qty_change) FROM stock_transactions st2 WHERE st2.item_id = it.item_id), 0) AS qty,
                10 AS threshold,
                0 AS notified,
                it.item_id
                FROM items it
                HAVING qty <= 10
                ORDER BY qty ASC";
    } elseif ($has_stock_balance) {
        $sql = "SELECT {$item_name_expr} AS item_name,
                COALESCE(sb.quantity, 0) AS qty,
                COALESCE(t.threshold_qty, 10) AS threshold,
                COALESCE(t.notified, 0) AS notified,
                it.item_id
                FROM items it
                LEFT JOIN stock_balance sb ON it.item_id = sb.item_id
                LEFT JOIN inventory_thresholds t ON it.item_id = t.item_id
                WHERE COALESCE(sb.quantity, 0) <= COALESCE(t.threshold_qty, 10)
                ORDER BY sb.quantity ASC";
    } else {
        $sql = "SELECT {$item_name_expr} AS item_name, 0 AS qty, 10 AS threshold, 0 AS notified, it.item_id FROM items it ORDER BY item_name";
    }

    $res = $mysqli->query($sql);
    if ($res) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<?php include("assets/inc/head.php"); ?>
<body><?php include("assets/inc/nav.php"); ?>

<div class="container mt-4">
  <h3>Low Stock Alerts</h3>
  <div class="card-box">
    <?php if(empty($rows)) echo "<div class='alert alert-success'>No low stock items.</div>"; ?>
    <?php if(!empty($rows)){ ?>
      <div class="table-responsive">
        <table class="table table-striped">
        <thead><tr><th>Item</th><th>Qty</th><th>Threshold</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($rows as $r){ ?>
            <tr>
              <td><?= htmlentities($r['item_name']) ?></td>
              <td><?= $r['qty'] ?></td>
              <td><?= $r['threshold'] ?></td>
              <td>
                <a href="stock_management.php" class="btn btn-sm btn-primary">Add Stock</a>
                <a href="inventory_report.php" class="btn btn-sm btn-secondary">View Report</a>
              </td>
            </tr>
          <?php } ?>
        </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</div>
</body>
</html>