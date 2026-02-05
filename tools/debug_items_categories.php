<?php
// Debug listing for items and their categories
// Open in browser: http://localhost/bursary/tools/debug_items_categories.php

require_once __DIR__ . '/../assets/inc/config.php';
header('Content-Type: application/json; charset=utf-8');

$out = [
    'status' => 'ok',
    'items' => [],
    'errors' => [],
];

try {
    // Detect which item-name column exists
    $labelColumn = 'name';
    if ($colRes = $mysqli->query("SHOW COLUMNS FROM items LIKE 'item_name'")) {
        if ($colRes->num_rows > 0) {
            $labelColumn = 'item_name';
        }
        $colRes->close();
    }

    // Detect whether items has category_id
    $hasCategoryId = false;
    if ($catColRes = $mysqli->query("SHOW COLUMNS FROM items LIKE 'category_id'")) {
        if ($catColRes->num_rows > 0) {
            $hasCategoryId = true;
        }
        $catColRes->close();
    }

    if ($hasCategoryId) {
        $sql = "SELECT it.item_id, it.{$labelColumn} AS item_name, it.category_id, c.name AS category_name
                FROM items it
                LEFT JOIN categories c ON it.category_id = c.category_id
                ORDER BY it.{$labelColumn}";
    } else {
        $sql = "SELECT item_id, {$labelColumn} AS item_name, NULL AS category_id, NULL AS category_name
                FROM items
                ORDER BY {$labelColumn}";
    }

    $res = $mysqli->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out['items'][] = $row;
        }
        $res->close();
    } else {
        $out['errors'][] = 'items query failed: ' . $mysqli->error;
    }
} catch (Throwable $e) {
    $out['status'] = 'error';
    $out['errors'][] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
