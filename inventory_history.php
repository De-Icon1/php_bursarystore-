<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = "";
$params = [];
if($from && $to){
    $where = " WHERE issued_at BETWEEN ? AND ? ";
}
?>
<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<div class="content-page">
<div class="content container">
  <h3>Issuance History</h3>

  <div class="card-box">
    <form method="GET" class="form-inline mb-3">
      <label class="mr-2">From</label>
      <input type="date" name="from" value="<?= htmlentities($from) ?>" class="form-control mr-2">
      <label class="mr-2">To</label>
      <input type="date" name="to" value="<?= htmlentities($to) ?>" class="form-control mr-2">
      <button class="btn btn-secondary">Filter</button>
    </form>

    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>Date</th><th>Item</th><th>Unit</th><th>Qty</th><th>By</th><th>Purpose</th></tr></thead>
        <tbody>
        <?php
        // Support both items.name and items.item_name, and use free-text unit from stock_issues
        $itemNameCol = 'it.item_name';
        $chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
        if(!$chk || $chk->num_rows === 0){
          $itemNameCol = 'it.name';
        }

        if($from && $to){
          $sql = "SELECT si.issued_at, {$itemNameCol} AS item_name, si.unit, si.quantity, si.issued_by, si.purpose
              FROM stock_issues si JOIN items it ON si.item_id = it.item_id
              WHERE si.issued_at BETWEEN ? AND ?
              ORDER BY si.issued_at DESC";
          $stmt = $mysqli->prepare($sql);
          $stmt->bind_param("ss", $from, $to);
          $stmt->execute();
          $res = $stmt->get_result();
        } else {
          $sql = "SELECT si.issued_at, {$itemNameCol} AS item_name, si.unit, si.quantity, si.issued_by, si.purpose
              FROM stock_issues si JOIN items it ON si.item_id = it.item_id
              ORDER BY si.issued_at DESC LIMIT 200";
          $res = $mysqli->query($sql);
        }

        while($r = $res->fetch_assoc()){
          echo "<tr>";
          echo "<td>".htmlentities($r['issued_at'])."</td>";
          echo "<td>".htmlentities($r['item_name'])."</td>";
          echo "<td>".htmlentities($r['unit'])."</td>";
          echo "<td>".htmlentities($r['quantity'])."</td>";
          echo "<td>".htmlentities($r['issued_by'])."</td>";
          echo "<td>".htmlentities($r['purpose'])."</td>";
          echo "</tr>";
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