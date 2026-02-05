<?php
session_start();
require_once __DIR__ . '/../assets/inc/config.php';
require_once __DIR__ . '/../assets/inc/checklogins.php';
require_once __DIR__ . '/../assets/inc/functions.php';

// Restrict to admin
if(!check_login() || !authorize(['admin'])){
    header('Location: ../index.php');
    exit;
}

$msg = '';
$errors = [];

// fetch items recorded with litres
$items = [];
$q = $mysqli->prepare("SELECT item_id, COALESCE(name,item_name) AS name, unit FROM items WHERE unit = 'litres'");
if($q){
    $q->execute();
    $res = $q->get_result();
    while($r = $res->fetch_assoc()) $items[] = $r;
    $q->close();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] == '1'){
    $target_unit = trim($_POST['target_unit'] ?? '');
    $apply_multiplier = isset($_POST['apply_multiplier']) ? true : false;
    $multiplier = isset($_POST['multiplier']) ? (float)$_POST['multiplier'] : 1.0;

    if(!in_array($target_unit, ['pcs','ream','pack','sets','box'])){
        $errors[] = 'Invalid target unit selected.';
    }

    if(empty($items)){
        $errors[] = 'No items found with unit litres.';
    }

    if(empty($errors)){
        $mysqli->begin_transaction();
        try {
            $update = $mysqli->prepare("UPDATE items SET unit = ? WHERE item_id = ?");
            $upd_receipt = null;
            $upd_tx = null;
            if($apply_multiplier){
                $upd_receipt = $mysqli->prepare("UPDATE receipt_items SET quantity = ROUND(quantity * ?) WHERE item_id = ?");
                $upd_tx = $mysqli->prepare("UPDATE stock_transactions SET qty_change = ROUND(qty_change * ?) WHERE item_id = ?");
            }

            foreach($items as $it){
                $iid = (int)$it['item_id'];
                $update->bind_param('si', $target_unit, $iid);
                $update->execute();

                if($apply_multiplier && $upd_receipt && $upd_tx){
                    $upd_receipt->bind_param('di', $multiplier, $iid);
                    $upd_receipt->execute();
                    $upd_tx->bind_param('di', $multiplier, $iid);
                    $upd_tx->execute();
                }
            }

            if(isset($update) && $update) $update->close();
            if($upd_receipt) $upd_receipt->close();
            if($upd_tx) $upd_tx->close();

            $mysqli->commit();

            if(function_exists('log_action') && isset($_SESSION['user_id'])){
                log_action($_SESSION['user_id'], 'Bulk migrated items with unit litres to '.$target_unit.($apply_multiplier?" (multiplier={$multiplier})":''));
            }

            $msg = 'Migration completed. Updated '.count($items).' item(s).';
            // refresh list
            $items = [];
            $q = $mysqli->prepare("SELECT item_id, COALESCE(name,item_name) AS name, unit FROM items WHERE unit = 'litres'");
            if($q){ $q->execute(); $res = $q->get_result(); while($r = $res->fetch_assoc()) $items[] = $r; $q->close(); }
        } catch(Exception $e){
            $mysqli->rollback();
            $errors[] = 'Migration failed: '.$e->getMessage();
        }
    }
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Migrate litres units — Bursary Store</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">
  <div class="container">
    <h3>Bulk migrate items recorded with unit = 'litres'</h3>
    <p class="text-muted">Warning: create a DB backup before running migration. Example:</p>
    <pre>mysqldump -u root -p b​ursarystore > bursary_backup.sql</pre>

    <?php if($msg) echo '<div class="alert alert-success">'.htmlspecialchars($msg).'</div>'; ?>
    <?php if($errors) foreach($errors as $e) echo '<div class="alert alert-danger">'.htmlspecialchars($e).'</div>'; ?>

    <h5>Items currently using 'litres' (<?php echo count($items); ?>)</h5>
    <table class="table table-sm">
      <thead><tr><th>ID</th><th>Name</th><th>Unit</th></tr></thead>
      <tbody>
      <?php if(empty($items)) echo '<tr><td colspan="3">None</td></tr>'; else foreach($items as $it){ echo '<tr><td>'.intval($it['item_id']).'</td><td>'.htmlspecialchars($it['name']).'</td><td>'.htmlspecialchars($it['unit']).'</td></tr>'; } ?>
      </tbody>
    </table>

    <form method="post" onsubmit="return confirm('Are you sure? This will update the database. Make a backup first.')">
      <div class="form-group">
        <label>Target unit</label>
        <select name="target_unit" class="form-control" required>
          <option value="pcs">pcs</option>
          <option value="ream">ream</option>
          <option value="pack">pack</option>
          <option value="sets">sets</option>
          <option value="box">box</option>
        </select>
      </div>
      <div class="form-group form-check">
        <input type="checkbox" name="apply_multiplier" id="apply_multiplier" class="form-check-input">
        <label for="apply_multiplier" class="form-check-label">Also multiply existing receipt/transaction quantities by a factor</label>
      </div>
      <div class="form-group">
        <label>Multiplier (applied only if above checked)</label>
        <input type="number" step="0.01" name="multiplier" value="1" class="form-control" />
      </div>
      <input type="hidden" name="confirm" value="1">
      <button class="btn btn-danger">Run Migration</button>
      <a class="btn btn-secondary" href="../admin_dashboard.php">Cancel</a>
    </form>

    <hr>
    <p class="text-muted small">This script updates only the `items.unit` field by default. If you choose to apply a multiplier, it will also multiply `receipt_items.quantity` and `stock_transactions.qty_change` for the affected item_ids. Use with caution and backup your DB first.</p>
  </div>
</body>
</html>
