<?php
session_start();
require_once 'config.php'; 

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Metode tidak diizinkan');
}


$log_id        = $_POST['log_id'] ?? null;
$nik_koreksi   = $_POST['nik_terkoreksi'] ?? null; 
$alasan        = $_POST['alasan'] ?? null;
$catatan       = $_POST['catatan'] ?? '';
$skor_percent  = $_POST['skor_koreksi'] ?? 100;


if (!$log_id || !$nik_koreksi || !$alasan) {
    
    header("Location: ../koreksi.php?id=$log_id&msg=data_tidak_lengkap");
    exit;
}


if (!preg_match('/^[0-9]{16}$/', $nik_koreksi)) {
    header("Location: ../koreksi.php?id=$log_id&msg=nik_invalid");
    exit;
}

$skor_decimal  = (float)$skor_percent / 100;
$catatan_final = "Alasan: $alasan. Catatan: $catatan";

$sql = "UPDATE log_ocr 
        SET nik_final = ?, 
            catatan_koreksi = ?, 
            status_proses = 'finalized', 
            skor_kepercayaan = ? 
        WHERE log_id = ?";

$stmt = mysqli_prepare($db, $sql);

if ($stmt) {
  
    mysqli_stmt_bind_param($stmt, "ssdi", $nik_koreksi, $catatan_final, $skor_decimal, $log_id);

if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
     
        header("Location: ../riwayat.php?msg=berhasil_edit&highlight_id=$log_id");
        exit;
    
    } else {
        // Gagal Eksekusi
        mysqli_stmt_close($stmt);
        header('Location: ../riwayat.php?msg=gagal');
        exit;
    }
} else {
  
    header('Location: ../riwayat.php?msg=gagal');
    exit;
}
?>