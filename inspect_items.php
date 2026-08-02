<?php
require 'assets/inc/config.php';

echo "COLUMNS:\n";
$res = $mysqli->query("SHOW COLUMNS FROM items");
while($r = $res->fetch_assoc()){
    echo $r['Field'].' | '.$r['Type'].PHP_EOL;
}

echo "\nROW COUNT:\n";
$res = $mysqli->query("SELECT COUNT(*) AS cnt FROM items");
if($res){ $r = $res->fetch_assoc(); echo $r['cnt'].PHP_EOL; }

echo "\nSAMPLE ROWS (up to 10):\n";
$res = $mysqli->query("SELECT * FROM items LIMIT 10");
if($res){
    while($row = $res->fetch_assoc()){
        print_r($row);
        echo PHP_EOL;
    }
}
$mysqli->close();
