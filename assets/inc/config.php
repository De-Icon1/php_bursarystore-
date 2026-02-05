<?php
$dbuser="root";
$dbpass="";
$host="localhost";
$db="bursarystore";
$mysqli = new mysqli($host, $dbuser, $dbpass, $db);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}
?>
