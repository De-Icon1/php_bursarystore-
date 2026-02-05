<?php
require_once __DIR__ . '/../assets/inc/config.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['status'=>'ok','columns'=>[], 'create'=>null, 'error'=>''];
$res = $mysqli->query("SHOW COLUMNS FROM stock_issues");
if($res){
    while($r = $res->fetch_assoc()) $out['columns'][] = $r;
    $cr = $mysqli->query("SHOW CREATE TABLE stock_issues");
    if($cr){
        $row = $cr->fetch_assoc();
        $out['create'] = $row['Create Table'] ?? $row['Create Table'];
    }
} else {
    $out['status'] = 'error';
    $out['error'] = $mysqli->error;
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
