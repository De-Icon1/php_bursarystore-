<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

// fetch items where stock <= threshold
$sql = "SELECT it.item_name, COALESCE(sb.quantity,0) AS qty, COALESCE(t.threshold_qty, 10) AS threshold, COALESCE(t.notified,0) AS notified, it.item_id FROM items it LEFT JOIN stock_balance sb ON it.item_id = sb.item_id LEFT JOIN inventory_thresholds t ON it.item_id = t.item_id WHERE COALESCE(sb.quantity,0) <= COALESCE(t.threshold_qty, 10) ORDER BY sb.quantity ASC";
$res = $mysqli->query($sql);
$rows = $res->fetch_all(MYSQLI_ASSOC);

// optionally mark notified when needed (not automatic here)
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