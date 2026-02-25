<?php

$db_host = 'localhost';
$db_port = '3306'; 
$db_name = 'ocrsambojakuala';
$db_user = 'root'; 
$db_pass = ''; 


$db = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

// Cek koneksi
if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . mysqli_connect_error()]);
    exit;
}
?>