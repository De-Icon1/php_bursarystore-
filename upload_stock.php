<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

$err = $success = '';
if(isset($_POST['upload'])){
  if(!isset($_FILES['file'])){ $err = "No file uploaded"; }
  else {
    $tmp = $_FILES['file']['tmp_name'];
    if(($handle = fopen($tmp, "r")) !== FALSE){
      // CSV formats supported:
      //   1) item_name,quantity (legacy)
      //   2) item_name,category,quantity  (preferred; uses category to distinguish similar items)
      $mysqli->begin_transaction();
      try {
        // Pre-detect schema once
        $has_categories_tbl = $mysqli->query("SHOW TABLES LIKE 'categories'");
        $has_category_id_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
        $has_items_category_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category'");

        while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){
          $cols = count($data);
          if($cols < 2) continue;

          $item_name = trim($data[0]);
          $csv_category = '';
          if($cols >= 3){
            $csv_category = trim($data[1]);
            $qty = (float)trim($data[2]);
          } else {
            $qty = (float)trim($data[1]);
          }

          if($item_name === '' || $qty == 0){
            continue; // skip empty or zero rows
          }

          $item_id = null;

          if($csv_category !== ''){
            // Try match by name + category, using categories table when available
            if($has_categories_tbl && $has_categories_tbl->num_rows && $has_category_id_col && $has_category_id_col->num_rows){
              $sql = "SELECT it.item_id
                  FROM items it
                  JOIN categories c ON it.category_id = c.category_id
                  WHERE it.item_name = ? AND c.name = ?
                  LIMIT 2";
              $stmt = $mysqli->prepare($sql);
              if($stmt){
                $stmt->bind_param("ss", $item_name, $csv_category);
                $stmt->execute();
                $res = $stmt->get_result();
                if($res && $res->num_rows === 1){
                  $row = $res->fetch_assoc();
                  $item_id = (int)$row['item_id'];
                } elseif($res && $res->num_rows > 1){
                  // Ambiguous: multiple items with same name & category label; skip row for safety
                  $item_id = null;
                }
                $stmt->close();
              }
            }

            // Fallback: match against items.category text column if present
            if(!$item_id && $has_items_category_col && $has_items_category_col->num_rows){
              $sql = "SELECT item_id FROM items WHERE item_name = ? AND category = ? LIMIT 2";
              $stmt = $mysqli->prepare($sql);
              if($stmt){
                $stmt->bind_param("ss", $item_name, $csv_category);
                $stmt->execute();
                $res = $stmt->get_result();
                if($res && $res->num_rows === 1){
                  $row = $res->fetch_assoc();
                  $item_id = (int)$row['item_id'];
                }
                $stmt->close();
              }
            }
          }

          // Legacy fallback: match by name only when **no category column** was supplied
          // This prevents "Paper" with category "Legal" from accidentally updating
          // the "Paper" row that belongs to category "A4".
          if(!$item_id && $csv_category === ''){
            $stmt = $mysqli->prepare("SELECT item_id FROM items WHERE item_name = ? LIMIT 1");
            if($stmt){
              $stmt->bind_param("s", $item_name);
              $stmt->execute();
              $stmt->bind_result($found_id);
              if($stmt->fetch()){
                $item_id = (int)$found_id;
              }
              $stmt->close();
            }
          }

          if($item_id){
            // insert entry & update balance
            $stmt = $mysqli->prepare("INSERT INTO stock_entries (item_id, qty_in, reference, note, created_by) VALUES (?, ?, 'CSV Upload', ?, ?)");
            $note = "CSV upload";
            $by = $_SESSION['doc_number'] ?? ($_SESSION['user_id'] ?? 'system');
            $stmt->bind_param("idss", $item_id, $qty, $note, $by);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare("INSERT INTO stock_balance (item_id, quantity) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
            $stmt->bind_param("id", $item_id, $qty);
            $stmt->execute();
            $stmt->close();
          }
        }
        fclose($handle);
        $mysqli->commit();
        $success = "CSV processed.";
      } catch(Exception $e){
        $mysqli->rollback();
        $err = "CSV error: " . $e->getMessage();
      }
    } else {
      $err = "Unable to open uploaded file.";
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
  <h3>Upload Stock (CSV)</h3>
  <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
  <?php if($err) echo "<div class='alert alert-danger'>$err</div>"; ?>

  <div class="card-box">
    <form method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <input type="file" name="file" accept=".csv" class="form-control" required>
      </div>
      <button class="btn btn-primary" name="upload">Upload</button>
    </form>
    <p class="mt-2"><small>CSV format (preferred): <code>item_name,category,quantity</code>. Legacy format <code>item_name,quantity</code> is also accepted.</small></p>
  </div>
  </div>
  </div>

<?php include("assets/inc/footer.php"); ?>

</body>
</html>