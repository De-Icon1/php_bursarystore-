<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$err = $success = '';

// Load categories if table exists
$categories = [];
$have_categories = false;
$check = $mysqli->query("SHOW TABLES LIKE 'categories'");
if($check && $check->num_rows > 0){
  $have_categories = true;
  $cq = $mysqli->query("SELECT category_id, name FROM categories ORDER BY name");
  if($cq) while($cr = $cq->fetch_assoc()) $categories[] = $cr;
}

// Handle new category creation
if(isset($_POST['add_category'])){
  $new = trim($_POST['new_category'] ?? '');
  if($new === ''){
    $err = 'Category name cannot be empty.';
  } else {
    if(!$have_categories){
      $err = 'Categories table not found.';
    } else {
      $s = $mysqli->prepare("INSERT INTO categories (name) VALUES (?)");
      if($s){
        $s->bind_param('s', $new);
        if($s->execute()){
          $success = 'Category added.';
          // reload categories
          $categories = [];
          $cq = $mysqli->query("SELECT category_id, name FROM categories ORDER BY name");
          if($cq) while($cr = $cq->fetch_assoc()) $categories[] = $cr;
        } else {
          $err = 'Insert failed: '.$mysqli->error;
        }
        $s->close();
      } else {
        $err = 'Prepare failed: '.$mysqli->error;
      }
    }
  }
}

// Handle new item creation
// AJAX handler: add item and return JSON
if(isset($_POST['add_item_ajax'])){
  header('Content-Type: application/json');
  $iname = trim($_POST['item_name'] ?? '');
  if($iname === ''){
    echo json_encode(['success'=>false,'message'=>'Item name cannot be empty.']);
    exit;
  }
  $new_item_id = null;
  if($have_categories){
    $icat = isset($_POST['item_category']) && $_POST['item_category'] !== '' ? (int)$_POST['item_category'] : null;
    if($icat !== null){
      $s = $mysqli->prepare("INSERT INTO items (item_name, category_id) VALUES (?, ?)");
      if($s){
        $s->bind_param('si', $iname, $icat);
        if($s->execute()){
          $new_item_id = $mysqli->insert_id;
        } else {
          echo json_encode(['success'=>false,'message'=>'Insert failed: '.$mysqli->error]);
          $s->close();
          exit;
        }
        $s->close();
      } else {
        echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$mysqli->error]);
        exit;
      }
    } else {
      $s = $mysqli->prepare("INSERT INTO items (item_name) VALUES (?)");
      if($s){
        $s->bind_param('s', $iname);
        if($s->execute()){
          $new_item_id = $mysqli->insert_id;
        } else {
          echo json_encode(['success'=>false,'message'=>'Insert failed: '.$mysqli->error]);
          $s->close();
          exit;
        }
        $s->close();
      } else {
        echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$mysqli->error]);
        exit;
      }
    }
  } else {
    $s = $mysqli->prepare("INSERT INTO items (item_name) VALUES (?)");
    if($s){
      $s->bind_param('s', $iname);
      if($s->execute()){
        $new_item_id = $mysqli->insert_id;
      } else {
        echo json_encode(['success'=>false,'message'=>'Insert failed: '.$mysqli->error]);
        $s->close();
        exit;
      }
      $s->close();
    } else {
      echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$mysqli->error]);
      exit;
    }
  }
  echo json_encode(['success'=>true,'new_item_id'=>$new_item_id,'message'=>'Item added.']);
  exit;
}

// Handle new item creation (non-AJAX fallback)
if(isset($_POST['add_item'])){
  $iname = trim($_POST['item_name'] ?? '');
  if($iname === ''){
    $err = 'Item name cannot be empty.';
  } else {
    $new_item_id = null;
    if($have_categories){
      $icat = isset($_POST['item_category']) && $_POST['item_category'] !== '' ? (int)$_POST['item_category'] : null;
      if($icat !== null){
        $s = $mysqli->prepare("INSERT INTO items (item_name, category_id) VALUES (?, ?)");
        if($s){
          $s->bind_param('si', $iname, $icat);
          if($s->execute()){
            $new_item_id = $mysqli->insert_id;
            $success = 'Item added.';
          } else {
            $err = 'Insert failed: '.$mysqli->error;
          }
          $s->close();
        } else {
          $err = 'Prepare failed: '.$mysqli->error;
        }
      } else {
        // insert without category
        $s = $mysqli->prepare("INSERT INTO items (item_name) VALUES (?)");
        if($s){
          $s->bind_param('s', $iname);
          if($s->execute()){
            $new_item_id = $mysqli->insert_id;
            $success = 'Item added.';
          } else {
            $err = 'Insert failed: '.$mysqli->error;
          }
          $s->close();
        } else {
          $err = 'Prepare failed: '.$mysqli->error;
        }
      }
    } else {
      // categories table missing, insert only name
      $s = $mysqli->prepare("INSERT INTO items (item_name) VALUES (?)");
      if($s){
        $s->bind_param('s', $iname);
        if($s->execute()){
          $new_item_id = $mysqli->insert_id;
          $success = 'Item added.';
        } else {
          $err = 'Insert failed: '.$mysqli->error;
        }
        $s->close();
      } else {
        $err = 'Prepare failed: '.$mysqli->error;
      }
    }
    // store new item id in session so it can be selected after reload
    if(isset($new_item_id) && $new_item_id) $_SESSION['new_item_id'] = $new_item_id;
  }

}

if(isset($_POST['update_stock'])){
    $item = (int)$_POST['item_id'];
    $qty = (float)$_POST['quantity'];
    $reference = trim($_POST['reference']);
    $note = trim($_POST['note']);
    $by = $_SESSION['doc_number'];

    // insert into stock_entries as qty_in
    $stmt = $mysqli->prepare("INSERT INTO stock_entries (item_id, qty_in, reference, note, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $item, $qty, $reference, $note, $by);
    if($stmt->execute()){
        $stmt2 = $mysqli->prepare("INSERT INTO stock_balance (item_id, quantity) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
        $stmt2->bind_param("id", $item, $qty);
        $stmt2->execute();

        // reset notified flag if above threshold
        $stmt3 = $mysqli->prepare("SELECT threshold_qty FROM inventory_thresholds WHERE item_id = ?");
        $stmt3->bind_param("i", $item);
        $stmt3->execute();
        $stmt3->bind_result($th);
        if($stmt3->fetch() && $th !== null){
            // fetch current qty
            $stmt3->close();
            $stmt4 = $mysqli->prepare("SELECT quantity FROM stock_balance WHERE item_id = ?");
            $stmt4->bind_param("i", $item);
            $stmt4->execute();
            $stmt4->bind_result($cur_qty);
            if($stmt4->fetch()){
                if($cur_qty > $th){
                    $upd = $mysqli->prepare("UPDATE inventory_thresholds SET notified = 0 WHERE item_id = ?");
                    $upd->bind_param("i", $item);
                    $upd->execute();
                }
            }
            $stmt4->close();
        } else {
            $stmt3->close();
        }

        $success = "Stock updated.";
    } else {
        $err = "Error: ".$mysqli->error;
    }
    $stmt->close();
}
?>
<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<!-- Page-specific CSS to prevent select dropdown overlap with sidebar -->
<style>
  /* Ensure the main content stacks above the fixed sidebar when dropdowns open */
  .container {
    position: relative;
    z-index: 12000;
  }
  /* Lower the sidebar slightly so native dropdowns render above it */
  .left-side-menu {
    z-index: 1000;
  }
  /* If a parent uses overflow hidden, allow dropdowns to be visible */
  .card-box, .form-group { overflow: visible; }
  /* Ensure select dropdowns appear above header and sidebar */
  /* Native/select2/bootstrap-select dropdowns should appear above everything */
  select, .bootstrap-select .dropdown-menu, .select2-container, .select2-dropdown, .dropdown-menu { z-index: 999999 !important; position: relative; }

  /* Add top spacing so the fixed header doesn't cover the top of the form */
  .container.mt-4 { margin-top: 120px; }

  /* On small screens reduce the top margin but keep dropdown z-index high */
  @media (max-width: 768px){
    .container.mt-4 { margin-top: 140px; }
  }
</style>

<div class="content-page">
  <div class="content container mt-4">
  <h3>Manage Stock</h3>
  <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
  <?php if($err) echo "<div class='alert alert-danger'>$err</div>"; ?>

  <div class="card-box">
    <form method="POST">
      <div class="form-row mb-3">
        <div class="form-group col-12">
          <label>Add Category</label>
          <div class="input-group">
            <input type="text" name="new_category" class="form-control" placeholder="New category name">
            <div class="input-group-append"><button class="btn btn-secondary" name="add_category" type="submit">Add</button></div>
          </div>
        </div>
      </div>
      <!-- Only Add Category is required on this page now -->
      <div class="form-row">
        <div class="form-group col-12">
          <p class="text-muted">Item management and stock updates have been removed from this form. Use the dedicated stock pages for adding stock or items.</p>
        </div>
      </div>
    </form>
  </div>

  <div class="card-box mt-3">
    <h5>Current Balances</h5>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>Item</th><th>Category</th><th>Quantity</th><th>Last Updated</th></tr></thead>
        <tbody>
          <?php
          $sql = "SELECT sb.quantity, sb.last_updated, it.item_name, c.name AS category FROM stock_balance sb JOIN items it ON sb.item_id = it.item_id LEFT JOIN categories c ON it.category_id = c.category_id ORDER BY it.item_name";
          $res = $mysqli->query($sql);
          while($row = $res->fetch_assoc()){
            echo "<tr>";
            echo "<td>".htmlentities($row['item_name'])."</td>";
            echo "<td>".htmlentities($row['category'])."</td>";
            echo "<td>".htmlentities($row['quantity'])."</td>";
            echo "<td>".htmlentities($row['last_updated'])."</td>";
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

  </div>
</div>
  <?php /* Item-related client scripts removed: page now only supports Add Category */ ?>
</body>
</html>