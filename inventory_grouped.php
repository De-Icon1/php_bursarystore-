<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

// Optional filter by base item group like "Paper" or "HP Toner 85A"
$selected_group = isset($_GET['group']) ? trim($_GET['group']) : '';

// Detect item name column
$has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'");
$has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
if($has_item_name_col && $has_item_name_col->num_rows){
  $item_col_select = "it.item_name";
} elseif($has_name_col && $has_name_col->num_rows){
  $item_col_select = "it.name";
} else {
  $item_col_select = "'Item'";
}

// Build groups by base name (strip trailing " (Variant)") and compute pack-equivalents
// Category: prefer categories.name if category_id + categories table exist
$category_expr = "it.category";
$joins = "";
$has_categories_tbl = $mysqli->query("SHOW TABLES LIKE 'categories'");
$has_category_id_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
if($has_categories_tbl && $has_categories_tbl->num_rows && $has_category_id_col && $has_category_id_col->num_rows){
  $category_expr = "COALESCE(c.name,it.category)";
  $joins .= " LEFT JOIN categories c ON it.category_id = c.category_id ";
}

// Detect unit column
$has_unit_measure = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit_measure'");
$has_unit = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit'");
if($has_unit_measure && $has_unit_measure->num_rows){
  $unit_col = "it.unit_measure";
} elseif($has_unit && $has_unit->num_rows){
  $unit_col = "it.unit";
} else {
  $unit_col = "''";
}

// Decide how to compute stock
$has_tx = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'");

if($has_tx && $has_tx->num_rows > 0){
  $sql = "SELECT it.item_id,
                 {$item_col_select} AS item_name,
                 {$category_expr} AS category,
                 {$unit_col} AS unit,
                 COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS stock,
                 (SELECT MAX(created_at) FROM stock_transactions st2 WHERE st2.item_id = it.item_id) AS last_updated
          FROM items it
          {$joins}
          ORDER BY item_name";
} else {
  $sql = "SELECT it.item_id,
                 {$item_col_select} AS item_name,
                 {$category_expr} AS category,
                 {$unit_col} AS unit,
                 0 AS stock,
                 NULL AS last_updated
          FROM items it
          {$joins}
          ORDER BY item_name";
}

$groups = [];
$group_totals = [];

if($res = $mysqli->query($sql)){
  while($row = $res->fetch_assoc()){
    $name = $row['item_name'];
    $group_name = $name;
    // If name looks like "Paper (A4)", use "Paper" as group label
    $pos = strpos($name, ' (');
    if($pos !== false){
      $group_name = substr($name, 0, $pos);
    }

    // Initialize group bucket if needed
    if(!isset($groups[$group_name])){
      $groups[$group_name] = [];
      $group_totals[$group_name] = 0;
    }
    $groups[$group_name][] = $row;
    $group_totals[$group_name] += (float)$row['stock'];
  }
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
