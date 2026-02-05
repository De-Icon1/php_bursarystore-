<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
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
        // Build an inventory stock report using stock_transactions + stock_issues
        // Detect item name column
        $has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'");
        $has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
        if($has_name_col && $has_name_col->num_rows && $has_item_name_col && $has_item_name_col->num_rows){
          $item_col_select = "COALESCE(it.item_name,it.name)";
        } elseif($has_item_name_col && $has_item_name_col->num_rows){
          $item_col_select = "it.item_name";
        } elseif($has_name_col && $has_name_col->num_rows){
          $item_col_select = "it.name";
        } else {
          $item_col_select = "'Item'";
        }

        // Category: prefer categories.name if category_id + categories table exist
        $category_expr = "it.category";
        $joins = "";
        $has_categories_tbl = $mysqli->query("SHOW TABLES LIKE 'categories'");
        $has_category_id_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
        if($has_categories_tbl && $has_categories_tbl->num_rows && $has_category_id_col && $has_category_id_col->num_rows){
          $category_expr = "COALESCE(c.name,it.category)";
          $joins .= " LEFT JOIN categories c ON it.category_id = c.category_id ";
        }

        // Use stock_transactions where available to compute stock, issued, last updated
        $has_tx = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'");
        if($has_tx && $has_tx->num_rows > 0){
          $sql = "SELECT it.item_id,
                         {$item_col_select} AS item_name,
                         {$category_expr} AS category,
                         it.unit_measure,
                         COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS stock,
                         COALESCE((SELECT SUM(-qty_change) FROM stock_transactions st2 WHERE st2.item_id = it.item_id AND st2.qty_change < 0),0) AS total_issued,
                         (SELECT MAX(created_at) FROM stock_transactions st3 WHERE st3.item_id = it.item_id) AS last_updated
                  FROM items it
                  {$joins}
                  ORDER BY item_name";
        } else {
          // Fallback: no transactions table yet; show zero stock/issued
          $sql = "SELECT it.item_id,
                         {$item_col_select} AS item_name,
                         {$category_expr} AS category,
                         it.unit_measure,
                         0 AS stock,
                         0 AS total_issued,
                         NULL AS last_updated
                  FROM items it
                  {$joins}
                  ORDER BY item_name";
        }

        $res = $mysqli->query($sql);
        while($r = $res->fetch_assoc()){
          // Remove decimal points for whole numbers
          $stock = (float)$r['stock'];
          $stock_display = number_format($stock, 0);
          $issued_display = number_format($r['total_issued'], 0);

          // Derive base name (before any " (Variant)")
          $name = $r['item_name'];
          $base_name = $name;
          $pos = strpos($name, ' (');
          if($pos !== false){
            $base_name = substr($name, 0, $pos);
          }
          $base_lower = strtolower(trim($base_name));

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