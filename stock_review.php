<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$err = $success = '';

// Get summary statistics and per-item stock using stock_transactions
$total_items = 0;
$total_stock = 0;
$low_stock_count = 0;
$critical_stock_count = 0;
$stock_rows = [];

// Total items count
$res = $mysqli->query("SELECT COUNT(*) AS cnt FROM items");
if($res) {
  $r = $res->fetch_assoc();
  $total_items = (int)$r['cnt'];
}

// Compute stock per item from stock_transactions (fallback-friendly)
$has_tx = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'");
if($has_tx && $has_tx->num_rows > 0){
  // Detect item name column
  $has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'");
  $has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
  if($has_name_col && $has_name_col->num_rows && $has_item_name_col && $has_item_name_col->num_rows){
    $item_col_select = "COALESCE(it.item_name,it.name)";
  } elseif($has_item_name_col && $has_item_name_col->num_rows) {
    $item_col_select = "it.item_name";
  } elseif($has_name_col && $has_name_col->num_rows) {
    $item_col_select = "it.name";
  } else {
    $item_col_select = "'Item'";
  }

  $sql = "SELECT it.item_id, {$item_col_select} AS item_name, it.unit_measure, COALESCE(SUM(st.qty_change),0) AS quantity
      FROM items it
      LEFT JOIN stock_transactions st ON st.item_id = it.item_id
      GROUP BY it.item_id, item_name, it.unit_measure
      ORDER BY item_name ASC";
  $res = $mysqli->query($sql);
  if($res){
    while($r = $res->fetch_assoc()){
      $qty = (float)$r['quantity'];
      $stock_rows[] = [
        'item_id'      => (int)$r['item_id'],
        'item_name'    => $r['item_name'],
        'unit_measure' => $r['unit_measure'],
        'quantity'     => $qty,
      ];
      $total_stock += $qty;
      if($qty <= 0){
        $critical_stock_count++;
      } elseif($qty < 20){
        $low_stock_count++;
      }
    }
  }
}
?>
<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<div class="content-page">
<div class="content container">
  <h3>Stock Review Dashboard</h3>
  <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
  <?php if($err) echo "<div class='alert alert-danger'>$err</div>"; ?>

  <!-- Summary Cards -->
  <div class="row mt-4">
    <div class="col-12 col-sm-6 col-md-3 mb-3">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Total Items</h6>
          <h3 class="text-primary"><?php echo $total_items; ?></h3>
          <small class="text-muted">Active inventory items</small>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3 mb-3">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Total Stock</h6>
          <h3 class="text-success"><?php echo number_format($total_stock, 0); ?></h3>
          <small class="text-muted">Units in stock</small>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3 mb-3">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Low Stock</h6>
          <h3 class="text-warning"><?php echo $low_stock_count; ?></h3>
          <small class="text-muted">Items below 20 units</small>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3 mb-3">
      <div class="card">
        <div class="card-body">
          <h6 class="card-title">Critical Stock</h6>
          <h3 class="text-danger"><?php echo $critical_stock_count; ?></h3>
          <small class="text-muted">Out of stock items</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Low Stock Alert Section -->
  <div class="card-box mt-4">
    <h5>Low Stock Alert Items</h5>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>Item Name</th>
            <th>Current Stock</th>
            <th>Unit</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Use precomputed $stock_rows from stock_transactions for low stock alert
        $has_low = false;
        foreach($stock_rows as $row){
          $qty = $row['quantity'];
          if($qty > 20) continue;
          $has_low = true;
          $status = $qty <= 0 ? '<span class="badge badge-danger">Out of Stock</span>' : '<span class="badge badge-warning">Low Stock</span>';
          echo "<tr>";
          echo "<td>".htmlentities($row['item_name'])."</td>";
          echo "<td>".number_format($qty, 0)."</td>";
          echo "<td>".htmlentities($row['unit_measure'])."</td>";
          echo "<td>".$status."</td>";
          echo "<td><a href='upload_stock.php' class='btn btn-sm btn-primary'>Replenish</a></td>";
          echo "</tr>";
        }
        if(!$has_low){
          echo "<tr><td colspan='5' class='text-center text-muted'>No low stock items</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Stock Movement Section -->
  <div class="card-box mt-4">
    <h5>Recent Stock Movements</h5>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>Date</th>
            <th>Item</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Reference</th>
            <th>By</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Recent stock movements using stock_entries + items (support name/item_name)
        $itemNameCol = 'i.item_name';
        $chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
        if(!$chk || $chk->num_rows === 0){
            $itemNameCol = 'i.name';
        }
        $sql = "SELECT se.*, {$itemNameCol} AS item_name
          FROM stock_entries se 
          JOIN items i ON se.item_id = i.item_id 
          ORDER BY se.created_at DESC 
          LIMIT 50";
        $res = $mysqli->query($sql);
        if($res && $res->num_rows > 0) {
            while($r = $res->fetch_assoc()) {
                $type = '';
                if($r['qty_in'] > 0) {
                    $type = '<span class="badge badge-success">In</span>';
                    $qty = $r['qty_in'];
                } else {
                    $type = '<span class="badge badge-danger">Out</span>';
                    $qty = $r['qty_out'];
                }
                echo "<tr>";
                echo "<td>".htmlentities($r['created_at'])."</td>";
                echo "<td>".htmlentities($r['item_name'])."</td>";
                echo "<td>".$type."</td>";
                echo "<td>".number_format($qty, 0)."</td>";
                echo "<td>".htmlentities($r['reference'])."</td>";
                echo "<td>".htmlentities($r['created_by'])."</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='6' class='text-center text-muted'>No stock movements</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Stock Issues Section -->
  <div class="card-box mt-4">
    <h5>Recent Stock Issues</h5>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>Date</th>
            <th>Item</th>
            <th>Unit Issued To</th>
            <th>Quantity</th>
            <th>Purpose</th>
            <th>Issued By</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Recent stock issues: use free-text unit column on stock_issues
        $itemNameCol2 = 'i.item_name';
        $chk2 = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
        if(!$chk2 || $chk2->num_rows === 0){
            $itemNameCol2 = 'i.name';
        }
        $sql = "SELECT si.issued_at, si.quantity, si.issued_by, si.purpose, si.unit, {$itemNameCol2} AS item_name
          FROM stock_issues si 
          JOIN items i ON si.item_id = i.item_id 
          ORDER BY si.issued_at DESC 
          LIMIT 30";
        $res = $mysqli->query($sql);
        if($res && $res->num_rows > 0) {
            while($r = $res->fetch_assoc()) {
                echo "<tr>";
                echo "<td>".htmlentities($r['issued_at'])."</td>";
                echo "<td>".htmlentities($r['item_name'])."</td>";
                echo "<td>".htmlentities($r['unit'])."</td>";
                echo "<td>".number_format($r['quantity'], 0)."</td>";
                echo "<td>".htmlentities($r['purpose'])."</td>";
                echo "<td>".htmlentities($r['issued_by'])."</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='6' class='text-center text-muted'>No stock issues</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Stock Balance by Item -->
  <div class="card-box mt-4">
    <h5>Current Stock Balance</h5>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>Item Name</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Current stock balance list from $stock_rows
        if(!empty($stock_rows)){
          foreach($stock_rows as $row){
            $qty = $row['quantity'];
            $status_class = 'success';
            $status_text = 'In Stock';
            if($qty <= 0) {
              $status_class = 'danger';
              $status_text = 'Out of Stock';
            } elseif($qty < 20) {
              $status_class = 'warning';
              $status_text = 'Low Stock';
            }
            echo "<tr>";
            echo "<td>".htmlentities($row['item_name'])."</td>";
            echo "<td><strong>".number_format($qty, 0)."</strong></td>";
            echo "<td>".htmlentities($row['unit_measure'])."</td>";
            echo "<td><span class='badge badge-".$status_class."'>".$status_text."</span></td>";
            echo "</tr>";
          }
        } else {
          echo "<tr><td colspan='4' class='text-center text-muted'>No items in stock</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

<?php include("assets/inc/footer.php"); ?>

</body>
</html>
