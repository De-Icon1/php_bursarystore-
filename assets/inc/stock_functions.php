<?php
// Helper functions for stock operations — use prepared statements and transactions.
// Depends on a global `$mysqli` from `assets/inc/config.php` and `log_action()` in `assets/inc/functions.php`.

/**
 * Get current stock level for an item (sum of qty_change in stock_transactions)
 */
function get_item_current_stock($item_id)
{
    global $mysqli;
    $qty = 0;
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(qty_change),0) FROM stock_transactions WHERE item_id = ?");
    if(!$stmt) return 0;
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $stmt->bind_result($qty);
    $stmt->fetch();
    $stmt->close();
    return (int)$qty;
}

/**
 * Receive stock: inserts a receipt, receipt item and a stock transaction.
 * Returns array: ['success'=>bool, 'message'=>string, 'receipt_id'=>int|null]
 */
function receive_stock($item_id, $quantity, $supplier = null, $received_by = null, $unit_cost = null, $reference = null, $note = null)
{
    global $mysqli;

    if($quantity <= 0) return ['success'=>false, 'message'=>'Quantity must be greater than zero'];

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO stock_receipts (supplier, received_by, note) VALUES (?, ?, ?)");
        if(!$stmt) throw new Exception('Prepare failed (stock_receipts)');
        $stmt->bind_param('sis', $supplier, $received_by, $note);
        $stmt->execute();
        $receipt_id = $stmt->insert_id;
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO receipt_items (receipt_id, item_id, quantity, unit_cost) VALUES (?, ?, ?, ?)");
        if(!$stmt) throw new Exception('Prepare failed (receipt_items)');
        // ensure unit_cost is float or null
        $uc = $unit_cost !== null ? (float)$unit_cost : null;
        $stmt->bind_param('iiid', $receipt_id, $item_id, $quantity, $uc);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO stock_transactions (item_id, qty_change, tx_type, reference_id, user_id, note) VALUES (?, ?, 'receive', ?, ?, ?)");
        if(!$stmt) throw new Exception('Prepare failed (stock_transactions)');
        $stmt->bind_param('iiiis', $item_id, $quantity, $receipt_id, $received_by, $note);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();

        if(function_exists('log_action') && $received_by) {
            log_action($received_by, "Received {$quantity} of item_id={$item_id} (receipt={$receipt_id})");
        }

        return ['success'=>true, 'message'=>'Stock received', 'receipt_id'=>$receipt_id];
    } catch(Exception $e) {
        $mysqli->rollback();
        return ['success'=>false, 'message'=>'Receive failed: '.$e->getMessage()];
    }
}

/**
 * Create a stock request from a unit. $items is an array of ['item_id'=>int,'quantity'=>int]
 */
function create_stock_request($unit_id, $requested_by = null, $items = [], $note = null)
{
    global $mysqli;
    if(empty($items)) return ['success'=>false, 'message'=>'No items provided'];

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO stock_requests (unit_id, requested_by, note) VALUES (?, ?, ?)");
        if(!$stmt) throw new Exception('Prepare failed (stock_requests)');
        $stmt->bind_param('iis', $unit_id, $requested_by, $note);
        $stmt->execute();
        $request_id = $stmt->insert_id;
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO request_items (request_id, item_id, quantity) VALUES (?, ?, ?)");
        if(!$stmt) throw new Exception('Prepare failed (request_items)');
        foreach($items as $it) {
            $iid = (int)$it['item_id'];
            $q = (int)$it['quantity'];
            $stmt->bind_param('iii', $request_id, $iid, $q);
            $stmt->execute();
        }
        $stmt->close();

        $mysqli->commit();
        if(function_exists('log_action') && $requested_by) log_action($requested_by, "Created stock request {$request_id} for unit {$unit_id}");
        return ['success'=>true, 'message'=>'Request created', 'request_id'=>$request_id];
    } catch(Exception $e) {
        $mysqli->rollback();
        return ['success'=>false, 'message'=>'Create request failed: '.$e->getMessage()];
    }
}

/**
 * Fulfill a request: checks stock and creates dispatch transactions reducing stock (qty_change negative).
 */
function fulfill_stock_request($request_id, $fulfilled_by = null)
{
    global $mysqli;

    // fetch request items
    $stmt = $mysqli->prepare("SELECT ri.item_id, ri.quantity, i.name FROM request_items ri JOIN items i ON ri.item_id = i.item_id WHERE ri.request_id = ?");
    if(!$stmt) return ['success'=>false, 'message'=>'Prepare failed'];
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if(empty($items)) return ['success'=>false, 'message'=>'Request has no items'];

    // Check stock availability
    foreach($items as $it) {
        $current = get_item_current_stock($it['item_id']);
        if($current < $it['quantity']) {
            return ['success'=>false, 'message'=>'Insufficient stock for '.$it['name']];
        }
    }

    // Perform dispatch
    $mysqli->begin_transaction();
    try {
        foreach($items as $it) {
            $stmt = $mysqli->prepare("INSERT INTO stock_transactions (item_id, qty_change, tx_type, reference_id, user_id, note) VALUES (?, ?, 'dispatch', ?, ?, ?)");
            if(!$stmt) throw new Exception('Prepare failed (dispatch insert)');
            $neg = -1 * (int)$it['quantity'];
            $note = 'Dispatch for request '.$request_id;
            $stmt->bind_param('iiiis', $it['item_id'], $neg, $request_id, $fulfilled_by, $note);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $mysqli->prepare("UPDATE stock_requests SET status = 'fulfilled', approved_at = NOW() WHERE request_id = ?");
        if(!$stmt) throw new Exception('Prepare failed (update request)');
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
        if(function_exists('log_action') && $fulfilled_by) log_action($fulfilled_by, "Fulfilled stock request {$request_id}");
        return ['success'=>true, 'message'=>'Request fulfilled'];
    } catch(Exception $e) {
        $mysqli->rollback();
        return ['success'=>false, 'message'=>'Fulfill failed: '.$e->getMessage()];
    }
}

?>
