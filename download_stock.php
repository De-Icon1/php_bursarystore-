<?php
// Export current stock to an Excel-friendly (tab-separated) file.
// This script is schema-aware: it works whether the app uses the
// newer stock_transactions model or an older stock_balance table.

session_start();
include('assets/inc/config.php');
include('assets/inc/checklogins.php');
check_login();

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=stock_report_".date('Ymd').".xls");

// Detect item name column (item_name vs name)
$has_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'name'");
$has_item_name_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'");
if($has_item_name_col && $has_item_name_col->num_rows){
    $item_col_select = "it.item_name";
} elseif($has_name_col && $has_name_col->num_rows){
    $item_col_select = "it.name";
} else {
    $item_col_select = "'Item'"; // fallback literal
}

// Detect unit column (unit vs unit_measure)
$has_unit_measure = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit_measure'");
$has_unit = $mysqli->query("SHOW COLUMNS FROM items LIKE 'unit'");
if($has_unit_measure && $has_unit_measure->num_rows){
    $unit_col = "it.unit_measure";
} elseif($has_unit && $has_unit->num_rows){
    $unit_col = "it.unit";
} else {
    $unit_col = "''";
}

// Category: prefer categories.name if categories table + category_id exist
$category_expr = "it.category";
$joins = "";
$has_categories_tbl = $mysqli->query("SHOW TABLES LIKE 'categories'");
$has_category_id_col = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'");
if($has_categories_tbl && $has_categories_tbl->num_rows && $has_category_id_col && $has_category_id_col->num_rows){
    $category_expr = "COALESCE(c.name,it.category)";
    $joins .= " LEFT JOIN categories c ON it.category_id = c.category_id ";
}

// Decide how to compute stock and last updated
$has_stock_balance = $mysqli->query("SHOW TABLES LIKE 'stock_balance'");
$has_tx = $mysqli->query("SHOW TABLES LIKE 'stock_transactions'");

if($has_stock_balance && $has_stock_balance->num_rows){
    // Legacy stock_balance table available
    $sql = "SELECT {$item_col_select} AS item_name,
                   {$category_expr} AS category,
                   {$unit_col} AS unit,
                   COALESCE(sb.quantity,0) AS qty,
                   sb.last_updated
            FROM items it
            {$joins}
            LEFT JOIN stock_balance sb ON it.item_id = sb.item_id
            ORDER BY item_name";
} elseif($has_tx && $has_tx->num_rows){
    // Use stock_transactions to compute balance and last updated
    $sql = "SELECT it.item_id,
                   {$item_col_select} AS item_name,
                   {$category_expr} AS category,
                   {$unit_col} AS unit,
                   COALESCE((SELECT SUM(qty_change) FROM stock_transactions st WHERE st.item_id = it.item_id),0) AS qty,
                   (SELECT MAX(created_at) FROM stock_transactions st2 WHERE st2.item_id = it.item_id) AS last_updated
            FROM items it
            {$joins}
            ORDER BY item_name";
} else {
    // No balance or transactions table; export items with zero stock
    $sql = "SELECT {$item_col_select} AS item_name,
                   {$category_expr} AS category,
                   {$unit_col} AS unit,
                   0 AS qty,
                   NULL AS last_updated
            FROM items it
            {$joins}
            ORDER BY item_name";
}

echo "Item\tCategory\tUnit\tQuantity\tLast Updated\n";

if($res = $mysqli->query($sql)){
    while($r = $res->fetch_assoc()){
        $item = isset($r['item_name']) ? $r['item_name'] : '';
        $cat = isset($r['category']) ? $r['category'] : '';
        $unit = isset($r['unit']) ? $r['unit'] : '';
        $qty = isset($r['qty']) ? $r['qty'] : 0;
        $last = isset($r['last_updated']) && $r['last_updated'] ? $r['last_updated'] : '';

        // Basic sanitisation for tab-separated export
        $item = str_replace(["\r","\n","\t"],' ',$item);
        $cat  = str_replace(["\r","\n","\t"],' ',$cat);
        $unit = str_replace(["\r","\n","\t"],' ',$unit);
        $last = str_replace(["\r","\n","\t"],' ',$last);

        echo $item."\t".$cat."\t".$unit."\t".$qty."\t".$last."\n";
    }
}
exit;