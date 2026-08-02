<?php
require 'assets/inc/config.php';
$res = $mysqli->query("SELECT i.item_id, i.name AS item_name FROM items i ORDER BY i.name LIMIT 1");
if($res){
    $r=$res->fetch_assoc();
    echo 'sample: '.($r['item_name'] ?? '');
} else {
    echo 'query failed: '.$mysqli->error;
}
$mysqli->close();
