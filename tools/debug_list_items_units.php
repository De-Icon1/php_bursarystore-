<?php
// Debug listing for items and units
// Place in tools/ and open in browser: http://localhost/bursary/tools/debug_list_items_units.php

require_once __DIR__ . '/../assets/inc/config.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['status' => 'ok', 'items' => [], 'units' => [], 'errors' => []];

try {
    $res = $mysqli->query("SELECT * FROM items LIMIT 500");
    if($res){
        while($r = $res->fetch_assoc()) $out['items'][] = $r;
    } else {
        $out['errors'][] = 'items query failed: ' . $mysqli->error;
    }

    $ures = $mysqli->query("SELECT * FROM units LIMIT 500");
    if($ures){
        while($ur = $ures->fetch_assoc()) $out['units'][] = $ur;
    } else {
        $out['errors'][] = 'units query failed: ' . $mysqli->error;
    }
} catch (Throwable $e) {
    $out['status'] = 'error';
    $out['errors'][] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
