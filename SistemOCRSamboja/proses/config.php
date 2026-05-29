<?php

$db_host = 'localhost';
$db_port = '3306';
$db_name = 'ocrsambojakuala';
$db_user = 'root';
$db_pass = '';

$db = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal.']);
    exit;
}

mysqli_set_charset($db, 'utf8mb4');
?>