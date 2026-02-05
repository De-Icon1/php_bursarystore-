<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$err = $success = '';

// Handle stock adjustment
if(isset($_POST['adjust'])){
    $item = (int)$_POST['item_id'];
    $adjustment_type = $_POST['adjustment_type'];
    $qty = (float)$_POST['adjustment_qty'];
    $reason = trim($_POST['reason']);
    $adjusted_by = $_SESSION['username'] ?? 'Unknown';

    if($qty <= 0){
        $err = "Adjustment quantity must be greater than zero.";
    } else if(empty($reason)){
        $err = "Reason for adjustment is required.";
    } else {
        // Get current stock
        $stmt = $mysqli->prepare("SELECT quantity FROM stock_balance WHERE item_id = ?");
        $stmt->bind_param("i", $item);
        $stmt->execute();
        $stmt->bind_result($current_qty);
        $stmt->fetch();
        $stmt->close();

        if($current_qty === null) $current_qty = 0;

        // Check if adjustment would go negative
        if($adjustment_type === 'subtract' && ($current_qty - $qty) < 0){
            $err = "Adjustment would result in negative stock. Current: " . number_format($current_qty, 0);
        } else {
            $mysqli->begin_transaction();

            try {
                // Check if item exists in stock_balance, if not create it
                $check = $mysqli->query("SELECT balance_id FROM stock_balance WHERE item_id = $item");
                if($check->num_rows == 0){
                    $mysqli->query("INSERT INTO stock_balance (item_id, quantity) VALUES ($item, 0)");
                }
                
                // Record adjustment entry
                if($adjustment_type === 'add'){
                    $stmt = $mysqli->prepare("INSERT INTO stock_entries (item_id, qty_in, reference, note, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("idsss", $item, $qty, $reference, $reason, $adjusted_by);
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO stock_entries (item_id, qty_out, reference, note, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("idsss", $item, $qty, $reference, $reason, $adjusted_by);
                }
                $stmt->execute();
                $stmt->close();

                // Update balance
                if($adjustment_type === 'add'){
                    $stmt = $mysqli->prepare("UPDATE stock_balance SET quantity = quantity + ? WHERE item_id = ?");
                } else {
                    $stmt = $mysqli->prepare("UPDATE stock_balance SET quantity = quantity - ? WHERE item_id = ?");
                }
                $stmt->bind_param("di", $qty, $item);
                $stmt->execute();
                $stmt->close();

                $mysqli->commit();
                $action = ($adjustment_type === 'add') ? 'added' : 'removed';
                $success = "Stock " . $action . " successfully. Quantity: " . number_format($qty, 0) . " units";
            } catch(Exception $e){
                $mysqli->rollback();
                $err = "Error adjusting stock: " . $e->getMessage();
            }
        }
    }
}

// Reorder level functionality removed - column doesn't exist in items table
?>
<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<div class="content-page">
<div class="content container">
  <h3>Stock Management</h3>
  <?php if($success) echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>".$success."<button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>"; ?>
  <?php if($err) echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>".$err."<button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>"; ?>

  <!-- Nav tabs -->
  <ul class="nav nav-tabs" id="stockTabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" id="adjust-tab" data-toggle="tab" href="#adjust-panel" role="tab">Adjust Stock</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="inventory-tab" data-toggle="tab" href="#inventory-panel" role="tab">Current Inventory</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="reorder-tab" data-toggle="tab" href="#reorder-panel" role="tab">Reorder Levels</a>
    </li>
  </ul>

  <!-- Tab content -->
  <div class="tab-content">
    <!-- Adjust Stock Tab -->
    <div class="tab-pane fade show active" id="adjust-panel" role="tabpanel">
      <div class="card-box mt-3">
        <h5>Adjust Stock Level</h5>
        <form method="POST">
          <div class="form-row">
            <div class="form-group col-12 col-md-6">
              <label>Item <span class="text-danger">*</span></label>
              <select name="item_id" class="form-control" required>
                <option value="">-- Select Item --</option>
                <?php
                $res = $mysqli->query("SELECT i.item_id, i.item_name, sb.quantity FROM items i LEFT JOIN stock_balance sb ON i.item_id = sb.item_id ORDER BY i.item_name");
                while($r = $res->fetch_assoc()){
                  $qty = isset($r['quantity']) ? number_format($r['quantity'], 0) : '0';
                  echo "<option value='{$r['item_id']}'>".htmlentities($r['item_name'])." (Current: ".$qty.")</option>";
                }
                ?>
              </select>
            </div>

            <div class="form-group col-12 col-md-3">
              <label>Adjustment Type <span class="text-danger">*</span></label>
              <select name="adjustment_type" class="form-control" required>
                <option value="add">Add Stock</option>
                <option value="subtract">Remove Stock</option>
              </select>
            </div>

            <div class="form-group col-12 col-md-3">
              <label>Quantity <span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="adjustment_qty" class="form-control" required placeholder="0.00" min="0.01">
            </div>
          </div>

          <div class="form-group">
            <label>Reason <span class="text-danger">*</span></label>
            <textarea name="reason" class="form-control" rows="2" required placeholder="e.g., Damage, Loss, Inventory Count Correction, etc."></textarea>
          </div>

          <button type="submit" name="adjust" class="btn btn-primary"><i class="fe-check"></i> Apply Adjustment</button>
        </form>
      </div>
    </div>

    <!-- Current Inventory Tab -->
    <div class="tab-pane fade" id="inventory-panel" role="tabpanel">
      <div class="card-box mt-3">
        <h5>Current Stock Levels</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover" id="inventoryTable">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>Current Stock</th>
                <th>Unit</th>
                <th>Reorder Level</th>
                <th>Status</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT i.item_id, i.item_name, i.unit, sb.quantity, sb.last_updated
                    FROM items i
                    LEFT JOIN stock_balance sb ON i.item_id = sb.item_id
                    ORDER BY i.item_name ASC";
            $res = $mysqli->query($sql);
            if($res && $res->num_rows > 0) {
                while($r = $res->fetch_assoc()) {
                    $qty = isset($r['quantity']) ? $r['quantity'] : 0;
                    $reorder = 10; // Default reorder level
                    
                    $status_class = 'success';
                    $status_text = 'In Stock';
                    if($qty <= 0) {
                        $status_class = 'danger';
                        $status_text = 'Out of Stock';
                    } else if($qty <= $reorder) {
                        $status_class = 'warning';
                        $status_text = 'Low Stock';
                    }
                    
                    $last_updated = isset($r['last_updated']) && $r['last_updated'] ? date('M d, Y H:i', strtotime($r['last_updated'])) : 'Never';
                    
                    echo "<tr>";
                    echo "<td>".htmlentities($r['item_name'])."</td>";
                    echo "<td><strong>".number_format($qty, 0)."</strong></td>";
                    echo "<td>".htmlentities($r['unit'] ?? '')."</td>";
                    echo "<td>".number_format($reorder, 0)."</td>";
                    echo "<td><span class='badge badge-".$status_class."'>".$status_text."</span></td>";
                    echo "<td>".htmlentities($last_updated)."</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='text-center text-muted'>No items in inventory</td></tr>";
            }
            ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Reorder Levels Tab -->
    <div class="tab-pane fade" id="reorder-panel" role="tabpanel">
      <div class="card-box mt-3">
        <h5>Items Below Reorder Level</h5>
        <p class="text-muted">Items with stock quantity of 10 or below are flagged as low stock.</p>
        
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>Unit</th>
                <th>Current Stock</th>
                <th>Reorder Level</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT i.item_id, i.item_name, i.unit, sb.quantity
                    FROM items i
                    LEFT JOIN stock_balance sb ON i.item_id = sb.item_id
                    ORDER BY sb.quantity ASC, i.item_name ASC";
            $res = $mysqli->query($sql);
            if($res && $res->num_rows > 0) {
                while($r = $res->fetch_assoc()) {
                    $qty = isset($r['quantity']) ? $r['quantity'] : 0;
                    $reorder = 10; // Default reorder level
                    
                    $status_class = 'success';
                    $status_text = 'Normal';
                    if($qty <= 0) {
                        $status_class = 'danger';
                        $status_text = 'Out of Stock';
                    } else if($qty <= $reorder) {
                        $status_class = 'warning';
                        $status_text = 'Low Stock';
                    }
                    
                    echo "<tr>";
                    echo "<td>".htmlentities($r['item_name'])."</td>";
                    echo "<td>".htmlentities($r['unit'] ?? '')."</td>";
                    echo "<td><strong>".number_format($qty, 0)."</strong></td>";
                    echo "<td>".number_format($reorder, 0)."</td>";
                    echo "<td><span class='badge badge-".$status_class."'>".$status_text."</span></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' class='text-center text-muted'>No items found</td></tr>";
            }
            ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Stock Adjustment History -->
  <div class="card-box mt-4">
    <h5>Stock Adjustment History</h5>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>Date</th>
            <th>Item</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Reason</th>
            <th>Adjusted By</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sql = "SELECT se.*, i.item_name
                FROM stock_entries se
                JOIN items i ON se.item_id = i.item_id
                WHERE se.reference LIKE 'Stock Adjustment%'
                ORDER BY se.created_at DESC
                LIMIT 50";
        $res = $mysqli->query($sql);
        if($res && $res->num_rows > 0) {
            while($r = $res->fetch_assoc()) {
                $type = 'Add';
                $qty = $r['qty_in'];
                $badge_class = 'success';
                
                if($r['qty_out'] > 0){
                    $type = 'Remove';
                    $qty = $r['qty_out'];
                    $badge_class = 'warning';
                }
                
                echo "<tr>";
                echo "<td>".date('M d, Y H:i', strtotime($r['created_at']))."</td>";
                echo "<td>".htmlentities($r['item_name'])."</td>";
                echo "<td><span class='badge badge-".$badge_class."'>".$type."</span></td>";
                echo "<td>".number_format($qty, 0)."</td>";
                echo "<td>".htmlentities($r['note'])."</td>";
                echo "<td>".htmlentities($r['created_by'])."</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='6' class='text-center text-muted'>No adjustments recorded</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include("assets/inc/footer.php"); ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Initialize DataTables if available
$(document).ready(function() {
    if($.fn.dataTable) {
        $('#inventoryTable').DataTable({
            "pageLength": 25,
            "order": [[0, "asc"]]
        });
    }
});
</script>

</body>
</html>
