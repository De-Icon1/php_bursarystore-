<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$err = $success = '';

// Detect column names for schema compatibility
$col_item_name = 'name';
$col_unit = 'unit_measure';
$chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
if ($chk && $chk->num_rows > 0) $col_item_name = 'item_name';
$chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit'");
if ($chk && $chk->num_rows > 0) $col_unit = 'unit';

$user_role = strtolower($_SESSION['role'] ?? '');
$can_approve_removal = in_array($user_role, ['admin', 'superadmin', 'HOD']);

// Check what tables exist
$has_stock_balance = false;
$chk = $mysqli->query("SHOW TABLES LIKE 'stock_balance'");
$has_stock_balance = $chk && $chk->num_rows > 0;

$has_stock_transactions = false;
$chk = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'");
$has_stock_transactions = $chk && $chk->num_rows > 0;

$has_inventory_thresholds = false;
$chk = $mysqli->query("SHOW TABLES LIKE 'inventory_thresholds'");
$has_inventory_thresholds = $chk && $chk->num_rows > 0;

// Helper: get current stock quantity for an item, optionally scoped to a category
function get_item_qty($item_id, $category_id = null) {
    global $mysqli, $has_stock_balance, $has_stock_transactions;

    if ($category_id === null && $has_stock_balance) {
        $s = $mysqli->prepare("SELECT quantity FROM stock_balance WHERE item_id = ?");
        if ($s) {
            $s->bind_param('i', $item_id);
            $s->execute();
            $s->bind_result($qty);
            $s->fetch();
            $s->close();
            return $qty !== null ? (float)$qty : 0;
        }
    }
    
    if ($has_stock_transactions) {
        if ($category_id !== null) {
            $s = $mysqli->prepare("SELECT COALESCE(SUM(qty_change), 0) FROM stock_transactions WHERE item_id = ? AND category_id = ?");
            if ($s) {
                $s->bind_param('ii', $item_id, $category_id);
                $s->execute();
                $s->bind_result($qty);
                $s->fetch();
                $s->close();
                return (float)$qty;
            }
        } else {
            $s = $mysqli->prepare("SELECT COALESCE(SUM(qty_change), 0) FROM stock_transactions WHERE item_id = ?");
            if ($s) {
                $s->bind_param('i', $item_id);
                $s->execute();
                $s->bind_result($qty);
                $s->fetch();
                $s->close();
                return (float)$qty;
            }
        }
    }
    
    return 0;
}

// Helper: get reorder level for an item
function get_reorder_level($item_id) {
    global $mysqli, $has_inventory_thresholds;
    
    if ($has_inventory_thresholds) {
        $s = $mysqli->prepare("SELECT threshold_qty FROM inventory_thresholds WHERE item_id = ?");
        if ($s) {
            $s->bind_param('i', $item_id);
            $s->execute();
            $s->bind_result($level);
            $s->fetch();
            $s->close();
            if ($level !== null) return (float)$level;
        }
    }
    
    // Fallback: check items.reorder_level column
    $s = $mysqli->prepare("SELECT reorder_level FROM items WHERE item_id = ?");
    if ($s) {
        $s->bind_param('i', $item_id);
        $s->execute();
        $s->bind_result($level);
        $s->fetch();
        $s->close();
        if ($level !== null && $level > 0) return (float)$level;
    }
    
    return 10;
}

// Handle stock adjustment
if(isset($_POST['adjust'])){
    $item = (int)$_POST['item_id'];
    $adjustment_type = $_POST['adjustment_type'];
    $qty = (float)$_POST['adjustment_qty'];
    $reason = trim($_POST['reason']);
    $adjusted_by = $_SESSION['username'] ?? 'Unknown';
    $reference = 'Stock Adjustment - ' . date('Y-m-d H:i');

    if($qty <= 0){
        $err = "Adjustment quantity must be greater than zero.";
    } else if($adjustment_type === 'subtract' && !$can_approve_removal){
        $err = "Stock removals for damage, spoilage, or loss require an Administrator or HOD to authorize.";
    } else if(empty($reason)){
        $err = "Reason for adjustment is required.";
    } else {
        $current_qty = get_item_qty($item);

        if($adjustment_type === 'subtract' && ($current_qty - $qty) < 0){
            $err = "Adjustment would result in negative stock. Current: " . number_format($current_qty, 0);
        } else {
            $mysqli->begin_transaction();
            try {
                // Record in stock_entries
                if($adjustment_type === 'add'){
                    $stmt = $mysqli->prepare("INSERT INTO stock_entries (item_id, qty_in, reference, note, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("idsss", $item, $qty, $reference, $reason, $adjusted_by);
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO stock_entries (item_id, qty_out, reference, note, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("idsss", $item, $qty, $reference, $reason, $adjusted_by);
                }
                $stmt->execute();
                $stmt->close();

                // Record in stock_transactions
                if ($has_stock_transactions) {
                    $change_qty = ($adjustment_type === 'add') ? $qty : (-1 * $qty);
                    $tx_note = "Stock adjustment: {$reason}";
                    $user_id = (int)($_SESSION['user_id'] ?? 0);
                    $stmt = $mysqli->prepare("INSERT INTO stock_transactions (item_id, qty_change, tx_type, user_id, note) VALUES (?, ?, 'adjustment', ?, ?)");
                    $stmt->bind_param("idis", $item, $change_qty, $user_id, $tx_note);
                    $stmt->execute();
                    $stmt->close();
                }

                // Update stock_balance
                if ($has_stock_balance) {
                    $check = $mysqli->query("SELECT balance_id FROM stock_balance WHERE item_id = $item");
                    if($check->num_rows == 0){
                        $mysqli->query("INSERT INTO stock_balance (item_id, quantity) VALUES ($item, 0)");
                    }
                    if($adjustment_type === 'add'){
                        $stmt = $mysqli->prepare("UPDATE stock_balance SET quantity = quantity + ? WHERE item_id = ?");
                    } else {
                        $stmt = $mysqli->prepare("UPDATE stock_balance SET quantity = quantity - ? WHERE item_id = ?");
                    }
                    $stmt->bind_param("di", $qty, $item);
                    $stmt->execute();
                    $stmt->close();
                }

                // Reset notified flag if stock is now above threshold
                if ($has_inventory_thresholds) {
                    $new_qty = get_item_qty($item);
                    $threshold = get_reorder_level($item);
                    if ($new_qty > $threshold) {
                        $upd = $mysqli->prepare("UPDATE inventory_thresholds SET notified = 0 WHERE item_id = ?");
                        $upd->bind_param("i", $item);
                        $upd->execute();
                        $upd->close();
                    }
                }

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

// Handle reorder level update
if (isset($_POST['update_reorder'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $new_level = (float)($_POST['reorder_level'] ?? 10);
    
    if ($item_id > 0 && $new_level >= 0) {
        if ($has_inventory_thresholds) {
            $stmt = $mysqli->prepare("INSERT INTO inventory_thresholds (item_id, threshold_qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE threshold_qty = VALUES(threshold_qty)");
            $stmt->bind_param("id", $item_id, $new_level);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare("UPDATE items SET reorder_level = ? WHERE item_id = ?");
            $stmt->bind_param("di", $new_level, $item_id);
            $stmt->execute();
            $stmt->close();
        }
        $success = "Reorder level updated successfully.";
    }
}
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

  <div class="tab-content">
    <!-- Adjust Stock Tab -->
    <div class="tab-pane fade show active" id="adjust-panel" role="tabpanel">
      <div class="card-box mt-3">
        <h5>Adjust Stock Level</h5>
        <?php if($user_role === 'storekeeper'): ?>
          <div class="alert alert-warning">
            <strong>Note:</strong> As a Storekeeper, you can <strong>add</strong> stock. To <strong>remove</strong> stock (for damage, spoilage, or loss), an Administrator or HOD must authorize it.
          </div>
        <?php endif; ?>

        <form method="POST">
          <div class="form-row">
            <div class="form-group col-12 col-md-6">
              <label>Item <span class="text-danger">*</span></label>
              <select name="item_id" class="form-control" required>
                <option value="">-- Select Item --</option>
                <?php
                $q = "SELECT i.item_id, i.{$col_item_name} AS item_name FROM items i ORDER BY i.{$col_item_name}";
                $res = $mysqli->query($q);
                while($r = $res->fetch_assoc()){
                  $cur_qty = get_item_qty($r['item_id']);
                  echo "<option value='".intval($r['item_id'])."'>".htmlentities($r['item_name'])." (Current: ".number_format($cur_qty, 0).")</option>";
                }
                ?>
              </select>
            </div>

            <div class="form-group col-12 col-md-3">
              <label>Adjustment Type <span class="text-danger">*</span></label>
              <select name="adjustment_type" class="form-control" required>
                <option value="add">Add Stock</option>
                <option value="subtract" <?= !$can_approve_removal ? 'disabled' : '' ?>>
                  Remove Stock <?= !$can_approve_removal ? '(Admin/HOD only)' : '' ?>
                </option>
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
                <th>Category</th>
                <th>Current Stock</th>
                <th>Unit</th>
                <th>Reorder Level</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT i.item_id, i.{$col_item_name} AS item_name, i.{$col_unit} AS unit FROM items i ORDER BY i.{$col_item_name} ASC";
            $res = $mysqli->query($sql);
            
            $inventory_rows = [];
            if($res && $res->num_rows > 0) {
                while($r = $res->fetch_assoc()) {
                    $item_id = (int)$r['item_id'];
                    $cats_res = $mysqli->query("SELECT c.category_id, c.name FROM item_categories ic JOIN categories c ON c.category_id = ic.category_id WHERE ic.item_id = $item_id ORDER BY c.name");
                    $cats = $cats_res ? $cats_res->fetch_all(MYSQLI_ASSOC) : [];
                    $reorder = get_reorder_level($item_id);

                    if(empty($cats)){
                        $inventory_rows[] = ['item_name' => $r['item_name'], 'category' => '', 'unit' => $r['unit'], 'qty' => get_item_qty($item_id), 'reorder' => $reorder];
                    } else {
                        foreach($cats as $cat){
                            $inventory_rows[] = ['item_name' => $r['item_name'], 'category' => $cat['name'], 'unit' => $r['unit'], 'qty' => get_item_qty($item_id, (int)$cat['category_id']), 'reorder' => $reorder];
                        }
                        $uncat_qty = get_item_qty($item_id) - array_sum(array_map(function($cat) use ($item_id) { return get_item_qty($item_id, (int)$cat['category_id']); }, $cats));
                        if($uncat_qty != 0){
                            $inventory_rows[] = ['item_name' => $r['item_name'], 'category' => '(Uncategorized)', 'unit' => $r['unit'], 'qty' => $uncat_qty, 'reorder' => $reorder];
                        }
                    }
                }
            }

            if(!empty($inventory_rows)) {
                foreach($inventory_rows as $r) {
                    $qty = $r['qty'];
                    $reorder = $r['reorder'];

                    $status_class = 'success';
                    $status_text = 'In Stock';
                    if($qty <= 0) {
                        $status_class = 'danger';
                        $status_text = 'Out of Stock';
                    } else if($qty <= $reorder) {
                        $status_class = 'warning';
                        $status_text = 'Low Stock';
                    }
                    
                    echo "<tr>";
                    echo "<td>".htmlentities($r['item_name'])."</td>";
                    echo "<td>".htmlentities($r['category'])."</td>";
                    echo "<td><strong>".number_format($qty, 0)."</strong></td>";
                    echo "<td>".htmlentities($r['unit'] ?? '')."</td>";
                    echo "<td>".number_format($reorder, 0)."</td>";
                    echo "<td><span class='badge badge-".$status_class."'>".$status_text."</span></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='text-center text-muted'>No items in inventory</td></tr>";
            }
            ?>
            </tbody>
          </table>
        </div>
        <p class="text-muted small mt-2">
          <i class="fe-info"></i> Stock quantities computed from stock_transactions history. Reorder levels from inventory_thresholds / items.reorder_level.
        </p>
      </div>
    </div>

    <!-- Reorder Levels Tab -->
    <div class="tab-pane fade" id="reorder-panel" role="tabpanel">
      <div class="card-box mt-3">
        <h5>Manage Reorder Levels</h5>
        <p class="text-muted">Set the minimum stock quantity that triggers a "low stock" alert for each item.</p>
        
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>Current Stock</th>
                <th>Current Reorder Level</th>
                <th>New Reorder Level</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $q = "SELECT i.item_id, i.{$col_item_name} AS item_name FROM items i ORDER BY i.{$col_item_name}";
            $res = $mysqli->query($q);
            if($res && $res->num_rows > 0) {
                while($r = $res->fetch_assoc()) {
                    $qty = get_item_qty($r['item_id']);
                    $reorder = get_reorder_level($r['item_id']);
                    
                    $status_class = 'success';
                    $status_text = 'Normal';
                    if($qty <= 0) {
                        $status_class = 'danger';
                        $status_text = 'Out of Stock';
                    } else if($qty <= $reorder) {
                        $status_class = 'warning';
                        $status_text = 'Low Stock';
                    }
                    
                    $form_id = 'reorder_form_' . intval($r['item_id']);
                    echo "<tr>";
                    echo "<td>".htmlentities($r['item_name'])."</td>";
                    echo "<td><strong>".number_format($qty, 0)."</strong></td>";
                    echo "<td>".number_format($reorder, 0)."</td>";
                    echo "<td>
                      <form id='".$form_id."' method='POST'>
                        <input type='hidden' name='item_id' value='".intval($r['item_id'])."'>
                        <input type='number' name='reorder_level' class='form-control form-control-sm' style='width:80px;' value='".intval($reorder)."' min='0'>
                      </form>
                    </td>";
                    echo "<td><span class='badge badge-".$status_class."'>".$status_text."</span></td>";
                    echo "<td><button type='submit' form='".$form_id."' name='update_reorder' class='btn btn-sm btn-primary'>Update</button></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='text-center text-muted'>No items found</td></tr>";
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
        $sql = "SELECT se.*, i.{$col_item_name} AS item_name
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