<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
include('assets/inc/stock_functions.php');
check_login();

// Optional filter by item name, e.g. "Paper"
$selected_group = isset($_GET['group']) ? trim($_GET['group']) : '';

// Detect item name/unit columns
$has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'");
$has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
$item_col = ($has_item_name_col && $has_item_name_col->num_rows) ? 'item_name' : 'name';

$has_unit_measure = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit_measure'");
$unit_col = ($has_unit_measure && $has_unit_measure->num_rows) ? 'unit_measure' : 'unit';

// Each item is now its own group; categories are shown as rows (variants) within it.
$groups = [];
$group_totals = [];

$items_res = $mysqli->query("SELECT item_id, `$item_col` AS item_name, `$unit_col` AS unit FROM items ORDER BY `$item_col`");
while($it = $items_res->fetch_assoc()){
  $item_id = (int)$it['item_id'];
  $group_name = $it['item_name'];

  $cats_res = $mysqli->query("SELECT c.category_id, c.name FROM item_categories ic JOIN categories c ON c.category_id = ic.category_id WHERE ic.item_id = $item_id ORDER BY c.name");
  $cats = $cats_res ? $cats_res->fetch_all(MYSQLI_ASSOC) : [];

  $rows = [];
  if(empty($cats)){
    $stock = get_item_current_stock($item_id);
    $lu = $mysqli->query("SELECT MAX(created_at) AS lu FROM stock_transactions WHERE item_id = $item_id")->fetch_assoc()['lu'];
    $rows[] = ['item_name' => $it['item_name'], 'category' => '—', 'unit' => $it['unit'], 'stock' => $stock, 'last_updated' => $lu];
  } else {
    foreach($cats as $cat){
      $cid = (int)$cat['category_id'];
      $stock = get_item_current_stock($item_id, $cid);
      $lu = $mysqli->query("SELECT MAX(created_at) AS lu FROM stock_transactions WHERE item_id = $item_id AND category_id = $cid")->fetch_assoc()['lu'];
      $rows[] = ['item_name' => $it['item_name'], 'category' => $cat['name'], 'unit' => $it['unit'], 'stock' => $stock, 'last_updated' => $lu];
    }
    // Stock recorded before a category was linked (category_id NULL) still needs to be shown
    $uncat = (int)$mysqli->query("SELECT COALESCE(SUM(qty_change),0) AS s FROM stock_transactions WHERE item_id = $item_id AND category_id IS NULL")->fetch_assoc()['s'];
    if($uncat != 0){
      $lu = $mysqli->query("SELECT MAX(created_at) AS lu FROM stock_transactions WHERE item_id = $item_id AND category_id IS NULL")->fetch_assoc()['lu'];
      $rows[] = ['item_name' => $it['item_name'], 'category' => '(Uncategorized)', 'unit' => $it['unit'], 'stock' => $uncat, 'last_updated' => $lu];
    }
  }

  $groups[$group_name] = $rows;
  $group_totals[$group_name] = array_sum(array_column($rows, 'stock'));
}

ksort($groups);

// If filter not set, default to first group (e.g., Paper) when available
if($selected_group === '' && !empty($groups)){
  $keys = array_keys($groups);
  $selected_group = $keys[0];
}

?>
<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<div class="content-page">
  <div class="content container">
    <h3>Grouped Inventory Report</h3>
    <p class="text-muted">
      View items grouped by base name (for example, all Paper sizes together, or toner variants by printer model).
    </p>

    <div class="card-box">
      <form method="get" class="form-inline mb-3">
        <label class="mr-2">Item group</label>
        <select name="group" class="form-control mr-2" onchange="this.form.submit()">
          <?php foreach($groups as $gName => $items): ?>
            <option value="<?= htmlspecialchars($gName) ?>" <?= $gName === $selected_group ? 'selected' : '' ?>>
              <?= htmlspecialchars($gName) ?> (<?= count($items) ?> variants, total stock: <?= number_format($group_totals[$gName],0) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-primary btn-sm">Filter</button></noscript>
      </form>

      <?php if($selected_group && isset($groups[$selected_group])): ?>
        <h5 class="mb-3">Group: <?= htmlspecialchars($selected_group) ?></h5>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead>
            <tr>
              <th>Item Variant</th>
              <th>Category / Size</th>
              <th>Unit</th>
              <th>Current Stock</th>
              <th>Last Updated</th>
              <th>Packs Equivalent</th>
            </tr>
            </thead>
            <tbody>
            <?php
              $base_lower = strtolower(trim($selected_group));
              $total_packs = 0;
              foreach($groups[$selected_group] as $row):
                $packs_equiv = 0;
                if($base_lower === 'paper'){
                  // 5 reams make 1 pack
                  $packs_equiv = ((float)$row['stock']) / 5.0;
                } elseif($base_lower === 'biro'){
                  // 50 pcs make 1 pack
                  $packs_equiv = ((float)$row['stock']) / 50.0;
                }
                $total_packs += $packs_equiv;
            ?>
              <tr>
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['unit']) ?></td>
                <td><?= number_format($row['stock'], 0) ?></td>
                <td><?= $row['last_updated'] ? htmlspecialchars($row['last_updated']) : 'N/A' ?></td>
                <td><?= number_format($packs_equiv, 0) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php if($base_lower === 'paper' || $base_lower === 'biro'): ?>
            <p class="mt-2"><strong>Total packs equivalent:</strong> <?= number_format($total_packs, 0) ?> pack(s)</p>
            <p class="text-muted mb-0">
              Conversion used: <?= $base_lower === 'paper' ? '5 reams per pack' : '50 pcs per pack'; ?>.
            </p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0">No items found for this group.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include("assets/inc/footer.php"); ?>

</body>
</html>
