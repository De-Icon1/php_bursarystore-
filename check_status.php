<?php
require 'assets/inc/config.php';

echo "=== CHECK TABLES ===\n\n";

$tables = ['stock_balance', 'stock_transactions', 'inventory_thresholds', 'items', 'stock_entries', 'stock_issues'];
foreach ($tables as $tbl) {
    $r = $mysqli->query("SHOW TABLES LIKE '$tbl'");
    $exists = $r && $r->num_rows > 0;
    echo "$tbl: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
    
    if ($exists) {
        $r2 = $mysqli->query("SELECT COUNT(*) AS cnt FROM $tbl");
        if ($r2) {
            $row = $r2->fetch_assoc();
            echo "  Rows: {$row['cnt']}\n";
        }
        // Show columns
        $cols = $mysqli->query("SHOW COLUMNS FROM $tbl");
        if ($cols) {
            $col_names = [];
            while ($c = $cols->fetch_assoc()) {
                $col_names[] = $c['Field'];
            }
            echo "  Columns: " . implode(', ', $col_names) . "\n";
        }
    }
}

echo "\n=== ITEMS TABLE ===\n";
$r = $mysqli->query("SELECT * FROM items");
while ($row = $r->fetch_assoc()) {
    print_r($row);
}

echo "\n=== STOCK TRANSACTIONS SAMPLE ===\n";
$r = $mysqli->query("SELECT * FROM stock_transactions LIMIT 10");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}

echo "\n=== STOCK BALANCE SAMPLE ===\n";
$r = $mysqli->query("SELECT * FROM stock_balance LIMIT 10");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}

echo "\n=== INVENTORY THRESHOLDS SAMPLE ===\n";
$r = $mysqli->query("SELECT * FROM inventory_thresholds LIMIT 10");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}
