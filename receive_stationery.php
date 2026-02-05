<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
include('assets/inc/functions.php');
include('assets/inc/stock_functions.php');

// Restrict to logged-in admin/storekeeper
if(!check_login() || !authorize(['admin','storekeeper'])){
    header('Location: index.php');
    exit;
}

$err = $success = '';

// Preselect item from querystring if provided
$pre_item = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Load stationery items only (include unit for UI convenience)
$items = [];
$sql = "SELECT item_id, COALESCE(name,item_name) AS item_name, unit FROM items WHERE category IN ('Stationery','Toner') OR COALESCE(name,item_name) LIKE '%pen%' OR COALESCE(name,item_name) LIKE '%paper%' OR COALESCE(name,item_name) LIKE '%toner%' ORDER BY item_name";
$res = $mysqli->query($sql);
if($res) while($r = $res->fetch_assoc()) $items[] = $r;

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
        if(empty($received_unit)) $received_unit = $stored_unit;
        $qty_to_store = $quantity;
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

?>
<?php include('assets/inc/head.php'); ?>
<body>
<?php include('assets/inc/nav.php'); ?>
<?php include('assets/inc/sidebar_admin.php'); ?>

<div class="content-page">
  <div class="content container">
    <h4>Receive Stationery / Toner</h4>

    <?php if($success) echo "<div class='alert alert-success'>{$success}</div>"; ?>
    <?php if($err) echo "<div class='alert alert-danger'>{$err}</div>"; ?>

    <div class="card-box">
      <form method="post">
        <div class="form-row">
          <div class="form-group col-md-5">
            <label>Item</label>
            <select name="item_id" class="form-control" required>
              <option value="">-- Select Stationery/Toner --</option>
              <?php foreach($items as $it){ $sel = ($pre_item && $pre_item == $it['item_id']) ? ' selected' : ''; $du = htmlspecialchars($it['unit'] ?? ''); echo "<option value='".intval($it['item_id'])."".$sel." data-unit='".$du."'>".htmlentities($it['item_name'])."</option>"; } ?>
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
            <label>Reference / Invoice #</label>
            <input type="text" name="reference" class="form-control">
          </div>
          <div class="form-group col-md-6">
            <label>Note</label>
            <input type="text" name="note" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <button type="submit" name="receive" class="btn btn-success">Receive Stationery</button>
          <a href="stationery_store.php" class="btn btn-light">Back to Store</a>
        </div>
      </form>
    </div>

  </div>
</div>

<?php include('assets/inc/footer.php'); ?>

<script>
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
