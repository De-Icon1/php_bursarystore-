<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
include('assets/inc/functions.php');
include('assets/inc/stock_functions.php');

// Require logged-in user and restrict to admin/storekeeper
if(!check_login() || !authorize(['admin','storekeeper'])){
    header('Location: index.php');
    exit;
}

$err = $success = '';

if(isset($_POST['receive'])){
    $item_id = (int)($_POST['item_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
  $received_unit = trim($_POST['received_unit'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $unit_cost = isset($_POST['unit_cost']) ? (float)$_POST['unit_cost'] : null;
    $reference = trim($_POST['reference'] ?? '');
    $note = trim($_POST['note'] ?? '');
  $migrate_litres = isset($_POST['migrate_litres']) ? true : false;
  $migrate_to = trim($_POST['migrate_to'] ?? 'pcs');

    if($item_id <= 0) $err = 'Please select an item.';
    else if($quantity <= 0) $err = 'Quantity must be greater than zero.';
    else if(empty($supplier)) $err = 'Supplier name is required.';
    else {
      // fetch stored unit for item
      $stmt = $mysqli->prepare("SELECT unit FROM items WHERE item_id = ? LIMIT 1");
      $stored_unit = null;
      if($stmt){
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $stmt->bind_result($stored_unit);
        $stmt->fetch();
        $stmt->close();
      }

      if($stored_unit === 'litres'){
        if(!$migrate_litres){
          $err = 'This item is recorded with unit "litres". Please update the item unit before receiving, or check the migrate option to convert it to a safer unit.';
        } else {
          // perform migration: update item unit to chosen unit
          $u = in_array($migrate_to, ['pcs','ream','pack','sets','box']) ? $migrate_to : 'pcs';
          $up = $mysqli->prepare("UPDATE items SET unit = ? WHERE item_id = ?");
          if($up){
            $up->bind_param('si', $u, $item_id);
            $up->execute();
            $up->close();
            $stored_unit = $u;
          }
        }
      }

      if(!$err){
        // default received_unit to stored_unit if not supplied
        if(empty($received_unit)) $received_unit = $stored_unit;

        $qty_to_store = $quantity;
        // conversion rules: 1 pack = 5 reams
        if($stored_unit && $received_unit && $stored_unit !== $received_unit){
          if($stored_unit === 'pack' && $received_unit === 'ream'){
            if($quantity % 5 !== 0){
              $err = 'Conversion requires quantity to be a multiple of 5 reams when storing as packs.';
            } else {
              $qty_to_store = (int)($quantity / 5);
            }
          } elseif($stored_unit === 'ream' && $received_unit === 'pack'){
            $qty_to_store = (int)($quantity * 5);
          } else {
            $err = "Unit mismatch: cannot convert from {$received_unit} to {$stored_unit}. Please receive in {$stored_unit} or update the item unit.";
          }
        }

        if(!$err){
          $res = receive_stock($item_id, $qty_to_store, $supplier, $_SESSION['user_id'] ?? null, $unit_cost, $reference, $note);
          if($res['success']) $success = $res['message']; else $err = $res['message'];
        }
      }
    }
}

// Load item list for form (include unit for UI convenience)
$items = [];
$q = $mysqli->query("SELECT item_id, name, unit FROM items ORDER BY name");
if($q){ while($r = $q->fetch_assoc()) $items[] = $r; }

?>
<?php include('assets/inc/head.php'); ?>
<body>
<?php include('assets/inc/nav.php'); ?>
<?php include('assets/inc/sidebar_admin.php'); ?>

<div class="content-page">
  <div class="content container">
    <h4>Receive Stock (Bursary Store)</h4>

    <?php if($success) echo "<div class='alert alert-success'>{$success}</div>"; ?>
    <?php if($err) echo "<div class='alert alert-danger'>{$err}</div>"; ?>

    <div class="card-box">
      <form method="post">
        <div class="form-row">
          <div class="form-group col-md-5">
            <label>Item</label>
            <select name="item_id" class="form-control" required>
              <option value="">-- Select Item --</option>
              <?php foreach($items as $it){ $du = htmlspecialchars($it['unit'] ?? ''); echo "<option value='{$it['item_id']}' data-unit='{$du}'>".htmlentities($it['name'])."</option>"; } ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Quantity</label>
            <input type="number" name="quantity" class="form-control" min="1" step="1" required>
          </div>
          <div class="form-group col-md-2">
            <label>Unit</label>
            <select name="received_unit" class="form-control" required>
              <option value="">-- Unit --</option>
              <option value="pcs">pcs</option>
              <option value="ream">ream</option>
              <option value="pack">pack</option>
              <option value="sets">sets</option>
              <option value="box">box</option>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Litres?</label>
            <div class="mt-2"><input type="checkbox" name="migrate_litres" value="1"> Migrate if litres</div>
            <select name="migrate_to" class="form-control mt-1">
              <option value="pcs">pcs</option>
              <option value="ream">ream</option>
              <option value="pack">pack</option>
              <option value="sets">sets</option>
              <option value="box">box</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Supplier</label>
            <input type="text" name="supplier" class="form-control" required>
          </div>
          <div class="form-group col-md-6">
            <label>Unit Cost</label>
            <input type="number" name="unit_cost" class="form-control" step="0.01" value="0">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Reference / PO #</label>
            <input type="text" name="reference" class="form-control">
          </div>
          <div class="form-group col-md-6">
            <label>Note</label>
            <input type="text" name="note" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <button type="submit" name="receive" class="btn btn-success">Receive Stock</button>
        </div>
      </form>
    </div>

    <div class="card-box mt-3">
      <h5>Recent Receipts</h5>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>Date</th><th>Item</th><th>Quantity</th><th>Supplier</th><th>Reference</th><th>Received By</th></tr></thead>
          <tbody>
          <?php
            $sql = "SELECT sr.received_at, i.name AS item_name, ri.quantity, sr.supplier, sr.receipt_id, sr.received_by, ri.unit_cost, sr.note
                    FROM stock_receipts sr
                    JOIN receipt_items ri ON ri.receipt_id = sr.receipt_id
                    JOIN items i ON i.item_id = ri.item_id
                    ORDER BY sr.received_at DESC LIMIT 50";
            $res = $mysqli->query($sql);
            if($res && $res->num_rows){
              while($row = $res->fetch_assoc()){
                echo '<tr>';
                echo '<td>'.htmlentities($row['received_at']).'</td>';
                echo '<td>'.htmlentities($row['item_name']).'</td>';
                echo '<td>'.number_format($row['quantity']).'</td>';
                echo '<td>'.htmlentities($row['supplier']).'</td>';
                echo '<td>'.htmlentities($row['receipt_id']).'</td>';
                echo '<td>'.htmlentities($row['received_by']).'</td>';
                echo '</tr>';
              }
            } else {
              echo '<tr><td colspan="6" class="text-center text-muted">No receipts yet</td></tr>';
            }
          ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php include('assets/inc/footer.php'); ?>

<script>
// Set received unit default when item selection changes
document.addEventListener('DOMContentLoaded', function(){
  var itemSel = document.querySelector('select[name="item_id"]');
  var unitSel = document.querySelector('select[name="received_unit"]');
  if(!itemSel || !unitSel) return;
  function apply(){
    var opt = itemSel.options[itemSel.selectedIndex];
    if(opt && opt.dataset && opt.dataset.unit){
      var u = opt.dataset.unit;
      if(u) unitSel.value = u;
    }
  }
  itemSel.addEventListener('change', apply);
  apply();
});
</script>

</body>
</html>
