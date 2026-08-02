<?php
session_start();
require_once 'assets/inc/config.php';
require_once 'assets/inc/checklogins.php';
require_once 'assets/inc/stock_functions.php';
check_login();

$err = '';
$success = '';

// Load items for dropdown first
$items = [];
$res = $mysqli->query("SELECT * FROM items");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id = $row['item_id'] ?? ($row['id'] ?? null);
        if (!$id) continue;

        $display = !empty($row['item_name']) ? $row['item_name'] : ($row['name'] ?? '');

        $items[] = [
            'item_id'  => $id,
            'label'    => $display,
        ];
    }
    $res->close();
}

// Check for junction table
$has_item_categories = false;
$check_junc = $mysqli->query("SHOW TABLES LIKE 'item_categories'");
$has_item_categories = $check_junc && $check_junc->num_rows > 0;

// Check if categories table exists (for fallback)
$has_categories = false;
$check_cats = $mysqli->query("SHOW TABLES LIKE 'categories'");
$has_categories = $check_cats && $check_cats->num_rows > 0;

// Build item → categories map ({id, name} pairs) using the junction table
$item_category_map = [];
if ($has_item_categories) {
    $junc_res = $mysqli->query("
        SELECT ic.item_id, c.category_id, c.name AS category
        FROM item_categories ic
        JOIN categories c ON ic.category_id = c.category_id
        ORDER BY c.name
    ");
    if ($junc_res) {
        while ($row = $junc_res->fetch_assoc()) {
            $item_id = (int)$row['item_id'];
            if (!isset($item_category_map[$item_id])) {
                $item_category_map[$item_id] = [];
            }
            $item_category_map[$item_id][] = ['id' => (int)$row['category_id'], 'name' => $row['category']];
        }
        $junc_res->close();
    }
} else {
    // Fallback: use the category column directly from items
    // Detect which category column exists
    $has_cat_id_col = false;
    $chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
    if ($chk) $has_cat_id_col = $chk->num_rows > 0;
    
    $has_cat_col = false;
    $chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category'");
    if ($chk) $has_cat_col = $chk->num_rows > 0;

    $has_name_col = false;
    $chk = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'");
    if ($chk) $has_name_col = $chk->num_rows > 0;
    
    foreach ($items as $it) {
        $id = $it['item_id'];
        static $cat_cache = [];
        if (!isset($cat_cache[$id])) {
            $cat_name = '';
            if ($has_cat_id_col && $has_categories) {
                // Try to get category name via foreign key
                $stmt = $mysqli->prepare("SELECT c.name FROM items i LEFT JOIN categories c ON i.category_id = c.category_id WHERE i.item_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->bind_result($cat_name);
                    $stmt->fetch();
                    $stmt->close();
                }
            } elseif ($has_cat_col) {
                // Direct text category column
                $stmt = $mysqli->prepare("SELECT category FROM items WHERE item_id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->bind_result($cat_name);
                    $stmt->fetch();
                    $stmt->close();
                }
            }
            $cat_cache[$id] = $cat_name ?: '';
        }
        if (!empty($cat_cache[$id])) {
            $item_category_map[$id][] = ['id' => 0, 'name' => $cat_cache[$id]];
        }
    }
}

// ✅ Process form
if (isset($_POST['issue'])) {
    $item_ids   = array_map('intval', $_POST['item_ids'] ?? []);
    $categories = $_POST['categories'] ?? [];
    $quantities = array_map('intval', $_POST['quantity'] ?? []);
    $unit       = trim($_POST['unit'] ?? '');
    $purpose    = trim($_POST['purpose'] ?? '');
    $collector_id = trim($_POST['collector_id'] ?? '');
    $issued_by  = $_SESSION['username'] ?? ($_SESSION['full_name'] ?? 'system');
    $issued_by_id = (int)($_SESSION['user_id'] ?? 0);

        if (empty($item_ids)) {
        $err = 'Please select one or more items to issue.';
    } elseif ($unit === '') {
        $err = 'Please enter the unit to issue to.';
    } elseif ($collector_id === '') {
        $err = 'Please enter the staff ID for the collector.';
    } else {
        // Process each item issue
        $mysqli->begin_transaction();
        try {
            for ($i = 0; $i < count($item_ids); $i++) {
                $iid = $item_ids[$i];
                $cid = isset($categories[$i]) ? (int)$categories[$i] : 0;
                $cat_id = $cid > 0 ? $cid : null;
                $qty = $quantities[$i] ?? 0;

                if ($iid <= 0 || $qty <= 0) continue;

                // Insert into stock_issues
                $stmt = $mysqli->prepare("INSERT INTO stock_issues (item_id, category_id, unit, quantity, issued_by, purpose) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception('Prepare stock_issues failed: ' . $mysqli->error);
                $stmt->bind_param('iisiss', $iid, $cat_id, $unit, $qty, $issued_by, $purpose);
                if (!$stmt->execute()) throw new Exception('Execute stock_issues failed: ' . $stmt->error);
                $stmt->close();

                // Also insert into stock_transactions as a negative change
                $tx_note = "Issued to {$unit}";
                $stmt2 = $mysqli->prepare("INSERT INTO stock_transactions (item_id, category_id, qty_change, tx_type, reference_id, user_id, note) VALUES (?, ?, ?, 'dispatch', ?, ?, ?)");
                if ($stmt2) {
                    $neg_qty = -$qty;
                    $ref_id = $issued_by_id;
                    $stmt2->bind_param('iiiiss', $iid, $cat_id, $neg_qty, $ref_id, $issued_by_id, $tx_note);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
            $mysqli->commit();
            $success = 'Items issued successfully!';
        } catch (Exception $e) {
            $mysqli->rollback();
            $err = 'Error issuing items: ' . $e->getMessage();
        }
    }
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
          <label>Items</label>
          <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
              <thead>
                <tr>
                                    <th>Item</th>
                  <th>Category (select after item)</th>
                  <th style="width: 15%;">Quantity</th>
                  <th style="width: 10%;">Action</th>
                </tr>
              </thead>
              <tbody id="issue-items-body">
                <tr>
                  <td>
                    <select name="item_ids[]" class="form-control item-select" required>
                      <option value="">-- Select Item --</option>
                      <?php foreach ($items as $it): ?>
                        <option value="<?php echo (int)$it['item_id']; ?>">
                          <?php echo htmlentities($it['label']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <select name="categories[]" class="form-control category-select" required>
                      <option value="">-- Select Category --</option>
                      <!-- Categories will be populated dynamically -->
                    </select>
                  </td>
                  <td>
                    <input type="number" name="quantity[]" class="form-control" min="0" step="1" placeholder="0" required>
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-row">Remove</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <button type="button" id="add-row" class="btn btn-secondary btn-sm mt-2">+ Add Another Item</button>
        </div>

        <div class="form-group">
          <label>Collector Staff ID</label>
          <input type="text" name="collector_id" class="form-control" placeholder="Enter staff ID collecting the items" required>
        </div>

        <div class="form-group">
          <label>Issue To Unit</label>
          <input type="text" name="unit" class="form-control" placeholder="Enter unit to issue items to" required>
        </div>

        <div class="form-group">
          <label>Purpose</label>
          <input type="text" name="purpose" class="form-control" placeholder="Enter purpose of issue (optional)">
        </div>

        <button type="submit" name="issue" class="btn btn-primary">Issue Items</button>
      </form>
    </div>
  </div>
</div>

<script>
  const itemCategoryMap = <?php echo json_encode($item_category_map); ?>;

  document.addEventListener('DOMContentLoaded', function() {
    const addRowBtn = document.getElementById('add-row');
    const tbody = document.getElementById('issue-items-body');

    addRowBtn.addEventListener('click', function() {
      const firstRow = tbody.querySelector('tr');
      const newRow = firstRow.cloneNode(true);
      newRow.querySelectorAll('select, input').forEach(el => el.value = '');
      tbody.appendChild(newRow);
    });

    tbody.addEventListener('click', function(e) {
      if (e.target.classList.contains('remove-row')) {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 1) {
          e.target.closest('tr').remove();
        } else {
          alert('You must keep at least one item row.');
        }
      }
    });

    tbody.addEventListener('change', function(e) {
      if (e.target.classList.contains('item-select')) {
        const itemId = e.target.value;
        const categorySelect = e.target.closest('tr').querySelector('.category-select');

        // Clear existing options
        categorySelect.innerHTML = '<option value="">-- Select Category --</option>';

        if (itemCategoryMap[itemId]) {
          itemCategoryMap[itemId].forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            categorySelect.appendChild(opt);
          });
        }
      }
    });
  });
</script>
</body>
</html>
