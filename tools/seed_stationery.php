<?php
// Seeder: insert common stationery and toner items into `items` table.
// Usage: php tools/seed_stationery.php

require_once __DIR__ . '/../assets/inc/config.php';

$items = [
    ['sku'=>'A4-500','name'=>'A4 Paper Ream (500 sheets)','category'=>'Stationery','unit_measure'=>'ream','reorder_level'=>10],
    ['sku'=>'PEN-BP','name'=>'Ballpoint Pen (Blue)','category'=>'Stationery','unit_measure'=>'each','reorder_level'=>100],
    ['sku'=>'STPL-01','name'=>'Stapler (Standard)','category'=>'Stationery','unit_measure'=>'each','reorder_level'=>10],
    ['sku'=>'CLIP-01','name'=>'Paper Clips (100pcs)','category'=>'Stationery','unit_measure'=>'pack','reorder_level'=>20],
    ['sku'=>'TONER-85A','name'=>'HP Toner 85A (Black)','category'=>'Toner','unit_measure'=>'each','reorder_level'=>5],
    ['sku'=>'TONER-05A','name'=>'Canon Toner 05A (Black)','category'=>'Toner','unit_measure'=>'each','reorder_level'=>5],
];

// Detect columns in items table
$colsRes = $mysqli->query("SHOW COLUMNS FROM items");
$cols = [];
while($c = $colsRes->fetch_assoc()) $cols[] = $c['Field'];

$use_item_name = in_array('item_name', $cols);
$use_name = in_array('name', $cols);
$use_sku = in_array('sku', $cols);
$use_category = in_array('category', $cols);
$use_unit = in_array('unit', $cols) || in_array('unit_measure', $cols);
$use_reorder = in_array('reorder_level', $cols) || in_array('reorder', $cols);

foreach($items as $it){
    $fields = [];
    $placeholders = [];
    $values = [];

    if($use_sku){ $fields[] = 'sku'; $placeholders[]='?'; $values[] = $it['sku']; }
    if($use_item_name){ $fields[] = 'item_name'; $placeholders[]='?'; $values[] = $it['name']; }
    elseif($use_name){ $fields[] = 'name'; $placeholders[]='?'; $values[] = $it['name']; }
    if($use_category){ $fields[] = 'category'; $placeholders[]='?'; $values[] = $it['category']; }
    if(in_array('unit', $cols)){ $fields[] = 'unit'; $placeholders[]='?'; $values[] = $it['unit_measure']; }
    elseif(in_array('unit_measure', $cols)){ $fields[] = 'unit_measure'; $placeholders[]='?'; $values[] = $it['unit_measure']; }
    if($use_reorder){ $fields[] = in_array('reorder_level',$cols)?'reorder_level':'reorder'; $placeholders[]='?'; $values[] = $it['reorder_level']; }

    if(empty($fields)){
        echo "No compatible columns found in `items` table. Aborting.\n";
        exit(1);
    }

    $sql = "INSERT INTO items (".implode(',', $fields).") VALUES (".implode(',', $placeholders).")";
    $stmt = $mysqli->prepare($sql);
    if(!$stmt){ echo "Prepare failed: " . $mysqli->error . "\n"; continue; }

    // bind params dynamically (all strings/ints)
    $types = '';
    foreach($values as $v) $types .= is_int($v)?'i':'s';
    $bind_names[] = $types;
    $refs = [];
    foreach($values as $k => $v){ $refs[$k] = &$values[$k]; }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if($stmt->execute()){
        echo "Inserted item: {$it['name']} (id={$stmt->insert_id})\n";
    } else {
        echo "Insert failed for {$it['name']}: {$stmt->error}\n";
    }
    $stmt->close();
}

echo "Seeding complete.\n";
