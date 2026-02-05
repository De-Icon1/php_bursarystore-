<?php
session_start();
include('assets/inc/config.php');
include('assets/inc/stock_functions.php');
include('assets/inc/checklogins.php');
// simple access: any logged-in user can view
if(!check_login()) { header('Location: index.php'); exit; }
// Load stationery and toner items by category, fallback to name matching
// Use a safe SELECT * and filter in PHP to avoid referencing non-existent columns in SQL
$items = [];
$res = $mysqli->query("SELECT * FROM items");
if($res){
  while($row = $res->fetch_assoc()){
    // determine id (support both item_id and id)
    $id = $row['item_id'] ?? $row['id'] ?? null;
    if(!$id) continue;

    // display name fallbacks
    $display = '';
    if(isset($row['item_name']) && $row['item_name'] !== null && $row['item_name'] !== '') $display = $row['item_name'];
    elseif(isset($row['name']) && $row['name'] !== null) $display = $row['name'];

    $category = $row['category'] ?? ($row['category_name'] ?? '');
    $unit = $row['unit_measure'] ?? ($row['unit'] ?? '');

    // Decide if this is stationery/toner by category or by matching keywords in the name
    $is_stationery = false;
    $catLower = strtolower(trim($category));
    // Category-based detection (case-insensitive, supports variants like "Office Stationery")
    if(in_array($catLower, ['stationery','toner'], true) ||
       strpos($catLower, 'stationery') !== false ||
       strpos($catLower, 'toner') !== false) {
      $is_stationery = true;
    }

    // Name-based detection, including biro and common stationery keywords
    $lower = strtolower($display);
    if(strpos($lower, 'paper') !== false ||
       strpos($lower, 'pen') !== false ||
       strpos($lower, 'biro') !== false ||
       strpos($lower, 'pencil') !== false ||
       strpos($lower, 'marker') !== false ||
       strpos($lower, 'toner') !== false) {
      $is_stationery = true;
    }

    // Normalize unit display for key items
    // Base name is everything before " (" if present, e.g. "Paper (A4)" -> "Paper"
    $base_name = $display;
    $pos = strpos($display, ' (');
    if($pos !== false){
      $base_name = substr($display, 0, $pos);
    }
    $base_lower = strtolower(trim($base_name));
    if($base_lower === 'paper'){
      $unit = 'ream';
    } elseif($base_lower === 'biro'){
      $unit = 'pcs';
    }

    if($is_stationery){
      $items[] = [
        'item_id' => $id,
        'item_name' => $display,
        'category' => $category,
        'unit' => $unit
      ];
    }
  }
}

?>
<?php include('assets/inc/head.php'); ?>
<body>
<?php include('assets/inc/nav.php'); ?>
<?php include('assets/inc/sidebar_admin.php'); ?>

<div class="content-page"><div class="content container">
  <h4>Stationery Store</h4>
  <p class="text-muted">List of stationery items and toners managed by the bursary store.</p>

  <div class="card-box">
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>Item</th><th>Category</th><th>Unit</th><th>Current Stock</th><th>Packs Equivalent</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(empty($items)){ echo '<tr><td colspan="6" class="text-center text-muted">No stationery items found</td></tr>'; }
        foreach($items as $it){
            $stock = get_item_current_stock($it['item_id']);

            // Determine base name (before any " (Variant)")
            $name = $it['item_name'];
            $base_name = $name;
            $pos = strpos($name, ' (');
            if($pos !== false){
              $base_name = substr($name, 0, $pos);
            }
            $base_lower = strtolower(trim($base_name));

            // Compute packs equivalent
            $packs_equiv = 0.0;
            if($base_lower === 'paper'){
              if($stock > 0){
                $packs_equiv = $stock / 5.0; // 5 reams per pack
              }
            } elseif($base_lower === 'biro'){
              if($stock > 0){
                $packs_equiv = $stock / 50.0; // 50 pcs per pack
              }
            }

            echo '<tr>';
            echo '<td>'.htmlentities($it['item_name']).'</td>';
            echo '<td>'.htmlentities($it['category']).'</td>';
            echo '<td>'.htmlentities($it['unit']).'</td>';
            echo '<td>'.number_format($stock).'</td>';
            echo '<td>'.number_format($packs_equiv, 0).'</td>';
            echo '<td><a class="btn btn-sm btn-success" href="stock_receive.php?item_id='.intval($it['item_id']).'">Receive</a> <a class="btn btn-sm btn-primary" href="create_request.php">Request</a></td>';
            echo '</tr>';
        }
        ?>
        </tbody>
      </table>
    </div>
  </div>

</div></div>
<?php include('assets/inc/footer.php'); ?>
</body></html>
