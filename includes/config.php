<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "tricycle_booking";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: para hindi magulo ang output
date_default_timezone_set('Asia/Manila');
?>