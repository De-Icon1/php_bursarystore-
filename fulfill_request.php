<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
include('assets/inc/functions.php');
include('assets/inc/stock_functions.php');

if(!check_login() || !authorize(['admin','storekeeper'])){ header('Location: index.php'); exit; }

$err = $success = '';

// Approve/fulfill action
if(isset($_POST['fulfill']) && isset($_POST['request_id'])){
    $request_id = (int)$_POST['request_id'];
    $res = fulfill_stock_request($request_id, $_SESSION['user_id'] ?? null);
    if($res['success']) $success = $res['message']; else $err = $res['message'];
}

// load pending requests
$requests = [];
$res = $mysqli->query("SELECT sr.request_id, sr.created_at, sr.status, u.name as unit_name FROM stock_requests sr JOIN units u ON sr.unit_id = u.unit_id WHERE sr.status = 'pending' ORDER BY sr.created_at DESC");
if($res) while($r = $res->fetch_assoc()) $requests[] = $r;

?>
<?php include('assets/inc/head.php'); ?>
<body>
<?php include('assets/inc/nav.php'); ?>
<?php include('assets/inc/sidebar_admin.php'); ?>
<div class="content-page"><div class="content container">
  <h4>Pending Stock Requests</h4>
  <?php if($success) echo "<div class='alert alert-success'>{$success}</div>"; ?>
  <?php if($err) echo "<div class='alert alert-danger'>{$err}</div>"; ?>

  <div class="table-responsive card-box">
    <table class="table table-striped">
      <thead><tr><th>Requested At</th><th>Request #</th><th>Unit</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if($requests) foreach($requests as $rq){
        echo '<tr>';
        echo '<td>'.htmlentities($rq['created_at']).'</td>';
        echo '<td>'.htmlentities($rq['request_id']).'</td>';
        echo '<td>'.htmlentities($rq['unit_name']).'</td>';
        echo "<td><form method='post' style='display:inline'><input type='hidden' name='request_id' value='".intval($rq['request_id'])."'><button type='submit' name='fulfill' class='btn btn-success btn-sm'>Fulfill</button></form> <a class='btn btn-info btn-sm' href='view_request.php?request_id=".intval($rq['request_id'])."'>View</a></td>";
        echo '</tr>';
      } else { echo '<tr><td colspan="4" class="text-center text-muted">No pending requests</td></tr>'; } ?>
      </tbody>
    </table>
  </div>

</div></div>
<?php include('assets/inc/footer.php'); ?>
</body></html>
