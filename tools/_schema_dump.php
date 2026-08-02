<?php
require __DIR__ . '/../assets/inc/config.php';
$tables = ['items','categories','item_categories','stock_balance','stock_transactions','receipt_items','stock_receipts','stock_issues','stock_entries','inventory_thresholds','request_items'];
foreach ($tables as $t) {
    $res = $mysqli->query("SHOW TABLES LIKE '$t'");
    if (!$res || $res->num_rows === 0) { echo "=== $t : MISSING ===\n\n"; continue; }
    echo "=== $t ===\n";
    $cols = $mysqli->query("SHOW COLUMNS FROM $t");
    while ($c = $cols->fetch_assoc()) {
        echo "  {$c['Field']} ({$c['Type']})\n";
    }
    echo "\n";
}
