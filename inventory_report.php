<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
include('assets/inc/stock_functions.php');
check_login();
?>
<?php include("assets/inc/head.php"); ?>
<body>
<?php include("assets/inc/nav.php"); ?>
<?php include("assets/inc/sidebar_admin.php"); ?>

<div class="content-page">
<div class="content container">
  <h3>Inventory Stock Report</h3>
  <div class="card-box">
    <div class="table-responsive">
      <table class="table table-bordered">
        <thead><tr><th>Item</th><th>Category</th><th>Unit/Quantity</th><th>Current Stock</th><th>Total Issued</th><th>Last Updated</th><th>Packs Equivalent</th></tr></thead>
        <tbody>
        <?php
        // Detect item name/unit columns
        $has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
        $item_col = ($has_item_name_col && $has_item_name_col->num_rows) ? 'item_name' : 'name';
        $has_unit_measure = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit_measure'");
        $unit_col = ($has_unit_measure && $has_unit_measure->num_rows) ? 'unit_measure' : 'unit';

        // One row per (item, category) so stock/issued reflect that category alone
        $rows = [];
        $items_res = $mysqli->query("SELECT item_id, `$item_col` AS item_name, `$unit_col` AS unit FROM items ORDER BY `$item_col`");
        while($it = $items_res->fetch_assoc()){
          $item_id = (int)$it['item_id'];
          $cats_res = $mysqli->query("SELECT c.category_id, c.name FROM item_categories ic JOIN categories c ON c.category_id = ic.category_id WHERE ic.item_id = $item_id ORDER BY c.name");
          $cats = $cats_res ? $cats_res->fetch_all(MYSQLI_ASSOC) : [];

          $build_row = function($category_id, $category_label) use ($item_id, $it, $mysqli) {
            $where_cat = $category_id === null ? "category_id IS NULL" : "category_id = " . (int)$category_id;
            $stock = (float)$mysqli->query("SELECT COALESCE(SUM(qty_change),0) AS s FROM stock_transactions WHERE item_id = $item_id AND $where_cat")->fetch_assoc()['s'];
            $issued = (float)$mysqli->query("SELECT COALESCE(SUM(-qty_change),0) AS s FROM stock_transactions WHERE item_id = $item_id AND $where_cat AND qty_change < 0")->fetch_assoc()['s'];
            $lu = $mysqli->query("SELECT MAX(created_at) AS lu FROM stock_transactions WHERE item_id = $item_id AND $where_cat")->fetch_assoc()['lu'];
            return ['item_name' => $it['item_name'], 'category' => $category_label, 'unit_measure' => $it['unit'], 'stock' => $stock, 'total_issued' => $issued, 'last_updated' => $lu];
          };

          if(empty($cats)){
            $rows[] = $build_row(null, '');
          } else {
            foreach($cats as $cat){
              $rows[] = $build_row((int)$cat['category_id'], $cat['name']);
            }
            $uncat = (float)$mysqli->query("SELECT COALESCE(SUM(qty_change),0) AS s FROM stock_transactions WHERE item_id = $item_id AND category_id IS NULL")->fetch_assoc()['s'];
            if($uncat != 0){
              $rows[] = $build_row(null, '(Uncategorized)');
            }
          }
        }

        foreach($rows as $r){
          // Remove decimal points for whole numbers
          $stock = (float)$r['stock'];
          $stock_display = number_format($stock, 0);
          $issued_display = number_format($r['total_issued'], 0);

          $base_lower = strtolower(trim($r['item_name']));

          // Normalize units for key items and compute packs equivalent
          $unit_display = $r['unit_measure'];
          $packs_equiv = 0.0;
          if($base_lower === 'paper'){
            $unit_display = 'ream';
            if($stock > 0){
              $packs_equiv = $stock / 5.0; // 5 reams per pack
            }
          } elseif($base_lower === 'biro'){
            $unit_display = 'pcs';
            if($stock > 0){
              $packs_equiv = $stock / 50.0; // 50 pcs per pack
            }
          }

          echo "<tr>";
          echo "<td>".htmlentities($r['item_name'])."</td>";
          echo "<td>".htmlentities($r['category'])."</td>";
          echo "<td>".htmlentities($unit_display)."</td>";
          echo "<td>".$stock_display."</td>";
          echo "<td>".$issued_display."</td>";
          echo "<td>".($r['last_updated'] ? htmlentities($r['last_updated']) : 'N/A')."</td>";
          echo "<td>".number_format($packs_equiv, 0)."</td>";
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