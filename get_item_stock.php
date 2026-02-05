<?php
// AJAX endpoint: returns current stock level for an item
header('Content-Type: application/json');
session_start();
include('assets/inc/config.php');
include('assets/inc/stock_functions.php');

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if($item_id <= 0){
    echo json_encode(['success'=>false,'message'=>'Invalid item']);
    exit;
}

$current = get_item_current_stock($item_id);
echo json_encode(['success'=>true,'current_stock'=>$current]);
exit;
?>
