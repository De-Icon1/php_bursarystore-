<?php
session_start();
require_once 'assets/inc/config.php';
require_once 'assets/inc/checklogins.php';
require_once 'assets/inc/stock_functions.php';
check_login();

$err = '';
$success = '';

// Optional: debug which file is executing
if (isset($_GET['debug_path'])) {
    echo '<pre>DEBUG issue_items.php path: ' . __FILE__ . '</pre>';
    exit;
}

if (isset($_POST['issue'])) {
  $item_id   = (int)($_POST['item_id'] ?? 0);
  $unit      = trim($_POST['unit'] ?? '');
  $qty       = (int)($_POST['quantity'] ?? 0);
  $purpose   = trim($_POST['purpose'] ?? '');
  $issued_by = $_SESSION['username'] ?? ($_SESSION['full_name'] ?? 'system');
  $issued_by_id = (int)($_SESSION['user_id'] ?? 0);

    if ($item_id <= 0) {
        $err = 'Please select an item.';
    } elseif ($qty <= 0) {
        $err = 'Quantity must be greater than zero.';
    } elseif ($unit === '') {
        $err = 'Please enter the unit to issue to.';
    } else {
      // Check available stock using stock_transactions helper
      $cur_qty = get_item_current_stock($item_id);

      if ($cur_qty < $qty) {
            $err = 'Not enough stock. Available: ' . $cur_qty;
        } else {
            $mysqli->begin_transaction();
            try {
                // Insert into stock_issues (unit stored as free-text)
          $issue_id = null;
          if ($ins = $mysqli->prepare('INSERT INTO stock_issues (item_id, unit, quantity, issued_by, purpose) VALUES (?, ?, ?, ?, ?)')) {
            $ins->bind_param('isiss', $item_id, $unit, $qty, $issued_by, $purpose);
            $ins->execute();
            $issue_id = $ins->insert_id;
            $ins->close();
          }

                // Record stock_entries movement (qty_out)
                if ($ent = $mysqli->prepare('INSERT INTO stock_entries (item_id, qty_out, reference, note, created_by) VALUES (?, ?, ?, ?, ?)')) {
                    $ent->bind_param('idsss', $item_id, $qty, $purpose, $purpose, $issued_by);
                    $ent->execute();
                    $ent->close();
                }

          // Record stock transaction (negative qty_change dispatch)
          if ($issue_id && $issued_by_id && $mysqli->query("SHOW TABLES LIKE 'stock_transactions'")) {
            if ($tx = $mysqli->prepare("INSERT INTO stock_transactions (item_id, qty_change, tx_type, reference_id, user_id, note) VALUES (?, ?, 'dispatch', ?, ?, ?)")) {
              $neg_qty = -1 * (int)$qty;
              $note = 'Issue to ' . $unit . ($purpose ? ' - ' . $purpose : '');
              $tx->bind_param('iiiis', $item_id, $neg_qty, $issue_id, $issued_by_id, $note);
              $tx->execute();
              $tx->close();
            }
          }

                $mysqli->commit();
                $success = 'Item issued successfully.';
            } catch (Throwable $e) {
                $mysqli->rollback();
                $err = 'Error issuing item: ' . $e->getMessage();
            }
        }
    }
}

// Load items for dropdown, mirroring stationery_store.php logic
$items = [];
$res = $mysqli->query("SELECT * FROM items");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    // determine id (support both item_id and id)
    $id = $row['item_id'] ?? ($row['id'] ?? null);
    if (!$id) continue;

    // display name fallbacks
    $display = '';
    if (isset($row['item_name']) && $row['item_name'] !== null && $row['item_name'] !== '') {
      $display = $row['item_name'];
    } elseif (isset($row['name']) && $row['name'] !== null) {
      $display = $row['name'];
    }

    // category string as used in stationery_store (e.g. "A4")
    $category = $row['category'] ?? ($row['category_name'] ?? '');

    $items[] = [
      'item_id'  => $id,
      'label'    => $display,
      'category' => $category,
    ];
  }
  $res->close();
}

?>
<?php include 'assets/inc/head.php'; ?>
<body>
<?php include 'assets/inc/nav.php'; ?>
<?php include 'assets/inc/sidebar_admin.php'; ?>

<div class="content-page">
  <div class="content container-fluid">
    <h3>Issue Items</h3>

    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo htmlentities($success); ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert alert-danger"><?php echo htmlentities($err); ?></div>
    <?php endif; ?>

    <div class="card-box">
      <form method="post">
        <div class="form-group">
          <label>Item</label>
          <select name="item_id" class="form-control" required>
            <option value="">-- Select Item --</option>
            <?php foreach ($items as $it): ?>
              <option value="<?php echo (int)$it['item_id']; ?>" data-category="<?php echo htmlentities($it['category']); ?>">
                <?php echo htmlentities($it['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Category</label>
          <input type="text" id="issue-item-category" class="form-control" readonly>
        </div>

        <div class="form-group">
          <label>Issue To (Unit)</label>
          <input type="text" name="unit" class="form-control" placeholder="e.g. Administration, Registry" required>
        </div>

        <div class="form-group">
          <label>Quantity</label>
          <input type="number" name="quantity" class="form-control" step="1" min="1" required>
        </div>

        <div class="form-group">
          <label>Purpose / Reference</label>
          <input type="text" name="purpose" class="form-control" placeholder="Job card / purpose">
        </div>

        <button type="submit" name="issue" class="btn btn-warning">Issue Item</button>
      </form>
    </div>
  </div>
</div>

<?php include 'assets/inc/footer.php'; ?>
</body>

<script>
// Auto-fill read-only Category field when an item is selected
document.addEventListener('DOMContentLoaded', function () {
  var itemSelect = document.querySelector('select[name="item_id"]');
  var categoryInput = document.getElementById('issue-item-category');
  if (!itemSelect || !categoryInput) return;

  function updateCategory() {
    var opt = itemSelect.options[itemSelect.selectedIndex];
    if (!opt) {
      categoryInput.value = '';
      return;
    }
    var cat = opt.getAttribute('data-category') || '';
    categoryInput.value = cat;
  }

  itemSelect.addEventListener('change', updateCategory);
  updateCategory();
});
</script>

</body>
</html>