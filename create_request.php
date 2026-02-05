<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
include('assets/inc/functions.php');
include('assets/inc/stock_functions.php');

if(!check_login()){
    header('Location: index.php'); exit;
}

$err = $success = '';
// load units for selection
$units = [];
$r = $mysqli->query("SELECT unit_id, code, name FROM units ORDER BY name");
if($r) while($row = $r->fetch_assoc()) $units[] = $row;

// load items
$items = [];
$r = $mysqli->query("SELECT item_id, name FROM items ORDER BY name");
if($r) while($row = $r->fetch_assoc()) $items[] = $row;

if(isset($_POST['create_request'])){
    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $requested_by = $_SESSION['user_id'] ?? null;

    $req_items = [];
    foreach($_POST['item_id'] as $idx => $it){
        $qty = (int)($_POST['quantity'][$idx] ?? 0);
        if($qty > 0){ $req_items[] = ['item_id'=>(int)$it, 'quantity'=>$qty]; }
    }

    if($unit_id <= 0) $err = 'Select a requesting unit.';
    else if(empty($req_items)) $err = 'Add at least one item with quantity.';
    else {
        $res = create_stock_request($unit_id, $requested_by, $req_items, $note);
        if($res['success']) $success = 'Request created (#'.$res['request_id'].')';
        else $err = $res['message'];
    }
}

?>
<?php include('assets/inc/head.php'); ?>
<body>
<?php include('assets/inc/nav.php'); ?>
<?php include('assets/inc/sidebar_admin.php'); ?>
<div class="content-page"><div class="content container">
  <h4>Bursary Store — Create Stock Request</h4>
  <?php if($success) echo "<div class='alert alert-success'>{$success}</div>"; ?>
  <?php if($err) echo "<div class='alert alert-danger'>{$err}</div>"; ?>

  <form method="post">
    <div class="form-row">
      <div class="form-group col-md-6">
        <label>Unit</label>
        <select name="unit_id" class="form-control" required>
          <option value="">-- Select Unit --</option>
          <?php foreach($units as $u) echo "<option value='{$u['unit_id']}'>".htmlentities($u['name'])." ({$u['code']})</option>"; ?>
        </select>
      </div>
      <div class="form-group col-md-6">
        <label>Note</label>
        <input type="text" name="note" class="form-control">
      </div>
    </div>

    <h5>Items</h5>
    <div id="items-area">
      <div class="form-row item-row">
        <div class="form-group col-md-6">
          <select name="item_id[]" class="form-control" required>
            <option value="">-- Select Item --</option>
            <?php foreach($items as $it) echo "<option value='{$it['item_id']}'>".htmlentities($it['name'])."</option>"; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <input type="number" name="quantity[]" class="form-control" min="1" placeholder="Quantity">
        </div>
        <div class="form-group col-md-3">
          <button type="button" class="btn btn-danger btn-sm remove-item">Remove</button>
        </div>
      </div>
    </div>
    <div class="form-group">
      <button type="button" id="add-item" class="btn btn-secondary">Add another item</button>
    </div>
    <div class="form-group">
      <button type="submit" name="create_request" class="btn btn-primary">Create Request</button>
    </div>
  </form>

</div></div>
<?php include('assets/inc/footer.php'); ?>
<script>
document.getElementById('add-item').addEventListener('click', function(){
  var row = document.querySelector('.item-row').cloneNode(true);
  row.querySelector('input').value = '';
  document.getElementById('items-area').appendChild(row);
});
document.addEventListener('click', function(e){ if(e.target && e.target.classList.contains('remove-item')){ var r = e.target.closest('.item-row'); if(r) r.remove(); }});
</script>
</body></html>
